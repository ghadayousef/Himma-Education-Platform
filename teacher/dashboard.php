<?php
/**
 * لوحة تحكم المعلم - منصة همّة التوجيهي
 * Teacher Dashboard - Himma Tawjihi Platform
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// التحقق من تسجيل الدخول كمعلم
if (!is_logged_in() || !has_role('teacher')) {
    redirect('../auth/login.php');
}

$db = new Database();
$conn = $db->connect();
$user_id = $_SESSION['user_id'];

// جلب بيانات المعلم
$teacher_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$teacher_stmt->execute([$user_id]);
$teacher = $teacher_stmt->fetch();

// التحقق من وجود بيانات المعلم
if (!$teacher) {
    redirect('../auth/logout.php');
}

/**
 * كشف اسم العمود المستخدم لاسم المادة في جدول subjects.
 * سنبحث عن أسماء شائعة: name, subject_name, title, subject
 * ثم نستخدم الاسم المكتشف في الاستعلامات لنعرض البيانات بدون خطأ Unknown column 's.name'.
 */
$subject_name_candidates = ['name', 'subject_name', 'title', 'subject'];
$subject_name_col = 'name'; // قيمة افتراضية

try {
    $in_list = "'" . implode("','", array_map('addslashes', $subject_name_candidates)) . "'";
    $col_check_sql = "
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'subjects'
          AND COLUMN_NAME IN ($in_list)
        LIMIT 1
    ";
    $col_check_stmt = $conn->prepare($col_check_sql);
    $col_check_stmt->execute();
    $col_row = $col_check_stmt->fetch(PDO::FETCH_ASSOC);
    if ($col_row && preg_match('/^[a-zA-Z0-9_]+$/', $col_row['COLUMN_NAME'])) {
        $subject_name_col = $col_row['COLUMN_NAME'];
    }
} catch (Exception $e) {
    // في حال فشل كشف العمود (مثلاً صلاحيات محدودة)، نكمل بالقيمة الافتراضية 'name'.
    // لا نقوم بعرض رسالة للمستخدم هنا لتجنب إفشاء تفاصيل النظام.
    $subject_name_col = 'name';
}

// تأكيد أن اسم العمود آمن للاستخدام داخل استعلامات SQL (أحرف وأرقام و underscore فقط)
if (!preg_match('/^[a-zA-Z0-9_]+$/', $subject_name_col)) {
    $subject_name_col = 'name';
}

// جلب إحصائيات المعلم
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(DISTINCT s.id) as total_subjects,
        COUNT(DISTINCT e.user_id) as total_students,
        COUNT(DISTINCT q.id) as total_quizzes,
        AVG(e.progress_percentage) as avg_progress
    FROM subjects s
    LEFT JOIN enrollments e ON s.id = e.subject_id AND e.status = 'active'
    LEFT JOIN quizzes q ON s.id = q.subject_id
    WHERE s.teacher_id = ?
");
$stats_stmt->execute([$user_id]);
$stats = $stats_stmt->fetch();

// التأكد من وجود البيانات وتعيين قيم افتراضية
if (!$stats) {
    $stats = [
        'total_subjects' => 0,
        'total_students' => 0,
        'total_quizzes' => 0,
        'avg_progress' => 0
    ];
}

// جلب المواد الحديثة
// نعيد تسمية عمود اسم المادة إلى alias "name" ليتوافق مع بقية القالب
$recent_subjects_sql = "
    SELECT s.*, s.`" . $subject_name_col . "` AS name,
           COUNT(DISTINCT e.user_id) as student_count,
           COUNT(DISTINCT q.id) as quiz_count
    FROM subjects s
    LEFT JOIN enrollments e ON s.id = e.subject_id AND e.status = 'active'
    LEFT JOIN quizzes q ON s.id = q.subject_id
    WHERE s.teacher_id = ?
    GROUP BY s.id
    ORDER BY s.created_at DESC
    LIMIT 6
