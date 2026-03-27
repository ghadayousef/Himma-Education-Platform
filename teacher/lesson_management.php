<?php
/**
 *   نظام إدارة الدروس مع إضافة رفع الفيديو - منصة همّة التوجيهي
 * Lesson Management System with Video  - Himma Educational Platform
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../home/index.php');
    exit();
}

$success_message = '';
$error_message = '';
$edit_lesson = null;

// Handle different actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        $db = new Database();
        $conn = $db->connect();
        
        switch ($action) {
            case 'create_lesson':
                // إنشاء درس جديد
                $subject_id = intval($_POST['subject_id'] ?? 0);
                $title = trim($_POST['title'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $content = trim($_POST['content'] ?? '');
                $duration_minutes = intval($_POST['duration_minutes'] ?? 0);
                $order_num = intval($_POST['order_num'] ?? 1);
                $is_free = isset($_POST['is_free']) ? 1 : 0;
                
                // Validation
                if (empty($title) || empty($description) || $subject_id <= 0) {
                    $error_message = 'جميع الحقول المطلوبة يجب ملؤها';
                } else {
                    // Verify that the subject belongs to the current teacher
                    $verify_stmt = $conn->prepare("SELECT id FROM subjects WHERE id = ? AND teacher_id = ?");
                    $verify_stmt->execute([$subject_id, $_SESSION['user_id']]);
                    
                    if (!$verify_stmt->fetch()) {
                        $error_message = 'غير مصرح لك بإضافة دروس لهذه المادة';
                    } else {
                        $video_url = '';
                        $video_type = 'none'; // none, youtube, upload
                        
                        // Handle YouTube URL
                        if (!empty($_POST['youtube_url'])) {
                            $youtube_url = trim($_POST['youtube_url']);
                            if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $youtube_url)) {
                                $video_url = $youtube_url;
                                $video_type = 'youtube';
                            }
                        }
                        
                        // Handle video file upload
                        if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
                            $upload_dir = '../uploads/videos/';
                            if (!is_dir($upload_dir)) {
                                mkdir($upload_dir, 0755, true);
                            }
                            
                            $file_info = pathinfo($_FILES['video_file']['name']);
                            $allowed_extensions = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv'];
                            
                            if (in_array(strtolower($file_info['extension']), $allowed_extensions)) {
                                $max_size = 500 * 1024 * 1024; // 500MB
                                if ($_FILES['video_file']['size'] <= $max_size) {
                                    $new_filename = uniqid('video_') . '_' . time() . '.' . $file_info['extension'];
                                    $upload_path = $upload_dir . $new_filename;
                                    
                                    if (move_uploaded_file($_FILES['video_file']['tmp_name'], $upload_path)) {
                                        $video_url = 'uploads/videos/' . $new_filename;
                                        $video_type = 'upload';
                                    } else {
                                        $error_message = 'فشل في رفع ملف الفيديو';
                                    }
                                } else {
                                    $error_message = 'حجم ملف الفيديو يجب أن يكون أقل من 500 ميجابايت';
                                }
                            } else {
                                $error_message = 'نوع ملف الفيديو غير مدعوم. الأنواع المدعومة: MP4, AVI, MOV, WMV, FLV, WebM, MKV';
                            }
                        }
                        
                        if (empty($error_message)) {
                            // Insert lesson
                            $insert_stmt = $conn->prepare("
                                INSERT INTO lessons (subject_id, title, description, content, video_url, video_type,
                                                   duration_minutes, order_num, is_free, created_at) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                            ");
                            
                            if ($insert_stmt->execute([$subject_id, $title, $description, $content, $video_url, $video_type,
                                                     $duration_minutes, $order_num, $is_free])) {
                                $success_message = 'تم إنشاء الدرس بنجاح!';
                            } else {
                                $error_message = 'حدث خطأ أثناء إنشاء الدرس';
                            }
                        }
                    }
                }
                break;
                
            case 'update_lesson':
                // تحديث درس موجود
                $lesson_id = intval($_POST['lesson_id'] ?? 0);
                $subject_id = intval($_POST['subject_id'] ?? 0);
                $title = trim($_POST['title'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $content = trim($_POST['content'] ?? '');
                $duration_minutes = intval($_POST['duration_minutes'] ?? 0);
                $order_num = intval($_POST['order_num'] ?? 1);
                $is_free = isset($_POST['is_free']) ? 1 : 0;
                
                if (empty($title) || empty($description) || $lesson_id <= 0) {
                    $error_message = 'جميع الحقول المطلوبة يجب ملؤها';
                } else {
                    // Verify ownership
                    $verify_stmt = $conn->prepare("
                        SELECT l.id, l.video_url, l.video_type FROM lessons l 
                        JOIN subjects s ON l.subject_id = s.id 
                        WHERE l.id = ? AND s.teacher_id = ?
                    ");
                    $verify_stmt->execute([$lesson_id, $_SESSION['user_id']]);
                    $existing_lesson = $verify_stmt->fetch();
                    
                    if (!$existing_lesson) {
                        $error_message = 'غير مصرح لك بتعديل هذا الدرس';
                    } else {
                        $video_url = $existing_lesson['video_url'];
                        $video_type = $existing_lesson['video_type'] ?? 'none';
                        
                        // Handle YouTube URL update
                        if (!empty($_POST['youtube_url'])) {
                            $youtube_url = trim($_POST['youtube_url']);
                            if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $youtube_url)) {
                                // Delete old uploaded video if exists
                                if ($video_type === 'upload' && !empty($video_url) && file_exists('../' . $video_url)) {
                                    unlink('../' . $video_url);
                                }
                                $video_url = $youtube_url;
                                $video_type = 'youtube';
                            }
                        }
                        
                        // Handle new video file upload
                        if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
                            $upload_dir = '../uploads/videos/';
                            if (!is_dir($upload_dir)) {
                                mkdir($upload_dir, 0755, true);
                            }
                            
                            $file_info = pathinfo($_FILES['video_file']['name']);
                            $allowed_extensions = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv'];
                            
                            if (in_array(strtolower($file_info['extension']), $allowed_extensions)) {
                                $max_size = 500 * 1024 * 1024; // 500MB
                                if ($_FILES['video_file']['size'] <= $max_size) {
                                    $new_filename = uniqid('video_') . '_' . time() . '.' . $file_info['extension'];
                                    $upload_path = $upload_dir . $new_filename;
                                    
                                    if (move_uploaded_file($_FILES['video_file']['tmp_name'], $upload_path)) {
                                        // Delete old uploaded video if exists
                                        if ($video_type === 'upload' && !empty($video_url) && file_exists('../' . $video_url)) {
                                            unlink('../' . $video_url);
                                        }
                                        $video_url = 'uploads/videos/' . $new_filename;
                                        $video_type = 'upload';
                                    } else {
                                        $error_message = 'فشل في رفع ملف الفيديو';
                                    }
                                } else {
                                    $error_message = 'حجم ملف الفيديو يجب أن يكون أقل من 500 ميجابايت';
                                }
                            } else {
                                $error_message = 'نوع ملف الفيديو غير مدعوم. الأنواع المدعومة: MP4, AVI, MOV, WMV, FLV, WebM, MKV';
                            }
                        }
                        
                        // Handle video removal
                        if (isset($_POST['remove_video']) && $_POST['remove_video'] === '1') {
                            if ($video_type === 'upload' && !empty($video_url) && file_exists('../' . $video_url)) {
                                unlink('../' . $video_url);
                            }
                            $video_url = '';
                            $video_type = 'none';
                        }
                        
                        if (empty($error_message)) {
                            // Update lesson
                            $update_stmt = $conn->prepare("
                                UPDATE lessons 
                                SET subject_id = ?, title = ?, description = ?, content = ?, video_url = ?, video_type = ?,
                                    duration_minutes = ?, order_num = ?, is_free = ?, updated_at = NOW()
                                WHERE id = ?
                            ");
                            
                            if ($update_stmt->execute([$subject_id, $title, $description, $content, $video_url, $video_type,
                                                     $duration_minutes, $order_num, $is_free, $lesson_id])) {
                                $success_message = 'تم تحديث الدرس بنجاح!';
                            } else {
                                $error_message = 'حدث خطأ أثناء تحديث الدرس';
                            }
                        }
                    }
                }
                break;
                
            case 'delete_lesson':
                // حذف درس
                $lesson_id = intval($_POST['lesson_id'] ?? 0);
                
                if ($lesson_id <= 0) {
                    $error_message = 'معرف الدرس غير صحيح';
                } else {
                    // Verify ownership and get video info
                    $verify_stmt = $conn->prepare("
                        SELECT l.id, l.video_url, l.video_type FROM lessons l 
                        JOIN subjects s ON l.subject_id = s.id 
                        WHERE l.id = ? AND s.teacher_id = ?
                    ");
                    $verify_stmt->execute([$lesson_id, $_SESSION['user_id']]);
                    $lesson_to_delete = $verify_stmt->fetch();
                    
                    if (!$lesson_to_delete) {
                        $error_message = 'غير مصرح لك بحذف هذا الدرس';
                    } else {
                        // Delete uploaded video file if exists
                        if ($lesson_to_delete['video_type'] === 'upload' && !empty($lesson_to_delete['video_url'])) {
                            $video_path = '../' . $lesson_to_delete['video_url'];
                            if (file_exists($video_path)) {
                                unlink($video_path);
                            }
                        }
                        
                        // Delete lesson from database
                        $delete_stmt = $conn->prepare("DELETE FROM lessons WHERE id = ?");
                        
                        if ($delete_stmt->execute([$lesson_id])) {
                            $success_message = 'تم حذف الدرس بنجاح!';
                        } else {
                            $error_message = 'حدث خطأ أثناء حذف الدرس';
                        }
                    }
                }
                break;
        }
        
    } catch (Exception $e) {
        $error_message = 'حدث خطأ في النظام: ' . $e->getMessage();
        error_log("Lesson management error: " . $e->getMessage());
    }
}

// Handle edit request
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    try {
        $db = new Database();
        $conn = $db->connect();
        
        $edit_stmt = $conn->prepare("
            SELECT l.* FROM lessons l 
            JOIN subjects s ON l.subject_id = s.id 
            WHERE l.id = ? AND s.teacher_id = ?
        ");
        $edit_stmt->execute([$edit_id, $_SESSION['user_id']]);
        $edit_lesson = $edit_stmt->fetch();
        
        if (!$edit_lesson) {
            $error_message = 'الدرس غير موجود أو غير مصرح لك بتعديله';
        }
    } catch (Exception $e) {
        $error_message = 'حدث خطأ أثناء تحميل بيانات الدرس';
    }
}

// Add video_type column if it doesn't exist
try {
    $db = new Database();
    $conn = $db->connect();
    
    $check_column = $conn->prepare("SHOW COLUMNS FROM lessons LIKE 'video_type'");
    $check_column->execute();
    if (!$check_column->fetch()) {
        $conn->exec("ALTER TABLE lessons ADD COLUMN video_type ENUM('none', 'youtube', 'upload') DEFAULT 'none' AFTER video_url");
    }
} catch (Exception $e) {
    // Column might already exist
}

// Get teacher's subjects
$subjects = [];
try {
    $db = new Database();
    $conn = $db->connect();
    
    $subjects_stmt = $conn->prepare("
        SELECT id, name, description 
        FROM subjects 
        WHERE teacher_id = ? AND is_active = 1 
        ORDER BY created_at DESC
    ");
    $subjects_stmt->execute([$_SESSION['user_id']]);
    $subjects = $subjects_stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching subjects: " . $e->getMessage());
}

// Get lessons for selected subject
$lessons = [];
$selected_subject_id = $_GET['subject_id'] ?? '';
if (!empty($selected_subject_id)) {
    try {
        $lessons_stmt = $conn->prepare("
            SELECT l.*, s.name as subject_name 
            FROM lessons l
            JOIN subjects s ON l.subject_id = s.id
            WHERE l.subject_id = ? AND s.teacher_id = ?
            ORDER BY l.order_num ASC, l.created_at DESC
        ");
        $lessons_stmt->execute([$selected_subject_id, $_SESSION['user_id']]);
        $lessons = $lessons_stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error fetching lessons: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الدروس مع رفع الفيديو - منصة همّة التعليمية</title>
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

        .main-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .form-section {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: white;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-size: 1rem;
            backdrop-filter: blur(10px);
            font-family: 'Cairo', sans-serif;
        }

        .form-input::placeholder, .form-textarea::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.2);
        }

        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-select option {
            background: var(--dark-color);
            color: white;
        }

        .file-upload-area {
            border: 2px dashed rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            background: rgba(255, 255, 255, 0.05);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }

        .file-upload-area:hover {
            border-color: var(--accent-color);
            background: rgba(255, 255, 255, 0.1);
        }

        .file-upload-area.dragover {
            border-color: var(--success-color);
            background: rgba(79, 172, 254, 0.1);
        }

        .file-upload-input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }

        .upload-icon {
            font-size: 3rem;
            color: var(--accent-color);
            margin-bottom: 1rem;
        }

        .upload-text {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }

        .upload-hint {
            font-size: 0.9rem;
            opacity: 0.7;
        }

        .video-preview {
            margin-top: 1rem;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            display: none;
        }

        .video-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .video-icon {
            font-size: 2rem;
            color: var(--success-color);
        }

        .remove-video {
            background: var(--danger-color);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            margin-left: auto;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin: 0;
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

        .btn-danger {
            background: linear-gradient(135deg, var(--danger-color), #ff6b6b);
            color: white;
        }

        .btn-warning {
            background: linear-gradient(135deg, #ffc107, #ff8f00);
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

        .lessons-section {
            padding: 2rem;
        }

        .subject-selector {
            margin-bottom: 2rem;
        }

        .lessons-grid {
            display: grid;
            gap: 1rem;
        }

        .lesson-card {
            padding: 1.5rem;
            border-radius: 15px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .lesson-card:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }

        .lesson-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .lesson-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--accent-color);
        }

        .lesson-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .lesson-description {
            margin: 1rem 0;
            line-height: 1.6;
            opacity: 0.9;
        }

        .video-status {
            margin: 1rem 0;
            padding: 0.5rem;
            border-radius: 8px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .video-youtube {
            background: rgba(255, 0, 0, 0.2);
            color: #ff6b6b;
        }

        .video-upload {
            background: rgba(79, 172, 254, 0.2);
            color: var(--success-color);
        }

        .video-none {
            background: rgba(255, 255, 255, 0.05);
            color: rgba(255, 255, 255, 0.7);
        }

        .lesson-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
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

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            margin: 5% auto;
            padding: 2rem;
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            border: 1px solid var(--glass-border);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: var(--danger-color);
        }

        .progress-bar {
            width: 100%;
            height: 10px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 5px;
            overflow: hidden;
            margin-top: 1rem;
            display: none;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--success-color), var(--warning-color));
            width: 0%;
            transition: width 0.3s ease;
        }

        @media (max-width: 768px) {
            .main-grid {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .lesson-header {
                flex-direction: column;
                gap: 0.5rem;
            }

            .lesson-actions {
                flex-wrap: wrap;
            }

            .file-upload-area {
                padding: 1rem;
            }

            .upload-icon {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>إدارة الدروس مع رفع الفيديو</h1>
            <p>أضف وأدر وعدّل دروس موادك التعليمية مع إمكانية رفع الفيديوهات</p>
        </div>

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

        <div class="main-grid">
            <!-- Lesson Creation/Edit Form -->
            <div class="form-section glass">
                <h2 style="margin-bottom: 1.5rem; color: var(--accent-color);">
                    <i class="fas fa-<?php echo $edit_lesson ? 'edit' : 'plus-circle'; ?>"></i> 
                    <?php echo $edit_lesson ? 'تعديل الدرس' : 'إضافة درس جديد'; ?>
                </h2>

                <form method="POST" id="lessonForm" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="<?php echo $edit_lesson ? 'update_lesson' : 'create_lesson'; ?>">
                    <?php if ($edit_lesson): ?>
                        <input type="hidden" name="lesson_id" value="<?php echo $edit_lesson['id']; ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label class="form-label">المادة الدراسية *</label>
                        <select name="subject_id" class="form-select" required>
                            <option value="">اختر المادة</option>
                            <?php foreach ($subjects as $subject): ?>
                                <option value="<?php echo $subject['id']; ?>" 
                                        <?php echo ($edit_lesson && $edit_lesson['subject_id'] == $subject['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($subject['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">عنوان الدرس *</label>
                        <input type="text" name="title" class="form-input" placeholder="أدخل عنوان الدرس" 
                               value="<?php echo $edit_lesson ? htmlspecialchars($edit_lesson['title']) : ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">وصف الدرس *</label>
                        <textarea name="description" class="form-textarea" placeholder="وصف مفصل للدرس" required><?php echo $edit_lesson ? htmlspecialchars($edit_lesson['description']) : ''; ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">محتوى الدرس</label>
                        <textarea name="content" class="form-textarea" placeholder="محتوى الدرس التفصيلي" style="min-height: 150px;"><?php echo $edit_lesson ? htmlspecialchars($edit_lesson['content']) : ''; ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">مدة الدرس (بالدقائق)</label>
                            <input type="number" name="duration_minutes" class="form-input" placeholder="60" min="1"
                                   value="<?php echo $edit_lesson ? $edit_lesson['duration_minutes'] : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">ترتيب الدرس</label>
                            <input type="number" name="order_num" class="form-input" placeholder="1" min="1" 
                                   value="<?php echo $edit_lesson ? $edit_lesson['order_num'] : '1'; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">رابط YouTube (اختياري)</label>
                        <input type="url" name="youtube_url" class="form-input" placeholder="https://www.youtube.com/watch?v=..."
                               value="<?php echo ($edit_lesson && isset($edit_lesson['video_type']) && $edit_lesson['video_type'] === 'youtube') ? htmlspecialchars($edit_lesson['video_url']) : ''; ?>">
                        <small style="color: rgba(255,255,255,0.7); font-size: 0.8rem; display: block; margin-top: 0.5rem;">
                            أو ارفع ملف فيديو من جهازك أدناه
                        </small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">رفع ملف فيديو (اختياري)</label>
                        <div class="file-upload-area" id="fileUploadArea">
                            <input type="file" name="video_file" class="file-upload-input" id="videoFile" accept="video/*">
                            <div class="upload-content">
                                <i class="fas fa-cloud-upload-alt upload-icon"></i>
                                <div class="upload-text">اسحب ملف الفيديو هنا أو انقر للاختيار</div>
                                <div class="upload-hint">الحد الأقصى: 500 ميجابايت | الأنواع المدعومة: MP4, AVI, MOV, WMV, FLV, WebM, MKV</div>
                            </div>
                        </div>
                        <div class="video-preview" id="videoPreview">
                            <div class="video-info">
                                <i class="fas fa-video video-icon"></i>
                                <div>
                                    <div id="fileName"></div>
                                    <div id="fileSize" style="font-size: 0.8rem; opacity: 0.7;"></div>
                                </div>
                                <button type="button" class="remove-video" onclick="removeVideo()">
                                    <i class="fas fa-times"></i> إزالة
                                </button>
                            </div>
                        </div>
                        <div class="progress-bar" id="progressBar">
                            <div class="progress-fill" id="progressFill"></div>
                        </div>
                    </div>

                    <?php if ($edit_lesson && !empty($edit_lesson['video_url'])): ?>
                        <div class="form-group">
                            <div style="padding: 1rem; background: rgba(255,255,255,0.1); border-radius: 10px;">
                                <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                                    <i class="fas fa-video" style="color: var(--success-color);"></i>
                                    <div>
                                        <strong>الفيديو الحالي:</strong>
                                        <?php if (isset($edit_lesson['video_type']) && $edit_lesson['video_type'] === 'youtube'): ?>
                                            <span style="color: #ff6b6b;">رابط يوتيوب</span>
                                        <?php elseif (isset($edit_lesson['video_type']) && $edit_lesson['video_type'] === 'upload'): ?>
                                            <span style="color: var(--success-color);">ملف مرفوع</span>
                                        <?php else: ?>
                                            <span>فيديو متوفر</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                    <input type="checkbox" name="remove_video" value="1">
                                    <span style="color: var(--danger-color);">إزالة الفيديو الحالي</span>
                                </label>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="is_free" id="isFree" 
                                   <?php echo ($edit_lesson && $edit_lesson['is_free']) ? 'checked' : ''; ?>>
                            <label for="isFree" class="form-label">درس مجاني</label>
                        </div>
                    </div>

                    <div style="display: flex; gap: 1rem;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            <i class="fas fa-<?php echo $edit_lesson ? 'save' : 'plus'; ?>"></i>
                            <?php echo $edit_lesson ? 'حفظ التعديلات' : 'إضافة الدرس'; ?>
                        </button>
                        <?php if ($edit_lesson): ?>
                            <a href="lesson_management.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> إلغاء
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Lessons List -->
            <div class="lessons-section glass">
                <h2 style="margin-bottom: 1.5rem; color: var(--accent-color);">
                    <i class="fas fa-list"></i> الدروس الموجودة
                </h2>

                <div class="form-group">
                    <label class="form-label">اختر المادة لعرض دروسها:</label>
                    <select class="form-select" onchange="location.href='?subject_id=' + this.value">
                        <option value="">اختر المادة</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo $subject['id']; ?>" 
                                    <?php echo ($selected_subject_id == $subject['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subject['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="margin-top: 2rem;">
                    <?php if (empty($lessons)): ?>
                        <div style="text-align: center; padding: 2rem; opacity: 0.7;">
                            <i class="fas fa-book-open" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
                            <?php if (empty($selected_subject_id)): ?>
                                اختر مادة لعرض دروسها
                            <?php else: ?>
                                لا توجد دروس لهذه المادة بعد
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($lessons as $lesson): ?>
                            <div class="lesson-card">
                                <h3 style="color: var(--accent-color); margin-bottom: 0.5rem;">
                                    <?php echo htmlspecialchars($lesson['title']); ?>
                                </h3>
                                <p style="opacity: 0.8; margin-bottom: 1rem;">
                                    <?php echo htmlspecialchars($lesson['description']); ?>
                                </p>
                                
                                <div style="display: flex; gap: 1rem; font-size: 0.9rem; opacity: 0.7; margin-bottom: 1rem;">
                                    <span><i class="fas fa-clock"></i> <?php echo $lesson['duration_minutes']; ?> دقيقة</span>
                                    <span><i class="fas fa-sort-numeric-up"></i> ترتيب: <?php echo $lesson['order_num']; ?></span>
                                    <?php if ($lesson['is_free']): ?>
                                        <span style="color: var(--warning-color);"><i class="fas fa-gift"></i> مجاني</span>
                                    <?php endif; ?>
                                </div>

                                <div class="lesson-actions">
                                    <a href="preview_lesson.php?id=<?php echo $lesson['id']; ?>" class="btn btn-info btn-small">
                                        <i class="fas fa-eye"></i> معاينة
                                    </a>
                                    <a href="?edit=<?php echo $lesson['id']; ?>&subject_id=<?php echo $selected_subject_id; ?>" class="btn btn-warning btn-small">
                                        <i class="fas fa-edit"></i> تعديل
                                    </a>
                                    <button class="btn btn-danger btn-small" onclick="confirmDelete(<?php echo $lesson['id']; ?>, '<?php echo htmlspecialchars($lesson['title']); ?>')">
                                        <i class="fas fa-trash"></i> حذف
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div style="text-align: center; margin-top: 2rem;">
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-right"></i> العودة للوحة التحكم
            </a>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="color: var(--danger-color);">
                    <i class="fas fa-exclamation-triangle"></i> تأكيد الحذف
                </h3>
                <span class="close" onclick="closeDeleteModal()">&times;</span>
            </div>
            <p>هل أنت متأكد من حذف الدرس "<span id="lessonTitle"></span>"؟</p>
            <p style="color: var(--warning-color); margin-top: 1rem;">
                <i class="fas fa-warning"></i> هذا الإجراء لا يمكن التراجع عنه وسيتم حذف الفيديو المرفوع أيضاً!
            </p>
            <div style="margin-top: 2rem; display: flex; gap: 1rem; justify-content: flex-end;">
                <button class="btn btn-secondary" onclick="closeDeleteModal()">إلغاء</button>
                <form method="POST" style="display: inline;" id="deleteForm">
                    <input type="hidden" name="action" value="delete_lesson">
                    <input type="hidden" name="lesson_id" id="deleteId">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> حذف نهائياً
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // File upload handling
        const fileUploadArea = document.getElementById('fileUploadArea');
        const videoFile = document.getElementById('videoFile');
        const videoPreview = document.getElementById('videoPreview');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        const progressBar = document.getElementById('progressBar');
        const progressFill = document.getElementById('progressFill');

        // Drag and drop functionality
        fileUploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            fileUploadArea.classList.add('dragover');
        });

        fileUploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            fileUploadArea.classList.remove('dragover');
        });

        fileUploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            fileUploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleFileSelect(files[0]);
            }
        });

        videoFile.addEventListener('change', function(e) {
            if (e.target.files.length > 0) {
                handleFileSelect(e.target.files[0]);
            }
        });

        function handleFileSelect(file) {
            // Check file type
            const allowedTypes = ['video/mp4', 'video/avi', 'video/mov', 'video/wmv', 'video/flv', 'video/webm', 'video/x-msvideo', 'video/quicktime'];
            if (!allowedTypes.includes(file.type)) {
                alert('نوع الملف غير مدعوم. يرجى اختيار ملف فيديو صالح.');
                return;
            }

            // Check file size (500MB)
            const maxSize = 500 * 1024 * 1024;
            if (file.size > maxSize) {
                alert('حجم الملف كبير جداً. الحد الأقصى هو 500 ميجابايت.');
                return;
            }

            // Update file input
            const dt = new DataTransfer();
            dt.items.add(file);
            videoFile.files = dt.files;

            // Show preview
            fileName.textContent = file.name;
            fileSize.textContent = formatFileSize(file.size);
            videoPreview.style.display = 'block';
        }

        function removeVideo() {
            videoFile.value = '';
            videoPreview.style.display = 'none';
            progressBar.style.display = 'none';
            progressFill.style.width = '0%';
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Form validation
        document.getElementById('lessonForm').addEventListener('submit', function(e) {
            const title = document.querySelector('input[name="title"]').value.trim();
            const description = document.querySelector('textarea[name="description"]').value.trim();
            const subjectId = document.querySelector('select[name="subject_id"]').value;
            
            if (!title || !description || !subjectId) {
                e.preventDefault();
                alert('يرجى ملء جميع الحقول المطلوبة');
                return false;
            }

            // Show progress bar if file is selected
            if (videoFile.files.length > 0) {
                progressBar.style.display = 'block';
                // Simulate upload progress (in real implementation, this would be handled by the server)
                let progress = 0;
                const interval = setInterval(() => {
                    progress += Math.random() * 10;
                    if (progress >= 90) {
                        clearInterval(interval);
                        progress = 90;
                    }
                    progressFill.style.width = progress + '%';
                }, 100);
            }
        });

        // Delete confirmation
        function confirmDelete(lessonId, lessonTitle) {
            document.getElementById('deleteId').value = lessonId;
            document.getElementById('lessonTitle').textContent = lessonTitle;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target == modal) {
                modal.style.display = 'none';
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