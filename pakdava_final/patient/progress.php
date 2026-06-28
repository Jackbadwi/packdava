<?php
session_start();
require_once __DIR__ . '/../conn.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$patient_id = 0;
$stmt = $conn->prepare("SELECT id FROM patients WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) $patient_id = $row['id'];
$stmt->close();

// دریافت رکوردهای پیشرفت
$progress = [];
$stmt = $conn->prepare("SELECT record_date, progress_notes, status, next_steps FROM progress WHERE patient_id = ? ORDER BY record_date DESC LIMIT 10");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $progress[] = $row;
}
$stmt->close();

// ذخیره رکورد جدید
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $notes = $_POST['notes'] ?? '';
    $status = $_POST['status'] ?? '';
    $next = $_POST['next'] ?? '';
    $stmt = $conn->prepare("INSERT INTO progress (patient_id, record_date, progress_notes, status, next_steps) VALUES (?, CURDATE(), ?, ?, ?)");
    $stmt->bind_param("isss", $patient_id, $notes, $status, $next);
    $stmt->execute();
    $stmt->close();
    header('Location: progress.php');
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
<title>پیشرفت من | پک دوا</title>
<link rel="stylesheet" href="../assets/css/main.css">
</head>
<body>
<div class="app-layout">
  <?php include '../assets/sidebar_patient.php'; ?>
  <div class="main-content">
    <div class="topbar"><div><div class="topbar-title">پیگیری پیشرفت</div></div></div>
    <div class="page-body">
      <div class="grid-2">
        <div class="card">
          <div class="card-header"><div class="card-title">ثبت پیشرفت جدید</div></div>
          <div class="card-body">
            <form method="POST">
              <div class="field"><label>یادداشت پیشرفت</label><textarea name="notes"></textarea></div>
              <div class="field"><label>وضعیت</label><input type="text" name="status" placeholder="مثلاً در حال بهبود"></div>
              <div class="field"><label>گام‌های بعدی</label><input type="text" name="next"></div>
              <button type="submit" name="save" class="btn btn-green w-100">ذخیره</button>
            </form>
          </div>
        </div>
        <div class="card">
          <div class="card-header"><div class="card-title">تاریخچه پیشرفت</div></div>
          <div class="card-body" style="padding:0">
            <?php foreach ($progress as $p): ?>
              <div style="padding:12px 20px;border-bottom:1px solid var(--gray-100)">
                <div><strong><?= htmlspecialchars($p['record_date']) ?></strong></div>
                <div><?= htmlspecialchars($p['progress_notes']) ?></div>
                <div style="font-size:12px;color:var(--gray-500)">وضعیت: <?= htmlspecialchars($p['status']) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
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