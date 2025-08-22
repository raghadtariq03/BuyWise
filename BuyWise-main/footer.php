<?php
require_once "config.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
    require_once 'PHPMailer/src/PHPMailer.php';
    require_once 'PHPMailer/src/SMTP.php';
    require_once 'PHPMailer/src/Exception.php';

// Handle newsletter subscription
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subscribe_newsletter'])) {
    $email = trim($_POST['newsletter_email']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['newsletter_status'] = __('invalid_email');
    } else {
        try {
            $mail->addAddress($email);
            $mail->isHTML(true);
            $mail->Subject = 'Welcome to BuyWise Newsletter!';
            $mail->Body = '
                <div style="font-family: Arial, sans-serif; color: #333; font-size: 16px; background-color: #f9f9f9; padding: 20px; border-radius: 10px;">
                    <h2 style="color: #006d77;">Welcome to the BuyWise Newsletter!</h2>
                    <p>Thank you for subscribing. You’re now part of our community of smart shoppers.</p>
                    <p>We’ll keep you informed with the latest product updates, exclusive deals, and helpful reviews.</p>
                    <p style="margin-top: 25px;">If you ever wish to unsubscribe, you can do so from the footer of any email we send.</p>
                    <br>
                    <p style="color: #555;">Warm regards,<br>The BuyWise Team</p>
                    <hr style="border: none; border-top: 1px solid #ddd; margin: 30px 0;">
                    <p style="font-size: 12px; color: #999;">
                        Need help? Contact us at 
                        <a href="mailto:buywise2025@gmail.com" style="color: #006d77;">buywise2025@gmail.com</a>
                    </p>
                </div>
            ';
            $mail->send();
            $_SESSION['newsletter_status'] = __('subscription_successful');
        } catch (Exception $e) {
            $_SESSION['newsletter_status'] = __('subscription_failed') . ' ' . $mail->ErrorInfo;
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }
}
?>

<link rel="stylesheet" href="style.css">

<footer class="footer">
    <div class="container-fluid py-5 px-sm-3 px-md-5">
        <div class="row pt-3 text-md-left">

            <!-- Contact Info -->
            <div class="col-lg-4 col-md-6 mb-5">
                <h4 class="footer-title text-uppercase mb-4"><?= __('get_in_touch') ?></h4>
                <p class="footer-text mb-2 text-light"><i class="fa fa-map-marker-alt me-3"></i><?= __('addresss') ?></p>
                <p class="footer-text mb-2 text-light"><i class="fa fa-phone-alt me-3"></i><?= __('phonee') ?></p>
                <p class="footer-text text-light"><i class="fa fa-envelope me-3"></i><?= __('emaill') ?></p>

                <h6 class="footer-subtitle text-uppercase text-light py-2"><?= __('follow_us') ?></h6>
                <div class="d-flex">
                    <a class="btn btn-lg footer-social-icon me-2" href="#"><i class="fab fa-twitter"></i></a>
                    <a class="btn btn-lg footer-social-icon me-2" href="#"><i class="fab fa-facebook-f"></i></a>
                    <a class="btn btn-lg footer-social-icon me-2" href="#"><i class="fab fa-linkedin-in"></i></a>
                    <a class="btn btn-lg footer-social-icon" href="#"><i class="fab fa-instagram"></i></a>
                </div>

                <br>

                <!-- Newsletter -->
                <h4 class="footer-title text-uppercase mb-4"><?= __('newsletter') ?></h4>
                <p class="footer-text text-light"><?= __('newsletter_text') ?></p>

                <?php if (isset($_SESSION['newsletter_status'])): ?>
                    <div class="alert alert-info p-2 mt-2">
                        <?= htmlspecialchars($_SESSION['newsletter_status']) ?>
                    </div>
                    <?php unset($_SESSION['newsletter_status']); ?>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="input-group mb-3">
                        <input type="email" name="newsletter_email" class="form-control bg-light border-0" placeholder="<?= __('your_email') ?>" required>
                        <div class="input-group-append">
                            <button class="btn footer-join-btn" name="subscribe_newsletter"><?= __('join_us') ?></button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Useful Links -->
            <div class="col-lg-4 col-md-6 mb-5">
                <h4 class="footer-title text-uppercase mb-4"><?= __('useful_links') ?></h4>
                <div class="d-flex flex-column">
                    <a class="footer-link text-light mb-2" href="Home.php"><i class="fa fa-angle-right me-2"></i><?= __('home') ?></a>
                    <a class="footer-link text-light" href="login.php"><i class="fa fa-angle-right me-2"></i><?= __('sign_in') ?></a>
                </div>
            </div>

            <!-- Location -->
            <div class="col-lg-4 col-md-6 mb-5">
                <h4 class="footer-title text-uppercase mb-4"><?= __('our_location') ?></h4>
                <div class="footer-map bg-secondary d-flex flex-column justify-content-center px-5" style="height: 400px;">
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3383.101428153116!2d35.93566609999999!3d32.012366099999994!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x151b6037d70a02f5%3A0x422b60b0d9a86253!2sThe%20World%20Islamic%20Sciences%20and%20Education%20University!5e0!3m2!1sen!2sjo!4v1741401742966!5m2!1sen!2sjo" width="100%" height="200" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid py-3">
        <p class="footer-bottom mb-0 text-center text-light">&copy; 2025 <a href="#" class="text-light">BuyWise</a>. <?= __('all_rights_reserved') ?></p>
    </div>
</footer>
