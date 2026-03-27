<?php
/**
 * مكونات الهيدر - منصة همّة التوجيهي
 *  Header Components - Himma Tawjihi Platform
 */

// تأكد من تضمين دوال الإشعارات
require_once __DIR__ . '/notification_functions.php';

/**
 * احصل على كائن mysqli الفعلي من أي كائن قاعدة بيانات مستخدم
 */
function getMysqliObject($db) {
    if ($db instanceof mysqli) {
        return $db;
    }
    if (isset($db->conn) && $db->conn instanceof mysqli) {
        return $db->conn;
    }
    if (method_exists($db, 'getConnection')) {
        $maybe = $db->getConnection();
        if ($maybe instanceof mysqli) return $maybe;
    }
    return null;
}

/**
 * دالة الحصول على عدد الرسائل غير المقروءة
 */
if (!function_exists('getUnreadMessagesCount')) {
    function getUnreadMessagesCount($user_id) {
        global $db;

        $mysqli = null;

        // جلب الاتصال الرئيسي بالقاعدة أو إنشاء اتصال مؤقت إذا لم يكن موجوداً
        if (isset($db)) {
            $mysqli = getMysqliObject($db);
        }

        if (!$mysqli) {
            $mysqli = new mysqli('localhost', 'root', '', 'himma_tawjihi');
        }

        if (!$mysqli || mysqli_connect_errno()) {
            return 0;
        }

        $unread = 0;
        $sql = "SELECT COUNT(*) as unread_count FROM messages WHERE recipient_id = ? AND is_read = 0";
        if ($stmt = $mysqli->prepare($sql)) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->bind_result($unread);
            $stmt->fetch();
            $stmt->close();
        }
        return (int)$unread;
    }
}

/**
 * عرض أيقونات الدردشة والإشعارات في الهيدر
 */
function renderChatAndNotificationIcons($user_id, $user_role) {
    // الحصول على عدد الإشعارات غير المقروءة
    $unread_notifications = getTotalUnreadCount($user_id);
    $unread_messages = getUnreadMessagesCount($user_id);
    $total_unread = $unread_notifications + $unread_messages;

    ob_start();
    ?>
    <!-- Chat and Notification Icons -->

    <!-- أيقونة الإشعارات -->
    <li class="nav-item dropdown me-2">
        <a class="nav-link position-relative" href="#" id="notificationDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" title="الإشعارات">
            <i class="fas fa-bell"></i>
            <?php if ($unread_notifications > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge">
                    <?php echo $unread_notifications > 99 ? '99+' : $unread_notifications; ?>
                </span>
            <?php endif; ?>
        </a>

        <!-- قائمة الإشعارات المنسدلة -->
        <ul class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notificationDropdown">
            <li class="dropdown-header">
                <h6 class="mb-0">الإشعارات</h6>
                <?php if ($unread_notifications > 0): ?>
                    <small class="text-muted"><?php echo $unread_notifications; ?> إشعار جديد</small>
                <?php endif; ?>
            </li>
            <li><hr class="dropdown-divider"></li>

            <?php
            $recent_notifications = getRecentNotifications($user_id, 5);
            if (empty($recent_notifications)):
            ?>
                <li class="dropdown-item text-center text-muted py-3">
                    لا توجد إشعارات
                </li>
            <?php else: ?>
                <?php foreach ($recent_notifications as $notification): ?>
                    <li>
                        <a class="dropdown-item notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>"
                           href="#" onclick="markNotificationAsRead(<?php echo $notification['id']; ?>)">
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
    </li>

    <!-- أيقونة المحادثة -->
    <li class="nav-item me-3">
        <a class="nav-link position-relative" href="../chat/chat_interface.php" title="المحادثات">
            <i class="fas fa-comments"></i>
            <?php if ($unread_messages > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-success chat-badge">
                    <?php echo $unread_messages > 99 ? '99+' : $unread_messages; ?>
                </span>
            <?php endif; ?>
        </a>
    </li>

    <style>
    .notification-badge, .chat-badge {
        font-size: 0.6rem;
        min-width: 16px;
        height: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        animation: pulse 2s infinite;
    }
    @keyframes pulse {
        0% { transform: translate(-50%, -50%) scale(1); }
        50% { transform: translate(-50%, -50%) scale(1.1); }
        100% { transform: translate(-50%, -50%) scale(1); }
    }
    .nav-link:hover .notification-badge,
    .nav-link:hover .chat-badge {
        animation: none;
        transform: translate(-50%, -50%) scale(1.1);
    }
    .notification-dropdown {
        width: 350px;
        max-height: 400px;
        overflow-y: auto;
    }
    .notification-item {
        padding: 10px 15px;
        border-bottom: 1px solid #eee;
        white-space: normal;
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
    // دالة تحديد الإشعار كمقروء
    function markNotificationAsRead(notificationId) {
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
                // تحديث الصفحة لإظهار التغييرات
                location.reload();
            }
        })
        .catch(error => {
            console.error('خطأ في تحديث الإشعار:', error);
        });
    }

    // تحديث عدد الإشعارات والرسائل كل 30 ثانية
    setInterval(function() {
        fetch('../api/get_notifications_count.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const notificationBadge = document.querySelector('.notification-badge');
                    const notificationCount = data.count;

                    if (notificationCount > 0) {
                        if (notificationBadge) {
                            notificationBadge.textContent = notificationCount > 99 ? '99+' : notificationCount;
                            notificationBadge.style.display = 'flex';
                        } else {
                            // إنشاء الشارة إذا لم تكن موجودة
                            const notificationLink = document.querySelector('#notificationDropdown');
                            if (notificationLink) {
                                const newBadge = document.createElement('span');
                                newBadge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge';
                                newBadge.textContent = notificationCount > 99 ? '99+' : notificationCount;
                                notificationLink.appendChild(newBadge);
                            }
                        }
                    } else {
                        if (notificationBadge) {
                            notificationBadge.style.display = 'none';
                        }
                    }
                }
            })
            .catch(error => console.error('Error updating notification count:', error));

        // تحديث عدد الرسائل
        fetch('../chat/chat_api.php?action=get_unread_counts')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const chatBadge = document.querySelector('.chat-badge');
                    const messageCount = data.data.messages;

                    if (messageCount > 0) {
                        if (chatBadge) {
                            chatBadge.textContent = messageCount > 99 ? '99+' : messageCount;
                            chatBadge.style.display = 'flex';
                        } else {
                            // إنشاء الشارة إذا لم تكن موجودة
                            const chatLink = document.querySelector('a[href="../chat/chat_interface.php"]');
                            if (chatLink) {
                                const newBadge = document.createElement('span');
                                newBadge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-success chat-badge';
                                newBadge.textContent = messageCount > 99 ? '99+' : messageCount;
                                chatLink.appendChild(newBadge);
                            }
                        }
                    } else {
                        if (chatBadge) {
                            chatBadge.style.display = 'none';
                        }
                    }
                }
            })
            .catch(error => console.error('Error updating chat count:', error));
    }, 30000);
    </script>
    <?php
    return ob_get_clean();
}
?>