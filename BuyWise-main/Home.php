<?php
@session_start();

// Language handling
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'ar'])) {
    $_SESSION['lang'] = $_GET['lang'];
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit();
}

$lang = $_SESSION['lang'] ?? 'en';
$dir = $lang === 'ar' ? 'rtl' : 'ltr';
$lang_code = $lang;

include('lang.php');

// Database connection
try {
    $con = new mysqli("localhost", "root", "", "BuyWise");
    if ($con->connect_error) {
        throw new Exception("Connection failed: " . $con->connect_error);
    }
    $con->set_charset("utf8mb4");
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("We're experiencing technical difficulties. Please try again later.");
}

// Fetch testimonials with user and product information
$testimonials = [];

$sql = "SELECT 
    c.CommentID,
    c.UserID,
    c.ProductID,
    c.CommentText,
    c.CommentDate,
    c.Rating,
    c.QualityRating,
    c.CommentImage,
    u.UserName,
    u.Avatar,
    u.badge,
    u.points,
    u.UserGender,
    p.ProductName,
    cat.CategoryName_en,
    cat.CategoryName_ar

FROM comments c
JOIN users u ON c.UserID = u.UserID
JOIN products p ON c.ProductID = p.ProductID
JOIN categories cat ON p.CategoryID = cat.CategoryID
WHERE c.CommentStatus = 1 
    AND (c.IsFake = 0 OR c.IsFake IS NULL)
    AND c.ParentCommentID IS NULL
    AND c.Rating >= 4
ORDER BY c.Rating DESC, c.CommentDate DESC
LIMIT 8";

$result = $con->query($sql);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $testimonials[] = $row;
    }
}

// Define goal data for DRY code
// $goals = [
//     ['icon' => 'fa-shield-alt', 'key' => 1],
//     ['icon' => 'fa-globe', 'key' => 2],
//     ['icon' => 'fa-book-open', 'key' => 3],
//     ['icon' => 'fa-comments', 'key' => 4],
//     ['icon' => 'fa-trophy', 'key' => 5],
//     ['icon' => 'fa-search', 'key' => 6]
// ];

function generateStarRating($rating)
{
    $stars = '';
    for ($i = 1; $i <= 5; $i++) {
        $stars .= ($i <= $rating) ? '<i class="star filled">★</i>' : '<i class="star">☆</i>';
    }
    return $stars;
}

function truncateText($text, $maxLength)
{
    return (strlen($text) <= $maxLength) ? $text : substr($text, 0, $maxLength) . '...';
}

function formatDate($dateString)
{
    return date('F j, Y', strtotime($dateString)); //بتحط اسم الشهر بعدين اليوم بعدين فاصله ثم السنه
}
// strtotime بتجيب التوقيت بالثواني 
// date بتحول هاي الثواني لصيغة التاريخ كامل


function getBadgeClass($badge)
{
    switch (strtolower($badge)) {
        case 'admin': return 'badge-admin';
        case 'verified': return 'badge-verified';
        default: return 'badge-normal';
    }
}

function getAvatarUrl($avatar, $gender = 'Male')
{
    if (!$avatar || $avatar === 'MaleDef.png' || $avatar === 'FemDef.png') {
        return $gender === 'Female' ? 'img/FemDef.png' : 'img/MaleDef.png';
    }
    return 'uploads/avatars/' . $avatar;
}

