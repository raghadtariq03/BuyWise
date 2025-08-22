<?php
@session_start();
require_once '../config.php';

// Restrict access to admins
if (!isset($_SESSION['type']) || $_SESSION['type'] != 1 || !isset($_SESSION['UserID'])) {
    header("Location: ../login.php");
    exit;
}

// Handle POST actions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $upload_dir = "uploads/categories/";
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    // Add category
    if (isset($_POST['CategoryName_en'], $_POST['CategoryName_ar'], $_POST['CategoryStatus'], $_FILES['CategoryImage'])) {
        $name_en = trim($_POST['CategoryName_en']);
        $name_ar = trim($_POST['CategoryName_ar']);
        $status = (int)$_POST['CategoryStatus'];
        $file = $_FILES['CategoryImage'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $fileName = time() . '_' . preg_replace("/[^a-zA-Z0-9._-]/", "", basename($file['name']));
        $path = $upload_dir . $fileName;

        $check = $con->prepare("SELECT CategoryID FROM categories WHERE CategoryName_en = ? OR CategoryName_ar = ?");
        $check->bind_param("ss", $name_en, $name_ar);
        $check->execute();
        $check->store_result();
        if ($check->num_rows > 0) { echo 0; exit; }

        if (in_array($ext, $allowed) && move_uploaded_file($file['tmp_name'], $path)) {
            $stmt = $con->prepare("INSERT INTO categories (CategoryName_en, CategoryName_ar, CategoryImage, CategoryStatus) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sssi", $name_en, $name_ar, $fileName, $status);
            echo $stmt->execute() ? 1 : 2;
        } else {
            echo 5;
        }
        exit;
    }

    // Edit category
    if (isset($_POST['EditCategory'], $_POST['CategoryID'], $_POST['CategoryName_en'], $_POST['CategoryName_ar'], $_POST['CategoryStatus'])) {
        $id = (int)$_POST['CategoryID'];
        $name_en = trim($_POST['CategoryName_en']);
        $name_ar = trim($_POST['CategoryName_ar']);
        $status = (int)$_POST['CategoryStatus'];
        $stmt = $con->prepare("UPDATE categories SET CategoryName_en = ?, CategoryName_ar = ?, CategoryStatus = ? WHERE CategoryID = ?");
        $stmt->bind_param("ssii", $name_en, $name_ar, $status, $id);
        $_SESSION['popup'] = $stmt->execute() ? __('category_updated') : __('update_failed');
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }

    // Toggle status
    if (isset($_POST['CategoryID'], $_POST['CategoryStatus'])) {
        $stmt = $con->prepare("UPDATE categories SET CategoryStatus = ? WHERE CategoryID = ?");
        $stmt->bind_param("ii", $_POST['CategoryStatus'], $_POST['CategoryID']);
        $stmt->execute();
        echo $stmt->affected_rows > 0 ? 1 : 2;
        exit;
    }

    // Delete category
    if (isset($_POST['DeleteCategoryID'])) {
        $stmt = $con->prepare("DELETE FROM categories WHERE CategoryID = ?");
        $stmt->bind_param("i", $_POST['DeleteCategoryID']);
        $stmt->execute();
        echo $stmt->affected_rows > 0 ? 1 : 2;
        exit;
    }
}

// Count total categories
$totalCategories     = $con->query("SELECT COUNT(*) AS total FROM categories")->fetch_assoc()['total'] ?? 0;
$activeCategories    = $con->query("SELECT COUNT(*) AS active FROM categories WHERE CategoryStatus = 1")->fetch_assoc()['active'] ?? 0;
$inactiveCategories  = $con->query("SELECT COUNT(*) AS inactive FROM categories WHERE CategoryStatus = 0")->fetch_assoc()['inactive'] ?? 0;
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">
<head>
  <title><?= __('admin_categories') ?> | BuyWise</title>
  <link rel="icon" href="../img/favicon.ico">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
  <link href="../style.css" rel="stylesheet">
  <link href="Admin.css" rel="stylesheet">
  <?php include("../header.php"); ?>
