<?php
// إعدادات قاعدة البيانات
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'himma_tawjihi');
define('DB_CHARSET', 'utf8mb4');

// إعدادات الموقع
define('SITE_NAME', 'منصة همة التوجيهي');
define('SITE_URL', 'http://localhost/himma_tawjihi');
define('ADMIN_EMAIL', 'admin@himma.edu');

// إعدادات الأمان
define('SESSION_TIMEOUT', 3600); // ساعة واحدة
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 دقيقة

// إعدادات الملفات
define('UPLOAD_DIR', 'uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png']);

// بدء الجلسة
session_start();

// تعيين المنطقة الزمنية
date_default_timezone_set('Asia/Gaza');
?>