<?php
@session_start();
require_once '../config.php';

// Admin access restriction
if (!isset($_SESSION['type'], $_SESSION['UserID']) || $_SESSION['type'] != 1) {
    header("Location: ../login.php");
    exit();
}

// Company action handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirectUrl = "AdminManageCompanies.php";

    // Approve company
    if (isset($_POST['approve_company_id'])) {
        $companyID = intval($_POST['approve_company_id']);
        $stmt = $con->prepare("UPDATE companies SET Verified = 1, CompanyStatus = 1 WHERE CompanyID = ?");
        $stmt->bind_param("i", $companyID);
        $_SESSION['popup'] = $stmt->execute() ? __('company_approved') : __('action_failed');
        $stmt->close();
        header("Location: $redirectUrl");
        exit();
    }

    // Reject company (delete)
    if (isset($_POST['reject_company_id'])) {
        $companyID = intval($_POST['reject_company_id']);
        $stmt = $con->prepare("DELETE FROM companies WHERE CompanyID = ?");
        $stmt->bind_param("i", $companyID);
        $_SESSION['popup'] = $stmt->execute() ? __('company_rejected') : __('action_failed');
        $stmt->close();
        header("Location: $redirectUrl");
        exit();
    }

    // Toggle company status
    if (isset($_POST['toggle_company_status'])) {
        $companyID = intval($_POST['toggle_company_status']);
        $stmt = $con->prepare("UPDATE companies SET CompanyStatus = IF(CompanyStatus = 1, 0, 1) WHERE CompanyID = ?");
        $stmt->bind_param("i", $companyID);
        $_SESSION['popup'] = $stmt->execute() ? __('company_status_updated') : __('action_failed');
        $stmt->close();
        header("Location: $redirectUrl");
        exit();
    }

    // Delete company
    if (isset($_POST['delete_company_id'])) {
        $companyID = intval($_POST['delete_company_id']);
        $stmt = $con->prepare("DELETE FROM companies WHERE CompanyID = ?");
        $stmt->bind_param("i", $companyID);
        $_SESSION['popup'] = $stmt->execute() ? __('company_deleted') : __('action_failed');
        $stmt->close();
        header("Location: $redirectUrl");
        exit();
    }
}

