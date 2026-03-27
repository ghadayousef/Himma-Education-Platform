<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// التأكد من بدء الجلسة
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// محاكاة بيانات المستخدم إذا لم تكن موجودة
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['user_name'] = 'مستخدم تجريبي';
    $_SESSION['full_name'] = 'مستخدم تجريبي';
    $_SESSION['role'] = 'student';
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ملف لحفظ البيانات
$data_file = __DIR__ . '/chat_data.json';

// قراءة البيانات المحفوظة
function loadChatData() {
    global $data_file;
    
    if (!file_exists($data_file)) {
        return getDefaultData();
    }
    
    $content = file_get_contents($data_file);
    if ($content === false) {
        return getDefaultData();
    }
    
    // تنظيف المحتوى من أي أحرف غير مرغوب فيها
    $content = trim($content);
    $content = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $content);
    
    $data = json_decode($content, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('JSON Error: ' . json_last_error_msg());
        return getDefaultData();
    }
    
    return $data ?: getDefaultData();
}

function getDefaultData() {
    return [
        'conversations' => [],
        'messages' => [],
        'users' => [
            1 => ['id' => 1, 'name' => 'مستخدم تجريبي', 'role' => 'student'],
            2 => ['id' => 2, 'name' => 'د. احمد محمد', 'role' => 'teacher'],
            3 => ['id' => 3, 'name' => 'أ. فاطمة علي', 'role' => 'teacher'],
            4 => ['id' => 4, 'name' => 'سارة احمد', 'role' => 'student']
        ]
    ];
}

// حفظ البيانات
function saveChatData($data) {
    global $data_file;
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        error_log('JSON Encode Error: ' . json_last_error_msg());
        return false;
    }
    return file_put_contents($data_file, $json) !== false;
}

try {
    switch ($action) {
        case 'get_conversations':
            getConversations($user_id);
            break;
            
        case 'get_messages':
            $conversation_id = $_GET['conversation_id'] ?? 0;
            getMessages($conversation_id, $user_id);
            break;
            
        case 'send_message':
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('خطأ في تحليل البيانات المرسلة');
            }
            sendMessage($data, $user_id);
            break;
            
        case 'create_conversation':
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('خطأ في تحليل البيانات المرسلة');
            }
            createConversation($data, $user_id);
            break;
            
        case 'search_users':
            $query = $_GET['query'] ?? '';
            searchUsers($query, $user_id);
            break;
            
        case 'mark_read':
            $conversation_id = $_POST['conversation_id'] ?? 0;
            markMessagesAsRead($conversation_id, $user_id);
            break;
            
        case 'get_unread_counts':
            getUnreadCounts($user_id);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'إجراء غير صحيح'], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'خطأ في الخادم: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

function getConversations($user_id) {
    $data = loadChatData();
    $conversations = [];
    
    foreach ($data['conversations'] as $conv) {
        if (in_array($user_id, $conv['participants'])) {
            // البحث عن آخر رسالة
            $last_message = '';
            $last_message_time = '';
            $unread_count = 0;
            
            foreach ($data['messages'] as $msg) {
                if ($msg['conversation_id'] == $conv['id']) {
                    $last_message = $msg['message'];
                    $last_message_time = $msg['created_at'];
                    if ($msg['sender_id'] != $user_id && !$msg['is_read']) {
                        $unread_count++;
                    }
                }
            }
            
            // العثور على المستخدم الآخر
            $other_user_id = null;
            foreach ($conv['participants'] as $participant) {
                if ($participant != $user_id) {
                    $other_user_id = $participant;
                    break;
                }
            }
            
            $other_user = $data['users'][$other_user_id] ?? ['name' => 'مستخدم غير معروف', 'role' => 'student'];
            
            $conversations[] = [
                'id' => $conv['id'],
                'title' => $conv['title'] ?: $other_user['name'],
                'type' => $conv['type'],
                'other_user_name' => $other_user['name'],
                'other_user_type' => $other_user['role'],
                'last_message' => $last_message,
                'last_message_time' => $last_message_time,
                'last_message_formatted' => timeAgo($last_message_time),
                'unread_count' => $unread_count,
                'created_at' => $conv['created_at'],
                'updated_at' => $conv['updated_at']
            ];
        }
    }
    
    // ترتيب حسب آخر تحديث
    usort($conversations, function($a, $b) {
        return strtotime($b['updated_at']) - strtotime($a['updated_at']);
    });
    
    echo json_encode(['conversations' => $conversations], JSON_UNESCAPED_UNICODE);
}

