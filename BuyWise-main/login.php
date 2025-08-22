<?php
// Session & Security
@session_start();
require_once 'lang.php';


// CSRF token (Cross-Site Request Forgery توليد وحماية المستخدم من هجوم )
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); //حيكون 64 خانه لانو كل بايت لما يتحول لهكسا بصير خانتين(حرفين) عشان هيك ضربنا ب 2
}  

// Language handling
$langOptions = ['en', 'ar'];
if (isset($_GET['lang']) && in_array($_GET['lang'], $langOptions)) {
    $_SESSION['lang'] = $_GET['lang'];
    header("Location: " . htmlspecialchars(strtok($_SERVER["REQUEST_URI"], '?'))); //يعيد توجيه المستخدم لنفس الصفحة لكن بدون باراميتر اللانج
    exit();
}

$lang = $_SESSION['lang'] ?? 'en';
// Force LTR direction regardless of language
$dir = 'ltr';

// Redirect if already logged in
//  تتحقق إذا كانت متغيرات موجودة ومُعيَنة ام نَل is set 
if (isset($_SESSION['type'])) {
    if ($_SESSION['type'] == 1) {
        header("location: ../Dashboard.php");
        exit();
    } else if ($_SESSION['type'] == 2) {
        header("location: Profile.php");
        exit();
    }
}

// Countries list
$countries = [
    "Afghanistan", "Albania", "Algeria", "Andorra", "Angola", "Argentina", "Armenia", "Australia", "Austria", "Azerbaijan",
    "Bahrain", "Bangladesh", "Belarus", "Belgium", "Belize", "Benin", "Bhutan", "Bolivia", "Bosnia and Herzegovina", "Botswana",
    "Brazil", "Brunei", "Bulgaria", "Burkina Faso", "Burundi", "Cambodia", "Cameroon", "Canada", "Cape Verde", "Central African Republic",
    "Chad", "Chile", "China", "Colombia", "Comoros", "Congo (Brazzaville)", "Congo (Kinshasa)", "Costa Rica", "Croatia", "Cuba",
    "Cyprus", "Czech Republic", "Denmark", "Djibouti", "Dominica", "Dominican Republic", "Ecuador", "Egypt", "El Salvador", "Equatorial Guinea",
    "Eritrea", "Estonia", "Eswatini", "Ethiopia", "Fiji", "Finland", "France", "Gabon", "Gambia", "Georgia", "Germany", "Ghana", "Greece",
    "Grenada", "Guatemala", "Guinea", "Guinea-Bissau", "Guyana", "Haiti", "Honduras", "Hungary", "Iceland", "India", "Indonesia", "Iran",
    "Iraq", "Ireland", "Italy", "Ivory Coast", "Jamaica", "Japan", "Jordan", "Kazakhstan", "Kenya", "Kiribati", "Kuwait",
    "Kyrgyzstan", "Laos", "Latvia", "Lebanon", "Lesotho", "Liberia", "Libya", "Liechtenstein", "Lithuania", "Luxembourg", "Madagascar",
    "Malawi", "Malaysia", "Maldives", "Mali", "Malta", "Marshall Islands", "Mauritania", "Mauritius", "Mexico", "Micronesia", "Moldova",
    "Monaco", "Mongolia", "Montenegro", "Morocco", "Mozambique", "Myanmar", "Namibia", "Nauru", "Nepal", "Netherlands", "New Zealand",
    "Nicaragua", "Niger", "Nigeria", "North Korea", "North Macedonia", "Norway", "Oman", "Pakistan", "Palau", "Palestine", "Panama",
    "Papua New Guinea", "Paraguay", "Peru", "Philippines", "Poland", "Portugal", "Qatar", "Romania", "Russia", "Rwanda", "Saint Kitts and Nevis",
    "Saint Lucia", "Saint Vincent", "Samoa", "San Marino", "Saudi Arabia", "Senegal", "Serbia", "Seychelles", "Sierra Leone", "Singapore",
    "Slovakia", "Slovenia", "Solomon Islands", "Somalia", "South Africa", "South Korea", "South Sudan", "Spain", "Sri Lanka", "Sudan",
    "Suriname", "Sweden", "Switzerland", "Syria", "Taiwan", "Tajikistan", "Tanzania", "Thailand", "Togo", "Tonga", "Trinidad and Tobago",
    "Tunisia", "Turkey", "Turkmenistan", "Tuvalu", "Uganda", "Ukraine", "United Arab Emirates", "United Kingdom", "United States",
    "Uruguay", "Uzbekistan", "Vanuatu", "Vatican City", "Venezuela", "Vietnam", "Yemen", "Zambia", "Zimbabwe"
];
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="ltr">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars(__('login_title')) ?> - BuyWise</title>
    <link rel="icon" href="/product1/img/favicon.ico">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Rubik&display=swap" rel="stylesheet"> 
    
    <!-- CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="login.css" rel="stylesheet">
    <link href="/product1/header.css" rel="stylesheet">

    <!-- Password toggle styles -->
    <style>
        .password-container {
            position: relative;
            width: 100%;
        }
        
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
            font-size: 16px;
            z-index: 10;
        }
        
        .password-toggle:hover {
            color: #333;
        }
        
        /* RTL support */
        [dir="rtl"] .password-toggle {
            right: auto;
            left: 12px;
        }
        
        .password-input {
            padding-right: 40px !important;
        }
        
        [dir="rtl"] .password-input {
            padding-right: 12px !important;
            padding-left: 40px !important;
        }
    </style>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-datetimepicker/2.5.20/jquery.datetimepicker.full.min.js"></script>
