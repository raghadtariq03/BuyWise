<?php
require_once 'config.php'; // Includes session, lang, env, and PHPMailer

date_default_timezone_set("Asia/Amman");

$error = '';
$success = '';
$dir = 'ltr';

// Available company email senders
$company_emails = [
    'support' => [
        'email' => 'buywise2025@gmail.com',
        'name'  => 'BuyWise Support'
    ],
];

// Default sender type
$sender_type = 'support';

// Handle password reset request
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['email'])) {
    $email = trim($_POST['email']);

    // Use specified sender type if valid
    if (isset($_POST['sender_type'], $company_emails[$_POST['sender_type']])) {
        $sender_type = $_POST['sender_type'];
    }

    // Check if the email exists
    $stmt = $con->prepare("SELECT UserID FROM users WHERE UserEmail = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        $stmt->bind_result($userID);
        $stmt->fetch();

        // Generate secure token and expiry
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Clear any previous token
        $clear = $con->prepare("UPDATE users SET reset_token = NULL, token_expiry = NULL WHERE UserID = ?");
        $clear->bind_param("i", $userID);
        $clear->execute();

        // Store new reset token
        $update = $con->prepare("UPDATE users SET reset_token = ?, token_expiry = ? WHERE UserID = ?");
        $update->bind_param("ssi", $token, $expiry, $userID);

        if ($update->execute()) {
            $resetLink = "http://localhost/product1/reset-password.php?token=" . urlencode($token); //الميثود هاي بتشفر التوكين حتى يكون آمن لاستخدامه داخل اليو آر إل

            try {
                // Prepare email
                $mail->setFrom($company_emails[$sender_type]['email'], $company_emails[$sender_type]['name']);
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = "Reset Your BuyWise Password";

                // Email content
                $mail->Body = "
                <div style='font-family: Arial, sans-serif; font-size: 16px; color: #333;'>
                    <p>Hi,</p>
                    <p>You recently requested to reset your password for your <strong>BuyWise</strong> account.</p>
                    <p>Please click the button below to reset your password:</p>
                    <p style='margin: 20px 0;'>
                        <a href='$resetLink' style='
                            display: inline-block;
                            background-color: #006d77;
                            color: #fff;
                            padding: 10px 20px;
                            border-radius: 5px;
                            text-decoration: none;
                            font-weight: bold;
                        '>Reset Password</a>
                    </p>
                    <p>This link will expire in 1 hour. If you did not request a password reset, you can safely ignore this email.</p>
                    <br>
                    <p style='color: #555;'>Best regards,<br>The BuyWise Team</p>
                    <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                    <p style='font-size: 12px; color: #999;'>
                        Need help? Contact our support team at 
                        <a href='mailto:buywise2025@gmail.com' style='color: #006d77;'>buywise2025@gmail.com</a>.
                    </p>
                </div>
                ";
                $mail->send();
                $success = __('reset_email_sent');
            } catch (Exception $e) {
                $error = __('error_reset_request') . " Error: {$mail->ErrorInfo}";
            }
        } else {
            $error = __('error_reset_request');
        }
    } else {
        $error = __('error_email_not_found');
    }
}
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <title><?= __('forgot_password_title') ?> - BuyWise</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="login.css" rel="stylesheet">
    <link rel="icon" href="img/favicon.ico">
    <style>
        .forgot-password-container {
            background-color: var(--form-bg);
            border-radius: 10px;
            box-shadow: 0 14px 28px rgba(0,0,0,0.25), 0 10px 10px rgba(0,0,0,0.22);
            width: 450px;
            max-width: 100%;
            padding: 30px 25px;
            margin: 0 auto;
        }

        body.loginn {
            min-height: 100vh;
            
            display: block; 
            padding: 130px; 
            overflow-y: auto;   
        }

        .forgot-password-card {
            text-align: center;
        }
        .forgot-password-card h1 {
            color: var(--form-header);
            margin-bottom: 30px;
        }
        .forgot-icon {
            font-size: 50px;
            color: var(--form-header);
            margin-bottom: 20px;
        }
        .forgot-description {
            color: var(--form-text);
            margin-bottom: 25px;
        }
        
        .forgot-password-card a {
            color: var(--form-link);
            text-decoration: none;
        }
        
        .forgot-password-card a:hover {
            color: var(--form-link-hover);
            text-decoration: underline;
        }
        
        .company-email-select {
            margin-bottom: 20px;
        }
        
        .company-email-select select {
            width: 100%;
            padding: 10px;
            border-radius: 5px;
            border: 1px solid #ccc;
            background-color: var(--input-bg);
            color: var(--form-text);
        }
    </style>
</head>
<body class="loginn">
    <div class="filterblur">
        <?php include("header.php"); ?>

        <div class="forgot-password-container">
            <div class="forgot-password-card">
                <div class="forgot-icon">
                    <i class="bi bi-key"></i>
                </div>
                <h1><?= __('forgot_password') ?></h1>
                <p class="forgot-description"><?= __('forgot_password_instruction') ?></p>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php elseif ($success): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                <?php endif; ?>

                <form action="" method="POST">
                    <div class="mb-4">
                        <input class="loginp" type="email" name="email" placeholder="<?= __('email') ?>" required>
                    </div>
                    
                    <button class="logbtn" type="submit"><?= __('send_reset_link') ?></button>
                </form>

                <div class="mt-4">
                    <p><?= __('remember_your_password') ?? 'Remember your password?' ?> <a href="login.php"><?= __('login') ?></a></p>
                </div>
            </div>
        </div>
    </div>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</body>
</html>