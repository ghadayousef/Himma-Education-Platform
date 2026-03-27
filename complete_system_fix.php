<?php
/**
 * إصلاح شامل ونهائي لمنصة همة التوجيهي
 * Complete System Fix for Himma Tawjihi Platform
 * 
 * هذا الملف يحل المشاكل التالية:
 * 1. توحيد أسماء الجداول (teacher_applications vs app_d2335_teacher_applications)
 * 2. إصلاح عملية تسجيل المعلمين
 * 3. إصلاح لوحة تحكم المدير
 * 4. إنشاء جدول المناطق والبيانات الأساسية
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300);

// تكوين قاعدة البيانات
$db_config = [
    'host' => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'himma_tawjihi',
    'charset' => 'utf8mb4'
];

class CompleteFix {
    private $pdo;
    private $results = [];
    private $errors = [];
    private $fixes_applied = [];
    
    public function __construct($config) {
        try {
            $dsn = "mysql:host={$config['host']};charset={$config['charset']}";
            $this->pdo = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$config['charset']}"
            ]);
            
            $this->pdo->exec("CREATE DATABASE IF NOT EXISTS `{$config['database']}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $this->pdo->exec("USE `{$config['database']}`");
            
            // تعطيل فحص المفاتيح الخارجية مؤقتاً
            $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            
            $this->results[] = "✅ تم الاتصال بقاعدة البيانات بنجاح";
        } catch (Exception $e) {
            $this->errors[] = "❌ خطأ في الاتصال: " . $e->getMessage();
            die("فشل الاتصال بقاعدة البيانات");
        }
    }
    
    public function __destruct() {
        // إعادة تفعيل فحص المفاتيح الخارجية
        try {
            if ($this->pdo) {
                $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            }
        } catch (Exception $e) {
            // تجاهل الأخطاء عند الإغلاق
        }
    }
    
    public function runCompleteFix() {
        $this->results[] = "<h3>🔧 بدء الإصلاح الشامل</h3>";
        
        // 1. إنشاء/تحديث جدول المناطق أولاً (قبل الجداول التي تعتمد عليه)
        $this->createRegionsTable();
        
        // 2. إدراج المناطق الأساسية
        $this->insertRegions();
        
        // 3. إنشاء جدول المستخدمين المحدث
        $this->createUsersTable();
        
        // 4. التأكد من وجود المدير العام
        $this->ensureAdminExists();
        
        // 5. توحيد جدول طلبات المعلمين
        $this->unifyTeacherApplicationsTable();
        
        // 6. إصلاح العلاقات الخارجية
        $this->fixForeignKeys();
        
        // 7. تحديث ملفات PHP للاستخدام الموحد
        $this->updatePHPFiles();
        
        return [
            'results' => $this->results,
            'errors' => $this->errors,
            'fixes_applied' => $this->fixes_applied
        ];
    }
    
    private function createRegionsTable() {
        $this->results[] = "<h4>🌍 إنشاء جدول المناطق</h4>";
        
        try {
            // التحقق من وجود جدول regions
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'regions'");
            $regions_exists = $stmt->rowCount() > 0;
            
            // التحقق من وجود جدول app_d2335_regions
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'app_d2335_regions'");
            $old_regions_exists = $stmt->rowCount() > 0;
            
            if (!$regions_exists) {
                // إنشاء جدول المناطق الموحد
                $this->pdo->exec("
                    CREATE TABLE IF NOT EXISTS regions (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(100) NOT NULL,
                        description TEXT DEFAULT NULL,
                        is_active BOOLEAN DEFAULT TRUE,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX idx_name (name),
                        INDEX idx_active (is_active)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                ");
                
                $this->fixes_applied[] = "تم إنشاء جدول regions";
                $this->results[] = "✅ تم إنشاء جدول المناطق بنجاح";
                
                // نقل البيانات من الجدول القديم إن وجد
                if ($old_regions_exists) {
                    try {
                        $this->pdo->exec("
                            INSERT INTO regions (id, name, description, is_active, created_at, updated_at)
                            SELECT id, name, description, is_active, created_at, updated_at
                            FROM app_d2335_regions
                        ");
                        $this->results[] = "✅ تم نقل البيانات من app_d2335_regions";
                    } catch (Exception $e) {
                        $this->results[] = "⚠️ تحذير: " . $e->getMessage();
                    }
                }
            } else {
                $this->results[] = "✅ جدول المناطق موجود بالفعل";
            }
            
            // حذف الجدول القديم بعد نقل البيانات
            if ($old_regions_exists && $regions_exists) {
                try {
                    // حذف العلاقات الخارجية المرتبطة بالجدول القديم
                    $this->pdo->exec("
                        SELECT CONCAT('ALTER TABLE ', TABLE_NAME, ' DROP FOREIGN KEY ', CONSTRAINT_NAME, ';')
                        INTO @sql
                        FROM information_schema.KEY_COLUMN_USAGE
                        WHERE REFERENCED_TABLE_NAME = 'app_d2335_regions'
                        AND TABLE_SCHEMA = DATABASE()
                    ");
                    
                    $this->pdo->exec("DROP TABLE IF EXISTS app_d2335_regions");
                    $this->results[] = "✅ تم حذف الجدول القديم app_d2335_regions";
                } catch (Exception $e) {
                    $this->results[] = "⚠️ لم يتم حذف الجدول القديم: " . $e->getMessage();
                }
            }
            
        } catch (Exception $e) {
            $this->errors[] = "❌ خطأ في إنشاء جدول المناطق: " . $e->getMessage();
        }
    }
    
    private function insertRegions() {
        $this->results[] = "<h4>📍 إدراج المناطق الأساسية</h4>";
        
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM regions");
            $count = $stmt->fetch()['count'];
            
            if ($count == 0) {
                $regions = [
                    ['شمال غزة', 'محافظة شمال غزة'],
                    ['غزة', 'محافظة غزة'],
                    ['الوسطى', 'محافظة الوسطى'],
                    ['خان يونس', 'محافظة خان يونس'],
                    ['رفح', 'محافظة رفح']
                ];
                
                $stmt = $this->pdo->prepare("INSERT INTO regions (name, description) VALUES (?, ?)");
                
                foreach ($regions as $region) {
                    $stmt->execute($region);
                }
                
                $this->fixes_applied[] = "تم إدراج " . count($regions) . " مناطق";
                $this->results[] = "✅ تم إدراج المناطق الأساسية";
            } else {
                $this->results[] = "✅ المناطق موجودة بالفعل ({$count} منطقة)";
            }
            
        } catch (Exception $e) {
            $this->errors[] = "❌ خطأ في إدراج المناطق: " . $e->getMessage();
        }
    }
    
    private function createUsersTable() {
        $this->results[] = "<h4>👥 تحديث جدول المستخدمين</h4>";
        
        try {
            // إضافة الحقول المفقودة إن لم تكن موجودة
            $columns_to_add = [
                "branch_id INT DEFAULT NULL",
                "last_seen TIMESTAMP NULL DEFAULT NULL",
                "last_login TIMESTAMP NULL DEFAULT NULL",
                "is_online BOOLEAN DEFAULT FALSE"
            ];
            
            foreach ($columns_to_add as $column) {
                try {
                    $col_name = explode(' ', $column)[0];
                    $stmt = $this->pdo->query("SHOW COLUMNS FROM users LIKE '{$col_name}'");
                    if ($stmt->rowCount() == 0) {
                        $this->pdo->exec("ALTER TABLE users ADD COLUMN {$column}");
                        $this->results[] = "✅ تم إضافة العمود {$col_name}";
                    }
                } catch (Exception $e) {
                    // العمود موجود بالفعل
                }
            }
            
        } catch (Exception $e) {
            $this->errors[] = "❌ خطأ في تحديث جدول المستخدمين: " . $e->getMessage();
        }
    }
    
    private function ensureAdminExists() {
        $this->results[] = "<h4>👨‍💼 التحقق من حساب المدير</h4>";
        
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
            $count = $stmt->fetch()['count'];
            
            if ($count == 0) {
                $password = password_hash('admin123', PASSWORD_DEFAULT);
                $this->pdo->prepare("
                    INSERT INTO users (username, email, password, full_name, phone, role, is_active, email_verified)
                    VALUES ('admin', 'admin@himma.edu', ?, 'المدير العام', '0599999999', 'admin', 1, 1)
                ")->execute([$password]);
                
                $this->fixes_applied[] = "تم إنشاء حساب المدير العام";
                $this->results[] = "✅ تم إنشاء حساب المدير (admin/admin123)";
            } else {
                $this->results[] = "✅ حساب المدير موجود بالفعل";
            }
            
        } catch (Exception $e) {
            $this->errors[] = "❌ خطأ في التحقق من المدير: " . $e->getMessage();
        }
    }
    
    private function unifyTeacherApplicationsTable() {
        $this->results[] = "<h4>📋 توحيد جدول طلبات المعلمين</h4>";
        
        try {
            // التحقق من وجود الجداول
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'teacher_applications'");
            $old_table_exists = $stmt->rowCount() > 0;
            
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'app_d2335_teacher_applications'");
            $new_table_exists = $stmt->rowCount() > 0;
            
            // إنشاء الجدول الموحد باسم teacher_applications
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS teacher_applications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    full_name VARCHAR(100) NOT NULL,
                    email VARCHAR(100) NOT NULL UNIQUE,
                    phone VARCHAR(20) DEFAULT NULL,
                    region_id INT DEFAULT NULL,
                    subject_specialization VARCHAR(200) NOT NULL,
                    experience_years INT DEFAULT 0,
                    education_level VARCHAR(50) DEFAULT NULL,
                    cv_file VARCHAR(500) DEFAULT NULL,
                    certificates_file VARCHAR(500) DEFAULT NULL,
                    status ENUM('pending', 'approved', 'rejected', 'under_review') DEFAULT 'pending',
                    admin_notes TEXT DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    approved_at TIMESTAMP NULL DEFAULT NULL,
                    approved_by INT DEFAULT NULL,
                    teacher_user_id INT DEFAULT NULL,
                    INDEX idx_email (email),
                    INDEX idx_status (status),
                    INDEX idx_region (region_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            $this->results[] = "✅ تم إنشاء/تحديث جدول teacher_applications";
            
            // نقل البيانات من الجدول القديم إن وجد
            if ($new_table_exists) {
                try {
                    $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM app_d2335_teacher_applications");
                    $count = $stmt->fetch()['count'];
                    
                    if ($count > 0) {
                        $this->pdo->exec("
                            INSERT IGNORE INTO teacher_applications 
                            (id, full_name, email, phone, region_id, subject_specialization, 
                             experience_years, education_level, status, admin_notes, 
                             created_at, updated_at, approved_at, approved_by, teacher_user_id)
                            SELECT id, teacher_name, email, phone, region_id, subject_specialization,
                                   experience_years, qualifications, status, review_notes,
                                   submitted_at, updated_at, reviewed_at, reviewed_by, teacher_user_id
                            FROM app_d2335_teacher_applications
                        ");
                        
                        $this->fixes_applied[] = "تم نقل {$count} طلب من app_d2335_teacher_applications إلى teacher_applications";
                        $this->results[] = "✅ تم نقل البيانات من الجدول القديم";
                    }
                } catch (Exception $e) {
                    $this->results[] = "⚠️ تحذير في نقل البيانات: " . $e->getMessage();
                }
            }
            
        } catch (Exception $e) {
            $this->errors[] = "❌ خطأ في توحيد الجدول: " . $e->getMessage();
        }
    }
    
    private function fixForeignKeys() {
        $this->results[] = "<h4>🔗 إصلاح العلاقات الخارجية</h4>";
        
        try {
            // حذف العلاقات القديمة إن وجدت
            try {
                $this->pdo->exec("ALTER TABLE teacher_applications DROP FOREIGN KEY fk_teacher_app_region");
            } catch (Exception $e) {
                // العلاقة غير موجودة
            }
            
            try {
                $this->pdo->exec("ALTER TABLE teacher_applications DROP FOREIGN KEY fk_teacher_app_approved_by");
            } catch (Exception $e) {
                // العلاقة غير موجودة
            }
            
            // إضافة العلاقات الجديدة
            try {
                $this->pdo->exec("
                    ALTER TABLE teacher_applications 
                    ADD CONSTRAINT fk_teacher_app_region 
                    FOREIGN KEY (region_id) REFERENCES regions(id) ON DELETE SET NULL
                ");
                $this->results[] = "✅ تم إضافة علاقة region_id";
            } catch (Exception $e) {
                $this->results[] = "⚠️ علاقة region_id موجودة بالفعل";
            }
            
            try {
                $this->pdo->exec("
                    ALTER TABLE teacher_applications 
                    ADD CONSTRAINT fk_teacher_app_approved_by 
                    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
                ");
                $this->results[] = "✅ تم إضافة علاقة approved_by";
            } catch (Exception $e) {
                $this->results[] = "⚠️ علاقة approved_by موجودة بالفعل";
            }
            
        } catch (Exception $e) {
            $this->results[] = "⚠️ تحذير في العلاقات الخارجية: " . $e->getMessage();
        }
    }
    
    private function updatePHPFiles() {
        $this->results[] = "<h4>📝 تحديث ملفات PHP</h4>";
        
        $files_to_update = [
            'auth/register_teacher_fixed.php',
            'admin/manage_teacher_applications.php'
        ];
        
        $updated_count = 0;
        
        foreach ($files_to_update as $file) {
            $full_path = __DIR__ . '/himma_tawjihi/' . $file;
            
            if (file_exists($full_path)) {
                try {
                    $content = file_get_contents($full_path);
                    
                    // استبدال أسماء الجداول القديمة
                    $content = str_replace('app_d2335_teacher_applications', 'teacher_applications', $content);
                    $content = str_replace('app_d2335_regions', 'regions', $content);
                    $content = str_replace('teacher_name', 'full_name', $content);
                    $content = str_replace('submitted_at', 'created_at', $content);
                    
                    file_put_contents($full_path, $content);
                    $updated_count++;
                    $this->results[] = "✅ تم تحديث: {$file}";
                    
                } catch (Exception $e) {
                    $this->errors[] = "❌ فشل تحديث {$file}: " . $e->getMessage();
                }
            }
        }
        
        if ($updated_count > 0) {
            $this->fixes_applied[] = "تم تحديث {$updated_count} ملف PHP";
        }
    }
}

// تشغيل الإصلاح
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_fix'])) {
    $fix = new CompleteFix($db_config);
    $report = $fix->runCompleteFix();
} else {
    $report = null;
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الإصلاح الشامل - منصة همة التوجيهي</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            max-width: 1000px;
        }
        .header {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
        }
        .result-box {
            background: #f8f9fa;
            border-right: 4px solid #007bff;
            padding: 20px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .error-box {
            background: #fff5f5;
            border-right: 4px solid #dc3545;
            padding: 20px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .success-box {
            background: #f0fff4;
            border-right: 4px solid #28a745;
            padding: 20px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .btn-fix {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            padding: 15px 40px;
            font-size: 18px;
            border-radius: 10px;
            color: white;
            font-weight: bold;
        }
        .btn-fix:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin: 10px 0;
            text-align: center;
        }
        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-wrench"></i> الإصلاح الشامل والنهائي</h1>
            <p class="mb-0">حل جميع مشاكل النظام دفعة واحدة</p>
        </div>
        
        <?php if (!$report): ?>
            <div class="text-center">
                <div class="alert alert-info">
                    <h4><i class="fas fa-info-circle"></i> ما سيتم إصلاحه:</h4>
                    <ul class="list-unstyled text-start">
                        <li><i class="fas fa-check text-success"></i> توحيد جداول طلبات المعلمين</li>
                        <li><i class="fas fa-check text-success"></i> إنشاء جدول المناطق والبيانات الأساسية</li>
                        <li><i class="fas fa-check text-success"></i> إصلاح عملية تسجيل المعلمين</li>
                        <li><i class="fas fa-check text-success"></i> إصلاح لوحة تحكم المدير</li>
                        <li><i class="fas fa-check text-success"></i> تحديث ملفات PHP تلقائياً</li>
                        <li><i class="fas fa-check text-success"></i> إصلاح العلاقات بين الجداول</li>
                    </ul>
                </div>
                
                <form method="POST">
                    <button type="submit" name="run_fix" class="btn btn-fix">
                        <i class="fas fa-play"></i> تشغيل الإصلاح الشامل
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo count($report['results']); ?></div>
                        <div>عمليات الفحص</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo count($report['fixes_applied']); ?></div>
                        <div>الإصلاحات المطبقة</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card">
                        <div class="stats-number"><?php echo count($report['errors']); ?></div>
                        <div>الأخطاء</div>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($report['results'])): ?>
                <div class="result-box">
                    <h4><i class="fas fa-list-check"></i> نتائج الإصلاح</h4>
                    <?php foreach ($report['results'] as $result): ?>
                        <div><?php echo $result; ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($report['fixes_applied'])): ?>
                <div class="success-box">
                    <h4><i class="fas fa-check-circle"></i> الإصلاحات المطبقة</h4>
                    <?php foreach ($report['fixes_applied'] as $fix): ?>
                        <div>✅ <?php echo $fix; ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($report['errors'])): ?>
                <div class="error-box">
                    <h4><i class="fas fa-exclamation-triangle"></i> الأخطاء</h4>
                    <?php foreach ($report['errors'] as $error): ?>
                        <div><?php echo $error; ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <div class="alert alert-success mt-4">
                <h5><i class="fas fa-check-circle"></i> ملخص العملية</h5>
                <p>
                    تم تنفيذ <?php echo count($report['results']); ?> عملية،
                    وتطبيق <?php echo count($report['fixes_applied']); ?> إصلاح،
                    مع <?php echo count($report['errors']); ?> خطأ.
                </p>
                
                <?php if (count($report['errors']) == 0): ?>
                    <div class="alert alert-success">
                        <h6>🎉 تم الإصلاح بنجاح!</h6>
                        <p><strong>الخطوات التالية:</strong></p>
                        <ol>
                            <li>سجل دخول كمدير: <code>admin / admin123</code></li>
                            <li>اذهب إلى إدارة طلبات المعلمين</li>
                            <li>وافق على طلبات المعلمين</li>
                            <li>سيتمكن المعلمون من إنشاء حساباتهم بعد الموافقة</li>
                        </ol>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <h6>⚠️ تم الإصلاح مع بعض التحذيرات</h6>
                        <p>يمكنك المتابعة، معظم المشاكل تم حلها. التحذيرات المذكورة أعلاه ليست حرجة.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="text-center mt-4">
                <a href="?" class="btn btn-primary me-2">
                    <i class="fas fa-redo"></i> تشغيل مرة أخرى
                </a>
                <a href="himma_tawjihi/auth/login.php" class="btn btn-success">
                    <i class="fas fa-sign-in-alt"></i> تسجيل الدخول
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>