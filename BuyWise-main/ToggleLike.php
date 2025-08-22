<?php
require_once "config.php";

if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'ar'])) {
    $_SESSION['lang'] = $_GET['lang'];
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit();
}

$lang = $_SESSION['lang'] ?? 'en';
$dir = $lang === 'ar' ? 'rtl' : 'ltr';
require_once "lang.php";

if (!$con) {
    echo json_encode(['status' => 'error', 'message' => __('db_connection_failed')]);
    exit;
}

$commentID = intval($_POST['CommentID'] ?? 0);
$UserID = intval($_POST['UserID'] ?? 0);
$action = $_POST['Action'] ?? 'like';

if ($commentID <= 0 || $UserID <= 0 || $action !== 'like') {
    echo json_encode(['status' => 'error', 'message' => __('invalid_parameters')]);
    exit;
}

// Check if user already liked the comment
$stmt = $con->prepare("SELECT LikeID FROM comment_likes WHERE CommentID = ? AND UserID = ?");
$stmt->bind_param("ii", $commentID, $UserID);
$stmt->execute();
$existingLike = $stmt->get_result()->fetch_assoc();
$stmt->close();

$response = ['status' => 'success', 'action' => '', 'likeCount' => 0];

if ($existingLike) {
    // Unlike (remove the like)
    $stmt = $con->prepare("DELETE FROM comment_likes WHERE CommentID = ? AND UserID = ?");
    $stmt->bind_param("ii", $commentID, $UserID);
    $stmt->execute();
    $stmt->close();
    $response['action'] = 'removed';
} else {
    // Add like
    $stmt = $con->prepare("INSERT INTO comment_likes (CommentID, UserID) VALUES (?, ?)");
    $stmt->bind_param("ii", $commentID, $UserID);
    $stmt->execute();
    $stmt->close();
    $response['action'] = 'liked';

    // Update points and badges for comment owner
    $ownerStmt = $con->prepare("SELECT UserID FROM comments WHERE CommentID = ?");
    $ownerStmt->bind_param("i", $commentID);
    $ownerStmt->execute();
    $commentOwnerID = $ownerStmt->get_result()->fetch_assoc()['UserID'] ?? 0;
    $ownerStmt->close();

    if ($UserID != $commentOwnerID && $commentOwnerID > 0) {
        $categoryField = ($lang === 'ar') ? 'CategoryName_ar' : 'CategoryName_en';
        $catStmt = $con->prepare("
            SELECT cat.$categoryField AS CategoryName
            FROM comments c
            JOIN products p ON c.ProductID = p.ProductID
            JOIN categories cat ON p.CategoryID = cat.CategoryID
            WHERE c.CommentID = ?");
        $catStmt->bind_param("i", $commentID);
        $catStmt->execute();
        $catName = strtolower($catStmt->get_result()->fetch_assoc()['CategoryName'] ?? '');
        $catStmt->close();

        $pointsToAdd = ($catName === 'local') ? 4 : 2;
        $addPoints = $con->prepare("UPDATE users SET points = points + ? WHERE UserID = ?");
        $addPoints->bind_param("ii", $pointsToAdd, $commentOwnerID);
        $addPoints->execute();
        $addPoints->close();

        // Update badge if needed
        $badgeStmt = $con->prepare("SELECT points, badge, badge_rank FROM users WHERE UserID = ?");
        $badgeStmt->bind_param("i", $commentOwnerID);
        $badgeStmt->execute();
        $user = $badgeStmt->get_result()->fetch_assoc();
        $badgeStmt->close();

        if ($user) {
            $points = (int)$user['points'];
            $currentRank = (int)$user['badge_rank'];
            $badges = ['Professional', 'Expert', 'Legend'];
            $ranks = [500 => 1, 1500 => 2, 5000 => 3];
  
            //لكل عنصر في الرانك، المفتاح هو ثريشولدنج و القيمه هي الرانك
            foreach ($ranks as $threshold => $rank) {
                if ($points >= $threshold && $currentRank < $rank) {
                    $badge = $badges[$rank - 1]; //ناقص واحد لاو المصفوفات تبدأ من صفر، و هالسطر بجيب البادج الجديده المناسبه
                    $stmt = $con->prepare("UPDATE users SET badge = ?, badge_rank = ? WHERE UserID = ?");
                    $stmt->bind_param("sii", $badge, $rank, $commentOwnerID);
                    $stmt->execute();
                    $stmt->close();
                    break;
                }
            }
        }
    }
}

// Prepare notification link and insert notification if liker is not the comment owner
$getCommentOwner = $con->prepare("
    SELECT c.UserID AS CommentOwnerID, c.ProductID, c.ParentCommentID
    FROM comments c
    WHERE c.CommentID = ?");
$getCommentOwner->bind_param("i", $commentID);
$getCommentOwner->execute();
$ownerData = $getCommentOwner->get_result()->fetch_assoc();
$getCommentOwner->close();

if ($ownerData && $UserID != $ownerData['CommentOwnerID']) {
    $link = "Products.php?ProductID={$ownerData['ProductID']}";
    $link .= !empty($ownerData['ParentCommentID']) ? "&comment={$ownerData['ParentCommentID']}&reply=$commentID" : "&comment=$commentID"; //هاد مشان ازا كا الكومت عباره عن رد فيضيف رقم الربلي بالليك حتى يوصل لعنده بالزبط
    $msg = __('liked_your_comment');
    $notif = $con->prepare("INSERT INTO notifications (recipient_id, sender_id, message, link) VALUES (?, ?, ?, ?)");
    $notif->bind_param("iiss", $ownerData['CommentOwnerID'], $UserID, $msg, $link);
    $notif->execute();
    $notif->close();
}

// Get current like count for the comment
$stmt = $con->prepare("SELECT COUNT(*) as count FROM comment_likes WHERE CommentID = ?");
$stmt->bind_param("i", $commentID);
$stmt->execute();
$response['likeCount'] = $stmt->get_result()->fetch_assoc()['count']; //بوديه رد لصفحة البروكتس عشان يتجدد عدد اللايكات بالسبان هناك ويظهر لليوزرز عالصفحه
$stmt->close();

echo json_encode($response);
