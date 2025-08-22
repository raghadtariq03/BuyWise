<?php
require_once '../config.php';

// Check admin access
if (!isset($_SESSION['type']) || $_SESSION['type'] != 1) {
    header("Location: ../login.php");
    exit();
}

// Get admin name
$AdminID = $_SESSION['UserID'];
$adminRes = $con->query("SELECT AdminName FROM admins WHERE AdminID = '$AdminID'");
$admin = mysqli_fetch_assoc($adminRes);
$AdminName = $admin['AdminName'] ?? 'Unknown';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update user status
    if (isset($_POST['UserID'], $_POST['UserStatus'])) {
        $UserID = intval($_POST['UserID']);
        $UserStatus = intval($_POST['UserStatus']);
        $stmt = $con->prepare("UPDATE users SET UserStatus = ? WHERE UserID = ?");
        $stmt->bind_param("ii", $UserStatus, $UserID);
        echo $stmt->execute() ? 1 : 0;
        $stmt->close();
        exit();
    }

    // Delete user
    if (isset($_POST['UserID']) && !isset($_POST['UserStatus'])) {
        $UserID = intval($_POST['UserID']);
        $stmt = $con->prepare("DELETE FROM users WHERE UserID = ?");
        $stmt->bind_param("i", $UserID);
        $stmt->execute();
        echo ($stmt->affected_rows > 0) ? 1 : 0;
        $stmt->close();
        exit();
    }
}

// User search
$search = $_GET['search'] ?? '';
if (!empty($search)) {
    $stmt = $con->prepare("SELECT * FROM users WHERE UserEmail LIKE ? ORDER BY UserName");
    $searchParam = "%{$search}%";
    $stmt->bind_param("s", $searchParam);
    $stmt->execute();
    $allUsers = $stmt->get_result();
} else {
    $allUsers = $con->query("SELECT * FROM users ORDER BY UserName");
}

// User statistics
$totalUsers = $con->query("SELECT COUNT(*) AS total FROM users")->fetch_assoc()['total'];
$activeUsers = $con->query("SELECT COUNT(*) AS active FROM users WHERE UserStatus = 1")->fetch_assoc()['active'];
$inactiveUsers = $con->query("SELECT COUNT(*) AS inactive FROM users WHERE UserStatus = 0")->fetch_assoc()['inactive'];

$genderCounts = [
    'male' => $con->query("SELECT COUNT(*) AS count FROM users WHERE UserGender = 'Male'")->fetch_assoc()['count'],
    'female' => $con->query("SELECT COUNT(*) AS count FROM users WHERE UserGender = 'Female'")->fetch_assoc()['count'],
];

// Badge stats
$badgeData = $con->query("SELECT badge, COUNT(*) AS count FROM users GROUP BY badge");
$badgeLabels = [];
$badgeCounts = [];
while ($row = $badgeData->fetch_assoc()) {
    $badgeLabels[] = $row['badge'];
    $badgeCounts[] = $row['count'];
}

