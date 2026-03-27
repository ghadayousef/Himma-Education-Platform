<?php
/**
 * API: Update lesson progress heartbeat (keep-alive)
 * يقوم بتحديث الحقل updated_at لسجل lesson_progress بدون تغيير الإكمال
 * المدخل: JSON { lesson_id: number }
 * المخرج: JSON { success: bool }
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

    // جلب الدرس لتحديد المادة
    $lesson_stmt = $conn->prepare("SELECT subject_id FROM lessons WHERE id = ?");
    $lesson_stmt->execute([$lesson_id]);
    $lesson = $lesson_stmt->fetch();

    if (!$lesson) {
        echo json_encode(['success' => false, 'message' => 'Lesson not found']);
        exit();
    }

    $subject_id = intval($lesson['subject_id']);

    // التحقق من التسجيل في المادة
    $enroll_stmt = $conn->prepare("SELECT id FROM enrollments WHERE user_id = ? AND subject_id = ? AND status = 'active'");
    $enroll_stmt->execute([$user_id, $subject_id]);
    if (!$enroll_stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Not enrolled']);
        exit();
    }

    // تحديث أو إدراج سجل الوصول
    $exists_stmt = $conn->prepare("SELECT id FROM lesson_progress WHERE user_id = ? AND lesson_id = ?");
    $exists_stmt->execute([$user_id, $lesson_id]);

    if ($exists_stmt->fetch()) {
        $update = $conn->prepare("UPDATE lesson_progress SET updated_at = NOW() WHERE user_id = ? AND lesson_id = ?");
        $update->execute([$user_id, $lesson_id]);
    } else {
        $insert = $conn->prepare("INSERT INTO lesson_progress (user_id, lesson_id, completed, completed_at, created_at, updated_at) VALUES (?, ?, 0, NULL, NOW(), NOW())");
        $insert->execute([$user_id, $lesson_id]);
    }

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log('update_lesson_progress error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Internal error']);
}