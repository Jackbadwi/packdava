<?php
// بارگذاری تنظیمات از فایل جداگانه
$configFile = '/home/healthap/secure_config/config.php';

if (!file_exists($configFile)) {
    http_response_code(500);
    error_log("Configuration file not found: " . $configFile);
    die('فایل پیکربندی یافت نشد.');
}

$config = require_once $configFile;

// بررسی ساختار فایل پیکربندی
if (!isset($config['db']) || !is_array($config['db'])) {
    http_response_code(500);
    error_log("Invalid configuration structure");
    die('ساختار فایل پیکربندی نامعتبر است.');
}

// ------------------------------------------------------------
// مدیریت CORS و بررسی منشأ درخواست (رفع مشکل کروم)
// ------------------------------------------------------------
$allowedOrigins = $config['security']['allowed_origins'] ?? [];
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
$serverHost = $_SERVER['HTTP_HOST'] ?? '';

// حذف پورت از host سرور (مثلاً localhost:8080 -> localhost)
$serverHost = explode(':', $serverHost)[0];

$originAllowed = false;

if (!empty($requestOrigin)) {
    $originHost = parse_url($requestOrigin, PHP_URL_HOST);
    // حذف پورت از origin host
    $originHost = explode(':', $originHost)[0];
    
    // اگر منشأ با host سرور یکی باشد، مجاز است (درخواست هم‌خاستگاه)
    if ($originHost === $serverHost) {
        $originAllowed = true;
    } else {
        // در غیر این صورت، بررسی لیست مجاز
        foreach ($allowedOrigins as $allowedOrigin) {
            if ($allowedOrigin === '*') {
                $originAllowed = true;
                break;
            }
            
            // حذف پورت از allowedOrigin اگر وجود داشته باشد
            $allowedHost = explode(':', $allowedOrigin)[0];
            
            if (strpos($allowedHost, '*') !== false) {
                // تبدیل الگوی wildcard به regex
                $pattern = '/^' . str_replace('\*', '.*', preg_quote($allowedHost, '/')) . '$/';
                if (preg_match($pattern, $originHost)) {
                    $originAllowed = true;
                    break;
                }
            } elseif ($allowedHost === $originHost) {
                $originAllowed = true;
                break;
            }
        }
    }
} else {
    // اگر هدر Origin وجود نداشت (درخواست هم‌خاستگاه بدون Origin)، اجازه بده
    $originAllowed = true;
}

if ($originAllowed) {
    if (!empty($requestOrigin)) {
        header("Access-Control-Allow-Origin: " . $requestOrigin);
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");
        header("Access-Control-Allow-Credentials: true");
    }
} else {
    http_response_code(403);
    exit('منشأ درخواست مجاز نیست.');
}

// اضافه کردن هدرهای امنیتی اضافی
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

// برای درخواست‌های OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ------------------------------------------------------------
// اتصال به پایگاه داده
// ------------------------------------------------------------
$DB_CONFIG = $config['db'];

// بررسی وجود مقادیر ضروری
if (empty($DB_CONFIG['host']) || empty($DB_CONFIG['user']) || empty($DB_CONFIG['name'])) {
    http_response_code(500);
    error_log("Database configuration error: Missing required parameters");
    die('پیکربندی پایگاه داده ناقص است.');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(
        $DB_CONFIG['host'],
        $DB_CONFIG['user'],
        $DB_CONFIG['pass'],
        $DB_CONFIG['name'],
        $DB_CONFIG['port']
    );
    
    // تنظیم charset برای اتصال
    $conn->set_charset($DB_CONFIG['charset']);
    
    // فعال کردن حالت امن برای مدیریت خطاها
    $conn->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, true);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Database connection error: " . $e->getMessage());
    // عدم نمایش جزئیات خطا به کاربر
    die('خطا در اتصال به پایگاه داده.');
}

// ------------------------------------------------------------
// توابع کمکی
// ------------------------------------------------------------
function fa_clean($s) {
    $s = trim($s ?? '');
    $map = [
        '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
        'ي'=>'ی','ك'=>'ک','ـ'=>'','‏'=>'','‍'=>''
    ];
    return strtr($s, $map);
}

function require_post() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('درخواست نامعتبر است.');
    }
}

function check_origin_if_present() {
    global $config;
    $allowedOrigins = $config['security']['allowed_origins'] ?? [];
    $serverHost = $_SERVER['HTTP_HOST'] ?? '';
    $serverHost = explode(':', $serverHost)[0];
    
    if (!empty($_SERVER['HTTP_ORIGIN'])) {
        $origin = parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST);
        $origin = explode(':', $origin)[0];
        
        // اگر با host سرور یکی باشد، مجاز است
        if ($origin === $serverHost) {
            return;
        }
        
        $originAllowed = false;
        foreach ($allowedOrigins as $allowed) {
            $allowedHost = explode(':', $allowed)[0];
            if (strpos($allowedHost, '*') !== false) {
                $pattern = '/^' . str_replace('\*', '.*', preg_quote($allowedHost, '/')) . '$/';
                if (preg_match($pattern, $origin)) {
                    $originAllowed = true;
                    break;
                }
            } elseif ($allowedHost === $origin) {
                $originAllowed = true;
                break;
            }
        }
        
        if (!$originAllowed) {
            http_response_code(400);
            exit('منشأ درخواست معتبر نیست.');
        }
    }
}

function strong_password($pwd) {
    if (strlen($pwd) < 8) return false;
    if (!preg_match('/[A-Za-zآ-ی]/u', $pwd)) return false;
    if (!preg_match('/\d/', $pwd)) return false;
    if (!preg_match('/[^A-Za-zآ-ی0-9]/', $pwd)) return false;
    return true;
}

function redirect($url) {
    if (filter_var($url, FILTER_VALIDATE_URL)) {
        header('Location: ' . $url);
        exit;
    } else {
        error_log("Redirect attempt to invalid URL: " . $url);
        die('آدرس نامعتبر است.');
    }
}

function now_utc() {
    return (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
}

function log_debug($message) {
    global $config;
    if (($config['security']['debug_mode'] ?? false) === true) {
        error_log("DEBUG: " . $message);
    }
}

function escape_output($data) {
    return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
?>