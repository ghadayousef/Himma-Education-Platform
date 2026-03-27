<?php
/**
 * دوال إدارية للنظام الهرمي
 * منصة همة التوجيهي
 */

// التحقق من نوع المدير
if (!function_exists('is_super_admin')) {
    function is_super_admin() {
        return isset($_SESSION['admin_type']) && $_SESSION['admin_type'] === 'super_admin';
    }
}

if (!function_exists('is_branch_admin')) {
    function is_branch_admin() {
        return isset($_SESSION['admin_type']) && $_SESSION['admin_type'] === 'branch_admin';
    }
}

if (!function_exists('get_admin_branch_id')) {
    function get_admin_branch_id() {
        return $_SESSION['branch_id'] ?? null;
    }
}

// التحقق من صلاحية الوصول للفرع
if (!function_exists('can_access_branch')) {
    function can_access_branch($branch_id) {
        if (is_super_admin()) {
            return true; // المدير العام يمكنه الوصول لجميع الفروع
        }
        
        if (is_branch_admin()) {
            return get_admin_branch_id() == $branch_id; // المدير الفرعي يمكنه الوصول لفرعه فقط
        }
        
        return false;
    }
}

// الحصول على معلومات الفرع
if (!function_exists('get_branch_info')) {
    function get_branch_info($branch_id) {
        global $conn;
        
        if (!$conn) {
            return null;
        }
        
        try {
            $stmt = $conn->prepare("
                SELECT b.*, u.full_name as manager_name 
                FROM branches b 
                LEFT JOIN users u ON b.manager_id = u.id 
                WHERE b.id = ?
            ");
            $stmt->execute([$branch_id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("خطأ في جلب معلومات الفرع: " . $e->getMessage());
            return null;
        }
    }
}

// الحصول على جميع الفروع (للمدير العام) أو فرع واحد (للمدير الفرعي)
if (!function_exists('get_accessible_branches')) {
    function get_accessible_branches() {
        global $conn;
        
        if (!$conn) {
            return [];
        }
        
        try {
            if (is_super_admin()) {
                // المدير العام يرى جميع الفروع
                $stmt = $conn->prepare("
                    SELECT b.*, u.full_name as manager_name 
                    FROM branches b 
                    LEFT JOIN users u ON b.manager_id = u.id 
                    WHERE b.is_active = 1 
                    ORDER BY b.name
                ");
                $stmt->execute();
            } else if (is_branch_admin()) {
                // المدير الفرعي يرى فرعه فقط
                $branch_id = get_admin_branch_id();
                $stmt = $conn->prepare("
                    SELECT b.*, u.full_name as manager_name 
                    FROM branches b 
                    LEFT JOIN users u ON b.manager_id = u.id 
                    WHERE b.id = ? AND b.is_active = 1
                ");
                $stmt->execute([$branch_id]);
            } else {
                return [];
            }
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("خطأ في جلب الفروع: " . $e->getMessage());
            return [];
        }
    }
}

// الحصول على المعلمين حسب الفرع
if (!function_exists('get_teachers_by_branch')) {
    function get_teachers_by_branch($branch_id = null) {
        global $conn;
        
        if (!$conn) {
            return [];
        }
        
        try {
            if (is_super_admin()) {
                // المدير العام يرى جميع المعلمين
                if ($branch_id) {
                    $stmt = $conn->prepare("
                        SELECT u.*, b.name as branch_name 
                        FROM users u 
                        LEFT JOIN branches b ON u.branch_id = b.id 
                        WHERE u.role = 'teacher' AND u.branch_id = ?
                        ORDER BY u.full_name
                    ");
                    $stmt->execute([$branch_id]);
                } else {
                    $stmt = $conn->prepare("
                        SELECT u.*, b.name as branch_name 
                        FROM users u 
                        LEFT JOIN branches b ON u.branch_id = b.id 
                        WHERE u.role = 'teacher' 
                        ORDER BY u.full_name
                    ");
                    $stmt->execute();
                }
            } else if (is_branch_admin()) {
                // المدير الفرعي يرى معلمي فرعه فقط
                $admin_branch_id = get_admin_branch_id();
                $stmt = $conn->prepare("
                    SELECT u.*, b.name as branch_name 
                    FROM users u 
                    LEFT JOIN branches b ON u.branch_id = b.id 
                    WHERE u.role = 'teacher' AND u.branch_id = ?
                    ORDER BY u.full_name
                ");
                $stmt->execute([$admin_branch_id]);
            } else {
                return [];
            }
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("خطأ في جلب المعلمين: " . $e->getMessage());
            return [];
        }
    }
}

// الحصول على الطلاب حسب الفرع
if (!function_exists('get_students_by_branch')) {
    function get_students_by_branch($branch_id = null) {
        global $conn;
        
        if (!$conn) {
            return [];
        }
        
        try {
            if (is_super_admin()) {
                // المدير العام يرى جميع الطلاب
                if ($branch_id) {
                    $stmt = $conn->prepare("
                        SELECT u.*, b.name as branch_name 
                        FROM users u 
                        LEFT JOIN branches b ON u.branch_id = b.id 
                        WHERE u.role = 'student' AND u.branch_id = ?
                        ORDER BY u.full_name
                    ");
                    $stmt->execute([$branch_id]);
                } else {
                    $stmt = $conn->prepare("
                        SELECT u.*, b.name as branch_name 
                        FROM users u 
                        LEFT JOIN branches b ON u.branch_id = b.id 
                        WHERE u.role = 'student' 
                        ORDER BY u.full_name
                    ");
                    $stmt->execute();
                }
            } else if (is_branch_admin()) {
                // المدير الفرعي يرى طلاب فرعه فقط
                $admin_branch_id = get_admin_branch_id();
                $stmt = $conn->prepare("
                    SELECT u.*, b.name as branch_name 
                    FROM users u 
                    LEFT JOIN branches b ON u.branch_id = b.id 
                    WHERE u.role = 'student' AND u.branch_id = ?
                    ORDER BY u.full_name
                ");
                $stmt->execute([$admin_branch_id]);
            } else {
                return [];
            }
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("خطأ في جلب الطلاب: " . $e->getMessage());
            return [];
        }
    }
}

// طلب موافقة لمعلم جديد
if (!function_exists('request_teacher_approval')) {
    function request_teacher_approval($teacher_id, $branch_id, $notes = '') {
        global $conn;
        
        if (!$conn) {
            return false;
        }
        
        try {
            // التحقق من عدم وجود طلب سابق معلق
            $stmt = $conn->prepare("
                SELECT COUNT(*) FROM teacher_approvals 
                WHERE teacher_id = ? AND status = 'pending'
            ");
            $stmt->execute([$teacher_id]);
            
            if ($stmt->fetchColumn() > 0) {
                return false; // يوجد طلب معلق بالفعل
            }
            
            // إدراج طلب الموافقة
            $stmt = $conn->prepare("
                INSERT INTO teacher_approvals (teacher_id, branch_id, notes, requested_at) 
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$teacher_id, $branch_id, $notes]);
            
            // إرسال إشعار للمدير العام
            $teacher_info = get_user_info($teacher_id);
            $branch_info = get_branch_info($branch_id);
            
            $super_admins = get_super_admins();
            foreach ($super_admins as $admin) {
                send_notification(
                    $admin['id'],
                    'طلب موافقة معلم جديد',
                    "يوجد طلب موافقة لمعلم جديد: {$teacher_info['full_name']} في فرع {$branch_info['name']}",
                    'system'
                );
            }
            
            return true;
        } catch (Exception $e) {
            error_log("خطأ في طلب الموافقة: " . $e->getMessage());
            return false;
        }
    }
}

// الموافقة على معلم أو رفضه
if (!function_exists('approve_teacher')) {
    function approve_teacher($approval_id, $status, $super_admin_id, $notes = '') {
        global $conn;
        
        if (!$conn || !in_array($status, ['approved', 'rejected'])) {
            return false;
        }
        
        try {
            $conn->beginTransaction();
            
            // الحصول على معلومات الطلب
            $stmt = $conn->prepare("
                SELECT ta.*, u.full_name as teacher_name, b.name as branch_name 
                FROM teacher_approvals ta 
                JOIN users u ON ta.teacher_id = u.id 
                JOIN branches b ON ta.branch_id = b.id 
                WHERE ta.id = ? AND ta.status = 'pending'
            ");
            $stmt->execute([$approval_id]);
            $approval = $stmt->fetch();
            
            if (!$approval) {
                $conn->rollBack();
                return false;
            }
            
            // تحديث حالة الطلب
            $stmt = $conn->prepare("
                UPDATE teacher_approvals 
                SET status = ?, super_admin_id = ?, notes = ?, approved_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$status, $super_admin_id, $notes, $approval_id]);
            
            if ($status === 'approved') {
                // تفعيل المعلم وربطه بالفرع
                $stmt = $conn->prepare("
                    UPDATE users 
                    SET is_active = 1, branch_id = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$approval['branch_id'], $approval['teacher_id']]);
                
                $message = "تم قبول طلبك للتدريس في فرع {$approval['branch_name']}";
            } else {
                $message = "تم رفض طلبك للتدريس في فرع {$approval['branch_name']}";
                if ($notes) {
                    $message .= ". السبب: $notes";
                }
            }
            
            // إرسال إشعار للمعلم
            send_notification(
                $approval['teacher_id'],
                'نتيجة طلب التدريس',
                $message,
                'system'
            );
            
            $conn->commit();
            return true;
            
        } catch (Exception $e) {
            $conn->rollBack();
            error_log("خطأ في الموافقة على المعلم: " . $e->getMessage());
            return false;
        }
    }
}

// الحصول على طلبات الموافقة المعلقة
if (!function_exists('get_pending_teacher_approvals')) {
    function get_pending_teacher_approvals() {
        global $conn;
        
        if (!$conn || !is_super_admin()) {
            return [];
        }
        
        try {
            $stmt = $conn->prepare("
                SELECT ta.*, u.full_name as teacher_name, u.email as teacher_email, 
                       b.name as branch_name 
                FROM teacher_approvals ta 
                JOIN users u ON ta.teacher_id = u.id 
                JOIN branches b ON ta.branch_id = b.id 
                WHERE ta.status = 'pending' 
                ORDER BY ta.requested_at DESC
            ");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("خطأ في جلب طلبات الموافقة: " . $e->getMessage());
            return [];
        }
    }
}

// الحصول على المديرين العامين
if (!function_exists('get_super_admins')) {
    function get_super_admins() {
        global $conn;
        
        if (!$conn) {
            return [];
        }
        
        try {
            $stmt = $conn->prepare("
                SELECT * FROM users 
                WHERE admin_type = 'super_admin' AND is_active = 1
            ");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("خطأ في جلب المديرين العامين: " . $e->getMessage());
            return [];
        }
    }
}

// الحصول على معلومات المستخدم
if (!function_exists('get_user_info')) {
    function get_user_info($user_id) {
        global $conn;
        
        if (!$conn) {
            return null;
        }
        
        try {
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("خطأ في جلب معلومات المستخدم: " . $e->getMessage());
            return null;
        }
    }
}

// إحصائيات الفرع
if (!function_exists('get_branch_statistics')) {
    function get_branch_statistics($branch_id = null) {
        global $conn;
        
        if (!$conn) {
            return [];
        }
        
        try {
            $where_clause = '';
            $params = [];
            
            if ($branch_id) {
                $where_clause = 'WHERE u.branch_id = ?';
                $params[] = $branch_id;
            } else if (is_branch_admin()) {
                $where_clause = 'WHERE u.branch_id = ?';
                $params[] = get_admin_branch_id();
            }
            
            // عدد المعلمين
            $stmt = $conn->prepare("
                SELECT COUNT(*) FROM users u 
                $where_clause AND u.role = 'teacher' AND u.is_active = 1
            ");
            $stmt->execute($params);
            $teachers_count = $stmt->fetchColumn();
            
            // عدد الطلاب
            $stmt = $conn->prepare("
                SELECT COUNT(*) FROM users u 
                $where_clause AND u.role = 'student' AND u.is_active = 1
            ");
            $stmt->execute($params);
            $students_count = $stmt->fetchColumn();
            
            // عدد المواد
            $subjects_where = $branch_id ? 'WHERE s.teacher_id IN (SELECT id FROM users WHERE branch_id = ? AND role = "teacher")' : '';
            $subjects_params = $branch_id ? [$branch_id] : [];
            
            if (is_branch_admin() && !$branch_id) {
                $subjects_where = 'WHERE s.teacher_id IN (SELECT id FROM users WHERE branch_id = ? AND role = "teacher")';
                $subjects_params = [get_admin_branch_id()];
            }
            
            $stmt = $conn->prepare("
                SELECT COUNT(*) FROM subjects s 
                $subjects_where AND s.is_active = 1
            ");
            $stmt->execute($subjects_params);
            $subjects_count = $stmt->fetchColumn();
            
            // عدد التسجيلات
            $stmt = $conn->prepare("
                SELECT COUNT(*) FROM enrollments e 
                JOIN subjects s ON e.subject_id = s.id 
                JOIN users u ON s.teacher_id = u.id 
                " . ($branch_id || is_branch_admin() ? 'WHERE u.branch_id = ?' : '')
            );
            
            if ($branch_id) {
                $stmt->execute([$branch_id]);
            } else if (is_branch_admin()) {
                $stmt->execute([get_admin_branch_id()]);
            } else {
                $stmt->execute();
            }
            $enrollments_count = $stmt->fetchColumn();
            
            return [
                'teachers' => $teachers_count,
                'students' => $students_count,
                'subjects' => $subjects_count,
                'enrollments' => $enrollments_count
            ];
            
        } catch (Exception $e) {
            error_log("خطأ في جلب الإحصائيات: " . $e->getMessage());
            return [
                'teachers' => 0,
                'students' => 0,
                'subjects' => 0,
                'enrollments' => 0
            ];
        }
    }
}

?>