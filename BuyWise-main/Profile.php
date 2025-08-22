<?php
require_once "config.php"; // handles session, DB connection, lang, dir, popup
require_once 'functions.php';

// Ensure user is authenticated and of type 2
if (!isset($_SESSION['type']) || $_SESSION['type'] != 2) {
    echo 2;
    exit();
}
// handle product deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product_id'])) {
    $productID = intval($_POST['delete_product_id']);
    $UserID = $_SESSION['UserID'];

    $stmt = mysqli_prepare($con, "DELETE FROM products WHERE ProductID = ? AND UserID = ?");
    mysqli_stmt_bind_param($stmt, "ii", $productID, $UserID);
    $result = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $_SESSION['popup'] = $result ? __("product_deleted_success") : __("error_deleting_product");
    header("Location: Profile.php");
    exit();
}


$UserID = $_SESSION['UserID'];
$loggedInUserID = $UserID;

// Delete comment if requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_comment_id'])) {
    $commentID = intval($_POST['delete_comment_id']);
    $stmt = $con->prepare("DELETE FROM comments WHERE CommentID = ? AND UserID = ?");
    $stmt->bind_param("ii", $commentID, $UserID);
    $result = $stmt->execute();
    $_SESSION['popup'] = $result ? __('comment_deleted_success') : __('comment_delete_failed');
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Helper functions
function executeQuery($stmt) {
    if (!$stmt->execute()) {
        error_log("SQL Error: " . $stmt->error);
        die("An error occurred.");
    }
    return $stmt->get_result();
}

function fetchSingleRow($sql, $types, $params) {
    global $con;
    $stmt = $con->prepare($sql);
    if (!empty($types)) $stmt->bind_param($types, ...$params);
    $result = executeQuery($stmt);
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row;
}

function fetchMultipleRows($sql, $types, $params) {
    global $con;
    $stmt = $con->prepare($sql);
    if (!empty($types)) $stmt->bind_param($types, ...$params);
    $result = executeQuery($stmt);
    $rows = [];
    while ($row = $result->fetch_assoc()) $rows[] = $row;
    $stmt->close();
    return $rows;
}

// Badge logic
$badgeRanks = ['Normal' => 0, 'Professional' => 1, 'Expert' => 2, 'Legend' => 3];
$userInfo = fetchSingleRow(
    "SELECT UserName, UserEmail, UserAddress, UserPhone, Bio, Avatar, UserGender, points, badge FROM users WHERE UserID = ?",
    "i",
    [$UserID]
);

$points = (int) $userInfo['points'];
$currentBadge = $userInfo['badge'];
$currentRank = $badgeRanks[$currentBadge] ?? 0;

if ($points >= 5000 && $currentRank < 3) {
    $newBadge = 'Legend';
    $newRank = 3;
} elseif ($points >= 1500 && $currentRank < 2) {
    $newBadge = 'Expert';
    $newRank = 2;
} elseif ($points >= 500 && $currentRank < 1) {
    $newBadge = 'Professional';
    $newRank = 1;
} else {
    $newBadge = $currentBadge;
    $newRank = $currentRank;
}

// Update badge if rank increased
if ($newRank > $currentRank) {
    $stmt = $con->prepare("UPDATE users SET badge = ?, badge_rank = ? WHERE UserID = ?");
    $stmt->bind_param("sii", $newBadge, $newRank, $UserID);
    $stmt->execute();
    $stmt->close();
    $userInfo['badge'] = $newBadge;
}

// Format user details
$UserName = ucwords(strtolower(htmlspecialchars($userInfo['UserName'] ?? 'User')));
$userEmailSanitized = htmlspecialchars($userInfo['UserEmail'] ?? '');
$UserAddress = htmlspecialchars($userInfo['UserAddress'] ?? '');
$UserPhone = htmlspecialchars($userInfo['UserPhone'] ?? '');
$Bio = htmlspecialchars($userInfo['Bio'] ?? '');
$gender = strtolower($userInfo['UserGender'] ?? '');
$Avatar = getAvatarPath($userInfo['Avatar'] ?? '', $gender);

