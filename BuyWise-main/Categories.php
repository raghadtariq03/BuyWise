<?php
require_once 'config.php';

// === Constants for Pagination & Sorting ===
define('PRODUCTS_PER_PAGE', 8);
define('PAGINATION_ADJACENTS', 1); //يحدد عدد الصفحات المجاورة التي يتم عرضها بجانب الصفحة الحالية في واجهة الترقيم.
define('DEFAULT_SORT', 'newest');
define('ALLOWED_SORT_OPTIONS', ['newest', 'oldest', 'az', 'za', 'rating_high', 'rating_low']);
define('ALLOWED_SOURCE_FILTERS', ['all', 'company', 'user']);

// Category Products Controller
class CategoryProductsController {
    private $db; //لحفظ الاتصال بقاعدة البيانات
    private $lang; //لحفظ اللغة الحالية المستخدمة (عربي/إنجليزي)

    //الكنستركتر هي دالة تُستدعى تلقائيًا عند إنشاء كائن من هذا الكلاس.
    public function __construct($database, $language) {
        $this->db = $database;
        $this->lang = $language;
    }

    // Validate category using name or ID
    public function validateCategory($categoryParam, $categoryId = null) {
        try {
            if (!empty($categoryParam)) {
                $categoryParam = trim($categoryParam);
                $stmt = $this->db->prepare("SELECT CategoryID, CategoryName_ar, CategoryName_en FROM categories WHERE BINARY CategoryName_ar = ? OR BINARY CategoryName_en = ?"); //بايري تجعل المقارنه حساسه للاحرف
                $stmt->bind_param("ss", $categoryParam, $categoryParam);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    return ['id' => (int)$row['CategoryID'], 'name' => $this->lang === 'ar' ? $row['CategoryName_ar'] : $row['CategoryName_en']];
                }
            } elseif ($categoryId && is_numeric($categoryId)) {
                $categoryId = intval($categoryId);
                $nameField = $this->getCategoryNameField();
                $stmt = $this->db->prepare("SELECT {$nameField} as CategoryName FROM categories WHERE CategoryID = ?");
                $stmt->bind_param("i", $categoryId);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    return ['id' => $categoryId, 'name' => $row['CategoryName']];
                }
            }
            return null;
        } catch (Exception $e) {
            $this->logError("Category validation error: " . $e->getMessage());
            return null;
        }
    }

    // Count all products for the given category and filters
    public function getTotalProductsCount($categoryId, $filters = []) {
        try {
            $queryBuilder = $this->buildQueryComponents($filters);
            $countQuery = "
                SELECT COUNT(*) as total FROM (
                    SELECT cp.ProductID FROM company_products cp 
                    WHERE cp.ProductStatus = 1 AND cp.CategoryID = ? {$queryBuilder['company_search']} {$queryBuilder['company_condition']}
                    UNION ALL
                    SELECT p.ProductID FROM products p 
                    WHERE p.ProductStatus = 1 AND (p.IsFake = 0 OR p.IsFake IS NULL) AND p.CategoryID = ? {$queryBuilder['user_search']} {$queryBuilder['user_condition']}
                ) as all_products
            ";
            $stmt = $this->db->prepare($countQuery);
            $params = array_merge([$categoryId], $queryBuilder['company_params'], [$categoryId], $queryBuilder['user_params']);
            $types = 'i' . str_repeat('s', count($queryBuilder['company_params'])) . 'i' . str_repeat('s', count($queryBuilder['user_params']));
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_assoc()['total'] ?? 0;
        } catch (Exception $e) {
            $this->logError("Count query error: " . $e->getMessage());
            return 0;
        }
    }

    // Retrieve products with filters and pagination
    public function getProducts($categoryId, $filters = [], $page = 1, $limit = PRODUCTS_PER_PAGE) {
        try {
            $queryBuilder = $this->buildQueryComponents($filters);
            $offset = ($page - 1) * $limit; //نطبق هاي المعادله بكل مره لنحسب من أي منتج يبدأ العرض (حسب رقم الصفحة).
            //الاوفسيت هو اول رقم منتج بكل بداية صفحه اللي رح يبلش منه يعني
            //الليميت هو 8 لكل صفحه احنا حددناه فوق
            //البيج تتغير حسب احنا بأي صفحه
            
            $productQuery = "
                SELECT * FROM (
                    SELECT p.ProductID, p.ProductName, p.ProductImage,
                        COALESCE((SELECT AVG(QualityRating) FROM comments c WHERE c.ProductID = p.ProductID), 0) AS AverageRating,
                        'user' AS Source
                    FROM products p
                    WHERE p.ProductStatus = 1 AND (p.IsFake = 0 OR p.IsFake IS NULL) AND p.CategoryID = ? {$queryBuilder['user_search']} {$queryBuilder['user_condition']}
                    UNION ALL
                    SELECT cp.ProductID, cp.ProductName, cp.ProductImage,
                        COALESCE((SELECT AVG(QualityRating) FROM comments c WHERE c.ProductID = cp.ProductID), 0) AS AverageRating,
                        'company' AS Source
                    FROM company_products cp
                    WHERE cp.ProductStatus = 1 AND cp.CategoryID = ? {$queryBuilder['company_search']} {$queryBuilder['company_condition']}
                ) AS filtered_products
                ORDER BY 
                    CASE WHEN Source = 'company' THEN 0 ELSE 1 END,
                    {$queryBuilder['secondary_order']}
                LIMIT ? OFFSET ? 
            ";//COALESCE بترجع اول قيمه غير فارغه يعني لا تساوي نل
            // (case) هي فنكشن و تعني افحص شرط وأرجع قيمة بناءً على النتيجة".
            //اول شي برجع فوق منتجات الشركه بعدين العاديين بعدين بصير بناءا عالسيكوندري اوردر اللي هم حسب الفلتر وهالاشياء
            //آخر سطر عم يحدد الليميت بكل صفحه و الاوفسيت من اي برودكت يبلش بكل صفحه

            $stmt = $this->db->prepare($productQuery);
            $params = array_merge(
                [$categoryId], $queryBuilder['user_params'], 
                [$categoryId], $queryBuilder['company_params'], 
                [$limit, $offset]
            );
            $types = 'i' . str_repeat('s', count($queryBuilder['user_params'])) . 'i' . str_repeat('s', count($queryBuilder['company_params'])) . 'ii';
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            return $stmt->get_result();
        } catch (Exception $e) {
            $this->logError("Products query error: " . $e->getMessage());
            return null;
        }
    }

    // Build filtering and sorting SQL components
    private function buildQueryComponents($filters = []) {
        $searchTerm = $filters['search'] ?? '';
        $sourceFilter = in_array($filters['source'] ?? '', ALLOWED_SOURCE_FILTERS) ? $filters['source'] : 'all';
        $sortOption = in_array($filters['sort'] ?? '', ALLOWED_SORT_OPTIONS) ? $filters['sort'] : DEFAULT_SORT;

        $companySearch = $userSearch = '';
        $companyParams = $userParams = [];

        if (!empty($searchTerm)) {
            $companySearch = ' AND (cp.ProductName LIKE ? OR cp.ProductName LIKE ?)';
            $userSearch = ' AND (p.ProductName LIKE ? OR p.ProductName LIKE ?)';
            $companyParams = [$searchTerm . '%', '% ' . $searchTerm . '%'];
            $userParams = [$searchTerm . '%', '% ' . $searchTerm . '%'];
        }

        $companyCondition = ($sourceFilter === 'user') ? ' AND 1=0' : ''; // يُستخدم لإلغاء نتائج جهة معينة (يمنع إرجاع نتائج).
        $userCondition = ($sourceFilter === 'company') ? ' AND 1=0' : ''; //إذا طلب المستخدم يوزرز اونلي نضع شرط مستحيل للشركة. و العكس صحيح

        // Updated ordering - removed the main ORDER BY, now using secondary_order
        $secondaryOrder = match($sortOption) {
            'oldest' => 'ProductID ASC',
            'az' => 'ProductName ASC',
            'za' => 'ProductName DESC',
            'rating_high' => 'AverageRating DESC',
            'rating_low' => 'AverageRating ASC',
            default => 'ProductID DESC'
        };

        return [
            'company_search' => $companySearch,
            'user_search' => $userSearch,
            'company_params' => $companyParams,
            'user_params' => $userParams,
            'company_condition' => $companyCondition,
            'user_condition' => $userCondition,
            'secondary_order' => $secondaryOrder
        ];
    }

    private function getCategoryNameField() {
        return $this->lang === 'ar' ? 'CategoryName_ar' : 'CategoryName_en';
    }

    private function logError($message) {
        error_log("[CategoryProductsController] " . $message);
    }
}


