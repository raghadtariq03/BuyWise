<?php
require_once 'config.php';
require_once 'functions.php';

$ProductID = intval($_GET['ProductID'] ?? 0);
$commentID = intval($_GET['comment'] ?? 0);
$replyID = intval($_GET['reply'] ?? 0);
$showAll = isset($_GET['view']) && $_GET['view'] === 'all';
$isCompanyProduct = false;

// Set category name field based on current language
$nameField = ($lang === 'ar') ? 'CategoryName_ar' : 'CategoryName_en';

// Try loading user-submitted product first
$stmt = $con->prepare("SELECT p.*, c.$nameField AS CategoryName, u.UserName AS CustomerName, u.UserID AS CustomerID, u.Avatar, u.UserGender, u.badge, u.points FROM products p JOIN categories c ON p.CategoryID = c.CategoryID JOIN users u ON p.UserID = u.UserID WHERE p.ProductID = ? AND p.ProductStatus = 1 AND (p.IsFake = 0 OR p.IsFake IS NULL)");
$stmt->bind_param("i", $ProductID);
$stmt->execute();
$res = $stmt->get_result();

// Fallback to company product if no user product found
if ($res->num_rows === 0) {
    $stmt = $con->prepare("SELECT p.*, c.$nameField AS CategoryName, comp.CompanyName AS CustomerName, comp.CompanyID AS CustomerID, comp.CompanyLogo AS Avatar, 'Company' AS badge, 0 AS points, 'company' AS UserGender FROM company_products p JOIN categories c ON p.CategoryID = c.CategoryID JOIN companies comp ON p.CompanyID = comp.CompanyID WHERE p.ProductID = ?");
    $stmt->bind_param("i", $ProductID);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows === 0) {
        header("Location: Categories.php");
        exit;
    }
    $isCompanyProduct = true;
}

$product = $res->fetch_assoc();
$stmt->close();

// Safely extract variables
$ProductName = $product['ProductName'] ?? '';
$CategoryName = $product['CategoryName'] ?? '';
$CategoryID = $product['CategoryID'] ?? 0;
$ProductImage = $product['ProductImage'] ?? '';
$CustomerName = $product['CustomerName'] ?? '';
$ProductPrice = isset($product['ProductPrice']) ? floatval($product['ProductPrice']) : null;


$CustomerID = $product['CustomerID'];
$gender = strtolower($product['UserGender'] ?? '');
$Avatar = $isCompanyProduct
    ? (!empty($product['Avatar']) && file_exists($product['Avatar'])
        ? htmlspecialchars($product['Avatar'])
        : 'img/ComDef.png')
    : getAvatarPath($product['Avatar'] ?? '', strtolower($product['UserGender'] ?? 'other'));

$points = (int) $product['points'];
$currentBadge = $product['badge'] ?? 'Normal';
$badgeRank = (int) ($product['badge_rank'] ?? 0);

// Auto-upgrade user badge based on points
$badgeLevels = [10000 => ['Legend', 3], 5000 => ['Expert', 2], 1000 => ['Professional', 1]];
foreach ($badgeLevels as $minPoints => [$badge, $rank]) {
    if ($points >= $minPoints && $rank > $badgeRank && strtolower($currentBadge) !== 'admin') {
        $update = $con->prepare("UPDATE users SET badge = ?, badge_rank = ? WHERE UserID = ?");
        $update->bind_param("sii", $badge, $rank, $CustomerID);
        $update->execute();
        $update->close();
        $product['badge'] = $badge;
        $product['badge_rank'] = $rank;
        break;
    }
}

// Set avatar path
if ($isCompanyProduct) {
    $companyLogo = $product['Avatar'] ?? '';
    $avatarPath = (!empty($companyLogo) && file_exists($companyLogo))
        ? htmlspecialchars($companyLogo)
        : 'img/ComDef.png';
} else {
    $avatarPath = getAvatarPath($product['Avatar'] ?? '', strtolower($product['UserGender'] ?? 'other'));
}


