<?php
/**
 * صفحة تسجيل الدخول المُصلحة - منصة همّة التوجيهي
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

function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

function redirect($url) {
    if (!headers_sent()) {
        header("Location: " . $url);
        exit();
    } else {
        echo "<script>window.location.href='" . $url . "';</script>";
        exit();
    }
}

function is_logged_in() {
    return isset($_SESSION["user_id"]) && !empty($_SESSION["user_id"]);
}

function log_user_activity($user_id, $action, $description = "") {
    try {
        $conn = getDBConnection();
        
        // إنشاء جدول سجل الأنشطة إذا لم يكن موجوداً
        $conn->exec("
            CREATE TABLE IF NOT EXISTS activity_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                action VARCHAR(100) NOT NULL,
                description TEXT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user (user_id),
                INDEX idx_action (action),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $stmt = $conn->prepare("
            INSERT INTO activity_log (user_id, action, description, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");

        $ip = $_SERVER["REMOTE_ADDR"] ?? "unknown";
        $user_agent = $_SERVER["HTTP_USER_AGENT"] ?? "unknown";

        $stmt->execute([$user_id, $action, $description, $ip, $user_agent]);
        return true;
    } catch (Exception $e) {
        error_log("خطأ في تسجيل النشاط: " . $e->getMessage());
        return false;
    }
}

// إعادة توجيه المستخدمين المسجلين
if (is_logged_in()) {
    $role = $_SESSION["role"] ?? "student";
    redirect("../{$role}/dashboard.php");
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $conn = getDBConnection();
        
        // إنشاء جدول المستخدمين إذا لم يكن موجوداً
        $conn->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(100) UNIQUE,
                full_name VARCHAR(100) NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                phone VARCHAR(20),
                role ENUM('admin', 'teacher', 'student') NOT NULL DEFAULT 'student',
                profile_image VARCHAR(255),
                bio TEXT,
                is_active BOOLEAN DEFAULT TRUE,
                email_verified BOOLEAN DEFAULT TRUE,
                avatar VARCHAR(500) DEFAULT NULL,
                last_seen TIMESTAMP NULL DEFAULT NULL,
                last_login TIMESTAMP NULL DEFAULT NULL,
                is_online BOOLEAN DEFAULT FALSE,
                status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_username (username),
                INDEX idx_email (email),
                INDEX idx_role (role),
                INDEX idx_status (status),
                INDEX idx_last_seen (last_seen)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // التأكد من وجود مدير عام
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        $stmt->execute();
        $admin_count = $stmt->fetchColumn();
        
        if ($admin_count == 0) {
            // إنشاء حساب المدير العام
            $admin_password = password_hash('password', PASSWORD_DEFAULT);
            $stmt = $conn->prepare("
                INSERT INTO users (username, email, password, full_name, role, is_active, email_verified) 
                VALUES ('admin', 'admin@himma.edu', ?, 'المدير العام', 'admin', 1, 1)
            ");
            $stmt->execute([$admin_password]);
        }
        
        $login = sanitize_input($_POST["login"] ?? "");
        $password = $_POST["password"] ?? "";

        // التحقق من البيانات المدخلة
        if (empty($login) || empty($password)) {
            throw new Exception("يرجى إدخال البريد الإلكتروني/اسم المستخدم وكلمة المرور");
        }

        // البحث عن المستخدم
        $stmt = $conn->prepare("
            SELECT id, username, email, password, full_name, role, is_active 
            FROM users 
            WHERE (email = ? OR username = ?) AND is_active = 1
        ");
        
        $stmt->execute([$login, $login]);
        $user = $stmt->fetch();

        if (!$user) {
            throw new Exception("البيانات المدخلة غير صحيحة");
        }

        // التحقق من كلمة المرور
        if (!verify_password($password, $user["password"])) {
            throw new Exception("البيانات المدخلة غير صحيحة");
        }

        // تحديث وقت آخر دخول
        $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW(), is_online = 1 WHERE id = ?");
        $updateStmt->execute([$user["id"]]);

        // إنشاء جلسة المستخدم
        $_SESSION["user_id"] = $user["id"];
        $_SESSION["username"] = $user["username"];
        $_SESSION["full_name"] = $user["full_name"];
        $_SESSION["email"] = $user["email"];
        $_SESSION["role"] = $user["role"];
        $_SESSION["is_logged_in"] = true;

        // تسجيل النشاط
        log_user_activity($user["id"], "login", "تسجيل دخول ناجح");

        // إعادة توجيه حسب نوع المستخدم
        $redirect_url = "../{$user["role"]}/dashboard.php";
        
        // التحقق من وجود الملف قبل التوجيه
        $dashboard_file = __DIR__ . "/../{$user["role"]}/dashboard.php";
        if (!file_exists($dashboard_file)) {
            // إذا لم يوجد dashboard للدور، توجيه للصفحة المُصلحة
            if ($user["role"] === "admin") {
                $redirect_url = "../admin/dashboard_fixed.php";
            } else {
                $redirect_url = "../home/index.php";
            }
        }
        
        redirect($redirect_url);

    } catch (PDOException $e) {
        error_log("Database error in login: " . $e->getMessage());
        $error = "حدث خطأ في النظام، يرجى المحاولة لاحقاً";
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - منصة همّة التوجيهي</title>
    
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
            max-width: 400px;
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
            background: #e8f5e8;
            border: 1px solid #4caf50;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="auth-container">
            <div class="auth-card">
                <div class="text-center mb-4">
                    <i class="fas fa-graduation-cap fa-3x text-primary mb-3"></i>
                    <h2 class="text-primary">تسجيل الدخول</h2>
                    <p class="text-muted">مرحباً بك في منصة همّة التوجيهي</p>
                </div>

                <div class="info-box">
                    <i class="fas fa-info-circle text-success me-2"></i>
                    <strong>بيانات تجريبية:</strong><br>
                    المدير العام: admin / password
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="login" class="form-label">البريد الإلكتروني أو اسم المستخدم</label>
                        <input type="text" class="form-control" id="login" name="login" 
                               value="<?php echo htmlspecialchars($_POST['login'] ?? ''); ?>" required>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label">كلمة المرور</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-sign-in-alt"></i> تسجيل الدخول
                    </button>
                </form>

                <div class="text-center mt-3">
                    <p>ليس لديك حساب؟ <a href="register_teacher_fixed.php" class="text-primary">إنشاء حساب جديد</a></p>
                    <p><a href="../home/index.php" class="text-primary">العودة للصفحة الرئيسية</a></p>
                </div>

               
            </div>
        </div>
    </div>
</body>
</html>