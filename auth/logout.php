<?php
/**
 * صفحة تسجيل الخروج - منصة همّة التوجيهي
 */
session_start();
// إزالة جميع متغيرات الجلسة
$_SESSION = array();

// إذا تم استخدام الكوكيز للجلسة، قم بحذف الكوكيز أيضاً
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// تدمير الجلسة
session_destroy();

header("Location: login.php");
exit();
?>