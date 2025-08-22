<?php
require_once '../config.php';

// Admin check
if (!isset($_SESSION['type'], $_SESSION['UserID']) || $_SESSION['type'] != 1) {
    header("Location: ../login.php");
    exit();
}

// Ban user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['BanUserID'])) {
    $banUserID = intval($_POST['BanUserID']);
    $stmt = $con->prepare("UPDATE users SET UserStatus = 0 WHERE UserID = ?");
    $stmt->bind_param("i", $banUserID);
    echo $stmt->execute() ? 1 : 0;
    $stmt->close();
    exit;
}

// Reported user stats
$statsQuery = "
SELECT 
    COUNT(DISTINCT r.UserID) AS total_reported_users,
    SUM(CASE WHEN (
        (SELECT COUNT(*) FROM comments c WHERE c.UserID = r.UserID AND c.IsFake = 1) +
        (SELECT COUNT(*) FROM products p WHERE p.UserID = r.UserID AND p.IsFake = 1)
    ) >= 20 THEN 1 ELSE 0 END) AS users_ready_for_deletion,
    SUM(CASE WHEN (
        (SELECT COUNT(*) FROM comments c WHERE c.UserID = r.UserID AND c.IsFake = 1) +
        (SELECT COUNT(*) FROM products p WHERE p.UserID = r.UserID AND p.IsFake = 1)
    ) BETWEEN 15 AND 19 THEN 1 ELSE 0 END) AS users_ready_for_30day_ban,
    SUM(CASE WHEN (
        (SELECT COUNT(*) FROM comments c WHERE c.UserID = r.UserID AND c.IsFake = 1) +
        (SELECT COUNT(*) FROM products p WHERE p.UserID = r.UserID AND p.IsFake = 1)
    ) BETWEEN 10 AND 14 THEN 1 ELSE 0 END) AS users_ready_for_5day_ban,
    AVG((
        SELECT COUNT(*) FROM comments c WHERE c.UserID = r.UserID AND c.IsFake = 1
    ) + (
        SELECT COUNT(*) FROM products p WHERE p.UserID = r.UserID AND p.IsFake = 1
    )) AS avg_fake_reviews_per_user
FROM reported_reviews r
";
$statsResult = $con->query($statsQuery);
$stats = $statsResult->fetch_assoc();

// Get reported user details
$userQuery = "
SELECT 
    u.UserID,
    u.UserName,
    (
        SELECT COUNT(*) FROM comments c WHERE c.UserID = u.UserID AND c.IsFake = 1
    ) + (
        SELECT COUNT(*) FROM products p WHERE p.UserID = u.UserID AND p.IsFake = 1
    ) AS FakeReviews,
    (
        SELECT COUNT(*) FROM comments c WHERE c.UserID = u.UserID AND c.IsFake = 0
    ) + (
        SELECT COUNT(*) FROM products p WHERE p.UserID = u.UserID AND p.IsFake = 0
    ) AS RealReviews,
    (
        SELECT COUNT(*) FROM comments c WHERE c.UserID = u.UserID AND c.IsFake IS NULL
    ) + (
        SELECT COUNT(*) FROM products p WHERE p.UserID = u.UserID AND p.IsFake IS NULL
    ) AS UnverifiedReviews,
    (
        SELECT COUNT(*) FROM comments WHERE UserID = u.UserID
    ) + (
        SELECT COUNT(*) FROM products WHERE UserID = u.UserID
    ) AS TotalReviews,
    MAX(r.ReportDate) AS LastReportDate
FROM reported_reviews r
JOIN users u ON u.UserID = r.UserID
GROUP BY u.UserID, u.UserName
ORDER BY FakeReviews DESC, LastReportDate DESC
";
$userResult = $con->query($userQuery);

// Ban status helper
function calculateRemainingStatus($fakeCount) {
    if ($fakeCount < 10) {
        return ['text' => (10 - $fakeCount) . ' to 5-day ban', 'class' => 'bg-warning text-dark'];
    } elseif ($fakeCount < 15) {
        return ['text' => (15 - $fakeCount) . ' to 30-day ban', 'class' => 'bg-orange text-white'];
    } elseif ($fakeCount < 20) {
        return ['text' => (20 - $fakeCount) . ' to deletion', 'class' => 'bg-danger'];
    } else {
        return ['text' => 'Account deleted', 'class' => 'bg-dark'];
    }
}
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">
<head>
    <title><?= __('manage_users') ?> | BuyWise</title>
    <link rel="icon" href="../img/favicon.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="Admin.css">
    <?php include("../header.php"); ?>
</head>

