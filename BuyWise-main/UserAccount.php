<?php
require_once "config.php";

// Redirect if not logged in as type 2 (normal user)
if ($_SESSION['type'] != 2 || !isset($_SESSION['UserID'])) {
    header("Location: login.php");
    exit();
}

$UserID = $_SESSION['UserID'];
$updateSuccess = false;
$errorMessage = "";
$passwordUpdated = false;

// Account deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    $con->query("DELETE FROM comment_likes WHERE UserID = $UserID");
    $con->query("DELETE FROM comments WHERE UserID = $UserID");
    $con->query("DELETE FROM products WHERE UserID = $UserID");
    $con->query("DELETE FROM notifications WHERE recipient_id = $UserID OR sender_id = $UserID");
    $con->query("DELETE FROM users WHERE UserID = $UserID");

    session_destroy();
    header("Location: login.php");
    exit();
}

// Profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $UserName = $_POST['UserName'] ?? '';
    $UserEmail = $_POST['UserEmail'] ?? '';
    $UserBirth = $_POST['UserBirth'] ?? '';
    $UserGender = $_POST['UserGender'] ?? '';
    $UserAddress = $_POST['UserAddress'] ?? '';
    $UserPhone = $_POST['UserPhone'] ?? '';
    $Bio = $_POST['Bio'] ?? '';
    $CurrentPassword = $_POST['CurrentPassword'] ?? null;
    $UserPassword = $_POST['UserPassword'] ?? null;
    $ConfirmPassword = $_POST['ConfirmUserPassword'] ?? null;

    $stmt = $con->prepare("SELECT UserPassword, Avatar FROM users WHERE UserID = ?");
    $stmt->bind_param("i", $UserID);
    $stmt->execute();
    $userData = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$userData) die(__('user_data_not_found'));
    $Avatar = $userData['Avatar'];
    $hasError = false;

    // Password change
    if ($UserPassword) {
        $stored = $userData['UserPassword']; //هاي الباس القديمه المخزنه بالداتا بيس
        $valid = (strlen($stored) > 40 && (str_starts_with($stored, '$2y$') || str_starts_with($stored, '$2a$')))
            ? password_verify($CurrentPassword, $stored) //مقارنتهم سوا بحيث لو الباس مشفره عالطريقه الجديده تاعتنا
            : ($CurrentPassword === $stored || md5($CurrentPassword) === $stored); //بسمح يطابقها مع الباسوورد المخزن لو كان بدون تشفير او لو كان مشفر بالطريقه القديمه ام دي 5


        if (!$valid) {
            $errorMessage = __('incorrect_current_password');
            $hasError = true;
        } elseif ($UserPassword !== $ConfirmPassword) {
            $errorMessage = __('passwords_do_not_match');
            $hasError = true;
        } else {
            $hash = password_hash($UserPassword, PASSWORD_DEFAULT); //PASSWORD_DEFAULT = bcrypt افضل وأأمن خوارزميه 
            $stmt = $con->prepare("UPDATE users SET UserPassword = ? WHERE UserID = ?");
            $stmt->bind_param("si", $hash, $UserID);
            if ($stmt->execute()) {
                $passwordUpdated = true;
                $stmt->close();
            } else {
                $errorMessage = __('password_update_failed');
                $hasError = true;
            }
        }
    }

    // Avatar upload
    if (!$hasError && isset($_FILES['Avatar']) && $_FILES['Avatar']['error'] === 0) {
        $avatarFile = $_FILES['Avatar'];
        $targetDir = "uploads/avatars/";
        $targetFile = $targetDir . basename($avatarFile['name']);

        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

        // هذه الدالة تفحص ما إذا كان الملف صورة فعلًا
        // ثم تحاول نقل الملف المؤقت (الموجود في مجلد مؤقت على السيرفر) إلى المكان النهائي المحدد بتارغيت فايل
        if (getimagesize($avatarFile['tmp_name']) !== false && move_uploaded_file($avatarFile['tmp_name'], $targetFile)) {
            $Avatar = basename($avatarFile['name']); //اذا نجحت العمليه هذا السطر يحدد اسم الصورة فقط (بدون المسار)، ويخزنها في المتغير افاتار
        } else {
            $errorMessage = __('upload_failed');
            $hasError = true;
        }
    }

    // Default avatar by gender
    if ((!isset($_FILES['Avatar']) || $_FILES['Avatar']['error'] !== 0)) {
        if ($UserGender === 'Male' || $UserGender === '1') $Avatar = 'MaleDef.png';
        elseif ($UserGender === 'Female' || $UserGender === '2') $Avatar = 'FemDef.png';
    }

    // Required fields
    if (empty($UserName) || empty($UserEmail)) {
        $errorMessage = __('required_fields_missing');
        $hasError = true;
    }

    // Update profile
    if (!$hasError) {
        $stmt = $con->prepare("UPDATE users SET UserName = ?, UserEmail = ?, UserBirth = ?, UserGender = ?, UserAddress = ?, UserPhone = ?, Avatar = ?, Bio = ? WHERE UserID = ?");
        $stmt->bind_param("ssssssssi", $UserName, $UserEmail, $UserBirth, $UserGender, $UserAddress, $UserPhone, $Avatar, $Bio, $UserID);
        if ($stmt->execute()) {
            $updateSuccess = true;
        } else {
            $errorMessage = __('user_update_failed');
        }
        $stmt->close();
    }

    if ($passwordUpdated) {
        $_SESSION['password_changed'] = true;
    }
}

