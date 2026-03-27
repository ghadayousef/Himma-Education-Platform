<?php
/**
 * ملف إعداد المناطق - لإنشاء جدول المناطق وإدراج البيانات الافتراضية
 */

// الاتصال بقاعدة البيانات
$host = 'localhost';
$db = 'himma_tawjihi';
$user = 'root';
$pass = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>جاري إعداد جدول المناطق...</h2>";
    
    // إنشاء جدول المناطق
    $conn->exec("
        CREATE TABLE IF NOT EXISTS app_d2335_regions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            code VARCHAR(20) UNIQUE NOT NULL,
            description TEXT,
            manager_id INT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_code (code),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "<p>✅ تم إنشاء جدول app_d2335_regions بنجاح</p>";
    
    // التحقق من وجود بيانات
    $stmt = $conn->query("SELECT COUNT(*) FROM app_d2335_regions");
    $count = $stmt->fetchColumn();
    
    echo "<p>عدد المناطق الحالية: $count</p>";
    
    if ($count == 0) {
        echo "<p>جاري إدراج المناطق الافتراضية...</p>";
        
        $regions = [
            ['شمال غزة', 'NORTH_GAZA', 'منطقة شمال غزة التعليمية'],
            ['غزة', 'GAZA', 'منطقة غزة التعليمية'],
            ['الوسطى', 'MIDDLE', 'منطقة الوسطى التعليمية'],
            ['الجنوب', 'SOUTH', 'منطقة الجنوب التعليمية (خان يونس ورفح)']
        ];
        
        $stmt = $conn->prepare("
            INSERT INTO app_d2335_regions (name, code, description, is_active) 
            VALUES (?, ?, ?, 1)
        ");
        
        foreach ($regions as $region) {
            $stmt->execute($region);
            echo "<p>✅ تم إدراج المنطقة: {$region[0]}</p>";
        }
    } else {
        echo "<p>ℹ️ المناطق موجودة بالفعل</p>";
    }
    
    // عرض جميع المناطق
    echo "<h3>المناطق المتاحة:</h3>";
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>الاسم</th><th>الكود</th><th>الوصف</th><th>نشط</th></tr>";
    
    $stmt = $conn->query("SELECT * FROM app_d2335_regions ORDER BY id");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['name']}</td>";
        echo "<td>{$row['code']}</td>";
        echo "<td>{$row['description']}</td>";
        echo "<td>" . ($row['is_active'] ? 'نعم' : 'لا') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<hr>";
    echo "<h3>✅ تم الإعداد بنجاح!</h3>";
    echo "<p><a href='teacher_application_form.php'>اذهب إلى نموذج طلب الالتحاق</a></p>";
    
} catch (PDOException $e) {
    echo "<h3 style='color: red;'>❌ خطأ في الاتصال بقاعدة البيانات:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<hr>";
    echo "<h4>تأكد من:</h4>";
    echo "<ul>";
    echo "<li>تشغيل خادم MySQL</li>";
    echo "<li>وجود قاعدة البيانات himma_tawjihi</li>";
    echo "<li>صحة بيانات الاتصال (المستخدم وكلمة المرور)</li>";
    echo "</ul>";
}
?>