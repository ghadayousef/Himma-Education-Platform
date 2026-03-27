<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// التأكد من بدء الجلسة
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// تضمين ملفات الإعدادات
require_once '../config/database.php';
require_once '../includes/functions.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'غير مسموح - يجب تسجيل الدخول']);
    exit;
}

// التحقق من طريقة الطلب
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'طريقة الطلب غير مسموحة']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // قراءة البيانات المرسلة
    $input = json_decode(file_get_contents('php://input'), true);
    $notification_id = $input['notification_id'] ?? 0;

    if (empty($notification_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'معرف الإشعار مطلوب']);
        exit;
    }

    $db = new Database();
    $conn = $db->connect();

    // التحقق من أن الإشعار يخص المستخدم الحالي
    $check_stmt = $conn->prepare("SELECT id FROM notifications WHERE id = ? AND user_id = ?");
    $check_stmt->execute([$notification_id, $user_id]);
    
    if (!$check_stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'غير مسموح بالوصول لهذا الإشعار']);
        exit;
    }

    // تحديث الإشعار كمقروء
    $update_stmt = $conn->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?");
    $result = $update_stmt->execute([$notification_id, $user_id]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'تم تحديد الإشعار كمقروء بنجاح']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'خطأ في تحديث الإشعار']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطأ في الخادم: ' . $e->getMessage()]);
}
?>