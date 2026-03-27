<?php
/**
 * ملف الدوال المساعدة  - منصة همة التوجيهي
 */

// التحقق من تسجيل دخول المستخدم
if (!function_exists('is_logged_in')) {
    function is_logged_in() {
        return isset($_SESSION["user_id"]) && !empty($_SESSION["user_id"]);
    }
}

// التحقق من دور المستخدم الحالي
if (!function_exists('has_role')) {
    function has_role($role) {
        // إذا كان الدور غير محدد في الجلسة
        if (!isset($_SESSION['role'])) return false;
        // إذا كان الدور عبارة عن مصفوفة (لأكثر من دور)
        if (is_array($_SESSION['role'])) {
            return in_array($role, $_SESSION['role']);
        }
        // إذا كان الدور نص عادي
        return $_SESSION['role'] === $role;
    }
}

// التحقق من صلاحية الوصول بناءً على الدور
if (!function_exists('check_permission')) {
    function check_permission($role_required) {
        if (!is_logged_in() || !has_role($role_required)) {
            redirect('../login.php');
            exit();
        }
    }
}

// إعادة التوجيه الآمن
if (!function_exists('redirect')) {
    function redirect($url) {
        if (!headers_sent()) {
            header("Location: " . $url);
            exit();
        } else {
            echo "<script>window.location.href='" . $url . "';</script>";
            exit();
        }
    }
}

// تنظيف البيانات المدخلة
if (!function_exists('sanitize_input')) {
    function sanitize_input($data) {
        if (is_array($data)) {
            return array_map("sanitize_input", $data);
        }
        return htmlspecialchars(trim(stripslashes($data)), ENT_QUOTES, "UTF-8");
    }
}

// تشفير كلمة المرور
if (!function_exists('hash_password')) {
    function hash_password($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}

// التحقق من كلمة المرور
if (!function_exists('verify_password')) {
    function verify_password($password, $hash) {
        return password_verify($password, $hash);
    }
}

// إنشاء رمز CSRF
if (!function_exists('generate_csrf_token')) {
    function generate_csrf_token() {
        if (!isset($_SESSION["csrf_token"])) {
            $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
        }
        return $_SESSION["csrf_token"];
    }
}

// التحقق من رمز CSRF
if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token($token) {
        return isset($_SESSION["csrf_token"]) && hash_equals($_SESSION["csrf_token"], $token);
    }
}

// الحصول على المستخدم الحالي
if (!function_exists('get_current_user')) {
    function get_current_user() {
        global $conn;

        if (!is_logged_in() || !$conn) {
            return null;
        }

        try {
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
            $stmt->execute([$_SESSION["user_id"]]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("خطأ في جلب معلومات المستخدم: " . $e->getMessage());
            return null;
        }
    }
}

// تحديث آخر نشاط للمستخدم
if (!function_exists('update_user_activity')) {
    function update_user_activity($user_id) {
        global $conn;

        if (!$conn) {
            return false;
        }

        try {
            $stmt = $conn->prepare("UPDATE users SET last_seen = NOW(), is_online = 1 WHERE id = ?");
            $stmt->execute([$user_id]);
            return true;
        } catch (Exception $e) {
            error_log("خطأ في تحديث النشاط: " . $e->getMessage());
            return false;
        }
    }
}

// تسجيل خروج المستخدم
if (!function_exists('logout_user')) {
    function logout_user() {
        global $conn;

        if (is_logged_in() && $conn) {
            try {
                $stmt = $conn->prepare("UPDATE users SET is_online = 0 WHERE id = ?");
                $stmt->execute([$_SESSION["user_id"]]);
            } catch (Exception $e) {
                error_log("خطأ في تحديث حالة المستخدم: " . $e->getMessage());
            }
        }

        // مسح جميع متغيرات الجلسة
        $_SESSION = array();

        // حذف ملف تعريف الارتباط للجلسة
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), "", time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        // إنهاء الجلسة
        session_destroy();
    }
}

