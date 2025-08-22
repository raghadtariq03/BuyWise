<?php
require_once 'config.php'; // Includes session, lang, and all setup

//يبدأ التخزين المؤقت للآوتبوت ، لتأخير عرض المحتوى حتى يتم إنهاؤه 
ob_start();

// Determine user type
$type = $_SESSION['type'] ?? null;
$isAuthenticated = in_array($type, [1, 2, 3, 'company']);
$isAdmin = $type === 1;
$isUser = $type === 2;
$isCompany = $type === 3 || $type === 'company';

// Get username or fallback
$username = $isCompany
    ? htmlspecialchars($_SESSION['CompanyName'] ?? __('company'))
    : ($isAuthenticated && isset($_SESSION['username'])  //ترجع قيمة ترو او فولس isset 
        ? htmlspecialchars($_SESSION['username'])
        : __('user'));


// Notification setup
$unreadCount = 0;
$notif_result = null;

if ($isAuthenticated && (($isCompany && isset($_SESSION['CompanyID'])) || (!$isCompany && isset($_SESSION['UserID'])))) {
    $UserID = $isCompany ? $_SESSION['CompanyID'] : $_SESSION['UserID'];

    // Fetch last 5 notifications with sender info
    // بجيب كل اعمدة جدول النوتيفيكيشنز و بضيف عليهم عمود اسمه اليوزرنيم و قيمته بحددها وحده من التلات هدول حسب اول وحده مش نل بلاقيها منهم
    $stmt = $con->prepare("
        SELECT n.*, 
            CASE
                WHEN n.sender_id IS NULL OR n.sender_id = 0 THEN 'BuyWise System'
                ELSE COALESCE(u.UserName, c.CompanyName, 'Unknown')
            END AS UserName,
            u.Avatar AS Avatar, u.UserGender AS Gender, c.CompanyLogo AS CompanyLogo
        FROM notifications n 
        LEFT JOIN users u ON n.sender_id = u.UserID 
        LEFT JOIN companies c ON n.sender_id = c.CompanyID 
        WHERE n.recipient_id = ? 
        ORDER BY n.created_at DESC  
        LIMIT 5
    "); //تفحص من اليسار لليمين للي بين قوسين و اول قيمه مش نل تاخدهاCOALESCE 
    // ترتيب الإشعارات بحيث تظهر الأحدث أولاً

    $stmt->bind_param("i", $UserID); //معناها رح يمرر للاستعلام اللي فوق اشي نوعه انتجر مكان الاستفهام 
    $stmt->execute();
    $notif_result = $stmt->get_result();

    // Count unread notifications
    $stmt_unread = $con->prepare("SELECT COUNT(*) as unread FROM notifications WHERE recipient_id = ? AND is_read = 0");
    $stmt_unread->bind_param("i", $UserID);
    $stmt_unread->execute();
    $unread_data = $stmt_unread->get_result()->fetch_assoc(); //بتخزن العدد كمصفوفه كي مع قيمه جمبه
    $unreadCount = $unread_data['unread'] ?? 0; //هون باخد فقط العدد من المصفوفه
}
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">

<head>
    <meta charset="UTF-8">
    <title>BuyWise</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Rubik&display=swap"
        rel="stylesheet">
    <link href="/product1/style.css" rel="stylesheet">
    <link href="/product1/header.css" rel="stylesheet">
</head>

<body>
    <header class="fixed-top">
        <nav class="navbar navbar-expand-lg h-navbar">
            <div class="container">

            <?php if (isset($_SESSION['type']) && $_SESSION['type'] == 2): ?>
                
                <!-- Upgrade button -->
                <button class="my-button" onclick="window.location.href='UpgradePlan.php'" style="<?= $dir === 'rtl' ? 'margin-right: 15px;' : 'margin-left: 15px;' ?>">
                    <?= __('upgrade') ?>
                    <div class="star-1">
                        <svg xmlns="http://www.w3.org/2000/svg" xml:space="preserve" version="1.1"
                            style="shape-rendering:geometricPrecision; text-rendering:geometricPrecision; image-rendering:optimizeQuality; fill-rule:evenodd; clip-rule:evenodd"
                            viewBox="0 0 784.11 815.53" xmlns:xlink="http://www.w3.org/1999/xlink">
                            <defs></defs>
                            <g id="Layer_x0020_1">
                                <metadata id="CorelCorpID_0Corel-Layer"></metadata>
                                <path class="fil0"
                                    d="M392.05 0c-20.9,210.08 -184.06,378.41 -392.05,407.78 207.96,29.37 371.12,197.68 392.05,407.74 20.93,-210.06 184.09,-378.37 392.05,-407.74 -207.98,-29.38 -371.16,-197.69 -392.06,-407.78z">
                                </path>
                            </g>
                        </svg>
                    </div>
                    <div class="star-2">
                        <svg xmlns="http://www.w3.org/2000/svg" xml:space="preserve" version="1.1"
                            style="shape-rendering:geometricPrecision; text-rendering:geometricPrecision; image-rendering:optimizeQuality; fill-rule:evenodd; clip-rule:evenodd"
                            viewBox="0 0 784.11 815.53" xmlns:xlink="http://www.w3.org/1999/xlink">
                            <defs></defs>
                            <g id="Layer_x0020_1">
                                <metadata id="CorelCorpID_0Corel-Layer"></metadata>
                                <path class="fil0"
                                    d="M392.05 0c-20.9,210.08 -184.06,378.41 -392.05,407.78 207.96,29.37 371.12,197.68 392.05,407.74 20.93,-210.06 184.09,-378.37 392.05,-407.74 -207.98,-29.38 -371.16,-197.69 -392.06,-407.78z">
                                </path>
                            </g>
                        </svg>
                    </div>
                    <div class="star-3">
                        <svg xmlns="http://www.w3.org/2000/svg" xml:space="preserve" version="1.1"
                            style="shape-rendering:geometricPrecision; text-rendering:geometricPrecision; image-rendering:optimizeQuality; fill-rule:evenodd; clip-rule:evenodd"
                            viewBox="0 0 784.11 815.53" xmlns:xlink="http://www.w3.org/1999/xlink">
                            <defs></defs>
                            <g id="Layer_x0020_1">
                                <metadata id="CorelCorpID_0Corel-Layer"></metadata>
                                <path class="fil0"
                                    d="M392.05 0c-20.9,210.08 -184.06,378.41 -392.05,407.78 207.96,29.37 371.12,197.68 392.05,407.74 20.93,-210.06 184.09,-378.37 392.05,-407.74 -207.98,-29.38 -371.16,-197.69 -392.06,-407.78z">
                                </path>
                            </g>
                        </svg>
                    </div>
                    <div class="star-4">
                        <svg xmlns="http://www.w3.org/2000/svg" xml:space="preserve" version="1.1"
                            style="shape-rendering:geometricPrecision; text-rendering:geometricPrecision; image-rendering:optimizeQuality; fill-rule:evenodd; clip-rule:evenodd"
                            viewBox="0 0 784.11 815.53" xmlns:xlink="http://www.w3.org/1999/xlink">
                            <defs></defs>
                            <g id="Layer_x0020_1">
                                <metadata id="CorelCorpID_0Corel-Layer"></metadata>
                                <path class="fil0"
                                    d="M392.05 0c-20.9,210.08 -184.06,378.41 -392.05,407.78 207.96,29.37 371.12,197.68 392.05,407.74 20.93,-210.06 184.09,-378.37 392.05,-407.74 -207.98,-29.38 -371.16,-197.69 -392.06,-407.78z">
                                </path>
                            </g>
                        </svg>
                    </div>
                    <div class="star-5">
                        <svg xmlns="http://www.w3.org/2000/svg" xml:space="preserve" version="1.1"
                            style="shape-rendering:geometricPrecision; text-rendering:geometricPrecision; image-rendering:optimizeQuality; fill-rule:evenodd; clip-rule:evenodd"
                            viewBox="0 0 784.11 815.53" xmlns:xlink="http://www.w3.org/1999/xlink">
                            <defs></defs>
                            <g id="Layer_x0020_1">
                                <metadata id="CorelCorpID_0Corel-Layer"></metadata>
                                <path class="fil0"
                                    d="M392.05 0c-20.9,210.08 -184.06,378.41 -392.05,407.78 207.96,29.37 371.12,197.68 392.05,407.74 20.93,-210.06 184.09,-378.37 392.05,-407.74 -207.98,-29.38 -371.16,-197.69 -392.06,-407.78z">
                                </path>
                            </g>
                        </svg>
                    </div>
                    <div class="star-6">
                        <svg xmlns="http://www.w3.org/2000/svg" xml:space="preserve" version="1.1"
                            style="shape-rendering:geometricPrecision; text-rendering:geometricPrecision; image-rendering:optimizeQuality; fill-rule:evenodd; clip-rule:evenodd"
                            viewBox="0 0 784.11 815.53" xmlns:xlink="http://www.w3.org/1999/xlink">
                            <defs></defs>
                            <g id="Layer_x0020_1">
                                <metadata id="CorelCorpID_0Corel-Layer"></metadata>
                                <path class="fil0"
                                    d="M392.05 0c-20.9,210.08 -184.06,378.41 -392.05,407.78 207.96,29.37 371.12,197.68 392.05,407.74 20.93,-210.06 184.09,-378.37 392.05,-407.74 -207.98,-29.38 -371.16,-197.69 -392.06,-407.78z">
                                </path>
                            </g>
                        </svg>
                    </div>
                </button>
                <?php endif; ?>

                <!-- Brand logo -->
                <a href="/product1/Home.php" class="modern-navbar-brand">
                    <img src="/product1/img/BuyWise.png" alt="BuyWise Logo" class="h-logo" id="brand-logo">
                </a>
                
                <!-- Mobile toggle -->
                <button class="navbar-toggler modern-navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#navbarNav">
                    <i class="fas fa-bars"></i>
                </button>
                
                <div class="collapse navbar-collapse" id="navbarNav">
                    <!-- Navigation actions -->
                    <div class="modern-nav-actions ms-auto">
                        <a href="/product1/Home.php" class=" modern-btn h-nav-link"><?= __('home') ?></a>

                        <!-- More dropdown -->
                        <div class="dropdown">
                            <button class="modern-btn dropdown-toggle h-nav-link" type="button"
                                data-bs-toggle="dropdown">
                                <i class="fas fa-ellipsis-h"></i>
                                <span class="d-none d-sm-inline"><?= __('more') ?></span>
                            </button>
                            <ul class="dropdown-menu modern-dropdown-menu">
                                <li><a class="dropdown-item modern-dropdown-item" href="/product1/Home.php#categories"><?= __('categories') ?></a></li>
                                <li><a class="dropdown-item modern-dropdown-item" href="/product1/UserRewards.php"><?= __('rewards_center') ?></a></li>
                                <li><a class="dropdown-item modern-dropdown-item" href="/product1/faq.php"><?= __('faq') ?></a></li>
                                <li><a class="dropdown-item modern-dropdown-item" href="/product1/policy.php"><?= __('policies') ?></a></li>
                            </ul>
                        </div>

                        <!-- Language toggle -->
                        <a href="#" onclick="switchLang('<?= $lang === 'ar' ? 'en' : 'ar' ?>'); return false;" class="modern-btn h-nav-link">
                            <i class="fas fa-language"></i>
                            <span class="d-none d-sm-inline"><?= $lang === 'ar' ? __('english') : __('arabic') ?></span>
                        </a>

                        <!-- Notifications -->
                        <?php if ($isAuthenticated && !$isAdmin && $notif_result): ?>

                            <div class="dropdown">
                                <button class="modern-btn position-relative" type="button" id="notifDropdown"
                                    data-bs-toggle="dropdown">
                                    <i class="fas fa-bell"></i>
                                    <?php if ($unreadCount > 0): ?>
                                        <span class="modern-notification-badge"><?= $unreadCount ?></span>
                                    <?php endif; ?>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end modern-dropdown-menu"
                                    style="width: 320px; max-height: 400px; overflow-y: auto;">
                                    <li class="modern-dropdown-header"><?= __('notifications') ?></li>

                                    <?php if ($notif_result->num_rows > 0): ?>
                                        <?php while ($notif = $notif_result->fetch_assoc()): ?>
                                            <li>
                                                <a class="dropdown-item modern-notification-item <?= $notif['is_read'] ? '' : 'fw-bold' ?>"
                                                    href="<?= htmlspecialchars($notif['link'] ?? '#') ?>">
                                                    <div class="d-flex align-items-start">
                                                        <?php
                                                        $avatarFile = $notif['Avatar'] ?? '';
                                                        $companyLogo = $notif['CompanyLogo'] ?? '';
                                                        $gender = strtolower($notif['Gender'] ?? '');

                                                        $senderID = $notif['sender_id'] ?? null;
                                                        $messageText = strtolower($notif['message'] ?? '');
                                                        $isSystemAlert = str_contains($messageText, 'flagged') || str_contains($messageText, 'fake');
                                                        $isSystem = is_null($senderID) || $senderID == 0;

                                                        if ($isSystem) {
                                                            $avatarPath = '/product1/img/favicon.ico';
                                                        } elseif (!empty($avatarFile) && file_exists(__DIR__ . '/uploads/avatars/' . $avatarFile)) {
                                                            $avatarPath = '/product1/uploads/avatars/' . htmlspecialchars($avatarFile);
                                                        } elseif (!empty($gender)) {
                                                            $avatarPath = $gender === 'female'
                                                                ? '/product1/img/FemDef.png'
                                                                : ($gender === 'male' ? '/product1/img/MaleDef.png' : '/product1/img/ProDef.png');
                                                        } elseif (!empty($companyLogo) && file_exists(__DIR__ . '/uploads/avatars/' . $companyLogo)) {
                                                            $avatarPath = '/product1/uploads/avatars/' . htmlspecialchars($companyLogo);
                                                        } else {
                                                            $avatarPath = '/product1/img/ProDef.png';
                                                        }
                                                        ?>
                                                        <img src="<?= $avatarPath ?>"
                                                            alt="<?= htmlspecialchars($notif['UserName']) ?>"
                                                            class="modern-notification-avatar">
                                                        <div class="modern-notification-content">
                                                            <?php
                                                            $messageText = strtolower($notif['message'] ?? '');
                                                            ?>

                                                            <div class="modern-notification-user">
                                                                <?= ucwords(htmlspecialchars($notif['UserName'])) ?>
                                                                <?php
                                                                // Check if points notification
                                                                $isPointsAlert = str_contains($messageText, 'points');
                                                                ?>
                                                                <?php if ($isSystemAlert): ?>
                                                                    <i class="fas fa-exclamation-triangle text-danger ms-1"
                                                                        title="System Alert"></i>
                                                                <?php elseif ($isPointsAlert): ?>
                                                                    <span class="ms-1" title="Points Earned">🎉</span>
                                                                <?php endif; ?>

                                                            </div>
                                                            <p class="modern-notification-message"><?= $notif['message'] ?></p>
                                                            <small
                                                                class="modern-notification-time"><?= date('M j, H:i', strtotime($notif['created_at'])) ?></small>
                                                        </div>
                                                    </div>
                                                </a>
                                            </li>
                                        <?php endwhile; ?>
                                        <li>
                                            <hr class="dropdown-divider modern-divider">
                                        </li>
                                        <li class="d-flex justify-content-between align-items-center px-3 py-2">
                                            <a class="modern-dropdown-item p-0"
                                                href="/product1/Notifications.php"><?= __('view_all') ?></a>
                                            <button id="clear-all-notifs" class="btn btn-sm btn-link p-0"
                                                style="color: var(--custom-coral);" title="Clear All">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </li>
                                        <li id="no-notifs-msg" class="dropdown-item modern-dropdown-item text-muted d-none">
                                            <?= __('no_notifications') ?>
                                        </li>

                                    <?php else: ?>
                                        <li><span
                                                class="dropdown-item modern-dropdown-item text-muted"><?= __('no_notifications') ?></span>
                                        </li>

                                    <?php endif; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <!-- Profile/Auth -->
                        <?php if ($isAuthenticated): ?>
                            <div class="dropdown">
                                <button class="modern-btn dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-user"></i>
                                    <span class="d-none d-sm-inline"><?= __('profile') ?></span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end modern-dropdown-menu">
                                    <li class="modern-dropdown-header"><?= ucwords(htmlspecialchars($username)) ?></li>
                                    <li>
                                        <hr class="dropdown-divider modern-divider">
                                    </li>
                                    <li>
                                        <a class="dropdown-item modern-dropdown-item"
                                            href="/product1/<?= $isAdmin ? 'Admin/Dashboard.php' : ($isCompany ? 'Company/CompanyDashboard.php' : 'Profile.php') ?>">
                                            <i class="fas fa-tachometer-alt me-2"></i>
                                            <?= $isAdmin ? __('admin_dashboard') : ($isCompany ? __('dashboard') : __('profile')) ?>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item modern-dropdown-item" href="/product1/logout.php">
                                            <i class="fas fa-sign-out-alt me-2"></i>
                                            <?= __('logout') ?>
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        <?php else: ?>
                            <a href="/product1/login.php" class="modern-btn modern-btn-primary">
                                <i class="fas fa-sign-in-alt"></i>
                                <span class="d-none d-sm-inline"><?= __('sign_in') ?></span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </nav>
    </header>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const notifDropdown = document.getElementById('notifDropdown');

            // Mark notifications as read
            if (notifDropdown) {
                let alreadyMarked = false;
                notifDropdown.addEventListener('click', function () {
                    if (!alreadyMarked) {
                        fetch('/product1/notificationsread.php', {
                            method: 'POST'
                        })
                            .then(res => res.text())
                            .then(() => {
                                const badge = notifDropdown.querySelector('.modern-notification-badge');
                                if (badge) badge.remove();
                                alreadyMarked = true;
                            })
                            .catch(err => console.error('Notification error:', err));
                    }
                });
            }

            // Clear all notifications
            const clearAllBtn = document.getElementById('clear-all-notifs');
            if (clearAllBtn) {
                clearAllBtn.addEventListener('click', function (e) {
                    e.preventDefault(); // يمنع السلوك الافتراضي مثل إعادة تحميل الصفحة  
                    fetch('/product1/notifications.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            clear_all: true //نرسلها للسيرفر 
                        })
                    })
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 'success') {
                                const menu = clearAllBtn.closest('.dropdown-menu'); //روح عالعنصر اللي حاملته كلير بوتن الكبسه ثم اطلع لأقرب اب او جد اله يحمل اسم الكلاس دروب مينيو
                                const items = menu.querySelectorAll('li:not(:first-child)'); // احذف كل الليست آيتم تعونها ماا عدا الأول 
                                items.forEach(item => item.remove());

                                const msg = document.getElementById('no-notifs-msg');
                                if (msg) msg.classList.remove('d-none');

                                // Remove notification badge
                                const badge = document.querySelector('.modern-notification-badge');
                                if (badge) badge.remove();
                            }
                        });
                });
            }
        });

        // Language switcher
        function switchLang(lang) {
            const url = new URL(window.location.href); //ينشئ اوبجكت من الرابط الحالي للصفحه
            url.searchParams.set('lang', lang); //يعدل أو يضيف باراميتر جديد في الرابط اسمه لانج
            window.location.href = url.toString(); //يعيد تحميل الصفحة بالرابط الجديد اللي فيه اللغة الجديدة
        }
    </script>

