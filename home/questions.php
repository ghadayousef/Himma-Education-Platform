<?php
/**
 * صفحة الأسئلة الشائعة - منصة همّة التوجيهي
 * FAQ Page - Himma Tawjihi Platform
 */

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الأسئلة الشائعة - منصة همّة التعليمية</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --accent-color: #f093fb;
            --success-color: #4facfe;
            --warning-color: #43e97b;
            --danger-color: #fa709a;
            --dark-color: #2c3e50;
            --light-color: #ecf0f1;
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(255, 255, 255, 0.2);
            --shadow: 0 8px 32px rgba(31, 38, 135, 0.37);
        }

        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Glass Morphism Components */
        .glass {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow);
        }

        /* Navigation */
        .navbar {
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            padding: 1rem 2rem;
            transition: all 0.3s ease;
        }

        .nav-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
        }

        .logo {
            font-size: 2rem;
            font-weight: 800;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-menu {
            display: flex;
            list-style: none;
            gap: 2rem;
        }

        .nav-link {
            color: white;
            text-decoration: none;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .nav-link:hover, .nav-link.active {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            color: white;
        }

        .auth-buttons {
            display: flex;
            gap: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--success-color), var(--warning-color));
            color: white;
        }

        .btn-outline {
            background: transparent;
            color: white;
            border: 2px solid white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }

        /* Main Content */
        .main-content {
            padding-top: 120px;
            min-height: 100vh;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        /* Hero Section */
        .hero-section {
            text-align: center;
            color: white;
            margin-bottom: 4rem;
            position: relative;
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #fff, #f093fb);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-subtitle {
            font-size: 1.3rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto 2rem;
            line-height: 1.8;
        }

        /* Search Section */
        .search-section {
            margin-bottom: 3rem;
        }

        .search-box {
            position: relative;
            max-width: 600px;
            margin: 0 auto;
        }

        .search-input {
            width: 100%;
            padding: 1.5rem 2rem 1.5rem 4rem;
            border: none;
            border-radius: 25px;
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            color: white;
            font-size: 1.1rem;
            border: 1px solid var(--glass-border);
        }

        .search-input::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        .search-input:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.2);
        }

        .search-icon {
            position: absolute;
            left: 1.5rem;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.2rem;
        }

        /* Categories Section */
        .categories-section {
            margin-bottom: 3rem;
        }

        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .category-btn {
            padding: 1rem 1.5rem;
            border: none;
            border-radius: 15px;
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 1px solid var(--glass-border);
        }

        .category-btn:hover, .category-btn.active {
            background: linear-gradient(135deg, var(--accent-color), var(--success-color));
            transform: translateY(-2px);
        }

        /* FAQ Section */
        .faq-section {
            margin-bottom: 4rem;
        }

        .faq-item {
            margin-bottom: 1rem;
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .faq-question {
            padding: 1.5rem 2rem;
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            color: white;
            border: none;
            width: 100%;
            text-align: right;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid var(--glass-border);
            transition: all 0.3s ease;
        }

        .faq-question:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        .faq-question.active {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }

        .faq-icon {
            transition: transform 0.3s ease;
        }

        .faq-question.active .faq-icon {
            transform: rotate(180deg);
        }

        .faq-answer {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
        }

        .faq-answer.active {
            max-height: 500px;
        }

        .faq-answer-content {
            padding: 2rem;
            color: white;
            line-height: 1.8;
            opacity: 0.9;
        }

        .faq-answer-content ul {
            margin: 1rem 0;
            padding-right: 1.5rem;
        }

        .faq-answer-content li {
            margin: 0.5rem 0;
        }

        /* Contact Section */
        .contact-section {
            text-align: center;
            color: white;
            margin-bottom: 4rem;
        }

        .contact-card {
            padding: 3rem;
            max-width: 600px;
            margin: 0 auto;
        }

        .contact-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--accent-color);
        }

        .contact-description {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        .contact-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .contact-btn {
            padding: 1rem 2rem;
            font-size: 1rem;
            border-radius: 15px;
        }

        /* Floating Elements */
        .floating-element {
            position: absolute;
            border-radius: 50%;
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            animation: float 6s ease-in-out infinite;
        }

        .floating-1 {
            width: 80px;
            height: 80px;
            top: 20%;
            left: 5%;
            animation-delay: 0s;
        }

        .floating-2 {
            width: 120px;
            height: 120px;
            top: 60%;
            right: 10%;
            animation-delay: 2s;
        }

        .floating-3 {
            width: 60px;
            height: 60px;
            bottom: 20%;
            left: 15%;
            animation-delay: 4s;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .nav-menu {
                display: none;
            }

            .hero-title {
                font-size: 2.5rem;
            }

            .hero-subtitle {
                font-size: 1.1rem;
            }

            .categories-grid {
                grid-template-columns: 1fr;
            }

            .contact-buttons {
                flex-direction: column;
                align-items: center;
            }

            .faq-question {
                padding: 1rem 1.5rem;
                font-size: 1rem;
            }

            .faq-answer-content {
                padding: 1.5rem;
            }
        }

        /* Animations */
        .fade-in {
            opacity: 0;
            transform: translateY(30px);
            animation: fadeIn 0.8s ease forwards;
        }

        @keyframes fadeIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .slide-in-left {
            opacity: 0;
            transform: translateX(-50px);
            animation: slideInLeft 0.8s ease forwards;
        }

        @keyframes slideInLeft {
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .slide-in-right {
            opacity: 0;
            transform: translateX(50px);
            animation: slideInRight 0.8s ease forwards;
        }

        @keyframes slideInRight {
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
    </style>
</head>
<body>
    <!-- Floating Elements -->
    <div class="floating-element floating-1"></div>
    <div class="floating-element floating-2"></div>
    <div class="floating-element floating-3"></div>

    <!-- Navigation -->
    <nav class="navbar glass">
        <div class="nav-container">
            <a href="home/index.php" class="logo">
                <i class="fas fa-graduation-cap"></i>
                همّة
            </a>
            <ul class="nav-menu">
                <li><a href="index.php" class="nav-link">الرئيسية</a></li>
                <li><a href="index.php#subjects" class="nav-link">المواد الدراسية</a></li>
                <li><a href="about.php" class="nav-link">من نحن</a></li>
                <li><a href="questions.php" class="nav-link active">الأسئلة الشائعة</a></li>
                <li><a href="contact.php" class="nav-link">اتصل بنا</a></li>
            </ul>
            <div class="auth-buttons">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <span style="color: white; margin-left: 1rem;">مرحباً، <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <a href="../<?php echo $_SESSION['role']; ?>/dashboard.php" class="btn btn-outline">
                        <i class="fas fa-tachometer-alt"></i>
                        لوحة التحكم
                    </a>
                    <a href="../auth/logout.php" class="btn btn-primary">
                        <i class="fas fa-sign-out-alt"></i>
                        تسجيل الخروج
                    </a>
                <?php else: ?>
                    <a href="index.php" class="btn btn-outline">
                        <i class="fas fa-sign-in-alt"></i>
                        تسجيل الدخول
                    </a>
                    <a href="index.php" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i>
                        إنشاء حساب
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container">
            <!-- Hero Section -->
            <section class="hero-section fade-in">
                <h1 class="hero-title">الأسئلة الشائعة</h1>
                <p class="hero-subtitle">
                    نجيب على أكثر الأسئلة شيوعاً حول منصة همّة التعليمية وخدماتها
                </p>
            </section>

            <!-- Search Section -->
            <section class="search-section fade-in">
                <div class="search-box">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" class="search-input" placeholder="ابحث في الأسئلة الشائعة..." id="searchInput">
                </div>
            </section>

            <!-- Categories Section -->
            <section class="categories-section fade-in">
                <div class="categories-grid">
                    <button class="category-btn active" data-category="all">جميع الأسئلة</button>
                    <button class="category-btn" data-category="registration">التسجيل والحساب</button>
                    <button class="category-btn" data-category="courses">المواد الدراسية</button>
                    <button class="category-btn" data-category="payment">الدفع والاشتراك</button>
                    <button class="category-btn" data-category="technical">المشاكل التقنية</button>
                    <button class="category-btn" data-category="certificates">الشهادات</button>
                </div>
            </section>

            <!-- FAQ Section -->
            <section class="faq-section">
                <!-- Registration FAQs -->
                <div class="faq-item slide-in-left" data-category="registration">
                    <button class="faq-question">
                        <span>كيف يمكنني إنشاء حساب جديد في المنصة؟</span>
                        <i class="fas fa-chevron-down faq-icon"></i>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-answer-content">
                            <p>يمكنك إنشاء حساب جديد بسهولة من خلال الخطوات التالية:</p>
                            <ul>
                                <li>اضغط على زر "إنشاء حساب" في الصفحة الرئيسية</li>
                                <li>املأ البيانات المطلوبة: الاسم الكامل، اسم المستخدم، البريد الإلكتروني</li>
                                <li>اختر نوع الحساب (طالب أو معلم)</li>
                                <li>أدخل كلمة مرور قوية وأكدها</li>
                                <li>اضغط على "إنشاء الحساب" وستتمكن من تسجيل الدخول فوراً</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="faq-item slide-in-right" data-category="registration">
                    <button class="faq-question">
                        <span>ماذا أفعل إذا نسيت كلمة المرور؟</span>
                        <i class="fas fa-chevron-down faq-icon"></i>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-answer-content">
                            <p>في حالة نسيان كلمة المرور، يمكنك استعادتها بسهولة:</p>
                            <ul>
                                <li>اضغط على "نسيت كلمة المرور؟" في صفحة تسجيل الدخول</li>
                                <li>أدخل البريد الإلكتروني المرتبط بحسابك</li>
                                <li>ستصلك رسالة بريد إلكتروني تحتوي على رابط إعادة تعيين كلمة المرور</li>
                                <li>اتبع التعليمات في الرسالة لإنشاء كلمة مرور جديدة</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Courses FAQs -->
                <div class="faq-item slide-in-left" data-category="courses">
                    <button class="faq-question">
                        <span>ما هي المواد الدراسية المتاحة في المنصة؟</span>
                        <i class="fas fa-chevron-down faq-icon"></i>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-answer-content">
                            <p>نوفر مجموعة شاملة من المواد الدراسية لطلاب التوجيهي:</p>
                            <ul>
                                <li><strong>المواد العلمية:</strong> الرياضيات، الفيزياء، الكيمياء، الأحياء</li>
                                <li><strong>المواد الأدبية:</strong> اللغة العربية، التاريخ، الجغرافيا، التربية الإسلامية</li>
                                <li><strong>اللغات:</strong> اللغة الإنجليزية، الفرنسية، الألمانية</li>
                                <li>جميع المواد تُدرّس من قبل معلمين متخصصين وذوي خبرة</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="faq-item slide-in-right" data-category="courses">
                    <button class="faq-question">
                        <span>كيف يمكنني التسجيل في مادة دراسية؟</span>
                        <i class="fas fa-chevron-down faq-icon"></i>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-answer-content">
                            <p>التسجيل في المواد الدراسية سهل وسريع:</p>
                            <ul>
                                <li>تصفح المواد المتاحة في الصفحة الرئيسية أو قسم المواد</li>
                                <li>اختر المادة التي تريد دراستها</li>
                                <li>اضغط على "التسجيل في المادة"</li>
                                <li>أكمل عملية الدفع إذا كانت المادة مدفوعة</li>
                                <li>ستظهر المادة في لوحة التحكم الخاصة بك فوراً</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Payment FAQs -->
                <div class="faq-item slide-in-left" data-category="payment">
                    <button class="faq-question">
                        <span>ما هي طرق الدفع المتاحة؟</span>
                        <i class="fas fa-chevron-down faq-icon"></i>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-answer-content">
                            <p>نوفر عدة طرق دفع مريحة وآمنة:</p>
                            <ul>
                                <li>البطاقات الائتمانية (فيزا، ماستركارد)</li>
                                <li>التحويل البنكي المحلي</li>
                                <li>محافظ الدفع الإلكترونية</li>
                                <li>الدفع النقدي في نقاط البيع المعتمدة</li>
                                <li>خصومات خاصة للطلاب المتفوقين والأسر المحتاجة</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="faq-item slide-in-right" data-category="payment">
                    <button class="faq-question">
                        <span>هل يمكنني الحصول على استرداد للمبلغ المدفوع؟</span>
                        <i class="fas fa-chevron-down faq-icon"></i>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-answer-content">
                            <p>نعم، لدينا سياسة استرداد عادلة ومرنة:</p>
                            <ul>
                                <li>يمكن طلب الاسترداد خلال 7 أيام من التسجيل</li>
                                <li>الاسترداد الكامل إذا لم تبدأ في دراسة المادة</li>
                                <li>استرداد جزئي حسب نسبة التقدم في المادة</li>
                                <li>لا يوجد استرداد بعد إتمام 50% من المادة</li>
                                <li>تواصل مع خدمة العملاء لمعالجة طلب الاسترداد</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Technical FAQs -->
                <div class="faq-item slide-in-left" data-category="technical">
                    <button class="faq-question">
                        <span>ما هي متطلبات النظام لاستخدام المنصة؟</span>
                        <i class="fas fa-chevron-down faq-icon"></i>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-answer-content">
                            <p>المنصة مصممة للعمل على جميع الأجهزة الحديثة:</p>
                            <ul>
                                <li><strong>المتصفحات:</strong> Chrome، Firefox، Safari، Edge (أحدث إصدار)</li>
                                <li><strong>الأجهزة:</strong> كمبيوتر، لابتوب، تابلت، هاتف ذكي</li>
                                <li><strong>الإنترنت:</strong> اتصال بسرعة 1 ميجا على الأقل</li>
                                <li><strong>نظام التشغيل:</strong> Windows، Mac، iOS، Android</li>
                                <li>لا حاجة لتثبيت أي برامج إضافية</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="faq-item slide-in-right" data-category="technical">
                    <button class="faq-question">
                        <span>ماذا أفعل إذا واجهت مشكلة تقنية؟</span>
                        <i class="fas fa-chevron-down faq-icon"></i>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-answer-content">
                            <p>نحن هنا لمساعدتك في حل أي مشكلة تقنية:</p>
                            <ul>
                                <li>تحقق من اتصال الإنترنت وحدث المتصفح</li>
                                <li>امسح ذاكرة التخزين المؤقت وملفات الكوكيز</li>
                                <li>جرب استخدام متصفح آخر أو جهاز مختلف</li>
                                <li>تواصل مع الدعم التقني عبر الواتساب أو البريد الإلكتروني</li>
                                <li>فريق الدعم متاح 24/7 لمساعدتك</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Certificates FAQs -->
                <div class="faq-item slide-in-left" data-category="certificates">
                    <button class="faq-question">
                        <span>هل أحصل على شهادة بعد إتمام المادة؟</span>
                        <i class="fas fa-chevron-down faq-icon"></i>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-answer-content">
                            <p>نعم، نقدم شهادات إتمام معتمدة:</p>
                            <ul>
                                <li>شهادة إتمام لكل مادة تكملها بنجاح</li>
                                <li>الشهادة تحمل اسمك واسم المادة وتاريخ الإتمام</li>
                                <li>يمكن تحميل الشهادة بصيغة PDF</li>
                                <li>الشهادات معتمدة من وزارة التربية والتعليم</li>
                                <li>يمكن استخدامها في التقديم للجامعات</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="faq-item slide-in-right" data-category="certificates">
                    <button class="faq-question">
                        <span>ما هي شروط الحصول على الشهادة؟</span>
                        <i class="fas fa-chevron-down faq-icon"></i>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-answer-content">
                            <p>للحصول على الشهادة، يجب استيفاء الشروط التالية:</p>
                            <ul>
                                <li>إتمام جميع دروس المادة (100%)</li>
                                <li>حل جميع التمارين والواجبات</li>
                                <li>اجتياز الاختبار النهائي بدرجة 70% على الأقل</li>
                                <li>عدم تجاوز المدة الزمنية المحددة للمادة</li>
                                <li>الالتزام بقواعد السلوك الأكاديمي</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- General FAQs -->
                <div class="faq-item slide-in-left" data-category="all">
                    <button class="faq-question">
                        <span>هل المنصة مناسبة لجميع مستويات الطلاب؟</span>
                        <i class="fas fa-chevron-down faq-icon"></i>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-answer-content">
                            <p>نعم، المنصة مصممة لتناسب جميع المستويات:</p>
                            <ul>
                                <li><strong>المبتدئين:</strong> دروس تأسيسية وشرح مبسط</li>
                                <li><strong>المتوسطين:</strong> تمارين متدرجة وأمثلة متنوعة</li>
                                <li><strong>المتقدمين:</strong> تحديات إضافية ومسائل متقدمة</li>
                                <li>اختبارات تحديد المستوى في بداية كل مادة</li>
                                <li>مسارات تعليمية مخصصة حسب مستوى كل طالب</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="faq-item slide-in-right" data-category="all">
                    <button class="faq-question">
                        <span>كيف يمكنني التواصل مع المعلمين؟</span>
                        <i class="fas fa-chevron-down faq-icon"></i>
                    </button>
                    <div class="faq-answer">
                        <div class="faq-answer-content">
                            <p>نوفر عدة طرق للتواصل مع المعلمين:</p>
                            <ul>
                                <li>منتدى النقاش داخل كل مادة</li>
                                <li>الرسائل الخاصة مع المعلم</li>
                                <li>جلسات الأسئلة والأجوبة المباشرة</li>
                                <li>التعليقات على الدروس والتمارين</li>
                                <li>ساعات مكتبية أسبوعية لكل معلم</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Contact Section -->
            <section class="contact-section">
                <div class="contact-card glass fade-in">
                    <h2 class="contact-title">لم تجد إجابة لسؤالك؟</h2>
                    <p class="contact-description">
                        فريق الدعم متاح دائماً لمساعدتك والإجابة على جميع استفساراتك
                    </p>
                    <div class="contact-buttons">
                        <a href="contact.php" class="btn btn-primary contact-btn">
                            <i class="fas fa-envelope"></i>
                            تواصل معنا
                        </a>
                        <a href="index.php" class="btn btn-outline contact-btn">
                            <i class="fas fa-home"></i>
                            العودة للرئيسية
                        </a>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <script>
        // FAQ Toggle Functionality
        document.querySelectorAll('.faq-question').forEach(question => {
            question.addEventListener('click', () => {
                const answer = question.nextElementSibling;
                const isActive = question.classList.contains('active');
                
                // Close all other FAQs
                document.querySelectorAll('.faq-question').forEach(q => {
                    q.classList.remove('active');
                    q.nextElementSibling.classList.remove('active');
                });
                
                // Toggle current FAQ
                if (!isActive) {
                    question.classList.add('active');
                    answer.classList.add('active');
                }
            });
        });

        // Category Filter Functionality
        document.querySelectorAll('.category-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const category = btn.dataset.category;
                
                // Update active button
                document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                
                // Filter FAQ items
                document.querySelectorAll('.faq-item').forEach(item => {
                    if (category === 'all' || item.dataset.category === category) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        });

        // Search Functionality
        document.getElementById('searchInput').addEventListener('input', (e) => {
            const searchTerm = e.target.value.toLowerCase();
            
            document.querySelectorAll('.faq-item').forEach(item => {
                const question = item.querySelector('.faq-question span').textContent.toLowerCase();
                const answer = item.querySelector('.faq-answer-content').textContent.toLowerCase();
                
                if (question.includes(searchTerm) || answer.includes(searchTerm)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });

        // Intersection Observer for animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animationPlayState = 'running';
                }
            });
        }, observerOptions);

        // Observe animated elements
        document.querySelectorAll('.fade-in, .slide-in-left, .slide-in-right').forEach(el => {
            el.style.animationPlayState = 'paused';
            observer.observe(el);
        });

        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 100) {
                navbar.style.background = 'rgba(255, 255, 255, 0.2)';
                navbar.style.backdropFilter = 'blur(15px)';
            } else {
                navbar.style.background = 'rgba(255, 255, 255, 0.1)';
                navbar.style.backdropFilter = 'blur(10px)';
            }
        });
    </script>
</body>
</html>