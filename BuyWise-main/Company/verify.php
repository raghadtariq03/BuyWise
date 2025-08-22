<?php
require_once '../config.php';

if (isset($_GET['email'], $_GET['token'])) {
    $email = $_GET['email'];
    $token = $_GET['token'];

    // Check if email and token are valid
    $stmt = $con->prepare("SELECT CompanyID FROM companies WHERE CompanyEmail = ? AND VerifyToken = ?");
    $stmt->bind_param("ss", $email, $token);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
        // Valid token – mark as verified
        $update = $con->prepare("UPDATE companies SET Verified = 1, VerifyToken = NULL WHERE CompanyEmail = ?");
        $update->bind_param("s", $email);
        if ($update->execute()) {
            $_SESSION['popup'] = ['title' => __('success'), 'message' => __('email_verified_success')];
            header("Location: CompanyLogin.php");
            exit();
        } else {
            $_SESSION['popup'] = ['title' => __('error'), 'message' => __('verification_update_failed')];
            header("Location: CompanyLogin.php");
            exit();
        }
    } else {
        $_SESSION['popup'] = ['title' => __('error'), 'message' => __('invalid_verification_link')];
        header("Location: CompanyLogin.php");
        exit();
    }
} else {
    $_SESSION['popup'] = ['title' => __('error'), 'message' => __('missing_verification_params')];
    header("Location: CompanyLogin.php");
    exit();
}
?>