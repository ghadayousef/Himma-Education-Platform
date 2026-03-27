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

// معالجة تحديد جميع الإشعارات كمقروءة
if (isset($_POST['mark_all_read'])) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $success_message = "تم تحديد جميع الإشعارات كمقروءة";
    } catch (Exception $e) {
        $error_message = "خطأ في تحديث الإشعارات";
    }
}

// جلب الإشعارات مع التصفح
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

try {
    // عدد الإشعارات الإجمالي
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
    $count_stmt->execute([$user_id]);
    $total_notifications = $count_stmt->fetchColumn();
    
    // جلب الإشعارات
    $stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$user_id, $per_page, $offset]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $total_pages = ceil($total_notifications / $per_page);
    
} catch (Exception $e) {
    $error_message = "خطأ في جلب الإشعارات";
    $notifications = [];
}

// دالة لتحويل نوع الإشعار إلى نص عربي
function getNotificationTypeText($type) {
    switch ($type) {
        case 'chat': return 'رسالة';
        case 'assignment': return 'واجب';
        case 'grade': return 'درجة';
        case 'announcement': return 'إعلان';
        case 'system': return 'نظام';
        default: return 'عام';
    }
}

// دالة لتحويل الوقت إلى نص عربي
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'الآن';
    if ($time < 3600) return floor($time/60) . ' دقيقة';
    if ($time < 86400) return floor($time/3600) . ' ساعة';
    if ($time < 2592000) return floor($time/86400) . ' يوم';
    if ($time < 31536000) return floor($time/2592000) . ' شهر';
    
    return floor($time/31536000) . ' سنة';
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
        .notification-item {
            border-left: 4px solid transparent;
            transition: all 0.3s ease;
        }
        
        .notification-item.unread {
            background-color: #f8f9fa;
            border-left-color: #007bff;
        }
        
        .notification-item:hover {
            background-color: #f1f1f1;
        }
        
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
        }
        
        .notification-chat { background-color: #28a745; }
        .notification-assignment { background-color: #ffc107; }
        .notification-grade { background-color: #17a2b8; }
        .notification-announcement { background-color: #dc3545; }
        .notification-system { background-color: #6c757d; }
        
        .notification-time {
            font-size: 0.875rem;
            color: #6c757d;
        }
        
        .notification-type-badge {
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="<?php echo $home_page; ?>">
                <i class="fas fa-graduation-cap"></i>
                همة التوجيهي
            </a>
            
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="<?php echo $home_page; ?>">
                    <i class="fas fa-home"></i> الرئيسية
                </a>
                <a class="nav-link" href="../chat/chat_interface.php">
                    <i class="fas fa-comments"></i> المحادثات
                </a>
                <span class="nav-link">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($user_name); ?>
                </span>
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i> تسجيل الخروج
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-bell"></i> جميع الإشعارات
                            <span class="badge bg-primary"><?php echo $total_notifications; ?></span>
                        </h5>
                        
                        <?php if ($total_notifications > 0): ?>
                            <form method="post" class="d-inline">
                                <button type="submit" name="mark_all_read" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-check-double"></i> تحديد الكل كمقروء
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-body p-0">
                        <?php if (isset($success_message)): ?>
                            <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
                                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($error_message)): ?>
                            <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
                                <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
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
                                <?php foreach ($notifications as $notification): ?>
                                    <div class="list-group-item notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>">
                                        <div class="d-flex align-items-start">
                                            <div class="notification-icon notification-<?php echo $notification['type']; ?> me-3">
                                                <?php
                                                switch ($notification['type']) {
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
                                                    default:
                                                        echo '<i class="fas fa-info"></i>';
                                                }
                                                ?>
                                            </div>
                                            
                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between align-items-start mb-1">
                                                    <h6 class="mb-0 <?php echo !$notification['is_read'] ? 'fw-bold' : ''; ?>">
                                                        <?php echo htmlspecialchars($notification['title']); ?>
                                                    </h6>
                                                    <div class="d-flex align-items-center">
                                                        <span class="badge bg-secondary notification-type-badge me-2">
                                                            <?php echo getNotificationTypeText($notification['type']); ?>
                                                        </span>
                                                        <?php if (!$notification['is_read']): ?>
                                                            <span class="badge bg-primary">جديد</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                
                                                <p class="mb-1 text-muted">
                                                    <?php echo htmlspecialchars($notification['message']); ?>
                                                </p>
                                                
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="notification-time">
                                                        <i class="fas fa-clock"></i>
                                                        <?php echo timeAgo($notification['created_at']); ?>
                                                        (<?php echo date('Y-m-d H:i', strtotime($notification['created_at'])); ?>)
                                                    </small>
                                                    
                                                    <?php if (!$notification['is_read']): ?>
                                                        <button class="btn btn-sm btn-outline-primary" 
                                                                onclick="markAsRead(<?php echo $notification['id']; ?>)">
                                                            <i class="fas fa-check"></i> تحديد كمقروء
                                                        </button>
                                                    <?php else: ?>
                                                        <small class="text-success">
                                                            <i class="fas fa-check-double"></i>
                                                            مقروء في <?php echo date('Y-m-d H:i', strtotime($notification['read_at'])); ?>
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
                                <div class="card-footer">
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
            fetch('../api/mark_notification_read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    notification_id: notificationId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // إعادة تحميل الصفحة لإظهار التحديث
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