// Product count
$productCount = fetchSingleRow(
    "SELECT COUNT(*) as product_count FROM products WHERE UserID = ? AND (IsFake = 0 OR IsFake IS NULL) AND ProductStatus = 1",
    "i",
    [$UserID]
)['product_count'];//هاد الاسم كأنه إيلياس عشان اوصل عطول للرقم من خلاله

// Comment count
$commentCount = fetchSingleRow(
    "SELECT COUNT(*) as comment_count FROM comments WHERE UserID = ? AND (IsFake = 0 OR IsFake IS NULL) AND CommentStatus = 1",
    "i",
    [$UserID]
)['comment_count'];

// Determine language
$lang = $_SESSION['lang'] ?? 'en';
$categoryField = ($lang === 'ar') ? 'c.CategoryName_ar' : 'c.CategoryName_en';

// Get user products with localized category names
$products = fetchMultipleRows(
    "SELECT p.ProductID, p.ProductName, p.ProductImage, $categoryField AS CategoryName,
            p.ProductStatus, p.ProductDescription
     FROM products p
     LEFT JOIN categories c ON p.CategoryID = c.CategoryID
     WHERE p.UserID = ? AND (p.IsFake = 0 OR p.IsFake IS NULL)
     ORDER BY p.CreatedAt DESC",
    "i",
    [$UserID] //هاي حيحطها مكان الاستفهام
);

// Fix product image paths
foreach ($products as &$product) {
    $path = 'uploads/products/' . $product['ProductImage'];
    $product['ProductImage'] = (empty($product['ProductImage']) || !file_exists($path)) ? 'img/ProDef.png' : $path;
}
unset($product);//عشان ما اعدل على بيانات اصليه لقدام بالغلط لانو فوق بعد الفور ايتش مستخدمه &$برودكت يعني انا واصله للرفرنس نفسه العنصر الاصل نفسه مو نسخه منه

// Get user comments
$comments = fetchMultipleRows(
    "SELECT c.CommentID, c.CommentText, c.CommentDate AS CreatedAt, c.ParentCommentID,
            p.ProductID, p.ProductName, p.ProductImage
     FROM comments c
     JOIN products p ON c.ProductID = p.ProductID
     WHERE c.UserID = ? AND c.CommentStatus = 1 AND (c.IsFake = 0 OR c.IsFake IS NULL)
     ORDER BY c.CommentDate DESC",
    "i",
    [$UserID]
);

// Get vouchers
$userVouchers = fetchMultipleRows(
    "SELECT uv.ID, uv.IsRedeemed, uv.AssignedAt,
            cv.VoucherCode, cv.Discount, cv.ExpiryDate, cv.MinPoints,
            c.CompanyName
     FROM user_vouchers uv
     JOIN company_vouchers cv ON uv.VoucherID = cv.VoucherID
     JOIN companies c ON cv.CompanyID = c.CompanyID
     WHERE uv.UserID = ?
     ORDER BY uv.AssignedAt DESC",
    "i",
    [$UserID]
);
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">

<head>
    <title><?= __('title') ?> | BuyWise</title>
    <link rel="icon" href="img/favicon.ico">

    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;700&family=Oswald:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="User.css">
    <link rel="stylesheet" href="profile.css">

</head>

<?php include("header.php"); ?>