// Fetch company data
$pendingCompanies = $con->query("SELECT CompanyID, CompanyName, CompanyEmail, Country, CreatedAt 
                                FROM companies 
                                WHERE Verified = 0 OR CompanyStatus = 0 
                                ORDER BY CreatedAt DESC");

$verifiedCompanies = $con->query("SELECT CompanyID, CompanyName, CompanyEmail, Country, CompanyStatus, CreatedAt 
                                 FROM companies 
                                 WHERE Verified = 1 
                                 ORDER BY CompanyName ASC");

// Helper: Format date
function formatDate($dateStr, $lang = 'en') {
    if (!$dateStr) return '-';
    $timestamp = strtotime($dateStr);
    return $lang === 'ar' 
        ? date('d/m/Y', $timestamp)
        : date('M d, Y', $timestamp);
}

// Helper: Get status badge
function getStatusBadge($status, $lang = 'en') {
    return $status == 1 
        ? '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>' . __('active') . '</span>'
        : '<span class="badge bg-warning text-dark"><i class="fas fa-pause-circle me-1"></i>' . __('inactive') . '</span>';
}
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <title><?= __('admin_companies') ?> | BuyWise</title>
    <link rel="icon" href="../img/favicon.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="Admin.css">
    <?php include("../header.php"); ?>
</head>

<body class="admin">

    <!-- Success/Error Message Popup -->
    <?php if ($showPopup): ?>
    <div class="popup show" id="messagePopup">
        <div id="popup-message"><?= htmlspecialchars($popupMessage) ?></div>
        <button class="okbutton" onclick="closeMessagePopup()"><?= __('ok') ?></button>
    </div>
    <?php endif; ?>

    <!-- Breadcrumb -->
    <div class="admin-breadcrumb-wrapper">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="Dashboard.php"><i class="fas fa-home me-1"></i><?= __('admin_dashboard') ?></a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        <i class="fas fa-building me-1"></i><?= __('admin_companies') ?>
                    </li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="container py-5">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card border-accent">
                    <div class="card-body text-center">
                        <i class="fas fa-clock fa-2x mb-2 text-accent"></i>
                        <h4 class="fw-bold text-accent"><?= $pendingCompanies->num_rows ?></h4>
                        <p class="mb-0"><?= __('pending_company_accounts') ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-accent">
                    <div class="card-body text-center">
                        <i class="fas fa-check-circle fa-2x mb-2 text-accent"></i>
                        <h4 class="fw-bold text-accent"><?= $verifiedCompanies->num_rows ?></h4>
                        <p class="mb-0"><?= __('approved_companies') ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Companies Section -->
        <div class="admin-card mb-5">
            <div class="card-header d-flex justify-content-between align-items-center bg-warning-subtle">
                <h5 class="mb-0">
                    <i class="fas fa-clock me-2 text-warning"></i><?= __('pending_company_accounts') ?>
                </h5>
                <span class="badge bg-warning text-dark"><?= $pendingCompanies->num_rows ?></span>
            </div>
            <div class="card-body">
                <?php if ($pendingCompanies->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle">
                            <thead class="table-warning">
                                <tr>
                                    <th style="width: 5%;">#</th>
                                    <th style="width: 20%;"><?= __('company_name') ?></th>
                                    <th style="width: 25%;"><?= __('company_email') ?></th>
                                    <th style="width: 15%;"><?= __('country') ?></th>
                                    <th style="width: 15%;"><?= __('joined_date') ?></th>
                                    <th style="width: 20%;"><?= __('actions') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $i = 1;
                                while ($company = $pendingCompanies->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($company['CompanyName']) ?></strong>
                                    </td>
                                    <td>
                                        <a href="mailto:<?= htmlspecialchars($company['CompanyEmail']) ?>" 
                                            class="text-decoration-none">
                                            <?= htmlspecialchars($company['CompanyEmail']) ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($company['Country'] ?? '-') ?></td>
                                    <td><?= formatDate($company['CreatedAt'], $lang) ?></td>
                                    <td>
                                    <div class="d-flex justify-content-center gap-2">
                                        <button class="btn btn-outline-success btn-sm" title="<?= __('approve') ?>"
                                                onclick="confirmApproveCompany(<?= $company['CompanyID'] ?>)">
                                        <i class="fas fa-check"></i>
                                        </button>
                                        <button class="btn btn-outline-danger btn-sm" title="<?= __('reject') ?>"
                                                onclick="confirmRejectCompany(<?= $company['CompanyID'] ?>, '<?= htmlspecialchars($company['CompanyName'], ENT_QUOTES) ?>')">
                                        <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle me-2"></i><?= __('no_pending_companies') ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Approved Companies Section -->
        <div class="admin-card">
            <div class="card-header d-flex justify-content-between align-items-center bg-success-subtle">
                <h5 class="mb-0">
                    <i class="fas fa-check-circle me-2 text-success"></i><?= __('approved_companies') ?>
                </h5>
                <span class="badge bg-success"><?= $verifiedCompanies->num_rows ?></span>
            </div>
            <div class="card-body">
                <?php if ($verifiedCompanies->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle">
                            <thead class="table-success">
                                <tr>
                                    <th style="width: 5%;">#</th>
                                    <th style="width: 20%;"><?= __('company_name') ?></th>
                                    <th style="width: 25%;"><?= __('company_email') ?></th>
                                    <th style="width: 15%;"><?= __('country') ?></th>
                                    <th style="width: 15%;"><?= __('joined_date') ?></th>
                                    <th style="width: 10%;"><?= __('actions') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $j = 1;
                                while ($company = $verifiedCompanies->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $j++ ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($company['CompanyName']) ?></strong>
                                    </td>
                                    <td>
                                        <a href="mailto:<?= htmlspecialchars($company['CompanyEmail']) ?>" 
                                            class="text-decoration-none">
                                            <?= htmlspecialchars($company['CompanyEmail']) ?>
                                        </a>
                                    </td>
                                    <td><?= htmlspecialchars($company['Country'] ?? '-') ?></td>
                                    <td><?= formatDate($company['CreatedAt'], $lang) ?></td>
                                    <td>
                                    <div class="d-flex justify-content-center gap-2">
                                        <!-- Toggle Status -->
                                        <button type="button"
                                                class="btn btn-sm <?= $company['CompanyStatus'] ? 'btn-outline-warning' : 'btn-outline-success' ?> rounded-circle"
                                                title="<?= $company['CompanyStatus'] ? __('deactivate') : __('activate') ?>"
                                                onclick="toggleCompanyStatus(<?= $company['CompanyID'] ?>, '<?= htmlspecialchars($company['CompanyName'], ENT_QUOTES) ?>', <?= $company['CompanyStatus'] ?>)">
                                        <i class="fas fa-power-off"></i>
                                        </button>

                                        <!-- Delete -->
                                        <button type="button"
                                                class="btn btn-outline-danger btn-sm rounded-circle"
                                                title="<?= __('delete') ?>"
                                                onclick="deleteCompany(<?= $company['CompanyID'] ?>, '<?= htmlspecialchars($company['CompanyName'], ENT_QUOTES) ?>')">
                                        <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning text-center">
                        <i class="fas fa-exclamation-triangle me-2"></i><?= __('no_approved_companies') ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer fixed-footer mt-auto py-3">
        <div class="container text-center">
            <p class="mb-0 text-light">
                &copy; <?= date('Y') ?> <a href="#" class="text-light">BuyWise</a>. <?= __('all_rights_reserved') ?>
            </p>
        </div>
    </footer>

    <!-- Confirmation Popup for Company Actions -->
    <div id="confirmDeletePopup" class="">
        <div id="popup-message"></div>
        <form method="post" id="confirmationForm">
            <input type="hidden" name="" id="confirmationInput">
            <div class="d-flex justify-content-center gap-3 mt-3">
                <button type="submit" class="okbutton btn-danger"><?= __('yes') ?></button>
                <button type="button" class="okbutton btn-secondary" onclick="closeConfirmationPopup()"><?= __('no') ?></button>
            </div>
        </form>
    </div>

    <script>
        // Message popup functions
        function closeMessagePopup() {
            const popup = document.getElementById('messagePopup');
            if (popup) {
                popup.classList.remove('show');
                setTimeout(() => popup.remove(), 400);
            }
        }

        // Auto-close message popup after 3.5 seconds
        <?php if ($showPopup): ?>
        setTimeout(closeMessagePopup, 3500);
        <?php endif; ?>

        // Confirmation popup functions
        function showConfirmationPopup(message, inputName, value) {
            const popup = document.getElementById('confirmDeletePopup');
            const messageEl = document.getElementById('popup-message');
            const input = document.getElementById('confirmationInput');
            
            if (popup && messageEl && input) {
                messageEl.textContent = message;
                input.name = inputName;
                input.value = value;
                popup.classList.add('show');
            }
        }

        function closeConfirmationPopup() {
            const popup = document.getElementById('confirmDeletePopup');
            const form = document.getElementById('confirmationForm');
            
            if (popup) {
                popup.classList.remove('show');
            }
            if (form) {
                form.reset();
            }
        }

        // Company action functions
        function confirmApproveCompany(companyID) {
            const message = "<?= $lang === 'ar' ? 'تأكيد الموافقة على حساب الشركة؟' : 'Confirm approving company account?' ?>";
            showConfirmationPopup(message, "approve_company_id", companyID);
        }

        function confirmRejectCompany(companyID, companyName) {
            const message = "<?= $lang === 'ar' ? 'تأكيد رفض حساب شركة' : 'Confirm rejecting company account' ?> (" + companyName + ") ؟";
            showConfirmationPopup(message, "reject_company_id", companyID);
        }

        function toggleCompanyStatus(companyID, companyName, currentStatus) {
            let message;
            if (currentStatus == 1) {
                // Deactivating
                message = "<?= $lang === 'ar' ? 'تأكيد تعطيل حساب شركة' : 'Confirm deactivating company account' ?> (" + companyName + ") ؟";
            } else {
                // Activating
                message = "<?= $lang === 'ar' ? 'تأكيد تفعيل حساب شركة' : 'Confirm activating company account' ?> (" + companyName + ") ؟";
            }
            showConfirmationPopup(message, "toggle_company_status", companyID);
        }

        function deleteCompany(companyID, companyName) {
            const message = "<?= $lang === 'ar' ? 'تأكيد حذف حساب شركة' : 'Confirm deleting company account' ?> (" + companyName + ") ؟\n\n<?= $lang === 'ar' ? 'هذا الإجراء لا يمكن التراجع عنه' : 'This action cannot be undone' ?>";
            showConfirmationPopup(message, "delete_company_id", companyID);
        }

        // Close popup when clicking outside
        document.addEventListener('click', function(e) {
            const confirmationPopup = document.getElementById('confirmDeletePopup');
            const messagePopup = document.getElementById('messagePopup');
            
            if (e.target === confirmationPopup) {
                closeConfirmationPopup();
            }
            if (e.target === messagePopup) {
                closeMessagePopup();
            }
        });

        // Auto-refresh badge every 30 seconds
        document.addEventListener('DOMContentLoaded', function () {
            if (<?= $pendingCompanies->num_rows ?> > 0) {
                setInterval(() => {
                    fetch(window.location.href)
                        .then(response => response.text())
                        .then(html => {
                            const parser = new DOMParser();
                            const doc = parser.parseFromString(html, 'text/html');
                            const newCount = doc.querySelector('.badge.bg-warning')?.textContent;
                            const currentCount = document.querySelector('.badge.bg-warning')?.textContent;

                            if (newCount && newCount !== currentCount) {
                                document.title = `(${newCount}) <?= __('manage_companies') ?> | BuyWise`;
                            }
                        })
                        .catch(console.error);
                }, 30000);
            }
        });
    </script>
</body>
</html>