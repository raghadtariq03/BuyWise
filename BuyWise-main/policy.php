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
$lang_code = $lang; //فقط نسخنا قيمة اللانج حطيناها بمتغير جديد عشان ذا بدنا نستخدمه لاحقًا

include('lang.php');
?>

<!DOCTYPE html>
<html lang="<?= $lang_code ?>" dir="<?= $dir ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>BuyWise | <?= __('policies') ?></title>
  <link rel="stylesheet" href="Home.css" />
  <link rel="icon" href="img/favicon.ico" type="image/x-icon" />
<style>
  body {
    background-color: var(--bg-color);
    color: var(--text-color);
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    padding: 0;
    margin: 0;
  }

  .faq-container {
    max-width: 950px;
    margin: 40px auto;
    background: var(--card-bg);
    border-radius: 16px;
    padding: 40px 30px;
    box-shadow: 0 4px 12px var(--shadow-color);
  }

  .faq-container h1 {
    color: var(--primary-dark);
    font-size: 2.25rem;
    margin-bottom: 30px;
    text-align: center;
    border-bottom: 2px solid var(--accent-color);
    padding-bottom: 15px;
  }

  .faq-container ul {
    list-style: none;
    padding: 0;
    margin-bottom: 40px;
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
  }

  .faq-container ul li {
    background-color: var(--form-input-bg);
    padding: 10px 15px;
    border-radius: 6px;
    transition: background-color 0.2s ease;
  }

  .faq-container ul li:hover {
    background-color: var(--accent-light);
  }

  .faq-container ul li a {
    color: var(--form-link);
    text-decoration: none;
    font-weight: 500;
  }

  .faq-container ul li a:hover {
    color: var(--form-link-hover);
  }

.faq-container section {
  padding-top: 25px;
  border-top: 1px solid var(--form-input-border);
  margin-top: 30px;
  scroll-margin-top: 100px; /* 💡 Adjust this value to match your header height */
}


  .faq-container h3 {
    color: var(--primary-color);
    font-size: 1.4rem;
    margin-bottom: 10px;
  }

  .faq-container h4 {
    color: var(--primary-dark);
    margin-top: 25px;
    font-size: 1.15rem;
  }

  .faq-container p,
  .faq-container ul li,
  .faq-container td {
    line-height: 1.7;
    font-size: 1rem;
    color: var(--text-color);
  }

  .faq-container ul {
    padding-left: 20px;
  }

  .faq-container ul li {
    margin-bottom: 6px;
  }

  .faq-container table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
    border-radius: 8px;
    overflow: hidden;
  }

  .faq-container thead tr {
    background-color: var(--accent-light);
  }

  .faq-container th,
  .faq-container td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid var(--form-input-border);
  }

  .faq-container tbody tr:nth-child(even) {
    background-color: var(--form-input-bg);
  }

  .faq-container a {
    color: var(--form-link);
    text-decoration: underline;
  }

  .faq-container a:hover {
    color: var(--form-link-hover);
  }

  .breadcrumb-wrapper {
    background-color: var(--bg-color);
    padding-top: 20px;
  }

  .breadcrumb {
    font-size: 0.95rem;
    color: var(--primary-dark);
    padding-left: 0;
    margin-bottom: 0;
  }

  .breadcrumb a {
    color: var(--form-link);
    text-decoration: none;
  }

  .breadcrumb a:hover {
    color: var(--form-link-hover);
    text-decoration: underline;
  }

  html[dir="rtl"] .breadcrumb-item + .breadcrumb-item::before {
  content: "/"; /* الفاصل */
  float: right;
  padding-left: 0.5rem;
  padding-right: 0;
  transform: scaleX(-1); /* يعكس اتجاه السلاش */
}


  @media (max-width: 768px) {
    .faq-container {
      padding: 30px 20px;
    }

    .faq-container h1 {
      font-size: 1.75rem;
    }

    .faq-container h3 {
      font-size: 1.2rem;
    }

    .faq-container h4 {
      font-size: 1.05rem;
    }

    .faq-container table,
    .faq-container thead,
    .faq-container tbody,
    .faq-container th,
    .faq-container td,
    .faq-container tr {
      font-size: 0.95rem;
    }

    .faq-container ul {
      flex-direction: column;
    }
  }
</style>

</head>
<body>

<?php include("header.php"); ?>

