<?php
/**
 * المساقات المسجلة - منصة همّة التوجيهي (محدث)
 * My Enrolled Courses - Himma Tawjihi Platform (Updated)
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

// جلب المساقات المسجل بها الطالب مع تفاصيل التقدم وحالة الدفع
$enrolled_courses_stmt = $conn->prepare("
    SELECT 
        s.id as subject_id,
        s.name as subject_name,
        s.description as subject_description,
        s.category,
        s.price,
        u.full_name as teacher_name,
        e.enrollment_date,
        e.progress_percentage,
        e.status,
        e.payment_status,
        e.payment_amount,
        e.payment_date,
        (SELECT COUNT(*) FROM lessons WHERE subject_id = s.id) as total_lessons,
        (SELECT COUNT(*) FROM lessons l 
         JOIN lesson_progress lp ON l.id = lp.lesson_id 
         WHERE l.subject_id = s.id AND lp.user_id = ? AND lp.completed = 1) as completed_lessons
    FROM enrollments e
    JOIN subjects s ON e.subject_id = s.id
    JOIN users u ON s.teacher_id = u.id
    WHERE e.user_id = ? AND e.status = 'active' AND s.is_active = 1
    ORDER BY e.enrollment_date DESC
");
$enrolled_courses_stmt->execute([$user_id, $user_id]);
$enrolled_courses = $enrolled_courses_stmt->fetchAll();

// جلب بيانات الطالب
$student_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$student_stmt->execute([$user_id]);
$student = $student_stmt->fetch();

// دالة لتوليد ألوان موحدة حسب الفئة
function getCategoryGradient($category) {
    $gradients = [
        'scientific' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
        'literary' => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
        'languages' => 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
        'default' => 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)'
    ];
    
    return $gradients[$category] ?? $gradients['default'];
}

// دالة لتحديد لون حالة الدفع
function getPaymentStatusBadge($status) {
    $badges = [
        'paid' => '<span class="badge bg-success"><i class="fas fa-check-circle"></i> مدفوع</span>',
        'pending' => '<span class="badge bg-warning"><i class="fas fa-clock"></i> قيد الانتظار</span>',
        'refunded' => '<span class="badge bg-secondary"><i class="fas fa-undo"></i> مسترد</span>'
    ];
    
    return $badges[$status] ?? '<span class="badge bg-secondary">غير محدد</span>';
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مساقاتي - منصة همّة التوجيهي</title>
    
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

        .course-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            margin-bottom: 2rem;
            overflow: hidden;
            height: 100%;
        }

        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .course-header {
            height: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            color: white;
            text-align: center;
        }

        .course-icon {
            font-size: 3.5rem;
            opacity: 0.3;
            position: absolute;
            top: 20px;
            right: 20px;
        }

        .course-title-overlay {
            z-index: 2;
            position: relative;
        }

        .enrollment-status-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            z-index: 3;
            background: rgba(67, 233, 123, 0.95);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }

        .progress-badge {
            position: absolute;
            top: 60px;
            left: 15px;
            z-index: 3;
        }

        .progress-bar-custom {
            height: 8px;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.2);
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--warning-color), var(--success-color));
            transition: width 0.3s ease;
        }

        .stats-row {
            background: rgba(102, 126, 234, 0.1);
            border-radius: 10px;
            padding: 1rem;
            margin: 1rem 0;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .stat-label {
            font-size: 0.8rem;
            color: #6c757d;
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
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .category-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            z-index: 3;
        }

        .lesson-count {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 0.25rem 0.75rem;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        .payment-info {
            background: rgba(67, 233, 123, 0.1);
            border-radius: 10px;
            padding: 0.75rem;
            margin: 1rem 0;
            border-left: 4px solid var(--warning-color);
        }

        .payment-info small {
            display: block;
            margin-bottom: 0.25rem;
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
                        <a class="nav-link active" href="my_courses.php">
                            <i class="fas fa-book-open"></i> مساقاتي
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="subjects.php">
                            <i class="fas fa-book"></i> المواد المتاحة
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
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-book-open"></i> مساقاتي</h2>
                    <p class="text-muted">المساقات التي سجلت بها وتقدمك في كل مساق</p>
                </div>
                <div>
                    <span class="badge bg-success fs-6">
                        <i class="fas fa-check-circle"></i> <?php echo count($enrolled_courses); ?> مساق مسجل
                    </span>
                </div>
            </div>

            <!-- Courses Grid -->
            <?php if (empty($enrolled_courses)): ?>
                <div class="empty-state">
                    <i class="fas fa-book-open"></i>
                    <h3>لم تسجل في أي مساق بعد</h3>
                    <p>ابدأ رحلتك التعليمية بتصفح المساقات المتاحة والتسجيل في المساق الذي يناسبك</p>
                    <a href="subjects.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-search"></i> تصفح المساقات
                    </a>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($enrolled_courses as $course): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card course-card">
                            <!-- Enrollment Status Badge -->
                            <span class="enrollment-status-badge">
                                <i class="fas fa-check-double"></i> مسجل
                            </span>
                            
                            <!-- Progress Badge -->
                            <span class="badge bg-success progress-badge">
                                <?php echo number_format($course['progress_percentage'], 1); ?>% مكتمل
                            </span>
                            
                            <!-- Category Badge -->
                            <span class="badge bg-secondary category-badge">
                                <?php 
                                $categories = ['scientific' => 'علمي', 'literary' => 'أدبي', 'languages' => 'لغات'];
                                echo $categories[$course['category']] ?? $course['category']; 
                                ?>
                            </span>
                            
                            <!-- Course Header with Gradient -->
                            <div class="course-header" style="background: <?php echo getCategoryGradient($course['category']); ?>">
                                <?php
                                $category_icons = [
                                    'scientific' => 'fas fa-flask',
                                    'literary' => 'fas fa-feather-alt',
                                    'languages' => 'fas fa-globe',
                                    'default' => 'fas fa-book'
                                ];
                                $icon = $category_icons[$course['category']] ?? $category_icons['default'];
                                ?>
                                <i class="<?php echo $icon; ?> course-icon"></i>
                                <div class="course-title-overlay">
                                    <h4 class="mb-2"><?php echo htmlspecialchars($course['subject_name']); ?></h4>
                                    <div class="lesson-count">
                                        <i class="fas fa-play-circle"></i>
                                        <?php echo $course['total_lessons']; ?> درس
                                    </div>
                                    <!-- Progress Bar -->
                                    <div class="progress-bar-custom mt-2">
                                        <div class="progress-fill" style="width: <?php echo $course['progress_percentage']; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card-body">
                                <p class="card-text text-muted">
                                    <?php echo htmlspecialchars(mb_substr($course['subject_description'], 0, 120)); ?>...
                                </p>

                                <!-- Payment Info -->
                                <div class="payment-info">
                                    <small>
                                        <i class="fas fa-credit-card"></i> 
                                        <strong>حالة الدفع:</strong> 
                                        <?php echo getPaymentStatusBadge($course['payment_status']); ?>
                                    </small>
                                    <?php if ($course['payment_date']): ?>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar-check"></i> 
                                        تاريخ الدفع: <?php echo date('Y-m-d', strtotime($course['payment_date'])); ?>
                                    </small>
                                    <?php endif; ?>
                                    <small class="text-muted">
                                        <i class="fas fa-money-bill-wave"></i> 
                                        المبلغ: <?php echo number_format($course['payment_amount'] ?? $course['price'], 2); ?> ش
                                    </small>
                                </div>

                                <!-- Course Stats -->
                                <div class="stats-row">
                                    <div class="row">
                                        <div class="col-4 stat-item">
                                            <div class="stat-number"><?php echo $course['total_lessons']; ?></div>
                                            <div class="stat-label">إجمالي الدروس</div>
                                        </div>
                                        <div class="col-4 stat-item">
                                            <div class="stat-number"><?php echo $course['completed_lessons']; ?></div>
                                            <div class="stat-label">دروس مكتملة</div>
                                        </div>
                                        <div class="col-4 stat-item">
                                            <div class="stat-number"><?php echo number_format($course['progress_percentage'], 0); ?>%</div>
                                            <div class="stat-label">التقدم</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Teacher Info -->
                                <div class="mb-3">
                                    <small class="text-muted">
                                        <i class="fas fa-user-tie"></i> 
                                        المعلم: <?php echo htmlspecialchars($course['teacher_name']); ?>
                                    </small>
                                </div>

                                <div class="mb-3">
                                    <small class="text-muted">
                                        <i class="fas fa-calendar-alt"></i> 
                                        تاريخ التسجيل: <?php echo date('Y-m-d', strtotime($course['enrollment_date'])); ?>
                                    </small>
                                </div>
                            </div>

                            <!-- Card Footer -->
                            <div class="card-footer bg-transparent">
                                <div class="d-grid">
                                    <a href="course_content.php?id=<?php echo $course['subject_id']; ?>" class="btn btn-success">
                                        <i class="fas fa-play"></i> متابعة الدراسة
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>