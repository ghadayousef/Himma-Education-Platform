<?php
/**
 * معاينة الدرس - منصة همّة التوجيهي
 * Lesson Preview - Himma Tawjihi Platform
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

$lesson = null;
$error = '';

// جلب بيانات الدرس
if (isset($_GET['id'])) {
    $lesson_id = intval($_GET['id']);
    
    try {
        // التحقق من ملكية الدرس للمعلم
        $lesson_stmt = $conn->prepare("
            SELECT l.*, s.name as subject_name, s.teacher_id
            FROM lessons l
            JOIN subjects s ON l.subject_id = s.id
            WHERE l.id = ? AND s.teacher_id = ?
        ");
        $lesson_stmt->execute([$lesson_id, $user_id]);
        $lesson = $lesson_stmt->fetch();
        
        if (!$lesson) {
            $error = 'الدرس غير موجود أو غير مصرح لك بمعاينته';
        }
    } catch (PDOException $e) {
        $error = 'خطأ في النظام: ' . $e->getMessage();
    }
} else {
    $error = 'معرف الدرس مطلوب';
}

// جلب بيانات المعلم
$teacher_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$teacher_stmt->execute([$user_id]);
$teacher = $teacher_stmt->fetch();

// دالة لتحديد نوع الملف
function getFileType($filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    $video_extensions = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv', '3gp'];
    $image_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];
    $document_extensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt'];
    
    if (in_array($extension, $video_extensions)) {
        return 'video';
    } elseif (in_array($extension, $image_extensions)) {
        return 'image';
    } elseif (in_array($extension, $document_extensions)) {
        return 'document';
    } else {
        return 'unknown';
    }
}

// دالة لاستخراج معرف فيديو YouTube
function getYouTubeVideoId($url) {
    preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $url, $matches);
    return isset($matches[1]) ? $matches[1] : null;
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>معاينة الدرس - منصة همّة التوجيهي</title>
    
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

        .header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #fff, #f093fb);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .lesson-content {
            padding: 2rem;
        }

        .lesson-header {
            margin-bottom: 2rem;
            text-align: center;
        }

        .lesson-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--accent-color);
            margin-bottom: 1rem;
        }

        .lesson-meta {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .video-container {
            margin: 2rem 0;
            text-align: center;
        }

        .video-player {
            width: 100%;
            max-width: 800px;
            height: 450px;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .video-player iframe,
        .video-player video {
            width: 100%;
            height: 100%;
            border: none;
        }

        .document-viewer {
            width: 100%;
            height: 600px;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            background: white;
        }

        .document-viewer iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        .image-viewer {
            text-align: center;
            margin: 2rem 0;
        }

        .image-viewer img {
            max-width: 100%;
            height: auto;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .lesson-description {
            margin: 2rem 0;
            line-height: 1.8;
            font-size: 1.1rem;
        }

        .lesson-content-text {
            margin: 2rem 0;
            line-height: 1.8;
            font-size: 1rem;
            background: rgba(255, 255, 255, 0.05);
            padding: 1.5rem;
            border-radius: 15px;
        }

        .download-section {
            margin: 2rem 0;
            text-align: center;
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

        .btn-info {
            background: linear-gradient(135deg, #17a2b8, #138496);
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

        .alert-danger {
            background: rgba(250, 112, 154, 0.2);
            color: var(--danger-color);
            border: 1px solid rgba(250, 112, 154, 0.3);
        }

        .no-content {
            text-align: center;
            padding: 3rem;
            opacity: 0.7;
        }

        .no-content i {
            font-size: 4rem;
            margin-bottom: 1rem;
            display: block;
        }

        .loading {
            text-align: center;
            padding: 2rem;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid rgba(255,255,255,0.3);
            border-top: 4px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .lesson-meta {
                flex-direction: column;
                gap: 1rem;
            }

            .video-player {
                height: 250px;
            }

            .document-viewer {
                height: 400px;
            }

            .lesson-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>معاينة الدرس</h1>
            <p>مراجعة محتوى الدرس والمواد التعليمية</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
            <div style="text-align: center; margin-top: 2rem;">
                <a href="lesson_management.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-right"></i> العودة لإدارة الدروس
                </a>
            </div>
        <?php elseif ($lesson): ?>
            <div class="lesson-content glass">
                <div class="lesson-header">
                    <h2 class="lesson-title"><?php echo htmlspecialchars($lesson['title']); ?></h2>
                    
                    <div class="lesson-meta">
                        <div class="meta-item">
                            <i class="fas fa-book"></i>
                            <span><?php echo htmlspecialchars($lesson['subject_name']); ?></span>
                        </div>
                        
                        <?php if ($lesson['duration_minutes']): ?>
                        <div class="meta-item">
                            <i class="fas fa-clock"></i>
                            <span><?php echo $lesson['duration_minutes']; ?> دقيقة</span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="meta-item">
                            <i class="fas fa-sort-numeric-up"></i>
                            <span>ترتيب: <?php echo $lesson['order_num']; ?></span>
                        </div>
                        
                        <?php if ($lesson['is_free']): ?>
                        <div class="meta-item">
                            <i class="fas fa-gift"></i>
                            <span style="color: var(--warning-color);">درس مجاني</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="lesson-description">
                    <h4 style="color: var(--accent-color); margin-bottom: 1rem;">
                        <i class="fas fa-info-circle"></i> وصف الدرس
                    </h4>
                    <p><?php echo nl2br(htmlspecialchars($lesson['description'])); ?></p>
                </div>

                <?php if (!empty($lesson['video_url'])): ?>
                    <div class="video-container">
                        <h4 style="color: var(--accent-color); margin-bottom: 1rem;">
                            <i class="fas fa-play-circle"></i> محتوى الدرس
                        </h4>
                        
                        <?php 
                        $video_type = $lesson['video_type'] ?? 'none';
                        
                        if ($video_type === 'youtube'): 
                            $youtube_id = getYouTubeVideoId($lesson['video_url']);
                            if ($youtube_id):
                        ?>
                            <div class="video-player">
                                <iframe src="https://www.youtube.com/embed/<?php echo $youtube_id; ?>?rel=0&showinfo=0" 
                                        allowfullscreen></iframe>
                            </div>
                        <?php 
                            else:
                        ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> رابط YouTube غير صحيح
                            </div>
                        <?php 
                            endif;
                        elseif ($video_type === 'upload' && file_exists('../' . $lesson['video_url'])): 
                            $file_type = getFileType($lesson['video_url']);
                            
                            if ($file_type === 'video'):
                        ?>
                            <div class="video-player">
                                <video controls>
                                    <source src="../<?php echo htmlspecialchars($lesson['video_url']); ?>" type="video/mp4">
                                    متصفحك لا يدعم تشغيل الفيديو
                                </video>
                            </div>
                        <?php 
                            elseif ($file_type === 'document'):
                                $file_extension = strtolower(pathinfo($lesson['video_url'], PATHINFO_EXTENSION));
                                if ($file_extension === 'pdf'):
                        ?>
                            <div class="document-viewer">
                                <iframe src="../<?php echo htmlspecialchars($lesson['video_url']); ?>#toolbar=1&navpanes=1&scrollbar=1"></iframe>
                            </div>
                            <div class="download-section">
                                <a href="../<?php echo htmlspecialchars($lesson['video_url']); ?>" 
                                   class="btn btn-info" target="_blank" download>
                                    <i class="fas fa-download"></i> تحميل المستند
                                </a>
                            </div>
                        <?php 
                                else:
                                    $google_docs_url = 'https://docs.google.com/viewer?url=' . urlencode('http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/../' . $lesson['video_url']) . '&embedded=true';
                        ?>
                            <div class="document-viewer">
                                <iframe src="<?php echo $google_docs_url; ?>"></iframe>
                            </div>
                            <div class="download-section">
                                <a href="../<?php echo htmlspecialchars($lesson['video_url']); ?>" 
                                   class="btn btn-info" target="_blank" download>
                                    <i class="fas fa-download"></i> تحميل المستند
                                </a>
                            </div>
                        <?php 
                                endif;
                            elseif ($file_type === 'image'):
                        ?>
                            <div class="image-viewer">
                                <img src="../<?php echo htmlspecialchars($lesson['video_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($lesson['title']); ?>">
                            </div>
                        <?php 
                            else:
                        ?>
                            <div class="download-section">
                                <p>ملف مرفق متاح للتحميل:</p>
                                <a href="../<?php echo htmlspecialchars($lesson['video_url']); ?>" 
                                   class="btn btn-info" target="_blank" download>
                                    <i class="fas fa-download"></i> تحميل الملف
                                </a>
                            </div>
                        <?php 
                            endif;
                        else:
                        ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> الملف غير موجود أو تالف
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($lesson['content'])): ?>
                    <div class="lesson-content-text">
                        <h4 style="color: var(--accent-color); margin-bottom: 1rem;">
                            <i class="fas fa-file-text"></i> محتوى تفصيلي
                        </h4>
                        <div><?php echo nl2br(htmlspecialchars($lesson['content'])); ?></div>
                    </div>
                <?php endif; ?>

                <?php if (empty($lesson['video_url']) && empty($lesson['content'])): ?>
                    <div class="no-content">
                        <i class="fas fa-file-slash"></i>
                        <h4>لا يوجد محتوى إضافي لهذا الدرس</h4>
                        <p>يحتوي الدرس على الوصف فقط</p>
                    </div>
                <?php endif; ?>
            </div>

            <div style="text-align: center; margin-top: 2rem; display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <a href="lesson_management.php?subject_id=<?php echo $lesson['subject_id']; ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-right"></i> العودة لإدارة الدروس
                </a>
                <a href="lesson_management.php?edit=<?php echo $lesson['id']; ?>&subject_id=<?php echo $lesson['subject_id']; ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> تعديل الدرس
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // إضافة مؤشر تحميل للمستندات
        document.addEventListener('DOMContentLoaded', function() {
            const documentViewer = document.querySelector('.document-viewer iframe');
            if (documentViewer) {
                const loadingDiv = document.createElement('div');
                loadingDiv.className = 'loading';
                loadingDiv.innerHTML = '<div class="spinner"></div><p>جاري تحميل المستند...</p>';
                
                documentViewer.parentNode.insertBefore(loadingDiv, documentViewer);
                documentViewer.style.display = 'none';
                
                documentViewer.onload = function() {
                    loadingDiv.style.display = 'none';
                    documentViewer.style.display = 'block';
                };
                
                // إخفاء مؤشر التحميل بعد 10 ثوان في حالة عدم التحميل
                setTimeout(function() {
                    if (loadingDiv.style.display !== 'none') {
                        loadingDiv.innerHTML = '<p style="color: var(--warning-color);">تعذر تحميل المستند. يرجى استخدام زر التحميل أدناه.</p>';
                        documentViewer.style.display = 'block';
                    }
                }, 10000);
            }
        });

        // تحسين تشغيل الفيديو
        document.addEventListener('DOMContentLoaded', function() {
            const videos = document.querySelectorAll('video');
            videos.forEach(video => {
                video.addEventListener('error', function() {
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'alert alert-danger';
                    errorDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> تعذر تشغيل الفيديو. تأكد من صحة الملف.';
                    video.parentNode.replaceChild(errorDiv, video);
                });
            });
        });
    </script>
</body>
</html>