<body id="body" class="user <?php echo $showPopup ? 'active-popup' : ''; ?>">

    <div class="container">

        <!-- Popup container -->
        <div class="popup" id="popup">
            <p id="popup-message">
                <?php echo htmlspecialchars($popupMessage); ?>
            </p>
            <button class="okbutton" onclick="closePopup()">OK</button>
        </div>

        <div class="profile-header">
            <div class="row align-items-center">
                <div class="col-md-2 text-center text-md-start">
                    <div class="profile-avatar-wrapper text-center">
                        <div class="avatar-container position-relative mx-auto">
                            <img src="<?= $Avatar ?>" alt="User Avatar" class="user-avatar">

                            <span class="badge-star badge-<?php echo strtolower($userInfo['badge']); ?>">
                                <i class="fas fa-star"></i>
                            </span>
                        </div>

                        <div class="badge-label mt-2">
                            <?php
                            $badgeKey = 'badge_' . strtolower($userInfo['badge']);
                            ?>
                            <div class="badge-name"><?= __($badgeKey) ?></div>
                            <div class="badge-points"><?= $userInfo['points']; ?> <?= __('points') ?></div>
                        </div>
                    </div>
                </div>
                <?php
                $textAlign = $dir === 'rtl' ? 'text-md-end' : 'text-md-start';
                $textDirection = $dir === 'rtl' ? 'ms-md-auto' : 'me-md-auto'; 
                ?>
                <div class="col-md-7 mt-3 mt-md-0 text-center <?= $textAlign ?> <?= $textDirection ?>">
                    <h2 class="mb-1"><?php echo $UserName; ?></h2>
                    <p class="mb-2">
                        <i class="fas fa-envelope <?= $dir === 'rtl' ? 'ms-2' : 'me-2' ?>"></i>
                        <?php echo $userEmailSanitized; ?>
                    </p>
                </div>
                <div class="col-md-3 mt-3 mt-md-0">
                    <div class="row">
                        <div class="col-6">
                            <div class="profile-stats">
                                <h4><?php echo $productCount; ?></h4>
                                <p class="mb-0"><?= __('products') ?></p>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="profile-stats">
                                <h4><?php echo $commentCount; ?></h4>
                                <p class="mb-0"><?= __('comments') ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <ul class="nav nav-tabs nav-fill flex-column flex-md-row" id="profileTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="products-tab" data-bs-toggle="tab" data-bs-target="#products" type="button" role="tab">
                    <i class="fas fa-box me-2"></i><?= __('tab_products') ?>
                </button>
            </li>

            <li class="nav-item" role="presentation">
                <button class="nav-link" id="comments-tab" data-bs-toggle="tab" data-bs-target="#comments" type="button" role="tab">
                    <i class="fas fa-comment me-2"></i><?= __('tab_comments') ?>
                </button>
            </li>

            <li class="nav-item" role="presentation">
                <button class="nav-link" id="vouchers-tab" data-bs-toggle="tab" data-bs-target="#vouchers" type="button" role="tab">
                    <i class="fas fa-ticket-alt me-2"></i><?= __('my_vouchers') ?>
                </button>
            </li>

            <li class="nav-item" role="presentation">
                <button class="nav-link" id="account-tab" data-bs-toggle="tab" data-bs-target="#account" type="button" role="tab">
                    <i class="fas fa-user-cog me-2"></i><?= __('tab_settings') ?>
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="profileTabsContent">
            <!-- Products Tab -->
            <div class="tab-pane fade show active" id="products" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center mb-4 mt-4">
                    <h3 class="mb-0"><?= __('your_products') ?></h3>
                    <a href="AddNewProducts.php?return=profile" class="btn btn-success rounded-pill px-4 py-2 shadow-sm" id="addProductBtn">
                        <i class="fas fa-plus me-2"></i><?= __('add_product') ?>
                    </a>
                </div>

                <!-- Calculate pagination variables -->
                <?php
                $total_products = count($products);
                $products_per_page = 8; // Number of products to show per page
                $total_pages = ceil($total_products / $products_per_page);
                
                //المِن تمنع اليوزر يطلب صفحه اكبر لانو حيلاقي اقل منه التوتال بيج فحياخده
                // الماكس تمنع اليوزر يطلب صفحه اقل من واحد لانو الماكسيمام الموجود واحد حيكون فحياخده  
                $current_page = isset($_GET['page']) ? max(1, min($total_pages, intval($_GET['page']))) : 1; //اذا مافي بيج بالرابط يبدأ من صفر
                
                // يحسب من وين نبدأ نجيب المنتجات من قاعدة البيانات حسب رقم الصفحة.
                $offset = ($current_page - 1) * $products_per_page;

                // Get products for current page
                $current_products = array_slice($products, $offset, $products_per_page);
                ?>

                <!-- Products Grid -->
                <div id="productsList">
                    <?php if (empty($current_products)): ?>
                        <div class="empty-products col-12">
                            <i class="fas fa-box-open"></i>
                            <p><?= __('no_products') ?></p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($current_products as $product): ?>
                            <div class="product-card <?= $product['ProductStatus'] == 0 ? 'pending-card' : '' ?>">

                                <span class="category-badge">
                                    <?php echo htmlspecialchars($product['CategoryName']); ?>
                                </span>
                                <?php if ($product['ProductStatus'] == 0): ?>
                                    <div class="pending-overlay">
                                        <span><?= __('pending') ?></span>
                                    </div>
                                <?php endif; ?>

                                <img src="<?php echo htmlspecialchars($product['ProductImage']); ?>"
                                    class="card-img-top" alt="<?php echo htmlspecialchars($product['ProductName']); ?>">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($product['ProductName']); ?></h5>


                                    <div class="text-center mt-3">
                                        <?php if ($product['ProductStatus'] == 0): ?>
                                            <!-- Pending button with modal trigger هاي لما يكون لسا البرودكت بيندنج مش نازل، بقدر يشوف بس تفاصيله -->
                                            <?php
                                            $ProductImage = $product['ProductImage'];
                                            $ProductName = $product['ProductName'];
                                            $CategoryName = $product['CategoryName'];
                                            $ProductDescription = $product['ProductDescription'] ?? '';

                                            $additionalImages = [];
                                            $result = mysqli_query($con, "SELECT ImageName FROM product_images WHERE ProductID = " . intval($product['ProductID']));
                                            while ($row = mysqli_fetch_assoc($result)) {
                                                $path = 'uploads/products/' . $row['ImageName'];
                                                if (file_exists($path)) {
                                                    $additionalImages[] = $path;
                                                }
                                            }

                                            // مصفوفة تحتوي على بيانات المنتج الأساسية
                                            $modalData = [
                                                'ProductImage' => $ProductImage,
                                                'ProductName' => $ProductName,
                                                'CategoryName' => $CategoryName,
                                                'ProductDescription' => $ProductDescription,
                                                'AdditionalImages' => $additionalImages
                                            ];
                                            //تحويل المصفوفة إلى جيسون اكثر امانًا
                                            $jsonData = htmlspecialchars(json_encode($modalData), ENT_QUOTES, 'UTF-8');
                                            ?>
                                            <button class="btn btn-view-product d-inline-flex align-items-center gap-2"
                                                onclick='showProductModal(<?= $jsonData ?>)'>
                                                <i class="fas fa-eye"></i> <?= __('view') ?>
                                            </button>
                                        <?php else: ?>
                                            <!-- Approved button to real product page هاي لما يكون المنتج موافَق عليه ونازل -->
                                            <a href="Products.php?ProductID=<?= $product['ProductID']; ?>"
                                                class="btn btn-view-product d-inline-flex align-items-center gap-2">
                                                <i class="fas fa-eye"></i> <?= __('view') ?>
                                            </a>
                                            <button type="button" class="btn btn-sm " style="background-color: transparent; color:#e29578; border-color:#e29578; "
                                                onclick="showDeleteProductPopup(<?= (int) $product['ProductID'] ?>)">
                                                <i class="fas fa-trash me-1"></i><?= __('delete') ?>
                                            </button>
                                        <?php endif; ?>
                                    </div>


                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                  <div id="confirmDeleteProductPopup" class="popup-overlay">
        <div class="popup-card">
            <h4 class="fw-bold text-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?= __('delete_product_title') ?>
            </h4>
            <p><?= __('delete_product_message') ?></p>

            <div class="popup-buttons">
                <button class="okbutton cancel" onclick="closeDeleteProductPopup()"><?= __('cancel') ?></button>
                <form method="POST" id="deleteProductConfirmForm">
                    <input type="hidden" name="delete_product_id" id="delete_product_id">
                    <button type="submit" class="okbutton confirm"><?= __('confirm') ?></button>
                </form>
            </div>
        </div>
    </div>

                <!-- Modal to show pending product details -->
                <!-- هون انشأنا هذا المكان يلي رح احط فيه تفاصيل المنتج -->
                <div class="modal fade" id="pendingProductModal" tabindex="-1" aria-labelledby="pendingProductModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="pendingProductModalLabel"><?= __('product_details') ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= __('close') ?>"></button>
                            </div>
                            <div class="modal-body" id="pendingProductModalContent">
                                <!-- by JS -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination-container">
                        <ul class="pagination">
                            <li class="prev <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                                <?php if ($current_page <= 1): ?>
                                    <span>Previous</span>
                                <?php else: ?>
                                    <a href="?page=<?php echo $current_page - 1; ?>">Previous</a>
                                <?php endif; ?>
                            </li>

                            <?php
                            // Determine which page numbers to show
                            $start_page = max(1, min($current_page - 1, $total_pages - 4));
                            $end_page = min($total_pages, max($current_page + 1, 5));

                            // Always show first page
                            if ($start_page > 1): ?>
                                <li class="page-item"><a href="?page=1">1</a></li>
                                <?php if ($start_page > 2): ?>
                                    <li class="page-item disabled"><span>...</span></li>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <li class="page-itempro <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                    <a href="?page=<?php echo $i; ?>"class="page-link"><?php echo $i; ?></a> <!--هنا يتم طباعة رابط الصفحة الأخيرة بشكل صريح، حتى لو كانت خارج نطاق الصفحة الحالية.-->
                                </li>
                            <?php endfor; ?>

                            <?php
                            // Always show last page
                            if ($end_page < $total_pages): ?>
                                <?php if ($end_page < $total_pages - 1): ?>
                                    <li class="page-item disabled"><span>...</span></li>
                                <?php endif; ?>
                                <li class="page-item"><a href="?page=<?php echo $total_pages; ?>"><?php echo $total_pages; ?></a></li>
                            <?php endif; ?>

                            <li class="next <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                                <?php if ($current_page >= $total_pages): ?>
                                    <span>Next</span>
                                <?php else: ?>
                                    <a class="page-link" href="?page=<?php echo $current_page + 1; ?>">Next</a>
                                <?php endif; ?>
                            </li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>


            <!-- Comments Tab -->
            <div class="tab-pane fade" id="comments" role="tabpanel">
                <h3 class="mb-4 mt-4"><?= __('your_comments') ?></h3>
                <div id="commentsList">
                    <?php if (empty($comments)): ?>
                        <div class="alert alert-info">
                            <?= __('no_comments') ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($comments as $comment): ?>
                            <div class="card comment-card mb-4">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div class="d-flex align-items-center">
                                            <img src="uploads/products/<?php echo htmlspecialchars($comment['ProductImage']); ?>"
                                                alt="<?php echo htmlspecialchars($comment['ProductName']); ?>"
                                                class="product-img me-3" style="width: 50px; height: 50px; object-fit: cover;" loading="lazy">
                                            <h5 class="mb-0"><?php echo htmlspecialchars($comment['ProductName']); ?></h5>
                                        </div>
                                        <span class="text-muted small">
                                            <?php echo date('F j, Y', strtotime($comment['CreatedAt'])); ?>
                                        </span>
                                    </div>
                                    <?php echo nl2br(htmlspecialchars(stripslashes($comment['CommentText']), ENT_QUOTES, 'UTF-8')); ?>

                                    <div class="d-flex justify-content-between mt-3">
                                        <?php
                                        $commentID = (int)$comment['CommentID'];
                                        $productID = (int)$comment['ProductID'];
                                        $parentID = (int)($comment['ParentCommentID'] ?? 0);
                                        ?>

                                        <a href="Products.php?ProductID=<?= $productID; ?>&view=all<?= $parentID ? "&comment=$parentID&reply=$commentID" : "&comment=$commentID" ?>"
                                            class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye me-1"></i><?= __('view_on_product') ?>
                                        </a>


                                        <form method="POST" onsubmit="return confirm('<?= __('confirm_delete_comment') ?>');" style="display:inline;">
                                            <input type="hidden" name="delete_comment_id" value="<?php echo (int)$comment['CommentID']; ?>">
                                            <button type="button" class="btn btn-sm btn-danger btn-delete-comment" data-id="<?php echo (int)$comment['CommentID']; ?>">
                                                <i class="fas fa-trash me-1"></i><?= __('delete') ?>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
