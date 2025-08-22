<?php

//هدول ال 3 اسطر يطلب من بي اتش بي عرض الاخطاء على الشاشه بدل ما يخفيها
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'config.php';

// Handle language switch (already in config, here for clean redirect with params) بحذف فقط اللانج من اليو ار ال
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'ar'])) {
    $_SESSION['lang'] = $_GET['lang'];
    $query = $_GET;
    unset($query['lang']);
    $url = strtok($_SERVER["REQUEST_URI"], '?');
    if (!empty($query)) $url .= '?' . http_build_query($query);
    header("Location: $url");
    exit;
}

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$lang = $_SESSION['lang'] ?? 'en';
$dir = 'ltr';

$tokenValid = false;
$success = '';
$userID = null;
$errors = ['new_password' => '', 'confirm_password' => ''];

if ($token) {
    $stmt = $con->prepare("SELECT UserID, token_expiry FROM users WHERE reset_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        if (strtotime($row['token_expiry']) > time()) {
            $tokenValid = true;
            $userID = $row['UserID'];
            $_SESSION['reset_user_id'] = $userID;
        } else {
            $globalError = __('invalid_or_expired_link');
        }
    } else {
        $globalError = __('invalid_or_expired_link');
    }
} else {
    $globalError = __('missing_token');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'], $_POST['confirm_password'])) {
    $newPass = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];
    $submittedToken = $token;
    $userID = $_SESSION['reset_user_id'] ?? null;
    $valid = true;

    if (empty($newPass)) {
        $errors['new_password'] = __('error_field_required');
        $valid = false;
    } elseif (strlen($newPass) < 8) {
        $errors['new_password'] = __('error_password_length');
        $valid = false;
    } 
    //هاي الميثود تستخدم الريجيولار اكسبرشن للتحقق من النمط
    elseif (!preg_match('/[A-Z]/', $newPass)) {
        $errors['new_password'] = __('error_password_uppercase');
        $valid = false;
    } elseif (!preg_match('/[a-z]/', $newPass)) {
        $errors['new_password'] = __('error_password_lowercase');
        $valid = false;
    } elseif (!preg_match('/[0-9]/', $newPass)) {
        $errors['new_password'] = __('error_password_number');
        $valid = false;
    } elseif (!preg_match('/[^A-Za-z0-9]/', $newPass)) {
        $errors['new_password'] = __('error_password_special');
        $valid = false;
    }

    if (empty($confirm)) {
        $errors['confirm_password'] = __('error_field_required');
        $valid = false;
    } elseif ($newPass !== $confirm) {
        $errors['confirm_password'] = __('error_passwords_do_not_match');
        $valid = false;
    }

    if ($valid && $userID) {
        $hashed = password_hash($newPass, PASSWORD_DEFAULT);
        $stmt = $con->prepare("UPDATE users SET UserPassword = ?, reset_token = NULL, token_expiry = NULL WHERE UserID = ? AND reset_token = ?");
        $stmt->bind_param("sis", $hashed, $userID, $submittedToken);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            unset($_SESSION['reset_user_id']);
            $tokenValid = false;
            $success = __('password_reset_success') . " <a href='login.php'>" . __('login_now') . "</a>";
        } else {
            $globalError = __('update_failed');
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="img/favicon.ico">
    <title><?= __('reset_password') ?> - BuyWise</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="login.css" rel="stylesheet">
    <style>
        .container.reset-wrapper {
            margin-top: 40px;
        }
        .reset-password-container {
            background-color: var(--form-bg);
            border-radius: 10px;
            box-shadow: 0 14px 28px rgba(0,0,0,0.25), 0 10px 10px rgba(0,0,0,0.22);
            width: 450px;
            max-width: 90%;
            padding: 20px;
            margin-top: 50px;
            margin-bottom: 50px;
        }
        .reset-password-card {
            text-align: center;
        }
        .reset-password-card h1 {
            color: var(--form-header);
            margin-bottom: 20px;
        }
        .reset-icon {
            font-size: 50px;
            color: var(--form-header);
            margin-bottom: 20px;
        }
        .reset-description {
            color: var(--form-text);
            margin-bottom: 25px;
        }
        .loginp-reset {
            background-color: var(--form-input-bg);
            border: none;
            padding: 12px 15px;
            margin: 8px 0;
            width: 100%;
            border-radius: 10px;
            color: var(--form-text);
        }
        .form-group {
            text-align: left;
            margin-bottom: 15px;
        }
        html[dir="rtl"] .form-group {
            text-align: right;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--form-text);
        }
        .invalid {
            color: red;
            font-size: 0.85em;
            margin-top: 3px;
            display: block;
        }
        .password-requirements {
            font-size: 0.8em;
            color: #777;
        }
        .reset-password-container a {
            color: var(--form-link);
            text-decoration: none;
        }
        .reset-password-container a:hover {
            color: var(--form-link-hover);
            text-decoration: underline;
        }


        body.loginn {
            min-height: 100vh;
            overflow-y: auto; /* السماح بالتمرير العمودي */
            padding: 60px 15px; 
        }

        .password-container {
            position: relative;
            width: 100%;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
            font-size: 16px;
            z-index: 10;
        }

        .password-toggle:hover {
            color: #333;
        }

        [dir="rtl"] .password-toggle {
            right: auto;
            left: 12px;
        }

        .password-input {
            padding-right: 40px !important;
        }

        [dir="rtl"] .password-input {
            padding-right: 12px !important;
            padding-left: 40px !important;
        }


    </style>