";
$recent_subjects_stmt = $conn->prepare($recent_subjects_sql);
$recent_subjects_stmt->execute([$user_id]);
$recent_subjects = $recent_subjects_stmt->fetchAll();

// جلب الطلاب الجدد
// نعيد تسمية عمود اسم المادة إلى alias "subject_name" ليتوافق مع القالب
$recent_students_sql = "
    SELECT u.full_name, u.email, s.`" . $subject_name_col . "` AS subject_name, e.enrollment_date
    FROM enrollments e
    INNER JOIN users u ON e.user_id = u.id
    INNER JOIN subjects s ON e.subject_id = s.id
    WHERE s.teacher_id = ? AND e.status = 'active'
    ORDER BY e.enrollment_date DESC
    LIMIT 5
";
$recent_students_stmt = $conn->prepare($recent_students_sql);
$recent_students_stmt->execute([$user_id]);
$recent_students = $recent_students_stmt->fetchAll();

// جلب الاختبارات الحديثة
$recent_quizzes_sql = "
    SELECT q.*, s.`" . $subject_name_col . "` as subject_name,
           COUNT(qr.id) as attempts_count,
           AVG(qr.score) as avg_score
    FROM quizzes q
    INNER JOIN subjects s ON q.subject_id = s.id
    LEFT JOIN quiz_results qr ON q.id = qr.quiz_id
    WHERE s.teacher_id = ?
    GROUP BY q.id
    ORDER BY q.created_at DESC
    LIMIT 5
