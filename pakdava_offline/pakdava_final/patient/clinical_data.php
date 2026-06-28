<?php
/**
 * clinical_data.php — ورود و پیگیری داده‌های بالینی کامل
 * شامل: FBS, HbA1c, فشارخون, کلسترول (LDL/HDL/TG), BMI, کراتینین
 * منبع داده مرجع: NCD-RisC Excel files (Diabetes, BMI, BP, Cholesterol)
 */
session_start();
require_once __DIR__ . '/../conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: ../index.php'); exit;
}

$user_id    = (int)$_SESSION['user_id'];
$patient_id = $user_id;

// ── توابع کمکی ────────────────────────────────────────────────────────────
function getBMIStatus($bmi) {
    if ($bmi < 18.5) return ['کم‌وزن','#2980B9'];
    if ($bmi < 25)   return ['نرمال','#27AE60'];
    if ($bmi < 30)   return ['اضافه‌وزن','#E67E22'];
    return ['چاق','#E74C3C'];
}
function getFBSStatus($fbs) {
    if (!$fbs)       return ['---',''];
    if ($fbs < 100)  return ['طبیعی','#27AE60'];
    if ($fbs < 126)  return ['پیش‌دیابت','#E67E22'];
    return ['دیابت','#E74C3C'];
}
function getHbA1cStatus($v) {
    if (!$v)        return ['---',''];
    if ($v < 5.7)   return ['طبیعی','#27AE60'];
    if ($v < 6.5)   return ['پیش‌دیابت','#E67E22'];
    return ['دیابت','#E74C3C'];
}
function getBPStatus($sys, $dia) {
    if (!$sys)      return ['---',''];
    if ($sys < 120 && $dia < 80)  return ['نرمال','#27AE60'];
    if ($sys < 130)               return ['پیش‌فشار','#F1C40F'];
    if ($sys < 140)               return ['مرحله ۱','#E67E22'];
    return ['مرحله ۲','#E74C3C'];
}
function getCholStatus($tc) {
    if (!$tc)       return ['---',''];
    if ($tc < 200)  return ['مطلوب','#27AE60'];
    if ($tc < 240)  return ['مرزی','#E67E22'];
    return ['بالا','#E74C3C'];
}

