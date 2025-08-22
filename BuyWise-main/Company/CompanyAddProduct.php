<?php
require_once '../config.php';

// Check company access
if (!isset($_SESSION['type'], $_SESSION['CompanyID']) || $_SESSION['type'] !== 'company') {
    header("Location: CompanyLogin.php");
    exit();
}

$CompanyID = intval($_SESSION['CompanyID']);

// JSON response helper
function returnJson($status, $message) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    echo json_encode(['status' => $status, 'message' => $message]);
    exit();
}

// Handle product submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ProductName'])) {
    $LocalProductNumber = !empty($_POST['LocalProductNumber']) ? trim($_POST['LocalProductNumber']) : null;
    $IsLocal = $LocalProductNumber ? 1 : 0;

    $ProductName = trim($_POST['ProductName']);
    $ProductDescription = trim($_POST['ProductDescription']);
    $ProductPrice = filter_var($_POST['ProductPrice'], FILTER_VALIDATE_FLOAT);
    $CategoryID = filter_var($_POST['CategoryID'], FILTER_VALIDATE_INT);

    if (!$ProductName || !$ProductDescription || $ProductPrice === false || $CategoryID === false) {
        returnJson('error', __('error_missing_fields'));
    }

    // Check for duplicate product
    $stmt = $con->prepare("SELECT 1 FROM company_products WHERE ProductName = ? AND CompanyID = ?");
    $stmt->bind_param("si", $ProductName, $CompanyID);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        returnJson('error', __('product_already_exists'));
    }
    $stmt->close();

    // Validate main image
    $mainImg = $_FILES['ProductImage'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $uploadDir = realpath('../uploads/products') . DIRECTORY_SEPARATOR;

    if (!isset($mainImg) || $mainImg['error'] !== UPLOAD_ERR_OK) {
        returnJson('error', __('main_image_required'));
    }
    if (!in_array($finfo->file($mainImg['tmp_name']), $allowedTypes)) {
        returnJson('error', __('invalid_file_type'));
    }
    if ($mainImg['size'] > 2 * 1024 * 1024) {
        returnJson('error', __('file_too_large'));
    }

    $mainImgName = uniqid() . '_' . basename($mainImg['name']);
    if (!move_uploaded_file($mainImg['tmp_name'], $uploadDir . $mainImgName)) {
        returnJson('error', __('error_upload'));
    }

    // Insert product
    $stmt = $con->prepare("INSERT INTO company_products (ProductName, ProductImage, ProductDescription, ProductPrice, ProductStatus, CategoryID, CompanyID, IsLocal, LocalProductNumber)
                           VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?)");
    $stmt->bind_param("sssdiiis", $ProductName, $mainImgName, $ProductDescription, $ProductPrice, $CategoryID, $CompanyID, $IsLocal, $LocalProductNumber);
    if (!$stmt->execute()) {
        returnJson('error', __('error_database'));
    }
    $ProductID = $con->insert_id;
    $stmt->close();

    // Insert additional images
    $additional = $_FILES['AdditionalImages'];
    if (!empty($additional['name'][0])) {
        foreach ($additional['tmp_name'] as $i => $tmp) {
            if ($i >= 2 || $additional['error'][$i] !== UPLOAD_ERR_OK) continue;
            if (!in_array($finfo->file($tmp), $allowedTypes) || $additional['size'][$i] > 2 * 1024 * 1024) continue;

            $addName = uniqid('img_', true) . '.' . pathinfo($additional['name'][$i], PATHINFO_EXTENSION);
            if (move_uploaded_file($tmp, $uploadDir . $addName)) {
                $imgStmt = $con->prepare("INSERT INTO company_product_images (ProductID, ImageName) VALUES (?, ?)");
                $imgStmt->bind_param("is", $ProductID, $addName);
                $imgStmt->execute();
                $imgStmt->close();
            }
        }
    }

    returnJson('success', __('product_submitted'));
}

// Fetch categories
$nameField = $lang === 'ar' ? 'CategoryName_ar' : 'CategoryName_en';
$categories = $con->query("SELECT CategoryID, $nameField AS CategoryName FROM categories WHERE CategoryStatus = 1");
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">

