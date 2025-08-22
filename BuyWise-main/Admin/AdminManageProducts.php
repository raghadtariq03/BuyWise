<?php
require_once '../config.php';

// Check admin access
if (!isset($_SESSION['type']) || $_SESSION['type'] != 1 || !isset($_SESSION['UserID'])) {
    header("Location: ../login.php");
    exit();
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update product status
    if (isset($_POST['ProductID'], $_POST['ProductStatus'])) {
        $ProductID = intval($_POST['ProductID']);
        $ProductStatus = intval($_POST['ProductStatus']);

        $stmt = $con->prepare("UPDATE products SET ProductStatus = ? WHERE ProductID = ?");
        $stmt->bind_param("ii", $ProductStatus, $ProductID);
        $stmt->execute();

        $stmt = $con->prepare("SELECT ProductID FROM products WHERE ProductID = ? AND ProductStatus = ?");
        $stmt->bind_param("ii", $ProductID, $ProductStatus);
        $stmt->execute();
        $stmt->store_result();
        echo ($stmt->num_rows > 0) ? 1 : 2;
        exit;
    }

    // Delete product
    if (isset($_POST['ProductID']) && !isset($_POST['ProductStatus'])) {
        $ProductID = intval($_POST['ProductID']);

        $stmt = $con->prepare("DELETE FROM products WHERE ProductID = ?");
        $stmt->bind_param("i", $ProductID);
        $stmt->execute();

        $stmt = $con->prepare("SELECT ProductID FROM products WHERE ProductID = ?");
        $stmt->bind_param("i", $ProductID);
        $stmt->execute();
        $stmt->store_result();
        echo ($stmt->num_rows > 0) ? 2 : 1;
        exit;
    }
}

// Get category name
$selectedCategoryID = isset($_GET['category']) ? intval($_GET['category']) : null;
$categoryName = '';

if ($selectedCategoryID) {
    $nameField = $lang === 'ar' ? 'CategoryName_ar' : 'CategoryName_en';
    $stmt = $con->prepare("SELECT `$nameField` AS CategoryName FROM categories WHERE CategoryID = ?");
    $stmt->bind_param("i", $selectedCategoryID);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $categoryName = $row['CategoryName'];
    }
}

// Get product stats
$statusCounts = [
    'Approved' => 0,
    'Pending' => 0,
    'Deactivated' => 0
];