$sliderImages = [];
$sliderQuery = $con->query("
    SELECT p.ProductID, p.ProductImage, p.ProductName, 
           COALESCE(AVG(c.Rating), 0) AS AvgRating, COUNT(c.CommentID) AS ReviewCount, 
           cat.CategoryName_en
    FROM products p
    JOIN categories cat ON p.CategoryID = cat.CategoryID
    LEFT JOIN comments c ON p.ProductID = c.ProductID AND c.CommentStatus = 1
    WHERE p.ProductImage IS NOT NULL AND p.ProductImage != '' 
          AND p.ProductStatus = 1 
          AND cat.CategoryName_en = 'Local'
    GROUP BY p.ProductID
    ORDER BY RAND()
    LIMIT 5
");

if ($sliderQuery) {
    while ($row = $sliderQuery->fetch_assoc()) {
        $sliderImages[] = [
            'id' => $row['ProductID'],
            'path' => 'uploads/products/' . $row['ProductImage'],
            'name' => $row['ProductName'],
            'rating' => round($row['AvgRating']),
            'review_count' => $row['ReviewCount'],
            'badge' => '🇯🇴 Local',
            'badge_type' => 'local'
        ];
    }
}

// Fetch testimonials for JavaScript
// $jsTestimonials = [];
// $jsQuery = "
// SELECT 
//     c.CommentText, c.Rating, c.CommentDate, c.QualityRating,
//     u.UserName, u.Avatar, u.UserGender, u.points, u.badge,
//     p.ProductName,
//     cat.CategoryName_en
// FROM comments c
// JOIN users u ON c.UserID = u.UserID
// JOIN products p ON c.ProductID = p.ProductID
// JOIN categories cat ON p.CategoryID = cat.CategoryID
// WHERE c.CommentStatus = 1 AND c.Rating >= 4
// ORDER BY RAND()
// LIMIT 10
// ";

// $jsResult = $con->query($jsQuery);
// if ($jsResult) {
//     while ($row = $jsResult->fetch_assoc()) {
//         $jsTestimonials[] = $row;
//     }
// }

?>

<!DOCTYPE html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BuyWise - <?= htmlspecialchars(__('home')) ?></title>
    <link rel="icon" href="img/favicon.ico">

    <!-- Fonts & CSS -->
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Rubik&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link href="style.css" rel="stylesheet">
    <link href="home.css" rel="stylesheet">
    <link href="header.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    
</head>

<body class="home">

    <?php 
    if (file_exists('header.php')) {
        include 'header.php'; 
    }
    ?>

<section class="hero-buywise container-fluid d-flex align-items-center justify-content-center">
    <div class="container py-5">
        <div class="row align-items-center">
            <!-- Left: Hero Text -->
            <div class="col-lg-6 text-center text-lg-start hero-content">
                <h1 class="hero-title">
                    <?= __('buywise_hero_main') ?> <span class="hero-highlight"><?= __('buywise_hero_focus') ?></span>
                </h1>
                <p class="hero-subtitle">
                    <?= __('buywise_hero_desc') ?>
                </p>
                <div class="d-flex flex-wrap justify-content-center justify-content-lg-start btn-group-hero">
                    <a href="login.php" class="btn hero-btn-primary">
                        <i class="fas fa-rocket me-2"></i><?= __('get_started') ?>
                    </a>
                    <a href="#categories" class="btn hero-btn-outline">
                        <i class="fas fa-compass me-2"></i><?= __('browse_categories') ?>
                    </a>
                </div>
            </div>

            <!-- Right: Hero Image -->
            <div class="col-lg-6 text-center mt-5 mt-lg-0">
                <div class="hero-image-container">
                    <img src="img/BuyWiseHero.png" alt="BuyWise" class="hero-img img-fluid" loading="lazy">
                </div>
            </div>
        </div>
    </div>
</section>

<?php if (!empty($sliderImages)): ?>

<!-- 🇯🇴 Jordanian-Themed Local Products Slider -->
<section class="featured-slider-section py-5 position-relative" 
         style="background: linear-gradient(to right, #000, #fff, #007a3d); overflow: hidden;">
    
    <!-- Jordan Pattern Overlay + Filter -->
    <div class="slider-bg-decoration" 
         style="
            background: url('img/jordan-pattern.png') repeat;
            background-size: 280px;
            opacity: 0.25;
            mix-blend-mode: multiply;
            filter: contrast(1.3) brightness(1.2);
            position: absolute;
            inset: 0;
            z-index: 0;">
    </div>

    <!-- Content Container -->
    <div class="container position-relative" style="z-index: 1;">

        <!-- Section Header -->
        <div class="text-center mb-5">
            <div class="section-badge mx-auto mb-3 d-inline-flex align-items-center gap-2 px-3 py-2 rounded-pill shadow-sm" style="background-color: #c8102e; color: white; font-weight: bold;">
                <img src="img/flag-jordan.png" alt="Jordan Flag" width="28" height="20" style="border-radius: 2px;">
                <span><?= __('local_jordanian') ?? 'Jordanian Local Picks' ?></span>
            </div>
            <h2 class="section-title mb-3 text-black"> <?= __('featured_products') ?? 'Featured Products' ?></h2>
            <p class="section-subtitle mx-auto text-black" style="max-width: 600px;">
                <?= __('featured_desc_local') ?? 'Explore authentic local Jordanian products proudly made and reviewed by the community.' ?>
            </p>

        </div>

        <!-- Slider Container -->
        <div class="slider-container position-relative">
            <div class="slider-wrapper">
                <div class="slider-track d-flex flex-nowrap gap-4">
                    <?php foreach ($sliderImages as $index => $image): ?>
                    <div class="slider-item" data-index="<?= $index ?>">
                        <div class="product-card shadow-lg border border-light rounded-3 overflow-hidden bg-white position-relative">
                            <!-- Product Image -->
                            <div class="product-image-wrapper position-relative">
                                <a href="Products.php?ProductID=<?= urlencode($image['id']) ?>" class="product-link d-block">
                                    <img src="<?= htmlspecialchars($image['path']) ?>" 
                                         alt="<?= htmlspecialchars($image['name'] ?? 'Product Image') ?>" 
                                         class="product-image w-100"
                                         style="aspect-ratio: 1/1; object-fit: cover; border-bottom: 3px solid #c8102e;"
                                         loading="<?= $index < 4 ? 'eager' : 'lazy' ?>">
                                    <div class="product-overlay d-flex align-items-center justify-content-center"> <!-- display flex من بوتستراب وهي اختصار ل -->

                                        <div class="overlay-content text-white text-center">
                                            <i class="fas fa-eye overlay-icon mb-2"></i>
                                            <span class="overlay-text"><?= __('view_product') ?? 'View Product' ?></span>
                                        </div>
                                    </div>
                                </a>
                                <!-- Jordan Badge -->
                                <div class="product-badge position-absolute top-0 start-0 m-2 px-2 py-1 rounded-pill text-white fw-bold" style="background-color: #c8102e;">
                                    <?= __('made_in_jordan') ?? 'Made in Jordan' ?>
                                </div>
                            </div>

                            <!-- Product Info -->
                            <div class="product-info p-3">
                                <?php if (!empty($image['name'])): ?>
                                <h3 class="product-name mb-2" style="font-size: 1.1rem; color: #222;">
                                    <a href="Products.php?ProductID=<?= urlencode($image['id']) ?>" style="text-decoration: none; color: inherit;">
                                        <?= htmlspecialchars($image['name']) ?>
                                    </a>
                                </h3>
                                <?php endif; ?>

                                <?php if (isset($image['rating'])): ?>
                                <div class="product-rating d-flex align-items-center">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?= $i <= $image['rating'] ? 'text-warning' : 'text-muted' ?>"></i>  <!-- هون بطلع افريج حسب اللي بالكومنتات -->
                                    <?php endfor; ?>
                                    <?php if (!empty($image['review_count'])): ?>
                                        <span class="rating-count ms-2 text-muted small">(<?= $image['review_count'] ?>)</span>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>


<div class="testimonials-section">
    <div class="testimonials-bg-decoration"></div>
    <div class="container">
        <!-- Section Header -->
        <div class="testimonial-section-header text-center mb-5">
            <div class="section-badge">
                ⭐ <?= __('testimonials_title') ?>
            </div>
            <h1><?= __('testimonials_heading') ?></h1>
            <p><?= __('testimonials_subtitle') ?></p>
        </div>

        <!-- Testimonials Grid -->
        <div class="testimonial-slider-wrapper">
            <div class="testimonial-cards-grid" id="testimonialCarousel">
                <?php if (!empty($testimonials)): ?>
                    <?php 
                    $repeatedTestimonials = array_merge($testimonials, $testimonials); // smoother scroll هون بكررها مسشان لو كان عدد التعليقات قليل تتكرر مرتبن يعني ما يبين مافي تعليقات كتير
                    foreach ($repeatedTestimonials as $index => $testimonial): ?>
                    <div class="testimonial-card-item" style="--delay: <?= 0.1 + ($index * 0.1) ?>s;">
                        <div class="testimonial-card-top">
                            <div class="testimonial-user-info">
                                <img src="<?= htmlspecialchars(getAvatarUrl($testimonial['Avatar'], $testimonial['UserGender'])) ?>"
                                     alt="<?= htmlspecialchars($testimonial['UserName']) ?>"
                                     class="testimonial-avatar-img">
                                <div class="testimonial-user-details">
                                    <h3><?= ucwords(htmlspecialchars($testimonial['UserName'])) ?></h3>
                                    <div class="testimonial-meta">
                                        <span class="testimonial-badge <?= getBadgeClass($testimonial['badge']) ?>">
                                            <?= htmlspecialchars($testimonial['badge']) ?>
                                        </span>
                                        <!-- getBadgeClass  هاي لاسم الكلاس للديزاين فقط-->
                                        <!-- htmlspecialchars($testimonial['badge']) هاي هي اللي بتضيف اسم البادج من الداتا بيس -->
                                         
                                        <span class="testimonial-points">📈 <?= number_format($testimonial['points']) ?> pts</span>
                                    </div>
                                </div>
                            </div>
                            <div class="testimonial-rating-box">
                                <div class="testimonial-stars">
                                    <?= generateStarRating($testimonial['Rating']) ?>
                                </div>
                                <div class="testimonial-quality">
                                    <?= __('quality') ?>: <?= $testimonial['QualityRating'] ?>/5
                                </div>
                            </div>
                            <div class="testimonial-quote-mark">"</div>
                        </div>
                        <div class="testimonial-card-bottom">
                            <div class="testimonial-product-details">
                                <h4 class="testimonial-product-name"><?= htmlspecialchars($testimonial['ProductName']) ?></h4>
                                <div class="testimonial-product-category">
                                    <?= htmlspecialchars($lang === 'ar' ? $testimonial['CategoryName_ar'] : $testimonial['CategoryName_en']) ?>
                                </div>
                            </div>
                            <p class="testimonial-comment-snippet">
                                <?= htmlspecialchars(truncateText(stripslashes($testimonial['CommentText']), 120)) ?>
                            </p>
                            <div class="testimonial-date">
                                <?= formatDate($testimonial['CommentDate']) ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-testimonials text-center">
                        <p><?= __('no_testimonials') ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>


<!-- Categories Section -->
<section id="categories" class="categories-section">
  <!-- <div class="categories-bg-decoration"></div> -->
  <div class="container">
    <div class="section-header">
      <div class="section-badge">
        🍿 <?= __('shop_by_category') ?>
      </div>
      <h2 class="section-title">
        <?= __('categories_title') ?>
      </h2>
    </div>

    <div class="categories-grid">
      <?php
      $nameField = ($lang === 'ar') ? 'CategoryName_ar' : 'CategoryName_en';
      $stmt = $con->prepare("SELECT CategoryID, $nameField AS CategoryName, CategoryImage FROM categories WHERE CategoryStatus = 1");
      if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0):
          $i = 0;
          while ($cat = $result->fetch_assoc()):
            $categoryImage = !empty($cat['CategoryImage']) ? htmlspecialchars($cat['CategoryImage']) : 'default-category.jpg';
            $delay = 0.1 + ($i * 0.1);
            ?>
            <div class="category-card" style="--delay: <?= $delay ?>s;">
                <div class="category-image-wrapper">
                    <img src="Admin/uploads/categories/<?= $categoryImage ?>" 
                        alt="<?= htmlspecialchars($cat['CategoryName']) ?>" 
                        loading="lazy" class="category-full-img">
                </div>
                <div class="category-info">
                    <a class="btn" href="Categories.php?Category=<?= urlencode($cat['CategoryName']) ?>">
                    <?= htmlspecialchars($cat['CategoryName']) ?>
                    </a>
                </div>
            </div>
            <?php $i++; endwhile;
        else:
          echo '<div class="col-12"><p class="lead">' . __('no_categories') . '</p></div>';
        endif;
        $stmt->close();
      } else {
        echo '<div class="col-12"><p class="lead">Error loading categories.</p></div>';
      }
      ?>
    </div>
  </div>
