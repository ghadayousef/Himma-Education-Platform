<?php
/**
 * الصفحة الرئيسية المحدثة - منصة همّة التوجيهي
 * Updated Homepage - Himma Tawjihi Platform
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Handle login form submission
$login_error = '';
$register_error = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        
        // Login Process
        if ($_POST['action'] === 'login') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (empty($username) || empty($password)) {
                $login_error = 'يرجى إدخال اسم المستخدم وكلمة المرور';
            } else {
                try {
                    $db = new Database();
                    $conn = $db->connect();
                    
                    $stmt = $conn->prepare("SELECT id, username, email, password, full_name, role FROM users WHERE (username = ? OR email = ?) AND is_active = 1");
                    $stmt->execute([$username, $username]);
                    $user = $stmt->fetch();
                    
                    if ($user && password_verify($password, $user['password'])) {
                        // Successful login
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['email'] = $user['email'];
                        
                        // Update last login
                        $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                        $update_stmt->execute([$user['id']]);
                        
                        // Redirect based on role
                        switch ($user['role']) {
                            case 'admin':
                                header('Location: ../admin/dashboard.php');
                                break;
                            case 'teacher':
                                header('Location: ../teacher/dashboard.php');
                                break;
                            case 'student':
                                header('Location: ../student/dashboard.php');
                                break;
                            default:
                                header('Location: home/index.php');
                        }
                        exit();
                    } else {
                        $login_error = 'اسم المستخدم أو كلمة المرور غير صحيحة';
                    }
                } catch (Exception $e) {
                    $login_error = 'حدث خطأ في النظام، يرجى المحاولة لاحقاً';
                    error_log("Login error: " . $e->getMessage());
                }
            }
        }
        
        // Registration Process (للطلاب فقط)
        elseif ($_POST['action'] === 'register') {
            $full_name = trim($_POST['reg_full_name'] ?? '');
            $username = trim($_POST['reg_username'] ?? '');
            $email = trim($_POST['reg_email'] ?? '');
            $phone = trim($_POST['reg_phone'] ?? '');
            $password = $_POST['reg_password'] ?? '';
            $confirm_password = $_POST['reg_confirm_password'] ?? '';
            $role = 'student'; // دائماً طالب في هذا النموذج
            
            // Validation
            if (empty($full_name) || empty($username) || empty($email) || empty($password)) {
                $register_error = 'جميع الحقول المطلوبة يجب ملؤها';
            } elseif (strlen($username) < 3) {
                $register_error = 'اسم المستخدم يجب أن يكون أكثر من 3 أحرف';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $register_error = 'البريد الإلكتروني غير صحيح';
            } elseif (strlen($password) < 6) {
                $register_error = 'كلمة المرور يجب أن تكون أكثر من 6 أحرف';
            } elseif ($password !== $confirm_password) {
                $register_error = 'كلمة المرور وتأكيد كلمة المرور غير متطابقتان';
            } else {
                try {
                    $db = new Database();
                    $conn = $db->connect();
                    
                    // Check if username or email exists
                    $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                    $check_stmt->execute([$username, $email]);
                    
                    if ($check_stmt->fetch()) {
                        $register_error = 'اسم المستخدم أو البريد الإلكتروني مستخدم بالفعل';
                    } else {
                        // Create new user
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $insert_stmt = $conn->prepare("
                            INSERT INTO users (username, email, password, full_name, phone, role, is_active, email_verified, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, 1, 1, NOW())
                        ");
                        
                        if ($insert_stmt->execute([$username, $email, $hashed_password, $full_name, $phone, $role])) {
                            $success_message = 'تم إنشاء الحساب بنجاح! يمكنك الآن تسجيل الدخول';
                        } else {
                            $register_error = 'حدث خطأ أثناء إنشاء الحساب';
                        }
                    }
                } catch (Exception $e) {
                    $register_error = 'حدث خطأ في النظام، يرجى المحاولة لاحقاً';
                    error_log("Registration error: " . $e->getMessage());
                }
            }
        }
    }
}

// Get statistics and subjects for homepage
$stats = [];
$subjects = [];

// Default fallback data - always available
$default_stats = [
    'total_students' => 1250,
    'total_teachers' => 45,
    'total_subjects' => 12,
    'total_enrollments' => 3200
];

$default_subjects = [
    [
        'id' => 1,
        'name' => 'الرياضيات المتقدمة',
        'description' => 'دراسة شاملة للرياضيات على مستوى التوجيهي العلمي تشمل التفاضل والتكامل والجبر المتقدم والهندسة التحليلية',
        'teacher_name' => 'أ. أحمد محمد الخالدي',
        'category' => 'scientific',
        'price' => 180.00,
        'students_count' => 245,
        'level' => 'advanced'
    ],
    [
        'id' => 2,
        'name' => 'الفيزياء العامة',
        'description' => 'أساسيات الفيزياء والميكانيكا والكهرباء والمغناطيسية والبصريات مع التطبيقات العملية والتجارب المعملية',
        'teacher_name' => 'أ. فاطمة علي الزهراني',
        'category' => 'scientific',
        'price' => 150.00,
        'students_count' => 189,
        'level' => 'intermediate'
    ],
    [
        'id' => 3,
        'name' => 'الكيمياء التطبيقية',
        'description' => 'دراسة الكيمياء العضوية وغير العضوية مع التجارب المعملية والتطبيقات الحياتية والصناعية',
        'teacher_name' => 'أ. عمر سالم القرشي',
        'category' => 'scientific',
        'price' => 160.00,
        'students_count' => 167,
        'level' => 'intermediate'
    ],
    [
        'id' => 4,
        'name' => 'اللغة العربية وآدابها',
        'description' => 'النحو والصرف والبلاغة والأدب العربي من العصر الجاهلي حتى العصر الحديث مع التطبيقات العملية',
        'teacher_name' => 'أ. مريم أحمد السالم',
        'category' => 'literary',
        'price' => 120.00,
        'students_count' => 134,
        'level' => 'intermediate'
    ],
    [
        'id' => 5,
        'name' => 'اللغة الإنجليزية المتقدمة',
        'description' => 'تطوير مهارات القراءة والكتابة والمحادثة والاستماع مع التركيز على القواعد المتقدمة والمفردات',
        'teacher_name' => 'أ. سارة محمد العلي',
        'category' => 'languages',
        'price' => 140.00,
        'students_count' => 198,
        'level' => 'advanced'
    ],
    [
        'id' => 6,
        'name' => 'التاريخ الإسلامي',
        'description' => 'دراسة التاريخ الإسلامي من البعثة النبوية حتى العصر العثماني مع التحليل والنقد التاريخي',
        'teacher_name' => 'أ. خالد يوسف الحمد',
        'category' => 'literary',
        'price' => 110.00,
        'students_count' => 156,
        'level' => 'intermediate'
    ]
];

try {
    $db = new Database();
    $conn = $db->connect();
    
    // Get stats
    $stats_stmt = $conn->prepare("
        SELECT 
            (SELECT COUNT(*) FROM users WHERE role = 'student' AND is_active = 1) as total_students,
            (SELECT COUNT(*) FROM users WHERE role = 'teacher' AND is_active = 1) as total_teachers,
            (SELECT COUNT(*) FROM subjects WHERE is_active = 1) as total_subjects,
            (SELECT COUNT(*) FROM enrollments) as total_enrollments
    ");
    $stats_stmt->execute();
    $db_stats = $stats_stmt->fetch();
    
    // Use database stats if available, otherwise use defaults
    $stats = $db_stats ? $db_stats : $default_stats;
    
    // Get featured subjects from database
    $subjects_stmt = $conn->prepare("
        SELECT s.*, u.full_name as teacher_name,
               (SELECT COUNT(*) FROM enrollments WHERE subject_id = s.id) as students_count
        FROM subjects s
        LEFT JOIN users u ON s.teacher_id = u.id
        WHERE s.is_active = 1 AND s.is_featured = 1
        ORDER BY s.created_at DESC
        LIMIT 6
    ");
    $subjects_stmt->execute();
    $db_subjects = $subjects_stmt->fetchAll();
    
    // If no subjects from database or less than 4, use default subjects
    if (!$db_subjects || count($db_subjects) < 4) {
        $subjects = $default_subjects;
    } else {
        $subjects = $db_subjects;
    }
    
} catch (Exception $e) {
    // Use fallback data if database connection fails
    $stats = $default_stats;
    $subjects = $default_subjects;
    error_log("Database error in homepage: " . $e->getMessage());
}

// Ensure we always have at least 4 subjects
if (count($subjects) < 4) {
    $subjects = $default_subjects;
}

// Category names mapping
$category_names = [
    'scientific' => 'علمي',
    'literary' => 'أدبي', 
    'languages' => 'لغات'
];

// Category icons mapping
$category_icons = [
    'scientific' => 'fas fa-flask',
    'literary' => 'fas fa-book',
    'languages' => 'fas fa-language'
];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>منصة همّة التعليمية - المنصة الأولى للتعليم الإلكتروني</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --accent-color: #f093fb;
            --success-color: #4facfe;
            --warning-color: #43e97b;
            --danger-color: #fa709a;
            --dark-color: #2c3e50;
            --light-color: #ecf0f1;
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
            --shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
        }

        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Glass Morphism Components */
        .glass {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow);
        }

        /* Navigation */
        .navbar {
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            padding: 1rem 2rem;
            transition: all 0.3s ease;
        }

        .nav-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
        }

        .logo {
            font-size: 2rem;
            font-weight: 800;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-menu {
            display: flex;
            list-style: none;
            gap: 2rem;
        }

        .nav-link {
            color: white;
            text-decoration: none;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            color: white;
        }

        .auth-buttons {
            display: flex;
            gap: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--success-color), var(--warning-color));
            color: white;
        }

        .btn-outline {
            background: transparent;
            color: white;
            border: 2px solid white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }

        /* Hero Section */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 2rem;
            position: relative;
        }

        .hero-content {
            max-width: 800px;
            color: white;
            z-index: 2;
        }

        .hero h1 {
            font-size: 4rem;
            font-weight: 800;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #fff, #f093fb);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero p {
            font-size: 1.5rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-hero {
            padding: 1rem 2rem;
            font-size: 1.1rem;
            border-radius: 20px;
        }

        /* Floating Elements */
        .floating-element {
            position: absolute;
            border-radius: 50%;
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            animation: float 6s ease-in-out infinite;
        }

        .floating-1 {
            width: 80px;
            height: 80px;
            top: 20%;
            left: 10%;
            animation-delay: 0s;
        }

        .floating-2 {
            width: 120px;
            height: 120px;
            top: 60%;
            right: 15%;
            animation-delay: 2s;
        }

        .floating-3 {
            width: 60px;
            height: 60px;
            bottom: 20%;
            left: 20%;
            animation-delay: 4s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        /* Stats Section */
        .stats-section {
            padding: 4rem 2rem;
            background: rgba(255, 255, 255, 0.05);
        }

        .stats-container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }

        .stat-card {
            text-align: center;
            color: white;
            padding: 2rem;
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--accent-color), var(--success-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-label {
            font-size: 1.2rem;
            margin-top: 0.5rem;
            opacity: 0.9;
        }

        /* Subjects Section */
        .subjects-section {
            padding: 4rem 2rem;
        }

        .section-title {
            text-align: center;
            font-size: 3rem;
            font-weight: 800;
            color: white;
            margin-bottom: 3rem;
        }

        .subjects-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
        }

        .subject-card {
            padding: 2rem;
            color: white;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }

        .subject-card:hover {
            transform: translateY(-10px);
        }

        .subject-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .subject-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            background: linear-gradient(135deg, var(--accent-color), var(--success-color));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .subject-title {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .subject-teacher {
            color: var(--accent-color);
            font-weight: 600;
        }

        .subject-description {
            margin: 1rem 0;
            opacity: 0.8;
            line-height: 1.6;
        }

        .subject-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1.5rem;
        }

        .subject-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--warning-color);
        }

        .subject-students {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            opacity: 0.8;
        }

        .subject-category {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255,255,255,0.2);
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.9rem;
        }

        .enroll-btn {
            width: 100%;
            margin-top: 1rem;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            padding: 0.75rem;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .enroll-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        /* Features Section */
        .features-section {
            padding: 4rem 2rem;
            background: rgba(255, 255, 255, 0.05);
        }

        .features-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            text-align: center;
            padding: 2rem;
            color: white;
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1rem;
            border-radius: 20px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
        }

        .feature-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .feature-description {
            opacity: 0.8;
            line-height: 1.6;
        }

        /* Footer */
        .footer {
            padding: 3rem 2rem 1rem;
            background: rgba(0, 0, 0, 0.2);
            color: white;
            text-align: center;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
        }

        .footer-logo {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 1rem;
        }

        .footer-links {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin: 2rem 0;
            flex-wrap: wrap;
        }

        .footer-link {
            color: white;
            text-decoration: none;
            opacity: 0.8;
            transition: opacity 0.3s ease;
        }

        .footer-link:hover {
            opacity: 1;
            color: white;
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 1rem;
            margin-top: 2rem;
            opacity: 0.6;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            backdrop-filter: blur(5px);
        }

        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 90%;
            max-width: 500px;
            padding: 2rem;
            color: white;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .modal-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .form-input {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-size: 1rem;
            backdrop-filter: blur(10px);
        }

        .form-input::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        .form-input:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.2);
        }

        .form-select {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-size: 1rem;
            backdrop-filter: blur(10px);
        }

        .form-select:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.2);
        }

        .form-select option {
            background: var(--dark-color);
            color: white;
        }

        .close-modal {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
        }

        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
        }

        .alert-danger {
            background: rgba(250, 112, 154, 0.2);
            color: var(--danger-color);
            border: 1px solid rgba(250, 112, 154, 0.3);
        }

        .alert-success {
            background: rgba(79, 172, 254, 0.2);
            color: var(--success-color);
            border: 1px solid rgba(79, 172, 254, 0.3);
        }

        .row {
            display: flex;
            flex-wrap: wrap;
            margin: -0.5rem;
        }

        .col-md-6 {
            flex: 0 0 50%;
            padding: 0.5rem;
        }

        /* Registration Type Selection Modal */
        .registration-type-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            backdrop-filter: blur(5px);
        }

        .registration-type-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 90%;
            max-width: 600px;
            padding: 2rem;
            color: white;
        }

        .registration-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-top: 2rem;
        }

        .registration-option {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .registration-option:hover {
            border-color: var(--accent-color);
            transform: translateY(-5px);
        }

        .registration-option i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--accent-color);
        }

        .registration-option h3 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .registration-option p {
            opacity: 0.8;
            line-height: 1.6;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .nav-menu {
                display: none;
            }

            .hero h1 {
                font-size: 2.5rem;
            }

            .hero p {
                font-size: 1.2rem;
            }

            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }

            .subjects-grid {
                grid-template-columns: 1fr;
            }

            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }

            .features-grid {
                grid-template-columns: 1fr;
            }

            .col-md-6 {
                flex: 0 0 100%;
            }

            .registration-options {
                grid-template-columns: 1fr;
            }
        }

        /* Animations */
        .fade-in {
            opacity: 0;
            transform: translateY(30px);
            animation: fadeIn 0.8s ease forwards;
        }

        @keyframes fadeIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .slide-in-left {
            opacity: 0;
            transform: translateX(-50px);
            animation: slideInLeft 0.8s ease forwards;
        }

        @keyframes slideInLeft {
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .slide-in-right {
            opacity: 0;
            transform: translateX(50px);
            animation: slideInRight 0.8s ease forwards;
        }

        @keyframes slideInRight {
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Loading Animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar glass">
        <div class="nav-container">
            <a href="index.php" class="logo">
                <i class="fas fa-graduation-cap"></i>
                همّة
            </a>
            <ul class="nav-menu">
                <li><a href="#home" class="nav-link">الرئيسية</a></li>
                <li><a href="#subjects" class="nav-link">المواد الدراسية</a></li>
                <li><a href="#features" class="nav-link">مميزات المنصة </a></li>

                <li><a href="about.php" class="nav-link">من نحن</a></li>
                <li><a href="questions.php" class="nav-link">الأسئلة الشائعة</a></li>
                <li><a href="contact.php" class="nav-link">اتصل بنا</a></li>
            </ul>
            <div class="auth-buttons">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <span style="color: white; margin-left: 1rem;">مرحباً، <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <a href="../<?php echo $_SESSION['role']; ?>/dashboard.php" class="btn btn-outline">
                        <i class="fas fa-tachometer-alt"></i>
                        لوحة التحكم
                    </a>
                    <a href="../auth/logout.php" class="btn btn-primary">
                        <i class="fas fa-sign-out-alt"></i>
                        تسجيل الخروج
                    </a>
                <?php else: ?>
                    <button class="btn btn-outline" onclick="openLoginModal()">
                        <i class="fas fa-sign-in-alt"></i>
                        تسجيل الدخول
                    </button>
                    <button class="btn btn-primary" onclick="openRegistrationTypeModal()">
                        <i class="fas fa-user-plus"></i>
                        إنشاء حساب
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero">
        <div class="floating-element floating-1"></div>
        <div class="floating-element floating-2"></div>
        <div class="floating-element floating-3"></div>
        
        <div class="hero-content fade-in">
            <h1>منصة همّة التعليمية</h1>
            <p>المنصة الأولى للتعليم الإلكتروني في قطاع غزة - لطلاب الثانوية العامة</p>
             <p>     تعلم بذكاء، انجح بتميز</p>

            <div class="hero-buttons">
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <button class="btn btn-primary btn-hero" onclick="openRegistrationTypeModal()">
                        <i class="fas fa-play"></i>
                        ابدأ التعلم الآن
                    </button>
                    <button class="btn btn-outline btn-hero" onclick="openLoginModal()">
                        <i class="fas fa-sign-in-alt"></i>
                        تسجيل الدخول
                    </button>
                <?php else: ?>
                    <a href="../<?php echo $_SESSION['role']; ?>/dashboard.php" class="btn btn-primary btn-hero">
                        <i class="fas fa-tachometer-alt"></i>
                        انتقل للوحة التحكم
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats-section">
        <div class="stats-container">
            <div class="stat-card glass slide-in-left">
                <div class="stat-number"><?php echo number_format($stats['total_students']); ?></div>
                <div class="stat-label">طالب مسجل</div>
            </div>
            <div class="stat-card glass slide-in-left" style="animation-delay: 0.2s;">
                <div class="stat-number"><?php echo number_format($stats['total_teachers']); ?></div>
                <div class="stat-label">معلم خبير</div>
            </div>
            <div class="stat-card glass slide-in-right">
                <div class="stat-number"><?php echo number_format($stats['total_subjects']); ?></div>
                <div class="stat-label">مادة دراسية</div>
            </div>
            <div class="stat-card glass slide-in-right" style="animation-delay: 0.2s;">
                <div class="stat-number">94.5%</div>
                <div class="stat-label">معدل النجاح</div>
            </div>
        </div>
    </section>

    <!-- Subjects Section -->
    <section id="subjects" class="subjects-section">
        <h2 class="section-title fade-in">المواد الدراسية المتاحة</h2>
        <div class="subjects-grid">
            <?php foreach (array_slice($subjects, 0, 6) as $index => $subject): ?>
            <div class="subject-card glass fade-in" style="animation-delay: <?php echo $index * 0.1; ?>s;">
                <div class="subject-category">
                    <?php echo $category_names[$subject['category']] ?? 'عام'; ?>
                </div>
                <div class="subject-header">
                    <div class="subject-icon">
                        <i class="<?php echo $category_icons[$subject['category']] ?? 'fas fa-book'; ?>"></i>
                    </div>
                    <div>
                        <div class="subject-title"><?php echo htmlspecialchars($subject['name']); ?></div>
                        <div class="subject-teacher"><?php echo htmlspecialchars($subject['teacher_name'] ?? 'غير محدد'); ?></div>
                    </div>
                </div>
                <div class="subject-description"><?php echo htmlspecialchars($subject['description']); ?></div>
                <div class="subject-meta">
                    <div class="subject-price"><?php echo number_format($subject['price']); ?> شيكل</div>
                    <div class="subject-students">
                        <i class="fas fa-users"></i>
                        <span><?php echo $subject['students_count']; ?> طالب</span>
                    </div>
                </div>
                <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'student'): ?>
                    <button class="enroll-btn" onclick="enrollInSubject(<?php echo $subject['id']; ?>)">
                        <i class="fas fa-plus"></i>
                        سجل في المادة
                    </button>
                <?php elseif (!isset($_SESSION['user_id'])): ?>
                    <button class="enroll-btn" onclick="openLoginModal()">
                        <i class="fas fa-sign-in-alt"></i>
                        سجل دخول للتسجيل
                    </button>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="features-section">
        <h2 class="section-title fade-in">لماذا منصة همّة؟</h2>
        <div class="features-grid">
            <div class="feature-card glass fade-in">
                <div class="feature-icon">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <h3 class="feature-title">معلمون خبراء</h3>
                <p class="feature-description">نخبة من أفضل المعلمين في قطاع غزة مع خبرة تزيد عن 10 سنوات في التدريس</p>
            </div>
            <div class="feature-card glass fade-in" style="animation-delay: 0.2s;">
                <div class="feature-icon">
                    <i class="fas fa-video"></i>
                </div>
                <h3 class="feature-title">دروس تفاعلية</h3>
                <p class="feature-description">محتوى تعليمي عالي الجودة مع فيديوهات تفاعلية وأمثلة عملية</p>
            </div>
            <div class="feature-card glass fade-in" style="animation-delay: 0.4s;">
                <div class="feature-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <h3 class="feature-title">متاح 24/7</h3>
                <p class="feature-description">تعلم في أي وقت ومن أي مكان بمرونة تامة تناسب جدولك اليومي</p>
            </div>
            <div class="feature-card glass fade-in" style="animation-delay: 0.6s;">
                <div class="feature-icon">
                    <i class="fas fa-certificate"></i>
                </div>
                <h3 class="feature-title">شهادات معتمدة</h3>
                <p class="feature-description">احصل على شهادات معتمدة عند إتمام المواد الدراسية بنجاح</p>
            </div>
            <div class="feature-card glass fade-in" style="animation-delay: 0.8s;">
                <div class="feature-icon">
                    <i class="fas fa-users"></i>
                </div>
                <h3 class="feature-title">مجتمع تعليمي</h3>
                <p class="feature-description">انضم لمجتمع من الطلاب والمعلمين للنقاش والمساعدة المتبادلة</p>
            </div>
            <div class="feature-card glass fade-in" style="animation-delay: 1s;">
                <div class="feature-icon">
                    <i class="fas fa-mobile-alt"></i>
                </div>
                <h3 class="feature-title">تطبيق الجوال</h3>
                <p class="feature-description">تعلم من هاتفك الذكي مع تطبيق سهل الاستخدام ومتزامن</p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-logo">منصة همّة التعليمية</div>
            <div class="footer-links">
                <a href="about.php" class="footer-link">من نحن</a>
                <a href="questions.php" class="footer-link">الأسئلة الشائعة</a>
                <a href="#" class="footer-link">سياسة الخصوصية</a>
                <a href="#" class="footer-link">الشروط والأحكام</a>
                <a href="contact.php" class="footer-link">اتصل بنا</a>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2025 منصة همّة التعليمية. جميع الحقوق محفوظة.</p>
            </div>
        </div>
    </footer>

    <!-- Registration Type Selection Modal -->
    <div id="registrationTypeModal" class="registration-type-modal">
        <div class="registration-type-content glass">
            <button class="close-modal" onclick="closeRegistrationTypeModal()">&times;</button>
            <div class="modal-header">
                <h2 class="modal-title">اختر نوع الحساب</h2>
                <p>يرجى اختيار نوع الحساب الذي تريد إنشاءه</p>
            </div>
            
            <div class="registration-options">
                <div class="registration-option" onclick="openStudentRegistration()">
                    <i class="fas fa-user-graduate"></i>
                    <h3>تسجيل كطالب</h3>
                    <p>للطلاب الذين يريدون الالتحاق بالمواد الدراسية والاستفادة من المحتوى التعليمي</p>
                </div>
                
                <div class="registration-option" onclick="openTeacherApplication()">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <h3>تسجيل كمعلم</h3>
                    <p>للمعلمين الذين يريدون الانضمام للمنصة وتقديم المحتوى التعليمي</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Login Modal -->
    <div id="loginModal" class="modal">
        <div class="modal-content glass">
            <button class="close-modal" onclick="closeLoginModal()">&times;</button>
            <div class="modal-header">
                <h2 class="modal-title">تسجيل الدخول</h2>
                <p>أدخل بياناتك للوصول إلى حسابك</p>
            </div>
            
            <?php if (!empty($login_error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($login_error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label class="form-label">البريد الإلكتروني أو اسم المستخدم</label>
                    <input type="text" class="form-input" name="username" placeholder="example@himma.edu" required>
                </div>
                <div class="form-group">
                    <label class="form-label">كلمة المرور</label>
                    <input type="password" class="form-input" name="password" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-sign-in-alt"></i>
                    تسجيل الدخول
                </button>
            </form>
            <div style="text-align: center; margin-top: 1rem;">
                <p>ليس لديك حساب؟ <a href="#" onclick="switchToRegistrationTypeModal()" style="color: var(--accent-color);">إنشاء حساب جديد</a></p>
            </div>
        </div>
    </div>

    <!-- Student Register Modal -->
    <div id="registerModal" class="modal">
        <div class="modal-content glass">
            <button class="close-modal" onclick="closeRegisterModal()">&times;</button>
            <div class="modal-header">
                <h2 class="modal-title">إنشاء حساب طالب</h2>
                <p>انضم إلى منصة همّة التعليمية كطالب</p>
            </div>
            
            <?php if (!empty($register_error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($register_error); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="registerForm">
                <input type="hidden" name="action" value="register">
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">الاسم الكامل</label>
                            <input type="text" class="form-input" name="reg_full_name" placeholder="الاسم الكامل" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">اسم المستخدم</label>
                            <input type="text" class="form-input" name="reg_username" placeholder="اسم المستخدم" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">البريد الإلكتروني</label>
                            <input type="email" class="form-input" name="reg_email" placeholder="البريد الإلكتروني" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">رقم الهاتف</label>
                            <input type="tel" class="form-input" name="reg_phone" placeholder="رقم الهاتف (اختياري)">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">كلمة المرور</label>
                            <input type="password" class="form-input" name="reg_password" placeholder="كلمة المرور" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">تأكيد كلمة المرور</label>
                            <input type="password" class="form-input" name="reg_confirm_password" placeholder="تأكيد كلمة المرور" required>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-user-plus"></i>
                    إنشاء حساب الطالب
                </button>
            </form>
            <div style="text-align: center; margin-top: 1rem;">
                <p>لديك حساب بالفعل؟ <a href="#" onclick="switchToLogin()" style="color: var(--accent-color);">تسجيل الدخول</a></p>
            </div>
        </div>
    </div>

    <script>
        // Registration Type Modal Functions
        function openRegistrationTypeModal() {
            document.getElementById('registrationTypeModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeRegistrationTypeModal() {
            document.getElementById('registrationTypeModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function openStudentRegistration() {
            closeRegistrationTypeModal();
            openRegisterModal();
        }

        function openTeacherApplication() {
            closeRegistrationTypeModal();
            window.location.href = '../teacher_application_form.php';
        }

        // Login Modal Functions
        function openLoginModal() {
            document.getElementById('loginModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeLoginModal() {
            document.getElementById('loginModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Student Register Modal Functions
        function openRegisterModal() {
            document.getElementById('registerModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function closeRegisterModal() {
            document.getElementById('registerModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function switchToRegistrationTypeModal() {
            closeLoginModal();
            openRegistrationTypeModal();
        }

        function switchToLogin() {
            closeRegisterModal();
            openLoginModal();
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const loginModal = document.getElementById('loginModal');
            const registerModal = document.getElementById('registerModal');
            const registrationTypeModal = document.getElementById('registrationTypeModal');
            
            if (event.target === loginModal) {
                closeLoginModal();
            }
            if (event.target === registerModal) {
                closeRegisterModal();
            }
            if (event.target === registrationTypeModal) {
                closeRegistrationTypeModal();
            }
        }

        // Enroll in subject function
        function enrollInSubject(subjectId) {
            if (confirm('هل تريد التسجيل في هذه المادة؟')) {
                // Here you would typically send an AJAX request to enroll
                alert('تم التسجيل في المادة بنجاح! سيتم تحويلك إلى صفحة الدفع.');
                // Redirect to payment or enrollment page
                window.location.href = '../student/enroll.php?subject_id=' + subjectId;
            }
        }

        // Form validation for registration
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.querySelector('input[name="reg_password"]').value;
            const confirmPassword = document.querySelector('input[name="reg_confirm_password"]').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('كلمة المرور وتأكيد كلمة المرور غير متطابقتان');
                return false;
            }
            
            if (password.length < 6) {
                e.preventDefault();
                alert('كلمة المرور يجب أن تكون أكثر من 6 أحرف');
                return false;
            }
        });

        // Auto-show modals if there are errors
        <?php if (!empty($login_error)): ?>
            openLoginModal();
        <?php endif; ?>

        <?php if (!empty($register_error)): ?>
            openRegisterModal();
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            openRegisterModal();
            // Auto-hide success message and switch to login after 3 seconds
            setTimeout(function() {
                closeRegisterModal();
                openLoginModal();
            }, 3000);
        <?php endif; ?>

        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 100) {
                navbar.style.background = 'rgba(255, 255, 255, 0.2)';
                navbar.style.backdropFilter = 'blur(15px)';
            } else {
                navbar.style.background = 'rgba(255, 255, 255, 0.1)';
                navbar.style.backdropFilter = 'blur(10px)';
            }
        });

        // Intersection Observer for animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animationPlayState = 'running';
                }
            });
        }, observerOptions);

        // Observe animated elements
        document.querySelectorAll('.fade-in, .slide-in-left, .slide-in-right').forEach(el => {
            el.style.animationPlayState = 'paused';
            observer.observe(el);
        });
    </script>
</body>
</html>