<?php
@session_start();
require_once "config.php";

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    echo 'csrf_error'; //اذا ما تطابق رمز الامان المُرسَل مع الموجود بالجلسه من قبل
    exit;
}

//يأخذ الإيميل وكلمة المرور المدخلة من المستخدم
$useremail = $_POST['email'] ?? ''; 
$rawPassword = $_POST['password'] ?? '';

// Check admin login
$stmt = $con->prepare("SELECT * FROM admins WHERE AdminEmail = ?");
$stmt->bind_param("s", $useremail);
$stmt->execute();
$result = $stmt->get_result();

if ($admin = $result->fetch_assoc()) {
    // باستخدام الميثود باسوورد فيريفايVerify admin password 
    if (password_verify($rawPassword, $admin['AdminPassword'])) {
        $_SESSION['UserID'] = $admin['AdminID'];
        $_SESSION['username'] = $admin['AdminName'];
        $_SESSION['badge'] = 'Admin';
        $_SESSION['type'] = 1;
        echo $_SESSION['type'];
        exit;
    }
}

// Check regular user login
$stmt = $con->prepare("SELECT * FROM users WHERE UserEmail = ?");
$stmt->bind_param("s", $useremail);
$stmt->execute();
$result = $stmt->get_result();

if ($user = $result->fetch_assoc()) {
    // Reactivate user if deactivation period ended
    if ($user['UserStatus'] == 0 && !empty($user['DeactivateUntil'])) {
        if (date('Y-m-d H:i:s') >= $user['DeactivateUntil']) {
            $update = $con->prepare("UPDATE users SET UserStatus = 1, DeactivateUntil = NULL WHERE UserID = ?");
            $update->bind_param("i", $user['UserID']);
            $update->execute();
            $user['UserStatus'] = 1;
        }
    }

    // Verify user password
    if ($user['UserStatus'] == 1 && password_verify($rawPassword, $user['UserPassword'])) {
        $_SESSION['UserID'] = $user['UserID'];
        $_SESSION['username'] = $user['UserName'];
        $_SESSION['badge'] = $user['badge'];
        $_SESSION['type'] = 2;
        echo $_SESSION['type'];
        exit;
    }
}

// If login fails (خطأ بالبريد أو كلمة المرور)
echo "0";