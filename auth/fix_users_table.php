<?php
/**
 * إصلاح جدول المستخدمين - إضافة الأعمدة المفقودة
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = 'localhost';
$db = 'himma_tawjihi';
$user = 'root';
$pass = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "<h3>إصلاح جدول المستخدمين</h3>";
    
    // التحقق من الأعمدة الموجودة
    $stmt = $conn->query("DESCRIBE users");
    $existing_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<p>الأعمدة الموجودة حالياً: " . implode(', ', $existing_columns) . "</p>";
    
    // قائمة الأعمدة المطلوبة
    $required_columns = [
        'email_verified' => "ALTER TABLE users ADD COLUMN email_verified BOOLEAN DEFAULT TRUE",
        'avatar' => "ALTER TABLE users ADD COLUMN avatar VARCHAR(500) DEFAULT NULL",
        'last_seen' => "ALTER TABLE users ADD COLUMN last_seen TIMESTAMP NULL DEFAULT NULL",
        'last_login' => "ALTER TABLE users ADD COLUMN last_login TIMESTAMP NULL DEFAULT NULL",
        'is_online' => "ALTER TABLE users ADD COLUMN is_online BOOLEAN DEFAULT FALSE",
        'status' => "ALTER TABLE users ADD COLUMN status ENUM('active', 'inactive', 'suspended') DEFAULT 'active'"
    ];
    
    $added = [];
    $already_exists = [];
    
    foreach ($required_columns as $column => $sql) {
        if (!in_array($column, $existing_columns)) {
            try {
                $conn->exec($sql);
                $added[] = $column;
                echo "<p style='color: green;'>✅ تم إضافة العمود: $column</p>";
            } catch (Exception $e) {
                echo "<p style='color: red;'>❌ خطأ في إضافة $column: " . $e->getMessage() . "</p>";
            }
        } else {
            $already_exists[] = $column;
            echo "<p style='color: blue;'>ℹ️ العمود موجود بالفعل: $column</p>";
        }
    }
    
    echo "<hr>";
    echo "<h4>ملخص:</h4>";
    echo "<p>تم إضافة " . count($added) . " عمود جديد</p>";
    echo "<p>كان موجوداً بالفعل: " . count($already_exists) . " عمود</p>";
    
    // التحقق النهائي
    $stmt = $conn->query("DESCRIBE users");
    $final_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h4>الأعمدة النهائية في جدول users:</h4>";
    echo "<ul>";
    foreach ($final_columns as $col) {
        echo "<li>$col</li>";
    }
    echo "</ul>";
    
    echo "<hr>";
    echo "<h3 style='color: green;'>✅ تم الإصلاح بنجاح!</h3>";
    echo "<p><a href='debug_teacher_registration.php'>جرب التسجيل مرة أخرى</a></p>";
    echo "<p><a href='register_teacher_fixed.php'>الذهاب لصفحة التسجيل</a></p>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ خطأ: " . $e->getMessage() . "</h3>";
}
?>