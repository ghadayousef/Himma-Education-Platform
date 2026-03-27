<?php
/**
 * صفحة فشل الدفع - منصة همّة التوجيهي
 * Payment Failed Page - Himma Tawjihi Platform
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// التحقق من تسجيل الدخول
if (!is_logged_in()) {
    redirect('../auth/login.php');
}

$subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
$error_code = isset($_GET['error']) ? $_GET['error'] : 'unknown';
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

// رسائل الخطأ
$error_messages = [
    'insufficient_funds' => 'الرصيد غير كافي في حسابك',
    'card_declined' => 'تم رفض البطاقة من قبل البنك',
    'expired_card' => 'البطاقة منتهية الصلاحية',
    'invalid_card' => 'بيانات البطاقة غير صحيحة',
    'network_error' => 'خطأ في الشبكة، يرجى المحاولة مرة أخرى',
    'timeout' => 'انتهت مهلة العملية',
    'cancelled' => 'تم إلغاء العملية من قبل المستخدم',
    'unknown' => 'حدث خطأ غير متوقع'
];

$error_message = $error_messages[$error_code] ?? $error_messages['unknown'];

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
    <title>فشل في الدفع - منصة همّة التوجيهي</title>
    
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
            --danger-color: #fa709a;
            --warning-color: #ff9a56;
        }

        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #f8f9fc 0%, #e9ecef 100%);
            min-height: 100vh;
        }

        .failed-container {
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

        .failed-header {
            background: linear-gradient(135deg, var(--danger-color), var(--warning-color));
            color: white;
            padding: 3rem 2rem;
            text-align: center;
        }

        .failed-icon {
            font-size: 5rem;
            margin-bottom: 1rem;
            animation: shake 0.5s ease-in-out infinite alternate;
        }

        @keyframes shake {
            0% { transform: translateX(0); }
            100% { transform: translateX(10px); }
        }

        .payment-details {
            padding: 2rem;
        }

        .error-info {
            background: rgba(250, 112, 154, 0.1);
            border-radius: 15px;
            padding: 1.5rem;
            margin: 1.5rem 0;
            border-left: 4px solid var(--danger-color);
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

        .btn-danger {
            background: linear-gradient(135deg, var(--danger-color), var(--warning-color));
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
        }

        .troubleshooting {
            background: rgba(102, 126, 234, 0.1);
            border-radius: 15px;
            padding: 1.5rem;
            margin: 1.5rem 0;
        }

        .solution-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .solution-item:last-child {
            margin-bottom: 0;
        }

        .solution-number {
            width: 25px;
            height: 25px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-left: 1rem;
            flex-shrink: 0;
            font-size: 0.9rem;
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
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-left: 0.5rem;
        }

        .step.completed .step-number-nav {
            background: var(--primary-color);
            color: white;
        }

        .step.failed .step-number-nav {
            background: var(--danger-color);
            color: white;
        }

        .step.pending .step-number-nav {
            background: #e9ecef;
            color: #6c757d;
        }

        @media (max-width: 768px) {
            .detail-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .failed-header {
                padding: 2rem 1rem;
            }
            
            .failed-icon {
                font-size: 4rem;
            }
        }
    </style>
</head>
<body>
    <div class="failed-container">
        <!-- Payment Steps -->
        <div class="payment-steps">
            <div class="step completed">
                <div class="step-number-nav">✓</div>
                <span>اختيار المادة</span>
            </div>
            <div class="step failed">
                <div class="step-number-nav">✗</div>
                <span>الدفع</span>
            </div>
            <div class="step pending">
                <div class="step-number-nav">3</div>
                <span>التأكيد</span>
            </div>
        </div>

        <div class="card">
            <!-- Failed Header -->
            <div class="failed-header">
                <i class="fas fa-times-circle failed-icon"></i>
                <h2>فشل في عملية الدفع</h2>
                <p class="mb-0">لم نتمكن من إتمام عملية الدفع</p>
            </div>

            <!-- Payment Details -->
            <div class="payment-details">
                <!-- Error Information -->
                <div class="error-info">
                    <h6><i class="fas fa-exclamation-triangle"></i> سبب الفشل:</h6>
                    <p class="mb-0 fw-bold"><?php echo htmlspecialchars($error_message); ?></p>
                </div>

                <h5 class="mb-4"><i class="fas fa-info-circle"></i> تفاصيل العملية</h5>
                
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-book"></i> المادة:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($subject['name']); ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-user-tie"></i> المعلم:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($subject['teacher_name']); ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-credit-card"></i> طريقة الدفع:</span>
                    <span class="detail-value"><?php echo $payment_method_name; ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-money-bill-wave"></i> المبلغ:</span>
                    <span class="detail-value"><?php echo number_format($subject['price'], 2); ?>ش</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label"><i class="fas fa-calendar"></i> وقت المحاولة:</span>
                    <span class="detail-value"><?php echo date('Y-m-d H:i:s'); ?></span>
                </div>

                <!-- Troubleshooting -->
                <div class="troubleshooting">
                    <h6><i class="fas fa-tools"></i> حلول مقترحة:</h6>
                    
                    <?php if ($error_code === 'insufficient_funds'): ?>
                        <div class="solution-item">
                            <div class="solution-number">1</div>
                            <span>تأكد من وجود رصيد كافي في حسابك أو بطاقتك</span>
                        </div>
                        <div class="solution-item">
                            <div class="solution-number">2</div>
                            <span>جرب استخدام بطاقة أخرى أو طريقة دفع مختلفة</span>
                        </div>
                    <?php elseif ($error_code === 'card_declined'): ?>
                        <div class="solution-item">
                            <div class="solution-number">1</div>
                            <span>تواصل مع البنك للتأكد من عدم وجود قيود على البطاقة</span>
                        </div>
                        <div class="solution-item">
                            <div class="solution-number">2</div>
                            <span>تأكد من صحة بيانات البطاقة المدخلة</span>
                        </div>
                    <?php elseif ($error_code === 'expired_card'): ?>
                        <div class="solution-item">
                            <div class="solution-number">1</div>
                            <span>استخدم بطاقة سارية المفعول</span>
                        </div>
                        <div class="solution-item">
                            <div class="solution-number">2</div>
                            <span>تحقق من تاريخ انتهاء صلاحية البطاقة</span>
                        </div>
                    <?php else: ?>
                        <div class="solution-item">
                            <div class="solution-number">1</div>
                            <span>تحقق من اتصالك بالإنترنت وحاول مرة أخرى</span>
                        </div>
                        <div class="solution-item">
                            <div class="solution-number">2</div>
                            <span>جرب استخدام طريقة دفع أخرى</span>
                        </div>
                        <div class="solution-item">
                            <div class="solution-number">3</div>
                            <span>تأكد من صحة جميع البيانات المدخلة</span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="solution-item">
                        <div class="solution-number"><i class="fas fa-headset"></i></div>
                        <span>إذا استمرت المشكلة، تواصل مع الدعم الفني</span>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                    <a href="payment_gateway.php?subject_id=<?php echo $subject_id; ?>" class="btn btn-danger">
                        <i class="fas fa-redo"></i> إعادة المحاولة
                    </a>
                    <a href="../student/subjects.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-right"></i> العودة للمواد
                    </a>
                </div>

                <!-- Support Info -->
                <div class="text-center mt-4">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>تحتاج مساعدة؟</strong><br>
                        تواصل مع فريق الدعم الفني:
                        <br>
                        <i class="fas fa-envelope"></i> <a href="mailto:support@himma.edu">support@himma.edu</a>
                        <br>
                        <i class="fas fa-phone"></i> <a href="tel:+966123456789">+966 12 345 6789</a>
                        <br>
                        <i class="fab fa-whatsapp"></i> <a href="https://wa.me/966123456789">واتساب</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-retry suggestion after 30 seconds
        setTimeout(() => {
            const retryNotice = document.createElement('div');
            retryNotice.className = 'alert alert-warning text-center mt-3';
            retryNotice.innerHTML = `
                <i class="fas fa-clock"></i> 
                هل تريد المحاولة مرة أخرى؟ أحياناً تنجح العملية في المحاولة الثانية.
                <br>
                <a href="payment_gateway.php?subject_id=<?php echo $subject_id; ?>" class="btn btn-sm btn-warning mt-2">
                    <i class="fas fa-redo"></i> إعادة المحاولة الآن
                </a>
            `;
            document.querySelector('.payment-details').appendChild(retryNotice);
        }, 30000);

        // Track failed payment for analytics (Frontend only)
        console.log('Payment failed:', {
            subject_id: <?php echo $subject_id; ?>,
            error_code: '<?php echo $error_code; ?>',
            payment_method: '<?php echo $payment_method; ?>',
            timestamp: new Date().toISOString()
        });

        // Show different messages based on error type
        document.addEventListener('DOMContentLoaded', function() {
            const errorCode = '<?php echo $error_code; ?>';
            
            // Add specific help based on error type
            if (errorCode === 'network_error') {
                setTimeout(() => {
                    const networkHelp = document.createElement('div');
                    networkHelp.className = 'alert alert-info mt-3';
                    networkHelp.innerHTML = `
                        <i class="fas fa-wifi"></i> 
                        <strong>مشكلة في الشبكة؟</strong> جرب الاتصال بشبكة واي فاي أخرى أو استخدم بيانات الجوال.
                    `;
                    document.querySelector('.troubleshooting').appendChild(networkHelp);
                }, 2000);
            }
        });
    </script>
</body>
</html>