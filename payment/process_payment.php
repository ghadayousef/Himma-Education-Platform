<?php
/**
 * معالجة الدفع وإنشاء التسجيل - منصة همّة التوجيهي
 * Process Payment and Create Enrollment - Himma Tawjihi Platform
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// التحقق من تسجيل الدخول
if (!is_logged_in()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'يجب تسجيل الدخول أولاً']);
    exit;
}

$db = new Database();
$conn = $db->connect();
$user_id = $_SESSION['user_id'];

// التحقق من البيانات المرسلة
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject_id = isset($_POST['subject_id']) ? intval($_POST['subject_id']) : 0;
    $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : '';
    
    if (!$subject_id || !$payment_method) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'بيانات غير صحيحة']);
        exit;
    }
    
    try {
        // جلب بيانات المادة
        $subject_stmt = $conn->prepare("SELECT * FROM subjects WHERE id = ? AND is_active = 1");
        $subject_stmt->execute([$subject_id]);
        $subject = $subject_stmt->fetch();
        
        if (!$subject) {
            throw new Exception('المادة غير موجودة');
        }
        
        // التحقق من عدم التسجيل المسبق
        $check_stmt = $conn->prepare("SELECT id FROM enrollments WHERE user_id = ? AND subject_id = ?");
        $check_stmt->execute([$user_id, $subject_id]);
        
        if ($check_stmt->fetch()) {
            throw new Exception('أنت مسجل بالفعل في هذه المادة');
        }
        
        // إنشاء التسجيل الجديد
        $enroll_stmt = $conn->prepare("
            INSERT INTO enrollments 
            (user_id, subject_id, enrollment_date, status, payment_status, payment_amount, payment_date, progress_percentage) 
            VALUES (?, ?, NOW(), 'active', 'paid', ?, NOW(), 0)
        ");
        
        if ($enroll_stmt->execute([$user_id, $subject_id, $subject['price']])) {
            $enrollment_id = $conn->lastInsertId();
            
            // إنشاء إشعار للطالب
            $notification_stmt = $conn->prepare("
                INSERT INTO notifications (user_id, type, title, message, created_at) 
                VALUES (?, 'system', ?, ?, NOW())
            ");
            
            $notification_title = 'تم التسجيل بنجاح';
            $notification_message = 'تم تسجيلك في مادة "' . $subject['name'] . '" بنجاح. يمكنك الآن البدء بالدراسة.';
            
            $notification_stmt->execute([$user_id, $notification_title, $notification_message]);
            
            // إشعار المعلم
            $teacher_notification_stmt = $conn->prepare("
                INSERT INTO notifications (user_id, type, title, message, created_at) 
                VALUES (?, 'system', ?, ?, NOW())
            ");
            
            $teacher_notification_title = 'طالب جديد سجل في مادتك';
            $teacher_notification_message = 'طالب جديد سجل في مادة "' . $subject['name'] . '"';
            
            $teacher_notification_stmt->execute([
                $subject['teacher_id'], 
                $teacher_notification_title, 
                $teacher_notification_message
            ]);
            
            // توليد رقم مرجعي
            $reference_number = 'PAY-' . date('Ymd') . '-' . str_pad($enrollment_id, 6, '0', STR_PAD_LEFT);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true, 
                'message' => 'تم الدفع والتسجيل بنجاح',
                'enrollment_id' => $enrollment_id,
                'reference_number' => $reference_number,
                'redirect_url' => 'payment_success.php?subject_id=' . $subject_id . '&method=' . $payment_method . '&ref=' . $reference_number
            ]);
        } else {
            throw new Exception('فشل في إنشاء التسجيل');
        }
        
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'طريقة طلب غير صحيحة']);
}
?>