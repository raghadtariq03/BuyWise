<?php
require_once 'config.php';
require_once 'lang.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'PHPMailer/src/Exception.php';

$lang = $_SESSION['lang'] ?? 'en';
$dir = 'ltr';

if (!isset($_SESSION['pending_user'])) {
    header("Location: login.php");
    exit();
}

// Handle resend
if (isset($_GET['resend']) && $_GET['resend'] == 1) {
    $code = rand(100000, 999999);
    $_SESSION['pending_user']['code'] = $code;

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = env('SMTP_HOST');
        $mail->SMTPAuth = true;
        $mail->Username = env('SMTP_USERNAME');
        $mail->Password = env('SMTP_PASSWORD');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = env('SMTP_PORT');

        $mail->setFrom(env('SMTP_FROM_EMAIL'), env('SMTP_FROM_NAME'));
        $mail->addAddress($_SESSION['pending_user']['email']);

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = "BuyWise Email Verification Code";

        $userName = htmlspecialchars($_SESSION['pending_user']['name'] ?? 'User');
        $mail->Body = "
            <div style='font-family: Arial; font-size: 16px; color: #333;'>
                <p>Hi <strong>$userName</strong>,</p>
                <p>Welcome to <strong>BuyWise</strong>! Please use the code below:</p>
                <div style='font-size: 24px; font-weight: bold; color: #006d77; margin: 20px 0;'>$code</div>
                <p>This code is valid for a limited time.</p>
                <br>
                <p style='color: #555;'>- BuyWise Team</p>
                <hr style='border:none; border-top:1px solid #eee; margin-top:30px;'/>
                <p style='font-size: 12px; color: #999;'>Need help? <a href='mailto:" . env('SMTP_FROM_EMAIL') . "'>Contact us</a>.</p>
            </div>";

        $mail->send();
        $resend_success = true;
    } catch (Exception $e) {
        $resend_error = __('resend_failed');
    }
}

// Handle form submit
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $entered_code = trim($_POST['code'] ?? '');
    $session_code = $_SESSION['pending_user']['code'] ?? '';

    if ($entered_code == $session_code) {
        $user = $_SESSION['pending_user'];
        $avatar = $user['gender'] == '2' ? 'FemDef.png' : 'MaleDef.png';

        $stmt = $con->prepare("INSERT INTO users (UserName, UserPhone, UserEmail, UserPassword, UserGender, Avatar, UserStatus) 
                               VALUES (?, ?, ?, ?, ?, ?, 1)");
        $stmt->bind_param("ssssss", $user['name'], $user['phone'], $user['email'], $user['password'], $user['gender'], $avatar);

        if ($stmt->execute()) {
            unset($_SESSION['pending_user']);
            $verified_success = true;
        } else {
            $error = __('registration_failed') . ": " . $stmt->error;
        }
    } else {
        $error = __('invalid_verification_code');
    }
}
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">

