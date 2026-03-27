<?php
/**
 * إضافة مادة دراسية جديدة - منصة همّة التوجيهي
 * Add New Subject - Himma Tawjihi Platform
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// التحقق من تسجيل الدخول والدور
if (!is_logged_in() || !has_role('teacher')) {
    redirect('../auth/login.php');
}

$db = new Database();
$conn = $db->connect();
$user_id = $_SESSION['user_id'];

$message = '';
$error = '';

// معالجة إرسال النموذج
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $duration_weeks = intval($_POST['duration_weeks']);
    $category = trim($_POST['category']);
    $level = trim($_POST['level']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // التحقق من صحة البيانات
    if (empty($name) || empty($description) || $price <= 0 || $duration_weeks <= 0) {
        $error = 'يرجى ملء جميع الحقول المطلوبة بشكل صحيح';
    } else {
        try {
            $stmt = $conn->prepare("
                INSERT INTO subjects (teacher_id, name, description, price, duration_weeks, category, level, is_active, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            if ($stmt->execute([$user_id, $name, $description, $price, $duration_weeks, $category, $level, $is_active])) {
                $message = 'تم إضافة المادة بنجاح!';
                // إعادة تعيين النموذج
                $_POST = array();
            } else {
                $error = 'حدث خطأ أثناء إضافة المادة';
            }
        } catch (PDOException $e) {
            $error = 'خطأ في قاعدة البيانات: ' . $e->getMessage();
        }
    }
}

$page_title = 'إضافة مادة دراسية جديدة';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - منصة همّة التوجيهي</title>
    
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

        .navbar-nav .nav-link:hover {
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

        .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 15px 15px 0 0 !important;
            font-weight: 600;
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

        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .alert {
            border-radius: 10px;
            border: none;
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
                            <i class="fas fa-book"></i> موادي
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="../auth/logout.php">
                            <i class="fas fa-sign-out-alt"></i> تسجيل الخروج
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="mb-0">
                                <i class="fas fa-plus-circle me-2"></i>
                                إضافة مادة دراسية جديدة
                            </h4>
                        </div>
                        <div class="card-body">
                            <?php if ($message): ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <?php echo $message; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($error): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    <?php echo $error; ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="name" class="form-label">اسم المادة *</label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="category" class="form-label">الفرع *</label>
                                        <select class="form-select" id="category" name="category" required>
                                            <option value="">اختر الفرع</option>
                                            <option value="scientific" <?php echo (($_POST['category'] ?? '') === 'scientific') ? 'selected' : ''; ?>>علمي</option>
                                            <option value="literary" <?php echo (($_POST['category'] ?? '') === 'literary') ? 'selected' : ''; ?>>أدبي</option>
                                            <option value="languages" <?php echo (($_POST['category'] ?? '') === 'languages') ? 'selected' : ''; ?>>لغات</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label">وصف المادة *</label>
                                    <textarea class="form-control" id="description" name="description" rows="4" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                </div>

                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="price" class="form-label">السعر ( الشيكل ) *</label>
                                        <input type="number" class="form-control" id="price" name="price" 
                                               value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>" 
                                               min="0" step="0.01" required>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="duration_weeks" class="form-label">مدة الكورس (أسابيع) *</label>
                                        <input type="number" class="form-control" id="duration_weeks" name="duration_weeks" 
                                               value="<?php echo htmlspecialchars($_POST['duration_weeks'] ?? ''); ?>" 
                                               min="1" required>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="level" class="form-label">المستوى *</label>
                                        <select class="form-select" id="level" name="level" required>
                                            <option value="">اختر المستوى</option>
                                            <option value="beginner" <?php echo (($_POST['level'] ?? '') === 'beginner') ? 'selected' : ''; ?>>مبتدئ</option>
                                            <option value="intermediate" <?php echo (($_POST['level'] ?? '') === 'intermediate') ? 'selected' : ''; ?>>متوسط</option>
                                            <option value="advanced" <?php echo (($_POST['level'] ?? '') === 'advanced') ? 'selected' : ''; ?>>متقدم</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                               <?php echo isset($_POST['is_active']) ? 'checked' : 'checked'; ?>>
                                        <label class="form-check-label" for="is_active">
                                            تفعيل المادة (متاحة للتسجيل)
                                        </label>
                                    </div>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>
                                        حفظ المادة
                                    </button>
                                    <a href="dashboard.php" class="btn btn-secondary">
                                        <i class="fas fa-arrow-left me-2"></i>
                                        العودة
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>