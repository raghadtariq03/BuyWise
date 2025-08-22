<?php
require_once '../config.php';

// Check company access
if (!isset($_SESSION['type'], $_SESSION['CompanyID']) || $_SESSION['type'] !== 'company') {
    header("Location: ../CompanyLogin.php");
    exit();
}

$_SESSION['UserID'] = $_SESSION['CompanyID'];
$CompanyID = intval($_SESSION['CompanyID']);

// Get company details
$stmt = $con->prepare("SELECT CompanyName, CompanyEmail, CompanyLogo FROM companies WHERE CompanyID = ?");
$stmt->bind_param("i", $CompanyID);
$stmt->execute();
$company = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$company) {
    header("Location: ../CompanyLogin.php");
    exit();
}

$CompanyName = $company['CompanyName'] ?? 'Company';
$CompanyEmail = $company['CompanyEmail'] ?? '';
$CompanyLogo = $company['CompanyLogo'] ?? '../img/ComDef.png';

// Determine language and select correct category name field
$lang = $_SESSION['lang'] ?? 'en';
$nameField = ($lang === 'ar') ? 'CategoryName_ar' : 'CategoryName_en';

// Get company products with localized category names
$stmt = $con->prepare("
    SELECT p.*, c.$nameField AS CategoryName
    FROM company_products p
    LEFT JOIN categories c ON p.CategoryID = c.CategoryID
    WHERE p.CompanyID = ?
    ORDER BY p.ProductID DESC
");

$stmt->bind_param("i", $CompanyID);
$stmt->execute();
$products = $stmt->get_result();
$productsCount = $products->num_rows;
$stmt->close();

// Arabic label helper
function getArabicProductLabel($count)
{
    if ($count == 0) return 'منتجات';
    if ($count == 1) return 'منتج';
    if ($count == 2) return 'منتجان';
    if ($count >= 3 && $count <= 10) return 'منتجات';
    return 'منتج';
}
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">

<head>
    <title><?= __('company_dashboard') ?> | BuyWise</title>
    <link rel="icon" href="../img/favicon.ico">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Bootstrap CSS -->
    <?php if ($dir === 'rtl'): ?>
        <!-- Bootstrap RTL CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <?php else: ?>
        <!-- Regular Bootstrap CSS -->
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php endif; ?>

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">

    <!-- Flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ar.css">

    <!-- Custom styles -->
    <link rel="stylesheet" href="../User.css">

</head>

<body class="bg-light">

    <?php include("../header.php"); ?>

    <div class="container py-5">

        <!-- Company Info Header -->
        <div class="profile-header mb-4">
            <div class="row align-items-center">
                <div class="col-md-2 text-center">
                    <?php
                    $CompanyLogoPath = (!empty($CompanyLogo) && file_exists('../' . $CompanyLogo))
                        ? '/product1/' . ltrim($CompanyLogo, '/')
                        : '/product1/img/ComDef.png';
                    ?>
                    <img src="<?= htmlspecialchars($CompanyLogoPath) ?>" class="user-avatar" alt="Company Logo">

                </div>
                <div class="col-md-7 <?= $dir === 'rtl' ? 'text-md-end' : 'text-md-start' ?> text-center mt-3 mt-md-0">
                    <h2><?= htmlspecialchars($CompanyName) ?></h2>
                    <p><i class="fas fa-envelope <?= $dir === 'rtl' ? 'ms-2' : 'me-2' ?>"></i><?= htmlspecialchars($CompanyEmail) ?></p>
                </div>
                <div class="col-md-3 text-center <?= $dir === 'rtl' ? 'text-md-start' : 'text-md-end' ?>">
                    <div><strong><?= $productsCount ?></strong>
                        <?= $lang === 'ar' ? getArabicProductLabel($productsCount) : __('product_with_count') ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-4" id="companyTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#products" type="button"><?= __('your_products') ?></button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#add" type="button"><?= __('add_product') ?></button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#voucher" type="button"><?= __('voucher') ?></button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#settings" type="button"><?= __('settings') ?></button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content">
            <!-- Your Products Tab -->
            <div class="tab-pane fade show active" id="products">
                <?php include 'CompanyViewProducts.php'; ?>
            </div>
            
            <!-- Add Product Tab -->
            <div class="tab-pane fade" id="add">
                <?php include 'CompanyAddProduct.php'; ?>
            </div>

            <!-- Voucher Tab -->
            <div class="tab-pane fade" id="voucher">
                <?php include 'CompanyVoucher.php'; ?>
            </div>

            <!-- Settings Tab -->
            <div class="tab-pane fade" id="settings">
                <?php include 'CompanySettings.php'; ?>
            </div>
        </div>
    </div>

    <footer class="footer fixed-footer mt-auto py-3">
        <div class="container text-center">
            <p class="mb-0 text-light">&copy; <?= date('Y') ?> <a href="#" class="text-light">BuyWise</a>. <?= __('all_rights_reserved') ?></p>
        </div>
    </footer>


    <script>

        //الكود ببساطة يسمح بفتح تبويب معين تلقائيًا إذا وُجد تاب آي دي في الرابط، ويُستخدم كثيرًا لروابط التوجيه داخل لوحة التحكم أو الصفحات ذات التبويبات.
        document.addEventListener("DOMContentLoaded", function() {
            const hash = window.location.hash;
            if (hash) {
                const tab = document.querySelector(`[data-bs-target="${hash}"]`);
                if (tab) new bootstrap.Tab(tab).show();
            }
        });

        const popup = document.getElementById('popup-js');
        const popupMessage = document.getElementById('popup-message');

        function showPopup(message, isConfirm = false, confirmCallback = null) {
            popupMessage.textContent = message;
            popup.classList.add('show');

            const okBtn = document.getElementById("popup-ok");
            const cancelBtn = document.getElementById("popup-cancel");

            cancelBtn.style.display = isConfirm ? "inline-block" : "none";

            const closePopup = () => {
                popup.classList.remove("show");
                okBtn.removeEventListener("click", okHandler);
                cancelBtn.removeEventListener("click", cancelHandler);
            };

            const okHandler = () => {
                closePopup();
                if (confirmCallback) confirmCallback();
            };

            const cancelHandler = () => {
                closePopup();
            };

            okBtn.addEventListener("click", okHandler);
            cancelBtn.addEventListener("click", cancelHandler);

            if (!isConfirm) {
                setTimeout(() => closePopup(), 3500);
            }
        }
    </script>

    <div class="popup" id="popup-js">
        <div class="popup-content">
            <p id="popup-message"></p>
            <div class="d-flex justify-content-center gap-3 mt-3">
                <button class="okbutton btn btn-primary btn-sm" id="popup-ok">OK</button>
                <button class="cancelbutton btn btn-secondary btn-sm" id="popup-cancel" style="display: none;">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Flatpickr JS -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ar.js"></script>

    <script>
        //flatpickrهذا الكود يقوم بتفعيل تقويم اختيار التاريخ ديت بيكَر لى حقل معين باستخدام مكتبة
        document.addEventListener("DOMContentLoaded", function() {
            flatpickr("#ExpiryDate", {
                dateFormat: "Y-m-d",
                locale: "<?= $lang === 'ar' ? 'ar' : 'default' ?>",
                disableMobile: true
            });
        });
    </script>


</body>

</html>