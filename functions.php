<?php
require_once 'database.php';

// وظائف المصادقة
function authenticate_user($username, $password) {
    $db = Database::getInstance();
    $stmt = $db->query('SELECT * FROM users WHERE username = ? AND is_active = 1', [$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        
        // تسجيل النشاط
        log_activity($user['id'], 'login', 'تسجيل دخول ناجح');
        
        return true;
    }
    
    return false;
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function is_teacher() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'teacher';
}

function logout_user() {
    if (isset($_SESSION['user_id'])) {
        log_activity($_SESSION['user_id'], 'logout', 'تسجيل خروج');
    }
    
    session_destroy();
    header('Location: login.php');
    exit;
}

// وظائف تسجيل النشاط
function log_activity($user_id, $action, $description = null) {
    $db = Database::getInstance();
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $db->query(
        'INSERT INTO activity_log (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)',
        [$user_id, $action, $description, $ip_address, $user_agent]
    );
}

// وظائف الإشعارات
function create_notification($user_id, $title, $message, $type = 'system') {
    $db = Database::getInstance();
    $db->query(
        'INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)',
        [$user_id, $title, $message, $type]
    );
}

// وظائف طلبات المعلمين
function create_teacher_application($data) {
    $db = Database::getInstance();
    
    $stmt = $db->query('
        INSERT INTO teacher_applications 
        (full_name, email, phone, region_id, subject_specialization, experience_years, education_level, cv_file, certificates_file) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ', [
        $data['full_name'],
        $data['email'],
        $data['phone'],
        $data['region_id'],
        $data['subject_specialization'],
        $data['experience_years'],
        $data['education_level'],
        $data['cv_file'] ?? null,
        $data['certificates_file'] ?? null
    ]);
    
    return $db->lastInsertId();
}

function approve_teacher_application($application_id, $admin_id, $notes = null) {
    $db = Database::getInstance();
    
    // الحصول على بيانات الطلب
    $stmt = $db->query('SELECT * FROM teacher_applications WHERE id = ?', [$application_id]);
    $application = $stmt->fetch();
    
    if (!$application) {
        return false;
    }
    
    try {
        $db->getConnection()->beginTransaction();
        
        // تحديث حالة الطلب
        $db->query('
            UPDATE teacher_applications 
            SET status = "approved", approved_at = NOW(), approved_by = ?, admin_notes = ? 
            WHERE id = ?
        ', [$admin_id, $notes, $application_id]);
        
        // إنشاء حساب المعلم
        $username = 'teacher_' . $application_id;
        $password = generate_random_password();
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $db->query('
            INSERT INTO users (username, email, password, full_name, phone, role, branch_id, is_active) 
            VALUES (?, ?, ?, ?, ?, "teacher", 1, 1)
        ', [
            $username,
            $application['email'],
            $password_hash,
            $application['full_name'],
            $application['phone']
        ]);
        
        $user_id = $db->lastInsertId();
        
        // إرسال إشعار للمعلم
        create_notification(
            $user_id,
            'مرحباً بك في منصة همة التوجيهي',
            'تم قبول طلبك وإنشاء حسابك بنجاح. اسم المستخدم: ' . $username . ' كلمة المرور: ' . $password,
            'success'
        );
        
        $db->getConnection()->commit();
        
        return [
            'success' => true,
            'user_id' => $user_id,
            'username' => $username,
            'password' => $password
        ];
        
    } catch (Exception $e) {
        $db->getConnection()->rollBack();
        error_log('Error approving teacher application: ' . $e->getMessage());
        return false;
    }
}

function generate_random_password($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    return substr(str_shuffle($chars), 0, $length);
}

// وظائف مساعدة
function sanitize_input($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function get_regions() {
    $db = Database::getInstance();
    $stmt = $db->query('SELECT * FROM regions WHERE is_active = 1 ORDER BY name');
    return $stmt->fetchAll();
}

function get_branches() {
    $db = Database::getInstance();
    $stmt = $db->query('SELECT * FROM branches WHERE is_active = 1 ORDER BY name');
    return $stmt->fetchAll();
}
?>