function getMessages($conversation_id, $user_id) {
    $data = loadChatData();
    $messages = [];
    
    // التحقق من الصلاحية
    $has_access = false;
    foreach ($data['conversations'] as $conv) {
        if ($conv['id'] == $conversation_id && in_array($user_id, $conv['participants'])) {
            $has_access = true;
            break;
        }
    }
    
    if (!$has_access) {
        http_response_code(403);
        echo json_encode(['error' => 'غير مسموح بالوصول لهذه المحادثة'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    foreach ($data['messages'] as $msg) {
        if ($msg['conversation_id'] == $conversation_id) {
            $sender = $data['users'][$msg['sender_id']] ?? ['name' => 'مستخدم غير معروف'];
            $messages[] = [
                'id' => $msg['id'],
                'message' => $msg['message'],
                'sender_id' => $msg['sender_id'],
                'sender_name' => $sender['name'],
                'sender_full_name' => $sender['name'],
                'is_own' => ($msg['sender_id'] == $user_id),
                'is_read' => $msg['is_read'],
                'created_at' => $msg['created_at'],
                'formatted_time' => date('H:i', strtotime($msg['created_at']))
            ];
        }
    }
    
    // ترتيب حسب الوقت
    usort($messages, function($a, $b) {
        return strtotime($a['created_at']) - strtotime($b['created_at']);
    });
    
    echo json_encode(['messages' => $messages], JSON_UNESCAPED_UNICODE);
}

function sendMessage($input_data, $user_id) {
    $conversation_id = $input_data['conversation_id'] ?? 0;
    $message = trim($input_data['message'] ?? '');
    
    if (empty($message)) {
        http_response_code(400);
        echo json_encode(['error' => 'الرسالة فارغة'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $data = loadChatData();
    
    // التحقق من الصلاحية
    $has_access = false;
    foreach ($data['conversations'] as &$conv) {
        if ($conv['id'] == $conversation_id && in_array($user_id, $conv['participants'])) {
            $has_access = true;
            $conv['updated_at'] = date('Y-m-d H:i:s');
            break;
        }
    }
    
    if (!$has_access) {
        http_response_code(403);
        echo json_encode(['error' => 'غير مسموح بالوصول لهذه المحادثة'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // إضافة الرسالة
    $message_id = count($data['messages']) + 1;
    $data['messages'][] = [
        'id' => $message_id,
        'conversation_id' => $conversation_id,
        'sender_id' => $user_id,
        'message' => $message,
        'is_read' => false,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    if (!saveChatData($data)) {
        http_response_code(500);
        echo json_encode(['error' => 'خطأ في حفظ البيانات'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'message_id' => $message_id,
        'message' => 'تم إرسال الرسالة بنجاح'
    ], JSON_UNESCAPED_UNICODE);
}

function createConversation($input_data, $user_id) {
    $participant_id = $input_data['participant_id'] ?? 0;
    $title = $input_data['title'] ?? '';
    $type = $input_data['type'] ?? 'private';
    
    if (empty($participant_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'يجب تحديد المشارك'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $data = loadChatData();
    
    // التحقق من وجود محادثة سابقة
    foreach ($data['conversations'] as $conv) {
        if ($conv['type'] == 'private' && 
            in_array($user_id, $conv['participants']) && 
            in_array($participant_id, $conv['participants'])) {
            echo json_encode([
                'success' => true,
                'conversation_id' => $conv['id'],
                'message' => 'المحادثة موجودة مسبقاً'
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
    }
    
    // إنشاء محادثة جديدة
    $conversation_id = count($data['conversations']) + 1;
    $data['conversations'][] = [
        'id' => $conversation_id,
        'title' => $title,
        'type' => $type,
        'participants' => [$user_id, $participant_id],
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    if (!saveChatData($data)) {
        http_response_code(500);
        echo json_encode(['error' => 'خطأ في حفظ البيانات'], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'conversation_id' => $conversation_id,
        'message' => 'تم إنشاء المحادثة بنجاح'
    ], JSON_UNESCAPED_UNICODE);
}

function searchUsers($query, $current_user_id) {
    if (strlen($query) < 2) {
        echo json_encode(['users' => []], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $data = loadChatData();
    $users = [];
    
    foreach ($data['users'] as $user) {
        if ($user['id'] != $current_user_id && 
            stripos($user['name'], $query) !== false) {
            $users[] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'user_type' => $user['role'],
                'display_role' => $user['role'] === 'teacher' ? 'معلم' : 'طالب',
                'email' => ''
            ];
        }
    }
    
    echo json_encode(['users' => $users], JSON_UNESCAPED_UNICODE);
}

function markMessagesAsRead($conversation_id, $user_id) {
    $data = loadChatData();
    
    foreach ($data['messages'] as &$msg) {
        if ($msg['conversation_id'] == $conversation_id && $msg['sender_id'] != $user_id) {
            $msg['is_read'] = true;
        }
    }
    
    saveChatData($data);
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
}

function getUnreadCounts($user_id) {
    $data = loadChatData();
    $messages_count = 0;
    $notifications_count = 0;
    
    // عد الرسائل غير المقروءة
    foreach ($data['messages'] as $msg) {
        if ($msg['sender_id'] != $user_id && !$msg['is_read']) {
            // التأكد من أن المستخدم مشارك في المحادثة
            foreach ($data['conversations'] as $conv) {
                if ($conv['id'] == $msg['conversation_id'] && in_array($user_id, $conv['participants'])) {
                    $messages_count++;
                    break;
                }
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'messages' => $messages_count,
            'notifications' => $notifications_count,
            'total' => $messages_count + $notifications_count
        ]
    ], JSON_UNESCAPED_UNICODE);
}

function timeAgo($datetime) {
    if (empty($datetime)) return '';
    
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'الآن';
    if ($time < 3600) return floor($time/60) . ' د';
    if ($time < 86400) return floor($time/3600) . ' س';
    if ($time < 2592000) return floor($time/86400) . ' ي';
    if ($time < 31536000) return floor($time/2592000) . ' ش';
    
    return floor($time/31536000) . ' سنة';
}
?>