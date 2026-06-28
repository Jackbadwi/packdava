<?php
session_start();
require_once __DIR__ . '/../conn.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
// دریافت اعلانات
$notifications = [];
$stmt = $conn->prepare("SELECT id, message, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $notifications[] = $row;
}
$stmt->close();

// علامت‌گذاری همه به عنوان خوانده‌شده
if (isset($_POST['mark_all_read'])) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    header('Location: notifications.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">  <link rel="manifest" href="../manifest.json">
  <meta name="theme-color" content="#1A7A4A">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="پک دوا">
  <link rel="apple-touch-icon" href="../assets/icons/icon-192.svg">
  <meta name="viewport" content="width=device-width,initial-scale=1">
<title>اعلانات | پک دوا</title>
<link rel="stylesheet" href="../assets/css/main.css">
</head>
<body>
<div class="app-layout">
  <?php include '../assets/sidebar_patient.php'; ?>
  <div class="main-content">
    <div class="topbar"><div><div class="topbar-title">مرکز اعلانات</div></div></div>
    <div class="page-body">
      <div class="card">
        <div class="card-header">
          <div class="card-title">لیست اعلانات</div>
          <form method="POST" style="display:inline">
            <button type="submit" name="mark_all_read" class="btn btn-outline btn-sm">همه خوانده شد</button>
          </form>
        </div>
        <div class="card-body" style="padding:0">
          <?php if (empty($notifications)): ?>
            <div style="padding:20px;text-align:center;color:var(--gray-500)">هیچ اعلانی وجود ندارد.</div>
          <?php else: ?>
            <?php foreach ($notifications as $n): ?>
              <div style="padding:14px 20px;border-bottom:1px solid var(--gray-100);<?= $n['is_read'] ? '' : 'background:rgba(41,128,185,0.04)' ?>">
                <div style="display:flex;justify-content:space-between">
                  <div style="font-weight:<?= $n['is_read'] ? '400' : '700' ?>"><?= htmlspecialchars($n['message']) ?></div>
                  <div style="font-size:11px;color:var(--gray-400)"><?= htmlspecialchars($n['created_at']) ?></div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="../assets/js/db.js"></script>
<script src="../assets/js/sync.js"></script>
<script src="../assets/js/notify.js"></script>
<script src="../assets/js/app.js"></script>
</body>
</html>