</section>

<!-- Trusted Companies Section -->
<section class="trusted-brands-section">
  <div class="brands-bg-decoration"></div>
  <div class="container">
    <div class="section-header">
      <div class="section-badge">
        ✨ <?= __('premium_partners') ?>
      </div>
      <h2 class="section-title">
        <?= __('trusted_brands') ?>
      </h2>
    </div>

    <div class="brands-grid">
      <?php
      $brands = $con->query("SELECT CompanyID, CompanyName, CompanyLogo FROM companies WHERE Verified = 1 LIMIT 6"); //نتائج كل الجدول
      $j = 0;
      while ($brand = $brands->fetch_assoc()): //نتيجة الصف الحالي من الجدول (يعني صف صف باخد)
        $companyID = (int) $brand['CompanyID'];
        $companyName = htmlspecialchars($brand['CompanyName']);
        $logoPath = $brand['CompanyLogo']; // already includes full relative path
        $absoluteLogoPath = realpath(__DIR__ . '/' . $logoPath); //ركب المسار الحالي الكلي مع مسار اللوجو عشان يقدر يتحقق منه كامل اذا موجود

$logo = (!empty($logoPath) && $absoluteLogoPath && file_exists($absoluteLogoPath)) ? $logoPath : 'img/ComDef.png';

        $delay = 0.1 + ($j * 0.1);
        ?>
        <div class="brand-card" style="--delay: <?= $delay ?>s;">
          <a href="PublicProfile.php?CompanyID=<?= $companyID ?>">
            <img src="<?= $logo ?>" alt="<?= $companyName ?>" class="company-logo">
            <h5><?= $companyName ?></h5>
            <span class="badge"><?= __('verified') ?></span>
          </a>
        </div>
      <?php $j++; endwhile; ?>
    </div>
  </div>
