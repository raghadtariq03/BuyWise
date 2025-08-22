<?php
require_once 'config.php'; 
require_once 'functions.php';

$loggedInUserID   = $_SESSION['UserID'] ?? 0;
$viewedUserID     = intval($_GET['UserID'] ?? 0);
$viewedCompanyID  = intval($_GET['CompanyID'] ?? ($_GET['Company'] ?? 0));

$isUserProfile    = $viewedUserID > 0;
$isCompanyProfile = $viewedCompanyID > 0;

// Redirect to own profile if trying to view self
if ($loggedInUserID > 0 && $viewedUserID > 0 && $loggedInUserID === $viewedUserID) {
    header("Location: Profile.php");
    exit();
}

// DB helpers
function getConnection() {
    global $con;
    return $con;
}

function executeQuery($stmt) {
    if (!$stmt->execute()) {
        error_log("SQL Error: " . $stmt->error);
        die("An error occurred.");
    }
    return $stmt->get_result();
}

function fetchSingleRow($sql, $types, $params) {
    $stmt = getConnection()->prepare($sql);
    if (!empty($types)) $stmt->bind_param($types, ...$params);
    $result = executeQuery($stmt);
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row;
}

function fetchMultipleRows($sql, $types, $params) {
    $stmt = getConnection()->prepare($sql);
    if (!empty($types)) $stmt->bind_param($types, ...$params);
    $result = executeQuery($stmt);
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $img = $row['ProductImage'] ?? '';
        $row['ProductImagePath'] = (!empty($img) && file_exists("uploads/products/$img")) ? "uploads/products/$img" : "img/ProDef.png";
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

// Badge style lookup
function getBadgeLabel($rank) {
    return [
        1 => ['key' => 'badge_normal',    'class' => 'badge-normal'],
        2 => ['key' => 'badge_bronze',    'class' => 'badge-bronze'],
        3 => ['key' => 'badge_silver',    'class' => 'badge-silver'],
        4 => ['key' => 'badge_gold',      'class' => 'badge-gold'],
        5 => ['key' => 'badge_diamond',   'class' => 'badge-diamond'],
    ][$rank] ?? ['key' => 'badge_normal', 'class' => 'badge-normal'];
}

// Load user or company info
$user = null;
$isCompany = false;

if ($viewedUserID > 0) {
    $user = fetchSingleRow(
        "SELECT UserName, UserEmail, Bio, Avatar, badge, points, badge_rank, 'user' AS type, UserGender AS Gender 
         FROM users WHERE UserID = ?", 
        "i", 
        [$viewedUserID]
    );
} elseif ($viewedCompanyID > 0) {
    $user = fetchSingleRow(
        "SELECT CompanyName AS UserName, CompanyEmail AS UserEmail, '' AS Bio, CompanyLogo AS Avatar, 
                'company' AS type, '' AS Gender 
         FROM companies WHERE CompanyID = ?", 
        "i", 
        [$viewedCompanyID]
    );
    $isCompany = true;
}

if (!$user || !is_array($user)) {
    echo "<div class='alert alert-danger'>" . __('user_not_found') . "</div>";
    exit;
}

// Avatar fallback
$gender = strtolower($user['Gender'] ?? '');

if ($user['type'] === 'company') {
    $logoPath = $user['Avatar'] ?? '';
    $absolutePath = __DIR__ . '/' . $logoPath;
    $Avatar = (!empty($logoPath) && file_exists($absolutePath))
        ? htmlspecialchars($logoPath)
        : 'img/ComDef.png';
} else {
    $defaultAvatar = match ($gender) {
        'male'   => 'img/MaleDef.png',
        'female' => 'img/FemDef.png',
        default  => 'img/ProDef.png',
    };

    $avatarPath = $user['Avatar'] ?? '';
    $absoluteAvatarPath = __DIR__ . '/' . $avatarPath;

    $Avatar = (!empty($avatarPath) && file_exists($absoluteAvatarPath))
        ? htmlspecialchars($avatarPath)
        : $defaultAvatar;
}



// Badge logic for users
$badge = ($user['type'] === 'user') ? ($user['badge'] ?? 'Normal') : 'Company';
$points = (int)($user['points'] ?? 0);

// Products
$lang = $_SESSION['lang'] ?? 'en';
$categoryField = ($lang === 'ar') ? 'c.CategoryName_ar' : 'c.CategoryName_en';

$products = $isCompany
    ? fetchMultipleRows(
        "SELECT p.ProductID, p.ProductName, p.ProductImage, p.ProductPrice, $categoryField AS CategoryName 
         FROM company_products p 
         LEFT JOIN categories c ON p.CategoryID = c.CategoryID 
         WHERE p.CompanyID = ? AND p.ProductStatus = 1
         ORDER BY p.CreatedAt DESC", 
        "i", [$viewedCompanyID]
    )
    : fetchMultipleRows(
        "SELECT p.ProductID, p.ProductName, p.ProductImage, $categoryField AS CategoryName 
         FROM products p 
         LEFT JOIN categories c ON p.CategoryID = c.CategoryID 
         WHERE p.UserID = ? AND p.ProductStatus = 1 
         ORDER BY p.CreatedAt DESC", 
        "i", [$viewedUserID]
    );


// Comments (users only)
$comments = [];
if ($user['type'] === 'user') {
    $comments = fetchMultipleRows(
        "SELECT c.CommentID, c.CommentText, c.CommentDate AS CreatedAt, 
                p.ProductID, p.ProductName, p.ProductImage, c.ParentCommentID 
         FROM comments c 
         JOIN products p ON c.ProductID = p.ProductID 
         WHERE c.UserID = ? AND c.CommentStatus = 1 
         ORDER BY c.CommentDate DESC", 
        "i", [$viewedUserID]
    );
}

// Pagination
$products_per_page = 6;
$total_products    = count($products);
$total_pages       = ceil($total_products / $products_per_page);
$current_page      = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$current_page      = min($current_page, $total_pages);
$offset            = ($current_page - 1) * $products_per_page;
$current_products  = array_slice($products, $offset, $products_per_page);

// Final user data
$profileData = $user;
?>


<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">

<head>
    <meta charset="UTF-8">
    <title><?= __('public_profile') ?> | BuyWise</title>
    <link rel="stylesheet" href="User.css">
    <link rel="stylesheet" href="profile.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="icon" href="img/favicon.ico">
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="container py-5">
        <div class="profile-header">
            <div class="row align-items-center">
                <div class="col-md-2 text-center text-md-start">
                <div class="profile-avatar-wrapper text-center">
                    <div class="avatar-container position-relative mx-auto">
                        <img src="<?= $Avatar ?>" alt="User Avatar" class="user-avatar"
     onerror="this.onerror=null; this.src='<?= $defaultAvatar ?>';">

                        <?php
                        $badgeClass = 'badge-' . strtolower($badge); 
                        ?>
                        <span class="badge-star <?= $badgeClass ?>">
                            <i class="fas fa-star"></i>
                        </span>
                    </div>

                    <div class="badge-label mt-2">
                        <?php
                        $badgeKey = 'badge_' . strtolower($badge);
                        ?>
                    <?php if ($isUserProfile): ?>
                        <div class="badge-name"><?= __($badgeKey) ?></div>
                        <div class="badge-points"><?= $points ?> <?= __('points') ?></div>
                    <?php endif; ?>
                    </div>
                </div>
                </div>
                <?php
                $textAlign = $dir === 'rtl' ? 'text-md-end' : 'text-md-start';
                $textDirection = $dir === 'rtl' ? 'ms-md-auto' : 'me-md-auto';
                ?>
                <div class="col-md-7 mt-3 mt-md-0 text-center <?= $textAlign ?> <?= $textDirection ?>">
                    <h2 class="mb-1"><?= ucwords(htmlspecialchars($profileData['UserName'])) ?></h2>
                </div>
                <div class="col-md-3 mt-3 mt-md-0">
                    <div class="row">
                        <div class="col-6">
                            <div class="profile-stats">
                                <h4><?= count($products) ?></h4>
                                <p class="mb-0"><?= __('products') ?></p>
                            </div>
                        </div>
                        <?php if (!$isCompanyProfile): ?>
                            <div class="col-6">
                                <div class="profile-stats">
                                    <h4><?= count($comments) ?></h4>
                                    <p class="mb-0"><?= __('comments') ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>


        <!-- Tabs -->
        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#products" type="button">
                    <?= __('products') ?>
                </button>
            </li>
            <?php if ($isUserProfile): ?>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#comments" type="button">
                        <?= __('comments') ?>
                    </button>
                </li>
            <?php endif; ?>
        </ul>

        <div class="tab-content">



            <!-- Products Tab -->
            <div class="tab-pane fade show active" id="products" role="tabpanel" name="products">
                <div class="row">
                    <?php foreach ($current_products as $product): ?>

                        <div class="col-md-4 mb-4 d-flex">
                            <div class="card product-card flex-fill">
                                <div class="position-relative square-container">
                                    <img src="<?= htmlspecialchars($product['ProductImagePath']) ?>" class="card-img-top square-img" alt="<?= htmlspecialchars($product['ProductName']) ?>">
                                    <span class="category-badge">
                                        <?= htmlspecialchars($product['CategoryName']) ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title mt-2"><?= htmlspecialchars($product['ProductName']) ?></h5>
                                    <?php if ($isCompanyProfile && isset($product['ProductPrice'])): ?>
                                        <p class="card-text"><?= __('price') ?>: <strong>$<?= number_format($product['ProductPrice'], 2) ?></strong></p>
                                    <?php endif; ?>
                                    <div class="text-center mt-3">
                                        <a href="Products.php?ProductID=<?= $product['ProductID'] ?>" class="btn btn-view-product d-inline-flex align-items-center gap-2">
                                            <i class="fas fa-eye"></i> <?= __('view') ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <!-- Pagination for Products -->
            <?php if ($total_pages > 1 && (!$isUserProfile || !isset($_GET['tab']) || $_GET['tab'] !== 'comments')): ?>
               <div class="pagination-container" id="paginationContainer">

                    <ul class="pagination">
                        <li class="page-item <?= ($current_page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => max(1, $current_page - 1)])) ?>">&laquo; <?= __('prev') ?></a>
                        </li>

                        <?php
                        $max_display = 5;
                        $half = floor($max_display / 2);
                        $start_page = max(2, $current_page - $half);
                        $end_page = min($total_pages - 1, $current_page + $half);

                        if ($current_page <= $half) {
                            $start_page = 2;
                            $end_page = min($total_pages - 1, $max_display);
                        }

                        if ($current_page >= $total_pages - $half) {
                            $start_page = max(2, $total_pages - $max_display + 1);
                            $end_page = $total_pages - 1;
                        }
                        ?>

                        <!--  page 1 -->
                        <li class="page-item <?= ($current_page == 1) ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">1</a>
                        </li>

                        <?php if ($start_page > 2): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>

                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <li class="page-item <?= ($i == $current_page) ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($end_page < $total_pages - 1): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>

                        <?php if ($total_pages > 1): ?>
                            <li class="page-item <?= ($current_page == $total_pages) ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>"><?= $total_pages ?></a>
                            </li>
                        <?php endif; ?>

                        <li class="page-item <?= ($current_page >= $total_pages) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => min($total_pages, $current_page + 1)])) ?>"><?= __('next') ?> &raquo;</a>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Comments Tab -->
            <?php if ($isUserProfile): ?>

                <div class="tab-pane fade" id="comments" role="tabpanel" name="comments">
                    <div id="commentsList">
                        <?php if (empty($comments)): ?>

                        <?php else: ?>
                            <?php foreach ($comments as $comment): ?>
                                <?php
                                $commentID = (int)$comment['CommentID'];
                                $productID = (int)$comment['ProductID'];
                                $parentID = (int)($comment['ParentCommentID'] ?? 0);
                                $link = "Products.php?ProductID=$productID" . ($parentID ? "&comment=$parentID&reply=$commentID" : "&comment=$commentID");
                                ?>
                                <div class="card comment-card mb-4">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div class="d-flex align-items-center">
                                                <img src="<?= htmlspecialchars($comment['ProductImage'] ? 'uploads/products/' . $comment['ProductImage'] : 'img/ProDef.png') ?>" alt="<?= htmlspecialchars($comment['ProductName']) ?>" class="product-img me-3" style="width: 50px; height: 50px; object-fit: cover;" loading="lazy">
                                                <h5 class="mb-0"><?= htmlspecialchars($comment['ProductName']) ?></h5>
                                            </div>
                                            <?php
                                            $date = strtotime($comment['CreatedAt']);

                                            if ($lang === 'ar') {
                                                $months = [
                                                    'January' => 'يناير',
                                                    'February' => 'فبراير',
                                                    'March' => 'مارس',
                                                    'April' => 'أبريل',
                                                    'May' => 'مايو',
                                                    'June' => 'يونيو',
                                                    'July' => 'يوليو',
                                                    'August' => 'أغسطس',
                                                    'September' => 'سبتمبر',
                                                    'October' => 'أكتوبر',
                                                    'November' => 'نوفمبر',
                                                    'December' => 'ديسمبر'
                                                ];

                                                $monthEn = date('F', $date);
                                                $day = date('j', $date);
                                                $year = date('Y', $date);
                                                $monthAr = isset($months[$monthEn]) ? $months[$monthEn] : $monthEn;

                                                echo "<span class='text-muted small'>{$day} {$monthAr}، {$year}</span>";
                                            } else {
                                                echo "<span class='text-muted small'>" . date('F j, Y', $date) . "</span>";
                                            }
                                            ?>

                                        </div>
                                        <p class="mt-3"><?= nl2br(htmlspecialchars(stripslashes($comment['CommentText']))) ?></p>

                                        <div class="text-end mt-3">
                                            <a href="Products.php?ProductID=<?= $productID; ?>&view=all<?= $parentID ? "&comment=$parentID&reply=$commentID" : "&comment=$commentID" ?>" class="btn view-product-btn d-inline-flex align-items-center">
                                                <i class="fas fa-eye me-1"></i><?= __('view_on_product') ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>


        </div>
    </div>

    <footer class="footer fixed-footer mt-auto py-3">
        <div class="container text-center">
            <p class="mb-0 text-light">&copy; <?= date('Y') ?> <a href="#" class="text-light">BuyWise</a>. <?= __('all_rights_reserved') ?></p>
        </div>
    </footer>
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        const pagination = document.getElementById("paginationContainer");

        function updatePaginationVisibility() {
            const activePane = document.querySelector('.tab-pane.active');
            if (activePane && activePane.id === 'comments') {
                pagination?.classList.add('d-none');
            } else {
                pagination?.classList.remove('d-none');
            }
        }

        // عند التبديل بين التبويبات
        const tabButtons = document.querySelectorAll('[data-bs-toggle="tab"]');
        tabButtons.forEach(tab => {
            tab.addEventListener('shown.bs.tab', updatePaginationVisibility);
        });

        
        setTimeout(updatePaginationVisibility, 300);
    });
</script>

</body>

</html>