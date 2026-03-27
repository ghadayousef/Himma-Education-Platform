<?php
/**
 * رأس صفحات المعلم - منصة همّة التوجيهي
 * Teacher Header - Himma Tawjihi Platform
 */

// بدء السيشن إن لم يكن قد بدأ
if (!isset($_SESSION)) { session_start(); }

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

// التحقق من صلاحية المعلم
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../home/index.php');
    exit();
}

// تضمين مكونات الهيدر
require_once __DIR__ . '/header_components.php';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'لوحة المعلم'; ?> - منصة همّة التوجيهي</title>
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
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> لوحة التحكم
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'subjects.php' ? 'active' : ''; ?>" href="subjects.php">
                            <i class="fas fa-book"></i> إدارة المواد
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'quiz_system.php' ? 'active' : ''; ?>" href="quiz_system.php">
                            <i class="fas fa-clipboard-list"></i> إضافة اختبار
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'students.php' ? 'active' : ''; ?>" href="students.php">
                            <i class="fas fa-users"></i> الطلاب
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <?php
                        echo renderChatAndNotificationIcons($_SESSION['user_id'], $_SESSION['role']);
                    ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['full_name'] ?? ''); ?>
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
        <?php
        // عرض رسائل التنبيه
        if (isset($_SESSION['alert'])) {
            $alert = $_SESSION['alert'];
            $alert_class = $alert['type'] === 'error' ? 'alert-danger' : 'alert-' . $alert['type'];
            echo '<div class="alert ' . $alert_class . ' alert-dismissible fade show" role="alert">';
            echo htmlspecialchars($alert['message']);
            echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
            echo '</div>';
            unset($_SESSION['alert']);
        }
        ?>