// Fetch extra product images
$additionalImages = [];
$stmt = $con->prepare("SELECT ImageName FROM product_images WHERE ProductID = ?");
$stmt->bind_param("i", $ProductID);
$stmt->execute();
$imgs = $stmt->get_result();
while ($img = $imgs->fetch_assoc()) $additionalImages[] = $img['ImageName'];
$stmt->close();

// Count valid top-level comments
$commentField = $isCompanyProduct ? 'CproductID' : 'ProductID';
$q = $con->query("SELECT COUNT(*) AS Count_Coumments FROM comments WHERE $commentField = $ProductID AND ParentCommentID IS NULL AND CommentStatus = 1 AND (IsFake = 0 OR IsFake IS NULL)");
$Comments = ($row = $q->fetch_assoc()) ? $row['Count_Coumments'] : 0;

// Hide rating for company-submitted products
if ($isCompanyProduct) $product['ProductRating'] = null;
?>

<script>
    const targetCommentID = <?= $commentID ?>;
    const targetReplyID = <?= $replyID ?>;
</script>

<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">

<head>
    <meta charset="utf-8">
    <title><?php echo $ProductName; ?> - BuyWise</title>
    <link rel="icon" href="img/favicon.ico">
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Rubik&display=swap"
        rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.0/css/all.min.css" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">

    <link href="Products.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>


</head>

