<?php
/**
 * إدارة طلبات انضمام المعلمين - نظام متكامل
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

// التحقق من تسجيل الدخول والصلاحيات
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$conn = getDBConnection();

// معالجة طلبات قبول/رفض المعلمين
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $application_id = (int)$_POST['application_id'];
    $action = $_POST['action'];
    $notes = $_POST['notes'] ?? '';
    
    try {
        $conn->beginTransaction();
        
        // الحصول على بيانات الطلب
        $stmt = $conn->prepare("SELECT * FROM app_d2335_teacher_applications WHERE id = ?");
        $stmt->execute([$application_id]);
        $application = $stmt->fetch();
        
        if (!$application) {
            throw new Exception('الطلب غير موجود');
        }
        
        if ($action === 'approve') {
            // قبول الطلب - لا يتم إنشاء حساب تلقائياً
            // المعلم يجب أن يقوم بإنشاء حسابه بنفسه بعد الموافقة
            
            // تحديث حالة الطلب إلى موافق عليه
            $stmt = $conn->prepare("
                UPDATE app_d2335_teacher_applications 
                SET status = 'approved', 
                    reviewed_by = ?, 
                    reviewed_at = NOW(), 
                    review_notes = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$user_id, $notes, $application_id]);
            
            // إرسال إشعار للمعلم عبر البريد الإلكتروني (يمكن تطويره لاحقاً)
            // هنا يمكن إضافة كود لإرسال بريد إلكتروني للمعلم يخبره بالموافقة
            
            // تسجيل النشاط
            try {
                $stmt = $conn->prepare("
                    INSERT INTO admin_activity_log (admin_id, action_type, action_description, ip_address, created_at) 
                    VALUES (?, 'approve_teacher', ?, ?, NOW())
                ");
                $stmt->execute([
                    $user_id,
                    "قبول طلب المعلم: {$application['teacher_name']} ({$application['email']})",
                    $_SERVER['REMOTE_ADDR']
                ]);
            } catch (Exception $e) {
                // تجاهل أخطاء تسجيل النشاط
                error_log("Activity log error: " . $e->getMessage());
            }
            
            $_SESSION['success_message'] = 'تم قبول الطلب بنجاح. سيتمكن المعلم الآن من إنشاء حسابه وتسجيل الدخول للمنصة.';
            
        } elseif ($action === 'reject') {
            // رفض الطلب
            $stmt = $conn->prepare("
                UPDATE app_d2335_teacher_applications 
                SET status = 'rejected', 
                    reviewed_by = ?, 
                    reviewed_at = NOW(), 
                    review_notes = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$user_id, $notes, $application_id]);
            
            // تسجيل النشاط
            try {
                $stmt = $conn->prepare("
                    INSERT INTO admin_activity_log (admin_id, action_type, action_description, ip_address, created_at) 
                    VALUES (?, 'reject_teacher', ?, ?, NOW())
                ");
                $stmt->execute([
                    $user_id,
                    "رفض طلب المعلم: {$application['teacher_name']}",
                    $_SERVER['REMOTE_ADDR']
                ]);
            } catch (Exception $e) {
                error_log("Activity log error: " . $e->getMessage());
            }
            
            $_SESSION['success_message'] = 'تم رفض الطلب';
            
        } elseif ($action === 'under_review') {
            // وضع الطلب قيد المراجعة
            $stmt = $conn->prepare("
                UPDATE app_d2335_teacher_applications 
                SET status = 'under_review', 
                    reviewed_by = ?, 
                    reviewed_at = NOW(), 
                    review_notes = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$user_id, $notes, $application_id]);
            
            $_SESSION['success_message'] = 'تم وضع الطلب قيد المراجعة';
        }
        
        $conn->commit();
        
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error_message'] = 'حدث خطأ: ' . $e->getMessage();
    }
    
    header("Location: manage_teacher_applications.php");
    exit();
}

// الحصول على الطلبات مع الفلاتر
$status_filter = $_GET['status'] ?? '';
$region_filter = $_GET['region'] ?? '';
$search = $_GET['search'] ?? '';

$where_conditions = [];
$params = [];

if ($status_filter) {
    $where_conditions[] = "ta.status = ?";
    $params[] = $status_filter;
}

if ($region_filter) {
    $where_conditions[] = "ta.region_id = ?";
    $params[] = $region_filter;
}

if ($search) {
    $where_conditions[] = "(ta.teacher_name LIKE ? OR ta.email LIKE ? OR ta.subject_specialization LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

$stmt = $conn->prepare("
    SELECT 
        ta.*,
        r.name as region_name,
        u.full_name as reviewer_name,
        tu.username as teacher_username
    FROM app_d2335_teacher_applications ta
    LEFT JOIN app_d2335_regions r ON ta.region_id = r.id
    LEFT JOIN users u ON ta.reviewed_by = u.id
    LEFT JOIN users tu ON ta.teacher_user_id = tu.id
    $where_clause
    ORDER BY 
        CASE ta.status 
            WHEN 'pending' THEN 1 
            WHEN 'under_review' THEN 2 
            WHEN 'approved' THEN 3 
            WHEN 'rejected' THEN 4 
        END,
        ta.submitted_at DESC
");
$stmt->execute($params);
$applications = $stmt->fetchAll();

// الحصول على المناطق
$stmt = $conn->prepare("SELECT id, name FROM app_d2335_regions WHERE is_active = 1 ORDER BY name");
$stmt->execute();
$regions = $stmt->fetchAll();

// إحصائيات
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
$stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة طلبات المعلمين - منصة همة التوجيهي</title>
    
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
        
        .status-pending { background-color: #fef3c7; color: #92400e; }
        .status-under-review { background-color: #dbeafe; color: #1e40af; }
        .status-approved { background-color: #d1fae5; color: #065f46; }
        .status-rejected { background-color: #fee2e2; color: #991b1b; }
        
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
        
        .application-details {
            background: #f8fafc;
            border-radius: 8px;
            padding: 12px;
            margin-top: 8px;
        }
        
        .filter-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
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
                    <i class="fas fa-user-graduate me-2 text-primary"></i>
                    إدارة طلبات انضمام المعلمين
                </h1>
                <p class="text-muted">
                    مراجعة وقبول أو رفض طلبات المعلمين للانضمام للمنصة
                </p>
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
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number text-primary"><?php echo $stats['total']; ?></div>
                    <div class="text-muted">إجمالي الطلبات</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number text-warning"><?php echo $stats['pending']; ?></div>
                    <div class="text-muted">معلقة</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number text-info"><?php echo $stats['under_review']; ?></div>
                    <div class="text-muted">قيد المراجعة</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number text-success"><?php echo $stats['approved']; ?></div>
                    <div class="text-muted">موافق عليها</div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">حالة الطلب</label>
                    <select name="status" class="form-select">
                        <option value="">جميع الحالات</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>معلق</option>
                        <option value="under_review" <?php echo $status_filter === 'under_review' ? 'selected' : ''; ?>>قيد المراجعة</option>
                        <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>موافق عليه</option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>مرفوض</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">المنطقة</label>
                    <select name="region" class="form-select">
                        <option value="">جميع المناطق</option>
                        <?php foreach ($regions as $region): ?>
                        <option value="<?php echo $region['id']; ?>" <?php echo $region_filter == $region['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($region['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">بحث</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="اسم المعلم، البريد، التخصص..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search me-2"></i>بحث
                    </button>
                    <a href="manage_teacher_applications.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </form>
        </div>

        <!-- Applications Table -->
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    قائمة الطلبات (<?php echo count($applications); ?>)
                </h5>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>معلومات المعلم</th>
                            <th>المنطقة</th>
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
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($applications as $app): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($app['teacher_name']); ?></strong>
                                <div class="application-details">
                                    <small class="text-muted">
                                        <i class="fas fa-envelope me-1"></i>
                                        <?php echo htmlspecialchars($app['email']); ?>
                                    </small>
                                    <?php if ($app['phone']): ?>
                                    <br>
                                    <small class="text-muted">
                                        <i class="fas fa-phone me-1"></i>
                                        <?php echo htmlspecialchars($app['phone']); ?>
                                    </small>
                                    <?php endif; ?>
                                    <?php if ($app['teacher_username']): ?>
                                    <br>
                                    <small class="text-success">
                                        <i class="fas fa-user-check me-1"></i>
                                        حساب: <?php echo htmlspecialchars($app['teacher_username']); ?>
                                    </small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($app['region_name'] ?? 'غير محدد'); ?>
                                <?php if ($app['directorate']): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars($app['directorate']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($app['subject_specialization']); ?></td>
                            <td><?php echo $app['experience_years']; ?> سنوات</td>
                            <td>
                                <span class="status-badge status-<?php echo $app['status']; ?>">
                                    <?php 
                                    $status_labels = [
                                        'pending' => 'معلق',
                                        'under_review' => 'قيد المراجعة',
                                        'approved' => 'موافق عليه',
                                        'rejected' => 'مرفوض'
                                    ];
                                    echo $status_labels[$app['status']];
                                    ?>
                                </span>
                            </td>
                            <td>
                                <?php echo date('Y-m-d', strtotime($app['submitted_at'])); ?>
                                <br><small class="text-muted"><?php echo date('H:i', strtotime($app['submitted_at'])); ?></small>
                            </td>
                            <td>
                                <?php if ($app['status'] === 'pending' || $app['status'] === 'under_review'): ?>
                                <button class="btn btn-success btn-action btn-sm" 
                                        onclick="reviewApplication(<?php echo $app['id']; ?>, 'approve', '<?php echo htmlspecialchars($app['teacher_name']); ?>')">
                                    <i class="fas fa-check me-1"></i>قبول
                                </button>
                                <button class="btn btn-danger btn-action btn-sm" 
                                        onclick="reviewApplication(<?php echo $app['id']; ?>, 'reject', '<?php echo htmlspecialchars($app['teacher_name']); ?>')">
                                    <i class="fas fa-times me-1"></i>رفض
                                </button>
                                <?php endif; ?>
                                <button class="btn btn-info btn-action btn-sm" 
                                        onclick="viewDetails(<?php echo htmlspecialchars(json_encode($app)); ?>)">
                                    <i class="fas fa-eye me-1"></i>تفاصيل
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
                        <span id="modal_title">مراجعة الطلب</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="application_id" id="review_application_id">
                        <input type="hidden" name="action" id="review_action">
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <span id="review_message"></span>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">ملاحظات</label>
                            <textarea name="notes" class="form-control" rows="4" 
                                      placeholder="أضف ملاحظاتك (اختياري)..."></textarea>
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

    <!-- Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-info-circle me-2"></i>
                        تفاصيل الطلب
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="details_content">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function reviewApplication(applicationId, action, teacherName) {
            document.getElementById('review_application_id').value = applicationId;
            document.getElementById('review_action').value = action;
            
            const submitBtn = document.getElementById('review_submit_btn');
            const message = document.getElementById('review_message');
            const title = document.getElementById('modal_title');
            
            if (action === 'approve') {
                submitBtn.className = 'btn btn-success';
                submitBtn.innerHTML = '<i class="fas fa-check me-2"></i>قبول الطلب';
                message.textContent = `سيتم قبول طلب المعلم "${teacherName}". بعد الموافقة، سيتمكن المعلم من إنشاء حسابه وتسجيل الدخول للمنصة باستخدام بريده الإلكتروني.`;
                title.textContent = 'قبول طلب المعلم';
            } else if (action === 'reject') {
                submitBtn.className = 'btn btn-danger';
                submitBtn.innerHTML = '<i class="fas fa-times me-2"></i>رفض الطلب';
                message.textContent = `سيتم رفض طلب المعلم "${teacherName}". يرجى إضافة سبب الرفض في الملاحظات.`;
                title.textContent = 'رفض طلب المعلم';
            }
            
            const modal = new bootstrap.Modal(document.getElementById('reviewModal'));
            modal.show();
        }
        
        function viewDetails(application) {
            const content = `
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>اسم المعلم:</strong><br>
                        ${application.teacher_name}
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>البريد الإلكتروني:</strong><br>
                        ${application.email}
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>رقم الهاتف:</strong><br>
                        ${application.phone || 'غير محدد'}
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>المنطقة:</strong><br>
                        ${application.region_name || 'غير محدد'}
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>المديرية:</strong><br>
                        ${application.directorate || 'غير محدد'}
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>التخصص:</strong><br>
                        ${application.subject_specialization}
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>سنوات الخبرة:</strong><br>
                        ${application.experience_years} سنوات
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>تاريخ التقديم:</strong><br>
                        ${new Date(application.submitted_at).toLocaleString('ar-EG')}
                    </div>
                    ${application.qualifications ? `
                    <div class="col-12 mb-3">
                        <strong>المؤهلات:</strong><br>
                        ${application.qualifications}
                    </div>
                    ` : ''}
                    ${application.review_notes ? `
                    <div class="col-12 mb-3">
                        <strong>ملاحظات المراجعة:</strong><br>
                        <div class="alert alert-info">${application.review_notes}</div>
                    </div>
                    ` : ''}
                    ${application.reviewer_name ? `
                    <div class="col-12 mb-3">
                        <strong>تمت المراجعة بواسطة:</strong><br>
                        ${application.reviewer_name} في ${new Date(application.reviewed_at).toLocaleString('ar-EG')}
                    </div>
                    ` : ''}
                </div>
            `;
            
            document.getElementById('details_content').innerHTML = content;
            const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
            modal.show();
        }
    </script>
</body>
</html>