// Signup per month
$monthData = $con->query("
    SELECT DATE_FORMAT(CreatedAt, '%Y-%m') AS month, COUNT(*) AS count 
    FROM users
    GROUP BY month
    ORDER BY month
"); // هون فوق فقط جاب الشهر مع السنه بعديها حسب كم بكل شهر كم يوزر سجل
$monthLabels = [];
$monthCounts = [];
while ($row = $monthData->fetch_assoc()) {
    $monthLabels[] = $row['month'];
    $monthCounts[] = $row['count'];
}
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $dir ?>">
<head>
  <meta charset="UTF-8">
  <title><?= __('manage_users') ?> | BuyWise</title>
  <link rel="icon" href="../img/favicon.ico">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../style.css">
  <link rel="stylesheet" href="Admin.css">
  <?php include("../header.php"); ?>
</head>
<body class="admin">

<!-- Message Popup -->
<?php if ($showPopup): ?>
<div class="popup show" id="messagePopup">
    <div class="popup-content">
        <p><?= htmlspecialchars($popupMessage) ?></p>
        <button class="okbutton" onclick="closeMessagePopup()"><?= __('ok') ?></button>
    </div>
</div>
<?php endif; ?>

<!-- Breadcrumb -->
<div class="admin-breadcrumb-wrapper">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="Dashboard.php"><i class="fas fa-home me-1"></i> <?= __('admin_dashboard') ?></a></li>
                <li class="breadcrumb-item active" aria-current="page"><i class="fas fa-users me-1"></i> <?= __('manage_users') ?></li>
            </ol>
        </nav>
    </div>
</div>

<div class="container py-5">

    <div class="row mb-4 justify-content-center g-4">
    <!-- User Statistics -->
    <div class="row mb-4 g-4">
        <!-- Total Users -->
        <div class="col-md-3">
            <div class="card border-accent shadow-sm rounded-4 text-center">
                <div class="card-body d-flex flex-column justify-content-center" style="min-height: 160px;">
                    <i class="fas fa-users fa-2x mb-3" style="color: var(--primary-dark);"></i>
                    <h4 class="fw-bold" style="color: var(--primary-dark);"><?= $totalUsers ?></h4>
                    <p class="mb-0"><?= __('total_users') ?></p>
                </div>
            </div>
        </div>

        <!-- Active Users -->
        <div class="col-md-3">
            <div class="card border-accent shadow-sm rounded-4 text-center">
                <div class="card-body d-flex flex-column justify-content-center" style="min-height: 160px;">
                    <i class="fas fa-user-check fa-2x mb-3" style="color: var(--primary-color);"></i>
                    <h4 class="fw-bold" style="color: var(--primary-color);"><?= $activeUsers ?></h4>
                    <p class="mb-0"><?= __('active_users') ?></p>
                </div>
            </div>
        </div>

        <!-- Inactive Users -->
        <div class="col-md-3">
            <div class="card border-accent shadow-sm rounded-4 text-center">
                <div class="card-body d-flex flex-column justify-content-center" style="min-height: 160px;">
                    <i class="fas fa-user-slash fa-2x mb-3" style="color: var(--accent-color);"></i>
                    <h4 class="fw-bold" style="color: var(--accent-color);"><?= $inactiveUsers ?></h4>
                    <p class="mb-0"><?= __('inactive_users') ?></p>
                </div>
            </div>
        </div>

        <!-- Gender Distribution Chart -->
        <div class="col-md-3">
            <div class="card border-accent shadow-sm rounded-4">
                <div class="card-body d-flex flex-column justify-content-center" style="min-height: 160px;">
                    <h6 class="fw-bold text-center mb-3" style="color: var(--accent-color);">
                        <i class="fas fa-venus-mars me-1"></i><?= __('gender_distribution') ?>
                    </h6>
                    <div style="height: 100px;">
                        <canvas id="genderChart"></canvas> <!-- هاي مكتبه بنستدعيها بترسملنا رسومات تو و ثري دي كمان -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="row mt-4 g-4">
        <!-- Badge Types Chart -->
        <div class="col-md-6">
            <div class="card border-accent shadow-sm rounded-4">
                <div class="card-body">
                    <h6 class="fw-bold text-center mb-3" style="color: var(--accent-color);">
                        <i class="fas fa-award me-1"></i><?= __('badge_distribution') ?>
                    </h6>
                    <div style="height: 250px;">
                        <canvas id="badgeChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly Signups Chart -->
        <div class="col-md-6">
            <div class="card border-accent shadow-sm rounded-4">
                <div class="card-body">
                    <h6 class="fw-bold text-center mb-3" style="color: var(--primary-dark);">
                        <i class="fas fa-calendar-plus me-1"></i><?= __('monthly_signups') ?>
                    </h6>
                    <div style="height: 250px;">
                        <canvas id="signupChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <br>
    <br>

    <!-- User Table -->
    <div class="admin-card">
        <form method="GET" class="mb-4 row g-2 align-items-center">
            <div class="col-md-4">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" placeholder="<?= __('search_by_email') ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i> <?= __('search') ?></button>
            </div>
            <?php if (!empty($search)): ?>
            <div class="col-auto">
                <a href="AdminManageUsers.php" class="btn btn-secondary"><?= __('reset') ?></a>
            </div>
            <?php endif; ?>
        </form>

        <h5 class="mb-3"><i class="fas fa-users me-2"></i><?= __('all_users') ?></h5>
        <div class="table-responsive">
          <table class="table table-bordered table-hover align-middle">
              <thead class="table-success">
                  <tr>
                      <th style="width: 4%;">#</th>
                      <th style="width: 14%;"><?= __('name') ?></th>
                      <th style="width: 22%;"><?= __('email') ?></th>
                      <th style="width: 12%;"><?= __('phone') ?></th>
                      <th style="width: 16%;"><?= __('address') ?></th>
                      <th style="width: 10%;"><?= __('birth') ?></th>
                      <th style="width: 8%;"><?= __('gender') ?></th>
                      <th style="width: 14%;"><?= __('actions') ?></th>
                  </tr>
              </thead>
              <tbody>
              <?php
              $i = 1;
              if ($allUsers->num_rows > 0):
                  while ($u = $allUsers->fetch_assoc()):
                      $gender = $u['UserGender'] == 1 ? __('male') : __('female');
                      echo "<tr>
                          <td>{$i}</td>
                          <td>" . htmlspecialchars($u['UserName'] ?? '') . "</td>
                          <td><a href='mailto:" . htmlspecialchars($u['UserEmail'] ?? '') . "' class='text-decoration-none'>" . htmlspecialchars($u['UserEmail'] ?? '') . "</a></td>
                          <td>" . htmlspecialchars($u['UserPhone'] ?? '') . "</td>
                          <td>" . htmlspecialchars($u['UserAddress'] ?? '') . "</td>
                          <td>" . htmlspecialchars($u['UserBirth'] ?? '') . "</td>
                          <td>{$gender}</td>
                          <td>
                              <div class='d-flex justify-content-center gap-2'>";
                      if ($u['UserID'] != $AdminID) {
                          // Toggle status button
                          if ($u['UserStatus'] == 1) {
                              echo "<button type='button'
                                      class='btn btn-outline-warning btn-sm rounded-circle'
                                      title='" . __('deactivate') . "'
                                      onclick='changeUserStatus({$u['UserID']}, 0)'>
                                      <i class='fas fa-user-slash'></i>
                                    </button>";
                          } else {
                              echo "<button type='button'
                                      class='btn btn-outline-success btn-sm rounded-circle'
                                      title='" . __('activate') . "'
                                      onclick='changeUserStatus({$u['UserID']}, 1)'>
                                      <i class='fas fa-user-check'></i>
                                    </button>";
                          }

                          // Delete button
                          echo "<button type='button'
                                  class='btn btn-outline-danger btn-sm rounded-circle'
                                  title='" . __('delete') . "'
                                  onclick='deleteUser({$u['UserID']})'>
                                  <i class='fas fa-trash'></i>
                                </button>";
                      } else {
                          echo "<span class='badge bg-info text-dark'>
                                    <i class='fas fa-user me-1'></i>" . __('current_user') . "
                                </span>";
                      }
                      echo "  </div>
                          </td>
                      </tr>";
                      $i++;
                  endwhile;
              else:
                  echo '<tr><td colspan="8" class="text-center">' . __('no_users_available') . '</td></tr>';
              endif;
              ?>
              </tbody>
          </table>
        </div>
    </div>
</div>
</div>

<footer class="footer fixed-footer mt-auto py-3">
    <div class="container text-center">
        <p class="mb-0 text-light">&copy; <?= date('Y') ?> <a href="#" class="text-light">BuyWise</a>. <?= __('all_rights_reserved') ?></p>
    </div>
</footer>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- المكتبه يلي رح ترسملنا الرسومات -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script> 
<script>
const ctx = document.getElementById('genderChart').getContext('2d');
new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: ['<?= __('male') ?>', '<?= __('female') ?>'],
        datasets: [{
            data: [<?= $genderCounts['male'] ?>, <?= $genderCounts['female'] ?>],
            backgroundColor: ['#0dcaf0', '#f06595'],
            borderWidth: 1
        }]
    },
    options: {
        cutout: '60%',
        plugins: {
            legend: {
                display: true,
                position: 'bottom',
                labels: {
                    color: '#666',
                    padding: 10,
                    usePointStyle: true,
                    pointStyle: 'circle'
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const value = context.parsed;
                        const total = <?= $genderCounts['male'] + $genderCounts['female'] ?>;
                        const percent = (value / total * 100).toFixed(1);
                        return `${context.label}: ${value} (${percent}%)`;
                    }
                }
            }
        }
    }
});

