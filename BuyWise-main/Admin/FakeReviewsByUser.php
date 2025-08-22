<?php
require_once '../config.php';

// Check admin access
if (!isset($_SESSION['type'], $_SESSION['UserID']) || $_SESSION['type'] != 1) {
    header("Location: ../login.php");
    exit;
}

$userID = intval($_GET['UserID'] ?? 0);
$UserEmail = '';
$reviews = [];
$errorMessage = '';

if ($userID > 0) {
    // Get user email
    $stmt = $con->prepare("SELECT UserEmail FROM users WHERE UserID = ?");
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($user = $res->fetch_assoc()) {
        $UserEmail = $user['UserEmail'];

        // Get reported reviews
        $stmt = $con->prepare("
            SELECT rr.ReviewContent, rr.ReportDate, 
                   COALESCE(p.ProductName, prod.ProductName) AS ProductName
            FROM reported_reviews rr
            LEFT JOIN comments c ON rr.CommentID = c.CommentID
            LEFT JOIN products p ON c.ProductID = p.ProductID
            LEFT JOIN products prod ON rr.ProductID = prod.ProductID
            WHERE rr.UserID = ?
            ORDER BY rr.ReportDate DESC
        ");
        $stmt->bind_param("i", $userID);
        $stmt->execute();
        $reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $errorMessage = __('user_not_found');
    }
} else {
    $errorMessage = __('invalid_user_id');
}
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <title><?= __('fake_reviews_by_user') ?> | BuyWise</title>
    <link rel="icon" href="../img/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="../style.css" rel="stylesheet">
    <link href="Admin.css" rel="stylesheet">
    <?php include("../header.php"); ?>
</head>

<body class="admin">

<div class="admin-breadcrumb-wrapper">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="Dashboard.php"><i class="fas fa-home me-1"></i> <?= __('admin_dashboard') ?></a></li>
                <li class="breadcrumb-item"><a href="AdminReports.php"><i class="fas fa-flag me-1"></i> <?= __('reports') ?></a></li>
                <li class="breadcrumb-item active" aria-current="page"><i class="fas fa-user-secret me-1"></i> <?= __('fake_reviews_by_user') ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="container py-5">
    <div class="admin-card">
        <h5 class="mb-3"><i class="fas fa-user me-2"></i><?= __('fake_reviews_by_user') ?></h5>

        <?php if ($errorMessage): ?>
            <div class="alert alert-danger text-center"><?= htmlspecialchars($errorMessage) ?></div>
        <?php else: ?>
            <h6 class="mb-4"><strong><?= __('email') ?>:</strong> <?= htmlspecialchars($UserEmail) ?></h6>

            <?php if (count($reviews) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover text-center align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 40%"><?= __('review_content') ?></th>
                                <th style="width: 20%"><?= __('date') ?></th>
                                <th style="width: 40%"><?= __('product') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reviews as $r): ?>
                                <tr>
                                    <td class="text-break text-start px-3"><?= htmlspecialchars($r['ReviewContent']) ?></td>
                                    <td><?= htmlspecialchars($r['ReportDate']) ?></td>
                                    <td><?= htmlspecialchars($r['ProductName']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info text-center"><?= __('no_fake_reviews_found') ?></div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<footer class="footer fixed-footer mt-auto py-3">
    <div class="container text-center">
        <p class="mb-0 text-light">&copy; <?= date('Y') ?> <a href="#" class="text-light">BuyWise</a>. <?= __('all_rights_reserved') ?></p>
    </div>
</footer>
</body>
</html>