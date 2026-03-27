<?php
/**
 * الملف الشخصي للطالب - منصة همّة التوجيهي
 * Student Profile - Himma Tawjihi Platform
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// التحقق من تسجيل الدخول كطالب
if (!is_logged_in() || !has_role('student')) {
    redirect('../auth/login.php');
}

$db = new Database();
$conn = $db->connect();
$user_id = $_SESSION['user_id'];

// التأكد من وجود جدول user_profiles
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS user_profiles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            date_of_birth DATE NULL,
            gender ENUM('male', 'female') NULL,
            nationality VARCHAR(100) NULL,
            address TEXT NULL,
            city VARCHAR(100) NULL,
            emergency_contact_name VARCHAR(100) NULL,
            emergency_contact_phone VARCHAR(20) NULL,
            education_level VARCHAR(50) NULL,
            specialization VARCHAR(100) NULL,
            years_of_experience INT DEFAULT 0,
            qualifications TEXT NULL,
            interests TEXT NULL,
            profile_completion_percentage INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_profile (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Exception $e) {
    // الجدول موجود بالفعل
}

// جلب بيانات المستخدم الحالية
$user_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch();

if (!$user) {
    redirect('../auth/login.php');
}

// جلب بيانات الملف الشخصي إن وجدت
$profile_stmt = $conn->prepare("SELECT * FROM user_profiles WHERE user_id = ?");
$profile_stmt->execute([$user_id]);
$profile = $profile_stmt->fetch();

// جلب إحصائيات الطالب
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(DISTINCT e.subject_id) as enrolled_subjects,
        COALESCE(AVG(e.progress_percentage), 0) as avg_progress,
        COUNT(DISTINCT qr.quiz_id) as completed_quizzes,
        COALESCE(AVG(qr.score), 0) as avg_quiz_score
    FROM enrollments e
    LEFT JOIN quiz_results qr ON e.user_id = qr.user_id
    WHERE e.user_id = ? AND e.status = 'active'
");
$stats_stmt->execute([$user_id]);
$stats = $stats_stmt->fetch();

// جلب المواد المسجل بها الطالب
$subjects_stmt = $conn->prepare("
    SELECT s.name, s.category, e.progress_percentage, e.enrollment_date,
           u.full_name as teacher_name
    FROM enrollments e
    INNER JOIN subjects s ON e.subject_id = s.id
    INNER JOIN users u ON s.teacher_id = u.id
    WHERE e.user_id = ? AND e.status = 'active'
    ORDER BY e.enrollment_date DESC
    LIMIT 5
");
$subjects_stmt->execute([$user_id]);
$enrolled_subjects = $subjects_stmt->fetchAll();

$success_message = '';
$error_message = '';

// معالجة تحديث البيانات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // تحديث بيانات المستخدم الأساسية
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        
        // التحقق من صحة البيانات
        if (empty($full_name) || empty($email)) {
            throw new Exception('الاسم الكامل والبريد الإلكتروني مطلوبان');
        }
        
        // التحقق من عدم تكرار البريد الإلكتروني
        $email_check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $email_check->execute([$email, $user_id]);
        if ($email_check->fetch()) {
            throw new Exception('البريد الإلكتروني مستخدم بالفعل');
        }
        
        // تحديث بيانات المستخدم
        $update_user = $conn->prepare("
            UPDATE users 
            SET full_name = ?, email = ?, phone = ?, bio = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $update_user->execute([$full_name, $email, $phone, $bio, $user_id]);
        
        // معالجة بيانات الملف الشخصي الإضافية
        $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
        $gender = !empty($_POST['gender']) ? $_POST['gender'] : null;
        $nationality = trim($_POST['nationality'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $emergency_contact_name = trim($_POST['emergency_contact_name'] ?? '');
        $emergency_contact_phone = trim($_POST['emergency_contact_phone'] ?? '');
        $education_level = trim($_POST['education_level'] ?? '');
        $interests = trim($_POST['interests'] ?? '');
        
        // حساب نسبة اكتمال الملف الشخصي
        $completion_fields = [
            $full_name, $email, $phone, $date_of_birth, $gender, 
            $nationality, $address, $city, $education_level
        ];
        $completed_fields = count(array_filter($completion_fields, function($field) {
            return !empty($field);
        }));
        $completion_percentage = round(($completed_fields / count($completion_fields)) * 100);
        
        // إدراج أو تحديث بيانات الملف الشخصي
        if ($profile) {
            $update_profile = $conn->prepare("
                UPDATE user_profiles 
                SET date_of_birth = ?, gender = ?, nationality = ?, address = ?, 
                    city = ?, emergency_contact_name = ?, emergency_contact_phone = ?, 
                    education_level = ?, interests = ?, profile_completion_percentage = ?, 
                    updated_at = NOW()
                WHERE user_id = ?
            ");
            $update_profile->execute([
                $date_of_birth, $gender, $nationality, $address, $city,
                $emergency_contact_name, $emergency_contact_phone, $education_level,
                $interests, $completion_percentage, $user_id
            ]);
        } else {
            $insert_profile = $conn->prepare("
                INSERT INTO user_profiles 
                (user_id, date_of_birth, gender, nationality, address, city, 
                 emergency_contact_name, emergency_contact_phone, education_level, 
                 interests, profile_completion_percentage)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $insert_profile->execute([
                $user_id, $date_of_birth, $gender, $nationality, $address, $city,
                $emergency_contact_name, $emergency_contact_phone, $education_level,
                $interests, $completion_percentage
            ]);
        }
        
        $success_message = 'تم تحديث الملف الشخصي بنجاح';
        
        // إعادة جلب البيانات المحدثة
        $user_stmt->execute([$user_id]);
        $user = $user_stmt->fetch();
        $profile_stmt->execute([$user_id]);
        $profile = $profile_stmt->fetch();
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

$page_title = 'الملف الشخصي';
include '../includes/student_header.php';
?>

<div class="container">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-user"></i> الملف الشخصي</h1>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Profile Summary -->
                <div class="col-md-4 mb-4">
                    <!-- Profile Completion -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-pie"></i> اكتمال الملف الشخصي</h5>
                        </div>
                        <div class="card-body text-center">
                            <div class="progress mb-3" style="height: 20px;">
                                <div class="progress-bar bg-success" role="progressbar" 
                                     style="width: <?php echo ($profile['profile_completion_percentage'] ?? 30); ?>%">
                                    <?php echo ($profile['profile_completion_percentage'] ?? 30); ?>%
                                </div>
                            </div>
                            <p class="text-muted">أكمل ملفك الشخصي للحصول على تجربة أفضل</p>
                        </div>
                    </div>

                    <!-- Academic Statistics -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-chart-bar"></i> الإحصائيات الأكاديمية</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6 mb-3">
                                    <div class="stat-item">
                                        <div class="stat-number text-primary"><?php echo (int)($stats['enrolled_subjects'] ?? 0); ?></div>
                                        <div class="stat-label">مادة مسجلة</div>
                                    </div>
                                </div>
                                <div class="col-6 mb-3">
                                    <div class="stat-item">
                                        <div class="stat-number text-success"><?php echo number_format((float)($stats['avg_progress'] ?? 0), 1); ?>%</div>
                                        <div class="stat-label">متوسط التقدم</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="stat-item">
                                        <div class="stat-number text-info"><?php echo (int)($stats['completed_quizzes'] ?? 0); ?></div>
                                        <div class="stat-label">اختبار مكتمل</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="stat-item">
                                        <div class="stat-number text-warning"><?php echo number_format((float)($stats['avg_quiz_score'] ?? 0), 1); ?>%</div>
                                        <div class="stat-label">متوسط الدرجات</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Enrolled Subjects -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-book"></i> موادي المسجلة</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($enrolled_subjects)): ?>
                                <p class="text-muted text-center">لم تسجل في أي مادة بعد</p>
                                <div class="text-center">
                                    <a href="subjects.php" class="btn btn-primary btn-sm">
                                        <i class="fas fa-plus"></i> تصفح المواد
                                    </a>
                                </div>
                            <?php else: ?>
                                <?php foreach ($enrolled_subjects as $subject): ?>
                                    <div class="subject-item mb-2 p-2 border rounded">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($subject['name']); ?></h6>
                                        <small class="text-muted">المعلم: <?php echo htmlspecialchars($subject['teacher_name']); ?></small>
                                        <div class="progress mt-1" style="height: 5px;">
                                            <div class="progress-bar" role="progressbar" 
                                                 style="width: <?php echo $subject['progress_percentage']; ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?php echo number_format($subject['progress_percentage'], 1); ?>% مكتمل</small>
                                    </div>
                                <?php endforeach; ?>
                                <div class="text-center mt-2">
                                    <a href="my_courses.php" class="btn btn-outline-primary btn-sm">
                                        عرض جميع موادي
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Profile Form -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-edit"></i> تحديث البيانات الشخصية</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <!-- Basic Information -->
                                <h6 class="text-primary mb-3"><i class="fas fa-user"></i> المعلومات الأساسية</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="full_name" class="form-label">الاسم الكامل *</label>
                                        <input type="text" class="form-control" id="full_name" name="full_name" 
                                               value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">البريد الإلكتروني *</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="phone" class="form-label">رقم الهاتف</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="date_of_birth" class="form-label">تاريخ الميلاد</label>
                                        <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                               value="<?php echo ($profile['date_of_birth'] ?? ''); ?>">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="gender" class="form-label">الجنس</label>
                                        <select class="form-select" id="gender" name="gender">
                                            <option value="">اختر الجنس</option>
                                            <option value="male" <?php echo (($profile['gender'] ?? '') === 'male') ? 'selected' : ''; ?>>ذكر</option>
                                            <option value="female" <?php echo (($profile['gender'] ?? '') === 'female') ? 'selected' : ''; ?>>أنثى</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="nationality" class="form-label">الجنسية</label>
                                        <input type="text" class="form-control" id="nationality" name="nationality" 
                                               value="<?php echo htmlspecialchars($profile['nationality'] ?? ''); ?>">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="bio" class="form-label">نبذة شخصية</label>
                                    <textarea class="form-control" id="bio" name="bio" rows="3" 
                                              placeholder="اكتب نبذة عن نفسك وأهدافك الأكاديمية..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                                </div>

                                <!-- Academic Information -->
                                <hr>
                                <h6 class="text-primary mb-3"><i class="fas fa-graduation-cap"></i> المعلومات الأكاديمية</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="education_level" class="form-label">المرحلة الدراسية</label>
                                        <select class="form-select" id="education_level" name="education_level">
                                            <option value="">اختر المرحلة الدراسية</option>
                                            <option value="grade_10" <?php echo (($profile['education_level'] ?? '') === 'grade_10') ? 'selected' : ''; ?>>الصف العاشر</option>
                                            <option value="grade_11" <?php echo (($profile['education_level'] ?? '') === 'grade_11') ? 'selected' : ''; ?>>الصف الحادي عشر</option>
                                            <option value="grade_12" <?php echo (($profile['education_level'] ?? '') === 'grade_12') ? 'selected' : ''; ?>>الصف الثاني عشر (التوجيهي)</option>
                                            <option value="university" <?php echo (($profile['education_level'] ?? '') === 'university') ? 'selected' : ''; ?>>جامعي</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="interests" class="form-label">الاهتمامات الأكاديمية</label>
                                    <textarea class="form-control" id="interests" name="interests" rows="2"
                                              placeholder="اذكر المواد والمجالات التي تهتم بها..."><?php echo htmlspecialchars($profile['interests'] ?? ''); ?></textarea>
                                </div>

                                <!-- Contact Information -->
                                <hr>
                                <h6 class="text-primary mb-3"><i class="fas fa-map-marker-alt"></i> معلومات الاتصال</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="city" class="form-label">المدينة</label>
                                        <input type="text" class="form-control" id="city" name="city" 
                                               value="<?php echo htmlspecialchars($profile['city'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="emergency_contact_name" class="form-label">اسم ولي الأمر</label>
                                        <input type="text" class="form-control" id="emergency_contact_name" name="emergency_contact_name" 
                                               value="<?php echo htmlspecialchars($profile['emergency_contact_name'] ?? ''); ?>">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="emergency_contact_phone" class="form-label">هاتف ولي الأمر</label>
                                        <input type="tel" class="form-control" id="emergency_contact_phone" name="emergency_contact_phone" 
                                               value="<?php echo htmlspecialchars($profile['emergency_contact_phone'] ?? ''); ?>">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="address" class="form-label">العنوان</label>
                                    <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($profile['address'] ?? ''); ?></textarea>
                                </div>

                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> حفظ التغييرات
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.progress {
    border-radius: 10px;
}

.card {
    border-radius: 15px;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    margin-bottom: 1rem;
}

.card-header {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
    border-radius: 15px 15px 0 0 !important;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    border: none;
    border-radius: 10px;
}

.form-control, .form-select {
    border-radius: 8px;
}

.text-primary {
    color: var(--primary-color) !important;
}

.stat-item {
    text-align: center;
}

.stat-number {
    font-size: 1.5rem;
    font-weight: bold;
}

.stat-label {
    font-size: 0.875rem;
    color: #6c757d;
}

.subject-item {
    background-color: #f8f9fa;
}

.subject-item:hover {
    background-color: #e9ecef;
}
</style>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>