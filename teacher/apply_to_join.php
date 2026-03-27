<?php
/**
 * نموذج طلب انضمام المعلم - منصة همة التوجيهي
 * صفحة تقديم طلب انضمام للمعلمين الجدد
 */

session_start();
require_once '../config/database.php';
require_once '../includes/hierarchy_functions.php';
require_once '../includes/functions.php';

// معالجة تقديم الطلب
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $teacher_name = sanitize_input($_POST['teacher_name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $phone = sanitize_input($_POST['phone'] ?? '');
    $region_id = (int)($_POST['region_id'] ?? 0);
    $directorate = sanitize_input($_POST['directorate'] ?? '');
    $subject_specialization = sanitize_input($_POST['subject_specialization'] ?? '');
    $experience_years = (int)($_POST['experience_years'] ?? 0);
    $qualifications = sanitize_input($_POST['qualifications'] ?? '');
    
    $errors = [];
    
    // التحقق من صحة البيانات
    if (empty($teacher_name)) {
        $errors[] = 'اسم المعلم مطلوب';
    }
    
    if (empty($email) || !validate_email($email)) {
        $errors[] = 'بريد إلكتروني صحيح مطلوب';
    }
    
    if ($region_id <= 0) {
        $errors[] = 'يجب اختيار المنطقة';
    }
    
    if (empty($directorate)) {
        $errors[] = 'المديرية مطلوبة';
    }
    
    if (empty($subject_specialization)) {
        $errors[] = 'التخصص مطلوب';
    }
    
    // التحقق من عدم وجود طلب سابق بنفس البريد الإلكتروني
    if (empty($errors) && $conn) {
        try {
            $stmt = $conn->prepare("SELECT id FROM app_d2335_teacher_applications WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $errors[] = 'يوجد طلب سابق بنفس البريد الإلكتروني';
            }
        } catch (Exception $e) {
            $errors[] = 'حدث خطأ أثناء التحقق من البيانات';
        }
    }
    
    // إدراج الطلب إذا لم توجد أخطاء
    if (empty($errors) && $conn) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO app_d2335_teacher_applications 
                (teacher_name, email, phone, region_id, directorate, subject_specialization, experience_years, qualifications)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([$teacher_name, $email, $phone, $region_id, $directorate, $subject_specialization, $experience_years, $qualifications])) {
                $_SESSION['success_message'] = 'تم تقديم طلب الانضمام بنجاح. سيتم مراجعته والرد عليك قريباً.';
                header("Location: apply_to_join.php");
                exit();
            } else {
                $errors[] = 'حدث خطأ أثناء تقديم الطلب';
            }
        } catch (Exception $e) {
            $errors[] = 'حدث خطأ أثناء تقديم الطلب: ' . $e->getMessage();
        }
    }
    
    // إضافة خطأ إذا لم يكن هناك اتصال بقاعدة البيانات
    if (!$conn && empty($errors)) {
        $errors[] = 'لا يوجد اتصال بقاعدة البيانات';
    }
}

