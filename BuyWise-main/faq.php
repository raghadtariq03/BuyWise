<?php
require_once 'config.php';
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BuyWise | <?= __('faq_page_title') ?> </title>
  <link rel="stylesheet" href="Home.css">
  <link rel="icon" href="img/favicon.ico">
  <style>
    body {
      background-color: var(--bg-color);
      color: var(--text-color);
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      margin: 0;
      padding: 0;
    }

    .faq-wrapper {
      padding: 100px 20px 40px;
      max-width: 950px;
      margin: auto;
    }

    .faq-header {
      text-align: center;
      margin-bottom: 40px;
    }

    .faq-header h1 {
      font-size: 2.2rem;
      color: var(--primary-dark);
      margin-bottom: 10px;
    }

    .breadcrumb-wrapper {
      margin-bottom: 25px;
    }

    .breadcrumb {
      font-size: 0.95rem;
      color: var(--primary-dark);
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

    .faq-container {
      background: var(--card-bg);
      border-radius: 16px;
      box-shadow: 0 4px 12px var(--shadow-color);
      padding: 30px;
    }

    .faq-item {
      margin-bottom: 20px;
      border: 1px solid var(--form-input-border);
      border-radius: 12px;
      background-color: var(--form-input-bg);
      overflow: hidden;
      transition: transform 0.2s ease;
    }

    .faq-item:hover {
      transform: translateY(-2px);
    }

    .faq-question {
      margin: 0;
      padding: 18px 20px;
      font-size: 1.1rem;
      color: var(--primary-color);
      cursor: pointer;
      background-color: transparent;
      border: none;
      width: 100%;
      text-align: start;
      font-weight: 600;
    }

    .faq-answer {
      display: none;
      padding: 0 20px 20px;
      font-size: 1rem;
      line-height: 1.7;
      color: var(--text-color);
      text-align: start;
    }

    .faq-answer ul {
      margin-top: 8px;
      padding-inline-start: 1.5rem;
      list-style-type: disc;
    }

    [dir="rtl"] .faq-question,
    [dir="rtl"] .faq-answer {
      text-align: right;
    }

    [dir="rtl"] .faq-answer ul {
      padding-inline-start: 0;
      padding-inline-end: 1.5rem;
    }

    .faq-item.active .faq-answer {
      display: block;
    }

    .footer {
      background: var(--footer-bg);
      color: #fff;
      font-size: 0.95rem;
      box-shadow: 0 -2px 10px var(--shadow-color);
      padding: 10px 0;
      text-align: center;
    }

    .footer a {
      color: var(--popup-text);
    }

    .footer a:hover {
      color: var(--popup-button);
      text-decoration: underline;
    }
  </style>
</head>
<body>

<?php include("header.php"); ?>

<main class="faq-wrapper">
  <div class="breadcrumb-wrapper">
    <div class="container">
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="Home.php"><i class="fas fa-home me-1"></i> <?= __('home') ?></a></li>
          <li class="breadcrumb-item active" aria-current="page"><i class="fas fa-folder me-1"></i> <?= __('faq_title') ?></li>
        </ol>
      </nav>
    </div>
  </div>

  <div class="faq-header">
    <h1><?= __('faq_title') ?></h1>
  </div>

  <div class="faq-container">
    <?php
    $faqs = [
      'faq_register',
      'faq_no_verification',
      'faq_reset_password',
      'faq_add_product',
      'faq_badges',
      'faq_points',
      'faq_rewards',
      'faq_interaction',
      'faq_report',
      'faq_languages',
      'faq_notifications',
      'faq_profile_visibility',
      'faq_account_update'
    ];

    foreach ($faqs as $key) {
      echo '
        <div class="faq-item">
          <button class="faq-question">' . __('' . $key . '') . '</button>
          <div class="faq-answer">' . __('' . $key . '_ans') . '</div>
        </div>';
    }
    ?>
  </div>
</main>

<footer class="footer mt-auto py-3">
  <div class="container text-center">
    <p class="mb-0 text-light">&copy; <?= date('Y') ?> <a href="#" class="text-light">BuyWise</a>. <?= __('all_rights_reserved') ?></p>
  </div>
</footer>

<script>
  document.querySelectorAll('.faq-question').forEach(button => {
    button.addEventListener('click', () => {
      const item = button.closest('.faq-item');
      item.classList.toggle('active');
    });
  });
</script>

</body>
</html>