</head>

<body class="loginn">
    <!-- Popup notification -->
    <?php if ($popup): ?>
    <div class="popup" id="popup">
        <h5><?= htmlspecialchars($popup['title']) ?></h5>
        <p><?= htmlspecialchars($popup['message']) ?></p>
        <button class="okbutton" onclick="closePopup()">OK</button>
    </div>
    <script>
        document.body.classList.add('active-popup');
        function closePopup() {
            document.body.classList.remove('active-popup');
            document.getElementById('popup').style.display = 'none';
            window.location.href = "login.php";
        }
    </script>
    <?php endif; ?>

    <div class="filterblur">
        <div class="container-fluid px-lg-5 d-none d-lg-block main"></div>

        <!-- Header -->
        <?php include 'header.php';?>

        <div class="logcontainer" id="logcontainer" style="top:7%;">
            <!-- Login Form -->
            <div class="form-container log-in-container">
                <form class="loginform" action="#" method="post" id="loginForm">
                    <h1>Login Account</h1>
                    <div class="logindiv">
                        <input class="loginp" type="email" placeholder="<?= htmlspecialchars(__('email')) ?>" name="Email" id="loginEmail" required>
                        <span id="invalidEmailLogin" class="invalid"></span>
                        
                        <div class="password-container">
                            <input class="loginp password-input" type="password" placeholder="<?= htmlspecialchars(__('password')) ?>" name="Password" id="loginPassword" required>
                            <i class="fas fa-eye password-toggle" id="toggleLoginPassword"></i>
                        </div>
                        <span id="invalidlogin" class="invalid"></span>
                        
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        
                        <div class="d-flex justify-content-between align-items-center w-100">
                            <a href="Company/CompanyLogin.php" class="company-register">
                                <?= htmlspecialchars(__('Login_as_company')) ?>
                            </a>
                            <a href="forgot-password.php" class="forgot-password">
                                <?= htmlspecialchars(__('forgot_password')) ?>
                            </a>
                        </div>
                    </div>
                    <button class="logbtn" type="button" id="loginButton"><?= htmlspecialchars(__('login')) ?></button>
                </form>
            </div>

            <!-- Register Form -->
            <div class="form-container sign-up-container" id="sign-up-container">
                <div class="form-header">
                    <h1><?= htmlspecialchars(__('create_account')) ?></h1>
                </div>
                <div class="signupinp">
                    <form id="signupformm" class="signupform">
                        <div class="inputscontainer">
                            <div class="inputs">  
                                <input class="loginp" type="text" id="UserName" name="UserName" placeholder="<?= htmlspecialchars(__('username')) ?>" required>
                                <span id="invalidName" class="invalid"></span>
                            </div>
                            <div class="inputs">
                                <input class="loginp" type="email" id="UserEmail" name="UserEmail" placeholder="<?= htmlspecialchars(__('email')) ?>" required>
                                <span id="invalidEmail" class="invalid"></span>
                            </div>
                        </div>
                        
                        <div class="inputscontainer">
                            <div class="inputs"> 
                                <div class="password-container">
                                    <input class="loginp password-input" type="password" id="UserPassword" name="UserPassword" placeholder="<?= htmlspecialchars(__('password')) ?>" required>
                                    <i class="fas fa-eye password-toggle" id="toggleUserPassword"></i>
                                </div>
                                <span id="invalidPassword" class="invalid"></span>
                            </div>
                            <div class="inputs">
                                <div class="password-container">
                                    <input class="loginp password-input" type="password" id="ConfirmUserPassword" name="ConfirmUserPassword" placeholder="<?= htmlspecialchars(__('confirm_password')) ?>" required>
                                    <i class="fas fa-eye password-toggle" id="toggleConfirmPassword"></i>
                                </div>
                                <span id="invalidConfirmation" class="invalid"></span>
                            </div>
                        </div>
                        
                        <div class="inputscontainer">
                            <div class="inputs">   
                                <select class="loginp" id="UserGender" name="UserGender" required>
                                    <option value="" selected><?= htmlspecialchars(__('gender')) ?></option>
                                    <option value="1"><?= htmlspecialchars(__('male')) ?></option>
                                    <option value="2"><?= htmlspecialchars(__('female')) ?></option>
                                </select>
                                <span id="invalidGender" class="invalid"></span>
                            </div>
                            <div class="inputs"> 
                                <input class="loginp" type="text" id="UserPhone" name="UserPhone" placeholder="<?= htmlspecialchars(__('phone')) ?>" required>
                                <span id="invalidPhone" class="invalid"></span>
                            </div>
                        </div>
                        
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <button class="logbtn" id="signupButton" type="button"><?= htmlspecialchars(__('sign_up')) ?></button>
                        <br><br>
                        <a href="Company/CompanyLogin.php" class="company-register">
                            <?= htmlspecialchars(__('register_as_company')) ?>
                        </a>
                    </form>
                </div>
            </div>

            <!-- Overlay Panel -->
            <div class="overlay-container">
                <div class="overlay">
                    <div class="overlay-panel overlay-left">
                        <h1><?= htmlspecialchars(__('hello_friend')) ?></h1>
                        <p><?= htmlspecialchars(__('enter_details_msg')) ?></p>
                        <button class="ghost logbtn" id="signUp"><?= htmlspecialchars(__('sign_up')) ?></button>
                    </div>
                    <div class="overlay-panel overlay-right">
                        <h1><?= htmlspecialchars(__('welcome_back')) ?></h1>
                        <p><?= htmlspecialchars(__('keep_connected_msg')) ?></p>
                        <button class="ghost logbtn" id="signIn"><?= htmlspecialchars(__('sign_in')) ?></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Global popup -->
    <div class="popup">
        <p id="result"></p>
        <button class="okbutton"><?= htmlspecialchars(__('ok')) ?></button>
    </div>
    
    <script>
        // Page initialization
        document.addEventListener('DOMContentLoaded', function () {
            const container = document.getElementById('logcontainer');
            const urlParams = new URLSearchParams(window.location.search);
            
            setupEventListeners();
            setupPasswordToggles();
        });

        // Setup all event listeners
        function setupEventListeners() {
            const signUpButton = document.getElementById('signUp');
            const signInButton = document.getElementById('signIn');
            const container = document.getElementById('logcontainer');

            // Toggle between login/register
            if (signUpButton && signInButton && container) {
                signUpButton.addEventListener('click', () => {
                    container.classList.add("right-panel-active");
                });

                signInButton.addEventListener('click', () => {
                    container.classList.remove("right-panel-active");
                });
            }

            // Form buttons
            document.getElementById('loginButton').addEventListener('click', LoginFun);
            document.getElementById('signupButton').addEventListener('click', CreateAccountFun);
            
            // Enter key handlers
            document.getElementById('loginEmail').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') LoginFun();
            });
            
            document.getElementById('loginPassword').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') LoginFun();
            });
            
            // Popup close
            document.querySelector(".okbutton").addEventListener("click", function() {
                document.body.classList.remove("active-popup");
            });
        }

        // Setup password toggle functionality
        function setupPasswordToggles() {
            function setupToggle(toggleId, inputId) {
                const toggle = document.getElementById(toggleId);
                const input = document.getElementById(inputId);
                
                if (toggle && input) {
                    toggle.addEventListener('click', function() {
                        const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                        input.setAttribute('type', type);
                        
                        // Toggle eye icon
                        if (type === 'password') {
                            toggle.classList.remove('fa-eye-slash');
                            toggle.classList.add('fa-eye');
                        } else {
                            toggle.classList.remove('fa-eye');
                            toggle.classList.add('fa-eye-slash');
                        }
                    });
                }
            }
            
            setupToggle('toggleLoginPassword', 'loginPassword');
            setupToggle('toggleUserPassword', 'UserPassword');
            setupToggle('toggleConfirmPassword', 'ConfirmUserPassword');
        }
        
        // Login function
        function LoginFun() {
            const email = document.getElementById("loginEmail").value.trim();
            const password = document.getElementById("loginPassword").value.trim();
            const csrfToken = document.querySelector("#loginForm input[name='csrf_token']").value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            let valid = true;

            // Clear errors
            document.getElementById("invalidEmailLogin").innerHTML = "";
            document.getElementById("invalidlogin").innerHTML = "";

            // Validate
            if (email === "" || password === "") {
                document.getElementById("invalidlogin").innerHTML = "<?= htmlspecialchars(__('error_required_fields')) ?>";
                valid = false;
            }

            if (!emailRegex.test(email)) {
                document.getElementById("invalidEmailLogin").innerHTML = "<?= htmlspecialchars(__('error_incorrect_email')) ?>";
                valid = false;
            }

            if (!valid) return;

            // Disable button
            document.getElementById("loginButton").disabled = true;

            // AJAX request
            $.ajax({
                type: 'POST',
                url: "LoginProcess.php",
                data: {
                    email: email,
                    password: password,
                    csrf_token: csrfToken
                },
                success: function(result) {
                    document.getElementById("loginButton").disabled = false;
                    
                    if (result == 0) {
                        document.getElementById("invalidlogin").innerHTML = "<?= htmlspecialchars(__('error_invalid_credentials')) ?>";
                    } else if (result == 1) {
                        window.location.href = 'Admin/Dashboard.php';
                    } else if (result == 2) {
                        window.location.href = 'Profile.php';
                    } else if (result == 'csrf_error') {
                        document.getElementById("invalidlogin").innerHTML = "<?= htmlspecialchars(__('error_security_token')) ?>";
                        setTimeout(() => window.location.reload(), 2000);
                    } else {
                        document.getElementById("invalidlogin").innerHTML = "<?= htmlspecialchars(__('error_server_connection')) ?>";
                    }
                },
                error: function() {
                    document.getElementById("loginButton").disabled = false;
                    document.getElementById("invalidlogin").innerHTML = "<?= htmlspecialchars(__('error_server_connection')) ?>";
                }
            });
        }

        // Registration function
        function CreateAccountFun() {
            const name = document.getElementById("UserName").value.trim();
            const email = document.getElementById("UserEmail").value.trim();
            const password = document.getElementById("UserPassword").value.trim();
            const confirmPassword = document.getElementById("ConfirmUserPassword").value.trim();
            const gender = document.getElementById("UserGender").value.trim();
            const phone = document.getElementById("UserPhone").value.trim();
            const csrfToken = document.querySelector("#signupformm input[name='csrf_token']").value;
            
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const phoneRegex = /^07\d{8}$/;
            let valid = true;
            
            // Clear all errors
            document.querySelectorAll(".invalid").forEach(el => el.innerHTML = "");

            // Validate name
            if (name === "") {
                document.getElementById("invalidName").innerHTML = "<?= htmlspecialchars(__('error_field_required')) ?>";
                valid = false;
            } else if (/\d/.test(name)) {
                document.getElementById("invalidName").innerHTML = "<?= htmlspecialchars(__('error_name_no_numbers')) ?>";
                valid = false;
            }

            // Validate email
            if (email === "") {
                document.getElementById("invalidEmail").innerHTML = "<?= htmlspecialchars(__('error_field_required')) ?>";
                valid = false;
            } else if (!emailRegex.test(email)) {
                document.getElementById("invalidEmail").innerHTML = "<?= htmlspecialchars(__('error_invalid_email')) ?>";
                valid = false;
            }

            // Validate password
            if (password === "") {
                document.getElementById("invalidPassword").innerHTML = "<?= htmlspecialchars(__('error_field_required')) ?>";
                valid = false;
            } else if (password.length < 8) {
                document.getElementById("invalidPassword").innerHTML = "<?= htmlspecialchars(__('error_password_length')) ?>";
                valid = false;
            } else if (!/[A-Z]/.test(password)) {
                document.getElementById("invalidPassword").innerHTML = "<?= htmlspecialchars(__('error_password_uppercase')) ?>";
                valid = false;
            } else if (!/[a-z]/.test(password)) {
                document.getElementById("invalidPassword").innerHTML = "<?= htmlspecialchars(__('error_password_lowercase')) ?>";
                valid = false;
            } else if (!/[0-9]/.test(password)) {
                document.getElementById("invalidPassword").innerHTML = "<?= htmlspecialchars(__('error_password_number')) ?>";
                valid = false;
            } else if (!/[^A-Za-z0-9]/.test(password)) {
                document.getElementById("invalidPassword").innerHTML = "<?= htmlspecialchars(__('error_password_special')) ?>";
                valid = false;
            }

            // Validate password confirmation
            if (confirmPassword === "") {
                document.getElementById("invalidConfirmation").innerHTML = "<?= htmlspecialchars(__('error_field_required')) ?>";
                valid = false;
            } else if (password !== confirmPassword) {
                document.getElementById("invalidConfirmation").innerHTML = "<?= htmlspecialchars(__('error_passwords_mismatch')) ?>";
                valid = false;
            }

            // Validate gender
            if (gender === "") {
                document.getElementById("invalidGender").innerHTML = "<?= htmlspecialchars(__('error_field_required')) ?>";
                valid = false;
            }

            // Validate phone
            if (phone === "") {
                document.getElementById("invalidPhone").innerHTML = "<?= htmlspecialchars(__('error_field_required')) ?>";
                valid = false;
            } else if (!phoneRegex.test(phone)) {
                document.getElementById("invalidPhone").innerHTML = "<?= htmlspecialchars(__('error_phone_format')) ?>";
                valid = false;
            }

            if (valid) {
                // Disable button
                document.getElementById("signupButton").disabled = true;
                
                $.ajax({
                    type: 'POST',
                    url: "RegisterProcess.php",
                    data: {
                        UserName: name,
                        UserEmail: email,
                        UserPassword: password,
                        UserGender: gender,
                        UserPhone: phone,
                        csrf_token: csrfToken
                    },
                    success: function(result) {
                        document.getElementById("signupButton").disabled = false;
                        
                        if (result == 0) {
                            showPopup("<?= htmlspecialchars(__('error_email_exists')) ?>"); 
                        } else if (result == 1) {
                            window.location.href = "verify-user.php";
                        } else if (result == 2) {
                            showPopup("<?= htmlspecialchars(__('error_email_sending')) ?>");
                        } else if (result == 'csrf_error') {
                            showPopup("<?= htmlspecialchars(__('error_security_token')) ?>");
                            setTimeout(() => window.location.reload(), 2000);
                        } else {
                            console.log("Registration failed. Server response:", result);
                            showPopup("<?= htmlspecialchars(__('error_registration_failed')) ?>"); 
                        }
                    },
                    error: function(xhr, status, error) {
                        document.getElementById("signupButton").disabled = false;
                        console.error("AJAX Error:", status, error);
                        console.log("Server response:", xhr.responseText);
                        showPopup("<?= htmlspecialchars(__('error_server_connection')) ?>");
                    }
                });
            }
        }

        // Show popup message
        function showPopup(message) {
            document.getElementById("result").innerHTML = message;
            document.body.classList.add("active-popup");
        }
    </script>
</body>
</html>