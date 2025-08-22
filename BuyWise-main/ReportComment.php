<?php
require_once 'config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'denied', 'message' => 'Only POST requests allowed']);
    exit;
}

$commentID  = intval($_POST['CommentID'] ?? 0);
$reporterID = $_SESSION['UserID'] ?? 0;
$reason     = trim($_POST['Reason'] ?? '');

if ($commentID <= 0 || $reporterID <= 0 || $reason === '') {
    echo json_encode(['status' => 'invalid', 'message' => 'Invalid data provided.']);
    exit;
}

mysqli_query($con, "SET NAMES 'utf8mb4'");

// Check for duplicate report
$check = $con->prepare("SELECT 1 FROM reported_comments WHERE CommentID = ? AND UserID = ?");
$check->bind_param("ii", $commentID, $reporterID);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['status' => 'exists', 'message' => 'You already reported this comment']);
    exit;
}

// Insert the report
$stmt = $con->prepare("INSERT INTO reported_comments (CommentID, UserID, ReportReason, ReportDate) VALUES (?, ?, ?, NOW())");
$stmt->bind_param("iis", $commentID, $reporterID, $reason);

if ($stmt->execute()) {

    // Get reporter name
 
$reporterName = 'User';
$commenterName = 'Unknown';

$res1 = $con->prepare("SELECT UserName FROM users WHERE UserID = ?");
$res1->bind_param("i", $reporterID);
$res1->execute();
if ($r = $res1->get_result()->fetch_assoc()) {
    $reporterName = $r['UserName'];
}

$res2 = $con->prepare("SELECT u.UserName FROM comments c JOIN users u ON c.UserID = u.UserID WHERE c.CommentID = ?");
$res2->bind_param("i", $commentID);
$res2->execute();
if ($r = $res2->get_result()->fetch_assoc()) {
    $commenterName = $r['UserName'];
}


$cleanReason = htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');
$message = "$commenterName's comment was reported by $reporterName for reason: \"$cleanReason\".";
$link = "/product1/Admin/AdminManageComments.php";


$admins = mysqli_query($con, "SELECT AdminID FROM admins");
while ($admin = mysqli_fetch_assoc($admins)) {
    $adminID = (int) $admin['AdminID'];
    $stmt = $con->prepare("INSERT INTO notifications (sender_id, recipient_id, message, link, created_at) VALUES (NULL, ?, ?, ?, NOW())");
    $stmt->bind_param("iss", $adminID, $message, $link);
    $stmt->execute();
    $stmt->close();
}


    echo json_encode(['status' => 'success', 'message' => 'Reported successfully']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to report']);
}

exit;
