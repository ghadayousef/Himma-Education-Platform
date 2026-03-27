<?php
/**
 * صفحة تسجيل المعلمين المُصلحة - منصة همّة التوجيهي
 * تم إصلاح: يجب الموافقة على الطلب من المدير قبل إنشاء الحساب
 * تم إصلاح: مشكلة الأعمدة المفقودة في جدول users
 */

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

// دوال مساعدة
function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validate_password($password) {
    return strlen($password) >= 6;
}

function send_notification($user_id, $title, $message, $type = 'system') {
    try {
        $conn = getDBConnection();
        
        // إنشاء جدول الإشعارات إذا لم يكن موجوداً
        $conn->exec("
            CREATE TABLE IF NOT EXISTS notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                title VARCHAR(200) NOT NULL,
                message TEXT NOT NULL,
                type ENUM('system', 'chat', 'assignment', 'grade', 'announcement') DEFAULT 'system',
                related_id INT NULL,
                is_read BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user (user_id),
                INDEX idx_read (is_read),
                INDEX idx_type (type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, title, message, type) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $title, $message, $type]);
        return true;
    } catch (Exception $e) {
        error_log("Notification error: " . $e->getMessage());
        return false;
    }
}

// إعادة توجيه المستخدمين المسجلين
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? 'student';
    header("Location: ../{$role}/dashboard.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $conn = getDBConnection();
        
        // التحقق من وجود الأعمدة المطلوبة في جدول users
        $stmt = $conn->query("DESCRIBE users");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // إضافة الأعمدة المفقودة إن لم تكن موجودة
        $required_columns = [
            'email_verified' => "ALTER TABLE users ADD COLUMN email_verified BOOLEAN DEFAULT TRUE",
            'avatar' => "ALTER TABLE users ADD COLUMN avatar VARCHAR(500) DEFAULT NULL",
            'last_seen' => "ALTER TABLE users ADD COLUMN last_seen TIMESTAMP NULL DEFAULT NULL",
            'last_login' => "ALTER TABLE users ADD COLUMN last_login TIMESTAMP NULL DEFAULT NULL",
            'is_online' => "ALTER TABLE users ADD COLUMN is_online BOOLEAN DEFAULT FALSE"
        ];
        
        foreach ($required_columns as $column => $sql) {
            if (!in_array($column, $columns)) {
                try {
                    $conn->exec($sql);
                } catch (Exception $e) {
                    // العمود قد يكون موجوداً بالفعل
                }
            }
        }
        
        // إنشاء الجداول المطلوبة إذا لم تكن موجودة
        $conn->exec("
            CREATE TABLE IF NOT EXISTS app_d2335_teacher_applications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                teacher_name VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL,
                phone VARCHAR(20) DEFAULT NULL,
                region_id INT DEFAULT NULL,
                directorate VARCHAR(100) DEFAULT NULL,
                subject_specialization VARCHAR(200) NOT NULL,
                experience_years INT DEFAULT 0,
                qualifications TEXT DEFAULT NULL,
                status ENUM('pending', 'approved', 'rejected', 'under_review') DEFAULT 'pending',
                teacher_user_id INT DEFAULT NULL,
                review_notes TEXT DEFAULT NULL,
                submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                reviewed_by INT DEFAULT NULL,
                reviewed_at TIMESTAMP NULL DEFAULT NULL,
                INDEX idx_email (email),
                INDEX idx_status (status),
                INDEX idx_region (region_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        $email = sanitize_input($_POST['email'] ?? '');
        
        if (empty($email)) {
            throw new Exception('يرجى إدخال البريد الإلكتروني');
        }
        
        if (!validate_email($email)) {
            throw new Exception('البريد الإلكتروني غير صالح');
        }
        
        // التحقق من وجود طلب التحاق موافق عليه
        $stmt = $conn->prepare("
            SELECT * FROM app_d2335_teacher_applications 
            WHERE email = ? AND status = 'approved' AND teacher_user_id IS NULL
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$email]);
        $application = $stmt->fetch();
        
        if (!$application) {
            throw new Exception('لا يوجد طلب التحاق موافق عليه لهذا البريد الإلكتروني. يرجى تقديم طلب التحاق أولاً والانتظار حتى تتم الموافقة عليه من قبل الإدارة.');
        }
        
        // التحقق من عدم وجود حساب بنفس البريد الإلكتروني
        $userStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $userStmt->execute([$email]);
        
        if ($userStmt->fetch()) {
            throw new Exception('يوجد حساب بهذا البريد الإلكتروني بالفعل');
        }
        
        // جمع باقي البيانات
        $username = sanitize_input($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $phone = sanitize_input($_POST['phone'] ?? '');
        
        if (empty($username) || empty($password)) {
            throw new Exception('جميع الحقول المطلوبة يجب ملؤها');
        }
        
        if (!validate_password($password)) {
            throw new Exception('كلمة المرور يجب أن تكون 6 أحرف على الأقل');
        }
        
        if ($password !== $confirm_password) {
            throw new Exception('كلمة المرور وتأكيدها غير متطابقتين');
        }
        
        // التحقق من عدم وجود اسم المستخدم
        $usernameStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $usernameStmt->execute([$username]);
        
        if ($usernameStmt->fetch()) {
            throw new Exception('اسم المستخدم مستخدم بالفعل');
        }
        
        // تشفير كلمة المرور
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // بدء معاملة قاعدة البيانات
        $conn->beginTransaction();
        
        try {
            // إنشاء حساب المعلم - استخدام الأعمدة الموجودة فقط
            $insertStmt = $conn->prepare("
                INSERT INTO users (username, email, password, full_name, phone, role, is_active, created_at) 
                VALUES (?, ?, ?, ?, ?, 'teacher', 1, NOW())
            ");
            
            if ($insertStmt->execute([$username, $email, $hashedPassword, $application['teacher_name'], $phone])) {
                $userId = $conn->lastInsertId();
                
                // ربط الحساب بطلب الالتحاق
                $updateStmt = $conn->prepare("
                    UPDATE app_d2335_teacher_applications 
                    SET teacher_user_id = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $updateStmt->execute([$userId, $application['id']]);
                
                // إرسال إشعار ترحيبي
                send_notification(
                    $userId, 
                    'مرحباً بك في منصة همّة التوجيهي', 
                    'تم إنشاء حسابك بنجاح كمعلم في المنصة. يمكنك الآن تسجيل الدخول والبدء في استخدام المنصة.', 
                    'system'
                );
                
                // تأكيد المعاملة
                $conn->commit();
                
                $success = 'تم إنشاء حسابك بنجاح! يمكنك الآن تسجيل الدخول';
            } else {
                throw new Exception('فشل في إنشاء الحساب، يرجى المحاولة مرة أخرى');
            }
        } catch (Exception $e) {
            $conn->rollBack();
            throw $e;
        }
        
    } catch (PDOException $e) {
        error_log("Database error in teacher register: " . $e->getMessage());
        $error = 'حدث خطأ في النظام: ' . $e->getMessage();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// الحصول على المناطق المتاحة
$regions = [];
try {
    $conn = getDBConnection();
    
    // إنشاء جدول المناطق إذا لم يكن موجوداً
    $conn->exec("
        CREATE TABLE IF NOT EXISTS app_d2335_regions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description TEXT DEFAULT NULL,
            code VARCHAR(10) DEFAULT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_name (name),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    $stmt = $conn->prepare("SELECT id, name FROM app_d2335_regions WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $regions = $stmt->fetchAll();
} catch (Exception $e) {
    // تسجيل الخطأ فقط
    error_log("Error fetching regions: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل المعلمين - منصة همّة التوجيهي</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
        }
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .auth-container {
            max-width: 500px;
            margin: 0 auto;
            padding: 2rem;
        }
        .auth-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
        }
        .info-box {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        .alert {
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="auth-container">
            <div class="auth-card">
                <div class="text-center mb-4">
                    <i class="fas fa-chalkboard-teacher fa-3x text-primary mb-3"></i>
                    <h2 class="text-primary">تسجيل المعلمين</h2>
                    <p class="text-muted">إنشاء حساب معلم في منصة همّة التوجيهي</p>
                </div>

                <div class="info-box">
                    <i class="fas fa-info-circle text-primary me-2"></i>
                    <strong>ملاحظة مهمة:</strong> يجب أن يكون لديك طلب التحاق موافق عليه من إدارة المنطقة قبل إنشاء الحساب.
                    <br>
                    <a href="../teacher_application_form.php" class="text-primary">تقديم طلب التحاق جديد</a>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                    </div>
                    <div class="text-center">
                        <a href="login.php" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt me-2"></i>تسجيل الدخول
                        </a>
                    </div>
                <?php else: ?>

                <form method="POST" action="" id="registerForm">
                    <div class="mb-3">
                        <label for="email" class="form-label">البريد الإلكتروني المستخدم في طلب الالتحاق</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                        <div class="form-text">يجب أن يكون نفس البريد المستخدم في طلب الالتحاق الموافق عليه</div>
                    </div>

                    <div class="mb-3">
                        <label for="username" class="form-label">اسم المستخدم</label>
                        <input type="text" class="form-control" id="username" name="username" 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="phone" class="form-label">رقم الهاتف (اختياري)</label>
                        <input type="tel" class="form-control" id="phone" name="phone" 
                               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">كلمة المرور</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <div class="form-text">يجب أن تكون 6 أحرف على الأقل</div>
                    </div>

                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">تأكيد كلمة المرور</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-user-plus"></i> إنشاء الحساب
                    </button>
                </form>

                <?php endif; ?>

                <div class="text-center mt-3">
                    <p>لديك حساب بالفعل؟ <a href="login.php" class="text-primary">تسجيل الدخول</a></p>
                    <p><a href="../home/index.php" class="text-primary">العودة للصفحة الرئيسية</a></p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('كلمة المرور وتأكيدها غير متطابقتين');
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('كلمة المرور يجب أن تكون 6 أحرف على الأقل');
                return false;
            }
        });
    </script>
</body>
</html>