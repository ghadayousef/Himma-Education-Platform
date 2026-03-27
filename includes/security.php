<?php
/**
 * نظام الأمان  - منصة همّة التعليمية
 *  Security System - Himma Educational Platform
 */

class SecurityManager {
    
    private static $instance = null;
    private $config;
    
    private function __construct() {
        $this->config = [
            'max_login_attempts' => 5,
            'lockout_duration' => 900, // 15 minutes
            'session_timeout' => 3600, // 1 hour
            'csrf_token_lifetime' => 1800, // 30 minutes
            'password_min_length' => 8,
            'allowed_file_types' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'ppt', 'pptx'],
            'max_file_size' => 10485760, // 10MB
        ];
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * تنظيف وتعقيم البيانات المدخلة
     * Clean and sanitize input data
     */
    public function sanitizeInput($data, $type = 'string') {
        if (is_array($data)) {
            return array_map(function($item) use ($type) {
                return $this->sanitizeInput($item, $type);
            }, $data);
        }
        
        // إزالة المسافات الزائدة
        $data = trim($data);
        
        switch ($type) {
            case 'email':
                return filter_var($data, FILTER_SANITIZE_EMAIL);
                
            case 'int':
                return filter_var($data, FILTER_SANITIZE_NUMBER_INT);
                
            case 'float':
                return filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                
            case 'url':
                return filter_var($data, FILTER_SANITIZE_URL);
                
            case 'html':
                return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
                
            case 'sql':
                // إزالة الأحرف الخطيرة لقواعد البيانات
                return preg_replace('/[\'";\\\\]/', '', $data);
                
            case 'filename':
                // تنظيف أسماء الملفات
                return preg_replace('/[^a-zA-Z0-9._-]/', '', $data);
                
            default: // string
                return htmlspecialchars(strip_tags($data), ENT_QUOTES, 'UTF-8');
        }
    }
    
    /**
     * التحقق من صحة البيانات المدخلة
     * Validate input data
     */
    public function validateInput($data, $rules) {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            
            // التحقق من الحقول المطلوبة
            if (isset($rule['required']) && $rule['required'] && empty($value)) {
                $errors[$field] = "الحقل {$field} مطلوب";
                continue;
            }
            
            if (!empty($value)) {
                // التحقق من نوع البيانات
                if (isset($rule['type'])) {
                    switch ($rule['type']) {
                        case 'email':
                            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                                $errors[$field] = "البريد الإلكتروني غير صحيح";
                            }
                            break;
                            
                        case 'int':
                            if (!filter_var($value, FILTER_VALIDATE_INT)) {
                                $errors[$field] = "يجب أن يكون رقماً صحيحاً";
                            }
                            break;
                            
                        case 'url':
                            if (!filter_var($value, FILTER_VALIDATE_URL)) {
                                $errors[$field] = "الرابط غير صحيح";
                            }
                            break;
                    }
                }
                
                // التحقق من الطول الأدنى
                if (isset($rule['min_length']) && strlen($value) < $rule['min_length']) {
                    $errors[$field] = "يجب أن يكون الطول أكبر من {$rule['min_length']} أحرف";
                }
                
                // التحقق من الطول الأقصى
                if (isset($rule['max_length']) && strlen($value) > $rule['max_length']) {
                    $errors[$field] = "يجب أن يكون الطول أقل من {$rule['max_length']} أحرف";
                }
                
                // التحقق من النمط (Regex)
                if (isset($rule['pattern']) && !preg_match($rule['pattern'], $value)) {
                    $errors[$field] = $rule['pattern_message'] ?? "التنسيق غير صحيح";
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * حماية من هجمات SQL Injection
     * SQL Injection protection
     */
    public function prepareSQLStatement($pdo, $sql, $params = []) {
        try {
            $stmt = $pdo->prepare($sql);
            
            // ربط المعاملات بشكل آمن
            foreach ($params as $key => $value) {
                $type = PDO::PARAM_STR;
                
                if (is_int($value)) {
                    $type = PDO::PARAM_INT;
                } elseif (is_bool($value)) {
                    $type = PDO::PARAM_BOOL;
                } elseif (is_null($value)) {
                    $type = PDO::PARAM_NULL;
                }
                
                if (is_numeric($key)) {
                    $stmt->bindValue($key + 1, $value, $type);
                } else {
                    $stmt->bindValue(':' . $key, $value, $type);
                }
            }
            
            return $stmt;
        } catch (PDOException $e) {
            error_log("SQL Preparation Error: " . $e->getMessage());
            throw new Exception("خطأ في قاعدة البيانات");
        }
    }
    
    /**
     * إنشاء وإدارة CSRF Token
     * CSRF Token generation and management
     */
    public function generateCSRFToken() {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();
        
        return $token;
    }
    
    public function validateCSRFToken($token) {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
            return false;
        }
        
        // التحقق من انتهاء صلاحية التوكن
        if (time() - $_SESSION['csrf_token_time'] > $this->config['csrf_token_lifetime']) {
            unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * حماية كلمات المرور
     * Password protection
     */
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 iterations
            'threads' => 3,         // 3 threads
        ]);
    }
    
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    public function validatePasswordStrength($password) {
        $errors = [];
        
        if (strlen($password) < $this->config['password_min_length']) {
            $errors[] = "كلمة المرور يجب أن تكون {$this->config['password_min_length']} أحرف على الأقل";
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "كلمة المرور يجب أن تحتوي على حرف كبير واحد على الأقل";
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "كلمة المرور يجب أن تحتوي على حرف صغير واحد على الأقل";
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "كلمة المرور يجب أن تحتوي على رقم واحد على الأقل";
        }
        
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = "كلمة المرور يجب أن تحتوي على رمز خاص واحد على الأقل";
        }
        
