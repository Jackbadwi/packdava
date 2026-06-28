<?php
session_start();
require_once __DIR__ . '/../conn.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// ==================== تابع safeQuery ====================
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

// ==================== دریافت اطلاعات کاربر ====================
$user = null;
$stmt = $conn->prepare("SELECT id, fullname, email, phone, dob, gender, address, student_id, school, class, profile_pic, height, weight, bmi_value, bmi_status FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$user) die('اطلاعات کاربر یافت نشد.');

$patient_id = $user_id;

// ==================== تابع دریافت عکس پروفایل ====================
function getProfilePic($user_id) {
    $base_filename = 'profile_' . $user_id;
    $upload_dir = '../uploads/profile_pics/';
    $formats = ['.jpg', '.jpeg', '.png', '.gif'];
    foreach ($formats as $format) {
        $profile_path = $upload_dir . $base_filename . $format;
        if (file_exists($profile_path)) return $profile_path;
    }
    return '../assets/Images/default-avatar.png';
}
$profile_pic = getProfilePic($user_id);

// ==================== دریافت داده‌ها ====================
// ریسک
$risk_score = 0;
$risk_label = 'نامشخص';
$risk_color = 'var(--gray-500)';
$risk = safeQuery($conn, "SELECT risk_level FROM risk_assessment WHERE patient_id = ? ORDER BY assessment_date DESC LIMIT 1", [$patient_id]);
if ($risk) {
    switch ($risk['risk_level']) {
        case 'low':   $risk_score = 6;  $risk_label = 'کم'; $risk_color = '#27AE60'; break;
        case 'medium': $risk_score = 14; $risk_label = 'متوسط'; $risk_color = '#E67E22'; break;
        case 'high':   $risk_score = 18; $risk_label = 'بالا'; $risk_color = '#E74C3C'; break;
    }
}

// SOC
$soc_stage = 'C';
$soc_label = 'تأمل';
$soc_color = '#E67E22';
$soc_stages_map = [
    'PC' => ['label'=>'پیش از تأمل', 'color'=>'#E74C3C', 'desc'=>'هنوز به تغییر فکر نمی‌کنم'],
    'C'  => ['label'=>'تأمل', 'color'=>'#E67E22', 'desc'=>'در حال فکر کردن هستم'],
    'PR' => ['label'=>'آماده‌سازی', 'color'=>'#F1C40F', 'desc'=>'قصد دارم به زودی شروع کنم'],
    'A'  => ['label'=>'عمل', 'color'=>'#27AE60', 'desc'=>'دارم تغییر می‌دهم'],
    'M'  => ['label'=>'نگهداری', 'color'=>'#2980B9', 'desc'=>'تغییر را حفظ می‌کنم'],
];
$soc = safeQuery($conn, "SELECT stage FROM soc_assessment WHERE patient_id = ? ORDER BY assessment_date DESC LIMIT 1", [$patient_id]);
if ($soc && isset($soc['stage']) && isset($soc_stages_map[$soc['stage']])) {
    $soc_stage = $soc['stage'];
    $soc_label = $soc_stages_map[$soc_stage]['label'];
    $soc_color = $soc_stages_map[$soc_stage]['color'];
}

// روزهای متوالی
$consecutive_days = 0;
$best_days = 0;
$prog = safeQuery($conn, "SELECT COUNT(DISTINCT record_date) as days FROM progress WHERE patient_id = ? AND record_date >= CURDATE() - INTERVAL 30 DAY", [$patient_id]);
if ($prog) {
    $consecutive_days = (int)$prog['days'];
    $best_days = $consecutive_days;
}

// کاهش ریسک
$risk_reduction = 0;
$first_risk = safeQuery($conn, "SELECT risk_level FROM risk_assessment WHERE patient_id = ? ORDER BY assessment_date ASC LIMIT 1", [$patient_id]);
$last_risk = safeQuery($conn, "SELECT risk_level FROM risk_assessment WHERE patient_id = ? ORDER BY assessment_date DESC LIMIT 1", [$patient_id]);
if ($first_risk && $last_risk) {
    $risk_values = ['low'=>1, 'medium'=>2, 'high'=>3];
    $first = $risk_values[$first_risk['risk_level']] ?? 2;
    $last = $risk_values[$last_risk['risk_level']] ?? 2;
    if ($first > $last) $risk_reduction = round((($first - $last) / $first) * 100);
}

// ==================== داده‌های بالینی و BMI ====================
$clinical = [
    'fbs' => '--',
    'hba1c' => '--',
    'bp' => '--',
];
$last_clinical = safeQuery($conn, "SELECT blood_pressure, weight, height, fbs, hba1c FROM clinical_data WHERE patient_id = ? ORDER BY record_date DESC LIMIT 1", [$patient_id]);
if ($last_clinical) {
    $clinical['bp'] = $last_clinical['blood_pressure'] ?? '--';
    if (isset($last_clinical['fbs'])) $clinical['fbs'] = $last_clinical['fbs'];
    if (isset($last_clinical['hba1c'])) $clinical['hba1c'] = $last_clinical['hba1c'];
}

// دریافت آخرین BMI از bmi_records
$latest_bmi = safeQuery($conn, "SELECT bmi_value, bmi_status, record_date FROM bmi_records WHERE user_id = ? ORDER BY record_date DESC LIMIT 1", [$patient_id]);
$clinical['bmi'] = $latest_bmi['bmi_value'] ?? '--';
$clinical['status_bmi'] = $latest_bmi['bmi_status'] ?? 'نامشخص';
if ($clinical['bmi'] === '--' && $last_clinical && $last_clinical['weight'] && $last_clinical['height']) {
    $bmi = round($last_clinical['weight'] / (($last_clinical['height']/100)**2), 1);
    $clinical['bmi'] = $bmi;
    $clinical['status_bmi'] = $bmi < 18.5 ? 'کم‌وزن' : ($bmi < 25 ? 'نرمال' : ($bmi < 30 ? 'اضافه‌وزن' : 'چاق'));
}

// برنامه امروز
$today_tasks = [];
try {
    $stmt = $conn->prepare("SELECT activities FROM daily_plan WHERE patient_id = ? AND plan_date = CURDATE() ORDER BY id LIMIT 3");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    if (count($tasks) > 0) {
        foreach ($tasks as $t) $today_tasks[] = ['title' => $t['activities'] ?: 'فعالیت روزانه', 'done' => false];
    } else {
        $today_tasks = [
            ['title'=>'ثبت فعالیت بدنی', 'done'=>false],
            ['title'=>'پیگیری رژیم غذایی', 'done'=>false],
            ['title'=>'یادآور دارو', 'done'=>false],
        ];
    }
    $stmt->close();
} catch (Exception $e) {
    $today_tasks = [
        ['title'=>'ثبت فعالیت بدنی', 'done'=>false],
        ['title'=>'پیگیری رژیم غذایی', 'done'=>false],
        ['title'=>'یادآور دارو', 'done'=>false],
    ];
}

// مقایسه همتا (mock)
$peers = [
    ['avatar'=>'⭐','id'=>'کاربر ۱۴۷','detail'=>'مرد، ۴۵ ساله — مرحله: عمل','progress'=>'↓ ۲۳٪ کاهش ریسک','days'=>'۴۵ روز','rank'=>1,'color'=>'#F1C40F'],
    ['avatar'=>'🏃','id'=>'کاربر ۲۳۸','detail'=>'مرد، ۴۳ ساله — مرحله: آماده‌سازی','progress'=>'↓ ۱۸٪ کاهش ریسک','days'=>'۳۰ روز','rank'=>2,'color'=>'var(--gray-400)'],
    ['avatar'=>'👤','id'=>'شما','detail'=>'مرد، ۴۲ ساله — مرحله: تأمل','progress'=>'↓ ۱۵٪ کاهش ریسک','days'=>'۷ روز','rank'=>3,'color'=>'var(--green)','me'=>true],
];

// اعلانات
$unread_count = 0;
$unread = safeQuery($conn, "SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0", [$user_id]);
if ($unread) $unread_count = (int)$unread['unread'];

$fullname = $user['fullname'] ?? 'کاربر';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
      <link rel="manifest" href="../manifest.json">
  <meta name="theme-color" content="#1A7A4A">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="پک دوا">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد بیمار | پک دوا</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .profile-avatar { width:40px; height:40px; border-radius:50%; object-fit:cover; border:2px solid var(--gray-300); transition:0.3s; cursor:pointer; }
        .profile-avatar:hover { border-color:var(--blue); transform:scale(1.05); }
        .profile-link { display:flex; align-items:center; gap:8px; text-decoration:none; color:var(--gray-700); font-weight:600; transition:0.3s; }
        .profile-link:hover { color:var(--blue); text-decoration:none; }
        .soc-stage-card { background:linear-gradient(135deg, var(--soc-color), var(--soc-color)cc); }
    </style>
</head>
<body>
<div class="app-layout">
    <?php include '../assets/sidebar_patient.php'; ?>
    <div class="main-content">
        <div class="topbar">
            <div>
                <div class="topbar-title">داشبورد سلامت</div>
                <div class="topbar-subtitle">خوش آمدید، <?= htmlspecialchars($fullname) ?></div>
            </div>
            <div class="topbar-actions" style="display:flex;align-items:center;gap:16px;">
                <a href="../patient/user.php" class="profile-link" title="پروفایل">
                    <img src="<?= $profile_pic ?>" alt="پروفایل" class="profile-avatar">
                    <span style="font-size:13px;display:none;" class="d-none d-sm-inline">پروفایل</span>
                </a>
                <a href="notifications.php" style="font-size:20px;text-decoration:none;position:relative;">
                    🔔
                    <?php if ($unread_count > 0): ?>
                        <span style="position:absolute;top:-6px;right:-6px;background:var(--red);color:white;padding:1px 6px;border-radius:99px;font-size:10px;font-weight:700;"><?= $unread_count ?></span>
                    <?php endif; ?>
                </a>
                <span class="topbar-date" id="now-date"></span>
            </div>
        </div>
        <div class="page-body">
            <!-- آمار -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-card-top"><div class="stat-icon orange">📊</div><span class="stat-trend up">↑ خطر</span></div>
                    <div class="stat-value text-orange"><?= $risk_score ?></div>
                    <div class="stat-label">امتیاز ریسک دیابت</div>
                    <div class="stat-sub">از ۲۶ (FINDRISC)</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-top"><div class="stat-icon green">🔄</div><span class="stat-trend neutral"><?= $soc_label ?></span></div>
                    <div class="stat-value text-green" style="font-size:20px"><?= $soc_label ?></div>
                    <div class="stat-label">مرحله تغییر فعلی (SOC)</div>
                    <div class="stat-sub">مرحله <?= array_search($soc_stage, array_keys($soc_stages_map)) + 1 ?> از ۵</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-top"><div class="stat-icon blue">🔥</div><span class="stat-trend down">↑ بهتر</span></div>
                    <div class="stat-value" style="color:var(--blue)"><?= $consecutive_days ?></div>
                    <div class="stat-label">روز متوالی در برنامه</div>
                    <div class="stat-sub">بهترین: <?= $best_days ?> روز</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-top"><div class="stat-icon green">📉</div><span class="stat-trend down">↓ کاهش</span></div>
                    <div class="stat-value text-green"><?= $risk_reduction ?>٪</div>
                    <div class="stat-label">کاهش ریسک نسبت به شروع</div>
                    <div class="stat-sub">در ۳ ماه گذشته</div>
                </div>
            </div>

            <!-- دو بخش -->
            <div class="grid-2">
                <div class="ncd-chart-card">
                    <div class="ncd-chart-header"><div class="ncd-chart-title">روند ریسک دیابت من — ۶ ماه اخیر</div></div>
                    <div class="ncd-chart-body">
                        <div class="chart-container" style="height:240px"><canvas id="riskTrendChart"></canvas></div>
                        <div class="ncd-legend">
                            <div class="legend-item"><div class="legend-dot" style="background:#E74C3C"></div>امتیاز ریسک من</div>
                            <div class="legend-item"><div class="legend-dot" style="background:#ADB5BD;border-radius:2px;width:14px;height:3px"></div>خط هشدار (۱۵)</div>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header"><div><div class="card-title">مرحله تغییر رفتاری (SOC)</div><div class="card-subtitle">مدل Prochaska & DiClemente</div></div></div>
                    <div class="card-body">
                        <div class="soc-stage-card" style="background:linear-gradient(135deg,<?= $soc_color ?>,<?= $soc_color ?>cc);padding:16px;border-radius:10px;color:white;">
                            <div style="font-size:12px;opacity:0.8;font-weight:600">مرحله فعلی شما</div>
                            <div class="soc-stage-name" style="font-size:24px;font-weight:700;"><?= $soc_label ?></div>
                            <div class="soc-stage-desc" style="font-size:14px;opacity:0.9;"><?= $soc_stages_map[$soc_stage]['desc'] ?? 'در حال فکر کردن به تغییر' ?></div>
                        </div>
                        <div style="display:flex;gap:6px;margin-bottom:12px;margin-top:12px;">
                            <?php
                            $stages = [
                                ['پیش از تأمل','#E74C3C',($soc_stage=='PC')],
                                ['تأمل','#E67E22',($soc_stage=='C')],
                                ['آماده‌سازی','#F1C40F',($soc_stage=='PR')],
                                ['عمل','#27AE60',($soc_stage=='A')],
                                ['نگهداری','#2980B9',($soc_stage=='M')],
                            ];
                            foreach($stages as $s) {
                                $active = $s[2] ? 'border:2px solid '.$s[1].';' : 'opacity:0.4;';
                                echo '<div style="flex:1;height:6px;border-radius:99px;background:'.$s[1].';'.$active.'"></div>';
                            }
                            ?>
                        </div>
                        <div class="soc-interventions">
                            <div class="soc-int-item" style="background:rgba(230,126,34,0.15);border-radius:8px;color:var(--gray-800);padding:8px;margin-bottom:6px;">⚖️ تحلیل مزایا و معایب تغییر سبک زندگی</div>
                            <div class="soc-int-item" style="background:rgba(230,126,34,0.15);border-radius:8px;color:var(--gray-800);padding:8px;">🎯 تعیین هدف کوچک و قابل دستیابی برای فردا</div>
                        </div>
                    </div>
                    <div class="card-footer"><a href="soc_assessment.php" class="btn btn-green btn-sm">به‌روزرسانی مرحله SOC</a></div>
                </div>
            </div>

            <!-- برنامه روزانه و داده بالینی -->
            <div class="grid-2">
                <div class="card">
                    <div class="card-header"><div><div class="card-title">برنامه امروز</div><div class="card-subtitle">روز <?= $consecutive_days ?> از برنامه</div></div><a href="daily_plan.php" class="btn btn-outline btn-sm">مشاهده کامل</a></div>
                    <div class="card-body" style="padding:0">
                        <?php foreach($today_tasks as $t) {
                            $bg = $t['done'] ? 'background:var(--green-light);' : '';
                            echo '<div style="display:flex;align-items:center;gap:14px;padding:14px 20px;border-bottom:1px solid var(--gray-100);'.$bg.'">';
                            echo '<span style="font-size:22px">📋</span>';
                            echo '<div style="flex:1"><div style="font-size:13px;font-weight:'.($t['done']?'700':'600').';color:'.($t['done']?'var(--green)':'var(--gray-900)').'">'.$t['title'].'</div>';
                            echo '<div style="font-size:11px;color:var(--gray-400)">برنامه روزانه</div></div>';
                            echo '<div style="width:22px;height:22px;border-radius:50%;border:2px solid '.($t['done']?'var(--green)':'var(--gray-300)').';background:'.($t['done']?'var(--green)':'none').';display:flex;align-items:center;justify-content:center;color:white;font-size:12px">'.($t['done']?'✓':'').'</div>';
                            echo '</div>';
                        } ?>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header"><div><div class="card-title">آخرین داده‌های بالینی</div><div class="card-subtitle">تأیید شده توسط پزشک</div></div><a href="clinical_data.php" class="btn btn-outline btn-sm">ورود داده</a></div>
                    <div class="card-body">
                        <div class="chart-container" style="height:180px"><canvas id="clinicalChart"></canvas></div>
                        <table class="data-table" style="margin-top:12px">
                            <tr><th>شاخص</th><th>مقدار</th><th>وضعیت</th></tr>
                            <tr><td>قند ناشتا (FBS)</td><td><strong><?= $clinical['fbs'] ?></strong> mg/dL</td><td><span style="color:var(--orange);font-weight:700">پیش‌دیابت</span></td></tr>
                            <tr><td>HbA1c</td><td><strong><?= $clinical['hba1c'] ?></strong>٪</td><td><span style="color:var(--orange);font-weight:700">پیش‌دیابت</span></td></tr>
                            <tr><td>BMI</td><td><strong><?= $clinical['bmi'] ?></strong></td><td><span style="color:var(--orange);font-weight:700"><?= $clinical['status_bmi'] ?></span></td></tr>
                            <tr><td>فشارخون</td><td><strong><?= $clinical['bp'] ?></strong></td><td><span style="color:var(--yellow);font-weight:700">مرزی</span></td></tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- مقایسه همتا -->
            <div class="card mb-20">
                <div class="card-header"><div><div class="card-title">مقایسه ناشناس با گروه همتا</div><div class="card-subtitle">افراد با مشخصات مشابه</div></div><a href="peer_compare.php" class="btn btn-outline btn-sm">مشاهده کامل</a></div>
                <div class="card-body" style="padding:0">
                    <?php foreach($peers as $p): ?>
                        <div class="peer-row" style="padding:14px 20px;<?= isset($p['me']) ? 'background:var(--green-light);' : '' ?>">
                            <div class="peer-avatar" style="background:<?= ($p['color']??'var(--green-light)') ?>;width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:20px;"><?= $p['avatar'] ?></div>
                            <div style="flex:1"><div class="peer-name" style="<?= isset($p['me']) ? 'color:var(--green)' : '' ?>"><?= $p['id'] ?></div><div class="peer-detail" style="font-size:12px;color:var(--gray-500);"><?= $p['detail'] ?></div></div>
                            <div style="text-align:left"><div style="font-size:13px;font-weight:700;color:var(--green)"><?= $p['progress'] ?></div><div style="font-size:11px;color:var(--gray-400)"><?= $p['days'] ?></div></div>
                            <div class="rank-badge" style="background:<?= ($p['rank']==1 ? '#F1C40F' : (isset($p['me']) ? 'var(--green)' : 'var(--gray-200)')) ?>;color:<?= ($p['rank']<3 || isset($p['me'])) ? 'white' : 'var(--gray-600)' ?>;padding:4px 12px;border-radius:99px;font-size:11px;font-weight:700;margin-right:8px">رتبه <?= $p['rank'] ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('now-date').textContent = new Date().toLocaleDateString('fa-IR',{weekday:'long',year:'numeric',month:'long',day:'numeric'});

const riskCtx = document.getElementById('riskTrendChart').getContext('2d');
new Chart(riskCtx, {
    type: 'line',
    data: {
        labels: ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور'],
        datasets: [{
            label: 'امتیاز ریسک',
            data: [18, 17, 16, 15.5, 14.5, <?= $risk_score ?: 14 ?>],
            borderColor: '#E74C3C',
            backgroundColor: 'rgba(231,76,60,0.08)',
            tension: 0.4,
            fill: true,
            pointBackgroundColor: '#E74C3C',
            pointRadius: 5,
        },{
            label: 'خط هشدار',
            data: [15, 15, 15, 15, 15, 15],
            borderColor: '#ADB5BD',
            borderDash: [6, 4],
            borderWidth: 1.5,
            pointRadius: 0,
            fill: false,
        }]
    },
    options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{display:false} }, scales:{ y:{min:8,max:22,ticks:{font:{family:'Vazirmatn',size:11}},grid:{color:'#F1F3F4'}}, x:{ticks:{font:{family:'Vazirmatn',size:11}},grid:{display:false}} } }
});

