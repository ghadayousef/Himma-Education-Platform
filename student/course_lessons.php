<?php
/**
 * صفحة عرض دروس المساق للطلاب - منصة همّة التوجيهي
 * Course Lessons View for Students - Himma Platform
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
$student_id = $_SESSION['user_id'];

// الحصول على معرف المساق
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;

if ($subject_id <= 0) {
    redirect('dashboard.php');
}

// التحقق من تسجيل الطالب في المساق
$enrollment_check = $conn->prepare("
    SELECT e.*, s.name as subject_name, s.description, u.full_name as teacher_name
    FROM enrollments e
    INNER JOIN subjects s ON e.subject_id = s.id
    INNER JOIN users u ON s.teacher_id = u.id
    WHERE e.user_id = ? AND e.subject_id = ? AND e.status = 'active'
");
$enrollment_check->execute([$student_id, $subject_id]);
$enrollment = $enrollment_check->fetch();

if (!$enrollment) {
    redirect('dashboard.php');
}

// جلب جميع الدروس المرفوعة للمساق
$lessons_stmt = $conn->prepare("
    SELECT l.*, u.full_name as teacher_name
    FROM lessons l
    INNER JOIN users u ON l.teacher_id = u.id
    WHERE l.subject_id = ? AND l.is_active = 1
    ORDER BY l.lesson_order ASC, l.created_at ASC
");
$lessons_stmt->execute([$subject_id]);
$lessons = $lessons_stmt->fetchAll();

// إحصائيات المساق
$total_lessons = count($lessons);
$video_lessons = count(array_filter($lessons, function($lesson) {
    return !empty($lesson['video_path']);
}));
$document_lessons = count(array_filter($lessons, function($lesson) {
    return !empty($lesson['document_path']);
}));

// تسجيل مشاهدة الدرس (إذا تم تمرير معرف الدرس)
if (isset($_POST['mark_watched']) && isset($_POST['lesson_id'])) {
    $lesson_id = intval($_POST['lesson_id']);
    
    // التحقق من وجود الدرس
    $lesson_check = $conn->prepare("SELECT id FROM lessons WHERE id = ? AND subject_id = ?");
    $lesson_check->execute([$lesson_id, $subject_id]);
    
    if ($lesson_check->fetch()) {
        // تسجيل أو تحديث مشاهدة الدرس
        $watch_stmt = $conn->prepare("
            INSERT INTO lesson_views (student_id, lesson_id, viewed_at, progress_percentage)
            VALUES (?, ?, NOW(), 100)
            ON DUPLICATE KEY UPDATE viewed_at = NOW(), progress_percentage = 100
        ");
        $watch_stmt->execute([$student_id, $lesson_id]);
    }
}

// جلب حالة مشاهدة الدروس للطالب
$viewed_lessons = [];
if (!empty($lessons)) {
    $lesson_ids = array_column($lessons, 'id');
    $placeholders = str_repeat('?,', count($lesson_ids) - 1) . '?';
    
    $views_stmt = $conn->prepare("
        SELECT lesson_id, viewed_at, progress_percentage
        FROM lesson_views 
        WHERE student_id = ? AND lesson_id IN ($placeholders)
    ");
    $views_stmt->execute(array_merge([$student_id], $lesson_ids));
    
    while ($view = $views_stmt->fetch()) {
        $viewed_lessons[$view['lesson_id']] = $view;
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>دروس <?php echo htmlspecialchars($enrollment['subject_name']); ?> - منصة همّة التوجيهي</title>
    
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
            --success-color: #4facfe;
            --warning-color: #43e97b;
            --danger-color: #fd79a8;
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

        .navbar-brand, .navbar-nav .nav-link {
            color: white !important;
            font-weight: 600;
        }

        .course-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
        }

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 1rem;
            transition: transform 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-5px);
        }

        .lesson-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 1.5rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .lesson-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .lesson-card.watched {
            border-left: 5px solid var(--success-color);
        }

        .lesson-card.unwatched {
            border-left: 5px solid var(--warning-color);
        }

        .lesson-thumbnail {
            position: relative;
            height: 200px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .video-thumbnail {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .play-button {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0,0,0,0.7);
            color: white;
            border: none;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            font-size: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .play-button:hover {
            background: rgba(0,0,0,0.9);
            transform: translate(-50%, -50%) scale(1.1);
        }

        .lesson-content {
            padding: 1.5rem;
        }

        .lesson-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            color: #666;
        }

        .lesson-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .progress-indicator {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 0.3rem 0.6rem;
            border-radius: 15px;
            font-size: 0.8rem;
        }

        .lesson-type-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            padding: 0.3rem 0.6rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .badge-video {
            background: var(--danger-color);
            color: white;
        }

        .badge-document {
            background: var(--warning-color);
            color: white;
        }

        .video-modal .modal-dialog {
            max-width: 90vw;
        }

        .video-container {
            position: relative;
            width: 100%;
            height: 0;
            padding-bottom: 56.25%; /* 16:9 aspect ratio */
        }

        .video-container video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
        }

        .back-button {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .back-button:hover {
            background: rgba(255,255,255,0.3);
            color: white;
            text-decoration: none;
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
            
            <div class="navbar-nav me-auto">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> لوحة التحكم
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Course Header -->
        <div class="course-header">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h1><i class="fas fa-play-circle"></i> <?php echo htmlspecialchars($enrollment['subject_name']); ?></h1>
                <a href="dashboard.php" class="back-button">
                    <i class="fas fa-arrow-right"></i> العودة للوحة التحكم
                </a>
            </div>
            
            <p class="mb-2"><?php echo htmlspecialchars($enrollment['description']); ?></p>
            <p class="mb-0"><i class="fas fa-user-tie"></i> المعلم: <?php echo htmlspecialchars($enrollment['teacher_name']); ?></p>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <i class="fas fa-book-open fa-2x text-primary mb-2"></i>
                    <h4><?php echo $total_lessons; ?></h4>
                    <small>إجمالي الدروس</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <i class="fas fa-video fa-2x text-danger mb-2"></i>
                    <h4><?php echo $video_lessons; ?></h4>
                    <small>دروس فيديو</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <i class="fas fa-file-alt fa-2x text-warning mb-2"></i>
                    <h4><?php echo $document_lessons; ?></h4>
                    <small>مواد مكتوبة</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                    <h4><?php echo count($viewed_lessons); ?></h4>
                    <small>دروس مكتملة</small>
                </div>
            </div>
        </div>

        <!-- Lessons List -->
        <?php if (empty($lessons)): ?>
            <div class="empty-state">
                <i class="fas fa-video fa-3x text-muted mb-3"></i>
                <h4>لا توجد دروس بعد</h4>
                <p class="text-muted">لم يقم المعلم برفع أي دروس لهذا المساق حتى الآن.</p>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($lessons as $lesson): ?>
                    <?php 
                    $is_watched = isset($viewed_lessons[$lesson['id']]);
                    $has_video = !empty($lesson['video_path']);
                    $has_document = !empty($lesson['document_path']);
                    ?>
                    <div class="col-lg-6 col-xl-4">
                        <div class="lesson-card <?php echo $is_watched ? 'watched' : 'unwatched'; ?>">
                            <div class="lesson-thumbnail">
                                <?php if ($has_video): ?>
                                    <!-- Video Thumbnail -->
                                    <video class="video-thumbnail" preload="metadata">
                                        <source src="../<?php echo htmlspecialchars($lesson['video_path']); ?>#t=1" type="video/mp4">
                                    </video>
                                    <button class="play-button" onclick="playVideo(<?php echo $lesson['id']; ?>, '<?php echo htmlspecialchars($lesson['video_path']); ?>', '<?php echo htmlspecialchars($lesson['title']); ?>')">
                                        <i class="fas fa-play"></i>
                                    </button>
                                    <div class="lesson-type-badge badge-video">
                                        <i class="fas fa-video"></i> فيديو
                                    </div>
                                <?php else: ?>
                                    <!-- Document Icon -->
                                    <i class="fas fa-file-alt fa-4x"></i>
                                    <div class="lesson-type-badge badge-document">
                                        <i class="fas fa-file-alt"></i> مستند
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($is_watched): ?>
                                    <div class="progress-indicator">
                                        <i class="fas fa-check"></i> مكتمل
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="lesson-content">
                                <h5><?php echo htmlspecialchars($lesson['title']); ?></h5>
                                
                                <?php if (!empty($lesson['description'])): ?>
                                    <p class="text-muted"><?php echo htmlspecialchars(substr($lesson['description'], 0, 100)); ?>...</p>
                                <?php endif; ?>
                                
                                <div class="lesson-meta">
                                    <span><i class="fas fa-clock"></i> <?php echo date('d/m/Y', strtotime($lesson['created_at'])); ?></span>
                                    <?php if (!empty($lesson['duration'])): ?>
                                        <span><i class="fas fa-stopwatch"></i> <?php echo $lesson['duration']; ?> دقيقة</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="lesson-actions">
                                    <?php if ($has_video): ?>
                                        <button class="btn btn-primary btn-sm" onclick="playVideo(<?php echo $lesson['id']; ?>, '<?php echo htmlspecialchars($lesson['video_path']); ?>', '<?php echo htmlspecialchars($lesson['title']); ?>')">
                                            <i class="fas fa-play"></i> مشاهدة
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($has_document): ?>
                                        <a href="../<?php echo htmlspecialchars($lesson['document_path']); ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-download"></i> تحميل المادة
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (!$is_watched): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="lesson_id" value="<?php echo $lesson['id']; ?>">
                                            <button type="submit" name="mark_watched" class="btn btn-success btn-sm">
                                                <i class="fas fa-check"></i> تسجيل كمكتمل
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Video Modal -->
    <div class="modal fade video-modal" id="videoModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="videoModalTitle">عرض الفيديو</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="video-container">
                        <video id="lessonVideo" controls controlsList="nodownload">
                            <source id="videoSource" src="" type="video/mp4">
                            متصفحك لا يدعم عرض الفيديو.
                        </video>
                    </div>
                </div>
                <div class="modal-footer">
                    <form method="POST" id="markWatchedForm">
                        <input type="hidden" name="lesson_id" id="modalLessonId">
                        <button type="submit" name="mark_watched" class="btn btn-success">
                            <i class="fas fa-check"></i> تسجيل كمكتمل
                        </button>
                    </form>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function playVideo(lessonId, videoPath, title) {
            // Set video source
            document.getElementById('videoSource').src = '../' + videoPath;
            document.getElementById('videoModalTitle').textContent = title;
            document.getElementById('modalLessonId').value = lessonId;
            
            // Load and show modal
            const video = document.getElementById('lessonVideo');
            video.load();
            
            const modal = new bootstrap.Modal(document.getElementById('videoModal'));
            modal.show();
            
            // Auto-play when modal is shown
            document.getElementById('videoModal').addEventListener('shown.bs.modal', function() {
                video.play();
            });
            
            // Pause when modal is hidden
            document.getElementById('videoModal').addEventListener('hidden.bs.modal', function() {
                video.pause();
                video.currentTime = 0;
            });
        }
        
        // Auto-mark as watched when video ends
        document.getElementById('lessonVideo').addEventListener('ended', function() {
            const lessonId = document.getElementById('modalLessonId').value;
            if (lessonId) {
                // Auto-submit the form to mark as watched
                document.getElementById('markWatchedForm').submit();
            }
        });
        
        // Progress tracking (optional)
        document.getElementById('lessonVideo').addEventListener('timeupdate', function() {
            const video = this;
            const progress = (video.currentTime / video.duration) * 100;
            
            // You can implement progress saving here if needed
            if (progress > 80) { // Mark as watched if 80% completed
                // Optional: Auto-mark as watched
            }
        });
    </script>
</body>
</html>