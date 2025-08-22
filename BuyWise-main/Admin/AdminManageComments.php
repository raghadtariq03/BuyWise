<?php
@session_start();
require_once "../config.php";

// Admin access restriction
if (!isset($_SESSION['type']) || $_SESSION['type'] != 1 || !isset($_SESSION['UserID'])) {
    header("Location: ../login.php");
    exit();
}

// Filter parameters
$categoryFilter = isset($_GET['CategoryID']) ? intval($_GET['CategoryID']) : 0;
$productNameSearch = trim($_GET['ProductName'] ?? '');

// AJAX: Update comment status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['CommentID'], $_POST['CommentStatus'])) {
    $stmt = $con->prepare("UPDATE comments SET CommentStatus = ? WHERE CommentID = ?");
    $stmt->bind_param("ii", $_POST['CommentStatus'], $_POST['CommentID']);
    echo $stmt->execute() ? 1 : 0;
    $stmt->close();
    exit;
}

// AJAX: Delete comment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['DeleteCommentID'])) {
    $commentID = intval($_POST['DeleteCommentID']);
    $stmt = $con->prepare("DELETE FROM comments WHERE CommentID = ?");
    $stmt->bind_param("i", $commentID);
    $success = $stmt->execute();
    $stmt->close();
    echo $success ? 1 : 0;
    exit;
}

// AJAX: Cancel comment report
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['CancelReportCommentID'])) {
    $stmt = $con->prepare("DELETE FROM reported_comments WHERE CommentID = ?");
    $stmt->bind_param("i", $_POST['CancelReportCommentID']);
    echo $stmt->execute() ? 1 : 0;
    $stmt->close();
    exit;
}

// Helper: Format localized dates
function formatDateLocalized($dateStr, $lang = 'en') {
    $timestamp = strtotime($dateStr);
    $day = date('j', $timestamp);
    $year = date('Y', $timestamp);
    $monthEn = date('F', $timestamp);

    $monthsAr = [
        'January' => 'يناير', 'February' => 'فبراير', 'March' => 'مارس',
        'April' => 'أبريل', 'May' => 'مايو', 'June' => 'يونيو',
        'July' => 'يوليو', 'August' => 'أغسطس', 'September' => 'سبتمبر',
        'October' => 'أكتوبر', 'November' => 'نوفمبر', 'December' => 'ديسمبر'
    ];

    return $lang === 'ar'
        ? $monthsAr[$monthEn] . " " . $day . ", " . $year
        : $monthEn . " " . $day . ", " . $year;
}

// Helper: Truncate text
function truncateText($text, $length = 70) {
    return strlen($text) > $length ? substr($text, 0, $length) . '...' : $text;
}

// Helper: Get fake status badge
function getFakeStatusBadge($isFake) {
    if ($isFake === null) return '<span class="badge bg-warning text-dark">' . __('unverified') . '</span>';
    return $isFake == 1
        ? '<span class="badge bg-danger">' . __('fake') . '</span>'
        : '<span class="badge bg-success">' . __('real') . '</span>';
}

// Build WHERE clause for filtering
$where = "WHERE 1=1";
if ($categoryFilter > 0) {
    $where .= " AND p.CategoryID = " . intval($categoryFilter);
}
if (!empty($productNameSearch)) {
    $safeSearch = mysqli_real_escape_string($con, $productNameSearch);
    $where .= " AND p.ProductName LIKE '%$safeSearch%'";
}

// Get localized category field
$categoryNameField = $lang === 'ar' ? 'CategoryName_ar' : 'CategoryName_en';

