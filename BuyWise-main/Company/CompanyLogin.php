<?php
require_once '../config.php';

// Redirect if already logged in
if (isset($_SESSION['type']) && $_SESSION['type'] === 'company') {
    header("Location: CompanyDashboard.php");
    exit();
}

$verified = isset($_GET['verified']) && $_GET['verified'] == '1';
$loginError = '';
$registerError = '';

// Send verification email
function sendVerificationEmail($email, $code)
{
    global $mail;
    try {
        $mail->clearAllRecipients();
        $mail->addAddress($email);
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);
        $mail->Subject = "BuyWise – Company Verification Code";
        //هذا المسج المعتمد للكومباني
        $mail->Body = "
        <div style='font-family: Arial, sans-serif; font-size: 16px; color: #333;'>
            <p>Welcome to <strong>BuyWise</strong> — your gateway to trusted, verified product reviews.</p>
            <p>To complete your company registration, please enter the following verification code:</p>

            <div style='font-size: 24px; font-weight: bold; color: #006d77; margin: 20px 0; background-color: #edf6f9; padding: 12px 24px; border-radius: 8px; display: inline-block;'>
                $code
            </div>

            <p>This code is required to activate your company account. If you did not request this registration, you can safely ignore this email.</p>

            <br>
            <p style='color: #555;'>Best regards,<br>The BuyWise Team</p>

            <hr style='border-top: 1px solid #eee; margin: 30px 0;'>
            <p style='font-size: 12px; color: #999;'>Please do not reply to this message. For assistance, contact us at
            <a href='mailto:" . env('SMTP_FROM_EMAIL') . "' style='color: #006d77;'>" . env('SMTP_FROM_EMAIL') . "</a>.</p>
        </div>";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email failed: " . $e->getMessage());
        return false;
    }
}

// Company login
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['CompanyEmail']) && !isset($_POST['action'])) {
    $email = trim($_POST['CompanyEmail']);
    $password = $_POST['CompanyPassword'];
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!isValidCSRF($csrfToken)) {
        $_SESSION['popup'] = ['title' => __('error'), 'message' => __('error_security_token')];
    } else {
        $stmt = $con->prepare("SELECT CompanyID, CompanyName, CompanyPassword, CompanyStatus, Verified FROM companies WHERE CompanyEmail = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $stmt->bind_result($companyID, $companyName, $hashed, $status, $verified);
            $stmt->fetch();

            if ($verified == 0) {
                $_SESSION['popup'] = ['title' => __('error'), 'message' => __('company_email_not_verified')];
            } elseif ($status == 0) {
                $_SESSION['popup'] = ['title' => __('error'), 'message' => __('company_pending_approval')];
            } elseif (password_verify($password, $hashed)) {
                $_SESSION['type'] = 'company';
                $_SESSION['CompanyID'] = $companyID;
                $_SESSION['CompanyName'] = $companyName;
                header("Location: CompanyDashboard.php");
                exit();
            } else {
                $_SESSION['popup'] = ['title' => __('error'), 'message' => __('error_invalid_credentials')];
            }
        } else {
            $_SESSION['popup'] = ['title' => __('error'), 'message' => __('company_not_found')];
        }

        $stmt->close();
    }
}

// Company registration
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST['action'] ?? '') === 'register') {
    $name = trim($_POST['CompanyName']);
    $email = trim($_POST['CompanyEmail']);
    $country = trim($_POST['CompanyCountry']);
    $password = password_hash($_POST['CompanyPassword'], PASSWORD_DEFAULT);
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!isValidCSRF($csrfToken)) {
        $_SESSION['popup'] = ['title' => __('error'), 'message' => __('error_security_token')];
    } else {
        $check = $con->prepare("SELECT CompanyID FROM companies WHERE CompanyEmail = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $_SESSION['popup'] = ['title' => __('error'), 'message' => __('error_email_exists')];
        } else {
            $code = rand(100000, 999999);
            $_SESSION['pending_company'] = [
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'country' => $country,
                'code' => $code
            ];

            if (sendVerificationEmail($email, $code)) {
                header("Location: verify-code.php");
                exit();
            } else {
                $_SESSION['popup'] = ['title' => __('error'), 'message' => __('error_email_sending')];
            }
        }

        $check->close();
    }
}