// Force re-login if password changed
if (!empty($_SESSION['password_changed'])) {
    $_SESSION['login_message'] = __('login_again_after_password');
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

// Fetch latest user data
$stmt = $con->prepare("SELECT UserName, UserEmail, UserBirth, UserGender, UserAddress, UserPhone, Avatar, Bio, UserPassword FROM users WHERE UserID = ?");
$stmt->bind_param("i", $UserID);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$userData) die(__('user_data_not_found'));
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">
<head>
    <title>Manage User Account | BuyWise</title>
        <link rel="icon" href="img/favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Flatpickr CSS مكتبه لإنشاء تقويم لاختيار التاريخ منها-->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ar.css">

    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

   
</head>
<body class="<?php echo ($showPopup && ($_SERVER['REQUEST_METHOD'] === 'POST')) ? 'active-popup' : ''; ?>">
    <!-- Popup container -->
    <div class="popup" id="popup">
        <p id="popup-message">
            <?php 
            if ($updateSuccess) {
                echo __('information_updated_success');
            } elseif (!empty($errorMessage)) {
                echo htmlspecialchars($errorMessage); //معرّفه مسبقًا فوق شو محتواها
            }
            ?>
        </p>
        <button class="okbutton" onclick="closePopup()"><?= __('ok') ?></button>
    </div>


    <div class="filterblur">
        <div class="container py-5 d-flex justify-content-center">
            <div class="account-container">
                <h3 class="text-center mb-4"><?= __('manage_account') ?></h3>
 
        <!--multipart/form-data هو ترميز بسمحلي ابعت بالفورم عدة انواع من البيانات مع صور يعني رح يعرف يتعامل معهم-->
                <form id="editForm" method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?= __('name') ?></label>
                            <input type="text" class="form-control" name="UserName" value="<?= htmlspecialchars($userData['UserName']) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?= __('email') ?></label>
                            <input type="email" class="form-control bg-light" name="UserEmail" value="<?= htmlspecialchars($userData['UserEmail']) ?>" required readonly>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?= __('birth_date') ?></label>
                            <input type="date" class="form-control" name="UserBirth" value="<?= htmlspecialchars($userData['UserBirth']) ?>"     placeholder="<?= __('date_placeholder') ?>">
                            
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?= __('gender') ?></label>
                            <select class="form-control" name="UserGender">
                                <option value="Male" <?= $userData['UserGender'] == 'Male' ? 'selected' : '' ?>><?= __('male') ?></option>
                                <option value="Female" <?= $userData['UserGender'] == 'Female' ? 'selected' : '' ?>><?= __('female') ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?= __('address') ?></label>
                        <input type="text" class="form-control" name="UserAddress" value="<?= htmlspecialchars($userData['UserAddress'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?= __('phone') ?></label>
                        <input type="tel" class="form-control" name="UserPhone" value="<?= htmlspecialchars($userData['UserPhone']) ?>" pattern="07\d{8}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?= __('bio') ?></label>
                        <textarea class="form-control" name="Bio"><?= htmlspecialchars($userData['Bio'] ?? '' ) ?></textarea>
                    </div>

 <div class="password-section">
    <div class="alert alert-info" role="alert">
        <i class="fas fa-info-circle"></i> <?= __('changing_password_alert') ?>
    </div>

    
    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label"><?= __('current_password') ?></label>
            <div class="password-container">
                <input type="password" class="form-control password-input" name="CurrentPassword" id="CurrentPassword">
                <i class="fas fa-eye password-toggle" id="toggleCurrentPassword"></i>
            </div>
        </div>
    </div>

    
    <div class="row">
        <div class="col-md-6 mb-3">
            <label class="form-label"><?= __('new_password') ?></label>
            <div class="password-container">
                <input type="password" class="form-control password-input" name="UserPassword" id="UserPassword">
                <i class="fas fa-eye password-toggle" id="toggleUserPassword"></i>
            </div>
            <span id="invalidNewPassword" class="invalid-text"></span>
        </div>

        <div class="col-md-6 mb-3">
            <label class="form-label"><?= __('confirm_password') ?></label>
            <div class="password-container">
                <input type="password" class="form-control password-input" name="ConfirmUserPassword" id="ConfirmUserPassword">
                <i class="fas fa-eye password-toggle" id="toggleConfirmPassword"></i>
            </div>
            <span id="invalidConfirmPassword" class="invalid-text"></span>
        </div>
    </div>