class Utils {
    
    //تحوّل أي نص إلى نص آمن للعرض في اتش تي ام ال
    public static function sanitizeOutput($value) {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
  
    public static function getProductImagePath($imageName) {
        if (empty($imageName)) {
            return 'img/no-image.png';
        }
        
        $safeName = basename($imageName); // تمنع الوصول لمجلدات غير مسموح بها (تزيل المسارات متل ../ وتأخذ الاسم الاخير فقط)
        $imagePath = "uploads/products/" . $safeName;
        
        
        if (file_exists($imagePath)) {
            return $imagePath;
        }
        
        return 'img/no-image.png';
    }
    
    //تقوم بـ تنظيف والتحقق من مدخلات المستخدم، سواء كانت أرقام أو نصوص.
    //يتحقق انو السترينغ ما يتعدى ال255 حرف
    public static function validateInput($input, $type = 'string', $maxLength = 255) {
        if ($input === null || $input === '') {
            return null;
        }
        
        switch ($type) {
            case 'int':
                return filter_var($input, FILTER_VALIDATE_INT); //بتحقق ازا هو جد انتجر 
            case 'string':
                $sanitized = trim(strip_tags($input)); // و ازا كان سترينغ بقص الزوائد و بشيل منه تاغات الاتش تي ام ال والجافا سكربت
                return strlen($sanitized) <= $maxLength ? $sanitized : substr($sanitized, 0, $maxLength); //إذا طول النص ضمن الحد المسموح به  اما اذا اطول يقصه
            default:
                return $input;
        }
    }
   
    //لتنظيف اليو ار ال بشكل عام من اي زوائد
    public static function buildUrl($baseUrl, $params = []) {
        //يحذف العناصر الفارغة من المعاملات
        $cleanParams = array_filter($params, function($value) {
            return $value !== null && $value !== '';
        });
        
        //يتجنب إضافة ? باليو ار ال إذا ما في معاملات
        if (empty($cleanParams)) {
            return $baseUrl;
        }
        //key=value&...ينشئ سلسلة استعلام  
        return $baseUrl . '?' . http_build_query($cleanParams);
    }
}


// Pagination Class
class Pagination {
    //بيس باراميترز هي الفلاتر التي تبقى مع كل صفحه
    public static function generate($currentPage, $totalPages, $baseParams = [], $adjacents = PAGINATION_ADJACENTS) {
        if ($totalPages <= 1) return '';
        
        $html = '<div class="pagination-container d-flex justify-content-center mt-4">';
        $html .= '<ul class="pagination custom-pagination">';
        
        // Previous button
        $prevDisabled = ($currentPage <= 1) ? 'disabled' : '';
        $prevPage = max(1, $currentPage - 1);
        $prevParams = array_merge($baseParams, ['page' => $prevPage]);
        $prevUrl = Utils::buildUrl('', $prevParams);
        
        $html .= "<li class='page-item {$prevDisabled}'>";
        $html .= "<a class='page-link' href='{$prevUrl}'>&laquo;</a></li>";
        
        // Page numbers
        // ستارت و اند بيج يحددون الصفحات الوسطية الظاهرة
        $startPage = max(2, $currentPage - $adjacents);
        $endPage = min($totalPages - 1, $currentPage + $adjacents);
        
        // First page
        if ($totalPages >= 1) {
            $firstParams = array_merge($baseParams, ['page' => 1]);
            $firstUrl = Utils::buildUrl('', $firstParams);
            $activeFirst = ($currentPage == 1) ? 'active' : ''; //تظهر دائمًا، وإذا المستخدم في صفحة 1، يتم تفعيلها آكتف
            $html .= "<li class='page-item {$activeFirst}'><a class='page-link' href='{$firstUrl}'>1</a></li>";
            
            if ($startPage > 2) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';//إذا في فجوة بين أول صفحة وبداية الصفحات الوسطيه يعرض ال3 نقاط)
            }
            
            //يعرض الصفحات القريبة من الصفحة الحالية
            for ($i = $startPage; $i <= $endPage; $i++) {
                $pageParams = array_merge($baseParams, ['page' => $i]);
                $pageUrl = Utils::buildUrl('', $pageParams); //الفائده منها اها يحافظ على الفلاتر اثناء التقل بين الصفحات
                $active = ($currentPage == $i) ? 'active' : '';
                $html .= "<li class='page-item {$active}'><a class='page-link' href='{$pageUrl}'>{$i}</a></li>";
            }
            
            //النقاط الأخيرة (إذا في فجوة قبل آخر صفحة)
            if ($endPage < $totalPages - 1) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            
            if ($totalPages > 1) {
                $lastParams = array_merge($baseParams, ['page' => $totalPages]);
                $lastUrl = Utils::buildUrl('', $lastParams); //تعرض دائمًا، مع تفعيلها آكتف إذا كانت الصفحة الحالية هي الأخيرة.
                $activeLast = ($currentPage == $totalPages) ? 'active' : '';
                $html .= "<li class='page-item {$activeLast}'><a class='page-link' href='{$lastUrl}'>{$totalPages}</a></li>";
            }
        }
        
