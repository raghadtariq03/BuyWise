<?php
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['UserID'], $_SESSION['type'])) {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(["status" => "unauthorized", "message" => "Please log in."]);
  } else {
    header("Location: login.php");
  }
  exit;
}

// Check database connection
if (!$con) {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection failed"]);
  } else {
    die("<div class='alert alert-danger text-center'>Database connection failed</div>");
  }
  exit;
}

// Handle AJAX POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json');
  $data = json_decode(file_get_contents("php://input"), true);
  $UserID = ($_SESSION['type'] === 3 || $_SESSION['type'] === 'company') ? intval($_SESSION['CompanyID']) : intval($_SESSION['UserID']);

  // Delete a single notification
  if (!empty($data['delete_notification']) && !empty($data['id'])) {
    $notifID = intval($data['id']);
    $stmt = $con->prepare("DELETE FROM notifications WHERE id = ? AND recipient_id = ?");
    $stmt->bind_param("ii", $notifID, $UserID);
    echo json_encode(["status" => $stmt->execute() ? "success" : "error"]);
    exit;
  }

  // Clear all notifications
  if (!empty($data['clear_all'])) {
    $stmt = $con->prepare("DELETE FROM notifications WHERE recipient_id = ?");
    $stmt->bind_param("i", $UserID);
    echo json_encode(["status" => $stmt->execute() ? "success" : "error"]);
    exit;
  }

  // Mark all notifications as read
  $stmt = $con->prepare("UPDATE notifications SET is_read = 1 WHERE recipient_id = ? AND is_read = 0");
  $stmt->bind_param("i", $UserID);
  if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Notifications marked as read."]);
  } else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to update notifications."]);
  }
  $stmt->close();
  exit;
}

