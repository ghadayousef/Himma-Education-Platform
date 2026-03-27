<?php
/**
 * تفاصيل الطالب - منصة همّة التوجيهي
 * Student Details - Himma Tawjihi Platform
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// التحقق من تسجيل الدخول كمعلم
if (!is_logged_in() || !has_role('teacher')) {
    exit('غير مسموح');
}

$db = new Database();
$conn = $db->connect();
$teacher_id = $_SESSION['user_id'];
$student_id = $_GET['id'] ?? 0;

// جلب بيانات الطالب
$student_stmt = $conn->prepare("
    SELECT u.*, 
           (SELECT COUNT(*) FROM enrollments e 
            JOIN subjects s ON e.subject_id = s.id 
            WHERE e.user_id = u.id AND s.teacher_id = ?) as enrolled_subjects_count
    FROM users u 
    WHERE u.id = ? AND u.role = 'student'
");
$student_stmt->execute([$teacher_id, $student_id]);
$student = $student_stmt->fetch();

if (!$student) {
    exit('الطالب غير موجود');
}

// جلب المواد المسجل بها الطالب لدى هذا المعلم
$enrollments_stmt = $conn->prepare("
    SELECT e.*, s.name as subject_name, s.price,
           (SELECT COUNT(*) FROM quiz_results qr 
            JOIN quizzes q ON qr.quiz_id = q.id 
            WHERE q.subject_id = s.id AND qr.user_id = e.user_id) as completed_quizzes,
           (SELECT AVG(qr.score) FROM quiz_results qr 
            JOIN quizzes q ON qr.quiz_id = q.id 
            WHERE q.subject_id = s.id AND qr.user_id = e.user_id) as avg_score
    FROM enrollments e
    JOIN subjects s ON e.subject_id = s.id
    WHERE e.user_id = ? AND s.teacher_id = ?
    ORDER BY e.enrollment_date DESC
");
$enrollments_stmt->execute([$student_id, $teacher_id]);
$enrollments = $enrollments_stmt->fetchAll();

// جلب نتائج الاختبارات
$quiz_results_stmt = $conn->prepare("
    SELECT qr.*, q.title as quiz_title, s.name as subject_name
    FROM quiz_results qr
    JOIN quizzes q ON qr.quiz_id = q.id
    JOIN subjects s ON q.subject_id = s.id
    WHERE qr.user_id = ? AND s.teacher_id = ?
    ORDER BY qr.completed_at DESC
    LIMIT 10
");
$quiz_results_stmt->execute([$student_id, $teacher_id]);
$quiz_results = $quiz_results_stmt->fetchAll();
?>

<div class="row">
    <!-- Student Info -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <div class="student-avatar mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2rem;">
                    <?php echo strtoupper(substr($student['full_name'], 0, 2)); ?>
                </div>
                <h5><?php echo htmlspecialchars($student['full_name']); ?></h5>
                <p class="text-muted"><?php echo htmlspecialchars($student['email']); ?></p>
                
                <div class="row text-center mt-3">
                    <div class="col-6">
                        <div class="border-end">
                            <h6 class="text-primary"><?php echo $student['enrolled_subjects_count']; ?></h6>
                            <small class="text-muted">مادة مسجلة</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <h6 class="text-success"><?php echo count($quiz_results); ?></h6>
                        <small class="text-muted">اختبار مكتمل</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact Info -->
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="mb-0">معلومات الاتصال</h6>
            </div>
            <div class="card-body">
                <p><i class="fas fa-envelope text-primary"></i> <?php echo htmlspecialchars($student['email']); ?></p>
                <?php if ($student['phone']): ?>
                    <p><i class="fas fa-phone text-success"></i> <?php echo htmlspecialchars($student['phone']); ?></p>
                <?php endif; ?>
                <p><i class="fas fa-calendar text-info"></i> انضم في <?php echo date('Y-m-d', strtotime($student['created_at'])); ?></p>
            </div>
        </div>
    </div>

    <!-- Enrollments and Progress -->
    <div class="col-md-8">
        <!-- Enrolled Subjects -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">المواد المسجل بها</h6>
            </div>
            <div class="card-body">
                <?php if (empty($enrollments)): ?>
                    <p class="text-muted">لم يسجل في أي من موادك</p>
                <?php else: ?>
                    <?php foreach ($enrollments as $enrollment): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3 p-3 bg-light rounded">
                            <div>
                                <h6 class="mb-1"><?php echo htmlspecialchars($enrollment['subject_name']); ?></h6>
                                <small class="text-muted">
                                    تسجل في <?php echo date('Y-m-d', strtotime($enrollment['enrollment_date'])); ?>
                                </small>
                            </div>
                            <div class="text-end">
                                <div class="progress mb-1" style="width: 100px; height: 6px;">
                                    <div class="progress-bar" style="width: <?php echo $enrollment['progress_percentage']; ?>%"></div>
                                </div>
                                <small><?php echo number_format($enrollment['progress_percentage'], 1); ?>%</small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Quiz Results -->
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="mb-0">نتائج الاختبارات الأخيرة</h6>
            </div>
            <div class="card-body">
                <?php if (empty($quiz_results)): ?>
                    <p class="text-muted">لم يكمل أي اختبار بعد</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>الاختبار</th>
                                    <th>المادة</th>
                                    <th>النتيجة</th>
                                    <th>التاريخ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($quiz_results as $result): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($result['quiz_title']); ?></td>
                                        <td><span class="badge bg-info"><?php echo htmlspecialchars($result['subject_name']); ?></span></td>
                                        <td>
                                            <span class="badge <?php echo $result['is_passed'] ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo number_format($result['score'], 1); ?>%
                                            </span>
                                        </td>
                                        <td><small><?php echo date('Y-m-d H:i', strtotime($result['completed_at'])); ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.student-avatar {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.progress-bar {
    background: linear-gradient(90deg, #4facfe, #43e97b);
}
</style>