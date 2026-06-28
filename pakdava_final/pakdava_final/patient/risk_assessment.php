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

// ذخیره نتیجه
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $risk_level = $_POST['risk_level'] ?? 'medium';
    $factors = json_encode($_POST['factors'] ?? []);
    $recommendations = $_POST['recommendations'] ?? '';
    $stmt = $conn->prepare("INSERT INTO risk_assessment (patient_id, assessment_date, risk_score, risk_level, risk_probability, population_prev, relative_risk, factors, recommendations) VALUES (?, CURDATE(), ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isss", $patient_id, $risk_level, $factors, $recommendations);
    $stmt->execute();
    $stmt->close();
    $success = 'ارزیابی ریسک با موفقیت ذخیره شد.';
}

// دریافت آخرین ارزیابی
$last_risk = [];
$stmt = $conn->prepare("SELECT * FROM risk_assessment WHERE patient_id = ? ORDER BY assessment_date DESC LIMIT 1");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$last_risk = $stmt->get_result()->fetch_assoc() ?? [];
$stmt->close();

$risk_levels = ['low'=>'کم', 'medium'=>'متوسط', 'high'=>'بالا'];
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
<title>ارزیابی ریسک | پک دوا</title>
<link rel="stylesheet" href="../assets/css/main.css">
</head>
<body>
<div class="app-layout">
  <?php include '../assets/sidebar_patient.php'; ?>
  <div class="main-content">
    <div class="topbar"><div><div class="topbar-title">ارزیابی ریسک دیابت</div></div></div>
    <div class="page-body">
      <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>
      <div class="grid-2">
        <div class="card">
          <div class="card-header"><div class="card-title">فرم ارزیابی</div></div>
          <div class="card-body">
            <form method="POST">
              <div class="field"><label>سطح ریسک</label>
                <select name="risk_level" required>
                  <option value="low" <?= ($last_risk['risk_level']??'')=='low'?'selected':'' ?>>کم</option>
                  <option value="medium" <?= ($last_risk['risk_level']??'')=='medium'?'selected':'' ?>>متوسط</option>
                  <option value="high" <?= ($last_risk['risk_level']??'')=='high'?'selected':'' ?>>بالا</option>
                </select>
              </div>
              <div class="field"><label>عوامل (JSON)</label><input type="text" name="factors" value='<?= htmlspecialchars($last_risk['factors'] ?? '{"age":42,"bmi":27.1}') ?>'></div>
              <div class="field"><label>توصیه‌ها</label><textarea name="recommendations"><?= htmlspecialchars($last_risk['recommendations'] ?? '') ?></textarea></div>
              <button type="submit" name="save" class="btn btn-green w-100">ذخیره ارزیابی</button>
            </form>
          </div>
        </div>
        <div class="card">
          <div class="card-header"><div class="card-title">نتیجه آخرین ارزیابی</div></div>
          <div class="card-body">
            <div><strong>سطح:</strong> <?= $risk_levels[$last_risk['risk_level'] ?? ''] ?? 'ثبت نشده' ?></div>
            <div><strong>تاریخ:</strong> <?= htmlspecialchars($last_risk['assessment_date'] ?? '---') ?></div>
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