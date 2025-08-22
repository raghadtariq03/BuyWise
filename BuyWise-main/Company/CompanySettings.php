<?php
require_once '../config.php';

// Check company access
if (!isset($_SESSION['type'], $_SESSION['CompanyID']) || $_SESSION['type'] !== 'company') {
    header("Location: ../CompanyLogin.php");
    exit();
}

$CompanyID = intval($_SESSION['CompanyID']);

// Handle company info update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $CompanyName = trim($_POST['CompanyName'] ?? '');
    $CompanyEmail = trim($_POST['CompanyEmail'] ?? '');

    if (!$CompanyName || !$CompanyEmail) {
        $_SESSION['popup'] = __('required_fields_missing');
    } elseif (strlen($CompanyName) > 100 || strlen($CompanyEmail) > 100) {
        $_SESSION['popup'] = __('field_too_long');
    } elseif (!filter_var($CompanyEmail, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['popup'] = __('invalid_email');
    } else {
        // Check if email exists for another company
        $stmt = $con->prepare("SELECT CompanyID FROM companies WHERE CompanyEmail = ? AND CompanyID != ?");
        $stmt->bind_param("si", $CompanyEmail, $CompanyID);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($exists) {
            $_SESSION['popup'] = __('email_already_exists');
        } else {
            $logoPath = null;
            $uploadError = false;

            // Handle logo upload
            //يتحقق أن الحقل موجود وتم رفع الملف بنجاح بدون اخطاء.
            if (isset($_FILES['CompanyLogo']) && $_FILES['CompanyLogo']['error'] === UPLOAD_ERR_OK) {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                $fileType = $_FILES['CompanyLogo']['type'];
                $fileSize = $_FILES['CompanyLogo']['size'];

                if (!in_array($fileType, $allowedTypes)) {
                    $_SESSION['popup'] = __('invalid_file_type');
                    $uploadError = true;
                } 
                //يعني لا يسمح اكتر من 2 ميغابايت
                elseif ($fileSize > 2 * 1024 * 1024) {
                    $_SESSION['popup'] = __('file_too_large');
                    $uploadError = true;
                } else {
                    $uploadDir = '../uploads/company_logos/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true); //انشاؤه اذا ما كان موجود

                    $extension = pathinfo($_FILES['CompanyLogo']['name'], PATHINFO_EXTENSION);
                    $fileName = 'company_' . $CompanyID . '_' . time() . '.' . $extension; //بختار اسم فريد للصوره ، بحط رقم الشركة + التوقيت لتجنب تكرار الأسماء.
                    $uploadPath = $uploadDir . $fileName;

                    //ينقل الصورة من الملف المؤقت إلى مكان التخزين الدائم.
                    if (move_uploaded_file($_FILES['CompanyLogo']['tmp_name'], $uploadPath)) {
                        // Delete old logo
                        $stmt = $con->prepare("SELECT CompanyLogo FROM companies WHERE CompanyID = ?");
                        $stmt->bind_param("i", $CompanyID);
                        $stmt->execute();
                        $currentLogo = $stmt->get_result()->fetch_assoc()['CompanyLogo'] ?? '';
                        $stmt->close();

                        $absoluteOldLogo = realpath(__DIR__ . '/../' . $currentLogo);
                        if ($currentLogo && $absoluteOldLogo && file_exists($absoluteOldLogo)) {
                            unlink($absoluteOldLogo); //ازا ما كان موجود فعليًا عالسيرفر ينمسح باستخدام أن لينك 
                        }

                        $logoPath = 'uploads/company_logos/' . $fileName; //تحديث مسار الشعار الجديد 
                        //هلأ بنقدر نستخدم هالمتغير لوجو باث لقدام لانه يحمل مسار اللوجو الجديد
                    } 
                    else {
                        $_SESSION['popup'] = __('upload_failed');
                        $uploadError = true;
                    }
                }
            }

            // Update info
            if (!$uploadError) {
                if ($logoPath) {
                    $stmt = $con->prepare("UPDATE companies SET CompanyName = ?, CompanyEmail = ?, CompanyLogo = ? WHERE CompanyID = ?");
                    $stmt->bind_param("sssi", $CompanyName, $CompanyEmail, $logoPath, $CompanyID);
                } else {
                    $stmt = $con->prepare("UPDATE companies SET CompanyName = ?, CompanyEmail = ? WHERE CompanyID = ?");
                    $stmt->bind_param("ssi", $CompanyName, $CompanyEmail, $CompanyID);
                }

                if ($stmt->execute()) {
                    $_SESSION['popup'] = __('info_updated');
                } else {
                    $_SESSION['popup'] = __('update_failed');
                }
                $stmt->close();
            }
        }
    }

    $_SESSION['tab'] = 'settings';
    header("Location: CompanyDashboard.php");
    exit();
}