</head>
<body class="admin">

<?php if ($showPopup): ?>
  <div class="popup show" id="popup">
    <div class="popup-content">
      <p><?= htmlspecialchars($popupMessage ?? '') ?></p>
      <button class="okbutton btn btn-primary btn-sm" onclick="closePopup()">OK</button>
    </div>
  </div>
  <script>
    function closePopup() {
      document.getElementById('popup')?.classList.remove('show');
    }
    window.addEventListener('DOMContentLoaded', () => setTimeout(closePopup, 3500));
  </script>
<?php endif; ?>

<!-- JavaScript-driven popup -->
<div class="popup" id="popup-js">
  <div class="popup-content">
    <p id="popup-message"></p>
    <div class="d-flex justify-content-center gap-3 mt-3">
      <button id="popup-ok" class="okbutton btn btn-primary btn-sm">OK</button>
      <button id="popup-cancel" class="cancelbutton btn btn-secondary btn-sm" style="display: none;">Cancel</button>
    </div>
  </div>
</div>

<!-- Breadcrumb Navigation -->
<div class="admin-breadcrumb-wrapper">
  <div class="container">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="Dashboard.php"><i class="fas fa-home me-1"></i> <?= __('admin_dashboard') ?></a></li>
        <li class="breadcrumb-item active"><i class="fas fa-folder me-1"></i> <?= __('manage_categories') ?></li>
      </ol>
    </nav>
  </div>
</div>

