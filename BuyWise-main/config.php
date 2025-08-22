<?php
// -------------------- SESSION HANDLING --------------------
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 3600);
    session_set_cookie_params(3600);
    session_start();
}

// Protect session from hijacking
// هون يتخزن الآيبي آدريس واليوزر اجينت(متل نوع الجهاز-نظام التشغيل-اسم المتصفح واصداره)لما المستخدم يسجل دخوله في الجلسه 
if (!isset($_SESSION['IPaddress'])) {
    $_SESSION['IPaddress'] = $_SERVER['REMOTE_ADDR'];
}
if (!isset($_SESSION['userAgent'])) {
    $_SESSION['userAgent'] = $_SERVER['HTTP_USER_AGENT'];
}

// بعد هيك، كل مرة بتنفتح الصفحة، بيعمل مقارنة واذا لاقى اختلاف بعمل دستروي للصفحه و برجعه لصفحة اللوجين ليرجع يسجل دخوله
if ($_SESSION['IPaddress'] !== $_SERVER['REMOTE_ADDR'] || $_SESSION['userAgent'] !== $_SERVER['HTTP_USER_AGENT']) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

// Auto-logout after 1 hour
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 3600)) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();

// -------------------- DEVELOPMENT MODE --------------------
// يعرض كل الأخطاء إذا كنت بمود التطوير
//  اذا فولس يخفي جميع الأخطاء

$isDev = true;
if ($isDev) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

// -------------------- ENVIRONMENT VARIABLES --------------------
require_once 'load_env.php'; // defines env()

// -------------------- PHPMailer CONFIG --------------------
$phpMailerPath = __DIR__ . '/PHPMailer/src/';
require_once $phpMailerPath . 'PHPMailer.php';
require_once $phpMailerPath . 'SMTP.php';
require_once $phpMailerPath . 'Exception.php';

use PHPMailer\PHPMailer\PHPMailer;

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = env('SMTP_HOST', 'smtp.gmail.com');
    $mail->SMTPAuth   = true;
    $mail->Username   = env('SMTP_USERNAME');
    $mail->Password   = env('SMTP_PASSWORD');
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = env('SMTP_PORT', 465);
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom(env('SMTP_FROM_EMAIL'), env('SMTP_FROM_NAME', 'BuyWise'));
} catch (Exception $e) {
    error_log("PHPMailer setup failed: " . $e->getMessage());
}

// -------------------- DATABASE CONNECTION --------------------
$host = "localhost";
$dbname = "BuyWise";
$user = "root";
$pass = "";

try {
    $con = new mysqli($host, $user, $pass, $dbname);
    $con->set_charset("utf8mb4");
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("Something went wrong. Please try again later.");
}

// -------------------- LANGUAGE SWITCH --------------------
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'ar'])) {
    $_SESSION['lang'] = $_GET['lang'];
    $url = strtok($_SERVER["REQUEST_URI"], '?');
    $query = $_GET;
    unset($query['lang']);
    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }
    header("Location: $url");
    exit;
}

$lang = $_SESSION['lang'] ?? 'en';
$dir = $lang === 'ar' ? 'rtl' : 'ltr';
$lang_code = $lang;

require_once 'lang.php';

// -------------------- POPUP SUPPORT --------------------
$popupMessage = $_SESSION['popup'] ?? '';
$popup = $popupMessage; // to avoid undefined variable
$showPopup = isset($_SESSION['popup']);
unset($_SESSION['popup']);

// -------------------- CSRF TOKEN --------------------

// يولد رمز حماية عند إرسال الفورمز 
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// تتحقق من صحة الرمز عند الاستلام
function isValidCSRF($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// -------------------- RATE LIMITING --------------------

//  يراقب عدد الطلبات القادمة من نفس الآي بي آدريس خلال دقيقه
// إذا تجاوزت 100 طلب، يعطيك خطأ 429: "Too many requests".
$rateKey = 'rate_' . $_SERVER['REMOTE_ADDR'];
$_SESSION[$rateKey] = $_SESSION[$rateKey] ?? ['count' => 0, 'start' => time()];
$rate = &$_SESSION[$rateKey];

if (time() - $rate['start'] > 60) {
    $rate = ['count' => 0, 'start' => time()];
}
$rate['count']++;

if ($rate['count'] > 100) {
    http_response_code(429);
    die("Too many requests. Please wait a moment.");
}

// -------------------- IP LOGGING --------------------
//    يسجل آي بي آدريس المستخدم والصفحة التي زارها في ملف نصي 
$ip = $_SERVER['REMOTE_ADDR']; //يأخذ اي بي اليوزر
$page = $_SERVER['REQUEST_URI']; //يجيب رابط الصفحه اللي زارها اليوزر
$timestamp = date("Y-m-d H:i:s");
$logDir = __DIR__ . '/logs'; // ينشئه لو مش موجود
if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true); //0777 = صلاحيات كاملة للمجلد (مسموح الكتابة داخله)
}
$logFile = $logDir . '/access.log';
$logLine = "[$timestamp] IP: $ip accessed $page\n";
@file_put_contents($logFile, $logLine, FILE_APPEND);

?>
