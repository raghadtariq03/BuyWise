<?php
@session_start();
require_once "config.php";

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    echo 'csrf_error';
    exit;
}

// Sanitize input
//real_escape_string(...) ->  SQL هاي تحميه من 
$UserName     = trim($con->real_escape_string($_POST['UserName']));
$UserEmail    = trim($con->real_escape_string($_POST['UserEmail']));
$UserPassword = trim($_POST['UserPassword']); // Will be hashed later مشان هيك ما استخدمله حمايه نفس الباقي
$UserGender   = trim($con->real_escape_string($_POST['UserGender']));
$UserPhone    = trim($con->real_escape_string($_POST['UserPhone']));

// Check if email already registered
$stmt = $con->prepare("SELECT UserID FROM users WHERE UserEmail = ?");
$stmt->bind_param("s", $UserEmail);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    echo "0"; // Already exists
    exit;
}

// Hash password
//( PASSWORD_DEFAULTعند استخدام bcryptتلقائيًا ) دالة بي اتش بي تقوم بـ تشفير كلمة المرور باستخدام خوارزمية آمنة 
$hashedPassword = password_hash($UserPassword, PASSWORD_DEFAULT);

// Generate 6-digit code
$code = rand(100000, 999999);

// Temporarily store in session
$_SESSION['pending_user'] = [
    'name'     => $UserName,
    'email'    => $UserEmail,
    'password' => $hashedPassword,
    'gender'   => $UserGender,
    'phone'    => $UserPhone,
    'code'     => $code
];


// Load PHPMailer

//لاستيراد اسماء الكلاسات من بي اتش بي ميلرuse
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

//تحميل الملفات الخاصه بمكتبة بي اتش بي ميلر
    require_once 'PHPMailer/src/PHPMailer.php'; //الكلاس الرئيسي لإرسال البريد
    require_once 'PHPMailer/src/SMTP.php'; //SMTPالتعامل مع الاتصال عبر
    require_once 'PHPMailer/src/Exception.php'; //التعامل مع الأخطاء

//انشاء اوبجكت جديد من بي اتش بي ميلر
$mail = new PHPMailer(true); //ترو يعني فعل وضع التحقق من الاخطاء

try {
    // SMTP configuration
    $mail->isSMTP();
    $mail->Host       = env('SMTP_HOST');
    $mail->SMTPAuth   = true;
    $mail->Username   = env('SMTP_USERNAME');
    $mail->Password   = env('SMTP_PASSWORD');
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = env('SMTP_PORT');

    // Email content
    $mail->setFrom(env('SMTP_FROM_EMAIL'), env('SMTP_FROM_NAME'));
    $mail->addAddress($UserEmail);
    $mail->isHTML(true); //يسمح باستخدامها داخل الرساله
    $mail->CharSet = 'UTF-8'; //حتى يدعم ايضًا اللغه العربيه
    $mail->Subject = "BuyWise Email Verification Code";
    $mail->Body = "
    <div style='font-family: Arial, sans-serif; font-size: 16px; color: #333;'>
        <p>Hi <strong>" . htmlspecialchars($UserName) . "</strong>,</p>
        <p>Welcome to <strong>BuyWise</strong>! Please enter the following verification code:</p>
        <div style='font-size: 24px; font-weight: bold; color: #006d77; margin: 20px 0;'>$code</div>
        <p>This code is required to activate your account. If you didn’t sign up for BuyWise, ignore this message.</p>
        <br><p style='color: #555;'>Best regards,<br>The BuyWise Team</p>
        <hr style='border-top: 1px solid #eee; margin: 30px 0;'>
        <p style='font-size: 12px; color: #999;'>Do not reply to this email. For help, contact 
        <a href='mailto:" . env('SMTP_FROM_EMAIL') . "' style='color: #006d77;'>" . env('SMTP_FROM_EMAIL') . "</a>.</p>
    </div>";

    $mail->send();
    echo "1"; // Email sent
} catch (Exception $e) {
    error_log("Mailer Error: " . $mail->ErrorInfo);
    echo "2"; // Sending failed
}
