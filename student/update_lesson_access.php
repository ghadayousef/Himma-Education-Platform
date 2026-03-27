<?php
/**
 * تحديث وقت الوصول للدرس
 * Update Lesson Access Time
 */

session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    exit('Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lesson_id = intval($_POST['lesson_id'] ?? 0);
    $subject_id = intval($_POST['subject_id'] ?? 0);
    $user_id = $_SESSION['user_id'];

    if ($lesson_id > 0 && $subject_id > 0) {
        try {
            $db = new Database();
            $conn = $db->connect();

            // التحقق من أن الطالب مسجل في المساق
            $enrollment_check = $conn->prepare("
                SELECT id FROM enrollments 
                WHERE user_id = ? AND subject_id = ? AND status = 'active'
            ");
            $enrollment_check->execute([$user_id, $subject_id]);

            if ($enrollment_check->fetch()) {
                // التحقق من وجود سجل تقدم مسبقاً
                $exists_stmt = $conn->prepare("SELECT id FROM lesson_progress WHERE user_id = ? AND lesson_id = ?");
                $exists_stmt->execute([$user_id, $lesson_id]);

                if ($exists_stmt->fetch()) {
                    // تحديث وقت الوصول
                    $update_stmt = $conn->prepare("UPDATE lesson_progress SET updated_at = NOW() WHERE user_id = ? AND lesson_id = ?");
                    $update_stmt->execute([$user_id, $lesson_id]);
                } else {
                    // إدراج سجل جديد للوصول
                    $insert_stmt = $conn->prepare("
                        INSERT INTO lesson_progress (user_id, lesson_id, completed, completed_at, created_at, updated_at)
                        VALUES (?, ?, 0, NULL, NOW(), NOW())
                    ");
                    $insert_stmt->execute([$user_id, $lesson_id]);
                }

                echo 'success';
            } else {
                echo 'not_enrolled';
            }
        } catch (Exception $e) {
            error_log("Error updating lesson access: " . $e->getMessage());
            echo 'error';
        }
    } else {
        echo 'invalid';
    }
}
?>