</div>

                    <button type="submit" class="btn btn-primary w-100"><?= __('update_information') ?></button>

                    <!-- Delete Account Button (Triggers Modal) -->
                    <button type="button" class="btn-delete-account" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                        <i class="fas fa-trash-alt me-2"></i> <?= __('delete_my_account') ?>
                    </button>


                </form>

            </div>
        </div>
    </div>

<script>
function previewImage(event) {
    var reader = new FileReader();
    reader.onload = function() {
        var output = document.getElementById('avatarPreview');
        output.src = reader.result;
    };
    reader.readAsDataURL(event.target.files[0]);
}

function closePopup() {
    document.body.classList.remove('active-popup');
}

$(document).ready(function() {
    $('#editForm').on('submit', function() {
        // Let the form submit normally and handle the response via PHP
        return true;
    });
});

document.addEventListener("DOMContentLoaded", function () {
    
    document.body.classList.remove('active-popup');
});


function setupPasswordToggle(toggleId, inputId) {
    const toggleIcon = document.getElementById(toggleId);
    const inputField = document.getElementById(inputId);

    toggleIcon.addEventListener("click", function () {
        const type = inputField.getAttribute("type") === "password" ? "text" : "password";
        inputField.setAttribute("type", type);

        if (type === "password") {
            toggleIcon.classList.remove("fa-eye-slash");
            toggleIcon.classList.add("fa-eye");
        } else {
            toggleIcon.classList.remove("fa-eye");
            toggleIcon.classList.add("fa-eye-slash");
        }
    });
}

document.addEventListener("DOMContentLoaded", function () {
    setupPasswordToggle("toggleCurrentPassword", "CurrentPassword");
    setupPasswordToggle("toggleUserPassword", "UserPassword");
    setupPasswordToggle("toggleConfirmPassword", "ConfirmUserPassword");
});