$countries = [
    "Afghanistan", "Albania", "Algeria", "Andorra", "Angola", "Argentina", "Armenia", "Australia",
    "Austria", "Azerbaijan", "Bahrain", "Bangladesh", "Belarus", "Belgium", "Belize", "Benin",
    "Bhutan", "Bolivia", "Bosnia and Herzegovina", "Botswana", "Brazil", "Brunei", "Bulgaria",
    "Burkina Faso", "Burundi", "Cambodia", "Cameroon", "Canada", "Cape Verde", "Central African Republic",
    "Chad", "Chile", "China", "Colombia", "Comoros", "Congo (Brazzaville)", "Congo (Kinshasa)",
    "Costa Rica", "Croatia", "Cuba", "Cyprus", "Czech Republic", "Denmark", "Djibouti", "Dominica",
    "Dominican Republic", "Ecuador", "Egypt", "El Salvador", "Equatorial Guinea", "Eritrea", "Estonia",
    "Eswatini", "Ethiopia", "Fiji", "Finland", "France", "Gabon", "Gambia", "Georgia", "Germany",
    "Ghana", "Greece", "Grenada", "Guatemala", "Guinea", "Guinea-Bissau", "Guyana", "Haiti",
    "Honduras", "Hungary", "Iceland", "India", "Indonesia", "Iran", "Iraq", "Ireland", "Italy",
    "Ivory Coast", "Jamaica", "Japan", "Jordan", "Kazakhstan", "Kenya", "Kiribati", "Kuwait",
    "Kyrgyzstan", "Laos", "Latvia", "Lebanon", "Lesotho", "Liberia", "Libya", "Liechtenstein",
    "Lithuania", "Luxembourg", "Madagascar", "Malawi", "Malaysia", "Maldives", "Mali", "Malta",
    "Marshall Islands", "Mauritania", "Mauritius", "Mexico", "Micronesia", "Moldova", "Monaco",
    "Mongolia", "Montenegro", "Morocco", "Mozambique", "Myanmar", "Namibia", "Nauru", "Nepal",
    "Netherlands", "New Zealand", "Nicaragua", "Niger", "Nigeria", "North Korea", "North Macedonia",
    "Norway", "Oman", "Pakistan", "Palau", "Palestine", "Panama", "Papua New Guinea", "Paraguay",
    "Peru", "Philippines", "Poland", "Portugal", "Qatar", "Romania", "Russia", "Rwanda",
    "Saint Kitts and Nevis", "Saint Lucia", "Saint Vincent", "Samoa", "San Marino", "Saudi Arabia",
    "Senegal", "Serbia", "Seychelles", "Sierra Leone", "Singapore", "Slovakia", "Slovenia",
    "Solomon Islands", "Somalia", "South Africa", "South Korea", "South Sudan", "Spain", "Sri Lanka",
    "Sudan", "Suriname", "Sweden", "Switzerland", "Syria", "Taiwan", "Tajikistan", "Tanzania",
    "Thailand", "Togo", "Tonga", "Trinidad and Tobago", "Tunisia", "Turkey", "Turkmenistan",
    "Tuvalu", "Uganda", "Ukraine", "United Arab Emirates", "United Kingdom", "United States",
    "Uruguay", "Uzbekistan", "Vanuatu", "Vatican City", "Venezuela", "Vietnam", "Yemen", "Zambia",
    "Zimbabwe"
];

// Set direction based on language (assumes this is set in config or earlier)
$dir = (isset($_SESSION['lang']) && $_SESSION['lang'] === 'ar') ? 'rtl' : 'ltr';

