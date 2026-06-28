<?php
session_start();
require_once __DIR__ . '/../conn.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// تابع کمکی برای کوئری‌های امن با مدیریت خطا
function safeQuery($conn, $sql, $params = [], $default = null) {
    try {
        $stmt = $conn->prepare($sql);
        if ($params) {
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        return $data ?: $default;
    } catch (Exception $e) {
        return $default;
    }
}

// دریافت آخرین مرحله SOC ثبت‌شده (در صورت وجود)
$last_stage = 'C'; // پیش‌فرض: تأمل
$soc_record = safeQuery($conn, "SELECT stage FROM soc_assessment WHERE patient_id = ? ORDER BY assessment_date DESC LIMIT 1", [$user_id]);
if ($soc_record && isset($soc_record['stage'])) {
    $last_stage = $soc_record['stage'];
}

$stages = [
    'PC' => ['label'=>'پیش از تأمل', 'desc'=>'هنوز به تغییر فکر نمی‌کنم'],
    'C'  => ['label'=>'تأمل', 'desc'=>'در حال فکر کردن هستم'],
    'PR' => ['label'=>'آماده‌سازی', 'desc'=>'قصد دارم به زودی شروع کنم'],
    'A'  => ['label'=>'عمل', 'desc'=>'دارم تغییر می‌دهم (کمتر از ۶ ماه)'],
    'M'  => ['label'=>'نگهداری', 'desc'=>'تغییر را حفظ می‌کنم (بیشتر از ۶ ماه)'],
];

$success = '';
$error = '';

// پردازش ثبت مرحله جدید
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['stage'])) {
    $stage = $_POST['stage'] ?? '';
    $duration = $_POST['duration'] ?? '';
    $barrier = $_POST['barrier'] ?? '';
    $note = $_POST['note'] ?? '';

    if (!isset($stages[$stage])) {
        $error = 'مرحله نامعتبر است.';
    } else {
        try {
            // بررسی وجود ستون‌های مورد نیاز در جدول - اگر جدول وجود نداشته باشد، ایجاد نمی‌کنیم، بلکه فقط خطا را مدیریت می‌کنیم
            $stmt = $conn->prepare("INSERT INTO soc_assessment (patient_id, assessment_date, stage, social_support, living_situation, occupation, education, comments) VALUES (?, CURDATE(), ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssss", $user_id, $stage, $barrier, $duration, '', '', $note);
            $stmt->execute();
            $stmt->close();
            $success = 'مرحله SOC با موفقیت ثبت شد.';
            // به‌روزرسانی متغیر برای نمایش
            $last_stage = $stage;
        } catch (Exception $e) {
            $error = 'خطا در ثبت مرحله: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">  <link rel="manifest" href="../manifest.json">
  <meta name="theme-color" content="#1A7A4A">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="پک دوا">
  <meta name="viewport" content="width=device-width,initial-scale=1">
<title>ارزیابی SOC | پک دوا</title>
<link rel="stylesheet" href="../assets/css/main.css">
</head>
<body>
<div class="app-layout">
  <?php include '../assets/sidebar_patient.php'; ?>
  <div class="main-content">
    <div class="topbar">
      <div><div class="topbar-title">ارزیابی مرحله تغییر رفتاری</div>
      <div class="topbar-subtitle">مدل Transtheoretical Model — Prochaska & DiClemente</div></div>
    </div>
    <div class="page-body">
      <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <div class="grid-2">
        <div class="card">
          <div class="card-header"><div class="card-title">پرسشنامه SOC</div><div class="card-subtitle">صادقانه پاسخ دهید — هیچ پاسخ اشتباهی وجود ندارد</div></div>
          <div class="card-body">
            <form method="POST">
              <div style="font-size:18px;font-weight:700;color:var(--gray-900);margin-bottom:8px;text-align:center">آیا در حال حاضر تصمیم به تغییر سبک زندگی خود دارید؟</div>
              <div style="font-size:13px;color:var(--gray-500);text-align:center;margin-bottom:28px">(تغذیه سالم‌تر، فعالیت بدنی بیشتر، کاهش وزن)</div>

              <?php foreach ($stages as $key => $s): ?>
                <label style="display:flex;align-items:center;gap:16px;padding:16px;border:2px solid <?= $last_stage===$key ? $s['color'] ?? '#E67E22' : 'var(--gray-200)' ?>;border-radius:var(--radius-sm);margin-bottom:10px;cursor:pointer;background:<?= $last_stage===$key ? 'rgba(230,126,34,0.06)' : 'var(--white)' ?>;transition:all 0.2s" onclick="this.querySelector('input').click()">
                  <input type="radio" name="stage" value="<?= $key ?>" <?= $last_stage===$key?'checked':'' ?> style="display:none">
                  <div style="width:20px;height:20px;border-radius:50%;border:2.5px solid <?= $last_stage===$key ? '#E67E22' : 'var(--gray-300)' ?>;background:<?= $last_stage===$key ? '#E67E22' : 'transparent' ?>;flex-shrink:0;display:flex;align-items:center;justify-content:center">
                    <?= $last_stage===$key ? '<span style="color:white;font-size:11px">✓</span>' : '' ?>
                  </div>
                  <div style="flex:1">
                    <div style="font-size:14px;font-weight:700;color:<?= $last_stage===$key ? '#E67E22' : 'var(--gray-800)' ?>"><?= $s['label'] ?></div>
                    <div style="font-size:12px;color:var(--gray-500);margin-top:2px"><?= $s['desc'] ?></div>
                  </div>
                </label>
              <?php endforeach; ?>

              <div style="margin-top:20px">
                <div class="field"><label>چه مدت است در این مرحله هستید؟</label>
                  <select name="duration">
                    <option>کمتر از یک هفته</option>
                    <option selected>یک تا چهار هفته</option>
                    <option>یک تا شش ماه</option>
                    <option>بیشتر از شش ماه</option>
                  </select>
                </div>
                <div class="field"><label>مهم‌ترین مانع تغییر شما چیست؟</label>
                  <select name="barrier">
                    <option>کمبود وقت</option>
                    <option selected>عدم انگیزه کافی</option>
                    <option>مشکلات مالی</option>
                    <option>بیماری یا درد</option>
                    <option>حمایت اجتماعی ضعیف</option>
                  </select>
                </div>
                <div class="field"><label>یادداشت شخصی (اختیاری)</label>
                  <textarea name="note" placeholder="هر چیزی که می‌خواهید بنویسید..."></textarea>
                </div>
              </div>
              <button type="submit" class="btn btn-green w-100">ثبت مرحله تغییر</button>
            </form>
          </div>
        </div>

        <div>
          <?php
          $current = $stages[$last_stage] ?? ['label'=>'تأمل', 'desc'=>'در حال فکر کردن هستم'];
          $color = '#E67E22'; // رنگ پیش‌فرض
          // اگر مرحله انتخابی دارای رنگ خاصی است، می‌توانیم از آرایه استفاده کنیم
          $stageColors = ['PC'=>'#E74C3C', 'C'=>'#E67E22', 'PR'=>'#F1C40F', 'A'=>'#27AE60', 'M'=>'#2980B9'];
          $color = $stageColors[$last_stage] ?? '#E67E22';
          ?>
          <div class="soc-stage-card mb-20" style="background:linear-gradient(135deg,<?= $color ?>,<?= $color ?>cc)">
            <div style="font-size:12px;opacity:0.8;font-weight:600">مرحله فعلی شما</div>
            <div class="soc-stage-name"><?= $current['label'] ?></div>
            <div class="soc-stage-desc"><?= $current['desc'] ?></div>
            <div class="soc-interventions">
              <div class="soc-int-item">✦ آگاهی از ریسک شخصی</div>
              <div class="soc-int-item">✦ اطلاعات عوارض دیابت</div>
            </div>
          </div>

          <div class="card mb-20">
            <div class="card-header"><div class="card-title">مدل مراحل تغییر</div></div>
            <div class="card-body">
              <?php foreach ($stages as $k => $st): ?>
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
                  <div style="width:32px;height:32px;border-radius:50%;background:<?= $k===$last_stage ? $color : 'var(--gray-200)' ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:<?= $k===$last_stage ? 'white' : 'var(--gray-500)' ?>;font-size:12px;font-weight:700">
                    <?= array_search($k, array_keys($stages)) + 1 ?>
                  </div>
                  <div style="flex:1">
                    <div style="font-size:13px;font-weight:<?= $k===$last_stage ? '700' : '500' ?>;color:<?= $k===$last_stage ? $color : 'var(--gray-700)' ?>"><?= $st['label'] ?></div>
                    <div style="font-size:11px;color:var(--gray-400)"><?= $st['desc'] ?></div>
                  </div>
                  <?php if ($k === $last_stage): ?><span style="font-size:16px">←</span><?php endif; ?>
                </div>
                <?php if ($k !== 'M'): ?>
                  <div style="width:2px;height:16px;background:var(--gray-200);margin-right:15px;margin-bottom:0"></div>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="card">
            <div class="card-header"><div class="card-title">تاریخچه ارزیابی SOC</div></div>
            <div class="card-body">
              <div class="timeline">
                <?php
                // نمایش تاریخچه واقعی در صورت وجود
                try {
                    $stmt = $conn->prepare("SELECT assessment_date, stage FROM soc_assessment WHERE patient_id = ? ORDER BY assessment_date DESC LIMIT 5");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                    if (count($history) > 0) {
                        foreach ($history as $h) {
                            $label = $stages[$h['stage']]['label'] ?? $h['stage'];
                            echo '<div class="timeline-item">';
                            echo '<div class="timeline-dot" style="background:#E67E22"></div>';
                            echo '<div class="timeline-date">' . htmlspecialchars($h['assessment_date']) . '</div>';
                            echo '<div class="timeline-content"><strong style="color:#E67E22">' . htmlspecialchars($label) . '</strong></div>';
                            echo '</div>';
                        }
                    } else {
                        echo '<div style="color:var(--gray-500);font-size:13px;padding:8px 0">هیچ ارزیابی ثبت نشده است.</div>';
                    }
                } catch (Exception $e) {
                    echo '<div style="color:var(--gray-500);font-size:13px;padding:8px 0">امکان نمایش تاریخچه وجود ندارد.</div>';
                }
                ?>
              </div>
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
document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('form[method="POST"]');
  if (!form) return;
  form.addEventListener('submit', async e => {
    if (navigator.onLine) return;
    e.preventDefault();
    const fd = new FormData(form);
    const data = {
      stage:    fd.get('stage')    || 'contemplation',
      duration: fd.get('duration') || '',
      barrier:  fd.get('barrier')  || '',
      comments: fd.get('note')     || '',
      assessment_date: new Date().toISOString().split('T')[0],
    };
    if (typeof PakDavaSync !== 'undefined') await PakDavaSync.enqueue('soc_queue', { data });
    showToast('📥 مرحله SOC ذخیره شد — ارسال هنگام اتصال');
  });
});
</script>
</body>
</html>