// If not POST, prepare latest 10 notifications for rendering
$UserID = intval($_SESSION['UserID']);
$stmt = $con->prepare("
    SELECT n.id, n.message, n.link, n.is_read, n.created_at,
           CASE WHEN n.sender_id IS NULL OR n.sender_id = 0 THEN 'BuyWise System'
                ELSE COALESCE(u.UserName, c.CompanyName, 'Unknown') END AS UserName,
           CASE WHEN n.sender_id IS NULL OR n.sender_id = 0 THEN ''
                ELSE COALESCE(u.Avatar, c.CompanyLogo, '') END AS Avatar,
           u.UserGender AS Gender,
           n.sender_id
    FROM notifications n
    LEFT JOIN users u ON n.sender_id = u.UserID
    LEFT JOIN companies c ON n.sender_id = c.CompanyID
    WHERE n.recipient_id = ?
    ORDER BY n.created_at DESC
    LIMIT 10
");
$stmt->bind_param("i", $UserID);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($notif = $result->fetch_assoc()) {
  $avatarFile = $notif['Avatar'];
  $gender = strtolower($notif['Gender'] ?? '');

  // Determine default avatar if not available
  if (!empty($avatarFile) && file_exists(__DIR__ . '/uploads/avatars/' . $avatarFile)) {
    $avatar = '/product1/uploads/avatars/' . htmlspecialchars($avatarFile);
  } else {
    $avatar = match ($gender) {
      'female' => '/product1/img/FemDef.png',
      'male' => '/product1/img/MaleDef.png',
      default => '/product1/img/ProDef.png'
    };
  }

  $notifications[] = [
    'id' => $notif['id'],
    'message' => $notif['message'],
    'link' => $notif['link'] ?? '#',
    'is_read' => (bool) $notif['is_read'],
    'created_at' => $notif['created_at'],
    'UserName' => $notif['UserName'],
    'Avatar' => $avatar,
    'sender_id' => $notif['sender_id'],
  ];
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>My Notifications | BuyWise</title>
  <link rel="icon" href="img/favicon.ico">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;600;700&family=Rubik&display=swap"
    rel="stylesheet">
  <link href="style.css" rel="stylesheet">
  <style>
    body {
      background-color: #fdfdfd;
      font-family: "Rubik", sans-serif;
      color: #333;
    }

    .container {
      max-width: 800px;
    }

    /* Header */
    h3 {
      font-weight: 600;
      color: #e29578;
      display: flex;
      align-items: center;
      margin-bottom: 1.5rem;
      padding-bottom: 0.75rem;
      border-bottom: 2px solid #f2d6cd;
    }

    h3 i {
      margin-right: 12px;
      background-color: #f9e2db;
      color: #e29578;
      padding: 10px;
      border-radius: 50%;
    }

    /* Empty message */
    .alert.alert-info {
      background-color: #fff9f6;
      border: 1px solid #fae0d9;
      border-radius: 12px;
      padding: 2rem;
      color: #9a6c5b;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
    }

    /* Notification Card */
    .notification-card {
      background-color: white;
      border-radius: 12px;
      padding: 1.25rem;
      margin-bottom: 1.2rem;
      box-shadow: 0 3px 10px rgba(0, 0, 0, 0.06);
      transition: 0.3s ease;
      position: relative;
      border-left: 5px solid transparent;
    }

    .notification-card.unread {
      background-color: #fff4ec;
      border-left-color: #e29578;
    }

    .notification-avatar {
      width: 52px;
      height: 52px;
      border-radius: 50%;
      object-fit: cover;
      border: 2px solid #f5d2c3;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    }

    /* Card Content */
    .notification-card strong {
      font-weight: 600;
      color: #006d77;
    }

    .notification-card p {
      color: #555;
      margin-bottom: 0.5rem;
      line-height: 1.4;
    }

    .notification-card small.text-muted {
      color: #aaa;
      font-size: 0.75rem;
    }

    /* Button */
    .notification-card .btn-sm.btn-outline-primary {
      border-radius: 8px;
      font-weight: 500;
      padding: 0.3rem 0.8rem;
      color: #e29578;
      border: 1px solid #eac2b5;
      background-color: #fff;
      transition: all 0.2s ease;
    }

    .notification-card .btn-sm.btn-outline-primary:hover {
      background-color: #e29578;
      color: white;
      border-color: #e29578;
    }

    .fade-out {
      opacity: 0;
      transform: translateY(-10px);
      transition: opacity 0.4s ease, transform 0.4s ease;
    }
  </style>
</head>

<body>
  <div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h3><i class="fas fa-bell"></i> My Notifications</h3>
      <button id="clear-all-btn" class="btn btn-sm btn-outline-danger">
        <i class="fas fa-trash-alt me-1"></i> Clear All
      </button>
    </div>

    <?php if (empty($notifications)): ?>
      <div class="alert alert-info text-center">You have no notifications.</div>
    <?php else: ?>
      <?php foreach ($notifications as $notif): ?>
        <?php
        $messageText = strtolower($notif['message'] ?? '');
        $isSystemAlert = strpos($messageText, 'flagged') !== false || strpos($messageText, 'fake') !== false; //بيعطيك رقم أول حرف من الكلمة المطابقة، والعد يبدأ من صفرstrpos() 


        $isSystem = is_null($notif['sender_id']) || $notif['sender_id'] == 0;
        if ($isSystem) {
          $notif['Avatar'] = '/product1/img/favicon.ico';
        }
        $link = $notif['link'];
        $commentID = 0;

        if (str_contains($link, 'ProductID=') && str_contains($link, 'comment=')) {
          if (preg_match('/comment=(\d+)/', $link, $matches)) {
            $commentID = intval($matches[1]);
          } //رجيولار اكسبرشن ليجيب الكومنت يلي يليه ارقام فقط واحفظ الرقم داخل ماتشز ون
            // نحول القيمه اللي لقيناها الى عدد صحيح ونخزنها في كومنت آي دي

          if ($commentID > 0) {
            $stmt = $con->prepare("SELECT ParentCommentID FROM comments WHERE CommentID = ?");
            $stmt->bind_param("i", $commentID);
            $stmt->execute();
            $stmt->bind_result($parentID); //نخزن الناتج في هذا المتغير

            if ($stmt->fetch()) {
              if (!is_null($parentID) && !str_contains($link, 'reply=')) {
                $link .= '&reply=' . $commentID;
              } //اول شي تأكدنا انو ما كان في قبل كلمة ربلي بالرابط مشان ما نرجع نضيفها و هي موجوده
            }

            $stmt->close();
          }
        }

        if (!str_contains($link, 'view=all')) {
          $link .= (str_contains($link, '?') ? '&' : '?') . 'view=all';
        }
        ?>

        <div class="notification-card <?= !$notif['is_read'] ? 'unread' : '' ?>">
          <div class="d-flex align-items-start">
            <img src="<?= htmlspecialchars($notif['Avatar']) ?>" class="notification-avatar me-3"
              alt="<?= htmlspecialchars($notif['UserName']) ?>">
            <div>
              <strong>
                <?= htmlspecialchars($notif['UserName']) ?>
                <?php

                $isPointsAlert = str_contains($messageText, 'points');
                ?>
                <?php if ($isSystemAlert): ?>
                  <i class="fas fa-exclamation-triangle text-danger ms-1" title="System Alert"></i>
                <?php elseif ($isPointsAlert): ?>
                  <span class="ms-1" title="Points Earned">🎉</span>
                <?php endif; ?>

              </strong>
              <p class="mb-1"><?= htmlspecialchars($notif['message']) ?></p>
              <div class="d-flex gap-2 mt-1">
                <?php
                $link = $notif['link'] ?? '#'; // تأكيد وجود لينك و اذا مافي حط هاشتاغ 
            
                $cleanedLink = trim(html_entity_decode($link)); // إزالة الفراغات والرموز المشفرة من الرابط(تنظيفه)
                $finalLink = in_array($cleanedLink, ['#', '', null]) ? 'policy.php' : htmlspecialchars($link); // اذا كانت النتيجه هاشتاغ او نل او رابط غير صالح نودي المستخدم عصفحة البوليسيز كَ فُل باك
                ?> 
                <a href="<?= $finalLink ?>" class="btn btn-sm btn-outline-primary">
                  <i class="fas fa-eye me-1"></i> <?= __('view') ?>
                </a>

                <button class="btn btn-sm btn-outline-danger delete-notif-btn" data-id="<?= $notif['id'] ?>">
                  <i class="fas fa-trash-alt"></i> <?= __('delete') ?>
                </button>
              </div>
              <div><small class="text-muted"><?= date("M j, Y h:i A", strtotime($notif['created_at'])) ?></small></div>   <!-- الشهر اليوم فاصله السنه الساعه الدقايق ثم صباحًا او مساءًا -->
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Auto-mark as read using JS fetch -->
  <script>
    fetch('notifications.php', {
      method: 'POST'
    });

    document.querySelectorAll('.delete-notif-btn').forEach(btn => {
      btn.addEventListener('click', function () {
        const notifID = this.dataset.id; //يحصل على قيمةآي دي النوتيفيكيشن الحالي من الزر المضغوط
        const card = this.closest('.notification-card'); //يبحث عن اقرب عنصر اب يحتوي على هذا الكلاس

        fetch('notifications.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            delete_notification: true,
            id: notifID
          })//ببعت بيانات للسيرفر بصيغه معينه (جيسون) حتى يفهمها - يعني حولها من جافا سكربت لجيسون ليبعتها
        })// بالسيرفر هناك البيانات بتتحول لبي اتش بي ثم بس يرجع الرد للمتصفح برجعه كجيسون كمان مره
          .then(res => res.json()) //بحول الرد القادم من السيرفر من جيسون الى جافا سكربت
          .then(data => {
            if (data.status === 'success') {
              // حذف ناعم بدون أليرت 
              card.classList.add('fade-out');
              setTimeout(() => card.remove(), 400);
            }
          });
      });
    });

    const clearAllBtn = document.getElementById('clear-all-btn');
    if (clearAllBtn) {
      clearAllBtn.addEventListener('click', () => {
        fetch('notifications.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            clear_all: true
          })
        })
          .then(res => res.json())
          .then(data => {
            if (data.status === 'success') {
              document.querySelectorAll('.notification-card').forEach(card => {
                card.classList.add('fade-out');
                setTimeout(() => card.remove(), 400); //بعد 400ملي ثانيه احذف العنصر نهائيًا
              });
            }
          });
      });
    }
  </script>


</body>

</html>