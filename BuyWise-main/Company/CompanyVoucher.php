<?php
@session_start();
require_once '../config.php';

$CompanyID = $_SESSION['CompanyID'] ?? 0;
$popupMessage = '';
$popupType = '';
$scrollToTable = false;
$tabToActivate = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['VoucherCode'])) {
    $VoucherCode = trim($_POST['VoucherCode']);
    $Discount = floatval($_POST['Discount']);
    $MinPoints = intval($_POST['MinPoints']);
    $ExpiryDate = $_POST['ExpiryDate'];

    if ($VoucherCode && $Discount > 0 && $MinPoints >= 0 && $ExpiryDate) {
        $stmt = $con->prepare("INSERT INTO company_vouchers (CompanyID, VoucherCode, Discount, MinPoints, ExpiryDate) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isdss", $CompanyID, $VoucherCode, $Discount, $MinPoints, $ExpiryDate);
        $stmt->execute();
        $stmt->close();

        $_SESSION['popup'] = __('voucher_added_successfully');
        $_SESSION['showVoucherPopup'] = true;
        header("Location: CompanyDashboard.php#voucher");
        exit();
    } else {
        $popupMessage = __('please_fill_all_fields');
        $popupType = 'danger';
    }
}


// جلب القسائم الحالية
$stmt = $con->prepare("SELECT * FROM company_vouchers WHERE CompanyID = ? ORDER BY ExpiryDate DESC");
$stmt->bind_param("i", $CompanyID);
$stmt->execute();
$vouchers = $stmt->get_result();
$stmt->close();
?>

<?php if (!empty($_SESSION['popup']) && isset($_SESSION['showVoucherPopup'])): ?>
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
  });
</script>
<?php unset($_SESSION['popup']); unset($_SESSION['showVoucherPopup']); ?>
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

<div class="card shadow p-4 mb-4">
    <h4 class="mb-3"><?= __('add_new_voucher') ?></h4>
    <form method="post" class="row g-3">
    <div class="col-md-3">
        <label class="form-label"><?= __('voucher_code') ?></label>
        <input type="text" name="VoucherCode" class="form-control" required>
    </div>
    <div class="col-md-3">
        <label class="form-label"><?= __('discount_value') ?> (%)</label>
        <input type="number" name="Discount" step="0.01" min="0.1" max="100" class="form-control" required>
    </div>
    <div class="col-md-3">
        <label class="form-label"><?= __('min_points_required') ?></label>
        <input type="number" name="MinPoints" min="0" class="form-control" required>
    </div>

    <div class="col-md-3">
        <label class="form-label"><?= __('expiry_date') ?></label>
        <input type="text" id="ExpiryDate" name="ExpiryDate" class="form-control" required
            placeholder="<?= $lang === 'ar' ? 'اختر تاريخ الانتهاء' : 'Select expiry date' ?>">
    </div>

    <div class="col-12 text-end">
        <button type="submit" class="btn btn-primary"><?= __('add_voucher') ?></button>
    </div>
</form>

</div>

<?php if ($vouchers->num_rows > 0): ?>
    <div class="card shadow p-4" id="voucher-table">
        <h4 class="mb-3"><?= __('your_vouchers') ?></h4>
        <div class="table-responsive">
            <table class="table table-bordered align-middle text-center">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th><?= __('voucher_code') ?></th>
                        <th><?= __('discount_value') ?> (%)</th>
                        <th><?= __('min_points_required') ?></th>
                        <th><?= __('expiry_date') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; while ($voucher = $vouchers->fetch_assoc()): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($voucher['VoucherCode']) ?></td>
                            <td><?= number_format($voucher['Discount'], 2) ?></td>
                            <td><?= intval($voucher['MinPoints']) ?></td>
                            <td><?= htmlspecialchars($voucher['ExpiryDate']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="alert alert-info"><?= __('no_vouchers_found') ?></div>
<?php endif; ?>