<head>
    <title><?= __('add_product') ?> | BuyWise</title>
    <link rel="icon" href="../img/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../User.css">
    <style>
        .popup {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: var(--popup-bg, #ffddd2);
            color: var(--popup-text, #000);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
            z-index: 9999;
            display: none;
        }

        .popup.show {
            display: block;
        }

        .popup .okbutton {
            margin-top: 10px;
        }
    </style>
    
</head>

<body class="bg-light">

    <div class="container mt-4">
        <div class="card shadow p-4">
            <h4 class="mb-3"><?= __('add_product') ?></h4>
            <div id="formFeedback" class="alert d-none"></div>
            <form id="addProductForm" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label"><?= __('product_name') ?></label>
                    <input type="text" class="form-control" name="ProductName" required>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?= __('product_description') ?></label>
                    <textarea class="form-control" name="ProductDescription" rows="4" required></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?= __('product_price') ?> <span class="text-muted small">(<?= $lang === 'ar' ? 'دينار أردني' : 'JOD' ?>)</span></label>
                    <input type="number" name="ProductPrice" step="0.01" min="0.01" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?= __('category') ?></label>
                    <select name="CategoryID" id="CategoryID" class="form-select" required onchange="checkLocalCategory(this)">
                        <option value=""><?= __('choose_category') ?></option>
                        <?php
                        $categories->data_seek(0);
                        while ($cat = $categories->fetch_assoc()): ?>
                            <option value="<?= $cat['CategoryID'] ?>" data-name="<?= htmlspecialchars($cat['CategoryName']) ?>">
                                <?= htmlspecialchars($cat['CategoryName']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>





                <div class="mb-3" id="localProductNumberGroup" style="display: none;">
                    <label for="LocalProductNumber" class="form-label"><?= __('local_product_number') ?></label>
                    <input type="text" class="form-control" name="LocalProductNumber" id="LocalProductNumber" placeholder="<?= __('enter_local_number') ?>">
                </div>
                <script>
                    function checkLocalCategory(select) {
                        const selectedOption = select.options[select.selectedIndex];
                        const categoryName = selectedOption.getAttribute("data-name")?.toLowerCase() || "";
                        const localField = document.getElementById('localProductNumberGroup');


                        const localNames = ['local', 'محلي', 'منتجات محلية', 'local products'];

                        const isLocal = localNames.some(name => categoryName.includes(name));

                        localField.style.display = isLocal ? 'block' : 'none';
                    }
                </script>
                


<div class="mb-3">
    <div class="custom-file-wrapper">
        <button type="button" class="form-control" onclick="document.getElementById('ProductImage').click()">
            <i class="fas fa-upload me-2"></i> <?= $lang === 'ar' ? 'اختر الصورة الرئيسية' : 'Choose Main Image' ?>
        </button>
        <input type="file" id="ProductImage" name="ProductImage" accept="image/*" required style="display: none;" onchange="
            document.getElementById('mainImageName').textContent = this.files[0]?.name || '<?= $lang === 'ar' ? 'لم يتم اختيار ملف' : 'No file chosen' ?>';
            previewMainImage(this);">
    </div>
    <span class="text-muted small d-block mt-1" id="mainImageName"><?= $lang === 'ar' ? 'لم يتم اختيار ملف' : 'No file chosen' ?></span>
    <div class="image-preview-container mt-2">
        <img id="mainImagePreview" class="image-preview d-none" alt="<?= __('preview_alt') ?>">
    </div>
</div>


<div class="mb-3">
    <div class="custom-file-wrapper">
        <button type="button" class="form-control" onclick="document.getElementById('AdditionalImages').click()">
            <i class="fas fa-upload me-2"></i> <?= $lang === 'ar' ? 'اختر صور إضافية' : 'Choose Additional Images' ?>
        </button>
        <input type="file" id="AdditionalImages" name="AdditionalImages[]" accept="image/*" multiple style="display: none;" onchange="handleAdditionalImages(this)">

    </div>
    <span class="text-muted small d-block mt-1" id="additionalImageNames"><?= $lang === 'ar' ? 'لم يتم اختيار ملفات' : 'No files chosen' ?></span>
    <div id="additionalImagesPreview" class="image-preview-container mt-2"></div>
</div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i><?= __('submit_product') ?>
                </button>
            </form>
        </div>
    </div>

    <!-- Custom Popup -->
    <div class="popup" id="popup-js">
        <div id="popup-message"></div>
        <button class="okbutton" id="popup-ok"><?= __('ok') ?></button>
    </div>

    <script>
        function showPopup(message) {
            const popup = document.getElementById('popup-js');
            const popupMessage = document.getElementById('popup-message');

            popupMessage.textContent = message;
            popup.classList.add('show');

            const okBtn = document.getElementById("popup-ok");

            const closePopup = () => {
                popup.classList.remove("show");
                okBtn.removeEventListener("click", closePopup);
            };

            okBtn.addEventListener("click", closePopup);

            setTimeout(() => {
                if (popup.classList.contains('show')) {
                    closePopup();
                }
            }, 3500);
        }

        const form = document.getElementById('addProductForm');
        const feedback = document.getElementById('formFeedback');

        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(form);
            const submitBtn = form.querySelector('button[type="submit"]');

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i><?= __('submitting') ?>';

            fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.includes('application/json')) {
                        return response.json();
                    } else {
                        return response.text().then(text => {
                            throw new Error(text);
                        });
                    }
                })
                .then(res => {
                    if (!res || typeof res.status === 'undefined' || typeof res.message === 'undefined') {
                        throw new Error('Invalid response format');
                    }

                    feedback.className = 'alert alert-' + (res.status === 'success' ? 'success' : 'danger');
                    feedback.textContent = res.message;
                    feedback.classList.remove('d-none');
                    feedback.scrollIntoView({
                        behavior: 'smooth'
                    });

                    if (res.status === 'success') {
                        form.reset();
                        showPopup(res.message);
                        setTimeout(() => {
                            feedback.classList.add('d-none');
                        }, 1000);
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    feedback.className = 'alert alert-danger';
                    feedback.textContent = '<?= __('error_network') ?>';
                    feedback.classList.remove('d-none');
                    feedback.scrollIntoView({
                        behavior: 'smooth'
                    });
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-plus me-2"></i><?= __('submit_product') ?>';
                });
        });

        function handleAdditionalImages(input) {
    const files = input.files;
    const maxAllowed = 2;

    if (files.length > maxAllowed) {
        input.value = ""; // clear selected files
        document.getElementById('additionalImageNames').textContent = "<?= $lang === 'ar' ? 'الحد الأقصى صورتان فقط' : 'Maximum of 2 images allowed' ?>";
        document.getElementById('additionalImagesPreview').innerHTML = '';
        return;
    }

    document.getElementById('additionalImageNames').textContent = files.length > 0
        ? [...files].map(f => f.name).join(', ')
        : "<?= $lang === 'ar' ? 'لم يتم اختيار ملفات' : 'No files chosen' ?>";

    previewAdditionalImages(input);
}

    </script>

    

</body>

</html>