const clinCtx = document.getElementById('clinicalChart').getContext('2d');
new Chart(clinCtx, {
    type: 'bar',
    data: {
        labels: ['FBS','HbA1c×20','BMI×4','فشار/10'],
        datasets: [{
            data: [
                <?= is_numeric($clinical['fbs']) ? $clinical['fbs'] : 118 ?>,
                <?= is_numeric($clinical['hba1c']) ? $clinical['hba1c'] * 20 : 118 ?>,
                <?= is_numeric($clinical['bmi']) ? $clinical['bmi'] * 4 : 108.4 ?>,
                <?= is_numeric(explode('/',$clinical['bp'])[0] ?? 135) ? explode('/',$clinical['bp'])[0] ?? 135 : 135 ?>
            ],
            backgroundColor: ['rgba(230,126,34,0.7)','rgba(230,126,34,0.7)','rgba(241,196,15,0.7)','rgba(241,196,15,0.7)'],
            borderRadius: 6,
        }]
    },
    options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{display:false} }, scales:{ y:{min:80,ticks:{font:{family:'Vazirmatn',size:10}}}, x:{ticks:{font:{family:'Vazirmatn',size:11}},grid:{display:false}} } }
});
</script>
<script src="../assets/js/db.js"></script>
<script src="../assets/js/sync.js"></script>
<script src="../assets/js/notify.js"></script>
<script src="../assets/js/app.js"></script>
</body>
</html>