<!-- my vouchers tab -->
<div class="tab-pane fade" id="vouchers" role="tabpanel" aria-labelledby="vouchers-tab">
    <h3 class="mb-4 mt-4">
        <i class="fas fa-ticket-alt me-2"></i><?= __('my_vouchers') ?>
    </h3>

    <?php if (empty($userVouchers)): ?>
        <div class="alert alert-info text-center">
            <i class="fas fa-info-circle me-2"></i><?= __('no_vouchers') ?>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($userVouchers as $voucher): ?>
                <?php
                    $isExpired = strtotime($voucher['ExpiryDate']) < time();
                    $statusText = $voucher['IsRedeemed'] ? __('redeemed') : ($isExpired ? __('expired') : __('active'));
                    $statusClass = $voucher['IsRedeemed'] ? 'voucher-redeemed' : ($isExpired ? 'voucher-expired' : 'voucher-active');
                    $dirClass = ($dir === 'rtl') ? 'text-end' : 'text-start';
                ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="voucher-card <?= $statusClass ?> shadow-sm border-0 h-100">
                        <div class="voucher-header d-flex justify-content-between align-items-center">
                            <span class="voucher-code"><?= htmlspecialchars($voucher['VoucherCode']) ?></span>
                            <span class="voucher-status"><?= $statusText ?></span>
                        </div>
                        <div class="voucher-body <?= $dirClass ?>">
                            <ul class="list-unstyled mb-3">
                                <li><i class="fas fa-percent me-2"></i><strong><?= __('discount') ?>:</strong> <?= $voucher['Discount'] ?>%</li>
                                <li><i class="fas fa-coins me-2"></i><strong><?= __('min_points') ?>:</strong> <?= $voucher['MinPoints'] ?></li>
                                <li><i class="fas fa-calendar-day me-2"></i><strong><?= __('expiry_date') ?>:</strong> <?= date('Y-m-d', strtotime($voucher['ExpiryDate'])) ?></li>
                                <li><i class="fas fa-store me-2"></i><strong><?= __('company') ?>:</strong> <?= htmlspecialchars($voucher['CompanyName']) ?></li>
                            </ul>
                            <p class="text-muted small mb-0">
                                <i class="fas fa-clock me-2"></i><?= __('assigned_at') ?>: <?= date('Y-m-d H:i', strtotime($voucher['AssignedAt'])) ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>



            <!-- Account Settings Tab -->
            <div class="tab-pane fade" id="account" role="tabpanel">
                <div class="row mt-4">
                    <?php require 'UserAccount.php'; ?>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer fixed-footer mt-auto py-3">
        <div class="container text-center">
            <p class="mb-0 text-light">&copy; <?= date('Y') ?> <a href="#" class="text-light">BuyWise</a>. <?= __('all_rights_reserved') ?></p>
        </div>
    </footer>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const htmlEl = document.documentElement;
    const logo = document.getElementById('brand-logo');
    const urlParams = new URLSearchParams(window.location.search);
    const lang = htmlEl.getAttribute("lang") || "en";

   
    if (urlParams.has('status') && urlParams.has('message')) {
        showAlert(decodeURIComponent(urlParams.get('message')), urlParams.get('status'));
    }

    
    if (urlParams.has('tab') && urlParams.get('tab') === 'comments') {
        document.getElementById('comments-tab')?.click();
    }

    
    const productCards = document.querySelectorAll('#productsList .card');
    productCards.forEach(card => {
        const categoryText = card.querySelector('.card-text');
        if (categoryText) {
            const categoryValue = categoryText.textContent.split(':')[1]?.trim();
            if (categoryValue) {
                const badge = document.createElement('span');
                badge.className = 'js-category-badge';
                badge.textContent = categoryValue;
                card.appendChild(badge);
            }
        }
    });

    // Pagination behavior
    document.querySelectorAll('.pagination a').forEach(link => {
        link.addEventListener('click', function(e) {
            if (this.getAttribute('href') === '#') {
                e.preventDefault();
            }
        });
    });
});
</script>


    <div id="confirmDeletePopup" class="popup-overlay">
        <div class="popup-card">
            <h4 class="fw-bold text-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?= __('delete_comment_title') ?>
            </h4>
            <p><?= __('delete_comment_message') ?></p>

            <div class="popup-buttons">
                <button class="okbutton cancel" onclick="closeDeletePopup()"><?= __('cancel') ?></button>
                <form method="POST" id="deleteConfirmForm">
                    <input type="hidden" name="delete_comment_id" id="delete_comment_id">
                    <button type="submit" class="okbutton confirm"><?= __('confirm') ?></button>
                </form>
            </div>
        </div>
    </div>
