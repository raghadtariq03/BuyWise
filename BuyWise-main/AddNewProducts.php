<?php
require_once "config.php";

// Language switch
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'ar'])) {
    $_SESSION['lang'] = $_GET['lang'];
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit();
}

$lang = $_SESSION['lang'] ?? 'en';
$dir = $lang === 'ar' ? 'rtl' : 'ltr';
require_once "lang.php";

// Show popup if exists
$popupMessage = $_SESSION['popup'] ?? '';
$showPopup = isset($_SESSION['popup']);
unset($_SESSION['popup']);

// Check if user is logged in and is type 2
if (!isset($_SESSION['type']) || $_SESSION['type'] != 2) {
    echo 2; // Not authorized
    exit;
}

// Get User ID from session
$UserID = $_SESSION['UserID'];


// Handle backend logic only if it is an AJAX POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ProductName'], $_POST['CategoryID'], $_POST['ProductDescription'], $_POST['ProductRating'])) {
    $ProductName = trim($_POST['ProductName']);
    $CategoryID = intval($_POST['CategoryID']);
    $ProductStatus = 0;
    $ProductDescription = trim($_POST['ProductDescription']);
    $ProductRating = intval($_POST['ProductRating']);
    $isFake = null; 

    if (empty($ProductDescription)) {
        echo 11;
        exit;
    }
   
    function is_mostly_english($text)
    {
        if (empty($text) || !is_string($text))
            return false;
        $cleaned = preg_replace('/[^\p{L}\s]/u', '', $text);
        $words = explode(' ', $cleaned);
        $englishCount = 0;
        $totalCount = 0;

        foreach ($words as $word) {
            $word = trim($word);
            if ($word === '')
                continue;
            $totalCount++;
            if (preg_match('/^[a-zA-Z]+$/', $word))
                $englishCount++;
        }

        return $totalCount > 0 && ($englishCount / $totalCount) >= 0.5;
    }

 
    if (is_mostly_english($ProductDescription)) {
        $ai_url = "http://127.0.0.1:5000/predict";
        $payload = json_encode(["text" => $ProductDescription]);

        $ch = curl_init($ai_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        $response = curl_exec($ch);
        curl_close($ch);

        $cleaned_response = str_replace(["\n", "\r", "\t"], '', $response);
        $ai_result = json_decode($cleaned_response, true);

        if (isset($ai_result['error']) && $ai_result['error'] === "Non-English input") {
            echo 10;
            exit; // Non-English input
        }

        if (json_last_error() === JSON_ERROR_NONE && is_array($ai_result)) {
            if (isset($ai_result['prediction']) && trim(strtolower($ai_result['prediction'])) === 'fake') {
                $isFake = 1;

               
                $recipientID = $UserID;
                $senderID = NULL;
                $recipientTypeUser = 'user';
                $messageToUser = __('suspicious_product_notice');
                $linkToUserProfile = "#";

                $stmt2 = $con->prepare("INSERT INTO notifications (sender_id, recipient_id, recipient_type, message, link, is_read)
                                        VALUES (?, ?, ?, ?, ?, 0)");
                $stmt2->bind_param("iisss", $senderID, $recipientID, $recipientTypeUser, $messageToUser, $linkToUserProfile);
                $stmt2->execute();
            } else {
                $isFake = 0;
            }
        }
    }
    $ProductRating = intval($_POST['ProductRating']);
    // Validate rating value
    if ($ProductRating < 0 || $ProductRating > 5) {
        $ProductRating = 0;
    }


    if (!isset($_FILES['ProductImage']) || $_FILES['ProductImage']['error'] !== 0) {
        echo 3;
        exit;
    }

    $absoluteUploadDir = 'C:/xampp/htdocs/product1/uploads/products/';
    if (!is_dir($absoluteUploadDir)) {
        mkdir($absoluteUploadDir, 0777, true);
    }

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $fileType = $_FILES['ProductImage']['type'];
    $fileSize = $_FILES['ProductImage']['size'];

    if (!in_array($fileType, $allowedTypes)) {
        echo 4;
        exit;
    }

    if ($fileSize > 2 * 1024 * 1024) {
        echo 5;
        exit;
    }


    $originalName = basename($_FILES['ProductImage']['name']);
    $cleanName = preg_replace("/[^a-zA-Z0-9._-]/", "", $originalName);
    $mainImageName = uniqid() . "_" . $cleanName;
    $targetPath = $absoluteUploadDir . $mainImageName;




    //reached daily limit
    $checkTodayQuery = "SELECT COUNT(*) AS today FROM products WHERE UserID = ? AND DATE(CreatedAt) = CURDATE()";
    $stmtToday = $con->prepare($checkTodayQuery);
    $stmtToday->bind_param("i", $UserID);
    $stmtToday->execute();
    $todayResult = $stmtToday->get_result();
    $productsToday = $todayResult->fetch_assoc()['today'] ?? 0;
    
    if ($productsToday >= 3) {
        echo 6; 
        exit;
    }


    if (move_uploaded_file($_FILES['ProductImage']['tmp_name'], $targetPath)) {
        $con->begin_transaction();

        try {
            // Insert main product data
            $insert = $con->prepare("INSERT INTO products (ProductName, ProductImage, ProductDescription, ProductRating, ProductStatus, CategoryID, UserID, IsFake) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $insert->bind_param("sssiiiii", $ProductName, $mainImageName, $ProductDescription, $ProductRating, $ProductStatus, $CategoryID, $UserID, $isFake);
            $insert->execute();

            $productID = $con->insert_id;


            $additionalImages = $_FILES['AdditionalImages'] ?? [];
            $uploadCount = 0;

            if (!empty($additionalImages['name'][0])) {
                for ($i = 0; $i < count($additionalImages['name']); $i++) {
                    if ($uploadCount >= 2)
                        break;
                    if ($additionalImages['error'][$i] === 0) {
                        $additionalFileType = $additionalImages['type'][$i];
                        $additionalFileSize = $additionalImages['size'][$i];

                        if (!in_array($additionalFileType, $allowedTypes))
                            continue;
                        if ($additionalFileSize > 2 * 1024 * 1024)
                            continue;

                        $additionalOriginalName = basename($additionalImages['name'][$i]);
                        $additionalCleanName = preg_replace("/[^a-zA-Z0-9._-]/", "", $additionalOriginalName);
                        $additionalImageName = uniqid() . "_" . $additionalCleanName;
                        $additionalTargetPath = $absoluteUploadDir . $additionalImageName;

                        if (move_uploaded_file($additionalImages['tmp_name'][$i], $additionalTargetPath)) {
                            $insertAdditional = $con->prepare("INSERT INTO product_images (ProductID, ImageName) VALUES (?, ?)");
                            $insertAdditional->bind_param("is", $productID, $additionalImageName);
                            $insertAdditional->execute();
                            $uploadCount++;
                        }
                    }
                }
            }
            if (isset($ai_result['prediction']) && $ai_result['prediction'] === 'fake') {
                // Log a fake review report in the reported_reviews table when a review is detected as fake
                $stmt = $con->prepare("INSERT INTO reported_reviews (UserID, ProductID ,ReviewContent) VALUES (?, ? ,?)");
                $stmt->bind_param("iis", $UserID, $productID, $ProductDescription);
                $stmt->execute();
                $stmt->close();

                //  Count how many fake reviews the user has submitted so far
                $stmt02 = $con->prepare("SELECT COUNT(*) AS FakeCount FROM reported_reviews WHERE UserID = ?");
                $stmt02->bind_param("i", $UserID);
                $stmt02->execute();
                $result02 = $stmt02->get_result();
                $FakeCount = $result02->fetch_assoc()['FakeCount'] ?? 0;
                $stmt02->close();

                // Determine the appropriate penalty
                $deactivationDays = 0;

                if ($FakeCount == 10) {
                    $deactivationDays = 5; // 5-day deactivation
                } elseif ($FakeCount == 15) {
                    $deactivationDays = 30; // 30-day deactivation
                } elseif ($FakeCount >= 20) {
                    // Permanently delete user account
                    $delete = $con->prepare("DELETE FROM users WHERE UserID = ?");
                    $delete->bind_param("i", $UserID);
                    $delete->execute();
                    $delete->close();
                    // Force logout
                    session_unset();
                    session_destroy();

                    echo 9;
                    exit();
                }

                // Apply temporary deactivation if needed
                if ($deactivationDays > 0) {
                    $DeactivateUntil = date('Y-m-d H:i:s', strtotime("+$deactivationDays days"));
                    $update = $con->prepare("UPDATE users SET UserStatus = 0, DeactivateUntil = ? WHERE UserID = ?");
                    $update->bind_param("si", $DeactivateUntil, $UserID);
                    $update->execute();
                    $update->close();

                    $con->commit();

                    // Force logout
                    session_unset();
                    session_destroy();

                    echo 9;
                    exit();
                }
            }

            $nameField = ($lang === 'ar') ? 'CategoryName_ar' : 'CategoryName_en';
            $getCatName = $con->prepare("SELECT $nameField FROM categories WHERE CategoryID = ?");
            $getCatName->bind_param("i", $CategoryID);
            $getCatName->execute();
            $catResult = $getCatName->get_result();
            $catName = strtolower($catResult->fetch_assoc()[$nameField] ?? '');
            $getCatName->close();

            $con->commit(); //نحفظ كل العمليات في قاعدة البيانات
            echo 1;
            exit;
        } catch (Exception $e) {
            echo "ERROR: " . $e->getMessage();
            $con->rollback(); //نلغي كل العمليات اللي تمت خلال المعاملة حتى ما يتخزن شي بالداتا بيس ازا صار ايرور عنا
            echo 2;
        }
    } else {
        echo 2;
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">

<head>
    <meta charset="utf-8">
    <title><?= __('add_products_title') ?> | BuyWise</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="img/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Rubik&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="AddNewProducts.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="popup-overlay"></div>

    <?php include("header.php"); ?>
    <div class="container py-5 mt-5"> 
        <div class="row mb-4 justify-content-center">
            <div class="col-lg-8">
                <div class="product-card p-4 animate">
                    <form id="uploadimageProducts" enctype="multipart/form-data">

                        <div class="mb-3">
                            <label for="ProductName" class="form-label"><?= __('product_name') ?></label>
                            <input type="text" class="form-control" id="ProductName" name="ProductName"
                                placeholder="<?= __('product_name') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="ProductDescription" class="form-label"><?= __('product_description') ?></label>
                            <textarea class="form-control" id="ProductDescription" name="ProductDescription" rows="4"
                                placeholder="<?= __('product_description') ?>..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="CategoryID" class="form-label"><?= __('product_category') ?></label>
                            <select class="form-select" id="CategoryID" name="CategoryID" required>
                                <option value=""><?= __('select_category') ?></option>
                                <?php
                                $nameField = $lang === 'ar' ? 'CategoryName_ar' : 'CategoryName_en';
                                $categories = $con->query("SELECT * FROM categories");

                                while ($cat = mysqli_fetch_assoc($categories)) {
                                    //حسب اللغه الحاليه للموقع ببحث عن الكاتيجوري نيم المناسبه 
                                    $catName = $cat[$nameField] ?: $cat['CategoryName_en']; // fallbackوازا ما لقاها برجع الاسم بالانجلش كَ 
                                    echo "<option value='{$cat['CategoryID']}'>" . htmlspecialchars($catName) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= __('product_rating') ?></label>
                            <div class="rating-container">
                                <div class="rating">
                                    <input type="radio" name="ProductRating" value="5" id="star5">
                                    <label for="star5" title="5 stars"></label>
                                    <input type="radio" name="ProductRating" value="4" id="star4">
                                    <label for="star4" title="4 stars"></label>
                                    <input type="radio" name="ProductRating" value="3" id="star3">
                                    <label for="star3" title="3 stars"></label>
                                    <input type="radio" name="ProductRating" value="2" id="star2">
                                    <label for="star2" title="2 stars"></label>
                                    <input type="radio" name="ProductRating" value="1" id="star1">
                                    <label for="star1" title="1 star"></label>
                                    <input type="radio" name="ProductRating" value="0" id="star0" checked> <!--اول ما تتحمل الصفحه تلقائي محدد انو مافي ريتنج الا ازا اختار المستخدم-->
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= __('main_image') ?> <span class="text-danger">*</span></label>
                            <div class="custom-file-wrapper">
                                <button type="button" class="form-control" onclick="document.getElementById('ProductImage').click()">
                                    <i class="fas fa-upload me-2"></i> <?= $lang === 'ar' ? 'اختر الصورة الرئيسية' : 'Choose Main Image' ?>
                                </button>
                                <input type="file" id="ProductImage" name="ProductImage" accept="image/*" required onchange="
            document.getElementById('mainImageName').textContent = this.files[0]?.name || '<?= $lang === 'ar' ? 'لم يتم اختيار ملف' : 'No file chosen' ?>';
            previewMainImage(this);">
                            </div>
                            <span class="text-muted small d-block mt-1" id="mainImageName"><?= $lang === 'ar' ? 'لم يتم اختيار ملف' : 'No file chosen' ?></span>
                            <div class="image-preview-container mt-2">
                                <img id="mainImagePreview" class="image-preview d-none" alt="<?= __('preview_alt') ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><?= __('additional_images') ?></label>
                            <div class="custom-file-wrapper">
                                <button type="button" class="form-control" onclick="document.getElementById('AdditionalImages').click()">
                                    <i class="fas fa-upload me-2"></i> <?= $lang === 'ar' ? 'اختر صور إضافية' : 'Choose Additional Images' ?>
                                </button>
                                <input type="file" id="AdditionalImages" name="AdditionalImages[]" accept="image/*" multiple onchange="
            document.getElementById('additionalImageNames').textContent = this.files.length > 0 ? [...this.files].map(f => f.name).join(', ') : '<?= $lang === 'ar' ? 'لم يتم اختيار ملفات' : 'No files chosen' ?>';
            previewAdditionalImages(this);">
                            </div>
                            <span class="text-muted small d-block mt-1" id="additionalImageNames"><?= $lang === 'ar' ? 'لم يتم اختيار ملفات' : 'No files chosen' ?></span>
                            <div id="additionalImagesPreview" class="image-preview-container mt-2"></div>
                        </div>


                        <button type="submit" class="btn w-100 py-3 "
                            style="background-color: #83c5be; color: white; font-weight: bold; border-radius: 10px; border: none; transition: background-color 0.3s ease;">
                            <?= __('add_product_btn') ?>
                        </button>

                        <!-- Store the return URL (if provided in the GET parameter) to redirect the user after form submission -->
                        <input type="hidden" id="redirectTo" name="redirectTo" value="<?= htmlspecialchars($_GET['return'] ?? '') ?>">

                    </form>
                </div>
            </div>
        </div>
    </div>


    <div class="popup">
        <p id="result"></p>
        <button class="okbutton"><?= __('ok') ?></button>
    </div>

    <script>
        const translations = {
            "0": "<?= __('product_exists') ?>",
            "1": "<?= __('product_submitted') ?>",
            "3": "<?= __('image_required') ?>",
            "4": "<?= __('image_invalid') ?>",
            "5": "<?= __('image_large') ?>",
            "6": "<?= __('daily_limit') ?>",
            "9": "<?= __('account_banned') ?>",
            "10": "<?= __('improve_review') ?>",
            "11": "<?= __('empty_product_description') ?>",
            "default": "<?= __('upload_failed') ?>",
            "upgrade": "<?= __('upgrade') ?>"
        };


        function previewMainImage(input) {
            const preview = document.getElementById('mainImagePreview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.classList.remove('d-none');
                }
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.classList.add('d-none');
            }
        }

        function previewAdditionalImages(input) {
            const previewContainer = document.getElementById('additionalImagesPreview');
            previewContainer.innerHTML = '';
            const maxFiles = 2;
            const filesToPreview = Math.min(input.files.length, maxFiles);

            if (input.files && input.files[0]) {
                for (let i = 0; i < filesToPreview; i++) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'image-preview';
                        img.alt = '<?= __('preview_alt') ?> ' + (i + 1);
                        previewContainer.appendChild(img);
                    }
                    reader.readAsDataURL(input.files[i]);
                }

                if (input.files.length > maxFiles) {
                    const message = document.createElement('div');
                    message.className = 'text-danger small mt-2';
                    message.textContent = `<?= str_replace(['{count}', '{selected}'], ['2', '${input.files.length}'], __('only_first_images')) ?>`;
                    previewContainer.appendChild(message);
                }
            }
        }

        document.addEventListener("DOMContentLoaded", function() {


            function showPopup(message) {
                const popup = document.querySelector('.popup');
                const overlay = document.querySelector('.popup-overlay');
                const result = document.getElementById("result");

              
                result.innerHTML = "";

           
                result.innerHTML = message;

            
                popup.classList.add("show");
                overlay.classList.add("show");
            }

            document.querySelector(".okbutton").addEventListener("click", function() {
                document.querySelector(".popup").classList.remove("show");
                document.querySelector(".popup-overlay").classList.remove("show");

                if (typeof window.popupCallback === 'function') {
                    window.popupCallback();
                }

                window.popupCallback = null;
            });






            function __(key) {
                const translations = {
                    'must_not_contain_numbers': "<?= __('must_not_contain_numbers') ?>",
                    'main_image_required': "<?= __('main_image_required') ?>",
                    'product_exists': "<?= __('product_exists') ?>",
                    'product_added': "<?= __('product_added') ?>",
                    'invalid_image_type': "<?= __('invalid_image_type') ?>",
                    'image_too_large': "<?= __('image_too_large') ?>",
                    'upload_failed': "<?= __('upload_failed') ?>",
                    'empty_product_description': "<?= __('empty_product_description') ?>"
                }
                return translations[key] || key;
            }

            // Preview main image


            document.getElementById("uploadimageProducts").addEventListener("submit", function(e) {
                e.preventDefault();

                const form = document.getElementById("uploadimageProducts");
                const formData = new FormData(form);
                const name = formData.get("ProductName");



                if (!formData.get("ProductImage") || formData.get("ProductImage").size === 0) {
                    showPopup("⚠️ Main product image is required.");
                    return;
                }


                fetch("AddNewProducts.php", {
                        method: "POST",
                        body: formData
                    })
                    .then(res => res.text())
                    .then(responseText => {
                        const cleanText = responseText.trim();
                        console.log("📦 Raw Response:", cleanText);

                        if (!cleanText) {
                            showPopup("❌ Server returned no response.");
                            return;
                        }

                      
                        if (!["0", "1", "3", "4", "5", "6", "7", "9", "10", "11"].includes(cleanText)) {
                            showPopup(cleanText);
                            return;
                        }

                        switch (cleanText) {
                            case "0":
                                showPopup(translations[cleanText]);
                                break;
                            case "1":
                                const approvalMessage = "<?= __('product_awaiting_approval') ?>";
                                showPopup(`${translations[cleanText]}<br><span class="text-muted small d-block mt-2">${approvalMessage}</span>`);

                                form.reset();
                                document.getElementById("mainImagePreview").classList.add('d-none');
                                document.getElementById("additionalImagesPreview").innerHTML = '';

                                const urlParams = new URLSearchParams(window.location.search);
                                const returnTo = urlParams.get('return');

                                window.popupCallback = function() {
                                    const catID = urlParams.get('CategoryID') || '';

                                    if (returnTo === 'profile') {
                                        window.location.href = "Profile.php";
                                    } else if (returnTo === 'category') {
                                        window.location.href = "Categories.php" + (catID ? "?CategoryID=" + catID : "");
                                    } else if (document.referrer) {
                                        window.location.href = document.referrer;
                                    } else {
                                        window.location.href = "Home.php";
                                    }
                                };
                                break;


                            case "3":
                                showPopup(translations[cleanText]);
                                break;
                            case "4":
                                showPopup(translations[cleanText]);
                                break;
                            case "5":
                                showPopup(translations[cleanText]);
                                break;
                            case "9":
                                showPopup(translations[cleanText]);
                                break;
                            case "10":
                                showPopup(translations[cleanText]);


                                break;

                            case "6":
                                showPopup(translations["6"]);

                                window.popupCallback = function() {
                                    window.location.href = "Home.php";
                                };

                                setTimeout(() => {
                                    const okButton = document.querySelector(".popup .okbutton");
                                    if (!okButton) return;

                                    if (!document.querySelector(".popup .upgrade-btn")) {
                                        const upgradeBtn = document.createElement("button");
                                        upgradeBtn.className = "upgrade-btn";
                                        upgradeBtn.innerText = translations["upgrade"];
                                        upgradeBtn.style.marginRight = "10px";
                                        upgradeBtn.onclick = () => window.location.href = 'UpgradePlan.php';

                                        okButton.parentElement.insertBefore(upgradeBtn, okButton);
                                    }
                                }, 50);
                                break;
                            case "11":
                                showPopup(translations[cleanText]);
                                break;

                            default:
                                showPopup(translations["default"]);
                        }

                    })
                    .catch(err => {
                        console.error(" Fetch Error:", err);
                        showPopup(translations["default"]);
                    });

            });
        });

        // Show popup if exists
        <?php if ($showPopup): ?>
            showPopup("<?= htmlspecialchars($popupMessage, ENT_QUOTES) ?>");
        <?php endif; ?>
    </script>

</body>

</html>