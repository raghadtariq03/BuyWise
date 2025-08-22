<?php
@session_start();
require_once '../config.php';

// Admin access restriction
if (!isset($_SESSION['type'], $_SESSION['UserID']) || $_SESSION['type'] != 1) {
    header("Location: ../login.php");
    exit();
}

// Approve company product
if (isset($_POST['approve_company_product'])) {
    $productID = intval($_POST['approve_company_product']);
    $con->query("UPDATE company_products SET ProductStatus = 1 WHERE ProductID = $productID");

    $stmt = $con->prepare("SELECT CompanyID, ProductName FROM company_products WHERE ProductID = ?");
    $stmt->bind_param("i", $productID);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $senderID = null;
    $recipientID = $product['CompanyID'];
    $recipientType = 'company';
    $productName = $product['ProductName'];
    $message = sprintf(__('product_approved_message'), $productName);
    $link = "/product1/Products.php?ProductID=$productID";

    $notifStmt = $con->prepare("INSERT INTO notifications (sender_id, recipient_id, recipient_type, message, link, is_read) VALUES (?, ?, ?, ?, ?, 0)");
    $notifStmt->bind_param("iisss", $senderID, $recipientID, $recipientType, $message, $link);
    $notifStmt->execute();
    $notifStmt->close();

    $_SESSION['popup'] = __('product_approved');
    header("Location: AdminManageCproduct.php");
    exit();
}

// Reject company product
if (isset($_POST['reject_company_product'])) {
    $productID = intval($_POST['reject_company_product']);
    $con->query("DELETE FROM company_products WHERE ProductID = $productID");
    $_SESSION['popup'] = __('product_rejected');
    header("Location: AdminManageCproduct.php");
    exit();
}

// Delete approved company product
if (isset($_POST['delete_approved_product'])) {
    $productID = intval($_POST['delete_approved_product']);
    $con->query("DELETE FROM company_product_images WHERE ProductID = $productID");
    $con->query("DELETE FROM company_products WHERE ProductID = $productID");
    $_SESSION['popup'] = __('product_deleted');
    header("Location: AdminManageCproduct.php");
    exit();
}

// Deactivate approved company product
if (isset($_POST['deactivate_approved_product'])) {
    $productID = intval($_POST['deactivate_approved_product']);
    $con->query("UPDATE company_products SET ProductStatus = 0 WHERE ProductID = $productID");

    $stmt = $con->prepare("SELECT CompanyID, ProductName FROM company_products WHERE ProductID = ?");
    $stmt->bind_param("i", $productID);
    $stmt->execute();
    $product = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $senderID = null;
    $recipientID = $product['CompanyID'];
    $recipientType = 'company';
    $productName = $product['ProductName'];
    $message = sprintf(__('product_deactivated_message'), $productName);
    $link = "/product1/Products.php?ProductID=$productID";

    $notifStmt = $con->prepare("INSERT INTO notifications (sender_id, recipient_id, recipient_type, message, link, is_read) VALUES (?, ?, ?, ?, ?, 0)");
    $notifStmt->bind_param("iisss", $senderID, $recipientID, $recipientType, $message, $link);
    $notifStmt->execute();
    $notifStmt->close();

    $_SESSION['popup'] = __('product_deactivated');
    header("Location: AdminManageCproduct.php");
    exit();
}

// Dynamic category name
$categoryNameField = $lang === 'ar' ? 'CategoryName_ar' : 'CategoryName_en';

