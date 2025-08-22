<?php
require_once "../config.php";

// Redirect non-admin users
if (!isset($_SESSION['type'], $_SESSION['UserID']) || $_SESSION['type'] != 1) {
    header("Location: ../login.php");
    exit();
}

$AdminID = $_SESSION['UserID'];
$passwordUpdated = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $AdminName = trim($_POST['AdminName'] ?? '');
    $AdminEmail = trim($_POST['AdminEmail'] ?? '');
    $CurrentPassword = $_POST['CurrentPassword'] ?? '';
    $NewPassword = $_POST['AdminPassword'] ?? '';
    $ConfirmPassword = $_POST['ConfirmPassword'] ?? '';

    // Get current admin password
    $stmt = $con->prepare("SELECT AdminPassword FROM admins WHERE AdminID = ?");
    $stmt->bind_param("i", $AdminID);
    $stmt->execute();
    $adminData = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$adminData) {
        $_SESSION['popup'] = __('admin_not_found');
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }

    $hasError = false;

    // Email uniqueness check
    if (!empty($AdminEmail)) {
        $stmt = $con->prepare("SELECT AdminID FROM admins WHERE AdminEmail = ? AND AdminID != ?");
        $stmt->bind_param("si", $AdminEmail, $AdminID);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $_SESSION['popup'] = __('email_in_use');
            $hasError = true;
        }
        $stmt->close();
    }

    // Password verification
    if (!$hasError && !empty($NewPassword)) {
        $stored = $adminData['AdminPassword'] ?? '';
        $passwordCorrect = false;

        if (strlen($stored) > 40 && str_starts_with($stored, '$2')) {
            $passwordCorrect = password_verify($CurrentPassword, $stored);
        } elseif (!empty($CurrentPassword)) {
            $passwordCorrect = $CurrentPassword === $stored || md5($CurrentPassword) === $stored;
        }

        if (empty($CurrentPassword)) {
            $_SESSION['popup'] = __('current_password_required');
            $hasError = true;
        } elseif (!$passwordCorrect) {
            $_SESSION['popup'] = __('incorrect_current_password');
            $hasError = true;
        } elseif ($NewPassword !== $ConfirmPassword) {
            $_SESSION['popup'] = __('passwords_do_not_match');
            $hasError = true;
        } else {
            $hashedPassword = password_hash($NewPassword, PASSWORD_DEFAULT);
            $passwordUpdated = true;
        }
    }

    // Update admin info
    if (!$hasError) {
        if ($passwordUpdated) {
            $stmt = $con->prepare("UPDATE admins SET AdminName = ?, AdminEmail = ?, AdminPassword = ? WHERE AdminID = ?");
            $stmt->bind_param("sssi", $AdminName, $AdminEmail, $hashedPassword, $AdminID);
        } else {
            $stmt = $con->prepare("UPDATE admins SET AdminName = ?, AdminEmail = ? WHERE AdminID = ?");
            $stmt->bind_param("ssi", $AdminName, $AdminEmail, $AdminID);
        }

        if ($stmt->execute()) {
            $_SESSION['admin_name'] = $AdminName;
            $_SESSION['admin_email'] = $AdminEmail;
            $_SESSION['popup'] = __('info_updated');

            if ($passwordUpdated) {
                $_SESSION['password_changed'] = true;
            }

            $stmt->close();
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit();
        } else {
            $_SESSION['popup'] = __('update_failed') . ': ' . $con->error;
            $stmt->close();
            header("Location: " . $_SERVER['REQUEST_URI']);
            exit();
        }
    }
}

// Handle account deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    $stmt = $con->prepare("DELETE FROM admins WHERE AdminID = ?");
    $stmt->bind_param("i", $AdminID);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $stmt->close();
        session_unset();
        session_destroy();
        header("Location: ../login.php?message=" . urlencode(__('account_deleted')));
        exit();
    } else {
        $_SESSION['popup'] = __('update_failed') . ': ' . $con->error;
        $stmt->close();
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit();
    }
}

// Handle logout after password change
if (!empty($_SESSION['password_changed'])) {
    $_SESSION['login_message'] = __('password_updated_login_again');
    session_unset();
    session_destroy();
    header("Location: ../login.php");
    exit();
}

// Load admin profile info
$stmt = $con->prepare("SELECT AdminName, AdminEmail FROM admins WHERE AdminID = ?");
$stmt->bind_param("i", $AdminID);
$stmt->execute();
$adminData = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">
<head>
  <title><?= __('admin_settings') ?> | BuyWise</title>
  <link rel="icon" href="../img/favicon.ico">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Rubik:wght@400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../style.css">
  <link rel="stylesheet" href="Admin.css">
  <?php include("../header.php"); ?>
</head>
<body class="admin">

<?php if ($showPopup): ?>
<!-- Inline popup for success/error messages -->
<div class="popup show" id="popup">
  <div class="popup-content">
    <p><?= htmlspecialchars($popupMessage) ?></p>
    <button class="okbutton btn btn-primary btn-sm" onclick="closePopup()"><?= __('ok') ?></button>
  </div>
</div>
<script>
  function closePopup() {
    document.getElementById('popup')?.classList.remove('show');
  }
  window.addEventListener('DOMContentLoaded', () => {
    setTimeout(closePopup, 3500);
  });
</script>
<?php endif; ?>

<!-- Hidden popup triggered via JavaScript -->
<div class="popup" id="popup-js">
  <div class="popup-content">
    <p id="popup-message"></p>
    <div class="d-flex justify-content-center gap-3 mt-3">
      <button class="okbutton btn btn-primary btn-sm" id="popup-ok">OK</button>
      <button class="cancelbutton btn btn-secondary btn-sm" id="popup-cancel" style="display: none;">Cancel</button>
    </div>
  </div>