document.getElementById("editForm").addEventListener("submit", function (e) {
    const newPassword = document.getElementById("UserPassword").value.trim();
    const confirmPassword = document.getElementById("ConfirmUserPassword").value.trim();
    const errorPassword = document.getElementById("invalidNewPassword");
    const errorConfirm = document.getElementById("invalidConfirmPassword");

    errorPassword.innerHTML = "";
    errorConfirm.innerHTML = "";

    let valid = true;

    if (newPassword !== "") {
        if (newPassword.length < 8) {
            errorPassword.innerHTML = "<?= __('error_password_length') ?>";
            valid = false;
        } else if (!/[A-Z]/.test(newPassword)) {
            errorPassword.innerHTML = "<?= __('error_password_uppercase') ?>";
            valid = false;
        } else if (!/[a-z]/.test(newPassword)) {
            errorPassword.innerHTML = "<?= __('error_password_lowercase') ?>";
            valid = false;
        } else if (!/[0-9]/.test(newPassword)) {
            errorPassword.innerHTML = "<?= __('error_password_number') ?>";
            valid = false;
        } else if (!/[^A-Za-z0-9]/.test(newPassword)) {
            errorPassword.innerHTML = "<?= __('error_password_special') ?>";
            valid = false;
        }

        if (confirmPassword !== newPassword) {
            errorConfirm.innerHTML = "<?= __('error_passwords_mismatch') ?>";
            valid = false;
        }
    }

    if (!valid) e.preventDefault();
});

</script>

<!-- Delete Account Confirmation Modal -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-labelledby="deleteAccountModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-danger">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="deleteAccountModalLabel">
          <i class="fas fa-exclamation-triangle me-2"></i> <?= __('confirm_delete_account') ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <?= __('no_undo') ?>
      </div>
      <div class="modal-footer">
        <form method="POST">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('cancel') ?></button>
          <button type="submit" name="delete_account" class="btn btn-danger"><?= __('delete') ?></button>
        </form>
      </div>
    </div>
  </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ar.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    flatpickr("input[name='UserBirth']", {
        dateFormat: "Y-m-d",
        locale: "<?= $lang === 'ar' ? 'ar' : 'default' ?>",
        allowInput: true
    });
});
</script>

</body>
</html>