// إرسال إشعار
if (!function_exists('send_notification')) {
    function send_notification($user_id, $title, $message, $type = "system", $related_id = null) {
        global $conn;

        if (!$conn) {
            return false;
        }

        try {
            $stmt = $conn->prepare("
                INSERT INTO notifications (user_id, title, message, type, related_id, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$user_id, $title, $message, $type, $related_id]);
            return $conn->lastInsertId();
        } catch (Exception $e) {
            error_log("خطأ في إرسال الإشعار: " . $e->getMessage());
            return false;
        }
    }
}

// الحصول على عدد الإشعارات غير المقروءة
if (!function_exists('get_unread_notifications_count')) {
    function get_unread_notifications_count($user_id) {
        global $conn;

        if (!$conn) {
            return 0;
        }

        try {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$user_id]);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("خطأ في جلب عدد الإشعارات: " . $e->getMessage());
            return 0;
        }
    }
}

// تنسيق التاريخ بالعربية
if (!function_exists('format_arabic_date')) {
    function format_arabic_date($date, $format = "Y-m-d H:i") {
        $arabic_months = [
            1 => "يناير", 2 => "فبراير", 3 => "مارس", 4 => "أبريل",
            5 => "مايو", 6 => "يونيو", 7 => "يوليو", 8 => "أغسطس",
            9 => "سبتمبر", 10 => "أكتوبر", 11 => "نوفمبر", 12 => "ديسمبر"
        ];

        $timestamp = strtotime($date);
        $day = date("d", $timestamp);
        $month = $arabic_months[(int)date("m", $timestamp)];
        $year = date("Y", $timestamp);
        $time = date("H:i", $timestamp);

        return "$day $month $year - $time";
    }
}

// حساب الوقت المنقضي
if (!function_exists('time_ago')) {
    function time_ago($datetime) {
        $time = time() - strtotime($datetime);

        if ($time < 60) {
            return "الآن";
        } elseif ($time < 3600) {
            $minutes = floor($time / 60);
            return $minutes . " دقيقة";
        } elseif ($time < 86400) {
            $hours = floor($time / 3600);
            return $hours . " ساعة";
        } elseif ($time < 2592000) {
            $days = floor($time / 86400);
            return $days . " يوم";
        } elseif ($time < 31536000) {
            $months = floor($time / 2592000);
            return $months . " شهر";
        } else {
            $years = floor($time / 31536000);
            return $years . " سنة";
        }
    }
}

// التحقق من صحة البريد الإلكتروني
if (!function_exists('validate_email')) {
    function validate_email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

// التحقق من قوة كلمة المرور
if (!function_exists('validate_password')) {
    function validate_password($password) {
        return strlen($password) >= 6;
    }
}

// تسجيل نشاط المستخدم
if (!function_exists('log_user_activity')) {
    function log_user_activity($user_id, $action, $description = "") {
        global $conn;

        if (!$conn) {
            return false;
        }

        try {
            // إنشاء جدول سجل الأنشطة إذا لم يكن موجوداً
            $conn->exec("
                CREATE TABLE IF NOT EXISTS activity_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    action VARCHAR(100) NOT NULL,
                    description TEXT,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user (user_id),
                    INDEX idx_action (action),
                    INDEX idx_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            $stmt = $conn->prepare("
                INSERT INTO activity_log (user_id, action, description, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");

            $ip = $_SERVER["REMOTE_ADDR"] ?? "unknown";
            $user_agent = $_SERVER["HTTP_USER_AGENT"] ?? "unknown";

            $stmt->execute([$user_id, $action, $description, $ip, $user_agent]);
            return true;
        } catch (Exception $e) {
            error_log("خطأ في تسجيل النشاط: " . $e->getMessage());
            return false;
        }
    }
}

// رسائل الفلاش
if (!function_exists('set_flash_message')) {
    function set_flash_message($type, $message) {
        $_SESSION["flash_message"] = [
            "type" => $type,
            "message" => $message
        ];
    }
}

if (!function_exists('get_flash_message')) {
    function get_flash_message() {
        if (isset($_SESSION["flash_message"])) {
            $message = $_SESSION["flash_message"];
            unset($_SESSION["flash_message"]);
            return $message;
        }
        return null;
    }
}

// تحديث آخر نشاط للمستخدم تلقائياً
if (session_status() === PHP_SESSION_ACTIVE && is_logged_in()) {
    update_user_activity($_SESSION["user_id"]);
}

?>