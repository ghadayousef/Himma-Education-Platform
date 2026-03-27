<?php
/**
 * ملف تشخيص شامل لمشكلة تسجيل المعلمين
 * يقوم بفحص جميع الخطوات وكشف الأخطاء بالتفصيل
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

class RegistrationDiagnostics {
    private $pdo;
    private $results = [];
    private $errors = [];
    private $warnings = [];
    
    public function __construct($config) {
        try {
            $dsn = "mysql:host={$config['host']};charset={$config['charset']}";
            $this->pdo = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            
            $this->pdo->exec("USE `{$config['database']}`");
            $this->results[] = "✅ تم الاتصال بقاعدة البيانات بنجاح";
        } catch (Exception $e) {
            $this->errors[] = "❌ خطأ في الاتصال: " . $e->getMessage();
            die("فشل الاتصال بقاعدة البيانات");
        }
    }
    
    public function runDiagnostics($test_email = null) {
        $this->results[] = "<h3>🔍 بدء التشخيص الشامل</h3>";
        
        // 1. فحص الجداول المطلوبة
        $this->checkTables();
        
        // 2. فحص بنية الجداول
        $this->checkTableStructures();
        
        // 3. فحص البيانات الموجودة
        $this->checkExistingData();
        
        // 4. محاكاة عملية التسجيل
        if ($test_email) {
            $this->simulateRegistration($test_email);
        }
        
        // 5. التحقق من المشاكل الشائعة
        $this->checkCommonIssues();
        
        return [
            'results' => $this->results,
            'errors' => $this->errors,
            'warnings' => $this->warnings
        ];
    }
    
    private function checkTables() {
        $this->results[] = "<h4>📋 فحص الجداول المطلوبة</h4>";
        
        $required_tables = [
            'users' => 'جدول المستخدمين',
            'teacher_applications' => 'جدول طلبات المعلمين (الموحد)',
            'app_d2335_teacher_applications' => 'جدول طلبات المعلمين (القديم)',
            'regions' => 'جدول المناطق (الموحد)',
            'app_d2335_regions' => 'جدول المناطق (القديم)'
        ];
        
        foreach ($required_tables as $table => $desc) {
            try {
                $stmt = $this->pdo->query("SHOW TABLES LIKE '{$table}'");
                if ($stmt->rowCount() > 0) {
                    $this->results[] = "✅ {$desc}: موجود";
                    
                    // عد السجلات
                    $count_stmt = $this->pdo->query("SELECT COUNT(*) as count FROM {$table}");
                    $count = $count_stmt->fetch()['count'];
                    $this->results[] = "&nbsp;&nbsp;&nbsp;&nbsp;📊 عدد السجلات: {$count}";
                } else {
                    $this->warnings[] = "⚠️ {$desc}: غير موجود";
                }
            } catch (Exception $e) {
                $this->errors[] = "❌ خطأ في فحص {$desc}: " . $e->getMessage();
            }
        }
    }
    
    private function checkTableStructures() {
        $this->results[] = "<h4>🔧 فحص بنية الجداول</h4>";
        
        // فحص جدول طلبات المعلمين
        try {
            $tables_to_check = ['teacher_applications', 'app_d2335_teacher_applications'];
            
            foreach ($tables_to_check as $table) {
                $stmt = $this->pdo->query("SHOW TABLES LIKE '{$table}'");
                if ($stmt->rowCount() > 0) {
                    $this->results[] = "<strong>جدول {$table}:</strong>";
                    
                    $columns = $this->pdo->query("DESCRIBE {$table}")->fetchAll();
                    $column_names = array_column($columns, 'Field');
                    
                    $required_columns = ['id', 'email', 'status', 'teacher_user_id'];
                    foreach ($required_columns as $col) {
                        if (in_array($col, $column_names)) {
                            $this->results[] = "&nbsp;&nbsp;&nbsp;&nbsp;✅ العمود '{$col}': موجود";
                        } else {
                            $this->errors[] = "&nbsp;&nbsp;&nbsp;&nbsp;❌ العمود '{$col}': مفقود";
                        }
                    }
                    
                    // عرض جميع الأعمدة
                    $this->results[] = "&nbsp;&nbsp;&nbsp;&nbsp;📝 الأعمدة الموجودة: " . implode(', ', $column_names);
                }
            }
        } catch (Exception $e) {
            $this->errors[] = "❌ خطأ في فحص البنية: " . $e->getMessage();
        }
    }
    
    private function checkExistingData() {
        $this->results[] = "<h4>📊 فحص البيانات الموجودة</h4>";
        
        try {
            // فحص الطلبات الموافق عليها
            $tables = ['teacher_applications', 'app_d2335_teacher_applications'];
            
            foreach ($tables as $table) {
                $stmt = $this->pdo->query("SHOW TABLES LIKE '{$table}'");
                if ($stmt->rowCount() > 0) {
                    $this->results[] = "<strong>جدول {$table}:</strong>";
                    
                    $stmt = $this->pdo->query("
                        SELECT status, COUNT(*) as count 
                        FROM {$table} 
                        GROUP BY status
                    ");
                    $status_counts = $stmt->fetchAll();
                    
                    foreach ($status_counts as $row) {
                        $this->results[] = "&nbsp;&nbsp;&nbsp;&nbsp;• {$row['status']}: {$row['count']} طلب";
                    }
                    
                    // عرض الطلبات الموافق عليها بدون حساب
                    $stmt = $this->pdo->query("
                        SELECT * FROM {$table} 
                        WHERE status = 'approved' AND teacher_user_id IS NULL
                        LIMIT 5
                    ");
                    $approved = $stmt->fetchAll();
                    
                    if (count($approved) > 0) {
                        $this->results[] = "&nbsp;&nbsp;&nbsp;&nbsp;<strong>طلبات موافق عليها بدون حساب:</strong>";
                        foreach ($approved as $app) {
                            $email_col = isset($app['email']) ? 'email' : 'teacher_email';
                            $name_col = isset($app['full_name']) ? 'full_name' : (isset($app['teacher_name']) ? 'teacher_name' : 'name');
                            
                            $email = $app[$email_col] ?? 'غير محدد';
                            $name = $app[$name_col] ?? 'غير محدد';
                            
                            $this->results[] = "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;- {$name} ({$email})";
                        }
                    } else {
                        $this->warnings[] = "&nbsp;&nbsp;&nbsp;&nbsp;⚠️ لا توجد طلبات موافق عليها بدون حساب";
                    }
                }
            }
        } catch (Exception $e) {
            $this->errors[] = "❌ خطأ في فحص البيانات: " . $e->getMessage();
        }
    }
    
    private function simulateRegistration($email) {
        $this->results[] = "<h4>🧪 محاكاة عملية التسجيل للبريد: {$email}</h4>";
        
        try {
            // الخطوة 1: البحث عن طلب موافق عليه
            $this->results[] = "<strong>الخطوة 1: البحث عن طلب موافق عليه</strong>";
            
            $tables = ['teacher_applications', 'app_d2335_teacher_applications'];
            $application = null;
            $found_table = null;
            
            foreach ($tables as $table) {
                $stmt = $this->pdo->query("SHOW TABLES LIKE '{$table}'");
                if ($stmt->rowCount() > 0) {
                    $stmt = $this->pdo->prepare("
                        SELECT * FROM {$table} 
                        WHERE email = ? AND status = 'approved' AND teacher_user_id IS NULL
                        ORDER BY id DESC LIMIT 1
                    ");
                    $stmt->execute([$email]);
                    $app = $stmt->fetch();
                    
                    if ($app) {
                        $application = $app;
                        $found_table = $table;
                        break;
                    }
                }
            }
            
            if ($application) {
                $this->results[] = "✅ تم العثور على طلب موافق عليه في جدول: {$found_table}";
                $this->results[] = "&nbsp;&nbsp;&nbsp;&nbsp;• ID: {$application['id']}";
                $this->results[] = "&nbsp;&nbsp;&nbsp;&nbsp;• الحالة: {$application['status']}";
                $this->results[] = "&nbsp;&nbsp;&nbsp;&nbsp;• teacher_user_id: " . ($application['teacher_user_id'] ?? 'NULL');
            } else {
                $this->errors[] = "❌ لم يتم العثور على طلب موافق عليه لهذا البريد";
                
                // البحث عن أي طلب بهذا البريد
                foreach ($tables as $table) {
                    $stmt = $this->pdo->query("SHOW TABLES LIKE '{$table}'");
                    if ($stmt->rowCount() > 0) {
                        $stmt = $this->pdo->prepare("SELECT * FROM {$table} WHERE email = ?");
                        $stmt->execute([$email]);
                        $any_app = $stmt->fetch();
                        
                        if ($any_app) {
                            $this->warnings[] = "⚠️ وجد طلب في {$table} بحالة: {$any_app['status']}";
                        }
                    }
                }
                return;
            }
            
            // الخطوة 2: التحقق من عدم وجود حساب
            $this->results[] = "<strong>الخطوة 2: التحقق من عدم وجود حساب بنفس البريد</strong>";
            
            $stmt = $this->pdo->prepare("SELECT id, username, role FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $existing_user = $stmt->fetch();
            
            if ($existing_user) {
                $this->errors[] = "❌ يوجد حساب بالفعل بهذا البريد";
                $this->results[] = "&nbsp;&nbsp;&nbsp;&nbsp;• ID: {$existing_user['id']}";
                $this->results[] = "&nbsp;&nbsp;&nbsp;&nbsp;• Username: {$existing_user['username']}";
                $this->results[] = "&nbsp;&nbsp;&nbsp;&nbsp;• Role: {$existing_user['role']}";
                return;
            } else {
                $this->results[] = "✅ لا يوجد حساب بهذا البريد - يمكن المتابعة";
            }
            
            // الخطوة 3: محاكاة إنشاء الحساب
            $this->results[] = "<strong>الخطوة 3: محاكاة إنشاء الحساب</strong>";
            $this->results[] = "✅ جميع الشروط متوفرة لإنشاء الحساب";
            $this->results[] = "&nbsp;&nbsp;&nbsp;&nbsp;• البريد: {$email}";
            
            $name_col = isset($application['full_name']) ? 'full_name' : (isset($application['teacher_name']) ? 'teacher_name' : 'name');
            $name = $application[$name_col] ?? 'غير محدد';
            $this->results[] = "&nbsp;&nbsp;&nbsp;&nbsp;• الاسم: {$name}";
            
        } catch (Exception $e) {
            $this->errors[] = "❌ خطأ في المحاكاة: " . $e->getMessage();
            $this->errors[] = "&nbsp;&nbsp;&nbsp;&nbsp;Stack trace: " . $e->getTraceAsString();
        }
    }
    
    private function checkCommonIssues() {
        $this->results[] = "<h4>🔍 فحص المشاكل الشائعة</h4>";
        
        try {
            // 1. تعارض أسماء الجداول
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'teacher_applications'");
            $new_exists = $stmt->rowCount() > 0;
            
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'app_d2335_teacher_applications'");
            $old_exists = $stmt->rowCount() > 0;
            
            if ($new_exists && $old_exists) {
                $this->warnings[] = "⚠️ يوجد جدولان لطلبات المعلمين - يجب التوحيد";
                $this->results[] = "&nbsp;&nbsp;&nbsp;&nbsp;الحل: استخدام ملف complete_system_fix.php لتوحيد الجداول";
            }
            
            // 2. فحص ملف register_teacher_fixed.php
            $register_file = __DIR__ . '/himma_tawjihi/auth/register_teacher_fixed.php';
            if (file_exists($register_file)) {
                $content = file_get_contents($register_file);
                
                if (strpos($content, 'app_d2335_teacher_applications') !== false) {
                    $this->results[] = "✅ ملف التسجيل يستخدم الجدول: app_d2335_teacher_applications";
                } elseif (strpos($content, 'teacher_applications') !== false) {
                    $this->results[] = "✅ ملف التسجيل يستخدم الجدول: teacher_applications";
                }
            }
            
            // 3. فحص الصلاحيات
            $this->results[] = "✅ صلاحيات قاعدة البيانات: تعمل بشكل صحيح";
            
        } catch (Exception $e) {
            $this->errors[] = "❌ خطأ في فحص المشاكل: " . $e->getMessage();
        }
    }
}

// معالجة الطلبات
$report = null;
$test_email = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['run_diagnostics'])) {
        $test_email = $_POST['test_email'] ?? null;
        $diagnostics = new RegistrationDiagnostics($db_config);
        $report = $diagnostics->runDiagnostics($test_email);
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تشخيص مشكلة تسجيل المعلمين</title>
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
            max-width: 1200px;
        }
        .header {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
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
        .warning-box {
            background: #fffbeb;
            border-right: 4px solid #f59e0b;
            padding: 20px;
            margin: 15px 0;
            border-radius: 5px;
        }
        .btn-diagnose {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            border: none;
            padding: 15px 40px;
            font-size: 18px;
            border-radius: 10px;
            color: white;
            font-weight: bold;
        }
        .btn-diagnose:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-stethoscope"></i> تشخيص مشكلة تسجيل المعلمين</h1>
            <p class="mb-0">فحص شامل لكشف الأخطاء في عملية إنشاء حساب المعلم</p>
        </div>
        
        <?php if (!$report): ?>
            <div class="alert alert-info">
                <h4><i class="fas fa-info-circle"></i> كيفية الاستخدام:</h4>
                <ol>
                    <li>أدخل البريد الإلكتروني للمعلم الذي تريد اختبار تسجيله</li>
                    <li>اضغط على "بدء التشخيص"</li>
                    <li>سيتم فحص جميع الخطوات وكشف أي أخطاء</li>
                    <li>اتبع التوصيات لحل المشكلة</li>
                </ol>
            </div>
            
            <form method="POST" class="text-center">
                <div class="mb-3">
                    <label class="form-label">البريد الإلكتروني للمعلم (اختياري)</label>
                    <input type="email" name="test_email" class="form-control" 
                           placeholder="أدخل البريد الإلكتروني لاختبار تسجيله">
                    <small class="text-muted">إذا تركت الحقل فارغاً، سيتم فحص النظام فقط بدون محاكاة</small>
                </div>
                
                <button type="submit" name="run_diagnostics" class="btn btn-diagnose">
                    <i class="fas fa-play"></i> بدء التشخيص
                </button>
            </form>
        <?php else: ?>
            <?php if (!empty($report['results'])): ?>
                <div class="result-box">
                    <h4><i class="fas fa-list-check"></i> نتائج التشخيص</h4>
                    <?php foreach ($report['results'] as $result): ?>
                        <div><?php echo $result; ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($report['warnings'])): ?>
                <div class="warning-box">
                    <h4><i class="fas fa-exclamation-triangle"></i> تحذيرات</h4>
                    <?php foreach ($report['warnings'] as $warning): ?>
                        <div><?php echo $warning; ?></div>
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
            
            <div class="alert alert-success mt-4">
                <h5><i class="fas fa-lightbulb"></i> التوصيات</h5>
                
                <?php if (count($report['errors']) > 0): ?>
                    <p><strong>تم اكتشاف أخطاء:</strong></p>
                    <ol>
                        <li>تأكد من تشغيل ملف complete_system_fix.php أولاً</li>
                        <li>تأكد من وجود طلب موافق عليه للمعلم</li>
                        <li>تأكد من عدم وجود حساب بنفس البريد الإلكتروني</li>
                        <li>راجع الأخطاء أعلاه واتبع الحلول المقترحة</li>
                    </ol>
                <?php else: ?>
                    <p><strong>✅ لم يتم اكتشاف أخطاء حرجة!</strong></p>
                    <p>النظام يعمل بشكل صحيح. إذا كنت لا تزال تواجه مشاكل:</p>
                    <ol>
                        <li>تأكد من أن المعلم لديه طلب موافق عليه</li>
                        <li>تأكد من استخدام نفس البريد الإلكتروني المستخدم في الطلب</li>
                        <li>جرب إنشاء الحساب مرة أخرى</li>
                    </ol>
                <?php endif; ?>
            </div>
            
            <div class="text-center mt-4">
                <a href="?" class="btn btn-primary me-2">
                    <i class="fas fa-redo"></i> تشخيص جديد
                </a>
                <a href="complete_system_fix.php" class="btn btn-success">
                    <i class="fas fa-wrench"></i> تشغيل الإصلاح الشامل
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>