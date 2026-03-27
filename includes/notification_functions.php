<?php
/**
 * دوال الإشعارات  - منصة همة التوجيهي
 */

// إرسال إشعار جديد
if (!function_exists('sendNotification')) {
    function sendNotification($user_id, $title, $message, $type = 'system', $related_id = null) {
        global $pdo, $conn;
        
        $db_connection = $pdo ?? $conn;
        if (!$db_connection) {
            error_log("لا يوجد اتصال بقاعدة البيانات في sendNotification");
            return false;
        }

        try {
            $stmt = $db_connection->prepare("
                INSERT INTO notifications (user_id, title, message, type, related_id, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$user_id, $title, $message, $type, $related_id]);
            return $db_connection->lastInsertId();
        } catch (Exception $e) {
            error_log("خطأ في إرسال الإشعار: " . $e->getMessage());
            return false;
        }
    }
}

// جلب عدد الإشعارات غير المقروءة
if (!function_exists('getTotalUnreadCount')) {
    function getTotalUnreadCount($user_id) {
        global $pdo, $conn;
        
        $db_connection = $pdo ?? $conn;
        if (!$db_connection) {
            return 0;
        }

        try {
            $stmt = $db_connection->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$user_id]);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("خطأ في جلب عدد الإشعارات: " . $e->getMessage());
            return 0;
        }
    }
}

// جلب آخر الإشعارات
if (!function_exists('getRecentNotifications')) {
    function getRecentNotifications($user_id, $limit = 10) {
        global $pdo, $conn;
        
        $db_connection = $pdo ?? $conn;
        if (!$db_connection) {
            return [];
        }

        try {
            $stmt = $db_connection->prepare("
                SELECT * FROM notifications 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$user_id, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("خطأ في جلب الإشعارات: " . $e->getMessage());
            return [];
        }
    }
}

// تحديد إشعار كمقروء
if (!function_exists('markNotificationAsRead')) {
    function markNotificationAsRead($notification_id, $user_id) {
        global $pdo, $conn;
        
        $db_connection = $pdo ?? $conn;
        if (!$db_connection) {
            return false;
        }

        try {
            $stmt = $db_connection->prepare("
                UPDATE notifications 
                SET is_read = 1 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$notification_id, $user_id]);
            return true;
        } catch (Exception $e) {
            error_log("خطأ في تحديث الإشعار: " . $e->getMessage());
            return false;
        }
    }
}

// تحديد جميع الإشعارات كمقروءة
if (!function_exists('markAllNotificationsAsRead')) {
    function markAllNotificationsAsRead($user_id) {
        global $pdo, $conn;
        
        $db_connection = $pdo ?? $conn;
        if (!$db_connection) {
            return false;
        }

        try {
            $stmt = $db_connection->prepare("
                UPDATE notifications 
                SET is_read = 1 
                WHERE user_id = ? AND is_read = 0
            ");
            $stmt->execute([$user_id]);
            return true;
        } catch (Exception $e) {
            error_log("خطأ في تحديث الإشعارات: " . $e->getMessage());
            return false;
        }
    }
}

// حذف إشعار
if (!function_exists('deleteNotification')) {
    function deleteNotification($notification_id, $user_id) {
        global $pdo, $conn;
        
        $db_connection = $pdo ?? $conn;
        if (!$db_connection) {
            return false;
        }

        try {
            $stmt = $db_connection->prepare("
                DELETE FROM notifications 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$notification_id, $user_id]);
            return true;
        } catch (Exception $e) {
            error_log("خطأ في حذف الإشعار: " . $e->getMessage());
            return false;
        }
    }
}

// جلب إحصائيات الإشعارات والمحادثات
if (!function_exists('getNotificationStats')) {
    function getNotificationStats($user_id) {
        global $pdo, $conn;
        
        $db_connection = $pdo ?? $conn;
        if (!$db_connection) {
            return [
                'notifications' => 0,
                'messages' => 0,
                'total' => 0
            ];
        }

        try {
            // عدد الإشعارات غير المقروءة
            $stmt = $db_connection->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$user_id]);
            $notifications_count = (int)$stmt->fetchColumn();
            
            // عدد الرسائل غير المقروءة
            $messages_count = 0;
            try {
                $stmt = $db_connection->prepare("
                    SELECT COUNT(*) 
                    FROM chat_messages cm
                    JOIN chat_participants cp ON cm.conversation_id = cp.conversation_id
                    WHERE cp.user_id = ? AND cm.sender_id != ? AND cm.is_read = 0
                ");
                $stmt->execute([$user_id, $user_id]);
                $messages_count = (int)$stmt->fetchColumn();
            } catch (Exception $e) {
                // إذا لم تكن جداول المحادثات موجودة بعد
                $messages_count = 0;
            }
            
            return [
                'notifications' => $notifications_count,
                'messages' => $messages_count,
                'total' => $notifications_count + $messages_count
            ];
            
        } catch (Exception $e) {
            error_log("خطأ في جلب إحصائيات الإشعارات: " . $e->getMessage());
            return [
                'notifications' => 0,
                'messages' => 0,
                'total' => 0
            ];
        }
    }
}
?>