<body class="admin">
    <div class="db-wrapper d-flex flex-column min-vh-100">
        <br><br><br>

        <!-- Breadcrumb -->
        <div class="admin-breadcrumb-wrapper">
            <div class="container">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="Dashboard.php">
                                <i class="fas fa-home me-1"></i><?= __('admin_dashboard') ?>
                            </a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">
                            <?= __('reported_comments') ?>
                        </li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="container py-4">
            <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="text-accent mb-2">
                            <i class="fas fa-users fa-2x"></i>
                        </div>
                        <h4 class="card-title text-accent mb-1">
                            <?= number_format($stats['total_reported_users'] ?? 0) ?>
                        </h4>
                        <p class="card-text mb-0"><?= __('total_reported_users') ?></p>
                    </div>
                </div>
            </div>

            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="text-accent mb-2">
                            <i class="fas fa-trash fa-2x"></i>
                        </div>
                        <h4 class="card-title text-accent mb-1">
                            <?= number_format($stats['users_ready_for_deletion'] ?? 0) ?>
                        </h4>
                        <p class="card-text mb-0"><?= __('ready_for_deletion') ?></p>
                    </div>
                </div>
            </div>

            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="text-accent mb-2">
                            <i class="fas fa-ban fa-2x"></i>
                        </div>
                        <h4 class="card-title text-accent mb-1">
                            <?= number_format(($stats['users_ready_for_30day_ban'] ?? 0) + ($stats['users_ready_for_5day_ban'] ?? 0)) ?>
                        </h4>
                        <p class="card-text mb-0"><?= __('ready_for_ban') ?></p>
                    </div>
                </div>
            </div>

            <div class="col-md-3 col-sm-6 mb-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <div class="text-accent mb-2">
                            <i class="fas fa-chart-line fa-2x"></i>
                        </div>
                        <h4 class="card-title text-accent mb-1">
                            <?= number_format($stats['avg_fake_reviews_per_user'] ?? 0, 1) ?>
                        </h4>
                        <p class="card-text mb-0"><?= __('avg_fake_reviews') ?></p>
                    </div>
                </div>
            </div>
        </div>

            <!-- Users Table -->
            <div class="admin-card mb-5">
            <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-comments me-2"></i>
                        <?= __('reported_users_management') ?>
                    </h5>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 5%;">#</th>
                                <th style="width: 15%;"><?= __('user') ?></th>
                                <th style="width: 10%;"><?= __('fake_reviews') ?></th>
                                <th style="width: 10%;"><?= __('real_reviews') ?></th>
                                <th style="width: 10%;"><?= __('unverified_reviews') ?></th>
                                <th style="width: 10%;"><?= __('total_reviews') ?></th>
                                <th style="width: 15%;"><?= __('last_reported') ?></th>
                                <th style="width: 15%;"><?= __('status') ?></th>
                                <th style="width: 25%;"><?= __('actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($userResult->num_rows === 0): ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                        <?= __('no_reported_comments') ?>
                                    </td>
                                </tr>
                            <?php else: 
                                $i = 1;
                                while ($row = $userResult->fetch_assoc()):
                                    $status = calculateRemainingStatus($row['FakeReviews']);
                            ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-user-circle me-2 text-muted"></i>
                                            <strong><?= htmlspecialchars($row['UserName']) ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-danger rounded-pill">
                                            <?= $row['FakeReviews'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-success rounded-pill">
                                            <?= $row['RealReviews'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-warning rounded-pill">
                                            <?= $row['UnverifiedReviews'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary rounded-pill">
                                            <?= $row['TotalReviews'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= date('M j, Y', strtotime($row['LastReportDate'])) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge <?= $status['class'] ?> rounded-pill">
                                            <?= $status['text'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex justify-content-center gap-2">
                                            <!-- View Button -->
                                            <button type="button"
                                                    class="btn btn-outline-primary btn-sm rounded-circle"
                                                    title="<?= __('view') ?>"
                                                    onclick="window.location.href='FakeReviewsByUser.php?UserID=<?= $row['UserID'] ?>'">
                                                <i class="fas fa-eye"></i>
                                            </button>

                                            <!-- Ban Button -->
                                            <button type="button"
                                                    class="btn btn-outline-danger btn-sm rounded-circle"
                                                    title="<?= __('ban') ?>"
                                                    onclick="banUser(<?= $row['UserID'] ?>)">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function banUser(userId) {
            if (confirm("<?= __('confirm_delete_user') ?>")) {
                // Show loading state
                const button = event.target.closest('button');
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                button.disabled = true;

                $.post("", { BanUserID: userId })
                    .done(function(response) {
                        if (response == 1) {
                            alert("<?= __('user_status_updated') ?>");
                            location.reload();
                        } else {
                            alert("<?= __('update_failed') ?>");
                            // Restore button state
                            button.innerHTML = originalText;
                            button.disabled = false;
                        }
                    })
                    .fail(function() {
                        alert("<?= __('update_failed') ?>");
                        // Restore button state
                        button.innerHTML = originalText;
                        button.disabled = false;
                    });
            }
        }

        // Add confirmation tooltips
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Bootstrap tooltips if available
            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            }
        });
    </script>
</body>
</html>