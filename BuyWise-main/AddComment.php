<?php
// Set JSON response header
header("Content-Type: application/json");

// Load config and session
require_once 'config.php';

// Load translations
require_once 'lang.php';
$lang = $_SESSION['lang'] ?? 'en';
$dir = ($lang === 'ar') ? 'rtl' : 'ltr';

// Ensure user is logged in and is type 2
if (!isset($_SESSION['type']) || $_SESSION['type'] != 2) {
    echo json_encode(["status" => 0, "error" => "Unauthorized"]);
    exit;
}

// Validate required POST fields
if (!isset($_POST['ProductID'], $_POST['UserID'], $_POST['CommentText'], $_POST['Rating'])) {
    echo json_encode(["status" => 0, "error" => "Missing required fields"]);
    exit;
}

$ProductID = intval($_POST['ProductID']);
$UserID = intval($_POST['UserID']);
$CommentText = trim($_POST['CommentText']);

$isFake = null;

// Detect mostly English content
function is_mostly_english($text)
{
    $cleaned = preg_replace('/[^\p{L}\s]/u', '', $text); // تنظيف النص من الرموز والعلامات
    $words = explode(' ', $cleaned);
    $englishCount = 0;
    $totalCount = 0;
    foreach ($words as $word) {
        $word = trim($word);
        if ($word === '') continue;
        $totalCount++;
        if (preg_match('/^[a-zA-Z]+$/', $word)) $englishCount++;
    }
    return $totalCount > 0 && ($englishCount / $totalCount) >= 0.5; //إذا على الأقل 50% من الكلمات إنجليزيه برجع ترو غير هيك فولس 
}

// Call AI to check for fake review
if (is_mostly_english($CommentText)) {
    $payload = json_encode(["text" => $CommentText]); // يرسل نص التعليق بصيغة جيسون 
    $ch = curl_init("http://127.0.0.1:5000/predict"); //يرسل بوست الى فلاسك إي بي آي على لوكال هوست
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload
    ]);
    $response = curl_exec($ch); // يتم تنفيذ الطلب واستلام الرد من الإي بي آي
    curl_close($ch);

    $ai_result = json_decode(trim($response), true); //تحويل الرد من جيسون الى مصفوفه

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($ai_result)) {
        echo json_encode(["status" => 0, "error" => "Unexpected AI response", "raw" => $response]);
        exit;
    }

    if (!empty($ai_result['error']) && $ai_result['error'] === "Non-English input") {
        echo json_encode(["status" => 0, "error" => __('improve_review_error')]);
        exit;
    }

    // If fake, flag it and notify user
    if (isset($ai_result['prediction']) && strtolower(trim($ai_result['prediction'])) === 'fake') {
        $isFake = 1;
        $stmt = $con->prepare("INSERT INTO notifications (sender_id, recipient_id, recipient_type, message, link, is_read) VALUES (?, ?, ?, ?, ?, 0)");
        $null = null;
        $type = 'user';
        $msg = __('review_flagged_fake');
        $link = "#";
        $stmt->bind_param("iisss", $null, $UserID, $type, $msg, $link);
        $stmt->execute();
        $stmt->close();
    } else {
        $isFake = 0;
    }
}

// Extract and validate ratings
$Rating = min(5, max(1, intval($_POST['Rating'])));
$QualityRating = isset($_POST['QualityRating']) ? min(5, max(1, intval($_POST['QualityRating']))) : 5;
$commentImage = null;

// Handle image upload if present
if (isset($_FILES['CommentImage']) && $_FILES['CommentImage']['error'] === UPLOAD_ERR_OK) {
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $filename = $_FILES['CommentImage']['name'];
    $fileExt = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    if (in_array($fileExt, $allowed)) {
        $uploadDir = 'uploads/comments/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $newFilename = time() . '_' . uniqid() . '.' . $fileExt;
        $destination = $uploadDir . $newFilename;
        if (move_uploaded_file($_FILES['CommentImage']['tmp_name'], $destination)) {
            $commentImage = $newFilename;
        }
    }
}

// Determine product source (user or company)
$isCompany = isset($_POST['isCompanyProduct']) && $_POST['isCompanyProduct'] === 'true';
$ProductID_DB = $isCompany ? null : $ProductID;
$CproductID_DB = $isCompany ? $ProductID : null;

