<?php
/**
 * لوحة تحكم الأدمن المُصلحة
 * منصة همة التوجيهي
 */

session_start();
require_once '../config/database.php';

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

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$conn = getDBConnection();

// إنشاء الجداول المطلوبة إذا لم تكن موجودة
try {
    // إنشاء جدول المناطق
    $conn->exec("
        CREATE TABLE IF NOT EXISTS app_d2335_regions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description TEXT DEFAULT NULL,
            code VARCHAR(10) DEFAULT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_name (name),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // إنشاء جدول طلبات المعلمين
    $conn->exec("
        CREATE TABLE IF NOT EXISTS app_d2335_teacher_applications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            teacher_name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            phone VARCHAR(20) DEFAULT NULL,
            region_id INT DEFAULT NULL,
            directorate VARCHAR(100) DEFAULT NULL,
            subject_specialization VARCHAR(200) NOT NULL,
            experience_years INT DEFAULT 0,
            qualifications TEXT DEFAULT NULL,
            status ENUM('pending', 'approved', 'rejected', 'under_review') DEFAULT 'pending',
            teacher_user_id INT DEFAULT NULL,
            review_notes TEXT DEFAULT NULL,
            submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            reviewed_by INT DEFAULT NULL,
            reviewed_at TIMESTAMP NULL DEFAULT NULL,
            INDEX idx_email (email),
            INDEX idx_status (status),
            INDEX idx_region (region_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // إنشاء جدول الوكلاء
    $conn->exec("
        CREATE TABLE IF NOT EXISTS region_deputies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            region_id INT NOT NULL,
            appointed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_active BOOLEAN DEFAULT TRUE,
            permissions JSON DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_region (region_id),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // إنشاء جدول سجل الأنشطة
    $conn->exec("
        CREATE TABLE IF NOT EXISTS admin_activity_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT NOT NULL,
            action_type VARCHAR(50) NOT NULL,
            action_description TEXT NOT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_admin (admin_id),
            INDEX idx_action (action_type),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // إدراج المناطق الأساسية إذا لم تكن موجودة
    $stmt = $conn->prepare("SELECT COUNT(*) FROM app_d2335_regions");
    $stmt->execute();
    $region_count = $stmt->fetchColumn();
    
    if ($region_count == 0) {
        $regions = [
            ['شمال غزة', 'محافظة شمال غزة', 'NG'],
            ['غزة', 'محافظة غزة', 'GZ'],
            ['الوسطى', 'محافظة الوسطى', 'MD'],
            ['خان يونس', 'محافظة خان يونس', 'KY'],
            ['رفح', 'محافظة رفح', 'RF']
        ];
        
        foreach ($regions as $region) {
            $stmt = $conn->prepare("
                INSERT INTO app_d2335_regions (name, description, code, is_active) 
                VALUES (?, ?, ?, 1)
            ");
            $stmt->execute($region);
        }
    }
    
} catch (Exception $e) {
    error_log("Database setup error: " . $e->getMessage());
}

// الحصول على الإحصائيات
try {
    // إحصائيات المستخدمين
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN role = 'teacher' THEN 1 ELSE 0 END) as teachers,
            SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) as students,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users
        FROM users
    ");
    $stmt->execute();
    $user_stats = $stmt->fetch();
    
    // إحصائيات طلبات المعلمين - استخدام الجدول الصحيح
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as under_review,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM app_d2335_teacher_applications
    ");
    $stmt->execute();
    $application_stats = $stmt->fetch();
    
    // إحصائيات الوكلاء
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active
        FROM region_deputies
    ");
    $stmt->execute();
    $deputy_stats = $stmt->fetch();
    
    // إحصائيات المناطق
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active
        FROM app_d2335_regions
    ");
    $stmt->execute();
    $region_stats = $stmt->fetch();
    
    // آخر الطلبات المعلقة
    $stmt = $conn->prepare("
        SELECT 
            ta.*,
            r.name as region_name
        FROM app_d2335_teacher_applications ta
        LEFT JOIN app_d2335_regions r ON ta.region_id = r.id
        WHERE ta.status = 'pending'
        ORDER BY ta.submitted_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $pending_applications = $stmt->fetchAll();
    
    // آخر الأنشطة
    $stmt = $conn->prepare("
        SELECT 
            aal.*,
            u.full_name as admin_name
        FROM admin_activity_log aal
        JOIN users u ON aal.admin_id = u.id
        ORDER BY aal.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recent_activities = $stmt->fetchAll();
    
} catch (Exception $e) {
    $error_message = 'حدث خطأ في جلب البيانات: ' . $e->getMessage();
    // تعيين قيم افتراضية في حالة الخطأ
    $user_stats = ['total' => 0, 'teachers' => 0, 'students' => 0, 'active_users' => 0];
    $application_stats = ['total' => 0, 'pending' => 0, 'under_review' => 0, 'approved' => 0, 'rejected' => 0];
    $deputy_stats = ['total' => 0, 'active' => 0];
    $region_stats = ['total' => 0, 'active' => 0];
    $pending_applications = [];
    $recent_activities = [];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - منصة همة التوجيهي</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #2563eb;
            --success-color: #16a34a;
            --warning-color: #d97706;
            --danger-color: #dc2626;
            --info-color: #0891b2;
        }
        
        body {
            background-color: #f8fafc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--primary-color), #1e40af);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border: none;
            height: 100%;
        }
        
        .stats-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .stats-label {
            color: #64748b;
            font-size: 0.95rem;
            font-weight: 500;
        }
        
        .stats-icon {
            font-size: 2.5rem;
            opacity: 0.2;
            position: absolute;
            left: 20px;
            top: 20px;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .card-header {
            background: white;
            border-bottom: 1px solid #e2e8f0;
            border-radius: 12px 12px 0 0 !important;
            padding: 20px 24px;
        }
        
        .quick-action-btn {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: block;
            height: 100%;
        }
        
        .quick-action-btn:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15);
        }
        
        .quick-action-icon {
            font-size: 2.5rem;
            margin-bottom: 12px;
        }
        
        .activity-item {
            padding: 12px;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-time {
            font-size: 0.85rem;
            color: #94a3b8;
        }
        
        .badge-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        .alert-info {
            background-color: #e0f2fe;
            border-color: #0891b2;
            color: #0e7490;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-graduation-cap me-2"></i>
                منصة همة التوجيهي
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text text-white me-3">
                    <i class="fas fa-user-shield me-2"></i>
                    المدير العام
                </span>
                <a href="../auth/logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt me-2"></i>
                    تسجيل الخروج
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col">
                <h1 class="h3 mb-2">
                    <i class="fas fa-tachometer-alt me-2 text-primary"></i>
                    لوحة التحكم الرئيسية
                </h1>
                <p class="text-muted">
                    مرحباً بك في لوحة التحكم - إدارة شاملة للمنصة
                </p>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            تم إنشاء الجداول المطلوبة تلقائياً. قد تحتاج لإعادة تحميل الصفحة لرؤية البيانات المحدثة.
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4 g-4">
            <div class="col-md-3">
                <div class="stats-card position-relative">
                    <i class="fas fa-users stats-icon text-primary"></i>
                    <div class="stats-number text-primary"><?php echo $user_stats['total']; ?></div>
                    <div class="stats-label">إجمالي المستخدمين</div>
                    <small class="text-muted">
                        <?php echo $user_stats['teachers']; ?> معلم | <?php echo $user_stats['students']; ?> طالب
                    </small>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stats-card position-relative">
                    <i class="fas fa-file-alt stats-icon text-warning"></i>
                    <div class="stats-number text-warning"><?php echo $application_stats['pending']; ?></div>
                    <div class="stats-label">طلبات معلقة</div>
                    <small class="text-muted">
                        <?php echo $application_stats['under_review']; ?> قيد المراجعة
                    </small>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stats-card position-relative">
                    <i class="fas fa-user-tie stats-icon text-success"></i>
                    <div class="stats-number text-success"><?php echo $deputy_stats['active']; ?></div>
                    <div class="stats-label">الوكلاء النشطون</div>
                    <small class="text-muted">
                        من أصل <?php echo $deputy_stats['total']; ?> وكيل
                    </small>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="stats-card position-relative">
                    <i class="fas fa-map-marked-alt stats-icon text-info"></i>
                    <div class="stats-number text-info"><?php echo $region_stats['active']; ?></div>
                    <div class="stats-label">المناطق النشطة</div>
                    <small class="text-muted">
                        من أصل <?php echo $region_stats['total']; ?> منطقة
                    </small>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4 g-4">
            <div class="col-md-3">
                <a href="manage_teacher_applications.php" class="quick-action-btn">
                    <div class="quick-action-icon text-primary">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <h5>إدارة طلبات المعلمين</h5>
                    <p class="text-muted mb-0">مراجعة وقبول الطلبات</p>
                    <?php if ($application_stats['pending'] > 0): ?>
                    <span class="badge badge-pending mt-2">
                        <?php echo $application_stats['pending']; ?> طلب معلق
                    </span>
                    <?php endif; ?>
                </a>
            </div>
            
            <div class="col-md-3">
                <a href="manage_deputies.php" class="quick-action-btn">
                    <div class="quick-action-icon text-success">
                        <i class="fas fa-users-cog"></i>
                    </div>
                    <h5>إدارة الوكلاء</h5>
                    <p class="text-muted mb-0">تعيين وإدارة الوكلاء</p>
                </a>
            </div>
            
            <div class="col-md-3">
                <a href="region_management.php" class="quick-action-btn">
                    <div class="quick-action-icon text-info">
                        <i class="fas fa-map-marked-alt"></i>
                    </div>
                    <h5>إدارة المناطق</h5>
                    <p class="text-muted mb-0">المناطق والمديريات</p>
                </a>
            </div>
            
            <div class="col-md-3">
                <a href="dashboard_fixed.php" class="quick-action-btn">
                    <div class="quick-action-icon text-warning">
                        <i class="fas fa-cog"></i>
                    </div>
                    <h5>الإعدادات العامة</h5>
                    <p class="text-muted mb-0">إدارة النظام</p>
                </a>
            </div>
        </div>

        <div class="row g-4">
            <!-- Pending Applications -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-clock me-2"></i>
                            طلبات المعلمين المعلقة
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($pending_applications)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-check-circle fa-3x mb-3 d-block"></i>
                            <p>لا توجد طلبات معلقة</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($pending_applications as $app): ?>
                        <div class="activity-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong><?php echo htmlspecialchars($app['teacher_name']); ?></strong>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($app['subject_specialization']); ?> - 
                                        <?php echo htmlspecialchars($app['region_name'] ?? 'غير محدد'); ?>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <div class="activity-time">
                                        <?php echo date('Y-m-d', strtotime($app['submitted_at'])); ?>
                                    </div>
                                    <a href="manage_teacher_applications.php" class="btn btn-sm btn-outline-primary mt-1">
                                        مراجعة
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-history me-2"></i>
                            آخر الأنشطة
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($recent_activities)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                            <p>لا توجد أنشطة حديثة</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($recent_activities as $activity): ?>
                        <div class="activity-item">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <strong><?php echo htmlspecialchars($activity['admin_name']); ?></strong>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($activity['action_description']); ?>
                                    </small>
                                </div>
                                <div class="activity-time">
                                    <?php echo date('Y-m-d H:i', strtotime($activity['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>