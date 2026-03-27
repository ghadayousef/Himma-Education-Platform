<?php
/**
 * دوال النظام الهرمي - منصة همة التوجيهي
 * يحتوي على جميع الدوال المتعلقة بإدارة النظام الهرمي للمناطق والمدراء والوكلاء
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

/**
 * إنشاء الجداول المطلوبة للنظام الهرمي المحدث
 */
function create_hierarchy_tables() {
    global $conn;
    
    if (!$conn) {
        return false;
    }
    
    try {
        // جدول المناطق
        $conn->exec("
            CREATE TABLE IF NOT EXISTS app_d2335_regions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                code VARCHAR(20) UNIQUE NOT NULL,
                description TEXT,
                manager_id INT NULL,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_code (code),
                INDEX idx_active (is_active),
                FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // جدول المديريات
        $conn->exec("
            CREATE TABLE IF NOT EXISTS app_d2335_directorates (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                code VARCHAR(20) NOT NULL,
                region_id INT NOT NULL,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_region (region_id),
                INDEX idx_code (code),
                INDEX idx_active (is_active),
                FOREIGN KEY (region_id) REFERENCES app_d2335_regions(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // جدول مدراء المناطق
        $conn->exec("
            CREATE TABLE IF NOT EXISTS app_d2335_region_managers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                region_id INT NOT NULL,
                assigned_by INT NOT NULL,
                permissions JSON,
                is_active BOOLEAN DEFAULT TRUE,
                assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deactivated_at TIMESTAMP NULL,
                INDEX idx_user (user_id),
                INDEX idx_region (region_id),
                INDEX idx_active (is_active),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (region_id) REFERENCES app_d2335_regions(id) ON DELETE CASCADE,
                FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // جدول وكلاء المناطق (محدث ليشمل المديريات)
        $conn->exec("
            CREATE TABLE IF NOT EXISTS app_d2335_region_deputies (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                region_id INT NOT NULL,
                directorate VARCHAR(100) NOT NULL,
                directorate_id INT NULL,
                assigned_by INT NOT NULL,
                permissions JSON,
                is_active BOOLEAN DEFAULT TRUE,
                assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                deactivated_at TIMESTAMP NULL,
                INDEX idx_user (user_id),
                INDEX idx_region (region_id),
                INDEX idx_directorate (directorate_id),
                INDEX idx_active (is_active),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (region_id) REFERENCES app_d2335_regions(id) ON DELETE CASCADE,
                FOREIGN KEY (directorate_id) REFERENCES app_d2335_directorates(id) ON DELETE SET NULL,
                FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // جدول طلبات انضمام المعلمين (محدث ليشمل المديريات)
        $conn->exec("
            CREATE TABLE IF NOT EXISTS app_d2335_teacher_applications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                teacher_name VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL,
                phone VARCHAR(20),
                region_id INT NOT NULL,
                directorate VARCHAR(100) NOT NULL,
                directorate_id INT NULL,
                subject_specialization VARCHAR(100) NOT NULL,
                experience_years INT DEFAULT 0,
                qualifications TEXT,
                cv_file VARCHAR(255),
                status ENUM('pending', 'under_review', 'approved', 'rejected') DEFAULT 'pending',
                reviewed_by INT NULL,
                review_notes TEXT,
                teacher_user_id INT NULL,
                submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                reviewed_at TIMESTAMP NULL,
                INDEX idx_status (status),
                INDEX idx_region (region_id),
                INDEX idx_directorate (directorate_id),
                INDEX idx_email (email),
                FOREIGN KEY (region_id) REFERENCES app_d2335_regions(id) ON DELETE CASCADE,
                FOREIGN KEY (directorate_id) REFERENCES app_d2335_directorates(id) ON DELETE SET NULL,
                FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (teacher_user_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // إدراج المناطق الافتراضية إذا لم تكن موجودة
        $stmt = $conn->prepare("SELECT COUNT(*) FROM app_d2335_regions");
        $stmt->execute();
        $count = $stmt->fetchColumn();
        
        if ($count == 0) {
            $regions = [
                ['شمال غزة', 'NORTH_GAZA', 'منطقة شمال غزة التعليمية'],
                ['غزة', 'GAZA', 'منطقة غزة التعليمية'],
                ['الوسطى', 'MIDDLE', 'منطقة الوسطى التعليمية'],
                ['الجنوب', 'SOUTH', 'منطقة الجنوب التعليمية (خان يونس ورفح)']
            ];
            
            $region_ids = [];
            foreach ($regions as $region) {
                $stmt = $conn->prepare("
                    INSERT INTO app_d2335_regions (name, code, description, is_active) 
                    VALUES (?, ?, ?, 1)
                ");
                $stmt->execute($region);
                $region_ids[$region[1]] = $conn->lastInsertId();
            }
            
            // إدراج المديريات الافتراضية
            $directorates = [
                // شمال غزة
                ['مديرية شمال غزة', 'NORTH_GAZA_DIR', $region_ids['NORTH_GAZA']],
                ['مديرية بيت حانون', 'BEIT_HANOUN_DIR', $region_ids['NORTH_GAZA']],
                ['مديرية بيت لاهيا', 'BEIT_LAHIA_DIR', $region_ids['NORTH_GAZA']],
                ['مديرية جباليا', 'JABALIA_DIR', $region_ids['NORTH_GAZA']],
                
                // غزة
                ['مديرية غزة', 'GAZA_DIR', $region_ids['GAZA']],
                ['مديرية الشاطئ', 'BEACH_DIR', $region_ids['GAZA']],
                ['مديرية الزيتون', 'ZEITOUN_DIR', $region_ids['GAZA']],
                ['مديرية التفاح', 'TUFFAH_DIR', $region_ids['GAZA']],
                
                // الوسطى
                ['مديرية الوسطى', 'MIDDLE_DIR', $region_ids['MIDDLE']],
                ['مديرية دير البلح', 'DEIR_BALAH_DIR', $region_ids['MIDDLE']],
                ['مديرية النصيرات', 'NUSEIRAT_DIR', $region_ids['MIDDLE']],
                ['مديرية المغازي', 'MAGHAZI_DIR', $region_ids['MIDDLE']],
                
                // الجنوب
                ['مديرية خان يونس', 'KHAN_YOUNIS_DIR', $region_ids['SOUTH']],
                ['مديرية رفح', 'RAFAH_DIR', $region_ids['SOUTH']],
                ['مديرية أبسان الكبيرة', 'ABASAN_DIR', $region_ids['SOUTH']],
                ['مديرية القرارة', 'QARARA_DIR', $region_ids['SOUTH']]
            ];
            
            foreach ($directorates as $directorate) {
                $stmt = $conn->prepare("
                    INSERT INTO app_d2335_directorates (name, code, region_id, is_active) 
                    VALUES (?, ?, ?, 1)
                ");
                $stmt->execute($directorate);
            }
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("خطأ في create_hierarchy_tables: " . $e->getMessage());
        return false;
    }
}

/**
 * الحصول على دور المستخدم في النظام الهرمي
 */
function get_user_hierarchy_role($user_id) {
    global $conn;
    
    if (!$conn) {
        return ['role' => null, 'region_id' => null, 'region_name' => null];
    }
    
    try {
        // التحقق من كون المستخدم مدير عام
        $stmt = $conn->prepare("SELECT role FROM users WHERE id = ? AND role = 'admin'");
        $stmt->execute([$user_id]);
        if ($stmt->fetch()) {
            return ['role' => 'super_admin', 'region_id' => null, 'region_name' => null];
        }
        
        // التحقق من كون المستخدم مدير منطقة
        $stmt = $conn->prepare("
            SELECT rm.region_id, r.name as region_name 
            FROM app_d2335_region_managers rm 
            JOIN app_d2335_regions r ON rm.region_id = r.id 
            WHERE rm.user_id = ? AND rm.is_active = 1
        ");
        $stmt->execute([$user_id]);
        $manager = $stmt->fetch();
        
        if ($manager) {
            return [
                'role' => 'region_manager',
                'region_id' => $manager['region_id'],
                'region_name' => $manager['region_name']
            ];
        }
        
        // التحقق من كون المستخدم وكيل
        $stmt = $conn->prepare("
            SELECT rd.region_id, r.name as region_name, rd.directorate, rd.directorate_id, d.name as directorate_name
            FROM app_d2335_region_deputies rd 
            JOIN app_d2335_regions r ON rd.region_id = r.id 
            LEFT JOIN app_d2335_directorates d ON rd.directorate_id = d.id
            WHERE rd.user_id = ? AND rd.is_active = 1
        ");
        $stmt->execute([$user_id]);
        $deputy = $stmt->fetch();
        
        if ($deputy) {
            return [
                'role' => 'deputy',
                'region_id' => $deputy['region_id'],
                'region_name' => $deputy['region_name'],
                'directorate' => $deputy['directorate'],
                'directorate_id' => $deputy['directorate_id'],
                'directorate_name' => $deputy['directorate_name']
            ];
        }
        
        // مستخدم عادي
        return ['role' => 'user', 'region_id' => null, 'region_name' => null];
        
    } catch (Exception $e) {
        error_log("خطأ في get_user_hierarchy_role: " . $e->getMessage());
        return ['role' => null, 'region_id' => null, 'region_name' => null];
    }
}

/**
 * الحصول على جميع المناطق
 */
function get_all_regions($active_only = true) {
    global $conn;
    
    if (!$conn) {
        return [];
    }
    
    try {
        $where_clause = $active_only ? "WHERE r.is_active = 1" : "";
        
        $stmt = $conn->prepare("
            SELECT 
                r.*,
                u.full_name as manager_name,
                (SELECT COUNT(*) FROM app_d2335_region_managers rm WHERE rm.region_id = r.id AND rm.is_active = 1) as managers_count,
                (SELECT COUNT(*) FROM app_d2335_region_deputies rd WHERE rd.region_id = r.id AND rd.is_active = 1) as deputies_count,
                (SELECT COUNT(*) FROM app_d2335_directorates d WHERE d.region_id = r.id AND d.is_active = 1) as directorates_count
            FROM app_d2335_regions r
            LEFT JOIN users u ON r.manager_id = u.id
            $where_clause
            ORDER BY r.name
        ");
        $stmt->execute();
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("خطأ في get_all_regions: " . $e->getMessage());
        return [];
    }
}

/**
 * الحصول على معلومات منطقة محددة
 */
function get_region_info($region_id) {
    global $conn;
    
    if (!$conn) {
        return null;
    }
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                r.*,
                u.full_name as manager_name,
                u.email as manager_email
            FROM app_d2335_regions r
            LEFT JOIN users u ON r.manager_id = u.id
            WHERE r.id = ?
        ");
        $stmt->execute([$region_id]);
        return $stmt->fetch();
        
    } catch (Exception $e) {
        error_log("خطأ في get_region_info: " . $e->getMessage());
        return null;
    }
}

/**
 * الحصول على المديريات لمنطقة محددة
 */
function get_region_directorates($region_id) {
    global $conn;
    
    if (!$conn) {
        return [];
    }
    
    try {
        $stmt = $conn->prepare("
            SELECT * FROM app_d2335_directorates 
            WHERE region_id = ? AND is_active = 1 
            ORDER BY name
        ");
        $stmt->execute([$region_id]);
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("خطأ في get_region_directorates: " . $e->getMessage());
        return [];
    }
}

/**
 * الحصول على إحصائيات المنطقة
 */
function get_region_statistics($region_id, $user_role = null) {
    global $conn;
    
    if (!$conn) {
        return [];
    }
    
    try {
        $stats = [];
        
        // إحصائيات طلبات المعلمين
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as under_review
            FROM app_d2335_teacher_applications 
            WHERE region_id = ?
        ");
        $stmt->execute([$region_id]);
        $stats['applications'] = $stmt->fetch();
        
        // عدد الوكلاء في المنطقة
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM app_d2335_region_deputies 
            WHERE region_id = ? AND is_active = 1
        ");
        $stmt->execute([$region_id]);
        $stats['deputies_count'] = $stmt->fetchColumn();
        
        // عدد المدراء في المنطقة
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM app_d2335_region_managers 
            WHERE region_id = ? AND is_active = 1
        ");
        $stmt->execute([$region_id]);
        $stats['managers_count'] = $stmt->fetchColumn();
        
        // عدد المديريات في المنطقة
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM app_d2335_directorates 
            WHERE region_id = ? AND is_active = 1
        ");
        $stmt->execute([$region_id]);
        $stats['directorates_count'] = $stmt->fetchColumn();
        
        return $stats;
        
    } catch (Exception $e) {
        error_log("خطأ في get_region_statistics: " . $e->getMessage());
        return [];
    }
}

/**
 * الحصول على طلبات انضمام المعلمين
 */
function get_teacher_applications($status = null, $region_id = null, $deputy_id = null, $directorate_id = null) {
    global $conn;
    
    if (!$conn) {
        return [];
    }
    
    try {
        $where_conditions = [];
        $params = [];
        
        if ($status) {
            $where_conditions[] = "ta.status = ?";
            $params[] = $status;
        }
        
        if ($region_id) {
            $where_conditions[] = "ta.region_id = ?";
            $params[] = $region_id;
        }
        
        if ($directorate_id) {
            $where_conditions[] = "ta.directorate_id = ?";
            $params[] = $directorate_id;
        }
        
        if ($deputy_id) {
            $where_conditions[] = "ta.reviewed_by = ?";
            $params[] = $deputy_id;
        }
        
        $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
        
        $stmt = $conn->prepare("
            SELECT 
                ta.*,
                r.name as region_name,
                d.name as directorate_name,
                u.full_name as reviewer_name
            FROM app_d2335_teacher_applications ta
            LEFT JOIN app_d2335_regions r ON ta.region_id = r.id
            LEFT JOIN app_d2335_directorates d ON ta.directorate_id = d.id
            LEFT JOIN users u ON ta.reviewed_by = u.id
            $where_clause
            ORDER BY ta.submitted_at DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("خطأ في get_teacher_applications: " . $e->getMessage());
        return [];
    }
}

/**
 * مراجعة طلب انضمام معلم
 */
function review_teacher_application($application_id, $reviewer_id, $reviewer_role, $status, $notes = '') {
    global $conn;
    
    if (!$conn) {
        return false;
    }
    
    try {
        $conn->beginTransaction();
        
        // تحديث حالة الطلب
        $stmt = $conn->prepare("
            UPDATE app_d2335_teacher_applications 
            SET status = ?, reviewed_by = ?, review_notes = ?, reviewed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$status, $reviewer_id, $notes, $application_id]);
        
        // إذا تم قبول الطلب، إنشاء حساب للمعلم
        if ($status === 'approved') {
            $stmt = $conn->prepare("SELECT * FROM app_d2335_teacher_applications WHERE id = ?");
            $stmt->execute([$application_id]);
            $application = $stmt->fetch();
            
            if ($application) {
                // التحقق من عدم وجود حساب بنفس البريد الإلكتروني
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$application['email']]);
                
                if (!$stmt->fetch()) {
                    // إنشاء حساب جديد للمعلم
                    $default_password = password_hash('123456', PASSWORD_DEFAULT);
                    $username = strtolower(str_replace(' ', '_', $application['teacher_name'])) . '_' . rand(100, 999);
                    
                    $stmt = $conn->prepare("
                        INSERT INTO users (username, full_name, email, password, phone, role, is_active, email_verified)
                        VALUES (?, ?, ?, ?, ?, 'teacher', 1, 1)
                    ");
                    $stmt->execute([
                        $username,
                        $application['teacher_name'],
                        $application['email'],
                        $default_password,
                        $application['phone']
                    ]);
                    
                    $teacher_id = $conn->lastInsertId();
                    
                    // ربط المعلم بالطلب
                    $stmt = $conn->prepare("
                        UPDATE app_d2335_teacher_applications 
                        SET teacher_user_id = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$teacher_id, $application_id]);
                }
            }
        }
        
        $conn->commit();
        return true;
        
    } catch (Exception $e) {
        $conn->rollBack();
        error_log("خطأ في review_teacher_application: " . $e->getMessage());
        return false;
    }
}

/**
 * الحصول على مدراء المناطق
 */
function get_region_managers() {
    global $conn;
    
    if (!$conn) {
        return [];
    }
    
    try {
        $stmt = $conn->prepare("
            SELECT 
                rm.*,
                u.full_name,
                u.email,
                r.name as region_name
            FROM app_d2335_region_managers rm
            JOIN users u ON rm.user_id = u.id
            JOIN app_d2335_regions r ON rm.region_id = r.id
            WHERE rm.is_active = 1
            ORDER BY r.name, u.full_name
        ");
        $stmt->execute();
        return $stmt->fetchAll();
        
    } catch (Exception $e) {
        error_log("خطأ في get_region_managers: " . $e->getMessage());
        return [];
    }
}

// إنشاء الجداول تلقائياً عند تحميل الملف
create_hierarchy_tables();

?>