        // Next button
        $nextDisabled = ($currentPage >= $totalPages) ? 'disabled' : '';
        $nextPage = min($totalPages, $currentPage + 1); // الهدف منها ما نخلي الرقم يتعدى عدد التوتال بيجز كلهم 
        //البيس باراميترز فيها كل الفلاتر اللي استعملها المستخدم فادمجها مع الصفحه التاليه الجديده
        $nextParams = array_merge($baseParams, ['page' => $nextPage]);//نجهز الرابط مع كل الفلاتر الأخرى ونجدد بيج لتصير الصفحه التاليه
        $nextUrl = Utils::buildUrl('', $nextParams);
        
        $html .= "<li class='page-item {$nextDisabled}'>"; //إذا الزر معطل، يُضاف له كلاس ديسيبلد
        $html .= "<a class='page-link' href='{$nextUrl}'>&raquo;</a></li>";//نضيف زر "التالي" إلى شريط الترقيم.


        
        $html .= '</ul></div>';
        
        return $html;
    }
}

// Initialize language handling
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'ar'])) {
    $_SESSION['lang'] = $_GET['lang'];
    
    $query = $_GET;
    unset($query['lang']); // نحذف اللانج من اليو ار ال حتى ما تضل ظاهره
    
    //اعادة بناء الرابط بدون لانج
    $queryString = http_build_query($query);
    $redirect = strtok($_SERVER["REQUEST_URI"], '?');
    
    // دمج الاستعلام الجديد لو في فلاتر وهيك
    if (!empty($queryString)) {
        $redirect .= '?' . $queryString;
    }
    
    //نوجّه المستخدم للرابط الجديد بدون لانج لكن باللغه الجديده المحفوظه في السيشن 
    header("Location: $redirect");
    exit();
}

