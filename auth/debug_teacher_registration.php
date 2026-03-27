<?php
/**
 * ملف تشخيص شامل لمشكلة تسجيل المعلمين
 * يكشف جميع الأخطاء المحتملة ويعرض تفاصيل دقيقة
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// دالة الاتصال بقاعدة البيانات
function getDBConnection() {
    $host = 'localhost';
    $db = 'himma_tawjihi';
    $user = 'root';
    $pass = '';
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    try {
        return new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        throw new Exception('فشل الاتصال بقاعدة البيانات: ' . $e->getMessage());
    }
}

$debug_info = [];
$errors = [];
$warnings = [];
$success_steps = [];

// التحقق من الاتصال بقاعدة البيانات
try {
    $conn = getDBConnection();
    $success_steps[] = "✅ الاتصال بقاعدة البيانات نجح";
} catch (Exception $e) {
    $errors[] = "❌ فشل الاتصال بقاعدة البيانات: " . $e->getMessage();
    die(renderDebugPage($debug_info, $errors, $warnings, $success_steps));
}

// التحقق من وجود الجداول المطلوبة
try {
    // فحص جدول المستخدمين
    $stmt = $conn->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        $success_steps[] = "✅ جدول users موجود";
        
        // فحص أعمدة جدول المستخدمين
        $stmt = $conn->query("DESCRIBE users");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $debug_info['users_columns'] = $columns;
        $success_steps[] = "✅ أعمدة جدول users: " . implode(', ', $columns);
    } else {
        $errors[] = "❌ جدول users غير موجود";
    }
    
    // فحص جدول طلبات المعلمين
    $tables_to_check = ['teacher_applications', 'app_d2335_teacher_applications'];
    $teacher_app_table = null;
    
    foreach ($tables_to_check as $table) {
        $stmt = $conn->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            $teacher_app_table = $table;
            $success_steps[] = "✅ جدول طلبات المعلمين موجود: $table";
            
            // فحص الأعمدة
            $stmt = $conn->query("DESCRIBE $table");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $debug_info['teacher_app_columns'] = $columns;
            $success_steps[] = "✅ أعمدة جدول $table: " . implode(', ', $columns);
            break;
        }
    }
    
    if (!$teacher_app_table) {
        $errors[] = "❌ جدول طلبات المعلمين غير موجود";
    }
    
} catch (Exception $e) {
    $errors[] = "❌ خطأ في فحص الجداول: " . $e->getMessage();
}

// معالجة النموذج
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $debug_info['post_data'] = $_POST;
    $success_steps[] = "✅ تم استلام بيانات النموذج";
    
    try {
        // الخطوة 1: التحقق من البيانات المدخلة
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $phone = trim($_POST['phone'] ?? '');
        
        $debug_info['input_data'] = [
            'email' => $email,
            'username' => $username,
            'password_length' => strlen($password),
            'phone' => $phone
        ];
        
        $success_steps[] = "✅ البيانات المدخلة: البريد=$email، اسم المستخدم=$username، طول كلمة المرور=" . strlen($password);
        
        // التحقق من الحقول الفارغة
        if (empty($email)) {
            $errors[] = "❌ البريد الإلكتروني فارغ";
        }
        if (empty($username)) {
            $errors[] = "❌ اسم المستخدم فارغ";
        }
        if (empty($password)) {
            $errors[] = "❌ كلمة المرور فارغة";
        }
        
        // التحقق من صحة البريد الإلكتروني
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "❌ البريد الإلكتروني غير صالح";
        } else {
            $success_steps[] = "✅ البريد الإلكتروني صالح";
        }
        
        // التحقق من طول كلمة المرور
        if (strlen($password) < 6) {
            $errors[] = "❌ كلمة المرور أقل من 6 أحرف (الطول الحالي: " . strlen($password) . ")";
        } else {
            $success_steps[] = "✅ طول كلمة المرور مناسب (" . strlen($password) . " حرف)";
        }
        
        // التحقق من تطابق كلمات المرور
        if ($password !== $confirm_password) {
            $errors[] = "❌ كلمة المرور وتأكيدها غير متطابقتين";
        } else {
            $success_steps[] = "✅ كلمة المرور وتأكيدها متطابقتان";
        }
        
        // إذا كانت هناك أخطاء في التحقق، توقف هنا
        if (!empty($errors)) {
            throw new Exception("فشل التحقق من البيانات المدخلة");
        }
        
        // الخطوة 2: البحث عن طلب موافق عليه
        $success_steps[] = "✅ بدء البحث عن طلب موافق عليه للبريد: $email";
        
        $stmt = $conn->prepare("
            SELECT * FROM $teacher_app_table 
            WHERE email = ? 
            ORDER BY id DESC
        ");
        $stmt->execute([$email]);
        $all_applications = $stmt->fetchAll();
        
        $debug_info['all_applications'] = $all_applications;
        $success_steps[] = "✅ تم العثور على " . count($all_applications) . " طلب(ات) لهذا البريد";
        
        // عرض تفاصيل كل طلب
        foreach ($all_applications as $idx => $app) {
            $success_steps[] = "📋 الطلب #" . ($idx + 1) . ": الحالة=" . $app['status'] . 
                              ", teacher_user_id=" . ($app['teacher_user_id'] ?? 'NULL');
        }
        
        // البحث عن طلب موافق عليه بدون حساب
        $stmt = $conn->prepare("
            SELECT * FROM $teacher_app_table 
            WHERE email = ? AND status = 'approved' AND teacher_user_id IS NULL
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$email]);
        $application = $stmt->fetch();
        
        if (!$application) {
            $errors[] = "❌ لا يوجد طلب موافق عليه بدون حساب مرتبط لهذا البريد";
            
            // فحص إذا كان هناك طلب موافق عليه لكن مرتبط بحساب
            $stmt = $conn->prepare("
                SELECT * FROM $teacher_app_table 
                WHERE email = ? AND status = 'approved' AND teacher_user_id IS NOT NULL
                ORDER BY id DESC LIMIT 1
            ");
            $stmt->execute([$email]);
            $linked_app = $stmt->fetch();
            
            if ($linked_app) {
                $warnings[] = "⚠️ يوجد طلب موافق عليه لكنه مرتبط بحساب موجود (teacher_user_id=" . $linked_app['teacher_user_id'] . ")";
            }
            
            throw new Exception("لا يوجد طلب التحاق موافق عليه");
        }
        
        $success_steps[] = "✅ تم العثور على طلب موافق عليه (ID=" . $application['id'] . ")";
        $debug_info['approved_application'] = $application;
        
        // الخطوة 3: التحقق من عدم وجود حساب بنفس البريد
        $stmt = $conn->prepare("SELECT id, username, email FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $existing_user = $stmt->fetch();
        
        if ($existing_user) {
            $errors[] = "❌ يوجد حساب بهذا البريد الإلكتروني بالفعل (ID=" . $existing_user['id'] . ", username=" . $existing_user['username'] . ")";
            $debug_info['existing_user'] = $existing_user;
            throw new Exception("البريد الإلكتروني مستخدم بالفعل");
        }
        
        $success_steps[] = "✅ البريد الإلكتروني غير مستخدم";
        
        // الخطوة 4: التحقق من عدم وجود اسم المستخدم
        $stmt = $conn->prepare("SELECT id, username, email FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $existing_username = $stmt->fetch();
        
        if ($existing_username) {
            $errors[] = "❌ اسم المستخدم مستخدم بالفعل (ID=" . $existing_username['id'] . ", email=" . $existing_username['email'] . ")";
            $debug_info['existing_username'] = $existing_username;
            throw new Exception("اسم المستخدم مستخدم بالفعل");
        }
        
        $success_steps[] = "✅ اسم المستخدم متاح";
        
        // الخطوة 5: تشفير كلمة المرور
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $success_steps[] = "✅ تم تشفير كلمة المرور بنجاح";
        $debug_info['password_hash'] = substr($hashedPassword, 0, 20) . '...';
        
        // الخطوة 6: بدء معاملة قاعدة البيانات
        $conn->beginTransaction();
        $success_steps[] = "✅ بدء معاملة قاعدة البيانات";
        
        try {
            // الخطوة 7: إنشاء حساب المعلم
            $full_name = $application['teacher_name'] ?? $application['full_name'] ?? 'معلم';
            
            $insertStmt = $conn->prepare("
                INSERT INTO users (username, email, password, full_name, phone, role, is_active, email_verified, created_at) 
                VALUES (?, ?, ?, ?, ?, 'teacher', 1, 1, NOW())
            ");
            
            $insert_result = $insertStmt->execute([$username, $email, $hashedPassword, $full_name, $phone]);
            
            if (!$insert_result) {
                $errors[] = "❌ فشل تنفيذ استعلام INSERT";
                $debug_info['insert_error'] = $insertStmt->errorInfo();
                throw new Exception("فشل في إنشاء الحساب");
            }
            
            $userId = $conn->lastInsertId();
            $success_steps[] = "✅ تم إنشاء حساب المستخدم بنجاح (ID=$userId)";
            $debug_info['new_user_id'] = $userId;
            
            // الخطوة 8: ربط الحساب بطلب الالتحاق
            $updateStmt = $conn->prepare("
                UPDATE $teacher_app_table 
                SET teacher_user_id = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $update_result = $updateStmt->execute([$userId, $application['id']]);
            
            if (!$update_result) {
                $errors[] = "❌ فشل تحديث طلب الالتحاق";
                $debug_info['update_error'] = $updateStmt->errorInfo();
                throw new Exception("فشل في ربط الحساب بالطلب");
            }
            
            $success_steps[] = "✅ تم ربط الحساب بطلب الالتحاق";
            
            // الخطوة 9: تأكيد المعاملة
            $conn->commit();
            $success_steps[] = "✅ تم تأكيد جميع التغييرات في قاعدة البيانات";
            
            // التحقق النهائي
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $created_user = $stmt->fetch();
            
            if ($created_user) {
                $success_steps[] = "✅ تم التحقق من إنشاء الحساب بنجاح";
                $debug_info['created_user'] = [
                    'id' => $created_user['id'],
                    'username' => $created_user['username'],
                    'email' => $created_user['email'],
                    'full_name' => $created_user['full_name'],
                    'role' => $created_user['role']
                ];
            }
            
            $success_steps[] = "🎉 تم إنشاء الحساب بنجاح! يمكنك الآن تسجيل الدخول";
            
        } catch (Exception $e) {
            $conn->rollBack();
            $errors[] = "❌ تم التراجع عن المعاملة: " . $e->getMessage();
            throw $e;
        }
        
    } catch (Exception $e) {
        $errors[] = "❌ خطأ عام: " . $e->getMessage();
        $debug_info['exception'] = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ];
    }
}

echo renderDebugPage($debug_info, $errors, $warnings, $success_steps);

function renderDebugPage($debug_info, $errors, $warnings, $success_steps) {
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="ar" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>تشخيص تسجيل المعلمين - منصة همة التوجيهي</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                padding: 20px;
            }
            .container {
                max-width: 1200px;
                background: white;
                border-radius: 15px;
                padding: 30px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            }
            .header {
                background: linear-gradient(135deg, #2c3e50, #3498db);
                color: white;
                padding: 20px;
                border-radius: 10px;
                margin-bottom: 30px;
            }
            .step-box {
                background: #f8f9fa;
                border-right: 4px solid #28a745;
                padding: 15px;
                margin: 10px 0;
                border-radius: 5px;
            }
            .error-box {
                background: #fff5f5;
                border-right: 4px solid #dc3545;
                padding: 15px;
                margin: 10px 0;
                border-radius: 5px;
            }
            .warning-box {
                background: #fffbf0;
                border-right: 4px solid #ffc107;
                padding: 15px;
                margin: 10px 0;
                border-radius: 5px;
            }
            .debug-box {
                background: #e7f3ff;
                border: 1px solid #0066cc;
                padding: 15px;
                margin: 10px 0;
                border-radius: 5px;
                max-height: 400px;
                overflow-y: auto;
            }
            pre {
                background: #f4f4f4;
                padding: 10px;
                border-radius: 5px;
                overflow-x: auto;
            }
            .form-section {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 10px;
                margin-top: 20px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1><i class="fas fa-bug"></i> تشخيص شامل لتسجيل المعلمين</h1>
                <p class="mb-0">يكشف هذا الملف جميع الأخطاء المحتملة ويعرض تفاصيل دقيقة لكل خطوة</p>
            </div>

            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <h4><i class="fas fa-exclamation-triangle"></i> الأخطاء المكتشفة (<?php echo count($errors); ?>)</h4>
                <?php foreach ($errors as $error): ?>
                    <div class="error-box"><?php echo $error; ?></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($warnings)): ?>
            <div class="alert alert-warning">
                <h4><i class="fas fa-exclamation-circle"></i> التحذيرات (<?php echo count($warnings); ?>)</h4>
                <?php foreach ($warnings as $warning): ?>
                    <div class="warning-box"><?php echo $warning; ?></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($success_steps)): ?>
            <div class="alert alert-success">
                <h4><i class="fas fa-check-circle"></i> الخطوات الناجحة (<?php echo count($success_steps); ?>)</h4>
                <?php foreach ($success_steps as $step): ?>
                    <div class="step-box"><?php echo $step; ?></div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($debug_info)): ?>
            <div class="debug-box">
                <h4><i class="fas fa-code"></i> معلومات التشخيص التفصيلية</h4>
                <pre><?php echo htmlspecialchars(print_r($debug_info, true)); ?></pre>
            </div>
            <?php endif; ?>

            <div class="form-section">
                <h4><i class="fas fa-user-plus"></i> نموذج اختبار التسجيل</h4>
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">البريد الإلكتروني</label>
                            <input type="email" class="form-control" name="email" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">اسم المستخدم</label>
                            <input type="text" class="form-control" name="username" 
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">رقم الهاتف (اختياري)</label>
                            <input type="tel" class="form-control" name="phone" 
                                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">كلمة المرور (6 أحرف على الأقل)</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">تأكيد كلمة المرور</label>
                            <input type="password" class="form-control" name="confirm_password" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-play"></i> اختبار التسجيل
                    </button>
                    <a href="register_teacher_fixed.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> العودة للتسجيل العادي
                    </a>
                </form>
            </div>

            <div class="alert alert-info mt-4">
                <h5><i class="fas fa-info-circle"></i> ملاحظات مهمة:</h5>
                <ul>
                    <li>تأكد من وجود طلب التحاق موافق عليه (status='approved') للبريد الإلكتروني</li>
                    <li>تأكد من أن الطلب الموافق عليه غير مرتبط بحساب (teacher_user_id IS NULL)</li>
                    <li>كلمة المرور يجب أن تكون 6 أحرف على الأقل</li>
                    <li>البريد الإلكتروني واسم المستخدم يجب أن يكونا فريدين</li>
                </ul>
            </div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}
?>