<div class="container py-5">

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-accent">
                <div class="card-body text-center">
                    <i class="fas fa-folder fa-2x mb-2 text-accent"></i>
                    <h4 class="fw-bold text-accent"><?= $totalCategories ?></h4>
                    <p class="mb-0"><?= __('total_categories') ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-accent">
                <div class="card-body text-center">
                    <i class="fas fa-check-circle fa-2x mb-2 text-accent"></i>
                    <h4 class="fw-bold text-accent"><?= $activeCategories ?></h4>
                    <p class="mb-0"><?= __('active_categories') ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-accent">
                <div class="card-body text-center">
                    <i class="fas fa-pause-circle fa-2x mb-2 text-accent"></i>
                    <h4 class="fw-bold text-accent"><?= $inactiveCategories ?></h4>
                    <p class="mb-0"><?= __('inactive_categories') ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Add New Category Form -->
    <div class="admin-card mb-4">
    <h5 class="mb-3"><i class="fas fa-plus-circle me-2"></i><?= __('add_new_category') ?></h5>
    <form class="row g-3" id="uploadimagecategories" enctype="multipart/form-data">

        <!-- English Category Name -->
        <div class="col-md-3">
        <input type="text" class="form-control" id="CategoryName_en" placeholder="<?= __('category_name_en') ?>">
        </div>

        <!-- Arabic Category Name -->
        <div class="col-md-3">
        <input type="text" class="form-control" id="CategoryName_ar" placeholder="<?= __('category_name_ar') ?>">
        </div>

        <!-- Category Image Upload -->
        <div class="col-md-3">
        <div class="custom-file-wrapper">
            <button type="button" class="form-control" onclick="document.getElementById('CategoryImage').click()">
            <i class="fas fa-upload me-2"></i> <?= $lang === 'ar' ? 'اختر ملف' : 'Choose File' ?>
            </button>
            <input type="file" id="CategoryImage" accept="image/*"
                onchange="document.getElementById('chosenFileName').textContent = this.files[0]?.name || '<?= $lang === 'ar' ? 'لم يتم اختيار ملف' : 'No file chosen' ?>'">
            <span class="text-muted small d-block mt-1" id="chosenFileName"><?= $lang === 'ar' ? 'لم يتم اختيار ملف' : 'No file chosen' ?></span>
        </div>
        </div>

        <!-- Status Dropdown -->
        <div class="col-md-3">
        <select class="form-select" id="CategoryStatus">
            <option value=""><?= __('select_category_status') ?></option>
            <option value="1"><?= __('active') ?></option>
            <option value="0"><?= __('inactive') ?></option>
        </select>
        </div>

        <!-- Submit Button -->
        <div class="col-12 <?= $lang === 'ar' ? 'text-start' : 'text-end' ?>">
        <button type="button" class="btn btn-primary"
                onclick="AddCategory(CategoryName_en.value, CategoryName_ar.value, CategoryImage, CategoryStatus.value)">
            <i class="fas fa-plus me-1"></i> <?= __('add_category') ?>
        </button>
        </div>
        
    </form>
    </div>

  <!-- Existing Categories Table -->
  <div class="admin-card">
    <h5 class="mb-3"><i class="fas fa-list me-2"></i><?= __('existing_categories') ?></h5>
    <div class="table-responsive">
      <table class="table table-bordered table-hover align-middle">
      <thead>
        <tr>
          <th style="width: 5%;">#</th>
          <th style="width: 20%;"><?= __('category_name_en') ?></th>
          <th style="width: 20%;"><?= __('category_name_ar') ?></th>
          <th class="text-center"><?= __('actions') ?></th>
        </tr>
      </thead>
        <tbody>
          <?php
          $res = $con->query("SELECT * FROM categories");
          if ($res->num_rows === 0) {
              echo '<tr><td colspan="6">' . __('no_categories') . '</td></tr>';
          } else {
              $i = 1;
              while ($cat = $res->fetch_assoc()) {
                  echo "<tr>
                          <td>{$i}</td>
                          <td class='text-truncate' style='max-width: 200px;' title=\"" . htmlspecialchars($cat['CategoryName_en']) . "\">" . htmlspecialchars($cat['CategoryName_en']) . "</td>
                          <td>" . htmlspecialchars($cat['CategoryName_ar']) . "</td>
                          <td>
                            <div class='d-flex justify-content-center gap-2'>
                              <!-- Toggle Status -->
                              <button type='button'
                                      class='btn btn-sm rounded-circle " . ($cat['CategoryStatus'] == 1 ? 'btn-outline-warning' : 'btn-outline-success') . "'
                                      title='" . ($cat['CategoryStatus'] == 1 ? __('deactivate') : __('activate')) . "'
                                      onclick='FunActiveDeactiveCategory(" . $cat['CategoryID'] . ", " . ($cat['CategoryStatus'] == 1 ? 0 : 1) . ")'>
                                <i class='fas fa-power-off'></i>
                              </button>

                              <!-- Edit -->
                              <button type='button'
                                      class='btn btn-outline-info btn-sm rounded-circle'
                                      onclick='openEditModal(" . $cat['CategoryID'] . ", " . json_encode($cat['CategoryName_en']) . ", " . json_encode($cat['CategoryName_ar']) . ", " . $cat['CategoryStatus'] . ")'
                                      title='" . __('edit_category') . "'>
                                <i class='fas fa-edit'></i>
                              </button>

                              <!-- Delete -->
                              <button type='button'
                                      class='btn btn-outline-danger btn-sm rounded-circle'
                                      title='" . __('delete_category') . "'
                                      onclick='FunDeleteCategory(" . $cat['CategoryID'] . ")'>
                                <i class='fas fa-trash'></i>
                              </button>
                            </div>
                          </td>
                        </tr>";
                  $i++;
              }
          }
          ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content rounded-4 border-0 shadow">
      <input type="hidden" name="EditCategory" value="1">
      <input type="hidden" id="editCategoryID" name="CategoryID">
      <div class="modal-header text-white">
        <h5 class="modal-title"><i class="fas fa-edit me-2"></i><?= __('edit_category') ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4">
        <input type="text" class="form-control rounded-pill mb-3" id="editCategoryName_en" name="CategoryName_en" placeholder="<?= __('category_name_en') ?>" required>
        <input type="text" class="form-control rounded-pill mb-3" id="editCategoryName_ar" name="CategoryName_ar" placeholder="<?= __('category_name_ar') ?>" required>
        <select class="form-select rounded-pill" id="editCategoryStatus" name="CategoryStatus" required>
          <option value="1"><?= __('active') ?></option>
          <option value="0"><?= __('inactive') ?></option>
        </select>
      </div>
      <div class="modal-footer border-0 d-flex justify-content-between px-4 pb-4">
        <button type="button" class="btn btn-light border rounded-pill px-4" data-bs-dismiss="modal"><?= __('cancel') ?></button>
        <button type="submit" class="btn btn-primary rounded-pill px-4 text-white"><?= __('save_changes') ?></button>
      </div>
    </form>
  </div>
