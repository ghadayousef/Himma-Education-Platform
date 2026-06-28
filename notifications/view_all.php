<?php
session_start();

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

require_once '../config/database.php';
require_once '../includes/functions.php';

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'المستخدم';
$user_type = $_SESSION['user_type'] ?? 'student';

// تحديد الصفحة الرئيسية حسب نوع المستخدم
$home_page = ($user_type === 'teacher') ? '../teacher/dashboard.php' : '../student/dashboard.php';

$error_message = '';
$success_message = '';
$notifications = [];
$total_notifications = 0;
$total_pages = 1;
$page = 1;

// معالجة تحديد جميع الإشعارات كمقروءة
if (isset($_POST['mark_all_read'])) {
    try {
        $db = new Database();
        $conn = $db->connect();
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $success_message = "تم تحديد جميع الإشعارات كمقروءة";
    } catch (Exception $e) {
        $error_message = "خطأ في تحديث الإشعارات";
    }
}

// جلب الإشعارات مع التصفح
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

try {
    $db = new Database();
    $conn = $db->connect();
    
    // عدد الإشعارات الإجمالي
    $count_stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
    $count_stmt->execute([$user_id]);
    $total_notifications = (int)$count_stmt->fetchColumn();
    
    // جلب الإشعارات
    if ($total_notifications > 0) {
        $stmt = $conn->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    $total_pages = $total_notifications > 0 ? ceil($total_notifications / $per_page) : 1;
    
} catch (Exception $e) {
    $error_message = "خطأ في جلب الإشعارات: " . $e->getMessage();
    $notifications = [];
    $total_pages = 1;
}

// دالة لتحويل نوع الإشعار إلى نص عربي
function getNotificationTypeText($type) {
    $types = [
        'chat' => 'رسالة',
        'assignment' => 'واجب',
        'grade' => 'درجة',
        'announcement' => 'إعلان',
        'system' => 'نظام',
        'welcome' => 'ترحيب'
    ];
    
    return $types[$type] ?? 'عام';
}

// دالة لتحويل الوقت إلى نص عربي
function timeAgo($datetime) {
    if (empty($datetime)) {
        return 'غير محدد';
    }
    
    try {
        $time = time() - strtotime($datetime);
        
        if ($time < 60) return 'الآن';
        if ($time < 3600) return floor($time/60) . ' دقيقة';
        if ($time < 86400) return floor($time/3600) . ' ساعة';
        if ($time < 2592000) return floor($time/86400) . ' يوم';
        if ($time < 31536000) return floor($time/2592000) . ' شهر';
        
        return floor($time/31536000) . ' سنة';
    } catch (Exception $e) {
        return 'غير محدد';
    }
}

// دالة آمنة للتحقق من قيمة is_read
function isNotificationUnread($notification) {
    return isset($notification['is_read']) && $notification['is_read'] == 0;
}

// دالة آمنة للحصول على قيمة read_at
function getReadAtDate($notification) {
    return isset($notification['read_at']) && !empty($notification['read_at']) 
        ? date('Y-m-d H:i', strtotime($notification['read_at']))
        : 'لم يتم القراءة';
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>جميع الإشعارات - منصة همة التوجيهي</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --success-color: #4facfe;
            --warning-color: #43e97b;
        }

        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #f8f9fc 0%, #e9ecef 100%);
            min-height: 100vh;
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .notification-item {
            border-left: 4px solid transparent;
            transition: all 0.3s ease;
            padding: 1rem;
        }
        
        .notification-item.unread {
            background-color: #f0f4ff;
            border-left-color: var(--primary-color);
        }
        
        .notification-item:hover {
            background-color: #f1f1f1;
            transform: translateX(-5px);
        }
        
        .notification-icon {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            flex-shrink: 0;
        }
        
        .notification-chat { background: linear-gradient(135deg, #28a745, #20c997); }
        .notification-assignment { background: linear-gradient(135deg, #ffc107, #ff9800); }
        .notification-grade { background: linear-gradient(135deg, #17a2b8, #00bcd4); }
        .notification-announcement { background: linear-gradient(135deg, #dc3545, #c82333); }
        .notification-system { background: linear-gradient(135deg, #6c757d, #495057); }
        .notification-welcome { background: linear-gradient(135deg, #667eea, #764ba2); }
        
        .notification-time {
            font-size: 0.875rem;
            color: #6c757d;
        }
        
        .notification-type-badge {
            font-size: 0.75rem;
            padding: 0.4rem 0.6rem;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            border-radius: 15px 15px 0 0;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="<?php echo htmlspecialchars($home_page); ?>">
                <i class="fas fa-graduation-cap"></i>
                همة التوجيهي
            </a>
            
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="<?php echo htmlspecialchars($home_page); ?>">
                    <i class="fas fa-home"></i> الرئيسية
                </a>
                <a class="nav-link" href="../chat/chat_interface.php">
                    <i class="fas fa-comments"></i> المحادثات
                </a>
                <span class="nav-link">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($user_name); ?>
                </span>
                <a class="nav-link" href="../auth/logout.php">
                    <i class="fas fa-sign-out-alt"></i> تسجيل الخروج
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4 mb-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-bell"></i> جميع الإشعارات
                            <span class="badge bg-light text-dark"><?php echo $total_notifications; ?></span>
                        </h5>
                        
                        <?php if ($total_notifications > 0): ?>
                            <form method="post" class="d-inline">
                                <button type="submit" name="mark_all_read" class="btn btn-light btn-sm" style="color: var(--primary-color);">
                                    <i class="fas fa-check-double"></i> تحديد الكل كمقروء
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-body p-0">
                        <?php if (!empty($success_message)): ?>
                            <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
                                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($error_message)): ?>
                            <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
                                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (empty($notifications)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">لا توجد إشعارات</h5>
                                <p class="text-muted">ستظهر إشعاراتك هنا عند وصولها</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($notifications as $notification): 
                                    $is_unread = isNotificationUnread($notification);
                                    $notification_type = $notification['type'] ?? 'system';
                                    $notification_title = $notification['title'] ?? 'بدون عنوان';
                                    $notification_message = $notification['message'] ?? 'بدون محتوى';
                                    $created_at = $notification['created_at'] ?? date('Y-m-d H:i:s');
                                    $notification_id = $notification['id'] ?? 0;
                                ?>
                                    <div class="list-group-item notification-item <?php echo $is_unread ? 'unread' : ''; ?>">
                                        <div class="d-flex align-items-start">
                                            <div class="notification-icon notification-<?php echo htmlspecialchars($notification_type); ?> me-3">
                                                <?php
                                                switch ($notification_type) {
                                                    case 'chat':
                                                        echo '<i class="fas fa-comments"></i>';
                                                        break;
                                                    case 'assignment':
                                                        echo '<i class="fas fa-tasks"></i>';
                                                        break;
                                                    case 'grade':
                                                        echo '<i class="fas fa-star"></i>';
                                                        break;
                                                    case 'announcement':
                                                        echo '<i class="fas fa-bullhorn"></i>';
                                                        break;
                                                    case 'welcome':
                                                        echo '<i class="fas fa-heart"></i>';
                                                        break;
                                                    default:
                                                        echo '<i class="fas fa-info"></i>';
                                                }
                                                ?>
                                            </div>
                                            
                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between align-items-start mb-1">
                                                    <h6 class="mb-0 <?php echo $is_unread ? 'fw-bold' : ''; ?>">
                                                        <?php echo htmlspecialchars($notification_title); ?>
                                                    </h6>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="badge bg-secondary notification-type-badge">
                                                            <?php echo getNotificationTypeText($notification_type); ?>
                                                        </span>
                                                        <?php if ($is_unread): ?>
                                                            <span class="badge bg-primary">جديد</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                
                                                <p class="mb-2 text-muted small">
                                                    <?php echo htmlspecialchars($notification_message); ?>
                                                </p>
                                                
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="notification-time">
                                                        <i class="fas fa-clock"></i>
                                                        <?php echo timeAgo($created_at); ?>
                                                        (<?php echo date('Y-m-d H:i', strtotime($created_at)); ?>)
                                                    </small>
                                                    
                                                    <?php if ($is_unread && $notification_id): ?>
                                                        <button class="btn btn-sm btn-outline-primary" 
                                                                onclick="markAsRead(<?php echo (int)$notification_id; ?>)">
                                                            <i class="fas fa-check"></i> تحديد كمقروء
                                                        </button>
                                                    <?php elseif (!$is_unread): ?>
                                                        <small class="text-success">
                                                            <i class="fas fa-check-double"></i>
                                                            مقروء في <?php echo getReadAtDate($notification); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <div class="card-footer bg-light">
                                    <nav aria-label="تصفح الإشعارات">
                                        <ul class="pagination justify-content-center mb-0">
                                            <?php if ($page > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>">السابق</a>
                                                </li>
                                            <?php endif; ?>
                                            
                                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            
                                            <?php if ($page < $total_pages): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>">التالي</a>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </nav>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function markAsRead(notificationId) {
            if (!notificationId) {
                alert('خطأ: معرف الإشعار غير صحيح');
                return;
            }

            fetch('../api/mark_notification_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    notification_id: parseInt(notificationId)
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('خطأ في تحديث الإشعار: ' + (data.message || 'خطأ غير معروف'));
                }
            })
            .catch(error => {
                console.error('خطأ في تحديث الإشعار:', error);
                alert('خطأ في تحديث الإشعار');
            });
        }
    </script>
</body>
</html>
