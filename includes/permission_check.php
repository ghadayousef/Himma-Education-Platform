<?php
/**
 * فحص الصلاحيات للنظام الهرمي
 * منصة همة التوجيهي
 */

// التحقق من تسجيل الدخول والصلاحيات الإدارية
function check_admin_permission() {
    if (!is_logged_in()) {
        redirect('../auth/login.php');
        exit();
    }
    
    if (!has_role('admin')) {
        redirect('../index.php');
        exit();
    }
    
    // تحديث معلومات المدير في الجلسة
    update_admin_session_info();
}

// تحديث معلومات المدير في الجلسة
function update_admin_session_info() {
    global $conn;
    
    if (!$conn || !isset($_SESSION['user_id'])) {
        return;
    }
    
    try {
        $stmt = $conn->prepare("
            SELECT admin_type, branch_id 
            FROM users 
            WHERE id = ? AND role = 'admin' AND is_active = 1
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $admin_info = $stmt->fetch();
        
        if ($admin_info) {
            $_SESSION['admin_type'] = $admin_info['admin_type'];
            $_SESSION['branch_id'] = $admin_info['branch_id'];
        }
    } catch (Exception $e) {
        error_log("خطأ في تحديث معلومات المدير: " . $e->getMessage());
    }
}

// التحقق من صلاحية المدير العام فقط
function require_super_admin() {
    check_admin_permission();
    
    if (!is_super_admin()) {
        set_flash_message('error', 'هذه الصفحة مخصصة للمدير العام فقط');
        redirect('dashboard.php');
        exit();
    }
}

// التحقق من صلاحية الوصول للفرع
function check_branch_access($branch_id) {
    if (!can_access_branch($branch_id)) {
        set_flash_message('error', 'ليس لديك صلاحية للوصول إلى هذا الفرع');
        redirect('dashboard.php');
        exit();
    }
}

// التحقق من صلاحية تعديل المستخدم
function can_edit_user($user_id) {
    global $conn;
    
    if (!$conn) {
        return false;
    }
    
    if (is_super_admin()) {
        return true; // المدير العام يمكنه تعديل أي مستخدم
    }
    
    if (is_branch_admin()) {
        // المدير الفرعي يمكنه تعديل المستخدمين في فرعه فقط
        try {
            $stmt = $conn->prepare("
                SELECT branch_id FROM users WHERE id = ?
            ");
            $stmt->execute([$user_id]);
            $user_branch = $stmt->fetchColumn();
            
            return $user_branch == get_admin_branch_id();
        } catch (Exception $e) {
            return false;
        }
    }
    
    return false;
}

// التحقق من صلاحية حذف المستخدم
function can_delete_user($user_id) {
    global $conn;
    
    if (!$conn) {
        return false;
    }
    
    // لا يمكن حذف نفسه
    if ($user_id == $_SESSION['user_id']) {
        return false;
    }
    
    try {
        $stmt = $conn->prepare("
            SELECT role, admin_type, branch_id FROM users WHERE id = ?
        ");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return false;
        }
        
        if (is_super_admin()) {
            // المدير العام يمكنه حذف أي مستخدم عدا المديرين العامين الآخرين
            return $user['admin_type'] !== 'super_admin';
        }
        
        if (is_branch_admin()) {
            // المدير الفرعي يمكنه حذف المعلمين والطلاب في فرعه فقط
            return in_array($user['role'], ['teacher', 'student']) && 
                   $user['branch_id'] == get_admin_branch_id();
        }
        
        return false;
    } catch (Exception $e) {
        return false;
    }
}

// الحصول على قائمة الصفحات المسموح بها للمدير
function get_allowed_admin_pages() {
    $common_pages = [
        'dashboard.php' => 'لوحة التحكم',
        'profile.php' => 'الملف الشخصي',
        'statistics.php' => 'الإحصائيات'
    ];
    
    if (is_super_admin()) {
        return array_merge($common_pages, [
            'branches.php' => 'إدارة الفروع',
            'admin_management.php' => 'إدارة المديرين',
            'teacher_approvals.php' => 'موافقات المعلمين',
            'users.php' => 'إدارة المستخدمين',
            'subjects.php' => 'إدارة المواد',
            'quizzes.php' => 'إدارة الاختبارات',
            'lessons.php' => 'إدارة الدروس',
            'enrollments.php' => 'إدارة التسجيلات'
        ]);
    } else if (is_branch_admin()) {
        return array_merge($common_pages, [
            'users.php' => 'إدارة المستخدمين',
            'subjects.php' => 'إدارة المواد',
            'quizzes.php' => 'إدارة الاختبارات',
            'lessons.php' => 'إدارة الدروس',
            'enrollments.php' => 'إدارة التسجيلات'
        ]);
    }
    
    return $common_pages;
}

// فحص الصلاحية للصفحة الحالية
function check_page_permission() {
    $current_page = basename($_SERVER['PHP_SELF']);
    $allowed_pages = get_allowed_admin_pages();
    
    if (!array_key_exists($current_page, $allowed_pages)) {
        set_flash_message('error', 'ليس لديك صلاحية للوصول إلى هذه الصفحة');
        redirect('dashboard.php');
        exit();
    }
}

?>