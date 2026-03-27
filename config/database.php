<?php

// إعدادات قاعدة البيانات
$db_config = [
    "host" => "localhost",
    "dbname" => "himma_tawjihi",
    "username" => "root",
    "password" => "",
    "charset" => "utf8mb4",
    "options" => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ]
];

// متغير الاتصال العام
$conn = null;
$pdo = null;

// كلاس قاعدة البيانات المُحدث
class Database {
    private $host;
    private $dbname;
    private $username;
    private $password;
    private $charset;
    private $options;
    private $pdo;

    public function __construct() {
        global $db_config;
        $this->host = $db_config["host"];
        $this->dbname = $db_config["dbname"];
        $this->username = $db_config["username"];
        $this->password = $db_config["password"];
        $this->charset = $db_config["charset"];
        $this->options = $db_config["options"];
    }

    public function connect() {
        if ($this->pdo === null) {
            try {
                $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset={$this->charset}";
                $this->pdo = new PDO($dsn, $this->username, $this->password, $this->options);
                $this->pdo->exec("SET time_zone = '+03:00'");
                
                // تحديث قاعدة البيانات تلقائياً
                $this->updateDatabase();
                
            } catch (PDOException $e) {
                error_log("Database connection error: " . $e->getMessage());
                throw new Exception("فشل في الاتصال بقاعدة البيانات");
            }
        }
        return $this->pdo;
    }

    public function getPDO() {
        return $this->connect();
    }
    