<main class="container py-5 mt-5">
  <div class="breadcrumb-wrapper">
    <div class="container">
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
          <li class="breadcrumb-item">
            <a href="Home.php"><i class="fas fa-home me-1"></i> <?= __('home') ?></a>
          </li>
          <li class="breadcrumb-item active" aria-current="page">
            <i class="fas fa-shield-alt me-1"></i> <?= __('policies') ?>
          </li>
        </ol>
      </nav>
    </div>
  </div>

  <div class="faq-container">
    <h1><?= __('policies') ?> & <?= __('guidelines') ?></h1>

    <ul>
      <li><a href="#privacy">1. <?= __('privacy_policy') ?></a></li>
      <li><a href="#terms">2. <?= __('terms_of_service') ?></a></li>
      <li><a href="#content">3. <?= __('content_policy') ?></a></li>
      <li><a href="#rewards">4. <?= __('reward_policy') ?></a></li>
      <li><a href="#reporting">5. <?= __('reporting_policy') ?></a></li>
    </ul>

<section id="privacy">
  <h3>1. <?= __('privacy_policy') ?></h3>
  <p><?= __('privacy_policy_desc') ?></p>
</section>

<section id="terms">
  <h3>2. <?= __('terms_of_service') ?></h3>
  <p><?= __('terms_of_service_desc') ?></p>
</section>

<section id="content">
  <h3>3. <?= __('content_policy') ?></h3>
  <p><?= __('content_policy_desc') ?></p>
</section>

<section id="rewards">
  <h3>4. <?= __('reward_policy') ?></h3>
  <p><?= __('reward_policy_intro') ?></p>

  <h4 style="color: var(--primary-dark); margin-top: 20px;"><?= __('how_points_earned') ?></h4>

<table style="width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 0.95rem;">
  <thead>
    <tr style="background-color: var(--accent-light); color: var(--primary-dark);">
      <th style="text-align: center; vertical-align: middle; padding: 10px; border-bottom: 1px solid var(--form-input-border);"><?= __('action') ?></th>
      <th style="text-align: center; vertical-align: middle; padding: 10px; border-bottom: 1px solid var(--form-input-border);"><?= __('regular_product') ?></th>
      <th style="text-align: center; vertical-align: middle; padding: 10px; border-bottom: 1px solid var(--form-input-border);"><?= __('local_product') ?></th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td style="text-align: center; vertical-align: middle; padding: 10px;"><?= __('submit_review') ?></td>
      <td style="text-align: center; vertical-align: middle; padding: 10px;">+10</td>
      <td style="text-align: center; vertical-align: middle; padding: 10px;">+20</td>
    </tr>
    <tr style="background-color: var(--form-input-bg);">
      <td style="text-align: center; vertical-align: middle; padding: 10px;"><?= __('post_comment') ?></td>
      <td style="text-align: center; vertical-align: middle; padding: 10px;">+3</td>
      <td style="text-align: center; vertical-align: middle; padding: 10px;">+6</td>
    </tr>
    <tr>
      <td style="text-align: center; vertical-align: middle; padding: 10px;"><?= __('receive_like') ?></td>
      <td style="text-align: center; vertical-align: middle; padding: 10px;">+2</td>
      <td style="text-align: center; vertical-align: middle; padding: 10px;">+4</td>
    </tr>
  </tbody>
</table>


  <h4 style="color: var(--primary-dark); margin-top: 30px;"><?= __('user_badges') ?></h4>
  <p><?= __('user_badges_desc') ?></p>
  <ul style="margin-left: 20px;">
    <li><strong>Normal</strong> – <?= __('badge_normal_range') ?></li>
    <li><strong>Professional</strong> – <?= __('badge_professional_range') ?></li>
    <li><strong>Expert</strong> – <?= __('badge_expert_range') ?></li>
    <li><strong>Legend</strong> – <?= __('badge_legend_range') ?></li>
  </ul>

  <h4 style="color: var(--primary-dark); margin-top: 30px;"><?= __('company_rewards') ?></h4>
  <p><?= __('company_rewards_desc') ?></p>

  <?php
  $dashboardLink = isset($_SESSION['UserID']) ? 'Profile.php' : 'login.php';
  ?>
  <h4 style="color: var(--primary-dark); margin-top: 30px;"><?= __('track_progress') ?></h4>
  <p><?= __('track_progress_desc') ?> <a href="<?= $dashboardLink ?>" style="color: var(--form-link);"><?= __('dashboard') ?></a>.</p>
</section>

<section id="reporting">
  <h3>5. <?= __('reporting_policy') ?></h3>
  <p><?= __('reporting_policy_desc') ?></p>
</section>


  </div>
</main>

<footer class="footer fixed-footer mt-auto py-3">
    <div class="container text-center">
        <p class="mb-0 text-light">&copy; <?= date('Y') ?> <a href="#" class="text-light">BuyWise</a>. <?= __('all_rights_reserved') ?></p>
    </div>
</footer>

</body>
</html>