// Get popup from session if it exists
$popup = $_SESSION['popup'] ?? null;
if ($popup) {
    unset($_SESSION['popup']); // Clear it after getting it
}
?>

<?php if ($popup): ?>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            showPopup("<?= htmlspecialchars($popup['title']) ?>", "<?= htmlspecialchars($popup['message']) ?>");
        });
    </script>
<?php endif; ?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang ?? 'en') ?>" dir="<?= $dir ?>">

<head>
    <title><?= htmlspecialchars(__('company_login_title')) ?> - BuyWise</title>
    <link rel="icon" href="../img/favicon.ico">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Rubik&display=swap" rel="stylesheet">

    <!-- CSS Libraries -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../login.css" rel="stylesheet">

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <style>
        .password-container {
            position: relative;
            display: flex;
            align-items: center;
            width: 100%;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            cursor: pointer;
            color: #666;
            font-size: 16px;
            z-index: 10;
            padding: 4px;
            transition: color 0.3s ease;
        }

        .password-toggle:hover {
            color: #333;
        }

        .loginp.password-input {
            padding-right: 45px !important;
        }

        /* RTL support for Arabic */
        [dir="rtl"] .password-toggle {
            right: auto;
            left: 12px;
        }

        [dir="rtl"] .loginp.password-input {
            padding-right: 15px !important;
            padding-left: 45px !important;
        }
    </style>
</head>