    /**
     * تحديث قاعدة البيانات تلقائياً
     */
    private function updateDatabase() {
        try {
            // التأكد من وجود جدول quiz_questions مع الحقول المطلوبة
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS quiz_questions (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    quiz_id INT NOT NULL,
                    question_text TEXT NOT NULL,
                    question_type ENUM('multiple_choice', 'true_false', 'short_answer') DEFAULT 'multiple_choice',
                    options JSON NULL,
                    correct_answer TEXT,
                    marks INT DEFAULT 5,
                    order_num INT DEFAULT 1,
                    explanation TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // التأكد من وجود جدول quiz_options
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS quiz_options (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    question_id INT NOT NULL,
                    option_text TEXT NOT NULL,
                    is_correct BOOLEAN DEFAULT FALSE,
                    order_number INT DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // التأكد من وجود جدول quiz_results
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS quiz_results (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    quiz_id INT NOT NULL,
                    user_id INT NOT NULL,
                    score DECIMAL(5,2) NOT NULL,
                    total_questions INT NOT NULL,
                    correct_answers INT NOT NULL,
                    time_taken INT NOT NULL,
                    answers JSON,
                    is_passed BOOLEAN DEFAULT FALSE,
                    attempt_number INT DEFAULT 1,
                    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // التأكد من وجود الحقول المطلوبة في جدول quizzes
            try {
                $this->pdo->exec("ALTER TABLE quizzes ADD COLUMN IF NOT EXISTS quiz_type ENUM('midterm', 'final', 'quiz', 'assignment') DEFAULT 'quiz'");
            } catch (Exception $e) {
                // الحقل موجود بالفعل
            }
            
            try {
                $this->pdo->exec("ALTER TABLE quizzes ADD COLUMN IF NOT EXISTS attempts_allowed INT DEFAULT 3");
            } catch (Exception $e) {
                // الحقل موجود بالفعل
            }

            // إنشاء الفهارس
            $indexes = [
                "CREATE INDEX IF NOT EXISTS idx_quiz_questions_quiz ON quiz_questions(quiz_id)",
                "CREATE INDEX IF NOT EXISTS idx_quiz_questions_type ON quiz_questions(question_type)",
                "CREATE INDEX IF NOT EXISTS idx_quiz_options_question ON quiz_options(question_id)",
                "CREATE INDEX IF NOT EXISTS idx_quiz_results_quiz ON quiz_results(quiz_id)",
                "CREATE INDEX IF NOT EXISTS idx_quiz_results_user ON quiz_results(user_id)"
            ];

            foreach ($indexes as $index) {
                try {
                    $this->pdo->exec($index);
                } catch (Exception $e) {
                    // الفهرس موجود بالفعل
                }
            }

            // إدراج بيانات تجريبية إذا لم تكن موجودة
            $this->insertSampleDataIfNeeded();
            
        } catch (Exception $e) {
            // تسجيل الخطأ فقط دون إيقاف التطبيق
            error_log("Database update error: " . $e->getMessage());
        }
    }
    
    /**
     * إدراج بيانات تجريبية إذا لم تكن موجودة
     */
    private function insertSampleDataIfNeeded() {
        try {
            // فحص وجود معلم تجريبي
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE email = 'teacher@demo.com'");
            $stmt->execute();
            $teacher_exists = $stmt->fetchColumn() > 0;
            
            if (!$teacher_exists) {
                // إدراج معلم تجريبي
                $password = password_hash('123456', PASSWORD_DEFAULT);
                $stmt = $this->pdo->prepare("
                    INSERT INTO users (username, full_name, email, password, role, is_active, email_verified) 
                    VALUES ('teacher_demo', 'معلم تجريبي', 'teacher@demo.com', ?, 'teacher', 1, 1)
                ");
                $stmt->execute([$password]);
                
                $teacher_id = $this->pdo->lastInsertId();
                
                if ($teacher_id > 0) {
                    // إدراج مادة تجريبية
                    $stmt = $this->pdo->prepare("
                        INSERT INTO subjects (teacher_id, name, description, category, level, price, duration_weeks, is_active) 
                        VALUES (?, 'مادة تجريبية للاختبارات', 'مادة للتجربة والاختبار في النظام المبسط', 'scientific', 'intermediate', 0, 12, 1)
                    ");
                    $stmt->execute([$teacher_id]);
                }
            }
        } catch (Exception $e) {
            // تسجيل الخطأ فقط
            error_log("Sample data insertion error: " . $e->getMessage());
        }
    }
}

// إنشاء اتصال عام
try {
    $db = new Database();
    $conn = $db->connect();
    $pdo = $conn;
} catch (Exception $e) {
    error_log("Database initialization error: " . $e->getMessage());
    $conn = null;
    $pdo = null;
}

// إنشاء الجداول المطلوبة
function create_required_tables() {
    global $conn;
    
    if (!$conn) {
        throw new Exception("لا يوجد اتصال بقاعدة البيانات");
    }

    $tables = [
        "users" => "
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(100) UNIQUE,
                full_name VARCHAR(100) NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                phone VARCHAR(20),
                role ENUM('admin', 'teacher', 'student') NOT NULL DEFAULT 'student',
                profile_image VARCHAR(255),
                bio TEXT,
                is_active BOOLEAN DEFAULT TRUE,
                email_verified BOOLEAN DEFAULT TRUE,
                avatar VARCHAR(500) DEFAULT NULL,
                last_seen TIMESTAMP NULL DEFAULT NULL,
                last_login TIMESTAMP NULL DEFAULT NULL,
                is_online BOOLEAN DEFAULT FALSE,
                status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_username (username),
                INDEX idx_email (email),
                INDEX idx_role (role),
                INDEX idx_status (status),
                INDEX idx_last_seen (last_seen)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        "subjects" => "
            CREATE TABLE IF NOT EXISTS subjects (
                id INT AUTO_INCREMENT PRIMARY KEY,
                teacher_id INT,
                name VARCHAR(200) NOT NULL,
                description TEXT,
                category ENUM('scientific', 'literary', 'languages') NOT NULL DEFAULT 'scientific',
                level ENUM('beginner', 'intermediate', 'advanced') NOT NULL DEFAULT 'beginner',
                price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                duration_weeks INT DEFAULT 12,
                is_active BOOLEAN DEFAULT TRUE,
                is_featured BOOLEAN DEFAULT FALSE,
                thumbnail VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_teacher (teacher_id),
                INDEX idx_category (category),
                INDEX idx_active (is_active),
                INDEX idx_featured (is_featured)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        "enrollments" => "
            CREATE TABLE IF NOT EXISTS enrollments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                subject_id INT NOT NULL,
                enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                progress_percentage DECIMAL(5,2) DEFAULT 0.00,
                status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
                payment_status ENUM('pending', 'paid', 'refunded') DEFAULT 'pending',
                payment_amount DECIMAL(10,2),
                payment_date TIMESTAMP NULL,
                INDEX idx_user (user_id),
                INDEX idx_subject (subject_id),
                INDEX idx_status (status),
                UNIQUE KEY unique_enrollment (user_id, subject_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        "lessons" => "
            CREATE TABLE IF NOT EXISTS lessons (
                id INT AUTO_INCREMENT PRIMARY KEY,
                subject_id INT NOT NULL,
                title VARCHAR(200) NOT NULL,
                description TEXT,
                content TEXT,
                video_url VARCHAR(500),
                order_num INT DEFAULT 1,
                duration_minutes INT DEFAULT 0,
                is_free BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_subject (subject_id),
                INDEX idx_order (order_num)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        "quizzes" => "
            CREATE TABLE IF NOT EXISTS quizzes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                subject_id INT NOT NULL,
                title VARCHAR(200) NOT NULL,
                description TEXT,
                quiz_type ENUM('midterm', 'final', 'quiz', 'assignment') NOT NULL DEFAULT 'quiz',
                total_marks INT NOT NULL DEFAULT 100,
                pass_marks INT NOT NULL DEFAULT 60,
                duration INT NOT NULL DEFAULT 60,
                attempts_allowed INT DEFAULT 3,
                start_date DATETIME NULL,
                end_date DATETIME NULL,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_subject (subject_id),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        "notifications" => "
            CREATE TABLE IF NOT EXISTS notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                title VARCHAR(200) NOT NULL,
                message TEXT NOT NULL,
                type ENUM('system', 'chat', 'assignment', 'grade', 'announcement') DEFAULT 'system',
                related_id INT NULL,
                is_read BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user (user_id),
                INDEX idx_read (is_read),
                INDEX idx_type (type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ",
        
        "contact_messages" => "
            CREATE TABLE IF NOT EXISTS contact_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL,
                subject VARCHAR(200) NOT NULL,
                message TEXT NOT NULL,
                status ENUM('new', 'read', 'replied') DEFAULT 'new',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_status (status),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        "
    ];

    foreach ($tables as $table_name => $sql) {
        try {
            $conn->exec($sql);
            echo "<p>✅ تم إنشاء جدول {$table_name} بنجاح</p>";
        } catch (PDOException $e) {
            echo "<p>❌ خطأ في إنشاء جدول {$table_name}: " . $e->getMessage() . "</p>";
        }
    }
}

// إدراج بيانات تجريبية
function insert_sample_data() {
    global $conn;
    
    if (!$conn) {
        throw new Exception("لا يوجد اتصال بقاعدة البيانات");
    }

    // تحميل دوال التشفير من ملف functions.php
    require_once __DIR__ . "/includes/functions.php";

    // التحقق من وجود بيانات
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users");
    $stmt->execute();
    $user_count = $stmt->fetchColumn();

    if ($user_count == 0) {
        // إدراج المستخدمين التجريبيين
        $default_password = hash_password("123456");
        
        $users = [
            ["admin", "المدير العام", "admin@himma.edu", $default_password, "admin"],
            ["teacher1", "أحمد محمد الخالدي", "teacher@himma.edu", $default_password, "teacher"],
            ["student1", "فاطمة علي الزهراني", "student@himma.edu", $default_password, "student"]
        ];

        foreach ($users as $user) {
            try {
                $stmt = $conn->prepare("
                    INSERT INTO users (username, full_name, email, password, role, is_active, email_verified) 
                    VALUES (?, ?, ?, ?, ?, 1, 1)
                ");
                $stmt->execute($user);
                echo "<p>✅ تم إدراج المستخدم: {$user[1]}</p>";
            } catch (PDOException $e) {
                echo "<p>⚠️ المستخدم {$user[1]} موجود بالفعل</p>";
            }
        }

        // إدراج مواد تجريبية
        $subjects = [
            [2, "الرياضيات المتقدمة", "دراسة شاملة للرياضيات على مستوى التوجيهي العلمي تشمل التفاضل والتكامل والجبر المتقدم", "scientific", "advanced", 180.00, 16, 1],
            [2, "الفيزياء العامة", "أساسيات الفيزياء والميكانيكا والكهرباء والمغناطيسية والبصريات مع التطبيقات العملية", "scientific", "intermediate", 150.00, 14, 1],
            [2, "الكيمياء التطبيقية", "دراسة الكيمياء العضوية وغير العضوية مع التجارب المعملية والتطبيقات الحياتية", "scientific", "intermediate", 160.00, 12, 1],
            [2, "اللغة العربية وآدابها", "النحو والصرف والبلاغة والأدب العربي من العصر الجاهلي حتى العصر الحديث", "literary", "intermediate", 120.00, 12, 1],
            [2, "اللغة الإنجليزية المتقدمة", "تطوير مهارات القراءة والكتابة والمحادثة والاستماع مع التركيز على القواعد المتقدمة", "languages", "advanced", 140.00, 14, 1],
            [2, "التاريخ الإسلامي", "دراسة التاريخ الإسلامي من البعثة النبوية حتى العصر العثماني مع التحليل والنقد", "literary", "intermediate", 110.00, 10, 1]
        ];

        foreach ($subjects as $subject) {
            try {
                $stmt = $conn->prepare("
                    INSERT INTO subjects (teacher_id, name, description, category, level, price, duration_weeks, is_featured) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute($subject);
                echo "<p>✅ تم إدراج المادة: {$subject[1]}</p>";
            } catch (PDOException $e) {
                echo "<p>⚠️ المادة {$subject[1]} موجودة بالفعل</p>";
            }
        }
    } else {
        echo "<p>ℹ️ البيانات التجريبية موجودة بالفعل</p>";
    }
}

?>