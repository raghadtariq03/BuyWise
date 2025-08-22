<?php
require_once '../config.php';
require_once '../lang.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once '../PHPMailer/src/PHPMailer.php';
require_once '../PHPMailer/src/SMTP.php';
require_once '../PHPMailer/src/Exception.php';

$lang = $_SESSION['lang'] ?? 'en';
$dir = $lang === 'ar' ? 'rtl' : 'ltr';

// Redirect if no pending registration
if (!isset($_SESSION['pending_company'])) {
    header("Location: CompanyLogin.php");
    exit();
}

$error = '';
$success = '';
$resend_success = false;
$resend_error = '';

// Handle verification code submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['code'])) {
    $inputCode = trim($_POST['code']);
    $storedCode = $_SESSION['pending_company']['code'] ?? '';
    
    if ($inputCode == $storedCode) {
        // Code is correct, save company to database
        $name = $_SESSION['pending_company']['name'];
        $email = $_SESSION['pending_company']['email'];
        $password = $_SESSION['pending_company']['password'];
        $country = $_SESSION['pending_company']['country'];
        
        $stmt = $con->prepare("INSERT INTO companies (CompanyName, CompanyEmail, CompanyPassword, Country, Verified, CompanyStatus) VALUES (?, ?, ?, ?, 1, 0)");
        $stmt->bind_param("ssss", $name, $email, $password, $country);
        
        if ($stmt->execute()) {
            unset($_SESSION['pending_company']);
            $success = __('registration_success_pending_approval');
            $_SESSION['popup'] = ['title' => __('success'), 'message' => __('registration_success_pending_approval')];
            header("Location: CompanyLogin.php?verified=1");
            exit();
        } else {
            $error = __('registration_failed');
        }
        $stmt->close();
    } else {
        $error = __('invalid_verification_code');
    }
}

