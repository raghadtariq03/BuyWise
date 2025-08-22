<?php
require_once '../config.php';

// Check admin access
if (!isset($_SESSION['type'], $_SESSION['UserID']) || $_SESSION['type'] != 1) {
    header("Location: ../login.php");
    exit();
}

// Approve user product
if (isset($_POST['approve_user_product'])) {
    $productID = intval($_POST['approve_user_product']);

    // Approve product
    $con->query("UPDATE products SET ProductStatus = 1 WHERE ProductID = $productID");

    // Get product + user + category
    $stmt = $con->prepare("SELECT p.UserID, p.ProductName, u.points, u.badge, c.CategoryName_en, c.CategoryName_ar
                           FROM products p
                           JOIN users u ON p.UserID = u.UserID
                           JOIN categories c ON p.CategoryID = c.CategoryID
                           WHERE p.ProductID = ?");
    $stmt->bind_param("i", $productID);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $userID = $data['UserID'];
    $productName = $data['ProductName'];
    $currentPoints = $data['points'];
    $currentBadge = $data['badge'];
    $category = strtolower($lang === 'ar' ? $data['CategoryName_ar'] : $data['CategoryName_en']);

    // Points logic
    $pointsToAdd = ($category === 'local') ? 20 : 10;
    $newPoints = $currentPoints + $pointsToAdd;

    // Update points
    $stmt = $con->prepare("UPDATE users SET points = ? WHERE UserID = ?");
    $stmt->bind_param("ii", $newPoints, $userID);
    $stmt->execute();
    $stmt->close();

    // Update badge if needed
    $ranks = ['Normal' => 1, 'Professional' => 2, 'Expert' => 3, 'Legend' => 4];
    $newBadge = ($newPoints >= 5000) ? "Legend" : (($newPoints >= 1500) ? "Expert" : (($newPoints >= 500) ? "Professional" : "Normal"));
    if ($ranks[$newBadge] > $ranks[$currentBadge]) {
        $stmt = $con->prepare("UPDATE users SET badge = ? WHERE UserID = ?");
        $stmt->bind_param("si", $newBadge, $userID);
        $stmt->execute();
        $stmt->close();
    }

    // Notify user
    $message = sprintf(__('product_approved_message'), $productName, $pointsToAdd);
    $link = "/product1/Products.php?ProductID=$productID";
    $stmt = $con->prepare("INSERT INTO notifications (sender_id, recipient_id, recipient_type, message, link, is_read)
                           VALUES (NULL, ?, 'user', ?, ?, 0)");
    $stmt->bind_param("iss", $userID, $message, $link);
    $stmt->execute();
    $stmt->close();

    $_SESSION['popup'] = __('product_approved');
    header("Location: AdminPendingProducts.php");
    exit();
}

// Reject user product
if (isset($_POST['reject_user_product'])) {
    $productID = intval($_POST['reject_user_product']);
    $con->query("DELETE FROM products WHERE ProductID = $productID");
    $_SESSION['popup'] = __('product_rejected');
    header("Location: AdminPendingProducts.php");
    exit();
}

// Pending products list
$productResult = $con->query("
    SELECT p.ProductID, p.ProductName, p.ProductImage, p.ProductDescription, p.ProductRating, p.IsFake ,
           c.CategoryName_en, c.CategoryName_ar, u.UserName
    FROM products p
    JOIN users u ON p.UserID = u.UserID
    JOIN categories c ON p.CategoryID = c.CategoryID
    WHERE p.ProductStatus = 0 AND (p.IsFake = 0 OR p.IsFake IS NULL)
");

// Selected category
$selectedCategoryID = isset($_GET['category']) ? intval($_GET['category']) : null;
$categoryName = '';
if ($selectedCategoryID) {
    $nameField = $lang === 'ar' ? 'CategoryName_ar' : 'CategoryName_en';
    $result = $con->query("SELECT `$nameField` AS CategoryName FROM categories WHERE CategoryID = $selectedCategoryID");
    if ($row = $result->fetch_assoc()) {
        $categoryName = $row['CategoryName'];
    }
}

// Product stats
$statusCounts = [
    'Approved' => 0,
    'Pending' => 0,
    'Deactivated' => 0
];

$res = $con->query("SELECT ProductStatus, COUNT(*) AS Count
FROM products
WHERE (IsFake = 0 OR IsFake IS NULL)
GROUP BY ProductStatus");
while ($row = $res->fetch_assoc()) {
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
    <title><?= __('pending_products') ?> | BuyWise</title>
    <link rel="icon" href="../img/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="Admin.css">
    <?php include("../header.php"); ?>
</head>

<body class="admin <?= $showPopup ? 'active-popup' : '' ?>">

    <!-- Breadcrumb navigation -->
    <div class="admin-breadcrumb-wrapper">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="Dashboard.php"><i class="fas fa-home me-1"></i> <?= __('admin_dashboard') ?></a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        <i class="fas fa-box"></i> <?= __('pending_user_products') ?>
                    </li>
                </ol>
            </nav>
        </div>
    </div>

    <!-- Popup for confirmations -->
    <div class="popup-overlay" id="popup-overlay"></div>
    <div class="popup" id="popup">
        <div class="popup-content">
            <p id="popup-message"></p>
            <div class="d-flex justify-content-center gap-3 mt-3">
                <button id="popup-ok" class="okbutton">OK</button>
                <button id="popup-cancel" class="cancelbutton btn btn-secondary btn-sm">Cancel</button>
            </div>
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

        <div class="admin-card">
            <h5 class="mb-3"><i class="fas fa-box me-2"></i> <?= __('pending_user_products') ?></h5>

            <?php if ($productResult->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle text-center shadow-sm" style="border-radius: 10px; overflow: hidden;">
                        <thead class="text-white" style="background-color: #006d77;">
                            <tr>
                                <th>#</th>
                                <th><?= __('product_name') ?></th>
                                <th><?= __('category') ?></th>
                                <th><?= __('posted_by') ?></th>
                                <th><?= __('actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $i = 1;
                            while ($p = $productResult->fetch_assoc()):
                                // Get additional images for this product
                                $p['AdditionalImages'] = [];
                                $imageQuery = $con->prepare("SELECT ImageName FROM product_images WHERE ProductID = ?");
                                $imageQuery->bind_param("i", $p['ProductID']);
                                $imageQuery->execute();
                                $imageResult = $imageQuery->get_result();
                                while ($img = $imageResult->fetch_assoc()) {
                                    $p['AdditionalImages'][] = $img['ImageName'];
                                }
                                $imageQuery->close();
                            ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td><?= htmlspecialchars($p['ProductName']) ?></td>
                                    <td><?= htmlspecialchars($lang === 'ar' ? $p['CategoryName_ar'] : $p['CategoryName_en']) ?></td>
                                    <td><?= htmlspecialchars($p['UserName']) ?></td>
                                    <td>
                                        <button class="btn btn-sm w-100 text-white" style="background-color: #e29578;"
                                            onclick="showProductDetails(<?= htmlspecialchars(json_encode($p), ENT_QUOTES) ?>)">
                                            <i class="fas fa-eye me-1"></i> <?= __('view') ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info text-center"><?= __('no_pending_products') ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Product details modal -->
    <div class="modal fade" id="productModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="productModalLabel"><?= __('product_details') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="modalContent"></div>
                <div class="modal-footer">
                    <!-- Approve form -->
                    <form method="post">
                        <input type="hidden" id="approveID" name="approve_user_product">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check"></i> <?= __('approve') ?>
                        </button>
                    </form>
                    <!-- Reject form -->
                    <form method="post" id="rejectForm">
                        <input type="hidden" id="rejectID" name="reject_user_product">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times"></i> <?= __('reject') ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Fullscreen image viewer -->
    <div id="fullscreenImageViewer" class="fullscreen-viewer"
        style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background-color:rgba(0,0,0,0.8); z-index:9999; justify-content:center; align-items:center;">
        <button id="closeFullscreenBtn"
            style="position:absolute; top:20px; right:30px; font-size:1.5rem; background:none; border:none; color:white; z-index:10000; cursor:pointer;">
            &times;
        </button>
        <img id="fullscreenImage" src=""
            style="max-width:90%; max-height:90%; border:5px solid white; border-radius:8px;">
    </div>

    <!-- Footer -->
    <footer class="footer fixed-footer mt-auto py-3">
        <div class="container text-center">
            <p class="mb-0 text-light">&copy; <?= date('Y') ?> <a href="#" class="text-light">BuyWise</a>.
                <?= __('all_rights_reserved') ?></p>
        </div>
    </footer>

    <script>
        // Show product details in modal
        function showProductDetails(data) {
            document.getElementById('approveID').value = data.ProductID;
            document.getElementById('rejectID').value = data.ProductID;

            // Build main product image
            let imagesHtml = `
                <img src="../uploads/products/${data.ProductImage}" class="img-fluid rounded mb-2"
                     style="max-height: 200px; object-fit: cover; cursor: pointer;"
                     onclick="openImageFullscreen('../uploads/products/${data.ProductImage}')">
            `;

            // Add additional images if available
            if (Array.isArray(data.AdditionalImages)) {
                data.AdditionalImages.forEach(function(img) {
                    imagesHtml += `
                        <img src="../uploads/products/${img}" class="img-fluid rounded mb-2 ms-2"
                             style="max-height: 200px; object-fit: cover; cursor: pointer;"
                             onclick="openImageFullscreen('../uploads/products/${img}')">
                    `;
                });
            }

            // Build modal content
            const content = `
                <div class="row">
                    <div class="col-12 mb-3 d-flex flex-wrap gap-2 justify-content-start">
                        ${imagesHtml}
                    </div>
                    <div class="col-12">
                        <h5>${data.ProductName}</h5>
                        <p><strong><?= __('category') ?>:</strong> ${data.CategoryName}</p>
                        <p><strong><?= __('rating') ?>:</strong> 
                            ${'<i class="fas fa-star text-warning"></i>'.repeat(data.ProductRating)}
                            ${'<i class="far fa-star text-muted"></i>'.repeat(5 - data.ProductRating)}
                        </p>
                        <p><strong><?= __('posted_by') ?>:</strong> ${data.UserName}</p>
                    </div>
                </div>
                <hr>
                <p><strong><?= __('product_description') ?>:</strong></p>
                <p>${data.ProductDescription}</p>
            `;

            document.getElementById('modalContent').innerHTML = content;
            const productModal = new bootstrap.Modal(document.getElementById('productModal'));
            productModal.show();
        }

        // Open image in fullscreen
        function openImageFullscreen(src) {
            const viewer = document.getElementById('fullscreenImageViewer');
            const img = document.getElementById('fullscreenImage');
            img.src = src;
            viewer.style.display = 'flex';
        }

        // Close fullscreen viewer when clicking outside image
        document.getElementById('fullscreenImageViewer').addEventListener('click', function() {
            this.style.display = 'none';
        });

        // Close fullscreen viewer with close button
        document.getElementById('closeFullscreenBtn').addEventListener('click', function() {
            document.getElementById('fullscreenImageViewer').style.display = 'none';
        });

        // Handle reject confirmation
        document.addEventListener("DOMContentLoaded", function() {
            const rejectButton = document.querySelector('#rejectForm button[type="submit"]');
            const rejectForm = document.getElementById('rejectForm');

            rejectButton.addEventListener('click', function(e) {
                e.preventDefault();
                showPopupMessage("<?= __('confirm_reject_user_product') ?>", true, function() {
                    rejectForm.submit();
                });
            });
        });

        // Show popup message 
        function showPopupMessage(message, showCancel = false, onConfirm = null) {
            const overlay = document.getElementById('popup-overlay');
            const popup = document.getElementById('popup');
            const popupMessage = document.getElementById('popup-message');
            const okButton = document.getElementById('popup-ok');
            const cancelButton = document.getElementById('popup-cancel');

            popupMessage.textContent = message;
            popup.classList.add('show');
            overlay.style.display = 'block';
            popup.style.display = 'block';

            cancelButton.style.display = showCancel ? 'inline-block' : 'none';

            // Close popup function
            const closePopup = () => {
                popup.classList.remove('show');
                popup.style.display = 'none';
                overlay.style.display = 'none';
            };

            // Handle OK button click
            okButton.onclick = function() {
                closePopup();
                if (onConfirm) onConfirm();
            };

            // Handle Cancel button and overlay clicks
            cancelButton.onclick = closePopup;
            overlay.onclick = closePopup;
        }
    </script>

    <!-- Show popup message if set in session -->
    <?php if ($showPopup): ?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                showPopupMessage("<?= htmlspecialchars($popupMessage) ?>");
            });
        </script>
    <?php endif; ?>

</body>

</html>