<head>
    <title><?= __('verify_email') ?> - BuyWise</title>
    <link rel="icon" href="img/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="login.css" rel="stylesheet">
    <style>
        :root {
            --form-bg: #ffffff;
            --form-header: #006d77;
            --form-input-bg: #f5f7fa;
            --form-input-border: #d1d9e6;
            --form-text: #333;
            --form-button-bg: #006d77;
            --form-button-text: #ffffff;
            --form-link: #008891;
            --form-link-hover: #005f63;
            --footer-bg: #006d77;
            --text-color: #ffffff;
            --shadow-color: rgba(0, 0, 0, 0.1);
            --popup-bg: #ffddd2;
            --popup-text: #006d77;
            --popup-button: #e29578;
            --popup-button-text: #fff;
        }

        body.loginn {
            background: linear-gradient(to bottom right, #e6f1f3, #ffffff);
            min-height: 100vh;
            display: flex;
            flex-direction: column;

            justify-content: center;
            align-items: center;
            min-height: 100vh;
            min-width: 100vw;
        }

        .verify-wrapper {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem;
            width: 100%;
            height: 100%;
        }

        .verification-container {
            width: 100%;
            max-width: 500px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            background: var(--form-bg);
            padding: 2rem;
            animation: fadeIn 0.6s ease-out;
        }

        .verification-header {
            color: var(--form-header);
            margin-bottom: 1.5rem;
            font-weight: 600;
        }

        .verification-input {
            background-color: var(--form-input-bg);
            border: 1px solid var(--form-input-border);
            color: var(--form-text);
            font-size: 1.1rem;
            letter-spacing: 0.5px;
            padding: 12px 20px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }

        .verification-input:focus {
            border-color: var(--form-header);
            box-shadow: 0 0 0 0.2rem rgba(0, 109, 119, 0.25);
        }

        .verification-btn {
            background: var(--form-button-bg) !important;
            color: var(--form-button-text);
            border: none;
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }

        .verification-btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .resend-btn {
            color: var(--form-link);
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin-top: 1rem;
        }

        .resend-btn:hover {
            color: var(--form-link-hover);
            transform: translateY(-1px);
        }

        .code-instruction {
            color: var(--form-text);
            opacity: 0.9;
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
        }

        .verification-icon {
            font-size: 2.5rem;
            color: var(--form-header);
            margin-bottom: 1rem;
        }

        .alert {
            border-radius: 10px;
        }

        .alert-success {
            background-color: rgba(130, 197, 190, 0.2);
            border-color: var(--form-header);
            color: var(--form-header);
        }

        .alert-danger {
            background-color: rgba(226, 149, 120, 0.2);
            border-color: var(--form-button-bg);
            color: var(--form-button-bg);
        }

        .verification-logo {
            max-width: 120px;
            margin-bottom: 1rem;
        }

        .code-input-wrapper {
            position: relative;
        }

        .code-input-icon {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            opacity: 0.7;
            color: var(--form-header);
        }

        /* اليمين للغة الإنجليزية */
        html[dir="ltr"] .code-input-icon {
            right: 15px;
        }

        /* اليسار للغة العربية */
        html[dir="rtl"] .code-input-icon {
            left: 15px;
        }


        .user-email {
            font-weight: 600;
            color: var(--form-link);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .footer {
            background-color: var(--footer-bg);
            color: var(--text-color);
            box-shadow: 0 -2px 10px var(--shadow-color);
            bottom: 0;
            width: 100%;
        }

        .footer a {
            color: inherit;
            text-decoration: none;
        }

        .footer a:hover {
            text-decoration: underline;
        }

        .main-header {
            background-color: #fff;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
        }

        .popup.active-popup {

            top: 50%;
            opacity: 1;
            margin-top: 0px;

            transition: top 0ms ease-in-out 0ms,
                opacity 300ms ease-in-out,
                margin-top 300ms ease-in-out;
        }

        .popup {
            position: fixed;
            top: -100%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: var(--popup-bg);
            color: var(--popup-text);
            border: 2px solid #006d77;
            padding: 2rem;
            z-index: 9999;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            width: 350px;
            height: 160px;
            padding: 20px;
            text-align: center;
            transition: top 0ms ease-in-out 300ms, opacity 300ms ease-in-out, margin-top 300ms ease-in-out;
            opacity: 0;
        }

        .okbutton {
            position: absolute;
            bottom: -10px;
            left: 45%;
            width: 40px;
            height: 30px;
            background-color: var(--popup-button);
            color: var(--popup-button-text);
            border: none;
            border-radius: 50%;
            cursor: pointer;
            margin-top: 10px;
        }

        body.active-popup {
            overflow: hidden;
        }

        body.active-popup .filterblur {
            filter: blur(5px);
            background: rgba(0, 0, 0, 0.08);
            transition: filter 0ms ease-in-out 0ms;
        }



        .filterblur {
            transition: filter 0ms ease-in-out 300ms;
            min-height: 100vh;
            min-width: 100%;
        }
    </style>
</head>

<body class="loginn <?= !empty($verified_success) ? 'active-popup' : '' ?>">


    <?php include("header.php"); ?>
    <?php if (!empty($verified_success)): ?>
        <div class="popup active-popup" id="popup">
            <h5><?= __('success') ?></h5>
            <p><?= __('account_verified_success') ?></p>
            <button class="okbutton" onclick="redirectToLogin()">OK</button>
        </div>
        <script>
            function redirectToLogin() {
                window.location.href = "login.php";
            }
        </script>
    <?php endif; ?>

    <div class="filterblur">
        <div class="container d-flex justify-content-between align-items-center">
            <a href="Home.php" class="navbar-brand fw-bold text-primary">BuyWise</a>
            <div>
                <a href="?lang=ar" class="btn btn-sm btn-outline-secondary me-2">العربية</a>
                <a href="?lang=en" class="btn btn-sm btn-outline-secondary">English</a>
            </div>
        </div>
        </header>

        <main class="verify-wrapper">
            <div class="verification-container text-center">
                <div class="verification-icon">
                    <i class="fas fa-envelope-open-text"></i>
                </div>

                <h3 class="verification-header"><?= __('verify_email') ?></h3>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger mb-4"><?= htmlspecialchars($error) ?></div>
                <?php elseif (!empty($resend_success)): ?>
                    <div class="alert alert-success mb-4"><?= __('code_resent_success') ?></div>
                <?php elseif (!empty($resend_error)): ?>
                    <div class="alert alert-danger mb-4"><?= htmlspecialchars($resend_error) ?></div>
                <?php endif; ?>

                <p class="code-instruction">
                    <span class="text-muted small"><?= __('email_sent_to') ?>:</span><br>
                    <?php if (isset($_SESSION['pending_user']['email'])): ?>
                        <span class="user-email"><?= htmlspecialchars($_SESSION['pending_user']['email']) ?></span>
                    <?php endif; ?>
                </p>

                <form method="post">
                    <div class="mb-4 code-input-wrapper">
                        <input name="code" type="text" class="form-control verification-input"
                            placeholder="<?= __('enter_code_placeholder') ?>" required>
                        <span class="code-input-icon">
                            <i class="fas fa-lock"></i>
                        </span>
                    </div>

                    <button type="submit" class="btn verification-btn w-100">
                        <i class="fas fa-check-circle me-2"></i><?= __('verify') ?>
                    </button>
                </form>

                <div class="mt-3">
                    <a href="?resend=1" class="resend-btn">
                        <i class="fas fa-paper-plane me-1"></i><?= __('resend_code') ?>
                    </a>
                </div>
            </div>
        </main>

        <footer class="footer py-3">
            <div class="container text-center">
                <p class="mb-0">
                    &copy; <?= date('Y') ?> <a href="#">BuyWise</a>. <?= __('all_rights_reserved') ?>
                </p>
            </div>
        </footer>
    </div>
</body>

</html>

<!--Output buffering-->
<!-- ينهي التخزين المؤقت للآوتبوت ويرسل المحتوى المخزن إلى المتصفح -->
<?php ob_end_flush(); ?> 