</head>
<body class="loginn">
    <div class="filterblur">
        <?php include("header.php"); ?>
        <div class="container reset-wrapper d-flex flex-column align-items-center justify-content-center">
            <?php if (!empty($success)): ?>
                <div class="reset-password-container text-center">
                    <div class="reset-icon"><i class="bi bi-check-circle"></i></div>
                    <h1><?= __('success') ?></h1>
                    <div class="alert alert-success"><?= $success ?></div>
                </div>
            <?php elseif (!empty($globalError)): ?>
                <div class="reset-password-container text-center">
                    <div class="reset-icon"><i class="bi bi-exclamation-triangle"></i></div>
                    <h1><?= __('error') ?></h1>
                    <div class="alert alert-danger"><?= $globalError ?></div>
                    <a href="forgot-password.php" class="logbtn mt-3"><?= __('try_again') ?></a>
                </div>
            <?php elseif ($tokenValid): ?>
                <div class="reset-password-container">
                    <div class="reset-password-card">
                        <div class="reset-icon"><i class="bi bi-shield-lock"></i></div>
                        <h1><?= __('reset_password') ?></h1>
                        <p class="reset-description"><?= __('enter_new_password') ?></p>
                        <form method="POST" action="">
                            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                            <div class="form-group">
                                <label for="new_password"><?= __('new_password') ?></label>
                                <div class="password-container">
                                    <input type="password" id="new_password" name="new_password" class="loginp-reset password-input" required>
                                    <i class="fas fa-eye password-toggle" onclick="togglePassword('new_password', this)"></i>
                                </div>

                                <small class="password-requirements"><?= __('password_requirements') ?></small>
                                <span class="invalid"><?= $errors['new_password'] ?></span>
                            </div>
                            <div class="form-group mb-4">
                                <label for="confirm_password"><?= __('confirm_password') ?></label>
                                <div class="password-container">
                                    <input type="password" id="confirm_password" name="confirm_password" class="loginp-reset password-input" required>
                                    <i class="fas fa-eye password-toggle" onclick="togglePassword('confirm_password', this)"></i>
                                </div>
                                <span class="invalid"><?= $errors['confirm_password'] ?></span>
                            </div>
                            <button type="submit" class="logbtn"><?= __('update_password') ?></button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

<script>
function togglePassword(fieldId, iconElement) {
    const input = document.getElementById(fieldId);
    const isHidden = input.type === "password";
    input.type = isHidden ? "text" : "password";

    //ميثود كلاس ليست تشتغل على اساس انو ازا الكلاس موجود تشيله و ازا مش موجود تضيفه
    iconElement.classList.toggle('fa-eye');
    iconElement.classList.toggle('fa-eye-slash');
}

</script>


</body>
</html>