// Fetch pending products
$productResult = $con->query("SELECT cp.ProductID, cp.ProductName, cp.ProductImage, cp.ProductDescription, cp.ProductPrice,
                                     cat.$categoryNameField AS CategoryName, c.CompanyName, cp.LocalProductNumber
                              FROM company_products cp
                              JOIN companies c ON cp.CompanyID = c.CompanyID
                              JOIN categories cat ON cp.CategoryID = cat.CategoryID
                              WHERE cp.ProductStatus = 0");

// Fetch approved products
$approvedProductResult = $con->query("SELECT cp.ProductID, cp.ProductName, cp.ProductImage, cp.ProductDescription, cp.ProductPrice,
                                             cat.$categoryNameField AS CategoryName, c.CompanyName, cp.LocalProductNumber
                                      FROM company_products cp
                                      JOIN companies c ON cp.CompanyID = c.CompanyID
                                      JOIN categories cat ON cp.CategoryID = cat.CategoryID
                                      WHERE cp.ProductStatus = 1");
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">

<head>
  <title><?= __('pending_company_products') ?> | BuyWise</title>
  <link rel="icon" href="../img/favicon.ico">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../style.css">
  <link rel="stylesheet" href="Admin.css">
  <?php include("../header.php"); ?>
</head>

<body class="admin <?= $showPopup ? 'active-popup' : '' ?>">

      <div class="admin-breadcrumb-wrapper">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="Dashboard.php"><i class="fas fa-home me-1"></i> <?= __('admin_dashboard') ?></a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">
                        <i class="fas fa-box"></i> <?= __('pending_company_products') ?>
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
                    <h4 class="fw-bold text-accent"><?= $productResult->num_rows ?></h4>
                    <p class="mb-0"><?= __('pending_company_products') ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-accent">
                <div class="card-body text-center">
                    <i class="fas fa-check-circle fa-2x mb-2 text-accent"></i>
                    <h4 class="fw-bold text-accent"><?= $approvedProductResult->num_rows ?></h4>
                    <p class="mb-0"><?= __('approved_company_products') ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs mb-4" id="productTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab" aria-controls="pending" aria-selected="true">
          <i class="fas fa-clock me-2"></i><?= __('pending_products') ?>
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="approved-tab" data-bs-toggle="tab" data-bs-target="#approved" type="button" role="tab" aria-controls="approved" aria-selected="false">
          <i class="fas fa-check-circle me-2"></i><?= __('approved_products') ?>
        </button>
      </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="productTabsContent">
      <!-- Pending Products Tab -->
      <div class="tab-pane fade show active" id="pending" role="tabpanel" aria-labelledby="pending-tab">
        <div class="admin-card">
          <h5 class="mb-3"><i class="fas fa-box me-2"></i> <?= __('pending_company_products') ?></h5>
          <?php if ($productResult->num_rows > 0): ?>
            <div class="table-responsive">
              <table class="table table-hover align-middle text-center">
                <thead class="text-white" style="background-color: #006d77;">
                  <tr>
                    <th>#</th>
                    <th><?= __('product_name') ?></th>
                    <th><?= __('category') ?></th>
                    <th><?= __('company_name') ?></th>
                    <th><?= __('actions') ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php $i = 1;
                  $productResult->data_seek(0); // Reset result pointer
                  while ($p = $productResult->fetch_assoc()):
                    $p['AdditionalImages'] = [];
                    $imageQuery = $con->prepare("SELECT ImageName FROM company_product_images WHERE ProductID = ?");
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
                      <td><?= htmlspecialchars($p['CategoryName']) ?></td>
                      <td><?= htmlspecialchars($p['CompanyName']) ?></td>
                      <td>
                        <button class="btn btn-sm btn-primary" style="background-color:#e29578;" onclick='showProductDetails(<?= json_encode($p) ?>, "pending")'>
                          <i class="fas fa-eye"></i> <?= __('view') ?>
                        </button>
                      </td>
                    </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          <?php else: ?>
            <div class="alert alert-info text-center"><i class="fas fa-info-circle me-2"></i> <?= __('no_pending_products') ?></div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Approved Products Tab -->
      <div class="tab-pane fade" id="approved" role="tabpanel" aria-labelledby="approved-tab">
        <div class="admin-card">
          <h5 class="mb-3"><i class="fas fa-check-circle me-2"></i> <?= __('approved_company_products') ?></h5>
          <?php if ($approvedProductResult->num_rows > 0): ?>
            <div class="table-responsive">
              <table class="table table-bordered table-hover align-middle text-center">
                <thead class="text-white" style="background-color: #006d77;">
                  <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 25%;"><?= __('product_name') ?></th>
                    <th style="width: 20%;"><?= __('category') ?></th>
                    <th style="width: 20%;"><?= __('company_name') ?></th>
                    <th style="width: 15%;"><?= __('price') ?></th>
                    <th style="width: 15%;"><?= __('actions') ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php $j = 1;
                  while ($ap = $approvedProductResult->fetch_assoc()):
                    $ap['AdditionalImages'] = [];
                    $imageQuery = $con->prepare("SELECT ImageName FROM company_product_images WHERE ProductID = ?");
                    $imageQuery->bind_param("i", $ap['ProductID']);
                    $imageQuery->execute();
                    $imageResult = $imageQuery->get_result();
                    while ($img = $imageResult->fetch_assoc()) {
                      $ap['AdditionalImages'][] = $img['ImageName'];
                    }
                    $imageQuery->close();
                  ?>
                    <tr>
                      <td><?= $j++ ?></td>
                      <td><?= htmlspecialchars($ap['ProductName']) ?></td>
                      <td><?= htmlspecialchars($ap['CategoryName']) ?></td>
                      <td><?= htmlspecialchars($ap['CompanyName']) ?></td>
                      <td>
                        <?= $lang === 'ar'
                            ? number_format($ap['ProductPrice'], 2) . ' ' . __('jod')
                            : __('jod') . ' ' . number_format($ap['ProductPrice'], 2) ?>
                      </td>
                      <td>
                        <div class="d-flex justify-content-center gap-2 flex-wrap">
                          <button class="btn btn-sm btn-outline-info" title="<?= __('view') ?>" onclick='showProductDetails(<?= json_encode($ap) ?>, "approved")'>
                            <i class="fas fa-eye"></i>
                          </button>
                          <button class="btn btn-sm btn-outline-warning" title="<?= __('deactivate') ?>" onclick='deactivateProduct(<?= $ap['ProductID'] ?>)'>
                            <i class="fas fa-pause"></i>
                          </button>
                          <button class="btn btn-sm btn-outline-danger" title="<?= __('delete') ?>" onclick='deleteProduct(<?= $ap['ProductID'] ?>)'>
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
            <div class="alert alert-info text-center"><i class="fas fa-info-circle me-2"></i> <?= __('no_approved_products') ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Product Details Modal -->
  <div class="modal fade" id="productModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-box-open me-2"></i><?= __('product_details') ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="modalContent"></div>
        <div class="modal-footer" id="modalFooter"></div>
      </div>
    </div>
  </div>

  <!-- Delete Confirmation Modal -->
  <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-trash me-2"></i><?= __('confirm_delete') ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p><?= __('confirm_delete_product_message') ?></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('cancel') ?></button>
          <form method="post" style="display: inline;">
            <input type="hidden" id="deleteProductID" name="delete_approved_product">
            <button type="submit" class="btn btn-danger"><i class="fas fa-trash"></i> <?= __('delete') ?></button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Deactivate Confirmation Modal -->
  <div class="modal fade" id="deactivateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fas fa-pause me-2"></i><?= __('confirm_deactivate') ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p><?= __('confirm_deactivate_product_message') ?></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('cancel') ?></button>
          <form method="post" style="display: inline;">
            <input type="hidden" id="deactivateProductID" name="deactivate_approved_product">
            <button type="submit" class="btn btn-warning"><i class="fas fa-pause"></i> <?= __('deactivate') ?></button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div id="fullscreenImageViewer" class="fullscreen-viewer" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background-color:rgba(0,0,0,0.8); z-index:9999; justify-content:center; align-items:center;">
    <button id="closeFullscreenBtn" style="position:absolute; top:20px; right:30px; font-size:1.5rem; background:none; border:none; color:white; z-index:10000; cursor:pointer;">&times;</button>
    <img id="fullscreenImage" src="" style="max-width:90%; max-height:90%; border:5px solid white; border-radius:8px;">
  </div>

  <script>
    function showProductDetails(data, type) {
      let imagesHtml = `<img src="../uploads/products/${data.ProductImage}" class="img-fluid rounded mb-2" style="max-height: 200px; object-fit: cover; cursor: pointer;" onclick="openImageFullscreen('../uploads/products/${data.ProductImage}')">`;

      if (Array.isArray(data.AdditionalImages)) {
        data.AdditionalImages.forEach(function(img) {
          imagesHtml += `<img src="../uploads/products/${img}" class="img-fluid rounded mb-2 ms-2" style="max-height: 200px; object-fit: cover; cursor: pointer;" onclick="openImageFullscreen('../uploads/products/${img}')">`;
        });
      }

      const content = `
        <div class="row">
            <div class="col-12 mb-3 d-flex flex-wrap gap-2 justify-content-start">
                ${imagesHtml}
            </div>
            <div class="col-12">
                <h5>${data.ProductName}</h5>
                <p><strong><?= __('category') ?>:</strong> ${data.CategoryName}</p>
                <p><strong><?= __('price') ?>:</strong> $${parseFloat(data.ProductPrice).toFixed(2)}</p>
                <p><strong><?= __('company_name') ?>:</strong> ${data.CompanyName}</p>
                ${data.LocalProductNumber ? `<p><strong><?= __('product_number') ?>:</strong> ${data.LocalProductNumber}</p>` : ''}
            </div>
        </div>
        <hr>
        <p><strong><?= __('product_description') ?>:</strong></p>
        <p>${data.ProductDescription}</p>`;

      document.getElementById('modalContent').innerHTML = content;
      
      // Set modal footer based on type
      let footerContent = '';
      if (type === 'pending') {
        footerContent = `
          <form method="post" style="display: inline;">
            <input type="hidden" value="${data.ProductID}" name="approve_company_product">
            <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> <?= __('approve') ?></button>
          </form>
          <form method="post" onsubmit="return confirm('<?= __('confirm_reject_product') ?>')" style="display: inline;">
            <input type="hidden" value="${data.ProductID}" name="reject_company_product">
            <button type="submit" class="btn btn-danger"><i class="fas fa-times"></i> <?= __('reject') ?></button>
          </form>`;
      } else {
        footerContent = `
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('close') ?></button>`;
      }
      
      document.getElementById('modalFooter').innerHTML = footerContent;
      new bootstrap.Modal(document.getElementById('productModal')).show();
    }

    function deleteProduct(productID) {
      document.getElementById('deleteProductID').value = productID;
      new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }

    function deactivateProduct(productID) {
      document.getElementById('deactivateProductID').value = productID;
      new bootstrap.Modal(document.getElementById('deactivateModal')).show();
    }

    function openImageFullscreen(src) {
      document.getElementById('fullscreenImage').src = src;
      document.getElementById('fullscreenImageViewer').style.display = 'flex';
    }

    document.getElementById('fullscreenImageViewer').addEventListener('click', function() {
      this.style.display = 'none';
    });
    document.getElementById('closeFullscreenBtn').addEventListener('click', function() {
      document.getElementById('fullscreenImageViewer').style.display = 'none';
    });
  </script>
</body>

</html>