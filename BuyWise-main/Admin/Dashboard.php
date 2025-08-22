<?php
require_once '../config.php';

// Check admin access
if (!isset($_SESSION['type']) || $_SESSION['type'] != 1 || !isset($_SESSION['UserID'])) {
    header("Location: ../login.php");
    exit();
}

// Admin info
$adminName = $_SESSION['username'] ?? __('unknown_admin');
$adminBadge = $_SESSION['badge'] ?? 'Admin';

// Get dashboard stats
$stats = [];
try {
    $stats['users'] = $con->query("SELECT COUNT(*) as count FROM users WHERE UserStatus = 1")->fetch_assoc()['count'];
    $stats['products'] = $con->query("SELECT COUNT(*) as count FROM products WHERE ProductStatus = 1")->fetch_assoc()['count'];
    $stats['pending_products'] = $con->query("SELECT COUNT(*) as count FROM products WHERE ProductStatus = 0 AND (IsFake = 0 OR IsFake IS NULL)")->fetch_assoc()['count'];
    $stats['comments'] = $con->query("SELECT COUNT(*) as count FROM comments")->fetch_assoc()['count'];
    $stats['reported_users'] = $con->query("SELECT COUNT(DISTINCT UserID) as count FROM reported_reviews")->fetch_assoc()['count'];
    $stats['companies'] = $con->query("SELECT COUNT(*) as count FROM companies")->fetch_assoc()['count'];
    $stats['categories'] = $con->query("SELECT COUNT(*) as count FROM categories")->fetch_assoc()['count'];
    $stats['new_users_week'] = $con->query("SELECT COUNT(*) as count FROM users WHERE DATE(CreatedAt) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetch_assoc()['count'];
} catch (Exception $e) {
    $stats = array_fill_keys(['users', 'products', 'pending_products', 'comments', 'reported_users', 'companies', 'categories', 'new_users_week'], 0);
}

// Dashboard menu
$menuOptions = [
    [
        'link' => 'AdminAccount.php',
        'icon' => 'fa-user-cog',
        'title' => __('admin_settings'),
        'description' => __('manage_your_admin_account'),
        'color' => 'style="background-color: var(--accent-light);"',
        'urgent' => false
    ],
    [
        'link' => 'AdminManageUsers.php',
        'icon' => 'fa-users',
        'title' => __('admin_users'),
        'description' => __('manage_user_accounts') . ' (' . number_format($stats['users']) . ')',
        'color' => 'style="background-color: var(--accent-light);"',
        'urgent' => false
    ],
    [
        'link' => 'AdminPendingProducts.php',
        'icon' => 'fa-clock',
        'title' => __('admin_pending_user_products'),
        'description' => __('review_pending_products') . ' (' . number_format($stats['pending_products']) . ')',
        'color' => 'style="background-color: var(--accent-light);"',
        'urgent' => $stats['pending_products'] > 0
    ],
    [
        'link' => 'AdminManageProducts.php',
        'icon' => 'fa-box',
        'title' => __('admin_products'),
        'description' => __('manage_all_products') . ' (' . number_format($stats['products']) . ')',
        'color' => 'style="background-color: var(--accent-light);"',
        'urgent' => false
    ],
    [
        'link' => 'AdminManageComments.php',
        'icon' => 'fa-comments',
        'title' => __('admin_comments_reports'),
        'description' => __('manage_user_comments') . ' (' . number_format($stats['comments']) . ')',
        'color' => 'style="background-color: var(--accent-light);"',
        'urgent' => $stats['reported_users'] > 0
    ],
    [
        'link' => 'AdminReports.php',
        'icon' => 'fa-exclamation-triangle',
        'title' => __('admin_fake_content'),
        'description' => __('review_reported_content') . ' (' . number_format($stats['reported_users']) . ')',
        'color' => 'style="background-color: var(--accent-light);"',
        'urgent' => $stats['reported_users'] > 5
    ],
    [
        'link' => 'AdminManageCompanies.php',
        'icon' => 'fa-building',
        'title' => __('admin_companies'),
        'description' => __('manage_companies') . ' (' . number_format($stats['companies']) . ')',
        'color' => 'style="background-color: var(--accent-light);"',
        'urgent' => false
    ],
    [
        'link' => 'AdminManageCategories.php',
        'icon' => 'fa-folder',
        'title' => __('admin_categories'),
        'description' => __('manage_categories') . ' (' . number_format($stats['categories']) . ')',
        'color' => 'style="background-color: var(--accent-light);"',
        'urgent' => false
    ],
    [
        'link' => 'AdminManageCproduct.php',
        'icon' => 'fa-industry',
        'title' => __('admin_company_products'),
        'description' => __('manage_company_products'),
        'color' => 'style="background-color: var(--accent-light);"',
        'urgent' => false
    ]
];
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">

<head>
    <title>BuyWise - <?= __('dashboard') ?></title>
    <link rel="icon" href="../img/favicon.ico">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="Admin.css">
    <?php include '../header.php'; ?>
</head>

<body class="admin-dashboard">
    <div class="db-wrapper d-flex flex-column min-vh-100">

        <div class="container py-5">
            <!-- Welcome Section -->
            <div class="row mb-5">
                <div class="col-12">
                    <div class="admin-card welcome-section text-white d-flex flex-column align-items-center justify-content-center text-center"
                        style="background: var(--overlay-bg); padding: 3rem;">

                        <!-- Admin Avatar -->
                        <div class="profile-avatar-wrapper mb-3">
                            <img src="../img/AdminLogo.png" alt="Admin Avatar" class="profile-avatar">
                            <div class="mt-3">
                                <span class="badge" style="
                                    background-color: var(--popup-bg);
                                    color: var(--popup-text);
                                    padding: 6px 14px;
                                    font-weight: bold;
                                    font-size: 0.9rem;
                                    border-radius: 20px;">
                                    <?= htmlspecialchars($adminBadge) ?>
                                </span>
                            </div>
                        </div>

                        <!-- Welcome Message -->
                        <div class="text-center">
                            <h1 class="fw-bold mb-3" style="color: var(--card-bg);">
                                <?= __('welcome') ?>, <?= htmlspecialchars($adminName) ?>!
                            </h1>
                            <p class="lead mb-2" style="color: var(--accent-light);">
                                <?= __('dashboard_intro') ?>
                            </p>
                            <small style="opacity: 0.9; color: var(--form-input-border);">
                                <?= __('last_login') ?>: <?= date('M j, Y \a\t g:i A') ?>
                            </small>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="row mb-5">
                <div class="col-12">
                    <h3 class="mb-4 fw-bold">
                        <i class="fas fa-chart-bar me-2 text-primary"></i>
                        <?= __('quick_overview') ?>
                    </h3>
                </div>

                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="card border-0 shadow-sm h-100 hover-lift stats-card">
                        <div class="card-body text-center">
                            <div class="text-primary mb-3">
                                <i class="fas fa-users fa-2x"></i>
                            </div>
                            <h4 class="card-text text-muted mb-0">
                                <?= number_format($stats['users']) ?>
                            </h4>
                            <p class="card-text text-muted mb-0"><?= __('total_users') ?></p>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="card border-0 shadow-sm h-100 hover-lift stats-card">
                        <div class="card-body text-center">
                            <div class="text-success mb-3">
                                <i class="fas fa-box fa-2x"></i>
                            </div>
                            <h4 class="card-text text-muted mb-0">
                                <?= number_format($stats['products']) ?>
                            </h4>
                            <p class="card-text text-muted mb-0"><?= __('total_products') ?></p>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="card border-0 shadow-sm h-100 hover-lift stats-card">
                        <div class="card-body text-center">
                            <div class="text-info mb-3">
                                <i class="fas fa-comments fa-2x"></i>
                            </div>
                            <h4 class="card-text text-muted mb-0">
                                <?= number_format($stats['comments']) ?>
                            </h4>
                            <p class="card-text text-muted mb-0"><?= __('total_comments') ?></p>
                        </div>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="card border-0 shadow-sm h-100 hover-lift stats-card">
                        <div class="card-body text-center">
                            <div class="text-warning mb-3">
                                <i class="fas fa-exclamation-triangle fa-2x"></i>
                            </div>
                            <h4 class="ccard-text text-muted mb-0">
                                <?= number_format($stats['reported_users']) ?>
                            </h4>
                            <p class="card-text text-muted mb-0"><?= __('reported_users') ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Management Options -->
            <div class="row">
                <div class="col-12">
                    <h3 class="mb-4 fw-bold">
                        <i class="fas fa-cogs me-2 text-primary"></i>
                        <?= __('management_tools') ?>
                    </h3>
                </div>

                <?php foreach ($menuOptions as $option): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <a href="<?= htmlspecialchars($option['link']) ?>" class="text-decoration-none">
                            <div class="card border-0 shadow-sm h-100 hover-lift position-relative management-card">
                                <?php if ($option['urgent']): ?>
                                    <div class="position-absolute top-0 end-0 mt-2 me-2">
                                        <span class="badge bg-danger rounded-pill pulse-animation">
                                            <i class="fas fa-bell fa-xs"></i>
                                        </span>
                                    </div>
                                <?php endif; ?>

                                <div class="card-body p-4">
                                    <div class="d-flex align-items-start">
                                        <div class="flex-shrink-0 me-3">
                                            <div class="bg-<?= $option['color'] ?> bg-opacity-10 rounded-3 p-3">
                                                <i
                                                    class="fas <?= $option['icon'] ?> fa-2x text-<?= $option['color'] ?>"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h5 class="card-title mb-2 fw-bold">
                                                <?= htmlspecialchars($option['title']) ?>
                                            </h5>
                                            <p class="card-text text-muted small mb-0">
                                                <?= htmlspecialchars($option['description']) ?>
                                            </p>
                                        </div>
                                        <div class="flex-shrink-0 ms-2">
                                            <i class="fas fa-chevron-right text-muted"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>


        <!-- Footer -->
        <footer class="footer fixed-footer mt-auto py-3">
            <div class="container text-center">
                <p class="mb-0 text-light">
                    &copy; <?= date('Y') ?> <a href="#" class="text-light">BuyWise</a>. <?= __('all_rights_reserved') ?>
                </p>
            </div>
        </footer>

        <script>
            // Enhanced dashboard interactions
            document.addEventListener('DOMContentLoaded', function () {
                // Smooth hover effects for cards
                const cards = document.querySelectorAll('.hover-lift');
                cards.forEach(card => {
                    card.addEventListener('mouseenter', function () {
                        this.style.transform = 'translateY(-8px)';
                        this.style.transition = 'all 0.3s ease';
                    });

                    card.addEventListener('mouseleave', function () {
                        this.style.transform = 'translateY(0)';
                    });
                });

                // Animate stats cards on load
                const statsCards = document.querySelectorAll('.stats-card');
                statsCards.forEach((card, index) => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';

                    setTimeout(() => {
                        card.style.transition = 'all 0.6s ease';
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, index * 150);
                });

                // Management cards stagger animation
                const managementCards = document.querySelectorAll('.management-card');
                managementCards.forEach((card, index) => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateX(-20px)';

                    setTimeout(() => {
                        card.style.transition = 'all 0.5s ease';
                        card.style.opacity = '1';
                        card.style.transform = 'translateX(0)';
                    }, 500 + (index * 100));
                });

                // Add ripple effect to buttons
                function createRipple(event) {
                    const button = event.currentTarget;
                    const circle = document.createElement("span");
                    const diameter = Math.max(button.clientWidth, button.clientHeight);
                    const radius = diameter / 2;

                    circle.style.width = circle.style.height = `${diameter}px`;
                    circle.style.left = `${event.clientX - button.offsetLeft - radius}px`;
                    circle.style.top = `${event.clientY - button.offsetTop - radius}px`;
                    circle.classList.add("ripple");

                    const ripple = button.getElementsByClassName("ripple")[0];
                    if (ripple) {
                        ripple.remove();
                    }

                    button.appendChild(circle);
                }

                // Add ripple effect to all buttons
                const buttons = document.querySelectorAll('.btn');
                buttons.forEach(button => {
                    button.addEventListener('click', createRipple);
                });

                // Progress bar animation for stats
                function animateProgressBars() {
                    const maxValue = Math.max(...Object.values([
                        <?= $stats['users'] ?>,
                        <?= $stats['products'] ?>,
                        <?= $stats['comments'] ?>,
                        <?= $stats['reported_users'] ?>
                    ]));

                    const statsData = [
                        { element: '.text-primary', value: <?= $stats['users'] ?> },
                        { element: '.text-success', value: <?= $stats['products'] ?> },
                        { element: '.text-info', value: <?= $stats['comments'] ?> },
                        { element: '.text-warning', value: <?= $stats['reported_users'] ?> }
                    ];

                    statsData.forEach((stat, index) => {
                        const percentage = (stat.value / maxValue) * 100;
                        setTimeout(() => {
                            const elements = document.querySelectorAll(stat.element);
                            elements.forEach(el => {
                                if (el.tagName === 'H4') {
                                    el.style.background = `linear-gradient(90deg, var(--primary-color) ${percentage}%, transparent ${percentage}%)`;
                                    el.style.backgroundClip = 'text';
                                    el.style.webkitBackgroundClip = 'text';
                                }
                            });
                        }, index * 200);
                    });
                }

                // Initialize progress bar animation after cards load
                setTimeout(animateProgressBars, 1000);

                // Notification system for urgent items
                if (<?= $stats['pending_products'] ?> > 0 || <?= $stats['reported_users'] ?> > 5) {
                    showNotification();
                }

                function showNotification() {
                    const notification = document.createElement('div');
                    notification.className = 'alert alert-warning alert-dismissible fade show position-fixed';
                    notification.style.cssText = `
                    top: 100px;
                    right: 20px;
                    z-index: 1050;
                    min-width: 300px;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
                    border: none;
                    background: var(--popup-bg);
                    color: var(--popup-text);
                `;

                    let message = '';
                    if (<?= $stats['pending_products'] ?> > 0) {
                        message += `<i class="fas fa-clock me-2"></i><?= $stats['pending_products'] ?> <?= __('pending_products_notification') ?>`;
                    }
                    if (<?= $stats['reported_users'] ?> > 5) {
                        if (message) message += '<br>';
                        message += `<i class="fas fa-exclamation-triangle me-2"></i><?= $stats['reported_users'] ?> <?= __('reports_need_attention') ?>`;
                    }

                    notification.innerHTML = `
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;

                    document.body.appendChild(notification);

                    // Auto-dismiss after 8 seconds
                    setTimeout(() => {
                        if (notification.parentNode) {
                            notification.remove();
                        }
                    }, 8000);
                }

                // Enhanced keyboard navigation
                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape') {
                        // Close any open notifications
                        const notifications = document.querySelectorAll('.alert');
                        notifications.forEach(notification => {
                            if (notification.classList.contains('show')) {
                                notification.remove();
                            }
                        });
                    }
                });

                // Accessibility improvements
                const interactiveElements = document.querySelectorAll('a, button, [tabindex]');
                interactiveElements.forEach(element => {
                    element.addEventListener('focus', function () {
                        this.style.outline = '2px solid var(--primary-color)';
                        this.style.outlineOffset = '2px';
                    });

                    element.addEventListener('blur', function () {
                        this.style.outline = 'none';
                    });
                });

                // Dynamic theme updates based on time of day
                const hour = new Date().getHours();
                if (hour < 6 || hour > 20) {
                    // Evening mode - slightly darker theme
                    document.documentElement.style.setProperty('--bg-color', '#e8f4f8');
                    document.documentElement.style.setProperty('--card-bg', '#fafafa');
                }

                // Performance monitoring
                const performanceObserver = new PerformanceObserver((list) => {
                    const entries = list.getEntries();
                    entries.forEach((entry) => {
                        if (entry.entryType === 'measure') {
                            console.log(`${entry.name}: ${entry.duration}ms`);
                        }
                    });
                });

                if ('PerformanceObserver' in window) {
                    performanceObserver.observe({ entryTypes: ['measure'] });
                }

                // Measure page load performance
                window.addEventListener('load', function () {
                    performance.mark('dashboard-loaded');
                    performance.measure('dashboard-load-time', 'navigationStart', 'dashboard-loaded');
                });
            });

            // Add CSS for ripple effect
            const rippleStyle = document.createElement('style');
            rippleStyle.textContent = `
            .btn {
                position: relative;
                overflow: hidden;
            }
            
            .ripple {
                position: absolute;
                border-radius: 50%;
                transform: scale(0);
                animation: ripple 600ms linear;
                background-color: rgba(255, 255, 255, 0.6);
            }
            
            @keyframes ripple {
                to {
                    transform: scale(4);
                    opacity: 0;
                }
            }
            
            /* Smooth transitions for all interactive elements */
            .card, .btn, .badge {
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }
            
            /* Enhanced focus states */
            .card:focus-within {
                transform: translateY(-4px);
                box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            }
            
            /* Loading states */
            .btn:disabled {
                opacity: 0.7;
                cursor: not-allowed;
            }
            
            /* Custom scrollbar */
            ::-webkit-scrollbar {
                width: 8px;
            }
            
            ::-webkit-scrollbar-track {
                background: var(--bg-color);
            }
            
            ::-webkit-scrollbar-thumb {
                background: var(--primary-color);
                border-radius: 4px;
            }
            
            ::-webkit-scrollbar-thumb:hover {
                background: var(--primary-dark);
            }
            
            /* Mobile optimizations */
            @media (max-width: 768px) {
                .stats-card {
                    margin-bottom: 1rem;
                }
                
                .management-card {
                    margin-bottom: 1rem;
                }
                
                .btn-lg {
                    padding: 0.75rem 1rem;
                    font-size: 0.9rem;
                }
                
                .display-6 {
                    font-size: 1.5rem;
                }
                
                .hover-lift:hover {
                    transform: none;
                }
            }
            
            /* Print styles */
            @media print {
                .btn, .badge, .shadow-sm, .shadow-lg {
                    display: none !important;
                }
                
                .card {
                    border: 1px solid #ddd !important;
                    box-shadow: none !important;
                }
                
                body {
                    background: white !important;
                }
            }
            
            /* High contrast mode support */
            @media (prefers-contrast: high) {
                .card {
                    border: 2px solid var(--text-color);
                }
                
                .text-muted {
                    color: var(--text-color) !important;
                }
            }
            
            /* Reduced motion support */
            @media (prefers-reduced-motion: reduce) {
                *, *::before, *::after {
                    animation-duration: 0.01ms !important;
                    animation-iteration-count: 1 !important;
                    transition-duration: 0.01ms !important;
                }
                
                .hover-lift:hover {
                    transform: none;
                }
                
                .pulse-animation {
                    animation: none;
                }
            }
        `;
            document.head.appendChild(rippleStyle);
        </script>
</body>

</html>