// Insert comment
$stmt = $con->prepare("INSERT INTO comments (UserID, ProductID, CproductID, CommentText, Rating, CommentImage, QualityRating, IsFake) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("iiisssii", $UserID, $ProductID_DB, $CproductID_DB, $CommentText, $Rating, $commentImage, $QualityRating, $isFake);

if (!$stmt->execute()) {
    echo json_encode(["status" => 0, "error" => $stmt->error]);
    exit;
}

$CommentID = $stmt->insert_id;
$stmt->close();

// Get product category
$categoryName = '';
$catQuery = $isCompany
    ? "SELECT c.CategoryName_en, c.CategoryName_ar FROM company_products p JOIN categories c ON p.CategoryID = c.CategoryID WHERE p.ProductID = ?"
    : "SELECT c.CategoryName_en, c.CategoryName_ar FROM products p JOIN categories c ON p.CategoryID = c.CategoryID WHERE p.ProductID = ?";
$stmtCat = $con->prepare($catQuery);
$stmtCat->bind_param("i", $ProductID);
$stmtCat->execute();
$catRes = $stmtCat->get_result();
if ($row = $catRes->fetch_assoc()) {
    $categoryName = strtolower(trim($lang === 'ar' ? $row['CategoryName_ar'] : $row['CategoryName_en']));
}
$stmtCat->close();

// Get product owner ID
$productOwnerID = null;
$ownerQuery = $isCompany ? "SELECT CompanyID AS owner FROM company_products WHERE ProductID = ?" : "SELECT UserID AS owner FROM products WHERE ProductID = ?";
$stmtOwner = $con->prepare($ownerQuery);
$stmtOwner->bind_param("i", $ProductID);
$stmtOwner->execute();
$resOwner = $stmtOwner->get_result();
if ($owner = $resOwner->fetch_assoc()) {
    $productOwnerID = $owner['owner'];
}
$stmtOwner->close();

// Notify product owner if different
if ($productOwnerID && $productOwnerID != $UserID) {
    $msg = __('commented_on_product');
    $link = "Products.php?ProductID=$ProductID&comment=$CommentID";
    $stmtNotif = $con->prepare("INSERT INTO notifications (recipient_id, sender_id, message, link) VALUES (?, ?, ?, ?)");
    $stmtNotif->bind_param("iiss", $productOwnerID, $UserID, $msg, $link);
    $stmtNotif->execute();
    $stmtNotif->close();
}

// Update points and badge only if not fake
if ($isFake !== 1 && $productOwnerID != $UserID) {
    $pointsToAdd = ($categoryName === 'local') ? 6 : 3;
    $updatePoints = $con->prepare("UPDATE users SET points = points + ? WHERE UserID = ?");
    $updatePoints->bind_param("ii", $pointsToAdd, $UserID);
    $updatePoints->execute();
    $updatePoints->close();

    if ($categoryName === 'local') {
        $null = null;
        $type = 'user';
        $msg = __('review_earned_points');
        $link = "/product1/Profile.php";
        $stmtNotif = $con->prepare("INSERT INTO notifications (sender_id, recipient_id, recipient_type, message, link, is_read) VALUES (?, ?, ?, ?, ?, 0)");
        $stmtNotif->bind_param("iisss", $null, $UserID, $type, $msg, $link);
        $stmtNotif->execute();
        $stmtNotif->close();
    }

    // Check badge upgrade
    $getUser = $con->prepare("SELECT points, badge, badge_rank FROM users WHERE UserID = ?");
    $getUser->bind_param("i", $UserID);
    $getUser->execute();
    $userData = $getUser->get_result()->fetch_assoc();
    $getUser->close();

    $newPoints = $userData['points'];
    $currentRank = (int)$userData['badge_rank'];
    $badge = $userData['badge'];
    $newBadge = $badge;
    $newRank = $currentRank;

    if ($newPoints >= 5000 && $currentRank < 3) {
        $newBadge = 'Legend'; $newRank = 3;
    } elseif ($newPoints >= 1500 && $currentRank < 2) {
        $newBadge = 'Expert'; $newRank = 2;
    } elseif ($newPoints >= 500 && $currentRank < 1) {
        $newBadge = 'Professional'; $newRank = 1;
    }

    if ($newRank > $currentRank) {
        $stmt = $con->prepare("UPDATE users SET badge = ?, badge_rank = ? WHERE UserID = ?");
        $stmt->bind_param("sii", $newBadge, $newRank, $UserID);
        $stmt->execute();
        $stmt->close();
    }
}


// Handle penalties for repeated fake reviews(repeat fake reviews)
if ($isFake === 1 && is_mostly_english($CommentText)) {
    $stmt = $con->prepare("INSERT INTO reported_reviews (UserID, CommentID, ReviewContent) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $UserID, $CommentID, $CommentText);
    $stmt->execute();
    $stmt->close();

    $stmt = $con->prepare("SELECT COUNT(*) AS FakeCount FROM reported_reviews WHERE UserID = ?");
    $stmt->bind_param("i", $UserID);
    $stmt->execute();
    $FakeCount = $stmt->get_result()->fetch_assoc()['FakeCount'] ?? 0;
    $stmt->close();

    if ($FakeCount == 10) $days = 5;
    elseif ($FakeCount == 15) $days = 30;
    elseif ($FakeCount >= 20) {
        $stmt = $con->prepare("DELETE FROM users WHERE UserID = ?");
        $stmt->bind_param("i", $UserID);
        $stmt->execute();
        $stmt->close();
        session_unset();
        session_destroy();
        echo json_encode(["status" => 9, "message" => __('account_permanently_deleted')]);
        exit;
    }

    if (isset($days)) {
        $until = date('Y-m-d H:i:s', strtotime("+{$days} days"));
        $stmt = $con->prepare("UPDATE users SET UserStatus = 0, DeactivateUntil = ? WHERE UserID = ?");
        $stmt->bind_param("si", $until, $UserID);
        $stmt->execute();
        $stmt->close();
        session_unset();
        session_destroy();
        echo json_encode(["status" => 9, "message" => __('account_temp_deactivated')]);
        exit;
    }

    echo json_encode(["status" => 2, "message" => __('review_flagged_message')]);
    exit;
}

// Final successful response
echo json_encode([
    "status" => 1,
    "message" => __('comment_added_points_updated'),
    "commentID" => $CommentID
]);
$con->close();
exit;