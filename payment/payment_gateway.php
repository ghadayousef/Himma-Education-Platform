<?php
/**
 * بوابة الدفع - منصة همّة التوجيهي (محدثة مع PayPal)
 * Payment Gateway - Himma Tawjihi Platform (Updated with PayPal)
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// التحقق من تسجيل الدخول
if (!is_logged_in()) {
    redirect('../auth/login.php');
}

// جلب معرف المادة من الرابط
$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;

if (!$subject_id) {
    redirect('../student/subjects.php');
}

$db = new Database();
$conn = $db->connect();
$user_id = $_SESSION['user_id'];

// جلب بيانات المادة
$subject_stmt = $conn->prepare("
    SELECT s.*, u.full_name as teacher_name
    FROM subjects s
    LEFT JOIN users u ON s.teacher_id = u.id
    WHERE s.id = ? AND s.is_active = 1
");
$subject_stmt->execute([$subject_id]);
$subject = $subject_stmt->fetch();

if (!$subject) {
    redirect('../student/subjects.php');
}

// جلب بيانات المستخدم
$user_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بوابة الدفع - منصة همّة التوجيهي</title>
    
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
            --success-color: #4facfe;
            --warning-color: #43e97b;
            --danger-color: #fa709a;
            --info-color: #00d4ff;
        }

        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #f8f9fc 0%, #e9ecef 100%);
            min-height: 100vh;
        }

        .payment-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .payment-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            padding: 2rem;
        }

        .payment-method {
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }

        .payment-method:hover {
            border-color: var(--primary-color);
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.15);
        }

        .payment-method.active {
            border-color: var(--primary-color);
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
        }

        .payment-method i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }

        .payment-form {
            padding: 2rem;
            display: none;
        }

        .payment-form.active {
            display: block;
        }

        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            font-size: 1rem;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .order-summary {
            background: rgba(102, 126, 234, 0.1);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .price-breakdown {
            border-top: 2px solid #e9ecef;
            padding-top: 1rem;
            margin-top: 1rem;
        }

        .total-amount {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .security-badges {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .security-badge {
            background: white;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            border: 1px solid #e9ecef;
            font-size: 0.9rem;
            color: #6c757d;
        }

        .payment-steps {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }

        .step {
            display: flex;
            align-items: center;
            margin: 0 1rem;
        }

        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-left: 0.5rem;
        }

        .step.completed .step-number {
            background: var(--success-color);
        }

        .step.active .step-number {
            background: var(--warning-color);
        }

        @media (max-width: 768px) {
            .payment-methods {
                grid-template-columns: 1fr;
            }
            
            .security-badges {
                flex-direction: column;
                align-items: center;
            }
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <!-- Payment Steps -->
        <div class="payment-steps">
            <div class="step completed">
                <div class="step-number">1</div>
                <span>اختيار المادة</span>
            </div>
            <div class="step active">
                <div class="step-number">2</div>
                <span>الدفع</span>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <span>التأكيد</span>
            </div>
        </div>

        <!-- Payment Header -->
        <div class="card">
            <div class="payment-header">
                <h2><i class="fas fa-credit-card"></i> بوابة الدفع الآمنة</h2>
                <p class="mb-0">اختر طريقة الدفع المناسبة لك</p>
            </div>

            <!-- Order Summary -->
            <div class="order-summary">
                <h5><i class="fas fa-receipt"></i> ملخص الطلب</h5>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span><i class="fas fa-book"></i> <?php echo htmlspecialchars($subject['name']); ?></span>
                    <span><?php echo number_format($subject['price'], 2); ?>ش</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span><i class="fas fa-user-tie"></i> المعلم: <?php echo htmlspecialchars($subject['teacher_name']); ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span><i class="fas fa-calendar-week"></i> المدة: <?php echo $subject['duration_weeks']; ?> أسبوع</span>
                </div>
                
                <div class="price-breakdown">
                    <div class="d-flex justify-content-between">
                        <span>المبلغ الأساسي:</span>
                        <span><?php echo number_format($subject['price'], 2); ?>ش</span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>رسوم المعالجة:</span>
                        <span>0.00 ش</span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between total-amount">
                        <span>المجموع الكلي:</span>
                        <span><?php echo number_format($subject['price'], 2); ?>ش </span>
                    </div>
                </div>
            </div>

            <!-- Payment Methods -->
            <div class="payment-methods">
                <div class="payment-method" data-method="credit-card">
                    <i class="fas fa-credit-card"></i>
                    <h6>بطاقة ائتمانية</h6>
                    <small class="text-muted">فيزا، ماستركارد</small>
                </div>
                
                <div class="payment-method" data-method="paypal">
                    <i class="fab fa-paypal"></i>
                    <h6>PayPal</h6>
                    <small class="text-muted">ادفع بأمان عبر PayPal</small>
                </div>
                
                <div class="payment-method" data-method="bank-transfer">
                    <i class="fas fa-building"></i>
                    <h6>تحويل بنكي</h6>
                    <small class="text-muted">تحويل مباشر</small>
                </div>
            </div>

            <!-- Credit Card Form -->
            <div class="payment-form" id="credit-card-form">
                <h5><i class="fas fa-credit-card"></i> بيانات البطاقة الائتمانية</h5>
                <form id="creditCardForm">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">رقم البطاقة</label>
                            <input type="text" class="form-control" id="cardNumber" placeholder="1234 5678 9012 3456" maxlength="19" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">تاريخ الانتهاء</label>
                            <input type="text" class="form-control" id="expiryDate" placeholder="MM/YY" maxlength="5" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">رمز الأمان (CVV)</label>
                            <input type="text" class="form-control" id="cvv" placeholder="123" maxlength="4" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">اسم حامل البطاقة</label>
                            <input type="text" class="form-control" id="cardholderName" placeholder="كما هو مكتوب على البطاقة" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        <i class="fas fa-lock"></i> ادفع <?php echo number_format($subject['price'], 2); ?> ش بأمان
                    </button>
                </form>
            </div>

            <!-- PayPal Form -->
            <div class="payment-form" id="paypal-form">
                <h5><i class="fab fa-paypal"></i> الدفع عبر PayPal</h5>
                <div class="text-center">
                    <p class="text-muted mb-4">سيتم معالجة الدفع عبر PayPal بأمان تام</p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>ملاحظة:</strong> سيتم تحويلك إلى صفحة PayPal الآمنة لإتمام عملية الدفع
                    </div>
                    <button type="button" class="btn btn-primary btn-lg" onclick="processPayPal()">
                        <i class="fab fa-paypal"></i> متابعة مع PayPal
                    </button>
                </div>
            </div>

            <!-- Bank Transfer Form -->
            <div class="payment-form" id="bank-transfer-form">
                <h5><i class="fas fa-building"></i> التحويل البنكي</h5>
                <div class="alert alert-info">
                    <h6><i class="fas fa-info-circle"></i> بيانات التحويل:</h6>
                    <p><strong>اسم البنك:</strong> بنك فلسطين الدولي</p>
                    <p><strong>رقم الحساب:</strong> PS1234567890123456789012</p>
                    <p><strong>اسم المستفيد:</strong> منصة همّة التوجيهي</p>
                    <p><strong>المبلغ:</strong> <?php echo number_format($subject['price'], 2); ?> ش</p>
                </div>
                <form id="bankTransferForm">
                    <div class="mb-3">
                        <label class="form-label">رقم العملية (بعد التحويل)</label>
                        <input type="text" class="form-control" id="transferReference" placeholder="أدخل رقم العملية" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">تاريخ التحويل</label>
                        <input type="date" class="form-control" id="transferDate" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        <i class="fas fa-check"></i> تأكيد التحويل
                    </button>
                </form>
            </div>

            <!-- Security Badges -->
            <div class="security-badges">
                <span class="security-badge">
                    <i class="fas fa-shield-alt"></i> SSL مشفر
                </span>
                <span class="security-badge">
                    <i class="fas fa-lock"></i> آمن 100%
                </span>
                <span class="security-badge">
                    <i class="fas fa-user-shield"></i> حماية البيانات
                </span>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        const subjectId = <?php echo $subject_id; ?>;
        
        // Payment method selection
        document.querySelectorAll('.payment-method').forEach(method => {
            method.addEventListener('click', function() {
                document.querySelectorAll('.payment-method').forEach(m => m.classList.remove('active'));
                document.querySelectorAll('.payment-form').forEach(f => f.classList.remove('active'));
                
                this.classList.add('active');
                
                const methodType = this.dataset.method;
                const form = document.getElementById(methodType + '-form');
                if (form) {
                    form.classList.add('active');
                }
            });
        });

        // Credit card number formatting
        document.getElementById('cardNumber').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s/g, '').replace(/[^0-9]/gi, '');
            let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
            e.target.value = formattedValue;
        });

        // Expiry date formatting
        document.getElementById('expiryDate').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            e.target.value = value;
        });

        // Form submissions
        document.getElementById('creditCardForm').addEventListener('submit', function(e) {
            e.preventDefault();
            processCreditCard();
        });

        document.getElementById('bankTransferForm').addEventListener('submit', function(e) {
            e.preventDefault();
            processBankTransfer();
        });

        // Payment processing functions
        function processCreditCard() {
            showProcessingModal();
            processPayment('credit-card');
        }

        function processPayPal() {
            showProcessingModal();
            processPayment('paypal');
        }

        function processBankTransfer() {
            showProcessingModal();
            processPayment('bank-transfer');
        }

        function processPayment(method) {
            fetch('process_payment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `subject_id=${subjectId}&payment_method=${method}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = data.redirect_url;
                } else {
                    alert('خطأ: ' + data.message);
                    location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('حدث خطأ أثناء معالجة الدفع');
                location.reload();
            });
        }

        function showProcessingModal() {
            const modal = document.createElement('div');
            modal.innerHTML = `
                <div class="modal fade show" id="processingModal" tabindex="-1" style="display: block; background: rgba(0,0,0,0.5);">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-body text-center p-4">
                                <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;"></div>
                                <h5>جاري معالجة الدفع...</h5>
                                <p class="text-muted">يرجى عدم إغلاق هذه الصفحة</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }

        // Set today's date for bank transfer
        document.getElementById('transferDate').value = new Date().toISOString().split('T')[0];
    </script>
</body>
</html>