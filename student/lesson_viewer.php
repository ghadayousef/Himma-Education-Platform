<?php
/**
 * صفحة معاينة الدروس للطلاب - منصة همّة التعليمية
 * Student Lesson Viewer - Himma Educational Platform
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../home/index.php');
    exit();
}

$success_message = '';
$error_message = '';
$lesson = null;
$subject = null;
$teacher = null;
$lesson_rating = null;
$user_rating = null;

// Get lesson ID from URL
$lesson_id = intval($_GET['id'] ?? 0);

if ($lesson_id <= 0) {
    header('Location: ../student/dashboard.php');
    exit();
}

try {
    $db = new Database();
    $conn = $db->connect();
    
    // Get lesson details with subject and teacher info
    $lesson_stmt = $conn->prepare("
        SELECT l.*, s.name as subject_name, s.description as subject_description,
               u.name as teacher_name, u.email as teacher_email,
               AVG(lr.rating) as avg_rating, COUNT(lr.rating) as rating_count
        FROM lessons l
        JOIN subjects s ON l.subject_id = s.id
        JOIN users u ON s.teacher_id = u.id
        LEFT JOIN lesson_ratings lr ON l.id = lr.lesson_id
        WHERE l.id = ?
        GROUP BY l.id
    ");
    $lesson_stmt->execute([$lesson_id]);
    $lesson = $lesson_stmt->fetch();
    
    if (!$lesson) {
        $error_message = 'الدرس غير موجود';
    } else {
        // Check if lesson is free or if student has access
        $has_access = false;
        if ($lesson['is_free']) {
            $has_access = true;
        } else {
            // Check enrollment using user_id (not student_id)
            $enrollment_stmt = $conn->prepare("
                SELECT id FROM enrollments 
                WHERE user_id = ? AND subject_id = ? AND status = 'active'
            ");
            $enrollment_stmt->execute([$_SESSION['user_id'], $lesson['subject_id']]);
            $has_access = $enrollment_stmt->fetch() !== false;
        }
        
        if ($has_access) {
            // Record lesson view (table may not exist; ignore errors)
            try {
                $view_stmt = $conn->prepare("
                    INSERT INTO lesson_views (lesson_id, student_id, viewed_at) 
                    VALUES (?, ?, NOW())
                    ON DUPLICATE KEY UPDATE viewed_at = NOW(), view_count = view_count + 1
                ");
                $view_stmt->execute([$lesson_id, $_SESSION['user_id']]);
            } catch (Exception $e) {
                // Ignore tracking errors silently
            }
            
            // Get user's rating for this lesson
            try {
                $rating_stmt = $conn->prepare("
                    SELECT rating, review FROM lesson_ratings 
                    WHERE lesson_id = ? AND student_id = ?
                ");
                $rating_stmt->execute([$lesson_id, $_SESSION['user_id']]);
                $user_rating = $rating_stmt->fetch();
            } catch (Exception $e) {
                // Ignore rating errors
            }
        }
    }
    
} catch (Exception $e) {
    $error_message = 'حدث خطأ في تحميل الدرس: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $lesson ? htmlspecialchars($lesson['title']) : 'معاينة الدرس'; ?> - منصة همّة التعليمية</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --accent-color: #f093fb;
            --success-color: #4facfe;
            --warning-color: #43e97b;
            --danger-color: #fa709a;
            --dark-color: #2c3e50;
            --light-color: #ecf0f1;
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
        .glass { background: var(--glass-bg); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border-radius: 20px; border: 1px solid var(--glass-border); box-shadow: var(--shadow); }
        .container { max-width: 1400px; margin: 0 auto; padding: 2rem; }
        .lesson-header { text-align: center; margin-bottom: 3rem; padding: 2rem; }
        .lesson-title { font-size: 2.5rem; font-weight: 800; margin-bottom: 1rem; background: linear-gradient(135deg, #fff, #f093fb); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .lesson-meta { display: flex; justify-content: center; gap: 2rem; margin-top: 1rem; flex-wrap: wrap; }
        .meta-item { display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; background: rgba(255, 255, 255, 0.1); border-radius: 25px; }
        .main-content { display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-bottom: 3rem; }
        .video-section { padding: 2rem; }
        .video-container { position: relative; width: 100%; height: 0; padding-bottom: 56.25%; background: #000; border-radius: 15px; overflow: hidden; margin-bottom: 2rem; }
        .video-container iframe, .video-container video { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }
        .video-placeholder { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; color: white; }
        .video-placeholder i { font-size: 4rem; margin-bottom: 1rem; display: block; }
        .lesson-description { background: rgba(255, 255, 255, 0.05); padding: 2rem; border-radius: 15px; margin-bottom: 2rem; }
        .lesson-description h3 { color: var(--accent-color); margin-bottom: 1rem; }
        .lesson-description p { line-height: 1.8; opacity: 0.9; }
        .sidebar { display: flex; flex-direction: column; gap: 2rem; }
        .stats-card { padding: 2rem; }
        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 2rem; }
        .stat-item { text-align: center; padding: 1rem; background: rgba(255, 255, 255, 0.05); border-radius: 10px; }
        .stat-number { font-size: 2rem; font-weight: 700; color: var(--accent-color); }
        .stat-label { font-size: 0.9rem; opacity: 0.8; margin-top: 0.5rem; }
        .rating-section { padding: 2rem; }
        .rating-display { text-align: center; margin-bottom: 2rem; }
        .rating-stars { font-size: 2rem; margin: 1rem 0; }
        .star { color: #ddd; cursor: pointer; transition: color 0.2s; }
        .star.filled { color: #ffd700; }
        .star:hover { color: #ffd700; }
        .rating-form { margin-top: 2rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-label { display: block; margin-bottom: 0.5rem; font-weight: 600; }
        .form-textarea { width: 100%; padding: 1rem; border: none; border-radius: 10px; background: rgba(255, 255, 255, 0.1); color: white; font-family: 'Cairo', sans-serif; min-height: 100px; resize: vertical; }
        .form-textarea::placeholder { color: rgba(255, 255, 255, 0.7); }
        .btn { padding: 1rem 2rem; border: none; border-radius: 15px; font-weight: 600; cursor: pointer; transition: all 0.3s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; font-family: 'Cairo', sans-serif; }
        .btn-primary { background: linear-gradient(135deg, var(--success-color), var(--warning-color)); color: white; }
        .btn-secondary { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); color: white; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
        .related-lessons { padding: 2rem; }
        .lesson-item { display: flex; align-items: center; gap: 1rem; padding: 1rem; background: rgba(255, 255, 255, 0.05); border-radius: 10px; margin-bottom: 1rem; transition: all 0.3s ease; text-decoration: none; color: white; }
        .lesson-item:hover { background: rgba(255, 255, 255, 0.1); transform: translateX(-5px); }
        .lesson-icon { font-size: 1.5rem; color: var(--accent-color); width: 40px; text-align: center; }
        .lesson-info { flex: 1; }
        .lesson-name { font-weight: 600; margin-bottom: 0.5rem; }
        .lesson-duration { font-size: 0.9rem; opacity: 0.7; }
        .alert { padding: 1rem 1.5rem; border-radius: 10px; margin-bottom: 1.5rem; font-weight: 500; }
        .alert-success { background: rgba(79, 172, 254, 0.2); color: var(--success-color); border: 1px solid rgba(79, 172, 254, 0.3); }
        .alert-danger { background: rgba(250, 112, 154, 0.2); color: var(--danger-color); border: 1px solid rgba(250, 112, 154, 0.3); }
        .alert-warning { background: rgba(255, 193, 7, 0.2); color: #ffc107; border: 1px solid rgba(255, 193, 7, 0.3); }
        .access-denied { text-align: center; padding: 3rem; }
        .access-denied i { font-size: 4rem; color: var(--warning-color); margin-bottom: 1rem; }
        @media (max-width: 768px) {
            .main-content { grid-template-columns: 1fr; }
            .lesson-meta { flex-direction: column; align-items: center; }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($lesson && isset($has_access) && $has_access): ?>
            <!-- Lesson Header -->
            <div class="lesson-header glass">
                <h1 class="lesson-title"><?php echo htmlspecialchars($lesson['title']); ?></h1>
                <p><?php echo htmlspecialchars($lesson['subject_name']); ?></p>
                <div class="lesson-meta">
                    <div class="meta-item">
                        <i class="fas fa-user"></i>
                        <span><?php echo htmlspecialchars($lesson['teacher_name']); ?></span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-clock"></i>
                        <span><?php echo $lesson['duration_minutes']; ?> دقيقة</span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-star"></i>
                        <span><?php echo number_format($lesson['avg_rating'] ?? 0, 1); ?> (<?php echo $lesson['rating_count']; ?> تقييم)</span>
                    </div>
                    <?php if ($lesson['is_free']): ?>
                        <div class="meta-item" style="background: rgba(67, 233, 123, 0.2);">
                            <i class="fas fa-gift"></i>
                            <span>مجاني</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="main-content">
                <!-- Video Section -->
                <div class="video-section glass">
                    <div class="video-container">
                        <?php if (!empty($lesson['video_url'])): ?>
                            <?php if (strpos($lesson['video_url'], 'youtube.com') !== false || strpos($lesson['video_url'], 'youtu.be') !== false): ?>
                                <?php
                                // Extract YouTube video ID
                                preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $lesson['video_url'], $matches);
                                $video_id = $matches[1] ?? '';
                                ?>
                                <?php if ($video_id): ?>
                                    <iframe src="https://www.youtube.com/embed/<?php echo $video_id; ?>?rel=0&modestbranding=1" 
                                            frameborder="0" allowfullscreen></iframe>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php
                                $video_src = preg_match('/^https?:\/\//', $lesson['video_url']) ? $lesson['video_url'] : '../' . ltrim($lesson['video_url'], '/');
                                ?>
                                <video controls>
                                    <source src="<?php echo htmlspecialchars($video_src); ?>" type="video/mp4">
                                    متصفحك لا يدعم تشغيل الفيديو
                                </video>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="video-placeholder">
                                <i class="fas fa-play-circle"></i>
                                <p>لا يوجد فيديو متاح لهذا الدرس</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Lesson Description -->
                    <div class="lesson-description">
                        <h3><i class="fas fa-info-circle"></i> وصف الدرس</h3>
                        <p><?php echo nl2br(htmlspecialchars($lesson['description'])); ?></p>
                    </div>

                    <!-- Download Section -->
                    <?php
                    $file_fs_path = null;
                    if (!empty($lesson['file_path'])) {
                        $file_fs_path = '../' . ltrim($lesson['file_path'], '/');
                    }
                    ?>
                    <?php if (!empty($lesson['file_path']) && file_exists($file_fs_path)): ?>
                        <div class="lesson-description">
                            <h3><i class="fas fa-download"></i> ملفات الدرس</h3>
                            <p>يمكنك تحميل ملف الدرس للمراجعة لاحقاً</p>
                            <a href="<?php echo htmlspecialchars('../' . ltrim($lesson['file_path'], '/')); ?>" 
                               class="btn btn-secondary" download>
                                <i class="fas fa-download"></i>
                                تحميل الملف (<?php echo number_format(($lesson['file_size'] ?? 0) / 1024 / 1024, 2); ?> MB)
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Sidebar -->
                <div class="sidebar">
                    <!-- Statistics -->
                    <div class="stats-card glass">
                        <h3 style="color: var(--accent-color); margin-bottom: 1.5rem;">
                            <i class="fas fa-chart-bar"></i> إحصائيات الدرس
                        </h3>
                        <div class="stats-grid">
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $lesson_stats['views']['total_views'] ?? 0; ?></div>
                                <div class="stat-label">إجمالي المشاهدات</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $lesson_stats['views']['unique_viewers'] ?? 0; ?></div>
                                <div class="stat-label">المشاهدون الفريدون</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $lesson_stats['completion_rate'] ?? 0; ?>%</div>
                                <div class="stat-label">معدل الإكمال</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $lesson_stats['avg_watch_time'] ?? 0; ?></div>
                                <div class="stat-label">متوسط وقت المشاهدة (دقيقة)</div>
                            </div>
                        </div>
                    </div>

                    <!-- Rating Section -->
                    <div class="rating-section glass">
                        <h3 style="color: var(--accent-color); margin-bottom: 1.5rem;">
                            <i class="fas fa-star"></i> تقييم الدرس
                        </h3>
                        
                        <div class="rating-display">
                            <div class="stat-number"><?php echo number_format($lesson['avg_rating'] ?? 0, 1); ?></div>
                            <div class="rating-stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?php echo ($i <= ($lesson['avg_rating'] ?? 0)) ? 'filled' : ''; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <div class="stat-label"><?php echo $lesson['rating_count']; ?> تقييم</div>
                        </div>

                        <!-- User Rating Form -->
                        <form method="POST" class="rating-form">
                            <input type="hidden" name="action" value="rate_lesson">
                            
                            <div class="form-group">
                                <label class="form-label">تقييمك للدرس:</label>
                                <div class="rating-stars" id="userRating">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star star" data-rating="<?php echo $i; ?>" 
                                           <?php echo ($user_rating && $i <= $user_rating['rating']) ? 'style="color: #ffd700;"' : ''; ?>></i>
                                    <?php endfor; ?>
                                </div>
                                <input type="hidden" name="rating" id="ratingValue" 
                                       value="<?php echo $user_rating['rating'] ?? 0; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">مراجعتك (اختياري):</label>
                                <textarea name="review" class="form-textarea" 
                                          placeholder="شاركنا رأيك في الدرس..."><?php echo $user_rating['review'] ?? ''; ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary" style="width: 100%;">
                                <i class="fas fa-save"></i>
                                <?php echo $user_rating ? 'تحديث التقييم' : 'إرسال التقييم'; ?>
                            </button>
                        </form>
                    </div>

                    <!-- Related Lessons -->
                    <?php if (!empty($related_lessons)): ?>
                        <div class="related-lessons glass">
                            <h3 style="color: var(--accent-color); margin-bottom: 1.5rem;">
                                <i class="fas fa-list"></i> دروس أخرى في المادة
                            </h3>
                            <?php foreach ($related_lessons as $related): ?>
                                <a href="lesson_viewer.php?id=<?php echo $related['id']; ?>" class="lesson-item">
                                    <div class="lesson-icon">
                                        <i class="fas fa-<?php echo $related['lesson_type'] === 'video' ? 'play-circle' : 'file-alt'; ?>"></i>
                                    </div>
                                    <div class="lesson-info">
                                        <div class="lesson-name"><?php echo htmlspecialchars($related['title']); ?></div>
                                        <div class="lesson-duration">
                                            <?php echo $related['duration_minutes']; ?> دقيقة
                                            <?php if ($related['is_free']): ?>
                                                <span style="color: var(--warning-color);"> • مجاني</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($lesson && isset($has_access) && !$has_access): ?>
            <!-- Access Denied -->
            <div class="access-denied glass">
                <i class="fas fa-lock"></i>
                <h2>هذا الدرس غير مجاني</h2>
                <p>تحتاج للاشتراك في المادة للوصول إلى هذا الدرس</p>
                <div style="margin-top: 2rem;">
                    <a href="../student/subjects.php" class="btn btn-primary">
                        <i class="fas fa-shopping-cart"></i>
                        تصفح المواد المتاحة
                    </a>
                </div>
            </div>

        <?php else: ?>
            <!-- Lesson Not Found -->
            <div class="access-denied glass">
                <i class="fas fa-exclamation-triangle"></i>
                <h2>الدرس غير موجود</h2>
                <p>لم نتمكن من العثور على الدرس المطلوب</p>
                <div style="margin-top: 2rem;">
                    <a href="../student/dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-right"></i>
                        العودة للوحة التحكم
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Navigation -->
        <div style="text-align: center; margin-top: 2rem;">
            <a href="../student/dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-right"></i> العودة للوحة التحكم
            </a>
        </div>
    </div>

    <script>
        // Rating system
        document.addEventListener('DOMContentLoaded', function() {
            const stars = document.querySelectorAll('#userRating .star');
            const ratingValue = document.getElementById('ratingValue');
            
            stars.forEach(star => {
                star.addEventListener('click', function() {
                    const rating = parseInt(this.getAttribute('data-rating'));
                    ratingValue.value = rating;
                    
                    // Update visual stars
                    stars.forEach((s, index) => {
                        if (index < rating) {
                            s.style.color = '#ffd700';
                        } else {
                            s.style.color = '#ddd';
                        }
                    });
                });
                
                star.addEventListener('mouseover', function() {
                    const rating = parseInt(this.getAttribute('data-rating'));
                    
                    stars.forEach((s, index) => {
                        if (index < rating) {
                            s.style.color = '#ffd700';
                        } else {
                            s.style.color = '#ddd';
                        }
                    });
                });
            });
            
            // Reset stars on mouse leave
            document.getElementById('userRating').addEventListener('mouseleave', function() {
                const currentRating = parseInt(ratingValue.value);
                
                stars.forEach((s, index) => {
                    if (index < currentRating) {
                        s.style.color = '#ffd700';
                    } else {
                        s.style.color = '#ddd';
                    }
                });
            });
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 300);
            });
        }, 5000);
    </script>
</body>
</html>