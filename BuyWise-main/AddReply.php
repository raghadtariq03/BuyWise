<?php
// Return JSON response
header('Content-Type: application/json');

// Load required files
require_once 'config.php';
require_once 'functions.php';

// Get and sanitize POST data
$commentID = intval($_POST['CommentID']);
$UserID = intval($_POST['UserID']);
$productID = intval($_POST['ProductID']);
$commentText = trim($_POST['CommentText']);
$isCompanyProduct = isset($_POST['isCompanyProduct']) && $_POST['isCompanyProduct'] == 1;

// Validate comment text
if (empty($commentText)) {
    echo json_encode(['status' => 'error', 'message' => __('empty_comment')]);
    exit;
}

// Ensure parent comment exists
$stmt = $con->prepare("SELECT UserID FROM comments WHERE CommentID = ?");
$stmt->bind_param("i", $commentID);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows == 0) {
    echo json_encode(['status' => 'error', 'message' => __('parent_comment_not_found')]);
    exit;
}
$parentUserID = $result->fetch_assoc()['UserID'];
$stmt->close();

// Insert reply (user or company product)
if ($isCompanyProduct) {
    $stmt = $con->prepare("INSERT INTO comments (UserID, CproductID, CommentText, ParentCommentID, CommentDate) VALUES (?, ?, ?, ?, NOW())");
} else {
    $stmt = $con->prepare("INSERT INTO comments (UserID, ProductID, CommentText, ParentCommentID, CommentDate) VALUES (?, ?, ?, ?, NOW())");
}
$stmt->bind_param("iisi", $UserID, $productID, $commentText, $commentID);
$success = $stmt->execute();
$replyID = $stmt->insert_id;
$stmt->close();

if ($success) {
   
    if ($parentUserID !== $UserID) {
        $link = "Products.php?ProductID={$productID}&comment={$commentID}&reply={$replyID}";
        $message = __('replied_to_comment');
        $stmt = $con->prepare("INSERT INTO notifications (recipient_id, sender_id, message, link) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $parentUserID, $UserID, $message, $link);
        $stmt->execute();
        $stmt->close();
    }

    // Get reply user info
    $stmt = $con->prepare("SELECT UserName, Avatar, badge, UserGender FROM users WHERE UserID = ?");
    $stmt->bind_param("i", $UserID);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Send success response with reply info
    echo json_encode([
        'status' => 'success',
        'reply' => [
            'CommentID'   => $replyID,
            'CommentText' => nl2br(htmlspecialchars($commentText)),
            'CommentDate' => date('F j, Y \a\t g:i a'),
            'UserName'    => $user['UserName'],
            'Avatar'      => getAvatarPath($user['Avatar'] ?? '', $user['UserGender'] ?? ''),
            'badge'       => $user['badge'] ?? 'Normal',
            'UserID'      => $UserID,
            'LikeCount'   => 0,
            'UserLiked'   => 0
        ]
    ]);
} else {
    // Handle failure
    echo json_encode(['status' => 'error', 'message' => __('reply_insert_failed')]);
}