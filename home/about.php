<?php
/**
 * صفحة من نحن - منصة همّة التوجيهي
 * About Us Page - Himma Tawjihi Platform
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
    <title>من نحن - منصة همّة التعليمية</title>
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
            max-width: 1200px;
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
            font-size: 4rem;
            font-weight: 800;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #fff, #f093fb);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-subtitle {
            font-size: 1.5rem;
            opacity: 0.9;
            max-width: 800px;
            margin: 0 auto 2rem;
            line-height: 1.8;
        }

        /* Story Section */
        .story-section {
            margin-bottom: 4rem;
        }

        .story-card {
            padding: 3rem;
            color: white;
            margin-bottom: 2rem;
        }

        .story-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 2rem;
            text-align: center;
            color: var(--accent-color);
        }

        .story-content {
            font-size: 1.2rem;
            line-height: 2;
            text-align: justify;
            margin-bottom: 2rem;
        }

        .story-highlight {
            background: linear-gradient(135deg, var(--accent-color), var(--success-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
        }

        /* Team Section */
        .team-section {
            margin-bottom: 4rem;
        }

        .section-title {
            text-align: center;
            font-size: 3rem;
            font-weight: 800;
            color: white;
            margin-bottom: 3rem;
        }

        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .team-card {
            padding: 2rem;
            color: white;
            text-align: center;
            transition: all 0.3s ease;
        }

        .team-card:hover {
            transform: translateY(-10px);
        }

        .team-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent-color), var(--success-color));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
            margin: 0 auto 1.5rem;
        }

        .team-name {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .team-role {
            color: var(--accent-color);
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .team-description {
            opacity: 0.9;
            line-height: 1.6;
        }

        /* Mission Section */
        .mission-section {
            margin-bottom: 4rem;
        }

        .mission-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .mission-card {
            padding: 2rem;
            color: white;
            text-align: center;
        }

        .mission-icon {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            margin: 0 auto 1.5rem;
        }

        .mission-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .mission-description {
            opacity: 0.9;
            line-height: 1.6;
        }

        /* Gaza Section */
        .gaza-section {
            margin-bottom: 4rem;
        }

        .gaza-card {
            padding: 3rem;
            color: white;
            text-align: center;
        }

        .gaza-flag {
            width: 100px;
            height: 60px;
            margin: 0 auto 2rem;
            background: linear-gradient(to bottom, #000 33.33%, #fff 33.33%, #fff 66.66%, #00b04f 66.66%);
            border-radius: 10px;
            position: relative;
        }

        .gaza-flag::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 0;
            height: 0;
            border-style: solid;
            border-width: 30px 0 30px 40px;
            border-color: transparent transparent transparent #dc143c;
        }

        .gaza-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 2rem;
            color: var(--success-color);
        }

        .gaza-content {
            font-size: 1.2rem;
            line-height: 2;
            text-align: justify;
        }

        /* Contact Section */
        .contact-section {
            text-align: center;
            color: white;
            margin-bottom: 4rem;
        }

        .contact-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 2rem;
        }

        .contact-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .contact-btn {
            padding: 1rem 2rem;
            font-size: 1.1rem;
            border-radius: 20px;
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
                font-size: 1.2rem;
            }

            .team-grid {
                grid-template-columns: 1fr;
            }

            .mission-grid {
                grid-template-columns: 1fr;
            }

            .contact-buttons {
                flex-direction: column;
                align-items: center;
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
                <li><a href="about.php" class="nav-link active">من نحن</a></li>
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
                <h1 class="hero-title">من نحن</h1>
                <p class="hero-subtitle">
                    نحن فريق من المطورين والمعلمين المبادرين الذين يؤمنون بقوة التعليم في تغيير الحياة
                    <br>
                    ولدت منصة همّة من رحم المعاناة والتحدي، لتكون شعلة أمل في ظلام الحصار
                </p>
            </section>

            <!-- Story Section -->
            <section class="story-section">
                <div class="story-card glass fade-in">
                    <h2 class="story-title">قصتنا</h2>
                    <div class="story-content">
                        <p>
                            في قلب <span class="story-highlight">قطاع غزة</span>، حيث تتحدى الإرادة كل الصعاب، وُلدت فكرة منصة همّة التعليمية. 
                            في ظل الظروف الاستثنائية التي يواجهها أبناء غزة، من انقطاع الكهرباء المستمر، وضعف الاتصال بالإنترنت، 
                            ونقص الموارد التعليمية، قرر فريق من المطورين الشباب أن يحولوا هذه التحديات إلى فرص.
                        </p>
                        
                        <p>
                            لقد شهدنا كيف يعاني طلاب <span class="story-highlight">الثانوية العامة (التوجيهي)</span> في الوصول إلى تعليم عالي الجودة، 
                            وكيف تؤثر الظروف الصعبة على مستقبلهم الأكاديمي. من هنا جاءت الفكرة: لماذا لا ننشئ منصة تعليمية تتكيف مع 
                            ظروفنا الخاصة؟ منصة تعمل حتى مع الإنترنت البطيء، وتوفر محتوى تعليمي متميز يمكن الوصول إليه في أي وقت.
                        </p>

                        <p>
                            بدأنا العمل بإمكانيات محدودة، ولكن بعزيمة لا تنكسر. عملنا ليلاً ونهاراً، نتحدى انقطاع الكهرباء 
                            وضعف الإنترنت، لنطور منصة تعليمية تلبي احتياجات طلابنا الأعزاء. كل سطر من الكود كُتب بحب وأمل، 
                            وكل ميزة صُممت لتخدم هدفاً واحداً: <span class="story-highlight">تمكين طلاب غزة من الوصول إلى تعليم متميز</span>.
                        </p>
                    </div>
                </div>
            </section>

            <!-- Gaza Section -->
            <section class="gaza-section">
                <div class="gaza-card glass fade-in">
                    <div class="gaza-flag"></div>
                    <h2 class="gaza-title">من أجل غزة... من أجل المستقبل</h2>
                    <div class="gaza-content">
                        <p>
                            غزة ليست مجرد مكان نعيش فيه، بل هي هويتنا وانتماؤنا. هي الأرض التي علمتنا أن الإبداع يولد من رحم المعاناة، 
                            وأن الأمل أقوى من كل الحواجز. في كل مرة ينقطع فيها التيار الكهربائي، نتذكر لماذا نعمل. في كل مرة يتقطع 
                            فيها الإنترنت، نتذكر أن هناك طالباً ينتظر درسه التالي.
                        </p>
                        
                        <p>
                            لقد صممنا منصة همّة لتكون أكثر من مجرد موقع تعليمي. إنها رسالة للعالم بأن الحصار لن يوقف أحلامنا، 
                            وأن الصعوبات لن تمنعنا من بناء مستقبل أفضل لأجيالنا القادمة. كل طالب ينجح من خلال منصتنا هو انتصار 
                            لإرادة الحياة على قوى الظلام.
                        </p>
                    </div>
                </div>
            </section>

            <!-- Team Section -->
            <section class="team-section">
                <h2 class="section-title fade-in">فريق العمل</h2>
                <div class="team-grid">
                    <div class="team-card glass slide-in-left">
                        <div class="team-avatar">
                            <i class="fas fa-code"></i>
                        </div>
                        <h3 class="team-name">فريق التطوير</h3>
                        <p class="team-role">مطورو الواجهات الأمامية والخلفية</p>
                        <p class="team-description">
                            مجموعة من المطورين الشباب المتخصصين في تقنيات الويب الحديثة، 
                            يعملون بشغف لتطوير منصة تعليمية متطورة وسهلة الاستخدام.
                        </p>
                    </div>

                    <div class="team-card glass fade-in">
                        <div class="team-avatar">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                        <h3 class="team-name">الفريق التعليمي</h3>
                        <p class="team-role">خبراء المناهج والمحتوى التعليمي</p>
                        <p class="team-description">
                            نخبة من المعلمين والخبراء التربويين المتخصصين في مناهج التوجيهي، 
                            يعملون على إعداد محتوى تعليمي عالي الجودة ومناسب للطلاب.
                        </p>
                    </div>

                    <div class="team-card glass slide-in-right">
                        <div class="team-avatar">
                            <i class="fas fa-palette"></i>
                        </div>
                        <h3 class="team-name">فريق التصميم</h3>
                        <p class="team-role">مصممو واجهات المستخدم والتجربة</p>
                        <p class="team-description">
                            مصممون مبدعون يهتمون بكل تفصيلة في تجربة المستخدم، 
                            لضمان واجهة جميلة وسهلة الاستخدام تناسب جميع الطلاب.
                        </p>
                    </div>
                </div>
            </section>

            <!-- Mission Section -->
            <section class="mission-section">
                <h2 class="section-title fade-in">رسالتنا</h2>
                <div class="mission-grid">
                    <div class="mission-card glass fade-in">
                        <div class="mission-icon">
                            <i class="fas fa-graduation-cap"></i>
                        </div>
                        <h3 class="mission-title">التعليم للجميع</h3>
                        <p class="mission-description">
                            نؤمن بأن التعليم حق لكل طالب، بغض النظر عن الظروف المحيطة. 
                            نسعى لتوفير تعليم عالي الجودة يمكن الوصول إليه من أي مكان وفي أي وقت.
                        </p>
                    </div>

                    <div class="mission-card glass fade-in" style="animation-delay: 0.2s;">
                        <div class="mission-icon">
                            <i class="fas fa-rocket"></i>
                        </div>
                        <h3 class="mission-title">الابتكار والتطوير</h3>
                        <p class="mission-description">
                            نستخدم أحدث التقنيات لتطوير حلول تعليمية مبتكرة تتكيف مع 
                            التحديات المحلية وتلبي احتياجات الطلاب الفعلية.
                        </p>
                    </div>

                    <div class="mission-card glass fade-in" style="animation-delay: 0.4s;">
                        <div class="mission-icon">
                            <i class="fas fa-heart"></i>
                        </div>
                        <h3 class="mission-title">الشغف والالتزام</h3>
                        <p class="mission-description">
                            نعمل بشغف وحب لخدمة مجتمعنا، ونلتزم بتقديم أفضل ما لدينا 
                            لضمان نجاح كل طالب يثق بنا في رحلته التعليمية.
                        </p>
                    </div>
                </div>
            </section>

            <!-- Contact Section -->
            <section class="contact-section">
                <div class="glass fade-in" style="padding: 3rem;">
                    <h2 class="contact-title">انضم إلى رحلتنا</h2>
                    <p style="font-size: 1.2rem; margin-bottom: 2rem; opacity: 0.9;">
                        نحن نؤمن بأن النجاح يأتي من العمل الجماعي. انضم إلينا في رحلتنا لتطوير التعليم في غزة
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