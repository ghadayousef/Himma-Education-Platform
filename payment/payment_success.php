<?php
/**
 * صفحة نجاح الدفع - منصة همّة التوجيهي
 * Payment Success Page - Himma Tawjihi Platform
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// التحقق من تسجيل الدخول
if (!is_logged_in()) {
    redirect('../auth/login.php');
}

$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$payment_method = isset($_GET['method']) ? $_GET['method'] : 'unknown';

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

// توليد رقم مرجعي وهمي للعرض
$reference_number = 'PAY-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

// تحديد اسم طريقة الدفع
$payment_methods = [
    'credit-card' => 'بطاقة ائتمانية',
    'paypal' => 'PayPal',
    'apple-pay' => 'Apple Pay',
    'bank-transfer' => 'تحويل بنكي'
];

$payment_method_name = $payment_methods[$payment_method] ?? 'غير محدد';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تم الدفع بنجاح - منصة همّة التوجيهي</title>
    
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
        }

        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #f8f9fc 0%, #e9ecef 100%);
            min-height: 100vh;
        }

        .success-container {
            max-width: 700px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .success-header {
            background: linear-gradient(135deg, var(--success-color), var(--warning-color));
            color: white;
            padding: 3rem 2rem;
            text-align: center;
        }

        .success-icon {
            font-size: 5rem;
            margin-bottom: 1rem;
            animation: successPulse 2s infinite;
        }

        @keyframes successPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .payment-details {
            padding: 2rem;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #e9ecef;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            font-weight: 600;
            color: #495057;
        }

        .detail-value {
            color: var(--primary-color);
            font-weight: 500;
        }

        .reference-number {
            background: rgba(102, 126, 234, 0.1);
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
            margin: 1.5rem 0;
        }

        .reference-number .number {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
            font-family: 'Courier New', monospace;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .btn-outline-primary {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
        }

        .btn-outline-primary:hover {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }

        .next-steps {
            background: rgba(67, 233, 123, 0.1);
            border-radius: 15px;
            padding: 1.5rem;
            margin: 1.5rem 0;
        }

        .step-item {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .step-item:last-child {
            margin-bottom: 0;
        }

        .step-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--warning-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-left: 1rem;
            flex-shrink: 0;
        }

        .confetti {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1000;
        }

        .confetti-piece {
            position: absolute;
            width: 10px;
            height: 10px;
            background: var(--success-color);
            animation: confetti-fall 3s linear infinite;
        }

        @keyframes confetti-fall {
            0% {
                transform: translateY(-100vh) rotate(0deg);
                opacity: 1;
            }
            100% {
                transform: translateY(100vh) rotate(720deg);
                opacity: 0;
            }
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

        .step-number-nav {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--success-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-left: 0.5rem;
        }

        @media (max-width: 768px) {
            .detail-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .success-header {
                padding: 2rem 1rem;
            }
            
            .success-icon {
                font-size: 4rem;
            }
        }
    </style>
</head>
<body>
    <!-- Confetti Animation -->
    <div class="confetti" id="confetti"></div>

    <div class="success-container">
        <!-- Payment Steps -->
        <div class="payment-steps">
            <div class="step">
                <div class="step-number-nav">✓</div>
                <span>اختيار المادة</span>
            </div>
            <div class="step">
                <div class="step-number-nav">✓</div>
                <span>الدفع</span>
            </div>
            <div class="step">
                <div class="step-number-nav">✓</div>
                <span>التأكيد</span>
            </div>
        </div>

        <div class="card">
            <!-- Success Header -->
            <div class="success-header">
                <i class="fas fa-check-circle success-icon"></i>
                <h2>تم الدفع بنجاح!</h2>
                <p class="mb-0">تهانينا! تم تسجيلك في المادة بنجاح</p>
            </div>

            <!-- Payment Details -->
            <div class="payment-details">
                <h5 class="mb-4"><i class="fas fa-receipt"></i> تفاصيل العملية</h5>
                
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-book"></i> المادة:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($subject['name']); ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-user-tie"></i> المعلم:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($subject['teacher_name']); ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-user"></i> الطالب:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($user['full_name']); ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-credit-card"></i> طريقة الدفع:</span>
                    <span class="detail-value"><?php echo $payment_method_name; ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-money-bill-wave"></i> المبلغ المدفوع:</span>
                    <span class="detail-value"><?php echo number_format($subject['price'], 2); ?> ش </span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-calendar"></i> تاريخ الدفع:</span>
                    <span class="detail-value"><?php echo date('Y-m-d H:i:s'); ?></span>
                </div>

                <!-- Reference Number -->
                <div class="reference-number">
                    <div class="mb-2">
                        <i class="fas fa-hashtag"></i> رقم العملية المرجعي
                    </div>
                    <div class="number"><?php echo $reference_number; ?></div>
                    <small class="text-muted">احتفظ بهذا الرقم للمراجعة</small>
                </div>

                <!-- Next Steps -->
                <div class="next-steps">
                    <h6><i class="fas fa-list-check"></i> الخطوات التالية:</h6>
                    <div class="step-item">
                        <div class="step-number">1</div>
                        <span>ستتلقى إيصال الدفع على بريدك الإلكتروني</span>
                    </div>
                    <div class="step-item">
                        <div class="step-number">2</div>
                        <span>يمكنك الآن الوصول إلى محتوى المادة من لوحة التحكم</span>
                    </div>
                    <div class="step-item">
                        <div class="step-number">3</div>
                        <span>ابدأ رحلتك التعليمية واستمتع بالتعلم!</span>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                    <a href="../student/dashboard.php" class="btn btn-primary">
                        <i class="fas fa-tachometer-alt"></i> انتقل إلى لوحة التحكم
                    </a>
                    <a href="../student/subjects.php" class="btn btn-outline-primary">
                        <i class="fas fa-book"></i> تصفح مواد أخرى
                    </a>
                </div>

                <!-- Support Info -->
                <div class="text-center mt-4">
                    <small class="text-muted">
                        <i class="fas fa-headset"></i> 
                        في حالة وجود أي استفسار، تواصل مع الدعم الفني على: 
                        <a href="mailto:support@himma.edu">support@himma.edu</a>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Confetti animation
        function createConfetti() {
            const confettiContainer = document.getElementById('confetti');
            const colors = ['#667eea', '#764ba2', '#4facfe', '#43e97b', '#fa709a'];
            
            for (let i = 0; i < 50; i++) {
                const confettiPiece = document.createElement('div');
                confettiPiece.className = 'confetti-piece';
                confettiPiece.style.left = Math.random() * 100 + '%';
                confettiPiece.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                confettiPiece.style.animationDelay = Math.random() * 3 + 's';
                confettiPiece.style.animationDuration = (Math.random() * 3 + 2) + 's';
                confettiContainer.appendChild(confettiPiece);
            }
            
            // Remove confetti after animation
            setTimeout(() => {
                confettiContainer.innerHTML = '';
            }, 5000);
        }

        // Start confetti animation on page load
        window.addEventListener('load', createConfetti);

        // Auto-redirect to dashboard after 10 seconds (optional)
        setTimeout(() => {
            const redirectNotice = document.createElement('div');
            redirectNotice.className = 'alert alert-info text-center mt-3';
            redirectNotice.innerHTML = `
                <i class="fas fa-info-circle"></i> 
                سيتم توجيهك تلقائياً إلى لوحة التحكم خلال <span id="countdown">5</span> ثوانِ...
                <button class="btn btn-sm btn-outline-info ms-2" onclick="clearTimeout(autoRedirect)">إلغاء</button>
            `;
            document.querySelector('.payment-details').appendChild(redirectNotice);
            
            let countdown = 5;
            const countdownElement = document.getElementById('countdown');
            const countdownInterval = setInterval(() => {
                countdown--;
                countdownElement.textContent = countdown;
                if (countdown <= 0) {
                    clearInterval(countdownInterval);
                }
            }, 1000);
            
            window.autoRedirect = setTimeout(() => {
                window.location.href = '../student/dashboard.php';
            }, 5000);
        }, 5000);

        // Print receipt function
        function printReceipt() {
            window.print();
        }

        // Copy reference number
        function copyReference() {
            const referenceNumber = '<?php echo $reference_number; ?>';
            navigator.clipboard.writeText(referenceNumber).then(() => {
                alert('تم نسخ رقم العملية المرجعي');
            });
        }

        // Add print and copy buttons
        document.addEventListener('DOMContentLoaded', function() {
            const actionButtons = document.querySelector('.d-grid');
            actionButtons.insertAdjacentHTML('beforeend', `
                <button class="btn btn-outline-secondary" onclick="printReceipt()">
                    <i class="fas fa-print"></i> طباعة الإيصال
                </button>
            `);
            
            const referenceDiv = document.querySelector('.reference-number');
            referenceDiv.insertAdjacentHTML('beforeend', `
                <button class="btn btn-sm btn-outline-primary mt-2" onclick="copyReference()">
                    <i class="fas fa-copy"></i> نسخ الرقم
                </button>
            `);
        });
    </script>
</body>
</html>