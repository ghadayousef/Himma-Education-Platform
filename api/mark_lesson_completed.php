<?php
/**
 * API: Mark lesson as completed and update enrollment overall progress
 * المدخل: JSON { lesson_id: number }
 * المخرج: JSON { success: bool, progress: number, message?: string }
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    $db = new Database();
    $conn = $db->connect();

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    $lesson_id = intval($data['lesson_id'] ?? 0);
    $user_id = intval($_SESSION['user_id']);

    if ($lesson_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid lesson_id']);
        exit();
    }

    // جلب الدرس وتحديد المادة
    $lesson_stmt = $conn->prepare("SELECT id, subject_id FROM lessons WHERE id = ?");
    $lesson_stmt->execute([$lesson_id]);
    $lesson = $lesson_stmt->fetch();

    if (!$lesson) {
        echo json_encode(['success' => false, 'message' => 'الدرس غير موجود']);
        exit();
    }

    $subject_id = intval($lesson['subject_id']);

    // التحقق من التسجيل في المادة
    $enroll_stmt = $conn->prepare("SELECT id FROM enrollments WHERE user_id = ? AND subject_id = ? AND status = 'active'");
    $enroll_stmt->execute([$user_id, $subject_id]);
    $enrollment = $enroll_stmt->fetch();

    if (!$enrollment) {
        echo json_encode(['success' => false, 'message' => 'غير مشترك في المادة']);
        exit();
    }

    // تسجيل أو تحديث تقدم الدرس
    $progress_check = $conn->prepare("SELECT id FROM lesson_progress WHERE user_id = ? AND lesson_id = ?");
    $progress_check->execute([$user_id, $lesson_id]);

    if ($progress_check->fetch()) {
        $update = $conn->prepare("UPDATE lesson_progress SET completed = 1, completed_at = NOW(), updated_at = NOW() WHERE user_id = ? AND lesson_id = ?");
        $update->execute([$user_id, $lesson_id]);
    } else {
        $insert = $conn->prepare("INSERT INTO lesson_progress (user_id, lesson_id, completed, completed_at, created_at, updated_at) VALUES (?, ?, 1, NOW(), NOW(), NOW())");
        $insert->execute([$user_id, $lesson_id]);
    }

    // حساب التقدم العام للمادة
    $total_lessons_stmt = $conn->prepare("SELECT COUNT(*) FROM lessons WHERE subject_id = ?");
    $total_lessons_stmt->execute([$subject_id]);
    $total_lessons = intval($total_lessons_stmt->fetchColumn());

    $completed_lessons_stmt = $conn->prepare("SELECT COUNT(*) FROM lesson_progress WHERE user_id = ? AND lesson_id IN (SELECT id FROM lessons WHERE subject_id = ?) AND completed = 1");
    $completed_lessons_stmt->execute([$user_id, $subject_id]);
    $completed_count = intval($completed_lessons_stmt->fetchColumn());

    $overall_progress = $total_lessons > 0 ? ($completed_count / $total_lessons) * 100 : 0;

    // تحديث نسبة التقدم في جدول الاشتراكات
    $update_enrollment = $conn->prepare("UPDATE enrollments SET progress_percentage = ? WHERE user_id = ? AND subject_id = ?");
    $update_enrollment->execute([$overall_progress, $user_id, $subject_id]);

    echo json_encode(['success' => true, 'progress' => round($overall_progress, 2)]);
} catch (Exception $e) {
    error_log('mark_lesson_completed error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'حدث خطأ داخلي']);
}