</div>

<!-- Breadcrumb navigation -->
<div class="admin-breadcrumb-wrapper">
  <div class="container">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item">
          <a href="Dashboard.php"><i class="fas fa-home me-1"></i> <?= __('admin_dashboard') ?></a>
        </li>
        <li class="breadcrumb-item active" aria-current="page">
          <i class="fas fa-folder me-1"></i> <?= __('manage_account') ?>
        </li>
      </ol>
    </nav>
  </div>
</div>

<!-- Account update form -->
<div class="container d-flex justify-content-center align-items-center py-5">
  <div class="account-container">
    <h4 class="mb-4 text-center">
      <i class="fas fa-user-cog me-2"></i><?= __('manage_account') ?>
    </h4>
    <form method="POST">
      <div class="row mb-3">
        <div class="col-md-6">
          <label class="form-label"><?= __('admin_name') ?></label>
          <input type="text" name="AdminName" class="form-control" value="<?= htmlspecialchars($adminData['AdminName']) ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label"><?= __('email') ?></label>
          <input type="email" class="form-control" value="<?= htmlspecialchars($adminData['AdminEmail']) ?>" disabled readonly>
        </div>
      </div>

      <div class="alert alert-info">
        <i class="fas fa-info-circle me-1"></i> <?= __('password_info') ?>
      </div>

      <div class="row mb-3">
        <div class="col-md-6">
          <label class="form-label"><?= __('current_password') ?></label>
          <div class="password-container">
            <input type="password" name="CurrentPassword" id="CurrentPassword" class="form-control password-input">
            <i class="fas fa-eye password-toggle" toggle="#CurrentPassword"></i>
          </div>
        </div>
      </div>

      <div class="row mb-4">
        <div class="col-md-6">
          <label class="form-label"><?= __('new_password') ?></label>
          <div class="password-container">
            <input type="password" name="AdminPassword" id="AdminPassword" class="form-control password-input">
            <i class="fas fa-eye password-toggle" toggle="#AdminPassword"></i>
          </div>
        </div>
        <div class="col-md-6">
          <label class="form-label"><?= __('confirm_password') ?></label>
          <div class="password-container">
            <input type="password" name="ConfirmPassword" id="ConfirmPassword" class="form-control password-input">
            <i class="fas fa-eye password-toggle" toggle="#ConfirmPassword"></i>
          </div>
        </div>
      </div>

      <button class="btn btn-primary w-100 text-center"><?= __('update_info') ?></button>

      <!-- Delete account button -->
      <div class="text-center mt-4">
        <button type="button" class="btn btn-outline-danger w-100" onclick="confirmAccountDeletion()">
          <i class="fas fa-trash-alt me-1"></i> <?= __('delete_my_account') ?>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Footer -->
<footer class="footer fixed-footer mt-auto py-3">
  <div class="container text-center">
    <p class="mb-0">&copy; <?= date('Y') ?> <a href="#">BuyWise</a>. <?= __('all_rights_reserved') ?></p>
  </div>
</footer>

<script>
// Show generic popup with message
function showPopup(message) {
  popupMessage.textContent = message;
  popup.classList.add('show');
  const okButton = document.getElementById("popup-ok");
  const cancelButton = document.getElementById("popup-cancel");
  cancelButton.style.display = "none";
  const closeHandler = () => {
    popup.classList.remove("show");
    okButton.removeEventListener("click", closeHandler);
  };
  okButton.addEventListener("click", closeHandler);
  setTimeout(closeHandler, 3500);
}

// Hide popup manually
function closePopup() {
  document.getElementById('popup')?.classList.remove('show');
}

// Confirm account deletion with custom modal
function confirmAccountDeletion() {
  const confirmPopup = document.getElementById("confirmDeletePopup");
  confirmPopup.classList.add("show");
  const confirmBtn = document.getElementById("confirmDelete");
  const cancelBtn = document.getElementById("cancelDelete");
  const handler = () => {
    confirmPopup.classList.remove("show");
    confirmBtn.removeEventListener("click", handler);
    cancelBtn.removeEventListener("click", cancelHandler);
    const form = document.createElement("form");
    form.method = "POST";
    form.action = "";
    form.innerHTML = '<input type="hidden" name="delete_account" value="1">';
    document.body.appendChild(form);
    form.submit();
  };
  const cancelHandler = () => {
    confirmPopup.classList.remove("show");
    confirmBtn.removeEventListener("click", handler);
    cancelBtn.removeEventListener("click", cancelHandler);
  };
  confirmBtn.addEventListener("click", handler);
  cancelBtn.addEventListener("click", cancelHandler);
}

// Auto show popup if needed
<?php if ($showPopup): ?>
window.addEventListener('DOMContentLoaded', () => {
  showPopup('<?= $popupMessage ?>');
});
<?php endif; ?>

// Toggle password visibility
document.querySelectorAll('.password-toggle').forEach(toggle => {
  toggle.addEventListener('click', function() {
    const input = document.querySelector(this.getAttribute('toggle'));
    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
    input.setAttribute('type', type);
    this.classList.toggle('fa-eye-slash');
    this.classList.toggle('fa-eye');
  });
});
</script>

<!-- Delete confirmation popup -->
<div id="confirmDeletePopup" class="popup">
  <div class="popup-content">
    <p><?= __('confirm_delete_account') ?></p>
    <div class="d-flex justify-content-center gap-3 mt-3">
      <button id="confirmDelete" class="btn btn-danger"><?= __('delete') ?></button>
      <button id="cancelDelete" class="btn btn-outline-secondary"><?= __('cancel') ?></button>
    </div>
  </div>
</div>

</body>
</html>
