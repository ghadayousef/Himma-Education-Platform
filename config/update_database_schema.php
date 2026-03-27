<?php
/**
 * تحديث قاعدة البيانات لدعم النظام المحسّن
 * منصة همة التوجيهي
 *
 * هذا الملف معدل ليقوم بما يلي:
 *  - قبل إنشاء أي جدول يتحقق من المساحة الحرة على القرص في مجلد بيانات MySQL (datadir)
 *  - إذا كانت المساحة أقل من العتبة المحددة، يحاول تنظيف مجلدات مؤقتة (بحذر) مثل tmp PHP و /tmp و tmpdir الخاص بـ MySQL (إذا كان قابل للكتابة)
 *  - يحاول إعادة المحاولة بعد التنظيف، وإن لم تنجح يعطي رسالة خطأ مفيدة مع مقترحات لتدخل يدوي
 *
 * ملاحظة أمان: عمليات الحذف التلقائي تجري فقط على ملفات مؤقتة قديمة (أكبر من $cleanupFileAgeSeconds)
 * ويجب تشغيل هذا السكربت بواسطة مستخدم لديه صلاحيات إدارة قاعدة البيانات وقراءة/حذف الملفات المؤقتة عند الحاجة.
 */

require_once 'database.php';

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

/**
 * إعدادات السلوك
 */
const MIN_FREE_BYTES = 150 * 1024 * 1024; // 150 MB مطلوب كمساحة حرة على الأقل (قابلة للتعديل)
const cleanupFileAgeSeconds = 24 * 3600;  // حذف الملفات الأقدم من 24 ساعة في مجلدات tmp (قابلة للتعديل)
const RETRY_AFTER_CLEANUP = 1;            // عدد المحاولات بعد التنظيف

/**
 * الحصول على مسار datadir الخاص بخادم MySQL
 */
function getMySQLDataDir(PDO $conn) {
    try {
        $stmt = $conn->query("SHOW VARIABLES LIKE 'datadir'");
        $row = $stmt->fetch();
        if ($row && isset($row['Value'])) {
            return $row['Value'];
        }
    } catch (Exception $e) {
        // تجاهل، سنحاول استخدام مسارات افتراضية لاحقاً
    }
    return null;
}

/**
 * الحصول على tmpdir الخاص بـ MySQL (قد يكون مفيدا لتنظيف الملفات المؤقتة)
 */
function getMySQLTmpDir(PDO $conn) {
    try {
        $stmt = $conn->query("SHOW VARIABLES LIKE 'tmpdir'");
        $row = $stmt->fetch();
        if ($row && isset($row['Value'])) {
            return $row['Value'];
        }
    } catch (Exception $e) {
    }
    return null;
}

/**
 * التحقق من المساحة الحرة في المسار المحدد (يعيد false إذا المسار غير موجود)
 */
function getFreeSpaceBytes($path) {
    if (!$path) return false;
    // في بعض الأنظمة datadir قد ينتهي بشرطة مائلة، لذا نتعامل مع ذلك
    $path = rtrim($path, "/\\");
    if (!file_exists($path)) return false;
    $free = @disk_free_space($path);
    return ($free === false) ? false : $free;
}

/**
 * تنظيف ملفات مؤقتة قديمة في مجلد معين
 * يحذف الملفات فقط وليس المجلدات، وعلى شرط أن يكون قابلاً للكتابة
 */
function cleanupOldFilesInDir($dir, $ageSeconds = cleanupFileAgeSeconds) {
    $deleted = 0;
    if (!$dir || !is_dir($dir) || !is_writable($dir)) return $deleted;
    $now = time();
    $it = @new DirectoryIterator($dir);
    foreach ($it as $fileinfo) {
        if ($fileinfo->isFile()) {
            $filePath = $fileinfo->getPathname();
            $fileMTime = $fileinfo->getMTime();
            if (($now - $fileMTime) > $ageSeconds) {
                try {
                    @unlink($filePath);
                    $deleted++;
                } catch (Exception $e) {
                    // تجاهل أخطاء الحذف لملف معين وواصل
                }
            }
        }
    }
    return $deleted;
}

/**
 * محاولات تنظيف معقولة لتحرير مساحة:
 *  - تنظيف sys_get_temp_dir()
 *  - تنظيف /tmp (إن وجد)
 *  - تنظيف tmpdir الخاص بـ MySQL إذا كان معرفاً
 * إعادة عدد الملفات المحذوفة
 */
function attemptCleanupTempAreas(PDO $conn) {
    $totalDeleted = 0;
    $checkedDirs = [];

    $phpTmp = sys_get_temp_dir();
    if ($phpTmp && is_dir($phpTmp)) {
        $checkedDirs[] = $phpTmp;
        $totalDeleted += cleanupOldFilesInDir($phpTmp);
    }

    // حاول /tmp (نظامي على لينكس)
    if (is_dir('/tmp')) {
        $checkedDirs[] = '/tmp';
        $totalDeleted += cleanupOldFilesInDir('/tmp');
    }

    // حاول tmpdir من MySQL
    $mysqlTmp = getMySQLTmpDir($conn);
    if ($mysqlTmp && is_dir($mysqlTmp)) {
        $checkedDirs[] = $mysqlTmp;
        $totalDeleted += cleanupOldFilesInDir($mysqlTmp);
    }

    return ['deleted' => $totalDeleted, 'dirs' => $checkedDirs];
}

