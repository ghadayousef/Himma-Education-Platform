<?php
/**
 * صفحة الدرس - منصة همّة التوجيهي
 * Lesson Page - Himma Tawjihi Platform
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$db = new Database();
$conn = $db->connect();

$lesson_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];

// جلب معلومات الدرس
try {
    $stmt = $conn->prepare("
        SELECT l.*, s.name as subject_name, s.category, s.price,
               e.id as enrollment_id, e.status as enrollment_status
        FROM lessons l
        LEFT JOIN subjects s ON l.subject_id = s.id
        LEFT JOIN enrollments e ON s.id = e.subject_id AND e.user_id = ?
        WHERE l.id = ?
    ");
    $stmt->execute([$user_id, $lesson_id]);
    $lesson = $stmt->fetch();
    
    if (!$lesson) {
        header('Location: subjects.php');
        exit;
    }
    
    // التحقق من التسجيل في المادة
    if (!$lesson['enrollment_id'] || $lesson['enrollment_status'] !== 'active') {
        header('Location: subjects.php?error=not_enrolled');
        exit;
    }
    
} catch (Exception $e) {
    header('Location: subjects.php?error=database');
    exit;
}

// جلب دروس المادة الأخرى
$other_lessons = $conn->prepare("
    SELECT id, title, order_num 
    FROM lessons 
    WHERE subject_id = ? AND id != ? 
    ORDER BY order_num
");
$other_lessons->execute([$lesson['subject_id'], $lesson_id]);
$other_lessons = $other_lessons->fetchAll();

// تسجيل مشاهدة الدرس
try {
    $stmt = $conn->prepare("
        INSERT INTO lesson_progress (user_id, lesson_id, started_at, last_accessed) 
        VALUES (?, ?, NOW(), NOW())
        ON DUPLICATE KEY UPDATE last_accessed = NOW(), view_count = view_count + 1
    ");
    $stmt->execute([$user_id, $lesson_id]);
} catch (Exception $e) {
    // تجاهل الأخطاء في تسجيل التقدم
}

$page_title = $lesson['title'];
include '../includes/student_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <nav class="col-md-3 col-lg-2 d-md-block sidebar">
            <div class="position-sticky pt-3">
                <div class="mb-3">
                    <h6 class="text-white"><?php echo htmlspecialchars($lesson['subject_name']); ?></h6>
                    <span class="badge bg-<?php echo $lesson['category'] === 'scientific' ? 'info' : ($lesson['category'] === 'literary' ? 'warning' : 'success'); ?>">
                        <?php echo $lesson['category'] === 'scientific' ? 'علمي' : ($lesson['category'] === 'literary' ? 'أدبي' : 'لغات'); ?>
                    </span>
                </div>
                
                <hr class="text-white">
                
                <h6 class="text-white mb-3">دروس المادة</h6>
                <ul class="nav flex-column">
                    <?php foreach ($other_lessons as $other_lesson): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $other_lesson['id'] == $lesson_id ? 'active' : ''; ?>" 
                           href="lesson.php?id=<?php echo $other_lesson['id']; ?>">
                            <i class="fas fa-play-circle"></i> 
                            <?php echo $other_lesson['order_num']; ?>. <?php echo htmlspecialchars($other_lesson['title']); ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                
                <hr class="text-white">
                
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="subjects.php">
                            <i class="fas fa-arrow-right"></i> العودة للمواد
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> لوحة التحكم
                        </a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h2"><?php echo htmlspecialchars($lesson['title']); ?></h1>
                    <p class="text-muted">الدرس رقم <?php echo $lesson['order_num']; ?> - <?php echo htmlspecialchars($lesson['subject_name']); ?></p>
                </div>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-outline-primary me-2" onclick="markAsCompleted()">
                        <i class="fas fa-check"></i> تم الانتهاء
                    </button>
                    <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
                        <i class="fas fa-print"></i> طباعة
                    </button>
                </div>
            </div>

            <!-- Lesson Description -->
            <?php if ($lesson['description']): ?>
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">وصف الدرس</h5>
                    <p class="card-text"><?php echo nl2br(htmlspecialchars($lesson['description'])); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Video Section -->
            <?php if ($lesson['video_url']): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-play-circle text-primary"></i> فيديو الدرس</h5>
                </div>
                <div class="card-body">
                    <div class="ratio ratio-16x9">
                        <?php
                        $video_url = $lesson['video_url'];
                        
                        // تحويل روابط YouTube إلى embed
                        if (strpos($video_url, 'youtube.com/watch?v=') !== false) {
                            $video_id = explode('v=', $video_url)[1];
                            $video_id = explode('&', $video_id)[0];
                            $video_url = "https://www.youtube.com/embed/{$video_id}";
                        } elseif (strpos($video_url, 'youtu.be/') !== false) {
                            $video_id = explode('youtu.be/', $video_url)[1];
                            $video_id = explode('?', $video_id)[0];
                            $video_url = "https://www.youtube.com/embed/{$video_id}";
                        }
                        // تحويل روابط Vimeo إلى embed
                        elseif (strpos($video_url, 'vimeo.com/') !== false) {
                            $video_id = explode('vimeo.com/', $video_url)[1];
                            $video_id = explode('?', $video_id)[0];
                            $video_url = "https://player.vimeo.com/video/{$video_id}";
                        }
                        ?>
                        <iframe src="<?php echo $video_url; ?>" 
                                allowfullscreen 
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture">
                        </iframe>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Lesson Content -->
            <?php if ($lesson['content']): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-file-text text-success"></i> محتوى الدرس</h5>
                </div>
                <div class="card-body">
                    <div class="lesson-content">
                        <?php echo nl2br(htmlspecialchars($lesson['content'])); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Materials Section -->
            <?php 
            $materials = json_decode($lesson['materials'], true);
            if ($materials && count($materials) > 0): 
            ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-download text-warning"></i> المواد المرفقة</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($materials as $material): ?>
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card border-0 bg-light">
                                <div class="card-body text-center">
                                    <i class="fas fa-file-pdf fa-3x text-danger mb-2"></i>
                                    <h6 class="card-title"><?php echo htmlspecialchars($material); ?></h6>
                                    <a href="../uploads/lessons/<?php echo $material; ?>" 
                                       class="btn btn-sm btn-outline-primary" 
                                       download>
                                        <i class="fas fa-download"></i> تحميل
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Navigation -->
            <div class="row">
                <div class="col-md-6">
                    <?php
                    // الدرس السابق
                    $prev_lesson = $conn->prepare("
                        SELECT id, title 
                        FROM lessons 
                        WHERE subject_id = ? AND order_num < ? 
                        ORDER BY order_num DESC 
                        LIMIT 1
                    ");
                    $prev_lesson->execute([$lesson['subject_id'], $lesson['order_num']]);
                    $prev_lesson = $prev_lesson->fetch();
                    
                    if ($prev_lesson):
                    ?>
                    <a href="lesson.php?id=<?php echo $prev_lesson['id']; ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-right"></i> الدرس السابق: <?php echo htmlspecialchars($prev_lesson['title']); ?>
                    </a>
                    <?php endif; ?>
                </div>
                <div class="col-md-6 text-end">
                    <?php
                    // الدرس التالي
                    $next_lesson = $conn->prepare("
                        SELECT id, title 
                        FROM lessons 
                        WHERE subject_id = ? AND order_num > ? 
                        ORDER BY order_num ASC 
                        LIMIT 1
                    ");
                    $next_lesson->execute([$lesson['subject_id'], $lesson['order_num']]);
                    $next_lesson = $next_lesson->fetch();
                    
                    if ($next_lesson):
                    ?>
                    <a href="lesson.php?id=<?php echo $next_lesson['id']; ?>" class="btn btn-primary">
                        الدرس التالي: <?php echo htmlspecialchars($next_lesson['title']); ?> <i class="fas fa-arrow-left"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<style>
.sidebar {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
}

.sidebar .nav-link {
    color: rgba(255, 255, 255, 0.8);
    padding: 0.75rem 1rem;
    border-radius: 10px;
    margin: 0.25rem;
    transition: all 0.3s ease;
    font-size: 0.9rem;
}

.sidebar .nav-link:hover,
.sidebar .nav-link.active {
    color: white;
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
}

.card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
}

.lesson-content {
    font-size: 1.1rem;
    line-height: 1.8;
}

.ratio {
    border-radius: 10px;
    overflow: hidden;
}

@media print {
    .sidebar, .btn-toolbar, .card-header {
        display: none !important;
    }
    
    .col-md-9 {
        width: 100% !important;
        max-width: 100% !important;
    }
}
</style>

<script>
function markAsCompleted() {
    if (confirm('هل تريد تسجيل هذا الدرس كمكتمل؟')) {
        fetch('../api/mark_lesson_completed.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                lesson_id: <?php echo $lesson_id; ?>
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('تم تسجيل الدرس كمكتمل بنجاح!');
                // تحديث الصفحة أو إظهار علامة الإكمال
                location.reload();
            } else {
                alert('حدث خطأ في تسجيل إكمال الدرس');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('حدث خطأ في الاتصال');
        });
    }
}

// تسجيل وقت المشاهدة كل دقيقة
setInterval(function() {
    fetch('../api/update_lesson_progress.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            lesson_id: <?php echo $lesson_id; ?>
        })
    });
}, 60000); // كل دقيقة
</script>

<?php include '../includes/student_footer.php'; ?>