$result = $con->query("SELECT ProductStatus, COUNT(*) AS Count FROM products GROUP BY ProductStatus");
while ($row = $result->fetch_assoc()) {
    switch ((int)$row['ProductStatus']) {
        case 1:
            $statusCounts['Approved'] = $row['Count'];
            break;
        case 0:
            $statusCounts['Pending'] = $row['Count'];
            break;
        default:
            $statusCounts['Deactivated'] += $row['Count'];
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">

<head>
    <title><?= __('manage_products') ?> | BuyWise</title>
    <link rel="icon" href="../img/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="Admin.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <?php include("../header.php"); ?>
</head>

<body class="admin">

    <!-- Session popup message -->
    <?php if ($showPopup): ?>
        <div class="popup show" id="popup">
            <div class="popup-content">
                <p><?= htmlspecialchars($popupMessage) ?></p>
                <button class="okbutton" onclick="closePopup()"><?= __('ok') ?></button>
            </div>
        </div>
        <script>
            // Close popup function
            function closePopup() {
                document.getElementById('popup')?.classList.remove('show');
            }
            
            // Auto-close popup after 3.5 seconds
            window.addEventListener('DOMContentLoaded', function () {
                setTimeout(() => {
                    closePopup();
                }, 3500);
            });
        </script>
    <?php endif; ?>

    <!-- JavaScript popup for confirmations -->
    <div class="popup" id="popup-js">
        <div class="popup-content">
            <p id="popup-message"></p>
            <div class="d-flex justify-content-center gap-3 mt-3">
                <button class="okbutton" id="popup-ok"><?= __('ok') ?></button>
                <button class="cancelbutton btn btn-secondary btn-sm" id="popup-cancel" style="display: none;">
                    <?= __('cancel') ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Breadcrumb navigation -->
    <div class="admin-breadcrumb-wrapper">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="Dashboard.php"><i class="fas fa-home me-1"></i> <?= __('admin_dashboard') ?></a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        <i class="fas fa-box"></i> <?= __('manage_products') ?>
                    </li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="container py-5">
        <!-- Product statistics cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card border-accent">
                    <div class="card-body text-center">
                        <i class="fas fa-check-circle fa-2x mb-2 text-accent"></i>
                        <h4 class="fw-bold text-accent"><?= $statusCounts['Approved'] ?></h4>
                        <p class="mb-0"><?= __('approved_products') ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-accent">
                    <div class="card-body text-center">
                        <i class="fas fa-clock fa-2x mb-2 text-accent"></i>
                        <h4 class="fw-bold text-accent"><?= $statusCounts['Pending'] ?></h4>
                        <p class="mb-0"><?= __('pending_products') ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-accent">
                    <div class="card-body text-center">
                        <i class="fas fa-ban fa-2x mb-2 text-accent"></i>
                        <h4 class="fw-bold text-accent"><?= $statusCounts['Deactivated'] ?></h4>
                        <p class="mb-0"><?= __('deactivated_products') ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Category filter section -->
        <div class="admin-card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-filter me-2"></i><?= __('filter_by_category') ?></h5>
            </div>
            <div class="card-body">
                <form>
                    <div class="row">
                        <div class="col-md-12">
                            <select class="form-select" id="categorySelector" name="category" onchange="this.form.submit()">
                                <option value=""><?= __('select_category') ?></option>
                                <?php
                                // Load categories for dropdown
                                $nameField = $lang === 'ar' ? 'CategoryName_ar' : 'CategoryName_en';
                                $cats = $con->query("SELECT CategoryID, `$nameField` AS CategoryName FROM categories ORDER BY `$nameField`");
                                while ($cat = $cats->fetch_assoc()) {
                                    $selected = ($cat['CategoryID'] == $selectedCategoryID) ? 'selected' : '';
                                    echo "<option value='{$cat['CategoryID']}' $selected>{$cat['CategoryName']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Products table -->
        <div class="admin-card">
            <h5 class="mb-3">
                <i class="fas fa-boxes me-2"></i><?= __('products') ?><?= $categoryName ? " - $categoryName" : '' ?>
            </h5>
            <div class="table-responsive">
                <?php
                if (!$selectedCategoryID) {
                    echo '<div class="alert alert-warning text-center">' . __('please_select_category') . '</div>';
                } else {
                    // Check if category has products
                    $res = $con->query("SELECT COUNT(*) as total FROM products WHERE CategoryID = $selectedCategoryID");
                    $total = $res->fetch_assoc()['total'];

                    if ($total == 0) {
                        echo '<div class="alert alert-info text-center">' . __('no_products_found') . '</div>';
                    } else {
                        // Load products with user information
                        $res = $con->query("SELECT p.*, u.UserName FROM products p LEFT JOIN users u ON p.UserID = u.UserID WHERE p.CategoryID = $selectedCategoryID ORDER BY p.ProductName");

                        echo '<div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle text-center">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 4%;">#</th>
                                    <th style="width: 10%;">' . __('image') . '</th>
                                    <th style="width: 14%;">' . __('name') . '</th>
                                    <th style="width: 20%;">' . __('description') . '</th>
                                    <th style="width: 15%;">' . __('IsFake') . '</th>
                                    <th style="width: 14%;">' . __('added_by') . '</th>
                                    <th style="width: 20%;">' . __('actions') . '</th>
                                </tr>
                            </thead>
                            <tbody>';

                            $i = 1;
                            while ($p = $res->fetch_assoc()) {
                                $addedBy = $p['UserID'] == 0 ? __('admin') : ($p['UserName'] ?? __('unknown'));

                                $descFull = htmlspecialchars($p['ProductDescription']);
                                $shortDesc = strlen($p['ProductDescription']) > 70 ? 
                                    htmlspecialchars(substr($p['ProductDescription'], 0, 70)) . '...' : $descFull;

                                $filename = $p['ProductImage'];
                                $serverPath = "C:/xampp/htdocs/product1/uploads/products/";
                                $urlPath = "/product1/uploads/products/";
                                $imgSrc = (!empty($filename) && file_exists($serverPath . $filename))
                                    ? $urlPath . $filename
                                    : $urlPath . "default-product.png";

                                $isFakeBadge = $p['IsFake'] === null
                                    ? '<span class="badge bg-warning text-dark">' . __('Unverified') . '</span>'
                                    : ($p['IsFake'] == 1
                                        ? '<span class="badge bg-danger">' . __('Fake') . '</span>'
                                        : '<span class="badge bg-success">' . __('Real') . '</span>');
                                        echo "<tr>
                                            <td>{$i}</td>
                                            <td><img src='" . htmlspecialchars($imgSrc) . "' class='product-thumbnail' alt='" . htmlspecialchars($p['ProductName']) . "'></td>
                                            <td>" . htmlspecialchars($p['ProductName']) . "</td>
                                            <td>{$shortDesc}</td>
                                            <td>{$isFakeBadge}</td>
                                            <td>{$addedBy}</td>
                                            <td>
                                                <div class='d-flex justify-content-center gap-2 flex-wrap'>

                                                    <!-- View button -->
                                                    <button type='button'
                                                        class='btn btn-sm btn-outline-info rounded-circle'
                                                        title='" . __('view') . "'
                                                        onclick='showDescription(`{$descFull}`)'>
                                                        <i class='fas fa-eye'></i>
                                                    </button>

                                                    <!-- Status toggle -->
                                                    <button type='button'
                                                        class='btn btn-sm " . ($p['ProductStatus'] ? "btn-outline-warning" : "btn-outline-success") . " rounded-circle'
                                                        title='" . ($p['ProductStatus'] ? __('deactivate') : __('activate')) . "'
                                                        onclick='changeProductStatus(" . $p['ProductID'] . ", " . $p['ProductStatus'] . ")'>
                                                        <i class='fas fa-power-off'></i>
                                                    </button>

                                                    <!-- Delete button -->
                                                    <button type='button'
                                                        class='btn btn-sm btn-outline-danger rounded-circle'
                                                        title='" . __('delete') . "'
                                                        onclick='deleteProduct(" . $p['ProductID'] . ")'>
                                                        <i class='fas fa-trash'></i>
                                                    </button>

                                                </div>
                                            </td>
                                        </tr>";
                                $i++;
                            }

                            echo '</tbody></table></div>';
                    }
                }
                ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer fixed-footer mt-auto py-3">
        <div class="container text-center">
            <p class="mb-0 text-light">&copy; <?= date('Y') ?> <a href="#" class="text-light">BuyWise</a>.
                <?= __('all_rights_reserved') ?></p>
        </div>
    </footer>

<script>
    let pendingDeleteProductId = null;

    // Toggle product status (activate/deactivate)
    function changeProductStatus(productId, currentStatus) {
        const newStatus = currentStatus === 1 ? 0 : 1;
        $.post("", { ProductID: productId, ProductStatus: newStatus }, function (res) {
            showPopupMessage(res == 1 ? "<?= __('status_updated') ?>" : "<?= __('update_failed') ?>");
            setTimeout(() => location.reload(), 1200);
        });
    }

    // Ask for delete confirmation
    function deleteProduct(productId) {
        pendingDeleteProductId = productId;
        showPopupMessage("<?= __('confirm_delete_product') ?>", true);
    }

    // Show popup message (info or confirmation)
    function showPopupMessage(message, isConfirm = false) {
        const popup = document.getElementById("popup-js");
        const popupMessage = document.getElementById("popup-message");
        const okButton = document.getElementById("popup-ok");
        const cancelButton = document.getElementById("popup-cancel");

        popupMessage.textContent = message;
        popup.classList.add("show");
        cancelButton.style.display = isConfirm ? "inline-block" : "none";

        // OK button handler
        okButton.onclick = function () {
            popup.classList.remove("show");

            if (isConfirm && pendingDeleteProductId !== null) {
                $.post("", { ProductID: pendingDeleteProductId }, function (res) {
                    showPopupMessage(res == 1 ? "<?= __('product_deleted') ?>" : "<?= __('delete_failed') ?>");
                    pendingDeleteProductId = null;
                    setTimeout(() => location.reload(), 1200);
                });
            }
        };

        // Cancel button handler
        cancelButton.onclick = function () {
            popup.classList.remove("show");
            pendingDeleteProductId = null;
        };
    }

    // Show full description in a styled popup
    function showDescription(description) {
        if (document.querySelector(".popup.show")) return;

        const popup = document.createElement("div");
        popup.className = "popup show";

        popup.innerHTML = `
            <div class="popup-content" style="padding: 20px; text-align: start;">
                <p style="white-space: pre-wrap; word-break: break-word; font-size: 15px;">${description}</p>
                <div class="text-center mt-3">
                    <button class="okbutton" onclick="this.closest('.popup').remove()"><?= __('ok') ?></button>
                </div>
            </div>
        `;

        document.body.appendChild(popup);
    }
</script>
</body>

</html>