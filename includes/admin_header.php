<?php
/**
 * رأس صفحات الإدارة - منصة همّة التوجيهي
 * Admin Header - Himma Tawjihi Platform
 */

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

// التحقق من صلاحية المدير
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../home/index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'لوحة الإدارة'; ?> - منصة همّة التوجيهي</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --accent-color: #f093fb;
            --success-color: #4facfe;
            --warning-color: #43e97b;
            --danger-color: #fa709a;
            --dark-color: #2c3e50;
            --light-color: #ecf0f1;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Cairo', sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 900;
            color: white !important;
        }

        .navbar-nav .nav-link {
            color: rgba(255, 255, 255, 0.9) !important;
            font-weight: 500;
        }

        .navbar-nav .nav-link:hover {
            color: white !important;
        }

        .sidebar {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            min-height: calc(100vh - 56px);
            position: fixed;
            top: 56px;
            right: 0;
            width: 250px;
            z-index: 1000;
            padding: 20px 0;
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 12px 20px;
            margin: 5px 15px;
            border-radius: 10px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .sidebar .nav-link i {
            margin-left: 10px;
            width: 20px;
        }

        .main-content {
            margin-right: 250px;
            padding: 20px;
            min-height: calc(100vh - 56px);
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 20px;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 15px 20px;
            font-weight: 600;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            border: none;
            border-radius: 25px;
            padding: 10px 25px;
            font-weight: 600;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .table {
            border-radius: 10px;
            overflow: hidden;
        }

        .table thead th {
            background: var(--light-color);
            border: none;
            font-weight: 600;
            color: var(--dark-color);
        }

        .badge {
            padding: 8px 12px;
            border-radius: 20px;
            font-weight: 500;
        }

        .alert {
            border-radius: 15px;
            border: none;
            padding: 15px 20px;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-right: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="../home/index.php">
                <i class="fas fa-graduation-cap"></i> همّة التوجيهي
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../home/index.php"><i class="fas fa-home"></i> الصفحة الرئيسية</a></li>
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user"></i> الملف الشخصي</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div style="margin-top: 56px;">
        <!-- Sidebar -->
        <nav class="sidebar">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard_fixed' ? 'active' : ''; ?>" href="dashboard_fixed">
                        <i class="fas fa-tachometer-alt"></i> لوحة التحكم
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" href="users.php">
                        <i class="fas fa-users"></i> إدارة المستخدمين
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'subjects.php' ? 'active' : ''; ?>" href="subjects.php">
                        <i class="fas fa-book"></i> إدارة المواد
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'lessons.php' ? 'active' : ''; ?>" href="lessons.php">
                        <i class="fas fa-play-circle"></i> إدارة الدروس
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'quizzes.php' ? 'active' : ''; ?>" href="quizzes.php">
                        <i class="fas fa-question-circle"></i> إدارة الاختبارات
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'enrollments.php' ? 'active' : ''; ?>" href="enrollments.php">
                        <i class="fas fa-user-graduate"></i> التسجيلات
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'statistics.php' ? 'active' : ''; ?>" href="statistics.php">
                        <i class="fas fa-chart-bar"></i> الإحصائيات
                    </a>
                </li>
            </ul>
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