$totalComments = $con->query("SELECT COUNT(*) AS total FROM comments")->fetch_assoc()['total'];
$approvedComments = $con->query("SELECT COUNT(*) AS active FROM comments WHERE CommentStatus = 1")->fetch_assoc()['active'];
$reportedCommentsCount = $con->query("SELECT COUNT(DISTINCT CommentID) AS total FROM reported_comments")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">
<head>
    <meta charset="UTF-8">
    <title><?= __('admin_comments') ?> | BuyWise</title>
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

    <!-- Session Popup -->
    <?php if ($showPopup): ?>
    <div class="popup show" id="popup">
        <div class="popup-content">
            <p><?= htmlspecialchars($popupMessage) ?></p>
            <button class="okbutton btn btn-primary btn-sm" onclick="closePopup()"><?= __('ok') ?></button>
        </div>
    </div>
    <script>
        function closePopup() {
            document.getElementById('popup')?.classList.remove('show');
        }
        setTimeout(closePopup, 3500);
    </script>
    <?php endif; ?>

    <!-- Dynamic Popup -->
    <div class="popup" id="popup-js">
        <div class="popup-content">
            <p id="popup-message" style="white-space: pre-wrap; word-break: break-word;"></p>
            <div class="d-flex justify-content-center gap-3 mt-3">
                <button class="okbutton btn btn-primary btn-sm" id="popup-ok"><?= __('ok') ?></button>
                <button class="cancelbutton btn btn-secondary btn-sm" id="popup-cancel" style="display: none;"><?= __('cancel') ?></button>
            </div>
        </div>
    </div>

    <!-- Breadcrumb -->
    <div class="admin-breadcrumb-wrapper">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="Dashboard.php"><i class="fas fa-home me-1"></i><?= __('admin_dashboard') ?></a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        <i class="fas fa-comments me-1"></i><?= __('manage_comments') ?>
                    </li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="container py-5">

    <!-- Comment Statistics Overview -->
    <div class="row mb-4 text-center">
        <div class="col-md-4">
            <div class="card border-accent shadow-sm rounded-4">
                <div class="card-body py-4">
                    <i class="fas fa-comment-dots fa-2x mb-2 text-accent"></i>
                    <h4 class="fw-bold text-accent"><?= $totalComments ?? 0 ?></h4>
                    <p class="mb-0"><?= __('total_comments') ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-accent shadow-sm rounded-4">
                <div class="card-body py-4">
                    <i class="fas fa-eye fa-2x mb-2 text-accent"></i>
                    <h4 class="fw-bold text-accent"><?= $approvedComments ?? 0 ?></h4>
                    <p class="mb-0"><?= __('active_comments') ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-accent shadow-sm rounded-4">
                <div class="card-body py-4">
                    <i class="fas fa-flag fa-2x mb-2 text-accent"></i>
                    <h4 class="fw-bold text-accent"><?= $reportedCommentsCount ?? 0 ?></h4>
                    <p class="mb-0"><?= __('reported_comments') ?></p>
                </div>
            </div>
        </div>
    </div>

        <!-- Filter Section -->
        <div class="admin-card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-filter me-2"></i><?= __('filter_comments') ?></h5>
            </div>
            <div class="card-body">
                <form method="get" class="row g-3">
                    <div class="col-md-4">
                        <label for="CategoryID" class="form-label"><?= __('category') ?></label>
                        <select class="form-select" id="CategoryID" name="CategoryID">
                            <option value=""><?= __('all_categories') ?></option>
                            <?php
                            $categories = $con->query("SELECT CategoryID, $categoryNameField AS CategoryName FROM categories ORDER BY $categoryNameField");
                            while ($cat = $categories->fetch_assoc()) {
                                $selected = $categoryFilter == $cat['CategoryID'] ? 'selected' : '';
                                echo "<option value='{$cat['CategoryID']}' $selected>" . htmlspecialchars($cat['CategoryName']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="ProductName" class="form-label"><?= __('product') ?></label>
                        <div class="position-relative">
                            <input type="text" id="ProductName" name="ProductName" class="form-control" 
                                   placeholder="<?= __('search_product') ?>" autocomplete="off" 
                                   value="<?= htmlspecialchars($productNameSearch) ?>">
                            <div id="productSuggestions" class="list-group position-absolute w-100 shadow" 
                                 style="z-index: 10; display: none;"></div>
                        </div>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-accent w-100">
                            <i class="fas fa-filter me-2"></i><?= __('apply') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- All Comments Section -->
        <div class="admin-card mb-5">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-comments me-2"></i><?= __('all_comments') ?></h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <?php
                    $comments = $con->query("SELECT c.*, p.ProductName, u.UserName 
                                           FROM comments c
                                           JOIN products p ON c.ProductID = p.ProductID
                                           LEFT JOIN users u ON c.UserID = u.UserID
                                           $where 
                                           ORDER BY c.CommentDate DESC");

                    if ($comments->num_rows === 0): ?>
                        <div class="alert alert-info text-center">
                            <i class="fas fa-info-circle me-2"></i><?= __('no_comments_found') ?>
                        </div>
                    <?php else: ?>
                        <table class="table table-bordered table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th style="width: 5%;">#</th>
                                    <th style="width: 18%;"><?= __('product') ?></th>
                                    <th><?= __('comment') ?></th>
                                    <th style="width: 10%;"><?= __('status') ?></th>
                                    <th style="width: 12%;"><?= __('user') ?></th>
                                    <th style="width: 12%;"><?= __('date') ?></th>
                                    <th style="width: 20%;"><?= __('actions') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $i = 1;
                                while ($comment = $comments->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $i ?></td>
                                    <td><?= htmlspecialchars($comment['ProductName']) ?></td>
                                    <td>
                                        <span title="<?= htmlspecialchars($comment['CommentText']) ?>">
                                            <?= htmlspecialchars(truncateText($comment['CommentText'])) ?>
                                        </span>
                                        <?php if (strlen($comment['CommentText']) > 70): ?>
                                        <button class="btn btn-sm btn-outline-secondary ms-1" 
                                                onclick="showFullComment(<?= htmlspecialchars(json_encode($comment['CommentText'])) ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= getFakeStatusBadge($comment['IsFake']) ?></td>
                                    <td><?= htmlspecialchars($comment['UserName'] ?? __('guest')) ?></td>
                                    <td><?= formatDateLocalized($comment['CommentDate'], $lang) ?></td>
                                    <td>
                                    <div class="d-flex justify-content-center gap-2">
                                        <?php if ($comment['CommentStatus'] == 1): ?>
                                        <button class="btn btn-sm btn-outline-warning" 
                                                title="<?= __('deactivate') ?>"
                                                onclick="changeCommentStatus(<?= $comment['CommentID'] ?>, 0)">
                                            <i class="fas fa-eye-slash"></i>
                                        </button>
                                        <?php else: ?>
                                        <button class="btn btn-sm btn-outline-success" 
                                                title="<?= __('activate') ?>"
                                                onclick="changeCommentStatus(<?= $comment['CommentID'] ?>, 1)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php endif; ?>

                                        <button class="btn btn-sm btn-outline-danger" 
                                                title="<?= __('delete') ?>"
                                                onclick="deleteComment(<?= $comment['CommentID'] ?>)">
                                        <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                    </td>
                                </tr>
                                <?php $i++; endwhile; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Reported Comments Section -->
        <div class="admin-card">
            <div class="card-header">
                <h5 class="mb-0 text-danger"><i class="fas fa-flag me-2"></i><?= __('reported_comments') ?></h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <?php
                    $reportedComments = $con->query("SELECT rc.*, c.CommentText, c.CommentDate, u.UserName, p.ProductName
                                                   FROM reported_comments rc
                                                   JOIN comments c ON rc.CommentID = c.CommentID
                                                   JOIN products p ON c.ProductID = p.ProductID
                                                   LEFT JOIN users u ON rc.UserID = u.UserID
                                                   ORDER BY rc.ReportDate DESC");

                    if ($reportedComments->num_rows === 0): ?>
                        <div class="alert alert-info text-center">
                            <i class="fas fa-info-circle me-2"></i><?= __('no_reported_comments') ?>
                        </div>
                    <?php else: ?>
                        <table class="table table-bordered table-hover">
                            <thead class="table-danger">
                                <tr>
                                    <th style="width: 5%;">#</th>
                                    <th style="width: 20%;"><?= __('product') ?></th>
                                    <th><?= __('comment') ?></th>
                                    <th style="width: 12%;"><?= __('reported_by') ?></th>
                                    <th style="width: 12%;"><?= __('report_date') ?></th>
                                    <th style="width: 20%;"><?= __('actions') ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $i = 1;
                                while ($report = $reportedComments->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $i ?></td>
                                    <td><?= htmlspecialchars($report['ProductName']) ?></td>
                                    <td>
                                        <span title="<?= htmlspecialchars($report['CommentText']) ?>">
                                            <?= htmlspecialchars(truncateText($report['CommentText'])) ?>
                                        </span>
                                        <button class="btn btn-sm btn-outline-secondary ms-1" 
                                                onclick="showFullComment(<?= htmlspecialchars(json_encode($report['CommentText'])) ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                    <td><?= htmlspecialchars($report['UserName'] ?? __('guest')) ?></td>
                                    <td><?= formatDateLocalized($report['ReportDate'], $lang) ?></td>
                                    <td>
                                    <div class="d-flex justify-content-center gap-2">
                                        <button class="btn btn-sm btn-outline-danger" 
                                                title="<?= __('delete') ?>"
                                                onclick="deleteComment(<?= $report['CommentID'] ?>)">
                                        <i class="fas fa-trash"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary" 
                                                title="<?= __('cancel_report') ?>"
                                                onclick="cancelReport(<?= $report['CommentID'] ?>)">
                                        <i class="fas fa-times-circle"></i>
                                        </button>
                                    </div>
                                    </td>
                                </tr>
                                <?php $i++; endwhile; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Full Comment Modal -->
    <div class="modal fade" id="fullCommentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= __('full_comment') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="white-space: pre-wrap; word-break: break-word;">
                    <p id="fullCommentText"></p>
                </div>
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

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // Product search autocomplete
        $('#ProductName').on('input', function() {
            const query = $(this).val();
            if (query.length < 2) {
                $('#productSuggestions').hide();
                return;
            }
            
            $.get('ajax_search_products.php', { q: query })
                .done(function(data) {
                    try {
                        const suggestions = JSON.parse(data);
                        const $suggestBox = $('#productSuggestions');
                        $suggestBox.empty();
                        
                        if (suggestions.length === 0) {
                            $suggestBox.hide();
                            return;
                        }
                        
                        suggestions.forEach(name => {
                            $suggestBox.append(`<button type="button" class="list-group-item list-group-item-action">${name}</button>`);
                        });
                        $suggestBox.show();
                    } catch (e) {
                        console.error('Error parsing suggestions:', e);
                    }
                })
                .fail(function() {
                    $('#productSuggestions').hide();
                });
        });

        // Handle suggestion clicks
        $(document).on('click', '#productSuggestions button', function() {
            $('#ProductName').val($(this).text());
            $('#productSuggestions').hide();
        });

        // Hide suggestions on outside click
        $(document).click(function(e) {
            if (!$(e.target).closest('#ProductName, #productSuggestions').length) {
                $('#productSuggestions').hide();
            }
        });

        // Category filter change
        $('#CategoryID').on('change', function() {
            const categoryId = $(this).val();
            const url = new URL(window.location);
            if (categoryId) {
                url.searchParams.set('CategoryID', categoryId);
            } else {
                url.searchParams.delete('CategoryID');
            }
            window.location.href = url.toString();
        });
    });

    // Change comment status
    function changeCommentStatus(id, status) {
        $.post('', { CommentID: id, CommentStatus: status })
            .done(function(response) {
                const message = response == 1 ? "<?= __('comment_updated') ?>" : "<?= __('update_failed') ?>";
                showPopupMessage(message);
                if (response == 1) {
                    setTimeout(() => location.reload(), 1200);
                }
            })
            .fail(function() {
                showPopupMessage("<?= __('update_failed') ?>");
            });
    }

    // Delete comment with confirmation
    function deleteComment(id) {
        showConfirmDialog("<?= __('confirm_delete_comment') ?>", function() {
            $.post('', { DeleteCommentID: id })
                .done(function(response) {
                    const message = response == 1 ? "<?= __('comment_deleted') ?>" : "<?= __('delete_failed') ?>";
                    showPopupMessage(message);
                    if (response == 1) {
                        setTimeout(() => location.reload(), 1200);
                    }
                })
                .fail(function() {
                    showPopupMessage("<?= __('delete_failed') ?>");
                });
        });
    }

    // Cancel comment report
    function cancelReport(commentId) {
        $.post('', { CancelReportCommentID: commentId })
            .done(function(response) {
                const message = response == 1 ? "<?= __('report_cancelled') ?>" : "<?= __('cancel_failed') ?>";
                showPopupMessage(message);
                if (response == 1) {
                    setTimeout(() => location.reload(), 1200);
                }
            })
            .fail(function() {
                showPopupMessage("<?= __('cancel_failed') ?>");
            });
    }

    // Show simple popup message
    function showPopupMessage(message) {
        const popup = document.getElementById("popup-js");
        const popupMessage = document.getElementById("popup-message");
        const cancelButton = document.getElementById("popup-cancel");
        
        popupMessage.textContent = message;
        popup.classList.add("show");
        cancelButton.style.display = "none";
        
        document.getElementById("popup-ok").onclick = () => popup.classList.remove("show");
    }

    // Show confirmation dialog
    function showConfirmDialog(message, onConfirm) {
        const popup = document.getElementById("popup-js");
        const popupMessage = document.getElementById("popup-message");
        const okButton = document.getElementById("popup-ok");
        const cancelButton = document.getElementById("popup-cancel");

        popupMessage.textContent = message;
        popup.classList.add("show");
        cancelButton.style.display = "inline-block";

        okButton.onclick = () => {
            popup.classList.remove("show");
            if (typeof onConfirm === 'function') onConfirm();
        };
        
        cancelButton.onclick = () => popup.classList.remove("show");
    }

    // Show full comment in modal
    function showFullComment(text) {
        document.getElementById('fullCommentText').textContent = text;
        const modal = new bootstrap.Modal(document.getElementById('fullCommentModal'));
        modal.show();
    }
    </script>

    <!-- Dynamic JS Popup -->
<div class="popup" id="popup-js">
  <div class="popup-content">
    <p id="popup-message" style="white-space: pre-wrap; word-break: break-word;"></p>
    <div class="d-flex justify-content-center gap-3 mt-3">
      <button id="popup-ok" class="okbutton"><?= __('ok') ?></button>
      <button id="popup-cancel" class="okbutton btn-cancel" style="display: none;"><?= __('cancel') ?></button>
    </div>
  </div>
</div>

</body>
</html>