$lang = $_SESSION['lang'] ?? 'en';
$dir = $lang === 'ar' ? 'rtl' : 'ltr';
require_once "lang.php";

// Initialize controller
$controller = new CategoryProductsController($con, $lang); 

// Validate and get category
$categoryData = null;
$CategoryID = 0;
$CategoryName = '';

//تمرير اسم الكاتيجوري من الرابط و التحقق منها
if (!empty($_GET['Category'])) {
    $categoryData = $controller->validateCategory($_GET['Category']);
    if ($categoryData) {
        $CategoryID = $categoryData['id'];
        $CategoryName = $categoryData['name'];
        
        // Redirect to clean URL with CategoryID
        $currentParams = $_GET;
        unset($currentParams['Category']); //نجذف كاتيجوري من الرابط
        $currentParams['CategoryID'] = $CategoryID; //نضع بدالها كاتيجوري اي دي
        $queryString = http_build_query($currentParams); //نُعيد بناء الرابط  
        header("Location: " . strtok($_SERVER["PHP_SELF"], '?') . "?$queryString"); //ونعيد توجيه المستخدم لرابط نظيف
        exit();
    }
} elseif (isset($_GET['CategoryID'])) {
    $categoryId = Utils::validateInput($_GET['CategoryID'], 'int');//تحقق من الرقم
    if ($categoryId) {
        $categoryData = $controller->validateCategory(null, $categoryId);//نتحقق من وجود الفئه، نرسل نل للاسم ونعتمد فقط على كاتيجوري اي دي للتحقق
        if ($categoryData) {
            $CategoryID = $categoryData['id'];
            $CategoryName = $categoryData['name'];
        }
    }
}

