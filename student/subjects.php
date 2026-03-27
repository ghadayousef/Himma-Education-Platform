<?php
/**
 * المواد الدراسية المتاحة - منصة همّة التوجيهي (محدث مع بوابة الدفع)
 * Available Subjects - Himma Tawjihi Platform (Updated with Payment Gateway)
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

// جلب المواد المتاحة مع معلومات التسجيل
$stmt = $conn->prepare("
    SELECT s.*, 
           u.full_name as teacher_name,
           (SELECT COUNT(*) FROM enrollments WHERE subject_id = s.id) as enrollment_count,
           (SELECT COUNT(*) FROM enrollments WHERE subject_id = s.id AND user_id = ?) as is_enrolled,
           (SELECT AVG(progress_percentage) FROM enrollments WHERE subject_id = s.id AND progress_percentage > 0) as avg_progress
    FROM subjects s
    LEFT JOIN users u ON s.teacher_id = u.id
    WHERE s.is_active = 1
    ORDER BY s.is_featured DESC, s.created_at DESC
");
$stmt->execute([$user_id]);
$subjects = $stmt->fetchAll();

// جلب بيانات الطالب
$student_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$student_stmt->execute([$user_id]);
$student = $student_stmt->fetch();

// دالة لتوليد ألوان متدرجة حسب المادة
function getSubjectGradient($category, $id) {
    $gradients = [
        'scientific' => [
            'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
            'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
            'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)',
            'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
            'linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)'
        ],
        'literary' => [
            'linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%)',
            'linear-gradient(135deg, #ff8a80 0%, #ea6100 100%)',
            'linear-gradient(135deg, #ffd89b 0%, #19547b 100%)',
            'linear-gradient(135deg, #c471f5 0%, #fa71cd 100%)',
            'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'
        ],
        'languages' => [
            'linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)',
            'linear-gradient(135deg, #d299c2 0%, #fef9d7 100%)',
            'linear-gradient(135deg, #89f7fe 0%, #66a6ff 100%)',
            'linear-gradient(135deg, #fdbb2d 0%, #22c1c3 100%)',
            'linear-gradient(135deg, #e0c3fc 0%, #9bb5ff 100%)'
        ]
    ];
    
    $categoryGradients = $gradients[$category] ?? $gradients['scientific'];
    return $categoryGradients[$id % count($categoryGradients)];
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المواد الدراسية المتاحة - منصة همّة التوجيهي</title>
    
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

        .subject-card {
            height: 100%;
            position: relative;
        }

        .subject-header {
            height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            color: white;
            text-align: center;
        }

        .subject-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.9;
        }

        .subject-category-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            backdrop-filter: blur(10px);
        }

        .featured-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            background: rgba(255, 215, 0, 0.9);
            color: #333;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
            z-index: 2;
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
            color: white;
            border-radius: 10px;
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

        .price-tag {
            background: linear-gradient(135deg, var(--success-color), var(--warning-color));
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: bold;
            font-size: 1.1rem;
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
                        <a class="nav-link active" href="subjects.php">
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
                    <h2><i class="fas fa-book"></i> المواد الدراسية المتاحة</h2>
                    <p class="text-muted">اختر المواد التي تريد دراستها وابدأ رحلتك التعليمية</p>
                </div>
                <div class="text-end">
                    <span class="badge bg-info fs-6"><?php echo count($subjects); ?> مادة متاحة</span>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <input type="text" class="form-control search-box" id="searchInput" placeholder="ابحث عن مادة...">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="categoryFilter">
                            <option value="">جميع الفئات</option>
                            <option value="scientific">علمي</option>
                            <option value="literary">أدبي</option>
                            <option value="languages">لغات</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="levelFilter">
                            <option value="">جميع المستويات</option>
                            <option value="beginner">مبتدئ</option>
                            <option value="intermediate">متوسط</option>
                            <option value="advanced">متقدم</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Subjects Grid -->
            <?php if (empty($subjects)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-book-open fa-5x text-muted mb-3"></i>
                    <h3 class="text-muted">لا توجد مواد متاحة حالياً</h3>
                    <p class="text-muted">تحقق مرة أخرى لاحقاً للمواد الجديدة</p>
                </div>
            <?php else: ?>
                <div class="row" id="subjectsContainer">
                    <?php foreach ($subjects as $subject): ?>
                    <?php
                        // تحديد الأيقونة حسب الفئة
                        $categoryIcons = [
                            'scientific' => 'fas fa-flask',
                            'literary' => 'fas fa-book-open',
                            'languages' => 'fas fa-language'
                        ];
                        $icon = $categoryIcons[$subject['category']] ?? 'fas fa-book';
                        
                        // تحديد التدرج اللوني
                        $gradient = getSubjectGradient($subject['category'], $subject['id']);
                    ?>
                    <div class="col-lg-4 col-md-6 mb-4 subject-item" 
                         data-category="<?php echo $subject['category']; ?>" 
                         data-level="<?php echo $subject['level']; ?>"
                         data-name="<?php echo strtolower($subject['name']); ?>">
                        <div class="card subject-card">
                            <!-- Subject Header with Gradient -->
                            <div class="subject-header" style="background: <?php echo $gradient; ?>">
                                <?php if ($subject['is_featured']): ?>
                                    <div class="featured-badge">
                                        <i class="fas fa-star"></i> مميز
                                    </div>
                                <?php endif; ?>
                                
                                <div class="subject-category-badge">
                                    <?php 
                                    $categories = ['scientific' => 'علمي', 'literary' => 'أدبي', 'languages' => 'لغات'];
                                    echo $categories[$subject['category']] ?? $subject['category']; 
                                    ?>
                                </div>
                                
                                <div>
                                    <i class="<?php echo $icon; ?> subject-icon"></i>
                                    <h4 class="mb-0"><?php echo htmlspecialchars($subject['name']); ?></h4>
                                </div>
                            </div>
                            
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <p class="text-muted mb-0">
                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($subject['teacher_name'] ?? 'غير محدد'); ?>
                                    </p>
                                    <span class="price-tag"><?php echo number_format($subject['price'], 0); ?>ش</span>
                                </div>
                                
                                <p class="card-text text-muted">
                                    <?php echo htmlspecialchars(mb_substr($subject['description'] ?? '', 0, 120)); ?>...
                                </p>

                                <!-- Subject Stats -->
                                <div class="stats-row">
                                    <div class="row">
                                        <div class="col-3 stat-item">
                                            <div class="stat-number"><?php echo (int)$subject['enrollment_count']; ?></div>
                                            <div class="stat-label">طالب</div>
                                        </div>
                                        <div class="col-3 stat-item">
                                            <div class="stat-number"><?php echo $subject['duration_weeks']; ?></div>
                                            <div class="stat-label">أسبوع</div>
                                        </div>
                                        <div class="col-3 stat-item">
                                            <div class="stat-number">
                                                <?php 
                                                $levels = ['beginner' => 'مبتدئ', 'intermediate' => 'متوسط', 'advanced' => 'متقدم'];
                                                echo $levels[$subject['level']] ?? $subject['level'];
                                                ?>
                                            </div>
                                            <div class="stat-label">المستوى</div>
                                        </div>
                                        <div class="col-3 stat-item">
                                            <div class="stat-number"><?php echo number_format((float)($subject['avg_progress'] ?? 0), 1); ?>%</div>
                                            <div class="stat-label">متوسط التقدم</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Subject Info -->
                                <div class="mb-3">
                                    <small class="text-muted">
                                        <i class="fas fa-calendar-alt"></i> 
                                        تم إنشاؤها في <?php echo date('Y-m-d', strtotime($subject['created_at'])); ?>
                                    </small>
                                </div>
                            </div>

                            <!-- Card Footer -->
                            <div class="card-footer bg-transparent">
                                <?php if ($subject['is_enrolled']): ?>
                                    <div class="d-grid">
                                        <a href="dashboard.php" class="btn btn-success">
                                            <i class="fas fa-play-circle"></i> ابدأ الدراسة
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="d-grid">
                                        <a href="../payment/payment_gateway.php?subject_id=<?php echo $subject['id']; ?>" class="btn btn-primary">
                                            <i class="fas fa-credit-card"></i> اشترك الآن
                                        </a>
                                    </div>
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
        document.getElementById('searchInput').addEventListener('input', filterSubjects);
        document.getElementById('categoryFilter').addEventListener('change', filterSubjects);
        document.getElementById('levelFilter').addEventListener('change', filterSubjects);

        function filterSubjects() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const categoryFilter = document.getElementById('categoryFilter').value;
            const levelFilter = document.getElementById('levelFilter').value;
            const subjects = document.querySelectorAll('.subject-item');

            subjects.forEach(subject => {
                const name = subject.dataset.name;
                const category = subject.dataset.category;
                const level = subject.dataset.level;

                const matchesSearch = name.includes(searchTerm);
                const matchesCategory = !categoryFilter || category === categoryFilter;
                const matchesLevel = !levelFilter || level === levelFilter;

                if (matchesSearch && matchesCategory && matchesLevel) {
                    subject.style.display = 'block';
                } else {
                    subject.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>