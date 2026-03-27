<?php
/**
 * نظام التعليقات والنقاشات للدروس - منصة همّة التعليمية
 * Lesson Discussions System - Himma Educational Platform
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../home/index.php');
    exit();
}

$lesson_id = intval($_GET['lesson_id'] ?? 0);
$success_message = '';
$error_message = '';

if ($lesson_id <= 0) {
    header('Location: ../student/dashboard.php');
    exit();
}

try {
    $db = new Database();
    $conn = $db->connect();
    
    // Get lesson details
    $lesson_stmt = $conn->prepare("
        SELECT l.*, s.name as subject_name, u.name as teacher_name
        FROM lessons l
        JOIN subjects s ON l.subject_id = s.id
        JOIN users u ON s.teacher_id = u.id
        WHERE l.id = ?
    ");
    $lesson_stmt->execute([$lesson_id]);
    $lesson = $lesson_stmt->fetch();
    
    if (!$lesson) {
        header('Location: ../student/dashboard.php');
        exit();
    }
    
    // Handle comment submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] === 'add_comment') {
            $comment_text = trim($_POST['comment_text'] ?? '');
            $parent_id = intval($_POST['parent_id'] ?? 0) ?: null;
            
            if (!empty($comment_text)) {
                $insert_stmt = $conn->prepare("
                    INSERT INTO lesson_comments (lesson_id, user_id, parent_id, comment_text, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                
                if ($insert_stmt->execute([$lesson_id, $_SESSION['user_id'], $parent_id, $comment_text])) {
                    $success_message = 'تم إضافة تعليقك بنجاح!';
                } else {
                    $error_message = 'حدث خطأ أثناء إضافة التعليق';
                }
            }
        } elseif ($_POST['action'] === 'like_comment') {
            $comment_id = intval($_POST['comment_id'] ?? 0);
            
            if ($comment_id > 0) {
                // Toggle like
                $check_stmt = $conn->prepare("
                    SELECT id FROM comment_likes WHERE comment_id = ? AND user_id = ?
                ");
                $check_stmt->execute([$comment_id, $_SESSION['user_id']]);
                
                if ($check_stmt->fetch()) {
                    // Remove like
                    $delete_stmt = $conn->prepare("
                        DELETE FROM comment_likes WHERE comment_id = ? AND user_id = ?
                    ");
                    $delete_stmt->execute([$comment_id, $_SESSION['user_id']]);
                } else {
                    // Add like
                    $like_stmt = $conn->prepare("
                        INSERT INTO comment_likes (comment_id, user_id, created_at)
                        VALUES (?, ?, NOW())
                    ");
                    $like_stmt->execute([$comment_id, $_SESSION['user_id']]);
                }
            }
        }
    }
    
    // Get comments with replies
    $comments_stmt = $conn->prepare("
        SELECT 
            c.*,
            u.name as user_name,
            u.role as user_role,
            COUNT(cl.id) as like_count,
            MAX(CASE WHEN cl.user_id = ? THEN 1 ELSE 0 END) as user_liked
        FROM lesson_comments c
        JOIN users u ON c.user_id = u.id
        LEFT JOIN comment_likes cl ON c.id = cl.comment_id
        WHERE c.lesson_id = ? AND c.parent_id IS NULL
        GROUP BY c.id, u.name, u.role
        ORDER BY c.created_at DESC
    ");
    $comments_stmt->execute([$_SESSION['user_id'], $lesson_id]);
    $comments = $comments_stmt->fetchAll();
    
    // Get replies for each comment
    foreach ($comments as &$comment) {
        $replies_stmt = $conn->prepare("
            SELECT 
                c.*,
                u.name as user_name,
                u.role as user_role,
                COUNT(cl.id) as like_count,
                MAX(CASE WHEN cl.user_id = ? THEN 1 ELSE 0 END) as user_liked
            FROM lesson_comments c
            JOIN users u ON c.user_id = u.id
            LEFT JOIN comment_likes cl ON c.id = cl.comment_id
            WHERE c.parent_id = ?
            GROUP BY c.id, u.name, u.role
            ORDER BY c.created_at ASC
        ");
        $replies_stmt->execute([$_SESSION['user_id'], $comment['id']]);
        $comment['replies'] = $replies_stmt->fetchAll();
    }
    
} catch (Exception $e) {
    error_log("Error in discussions: " . $e->getMessage());
    $error_message = 'حدث خطأ في تحميل النقاشات';
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نقاشات الدرس - <?php echo htmlspecialchars($lesson['title']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem;
        }

        .lesson-header {
            text-align: center;
            margin-bottom: 3rem;
            padding: 2rem;
        }

        .lesson-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #fff, #f093fb);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .comment-form {
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .form-textarea {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-family: 'Cairo', sans-serif;
            min-height: 120px;
            resize: vertical;
        }

        .form-textarea::placeholder {
            color: rgba(255, 255, 255, 0.7);
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

        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }

        .comments-section {
            padding: 2rem;
        }

        .comment-item {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-right: 4px solid var(--accent-color);
        }

        .comment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .comment-author {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .author-name {
            font-weight: 600;
            color: var(--success-color);
        }

        .author-role {
            padding: 0.2rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .role-teacher {
            background: var(--warning-color);
            color: white;
        }

        .role-student {
            background: var(--accent-color);
            color: white;
        }

        .comment-date {
            font-size: 0.9rem;
            opacity: 0.7;
        }

        .comment-text {
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        .comment-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .like-btn {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .like-btn:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .like-btn.liked {
            color: var(--danger-color);
        }

        .reply-btn {
            background: none;
            border: none;
            color: var(--accent-color);
            cursor: pointer;
            font-family: 'Cairo', sans-serif;
        }

        .replies-section {
            margin-top: 1rem;
            padding-right: 2rem;
            border-right: 2px solid rgba(255, 255, 255, 0.1);
        }

        .reply-item {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .reply-form {
            display: none;
            margin-top: 1rem;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
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

        .alert-danger {
            background: rgba(250, 112, 154, 0.2);
            color: var(--danger-color);
            border: 1px solid rgba(250, 112, 154, 0.3);
        }

        @media (max-width: 768px) {
            .comment-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .replies-section {
                padding-right: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Lesson Header -->
        <div class="lesson-header glass">
            <h1 class="lesson-title">نقاشات الدرس</h1>
            <h2><?php echo htmlspecialchars($lesson['title']); ?></h2>
            <p><?php echo htmlspecialchars($lesson['subject_name']); ?> - <?php echo htmlspecialchars($lesson['teacher_name']); ?></p>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Add Comment Form -->
        <div class="comment-form glass">
            <h3 style="color: var(--accent-color); margin-bottom: 1.5rem;">
                <i class="fas fa-comment-dots"></i> أضف تعليقاً جديداً
            </h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_comment">
                <div class="form-group">
                    <textarea name="comment_text" class="form-textarea" 
                              placeholder="شاركنا رأيك أو اسألك سؤالاً حول الدرس..." required></textarea>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> إرسال التعليق
                </button>
            </form>
        </div>

        <!-- Comments Section -->
        <div class="comments-section glass">
            <h3 style="color: var(--accent-color); margin-bottom: 2rem;">
                <i class="fas fa-comments"></i> التعليقات والنقاشات (<?php echo count($comments); ?>)
            </h3>

            <?php if (empty($comments)): ?>
                <div style="text-align: center; padding: 3rem; opacity: 0.7;">
                    <i class="fas fa-comment-slash" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                    <p>لا توجد تعليقات بعد. كن أول من يعلق!</p>
                </div>
            <?php else: ?>
                <?php foreach ($comments as $comment): ?>
                    <div class="comment-item">
                        <div class="comment-header">
                            <div class="comment-author">
                                <span class="author-name"><?php echo htmlspecialchars($comment['user_name']); ?></span>
                                <span class="author-role role-<?php echo $comment['user_role']; ?>">
                                    <?php echo $comment['user_role'] === 'teacher' ? 'معلم' : 'طالب'; ?>
                                </span>
                            </div>
                            <span class="comment-date">
                                <i class="fas fa-clock"></i>
                                <?php echo date('Y-m-d H:i', strtotime($comment['created_at'])); ?>
                            </span>
                        </div>
                        
                        <div class="comment-text">
                            <?php echo nl2br(htmlspecialchars($comment['comment_text'])); ?>
                        </div>
                        
                        <div class="comment-actions">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="like_comment">
                                <input type="hidden" name="comment_id" value="<?php echo $comment['id']; ?>">
                                <button type="submit" class="like-btn <?php echo $comment['user_liked'] ? 'liked' : ''; ?>">
                                    <i class="fas fa-heart"></i>
                                    <span><?php echo $comment['like_count']; ?></span>
                                </button>
                            </form>
                            
                            <button class="reply-btn" onclick="toggleReplyForm(<?php echo $comment['id']; ?>)">
                                <i class="fas fa-reply"></i> رد
                            </button>
                        </div>

                        <!-- Reply Form -->
                        <div class="reply-form" id="replyForm<?php echo $comment['id']; ?>">
                            <form method="POST">
                                <input type="hidden" name="action" value="add_comment">
                                <input type="hidden" name="parent_id" value="<?php echo $comment['id']; ?>">
                                <div class="form-group">
                                    <textarea name="comment_text" class="form-textarea" 
                                              placeholder="اكتب ردك هنا..." required style="min-height: 80px;"></textarea>
                                </div>
                                <div style="display: flex; gap: 1rem;">
                                    <button type="submit" class="btn btn-primary btn-small">
                                        <i class="fas fa-paper-plane"></i> إرسال الرد
                                    </button>
                                    <button type="button" class="btn btn-secondary btn-small" 
                                            onclick="toggleReplyForm(<?php echo $comment['id']; ?>)">
                                        إلغاء
                                    </button>
                                </div>
                            </form>
                        </div>

                        <!-- Replies -->
                        <?php if (!empty($comment['replies'])): ?>
                            <div class="replies-section">
                                <?php foreach ($comment['replies'] as $reply): ?>
                                    <div class="reply-item">
                                        <div class="comment-header">
                                            <div class="comment-author">
                                                <span class="author-name"><?php echo htmlspecialchars($reply['user_name']); ?></span>
                                                <span class="author-role role-<?php echo $reply['user_role']; ?>">
                                                    <?php echo $reply['user_role'] === 'teacher' ? 'معلم' : 'طالب'; ?>
                                                </span>
                                            </div>
                                            <span class="comment-date">
                                                <i class="fas fa-clock"></i>
                                                <?php echo date('Y-m-d H:i', strtotime($reply['created_at'])); ?>
                                            </span>
                                        </div>
                                        
                                        <div class="comment-text">
                                            <?php echo nl2br(htmlspecialchars($reply['comment_text'])); ?>
                                        </div>
                                        
                                        <div class="comment-actions">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="like_comment">
                                                <input type="hidden" name="comment_id" value="<?php echo $reply['id']; ?>">
                                                <button type="submit" class="like-btn <?php echo $reply['user_liked'] ? 'liked' : ''; ?>">
                                                    <i class="fas fa-heart"></i>
                                                    <span><?php echo $reply['like_count']; ?></span>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Navigation -->
        <div style="text-align: center; margin-top: 2rem;">
            <a href="lesson_viewer.php?lesson_id=<?php echo $lesson_id; ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-right"></i> العودة للدرس
            </a>
        </div>
    </div>

    <script>
        function toggleReplyForm(commentId) {
            const form = document.getElementById('replyForm' + commentId);
            if (form.style.display === 'none' || form.style.display === '') {
                form.style.display = 'block';
                form.querySelector('textarea').focus();
            } else {
                form.style.display = 'none';
            }
        }

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