";
$recent_quizzes_stmt = $conn->prepare($recent_quizzes_sql);
$recent_quizzes_stmt->execute([$user_id]);
$recent_quizzes = $recent_quizzes_stmt->fetchAll();

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
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم المعلم - منصة همّة التوجيهي</title>
    
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

        .list-group-item {
            border: none;
            border-radius: 10px !important;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }

        .list-group-item:hover {
            background: #f8f9fa;
            transform: translateX(-5px);
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
                        <a class="nav-link" href="subjects.php">
                            <i class="fas fa-book"></i> إدارة المواد
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="quiz_system.php">
                            <i class="fas fa-clipboard-list"></i> إضافة اختبار
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="students.php">
                            <i class="fas fa-users"></i> الطلاب
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
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($teacher['full_name'] ?? 'المعلم'); ?>
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
                        <h2><i class="fas fa-hand-sparkles"></i>  مرحباً بك/ معلم <?php echo htmlspecialchars($teacher['full_name'] ?? 'المعلم'); ?>!</h2>
                        <p class="mb-0">إدارة موادك التعليمية ومتابعة تقدم طلابك</p>
                    </div>
                    <div class="col-md-4 text-center">
                        <i class="fas fa-chalkboard-teacher fa-4x" style="opacity: 0.3;"></i>
                    </div>
                </div>
            </div>

            <!-- Statistics Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <div class="stat-value"><?php echo (int)($stats['total_subjects'] ?? 0); ?></div>
                    <div class="stat-label">إجمالي المواد</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value"><?php echo (int)($stats['total_students'] ?? 0); ?></div>
                    <div class="stat-label">إجمالي الطلاب</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="stat-value"><?php echo (int)($stats['total_quizzes'] ?? 0); ?></div>
                    <div class="stat-label">إجمالي الاختبارات</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format((float)($stats['avg_progress'] ?? 0), 1); ?>%</div>
                    <div class="stat-label">متوسط تقدم الطلاب</div>
                </div>
            </div>

            <div class="row">
                <!-- Recent Subjects -->
                <div class="col-lg-8">
                    <h3 class="section-title"><i class="fas fa-book"></i> موادي الدراسية</h3>
                    
                    <?php if (empty($recent_subjects)): ?>
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-book-open fa-5x text-muted mb-3"></i>
                                <h4 class="text-muted">لم تقم بإنشاء أي مادة بعد</h4>
                                <p class="text-muted">ابدأ بإنشاء مادتك الأولى لتتمكن من إدارة محتواها</p>
                                <a href="add_subject.php" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> إنشاء مادة جديدة
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($recent_subjects as $subject): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card subject-card">
                                    <!-- Subject Header -->
                                    <div class="subject-header" style="background: <?php echo getCategoryGradient($subject['category'] ?? 'default'); ?>">
                                        <i class="<?php echo getCategoryIcon($subject['category'] ?? 'default'); ?> subject-icon"></i>
                                        <div class="subject-title-overlay">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($subject['name'] ?? 'مادة غير محددة'); ?></h6>
                                        </div>
                                    </div>
                                    
                                    <div class="card-body">
                                        <p class="text-muted small mb-2">
                                            <?php echo htmlspecialchars(mb_substr($subject['description'] ?? '', 0, 80)); ?>...
                                        </p>
                                        
                                        <div class="row text-center">
                                            <div class="col-4">
                                                <div class="small text-muted">الطلاب</div>
                                                <div class="fw-bold"><?php echo (int)($subject['student_count'] ?? 0); ?></div>
                                            </div>
                                            <div class="col-4">
                                                <div class="small text-muted">الاختبارات</div>
                                                <div class="fw-bold"><?php echo (int)($subject['quiz_count'] ?? 0); ?></div>
                                            </div>
                                            <div class="col-4">
                                                <div class="small text-muted">السعر</div>
                                                <div class="fw-bold"><?php echo number_format($subject['price'] ?? 0, 0); ?>ش</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="text-center">
                            <a href="subjects.php" class="btn btn-outline-primary">
                                <i class="fas fa-eye"></i> عرض جميع موادي
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Recent Students -->
                    <h4 class="section-title"><i class="fas fa-user-plus"></i> الطلاب الجدد</h4>
                    
                    <?php if (empty($recent_students)): ?>
                        <div class="card">
                            <div class="card-body text-center py-4">
                                <i class="fas fa-users fa-3x text-muted mb-2"></i>
                                <p class="text-muted mb-0">لا يوجد طلاب جدد</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card">
                            <div class="card-body p-0">
                                <div class="list-group list-group-flush">
                                    <?php foreach ($recent_students as $student): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-user-circle fa-2x text-primary me-3"></i>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($student['full_name'] ?? 'طالب'); ?></h6>
                                                <small class="text-muted"><?php echo htmlspecialchars($student['subject_name'] ?? 'مادة'); ?></small>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo date('Y-m-d', strtotime($student['enrollment_date'] ?? 'now')); ?>
                                            </small>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Recent Quizzes -->
                    <h4 class="section-title mt-4"><i class="fas fa-clipboard-check"></i> الاختبارات الحديثة</h4>
                    
                    <?php if (empty($recent_quizzes)): ?>
                        <div class="card">
                            <div class="card-body text-center py-4">
                                <i class="fas fa-clipboard-list fa-3x text-muted mb-2"></i>
                                <p class="text-muted mb-2">لا توجد اختبارات بعد</p>
                                <a href="add_quiz.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus"></i> إضافة اختبار
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card">
                            <div class="card-body p-0">
                                <div class="list-group list-group-flush">
                                    <?php foreach ($recent_quizzes as $quiz): ?>
                                    <div class="list-group-item">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($quiz['title'] ?? 'اختبار'); ?></h6>
                                        <p class="text-muted small mb-1"><?php echo htmlspecialchars($quiz['subject_name'] ?? 'مادة'); ?></p>
                                        <div class="d-flex justify-content-between">
                                            <small class="text-muted">
                                                <i class="fas fa-users"></i> <?php echo (int)($quiz['attempts_count'] ?? 0); ?> محاولة
                                            </small>
                                            <small class="text-success">
                                                <i class="fas fa-star"></i> <?php echo number_format((float)($quiz['avg_score'] ?? 0), 1); ?>%
                                            </small>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
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