// Handle resend code
if (isset($_GET['resend']) && $_GET['resend'] == 1) {
    $code = rand(100000, 999999);
    $_SESSION['pending_company']['code'] = $code;

    $email = $_SESSION['pending_company']['email'];
    $companyName = htmlspecialchars($_SESSION['pending_company']['name'] ?? 'Company');

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
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = __('verify_account_title') . ' - BuyWise';

        //هذا المسج غير معتمد للكومباني، المعتمد بصفحة الكومباني لوجين واسمه ميل بودي
        $message = '
        <!DOCTYPE html>
        <html lang="' . $lang . '" dir="' . $dir . '">
        <head>
          <meta charset="UTF-8">
          <title>' . __('verify_account_title') . '</title>
        </head>
        <body style="font-family: Arial, sans-serif; background-color: #f4f4f4; padding: 20px; direction:' . $dir . '; margin: 0;">
          <table width="100%" cellpadding="0" cellspacing="0" style="max-width:600px; margin:auto; background-color:#ffffff; border-radius:10px; box-shadow:0 0 10px rgba(0,0,0,0.1);">
            <tr>
              <td style="padding: 30px; text-align: center;">
                <h2 style="color: #006d77; margin-bottom: 10px;">' . __('verify_account_title') . '</h2>
                <p style="color: #333333; font-size: 16px; margin-bottom: 20px;">' . __('verify_account_description') . '</p>

                <div style="background-color: #e7f3ff; border-left: 4px solid #83c5be; padding: 10px 15px; border-radius: 5px; margin-bottom: 30px;">
                  <small style="color: #333333;">' . __('code_sent_notice') . ' <strong>' . htmlspecialchars($email) . '</strong></small>
                </div>

                <p style="font-size: 18px; font-weight: bold; margin-bottom: 10px;">' . __('verification_code_label') . ':</p>
                <div style="display:inline-block; font-size: 24px; font-weight: bold; color: #006d77; background-color: #edf6f9; padding: 15px 25px; border-radius: 8px; letter-spacing: 8px; margin-bottom: 20px;">
                  ' . htmlspecialchars($code) . '
                </div>

                <p style="font-size: 14px; color: #666666; margin-top: 30px;">' . __('didnt_receive_code') . '</p>

                <p style="font-size: 12px; color: #aaaaaa; margin-top: 40px;">&copy; ' . date('Y') . ' BuyWise</p>
              </td>
            </tr>
          </table>
        </body>
        </html>';

        $mail->Body = $message;
        $mail->send();
        $resend_success = true;
        $_SESSION['popup'] = ['title' => __('success'), 'message' => __('verification_code_resent')];
    } catch (Exception $e) {
        $resend_error = __('resend_failed');
        $_SESSION['popup'] = ['title' => __('error'), 'message' => __('resend_failed')];
    }
}
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('verify_account_title') ?> | BuyWise</title>
    <link rel="icon" href="../img/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #83c5be;
            --primary-dark: #006d77;
            --accent-color: #e29578;
            --accent-light: #ffddd2;
            --text-color: #333;
            --bg-color: #edf6f9;
            --card-bg: #fff;
            --navbar-bg: linear-gradient(135deg, #83c5be, #006d77);
            --footer-bg: #83c5be;
            --shadow-color: rgba(0, 0, 0, 0.1);
        }

        body {
            background-color: var(--bg-color);
            font-family: 'Rubik', sans-serif;
            padding-top: 100px;
            min-height: 100vh;
        }

        .header-title {
            color: var(--primary-dark);
            font-weight: 600;
        }

        .product-card {
            background-color: var(--card-bg);
            border-radius: 15px;
            box-shadow: 0 8px 25px var(--shadow-color);
            transition: transform 0.3s ease;
        }

        .product-card:hover {
            transform: translateY(-5px);
        }

        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 12px;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(131, 197, 190, 0.25);
        }

        .btn-verify {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            font-weight: bold;
            border-radius: 10px;
            border: none;
            padding: 12px;
            transition: all 0.3s ease;
        }

        .btn-verify:hover {
            background: linear-gradient(135deg, var(--accent-color), var(--primary-color));
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .alert {
            border-radius: 10px;
            border: none;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }

        .alert-success {
            background-color: #d1edda;
            color: #155724;
        }

        .footer {
            background-color: var(--footer-bg);
            color: var(--text-color);
            box-shadow: 0 -2px 10px var(--shadow-color);
        }

        .footer a {
            color: inherit;
            text-decoration: none;
        }

        .footer a:hover {
            text-decoration: underline;
        }

        .verification-info {
            background-color: #e7f3ff;
            border-left: 4px solid var(--primary-color);
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .code-input {
            font-size: 18px;
            text-align: center;
            letter-spacing: 3px;
        }
    </style>
</head>

<body class="d-flex flex-column min-vh-100">
    <?php include("../header.php"); ?>

    <div class="container py-5 flex-grow-1">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8">
                <div class="text-center mb-4">
                    <h2 class="header-title"><?= __('verify_account_title') ?></h2>
                    <p class="text-muted"><?= __('verify_account_description') ?></p>
                </div>

                <div class="product-card p-4">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger text-center">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                        <?php if (strpos($error, __('no_pending_registration')) !== false): ?>
                            <div class="text-center">
                                <a href="CompanyLogin.php" class="btn btn-outline-primary"><?= __('go_to_registration') ?></a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success text-center">
                            <i class="fas fa-check-circle me-2"></i>
                            <?= htmlspecialchars($success) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_SESSION['pending_company']) && empty($success)): ?>
                        <div class="verification-info">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                <?= __('code_sent_notice') ?> <strong><?= htmlspecialchars($_SESSION['pending_company']['email']) ?></strong>
                            </small>
                        </div>

                        <form method="post" id="verifyForm">
                            <div class="mb-4">
                                <label for="code" class="form-label fw-bold"><?= __('verification_code_label') ?></label>
                                <input 
                                    name="code" 
                                    type="text" 
                                    class="form-control code-input" 
                                    id="code" 
                                    placeholder="000000"
                                    maxlength="6"
                                    pattern="[0-9]{6}"
                                    title="<?= __('verification_code_title') ?>"
                                    required
                                    autocomplete="off"
                                >
                            </div>
                            
                            <button type="submit" class="btn btn-verify w-100 py-3">
                                <i class="fas fa-shield-check me-2"></i>
                                <?= __('verify_account_button') ?>
                            </button>
                        </form>

                        <div class="text-center mt-3">
                            <small class="text-muted">
                                <?= __('didnt_receive_code') ?> 
                                <a href="?resend=1" class="text-decoration-none"><?= __('resend_code_link') ?></a>
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer py-3 mt-auto">
        <div class="container text-center">
            <p class="mb-0 text-light">
                &copy; <?= date('Y') ?> <a href="#" class="text-light">BuyWise</a>. <?= __('all_rights_reserved') ?>
            </p>
        </div>
    </footer>

    <script src="https://kit.fontawesome.com/your-fontawesome-kit.js"></script>
    <script>
        const codeInput = document.getElementById('code');
        if (codeInput) {
            codeInput.addEventListener('input', function () {
                this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
            });
            codeInput.addEventListener('keyup', function () {
                if (this.value.length === 6) {
                    document.getElementById('verifyForm').submit();
                }
            });
        }

    document.getElementById('resendLink').addEventListener('click', function(e) {
        e.preventDefault();
        fetch(window.location.href + '?resend=1')
            .then(() => {
                alert("<?= __('verification_code_resent') ?>");
            })
            .catch(() => {
                alert("<?= __('resend_failed') ?>");
            });
    });
    </script>
</body>
</html>