/**
 * تحقق عام من وجود مساحة كافية على datadir.
 * إذا لم يحدد datadir يحاول التحقق على جذر النظام (/) كملاذ أخير.
 */
function ensureEnoughSpace(PDO $conn, $minBytes = MIN_FREE_BYTES) {
    $datadir = getMySQLDataDir($conn);
    $pathsToCheck = [];

    if ($datadir) $pathsToCheck[] = $datadir;

    // إضافة جذر الجهاز أو مجلد العمل كخيار آخر
    $pathsToCheck[] = sys_get_temp_dir();
    if (DIRECTORY_SEPARATOR === '/') $pathsToCheck[] = '/';

    foreach ($pathsToCheck as $p) {
        $free = getFreeSpaceBytes($p);
        if ($free !== false) {
            return ['ok' => ($free >= $minBytes), 'path' => $p, 'free' => $free];
        }
    }

    // إذا لم نتمكن من تحديد المسار أو قراءة المساحة
    return ['ok' => false, 'path' => null, 'free' => null];
}

/**
 * تنفيذ تعليمة SQL مع التحقق من المساحة قبل الإنشاء والتعامل مع أخطاء Errcode: 28
 */
function execCreateWithSpaceCheck(PDO $conn, $sql, $description = '', $minBytes = MIN_FREE_BYTES) {
    // تحقق أولي
    $check = ensureEnoughSpace($conn, $minBytes);
    if (!$check['ok']) {
        echo "⚠️ لا توجد مساحة كافية في المسار: " . ($check['path'] ?? 'غير معروف') . " — المساحة الحرة: " . 
            (($check['free'] !== null) ? round($check['free']/1024/1024,2) . "MB" : 'غير متاحة') . "\n";
        echo "محاولة تنظيف الملفات المؤقتة لتحرير مساحة...\n";
        $cleanup = attemptCleanupTempAreas($conn);
        echo "تم محاولة تنظيف المجلدات: " . implode(', ', $cleanup['dirs']) . " — عدد الملفات المحذوفة التقريبي: " . $cleanup['deleted'] . "\n";
        // أعد التحقق
        $check = ensureEnoughSpace($conn, $minBytes);
        if (!$check['ok']) {
            throw new Exception("المساحة الحرة لا زالت غير كافية لإنشاء الجداول. المساحة الحرة المتبقية: " . 
                (($check['free'] !== null) ? round($check['free']/1024/1024,2) . "MB" : 'غير متاحة') . 
                ". الرجاء تفريغ المساحة على الخادم (مسح سجلات قديمة، تكبير مساحة القرص، أو تشغيل تنظيف يدوي) ثم أعد التشغيل.");
        } else {
            echo "✅ بعد التنظيف المساحة كافية على المسار: {$check['path']} — الحرة: " . round($check['free']/1024/1024,2) . "MB\n";
        }
    }

    // الآن محاولة التنفيذ مع محاولة إعادة المحاولة في حالة Errcode: 28
    $attempt = 0;
    $maxAttempts = RETRY_AFTER_CLEANUP + 1;
    while ($attempt < $maxAttempts) {
        try {
            $conn->exec($sql);
            if ($description) echo "✅ جدول/جسم: {$description} جاهز\n";
            return;
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            // تحقق من كود الخطأ 28 في نص الرسالة (Errcode: 28)
            if (stripos($msg, 'Errcode: 28') !== false || stripos($msg, 'No space left on device') !== false) {
                $attempt++;
                if ($attempt >= $maxAttempts) {
                    throw new Exception("فشل إنشاء {$description} بسبب نفاد المساحة بعد محاولات التنظيف. رسالة MySQL: {$msg}");
                }
                // حاول تنظيف مجدداً ثم إعادة المحاولة
                echo "⚠️ اكتشاف خطأ نفاد المساحة أثناء إنشاء {$description}. محاولة تنظيف وإعادة المحاولة ({$attempt}/{$maxAttempts})...\n";
                $cleanup = attemptCleanupTempAreas($conn);
                echo "تم حذف ملفات تقريبية: {$cleanup['deleted']} من مجلدات: " . implode(', ', $cleanup['dirs']) . "\n";
                // متابعة الحلقة لإعادة المحاولة
                continue;
            } else {
                // أي خطأ آخر نعيد رميه
                throw $e;
            }
        }
    }
}

