<?php
@session_start();
error_reporting(E_ALL); //تظهرلي كل انواع الاخطاء مو بس بتسجلهم
ini_set('display_errors', 1); //واحد يعني عرض جميع الاخطاء

require_once 'config.php';

// Ensure user is logged in
if (!isset($_SESSION['type'])) {
    header("Location: login.php");
    exit();
}

$isUser = $_SESSION['type'] == 2;
$popupMessage = '';
$showPopup = false;

$UserID = $_SESSION['UserID'] ?? 0; //بحط قيمتها صفر ازا ما لقاها
$userPoints = 0;

if ($isUser) {
    // Fetch user points
    $userStmt = $con->prepare("SELECT Points FROM users WHERE UserID = ?");
    $userStmt->bind_param("i", $UserID);
    $userStmt->execute();
    $userData = $userStmt->get_result()->fetch_assoc();
    $userPoints = $userData['Points'] ?? 0;
    $userStmt->close();
}

// Handle voucher redemption
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isUser) {
        $popupMessage = __('only_users_can_redeem');
        $showPopup = true;
    } elseif (isset($_POST['VoucherID'])) {
        $voucherID = intval($_POST['VoucherID']);

        $stmt = $con->prepare("SELECT MinPoints FROM company_vouchers WHERE VoucherID = ?");
        $stmt->bind_param("i", $voucherID);
        $stmt->execute();
        $voucher = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($voucher && $userPoints >= $voucher['MinPoints']) {
            $checkStmt = $con->prepare("SELECT 1 FROM user_vouchers WHERE UserID = ? AND VoucherID = ?"); //الهدف انو ازا رجع رقم واحد عالاقل يعني الفاوتشر هاي ماخدها اليوزر مسبقًا
            $checkStmt->bind_param("ii", $UserID, $voucherID);
            $checkStmt->execute();
            $alreadyRedeemed = $checkStmt->get_result()->num_rows > 0;
            $checkStmt->close();

            if (!$alreadyRedeemed) {
                $con->begin_transaction(); //رح يبدا تنفيذ عدة اوامر سوا عالداتا بيس ازا نجت كلها تمام اما صار مشكله بوقف تنفيذ

                //ديداكت متغير احنا اخترنا اسمه و معناه طرح او خضم من النقاط
                $deduct = $con->prepare("UPDATE users SET Points = Points - ? WHERE UserID = ?");
                $deduct->bind_param("ii", $voucher['MinPoints'], $UserID);
                $deduct->execute();
                $deduct->close();

                $insert = $con->prepare("INSERT INTO user_vouchers (UserID, VoucherID) VALUES (?, ?)");
                $insert->bind_param("ii", $UserID, $voucherID);
                $insert->execute();
                $insert->close();

                $userInfoStmt = $con->prepare("SELECT UserName FROM users WHERE UserID = ?");
                $userInfoStmt->bind_param("i", $UserID);
                $userInfoStmt->execute();
                $userRow = $userInfoStmt->get_result()->fetch_assoc();
                $userInfoStmt->close();

                $voucherInfoStmt = $con->prepare("SELECT VoucherCode, CompanyID FROM company_vouchers WHERE VoucherID = ?");
                $voucherInfoStmt->bind_param("i", $voucherID);
                $voucherInfoStmt->execute();
                $voucherRow = $voucherInfoStmt->get_result()->fetch_assoc();
                $voucherInfoStmt->close();

                $userName = $userRow['UserName'] ?? 'User';
                $voucherCode = $voucherRow['VoucherCode'] ?? 'Voucher';
                $companyID = (int)$voucherRow['CompanyID'];

                $message = "$userName claimed voucher '$voucherCode'.";
                $url = "CompanyDashboard.php?tab=vouchers";

                $notifyStmt = $con->prepare("INSERT INTO notifications (sender_id, recipient_id, message, link, is_read, created_at)
                                             VALUES (?, ?, ?, ?, 0, NOW())");
                $notifyStmt->bind_param("iiss", $UserID, $companyID, $message, $url);
                $notifyStmt->execute();
                $notifyStmt->close();

                $con->commit();

                $popupMessage = __('voucher_redeemed_success');
                $userPoints -= $voucher['MinPoints'];
            } else {
                $popupMessage = __('voucher_already_redeemed');
            }
        } else {
            $popupMessage = __('voucher_insufficient_points');
        }

        $showPopup = true;
    }
}

// Fetch available vouchers
$voucherQuery = $con->query("SELECT cv.VoucherID, cv.VoucherCode, cv.MinPoints, cv.ExpiryDate, c.CompanyName
    FROM company_vouchers cv
    JOIN companies c ON cv.CompanyID = c.CompanyID
    WHERE cv.ExpiryDate >= CURDATE()
    ORDER BY cv.MinPoints ASC");
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">
<head>
    <title><?= __('rewards_center') ?> | BuyWise</title>
    <link rel="icon" href="img/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="User.css">
</head>
<body>
<?php include 'header.php'; ?>

<div class="container py-5">
    <h2 class="mb-4"><?= __('rewards_center') ?></h2>
    <?php if ($isUser): ?>
        <div class="mb-3">
            <strong><?= __('your_points') ?>:</strong> <?= $userPoints ?>
        </div>
    <?php endif; ?>

    <?php if ($voucherQuery->num_rows > 0): ?>
        <div class="row row-cols-1 row-cols-md-3 g-4">
            <?php while ($voucher = $voucherQuery->fetch_assoc()): ?>
                <div class="col">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-body text-center">
                            <h5 class="card-title"><?= htmlspecialchars($voucher['VoucherCode']) ?></h5>
                            <p><strong><?= __('company_name') ?>:</strong> <?= htmlspecialchars($voucher['CompanyName']) ?></p>
                            <p><strong><?= __('points_required') ?>:</strong> <?= $voucher['MinPoints'] ?></p>
                            <p><strong><?= __('expiry_date') ?>:</strong> <?= htmlspecialchars($voucher['ExpiryDate']) ?></p>
                            <form method="POST">
                                <input type="hidden" name="VoucherID" value="<?= $voucher['VoucherID'] ?>"><!--مخفي لكن ببعت قيمه للسيرفر لما ينبعت -->
                                <button type="submit" class="btn btn-outline-success w-100"
                                    <?= (!$isUser || $userPoints < $voucher['MinPoints']) ? 'disabled' : '' ?>
                                >
                                    <?= __('redeem_now') ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info"><?= __('no_vouchers_available') ?></div>
    <?php endif; ?>
</div>

<?php if ($showPopup): ?>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const popup = document.createElement("div");
        popup.className = "popup show";
        popup.innerHTML = `
        <div id="popup-message"><?= htmlspecialchars($popupMessage, ENT_QUOTES) ?></div>
        <button class="okbutton" onclick="document.querySelector('.popup').remove()">OK</button>
    `; //بتحول الكوتس لصيغةاتش تي ام الENT_QUOTES  
        document.body.appendChild(popup);
    });
</script>
<?php endif; ?>

</body>
</html>