        return $errors;
    }
    
    /**
     * حماية من هجمات Brute Force
     * Brute Force protection
     */
    public function recordLoginAttempt($identifier, $success = false) {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        $key = 'login_attempts_' . md5($identifier);
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'count' => 0,
                'last_attempt' => time(),
                'locked_until' => 0
            ];
        }
        
        if ($success) {
            // إعادة تعيين المحاولات عند النجاح
            unset($_SESSION[$key]);
        } else {
            $_SESSION[$key]['count']++;
            $_SESSION[$key]['last_attempt'] = time();
            
            if ($_SESSION[$key]['count'] >= $this->config['max_login_attempts']) {
                $_SESSION[$key]['locked_until'] = time() + $this->config['lockout_duration'];
            }
        }
    }
    
    public function isAccountLocked($identifier) {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        $key = 'login_attempts_' . md5($identifier);
        
        if (!isset($_SESSION[$key])) {
            return false;
        }
        
        $attempts = $_SESSION[$key];
        
        if ($attempts['locked_until'] > time()) {
            return true;
        }
        
        // إزالة القفل المنتهي الصلاحية
        if ($attempts['locked_until'] > 0 && $attempts['locked_until'] <= time()) {
            unset($_SESSION[$key]);
        }
        
        return false;
    }
    
    public function getRemainingLockoutTime($identifier) {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        $key = 'login_attempts_' . md5($identifier);
        
        if (!isset($_SESSION[$key])) {
            return 0;
        }
        
        $remaining = $_SESSION[$key]['locked_until'] - time();
        return max(0, $remaining);
    }
    
    /**
     * حماية رفع الملفات
     * File upload protection
     */
    public function validateFileUpload($file) {
        $errors = [];
        
        // التحقق من وجود الملف
        if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
            $errors[] = "لم يتم رفع أي ملف";
            return $errors;
        }
        
        // التحقق من حجم الملف
        if ($file['size'] > $this->config['max_file_size']) {
            $errors[] = "حجم الملف كبير جداً (الحد الأقصى: " . ($this->config['max_file_size'] / 1024 / 1024) . " MB)";
        }
        
        // التحقق من نوع الملف
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->config['allowed_file_types'])) {
            $errors[] = "نوع الملف غير مسموح";
        }
        
        // التحقق من MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimes = [
            'image/jpeg', 'image/png', 'image/gif',
            'application/pdf',
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'
        ];
        
        if (!in_array($mimeType, $allowedMimes)) {
            $errors[] = "نوع الملف غير صحيح";
        }
        
        return $errors;
    }
    
    public function generateSecureFileName($originalName) {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        
        // تنظيف اسم الملف
        $baseName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $baseName);
        $baseName = substr($baseName, 0, 50); // تحديد الطول
        
        // إضافة timestamp و random string
        $timestamp = time();
        $randomString = bin2hex(random_bytes(8));
        
        return $baseName . '_' . $timestamp . '_' . $randomString . '.' . $extension;
    }
    
    /**
     * حماية الجلسات
     * Session protection
     */
    public function secureSession() {
        if (!isset($_SESSION)) {
            // إعدادات الجلسة الآمنة
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_samesite', 'Strict');
            
            session_start();
        }
        
        // التحقق من انتهاء صلاحية الجلسة
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity']) > $this->config['session_timeout']) {
            session_unset();
            session_destroy();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        
        // تجديد معرف الجلسة دورياً
        if (!isset($_SESSION['created_at'])) {
            $_SESSION['created_at'] = time();
        } elseif (time() - $_SESSION['created_at'] > 1800) { // 30 minutes
            session_regenerate_id(true);
            $_SESSION['created_at'] = time();
        }
        
        return true;
    }
    
    /**
     * تسجيل الأحداث الأمنية
     * Security event logging
     */
    public function logSecurityEvent($event, $details = []) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'user_id' => $_SESSION['user_id'] ?? null,
            'details' => $details
        ];
        
        $logFile = __DIR__ . '/../logs/security_' . date('Y-m-d') . '.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($logFile, json_encode($logEntry, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
    }
    
    /**
     * حماية من XSS
     * XSS protection
     */
    public function preventXSS($data) {
        if (is_array($data)) {
            return array_map([$this, 'preventXSS'], $data);
        }
        
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * التحقق من معدل الطلبات (Rate Limiting)
     * Rate limiting
     */
    public function checkRateLimit($identifier, $maxRequests = 60, $timeWindow = 3600) {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        $key = 'rate_limit_' . md5($identifier);
        $now = time();
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [
                'requests' => [],
                'blocked_until' => 0
            ];
        }
        
        $rateData = &$_SESSION[$key];
        
        // التحقق من الحظر
        if ($rateData['blocked_until'] > $now) {
            return false;
        }
        
        // إزالة الطلبات القديمة
        $rateData['requests'] = array_filter($rateData['requests'], function($timestamp) use ($now, $timeWindow) {
            return ($now - $timestamp) < $timeWindow;
        });
        
        // التحقق من تجاوز الحد
        if (count($rateData['requests']) >= $maxRequests) {
            $rateData['blocked_until'] = $now + 300; // حظر لمدة 5 دقائق
            return false;
        }
        
        // إضافة الطلب الحالي
        $rateData['requests'][] = $now;
        
        return true;
    }
}

/**
 * فئة مساعدة للتحقق من الصلاحيات
 * Permission helper class
 */
class PermissionManager {
    
    public static function checkPermission($requiredRole, $userRole = null) {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        $userRole = $userRole ?? ($_SESSION['role'] ?? null);
        
        if (!$userRole) {
            return false;
        }
        
        $roleHierarchy = [
            'student' => 1,
            'teacher' => 2,
            'admin' => 3
        ];
        
        $userLevel = $roleHierarchy[$userRole] ?? 0;
        $requiredLevel = $roleHierarchy[$requiredRole] ?? 0;
        
        return $userLevel >= $requiredLevel;
    }
    
    public static function requirePermission($requiredRole, $redirectUrl = '../home/index.php') {
        if (!self::checkPermission($requiredRole)) {
            header("Location: $redirectUrl");
            exit();
        }
    }
    
    public static function requireLogin($redirectUrl = '../home/index.php') {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        if (!isset($_SESSION['user_id'])) {
            header("Location: $redirectUrl");
            exit();
        }
    }
}