// Badge Chart
const badgeCtx = document.getElementById('badgeChart').getContext('2d');
new Chart(badgeCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($badgeLabels) ?>,
        datasets: [{
            label: '<?= __('users') ?>',
            data: <?= json_encode($badgeCounts) ?>,
            backgroundColor: '#ffc107',
            borderRadius: 5
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: { beginAtZero: true }
        },
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.parsed.y + " <?= __('users') ?>";
                    }
                }
            }
        }
    }
});

// Monthly Signup Chart
const signupCtx = document.getElementById('signupChart').getContext('2d');
new Chart(signupCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($monthLabels) ?>,
        datasets: [{
            label: '<?= __('users') ?>',
            data: <?= json_encode($monthCounts) ?>,
            backgroundColor: '#6c757d',
            borderRadius: 5
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: { beginAtZero: true }
        },
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.parsed.y + " <?= __('users') ?>";
                    }
                }
            }
        }
    }
});

</script>

<script>
let pendingStatusUserId = null;
let pendingNewStatus = null;

function changeUserStatus(userID, newStatus) {
  if (!userID) return;

  pendingStatusUserId = userID;
  pendingNewStatus = newStatus;

  const message = newStatus === 1
    ? "<?= __('confirm_activate_user') ?>"
    : "<?= __('confirm_deactivate_user') ?>";

  showPopupMessage(message, true, handleStatusChange);
}