</body>

</html>

<style>
/* Upgrade button styles */
.my-button {
  position: relative;
  padding: 8px 24px;
  background: #fec195;
  font-size: 16px;
  font-weight: 500;
  color: #ffffff;
  border: 3px solid #fec195;
  border-radius: 8px;
  box-shadow: 0 0 0 #fec1958c;
  transition: all 0.3s ease-in-out;
  cursor: pointer;
  margin-inline: 0 15px;
}

.star-1 {
  position: absolute;
  top: 20%;
  left: 20%;
  width: 25px;
  height: auto;
  filter: drop-shadow(0 0 0 #fffdef);
  z-index: -5;
  transition: all 1s cubic-bezier(0.05, 0.83, 0.43, 0.96);
}

.star-2 {
  position: absolute;
  top: 45%;
  left: 45%;
  width: 15px;
  height: auto;
  filter: drop-shadow(0 0 0 #fffdef);
  z-index: -5;
  transition: all 1s cubic-bezier(0, 0.4, 0, 1.01);
}

.star-3 {
  position: absolute;
  top: 40%;
  left: 40%;
  width: 5px;
  height: auto;
  filter: drop-shadow(0 0 0 #fffdef);
  z-index: -5;
  transition: all 1s cubic-bezier(0, 0.4, 0, 1.01);
}

.star-4 {
  position: absolute;
  top: 20%;
  left: 40%;
  width: 8px;
  height: auto;
  filter: drop-shadow(0 0 0 #fffdef);
  z-index: -5;
  transition: all 0.8s cubic-bezier(0, 0.4, 0, 1.01);
}

.star-5 {
  position: absolute;
  top: 25%;
  left: 45%;
  width: 15px;
  height: auto;
  filter: drop-shadow(0 0 0 #fffdef);
  z-index: -5;
  transition: all 0.6s cubic-bezier(0, 0.4, 0, 1.01);
}

.star-6 {
  position: absolute;
  top: 5%;
  left: 50%;
  width: 5px;
  height: auto;
  filter: drop-shadow(0 0 0 #fffdef);
  z-index: -5;
  transition: all 0.8s ease;
}

button:hover {
  background: transparent;
  color: #fec195;
  box-shadow: 0 0 25px #fec1958c;
}

button:hover .star-1 {
  position: absolute;
  top: -80%;
  left: -30%;
  width: 25px;
  height: auto;
  filter: drop-shadow(0 0 10px #fffdef);
  z-index: 2;
}

button:hover .star-2 {
  position: absolute;
  top: -25%;
  left: 10%;
  width: 15px;
  height: auto;
  filter: drop-shadow(0 0 10px #fffdef);
  z-index: 2;
}

button:hover .star-3 {
  position: absolute;
  top: 55%;
  left: 25%;
  width: 5px;
  height: auto;
  filter: drop-shadow(0 0 10px #fffdef);
  z-index: 2;
}

button:hover .star-4 {
  position: absolute;
  top: 30%;
  left: 80%;
  width: 8px;
  height: auto;
  filter: drop-shadow(0 0 10px #fffdef);
  z-index: 2;
}

button:hover .star-5 {
  position: absolute;
  top: 25%;
  left: 115%;
  width: 15px;
  height: auto;
  filter: drop-shadow(0 0 10px #fffdef);
  z-index: 2;
}

button:hover .star-6 {
  position: absolute;
  top: 5%;
  left: 60%;
  width: 5px;
  height: auto;
  filter: drop-shadow(0 0 10px #fffdef);
  z-index: 2;
}

.fil0 {
  fill: #fffdef;
}
</style>