// ── ذخیره داده ────────────────────────────────────────────────────────────
$success = $error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $fbs       = $_POST['fbs']       !== '' ? floatval($_POST['fbs'])       : null;
    $ppg       = $_POST['ppg']       !== '' ? floatval($_POST['ppg'])       : null;
    $hba1c     = $_POST['hba1c']     !== '' ? floatval($_POST['hba1c'])     : null;
    $bp_sys    = $_POST['bp_sys']    !== '' ? intval($_POST['bp_sys'])      : null;
    $bp_dia    = $_POST['bp_dia']    !== '' ? intval($_POST['bp_dia'])      : null;
    $heart_rate= $_POST['heart_rate']!== '' ? intval($_POST['heart_rate']) : null;
    $chol      = $_POST['cholesterol']!== ''? floatval($_POST['cholesterol']):null;
    $ldl       = $_POST['ldl']       !== '' ? floatval($_POST['ldl'])       : null;
    $hdl       = $_POST['hdl']       !== '' ? floatval($_POST['hdl'])       : null;
    $tg        = $_POST['triglycerides']!==''?floatval($_POST['triglycerides']):null;
    $weight    = $_POST['weight']    !== '' ? floatval($_POST['weight'])    : null;
    $height    = $_POST['height']    !== '' ? floatval($_POST['height'])    : null;
    $waist     = $_POST['waist']     !== '' ? floatval($_POST['waist'])     : null;
    $creatinine= $_POST['creatinine']!== '' ? floatval($_POST['creatinine']): null;
    $medications=$_POST['medications']?? '';
    $notes     = $_POST['notes']     ?? '';
    $symptoms  = $_POST['symptoms']  ?? '';

    $stmt = $conn->prepare("
        INSERT INTO clinical_data
          (patient_id, record_date, fbs, ppg, hba1c, bp_systolic, bp_diastolic,
           heart_rate, cholesterol_total, ldl, hdl, triglycerides,
           weight, height, waist_circumference, creatinine,
           symptoms, medications, notes, status)
        VALUES (?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    if ($stmt) {
        $stmt->bind_param('idddiiiddddddddsss',
            $patient_id, $fbs, $ppg, $hba1c, $bp_sys, $bp_dia, $heart_rate,
            $chol, $ldl, $hdl, $tg, $weight, $height, $waist, $creatinine,
            $symptoms, $medications, $notes
        );
        if ($stmt->execute()) {
            $success = 'داده‌ها با موفقیت ثبت شد. منتظر تأیید پزشک باشید.';

            // ذخیره BMI جداگانه
            if ($weight && $height) {
                $bmi_v = round($weight / (($height/100)**2), 1);
                [$bmi_s] = getBMIStatus($bmi_v);
                $s2 = $conn->prepare("INSERT INTO bmi_records (user_id,record_date,height,weight,bmi_value,bmi_status) VALUES (?,CURDATE(),?,?,?,?) ON DUPLICATE KEY UPDATE height=VALUES(height),weight=VALUES(weight),bmi_value=VALUES(bmi_value),bmi_status=VALUES(bmi_status)");
                if ($s2) { $s2->bind_param('iddds',$patient_id,$height,$weight,$bmi_v,$bmi_s); $s2->execute(); $s2->close(); }
                // به‌روز کردن users
                $conn->query("UPDATE users SET height=$height, weight=$weight, bmi_value=$bmi_v, bmi_status='$bmi_s' WHERE id=$patient_id");
            }

            // اعلان به پزشک
            $pname = $_SESSION['name'] ?? 'بیمار';
            $conn->query("INSERT INTO notifications (user_id,type,title,message,url)
                SELECT u.id,'clinical','داده بالینی جدید','بیمار $pname داده بالینی ثبت کرده — نیاز به تأیید','../doctor/approve_data.php'
                FROM users u WHERE u.role='doctor' LIMIT 1");

            // هشدار اگر FBS بالا
            if ($fbs && $fbs >= 126) {
                $conn->query("INSERT INTO alerts (patient_id,type,message) VALUES ($patient_id,'clinical_threshold','FBS = $fbs mg/dL — بالاتر از آستانه دیابت (126)')");
            }
        } else {
            $error = 'خطا: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        $error = 'خطا در آماده‌سازی: ' . $conn->error;
    }
}

// ── آخرین رکورد ────────────────────────────────────────────────────────────
$last = [];
$s = $conn->prepare("SELECT * FROM clinical_data WHERE patient_id=? ORDER BY record_date DESC LIMIT 1");
$s->bind_param('i',$patient_id); $s->execute();
$last = $s->get_result()->fetch_assoc() ?? []; $s->close();

// ── تاریخچه برای نمودار (۶ رکورد) ────────────────────────────────────────
$s = $conn->prepare("SELECT record_date,fbs,hba1c,bp_systolic,bp_diastolic,cholesterol_total,weight,height FROM clinical_data WHERE patient_id=? ORDER BY record_date ASC LIMIT 6");
$s->bind_param('i',$patient_id); $s->execute();
$history = $s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
if (empty($history)) {
    $history = [
        ['record_date'=>'2025-01-01','fbs'=>128,'hba1c'=>6.2,'bp_systolic'=>135,'bp_diastolic'=>85,'cholesterol_total'=>198,'weight'=>83,'height'=>174],
        ['record_date'=>'2025-04-01','fbs'=>122,'hba1c'=>6.0,'bp_systolic'=>130,'bp_diastolic'=>82,'cholesterol_total'=>194,'weight'=>82,'height'=>174],
        ['record_date'=>'2025-07-01','fbs'=>118,'hba1c'=>5.9,'bp_systolic'=>128,'bp_diastolic'=>82,'cholesterol_total'=>191,'weight'=>81,'height'=>174],
    ];
}

// ── آخرین BMI ─────────────────────────────────────────────────────────────
$latest_bmi = null;
$s = $conn->prepare("SELECT bmi_value,bmi_status FROM bmi_records WHERE user_id=? ORDER BY record_date DESC LIMIT 1");
$s->bind_param('i',$patient_id); $s->execute();
$latest_bmi = $s->get_result()->fetch_assoc(); $s->close();

// ── داده‌های مرجع NCD-RisC ───────────────────────────────────────────────
$ncd = [];
$s = $conn->prepare("SELECT indicator,year,sex,value FROM ncd_risc_iran WHERE sex IN ('male','both') AND year >= 2010 ORDER BY indicator,year");
if ($s) { $s->execute(); $ncd_rows = $s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
    foreach ($ncd_rows as $r) $ncd[$r['indicator']][$r['year']] = $r['value']; }
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>داده‌های بالینی | پک دوا</title>
<link rel="manifest" href="../manifest.json">
<meta name="theme-color" content="#1A7A4A">
<link rel="stylesheet" href="../assets/css/main.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
.form-section{border:1px solid var(--gray-200);border-radius:10px;padding:16px;margin-bottom:16px;background:var(--gray-50)}
.form-section-title{font-weight:700;color:var(--green);margin-bottom:12px;font-size:13px;display:flex;align-items:center;gap:8px}
.form-row{display:flex;gap:12px;flex-wrap:wrap}
.form-row .field{flex:1;min-width:130px}
.field label{display:block;font-size:11px;font-weight:700;color:var(--gray-600);margin-bottom:4px;text-transform:uppercase}
.field input,.field select,.field textarea{width:100%;padding:9px 12px;border:1.5px solid var(--gray-300);border-radius:6px;font-size:14px;font-family:inherit}
.field input:focus{outline:none;border-color:var(--green)}
.ref-val{font-size:10px;color:var(--gray-400);margin-top:2px}
.status-pill{padding:2px 10px;border-radius:99px;font-size:11px;font-weight:700}
.ncd-ref-box{background:linear-gradient(135deg,var(--green-dark),var(--green));color:white;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:12px}
.ncd-ref-box strong{font-size:14px}
</style>
</head>
<body>
<div class="app-layout">
  <?php include '../assets/sidebar_patient.php'; ?>
  <div class="main-content">
    <div class="topbar">
      <div><div class="topbar-title">داده‌های بالینی</div><div class="topbar-subtitle">شامل تمام شاخص‌های NCD-RisC | نیاز به تأیید پزشک</div></div>
    </div>
    <div class="page-body">
      <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
      <?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

      <!-- مرجع NCD-RisC -->
      <div class="ncd-ref-box">
        🌍 <strong>مقادیر مرجع جمعیت ایران (NCD-RisC 2014–2017):</strong>
        شیوع دیابت مردان: ۱۱.۴٪ | زنان: ۱۲.۸٪ |
        میانگین BMI مردان: ۲۵.۷ | زنان: ۳۰.۱ |
        میانگین SBP مردان: ۱۲۳.۸ mmHg |
        میانگین کلسترول مردان: ۱۸۹.۶ mg/dL
        <span style="opacity:0.7;font-size:10px;margin-right:8px">— Lancet 2016, 2017, 2019 | EJPC 2020</span>
      </div>

      <div class="grid-2">
        <!-- فرم ورود -->
        <div class="card">
          <div class="card-header"><div class="card-title">ورود داده جدید</div><div class="card-subtitle">پس از ثبت، پزشک تأیید می‌کند</div></div>
          <div class="card-body">
            <form method="POST">
              <!-- دیابت -->
              <div class="form-section">
                <div class="form-section-title">🩸 شاخص‌های دیابت</div>
                <div class="form-row">
                  <div class="field">
                    <label>قند ناشتا FBS (mg/dL)</label>
                    <input type="number" step="0.1" name="fbs" value="<?= htmlspecialchars($last['fbs']??'') ?>" placeholder="مثال: ۱۱۸">
                    <div class="ref-val">طبیعی: &lt;۱۰۰ | پیش‌دیابت: ۱۰۰–۱۲۵ | دیابت: ≥۱۲۶</div>
                  </div>
                  <div class="field">
                    <label>قند ۲ ساعته PPG (mg/dL)</label>
                    <input type="number" step="0.1" name="ppg" value="<?= htmlspecialchars($last['ppg']??'') ?>" placeholder="مثال: ۱۴۵">
                    <div class="ref-val">طبیعی: &lt;۱۴۰ | دیابت: ≥۲۰۰</div>
                  </div>
                  <div class="field">
                    <label>HbA1c (%)</label>
                    <input type="number" step="0.1" name="hba1c" value="<?= htmlspecialchars($last['hba1c']??'') ?>" placeholder="مثال: ۵.۹">
                    <div class="ref-val">طبیعی: &lt;۵.۷ | پیش‌دیابت: ۵.۷–۶.۴</div>
                  </div>
                </div>
              </div>

              <!-- فشارخون و ضربان -->
              <div class="form-section">
                <div class="form-section-title">💉 فشارخون (NCD-RisC BP)</div>
                <div class="form-row">
                  <div class="field">
                    <label>فشار سیستولیک (mmHg)</label>
                    <input type="number" name="bp_sys" value="<?= htmlspecialchars($last['bp_systolic']??'') ?>" placeholder="۱۲۰">
                    <div class="ref-val">میانگین ایران مردان: ۱۲۳.۸ mmHg</div>
                  </div>
                  <div class="field">
                    <label>فشار دیاستولیک (mmHg)</label>
                    <input type="number" name="bp_dia" value="<?= htmlspecialchars($last['bp_diastolic']??'') ?>" placeholder="۸۰">
                    <div class="ref-val">طبیعی: &lt;۸۰</div>
                  </div>
                  <div class="field">
                    <label>ضربان قلب (bpm)</label>
                    <input type="number" name="heart_rate" value="<?= htmlspecialchars($last['heart_rate']??'') ?>" placeholder="۷۲">
                  </div>
                </div>
              </div>

              <!-- کلسترول -->
              <div class="form-section">
                <div class="form-section-title">🧪 کلسترول (NCD-RisC Cholesterol)</div>
                <div class="form-row">
                  <div class="field">
                    <label>کلسترول تام (mg/dL)</label>
                    <input type="number" step="0.1" name="cholesterol" value="<?= htmlspecialchars($last['cholesterol_total']??'') ?>" placeholder="۱۹۵">
                    <div class="ref-val">میانگین ایران مردان: ۱۸۹.۶ | مطلوب: &lt;۲۰۰</div>
                  </div>
                  <div class="field">
                    <label>LDL (mg/dL)</label>
                    <input type="number" step="0.1" name="ldl" value="<?= htmlspecialchars($last['ldl']??'') ?>" placeholder="۱۱۵">
                    <div class="ref-val">مطلوب: &lt;۱۰۰ | مرزی: &lt;۱۳۰</div>
                  </div>
                  <div class="field">
                    <label>HDL (mg/dL)</label>
                    <input type="number" step="0.1" name="hdl" value="<?= htmlspecialchars($last['hdl']??'') ?>" placeholder="۴۸">
                    <div class="ref-val">مطلوب مردان: &gt;۴۰ | زنان: &gt;۵۰</div>
                  </div>
                  <div class="field">
                    <label>تری‌گلیسرید (mg/dL)</label>
                    <input type="number" step="0.1" name="triglycerides" value="<?= htmlspecialchars($last['triglycerides']??'') ?>" placeholder="۱۵۰">
                    <div class="ref-val">طبیعی: &lt;۱۵۰ | بالا: ≥۲۰۰</div>
                  </div>
                </div>
              </div>

              <!-- آنتروپومتری -->
              <div class="form-section">
                <div class="form-section-title">📏 آنتروپومتری (NCD-RisC BMI)</div>
                <div class="form-row">
                  <div class="field">
                    <label>وزن (kg)</label>
                    <input type="number" step="0.1" name="weight" id="inp-weight" value="<?= htmlspecialchars($last['weight']??'') ?>" oninput="calcBMI()">
                    <div class="ref-val">میانگین ایران مردان: ۸۰ kg</div>
                  </div>
                  <div class="field">
                    <label>قد (cm)</label>
                    <input type="number" step="0.1" name="height" id="inp-height" value="<?= htmlspecialchars($last['height']??'') ?>" oninput="calcBMI()">
                  </div>
                  <div class="field">
                    <label>BMI (محاسبه خودکار)</label>
                    <input type="text" id="out-bmi" readonly style="background:var(--gray-100);font-weight:700">
                    <div class="ref-val">میانگین ایران مردان ۲۰۱۶: ۲۵.۷ | زنان: ۳۰.۱</div>
                  </div>
                  <div class="field">
                    <label>دور کمر (cm)</label>
                    <input type="number" step="0.1" name="waist" value="<?= htmlspecialchars($last['waist_circumference']??'') ?>">
                    <div class="ref-val">مردان: &lt;۹۴ | زنان: &lt;۸۰ (طبیعی)</div>
                  </div>
                </div>
              </div>

              <!-- کلیه -->
              <div class="form-section">
                <div class="form-section-title">🔬 عملکرد کلیه</div>
                <div class="form-row">
                  <div class="field">
                    <label>کراتینین (mg/dL)</label>
                    <input type="number" step="0.01" name="creatinine" value="<?= htmlspecialchars($last['creatinine']??'') ?>" placeholder="۰.۹">
                    <div class="ref-val">طبیعی مردان: ۰.۷–۱.۲</div>
                  </div>
                </div>
              </div>

              <!-- دارو و علائم -->
              <div class="form-section">
                <div class="form-section-title">💊 دارو و علائم</div>
                <div class="field" style="margin-bottom:10px">
                  <label>داروهای مصرفی</label>
                  <input type="text" name="medications" value="<?= htmlspecialchars($last['medications']??'') ?>" placeholder="متفورمین ۵۰۰mg، آتورواستاتین ۱۰mg">
                </div>
                <div class="field" style="margin-bottom:10px">
                  <label>علائم</label>
                  <input type="text" name="symptoms" value="<?= htmlspecialchars($last['symptoms']??'') ?>" placeholder="پرادراری، تشنگی، خستگی...">
                </div>
                <div class="field">
                  <label>یادداشت برای پزشک</label>
                  <textarea name="notes" rows="2"><?= htmlspecialchars($last['notes']??'') ?></textarea>
                </div>
              </div>

              <button type="submit" name="save" class="btn btn-green w-100">📤 ارسال برای تأیید پزشک</button>
            </form>
          </div>
        </div>

        <!-- نتایج و نمودارها -->
        <div>
          <!-- وضعیت کنونی -->
          <div class="card mb-20">
            <div class="card-header"><div class="card-title">وضعیت بالینی فعلی</div><div class="card-subtitle">مقایسه با مرجع NCD-RisC ایران</div></div>
            <div class="card-body" style="padding:0">
              <table class="data-table">
                <thead><tr><th>شاخص</th><th>مقدار</th><th>وضعیت</th><th>مرجع ایران</th></tr></thead>
                <tbody>
                  <?php
                  $bmi_v = ($last['weight']&&$last['height']) ? round($last['weight']/(($last['height']/100)**2),1) : ($latest_bmi['bmi_value']??null);
                  [$fbs_s,$fbs_c]   = getFBSStatus($last['fbs']??null);
                  [$hba_s,$hba_c]   = getHbA1cStatus($last['hba1c']??null);
                  [$bp_s,$bp_c]     = getBPStatus($last['bp_systolic']??null,$last['bp_diastolic']??null);
                  [$ch_s,$ch_c]     = getCholStatus($last['cholesterol_total']??null);
                  [$bmi_s,$bmi_c]   = $bmi_v ? getBMIStatus($bmi_v) : ['---',''];
                  $rows = [
                    ['FBS',      ($last['fbs']??'---').' mg/dL',  $fbs_s,$fbs_c,  'طبیعی: &lt;۱۰۰'],
                    ['HbA1c',    ($last['hba1c']??'---').'%',      $hba_s,$hba_c,  'طبیعی: &lt;۵.۷٪'],
                    ['فشارخون',  ($last['bp_systolic']??'---').'/'.($last['bp_diastolic']??'---'), $bp_s,$bp_c, 'ایران: ۱۲۳.۸ mmHg'],
                    ['کلسترول', ($last['cholesterol_total']??'---').' mg/dL', $ch_s,$ch_c, 'ایران: ۱۸۹.۶'],
                    ['LDL',      ($last['ldl']??'---').' mg/dL',  '','',          'مطلوب: &lt;۱۰۰'],
                    ['HDL',      ($last['hdl']??'---').' mg/dL',  '','',          'مطلوب: &gt;۴۰'],
                    ['BMI',      ($bmi_v??'---'),                   $bmi_s,$bmi_c,  'ایران مردان: ۲۵.۷'],
                  ];
                  foreach ($rows as $r) {
                    $pill = $r[2] ? "<span class='status-pill' style='background:".($r[3]?$r[3]:'#eee')."22;color:".($r[3]?$r[3]:'#666')."'>{$r[2]}</span>" : '';
                    echo "<tr><td><strong>{$r[0]}</strong></td><td>{$r[1]}</td><td>$pill</td><td style='font-size:11px;color:var(--gray-400)'>{$r[4]}</td></tr>";
                  }
                  ?>
                </tbody>
              </table>
            </div>
          </div>

          <!-- نمودار روند ۶ ماهه -->
          <div class="ncd-chart-card mb-20">
            <div class="ncd-chart-header"><div class="ncd-chart-title">روند شاخص‌های کلیدی — ۶ ماه اخیر</div></div>
            <div class="ncd-chart-body">
              <div class="chart-container" style="height:220px"><canvas id="multiChart"></canvas></div>
              <div class="ncd-legend" style="display:flex;gap:12px;flex-wrap:wrap;margin-top:10px;font-size:11px">
                <div>🔵 FBS</div><div>🟠 HbA1c×20</div><div>🟢 کلسترول/10</div><div>🔴 SBP</div>
              </div>
            </div>
          </div>

          <!-- مقایسه با NCD-RisC -->
          <div class="ncd-chart-card">
            <div class="ncd-chart-header"><div class="ncd-chart-title">جایگاه شما در جمعیت ایران</div><div class="ncd-chart-meta">مقایسه با مرجع NCD-RisC — Lancet 2016</div></div>
            <div class="ncd-chart-body">
              <?php
              $me_fbs  = $last['fbs'] ?? 118;
              $me_chol = $last['cholesterol_total'] ?? 195;
              $me_bmi  = $bmi_v ?? 27;
              $me_sbp  = $last['bp_systolic'] ?? 128;
              $bars = [
                ['FBS من',          $me_fbs,  200, 'var(--blue)'],
                ['FBS طبیعی',       100,      200, 'var(--green)'],
                ['کلسترول من',      $me_chol, 300, 'var(--orange)'],
                ['کلسترول ایران',   189.6,    300, 'var(--gray-400)'],
                ['SBP من',          $me_sbp,  180, 'var(--red)'],
                ['SBP ایران مردان', 123.8,    180, 'var(--gray-400)'],
              ];
              foreach ($bars as $b) {
                $pct = min(100, round(($b[1]/$b[2])*100));
                echo "<div style='margin-bottom:10px'>";
                echo "<div style='display:flex;justify-content:space-between;font-size:11px;color:var(--gray-600);margin-bottom:3px'><span>{$b[0]}</span><strong>{$b[1]}</strong></div>";
                echo "<div style='height:8px;background:var(--gray-200);border-radius:99px;overflow:hidden'><div style='height:100%;width:{$pct}%;background:{$b[3]};border-radius:99px'></div></div>";
                echo "</div>";
              }
              ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
Chart.defaults.font.family = 'Vazirmatn, Tahoma';
const labels   = <?= json_encode(array_column($history,'record_date')) ?>;
const fbsArr   = <?= json_encode(array_column($history,'fbs')) ?>;
const hba1cArr = <?= json_encode(array_map(fn($r)=>round(($r['hba1c']??0)*20,1),$history)) ?>;
const cholArr  = <?= json_encode(array_map(fn($r)=>round(($r['cholesterol_total']??0)/10,1),$history)) ?>;
const sbpArr   = <?= json_encode(array_column($history,'bp_systolic')) ?>;

new Chart(document.getElementById('multiChart').getContext('2d'),{
  type:'line',
  data:{
    labels,
    datasets:[
      {label:'FBS',data:fbsArr,borderColor:'#2980B9',tension:0.4,pointRadius:4,fill:false},
      {label:'HbA1c×20',data:hba1cArr,borderColor:'#E67E22',tension:0.4,pointRadius:4,fill:false},
      {label:'کلسترول÷10',data:cholArr,borderColor:'#27AE60',tension:0.4,pointRadius:4,fill:false},
      {label:'SBP',data:sbpArr,borderColor:'#E74C3C',tension:0.4,pointRadius:4,fill:false},
    ]
  },
  options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},
    scales:{y:{ticks:{font:{size:10}}},x:{ticks:{font:{size:10}},grid:{display:false}}}}
});

function calcBMI(){
  const w=parseFloat(document.getElementById('inp-weight').value);
  const h=parseFloat(document.getElementById('inp-height').value)/100;
  const bmi=w&&h ? (w/(h*h)).toFixed(1) : '';
  document.getElementById('out-bmi').value = bmi ? bmi+' kg/m²' : '';
}
// init
const lw=<?= json_encode($last['weight']??null) ?>, lh=<?= json_encode($last['height']??null) ?>;
if(lw&&lh){document.getElementById('inp-weight').value=lw;document.getElementById('inp-height').value=lh;calcBMI();}
</script>
<script src="../assets/js/db.js"></script>
<script src="../assets/js/sync.js"></script>
<script src="../assets/js/app.js"></script>

<script>
// ── آفلاین: ذخیره فرم در IDB هنگام submit ──────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  const form = document.querySelector('form[method="POST"]');
  if (!form) return;

  form.addEventListener('submit', async e => {
    // اگر آنلاین هستیم → submit عادی PHP
    if (navigator.onLine) return;

    // آفلاین → جلوگیری از submit + ذخیره در IDB
    e.preventDefault();

    const fd = new FormData(form);
    const data = {
      fbs:            fd.get('fbs')            || null,
      ppg:            fd.get('ppg')            || null,
      hba1c:          fd.get('hba1c')          || null,
      bp_systolic:    fd.get('bp_sys')         || null,
      bp_diastolic:   fd.get('bp_dia')         || null,
      heart_rate:     fd.get('heart_rate')     || null,
      cholesterol_total: fd.get('cholesterol') || null,
      ldl:            fd.get('ldl')            || null,
      hdl:            fd.get('hdl')            || null,
      triglycerides:  fd.get('triglycerides')  || null,
      weight:         fd.get('weight')         || null,
      height:         fd.get('height')         || null,
      waist_circumference: fd.get('waist')     || null,
      creatinine:     fd.get('creatinine')     || null,
      medications:    fd.get('medications')    || '',
      symptoms:       fd.get('symptoms')       || '',
      notes:          fd.get('notes')          || '',
      record_date:    new Date().toISOString().split('T')[0],
    };

    if (typeof PakDavaSync !== 'undefined') {
      await PakDavaSync.enqueue('clinical_queue', { data });
    } else if (typeof PakDavaDB !== 'undefined') {
      await PakDavaDB.enqueue('clinical_queue', { data });
    }

    showToast('📥 آفلاین — داده ذخیره شد، هنگام اتصال ارسال می‌شود');

    // نمایش پیام موفقیت در UI
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-success';
    alertDiv.innerHTML = '📥 داده آفلاین ذخیره شد — هنگام اتصال به سرور ارسال خواهد شد';
    form.insertAdjacentElement('beforebegin', alertDiv);
    setTimeout(() => alertDiv.remove(), 5000);
  });
});
</script>
</body>
</html>