function deleteUser(UserID) {
  pendingDeleteUserId = UserID;
  showPopupMessage("<?= __('confirm_delete_user') ?>", true);
}

function confirmDeleteUser() {
  if (!pendingDeleteUserId) return;

  $.post("", { UserID: pendingDeleteUserId }, function(response) {
    const result = response.trim();
    showPopupMessage(result === "1" ? "<?= __('user_deleted') ?>" : "<?= __('delete_failed') ?>");
    if (result === "1") setTimeout(() => location.reload(), 1500);
  }).fail(() => showPopupMessage("<?= __('delete_failed') ?>"));

  pendingDeleteUserId = null;
}

function showPopupMessage(message, isConfirm = false, confirmCallback = null) {
  const popup = document.getElementById("popup");
  const popupMessage = document.getElementById("popup-message");
  const okButton = document.getElementById("popup-ok");
  const cancelButton = document.getElementById("popup-cancel");

  popupMessage.textContent = message;
  cancelButton.style.display = isConfirm ? "inline-block" : "none";

  okButton.onclick = function () {
    popup.classList.remove("show");
    if (isConfirm && typeof confirmCallback === 'function') {
      confirmCallback();
    }
  };

  cancelButton.onclick = function () {
    popup.classList.remove("show");
    pendingStatusUserId = null;
    pendingNewStatus = null;
  };

  popup.classList.add("show");
}

function handleStatusChange() {
  if (!pendingStatusUserId || pendingNewStatus === null) return;

  $.post("", { UserID: pendingStatusUserId, UserStatus: pendingNewStatus }, function(response) {
    if (response.trim() === "1") {
      location.reload();
    } else {
      showPopupMessage("<?= __('action_failed') ?>");
    }
  }).fail(() => showPopupMessage("<?= __('action_failed') ?>"));

  pendingStatusUserId = null;
  pendingNewStatus = null;
}

function closeMessagePopup() {
    document.getElementById("messagePopup")?.classList.remove("show");
}
</script>

<div class="popup" id="popup">
  <div id="popup-message"></div>
  <div class="mt-3 d-flex justify-content-center gap-2">
    <button id="popup-ok" class="okbutton"><?= __('ok') ?></button>
    <button id="popup-cancel" class="okbutton" style="display: none; background-color: var(--card-bg); color: var(--text-color); border: 1px solid var(--accent-color);">
      <?= __('cancel') ?>
    </button>
  </div>
</div>

</body>
</html>
