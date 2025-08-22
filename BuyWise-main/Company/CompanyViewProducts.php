<?php
@session_start();
require_once '../config.php';
if (isset($_SESSION['popup'])) {
    $popupMessage = $_SESSION['popup'];
    unset($_SESSION['popup']);
}


$CompanyID = $_SESSION['CompanyID'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product_id'])) {
    $deleteID = intval($_POST['delete_product_id']);

    // حذف الصور
    $stmtDelImages = $con->prepare("DELETE FROM company_product_images WHERE ProductID = ?");
    $stmtDelImages->bind_param("i", $deleteID);
    $stmtDelImages->execute();
    $stmtDelImages->close();

    // حذف المنتج
    $stmtDelProduct = $con->prepare("DELETE FROM company_products WHERE ProductID = ? AND CompanyID = ?");
    $stmtDelProduct->bind_param("ii", $deleteID, $CompanyID);
    $stmtDelProduct->execute();
    $stmtDelProduct->close();

    $_SESSION['popup'] = __('product_deleted_successfully');
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}



header('Content-Type: text/html; charset=utf-8');
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$perPage = 6;
$offset = ($page - 1) * $perPage;


$countStmt = $con->prepare("SELECT COUNT(*) FROM company_products WHERE CompanyID = ?");
$countStmt->bind_param("i", $CompanyID);
$countStmt->execute();
$countStmt->bind_result($total);
$countStmt->fetch();
$countStmt->close();


