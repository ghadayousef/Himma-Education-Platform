<?php
/**
 * الإصلاح النهائي لمشكلة تسجيل المعلمين
 * يحل المشكلة بشكل نهائي ويوحد الجداول
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300);

$db_config = [
    'host' => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'himma_tawjihi',
    'charset' => 'utf8mb4'
];

class FinalFix {
    private $pdo;
    private $results = [];
    private $errors = [];
    
    public function __construct($config) {
        try {
            $dsn = "mysql:host={$config['host']};charset={$config['charset']}";
            $this->pdo = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            
            $this->pdo->exec("CREATE DATABASE IF NOT EXISTS `{$config['database']}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $this->pdo->exec("USE `{$config['database']}`");
            $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            
            $this->results[] = "✅ تم الاتصال بقاعدة البيانات";
        } catch (Exception $e) {
            die("فشل الاتصال: " . $e->getMessage());
        }
    }
    
    public function __destruct() {
        try {
            if ($this->pdo) {
                $this->pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            }
        } catch (Exception $e) {}
    }
    
    public function applyFix() {
        $this->results[] = "<h3>🔧 بدء الإصلاح النهائي</h3>";
        
        // 1. إنشاء/تحديث جدول المناطق
        $this->fixRegionsTable();
        
        // 2. توحيد جدول طلبات المعلمين
        $this->unifyTeacherApplications();
        
        // 3. تحديث ملف التسجيل
        $this->updateRegisterFile();
        
        // 4. تحديث ملف إدارة الطلبات
        $this->updateManageFile();
        
        // 5. التحقق النهائي
        $this->finalVerification();
        
        return [
            'results' => $this->results,
            'errors' => $this->errors
        ];
    }
    
    private function fixRegionsTable() {
        $this->results[] = "<h4>🌍 إصلاح جدول المناطق</h4>";
        
        try {
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
            
            // نقل البيانات من الجدول القديم إن وجد
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'app_d2335_regions'");
            if ($stmt->rowCount() > 0) {
                $this->pdo->exec("
                    INSERT IGNORE INTO regions (id, name, description, is_active, created_at, updated_at)
                    SELECT id, name, description, is_active, created_at, updated_at
                    FROM app_d2335_regions
                ");
            }
            
            // إدراج المناطق الأساسية إن لم تكن موجودة
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM regions");
            if ($stmt->fetch()['count'] == 0) {
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
            }
            
            $this->results[] = "✅ تم إصلاح جدول المناطق";
        } catch (Exception $e) {
            $this->errors[] = "❌ خطأ في المناطق: " . $e->getMessage();
        }
    }
    
    private function unifyTeacherApplications() {
        $this->results[] = "<h4>📋 توحيد جدول طلبات المعلمين</h4>";
        
        try {
            // إنشاء الجدول الموحد
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS teacher_applications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    full_name VARCHAR(100) NOT NULL,
                    email VARCHAR(100) NOT NULL,
                    phone VARCHAR(20) DEFAULT NULL,
                    region_id INT DEFAULT NULL,
                    subject_specialization VARCHAR(200) NOT NULL,
                    experience_years INT DEFAULT 0,
                    education_level VARCHAR(50) DEFAULT NULL,
                    status ENUM('pending', 'approved', 'rejected', 'under_review') DEFAULT 'pending',
                    admin_notes TEXT DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    approved_at TIMESTAMP NULL DEFAULT NULL,
                    approved_by INT DEFAULT NULL,
                    teacher_user_id INT DEFAULT NULL,
                    INDEX idx_email (email),
                    INDEX idx_status (status),
                    INDEX idx_region (region_id),
                    UNIQUE KEY unique_email_active (email, status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            // نقل البيانات من الجدول القديم
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'app_d2335_teacher_applications'");
            if ($stmt->rowCount() > 0) {
                // التحقق من الأعمدة الموجودة في الجدول القديم
                $old_columns = $this->pdo->query("DESCRIBE app_d2335_teacher_applications")->fetchAll();
                $old_column_names = array_column($old_columns, 'Field');
                
                // تحديد أسماء الأعمدة الصحيحة
                $name_col = in_array('teacher_name', $old_column_names) ? 'teacher_name' : 'full_name';
                $date_col = in_array('submitted_at', $old_column_names) ? 'submitted_at' : 'created_at';
                $notes_col = in_array('review_notes', $old_column_names) ? 'review_notes' : 'admin_notes';
                $reviewed_by_col = in_array('reviewed_by', $old_column_names) ? 'reviewed_by' : 'approved_by';
                $reviewed_at_col = in_array('reviewed_at', $old_column_names) ? 'reviewed_at' : 'approved_at';
                $qual_col = in_array('qualifications', $old_column_names) ? 'qualifications' : 'education_level';
                
                $this->pdo->exec("
                    INSERT INTO teacher_applications 
                    (id, full_name, email, phone, region_id, subject_specialization, 
                     experience_years, education_level, status, admin_notes, 
                     created_at, updated_at, approved_at, approved_by, teacher_user_id)
                    SELECT 
                        id, 
                        {$name_col}, 
                        email, 
                        phone, 
                        region_id, 
                        subject_specialization,
                        experience_years, 
                        {$qual_col}, 
                        status, 
                        {$notes_col},
                        {$date_col}, 
                        updated_at, 
                        {$reviewed_at_col}, 
                        {$reviewed_by_col}, 
                        teacher_user_id
                    FROM app_d2335_teacher_applications
                    ON DUPLICATE KEY UPDATE
                        full_name = VALUES(full_name),
                        phone = VALUES(phone),
                        region_id = VALUES(region_id),
                        status = VALUES(status)
                ");
            }
            
            $this->results[] = "✅ تم توحيد جدول طلبات المعلمين";
            
            // عرض الإحصائيات
            $stmt = $this->pdo->query("
                SELECT status, COUNT(*) as count 
                FROM teacher_applications 
                GROUP BY status
            ");
            $stats = $stmt->fetchAll();
            
            foreach ($stats as $stat) {
                $this->results[] = "&nbsp;&nbsp;&nbsp;&nbsp;• {$stat['status']}: {$stat['count']} طلب";
            }
            
        } catch (Exception $e) {
            $this->errors[] = "❌ خطأ في التوحيد: " . $e->getMessage();
        }
    }
    
    private function updateRegisterFile() {
        $this->results[] = "<h4>📝 تحديث ملف التسجيل</h4>";
        
        $file_path = __DIR__ . '/himma_tawjihi/auth/register_teacher_fixed.php';
        
        if (!file_exists($file_path)) {
            $this->errors[] = "❌ ملف التسجيل غير موجود";
            return;
        }
        
        try {
            $content = file_get_contents($file_path);
            
            // استبدال جميع الإشارات للجدول القديم بالجدول الموحد
            $replacements = [
                'app_d2335_teacher_applications' => 'teacher_applications',
                'app_d2335_regions' => 'regions',
                'teacher_name' => 'full_name',
                'submitted_at' => 'created_at',
                'review_notes' => 'admin_notes',
                'reviewed_by' => 'approved_by',
                'reviewed_at' => 'approved_at'
            ];
            
            foreach ($replacements as $old => $new) {
                $content = str_replace($old, $new, $content);
            }
            
            file_put_contents($file_path, $content);
            $this->results[] = "✅ تم تحديث ملف التسجيل";
            
        } catch (Exception $e) {
            $this->errors[] = "❌ خطأ في تحديث ملف التسجيل: " . $e->getMessage();
        }
    }
    
    private function updateManageFile() {
        $this->results[] = "<h4>📝 تحديث ملف إدارة الطلبات</h4>";
        
        $file_path = __DIR__ . '/himma_tawjihi/admin/manage_teacher_applications.php';
        
        if (!file_exists($file_path)) {
            $this->errors[] = "❌ ملف الإدارة غير موجود";
            return;
        }
        
        try {
            $content = file_get_contents($file_path);
            
            // استبدال جميع الإشارات للجدول القديم
            $replacements = [
                'app_d2335_teacher_applications' => 'teacher_applications',
                'app_d2335_regions' => 'regions',
                'teacher_name' => 'full_name',
                'submitted_at' => 'created_at',
                'review_notes' => 'admin_notes',
                'reviewed_by' => 'approved_by',
                'reviewed_at' => 'approved_at'
            ];
            
            foreach ($replacements as $old => $new) {
                $content = str_replace($old, $new, $content);
            }
            
            file_put_contents($file_path, $content);
            $this->results[] = "✅ تم تحديث ملف الإدارة";
            
        } catch (Exception $e) {
            $this->errors[] = "❌ خطأ في تحديث ملف الإدارة: " . $e->getMessage();
        }
    }
    
    private function finalVerification() {
        $this->results[] = "<h4>✔️ التحقق النهائي</h4>";
        
        try {
            // التحقق من الجداول
            $tables = ['regions', 'teacher_applications', 'users'];
            foreach ($tables as $table) {
                $stmt = $this->pdo->query("SHOW TABLES LIKE '{$table}'");
                if ($stmt->rowCount() > 0) {
                    $this->results[] = "✅ جدول {$table}: موجود";
                } else {
                    $this->errors[] = "❌ جدول {$table}: مفقود";
                }
            }
            
            // عرض الطلبات الموافق عليها بدون حساب
            $stmt = $this->pdo->query("
                SELECT COUNT(*) as count 
                FROM teacher_applications 
                WHERE status = 'approved' AND teacher_user_id IS NULL
            ");
            $count = $stmt->fetch()['count'];
            
            $this->results[] = "📊 عدد الطلبات الموافق عليها بدون حساب: {$count}";
            
        } catch (Exception $e) {
            $this->errors[] = "❌ خطأ في التحقق: " . $e->getMessage();
        }
    }
}

// تشغيل الإصلاح
$report = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_fix'])) {
    $fix = new FinalFix($db_config);
    $report = $fix->applyFix();
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الإصلاح النهائي - تسجيل المعلمين</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #16a085 0%, #27ae60 100%);
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
            background: linear-gradient(135deg, #16a085, #27ae60);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
        }
        .result-box {
            background: #f8f9fa;
            border-right: 4px solid #28a745;
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
        .btn-fix {
            background: linear-gradient(135deg, #16a085, #27ae60);
            border: none;
            padding: 15px 40px;
            font-size: 18px;
            border-radius: 10px;
            color: white;
            font-weight: bold;
        }
        .btn-fix:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(22, 160, 133, 0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-tools"></i> الإصلاح النهائي</h1>
            <p class="mb-0">حل مشكلة تسجيل المعلمين نهائياً</p>
        </div>
        
        <?php if (!$report): ?>
            <div class="alert alert-warning">
                <h4><i class="fas fa-exclamation-triangle"></i> تحذير مهم</h4>
                <p>هذا الإصلاح سيقوم بـ:</p>
                <ul>
                    <li>توحيد جداول طلبات المعلمين</li>
                    <li>تحديث ملفات PHP تلقائياً</li>
                    <li>إصلاح جميع المشاكل المتعلقة بتسجيل المعلمين</li>
                </ul>
                <p><strong>تأكد من أخذ نسخة احتياطية من قاعدة البيانات قبل المتابعة</strong></p>
            </div>
            
            <form method="POST" class="text-center">
                <button type="submit" name="apply_fix" class="btn btn-fix">
                    <i class="fas fa-rocket"></i> تطبيق الإصلاح النهائي
                </button>
            </form>
        <?php else: ?>
            <?php if (!empty($report['results'])): ?>
                <div class="result-box">
                    <h4><i class="fas fa-check-circle"></i> نتائج الإصلاح</h4>
                    <?php foreach ($report['results'] as $result): ?>
                        <div><?php echo $result; ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($report['errors'])): ?>
                <div class="error-box">
                    <h4><i class="fas fa-times-circle"></i> أخطاء</h4>
                    <?php foreach ($report['errors'] as $error): ?>
                        <div><?php echo $error; ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (count($report['errors']) == 0): ?>
                <div class="alert alert-success">
                    <h4><i class="fas fa-check-circle"></i> تم الإصلاح بنجاح!</h4>
                    <p><strong>الخطوات التالية:</strong></p>
                    <ol>
                        <li>سجل دخول كمدير</li>
                        <li>اذهب إلى صفحة إدارة طلبات المعلمين</li>
                        <li>وافق على طلبات المعلمين</li>
                        <li>سيتمكن المعلمون من إنشاء حساباتهم بعد الموافقة</li>
                    </ol>
                </div>
            <?php endif; ?>
            
            <div class="text-center mt-4">
                <a href="diagnose_teacher_registration.php" class="btn btn-primary me-2">
                    <i class="fas fa-stethoscope"></i> تشخيص المشكلة
                </a>
                <a href="himma_tawjihi/auth/login.php" class="btn btn-success">
                    <i class="fas fa-sign-in-alt"></i> تسجيل الدخول
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>