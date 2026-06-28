<?php
/**
 * doctor/enter_clinical.php v3
 * ورود داده بالینی ۳ ماهه توسط پزشک — شامل تمام شاخص‌های NCD-RisC
 */
session_start();
require_once __DIR__ . '/../conn.php';
if (!isset($_SESSION['user_id'])||$_SESSION['role']!=='doctor'){header('Location: ../index.php');exit;}

$doctor_id = (int)$_SESSION['user_id'];
$success=$error=null;

// لیست بیماران
$patients = [];
$s = $conn->prepare("SELECT u.id,u.fullname,u.age,u.gender,u.phone,
    (SELECT risk_level FROM risk_assessment WHERE patient_id=u.id ORDER BY assessment_date DESC LIMIT 1) as risk_level,
    (SELECT stage FROM soc_assessment WHERE patient_id=u.id ORDER BY assessment_date DESC LIMIT 1) as soc_stage
    FROM users u WHERE u.role='patient' ORDER BY u.fullname");
if ($s){$s->execute();$patients=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();}

// ذخیره داده
if ($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['save_clinical'])) {
    $pid  = (int)$_POST['patient_id'];
    $stmt = $conn->prepare("
        INSERT INTO clinical_data
          (patient_id,record_date,fbs,ppg,hba1c,bp_systolic,bp_diastolic,
           heart_rate,cholesterol_total,ldl,hdl,triglycerides,
           weight,height,waist_circumference,creatinine,
           symptoms,medications,notes,status,approved_by,approved_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'approved',?,NOW())
    ");
    $date  = $_POST['record_date']??date('Y-m-d');
    $fbs   = $_POST['fbs']!==''   ? floatval($_POST['fbs'])   : null;
    $ppg   = $_POST['ppg']!==''   ? floatval($_POST['ppg'])   : null;
    $hba1c = $_POST['hba1c']!=='' ? floatval($_POST['hba1c']) : null;
    $bps   = $_POST['bp_sys']!==''? intval($_POST['bp_sys'])  : null;
    $bpd   = $_POST['bp_dia']!==''? intval($_POST['bp_dia'])  : null;
    $hr    = $_POST['heart_rate']!==''?intval($_POST['heart_rate']):null;
    $tc    = $_POST['cholesterol']!==''?floatval($_POST['cholesterol']):null;
    $ldl   = $_POST['ldl']!==''  ?floatval($_POST['ldl']):null;
    $hdl   = $_POST['hdl']!==''  ?floatval($_POST['hdl']):null;
    $tg    = $_POST['tg']!==''   ?floatval($_POST['tg']):null;
    $wt    = $_POST['weight']!==''?floatval($_POST['weight']):null;
    $ht    = $_POST['height']!==''?floatval($_POST['height']):null;
    $waist = $_POST['waist']!=='' ?floatval($_POST['waist']):null;
    $cr    = $_POST['creatinine']!==''?floatval($_POST['creatinine']):null;
    $symp  = $_POST['symptoms']??'';
    $meds  = $_POST['medications']??'';
    $notes = $_POST['notes']??'';

    if ($stmt) {
        $stmt->bind_param('isdddiiiddddddddsssii',
            $pid,$date,$fbs,$ppg,$hba1c,$bps,$bpd,$hr,
            $tc,$ldl,$hdl,$tg,$wt,$ht,$waist,$cr,
            $symp,$meds,$notes,$doctor_id
        );
        if ($stmt->execute()) {
            $lid = $conn->insert_id;
            // BMI update
            if ($wt&&$ht) {
                $bmi_v=round($wt/(($ht/100)**2),1);
                $bmi_s=$bmi_v<18.5?'کم‌وزن':($bmi_v<25?'نرمال':($bmi_v<30?'اضافه‌وزن':'چاق'));
                $conn->query("INSERT INTO bmi_records(user_id,record_date,height,weight,bmi_value,bmi_status) VALUES($pid,'$date',$ht,$wt,$bmi_v,'$bmi_s') ON DUPLICATE KEY UPDATE weight=VALUES(weight),bmi_value=VALUES(bmi_value)");
                $conn->query("UPDATE users SET height=$ht,weight=$wt,bmi_value=$bmi_v,bmi_status='$bmi_s' WHERE id=$pid");
            }
            // هشدار FBS
            if ($fbs&&$fbs>=126)
                $conn->query("INSERT INTO alerts(patient_id,doctor_id,type,message) VALUES($pid,$doctor_id,'clinical_threshold','FBS=$fbs ≥ 126 — محدوده دیابت')");
            // نوتیفیکیشن به بیمار
            $dname=$_SESSION['name']??'پزشک';
            $conn->query("INSERT INTO notifications(user_id,type,title,message,url) VALUES($pid,'doctor','داده بالینی جدید','داده بالینی توسط دکتر $dname ثبت و تأیید شد','clinical_data.php')");
            // SOC direction
            if (!empty($_POST['soc_direction'])) {
                $soc_new=$_POST['soc_direction'];
                $conn->query("INSERT INTO soc_assessment(patient_id,assessment_date,stage,comments) VALUES($pid,'$date','$soc_new','تعیین توسط پزشک مسئول')");
            }
            $success='داده‌های بالینی ثبت، تأیید و به بیمار اطلاع داده شد ✓';
        } else { $error=$stmt->error; }
        $stmt->close();
    }
}

// آخرین سه چرخه بیمار انتخاب‌شده
$sel_pid = (int)($_POST['patient_id']??$_GET['pid']??($patients[0]['id']??0));
$cycles = [];
if ($sel_pid) {
    $s=$conn->prepare("SELECT * FROM clinical_data WHERE patient_id=? ORDER BY record_date DESC LIMIT 3");
    $s->bind_param('i',$sel_pid);$s->execute();
    $cycles=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();
}
$sel_patient = array_filter($patients,fn($p)=>$p['id']===$sel_pid);
$sel_patient = array_values($sel_patient)[0]??[];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ورود داده بالینی | پک دوا</title>
<link rel="manifest" href="../manifest.json"><meta name="theme-color" content="#1A7A4A">
<link rel="stylesheet" href="../assets/css/main.css">
<style>
.form-section{border:1px solid var(--gray-200);border-radius:10px;padding:14px;margin-bottom:14px;background:var(--gray-50)}
.form-section-title{font-weight:700;color:var(--blue);margin-bottom:10px;font-size:12px;text-transform:uppercase;letter-spacing:.4px}
.form-row{display:flex;gap:10px;flex-wrap:wrap}
.form-row .field{flex:1;min-width:120px}
.field label{display:block;font-size:11px;font-weight:700;color:var(--gray-600);margin-bottom:3px}
.field input,.field select,.field textarea{width:100%;padding:8px 10px;border:1.5px solid var(--gray-300);border-radius:6px;font-size:13px;font-family:inherit}
.field input:focus{outline:none;border-color:var(--blue)}
.ref-note{font-size:10px;color:var(--gray-400);margin-top:2px}
.cycle-card{border:1.5px solid var(--gray-200);border-radius:8px;padding:12px;margin-bottom:10px}
.cycle-title{font-size:11px;font-weight:700;color:var(--blue);margin-bottom:6px}
</style>
</head>
<body>
<div class="app-layout">
  <?php include '../assets/sidebar_doctor.php'; ?>
  <div class="main-content">
    <div class="topbar">
      <div><div class="topbar-title">ورود داده بالینی ۳ ماهه</div><div class="topbar-subtitle">پزشک تأیید می‌کند — بیمار نوتیفیکیشن می‌گیرد</div></div>
    </div>
    <div class="page-body">
      <?php if($success):?><div class="alert alert-success"><?=htmlspecialchars($success)?></div><?php endif;?>
      <?php if($error):  ?><div class="alert alert-danger"><?=htmlspecialchars($error)?></div><?php endif;?>

      <!-- بنر چرخه ۳ ماهه -->
      <div style="background:linear-gradient(135deg,var(--blue),#1A2980);color:white;border-radius:var(--radius);padding:16px 20px;margin-bottom:16px;display:flex;gap:14px;align-items:center">
        <span style="font-size:28px">🔄</span>
        <div>
          <div style="font-weight:700">چرخه بالینی ۳ ماهه</div>
          <div style="font-size:12px;opacity:.9">داده پزشک → تأیید خودکار → اعلان فوری به بیمار → به‌روز شدن ریسک و SOC</div>
        </div>
      </div>

      <div class="grid-2">
        <div class="card">
          <div class="card-header"><div class="card-title">ورود داده</div></div>
          <div class="card-body">
            <form method="POST">
              <div class="field" style="margin-bottom:14px">
                <label>انتخاب بیمار</label>
                <select name="patient_id" id="sel-patient" onchange="this.form.submit()">
                  <?php foreach($patients as $p): ?>
                  <option value="<?=$p['id']?>" <?=$p['id']==$sel_pid?'selected':''?>>
                    <?=htmlspecialchars($p['fullname'])?> — ریسک: <?=$p['risk_level']??'نامشخص'?> | SOC: <?=$p['soc_stage']??'نامشخص'?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="field" style="margin-bottom:14px">
                <label>تاریخ ثبت</label>
                <input type="date" name="record_date" value="<?=date('Y-m-d')?>">
              </div>

              <!-- دیابت -->
              <div class="form-section">
                <div class="form-section-title">🩸 شاخص‌های دیابت (NCD-RisC Diabetes)</div>
                <div class="form-row">
                  <div class="field"><label>FBS (mg/dL)</label><input type="number" step=".1" name="fbs" placeholder="118"><div class="ref-note">پیش‌دیابت: ۱۰۰–۱۲۵ | دیابت: ≥۱۲۶</div></div>
                  <div class="field"><label>PPG (mg/dL)</label><input type="number" step=".1" name="ppg" placeholder="145"></div>
                  <div class="field"><label>HbA1c (%)</label><input type="number" step=".1" name="hba1c" placeholder="5.9"><div class="ref-note">پیش‌دیابت: ۵.۷–۶.۴</div></div>
                </div>
              </div>

              <!-- فشارخون -->
              <div class="form-section">
                <div class="form-section-title">💉 فشارخون (NCD-RisC BP)</div>
                <div class="form-row">
                  <div class="field"><label>سیستولیک (mmHg)</label><input type="number" name="bp_sys" placeholder="128"><div class="ref-note">ایران مردان: ۱۲۳.۸</div></div>
                  <div class="field"><label>دیاستولیک (mmHg)</label><input type="number" name="bp_dia" placeholder="82"></div>
                  <div class="field"><label>ضربان (bpm)</label><input type="number" name="heart_rate" placeholder="72"></div>
                </div>
              </div>

              <!-- کلسترول -->
              <div class="form-section">
                <div class="form-section-title">🧪 کلسترول (NCD-RisC Cholesterol)</div>
                <div class="form-row">
                  <div class="field"><label>کلسترول تام</label><input type="number" step=".1" name="cholesterol" placeholder="195"><div class="ref-note">ایران مردان: ۱۸۹.۶ | مطلوب &lt;۲۰۰</div></div>
                  <div class="field"><label>LDL</label><input type="number" step=".1" name="ldl" placeholder="115"><div class="ref-note">مطلوب &lt;۱۰۰</div></div>
                  <div class="field"><label>HDL</label><input type="number" step=".1" name="hdl" placeholder="48"><div class="ref-note">مردان &gt;۴۰</div></div>
                  <div class="field"><label>تری‌گلیسرید</label><input type="number" step=".1" name="tg" placeholder="150"><div class="ref-note">طبیعی &lt;۱۵۰</div></div>
                </div>
              </div>

              <!-- آنتروپومتری -->
              <div class="form-section">
                <div class="form-section-title">📏 آنتروپومتری (NCD-RisC BMI)</div>
                <div class="form-row">
                  <div class="field"><label>وزن (kg)</label><input type="number" step=".1" name="weight" id="inp-w" oninput="calcBMI()" placeholder="82"><div class="ref-note">ایران مردان: ۸۰ kg</div></div>
                  <div class="field"><label>قد (cm)</label><input type="number" step=".1" name="height" id="inp-h" oninput="calcBMI()" placeholder="174"></div>
                  <div class="field"><label>BMI (خودکار)</label><input id="out-bmi" readonly style="background:var(--gray-100);font-weight:700"><div class="ref-note">ایران مردان ۲۰۱۶: ۲۵.۷</div></div>
                  <div class="field"><label>دور کمر (cm)</label><input type="number" step=".1" name="waist" placeholder="96"><div class="ref-note">مردان &lt;۹۴</div></div>
                </div>
              </div>

              <!-- کلیه -->
              <div class="form-section">
                <div class="form-section-title">🔬 کلیه</div>
                <div class="form-row">
                  <div class="field"><label>کراتینین (mg/dL)</label><input type="number" step=".01" name="creatinine" placeholder="0.9"></div>
                </div>
              </div>

              <!-- دارو و SOC -->
              <div class="form-section">
                <div class="form-section-title">💊 درمان و SOC</div>
                <div class="field" style="margin-bottom:8px"><label>داروهای تجویزی</label><input type="text" name="medications" placeholder="متفورمین ۵۰۰mg، آتورواستاتین ۱۰mg"></div>
                <div class="field" style="margin-bottom:8px"><label>علائم</label><input type="text" name="symptoms" placeholder="پرادراری، تشنگی..."></div>
                <div class="field" style="margin-bottom:8px">
                  <label>تعیین مرحله SOC</label>
                  <select name="soc_direction">
                    <option value="">— بدون تغییر</option>
                    <option value="precontemplation">پیش از تأمل</option>
                    <option value="contemplation">تأمل</option>
                    <option value="preparation">آماده‌سازی</option>
                    <option value="action">عمل</option>
                    <option value="maintenance">نگهداری</option>
                  </select>
                </div>
                <div class="field"><label>پیام به بیمار</label><textarea name="notes" rows="2" placeholder="نتایج آزمایش بررسی شد..."></textarea></div>
              </div>

              <button type="submit" name="save_clinical" class="btn btn-blue w-100">✓ ثبت و تأیید — ارسال نوتیفیکیشن به بیمار</button>
            </form>
          </div>
        </div>

        <!-- مقایسه چرخه‌ها -->
        <div>
          <?php if($sel_patient): ?>
          <div class="card mb-20">
            <div class="card-header"><div class="card-title"><?=htmlspecialchars($sel_patient['fullname'])?></div><div class="card-subtitle">مقایسه چرخه‌های قبلی</div></div>
            <div class="card-body" style="padding:0">
              <?php if(empty($cycles)): ?>
                <div style="padding:20px;text-align:center;color:var(--gray-400)">هنوز داده‌ای ثبت نشده</div>
              <?php else: ?>
              <table class="data-table">
                <thead><tr><th>تاریخ</th><th>FBS</th><th>HbA1c</th><th>BP</th><th>کلسترول</th><th>BMI</th><th>وضعیت</th></tr></thead>
                <tbody>
                  <?php foreach($cycles as $c):
                    $bmi_c=$c['weight']&&$c['height']?round($c['weight']/(($c['height']/100)**2),1):'---';
                    $bp_c=($c['bp_systolic']??'?').'/'.($c['bp_diastolic']??'?');
                    $badge=['approved'=>'badge-approved','pending'=>'badge-pending','rejected'=>'badge-rejected'][$c['status']]??'badge-pending';
                  ?>
                  <tr>
                    <td><?=$c['record_date']?></td>
                    <td style="color:<?=($c['fbs']??0)>=126?'var(--red)':'var(--gray-700)'?>;font-weight:700"><?=$c['fbs']??'---'?></td>
                    <td style="color:<?=($c['hba1c']??0)>=6.5?'var(--red)':'var(--gray-700)'?>"><?=$c['hba1c']??'---'?>%</td>
                    <td><?=$bp_c?></td>
                    <td><?=$c['cholesterol_total']??'---'?></td>
                    <td><?=$bmi_c?></td>
                    <td><span class="badge-status <?=$badge?>"><?=$c['status']==='approved'?'تأیید':($c['status']==='pending'?'در انتظار':'رد')?></span></td>
                  </tr>
                  <?php endforeach;?>
                </tbody>
              </table>
              <?php endif;?>
            </div>
          </div>
          <?php endif;?>

          <!-- آستانه‌های هشدار -->
          <div class="card">
            <div class="card-header"><div class="card-title">⚡ آستانه‌های هشدار خودکار</div></div>
            <div class="card-body" style="padding:0">
              <?php
              $thresholds=[
                ['FBS ≥ ۱۲۶','تشخیص دیابت','ارجاع فوری','#E74C3C'],
                ['HbA1c ≥ ۶.۵٪','دیابت','نوتیفیکیشن اضطراری','#E74C3C'],
                ['SBP ≥ ۱۴۰','پرفشاری مرحله ۲','اعلان به بیمار','#E67E22'],
                ['کلسترول ≥ ۲۴۰','هیپرکلسترولمی','بررسی دارو','#F1C40F'],
                ['BMI ≥ ۳۰','چاقی','برنامه تغذیه','#E67E22'],
                ['عدم تمکین ≥ ۲ روز','انحراف از برنامه','هشدار موبایل','#2980B9'],
              ];
              foreach($thresholds as $t) echo "<div style='display:flex;align-items:center;gap:12px;padding:10px 16px;border-bottom:1px solid var(--gray-100)'><div style='width:8px;height:8px;border-radius:50%;background:{$t[3]};flex-shrink:0'></div><div style='flex:1'><div style='font-size:12px;font-weight:700'>{$t[0]}</div><div style='font-size:11px;color:var(--gray-400)'>{$t[1]}</div></div><div style='font-size:11px;font-weight:700;color:{$t[3]}'>{$t[2]}</div></div>";
              ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
function calcBMI(){
  const w=parseFloat(document.getElementById('inp-w').value),h=parseFloat(document.getElementById('inp-h').value)/100;
  document.getElementById('out-bmi').value=w&&h?(w/(h*h)).toFixed(1)+' kg/m²':'';
}
</script>
<script src="../assets/js/db.js"></script>
<script src="../assets/js/sync.js"></script>
<script src="../assets/js/app.js"></script>
</body>
</html>
