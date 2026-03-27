<?php
/**
 * محتوى المساق - منصة همّة التوجيهي
 * Course Content - Himma Tawjihi Platform
 */

ini_set('display_errors', 0);

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
$subject_id = intval($_GET['id'] ?? 0);

if ($subject_id <= 0) {
    redirect('my_courses.php');
}

// التحقق من تسجيل الطالب في المساق
$enrollment_check = $conn->prepare("
    SELECT e.*, s.name as subject_name, s.description, u.full_name as teacher_name, s.category
    FROM enrollments e
    JOIN subjects s ON e.subject_id = s.id
    JOIN users u ON s.teacher_id = u.id
    WHERE e.user_id = ? AND e.subject_id = ? AND e.status = 'active' AND s.is_active = 1
");
$enrollment_check->execute([$user_id, $subject_id]);
$enrollment = $enrollment_check->fetch();

if (!$enrollment) {
    redirect('my_courses.php');
}

// جلب دروس المساق
$lessons_stmt = $conn->prepare("
    SELECT l.*, 
           COALESCE(lp.completed, 0) as is_completed
    FROM lessons l
    LEFT JOIN lesson_progress lp ON l.id = lp.lesson_id AND lp.user_id = ?
    WHERE l.subject_id = ?
    ORDER BY l.order_num ASC, l.created_at ASC
");
$lessons_stmt->execute([$user_id, $subject_id]);
$lessons = $lessons_stmt->fetchAll();

// معالجة تسجيل تقدم الدرس
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_completed'])) {
    $lesson_id = intval($_POST['lesson_id']);
    
    // التحقق من أن الدرس ينتمي للمساق
    $lesson_check = $conn->prepare("SELECT id FROM lessons WHERE id = ? AND subject_id = ?");
    $lesson_check->execute([$lesson_id, $subject_id]);
    
    if ($lesson_check->fetch()) {
        // تسجيل أو تحديث تقدم الدرس (متوافق مع بنية الجدول الحالية)
        $progress_exists = $conn->prepare("SELECT id FROM lesson_progress WHERE user_id = ? AND lesson_id = ?");
        $progress_exists->execute([$user_id, $lesson_id]);

        if ($progress_exists->fetch()) {
            $progress_update = $conn->prepare("
                UPDATE lesson_progress 
                SET completed = 1, completed_at = NOW(), updated_at = NOW()
                WHERE user_id = ? AND lesson_id = ?
            ");
            $progress_update->execute([$user_id, $lesson_id]);
        } else {
            $progress_insert = $conn->prepare("
                INSERT INTO lesson_progress (user_id, lesson_id, completed, completed_at, created_at, updated_at)
                VALUES (?, ?, 1, NOW(), NOW(), NOW())
            ");
            $progress_insert->execute([$user_id, $lesson_id]);
        }
        
        // تحديث تقدم المساق العام
        $total_lessons = count($lessons);
        $completed_lessons = $conn->prepare("
            SELECT COUNT(*) FROM lesson_progress 
            WHERE user_id = ? AND lesson_id IN (SELECT id FROM lessons WHERE subject_id = ?) AND completed = 1
        ");
        $completed_lessons->execute([$user_id, $subject_id]);
        $completed_count = $completed_lessons->fetchColumn();
        
        $overall_progress = $total_lessons > 0 ? ($completed_count / $total_lessons) * 100 : 0;
        
        $update_enrollment = $conn->prepare("
            UPDATE enrollments SET progress_percentage = ? WHERE user_id = ? AND subject_id = ?
        ");
        $update_enrollment->execute([$overall_progress, $user_id, $subject_id]);
        
        header("Location: course_content.php?id=$subject_id");
        exit();
    }
}

// حساب التقدم العام
$completed_lessons_count = 0;
foreach ($lessons as $lesson) {
    if ($lesson['is_completed']) {
        $completed_lessons_count++;
    }
}
$total_lessons = count($lessons);
$overall_progress = $total_lessons > 0 ? ($completed_lessons_count / $total_lessons) * 100 : 0;

// دالة لتحديد نوع الملف
function getFileIcon($file_path) {
    if (empty($file_path)) return 'fas fa-file';
    
    $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    
    switch ($extension) {
        case 'mp4':
        case 'avi':
        case 'mov':
        case 'wmv':
            return 'fas fa-play-circle';
        case 'pdf':
            return 'fas fa-file-pdf';
        case 'doc':
        case 'docx':
            return 'fas fa-file-word';
        case 'ppt':
        case 'pptx':
            return 'fas fa-file-powerpoint';
        default:
            return 'fas fa-file';
    }
}

// دالة لاستخراج معرف فيديو YouTube
function getYouTubeVideoId($url) {
    if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $url, $matches)) {
        return $matches[1];
    }
    return false;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($enrollment['subject_name']); ?> - منصة همّة التوجيهي</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
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

        .course-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }

        .progress-container {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
        }

        .progress-bar-custom {
            height: 10px;
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

        .lesson-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            margin-bottom: 1rem;
        }

        .lesson-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 25px rgba(0,0,0,0.15);
        }

        .lesson-card.completed {
            border-left: 4px solid var(--success-color);
            background: linear-gradient(135deg, rgba(79, 172, 254, 0.05), rgba(67, 233, 123, 0.05));
        }

        .lesson-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-left: 1rem;
        }

        .lesson-number.completed {
            background: linear-gradient(135deg, var(--success-color), var(--warning-color));
        }

        .lesson-content {
            flex: 1;
        }

        .lesson-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .lesson-meta {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }

        .lesson-description {
            color: #495057;
            line-height: 1.6;
        }

        .lesson-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .btn-lesson {
            border-radius: 20px;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .video-container {
            position: relative;
            width: 100%;
            height: 0;
            padding-bottom: 56.25%; /* 16:9 aspect ratio */
            margin: 1rem 0;
        }

        .video-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: 10px;
        }

        .file-preview {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 1rem;
            margin: 1rem 0;
            text-align: center;
        }

        .file-icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .lesson-modal .modal-content {
            border-radius: 15px;
            border: none;
        }

        .lesson-modal .modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 15px 15px 0 0;
        }

        .completed-badge {
            background: linear-gradient(135deg, var(--success-color), var(--warning-color));
            color: white;
            border-radius: 15px;
            padding: 0.25rem 0.75rem;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .free-badge {
            background: linear-gradient(135deg, var(--warning-color), #ffd700);
            color: white;
            border-radius: 15px;
            padding: 0.25rem 0.75rem;
            font-size: 0.8rem;
            font-weight: 500;
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
                <a class="nav-link" href="my_courses.php">
                    <i class="fas fa-arrow-right"></i> العودة للمساقات
                </a>
            </div>
            
            <div class="navbar-nav">
                <span class="navbar-text">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </span>
            </div>
        </div>
    </nav>

    <!-- Course Header -->
    <div class="course-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><?php echo htmlspecialchars($enrollment['subject_name']); ?></h1>
                    <p class="mb-2"><?php echo htmlspecialchars($enrollment['description']); ?></p>
                    <p class="mb-0">
                        <i class="fas fa-user-tie"></i> المعلم: <?php echo htmlspecialchars($enrollment['teacher_name']); ?>
                    </p>
                </div>
                <div class="col-md-4">
                    <div class="progress-container">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>التقدم العام</span>
                            <span class="fw-bold"><?php echo number_format($overall_progress, 1); ?>%</span>
                        </div>
                        <div class="progress-bar-custom">
                            <div class="progress-fill" style="width: <?php echo $overall_progress; ?>%"></div>
                        </div>
                        <div class="mt-2 small">
                            <?php echo $completed_lessons_count; ?> من <?php echo $total_lessons; ?> دروس مكتملة
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mb-5">
        <div class="row">
            <div class="col-12">
                <h3 class="mb-4">
                    <i class="fas fa-list"></i> دروس المساق
                </h3>

                <?php if (empty($lessons)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-book-open fa-4x text-muted mb-3"></i>
                        <h4 class="text-muted">لا توجد دروس متاحة حالياً</h4>
                        <p class="text-muted">سيتم إضافة الدروس قريباً من قبل المعلم</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($lessons as $index => $lesson): ?>
                    <div class="lesson-card card <?php echo $lesson['is_completed'] ? 'completed' : ''; ?>">
                        <div class="card-body">
                            <div class="d-flex align-items-start">
                                <div class="lesson-number <?php echo $lesson['is_completed'] ? 'completed' : ''; ?>">
                                    <?php if ($lesson['is_completed']): ?>
                                        <i class="fas fa-check"></i>
                                    <?php else: ?>
                                        <?php echo $index + 1; ?>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="lesson-content">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h5 class="lesson-title"><?php echo htmlspecialchars($lesson['title']); ?></h5>
                                        <div class="lesson-actions">
                                            <?php if ($lesson['is_completed']): ?>
                                                <span class="completed-badge">
                                                    <i class="fas fa-check"></i> مكتمل
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($lesson['is_free'])): ?>
                                                <span class="free-badge">
                                                    <i class="fas fa-gift"></i> مجاني
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="lesson-meta">
                                        <span>
                                            <i class="fas fa-clock"></i> 
                                            <?php echo $lesson['duration_minutes']; ?> دقيقة
                                        </span>
                                        <span class="mx-2">•</span>
                                        <span>
                                            <i class="<?php echo htmlspecialchars(getFileIcon($lesson['file_path'])); ?>"></i>
                                            <?php 
                                            $type_labels = [
                                                'video' => 'فيديو',
                                                'document' => 'مستند',
                                                'presentation' => 'عرض تقديمي',
                                                'quiz' => 'اختبار'
                                            ];
                                            $lesson_type = isset($lesson['lesson_type']) ? $lesson['lesson_type'] : '';
                                            echo isset($type_labels[$lesson_type]) ? $type_labels[$lesson_type] : 'درس';
                                            ?>
                                        </span>
                                    </div>
                                    
                                    <p class="lesson-description">
                                        <?php echo htmlspecialchars($lesson['description']); ?>
                                    </p>
                                    
                                    <div class="mt-3">
                                        <button class="btn btn-primary btn-lesson" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#lessonModal<?php echo $lesson['id']; ?>">
                                            <i class="fas fa-play"></i> مشاهدة الدرس
                                        </button>
                                        
                                        <?php if (!$lesson['is_completed']): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="lesson_id" value="<?php echo $lesson['id']; ?>">
                                                <button type="submit" name="mark_completed" class="btn btn-success btn-lesson">
                                                    <i class="fas fa-check"></i> تم الانتهاء
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Lesson Modal -->
                    <div class="modal fade lesson-modal" id="lessonModal<?php echo $lesson['id']; ?>" tabindex="-1">
                        <div class="modal-dialog modal-xl">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title"><?php echo htmlspecialchars($lesson['title']); ?></h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <?php if (!empty($lesson['video_url'])): ?>
                                        <?php $youtube_id = getYouTubeVideoId($lesson['video_url']); ?>
                                        <?php if ($youtube_id): ?>
                                            <!-- YouTube Video -->
                                            <div class="video-container">
                                                <iframe src="https://www.youtube.com/embed/<?php echo $youtube_id; ?>" 
                                                        frameborder="0" 
                                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                                        allowfullscreen>
                                                </iframe>
                                            </div>
                                        <?php else: ?>
                                            <!-- Direct Video File -->
                                            <?php
                                                $video_src = preg_match('/^https?:\/\//', $lesson['video_url']) ? $lesson['video_url'] : '../' . ltrim($lesson['video_url'], '/');
                                            ?>
                                            <video controls class="w-100" style="border-radius: 10px;">
                                                <source src="<?php echo htmlspecialchars($video_src); ?>" type="video/mp4">
                                                متصفحك لا يدعم تشغيل الفيديو.
                                            </video>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <?php 
                                        $file_fs_path = null;
                                        $file_href = null;
                                        if (!empty($lesson['file_path'])) {
                                            $file_fs_path = '../' . ltrim($lesson['file_path'], '/');
                                            $file_href = '../' . ltrim($lesson['file_path'], '/');
                                        }
                                    ?>
                                    <?php if (!empty($lesson['file_path']) && file_exists($file_fs_path)): ?>
                                        <div class="file-preview">
                                            <div class="file-icon">
                                                <i class="<?php echo htmlspecialchars(getFileIcon($lesson['file_path'])); ?>"></i>
                                            </div>
                                            <h6><?php echo basename($lesson['file_path']); ?></h6>
                                            <p class="text-muted">
                                                حجم الملف: <?php echo number_format(($lesson['file_size'] ?? 0) / 1024 / 1024, 2); ?> MB
                                            </p>
                                            <a href="<?php echo htmlspecialchars($file_href); ?>" 
                                               target="_blank" 
                                               class="btn btn-primary">
                                                <i class="fas fa-download"></i> تحميل الملف
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mt-3">
                                        <h6>وصف الدرس:</h6>
                                        <p><?php echo nl2br(htmlspecialchars($lesson['description'])); ?></p>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <?php if (!$lesson['is_completed']): ?>
                                        <form method="POST">
                                            <input type="hidden" name="lesson_id" value="<?php echo $lesson['id']; ?>">
                                            <button type="submit" name="mark_completed" class="btn btn-success">
                                                <i class="fas fa-check"></i> تم الانتهاء من الدرس
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="completed-badge">
                                            <i class="fas fa-check"></i> تم إكمال هذا الدرس
                                        </span>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغلاق</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // تسجيل وقت الوصول للدرس عند فتح المودال
        document.addEventListener('DOMContentLoaded', function() {
            const lessonModals = document.querySelectorAll('.lesson-modal');
            
            lessonModals.forEach(modal => {
                modal.addEventListener('shown.bs.modal', function() {
                    const lessonId = this.id.replace('lessonModal', '');
                    
                    // إرسال طلب AJAX لتسجيل الوصول
                    fetch('update_lesson_access.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'lesson_id=' + lessonId + '&subject_id=<?php echo $subject_id; ?>'
                    });
                });
            });
        });
    </script>
</body>
</html>