// Redirect to home if no valid category
if ($CategoryID === 0 || empty($CategoryName)) {
    echo '<script>location.href="Home.php";</script>';
    exit();
}

// Get and validate filters
$filters = [
    'search' => Utils::validateInput($_GET['search'] ?? '', 'string', 100), //كلمة البحث عن المنتجات ،سترينج هو نوع البيانات المتوقع و 100 هي اقصى طول مسموح للنص
    'sort' => Utils::validateInput($_GET['sort'] ?? DEFAULT_SORT, 'string', 20),//ترتيب النتائج (الأحدث، الأعلى تقييمًا...)
    'source' => Utils::validateInput($_GET['source'] ?? 'all', 'string', 20) //مصدر المنتجات (user, company, all)
];

// Get pagination data
$currentPage = Utils::validateInput($_GET['page'] ?? 1, 'int') ?: 1; //يأخذ رقم الصفحة من الرابط، واذا غير صالح او فارغ حطله 1 تلقائيًا
$totalProducts = $controller->getTotalProductsCount($CategoryID, $filters);
$totalPages = ceil($totalProducts / PRODUCTS_PER_PAGE); //تقرب للأعلى
$currentPage = max(1, min($totalPages, $currentPage)); //يمنع القيم الخاطئة متل صفحه اقل من 1 او اعلى من عدد الصفحات الموجود

// Get products
$products = $controller->getProducts($CategoryID, $filters, $currentPage, PRODUCTS_PER_PAGE);

// Prepare pagination parameters بنمررها لباجينيشن جينيريت حتى يتم الحفاظ على كل الفلاتر عند تغيير الصفحة
$paginationParams = [
    'CategoryID' => $CategoryID,
    'search' => $filters['search'],
    'sort' => $filters['sort'],
    'source' => $filters['source']
];
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">

<head>
    <meta charset="utf-8">
    <title><?= Utils::sanitizeOutput($CategoryName) ?> - BuyWise</title>
    <link rel="icon" href="img/favicon.ico">
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Rubik&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.0/css/all.min.css" rel="stylesheet">
    <link href="Categories.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <?php include("header.php"); ?>

    <main class="container py-5 mt-5">
        <div class="breadcrumb-wrapper">
            <div class="container">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="Home.php"><i class="fas fa-home me-1"></i> <?= __('home') ?></a></li>
                        <li class="breadcrumb-item"><a href="Home.php#categories"><i class="fas fa-layer-group me-1"></i><?= __('categories_title') ?></a></li>
                        <li class="breadcrumb-item active" aria-current="page"><i class="fas fa-folder me-1"></i> <?= Utils::sanitizeOutput($CategoryName) ?></li>
                    </ol>
                </nav>
            </div>
        </div>

        <div class="main-spacing-above">
            <!-- Search and Filter Form -->
            <form method="get" class="row mb-4">
                <input type="hidden" name="CategoryID" value="<?= $CategoryID ?>">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="<?= __('search_products') ?>" value="<?= Utils::sanitizeOutput($filters['search'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <select name="sort" class="form-control">
                        <option value="newest" <?= $filters['sort'] === 'newest' ? 'selected' : '' ?>><?= __('sort_newest') ?></option>
                        <option value="oldest" <?= $filters['sort'] === 'oldest' ? 'selected' : '' ?>><?= __('sort_oldest') ?></option>
                        <option value="rating_high" <?= $filters['sort'] === 'rating_high' ? 'selected' : '' ?>><?= __('sort_rating_high') ?></option>
                        <option value="rating_low" <?= $filters['sort'] === 'rating_low' ? 'selected' : '' ?>><?= __('sort_rating_low') ?></option>
                        <option value="az" <?= $filters['sort'] === 'az' ? 'selected' : '' ?>><?= __('sort_az') ?></option>
                        <option value="za" <?= $filters['sort'] === 'za' ? 'selected' : '' ?>><?= __('sort_za') ?></option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="source" class="form-control">
                        <option value="all" <?= $filters['source'] === 'all' ? 'selected' : '' ?>><?= __('filter_all_sources') ?></option>
                        <option value="company" <?= $filters['source'] === 'company' ? 'selected' : '' ?>><?= __('filter_company') ?></option>
                        <option value="user" <?= $filters['source'] === 'user' ? 'selected' : '' ?>><?= __('filter_user') ?></option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><?= __('filter') ?></button> 
                    <!--الباك اند لا يتعامل مع الزر نفسه، بل يتعامل مع البيانات المرسلة من النموذج-->
                </div>
            </form>