</section>
<!-- poooints -->
<section class="points-guide-section py-5" style="background: linear-gradient(135deg, #f8f9fa, #e9f5ec);">
  <div class="container">
    <!-- Header -->
    <div class="section-header text-center mb-5">
      <div class="section-badge mx-auto mb-3 d-inline-flex align-items-center gap-2 px-3 py-2 rounded-pill shadow-sm" style="background-color: #28a745; color: white; font-weight: bold;">
        🧮 <?= __('points_system') ?>
      </div>
      <h2 class="section-title fw-bold text-dark"><?= __('how_points_work') ?></h2>
      <p class="section-subtitle text-muted"><?= __('points_explanation_text') ?></p> <!-- لون رمادي فاتح -->

    </div>

    <!-- Points Table -->
    <div class="table-responsive">
      <table class="table table-bordered align-middle text-center shadow-sm" style="border-radius: 10px; overflow: hidden; background-color: #fff;">
        <thead class="table-success text-dark">
          <tr>
            <th><i class="fas fa-bolt me-1"></i> <?= __('action') ?></th>
            <th><i class="fas fa-box-open me-1"></i> <?= __('regular_product') ?></th>
            <th><i class="fas fa-map-marker-alt me-1"></i> <?= __('local_product') ?></th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><?= __('submit_review') ?></td>
            <td><span class="badge fs-6"style="background-color:#ffddd2 ;">+10</span></td>
            <td><span class="badge fs-6" style="background-color:#e29578 ;">+20</span></td>

          </tr>
          <tr>
            <td><?= __('post_comment') ?></td>
            <td><span class="badge  fs-6" style="background-color:#ffddd2 ;">+3</span></td>
            <td><span class="badge  fs-6" style="background-color:#e29578 ;">+6</span></td>
          </tr>
          <tr>
            <td><?= __('receive_like') ?></td>
            <td><span class="badge fs-6" style="background-color:#ffddd2 ;">+2</span></td>
            <td><span class="badge fs-6" style="background-color:#e29578 ;">+4</span></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Badges Section -->
    <div class="text-center mt-5">
      <h4 class="fw-bold mb-3 text-dark"><?= __('badges_based_on_points') ?></h4>
      <div class="row justify-content-center g-4">
        <div class="col-md-3 col-sm-6">
          <div class="card border-0 shadow-sm p-3 text-center" style="background-color: #f0f0f0; border-left: 5px solid #6c757d;">
            <h5><i class="fas fa-user me-2 text-secondary"></i>Normal</h5>
            <p class="mb-0 text-muted"><?= __('badge_normal_range') ?></p>
          </div>
        </div>
        <div class="col-md-3 col-sm-6">
          <div class="card border-0 shadow-sm p-3 text-center" style="background-color: #f9f9f9; border-left: 5px solid #007bff;">
            <h5><i class="fas fa-user-check me-2 text-primary"></i>Professional</h5>
            <p class="mb-0 text-muted"><?= __('badge_professional_range') ?></p>
          </div>
        </div>
        <div class="col-md-3 col-sm-6">
          <div class="card border-0 shadow-sm p-3 text-center" style="background-color: #fefefe; border-left: 5px solid #ffc107;">
            <h5><i class="fas fa-user-tie me-2 text-warning"></i>Expert</h5>
            <p class="mb-0 text-muted"><?= __('badge_expert_range') ?></p>
          </div>
        </div>
        <div class="col-md-3 col-sm-6">
          <div class="card border-0 shadow-sm p-3 text-center" style="background-color: #fff; border-left: 5px solid #28a745;">
            <h5><i class="fas fa-crown me-2 text-success"></i>Legend</h5>
            <p class="mb-0 text-muted"><?= __('badge_legend_range') ?></p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>


