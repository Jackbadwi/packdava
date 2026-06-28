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

// دریافت برنامه امروز
$today_plans = [];
$stmt = $conn->prepare("SELECT activities, medication, diet, notes FROM daily_plan WHERE patient_id = ? AND plan_date = CURDATE()");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $today_plans[] = $row;
}
$stmt->close();

// اگر برنامه‌ای نبود، پیش‌فرض
if (empty($today_plans)) {
    $today_plans = [
        ['activities'=>'۱۰ دقیقه پیاده‌روی', 'medication'=>'متفورمین ۵۰۰mg', 'diet'=>'سالاد و مرغ', 'notes'=>'']
    ];
}
$day = 7; // می‌توان از progress محاسبه کرد
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
<title>برنامه روزانه | پک دوا</title>
<link rel="stylesheet" href="../assets/css/main.css">
</head>
<body>
<div class="app-layout">
  <?php include '../assets/sidebar_patient.php'; ?>
  <div class="main-content">
    <div class="topbar">
      <div><div class="topbar-title">برنامه روزانه مداخله</div><div class="topbar-subtitle">روز <?= $day ?> از برنامه</div></div>
    </div>
    <div class="page-body">
      <div class="grid-2">
        <div>
          <?php foreach ($today_plans as $i => $plan): ?>
          <div class="card mb-20">
            <div class="card-header">
              <div style="display:flex;align-items:center;gap:12px">
                <div style="width:44px;height:44px;background:var(--green-light);border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0">📋</div>
                <div><div class="card-title"><?= htmlspecialchars($plan['activities']) ?></div><div class="card-subtitle"><?= htmlspecialchars($plan['diet']) ?></div></div>
              </div>
            </div>
            <div class="card-body">
              <div><strong>دارو:</strong> <?= htmlspecialchars($plan['medication']) ?></div>
              <div><strong>یادداشت:</strong> <?= htmlspecialchars($plan['notes']) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="card">
          <div class="card-header"><div class="card-title">پیشرفت هفتگی</div></div>
          <div class="card-body">
            <div style="display:flex;gap:6px;margin-bottom:16px">
              <?php foreach(['ش','ی','د','س','چ','پ','ج'] as $d): ?>
              <div style="flex:1;height:40px;background:var(--green);border-radius:var(--radius-sm);display:flex;flex-direction:column;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:white"><span><?= $d ?></span><span>✓</span></div>
              <?php endforeach; ?>
            </div>
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

<script>
// ── ذخیره تکمیل برنامه روزانه در IDB ───────────────────────────────────
async function completePlanOffline(planData) {
  const entry = {
    plan_date:   new Date().toISOString().split('T')[0],
    soc_stage:   planData.stage || '',
    activities:  planData.activities || '',
    medication:  planData.medication || '',
    diet:        planData.diet || '',
    notes:       planData.notes || '',
    completed:   1,
  };
  if (typeof PakDavaSync !== 'undefined') {
    await PakDavaSync.enqueue('daily_queue', { data: entry });
    showToast('✅ برنامه ثبت شد' + (navigator.onLine ? '' : ' — ارسال هنگام اتصال'));
  }
}
window.completePlanOffline = completePlanOffline;

// intercept دکمه تکمیل
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-complete-plan]').forEach(btn => {
    btn.addEventListener('click', async e => {
      e.preventDefault();
      await completePlanOffline({
        stage:      btn.dataset.stage      || '',
        activities: btn.dataset.activities || '',
        medication: btn.dataset.medication || '',
        diet:       btn.dataset.diet       || '',
        notes:      btn.dataset.notes      || '',
      });
    });
  });
});
</script>
</body>
</html>