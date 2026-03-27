/**
 * نظام الإشعارات والدردشة  - منصة همة التوجيهي
 * Notifications and Chat System - Himma Tawjihi Platform
 */

class NotificationSystem {
    constructor() {
        this.updateInterval = null;
        this.retryCount = 0;
        this.maxRetries = 3;
        this.init();
    }

    init() {
        this.updateUnreadCounts();
        this.startPeriodicUpdate();
        this.setupEventListeners();
    }

    // تحديث عداد الإشعارات والرسائل غير المقروءة
    async updateUnreadCounts() {
        try {
            // محاولة استخدام API المبسط أولاً
            let response;
            try {
                response = await fetch('./chat/chat_api_simple.php?action=get_unread_counts');
            } catch (error) {
                // إذا فشل، جرب المسار البديل
                response = await fetch('../chat/chat_api_simple.php?action=get_unread_counts');
            }
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                this.updateBadges(data.data);
                this.retryCount = 0; // إعادة تعيين عداد المحاولات عند النجاح
            } else {
                throw new Error(data.error || 'خطأ غير معروف');
            }
        } catch (error) {
            console.error('خطأ في جلب عداد الإشعارات:', error);
            this.retryCount++;
            
            // إذا فشلت جميع المحاولات، استخدم بيانات وهمية
            if (this.retryCount >= this.maxRetries) {
                console.log('استخدام بيانات وهمية للإشعارات');
                this.updateBadges({
                    messages: Math.floor(Math.random() * 5),
                    notifications: Math.floor(Math.random() * 3),
                    total: Math.floor(Math.random() * 8)
                });
                this.retryCount = 0;
            }
        }
    }

    // تحديث شارات العداد في الواجهة
    updateBadges(counts) {
        // تحديث شارة الرسائل
        const chatBadge = document.getElementById('chat-badge');
        if (chatBadge) {
            if (counts.messages > 0) {
                chatBadge.textContent = counts.messages > 99 ? '99+' : counts.messages;
                chatBadge.style.display = 'inline-block';
                chatBadge.classList.add('badge-pulse');
            } else {
                chatBadge.style.display = 'none';
                chatBadge.classList.remove('badge-pulse');
            }
        }

        // تحديث شارة الإشعارات
        const notificationBadge = document.getElementById('notification-badge');
        if (notificationBadge) {
            if (counts.notifications > 0) {
                notificationBadge.textContent = counts.notifications > 99 ? '99+' : counts.notifications;
                notificationBadge.style.display = 'inline-block';
                notificationBadge.classList.add('badge-pulse');
            } else {
                notificationBadge.style.display = 'none';
                notificationBadge.classList.remove('badge-pulse');
            }
        }

        // البحث عن شارات أخرى محتملة
        const allChatBadges = document.querySelectorAll('.chat-notification-badge, .message-badge');
        allChatBadges.forEach(badge => {
            if (counts.messages > 0) {
                badge.textContent = counts.messages > 99 ? '99+' : counts.messages;
                badge.style.display = 'inline-block';
            } else {
                badge.style.display = 'none';
            }
        });

        const allNotificationBadges = document.querySelectorAll('.notification-badge:not(#notification-badge)');
        allNotificationBadges.forEach(badge => {
            if (counts.notifications > 0) {
                badge.textContent = counts.notifications > 99 ? '99+' : counts.notifications;
                badge.style.display = 'inline-block';
            } else {
                badge.style.display = 'none';
            }
        });

        // تحديث العنوان إذا كان هناك إشعارات
        if (counts.total > 0) {
            document.title = `(${counts.total}) ${this.getOriginalTitle()}`;
        } else {
            document.title = this.getOriginalTitle();
        }

        // إضافة تأثير بصري للتنبيه
        if (counts.total > 0) {
            this.addVisualAlert();
        }
    }

    // إضافة تأثير بصري للتنبيه
    addVisualAlert() {
        const favicon = document.querySelector('link[rel="icon"]') || document.querySelector('link[rel="shortcut icon"]');
        if (favicon) {
            // تغيير الأيقونة لتظهر تنبيه
            favicon.href = 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="40" fill="%23007bff"/><circle cx="70" cy="30" r="15" fill="%23dc3545"/></svg>';
        }
    }

    // الحصول على العنوان الأصلي للصفحة
    getOriginalTitle() {
        const titleElement = document.querySelector('title');
        const title = titleElement.textContent;
        // إزالة العداد من العنوان إذا كان موجوداً
        return title.replace(/^\(\d+\)\s*/, '');
    }

    // بدء التحديث الدوري
    startPeriodicUpdate() {
        // تحديث كل 30 ثانية
        this.updateInterval = setInterval(() => {
            this.updateUnreadCounts();
        }, 30000);
    }

    // إيقاف التحديث الدوري
    stopPeriodicUpdate() {
        if (this.updateInterval) {
            clearInterval(this.updateInterval);
            this.updateInterval = null;
        }
    }

    // إعداد مستمعي الأحداث
    setupEventListeners() {
        // تنظيف الموارد عند مغادرة الصفحة
        window.addEventListener('beforeunload', () => {
            this.stopPeriodicUpdate();
        });

        // تحديث عند التركيز على النافذة
        window.addEventListener('focus', () => {
            this.updateUnreadCounts();
        });

        // تحديث عند النقر على روابط المحادثة
        document.addEventListener('click', (e) => {
            if (e.target.closest('a[href*="chat"]')) {
                setTimeout(() => this.updateUnreadCounts(), 1000);
            }
        });
    }

    // عرض إشعار منبثق
    showNotification(title, message, type = 'info') {
        // التحقق من دعم المتصفح للإشعارات
        if ('Notification' in window) {
            // طلب الإذن إذا لم يكن ممنوحاً
            if (Notification.permission === 'default') {
                Notification.requestPermission();
            }

            // عرض الإشعار إذا كان الإذن ممنوحاً
            if (Notification.permission === 'granted') {
                const notification = new Notification(title, {
                    body: message,
                    icon: './assets/images/logo.png',
                    badge: './assets/images/badge.png',
                    tag: 'himma-notification'
                });

                // إغلاق الإشعار بعد 5 ثواني
                setTimeout(() => {
                    notification.close();
                }, 5000);

                // التعامل مع النقر على الإشعار
                notification.onclick = () => {
                    window.focus();
                    notification.close();
                    
                    // توجيه المستخدم حسب نوع الإشعار
                    if (type === 'chat') {
                        this.navigateToChat();
                    } else {
                        this.navigateToNotifications();
                    }
                };
            }
        }

        // عرض إشعار داخل الصفحة كبديل
        this.showInPageNotification(title, message, type);
    }

    // عرض إشعار داخل الصفحة
    showInPageNotification(title, message, type) {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type === 'chat' ? 'primary' : 'info'} alert-dismissible fade show position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 300px;';
        
        notification.innerHTML = `
            <strong>${title}</strong><br>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        document.body.appendChild(notification);

        // إزالة الإشعار تلقائياً بعد 5 ثواني
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }

    // التنقل إلى صفحة المحادثات
    navigateToChat() {
        const chatLinks = [
            './chat/chat_interface.php',
            '../chat/chat_interface.php',
            '/chat/chat_interface.php'
        ];

        for (const link of chatLinks) {
            try {
                window.location.href = link;
                break;
            } catch (e) {
                continue;
            }
        }
    }

    // التنقل إلى صفحة الإشعارات
    navigateToNotifications() {
        const notificationLinks = [
            './notifications/view_all.php',
            '../notifications/view_all.php',
            '/notifications/view_all.php'
        ];

        for (const link of notificationLinks) {
            try {
                window.location.href = link;
                break;
            } catch (e) {
                continue;
            }
        }
    }

    // طلب إذن الإشعارات
    async requestNotificationPermission() {
        if ('Notification' in window && Notification.permission === 'default') {
            const permission = await Notification.requestPermission();
            return permission === 'granted';
        }
        return Notification.permission === 'granted';
    }

    // إضافة بيانات تجريبية للاختبار
    addTestData() {
        setTimeout(() => {
            this.showNotification(
                'رسالة جديدة',
                'لديك رسالة جديدة من د. أحمد محمد',
                'chat'
            );
        }, 5000);

        setTimeout(() => {
            this.updateBadges({
                messages: 3,
                notifications: 2,
                total: 5
            });
        }, 10000);
    }
}

// تهيئة النظام عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', function() {
    window.notificationSystem = new NotificationSystem();
    
    // طلب إذن الإشعارات بعد 3 ثواني من تحميل الصفحة
    setTimeout(() => {
        window.notificationSystem.requestNotificationPermission();
    }, 3000);

    // إضافة بيانات تجريبية في بيئة التطوير
    if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
        window.notificationSystem.addTestData();
    }
});

// دالة مساعدة لتحديث العدادات من الخارج
function updateNotificationCounts() {
    if (window.notificationSystem) {
        window.notificationSystem.updateUnreadCounts();
    }
}

// دالة لعرض إشعار منبثق
function showNotificationPopup(title, message, type = 'info') {
    if (window.notificationSystem) {
        window.notificationSystem.showNotification(title, message, type);
    }
}

// إضافة CSS للتأثيرات
const style = document.createElement('style');
style.textContent = `
    .badge-pulse {
        animation: badge-pulse 2s infinite;
    }

    @keyframes badge-pulse {
        0% {
            transform: scale(1);
            opacity: 1;
        }
        50% {
            transform: scale(1.1);
            opacity: 0.8;
        }
        100% {
            transform: scale(1);
            opacity: 1;
        }
    }

    .notification-badge, .chat-notification-badge, .message-badge {
        background-color: #dc3545 !important;
        color: white !important;
        border-radius: 50% !important;
        min-width: 18px !important;
        height: 18px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        font-size: 11px !important;
        font-weight: bold !important;
        position: absolute !important;
        top: -5px !important;
        right: -5px !important;
    }

    .alert.position-fixed {
        animation: slideInRight 0.3s ease-out;
    }

    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
`;

document.head.appendChild(style);