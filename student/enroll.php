<?php
/**
 * التسجيل في المادة - منصة همّة التوجيهي
 * Subject Enrollment - Himma Tawjihi Platform
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// التحقق من تسجيل الدخول كطالب
if (!is_logged_in() || !has_role('student')) {
    redirect('../auth/login.php');
}

$db = new Database();
$conn = $db->connect();
$user_id = $_SESSION['user_id'];

// التحقق من وجود معرف المادة
if (!isset($_GET['subject_id']) || !is_numeric($_GET['subject_id'])) {
    redirect('subjects.php');
}

$subject_id = intval($_GET['subject_id']);

// جلب بيانات المادة
$subject_stmt = $conn->prepare("
    SELECT s.*, u.full_name as teacher_name
    FROM subjects s
    LEFT JOIN users u ON s.teacher_id = u.id
    WHERE s.id = ? AND s.is_active = 1
");
$subject_stmt->execute([$subject_id]);
$subject = $subject_stmt->fetch();

if (!$subject) {
    redirect('subjects.php');
}

// التحقق من التسجيل المسبق
$check_stmt = $conn->prepare("SELECT id FROM enrollments WHERE user_id = ? AND subject_id = ?");
$check_stmt->execute([$user_id, $subject_id]);
$already_enrolled = $check_stmt->fetch();

$message = '';
$error = '';

// معالجة التسجيل
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_enrollment'])) {
    if ($already_enrolled) {
        $error = 'أنت مسجل بالفعل في هذه المادة!';
    } else {
        try {
            // إدراج التسجيل الجديد
            $enroll_stmt = $conn->prepare("
                INSERT INTO enrollments (user_id, subject_id, enrollment_date, status, payment_status, progress_percentage) 
                VALUES (?, ?, NOW(), 'active', 'pending', 0)
            ");
            
            if ($enroll_stmt->execute([$user_id, $subject_id])) {
                // إشعار المعلم بالتسجيل الجديد
                $notification_stmt = $conn->prepare("
                    INSERT INTO notifications (user_id, type, title, message, created_at) 
                    VALUES (?, 'enrollment', ?, ?, NOW())
                ");
                
                $notification_title = 'طالب جديد سجل في مادتك';
                $notification_message = 'طالب جديد سجل في مادة "' . $subject['name'] . '"';
                
                $notification_stmt->execute([
                    $subject['teacher_id'], 
                    $notification_title, 
                    $notification_message
                ]);
                
                $message = 'تم التسجيل في المادة بنجاح! يمكنك الآن الوصول إليها من لوحة التحكم.';
            } else {
                $error = 'حدث خطأ أثناء التسجيل في المادة';
            }
        } catch (PDOException $e) {
            $error = 'خطأ في النظام: ' . $e->getMessage();
        }
    }
}

// جلب بيانات الطالب
$student_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$student_stmt->execute([$user_id]);
$student = $student_stmt->fetch();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>التسجيل في المادة - منصة همّة التوجيهي</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --accent-color: #f093fb;
            --success-color: #4facfe;
            --warning-color: #43e97b;
            --danger-color: #fa709a;
        }

        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #f8f9fc 0%, #e9ecef 100%);
            min-height: 100vh;
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            font-weight: 800;
            font-size: 1.5rem;
            color: white !important;
        }

        .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.9) !important;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .navbar-nav .nav-link:hover,
        .navbar-nav .nav-link.active {
            color: white !important;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
        }

        .main-content {
            padding: 2rem;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            margin-bottom: 2rem;
        }

        .subject-header {
            height: 200px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .subject-icon {
            font-size: 4rem;
            opacity: 0.9;
            margin-bottom: 1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success-color), var(--warning-color));
            border: none;
            border-radius: 10px;
            font-weight: 600;
        }

        .price-highlight {
            background: linear-gradient(135deg, var(--success-color), var(--warning-color));
            color: white;
            padding: 1rem 2rem;
            border-radius: 15px;
            font-size: 1.5rem;
            font-weight: bold;
            text-align: center;
        }

        .info-box {
            background: rgba(102, 126, 234, 0.1);
            border-radius: 10px;
            padding: 1.5rem;
            margin: 1rem 0;
        }

        .alert {
            border-radius: 10px;
            border: none;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../home/index.php">
                <i class="fas fa-graduation-cap"></i> همّة التوجيهي
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> لوحة التحكم
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="subjects.php">
                            <i class="fas fa-book"></i> المواد المتاحة
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my_subjects.php">
                            <i class="fas fa-bookmark"></i> موادي
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($student['full_name']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user"></i> الملف الشخصي</a></li>
                            <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <!-- Page Header -->
                    <div class="text-center mb-4">
                        <h2><i class="fas fa-graduation-cap"></i> التسجيل في المادة</h2>
                        <p class="text-muted">تأكيد التسجيل في المادة الدراسية</p>
                    </div>

                    <!-- Alerts -->
                    <?php if ($message): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <div class="text-center">
                            <a href="my_subjects.php" class="btn btn-primary me-2">
                                <i class="fas fa-arrow-left"></i> انتقل إلى موادي
                            </a>
                            <a href="subjects.php" class="btn btn-outline-primary">
                                <i class="fas fa-book"></i> تصفح مواد أخرى
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (!$message): ?>
                        <!-- Subject Details Card -->
                        <div class="card">
                            <!-- Subject Header -->
                            <div class="subject-header">
                                <div class="text-center">
                                    <i class="fas fa-book subject-icon"></i>
                                    <h3 class="mb-0"><?php echo htmlspecialchars($subject['name']); ?></h3>
                                </div>
                            </div>
                            
                            <div class="card-body">
                                <!-- Teacher Info -->
                                <div class="d-flex align-items-center mb-3">
                                    <i class="fas fa-user-tie fa-2x text-primary me-3"></i>
                                    <div>
                                        <h5 class="mb-0">المعلم</h5>
                                        <p class="text-muted mb-0"><?php echo htmlspecialchars($subject['teacher_name'] ?? 'غير محدد'); ?></p>
                                    </div>
                                </div>

                                <!-- Subject Description -->
                                <div class="info-box">
                                    <h6><i class="fas fa-info-circle"></i> وصف المادة</h6>
                                    <p class="mb-0"><?php echo htmlspecialchars($subject['description'] ?? 'لا يوجد وصف متاح'); ?></p>
                                </div>

                                <!-- Subject Details -->
                                <div class="row mb-4">
                                    <div class="col-md-4">
                                        <div class="text-center">
                                            <i class="fas fa-layer-group fa-2x text-info mb-2"></i>
                                            <h6>الفئة</h6>
                                            <p class="text-muted">
                                                <?php 
                                                $categories = ['scientific' => 'علمي', 'literary' => 'أدبي', 'languages' => 'لغات'];
                                                echo $categories[$subject['category']] ?? $subject['category']; 
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-center">
                                            <i class="fas fa-signal fa-2x text-warning mb-2"></i>
                                            <h6>المستوى</h6>
                                            <p class="text-muted">
                                                <?php 
                                                $levels = ['beginner' => 'مبتدئ', 'intermediate' => 'متوسط', 'advanced' => 'متقدم'];
                                                echo $levels[$subject['level']] ?? $subject['level']; 
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="text-center">
                                            <i class="fas fa-calendar-week fa-2x text-success mb-2"></i>
                                            <h6>المدة</h6>
                                            <p class="text-muted"><?php echo $subject['duration_weeks']; ?> أسبوع</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Price -->
                                <div class="price-highlight mb-4">
                                    <i class="fas fa-tag"></i> تكلفة المادة: <?php echo number_format($subject['price'], 2); ?> ش
                                </div>

                                <?php if ($already_enrolled): ?>
                                    <div class="alert alert-info text-center">
                                        <i class="fas fa-check-circle fa-2x mb-2"></i>
                                        <h5>أنت مسجل بالفعل في هذه المادة!</h5>
                                        <p class="mb-0">يمكنك الوصول إليها من لوحة التحكم أو صفحة موادي</p>
                                    </div>
                                    <div class="text-center">
                                        <a href="my_subjects.php" class="btn btn-success me-2">
                                            <i class="fas fa-arrow-left"></i> انتقل إلى موادي
                                        </a>
                                        <a href="subjects.php" class="btn btn-outline-primary">
                                            <i class="fas fa-book"></i> تصفح مواد أخرى
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <!-- Enrollment Info -->
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i>
                                        <strong>معلومات مهمة:</strong>
                                        <ul class="mb-0 mt-2">
                                            <li>سيتم إضافة المادة إلى قائمة موادك فوراً</li>
                                            <li>حالة الدفع ستكون "في الانتظار" حتى يتم تأكيد الدفع</li>
                                            <li>يمكنك البدء بالدراسة فور التسجيل</li>
                                            <li>سيتم إشعار المعلم بتسجيلك في المادة</li>
                                        </ul>
                                    </div>

                                    <!-- Enrollment Form -->
                                    <form method="POST" class="text-center">
                                        <button type="submit" name="confirm_enrollment" class="btn btn-primary btn-lg me-2">
                                            <i class="fas fa-check"></i> تأكيد التسجيل
                                        </button>
                                        <a href="subjects.php" class="btn btn-outline-secondary btn-lg">
                                            <i class="fas fa-times"></i> إلغاء
                                        </a>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>