<!-- About BuyWise Section -->
<section class="categories-section">
  <div class="container">
    <div class="section-header">
      <div class="section-badge">
        ℹ️ <?= __('about_badge') ?>
      </div>
      <h2 class="section-title">
        <?= __('about_buywise_title') ?>
      </h2>
    </div>

    <div class="row align-items-center g-5">
      <!-- Video Column -->
      <div class="col-lg-6">
        <div class="video-wrapper position-relative rounded overflow-hidden shadow-sm">
          <video class="w-100 rounded" autoplay muted loop playsinline aria-label="<?= __('about_buywise_title') ?>">
            <source src="img/us.mp4" type="video/mp4">
            <?= htmlspecialchars(__('video_not_supported')) ?>
          </video>
        </div>
      </div>

      <!-- Text Column -->
      <div class="col-lg-6">
        <p class="lead" style="color: #555;">
          <?= __('about_buywise_paragraph') ?>
        </p>
      </div>
    </div>
  </div>
</section>


    <button id="back-to-top" class="back-to-top" aria-label="<?= htmlspecialchars(__('back_to_top')) ?>">
        <i class="fa fa-arrow-up"></i>
    </button>

    <?php 
    if (file_exists('footer.php')) {
        include 'footer.php'; 
    }
    ?>
    