<!-- Products Grid -->
<div class="products-grid">
    <?php
    if ($products && $products->num_rows > 0) {
        while ($row = $products->fetch_assoc()) {
            $ProductID = (int)$row['ProductID'];
            //يوتيلز هو اسم كلاس عرفناه فوق وحطينا فيو اكتر فنكشنز مكرر استخدامهم عنا
            //: : للوصول الى الفنكشنز و المتغيرات اللي داخل كلاس يوتيلز
            $ProductName = Utils::sanitizeOutput($row['ProductName']);
            $ProductImage = Utils::getProductImagePath($row['ProductImage']);
            $Source = $row['Source'];
            $AverageRating = floatval($row['AverageRating']);

            echo '<div class="card-category">
                <div class="card-content-category">
                    <div class="card-image-container">
                        <img src="' . Utils::sanitizeOutput($ProductImage) . '" alt="' . $ProductName . '" onerror="this.src=\'img/ProDef.png\'">
                        ' . ($Source === 'company' ? '<div class="sponsored-badge">' . __('sponsored') . '</div>' : '') . '
                    </div>
                </div>
                <div class="card-header-category">
                    <span>' . $ProductName . '</span>
                </div>
                <div class="card-footer-category">';

 if ($Source !== 'company') {
    echo '<div class="product-rating mb-2">';

    $avg = floatval($AverageRating);
    $fullStars = floor($avg);
    $remainder = $avg - $fullStars;
    $emptyStars = 5 - ceil($avg);

    // Full stars
    for ($i = 0; $i < $fullStars; $i++) {
        echo '<i class="fas fa-star text-warning"></i>';
    }

    // Partial star with gradient fill (direction depends on language)
    if ($remainder > 0) {
        $percent = round($remainder * 100);
        echo '<i class="fas fa-star text-warning" style="background: linear-gradient(to ' . ($lang === 'ar' ? 'left' : 'right') . ', #ffc107 ' . $percent . '%, #e4e5e9 ' . $percent . '%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;"></i>';
    }

    // Empty stars
    for ($i = 0; $i < $emptyStars; $i++) {
        echo '<i class="far fa-star text-muted"></i>';
    }

    echo ' <span class="ms-1 text-muted small">(' . number_format($avg, 1) . ')</span>';//تأخذ الرقم وتعرضه بفاصلة عشرية واحدة لتوحيد شكل التقييم
    echo '</div>';
}


            echo '<a class="btnn full-width" href="Products.php?ProductID=' . $ProductID . '">' . __('view') . '</a>
                </div>
            </div>';
        }
    } else {
        echo '<div class="col-12 text-center py-5">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <h4>' . __('no_products_found') . '</h4>
                <p class="text-muted">' . __('try_different_search') . '</p>
              </div>';
    }
    ?>
</div>
            
            <!-- Pagination -->
            <?php echo Pagination::generate($currentPage, $totalPages, $paginationParams); ?>
            
        </div>
    </main>

    <?php if (isset($_SESSION['type']) && $_SESSION['type'] == 2): ?>
        <a href="AddNewProducts.php?return=category&CategoryID=<?= $CategoryID ?>" class="fixed-add-button" title="<?= __('add_product') ?>">
            <i class="fas fa-plus"></i>
        </a>
    <?php endif; ?>

        
    <button id="back-to-top" class="back-to-top" aria-label="<?= htmlspecialchars(__('back_to_top')) ?>">
        <i class="fa fa-arrow-up"></i>
    </button>
    
    <script>
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