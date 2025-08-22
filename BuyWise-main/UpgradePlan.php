<?php
require_once "config.php";
;
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <title>Upgrade to Pro</title>
  <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Rubik&display=swap" rel="stylesheet">
  <link rel="icon" href="img/favicon.ico">

<body style="padding-top: 230px;">
  <?php
  if (file_exists('header.php')) {
    include 'header.php';
  }
  ?>
  <div class="container1">
    <header>
      <h1><?= __('upgrade_title') ?></h1>
      <p class="subtitle1"><?= __('upgrade_subtitle') ?></p>
    </header>

    <div class="plan-card1">
      <div class="plan-header1">
        <h2><?= __('pro_plan') ?></h2>
        <div class="price1"><?= __('price_per_month') ?></div>
      </div>

      <div class="divider1"></div>

      <div class="features1">
        <div class="feature-item1">
          <div class="feature-icon1"><i class="fas fa-box-open"></i></div>
          <div class="feature-text1">
            <h3><?= __('unlimited_uploads') ?></h3>
            <p><?= __('unlimited_uploads_desc') ?></p>
          </div>
        </div>

        <div class="feature-item1">
          <div class="feature-icon1"><i class="fas fa-language"></i></div>
          <div class="feature-text1">
            <h3><?= __('ai_validation') ?></h3>
            <p><?= __('ai_validation_desc') ?></p>
          </div>
        </div>

        <div class="feature-item1">
          <div class="feature-icon1"><i class="fas fa-shield-alt"></i></div>
          <div class="feature-text1">
            <h3><?= __('ai_detection') ?></h3>
            <p><?= __('ai_detection_desc') ?></p>
          </div>
        </div>
      </div>


      <div class="plan-footer1">
        <button class="coming-soon-btn1" disabled><?= __('coming_soon') ?></button>
      </div>
    </div>
  </div>
</body>

</html>
<style>
  :root {
    --primary-color: #83c5be;
    --primary-dark: #006d77;
    --accent-color: #e29578;
    --accent-light: #ffddd2;
    --text-color: #333;
    --bg-color: #edf6f9;
    --card-bg: #fff;
    --popup-button: #e29578;
    --popup-button-text: #fff;
  }

  * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
  }

  body {
    font-family: 'Rubik', sans-serif;
    background-color: var(--bg-color);
    color: var(--text-color);
    line-height: 1.6;
    padding: 20px;
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
  }

  .container1 {
    max-width: 800px;
    width: 100%;
    margin: 0 auto;
    padding: 20px;
  }

  header {
    text-align: center;
    margin-bottom: 40px;
  }

  h1 {
    font-size: 2.5rem;
    color: var(--primary-dark);
    margin-bottom: 10px;
  }

  .subtitle1 {
    font-size: 1.1rem;
    color: var(--text-color);
    opacity: 0.8;
  }

  .plan-card1 {
    background-color: var(--card-bg);
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    padding: 40px;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
  }

  .plan-card1:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
  }

  .plan-header1 {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
  }

  .plan-header h2 {
    font-size: 1.8rem;
    color: var(--primary-dark);
    font-weight: 600;
  }

  .price1 {
    font-size: 2rem;
    font-weight: 700;
    color: var(--accent-color);
  }

  .price1 span {
    font-size: 1rem;
    font-weight: 400;
    opacity: 0.7;
  }

  .divider1 {
    height: 1px;
    background-color: rgba(0, 0, 0, 0.1);
    margin: 20px 0;
  }

  .features1 {
    margin: 30px 0;
  }

  .feature-item1 {
    display: flex;
    margin-bottom: 25px;
    align-items: flex-start;
  }

  .feature-icon1 {
    font-size: 1.5rem;
    margin-right: 15px;
    flex-shrink: 0;
  }

  .feature-text1 h3 {
    font-size: 1.1rem;
    margin-bottom: 5px;
    color: var(--primary-dark);
  }

  .feature-text1 p {
    font-size: 0.95rem;
    color: var(--text-color);
    opacity: 0.8;
  }

  .plan-footer1 {
    margin-top: 30px;
    text-align: center;
  }

  .coming-soon-btn1 {
    background-color: var(--popup-button);
    color: var(--popup-button-text);
    border: none;
    border-radius: 30px;
    padding: 15px 40px;
    font-size: 1rem;
    font-weight: 600;
    cursor: not-allowed;
    opacity: 0.9;
    transition: all 0.3s ease;
    font-family: 'Rubik', sans-serif;
    position: relative;
    overflow: hidden;
  }

  .coming-soon-btn1::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg,
        transparent,
        rgba(255, 255, 255, 0.2),
        transparent);
    transition: 0.5s;
  }

  .coming-soon-btn1:hover::before {
    left: 100%;
  }

  /* Responsive styles */
  @media (max-width: 768px) {
    .plan-header1 {
      flex-direction: column;
      text-align: center;
    }

    .price1 {
      margin-top: 10px;
    }

    .plan-card1 {
      padding: 30px 20px;
    }
  }

  @media (max-width: 480px) {
    h1 {
      font-size: 2rem;
    }

    .feature-item1 {
      flex-direction: column;
    }

    .feature-icon1 {
      margin-bottom: 10px;
    }

    .feature-text1 {
      text-align: center;
    }
  }

  .feature-icon1 i {
    font-size: 32px;
    color: var(--primary-dark);
  }
</style>