<script>
// Smooth horizontal scrolling for the slider
document.addEventListener('DOMContentLoaded', function() {
    const sliderWrapper = document.querySelector('.slider-wrapper');
    let isScrolling = false;
    
    sliderWrapper.addEventListener('wheel', function(e) {
        if (Math.abs(e.deltaX) > Math.abs(e.deltaY)) return;
        
        e.preventDefault();
        const scrollAmount = e.deltaY * 2;
        this.scrollLeft += scrollAmount;
    });
    
    // Add touch/drag scrolling for mobile
    let startX;
    let scrollLeft;
    
    sliderWrapper.addEventListener('mousedown', function(e) {
        isScrolling = true;
        startX = e.pageX - this.offsetLeft;
        scrollLeft = this.scrollLeft;
        this.style.cursor = 'grabbing';
    });
    
    sliderWrapper.addEventListener('mouseleave', function() {
        isScrolling = false;
        this.style.cursor = 'grab';
    });
    
    sliderWrapper.addEventListener('mouseup', function() {
        isScrolling = false;
        this.style.cursor = 'grab';
    });
    
    sliderWrapper.addEventListener('mousemove', function(e) {
        if (!isScrolling) return;
        e.preventDefault();
        const x = e.pageX - this.offsetLeft;
        const walk = (x - startX) * 2;
        this.scrollLeft = scrollLeft - walk;
    });
});

  document.addEventListener('DOMContentLoaded', function () {
    const categoryCards = document.querySelectorAll('.category-card');
    const brandCards = document.querySelectorAll('.brand-card');

    [...categoryCards, ...brandCards].forEach((card, index) => {
      card.style.animationPlayState = 'paused';
    });

    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.style.animationPlayState = 'running';
        }
      });
    }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });

    [...categoryCards, ...brandCards].forEach(card => observer.observe(card));
  });

  document.addEventListener("DOMContentLoaded", function() {
    const btn = document.getElementById("back-to-top");

    window.addEventListener("scroll", () => {
        if (window.scrollY > 300) {
            btn.style.display = "block";
        } else {
            btn.style.display = "none";
        }
    });

    btn.addEventListener("click", () => {
        window.scrollTo({ top: 0, behavior: "smooth" });
    });
});

</script>

</body>
</html>