</div>

<!-- Footer -->
<footer class="footer fixed-footer mt-auto py-3">
  <div class="container text-center">
    <p class="mb-0 text-light">&copy; <?= date('Y') ?> <a href="#" class="text-light">BuyWise</a>. <?= __('all_rights_reserved') ?></p>
  </div>
</footer>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
function showPopupMessage(message, isConfirm = false, onConfirm = null) {
  const popup = document.getElementById("popup-js");
  popup.querySelector("#popup-message").textContent = message;
  popup.classList.add("show");
  document.getElementById("popup-cancel").style.display = isConfirm ? "inline-block" : "none";

  document.getElementById("popup-ok").onclick = () => {
    popup.classList.remove("show");
    if (isConfirm && typeof onConfirm === 'function') onConfirm();
  };
  document.getElementById("popup-cancel").onclick = () => popup.classList.remove("show");
}

function AddCategory(nameEn, nameAr, imageInput, status) {
  if (!nameEn || !nameAr || status === "") return showPopupMessage("<?= __('please_fill_all_fields') ?>");
  if (!imageInput.files?.length) return showPopupMessage("<?= __('please_select_image') ?>");

  const formData = new FormData();
  formData.append('CategoryName_en', nameEn);
  formData.append('CategoryName_ar', nameAr);
  formData.append('CategoryImage', imageInput.files[0]);
  formData.append('CategoryStatus', status);

  $.ajax({
    url: "",
    type: "POST",
    data: formData,
    processData: false,
    contentType: false,
    success: function (res) {
      const msg = res == 1 ? "<?= __('category_added_successfully') ?>" :
                  res == 0 ? "<?= __('category_exists') ?>" :
                  res == 5 ? "<?= __('invalid_image_type') ?>" :
                             "<?= __('failed_to_add_category') ?>";
      showPopupMessage(msg);
      setTimeout(() => location.reload(), 1500);
    }
  });
}

function openEditModal(id, nameEn, nameAr, status) {
  document.getElementById('editCategoryID').value = id;
  document.getElementById('editCategoryName_en').value = nameEn;
  document.getElementById('editCategoryName_ar').value = nameAr;
  document.getElementById('editCategoryStatus').value = status;
  new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
}

function FunActiveDeactiveCategory(id, status) {
  const message = status === 1 ? "<?= __('confirm_activate_category') ?>" : "<?= __('confirm_deactivate_category') ?>";
  showPopupMessage(message, true, () => {
    $.post("", { CategoryID: id, CategoryStatus: status }, res => {
      showPopupMessage(res == 1 ? "<?= __('status_updated') ?>" : "<?= __('update_failed') ?>");
      setTimeout(() => location.reload(), 1500);
    });
  });
}

function FunDeleteCategory(id) {
  showPopupMessage("<?= __('confirm_delete_category') ?>", true, () => {
    $.post("", { DeleteCategoryID: id }, res => {
      showPopupMessage(res == 1 ? "<?= __('category_deleted') ?>" : "<?= __('delete_failed') ?>");
      setTimeout(() => location.reload(), 1500);
    });
  });
}

document.addEventListener('DOMContentLoaded', function () {
    const forms = document.querySelectorAll('form');

    forms.forEach(form => {
        form.addEventListener('submit', function (e) {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn && !form.dataset.confirmed) {
                e.preventDefault();

                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i><?= __('processing') ?>';
                submitBtn.disabled = true;
                setTimeout(() => {
                    form.submit();
                }, 800);
            }
        });
    });
});

</script>
</body>
</html>
