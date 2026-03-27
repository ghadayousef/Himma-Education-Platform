<?php
/**
 * إدارة المواد الدراسية - منصة همّة التوجيهي
 * Subjects Management - Himma Tawjihi Platform
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

// معالجة العمليات
$message = '';
$error = '';

// معالجة طلبات AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['ajax_action'] === 'toggle_subject') {
        $subject_id = intval($_POST['subject_id']);
        $new_status = intval($_POST['new_status']);
        
        try {
            // التحقق من ملكية المادة أولاً
            $check_stmt = $conn->prepare("SELECT id, name, is_active FROM subjects WHERE id = ? AND teacher_id = ?");
            $check_stmt->execute([$subject_id, $user_id]);
            $subject_data = $check_stmt->fetch();
            
            if (!$subject_data) {
                echo json_encode(['success' => false, 'message' => 'المادة غير موجودة أو غير مصرح لك بتعديلها']);
                exit;
            }
            
            // تحديث حالة المادة
            $toggle_stmt = $conn->prepare("UPDATE subjects SET is_active = ?, updated_at = NOW() WHERE id = ? AND teacher_id = ?");
            
            if ($toggle_stmt->execute([$new_status, $subject_id, $user_id])) {
                if ($toggle_stmt->rowCount() > 0) {
                    $action_text = $new_status ? 'تفعيل' : 'إلغاء تفعيل';
                    $status_text = $new_status ? 'نشط' : 'غير نشط';
                    $status_class = $new_status ? 'bg-success' : 'bg-secondary';
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => "تم {$action_text} المادة '{$subject_data['name']}' بنجاح!",
                        'new_status' => $new_status,
                        'status_text' => $status_text,
                        'status_class' => $status_class,
                        'subject_name' => $subject_data['name']
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'لم يتم تحديث أي سجل']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'حدث خطأ أثناء تحديث حالة المادة']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'خطأ في النظام: ' . $e->getMessage()]);
        }
        exit;
    }
}

// حذف مادة نهائياً
if (isset($_POST['delete_subject_permanently'])) {
    $subject_id = intval($_POST['subject_id']);
    
    try {
        // بدء المعاملة
        $conn->beginTransaction();
        
        // التحقق من ملكية المادة
        $check_stmt = $conn->prepare("SELECT id FROM subjects WHERE id = ? AND teacher_id = ?");
        $check_stmt->execute([$subject_id, $user_id]);
        
        if ($check_stmt->fetch()) {
            // حذف البيانات المرتبطة أولاً بالترتيب الصحيح
            
            // حذف التسجيلات
            $conn->prepare("DELETE FROM enrollments WHERE subject_id = ?")->execute([$subject_id]);
            
            // حذف الاختبارات ونتائجها
            $quizzes_stmt = $conn->prepare("SELECT id FROM quizzes WHERE subject_id = ?");
            $quizzes_stmt->execute([$subject_id]);
            $quizzes = $quizzes_stmt->fetchAll();
            
            foreach ($quizzes as $quiz) {
                // حذف نتائج الاختبارات (إذا كان الجدول موجود)
                try {
                    $conn->prepare("DELETE FROM quiz_results WHERE quiz_id = ?")->execute([$quiz['id']]);
                } catch (PDOException $e) {
                    // تجاهل الخطأ إذا كان الجدول غير موجود
                }
                
                // حذف أسئلة الاختبارات
                try {
                    $conn->prepare("DELETE FROM quiz_questions WHERE quiz_id = ?")->execute([$quiz['id']]);
                } catch (PDOException $e) {
                    // تجاهل الخطأ إذا كان الجدول غير موجود
                }
            }
            
            // حذف الاختبارات
            $conn->prepare("DELETE FROM quizzes WHERE subject_id = ?")->execute([$subject_id]);
            
            // حذف الدروس
            $conn->prepare("DELETE FROM lessons WHERE subject_id = ?")->execute([$subject_id]);
            
            // حذف المادة نفسها
            $delete_stmt = $conn->prepare("DELETE FROM subjects WHERE id = ? AND teacher_id = ?");
            if ($delete_stmt->execute([$subject_id, $user_id])) {
                $conn->commit();
                $message = 'تم حذف المادة نهائياً بنجاح!';
            } else {
                $conn->rollback();
                $error = 'حدث خطأ أثناء حذف المادة';
            }
        } else {
            $conn->rollback();
            $error = 'غير مسموح لك بحذف هذه المادة';
        }
    } catch (PDOException $e) {
        $conn->rollback();
        $error = 'خطأ في النظام: ' . $e->getMessage();
    }
}

// جلب مواد المعلم مع الإحصائيات
$subjects_stmt = $conn->prepare("
    SELECT s.*, 
           (SELECT COUNT(*) FROM enrollments WHERE subject_id = s.id) as enrollment_count,
           (SELECT AVG(progress_percentage) FROM enrollments WHERE subject_id = s.id) as avg_progress,
           (SELECT COUNT(*) FROM lessons WHERE subject_id = s.id) as lesson_count,
           (SELECT COUNT(*) FROM quizzes WHERE subject_id = s.id) as quiz_count
    FROM subjects s
    WHERE s.teacher_id = ?
    ORDER BY s.created_at DESC
");
$subjects_stmt->execute([$user_id]);
$teacher_subjects = $subjects_stmt->fetchAll();

// جلب بيانات المعلم
$teacher_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$teacher_stmt->execute([$user_id]);
$teacher = $teacher_stmt->fetch();

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
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المواد الدراسية - منصة همّة التوجيهي</title>
    
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
            --glass-bg: rgba(255, 255, 255, 0.95);
            --glass-border: rgba(255, 255, 255, 0.2);
            --shadow: 0 8px 32px rgba(31, 38, 135, 0.1);
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
            border-radius: 20px;
            box-shadow: var(--shadow);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            margin-bottom: 2rem;
            overflow: hidden;
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
        }

        .card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .subject-card {
            height: 100%;
            position: relative;
        }

        .subject-header {
            height: 160px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            color: white;
            text-align: center;
        }

        .subject-icon {
            font-size: 3rem;
            opacity: 0.2;
            position: absolute;
            top: 15px;
            right: 15px;
        }

        .subject-title-overlay {
            z-index: 2;
            position: relative;
        }

        .status-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            z-index: 3;
            font-size: 0.75rem;
            padding: 0.4rem 0.8rem;
        }

        /* تصميم الأزرار المحسن */
        .actions-container {
            background: linear-gradient(135deg, #f8f9fc 0%, #ffffff 100%);
            padding: 1.5rem;
            border-radius: 0 0 20px 20px;
        }

        /* الزر الرئيسي - إدارة المحتوى */
        .primary-action {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 15px;
            font-weight: 700;
            font-size: 1rem;
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }

        .primary-action:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.4);
            color: white;
        }

        /* شبكة الأزرار الثانوية */
        .secondary-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .action-btn {
            padding: 0.8rem 1rem;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }

        .action-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: left 0.5s;
        }

        .action-btn:hover::before {
            left: 100%;
        }

        .action-btn:hover {
            transform: translateY(-2px);
        }

        /* أنواع الأزرار */
        .btn-edit {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 3px 10px rgba(102, 126, 234, 0.3);
        }

        .btn-edit:hover {
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .btn-quiz {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
            box-shadow: 0 3px 10px rgba(23, 162, 184, 0.3);
        }

        .btn-quiz:hover {
            box-shadow: 0 6px 20px rgba(23, 162, 184, 0.4);
            color: white;
        }

        /* زر التفعيل/إلغاء التفعيل الموحد */
        .toggle-btn {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .toggle-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .toggle-btn:hover::before {
            left: 100%;
        }

        /* حالة التفعيل */
        .toggle-btn.active {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }

        .toggle-btn.active:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.4);
        }

        /* حالة إلغاء التفعيل */
        .toggle-btn.inactive {
            background: linear-gradient(135deg, #ffc107, #ff8f00);
            color: white;
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);
        }

        .toggle-btn.inactive:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255, 193, 7, 0.4);
        }

        /* زر الحذف النهائي */
        .delete-btn {
            width: 100%;
            padding: 0.9rem;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 3px 12px rgba(220, 53, 69, 0.3);
            cursor: pointer;
        }

        .delete-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 53, 69, 0.4);
        }

        /* الإحصائيات */
        .stats-container {
            background: rgba(102, 126, 234, 0.08);
            border-radius: 15px;
            padding: 1.2rem;
            margin: 1rem 0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.5rem;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--primary-color);
            margin-bottom: 0.2rem;
        }

        .stat-label {
            font-size: 0.75rem;
            color: #6c757d;
            font-weight: 500;
        }

        /* تحسينات الاستجابة */
        @media (max-width: 768px) {
            .secondary-actions {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            
            .action-btn {
                padding: 1rem;
                font-size: 0.85rem;
            }
        }

        /* تأثيرات إضافية */
        .filter-section {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }

        .search-box {
            border-radius: 25px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1.5rem;
            transition: all 0.3s ease;
        }

        .search-box:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .alert {
            border-radius: 15px;
            border: none;
        }

        /* تأثير النبض للزر الرئيسي */
        .pulse-effect {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
            }
            50% {
                box-shadow: 0 4px 25px rgba(40, 167, 69, 0.5);
            }
            100% {
                box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
            }
        }

        /* تأثير الانتقال السلس للحالة */
        .status-transition {
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* تنسيق Modal إلغاء التفعيل */
        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .modal-header {
            border-radius: 20px 20px 0 0;
            border-bottom: 1px solid #e9ecef;
        }

        .modal-body {
            padding: 2rem;
        }

        .deactivate-warning {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 1px solid #ffc107;
            border-radius: 15px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .deactivate-warning .fas {
            color: #856404;
            margin-left: 0.5rem;
        }

        /* تأثير التحميل */
        .loading {
            opacity: 0.6;
            pointer-events: none;
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
                            <i class="fas fa-book"></i> إدارة المواد
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="students.php">
                            <i class="fas fa-users"></i> إدارة الطلاب
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($teacher['full_name']); ?>
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
                    <h2><i class="fas fa-book"></i> إدارة المواد الدراسية</h2>
                    <p class="text-muted">إنشاء وإدارة المواد التعليمية الخاصة بك</p>
                </div>
                <div class="text-end">
                    <a href="add_subject.php" class="primary-action pulse-effect" style="width: auto; padding: 1rem 2rem;">
                        <i class="fas fa-plus"></i> إضافة مادة جديدة
                    </a>
                </div>
            </div>

            <!-- Alerts -->
            <div id="alertsContainer">
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
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
                        <select class="form-select" id="statusFilter">
                            <option value="">جميع الحالات</option>
                            <option value="active">نشط</option>
                            <option value="inactive">غير نشط</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Subjects Grid -->
            <?php if (empty($teacher_subjects)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-book-open fa-5x text-muted mb-3"></i>
                    <h3 class="text-muted">لا توجد مواد حالياً</h3>
                    <p class="text-muted">ابدأ بإنشاء مادتك الأولى</p>
                    
                </div>
            <?php else: ?>
                <div class="row" id="subjectsContainer">
                    <?php foreach ($teacher_subjects as $subject): ?>
                    <div class="col-lg-4 col-md-6 mb-4 subject-item" 
                         data-category="<?php echo $subject['category']; ?>" 
                         data-status="<?php echo $subject['is_active'] ? 'active' : 'inactive'; ?>"
                         data-name="<?php echo strtolower($subject['name']); ?>"
                         data-subject-id="<?php echo $subject['id']; ?>">
                        <div class="card subject-card">
                            <span class="badge status-badge <?php echo $subject['is_active'] ? 'bg-success' : 'bg-secondary'; ?>" 
                                  id="statusBadge_<?php echo $subject['id']; ?>">
                                <?php echo $subject['is_active'] ? 'نشط' : 'غير نشط'; ?>
                            </span>
                            
                            <!-- Subject Header with Gradient -->
                            <div class="subject-header" style="background: <?php echo getCategoryGradient($subject['category']); ?>">
                                <?php
                                $category_icons = [
                                    'scientific' => 'fas fa-flask',
                                    'literary' => 'fas fa-feather-alt',
                                    'languages' => 'fas fa-globe',
                                    'default' => 'fas fa-book'
                                ];
                                $icon = $category_icons[$subject['category']] ?? $category_icons['default'];
                                ?>
                                <i class="<?php echo $icon; ?> subject-icon"></i>
                                <div class="subject-title-overlay">
                                    <h4 class="mb-2"><?php echo htmlspecialchars($subject['name']); ?></h4>
                                    <div class="badge bg-light text-dark"><?php echo number_format($subject['price'], 0); ?>ش</div>
                                </div>
                            </div>
                            
                            <div class="card-body">
                                <p class="card-text text-muted mb-3">
                                    <?php echo htmlspecialchars(mb_substr($subject['description'] ?? '', 0, 100)); ?>...
                                </p>

                                <!-- Subject Stats -->
                                <div class="stats-container">
                                    <div class="stats-grid">
                                        <div class="stat-item">
                                            <div class="stat-number"><?php echo (int)$subject['enrollment_count']; ?></div>
                                            <div class="stat-label">طالب</div>
                                        </div>
                                        <div class="stat-item">
                                            <div class="stat-number"><?php echo (int)$subject['lesson_count']; ?></div>
                                            <div class="stat-label">درس</div>
                                        </div>
                                        <div class="stat-item">
                                            <div class="stat-number"><?php echo (int)$subject['quiz_count']; ?></div>
                                            <div class="stat-label">اختبار</div>
                                        </div>
                                        <div class="stat-item">
                                            <div class="stat-number"><?php echo number_format((float)($subject['avg_progress'] ?? 0), 1); ?>%</div>
                                            <div class="stat-label">التقدم</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Subject Info -->
                                <div class="mb-3">
                                    <span class="badge bg-secondary me-2">
                                        <?php 
                                        $categories = ['scientific' => 'علمي', 'literary' => 'أدبي', 'languages' => 'لغات'];
                                        echo $categories[$subject['category']] ?? $subject['category']; 
                                        ?>
                                    </span>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar-alt"></i> 
                                        <?php echo date('Y-m-d', strtotime($subject['created_at'])); ?>
                                    </small>
                                </div>
                            </div>

                            <!-- Actions Container -->
                            <div class="actions-container">
                                <!-- الإجراء الرئيسي -->
                                <a href="lesson_management.php?subject_id=<?php echo $subject['id']; ?>" 
                                   class="primary-action">
                                    <i class="fas fa-cogs"></i>
                                    إدارة المحتوى والدروس
                                </a>
                                
                                <!-- الإجراءات الثانوية -->
                                <div class="secondary-actions">
                                    <a href="edit_subject.php?id=<?php echo $subject['id']; ?>" 
                                       class="action-btn btn-edit">
                                        <i class="fas fa-edit"></i>
                                        تحرير المادة
                                    </a>
                                    <a href="quiz_system.php?subject_id=<?php echo $subject['id']; ?>" 
                                       class="action-btn btn-quiz">
                                        <i class="fas fa-plus"></i>
                                        إضافة اختبار
                                    </a>
                                </div>
                                
                                <!-- زر التفعيل/إلغاء التفعيل الموحد -->
                                <button type="button" 
                                        class="toggle-btn <?php echo $subject['is_active'] ? 'inactive' : 'active'; ?>"
                                        id="toggleBtn_<?php echo $subject['id']; ?>"
                                        onclick="toggleSubjectStatus(<?php echo $subject['id']; ?>, <?php echo $subject['is_active'] ? 0 : 1; ?>, '<?php echo htmlspecialchars($subject['name']); ?>')">
                                    <i class="fas <?php echo $subject['is_active'] ? 'fa-pause-circle' : 'fa-play-circle'; ?>" 
                                       id="toggleIcon_<?php echo $subject['id']; ?>"></i>
                                    <span id="toggleText_<?php echo $subject['id']; ?>">
                                        <?php echo $subject['is_active'] ? 'إيقاف المادة مؤقتاً' : 'تفعيل المادة'; ?>
                                    </span>
                                </button>
                                
                                <!-- زر الحذف النهائي -->
                                <button class="delete-btn" 
                                        onclick="confirmPermanentDelete(<?php echo $subject['id']; ?>, '<?php echo htmlspecialchars($subject['name']); ?>')">
                                    <i class="fas fa-trash-alt"></i>
                                    حذف نهائي
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Permanent Delete Confirmation Modal -->
    <div class="modal fade" id="permanentDeleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">تأكيد الحذف النهائي</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>تحذير!</strong> هذا الإجراء لا يمكن التراجع عنه.
                    </div>
                    <p>هل أنت متأكد من الحذف النهائي للمادة "<span id="permanentDeleteSubjectName"></span>"؟</p>
                    <p class="text-danger">
                        <strong>سيتم حذف:</strong>
                    </p>
                    <ul class="text-danger">
                        <li>المادة نفسها</li>
                        <li>جميع الدروس المرتبطة بها</li>
                        <li>جميع الاختبارات ونتائجها</li>
                        <li>جميع تسجيلات الطلاب</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="subject_id" id="permanentDeleteSubjectId">
                        <button type="submit" name="delete_subject_permanently" class="btn btn-danger">
                            <i class="fas fa-trash"></i> حذف نهائي
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Search and Filter functionality
        document.getElementById('searchInput').addEventListener('input', filterSubjects);
        document.getElementById('categoryFilter').addEventListener('change', filterSubjects);
        document.getElementById('statusFilter').addEventListener('change', filterSubjects);

        function filterSubjects() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const categoryFilter = document.getElementById('categoryFilter').value;
            const statusFilter = document.getElementById('statusFilter').value;
            const subjects = document.querySelectorAll('.subject-item');

            subjects.forEach(subject => {
                const name = subject.dataset.name;
                const category = subject.dataset.category;
                const status = subject.dataset.status;

                const matchesSearch = name.includes(searchTerm);
                const matchesCategory = !categoryFilter || category === categoryFilter;
                const matchesStatus = !statusFilter || status === statusFilter;

                if (matchesSearch && matchesCategory && matchesStatus) {
                    subject.style.display = 'block';
                } else {
                    subject.style.display = 'none';
                }
            });
        }

        // وظيفة تفعيل/إلغاء تفعيل المادة
        function toggleSubjectStatus(subjectId, newStatus, subjectName) {
            const button = document.getElementById(`toggleBtn_${subjectId}`);
            const icon = document.getElementById(`toggleIcon_${subjectId}`);
            const text = document.getElementById(`toggleText_${subjectId}`);
            const statusBadge = document.getElementById(`statusBadge_${subjectId}`);
            const subjectItem = document.querySelector(`[data-subject-id="${subjectId}"]`);
            
            // تعطيل الزر وإظهار التحميل
            button.disabled = true;
            icon.className = 'fas fa-spinner fa-spin';
            text.textContent = 'جاري المعالجة...';
            button.classList.add('loading');
            
            // إرسال طلب AJAX
            const formData = new FormData();
            formData.append('ajax_action', 'toggle_subject');
            formData.append('subject_id', subjectId);
            formData.append('new_status', newStatus);
            
            fetch('subjects.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // إظهار رسالة النجاح
                    showAlert('success', data.message);
                    
                    // تحديث واجهة المستخدم
                    updateSubjectUI(subjectId, data.new_status, data.status_text, data.status_class);
                    
                    // تحديث بيانات العنصر للفلترة
                    subjectItem.dataset.status = data.new_status ? 'active' : 'inactive';
                    
                } else {
                    // إظهار رسالة الخطأ
                    showAlert('danger', data.message);
                    
                    // إعادة تعيين الزر للحالة الأصلية
                    resetToggleButton(subjectId, 1 - newStatus);
                }
            })
            .catch(error => {
                console.error('خطأ:', error);
                showAlert('danger', 'حدث خطأ في الاتصال. يرجى المحاولة مرة أخرى.');
                
                // إعادة تعيين الزر للحالة الأصلية
                resetToggleButton(subjectId, 1 - newStatus);
            });
        }

        // تحديث واجهة المستخدم بعد تغيير الحالة
        function updateSubjectUI(subjectId, newStatus, statusText, statusClass) {
            const button = document.getElementById(`toggleBtn_${subjectId}`);
            const icon = document.getElementById(`toggleIcon_${subjectId}`);
            const text = document.getElementById(`toggleText_${subjectId}`);
            const statusBadge = document.getElementById(`statusBadge_${subjectId}`);
            
            // تحديث شارة الحالة
            statusBadge.className = `badge status-badge ${statusClass}`;
            statusBadge.textContent = statusText;
            
            // تحديث الزر
            button.disabled = false;
            button.classList.remove('loading');
            
            if (newStatus) {
                // المادة أصبحت نشطة - إظهار زر إلغاء التفعيل
                button.className = 'toggle-btn inactive';
                icon.className = 'fas fa-pause-circle';
                text.textContent = 'إيقاف المادة مؤقتاً';
                button.onclick = () => toggleSubjectStatus(subjectId, 0, button.dataset.subjectName);
            } else {
                // المادة أصبحت غير نشطة - إظهار زر التفعيل
                button.className = 'toggle-btn active';
                icon.className = 'fas fa-play-circle';
                text.textContent = 'تفعيل المادة';
                button.onclick = () => toggleSubjectStatus(subjectId, 1, button.dataset.subjectName);
            }
        }

        // إعادة تعيين الزر للحالة الأصلية في حالة الخطأ
        function resetToggleButton(subjectId, originalStatus) {
            const button = document.getElementById(`toggleBtn_${subjectId}`);
            const icon = document.getElementById(`toggleIcon_${subjectId}`);
            const text = document.getElementById(`toggleText_${subjectId}`);
            
            button.disabled = false;
            button.classList.remove('loading');
            
            if (originalStatus) {
                button.className = 'toggle-btn inactive';
                icon.className = 'fas fa-pause-circle';
                text.textContent = 'إيقاف المادة مؤقتاً';
            } else {
                button.className = 'toggle-btn active';
                icon.className = 'fas fa-play-circle';
                text.textContent = 'تفعيل المادة';
            }
        }

        // إظهار التنبيهات
        function showAlert(type, message) {
            const alertsContainer = document.getElementById('alertsContainer');
            const alertId = 'alert_' + Date.now();
            
            const alertHTML = `
                <div class="alert alert-${type} alert-dismissible fade show" id="${alertId}">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'}"></i> 
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            alertsContainer.innerHTML = alertHTML;
            
            // إخفاء التنبيه تلقائياً بعد 5 ثوانٍ
            setTimeout(() => {
                const alertElement = document.getElementById(alertId);
                if (alertElement) {
                    const bsAlert = new bootstrap.Alert(alertElement);
                    bsAlert.close();
                }
            }, 5000);
        }

        // إظهار modal الحذف النهائي
        function confirmPermanentDelete(subjectId, subjectName) {
            document.getElementById('permanentDeleteSubjectId').value = subjectId;
            document.getElementById('permanentDeleteSubjectName').textContent = subjectName;
            new bootstrap.Modal(document.getElementById('permanentDeleteModal')).show();
        }

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Add smooth animations
        document.addEventListener('DOMContentLoaded', function() {
            // Animate cards on load
            const cards = document.querySelectorAll('.subject-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Add click animation to toggle buttons
            const toggleBtns = document.querySelectorAll('.toggle-btn');
            toggleBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 150);
                });
            });
        });
    </script>
</body>
</html>