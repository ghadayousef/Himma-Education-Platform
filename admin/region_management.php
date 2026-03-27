<?php
/**
 * إدارة المناطق - منصة همة التوجيهي
 * صفحة إدارة المناطق (للمدير العام فقط)
 */

// تعريف دالة الاتصال بقاعدة البيانات (تأكد من صحة المعلومات أدناه)
function getDBConnection() {
    $host = 'localhost';
    $db   = 'himma_tawjihi';
    $user = 'root';
    $pass = '';
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        return new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        throw new Exception('فشل الاتصال بقاعدة البيانات: ' . $e->getMessage());
    }
}

session_start();
require_once '../config/database.php';
require_once '../includes/hierarchy_functions.php';

// التحقق من تسجيل الدخول والصلاحيات
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role_info = get_user_hierarchy_role($user_id);

if ($user_role_info['role'] !== 'super_admin') {
    header("Location: dashboard.php");
    exit();
}

// معالجة الطلبات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        $conn = getDBConnection();
        
        switch ($action) {
            case 'create_region':
                $name = sanitize_input($_POST['name']);
                $description = sanitize_input($_POST['description']);
                $code = strtoupper(sanitize_input($_POST['code']));
                
                $stmt = $conn->prepare("
                    INSERT INTO app_d2335_regions (name, description, code) 
                    VALUES (?, ?, ?)
                ");
                
                if ($stmt->execute([$name, $description, $code])) {
                    $_SESSION['success_message'] = 'تم إنشاء المنطقة بنجاح';
                } else {
                    $_SESSION['error_message'] = 'حدث خطأ أثناء إنشاء المنطقة';
                }
                break;
                
            case 'update_region':
                $region_id = (int)$_POST['region_id'];
                $name = sanitize_input($_POST['name']);
                $description = sanitize_input($_POST['description']);
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                $stmt = $conn->prepare("
                    UPDATE app_d2335_regions 
                    SET name = ?, description = ?, is_active = ?
                    WHERE id = ?
                ");
                
                if ($stmt->execute([$name, $description, $is_active, $region_id])) {
                    $_SESSION['success_message'] = 'تم تحديث المنطقة بنجاح';
                } else {
                    $_SESSION['error_message'] = 'حدث خطأ أثناء تحديث المنطقة';
                }
                break;
                
            case 'assign_manager':
                $region_id = (int)$_POST['region_id'];
                $manager_id = (int)$_POST['manager_id'];
                
                // التحقق من عدم وجود تعيين مسبق
                $stmt = $conn->prepare("
                    SELECT id FROM app_d2335_region_managers 
                    WHERE user_id = ? AND region_id = ? AND is_active = 1
                ");
                $stmt->execute([$manager_id, $region_id]);
                
                if (!$stmt->fetch()) {
                    // تعيين المدير الجديد
                    $stmt = $conn->prepare("
                        INSERT INTO app_d2335_region_managers 
                        (user_id, region_id, assigned_by, permissions) 
                        VALUES (?, ?, ?, ?)
                    ");
                    
                    $default_permissions = json_encode([
                        'manage_teachers' => true,
                        'approve_applications' => true,
                        'manage_deputies' => true,
                        'view_reports' => true
                    ]);
                    
                    if ($stmt->execute([$manager_id, $region_id, $user_id, $default_permissions])) {
                        // تحديث جدول المناطق
                        $stmt = $conn->prepare("UPDATE app_d2335_regions SET manager_id = ? WHERE id = ?");
                        $stmt->execute([$manager_id, $region_id]);
                        
                        $_SESSION['success_message'] = 'تم تعيين مدير المنطقة بنجاح';
                    } else {
                        $_SESSION['error_message'] = 'حدث خطأ أثناء تعيين المدير';
                    }
                } else {
                    $_SESSION['error_message'] = 'المستخدم معين بالفعل كمدير في هذه المنطقة';
                }
                break;
                
            case 'create_directorate':
                $region_id = (int)$_POST['region_id'];
                $directorate_name = sanitize_input($_POST['directorate_name']);
                $directorate_code = strtoupper(sanitize_input($_POST['directorate_code']));
                
                $stmt = $conn->prepare("
                    INSERT INTO app_d2335_directorates (name, code, region_id) 
                    VALUES (?, ?, ?)
                ");
                
                if ($stmt->execute([$directorate_name, $directorate_code, $region_id])) {
                    $_SESSION['success_message'] = 'تم إضافة المديرية بنجاح';
                } else {
                    $_SESSION['error_message'] = 'حدث خطأ أثناء إضافة المديرية';
                }
                break;
        }
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'حدث خطأ: ' . $e->getMessage();
    }
    
    header("Location: region_management.php");
    exit();
}

// الحصول على البيانات
$regions = get_all_regions(false); // جميع المناطق بما في ذلك غير النشطة
$region_managers = get_region_managers();

// الحصول على المديريات لكل منطقة
try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT d.*, r.name as region_name 
        FROM app_d2335_directorates d
        JOIN app_d2335_regions r ON d.region_id = r.id
        WHERE d.is_active = 1
        ORDER BY r.name, d.name
    ");
    $stmt->execute();
    $directorates = $stmt->fetchAll();
    
    // تجميع المديريات حسب المنطقة
    $directorates_by_region = [];
    foreach ($directorates as $directorate) {
        $directorates_by_region[$directorate['region_id']][] = $directorate;
    }
} catch (Exception $e) {
    $directorates_by_region = [];
}

// الحصول على المستخدمين المؤهلين لإدارة المناطق
try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT id, full_name, email 
        FROM users 
        WHERE role IN ('admin', 'teacher') 
        AND is_active = 1
        ORDER BY full_name
    ");
    $stmt->execute();
    $potential_managers = $stmt->fetchAll();
} catch (Exception $e) {
    $potential_managers = [];
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المناطق - منصة همة التوجيهي</title>
    
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
            background-color: var(--bg-color);
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary-color), #1e40af);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            background: var(--card-color);
        }

        .card-header {
            background: var(--card-color);
            border-bottom: 1px solid #e2e8f0;
            border-radius: 12px 12px 0 0 !important;
            padding: 20px 24px;
        }

        .table th {
            background-color: #f1f5f9;
            border: none;
            font-weight: 600;
            color: var(--secondary-color);
            padding: 16px;
        }

        .table td {
            border: none;
            padding: 16px;
            vertical-align: middle;
        }

        .btn-action {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 500;
            border: none;
            transition: all 0.3s ease;
            margin: 2px;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-active {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-inactive {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), #1e40af);
            color: white;
            border-radius: 12px 12px 0 0;
        }

        .modal-content {
            border-radius: 12px;
            border: none;
        }

        .alert {
            border-radius: 12px;
            border: none;
        }

        .stats-card {
            background: var(--card-color);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: none;
            transition: transform 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-2px);
        }

        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .directorate-list {
            background: #f8fafc;
            border-radius: 8px;
            padding: 12px;
            margin-top: 8px;
        }

        .directorate-item {
            display: inline-block;
            background: #e2e8f0;
            color: #475569;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            margin: 2px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="
            dashboard.php">
                <i class="fas fa-arrow-right me-2"></i>
                العودة للوحة التحكم
            </a>
            
            <div class="navbar-nav ms-auto">
                <span class="navbar-text text-white">
                    <i class="fas fa-user-shield me-2"></i>
                    المدير العام
                </span>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col">
                <h1 class="h3 mb-2">
                    <i class="fas fa-map-marked-alt me-2 text-primary"></i>
                    إدارة المناطق والمديريات
                </h1>
                <p class="text-muted">
                    إدارة المناطق التعليمية الأربع في غزة والمديريات التابعة لها
                </p>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#createRegionModal">
                    <i class="fas fa-plus me-2"></i>
                    إضافة منطقة جديدة
                </button>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createDirectorateModal">
                    <i class="fas fa-building me-2"></i>
                    إضافة مديرية
                </button>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number text-primary"><?php echo count($regions); ?></div>
                    <div class="text-muted">إجمالي المناطق</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number text-success">
                        <?php echo count(array_filter($regions, fn($r) => $r['is_active'])); ?>
                    </div>
                    <div class="text-muted">المناطق النشطة</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number text-info"><?php echo count($region_managers); ?></div>
                    <div class="text-muted">المدراء المعينون</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number text-warning">
                        <?php echo count($directorates); ?>
                    </div>
                    <div class="text-muted">إجمالي المديريات</div>
                </div>
            </div>
        </div>

        <!-- Regions Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    قائمة المناطق والمديريات
                </h5>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>اسم المنطقة</th>
                            <th>الكود</th>
                            <th>الوصف</th>
                            <th>مدير المنطقة</th>
                            <th>المديريات التابعة</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($regions as $region): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($region['name']); ?></strong>
                            </td>
                            <td>
                                <span class="badge bg-secondary"><?php echo $region['code']; ?></span>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($region['description'] ?? 'لا يوجد وصف'); ?>
                            </td>
                            <td>
                                <?php if ($region['manager_name']): ?>
                                    <i class="fas fa-user-tie text-primary me-2"></i>
                                    <?php echo htmlspecialchars($region['manager_name']); ?>
                                <?php else: ?>
                                    <span class="text-muted">غير معين</span>
                                    <button class="btn btn-outline-primary btn-sm ms-2" 
                                            onclick="assignManager(<?php echo $region['id']; ?>)">
                                        تعيين مدير
                                    </button>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (isset($directorates_by_region[$region['id']])): ?>
                                    <div class="directorate-list">
                                        <?php foreach ($directorates_by_region[$region['id']] as $directorate): ?>
                                            <span class="directorate-item">
                                                <?php echo htmlspecialchars($directorate['name']); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo count($directorates_by_region[$region['id']]); ?> مديرية
                                    </small>
                                <?php else: ?>
                                    <span class="text-muted">لا توجد مديريات</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $region['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $region['is_active'] ? 'نشط' : 'غير نشط'; ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-outline-primary btn-action btn-sm" 
                                        onclick="editRegion(<?php echo htmlspecialchars(json_encode($region)); ?>)">
                                    <i class="fas fa-edit me-1"></i>
                                    تعديل
                                </button>
                                
                                <button class="btn btn-outline-info btn-action btn-sm" 
                                        onclick="viewRegionDetails(<?php echo $region['id']; ?>)">
                                    <i class="fas fa-eye me-1"></i>
                                    تفاصيل
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Create Region Modal -->
    <div class="modal fade" id="createRegionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>
                        إضافة منطقة جديدة
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="create_region">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">اسم المنطقة</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">كود المنطقة</label>
                            <input type="text" name="code" class="form-control" 
                                   placeholder="مثال: GAZA, NORTH" required>
                            <div class="form-text">كود فريد للمنطقة (أحرف إنجليزية فقط)</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">الوصف</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">إضافة المنطقة</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Create Directorate Modal -->
    <div class="modal fade" id="createDirectorateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-building me-2"></i>
                        إضافة مديرية جديدة
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="create_directorate">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">المنطقة</label>
                            <select name="region_id" class="form-select" required>
                                <option value="">اختر المنطقة</option>
                                <?php foreach ($regions as $region): ?>
                                    <?php if ($region['is_active']): ?>
                                    <option value="<?php echo $region['id']; ?>">
                                        <?php echo htmlspecialchars($region['name']); ?>
                                    </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">اسم المديرية</label>
                            <input type="text" name="directorate_name" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">كود المديرية</label>
                            <input type="text" name="directorate_code" class="form-control" 
                                   placeholder="مثال: GAZA_DIR" required>
                            <div class="form-text">كود فريد للمديرية (أحرف إنجليزية فقط)</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-success">إضافة المديرية</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Region Modal -->
    <div class="modal fade" id="editRegionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>
                        تعديل المنطقة
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="update_region">
                    <input type="hidden" name="region_id" id="edit_region_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">اسم المنطقة</label>
                            <input type="text" name="name" id="edit_region_name" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">الوصف</label>
                            <textarea name="description" id="edit_region_description" class="form-control" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" id="edit_region_active" class="form-check-input">
                                <label class="form-check-label" for="edit_region_active">
                                    منطقة نشطة
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Assign Manager Modal -->
    <div class="modal fade" id="assignManagerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-tie me-2"></i>
                        تعيين مدير المنطقة
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="assign_manager">
                    <input type="hidden" name="region_id" id="assign_region_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">اختيار المدير</label>
                            <select name="manager_id" class="form-select" required>
                                <option value="">اختر المدير</option>
                                <?php foreach ($potential_managers as $manager): ?>
                                <option value="<?php echo $manager['id']; ?>">
                                    <?php echo htmlspecialchars($manager['full_name']); ?> 
                                    (<?php echo htmlspecialchars($manager['email']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            سيتم منح المدير الجديد جميع الصلاحيات اللازمة لإدارة المنطقة
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">تعيين المدير</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function editRegion(region) {
            document.getElementById('edit_region_id').value = region.id;
            document.getElementById('edit_region_name').value = region.name;
            document.getElementById('edit_region_description').value = region.description || '';
            document.getElementById('edit_region_active').checked = region.is_active == 1;
            
            const modal = new bootstrap.Modal(document.getElementById('editRegionModal'));
            modal.show();
        }
        
        function assignManager(regionId) {
            document.getElementById('assign_region_id').value = regionId;
            
            const modal = new bootstrap.Modal(document.getElementById('assignManagerModal'));
            modal.show();
        }
        
        function viewRegionDetails(regionId) {
            // إعادة توجيه لصفحة تفاصيل المنطقة
            window.location.href = 'region_details.php?id=' + regionId;
        }
    </script>
</body>
</html>