// Get current company info
$stmt = $con->prepare("SELECT CompanyName, CompanyEmail, CompanyLogo FROM companies WHERE CompanyID = ?");
$stmt->bind_param("i", $CompanyID);
$stmt->execute();
$company = $stmt->get_result()->fetch_assoc();
$stmt->close();

$CompanyName = $company['CompanyName'] ?? '';
$CompanyEmail = $company['CompanyEmail'] ?? '';
$CompanyLogo = $company['CompanyLogo'] ?? '';
?>

<?php if (!empty($_SESSION['popup'])): ?>
<div class="popup show" id="popup">
  <div class="popup-content">
    <p><?= htmlspecialchars($_SESSION['popup']) ?></p>
    <button class="okbutton btn btn-primary btn-sm" onclick="closePopup()">OK</button>
  </div>
</div>
<script>
  function closePopup() {
    document.getElementById('popup')?.classList.remove('show');
  }
  window.addEventListener('DOMContentLoaded', function () {
    setTimeout(() => {
      closePopup();
    }, 3500);
    const tab = document.querySelector('[data-bs-target="#settings"]');
    if (tab) new bootstrap.Tab(tab).show();
  });
</script>
<?php unset($_SESSION['popup']); unset($_SESSION['tab']); ?>
<?php endif; ?>

<div class="popup" id="popup-js">
  <div class="popup-content">
    <p id="popup-message"></p>
    <div class="d-flex justify-content-center gap-3 mt-3">
      <button class="okbutton btn btn-primary btn-sm" id="popup-ok">OK</button>
      <button class="cancelbutton btn btn-secondary btn-sm" id="popup-cancel" style="display: none;">Cancel</button>
    </div>
  </div>
</div>

<div class="card shadow p-4 mt-3" id="settings-section">
    <h4 class="mb-3"><?= __('account_settings') ?></h4>
    <form method="post" enctype="multipart/form-data" class="row g-3">
        <div class="col-md-6">
            <label class="form-label"><?= __('company_name') ?></label>
            <input type="text" name="CompanyName" class="form-control" value="<?= htmlspecialchars($CompanyName) ?>" maxlength="100" required>
        </div>

        <div class="col-md-6">
            <label class="form-label"><?= __('email') ?></label>
            <input type="email" name="CompanyEmail" class="form-control bg-light" value="<?= htmlspecialchars($CompanyEmail) ?>" maxlength="100" readonly>
        </div>

        <div class="col-12">
            <label class="form-label"><?= __('company_logo') ?> (Max 2MB)</label>
            <input type="file" name="CompanyLogo" class="form-control" accept="image/png,image/jpeg,image/webp,image/gif">
            <?php if (!empty($CompanyLogo) && file_exists(__DIR__ . '/../' . $CompanyLogo)): ?>
                <div class="mt-2">
                    <small class="text-muted"><?= __('current_logo') ?>:</small><br>
                    <img src="../<?= htmlspecialchars($CompanyLogo) ?>" alt="Current Logo" class="img-thumbnail" style="max-width: 150px; max-height: 150px;">
                </div>
            <?php endif; ?>
        </div>


        <div class="col-12 text-end">
            <button type="submit" class="btn btn-primary"><?= __('update') ?></button>
        </div>
    </form>
</div>
