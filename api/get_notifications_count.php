<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// التأكد من بدء الجلسة
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'غير مسموح - يجب تسجيل الدخول']);
    exit;
}

// تضمين ملف قاعدة البيانات
require_once '../config/database.php';

try {
    $user_id = $_SESSION['user_id'];
    
    // الحصول على عدد الإشعارات غير المقروءة
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM notifications 
        WHERE user_id = ? AND is_read = 0
    ");
    
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'count' => (int)$result['count']
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'خطأ في قاعدة البيانات',
        'details' => $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'خطأ في الخادم',
        'details' => $e->getMessage()
    ]);
}
?>