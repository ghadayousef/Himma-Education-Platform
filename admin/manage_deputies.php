<?php
/**
 * إدارة الوكلاء - لمدراء المناطق
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
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'student';

// التحقق من الصلاحيات (مدير عام أو مدير منطقة)
$conn = getDBConnection();

// التحقق من كون المستخدم مدير عام
$is_super_admin = $user_role === 'admin';

// التحقق من كون المستخدم مدير منطقة
$stmt = $conn->prepare("
    SELECT rm.*, r.name as region_name 
    FROM region_managers rm 
    JOIN regions r ON rm.region_id = r.id
    WHERE rm.user_id = ? AND rm.is_active = 1
");
$stmt->execute([$user_id]);
$manager_info = $stmt->fetch();

if (!$is_super_admin && !$manager_info) {
    header("Location: ../index.php");
    exit();
}

// معالجة الطلبات
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        $conn->beginTransaction();
        
        if ($action === 'add_deputy') {
            // إضافة وكيل جديد
            $deputy_user_id = (int)$_POST['deputy_user_id'];
            $region_id = $is_super_admin ? (int)$_POST['region_id'] : $manager_info['region_id'];
            $directorate = $_POST['directorate'];
            $notes = $_POST['notes'] ?? '';
            
            // التحقق من عدم وجود تعيين سابق نشط
            $stmt = $conn->prepare("
                SELECT id FROM region_deputies 
                WHERE user_id = ? AND region_id = ? AND is_active = 1
            ");
            $stmt->execute([$deputy_user_id, $region_id]);
            
            if ($stmt->fetch()) {
                throw new Exception('هذا المستخدم معين بالفعل كوكيل في هذه المنطقة');
            }
            
            // الصلاحيات الافتراضية للوكيل
            $default_permissions = json_encode([
                'review_applications' => true,
                'manage_teachers' => true,
                'view_reports' => true
            ]);
            
            $stmt = $conn->prepare("
                INSERT INTO region_deputies 
                (user_id, region_id, directorate, assigned_by, permissions, notes, assigned_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $deputy_user_id,
                $region_id,
                $directorate,
                $user_id,
                $default_permissions,
                $notes
            ]);
            
            // تسجيل النشاط
            $stmt = $conn->prepare("
                INSERT INTO admin_activity_log 
                (admin_id, action_type, action_description, target_user_id, ip_address, created_at) 
                VALUES (?, 'add_deputy', ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $user_id,
                "تعيين وكيل جديد في المديرية: $directorate",
                $deputy_user_id,
                $_SERVER['REMOTE_ADDR']
            ]);
            
            // إرسال إشعار للوكيل
            $stmt = $conn->prepare("
                INSERT INTO notifications (user_id, title, message, type, created_at) 
                VALUES (?, ?, ?, 'info', NOW())
            ");
            $stmt->execute([
                $deputy_user_id,
                'تعيين كوكيل',
                "تم تعيينك كوكيل في المديرية: $directorate"
            ]);
            
            $_SESSION['success_message'] = 'تم إضافة الوكيل بنجاح';
            
        } elseif ($action === 'deactivate_deputy') {
            // إلغاء تفعيل وكيل
            $deputy_id = (int)$_POST['deputy_id'];
            
            $stmt = $conn->prepare("
                UPDATE region_deputies 
                SET is_active = 0, deactivated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$deputy_id]);
            
            // تسجيل النشاط
            $stmt = $conn->prepare("
                INSERT INTO admin_activity_log 
                (admin_id, action_type, action_description, ip_address, created_at) 
                VALUES (?, 'deactivate_deputy', ?, ?, NOW())
            ");
            $stmt->execute([
                $user_id,
                "إلغاء تفعيل وكيل",
                $_SERVER['REMOTE_ADDR']
            ]);
            
            $_SESSION['success_message'] = 'تم إلغاء تفعيل الوكيل';
            
        } elseif ($action === 'activate_deputy') {
            // إعادة تفعيل وكيل
            $deputy_id = (int)$_POST['deputy_id'];
            
            $stmt = $conn->prepare("
                UPDATE region_deputies 
                SET is_active = 1, deactivated_at = NULL 
                WHERE id = ?
            ");
            $stmt->execute([$deputy_id]);
            
            $_SESSION['success_message'] = 'تم إعادة تفعيل الوكيل';
        }
        
        $conn->commit();
        
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = 'حدث خطأ: ' . $e->getMessage();
    }
    
    header("Location: manage_deputies.php");
    exit();
}

// الحصول على الوكلاء
$where_clause = "";
$params = [];

if (!$is_super_admin && $manager_info) {
    $where_clause = "WHERE rd.region_id = ?";
    $params[] = $manager_info['region_id'];
}

$stmt = $conn->prepare("
    SELECT 
        rd.*,
        u.full_name as deputy_name,
        u.email as deputy_email,
        u.phone as deputy_phone,
        r.name as region_name,
        assigner.full_name as assigned_by_name
    FROM region_deputies rd
    JOIN users u ON rd.user_id = u.id
    JOIN regions r ON rd.region_id = r.id
    JOIN users assigner ON rd.assigned_by = assigner.id
    $where_clause
    ORDER BY rd.is_active DESC, rd.assigned_at DESC
");
$stmt->execute($params);
$deputies = $stmt->fetchAll();

// الحصول على المناطق (للمدير العام)
$regions = [];
if ($is_super_admin) {
    $stmt = $conn->prepare("SELECT id, name FROM regions WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $regions = $stmt->fetchAll();
}

// الحصول على المستخدمين المؤهلين ليكونوا وكلاء
$stmt = $conn->prepare("
    SELECT id, full_name, email 
    FROM users 
    WHERE role IN ('teacher', 'admin') AND is_active = 1
    AND id NOT IN (SELECT user_id FROM region_deputies WHERE is_active = 1)
    ORDER BY full_name
");
$stmt->execute();
$potential_deputies = $stmt->fetchAll();

// إحصائيات
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive
    FROM region_deputies
    " . ($is_super_admin ? "" : "WHERE region_id = ?")
);

if (!$is_super_admin && $manager_info) {
    $stmt->execute([$manager_info['region_id']]);
} else {
    $stmt->execute();
}
$stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الوكلاء - منصة همة التوجيهي</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #2563eb;
            --success-color: #16a34a;
            --warning-color: #d97706;
            --danger-color: #dc2626;
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
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
        }
        
        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .table th {
            background-color: #f1f5f9;
            font-weight: 600;
            border: none;
        }
        
        .table td {
            vertical-align: middle;
            border: none;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .status-active { background-color: #d1fae5; color: #065f46; }
        .status-inactive { background-color: #fee2e2; color: #991b1b; }
        
        .btn-action {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            margin: 2px;
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), #1e40af);
            color: white;
            border-radius: 12px 12px 0 0;
        }
        
        .deputy-details {
            background: #f8fafc;
            border-radius: 8px;
            padding: 12px;
            margin-top: 8px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-arrow-right me-2"></i>
                العودة للوحة التحكم
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text text-white">
                    <i class="fas fa-user-tie me-2"></i>
                    <?php echo $is_super_admin ? 'المدير العام' : 'مدير منطقة ' . $manager_info['region_name']; ?>
                </span>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col">
                <h1 class="h3 mb-2">
                    <i class="fas fa-users-cog me-2 text-primary"></i>
                    إدارة الوكلاء
                </h1>
                <p class="text-muted">
                    إضافة وإدارة الوكلاء في المديريات التعليمية
                </p>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDeputyModal">
                    <i class="fas fa-plus me-2"></i>
                    إضافة وكيل جديد
                </button>
            </div>
        </div>

        <!-- Messages -->
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
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="stats-number text-primary"><?php echo $stats['total']; ?></div>
                    <div class="text-muted">إجمالي الوكلاء</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="stats-number text-success"><?php echo $stats['active']; ?></div>
                    <div class="text-muted">الوكلاء النشطون</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="stats-number text-danger"><?php echo $stats['inactive']; ?></div>
                    <div class="text-muted">الوكلاء غير النشطين</div>
                </div>
            </div>
        </div>

        <!-- Deputies Table -->
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    قائمة الوكلاء (<?php echo count($deputies); ?>)
                </h5>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>معلومات الوكيل</th>
                            <th>المنطقة</th>
                            <th>المديرية</th>
                            <th>تاريخ التعيين</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($deputies)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                <h5>لا يوجد وكلاء</h5>
                                <p>لم يتم تعيين أي وكلاء بعد</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($deputies as $deputy): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($deputy['deputy_name']); ?></strong>
                                <div class="deputy-details">
                                    <small class="text-muted">
                                        <i class="fas fa-envelope me-1"></i>
                                        <?php echo htmlspecialchars($deputy['deputy_email']); ?>
                                    </small>
                                    <?php if ($deputy['deputy_phone']): ?>
                                    <br>
                                    <small class="text-muted">
                                        <i class="fas fa-phone me-1"></i>
                                        <?php echo htmlspecialchars($deputy['deputy_phone']); ?>
                                    </small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($deputy['region_name']); ?></td>
                            <td><?php echo htmlspecialchars($deputy['directorate']); ?></td>
                            <td>
                                <?php echo date('Y-m-d', strtotime($deputy['assigned_at'])); ?>
                                <br><small class="text-muted">بواسطة: <?php echo htmlspecialchars($deputy['assigned_by_name']); ?></small>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $deputy['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $deputy['is_active'] ? 'نشط' : 'غير نشط'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($deputy['is_active']): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('هل أنت متأكد من إلغاء تفعيل هذا الوكيل؟');">
                                    <input type="hidden" name="action" value="deactivate_deputy">
                                    <input type="hidden" name="deputy_id" value="<?php echo $deputy['id']; ?>">
                                    <button type="submit" class="btn btn-warning btn-action btn-sm">
                                        <i class="fas fa-ban me-1"></i>إلغاء التفعيل
                                    </button>
                                </form>
                                <?php else: ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="activate_deputy">
                                    <input type="hidden" name="deputy_id" value="<?php echo $deputy['id']; ?>">
                                    <button type="submit" class="btn btn-success btn-action btn-sm">
                                        <i class="fas fa-check me-1"></i>إعادة التفعيل
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Deputy Modal -->
    <div class="modal fade" id="addDeputyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus me-2"></i>
                        إضافة وكيل جديد
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add_deputy">
                    <div class="modal-body">
                        <?php if ($is_super_admin): ?>
                        <div class="mb-3">
                            <label class="form-label">المنطقة</label>
                            <select name="region_id" class="form-select" required>
                                <option value="">اختر المنطقة</option>
                                <?php foreach ($regions as $region): ?>
                                <option value="<?php echo $region['id']; ?>">
                                    <?php echo htmlspecialchars($region['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">اختيار الوكيل</label>
                            <select name="deputy_user_id" class="form-select" required>
                                <option value="">اختر المستخدم</option>
                                <?php foreach ($potential_deputies as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['full_name']); ?> 
                                    (<?php echo htmlspecialchars($user['email']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">المديرية</label>
                            <input type="text" name="directorate" class="form-control" 
                                   placeholder="مثال: مديرية غزة" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">ملاحظات (اختياري)</label>
                            <textarea name="notes" class="form-control" rows="3" 
                                      placeholder="أضف أي ملاحظات إضافية..."></textarea>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            سيتم منح الوكيل صلاحيات مراجعة طلبات المعلمين وإدارة المعلمين في مديريته.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>إضافة الوكيل
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>