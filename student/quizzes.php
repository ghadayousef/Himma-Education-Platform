<?php
/**
 * الاختبارات والواجبات - منصة همّة التوجيهي
 * Quizzes and Assignments - Himma Tawjihi Platform
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

// جلب الاختبارات المتاحة للطالب
$quizzes_stmt = $conn->prepare("
    SELECT q.*, s.name as subject_name, s.category,
           (SELECT COUNT(*) FROM quiz_results qr WHERE qr.quiz_id = q.id AND qr.user_id = ?) as attempts_taken,
           (SELECT MAX(score) FROM quiz_results qr WHERE qr.quiz_id = q.id AND qr.user_id = ?) as best_score
    FROM quizzes q
    INNER JOIN subjects s ON q.subject_id = s.id
    INNER JOIN enrollments e ON s.id = e.subject_id
    WHERE e.user_id = ? AND e.status = 'active' AND q.is_active = 1
    AND (q.start_date IS NULL OR q.start_date <= NOW())
    AND (q.end_date IS NULL OR q.end_date >= NOW())
    ORDER BY q.created_at DESC
");
$quizzes_stmt->execute([$user_id, $user_id, $user_id]);
$quizzes = $quizzes_stmt->fetchAll();

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

// دالة لتحديد أيقونة نوع الاختبار
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

// دالة لتحديد اسم نوع الاختبار
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
    <title>الاختبارات والواجبات - منصة همّة التوجيهي</title>
    
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
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .quiz-card {
            height: 100%;
            position: relative;
        }

        .quiz-header {
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            color: white;
            text-align: center;
        }

        .quiz-type-icon {
            font-size: 2.5rem;
            opacity: 0.3;
            position: absolute;
            top: 15px;
            right: 15px;
        }

        .quiz-title-overlay {
            z-index: 2;
            position: relative;
        }

        .quiz-type-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background: rgba(255, 255, 255, 0.9);
            color: #333;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
            z-index: 3;
        }

        .status-badge {
            position: absolute;
            bottom: 15px;
            right: 15px;
            z-index: 3;
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
            background: var(--success-color);
            border: none;
            border-radius: 10px;
        }

        .btn-warning {
            background: var(--warning-color);
            border: none;
            border-radius: 10px;
            color: #333;
        }

        .badge {
            border-radius: 20px;
            padding: 0.5rem 1rem;
            font-weight: 500;
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

        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .search-box {
            border-radius: 25px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1.5rem;
        }

        .search-box:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .alert {
            border-radius: 10px;
            border: none;
        }

        .quiz-info {
            font-size: 0.9rem;
            color: #6c757d;
        }

        .quiz-info i {
            color: var(--primary-color);
            margin-left: 0.5rem;
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
                            <i class="fas fa-book"></i> المواد الدراسية
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="quizzes.php">
                            <i class="fas fa-clipboard-list"></i> الاختبارات والواجبات
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
                    <h2><i class="fas fa-clipboard-list"></i> الاختبارات والواجبات</h2>
                    <p class="text-muted">جميع الاختبارات والواجبات المتاحة لك</p>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <input type="text" class="form-control search-box" id="searchInput" placeholder="ابحث عن اختبار...">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="typeFilter">
                            <option value="">جميع الأنواع</option>
                            <option value="midterm">اختبارات نصفية</option>
                            <option value="final">اختبارات نهائية</option>
                            <option value="quiz">كويزات قصيرة</option>
                            <option value="assignment">واجبات منزلية</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="categoryFilter">
                            <option value="">جميع الفئات</option>
                            <option value="scientific">علمي</option>
                            <option value="literary">أدبي</option>
                            <option value="languages">لغات</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select" id="statusFilter">
                            <option value="">جميع الحالات</option>
                            <option value="available">متاح</option>
                            <option value="completed">مكتمل</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Quizzes Grid -->
            <?php if (empty($quizzes)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-clipboard-list fa-5x text-muted mb-3"></i>
                    <h3 class="text-muted">لا توجد اختبارات متاحة حالياً</h3>
                    <p class="text-muted">ستظهر الاختبارات والواجبات هنا عند إضافتها من قبل المعلمين</p>
                </div>
            <?php else: ?>
                <div class="row" id="quizzesContainer">
                    <?php foreach ($quizzes as $quiz): ?>
                    <?php
                        $can_attempt = true;
                        $status_text = 'متاح';
                        $status_class = 'bg-success';
                        $button_text = 'بدء الاختبار';
                        $button_class = 'btn-primary';
                        
                        if ($quiz['attempts_allowed'] > 0 && $quiz['attempts_taken'] >= $quiz['attempts_allowed']) {
                            $can_attempt = false;
                            $status_text = 'انتهت المحاولات';
                            $status_class = 'bg-danger';
                            $button_text = 'عرض النتيجة';
                            $button_class = 'btn-secondary';
                        } elseif ($quiz['attempts_taken'] > 0) {
                            $status_text = 'تم المحاولة';
                            $status_class = 'bg-warning';
                            $button_text = 'إعادة المحاولة';
                            $button_class = 'btn-warning';
                        }
                    ?>
                    <div class="col-lg-4 col-md-6 mb-4 quiz-item" 
                         data-type="<?php echo $quiz['quiz_type']; ?>" 
                         data-category="<?php echo $quiz['category']; ?>"
                         data-status="<?php echo $can_attempt ? 'available' : 'completed'; ?>"
                         data-name="<?php echo strtolower($quiz['title']); ?>">
                        <div class="card quiz-card">
                            <div class="quiz-type-badge">
                                <?php echo getQuizTypeName($quiz['quiz_type']); ?>
                            </div>
                            
                            <span class="badge status-badge <?php echo $status_class; ?>">
                                <?php echo $status_text; ?>
                            </span>
                            
                            <!-- Quiz Header with Gradient -->
                            <div class="quiz-header" style="background: <?php echo getCategoryGradient($quiz['category']); ?>">
                                <i class="<?php echo getQuizTypeIcon($quiz['quiz_type']); ?> quiz-type-icon"></i>
                                <div class="quiz-title-overlay">
                                    <h5 class="mb-1"><?php echo htmlspecialchars($quiz['title']); ?></h5>
                                    <small><?php echo htmlspecialchars($quiz['subject_name']); ?></small>
                                </div>
                            </div>
                            
                            <div class="card-body">
                                <?php if ($quiz['description']): ?>
                                    <p class="card-text text-muted mb-3">
                                        <?php echo htmlspecialchars(mb_substr($quiz['description'], 0, 100)); ?>...
                                    </p>
                                <?php endif; ?>

                                <!-- Quiz Stats -->
                                <div class="stats-row">
                                    <div class="row">
                                        <div class="col-3 stat-item">
                                            <div class="stat-number"><?php echo $quiz['duration']; ?></div>
                                            <div class="stat-label">دقيقة</div>
                                        </div>
                                        <div class="col-3 stat-item">
                                            <div class="stat-number"><?php echo $quiz['total_marks']; ?></div>
                                            <div class="stat-label">درجة</div>
                                        </div>
                                        <div class="col-3 stat-item">
                                            <div class="stat-number"><?php echo $quiz['attempts_taken']; ?></div>
                                            <div class="stat-label">محاولة</div>
                                        </div>
                                        <div class="col-3 stat-item">
                                            <div class="stat-number"><?php echo $quiz['best_score'] ? number_format($quiz['best_score'], 1) : '-'; ?></div>
                                            <div class="stat-label">أفضل نتيجة</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Quiz Info -->
                                <div class="quiz-info mb-3">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span><i class="fas fa-trophy"></i> درجة النجاح: <?php echo $quiz['pass_marks']; ?></span>
                                        <span><i class="fas fa-redo"></i> المحاولات: <?php echo $quiz['attempts_allowed'] == -1 ? 'غير محدود' : $quiz['attempts_allowed']; ?></span>
                                    </div>
                                    <?php if ($quiz['start_date'] || $quiz['end_date']): ?>
                                        <div class="mb-2">
                                            <?php if ($quiz['start_date']): ?>
                                                <small><i class="fas fa-play"></i> البداية: <?php echo date('Y-m-d H:i', strtotime($quiz['start_date'])); ?></small><br>
                                            <?php endif; ?>
                                            <?php if ($quiz['end_date']): ?>
                                                <small><i class="fas fa-stop"></i> النهاية: <?php echo date('Y-m-d H:i', strtotime($quiz['end_date'])); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Card Footer -->
                            <div class="card-footer bg-transparent">
                                <?php if ($can_attempt): ?>
                                    <a href="take_quiz.php?id=<?php echo $quiz['id']; ?>" 
                                       class="btn <?php echo $button_class; ?> w-100">
                                        <i class="fas fa-play"></i> <?php echo $button_text; ?>
                                    </a>
                                <?php else: ?>
                                    <a href="quiz_results.php?id=<?php echo $quiz['id']; ?>" 
                                       class="btn btn-outline-info w-100">
                                        <i class="fas fa-chart-line"></i> عرض النتائج
                                    </a>
                                <?php endif; ?>
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
    
    <script>
        // Search and Filter functionality
        document.getElementById('searchInput').addEventListener('input', filterQuizzes);
        document.getElementById('typeFilter').addEventListener('change', filterQuizzes);
        document.getElementById('categoryFilter').addEventListener('change', filterQuizzes);
        document.getElementById('statusFilter').addEventListener('change', filterQuizzes);

        function filterQuizzes() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const typeFilter = document.getElementById('typeFilter').value;
            const categoryFilter = document.getElementById('categoryFilter').value;
            const statusFilter = document.getElementById('statusFilter').value;
            const quizzes = document.querySelectorAll('.quiz-item');

            quizzes.forEach(quiz => {
                const name = quiz.dataset.name;
                const type = quiz.dataset.type;
                const category = quiz.dataset.category;
                const status = quiz.dataset.status;

                const matchesSearch = name.includes(searchTerm);
                const matchesType = !typeFilter || type === typeFilter;
                const matchesCategory = !categoryFilter || category === categoryFilter;
                const matchesStatus = !statusFilter || status === statusFilter;

                if (matchesSearch && matchesType && matchesCategory && matchesStatus) {
                    quiz.style.display = 'block';
                } else {
                    quiz.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>