try {
    $conn = getDBConnection();
    
    echo "🔄 بدء تحديث قاعدة البيانات...\n\n";

    // مثال عام: سنستخدم execCreateWithSpaceCheck لكل CREATE TABLE أو ALTER TABLE رئيسي
    // 1. جدول teacher_applications
    $sqlTeacherApplications = "
        CREATE TABLE IF NOT EXISTS teacher_applications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            teacher_name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            phone VARCHAR(20) DEFAULT NULL,
            region_id INT DEFAULT NULL,
            directorate VARCHAR(100) DEFAULT NULL,
            subject_specialization VARCHAR(200) NOT NULL,
            years_experience INT DEFAULT 0,
            qualifications TEXT DEFAULT NULL,
            cv_file VARCHAR(255) DEFAULT NULL,
            status ENUM('pending', 'under_review', 'approved', 'rejected') DEFAULT 'pending',
            teacher_user_id INT DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            reviewed_by INT DEFAULT NULL,
            reviewed_at TIMESTAMP NULL DEFAULT NULL,
            INDEX idx_email (email),
            INDEX idx_status (status),
            INDEX idx_region (region_id),
            FOREIGN KEY (teacher_user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    execCreateWithSpaceCheck($conn, $sqlTeacherApplications, 'teacher_applications');

    // 2. جدول region_deputies
    $sqlRegionDeputies = "
        CREATE TABLE IF NOT EXISTS region_deputies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            region_id INT DEFAULT NULL,
            directorate VARCHAR(100) NOT NULL,
            assigned_by INT NOT NULL,
            permissions JSON DEFAULT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            deactivated_at TIMESTAMP NULL DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            INDEX idx_user (user_id),
            INDEX idx_region (region_id),
            INDEX idx_active (is_active),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    execCreateWithSpaceCheck($conn, $sqlRegionDeputies, 'region_deputies');

    // 3. جدول region_managers
    $sqlRegionManagers = "
        CREATE TABLE IF NOT EXISTS region_managers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            region_id INT DEFAULT NULL,
            assigned_by INT NOT NULL,
            permissions JSON DEFAULT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            deactivated_at TIMESTAMP NULL DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            INDEX idx_user (user_id),
            INDEX idx_region (region_id),
            INDEX idx_active (is_active),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    execCreateWithSpaceCheck($conn, $sqlRegionManagers, 'region_managers');

    // 4. جدول regions
    $sqlRegions = "
        CREATE TABLE IF NOT EXISTS regions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            code VARCHAR(20) UNIQUE NOT NULL,
            description TEXT DEFAULT NULL,
            manager_id INT DEFAULT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_code (code),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    execCreateWithSpaceCheck($conn, $sqlRegions, 'regions');

    // 5. إدراج المناطق الافتراضية إذا لزم الأمر
    $stmt = $conn->prepare("SELECT COUNT(*) FROM regions");
    $stmt->execute();
    $count = $stmt->fetchColumn();

    if ($count == 0) {
        $regions = [
            ['شمال غزة', 'NORTH_GAZA', 'منطقة شمال غزة التعليمية'],
            ['غزة', 'GAZA', 'منطقة غزة التعليمية'],
            ['الوسطى', 'MIDDLE', 'منطقة الوسطى التعليمية'],
            ['الجنوب', 'SOUTH', 'منطقة الجنوب التعليمية (خان يونس ورفح)']
        ];

        foreach ($regions as $region) {
            $ins = $conn->prepare("INSERT INTO regions (name, code, description, is_active) VALUES (?, ?, ?, 1)");
            $ins->execute($region);
        }
        echo "✅ تم إدراج المناطق الافتراضية\n";
    }

    // 6. جدول admin_activity_log
    $sqlAdminActivity = "
        CREATE TABLE IF NOT EXISTS admin_activity_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT NOT NULL,
            action_type VARCHAR(50) NOT NULL,
            action_description TEXT NOT NULL,
            target_user_id INT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_admin (admin_id),
            INDEX idx_action (action_type),
            INDEX idx_created (created_at),
            FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    execCreateWithSpaceCheck($conn, $sqlAdminActivity, 'admin_activity_log');

    echo "\n✅ تم تحديث قاعدة البيانات بنجاح!\n";
    
} catch (Exception $e) {
    // رسالة مفيدة عند حدوث خطأ، مع توجيهات لصيانة المساحة إن كان السبب نفاد المساحة
    $msg = $e->getMessage();
    echo "❌ خطأ: " . $msg . "\n\n";
    if (stripos($msg, 'نفاد') !== false || stripos($msg, 'No space left on device') !== false || stripos($msg, 'Errcode: 28') !== false) {
        echo "اقتراحات لحل مشكلة المساحة:\n";
        echo " - افحص المساحة الحرة على الخادم: df -h أو استخدام الأدوات المتاحة في بيئتك.\n";
        echo " - احذف سجلات أو ملفات قديمة غير مهمة (سجلات النظام، ملفات مؤقتة، نسخ احتياطية قديمة).\n";
        echo " - إن أمكن، زد حجم قسم القرص أو أعد توجيه datadir الخاص بـ MySQL إلى قسم أوسع.\n";
        echo " - أعد تشغيل خدمة MySQL بعد تفريغ المساحة إن لزم.\n";
    }
    exit(1);
}
?>