<body>

    <!-- Navbar Start -->
    <?php include("header.php"); ?>
    <!-- Navbar End -->

    <!-- Product Details Section Start -->
    <main class="container py-5 mt-5">
        <!-- Breadcrumb Start -->
        <nav aria-label=" breadcrumb">
            <ol class="breadcrumb d-flex <?= $dir === 'rtl' ? 'flex-row-reverse justify-content-end' : '' ?>">
                <?php if ($lang === 'ar'): ?>
                    <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($ProductName) ?></li>
                    <li class="breadcrumb-item">
                        <a href="Categories.php?CategoryID=<?= $CategoryID; ?>"><?= htmlspecialchars($CategoryName) ?></a>
                    </li>
                    <li class="breadcrumb-item"><a href="Home.php"><?= __('home') ?></a></li>
                <?php else: ?>
                    <li class="breadcrumb-item"><a href="Home.php"><?= __('home') ?></a></li>
                    <li class="breadcrumb-item">
                        <a href="Categories.php?CategoryID=<?= $CategoryID; ?>"><?= htmlspecialchars($CategoryName) ?></a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($ProductName) ?></li>
                <?php endif; ?>
            </ol>


        </nav>
        <!-- Breadcrumb End -->

        <div class="product-card p-4 " style="height: auto;">
            <div class="row">
                <!-- Left Side: Image Gallery -->
                <div class="col-md-6">
                    <div class="image-gallery d-flex gap-3 flex-wrap">
                        <!-- Main Image -->
                        <?php $imagePath = getProductImagePath($ProductImage); ?>

                        <img src="<?= $imagePath ?>" class="img-thumbnail main-product-image"
                            style="width: 180px; height: 180px; object-fit: cover; cursor: pointer;"
                            onclick="openImageModal('<?= $imagePath ?>')">


                        <!-- Additional Images -->
                        <?php foreach ($additionalImages as $image): ?>
                            <?php $additionalImagePath = getProductImagePath($image); ?>
                            <img src="<?= $additionalImagePath ?>" class="img-thumbnail"
                                style="width: 180px; height: 180px; object-fit: cover; cursor: pointer;"
                                onclick="openImageModal('<?= $additionalImagePath ?>')">
                        <?php endforeach; ?>

                    </div>
                </div>

                <!-- Right Side: Product Details -->
                <div class="col-md-6">
                    <?php
                   
                    $rawBadge = strtolower($product['badge'] ?? 'normal');
                    $badgeClass = 'badge-' . ucfirst($rawBadge);

                    
                    if ($isCompanyProduct) {
                        $companyLogo = $product['Avatar'] ?? '';
                        $avatarPath = (!empty($companyLogo) && file_exists($companyLogo))
                            ? htmlspecialchars($companyLogo)
                            : 'img/ComDef.png';
                    } else {
                        $avatarPath = getAvatarPath($product['Avatar'] ?? '', strtolower($product['UserGender'] ?? 'other'));
                    }
                    ?>

                    <div class="posted-by d-flex align-items-center mb-3 mt-4 p-3 shadow-sm rounded"
                        style="background-color: #f9f9f9;">
                        <div class="avatar-container position-relative flex-shrink-0" style="width: 50px; height: 50px;">
                            <img src="<?= $avatarPath ?>" alt="<?= ucfirst(strtolower(htmlspecialchars($CustomerName))) ?>"
                                class="avatar rounded-circle"
                                style="width: 100%; height: 100%; object-fit: cover; border: 2px solid #ddd;">

                         
                            <span class="badge-star <?= $badgeClass ?>"
                                style="<?= $dir === 'rtl' ? 'left: -6px; right: auto;' : 'right: -6px; left: auto;' ?> width:20px; height:20px;">
                                <i class="fas fa-star"></i>
                            </span>
                        </div>

                        <div class="<?= $dir === 'rtl' ? 'me-3' : 'ms-3' ?>">
                            <div class="text-muted small"><?= __('posted_by') ?></div>
                            <a href="PublicProfile.php?<?= $isCompanyProduct ? 'CompanyID' : 'UserID' ?>=<?= $CustomerID ?>"
                                class="fw-bold text-dark" style="text-decoration: none; font-size: 1.1rem;">
                                <?= ucwords(strtolower(htmlspecialchars($CustomerName))) ?>
                            </a>
                        </div>
                    </div>

                    <p class="text-muted mb-2"><?= __('category') ?>: <strong><?= $CategoryName; ?></strong></p>
                    <?php if ($isCompanyProduct && $ProductPrice !== null): ?>
                        <p class="text-muted mb-2">
                            <?= __('price') ?>: <strong><?= $lang === 'ar' ? number_format($ProductPrice, 2) . ' $' : '$' . number_format($ProductPrice, 2) ?></strong>
                        </p>
                    <?php endif; ?>


                    <?php if (isset($product['ProductRating'])): ?>
                        <div class="mb-3">
                            <span class="text-muted"><?= __('rating') ?>: </span>
                            <?php
                            $rating = $product['ProductRating'];
                            for ($i = 1; $i <= 5; $i++) {
                                echo $i <= $rating
                                    ? '<i class="fas fa-star text-warning"></i>'
                                    : '<i class="far fa-star text-muted"></i>';
                            }
                            ?>
                        </div>
                    <?php endif; ?>

                    <div class="product-description">
                        <p><?= nl2br(htmlspecialchars($product['ProductDescription'])); ?></p>
                        <span id="review-label" class="badge"></span>
                    </div>
                </div>

            </div>
        </div>
        </div>

        <?php
        $summaryQuery = $con->query("
    SELECT 
        ROUND(AVG(Rating), 2) AS avgRating,
        ROUND(AVG(QualityRating), 2) AS avgQuality,
        COUNT(*) AS totalReviews
    FROM comments
    WHERE " . ($isCompanyProduct ? "CproductID" : "ProductID") . " = $ProductID
      AND CommentStatus = 1
      AND (CAST(IsFake AS UNSIGNED) = 0 OR IsFake IS NULL)
");

        $summary = $summaryQuery->fetch_assoc();

        ?>


        <?php

        function getRatingLabel($value)
        {
            if ($value >= 5)
                return __('excellent');
            if ($value >= 4)
                return __('very_good');
            if ($value >= 3)
                return __('good');
            if ($value >= 2)
                return __('fair');
            if ($value >= 1)
                return __('poor');
            return __('not_rated');
        }


        function getRatingColor($value)
        {
            if ($value === null || $value === '')
                return 'text-muted';
            if ($value >= 4)
                return 'text-success fw-bold';      
            if ($value >= 2)
                return 'text-warning fw-bold';       
            return 'text-danger fw-bold';                      
        }
        ?>



        <?php if ($summary['totalReviews'] > 0): ?>
            <div class="review-summary-centered-box shadow-sm p-4 mb-5 rounded bg-white mx-auto">
                <h5 class="fw-bold mb-3 text-center"><?= __('reviews') ?> (<?= $summary['totalReviews']; ?>)</h5>

                <!-- Rating number with stars on the left -->
                <div class="d-flex align-items-center justify-content-start mb-4 ps-2">
                    <span class="rating-number fs-2 fw-bold me-2"><?= number_format($summary['avgRating'], 2); ?></span>
                    <div class="rating-stars">
                        <?php
                        $avg = floatval($summary['avgRating']);
                        $fullStars = floor($avg);
                        $remainder = $avg - $fullStars;
                        $emptyStars = 5 - ceil($avg);

                        // Full stars
                        for ($i = 0; $i < $fullStars; $i++) {
                            echo '<i class="fas fa-star text-warning"></i>';
                        }

                        // Partial star
                        if ($remainder > 0) {
                            $percent = round($remainder * 100);
                            echo '<i class="fas fa-star text-warning" style="background: linear-gradient(to ' . ($lang === 'ar' ? 'left' : 'right') . ', #ffc107 ' . $percent . '%, #e4e5e9 ' . $percent . '%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>';
                        }

                        // Empty stars
                        for ($i = 0; $i < $emptyStars; $i++) {
                            echo '<i class="far fa-star text-muted"></i>';
                        }
                        ?>
                    </div>



                </div>

                <!-- Sub-ratings -->
                <div class="row justify-content-center subratings-box small text-center">
                    <div class="col-6 col-md-3 mb-3">
                        <div class="text-muted"><?= __('quality') ?></div>
                        <div class="fw-bold fs-6"><?= getRatingLabel($summary['avgQuality']); ?></div>
                    </div>
                </div>

                <?php if ($Comments > 2): ?>
                    <div class="text-center mt-3">
                        <?php if (!$showAll): ?>
                            <a href="Products.php?ProductID=<?= $ProductID; ?>&view=all#comments-container"
                                class="btn btn-outline-primary btn-sm rounded-pill"><?= __('view_all_reviews') ?></a>
                        <?php else: ?>
                            <a href="Products.php?ProductID=<?= $ProductID; ?>#comments-container"
                                class="btn btn-outline-secondary btn-sm rounded-pill"><?= __('hide_reviews') ?></a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>




        <?php endif; ?>




        <?php include 'ProductsComments.php'; ?>


        <!-- Image Modal -->
        <div id="imageModal" class="image-modal">
            <span class="close-modal" onclick="closeImageModal()">&times;</span>
            <img class="modal-content" id="modalImage">
        </div>

        </div>

        </div>

        <?php if (isset($_SESSION['type']) && $_SESSION['type'] == 2): ?>
            <div class="add-comment-card">
                <h2><?= __('add_your_review') ?></h2>
                <form id="comment-form" class="comment-form" enctype="multipart/form-data">
                    <div class="rating-container">
                        <p><?= __('your_rating') ?>:</p>
                        <div class="star-rating">
                            <input type="radio" id="star5" name="rating" value="5" />
                            <label for="star5" title="5 stars"><i class="fas fa-star"></i></label>

                            <input type="radio" id="star4" name="rating" value="4" />
                            <label for="star4" title="4 stars"><i class="fas fa-star"></i></label>

                            <input type="radio" id="star3" name="rating" value="3" />
                            <label for="star3" title="3 stars"><i class="fas fa-star"></i></label>

                            <input type="radio" id="star2" name="rating" value="2" />
                            <label for="star2" title="2 stars"><i class="fas fa-star"></i></label>

                            <input type="radio" id="star1" name="rating" value="1" />
                            <label for="star1" title="1 star"><i class="fas fa-star"></i></label>
                        </div>


                        <div class="form-group">
                            <label for="quality-rating"><?= __('quality') ?></label>
                            <select id="quality-rating" name="QualityRating" class="form-control">
                                <option value=""><?= __('select_option') ?></option>
                                <option value="1">1 - <?= __('poor') ?></option>
                                <option value="2">2 - <?= __('fair') ?></option>
                                <option value="3">3 - <?= __('good') ?></option>
                                <option value="4">4 - <?= __('very_good') ?></option>
                                <option value="5">5 - <?= __('excellent') ?></option>
                            </select>
                        </div>


                    </div>

                    <div class="form-group">
                        <textarea id="comment" name="CommentText" rows="4"
                            placeholder="<?= __('write_review_here') ?>"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label"><?= __('upload_image_optional') ?></label>
                        <div class="custom-upload-wrapper">
                            <label for="comment-image" class="custom-upload-label">
                                <i class="fas fa-upload"></i> <?= __('choose_image') ?>
                            </label>
                            <input type="file" id="comment-image" name="CommentImage" accept="image/*"
                                class="custom-upload-input" onchange="previewCommentImage(this)">
                            <div id="image-preview" class="image-preview mt-3"></div>
                        </div>
                    </div>


                    <?php if ($UserID == 0): ?>
                        <button type="button" class="accent-btn" onclick="window.location.href='login.php'">
                            <?= __('login_to_review') ?>
                        </button>
                    <?php else: ?>
                        <button type="button" class="accent-btn" onclick="submitComment()"><?= __('add_review') ?></button>
                    <?php endif; ?>
                </form>
            </div>
        <?php endif; ?>

    </main>

    <!-- Back to Top -->
    <button id="back-to-top" class="back-to-top" aria-label="<?= htmlspecialchars(__('back_to_top')) ?>">
        <i class="fa fa-arrow-up"></i>
    </button>

    <!-- Footer -->
    <footer class="footer fixed-footer mt-auto py-3">
        <div class="container text-center">
            <p class="mb-0 text-light">&copy; <?= date('Y') ?> <a href="#" class="text-light">BuyWise</a>.
                <?= __('all_rights_reserved') ?>
            </p>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const backToTopButton = document.getElementById('back-to-top');

          
            window.addEventListener('scroll', function() {
                if (window.scrollY > 300) {
                    backToTopButton.classList.add('show');
                } else {
                    backToTopButton.classList.remove('show');
                }
            });

          
            backToTopButton.addEventListener('click', function() {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
        });
    </script>

    <script>
        // Updated FunAddComment function with custom popups
        function FunAddComment(ProductID, UserID, CommentText) {
            if (UserID == 0) {
                showPopup("<?= __('login_required') ?>", "<?= __('please_login_to_review') ?>", function() {
                    window.location.href = 'login.php';
                });
            } else if (CommentText.trim() === "") {
                showPopup("<?= __('empty_review') ?>", "<?= __('write_review_here') ?>");
            } else {
                $.ajax({
                    type: 'POST',
                    url: 'AddComment.php',
                    data: {
                        ProductID: ProductID,
                        UserID: UserID,
                        CommentText: CommentText
                    },
                    success: function(Result) {
                        if (Result == 1) {

                            window.location.href = 'Products.php?ProductID=' + ProductID;

                        } else {
                            showPopup("<?= __('error') ?>", "<?= __('review_add_failed') ?>");
                        }
                    },
                    error: function() {
                        showPopup("<?= __('server_error') ?>", "<?= __('try_again_later') ?>");
                    }
                });
            }
        }

        function addPopupHtml() {
            const popupHtml = `
        <div class="popup" id="notification-popup">
            <h3 id="popup-title">Notification</h3>
            <p id="popup-message">Message text here</p>
            <button class="okbutton" onclick="closePopup()"><?= __('ok') ?></button>
        </div>
    `;

            const wrapper = document.createElement('div');
            wrapper.innerHTML = popupHtml;
            document.body.appendChild(wrapper.firstElementChild);
        }

        function attachEventHandlers() {
          
        }

        function showPopup(title, message, callback) {
            document.getElementById('popup-title').textContent = title;
            document.getElementById('popup-message').textContent = message;
            document.body.classList.add('active-popup');


            if (typeof callback === 'string') {
                window.popupCallback = () => window.location.href = callback;
            } else if (typeof callback === 'function') {
                window.popupCallback = callback;
            } else {
                window.popupCallback = null;
            }

            document.getElementById('popup-close')?.addEventListener('click', () => {
                document.body.classList.remove('active-popup');
                if (typeof window.popupCallback === 'function') {
                    window.popupCallback();
                }
                window.popupCallback = null;
            });

        }


        function closePopup() {
            document.body.classList.remove('active-popup');

          
            if (window.popupCallback) {
                setTimeout(() => {
                    window.popupCallback();
                    window.popupCallback = null;
                }, 300); // Wait for transition to complete
            }
        }

      
        const productID = <?php echo json_encode($ProductID); ?>;




      
        $(document).ready(function() {
          
            if (!document.getElementById('notification-popup')) {
                addPopupHtml();
            }

            document.querySelectorAll('.like-btn').forEach(btn => {
                if (btn.classList.contains('active')) {
                    btn.querySelector('i').className = 'fas fa-heart';
                }
            });
        });


        function likeComment(commentID, UserID, action, event) {
            if (UserID == 0) {
                showPopup("<?= __('login_required') ?>", "<?= __('please_login_to_like') ?>", function() {
                    window.location.href = 'login.php';
                });
                return;
            }

            $.ajax({
                type: 'POST',
                url: 'ToggleLike.php',
                dataType: 'json', 
                data: {
                    CommentID: commentID,
                    UserID: UserID,
                    Action: action
                },
                success: function(data) {
                    console.log("Parsed response:", data); 

                    if (data.status === 'success') {
                        const likeBtn = event.target.closest('.like-btn');
                        const icon = likeBtn.querySelector('i');
                        const countSpan = likeBtn.querySelector('.like-count');

                      
                        countSpan.textContent = data.likeCount;

                       
                        likeBtn.classList.toggle('active');
                        icon.className = likeBtn.classList.contains('active') ? 'fas fa-heart' : 'far fa-heart';
                    } else {
                        showPopup("Error", data.message || "Unexpected error occurred.");
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error:", error);
                    showPopup("Server Error", "An error occurred. Please try again later.");
                }
            });
        }



      
        $(document).ready(function() {
           
            if (!document.getElementById('notification-popup')) {
                addPopupHtml();
            }

        });
    
        document.getElementById('comment-image').addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('image-preview');
                    preview.innerHTML = `
                <div class="image-preview-container">
                    <img src="${e.target.result}" alt="Preview">
                </div>
            `;
                }
                reader.readAsDataURL(file);
            }
        });

  
        function submitComment() {
            const commentText = document.getElementById('comment').value;
            const selectedRating = document.querySelector('input[name="rating"]:checked');
            const ratingValue = selectedRating ? selectedRating.value : null;
            const qualityValue = document.getElementById('quality-rating').value;

         
            if (!ratingValue && !qualityValue) {
                showPopup("<?= __('missing_info') ?>", "<?= __('select_both_rating_quality') ?>");
                return;
            }

            if (!ratingValue) {
                showPopup("<?= __('missing_rating') ?>", "<?= __('select_star_rating') ?>");
                return;
            }

            if (!qualityValue) {
                showPopup("<?= __('missing_quality') ?>", "<?= __('select_quality_level') ?>");
                return;
            }



            const imageFile = document.getElementById('comment-image').files[0];
            const quality = document.getElementById('quality-rating').value || '';


            const formData = new FormData();

            formData.append('QualityRating', quality);

            if (commentText.trim() === "") {
                showPopup("<?= __('empty_review') ?>", "<?= __('write_review_here') ?>");
                return;
            }

            formData.append('ProductID', <?php echo $ProductID; ?>);
            formData.append('UserID', <?php echo $UserID; ?>);
            formData.append('CommentText', commentText);
            formData.append('Rating', ratingValue);
            formData.append('isCompanyProduct', <?php echo json_encode($isCompanyProduct); ?>); 


            if (imageFile) {
                formData.append('CommentImage', imageFile);
            }

            fetch('AddComment.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text()) 
                .then(result => {
                    console.log("Server raw response:", result);
                    try {
                        const parsed = JSON.parse(result);
                        if (parsed.status === 9) {
                            showPopup("<?= __('account_deactivated') ?>", parsed.message, "logout.php");
                            return;
                        }
                        if (parsed.status === 1) {
                            const commentID = parsed.commentID;

                            showPopup("<?= __('submitted') ?>", parsed.message, () => {
                                const newComment = document.getElementById("comment-" + commentID);
                                window.location.href = "Products.php?ProductID=<?= $ProductID ?>&comment=" + commentID;
                            });
                        } else if (parsed.status === 2) {
                          
                            showPopup("<?= __('failed') ?>", parsed.message, () => {
                                window.location.href = 'Products.php?ProductID=<?= $ProductID ?>';
                            });



                        } else {
                           
                            showPopup("<?= __('error') ?>", parsed.error || "<?= __('review_add_failed') ?>");
                        }
                    } catch (e) {
                        showPopup("<?= __('error') ?>", "<?= __('invalid_response') ?>: " + result);
                    }
                })
                .catch(error => {
                    showPopup("<?= __('server_error') ?>", "<?= __('try_again_later') ?>: " + error.message);
                });

        }

        // Function to open image modal
        function openImageModal(imgSrc) {
            const modal = document.getElementById('imageModal');
            const modalImg = document.getElementById('modalImage');
            modal.style.display = "block";
            modalImg.src = imgSrc;
        }

        // Function to close image modal
        function closeImageModal() {
            document.getElementById('imageModal').style.display = "none";
        }
        // Function to open image modal
        function openImageModal(imgSrc) {
            const modal = document.getElementById("imageModal");
            const modalImg = document.getElementById("modalImage");
            modal.style.display = "flex";
            modalImg.src = imgSrc;
        }

        // Function to close image modal
        function closeImageModal() {
            document.getElementById("imageModal").style.display = "none";
        }
        document.addEventListener("DOMContentLoaded", function() {
            const urlParams = new URLSearchParams(window.location.search);
            const commentID = urlParams.get("comment");
            const replyID = urlParams.get("reply");

            if (replyID) {
                const tryScrollToReply = () => {
                    const replyEl = document.getElementById("reply-" + replyID);
                    const container = document.getElementById("replies-" + commentID);

                    if (replyEl && container) {
                        container.style.display = "block";
                        replyEl.scrollIntoView({
                            behavior: "smooth",
                            block: "center"
                        });
                        replyEl.classList.add("highlight-reply");
                        setTimeout(() => {
                            replyEl.classList.remove("highlight-reply");
                        }, 2500);

                        const toggleBtn = document.querySelector(`#comment-${commentID} .toggle-replies-btn`);
                        if (toggleBtn) toggleBtn.classList.add("active");
                    } else {
                     
                        setTimeout(tryScrollToReply, 500);
                    }
                };

                tryScrollToReply();
            }
        });
    </script>

    <!-- Image Modal -->
    <div id="imageModal" class="image-modal">
        <span class="close-modal" onclick="closeImageModal()">&times;</span>
        <img class="modal-content" id="modalImage">
    </div>

</body>

</html>