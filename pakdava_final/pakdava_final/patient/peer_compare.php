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

// دریافت مقایسه‌های همتا
$peers = [];
$stmt = $conn->prepare("SELECT peer_id, comparison_data, created_at FROM peer_compare WHERE patient_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $peers[] = $row;
}
$stmt->close();

// اگر داده‌ای نبود، mock
if (empty($peers)) {
    $peers = [
        ['peer_id'=>0, 'comparison_data'=>'{"name":"کاربر ۱۴۷","progress":"۲۳٪"}', 'created_at'=>'2025-03-01'],
        ['peer_id'=>0, 'comparison_data'=>'{"name":"کاربر ۲۳۸","progress":"۱۸٪"}', 'created_at'=>'2025-03-02'],
    ];
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
<title>مقایسه همتایان | پک دوا</title>
<link rel="stylesheet" href="../assets/css/main.css">
</head>
<body>
<div class="app-layout">
  <?php include '../assets/sidebar_patient.php'; ?>
  <div class="main-content">
    <div class="topbar"><div><div class="topbar-title">مقایسه ناشناس با هم‌ورودی‌ها</div></div></div>
    <div class="page-body">
      <div class="card">
        <div class="card-header"><div class="card-title">لیست مقایسه‌ها</div></div>
        <div class="card-body">
          <?php foreach ($peers as $p): ?>
            <div style="padding:12px;border-bottom:1px solid var(--gray-100)">
              <div><strong><?= htmlspecialchars(json_decode($p['comparison_data'], true)['name'] ?? 'کاربر ناشناس') ?></strong></div>
              <div><?= htmlspecialchars(json_decode($p['comparison_data'], true)['progress'] ?? '') ?></div>
              <div style="font-size:11px;color:var(--gray-400)"><?= htmlspecialchars($p['created_at']) ?></div>
            </div>
          <?php endforeach; ?>
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