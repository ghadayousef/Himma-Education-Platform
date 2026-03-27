<?php
/**
 * نموذج طلب الالتحاق كمعلم - النسخة المُصلحة
 * منصة همّة التوجيهي
 */

session_start();

// دالة الاتصال بقاعدة البيانات
function getDBConnection() {
    $host = 'localhost';
    $db = 'himma_tawjihi';
    $user = 'root';
    $pass = '';
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    try {
        return new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        throw new Exception('فشل الاتصال بقاعدة البيانات: ' . $e->getMessage());
    }
}

$error = '';
$success = '';
$regions = [];

// الحصول على المناطق - تم إصلاح اسم الجدول
try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT id, name FROM app_d2335_regions WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $regions = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error fetching regions: " . $e->getMessage());
}

// معالجة النموذج
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $conn = getDBConnection();
        
        // جمع البيانات
        $teacher_name = trim($_POST['teacher_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $region_id = (int)($_POST['region_id'] ?? 0);
        $directorate = trim($_POST['directorate'] ?? '');
        $subject_specialization = trim($_POST['subject_specialization'] ?? '');
        $years_experience = (int)($_POST['years_experience'] ?? 0);
        $qualifications = trim($_POST['qualifications'] ?? '');
        
        // التحقق من البيانات
        if (empty($teacher_name) || empty($email) || empty($subject_specialization) || $region_id == 0) {
            throw new Exception('يرجى ملء جميع الحقول المطلوبة');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('البريد الإلكتروني غير صالح');
        }
        
        // التحقق من عدم وجود طلب سابق بنفس البريد
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM app_d2335_teacher_applications 
            WHERE email = ? AND status IN ('pending', 'under_review', 'approved')
        ");
        $stmt->execute([$email]);
        
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('يوجد طلب التحاق سابق بهذا البريد الإلكتروني');
        }
        
        // معالجة رفع الملف (CV) - تم إضافة صيغ الصور
        $cv_file = null;
        if (isset($_FILES['cv_file']) && $_FILES['cv_file']['error'] == 0) {
            $upload_dir = 'uploads/cv/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['cv_file']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
            
            if (in_array($file_extension, $allowed_extensions)) {
                $cv_file = $upload_dir . uniqid('cv_') . '.' . $file_extension;
                move_uploaded_file($_FILES['cv_file']['tmp_name'], $cv_file);
            } else {
                throw new Exception('صيغة الملف غير مدعومة. الصيغ المسموحة: PDF, DOC, DOCX, JPG, JPEG, PNG');
            }
        }
        
        // إدراج الطلب
        $stmt = $conn->prepare("
            INSERT INTO app_d2335_teacher_applications (
                teacher_name, email, phone, region_id, directorate, 
                subject_specialization, experience_years, qualifications, 
                cv_file, status, submitted_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        
        $result = $stmt->execute([
            $teacher_name,
            $email,
            $phone,
            $region_id,
            $directorate,
            $subject_specialization,
            $years_experience,
            $qualifications,
            $cv_file
        ]);
        
        if ($result) {
            $success = 'تم تقديم طلب الالتحاق بنجاح! سيتم مراجعته من قبل الإدارة وسنتواصل معك قريباً.';
            
            // إرسال إشعار للمدير
            try {
                $admin_stmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin' AND is_active = 1 LIMIT 1");
                $admin_stmt->execute();
                $admin = $admin_stmt->fetch();
                
                if ($admin) {
                    $notif_stmt = $conn->prepare("
                        INSERT INTO notifications (user_id, title, message, type, created_at)
                        VALUES (?, ?, ?, 'system', NOW())
                    ");
                    $notif_stmt->execute([
                        $admin['id'],
                        'طلب التحاق معلم جديد',
                        "تم تقديم طلب التحاق جديد من المعلم: $teacher_name ($email)"
                    ]);
                }
            } catch (Exception $e) {
                // تجاهل أخطاء الإشعارات
                error_log("Notification error: " . $e->getMessage());
            }
        }
        
    } catch (PDOException $e) {
        error_log("Database error in application form: " . $e->getMessage());
        $error = 'حدث خطأ في النظام، يرجى المحاولة لاحقاً';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طلب الالتحاق كمعلم - منصة همّة التوجيهي</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
        }
        
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }
        
        .application-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .application-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: transform 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .section-title {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 3px solid var(--primary-color);
        }
        
        .required-mark {
            color: #dc3545;
            font-weight: bold;
        }
        
        .file-info {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="application-container">
            <div class="application-card">
                <div class="text-center mb-4">
                    <i class="fas fa-chalkboard-teacher fa-4x text-primary mb-3"></i>
                    <h1 class="h2 text-primary mb-2">طلب الالتحاق كمعلم</h1>
                    <p class="text-muted">انضم إلى فريق منصة همّة التوجيهي التعليمية</p>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                    <div class="text-center mt-4">
                        <p class="mb-3">ماذا تريد أن تفعل الآن؟</p>
                        <a href="auth/login.php" class="btn btn-primary me-2">
                            <i class="fas fa-sign-in-alt me-2"></i>تسجيل الدخول
                        </a>
                        <a href="home/index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-home me-2"></i>الصفحة الرئيسية
                        </a>
                    </div>
                <?php else: ?>
                
                <form method="POST" enctype="multipart/form-data" id="applicationForm">
                    <!-- المعلومات الشخصية -->
                    <h3 class="section-title">
                        <i class="fas fa-user me-2"></i>المعلومات الشخصية
                    </h3>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="teacher_name" class="form-label">
                                الاسم الكامل <span class="required-mark">*</span>
                            </label>
                            <input type="text" class="form-control" id="teacher_name" name="teacher_name" 
                                   value="<?php echo htmlspecialchars($_POST['teacher_name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="email" class="form-label">
                                البريد الإلكتروني <span class="required-mark">*</span>
                            </label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                            <div class="form-text">سيتم استخدامه لإنشاء الحساب لاحقاً</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">رقم الهاتف</label>
                        <input type="tel" class="form-control" id="phone" name="phone" 
                               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" 
                               placeholder="مثال: 0599123456">
                    </div>
                    
                    <!-- المعلومات الجغرافية -->
                    <h3 class="section-title mt-4">
                        <i class="fas fa-map-marker-alt me-2"></i>المعلومات الجغرافية
                    </h3>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="region_id" class="form-label">
                                المنطقة <span class="required-mark">*</span>
                            </label>
                            <select class="form-select" id="region_id" name="region_id" required>
                                <option value="">اختر المنطقة</option>
                                <?php foreach ($regions as $region): ?>
                                    <option value="<?php echo $region['id']; ?>" 
                                            <?php echo (isset($_POST['region_id']) && $_POST['region_id'] == $region['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($region['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="directorate" class="form-label">المديرية / البلدة</label>
                            <input type="text" class="form-control" id="directorate" name="directorate" 
                                   value="<?php echo htmlspecialchars($_POST['directorate'] ?? ''); ?>" 
                                   placeholder="مثال: جباليا، خان يونس، رفح">
                        </div>
                    </div>
                    
                    <!-- المعلومات الأكاديمية -->
                    <h3 class="section-title mt-4">
                        <i class="fas fa-graduation-cap me-2"></i>المعلومات الأكاديمية والمهنية
                    </h3>
                    
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label for="subject_specialization" class="form-label">
                                التخصص / المادة <span class="required-mark">*</span>
                            </label>
                            <input type="text" class="form-control" id="subject_specialization" name="subject_specialization" 
                                   value="<?php echo htmlspecialchars($_POST['subject_specialization'] ?? ''); ?>" 
                                   placeholder="مثال: رياضيات، فيزياء، لغة عربية" required>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="years_experience" class="form-label">
                                سنوات الخبرة <span class="required-mark">*</span>
                            </label>
                            <input type="number" class="form-control" id="years_experience" name="years_experience" 
                                   value="<?php echo htmlspecialchars($_POST['years_experience'] ?? '0'); ?>" 
                                   min="0" max="50" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="qualifications" class="form-label">المؤهلات العلمية</label>
                        <textarea class="form-control" id="qualifications" name="qualifications" rows="3" 
                                  placeholder="اذكر مؤهلاتك العلمية والشهادات..."><?php echo htmlspecialchars($_POST['qualifications'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label for="cv_file" class="form-label">السيرة الذاتية</label>
                        <input type="file" class="form-control" id="cv_file" name="cv_file" 
                               accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                        <div class="file-info">
                            <i class="fas fa-info-circle me-1"></i>
                            الصيغ المسموحة: PDF, DOC, DOCX, JPG, JPEG, PNG (اختياري)
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>ملاحظة:</strong> بعد تقديم الطلب، ستتم مراجعته من قبل إدارة المنطقة. 
                        في حال الموافقة، ستتمكن من إنشاء حسابك وتسجيل الدخول للمنصة.
                    </div>
                    
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-paper-plane me-2"></i>تقديم الطلب
                        </button>
                    </div>
                </form>
                
                <?php endif; ?>
                
                <div class="text-center mt-4">
                    <p class="text-muted">
                        لديك حساب بالفعل؟ 
                        <a href="auth/login.php" class="text-primary">تسجيل الدخول</a>
                    </p>
                    <p class="text-muted">
                        <a href="home/index.php" class="text-primary">
                            <i class="fas fa-arrow-right me-1"></i>العودة للصفحة الرئيسية
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        document.getElementById('applicationForm')?.addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('يرجى إدخال بريد إلكتروني صحيح');
                return false;
            }
            
            const region = document.getElementById('region_id').value;
            if (!region) {
                e.preventDefault();
                alert('يرجى اختيار المنطقة');
                return false;
            }
            
            // Validate file size (max 5MB)
            const fileInput = document.getElementById('cv_file');
            if (fileInput.files.length > 0) {
                const fileSize = fileInput.files[0].size / 1024 / 1024; // in MB
                if (fileSize > 5) {
                    e.preventDefault();
                    alert('حجم الملف يجب أن لا يتجاوز 5 ميجابايت');
                    return false;
                }
            }
        });
    </script>
</body>
</html>