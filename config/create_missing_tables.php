<?php
/**
 * إنشاء الجداول المفقودة لطلبات المعلمين
 */

require_once 'database.php';

try {
    $conn = getDBConnection();
    
    // إنشاء جدول طلبات المعلمين
    $conn->exec("
        CREATE TABLE IF NOT EXISTS teacher_applications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            teacher_name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            phone VARCHAR(20) DEFAULT NULL,
            branch_id INT NOT NULL,
            subject_specialization VARCHAR(200) NOT NULL,
            years_experience INT DEFAULT 0,
            qualifications TEXT DEFAULT NULL,
            status ENUM('pending', 'approved', 'rejected', 'under_review') DEFAULT 'pending',
            teacher_user_id INT DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            reviewed_by INT DEFAULT NULL,
            reviewed_at TIMESTAMP NULL DEFAULT NULL,
            INDEX idx_email (email),
            INDEX idx_status (status),
            INDEX idx_branch (branch_id),
            FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
            FOREIGN KEY (teacher_user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "✅ تم إنشاء جدول teacher_applications بنجاح\n";
    
    // التأكد من وجود بيانات في جدول branches
    $stmt = $conn->prepare("SELECT COUNT(*) FROM branches");
    $stmt->execute();
    $branch_count = $stmt->fetchColumn();
    
    if ($branch_count == 0) {
        // إدراج فروع تجريبية
        $branches = [
            ['الفرع الرئيسي - غزة', 'الفرع الرئيسي في مدينة غزة', 'غزة - شارع الجامعة', '08-2123456', 'gaza@himma.edu'],
            ['فرع الشمال', 'فرع شمال غزة وجباليا', 'جباليا - شارع فلسطين', '08-2123457', 'north@himma.edu'],
            ['فرع الوسطى', 'فرع المحافظة الوسطى', 'دير البلح - شارع الشهداء', '08-2123458', 'middle@himma.edu'],
            ['فرع خان يونس', 'فرع محافظة خان يونس', 'خان يونس - شارع جمال عبد الناصر', '08-2123459', 'khanyounis@himma.edu'],
            ['فرع رفح', 'فرع محافظة رفح', 'رفح - شارع الشهداء', '08-2123460', 'rafah@himma.edu']
        ];
        
        foreach ($branches as $branch) {
            $stmt = $conn->prepare("
                INSERT INTO branches (name, description, address, phone, email, is_active) 
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute($branch);
        }
        
        echo "✅ تم إدراج الفروع التجريبية بنجاح\n";
    }
    
    echo "✅ تم إنشاء جميع الجداول المطلوبة بنجاح\n";
    
} catch (Exception $e) {
    echo "❌ خطأ: " . $e->getMessage() . "\n";
}

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
?>