// الحصول على قائمة المناطق
$regions = get_all_regions();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طلب انضمام معلم - منصة همة التوجيهي</title>
    
    <!-- Bootstrap RTL -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #64748b;
            --success-color: #16a34a;
            --warning-color: #d97706;
            --danger-color: #dc2626;
            --bg-color: #f8fafc;
            --card-color: #ffffff;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .application-container {
            background: var(--card-color);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            margin: 40px auto;
            max-width: 800px;
        }

        .application-header {
            background: linear-gradient(135deg, var(--primary-color), #1e40af);
            color: white;
            padding: 40px;
            text-align: center;
        }

        .application-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .application-header p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 0;
        }

        .application-form {
            padding: 40px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
            display: block;
        }

        .form-control, .form-select {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .btn-submit {
            background: linear-gradient(135deg, var(--primary-color), #1e40af);
            border: none;
            border-radius: 12px;
            padding: 15px 40px;
            font-size: 1.1rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.3);
        }

        .alert {
            border-radius: 12px;
            border: none;
            padding: 16px 20px;
            margin-bottom: 25px;
        }

        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
        }

        .alert-danger {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .form-text {
            color: var(--secondary-color);
            font-size: 0.9rem;
            margin-top: 5px;
        }

        .required {
            color: var(--danger-color);
        }

        .navbar {
            background: transparent !important;
            padding: 20px 0;
        }

        .navbar-brand {
            color: white !important;
            font-weight: 700;
            font-size: 1.25rem;
        }

        .navbar-nav .nav-link {
            color: white !important;
            font-weight: 500;
        }

        .specializations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }

        .specialization-option {
            padding: 10px;
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .specialization-option:hover {
            border-color: var(--primary-color);
            background: #eff6ff;
        }

        .specialization-option.selected {
            border-color: var(--primary-color);
            background: var(--primary-color);
            color: white;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="../home/index.php">
                <i class="fas fa-graduation-cap me-2"></i>
                منصة همة التوجيهي
            </a>
            
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../home/index.php">
                    <i class="fas fa-home me-2"></i>
                    الرئيسية
                </a>
                <a class="nav-link" href="../auth/login.php">
                    <i class="fas fa-sign-in-alt me-2"></i>
                    تسجيل الدخول
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="application-container">
            <!-- Header -->
            <div class="application-header">
                <h1>
                    <i class="fas fa-chalkboard-teacher me-3"></i>
                    طلب انضمام معلم
                </h1>
                <p>انضم إلى فريق المعلمين المتميزين في منصة همة التوجيهي</p>
            </div>

            <!-- Form -->
            <div class="application-form">
                <!-- Success Message -->
                <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                </div>
                <?php endif; ?>

                <!-- Error Messages -->
                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">
                                    الاسم الكامل <span class="required">*</span>
                                </label>
                                <input type="text" name="teacher_name" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['teacher_name'] ?? ''); ?>" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">
                                    البريد الإلكتروني <span class="required">*</span>
                                </label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">رقم الهاتف</label>
                                <input type="tel" name="phone" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">
                                    سنوات الخبرة <span class="required">*</span>
                                </label>
                                <select name="experience_years" class="form-select" required>
                                    <option value="">اختر سنوات الخبرة</option>
                                    <option value="0" <?php echo ($_POST['experience_years'] ?? '') == '0' ? 'selected' : ''; ?>>بدون خبرة</option>
                                    <option value="1" <?php echo ($_POST['experience_years'] ?? '') == '1' ? 'selected' : ''; ?>>سنة واحدة</option>
                                    <option value="2" <?php echo ($_POST['experience_years'] ?? '') == '2' ? 'selected' : ''; ?>>سنتان</option>
                                    <option value="3" <?php echo ($_POST['experience_years'] ?? '') == '3' ? 'selected' : ''; ?>>3 سنوات</option>
                                    <option value="4" <?php echo ($_POST['experience_years'] ?? '') == '4' ? 'selected' : ''; ?>>4 سنوات</option>
                                    <option value="5" <?php echo ($_POST['experience_years'] ?? '') == '5' ? 'selected' : ''; ?>>5 سنوات</option>
                                    <option value="6" <?php echo ($_POST['experience_years'] ?? '') == '6' ? 'selected' : ''; ?>>أكثر من 5 سنوات</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">
                                    المنطقة <span class="required">*</span>
                                </label>
                                <select name="region_id" class="form-select" required>
                                    <option value="">اختر المنطقة</option>
                                    <?php foreach ($regions as $region): ?>
                                    <option value="<?php echo $region['id']; ?>" 
                                            <?php echo ($_POST['region_id'] ?? '') == $region['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($region['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label">
                                    المديرية <span class="required">*</span>
                                </label>
                                <input type="text" name="directorate" class="form-control" 
                                       placeholder="مثال: مديرية التربية والتعليم - شمال غزة"
                                       value="<?php echo htmlspecialchars($_POST['directorate'] ?? ''); ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            التخصص <span class="required">*</span>
                        </label>
                        <input type="text" name="subject_specialization" class="form-control" 
                               placeholder="مثال: رياضيات، فيزياء، كيمياء، لغة عربية، لغة إنجليزية"
                               value="<?php echo htmlspecialchars($_POST['subject_specialization'] ?? ''); ?>" required>
                        <div class="form-text">حدد المادة أو المواد التي تتخصص في تدريسها</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">المؤهلات العلمية والخبرات</label>
                        <textarea name="qualifications" class="form-control" rows="4" 
                                  placeholder="اذكر مؤهلاتك العلمية وخبراتك في التدريس والدورات التي حضرتها..."><?php echo htmlspecialchars($_POST['qualifications'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn btn-submit">
                            <i class="fas fa-paper-plane me-2"></i>
                            تقديم طلب الانضمام
                        </button>
                    </div>

                    <div class="text-center mt-3">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            سيتم مراجعة طلبك والتواصل معك خلال 3-5 أيام عمل
                        </small>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // تحسين تجربة المستخدم
        document.addEventListener('DOMContentLoaded', function() {
            // تحديد التخصص
            const specializationInput = document.querySelector('input[name="subject_specialization"]');
            const commonSpecializations = [
                'رياضيات', 'فيزياء', 'كيمياء', 'أحياء', 'علوم الأرض',
                'لغة عربية', 'لغة إنجليزية', 'تاريخ', 'جغرافيا', 'تربية إسلامية',
                'حاسوب', 'تكنولوجيا المعلومات', 'فنون', 'موسيقى', 'رياضة'
            ];
            
            // إضافة اقتراحات للتخصص
            specializationInput.addEventListener('input', function() {
                // يمكن إضافة منطق الاقتراحات هنا
            });
            
            // التحقق من صحة النموذج قبل الإرسال
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        isValid = false;
                        field.classList.add('is-invalid');
                    } else {
                        field.classList.remove('is-invalid');
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert('يرجى ملء جميع الحقول المطلوبة');
                }
            });
        });
    </script>
</body>
</html>