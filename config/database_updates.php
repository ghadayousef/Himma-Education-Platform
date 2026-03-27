<?php
/**
 * تحديثات قاعدة البيانات للنظام الهرمي
 * منصة همة التوجيهي
 */

require_once 'database.php';

function update_database_for_hierarchy() {
    global $conn;
    
    if (!$conn) {
        throw new Exception("لا يوجد اتصال بقاعدة البيانات");
    }

    $updates = [
        // 1. إضافة جدول الفروع
        "branches" => "
            CREATE TABLE IF NOT EXISTS branches (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                manager_id INT NULL,
                address TEXT,
                phone VARCHAR(20),
                email VARCHAR(100),
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_manager (manager_id),
                INDEX idx_active (is_active),
                FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        // 2. إضافة جدول صلاحيات المديرين
        "admin_permissions" => "
            CREATE TABLE IF NOT EXISTS admin_permissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                admin_id INT NOT NULL,
                branch_id INT NULL,
                permissions JSON,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_admin (admin_id),
                INDEX idx_branch (branch_id),
                UNIQUE KEY unique_admin_branch (admin_id, branch_id),
                FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        // 3. إضافة جدول موافقات المعلمين
        "teacher_approvals" => "
            CREATE TABLE IF NOT EXISTS teacher_approvals (
                id INT AUTO_INCREMENT PRIMARY KEY,
                teacher_id INT NOT NULL,
                branch_id INT NOT NULL,
                super_admin_id INT NULL,
                status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                notes TEXT,
                requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                approved_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_teacher (teacher_id),
                INDEX idx_branch (branch_id),
                INDEX idx_status (status),
                INDEX idx_super_admin (super_admin_id),
                FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
                FOREIGN KEY (super_admin_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        "
    ];

    // تنفيذ إنشاء الجداول
    foreach ($updates as $table_name => $sql) {
        try {
            $conn->exec($sql);
            echo "<p>✅ تم إنشاء جدول {$table_name} بنجاح</p>";
        } catch (PDOException $e) {
            echo "<p>❌ خطأ في إنشاء جدول {$table_name}: " . $e->getMessage() . "</p>";
        }
    }

    // إضافة الحقول الجديدة لجدول users
    $user_updates = [
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS admin_type ENUM('super_admin', 'branch_admin', 'regular_admin') NULL DEFAULT NULL",
        "ALTER TABLE users ADD COLUMN IF NOT EXISTS branch_id INT NULL DEFAULT NULL",
        "ALTER TABLE users ADD INDEX IF NOT EXISTS idx_admin_type (admin_type)",
        "ALTER TABLE users ADD INDEX IF NOT EXISTS idx_branch_id (branch_id)"
    ];

    foreach ($user_updates as $sql) {
        try {
            $conn->exec($sql);
            echo "<p>✅ تم تحديث جدول users بنجاح</p>";
        } catch (PDOException $e) {
            // الحقل موجود بالفعل أو خطأ آخر
            if (strpos($e->getMessage(), 'Duplicate column') === false) {
                echo "<p>⚠️ تحديث جدول users: " . $e->getMessage() . "</p>";
            }
        }
    }

    // إضافة العلاقات الخارجية
    try {
        $conn->exec("ALTER TABLE users ADD CONSTRAINT fk_users_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL");
        echo "<p>✅ تم إضافة العلاقة الخارجية للفروع</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate foreign key') === false) {
            echo "<p>⚠️ العلاقة الخارجية: " . $e->getMessage() . "</p>";
        }
    }

    // إدراج بيانات تجريبية
    insert_hierarchy_sample_data();
}

function insert_hierarchy_sample_data() {
    global $conn;
    
    try {
        // التحقق من وجود مدير عام
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE admin_type = 'super_admin'");
        $stmt->execute();
        $super_admin_count = $stmt->fetchColumn();
        
        if ($super_admin_count == 0) {
            // تحديث المدير الحالي ليصبح مدير عام
            $stmt = $conn->prepare("UPDATE users SET admin_type = 'super_admin' WHERE role = 'admin' LIMIT 1");
            $stmt->execute();
            echo "<p>✅ تم تعيين المدير العام</p>";
        }
        
        // إضافة فروع تجريبية
        $branches = [
            ['الفرع الرئيسي - غزة', 'الفرع الرئيسي في مدينة غزة', 'غزة - شارع الجامعة', '08-2123456', 'gaza@himma.edu'],
            ['فرع الشمال', 'فرع شمال غزة وجباليا', 'جباليا - شارع فلسطين', '08-2123457', 'north@himma.edu'],
            ['فرع الوسطى', 'فرع المحافظة الوسطى', 'دير البلح - شارع الشهداء', '08-2123458', 'middle@himma.edu'],
            ['فرع خان يونس', 'فرع محافظة خان يونس', 'خان يونس - شارع جمال عبد الناصر', '08-2123459', 'khanyounis@himma.edu'],
            ['فرع رفح', 'فرع محافظة رفح', 'رفح - شارع الشهداء', '08-2123460', 'rafah@himma.edu']
        ];
        
        foreach ($branches as $branch) {
            $stmt = $conn->prepare("
                INSERT IGNORE INTO branches (name, description, address, phone, email, is_active) 
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute($branch);
        }
        echo "<p>✅ تم إضافة الفروع التجريبية</p>";
        
        // إضافة مديرين فرعيين تجريبيين
        $password = password_hash('123456', PASSWORD_DEFAULT);
        $branch_admins = [
            ['branch_admin_1', 'مدير فرع غزة', 'gaza.admin@himma.edu', 1],
            ['branch_admin_2', 'مدير فرع الشمال', 'north.admin@himma.edu', 2],
            ['branch_admin_3', 'مدير فرع الوسطى', 'middle.admin@himma.edu', 3]
        ];
        
        foreach ($branch_admins as $admin) {
            try {
                $stmt = $conn->prepare("
                    INSERT INTO users (username, full_name, email, password, role, admin_type, branch_id, is_active, email_verified) 
                    VALUES (?, ?, ?, ?, 'admin', 'branch_admin', ?, 1, 1)
                ");
                $stmt->execute([$admin[0], $admin[1], $admin[2], $password, $admin[3]]);
                
                $admin_id = $conn->lastInsertId();
                
                // تحديث مدير الفرع
                $stmt = $conn->prepare("UPDATE branches SET manager_id = ? WHERE id = ?");
                $stmt->execute([$admin_id, $admin[3]]);
                
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                    echo "<p>⚠️ خطأ في إضافة مدير فرعي: " . $e->getMessage() . "</p>";
                }
            }
        }
        echo "<p>✅ تم إضافة المديرين الفرعيين التجريبيين</p>";
        
    } catch (Exception $e) {
        echo "<p>❌ خطأ في إدراج البيانات التجريبية: " . $e->getMessage() . "</p>";
    }
}

// تشغيل التحديثات إذا تم استدعاء الملف مباشرة
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) {
    echo "<h2>تحديث قاعدة البيانات للنظام الهرمي</h2>";
    try {
        update_database_for_hierarchy();
        echo "<h3>✅ تم تحديث قاعدة البيانات بنجاح</h3>";
    } catch (Exception $e) {
        echo "<h3>❌ خطأ في التحديث: " . $e->getMessage() . "</h3>";
    }
}

?>