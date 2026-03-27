<?php
/**
 * صفحة اتصل بنا - منصة همّة التوجيهي
 * Contact Us Page - Himma Tawjihi Platform
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Handle contact form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    // Validation
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error_message = 'جميع الحقول المطلوبة يجب ملؤها';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'البريد الإلكتروني غير صحيح';
    } else {
        try {
            $db = new Database();
            $conn = $db->connect();
            
            // Insert contact message
            $stmt = $conn->prepare("
                INSERT INTO contact_messages (name, email, phone, subject, message, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            if ($stmt->execute([$name, $email, $phone, $subject, $message])) {
                $success_message = 'تم إرسال رسالتك بنجاح! سنتواصل معك قريباً';
                // Clear form data
                $name = $email = $phone = $subject = $message = '';
            } else {
                $error_message = 'حدث خطأ أثناء إرسال الرسالة، يرجى المحاولة لاحقاً';
            }
        } catch (Exception $e) {
            $error_message = 'حدث خطأ في النظام، يرجى المحاولة لاحقاً';
            error_log("Contact form error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اتصل بنا - منصة همّة التعليمية</title>
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

        .nav-link:hover, .nav-link.active {
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

        /* Main Content */
        .main-content {
            padding-top: 120px;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .contact-header {
            text-align: center;
            color: white;
            margin-bottom: 4rem;
        }

        .contact-title {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #fff, #f093fb);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .contact-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
        }

        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            margin-bottom: 4rem;
        }

        .contact-info {
            color: white;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            padding: 1.5rem;
            border-radius: 15px;
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
        }

        .info-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            background: linear-gradient(135deg, var(--accent-color), var(--success-color));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .info-content h3 {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }

        .info-content p {
            opacity: 0.8;
            line-height: 1.6;
        }

        .contact-form {
            padding: 2rem;
            color: white;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .form-input, .form-textarea {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-size: 1rem;
            backdrop-filter: blur(10px);
            font-family: 'Cairo', sans-serif;
        }

        .form-input::placeholder, .form-textarea::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        .form-input:focus, .form-textarea:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.2);
        }

        .form-textarea {
            min-height: 120px;
            resize: vertical;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .submit-btn {
            width: 100%;
            padding: 1rem 2rem;
            font-size: 1.1rem;
            border-radius: 15px;
            margin-top: 1rem;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(79, 172, 254, 0.2);
            color: var(--success-color);
            border: 1px solid rgba(79, 172, 254, 0.3);
        }

        .alert-danger {
            background: rgba(250, 112, 154, 0.2);
            color: var(--danger-color);
            border: 1px solid rgba(250, 112, 154, 0.3);
        }

        /* Social Links */
        .social-section {
            text-align: center;
            color: white;
            margin-top: 3rem;
        }

        .social-title {
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .social-links {
            display: flex;
            justify-content: center;
            gap: 1rem;
        }

        .social-link {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .social-link:hover {
            transform: translateY(-3px);
            background: linear-gradient(135deg, var(--accent-color), var(--success-color));
            color: white;
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
            left: 5%;
            animation-delay: 0s;
        }

        .floating-2 {
            width: 120px;
            height: 120px;
            top: 60%;
            right: 10%;
            animation-delay: 2s;
        }

        .floating-3 {
            width: 60px;
            height: 60px;
            bottom: 20%;
            left: 15%;
            animation-delay: 4s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .nav-menu {
                display: none;
            }

            .contact-title {
                font-size: 2rem;
            }

            .contact-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .social-links {
                flex-wrap: wrap;
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
    </style>
</head>
<body>
    <!-- Floating Elements -->
    <div class="floating-element floating-1"></div>
    <div class="floating-element floating-2"></div>
    <div class="floating-element floating-3"></div>

    <!-- Navigation -->
    <nav class="navbar glass">
        <div class="nav-container">
            <a href="home/index.php" class="logo">
                <i class="fas fa-graduation-cap"></i>
                همّة
            </a>
            <ul class="nav-menu">
                <li><a href="index.php" class="nav-link">الرئيسية</a></li>
                <li><a href="index.php#subjects" class="nav-link">المواد الدراسية</a></li>
                <li><a href="about.php" class="nav-link">من نحن</a></li>
                <li><a href="questions.php" class="nav-link">الأسئلة الشائعة</a></li>
                <li><a href="contact.php" class="nav-link active">اتصل بنا</a></li>
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
                    <a href="index.php" class="btn btn-outline">
                        <i class="fas fa-sign-in-alt"></i>
                        تسجيل الدخول
                    </a>
                    <a href="index.php" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i>
                        إنشاء حساب
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container">
            <!-- Header -->
            <div class="contact-header fade-in">
                <h1 class="contact-title">اتصل بنا</h1>
                <p class="contact-subtitle">
                    نحن هنا لمساعدتك! تواصل معنا في أي وقت وسنكون سعداء للرد على استفساراتك
                </p>
            </div>

            <!-- Contact Grid -->
            <div class="contact-grid">
                <!-- Contact Information -->
                <div class="contact-info fade-in">
                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="info-content">
                            <h3>العنوان</h3>
                            <p>غزة - فلسطين<br>شارع الجامعة الإسلامية<br>مجمع الأعمال التعليمي</p>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div class="info-content">
                            <h3>الهاتف</h3>
                            <p>+970 276 5355<br>+970 59 123 4567</p>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="info-content">
                            <h3>البريد الإلكتروني</h3>
                            <p>info@himma.edu<br>support@himma.edu</p>
                        </div>
                    </div>

                    <div class="info-item">
                        <div class="info-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="info-content">
                            <h3>ساعات العمل</h3>
                            <p>الأحد - الخميس: 8:00 ص - 8:00 م<br>الجمعة - السبت: 10:00 ص - 6:00 م</p>
                        </div>
                    </div>
                </div>

                <!-- Contact Form -->
                <div class="contact-form glass fade-in">
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">الاسم الكامل *</label>
                                <input type="text" name="name" class="form-input" placeholder="أدخل اسمك الكامل" 
                                       value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">البريد الإلكتروني *</label>
                                <input type="email" name="email" class="form-input" placeholder="example@email.com" 
                                       value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">رقم الهاتف</label>
                                <input type="tel" name="phone" class="form-input" placeholder="+970 59 123 4567" 
                                       value="<?php echo htmlspecialchars($phone ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">الموضوع *</label>
                                <input type="text" name="subject" class="form-input" placeholder="موضوع الرسالة" 
                                       value="<?php echo htmlspecialchars($subject ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">الرسالة *</label>
                            <textarea name="message" class="form-textarea" placeholder="اكتب رسالتك هنا..." required><?php echo htmlspecialchars($message ?? ''); ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary submit-btn">
                            <i class="fas fa-paper-plane"></i>
                            إرسال الرسالة
                        </button>
                    </form>
                </div>
            </div>

            <!-- Social Media Section -->
            <div class="social-section fade-in">
                <h3 class="social-title">تابعنا على وسائل التواصل الاجتماعي</h3>
                <div class="social-links">
                    <a href="#" class="social-link">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="#" class="social-link">
                        <i class="fab fa-twitter"></i>
                    </a>
                    <a href="#" class="social-link">
                        <i class="fab fa-instagram"></i>
                    </a>
                    <a href="#" class="social-link">
                        <i class="fab fa-linkedin-in"></i>
                    </a>
                    <a href="#" class="social-link">
                        <i class="fab fa-youtube"></i>
                    </a>
                    <a href="#" class="social-link">
                        <i class="fab fa-telegram-plane"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
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
        document.querySelectorAll('.fade-in').forEach(el => {
            el.style.animationPlayState = 'paused';
            observer.observe(el);
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

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const name = document.querySelector('input[name="name"]').value.trim();
            const email = document.querySelector('input[name="email"]').value.trim();
            const subject = document.querySelector('input[name="subject"]').value.trim();
            const message = document.querySelector('textarea[name="message"]').value.trim();

            if (!name || !email || !subject || !message) {
                e.preventDefault();
                alert('يرجى ملء جميع الحقول المطلوبة');
                return false;
            }

            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('يرجى إدخال بريد إلكتروني صحيح');
                return false;
            }
        });
    </script>
</body>
</html>