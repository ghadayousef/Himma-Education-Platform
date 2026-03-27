<?php
/**
 * تعديل المادة الدراسية - منصة همّة التوجيهي
 * Edit Subject - Himma Tawjihi Platform
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// التحقق من تسجيل الدخول كمعلم
if (!is_logged_in() || !has_role('teacher')) {
    redirect('../auth/login.php');
}

$db = new Database();
$conn = $db->connect();
$user_id = $_SESSION['user_id'];

$message = '';
$error = '';
$subject = null;

// جلب بيانات المادة للتعديل
if (isset($_GET['id'])) {
    $subject_id = intval($_GET['id']);
    
    try {
        $subject_stmt = $conn->prepare("SELECT * FROM subjects WHERE id = ? AND teacher_id = ?");
        $subject_stmt->execute([$subject_id, $user_id]);
        $subject = $subject_stmt->fetch();
        
        if (!$subject) {
            $error = 'المادة غير موجودة أو غير مصرح لك بتعديلها';
        }
    } catch (PDOException $e) {
        $error = 'خطأ في النظام: ' . $e->getMessage();
    }
} else {
    redirect('subjects.php');
}

// معالجة تحديث المادة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_subject'])) {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? '';
    $price = floatval($_POST['price'] ?? 0);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // التحقق من صحة البيانات
    if (empty($name) || empty($description) || empty($category)) {
        $error = 'جميع الحقول مطلوبة';
    } elseif ($price < 0) {
        $error = 'السعر يجب أن يكون رقماً موجباً';
    } else {
        try {
            // التحقق من عدم وجود مادة بنفس الاسم (باستثناء المادة الحالية)
            $check_stmt = $conn->prepare("SELECT id FROM subjects WHERE name = ? AND teacher_id = ? AND id != ?");
            $check_stmt->execute([$name, $user_id, $subject_id]);
            
            if ($check_stmt->fetch()) {
                $error = 'يوجد مادة أخرى بنفس الاسم';
            } else {
                // تحديث المادة
                $update_stmt = $conn->prepare("
                    UPDATE subjects 
                    SET name = ?, description = ?, category = ?, price = ?, is_active = ?, updated_at = NOW()
                    WHERE id = ? AND teacher_id = ?
                ");
                
                if ($update_stmt->execute([$name, $description, $category, $price, $is_active, $subject_id, $user_id])) {
                    $message = 'تم تحديث المادة بنجاح!';
                    // إعادة جلب البيانات المحدثة
                    $subject_stmt = $conn->prepare("SELECT * FROM subjects WHERE id = ? AND teacher_id = ?");
                    $subject_stmt->execute([$subject_id, $user_id]);
                    $subject = $subject_stmt->fetch();
                } else {
                    $error = 'حدث خطأ أثناء تحديث المادة';
                }
            }
        } catch (PDOException $e) {
            $error = 'خطأ في النظام: ' . $e->getMessage();
        }
    }
}

// جلب بيانات المعلم
$teacher_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$teacher_stmt->execute([$user_id]);
$teacher = $teacher_stmt->fetch();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل المادة - منصة همّة التوجيهي</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --accent-color: #f093fb;
            --success-color: #4facfe;
            --warning-color: #43e97b;
            --danger-color: #fa709a;
        }

        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #f8f9fc 0%, #e9ecef 100%);
            min-height: 100vh;
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            font-weight: 800;
            font-size: 1.5rem;
            color: white !important;
        }

        .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.9) !important;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .navbar-nav .nav-link:hover,
        .navbar-nav .nav-link.active {
            color: white !important;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
        }

        .main-content {
            padding: 2rem;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }

        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #495057);
            border: none;
            border-radius: 10px;
            font-weight: 600;
        }

        .alert {
            border-radius: 10px;
            border: none;
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../home/index.php">
                <i class="fas fa-graduation-cap"></i> همّة التوجيهي
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> لوحة التحكم
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="subjects.php">
                            <i class="fas fa-book"></i> إدارة المواد
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($teacher['full_name'] ?? 'المعلم'); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user"></i> الملف الشخصي</a></li>
                            <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <h2><i class="fas fa-edit"></i> تعديل المادة الدراسية</h2>
                <p class="mb-0">تحديث بيانات ومعلومات المادة</p>
            </div>

            <!-- Alerts -->
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($subject): ?>
            <!-- Edit Form -->
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-body p-4">
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="name" class="form-label">اسم المادة *</label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo htmlspecialchars($subject['name']); ?>" required>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="category" class="form-label">فئة المادة *</label>
                                        <select class="form-select" id="category" name="category" required>
                                            <option value="">اختر الفئة</option>
                                            <option value="scientific" <?php echo ($subject['category'] === 'scientific') ? 'selected' : ''; ?>>علمي</option>
                                            <option value="literary" <?php echo ($subject['category'] === 'literary') ? 'selected' : ''; ?>>أدبي</option>
                                            <option value="languages" <?php echo ($subject['category'] === 'languages') ? 'selected' : ''; ?>>لغات</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label">وصف المادة *</label>
                                    <textarea class="form-control" id="description" name="description" rows="4" required><?php echo htmlspecialchars($subject['description']); ?></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="price" class="form-label">سعر المادة (ش)</label>
                                        <input type="number" class="form-control" id="price" name="price" 
                                               value="<?php echo $subject['price']; ?>" min="0" step="0.01">
                                    </div>
                                    
                                    <div class="col-md-6 mb-3 d-flex align-items-end">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                                   <?php echo $subject['is_active'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="is_active">
                                                المادة نشطة ومتاحة للطلاب
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex gap-2 justify-content-end">
                                    <a href="subjects.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> إلغاء
                                    </a>
                                    <button type="submit" name="update_subject" class="btn btn-primary">
                                        <i class="fas fa-save"></i> حفظ التعديلات
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>