<body class="loginn">

    <div class="container-fluid px-lg-5 d-none d-lg-block main"></div>

    <!-- Navbar Start -->
    <?php include("../header.php"); ?>
    <!-- Navbar End -->

    <div class="logcontainer" id="logcontainer">
        <!-- Login Form Start -->
        <div class="form-container log-in-container">
            <form class="loginform" action="<?= htmlspecialchars($_SERVER["PHP_SELF"]) ?>" method="post" id="companyLoginForm">
                <h3><?= htmlspecialchars(__('company_login')) ?></h3>
                <div class="logindiv">
                    <?php if (!empty($loginError)): ?>
                        <div class="alert alert-danger text-center"><?= htmlspecialchars($loginError) ?></div>
                    <?php endif; ?>

                    <input class="loginp" type="email" placeholder="<?= htmlspecialchars(__('company_email')) ?>" name="CompanyEmail" id="loginEmail" required>

                    <div class="password-container">
                        <input class="loginp password-input" type="password" placeholder="<?= htmlspecialchars(__('password')) ?>" name="CompanyPassword" id="loginPassword" required>
                        <i class="fas fa-eye password-toggle" onclick="togglePassword('loginPassword', this)"></i>
                    </div>

                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                    <div class="d-flex justify-content-between align-items-center w-100">
                        <a href="../login.php" class="user-link">
                            <?= htmlspecialchars(__('Login_as_user')) ?>
                        </a>
                        <a href="../forgot-password.php" class="forgot-password">
                            <?= htmlspecialchars(__('forgot_password')) ?>
                        </a>
                    </div>
                </div>
                <button class="logbtn" type="submit"><?= htmlspecialchars(__('login')) ?></button>
            </form>
        </div>
        <!-- Login Form End -->

        <!-- Register Form Start -->
        <div class="form-container sign-up-container" id="sign-up-container">
            <div class="form-header">
                <h3><?= htmlspecialchars(__('register_company')) ?></h3>
            </div>

            <div class="signupinp">
                <form id="companySignupForm" class="signupform" action="<?= htmlspecialchars($_SERVER["PHP_SELF"]) ?>" method="post">
                    <?php if (!empty($registerError)): ?>
                        <div class="alert alert-danger text-center"><?= htmlspecialchars($registerError) ?></div>
                    <?php endif; ?>

                    <div class="inputscontainer">
                        <div class="inputs">
                            <input class="loginp" type="text" id="CompanyName" name="CompanyName" placeholder="<?= htmlspecialchars(__('company_name')) ?>">
                            <span id="invalidName" class="invalid"></span>
                        </div>
                        <div class="inputs">
                            <input class="loginp" type="email" id="CompanyEmail" name="CompanyEmail" placeholder="<?= htmlspecialchars(__('company_email')) ?>" autocomplete="off">
                            <span id="invalidEmail" class="invalid"></span>
                        </div>
                    </div>

                    <select class="loginp" id="CompanyCountry" name="CompanyCountry">
                        <option value=""><?= htmlspecialchars(__('select_country')) ?></option>
                        <?php foreach ($countries as $c): ?>
                            <option value="<?= htmlspecialchars($c) ?>" <?= (isset($_POST['CompanyCountry']) && $_POST['CompanyCountry'] == $c ? 'selected' : '') ?>>
                                <?= htmlspecialchars($c) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span id="invalidCountry" class="invalid"></span>
                    <div class="inputscontainer">
                        <div class="inputs">
                            <div class="password-container">
                                <input class="loginp password-input" type="password" placeholder="<?= htmlspecialchars(__('password')) ?>" name="CompanyPassword" id="CompanyPassword" autocomplete="new-password">
                                <i class="fas fa-eye password-toggle" onclick="togglePassword('CompanyPassword', this)"></i>
                            </div>
                            <span id="invalidPassword" class="invalid"></span>
                        </div>
                        <div class="inputs">
                            <div class="password-container">
                                <input class="loginp password-input" type="password" id="ConfirmCompanyPassword" name="ConfirmCompanyPassword" placeholder="<?= htmlspecialchars(__('confirm_password')) ?>">
                                <i class="fas fa-eye password-toggle" onclick="togglePassword('ConfirmCompanyPassword', this)"></i>
                            </div>
                            <span id="invalidConfirmation" class="invalid"></span>
                        </div>
                    </div>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="register">
                    <button class="logbtn" id="signupButton" type="submit"><?= htmlspecialchars(__('register')) ?></button>
                    <br><br>
                    <a href="../login.php" class="user-link">
                        <?= htmlspecialchars(__('register_as_user')) ?>
                    </a>
                </form>
            </div>
        </div>
        <!-- Register Form End -->

        <!-- Overlay Panel for SignIn/SignUp Toggle -->
        <div class="overlay-container">
            <div class="overlay">
                <div class="overlay-panel overlay-left">
                    <h1><?= htmlspecialchars(__('hello_company')) ?></h1>
                    <p><?= htmlspecialchars(__('enter_company_details_msg')) ?></p>
                    <button class="ghost logbtn" id="signUp"><?= htmlspecialchars(__('sign_up')) ?></button>
                </div>
                <div class="overlay-panel overlay-right">
                    <h1><?= htmlspecialchars(__('welcome_back_company')) ?></h1>
                    <p><?= htmlspecialchars(__('keep_connected_company_msg')) ?></p>
                    <button class="ghost logbtn" id="signIn"><?= htmlspecialchars(__('sign_in')) ?></button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Password toggle functionality
        function togglePassword(inputId, icon) {
            const input = document.getElementById(inputId);
            const isPassword = input.type === 'password';

            input.type = isPassword ? 'text' : 'password';
            icon.className = isPassword ? 'fas fa-eye-slash password-toggle' : 'fas fa-eye password-toggle';
        }

        document.addEventListener('DOMContentLoaded', function() {
            const signUpButton = document.getElementById('signUp');
            const signInButton = document.getElementById('signIn');
            const container = document.getElementById('logcontainer');
            const urlParams = new URLSearchParams(window.location.search);

            // If verified=1, ensure Sign In panel is shown (remove right-panel-active)
            if (urlParams.get('verified') === '1') {
                container.classList.remove('right-panel-active');
            }

            if (signUpButton && signInButton && container) {
                signUpButton.addEventListener('click', () => {
                    container.classList.add("right-panel-active");
                });

                signInButton.addEventListener('click', () => {
                    container.classList.remove("right-panel-active");
                });
            }

            // Form validation for signup
            document.getElementById('companySignupForm').addEventListener('submit', function(e) {
                const companyName = document.getElementById('CompanyName').value.trim();
                const companyEmail = document.getElementById('CompanyEmail').value.trim();
                const companyCountry = document.getElementById('CompanyCountry').value.trim();
                const companyPassword = document.getElementById('CompanyPassword').value.trim();
                const confirmPassword = document.getElementById('ConfirmCompanyPassword').value.trim();
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                let valid = true;

                // Reset previous error messages
                document.querySelectorAll('.invalid').forEach(el => el.innerHTML = '');

                // Name validation
                if (companyName === '') {
                    document.getElementById('invalidName').innerHTML = "<?= htmlspecialchars(__('error_field_required')) ?>";
                    valid = false;
                }

                // Email validation
                if (companyEmail === '') {
                    document.getElementById('invalidEmail').innerHTML = "<?= htmlspecialchars(__('error_field_required')) ?>";
                    valid = false;
                } else if (!emailRegex.test(companyEmail)) {
                    document.getElementById('invalidEmail').innerHTML = "<?= htmlspecialchars(__('error_invalid_email')) ?>";
                    valid = false;
                }

                // Country validation
                if (companyCountry === '') {
                    document.getElementById('invalidCountry').innerHTML = "<?= htmlspecialchars(__('error_field_required')) ?>";
                    valid = false;
                }

                // Password validation
                if (companyPassword === '') {
                    document.getElementById('invalidPassword').innerHTML = "<?= htmlspecialchars(__('error_field_required')) ?>";
                    valid = false;
                } else if (companyPassword.length < 8) {
                    document.getElementById('invalidPassword').innerHTML = "<?= htmlspecialchars(__('error_password_length')) ?>";
                    valid = false;
                } else if (!/[A-Z]/.test(companyPassword)) {
                    document.getElementById('invalidPassword').innerHTML = "<?= htmlspecialchars(__('error_password_uppercase')) ?>";
                    valid = false;
                } else if (!/[a-z]/.test(companyPassword)) {
                    document.getElementById('invalidPassword').innerHTML = "<?= htmlspecialchars(__('error_password_lowercase')) ?>";
                    valid = false;
                } else if (!/[0-9]/.test(companyPassword)) {
                    document.getElementById('invalidPassword').innerHTML = "<?= htmlspecialchars(__('error_password_number')) ?>";
                    valid = false;
                } else if (!/[^A-Za-z0-9]/.test(companyPassword)) {
                    document.getElementById('invalidPassword').innerHTML = "<?= htmlspecialchars(__('error_password_special')) ?>";
                    valid = false;
                }

                // Confirm password validation
                if (confirmPassword === '') {
                    document.getElementById('invalidConfirmation').innerHTML = "<?= htmlspecialchars(__('error_field_required')) ?>";
                    valid = false;
                } else if (companyPassword !== confirmPassword) {
                    document.getElementById('invalidConfirmation').innerHTML = "<?= htmlspecialchars(__('error_passwords_mismatch')) ?>";
                    valid = false;
                }

                if (!valid) {
                    e.preventDefault();
                }
            });
        });
    </script>
    
    <div class="custom-popup" id="customPopup" style="display: none;">
        <div class="custom-popup-content">
            <h5 id="popupTitle">Error</h5>
            <p id="popupMessage">Something went wrong</p>
            <button onclick="hidePopup()" class="custom-ok-button">OK</button>
        </div>
    </div>

    <script>
        function showPopup(title, message) {
            document.getElementById("popupTitle").innerText = title;
            document.getElementById("popupMessage").innerText = message;
            document.getElementById("customPopup").style.display = "flex";
            document.body.classList.add("no-scroll");
        }

        function hidePopup() {
            document.getElementById("customPopup").style.display = "none";
            document.body.classList.remove("no-scroll");
        }
    </script>

</body>

</html>