$stmt = $con->prepare("SELECT p.*, c.$nameField AS CategoryName 
    FROM company_products p 
    LEFT JOIN categories c ON p.CategoryID = c.CategoryID 
    WHERE p.CompanyID = ? 
    ORDER BY p.ProductID DESC 
    LIMIT ? OFFSET ?");
$stmt->bind_param("iii", $CompanyID, $perPage, $offset);
$stmt->execute();
$products = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">

<head>
    <meta charset="UTF-8">
    <title><?= __('your_products') ?> | BuyWise</title>
    <link rel="icon" href="../img/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../User.css">
    <style>
        .pending-card {
            opacity: 0.65;
            position: relative;


        }

        .pending-overlay {
            filter: grayscale(80%) brightness(90%);
            position: relative;
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            background: rgba(255, 193, 7, 0.9);
            color: #000;
            text-align: center;
            padding: 6px 0;
            font-weight: bold;
            font-size: 0.95rem;
            z-index: 2;
            border-top-left-radius: 0.5rem;
            border-top-right-radius: 0.5rem;
        }

        .modal-thumb {
            height: 90px;
            width: auto;
            border-radius: 6px;
            cursor: pointer;
            object-fit: cover;
            transition: transform 0.2s ease;
            margin-right: 8px;
        }

        .modal-thumb:hover {
            transform: scale(1.05);
        }

        #companyThumbContainer {
            display: flex;
            justify-content: center;
            height: 50px;
        }

        .popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.3);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .popup-overlay.show {
            display: flex;
        }

        .popup-card {
            background-color: var(--popup-bg, #ffddd2);
            color: var(--popup-text, #333);
            padding: 30px;
            max-width: 400px;
            width: 90%;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .popup-card h4 {
            font-size: 1.25rem;
            font-weight: bold;
            color: var(--primary-dark, #006d77);
            margin-bottom: 15px;
        }

        .popup-card p {
            font-size: 1rem;
            font-weight: 500;
            margin-bottom: 20px;
        }

        .popup-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 10px;
            flex-wrap: wrap;
        }

        .okbutton {
            padding: 10px 24px;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 12px;
            cursor: pointer;
            min-width: 100px;
            border: none;
            text-align: center;
            transition: background-color 0.3s ease;
        }

        .okbutton.confirm {
            background-color: var(--popup-button, #e29578);
            color: var(--popup-button-text, #fff);
        }

        .okbutton.confirm:hover {
            background-color: var(--form-link-hover, #e07a5f);
        }

        .okbutton.cancel {
            background-color: transparent;
            color: var(--popup-text, #333);
            border: 1px solid var(--popup-text, #333);
        }

        .okbutton.cancel:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }
.pagination-container {
    margin-top: 30px;
}

.custom-pagination {
    background-color: #fff;
    border-radius: 40px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.custom-pagination .page-item {
    list-style: none;
}

.custom-pagination .page-link {
    background-color: transparent;
    color: #0d6efd;
    font-weight: 500;
    padding: 8px 16px;
    border-radius: 12px;
    transition: all 0.3s ease;
}

.custom-pagination .page-item.active .page-link {
    background-color: #e29578;
    color: #fff !important;
    font-weight: bold;
    border-radius: 12px;
}

.custom-pagination .page-item.disabled .page-link {
    color: #999;
    pointer-events: none;
    opacity: 0.6;
}



    </style>
</head>

<body class="bg-light">
    <div class="row mt-3" id="product-list">

        <body class="bg-light">
            <div class="row mt-3" id="product-list">
                <?php while ($p = $products->fetch_assoc()): ?>

                    <?php
                    $productImage = $p['ProductImage'];
                    $filePath = __DIR__ . '/../uploads/products/' . $productImage;
                    $imagePath = (!empty($productImage) && file_exists($filePath)) ? '../uploads/products/' . htmlspecialchars($productImage) : '../img/ProDef.png';
                    $isPending = $p['ProductStatus'] == 0;


                    $imgRes = mysqli_query($con, "SELECT ImageName FROM company_product_images WHERE ProductID = " . intval($p['ProductID']));
                    $additionalImages = [];
                    while ($row = mysqli_fetch_assoc($imgRes)) {
                        $imageFile = $row['ImageName'];
                        if (!empty($imageFile) && file_exists(__DIR__ . '/../uploads/products/' . $imageFile)) {
                            $additionalImages[] = $imageFile; 
                        }
                    }



                    $modalData = [
                        'ProductImage' =>  $productImage,

                        'ProductName' => $p['ProductName'],
                        'CategoryName' => $p['CategoryName'],
                        'ProductDescription' => $p['ProductDescription'] ?? '',
                        'ProductPrice' => $p['ProductPrice'],
                        'AdditionalImages' => $additionalImages
                    ];
                    $jsonData = htmlspecialchars(json_encode($modalData), ENT_QUOTES, 'UTF-8');
                    ?>
                    <div class="col-md-4 mb-4 d-flex">
                        <div class="card product-card flex-fill <?= $isPending ? 'pending-card' : '' ?>">
                            <?php if ($p['ProductStatus'] == 0): ?>
                                <div class="pending-overlay">
                                    <span><?= __('pending') ?></span>
                                </div>
                            <?php endif; ?>
                            <img src="<?= $imagePath ?>" class="card-img-top" alt="<?= htmlspecialchars($p['ProductName']) ?>">
                            <div class="card-body">

                                <h5 class="card-title"><?= htmlspecialchars($p['ProductName']) ?></h5>
                                <p>
                                <strong><?= __('price') ?>:</strong>
                                <?= $lang === 'ar'
                                    ? number_format($p['ProductPrice'], 2) . ' ' . __('currency_jod')
                                    : __('currency_jod') . ' ' . number_format($p['ProductPrice'], 2)
                                ?>
                                </p>
                                <p><strong><?= __('category') ?>:</strong> <?= htmlspecialchars($p['CategoryName']) ?></p>

                                <?php if ($isPending): ?>
                                    <button class="btn btn-view-product d-inline-flex align-items-center gap-2" onclick='showCompanyProductModal(<?= $jsonData ?>)'>
                                        <i class="fas fa-eye"></i> <?= __('view') ?>
                                    </button>
                                <?php else: ?>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <a href="../Products.php?ProductID=<?= $p['ProductID'] ?>" class="btn btn-view-product d-inline-flex align-items-center gap-2">
                                            <i class="fas fa-eye"></i> <?= __('view') ?>
                                        </a>
                                        <form method="POST" onsubmit="return confirm('<?= __('confirm_delete') ?>');">
                                            <input type="hidden" name="delete_product_id" value="<?= $p['ProductID'] ?>">
                                            <button type="button"
                                                class="btn btn-danger d-inline-flex align-items-center gap-2"
                                                onclick="confirmCompanyProductDelete(<?= $p['ProductID'] ?>)" style="background-color: transparent; color:#e29578; border-color:#e29578; ">
                                                <i class="fas fa-trash-alt"></i> <?= __('delete') ?>
                                            </button>


                                        </form>
                                    </div>

                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </body>
    </div>

    <!-- Modal -->
    <div class="modal fade" id="companyProductModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="companyProductModalLabel"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex flex-wrap mb-3" id="companyThumbContainer"></div>

                    <p><strong><?= __('price') ?>:</strong> <span id="companyModalPrice"></span></p>
                    <p><strong><?= __('category') ?>:</strong> <span id="companyModalCategory"></span></p>
                    <p><strong><?= __('description') ?>:</strong> <span id="companyModalDescription"></span></p>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="companyImagePreviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-body text-center p-3">
                    <img id="companyPreviewImage" src="" class="img-fluid rounded shadow" style="max-height: 500px; object-fit: contain;">
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            function fetchProducts() {
                fetch(window.location.href, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.json())
                    .then(products => {
                        const container = document.getElementById('product-list');
                        container.innerHTML = '';

                        if (products.length === 0) {
                            container.innerHTML = '<div class="col-12"><div class="alert alert-info text-center"><?= __('"no_products"') ?></div></div>';
                            return;
                        }

                        products.forEach(product => {
                            const image = product.ProductImage ? '../uploads/products/' + product.ProductImage : '../img/ProDef.png';
                            const statusBadge = product.ProductStatus == 0 ?
                                '<div class="position-absolute top-0 end-0 bg-warning text-dark p-1 m-2 rounded"><small><?= __('pending') ?></small></div>' :
                                product.ProductStatus == 2 ?
                                '<div class="position-absolute top-0 end-0 bg-danger text-white p-1 m-2 rounded"><small><?= __('rejected') ?></small></div>' : '';

                            const card = `
                    <div class="col-md-4 mb-4 d-flex">
                        <div class="card product-card flex-fill">
                            <div class="position-relative square-container">
                                <img src="${image}" alt="${product.ProductName}" class="card-img-top square-img">
                                ${statusBadge}
                            </div>
                            <div class="card-body">
                                <h5 class="card-title">${product.ProductName}</h5>
                                <p class="card-text"><?= __('price') ?>: $${parseFloat(product.ProductPrice).toFixed(2)}</p>
                                <p class="card-text"><?= __('category') ?>: ${product.CategoryName}</p>
                                <a href="../Products.php?ProductID=${product.ProductID}" class="btn btn-sm w-100 mt-2"><?= __('view') ?></a>
                            </div>
                        </div>
                    </div>
                `;

                            container.insertAdjacentHTML('beforeend', card);
                        });
                    })
                    .catch(error => {
                        console.error('Error fetching products:', error);
                    });
            }

            if (window.XMLHttpRequest && window.location.search.indexOf('ajax') === -1) {
                fetchProducts();
                setInterval(fetchProducts, 5000);
            }
        });

        function confirmCompanyProductDelete(productId) {
            document.getElementById('deleteCompanyProductID').value = productId;
            document.getElementById('companyDeletePopup').classList.add('show');
        }

        function closeCompanyDeletePopup() {
            document.getElementById('companyDeletePopup').classList.remove('show');
        }

        function closeSuccessPopup() {
            document.getElementById('successPopup').classList.remove('show');
        }


        function showCompanyProductModal(product) {
            document.getElementById('companyProductModalLabel').textContent = product.ProductName;
            document.getElementById('companyModalPrice').textContent = `$${parseFloat(product.ProductPrice).toFixed(2)}`;
            document.getElementById('companyModalCategory').textContent = product.CategoryName;
            document.getElementById('companyModalDescription').textContent = product.ProductDescription;

            const allImages = [product.ProductImage, ...(product.AdditionalImages || [])];

            const thumbsHtml = allImages.map(img => `
  <img src="../uploads/products/${img}" class="modal-thumb"
       onclick="openCompanyFullImage('../uploads/products/${img}')">
`).join('');


            document.getElementById('companyThumbContainer').innerHTML = thumbsHtml;



            const modal = new bootstrap.Modal(document.getElementById('companyProductModal'));
            modal.show();
        }

        function showMainCompanyImage(src) {
            document.getElementById('companyModalImage').src = src;
        }


        function openCompanyFullImage(src) {
            document.getElementById('companyPreviewImage').src = src;
            const modal = new bootstrap.Modal(document.getElementById('companyImagePreviewModal'));
            modal.show();
        }
    </script>

    <?php
    // If this is an AJAX request (used by fetch), output JSON instead of HTML
    if (
        isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' &&
        isset($_GET['ajax'])
    ) {

        $stmt = $con->prepare("SELECT p.*, c.CategoryName, p.ProductStatus 
                           FROM company_products p 
                           LEFT JOIN categories c ON p.CategoryID = c.CategoryID 
                           WHERE p.CompanyID = ? 
                           ORDER BY p.ProductID DESC");
        $stmt->bind_param("i", $CompanyID);
        $stmt->execute();
        $result = $stmt->get_result();

        $products = [];
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }

        echo json_encode($products);
        exit();
    }
    ?>
    <div id="companyDeletePopup" class="popup-overlay">
        <div class="popup-card">
            <h4><?= __('confirm_delete_title') ?></h4>
            <p><?= __('confirm_delete_message') ?></p>
            <form method="POST">
                <input type="hidden" name="delete_product_id" id="deleteCompanyProductID">
                <div class="popup-buttons">
                    <button type="submit" class="okbutton confirm"><?= __('confirm') ?></button>
                    <button type="button" class="okbutton cancel" onclick="closeCompanyDeletePopup()"><?= __('cancel') ?></button>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($popupMessage)): ?>
        <div class="popup-overlay show" id="successPopup">
            <div class="popup-card">
                <h4><?= __('success') ?></h4>
                <p><?= htmlspecialchars($popupMessage) ?></p>
                <div class="popup-buttons">
                    <button class="okbutton confirm" onclick="closeSuccessPopup()">OK</button>
                </div>
            </div>
        </div>
    <?php endif; ?>

</body>

</html>