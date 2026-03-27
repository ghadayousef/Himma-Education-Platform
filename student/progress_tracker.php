<?php
/**
 * تتبع تقدم الطالب في المادة - منصة همّة التعليمية
 * Student Progress Tracker - Himma Educational Platform
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../home/index.php');
    exit();
}

$subject_id = intval($_GET['subject_id'] ?? 0);

if ($subject_id <= 0) {
    header('Location: ../student/dashboard.php');
    exit();
}

try {
    $db = new Database();
    $conn = $db->connect();
    
    // Get subject details
    $subject_stmt = $conn->prepare("
        SELECT s.*, u.name as teacher_name, 
               COUNT(l.id) as total_lessons
        FROM subjects s
        JOIN users u ON s.teacher_id = u.id
        LEFT JOIN lessons l ON s.id = l.subject_id
        WHERE s.id = ?
        GROUP BY s.id
    ");
    $subject_stmt->execute([$subject_id]);
    $subject = $subject_stmt->fetch();
    
    if (!$subject) {
        header('Location: ../student/dashboard.php');
        exit();
    }
    
    // Check if student is enrolled
    $enrollment_stmt = $conn->prepare("
        SELECT * FROM enrollments 
        WHERE student_id = ? AND subject_id = ? AND status = 'active'
    ");
    $enrollment_stmt->execute([$_SESSION['user_id'], $subject_id]);
    $enrollment = $enrollment_stmt->fetch();
    
    if (!$enrollment) {
        header('Location: ../student/subjects.php');
        exit();
    }
    
    // Get lessons with progress
    $lessons_stmt = $conn->prepare("
        SELECT 
            l.*,
            CASE WHEN lv.id IS NOT NULL THEN 1 ELSE 0 END as is_viewed,
            lv.view_count,
            lv.viewed_at,
            lr.rating as user_rating,
            AVG(all_ratings.rating) as avg_rating,
            COUNT(all_ratings.id) as rating_count
        FROM lessons l
        LEFT JOIN lesson_views lv ON l.id = lv.lesson_id AND lv.student_id = ?
        LEFT JOIN lesson_ratings lr ON l.id = lr.lesson_id AND lr.student_id = ?
        LEFT JOIN lesson_ratings all_ratings ON l.id = all_ratings.lesson_id
        WHERE l.subject_id = ?
        GROUP BY l.id
        ORDER BY l.order_num ASC, l.created_at ASC
    ");
    $lessons_stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $subject_id]);
    $lessons = $lessons_stmt->fetchAll();
    
    // Calculate progress statistics
    $total_lessons = count($lessons);
    $viewed_lessons = count(array_filter($lessons, function($lesson) {
        return $lesson['is_viewed'];
    }));
    $progress_percentage = $total_lessons > 0 ? round(($viewed_lessons / $total_lessons) * 100, 1) : 0;
    
    // Get recent activity
    $activity_stmt = $conn->prepare("
        SELECT 
            'view' as activity_type,
            l.title as lesson_title,
            lv.viewed_at as activity_date,
            'شاهد الدرس' as activity_description
        FROM lesson_views lv
        JOIN lessons l ON lv.lesson_id = l.id
        WHERE l.subject_id = ? AND lv.student_id = ?
        
        UNION ALL
        
        SELECT 
            'rating' as activity_type,
            l.title as lesson_title,
            lr.created_at as activity_date,
            CONCAT('قيّم الدرس بـ ', lr.rating, ' نجوم') as activity_description
        FROM lesson_ratings lr
        JOIN lessons l ON lr.lesson_id = l.id
        WHERE l.subject_id = ? AND lr.student_id = ?
        
        ORDER BY activity_date DESC
        LIMIT 10
    ");
    $activity_stmt->execute([$subject_id, $_SESSION['user_id'], $subject_id, $_SESSION['user_id']]);
    $recent_activity = $activity_stmt->fetchAll();
    
    // Update enrollment progress
    $update_progress_stmt = $conn->prepare("
        UPDATE enrollments 
        SET progress = ?, updated_at = NOW()
        WHERE student_id = ? AND subject_id = ?
    ");
    $update_progress_stmt->execute([$progress_percentage, $_SESSION['user_id'], $subject_id]);
    
    // Check if eligible for certificate
    $certificate_eligible = $progress_percentage >= 80; // 80% completion required
    
    // Get certificate if exists
    $certificate = null;
    if ($certificate_eligible) {
        $cert_stmt = $conn->prepare("
            SELECT * FROM certificates 
            WHERE student_id = ? AND subject_id = ?
        ");
        $cert_stmt->execute([$_SESSION['user_id'], $subject_id]);
        $certificate = $cert_stmt->fetch();
    }
    
} catch (Exception $e) {
    error_log("Error loading progress: " . $e->getMessage());
    header('Location: ../student/dashboard.php');
    exit();
}

// Handle certificate generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_certificate') {
    if ($certificate_eligible && !$certificate) {
        try {
            $cert_id = 'CERT-' . strtoupper(uniqid());
            $issue_date = date('Y-m-d H:i:s');
            
            $cert_stmt = $conn->prepare("
                INSERT INTO certificates (certificate_id, student_id, subject_id, issue_date, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            if ($cert_stmt->execute([$cert_id, $_SESSION['user_id'], $subject_id, $issue_date])) {
                // Get the certificate
                $cert_stmt = $conn->prepare("
                    SELECT * FROM certificates 
                    WHERE student_id = ? AND subject_id = ?
                ");
                $cert_stmt->execute([$_SESSION['user_id'], $subject_id]);
                $certificate = $cert_stmt->fetch();
                
                $success_message = 'تهانينا! تم إنشاء شهادة الإتمام بنجاح!';
            }
        } catch (Exception $e) {
            $error_message = 'حدث خطأ أثناء إنشاء الشهادة';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقدمك في <?php echo htmlspecialchars($subject['name']); ?> - منصة همّة التعليمية</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
            --shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
        }

        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: white;
        }

        .glass {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .subject-header {
            text-align: center;
            margin-bottom: 3rem;
            padding: 2rem;
        }

        .subject-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #fff, #f093fb);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .progress-overview {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .progress-card {
            padding: 2rem;
            text-align: center;
        }

        .progress-circle {
            position: relative;
            width: 200px;
            height: 200px;
            margin: 0 auto 2rem;
        }

        .progress-ring {
            transform: rotate(-90deg);
        }

        .progress-ring-bg {
            fill: none;
            stroke: rgba(255, 255, 255, 0.1);
            stroke-width: 8;
        }

        .progress-ring-fill {
            fill: none;
            stroke: var(--success-color);
            stroke-width: 8;
            stroke-linecap: round;
            transition: stroke-dasharray 0.5s ease;
        }

        .progress-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 2rem;
            font-weight: 700;
            color: var(--success-color);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }

        .stat-item {
            text-align: center;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--accent-color);
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
            margin-top: 0.5rem;
        }

        .lessons-progress {
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .lesson-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            margin-bottom: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .lesson-item:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .lesson-status {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 1rem;
            font-size: 1.2rem;
        }

        .status-completed {
            background: var(--success-color);
            color: white;
        }

        .status-pending {
            background: rgba(255, 255, 255, 0.2);
            color: rgba(255, 255, 255, 0.7);
        }

        .lesson-info {
            flex: 1;
        }

        .lesson-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .lesson-meta {
            font-size: 0.9rem;
            opacity: 0.7;
            display: flex;
            gap: 1rem;
        }

        .activity-section {
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .activity-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            margin-bottom: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            border-right: 4px solid var(--accent-color);
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 1rem;
            background: var(--success-color);
        }

        .activity-content {
            flex: 1;
        }

        .activity-description {
            font-weight: 600;
            margin-bottom: 0.3rem;
        }

        .activity-lesson {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .activity-date {
            font-size: 0.8rem;
            opacity: 0.6;
        }

        .certificate-section {
            padding: 2rem;
            text-align: center;
            margin-bottom: 2rem;
        }

        .certificate-card {
            background: linear-gradient(135deg, var(--warning-color), var(--success-color));
            padding: 3rem 2rem;
            border-radius: 20px;
            margin: 2rem 0;
        }

        .certificate-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-family: 'Cairo', sans-serif;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--success-color), var(--warning-color));
            color: white;
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
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

        @media (max-width: 768px) {
            .progress-overview {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Subject Header -->
        <div class="subject-header glass">
            <h1 class="subject-title"><?php echo htmlspecialchars($subject['name']); ?></h1>
            <p>معلم المادة: <?php echo htmlspecialchars($subject['teacher_name']); ?></p>
        </div>

        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <!-- Progress Overview -->
        <div class="progress-overview">
            <div class="progress-card glass">
                <h3 style="color: var(--accent-color); margin-bottom: 2rem;">
                    <i class="fas fa-chart-pie"></i> نسبة الإتمام
                </h3>
                <div class="progress-circle">
                    <svg class="progress-ring" width="200" height="200">
                        <circle class="progress-ring-bg" cx="100" cy="100" r="90"/>
                        <circle class="progress-ring-fill" cx="100" cy="100" r="90"
                                stroke-dasharray="<?php echo ($progress_percentage / 100) * 565.48; ?> 565.48"/>
                    </svg>
                    <div class="progress-text"><?php echo $progress_percentage; ?>%</div>
                </div>
                <p style="font-size: 1.2rem; opacity: 0.9;">
                    أكملت <?php echo $viewed_lessons; ?> من <?php echo $total_lessons; ?> دروس
                </p>
            </div>

            <div class="progress-card glass">
                <h3 style="color: var(--accent-color); margin-bottom: 2rem;">
                    <i class="fas fa-chart-bar"></i> إحصائيات التقدم
                </h3>
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $total_lessons; ?></div>
                        <div class="stat-label">إجمالي الدروس</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $viewed_lessons; ?></div>
                        <div class="stat-label">دروس مكتملة</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $total_lessons - $viewed_lessons; ?></div>
                        <div class="stat-label">دروس متبقية</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">
                            <?php 
                            $rated_lessons = count(array_filter($lessons, function($lesson) {
                                return !empty($lesson['user_rating']);
                            }));
                            echo $rated_lessons;
                            ?>
                        </div>
                        <div class="stat-label">دروس مُقيّمة</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lessons Progress -->
        <div class="lessons-progress glass">
            <h3 style="color: var(--accent-color); margin-bottom: 2rem;">
                <i class="fas fa-list-check"></i> تقدم الدروس
            </h3>
            <?php foreach ($lessons as $lesson): ?>
                <div class="lesson-item">
                    <div class="lesson-status <?php echo $lesson['is_viewed'] ? 'status-completed' : 'status-pending'; ?>">
                        <i class="fas fa-<?php echo $lesson['is_viewed'] ? 'check' : 'clock'; ?>"></i>
                    </div>
                    <div class="lesson-info">
                        <div class="lesson-title"><?php echo htmlspecialchars($lesson['title']); ?></div>
                        <div class="lesson-meta">
                            <span><i class="fas fa-clock"></i> <?php echo $lesson['duration_minutes']; ?> دقيقة</span>
                            <?php if ($lesson['is_viewed']): ?>
                                <span><i class="fas fa-eye"></i> شوهد <?php echo $lesson['view_count']; ?> مرة</span>
                                <span><i class="fas fa-calendar"></i> <?php echo date('Y-m-d', strtotime($lesson['viewed_at'])); ?></span>
                            <?php endif; ?>
                            <?php if ($lesson['user_rating']): ?>
                                <span style="color: #ffd700;">
                                    <i class="fas fa-star"></i> <?php echo $lesson['user_rating']; ?>/5
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <a href="lesson_viewer.php?lesson_id=<?php echo $lesson['id']; ?>" 
                           class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.9rem;">
                            <?php echo $lesson['is_viewed'] ? 'مراجعة' : 'مشاهدة'; ?>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Certificate Section -->
        <?php if ($certificate_eligible): ?>
            <div class="certificate-section glass">
                <h3 style="color: var(--accent-color); margin-bottom: 2rem;">
                    <i class="fas fa-certificate"></i> شهادة الإتمام
                </h3>
                
                <?php if ($certificate): ?>
                    <div class="certificate-card">
                        <div class="certificate-icon">🏆</div>
                        <h2 style="margin-bottom: 1rem;">تهانينا!</h2>
                        <p style="font-size: 1.2rem; margin-bottom: 1rem;">
                            لقد حصلت على شهادة إتمام مادة "<?php echo htmlspecialchars($subject['name']); ?>"
                        </p>
                        <p style="font-size: 1rem; opacity: 0.9;">
                            رقم الشهادة: <?php echo htmlspecialchars($certificate['certificate_id']); ?>
                        </p>
                        <p style="font-size: 0.9rem; opacity: 0.8;">
                            تاريخ الإصدار: <?php echo date('Y-m-d', strtotime($certificate['issue_date'])); ?>
                        </p>
                    </div>
                    <a href="certificate.php?id=<?php echo $certificate['id']; ?>" 
                       class="btn btn-primary" target="_blank">
                        <i class="fas fa-download"></i> تحميل الشهادة
                    </a>
                <?php else: ?>
                    <div class="certificate-card">
                        <div class="certificate-icon">🎓</div>
                        <h2 style="margin-bottom: 1rem;">مؤهل للحصول على الشهادة!</h2>
                        <p style="font-size: 1.1rem; margin-bottom: 2rem;">
                            لقد أكملت <?php echo $progress_percentage; ?>% من المادة وأصبحت مؤهلاً للحصول على شهادة الإتمام
                        </p>
                        <form method="POST">
                            <input type="hidden" name="action" value="generate_certificate">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-certificate"></i> إنشاء الشهادة
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="certificate-section glass">
                <h3 style="color: var(--accent-color); margin-bottom: 2rem;">
                    <i class="fas fa-certificate"></i> شهادة الإتمام
                </h3>
                <div style="text-align: center; padding: 2rem; opacity: 0.7;">
                    <i class="fas fa-lock" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                    <p style="font-size: 1.1rem;">
                        أكمل 80% من الدروس للحصول على شهادة الإتمام
                    </p>
                    <p style="margin-top: 1rem;">
                        التقدم الحالي: <?php echo $progress_percentage; ?>% 
                        (تحتاج <?php echo max(0, ceil($total_lessons * 0.8) - $viewed_lessons); ?> دروس إضافية)
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Recent Activity -->
        <div class="activity-section glass">
            <h3 style="color: var(--accent-color); margin-bottom: 2rem;">
                <i class="fas fa-history"></i> النشاط الأخير
            </h3>
            
            <?php if (empty($recent_activity)): ?>
                <div style="text-align: center; padding: 2rem; opacity: 0.7;">
                    <i class="fas fa-clock" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                    <p>لا يوجد نشاط بعد</p>
                </div>
            <?php else: ?>
                <?php foreach ($recent_activity as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon">
                            <i class="fas fa-<?php echo $activity['activity_type'] === 'view' ? 'eye' : 'star'; ?>"></i>
                        </div>
                        <div class="activity-content">
                            <div class="activity-description"><?php echo htmlspecialchars($activity['activity_description']); ?></div>
                            <div class="activity-lesson"><?php echo htmlspecialchars($activity['lesson_title']); ?></div>
                        </div>
                        <div class="activity-date">
                            <?php echo date('Y-m-d H:i', strtotime($activity['activity_date'])); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Navigation -->
        <div style="text-align: center; margin-top: 2rem;">
            <a href="../student/dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-right"></i> العودة للوحة التحكم
            </a>
        </div>
    </div>
</body>
</html>