<?php
// التأكد من بدء الجلسة
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    return;
}

// تضمين ملف قاعدة البيانات
require_once __DIR__ . '/../config/database.php';

// دالة للحصول على عدد الإشعارات غير المقروءة
function getUnreadNotificationsCount($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("خطأ في جلب الإشعارات: " . $e->getMessage());
        return 0;
    }
}

// دالة للحصول على الإشعارات الحديثة
function getRecentNotifications($user_id, $limit = 5) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->execute([$user_id, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("خطأ في جلب الإشعارات الحديثة: " . $e->getMessage());
        return [];
    }
}

$user_id = $_SESSION['user_id'];
$unread_count = getUnreadNotificationsCount($user_id);
$recent_notifications = getRecentNotifications($user_id);
?>

<!-- جرس الإشعارات -->
<div class="notification-bell dropdown">
    <button class="btn btn-link dropdown-toggle" type="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fas fa-bell"></i>
        <?php if ($unread_count > 0): ?>
            <span class="badge bg-danger notification-count"><?php echo $unread_count; ?></span>
        <?php endif; ?>
    </button>
    
    <ul class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notificationDropdown">
        <li class="dropdown-header">
            <h6 class="mb-0">الإشعارات</h6>
            <?php if ($unread_count > 0): ?>
                <small class="text-muted"><?php echo $unread_count; ?> إشعار جديد</small>
            <?php endif; ?>
        </li>
        <li><hr class="dropdown-divider"></li>
        
        <?php if (empty($recent_notifications)): ?>
            <li class="dropdown-item text-center text-muted py-3">
                لا توجد إشعارات
            </li>
        <?php else: ?>
            <?php foreach ($recent_notifications as $notification): ?>
                <li>
                    <a class="dropdown-item notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>" 
                       href="#" onclick="markAsRead(<?php echo $notification['id']; ?>)">
                        <div class="notification-content">
                            <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                            <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                            <small class="notification-time text-muted">
                                <?php echo date('Y-m-d H:i', strtotime($notification['created_at'])); ?>
                            </small>
                        </div>
                    </a>
                </li>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <li><hr class="dropdown-divider"></li>
        <li>
            <a class="dropdown-item text-center" href="../notifications/view_all.php">
                عرض جميع الإشعارات
            </a>
        </li>
    </ul>
</div>

<style>
.notification-bell {
    position: relative;
}

.notification-count {
    position: absolute;
    top: -5px;
    right: -5px;
    font-size: 10px;
    min-width: 18px;
    height: 18px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.notification-dropdown {
    width: 350px;
    max-height: 400px;
    overflow-y: auto;
}

.notification-item {
    padding: 10px 15px;
    border-bottom: 1px solid #eee;
}

.notification-item.unread {
    background-color: #f8f9fa;
    border-left: 3px solid #007bff;
}

.notification-content {
    white-space: normal;
}

.notification-title {
    font-weight: bold;
    margin-bottom: 5px;
}

.notification-message {
    font-size: 14px;
    color: #666;
    margin-bottom: 5px;
}

.notification-time {
    font-size: 12px;
}

.notification-item:hover {
    background-color: #f1f1f1;
}
</style>

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
            // تحديث العداد
            location.reload();
        }
    })
    .catch(error => {
        console.error('خطأ في تحديث الإشعار:', error);
    });
}

// تحديث الإشعارات كل دقيقة
setInterval(function() {
    fetch('../api/get_notifications_count.php')
    .then(response => response.json())
    .then(data => {
        if (data.count !== undefined) {
            const badge = document.querySelector('.notification-count');
            if (data.count > 0) {
                if (badge) {
                    badge.textContent = data.count;
                } else {
                    // إضافة badge جديد إذا لم يكن موجود
                    const bellIcon = document.querySelector('.notification-bell i');
                    const newBadge = document.createElement('span');
                    newBadge.className = 'badge bg-danger notification-count';
                    newBadge.textContent = data.count;
                    bellIcon.parentNode.appendChild(newBadge);
                }
            } else {
                if (badge) {
                    badge.remove();
                }
            }
        }
    })
    .catch(error => {
        console.error('خطأ في جلب عدد الإشعارات:', error);
    });
}, 60000); // كل دقيقة
</script>