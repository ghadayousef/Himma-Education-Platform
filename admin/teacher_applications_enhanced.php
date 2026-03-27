<?php
/**
 * إدارة طلبات انضمام المعلمين المُصلحة - منصة همة التوجيهي
 * تستخدم الجداول الصحيحة في قاعدة البيانات
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

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'student';

// التحقق من الصلاحيات (يجب أن يكون admin)
if ($user_role !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// معالجة طلبات المراجعة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $application_id = (int)$_POST['application_id'];
    $action = $_POST['action'];
    $notes = $_POST['notes'] ?? '';
    
    try {
        $conn = getDBConnection();
        
        $status = '';
        switch ($action) {
            case 'approve':
                $status = 'approved';
                break;
            case 'reject':
                $status = 'rejected';
                break;
            case 'under_review':
                $status = 'under_review';
                break;
        }
        
        if ($status) {
            $stmt = $conn->prepare("
                UPDATE teacher_applications 
                SET status = ?, notes = ?, reviewed_by = ?, reviewed_at = NOW(), updated_at = NOW()
                WHERE id = ?
            ");
            
            if ($stmt->execute([$status, $notes, $user_id, $application_id])) {
                $_SESSION['success_message'] = 'تم تحديث حالة الطلب بنجاح';
            } else {
                $_SESSION['error_message'] = 'حدث خطأ أثناء تحديث الطلب';
            }
        }
        
    } catch (Exception $e) {
        error_log("Error updating application: " . $e->getMessage());
        $_SESSION['error_message'] = 'حدث خطأ في النظام';
    }
    
    header("Location: teacher_applications_fixed.php");
    exit();
}

// تحديد المرشحات
$status_filter = $_GET['status'] ?? '';
$branch_filter = $_GET['branch'] ?? '';

// بناء استعلام البحث
$where_conditions = [];
$params = [];

if ($status_filter) {
    $where_conditions[] = "ta.status = ?";
    $params[] = $status_filter;
}

if ($branch_filter) {
    $where_conditions[] = "ta.branch_id = ?";
    $params[] = $branch_filter;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// الحصول على البيانات
try {
    $conn = getDBConnection();
    
    // الحصول على الطلبات
    $sql = "
        SELECT ta.*, b.name as branch_name, u.full_name as reviewer_name
        FROM teacher_applications ta
        LEFT JOIN branches b ON ta.branch_id = b.id
        LEFT JOIN users u ON ta.reviewed_by = u.id
        $where_clause
        ORDER BY ta.applied_at DESC
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $applications = $stmt->fetchAll();
    
    // الحصول على الفروع للمرشحات
    $stmt = $conn->prepare("SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $branches = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Error fetching applications: " . $e->getMessage());
    $applications = [];
    $branches = [];
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طلبات انضمام المعلمين - منصة همة التوجيهي</title>
    
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

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-under-review {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .status-approved {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-rejected {
            background-color: #fee2e2;
            color: #991b1b;
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

        .filter-section {
            background: var(--card-color);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .application-details {
            background: #f8fafc;
            border-radius: 8px;
            padding: 12px;
            margin-top: 8px;
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
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="../admin/dashboard.php">
                <i class="fas fa-arrow-right me-2"></i>
                العودة للوحة التحكم
            </a>
            
            <div class="navbar-nav ms-auto">
                <span class="navbar-text text-white">
                    <i class="fas fa-user me-2"></i>
                    مدير النظام
                </span>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col">
                <h1 class="h3 mb-2">
                    <i class="fas fa-file-alt me-2 text-primary"></i>
                    طلبات انضمام المعلمين
                </h1>
                <p class="text-muted">
                    إدارة ومراجعة طلبات المعلمين للانضمام للمنصة
                </p>
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

        <!-- Filters -->
        <div class="filter-section">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">حالة الطلب</label>
                    <select name="status" class="form-select">
                        <option value="">جميع الحالات</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>معلق</option>
                        <option value="under_review" <?php echo $status_filter === 'under_review' ? 'selected' : ''; ?>>قيد المراجعة</option>
                        <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>موافق عليه</option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>مرفوض</option>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">الفرع</label>
                    <select name="branch" class="form-select">
                        <option value="">جميع الفروع</option>
                        <?php foreach ($branches as $branch): ?>
                        <option value="<?php echo $branch['id']; ?>" 
                                <?php echo $branch_filter == $branch['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($branch['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search me-2"></i>
                        بحث
                    </button>
                    <a href="teacher_applications_fixed.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-2"></i>
                        إلغاء
                    </a>
                </div>
            </form>
        </div>

        <!-- Applications Table -->
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-list me-2"></i>
                        قائمة الطلبات (<?php echo count($applications); ?>)
                    </h5>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>معلومات المعلم</th>
                            <th>الفرع</th>
                            <th>التخصص</th>
                            <th>الخبرة</th>
                            <th>الحالة</th>
                            <th>تاريخ التقديم</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($applications)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                                <h5>لا توجد طلبات</h5>
                                <p>لم يتم العثور على طلبات تطابق المعايير المحددة</p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($applications as $application): ?>
                        <tr>
                            <td>
                                <div>
                                    <strong><?php echo htmlspecialchars($application['teacher_name']); ?></strong>
                                    <div class="application-details">
                                        <small class="text-muted">
                                            <i class="fas fa-envelope me-1"></i>
                                            <?php echo htmlspecialchars($application['email']); ?>
                                        </small>
                                        <?php if ($application['phone']): ?>
                                        <br>
                                        <small class="text-muted">
                                            <i class="fas fa-phone me-1"></i>
                                            <?php echo htmlspecialchars($application['phone']); ?>
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($application['branch_name'] ?? 'غير محدد'); ?></strong>
                            </td>
                            <td><?php echo htmlspecialchars($application['subject_specialization']); ?></td>
                            <td>
                                <?php echo $application['years_experience']; ?> 
                                <?php echo $application['years_experience'] == 1 ? 'سنة' : 'سنوات'; ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $application['status']; ?>">
                                    <?php 
                                    switch($application['status']) {
                                        case 'pending': echo 'معلق'; break;
                                        case 'under_review': echo 'قيد المراجعة'; break;
                                        case 'approved': echo 'موافق عليه'; break;
                                        case 'rejected': echo 'مرفوض'; break;
                                    }
                                    ?>
                                </span>
                            </td>
                            <td>
                                <?php echo date('Y-m-d', strtotime($application['applied_at'])); ?>
                                <br>
                                <small class="text-muted">
                                    <?php echo date('H:i', strtotime($application['applied_at'])); ?>
                                </small>
                            </td>
                            <td>
                                <?php if ($application['status'] !== 'approved' && $application['status'] !== 'rejected'): ?>
                                <button class="btn btn-success btn-action btn-sm" 
                                        onclick="reviewApplication(<?php echo $application['id']; ?>, 'approve')">
                                    <i class="fas fa-check me-1"></i>
                                    قبول
                                </button>
                                <button class="btn btn-danger btn-action btn-sm" 
                                        onclick="reviewApplication(<?php echo $application['id']; ?>, 'reject')">
                                    <i class="fas fa-times me-1"></i>
                                    رفض
                                </button>
                                <?php endif; ?>
                                
                                <button class="btn btn-primary btn-action btn-sm" 
                                        onclick="viewApplication(<?php echo $application['id']; ?>)">
                                    <i class="fas fa-eye me-1"></i>
                                    عرض
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Review Modal -->
    <div class="modal fade" id="reviewModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-clipboard-check me-2"></i>
                        مراجعة الطلب
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="application_id" id="review_application_id">
                        <input type="hidden" name="action" id="review_action">
                        
                        <div class="mb-3">
                            <label class="form-label">ملاحظات المراجعة</label>
                            <textarea name="notes" class="form-control" rows="4" 
                                      placeholder="أضف ملاحظاتك حول هذا الطلب..."></textarea>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <span id="review_message"></span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                        <button type="submit" class="btn" id="review_submit_btn">تأكيد</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function viewApplication(applicationId) {
            // يمكن إضافة modal لعرض تفاصيل الطلب
            alert('عرض تفاصيل الطلب رقم: ' + applicationId);
        }

        function reviewApplication(applicationId, action) {
            document.getElementById('review_application_id').value = applicationId;
            document.getElementById('review_action').value = action;
            
            const submitBtn = document.getElementById('review_submit_btn');
            const message = document.getElementById('review_message');
            
            if (action === 'approve') {
                submitBtn.className = 'btn btn-success';
                submitBtn.innerHTML = '<i class="fas fa-check me-2"></i>قبول الطلب';
                message.textContent = 'سيتم قبول هذا الطلب وإضافة المعلم إلى النظام.';
            } else if (action === 'reject') {
                submitBtn.className = 'btn btn-danger';
                submitBtn.innerHTML = '<i class="fas fa-times me-2"></i>رفض الطلب';
                message.textContent = 'سيتم رفض هذا الطلب ولن يتمكن المعلم من الوصول للنظام.';
            }
            
            const modal = new bootstrap.Modal(document.getElementById('reviewModal'));
            modal.show();
        }
    </script>
</body>
</html>