<div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-md">
    <div class="modal-content">
      <div class="modal-body text-center p-3">
        <img id="previewModalImage" src="" alt="Preview" class="img-fluid rounded shadow" style="max-height: 500px; object-fit: contain;">
      </div>
    </div>
  </div>
</div>


    <script>
        function showDeletePopup(commentID) {
            document.getElementById("delete_comment_id").value = commentID;
            document.getElementById("confirmDeletePopup").classList.add("show");
            document.body.classList.add("active-popup");
        }

        function closeDeletePopup() {
            document.getElementById("confirmDeletePopup").classList.remove("show");
            document.body.classList.remove("active-popup");
        }
         function showDeleteProductPopup(productID) {
            document.getElementById("delete_product_id").value = productID;
            document.getElementById("confirmDeleteProductPopup").classList.add("show");
            document.body.classList.add("active-popup");
        }

        function closeDeleteProductPopup() {
            document.getElementById("confirmDeleteProductPopup").classList.remove("show");
            document.body.classList.remove("active-popup");
        }


        document.addEventListener("DOMContentLoaded", function() {
            const deleteButtons = document.querySelectorAll(".btn-delete-comment");
            deleteButtons.forEach(btn => {
                btn.addEventListener("click", function(e) {
                    e.preventDefault();
                    const commentID = this.getAttribute("data-id");
                    showDeletePopup(commentID);
                });
            });
        });

        function showProductModal(product) {
            let allImages = [product.ProductImage, ...(product.AdditionalImages || [])];
            let imagesHtml = allImages.map(img =>
                `<img src="${img}" class="modal-thumb" onclick="openFullImage('${img}')">`
            ).join('');

            const content = `
        <div class="row">
          <div class="col-12 mb-3 d-flex flex-wrap gap-2 justify-content-start">
            ${imagesHtml}
          </div>
          <div class="col-12">
            <h5>${product.ProductName}</h5>
            <p><strong><?= __('category') ?>:</strong> ${product.CategoryName}</p>
            <p><strong><?= __('description') ?>:</strong> ${product.ProductDescription}</p>
            <p><strong>Status:</strong> <span class="badge bg-warning text-dark"><?= __('pending') ?></span></p>
          </div>
        </div>
    `;

            document.getElementById("pendingProductModalContent").innerHTML = content; //هون حطينا محتوى تفاصيل المنتج بداخلها
            const modal = new bootstrap.Modal(document.getElementById("pendingProductModal"));
            modal.show(); //هذا فقط لفتح النافذه المنبثقه
        }

        // Fullscreen preview
        function openFullImage(src) {
            const w = window.open();
            w.document.write(`<img src="${src}" style="width:100%">`);
        }
        modal.show();
        function openFullImage(src) {
    const modal = new bootstrap.Modal(document.getElementById('imagePreviewModal'));
    document.getElementById('previewModalImage').src = src;
    modal.show();
}

    </script>

</body>

</html>