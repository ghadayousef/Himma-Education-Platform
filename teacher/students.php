<?php
/**
 * إدارة الطلاب - منصة همّة التوجيهي
 * Students Management - Himma Tawjihi Platform
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
$teacher_id = $_SESSION['user_id'];

// جلب مواد المعلم
$subjects_stmt = $conn->prepare("SELECT id, name FROM subjects WHERE teacher_id = ? AND is_active = 1");
$subjects_stmt->execute([$teacher_id]);
$teacher_subjects = $subjects_stmt->fetchAll();

// تحديد المادة المختارة
$selected_subject = $_GET['subject_id'] ?? '';

// جلب الطلاب المسجلين في مواد المعلم
if ($selected_subject) {
    $students_stmt = $conn->prepare("
        SELECT e.*, u.full_name, u.email, u.phone, u.created_at as registration_date,
               s.name as subject_name, s.price,
               (SELECT COUNT(*) FROM quiz_results qr 
                JOIN quizzes q ON qr.quiz_id = q.id 
                WHERE q.subject_id = s.id AND qr.user_id = u.id) as completed_quizzes,
               (SELECT AVG(qr.score) FROM quiz_results qr 
                JOIN quizzes q ON qr.quiz_id = q.id 
                WHERE q.subject_id = s.id AND qr.user_id = u.id) as avg_score
        FROM enrollments e
        JOIN users u ON e.user_id = u.id
        JOIN subjects s ON e.subject_id = s.id
        WHERE s.teacher_id = ? AND e.subject_id = ?
        ORDER BY e.enrollment_date DESC
    ");
    $students_stmt->execute([$teacher_id, $selected_subject]);
} else {
    $students_stmt = $conn->prepare("
        SELECT e.*, u.full_name, u.email, u.phone, u.created_at as registration_date,
               s.name as subject_name, s.price,
               (SELECT COUNT(*) FROM quiz_results qr 
                JOIN quizzes q ON qr.quiz_id = q.id 
                WHERE q.subject_id = s.id AND qr.user_id = u.id) as completed_quizzes,
               (SELECT AVG(qr.score) FROM quiz_results qr 
                JOIN quizzes q ON qr.quiz_id = q.id 
                WHERE q.subject_id = s.id AND qr.user_id = u.id) as avg_score
        FROM enrollments e
        JOIN users u ON e.user_id = u.id
        JOIN subjects s ON e.subject_id = s.id
        WHERE s.teacher_id = ?
        ORDER BY e.enrollment_date DESC
    ");
    $students_stmt->execute([$teacher_id]);
}

$students = $students_stmt->fetchAll();

// إحصائيات عامة
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(DISTINCT e.user_id) as total_students,
        COUNT(DISTINCT e.subject_id) as active_subjects,
        SUM(CASE WHEN e.payment_status = 'paid' THEN s.price ELSE 0 END) as total_revenue,
        COUNT(CASE WHEN e.enrollment_date >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as new_students_week
    FROM enrollments e
    JOIN subjects s ON e.subject_id = s.id
    WHERE s.teacher_id = ?
");
$stats_stmt->execute([$teacher_id]);
$stats = $stats_stmt->fetch();

// جلب بيانات المعلم
$teacher_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$teacher_stmt->execute([$teacher_id]);
$teacher = $teacher_stmt->fetch();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الطلاب - منصة همّة التوجيهي</title>
    
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

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }

        .stats-card {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .stats-card.success {
            background: linear-gradient(135deg, var(--success-color), var(--warning-color));
        }

        .stats-card.warning {
            background: linear-gradient(135deg, var(--warning-color), var(--accent-color));
        }

        .stats-card.danger {
            background: linear-gradient(135deg, var(--danger-color), var(--accent-color));
        }

        .student-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .progress-bar {
            background: linear-gradient(90deg, var(--success-color), var(--warning-color));
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

        .table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
        }

        .table thead th {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            font-weight: 600;
        }

        .badge-status {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
        }

        .filter-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
        }

        .student-card {
            transition: all 0.3s ease;
            border-left: 4px solid var(--primary-color);
        }

        .student-card:hover {
            border-left-color: var(--accent-color);
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
                            <i class="fas fa-book"></i> إدارة المواد
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="students.php">
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
                    <h2><i class="fas fa-users"></i> إدارة الطلاب</h2>
                    <p class="text-muted">متابعة وإدارة الطلاب المسجلين في موادك</p>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card stats-card">
                        <div class="card-body text-center">
                            <i class="fas fa-users fa-2x mb-2"></i>
                            <h3><?php echo $stats['total_students']; ?></h3>
                            <p class="mb-0">إجمالي الطلاب</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card stats-card success">
                        <div class="card-body text-center">
                            <i class="fas fa-book fa-2x mb-2"></i>
                            <h3><?php echo $stats['active_subjects']; ?></h3>
                            <p class="mb-0">المواد النشطة</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card stats-card warning">
                        <div class="card-body text-center">
                            <i class="fas fa-dollar-sign fa-2x mb-2"></i>
                            <h3><?php echo number_format($stats['total_revenue'], 2); ?>ش</h3>
                            <p class="mb-0">إجمالي الإيرادات</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card stats-card danger">
                        <div class="card-body text-center">
                            <i class="fas fa-user-plus fa-2x mb-2"></i>
                            <h3><?php echo $stats['new_students_week']; ?></h3>
                            <p class="mb-0">طلاب جدد هذا الأسبوع</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Section -->
            <div class="card filter-card mb-4">
                <div class="card-body">
                    <form method="GET" action="">
                        <div class="row align-items-end">
                            <div class="col-md-4">
                                <label class="form-label">تصفية حسب المادة</label>
                                <select name="subject_id" class="form-select">
                                    <option value="">جميع المواد</option>
                                    <?php foreach ($teacher_subjects as $subject): ?>
                                        <option value="<?php echo $subject['id']; ?>" 
                                                <?php echo ($selected_subject == $subject['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($subject['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> تصفية
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Students List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-list"></i> قائمة الطلاب
                        <span class="badge bg-primary ms-2"><?php echo count($students); ?> طالب</span>
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($students)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-users fa-5x text-muted mb-3"></i>
                            <h4 class="text-muted">لا يوجد طلاب مسجلين</h4>
                            <p class="text-muted">لم يسجل أي طالب في موادك بعد</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>الطالب</th>
                                        <th>المادة</th>
                                        <th>التقدم</th>
                                        <th>الاختبارات</th>
                                        <th>المعدل</th>
                                        <th>حالة الدفع</th>
                                        <th>تاريخ التسجيل</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="student-avatar me-3">
                                                        <?php echo strtoupper(substr($student['full_name'], 0, 2)); ?>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0"><?php echo htmlspecialchars($student['full_name']); ?></h6>
                                                        <small class="text-muted"><?php echo htmlspecialchars($student['email']); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($student['subject_name']); ?></span>
                                            </td>
                                            <td>
                                                <div class="progress" style="height: 8px;">
                                                    <div class="progress-bar" style="width: <?php echo $student['progress_percentage']; ?>%"></div>
                                                </div>
                                                <small class="text-muted"><?php echo number_format($student['progress_percentage'], 1); ?>%</small>
                                            </td>
                                            <td>
                                                <span class="badge bg-success"><?php echo (int)$student['completed_quizzes']; ?></span>
                                            </td>
                                            <td>
                                                <?php if ($student['avg_score']): ?>
                                                    <span class="badge bg-primary"><?php echo number_format($student['avg_score'], 1); ?>%</span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-status <?php echo $student['payment_status'] === 'paid' ? 'bg-success' : 'bg-warning'; ?>">
                                                    <?php echo $student['payment_status'] === 'paid' ? 'مدفوع' : 'في الانتظار'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small><?php echo date('Y-m-d', strtotime($student['enrollment_date'])); ?></small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary" 
                                                            onclick="viewStudentDetails(<?php echo $student['user_id']; ?>)"
                                                            title="عرض التفاصيل">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-outline-success" 
                                                            onclick="sendMessage(<?php echo $student['user_id']; ?>)"
                                                            title="إرسال رسالة">
                                                        <i class="fas fa-envelope"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Student Details Modal -->
    <div class="modal fade" id="studentDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">تفاصيل الطالب</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="studentDetailsContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function viewStudentDetails(studentId) {
            // Load student details via AJAX
            fetch(`student_details.php?id=${studentId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('studentDetailsContent').innerHTML = data;
                    new bootstrap.Modal(document.getElementById('studentDetailsModal')).show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('حدث خطأ في تحميل بيانات الطالب');
                });
        }

        function sendMessage(studentId) {
            // Implement messaging functionality
            alert('سيتم إضافة نظام الرسائل قريباً');
        }

        // Auto refresh every 30 seconds
        setInterval(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>