<style>
    :root {
        /* Light Mode Variables - Using values from the first CSS file */
        --form-bg: #fff;
        --form-text: #333;
        --form-header: #006d77;
        --form-input-bg: #f6f6f6;
        --form-input-border: #eee;
        --form-button-bg: #e29578;
        --form-button-text: #fff;
        --form-link: #006d77;
        --form-link-hover: #e29578;
        --form-invalid: #ff3860;
        --overlay-bg: linear-gradient(to right, #83c5be, #006d77);
        --overlay-text: #fff;
        --popup-bg: #ffddd2;
        --popup-text: #006d77;
        --popup-button: #e29578;
        --popup-button-text: #ffddd2;
        --bg-color: #edf6f9;
    }

    body {
        font-family: 'Rubik', sans-serif;
        background-color: var(--bg-color);
        color: var(--form-text);
        transition: background-color 0.3s ease, color 0.3s ease;
    }

/* Popup Styling */
.popup {
    filter: none !important; 
    position: absolute;
    left: 50%;
    top: -100%;
    bottom: 50%;
    right: 50%;
    height: 160px;
    transform: translate(-50%, -50%);
    width: 350px;
    text-align: center;
    padding: 20px;
    background-color: var(--popup-bg);
    border-radius: 10px;
    box-shadow: 0 14px 28px rgba(0,0,0,0.25), 
                0 10px 10px rgba(0,0,0,0.22);
    margin-top: -25px;
    z-index: 4000;
    transition: top 0ms ease-in-out 300ms,
                opacity 300ms ease-in-out,
                margin-top 300ms ease-in-out;
}

.popup > * {
    margin: 15px 0px;
}

.popup .okbutton {
    position: absolute;
    bottom: -5px;
    left: 45%;
    right: 70%;
    width: 40px;
    height: 30px;
    background-color: var(--popup-button);
    color: var(--popup-button-text);
    border: none;
    outline: none;
    border-radius: 50%;
    cursor: pointer;
    margin-top: 10px;
}

.popup p {
    color: var(--popup-text);
}

body.active-popup {
    overflow: hidden;
}

body.active-popup .filterblur {
    filter: blur(5px);
    background: rgba(0,0,0,0.08);
    transition: filter 0ms ease-in-out 0ms;
}

body.active-popup .popup {
    top: 50%;
    opacity: 1;
    margin-top: 0px;
    transition: top 0ms ease-in-out 0ms,
                opacity 300ms ease-in-out,
                margin-top 300ms ease-in-out;
}

.filterblur {
    background-color: var(--bg-color);
    transition: filter 0ms ease-in-out 300ms, background-color 0.3s ease;
    min-height: 100vh;
}

    .account-container {
        max-width: 900px;
        background: var(--form-bg);
        padding: 2.5rem;
        border-radius: 12px;
        box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
        transition: background-color 0.3s ease;
    }

    .account-container h3 {
        color: var(--form-header);
        transition: color 0.3s ease;
    }

    .form-label {
        font-weight: 600;
        color: var(--form-header);
        transition: color 0.3s ease;
    }
    
    .password-section {
        border-top: 1px solid var(--form-input-border);
        padding-top: 20px;
        margin-top: 20px;
    }

   
    .form-control {
        border-radius: 8px;
        border: 1px solid var(--form-input-border);
        background-color: var(--form-input-bg);
        color: var(--form-text);
        transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
    }

    input[type="password"].form-control {
        background-color: var(--form-input-bg);
        color: var(--form-text);
        border-color: var(--form-input-border);
    }

  
    input:-webkit-autofill,
    input:-webkit-autofill:hover, 
    input:-webkit-autofill:focus, 
    input:-webkit-autofill:active {
        -webkit-box-shadow: 0 0 0 30px var(--form-input-bg) inset !important;
        -webkit-text-fill-color: var(--form-text) !important;
        transition: background-color 5000s ease-in-out 0s;
    }

    .form-control:focus {
        border-color: var(--form-link);
        box-shadow: 0 0 0 0.25rem rgba(0, 109, 119, 0.25);
        background-color: var(--form-input-bg);
        color: var(--form-text);
    }

    .btn-primary {
        background: var(--form-button-bg);
        color: var(--form-button-text);
        border: none;
        padding: 12px 18px;
        border-radius: 8px;
        font-weight: 600;
        transition: background-color 0.3s ease, color 0.3s ease;
    }

    .btn-primary:hover {
        background: var(--form-link-hover);
    }

  
    select.form-control {
        background-color: var(--form-input-bg);
        color: var(--form-text);
    }


    input[type="file"].form-control::file-selector-button {
        background-color: var(--form-button-bg);
        color: var(--form-button-text);
        border: none;
        padding: 8px 12px;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }

    input[type="file"].form-control::file-selector-button:hover {
        background-color: var(--form-link-hover);
    }
    
    
    .alert-info {
        background-color: rgba(0, 109, 119, 0.1);
        color: var(--form-header);
        border-color: rgba(0, 109, 119, 0.2);
        font-size: 0.9rem;
        padding: 0.75rem;
        margin-bottom: 1.5rem;
    }

.btn-delete-account {
    border: 2px solid #dc3545;
    background-color: transparent;
    color: #dc3545;
    font-weight: bold;
    border-radius: 8px;
    padding: 10px 20px;
    width: 100%;
    transition: all 0.3s ease;
    margin-top: 12px; 
}

.btn-delete-account:hover {
    background-color: #dc3545;
    color: white;
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

.invalid-text {
    color: #dc3545;
    font-size: 0.85rem;
    margin-top: 5px;
    display: block;
}

</style>