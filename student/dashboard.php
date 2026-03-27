<?php
/**
 * لوحة تحكم الطالب - منصة همّة التوجيهي
 * Student Dashboard - Himma Tawjihi Platform
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// تعريف دالة التحقق من دور المستخدم إذا لم تكن معرفة سابقاً
if (!function_exists('has_role')) {
    function has_role($role) {
        // يفترض أن الدور مخزن في $_SESSION['role']
        return (isset($_SESSION['role']) && $_SESSION['role'] === $role);
    }
}

// التحقق من تسجيل الدخول كطالب
if (!is_logged_in() || !has_role('student')) {
    redirect('../auth/login.php');
}

$db = new Database();
$conn = $db->connect();
$user_id = $_SESSION['user_id'];

// جلب بيانات الطالب
$student_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$student_stmt->execute([$user_id]);
$student = $student_stmt->fetch();

// جلب إحصائيات الطالب
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(DISTINCT e.subject_id) as enrolled_subjects,
        AVG(e.progress_percentage) as avg_progress,
        COUNT(DISTINCT qr.id) as completed_quizzes,
        AVG(qr.score) as avg_score
    FROM enrollments e
    LEFT JOIN quiz_results qr ON qr.user_id = e.user_id
    WHERE e.user_id = ? AND e.status = 'active'
");
$stats_stmt->execute([$user_id]);
$stats = $stats_stmt->fetch();

// جلب المواد المسجل بها الطالب
$subjects_stmt = $conn->prepare("
    SELECT s.*, e.progress_percentage, e.enrollment_date,
           (SELECT COUNT(*) FROM lessons WHERE subject_id = s.id) as total_lessons,
           (SELECT COUNT(*) FROM quizzes WHERE subject_id = s.id) as total_quizzes
    FROM subjects s
    INNER JOIN enrollments e ON s.id = e.subject_id
    WHERE e.user_id = ? AND e.status = 'active'
    ORDER BY e.enrollment_date DESC
    LIMIT 6
");
$subjects_stmt->execute([$user_id]);
$enrolled_subjects = $subjects_stmt->fetchAll();

// جلب الاختبارات المتاحة
$quizzes_stmt = $conn->prepare("
    SELECT q.*, s.name as subject_name, s.category,
           (SELECT COUNT(*) FROM quiz_results qr WHERE qr.quiz_id = q.id AND qr.user_id = ?) as attempts_taken
    FROM quizzes q
    INNER JOIN subjects s ON q.subject_id = s.id
    INNER JOIN enrollments e ON s.id = e.subject_id
    WHERE e.user_id = ? AND e.status = 'active' AND q.is_active = 1
    AND (q.start_date IS NULL OR q.start_date <= NOW())
    AND (q.end_date IS NULL OR q.end_date >= NOW())
    ORDER BY q.created_at DESC
    LIMIT 4
");
$quizzes_stmt->execute([$user_id, $user_id]);
$available_quizzes = $quizzes_stmt->fetchAll();

// جلب آخر النتائج
$recent_results_stmt = $conn->prepare("
    SELECT qr.*, q.title as quiz_title, s.name as subject_name
    FROM quiz_results qr
    INNER JOIN quizzes q ON qr.quiz_id = q.id
    INNER JOIN subjects s ON q.subject_id = s.id
    WHERE qr.user_id = ?
    ORDER BY qr.completed_at DESC
    LIMIT 3
");
$recent_results_stmt->execute([$user_id]);
$recent_results = $recent_results_stmt->fetchAll();

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

// دالة لتحديد أيقونة الفئة
function getCategoryIcon($category) {
    $icons = [
        'scientific' => 'fas fa-flask',
        'literary' => 'fas fa-feather-alt',
        'languages' => 'fas fa-globe',
        'default' => 'fas fa-book'
    ];
    
    return $icons[$category] ?? $icons['default'];
}

// دالة لتحديد نوع الاختبار
function getQuizTypeIcon($type) {
    $icons = [
        'midterm' => 'fas fa-clipboard-check',
        'final' => 'fas fa-graduation-cap',
        'quiz' => 'fas fa-clock',
        'assignment' => 'fas fa-tasks',
        'default' => 'fas fa-question-circle'
    ];
    
    return $icons[$type] ?? $icons['default'];
}

function getQuizTypeName($type) {
    $names = [
        'midterm' => 'اختبار نصفي',
        'final' => 'اختبار نهائي',
        'quiz' => 'كويز قصير',
        'assignment' => 'واجب منزلي',
        'default' => 'اختبار'
    ];
    
    return $names[$type] ?? $names['default'];
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم الطالب - منصة همّة التوجيهي</title>
    
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
            position: relative;
        }

        .navbar-nav .nav-link:hover,
        .navbar-nav .nav-link.active {
            color: white !important;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
        }

        /* تنسيق شارات العداد */
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            min-width: 18px;
            padding: 0 4px;
            display: none;
        }

        .main-content {
            padding: 2rem;
        }

        .welcome-card {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .welcome-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="2" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
            animation: float 20s infinite linear;
        }

        @keyframes float {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #6c757d;
            font-weight: 500;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .subject-card {
            height: 100%;
            position: relative;
        }

        .subject-header {
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            color: white;
            text-align: center;
        }

        .subject-icon {
            font-size: 2.5rem;
            opacity: 0.3;
            position: absolute;
            top: 15px;
            right: 15px;
        }

        .subject-title-overlay {
            z-index: 2;
            position: relative;
        }

        .progress-bar {
            height: 8px;
            border-radius: 10px;
        }

        .quiz-card {
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            background: white;
        }

        .quiz-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }

        .quiz-type-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(255, 255, 255, 0.9);
            color: #333;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
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

        .section-title {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 1.5rem;
            color: var(--primary-color);
            position: relative;
            padding-bottom: 0.5rem;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 2px;
        }

        .result-badge {
            position: absolute;
            top: 10px;
            right: 10px;
        }

        /* تنسيق أزرار الدردشة والإشعارات */
        .chat-notifications-section {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .floating-btn {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            border: none;
            color: white;
            font-size: 24px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .floating-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }

        .chat-btn {
            background: linear-gradient(135deg, #28a745, #20c997);
        }

        .notification-btn {
            background: linear-gradient(135deg, #007bff, #6f42c1);
        }

        .floating-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 11px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            min-width: 20px;
            padding: 0 4px;
            display: none;
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
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> لوحة التحكم
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="my_courses.php">
                            <i class="fas fa-book-open"></i> مساقاتي
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="subjects.php">
                            <i class="fas fa-book"></i> المواد الدراسية
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="quizzes.php">
                            <i class="fas fa-clipboard-list"></i> الاختبارات والواجبات
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <!-- زر المحادثات -->
                    <li class="nav-item">
                        <a class="nav-link position-relative" href="../chat/chat_interface.php" title="المحادثات">
                            <i class="fas fa-comments"></i>
                            <span id="chat-badge" class="notification-badge"></span>
                        </a>
                    </li>
                    
                    <!-- زر الإشعارات -->
                    <li class="nav-item">
                        <a class="nav-link position-relative" href="../notifications/view_all.php" title="الإشعارات">
                            <i class="fas fa-bell"></i>
                            <span id="notification-badge" class="notification-badge"></span>
                        </a>
                    </li>
                    
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
            <!-- Welcome Section -->
            <div class="welcome-card">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2><i class="fas fa-hand-sparkles"></i> مرحباً بك، <?php echo htmlspecialchars($student['full_name']); ?>!</h2>
                        <p class="mb-0">استمر في رحلتك التعليمية وحقق أهدافك الأكاديمية</p>
                    </div>
                    <div class="col-md-4 text-center">
                        <i class="fas fa-graduation-cap fa-4x" style="opacity: 0.3;"></i>
                    </div>
                </div>
            </div>

            <!-- Statistics Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <div class="stat-value"><?php echo (int)$stats['enrolled_subjects']; ?></div>
                    <div class="stat-label">المواد المسجلة</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['avg_progress'] ?? 0, 1); ?>%</div>
                    <div class="stat-label">متوسط التقدم</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <div class="stat-value"><?php echo (int)$stats['completed_quizzes']; ?></div>
                    <div class="stat-label">الاختبارات المكتملة</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['avg_score'] ?? 0, 1); ?>%</div>
                    <div class="stat-label">متوسط الدرجات</div>
                </div>
            </div>

            <div class="row">
                <!-- Enrolled Subjects -->
                <div class="col-lg-8">
                    <h3 class="section-title"><i class="fas fa-book"></i> موادي الدراسية</h3>
                    
                    <?php if (empty($enrolled_subjects)): ?>
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-book-open fa-5x text-muted mb-3"></i>
                                <h4 class="text-muted">لم تسجل في أي مادة بعد</h4>
                                <p class="text-muted">ابدأ رحلتك التعليمية بالتسجيل في المواد المتاحة</p>
                                <a href="subjects.php" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> تصفح المواد
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($enrolled_subjects as $subject): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card subject-card">
                                    <!-- Subject Header -->
                                    <div class="subject-header" style="background: <?php echo getCategoryGradient($subject['category']); ?>">
                                        <i class="<?php echo getCategoryIcon($subject['category']); ?> subject-icon"></i>
                                        <div class="subject-title-overlay">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($subject['name']); ?></h6>
                                        </div>
                                    </div>
                                    
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between mb-2">
                                            <small class="text-muted">التقدم</small>
                                            <small class="text-muted"><?php echo number_format($subject['progress_percentage'], 1); ?>%</small>
                                        </div>
                                        <div class="progress mb-3">
                                            <div class="progress-bar" style="width: <?php echo $subject['progress_percentage']; ?>%"></div>
                                        </div>
                                        
                                        <div class="row text-center">
                                            <div class="col-6">
                                                <div class="small text-muted">الدروس</div>
                                                <div class="fw-bold"><?php echo $subject['total_lessons']; ?></div>
                                            </div>
                                            <div class="col-6">
                                                <div class="small text-muted">الاختبارات</div>
                                                <div class="fw-bold"><?php echo $subject['total_quizzes']; ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="card-footer bg-transparent">
                                        <a href="course_content.php?id=<?php echo $subject['id']; ?>" class="btn btn-primary btn-sm w-100">
                                            <i class="fas fa-play"></i> متابعة التعلم
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="text-center">
                            <a href="my_courses.php" class="btn btn-outline-primary">
                                <i class="fas fa-eye"></i> عرض جميع موادي
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Available Quizzes -->
                    <h4 class="section-title"><i class="fas fa-clipboard-list"></i> الاختبارات المتاحة</h4>
                    
                    <?php if (empty($available_quizzes)): ?>
                        <div class="card">
                            <div class="card-body text-center py-4">
                                <i class="fas fa-clipboard-list fa-3x text-muted mb-2"></i>
                                <p class="text-muted mb-0">لا توجد اختبارات متاحة حالياً</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($available_quizzes as $quiz): ?>
                        <?php
                            $can_attempt = true;
                            if ($quiz['attempts_allowed'] > 0 && $quiz['attempts_taken'] >= $quiz['attempts_allowed']) {
                                $can_attempt = false;
                            }
                        ?>
                        <div class="quiz-card position-relative">
                            <div class="quiz-type-badge">
                                <?php echo getQuizTypeName($quiz['quiz_type']); ?>
                            </div>
                            
                            <h6 class="mb-2"><?php echo htmlspecialchars($quiz['title']); ?></h6>
                            <p class="text-muted small mb-2"><?php echo htmlspecialchars($quiz['subject_name']); ?></p>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted">
                                        <i class="fas fa-clock"></i> <?php echo $quiz['duration']; ?> دقيقة
                                    </small>
                                </div>
                                <div>
                                    <?php if ($can_attempt): ?>
                                        <a href="take_quiz.php?id=<?php echo $quiz['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-play"></i> بدء
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">انتهت المحاولات</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <div class="text-center">
                            <a href="quizzes.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-eye"></i> عرض جميع الاختبارات
                            </a>
                        </div>
                    <?php endif; ?>

                    <!-- Recent Results -->
                    <?php if (!empty($recent_results)): ?>
                    <h4 class="section-title mt-4"><i class="fas fa-chart-bar"></i> آخر النتائج</h4>
                    
                    <?php foreach ($recent_results as $result): ?>
                    <div class="card mb-2">
                        <div class="card-body p-3 position-relative">
                            <span class="badge result-badge <?php echo $result['is_passed'] ? 'bg-success' : 'bg-danger'; ?>">
                                <?php echo number_format($result['score'], 1); ?>%
                            </span>
                            
                            <h6 class="mb-1"><?php echo htmlspecialchars($result['quiz_title']); ?></h6>
                            <p class="text-muted small mb-1"><?php echo htmlspecialchars($result['subject_name']); ?></p>
                            <small class="text-muted">
                                <i class="fas fa-calendar"></i> 
                                <?php echo date('Y-m-d', strtotime($result['completed_at'])); ?>
                            </small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- أزرار الدردشة والإشعارات العائمة -->
    <div class="chat-notifications-section">
        <a href="../chat/chat_interface.php" class="floating-btn chat-btn" title="المحادثات">
            <i class="fas fa-comments"></i>
            <span id="floating-chat-badge" class="floating-badge"></span>
        </a>
        <a href="../notifications/view_all.php" class="floating-btn notification-btn" title="الإشعارات">
            <i class="fas fa-bell"></i>
            <span id="floating-notification-badge" class="floating-badge"></span>
        </a>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- نظام الإشعارات -->
    <script src="../js/notifications.js"></script>
    
    <script>
        // تحديث الشارات العائمة أيضاً
        const originalUpdateBadges = window.notificationSystem?.updateBadges;
        if (originalUpdateBadges) {
            window.notificationSystem.updateBadges = function(counts) {
                originalUpdateBadges.call(this, counts);
                
                // تحديث الشارات العائمة
                const floatingChatBadge = document.getElementById('floating-chat-badge');
                const floatingNotificationBadge = document.getElementById('floating-notification-badge');
                
                if (floatingChatBadge) {
                    if (counts.messages > 0) {
                        floatingChatBadge.textContent = counts.messages > 99 ? '99+' : counts.messages;
                        floatingChatBadge.style.display = 'flex';
                    } else {
                        floatingChatBadge.style.display = 'none';
                    }
                }
                
                if (floatingNotificationBadge) {
                    if (counts.notifications > 0) {
                        floatingNotificationBadge.textContent = counts.notifications > 99 ? '99+' : counts.notifications;
                        floatingNotificationBadge.style.display = 'flex';
                    } else {
                        floatingNotificationBadge.style.display = 'none';
                    }
                }
            };
        }
    </script>
</body>
</html>