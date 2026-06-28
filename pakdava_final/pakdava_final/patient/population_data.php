<?php
session_start();
require_once __DIR__ . '/../conn.php';
if (!isset($_SESSION['user_id'])) { header('Location: ../index.php'); exit; }

// دریافت داده‌های NCD-RisC از دیتابیس
$ncd_data = ['diabetes'=>[],'bmi'=>[],'sbp'=>[],'cholesterol'=>[]];
$s = $conn->prepare("SELECT indicator,year,sex,value,lower_95ci,upper_95ci,unit FROM ncd_risc_iran ORDER BY indicator,sex,year");
if ($s) {
    $s->execute();
    foreach ($s->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $ncd_data[$r['indicator']][$r['sex']][$r['year']] = $r;
    }
    $s->close();
}
// Fallback به داده‌های hardcoded اگر دیتابیس خالی بود
$db_years     = array_keys($ncd_data['diabetes']['male'] ?? []);
$use_db       = count($db_years) >= 4;
$years_diab   = $use_db ? $db_years : [1980,1985,1990,1995,2000,2005,2010,2014];
$men_prev     = $use_db ? array_values(array_map(fn($r)=>$r['value'],$ncd_data['diabetes']['male']))   : [5.03,5.40,5.80,6.51,7.39,8.70,10.19,11.39];
$women_prev   = $use_db ? array_values(array_map(fn($r)=>$r['value'],$ncd_data['diabetes']['female'])) : [6.02,6.35,6.76,7.52,8.48,9.87,11.51,12.86];
$bmi_m        = $use_db ? array_values(array_map(fn($r)=>$r['value'],array_filter($ncd_data['bmi']['male']??[])))   : [23.2,23.7,24.2,24.7,25.1,25.4,25.7];
$bmi_f        = $use_db ? array_values(array_map(fn($r)=>$r['value'],array_filter($ncd_data['bmi']['female']??[]))) : [25.1,26.0,27.1,28.1,29.0,29.7,30.1];
$sbp_m        = $use_db ? array_values(array_map(fn($r)=>$r['value'],array_filter($ncd_data['sbp']['male']??[])))   : [126.4,126.0,124.5,123.8];
$sbp_f        = $use_db ? array_values(array_map(fn($r)=>$r['value'],array_filter($ncd_data['sbp']['female']??[]))) : [121.3,122.7,122.1,120.9];
$chol_m       = $use_db ? array_values(array_map(fn($r)=>$r['value'],array_filter($ncd_data['cholesterol']['male']??[])))   : [195.2,198.4,193.1,189.6];
$chol_f       = $use_db ? array_values(array_map(fn($r)=>$r['value'],array_filter($ncd_data['cholesterol']['female']??[]))) : [202.8,207.5,204.3,199.7];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>داده‌های NCD-RisC ایران | پک دوا</title>
<link rel="manifest" href="../manifest.json"><meta name="theme-color" content="#1A7A4A">
<link rel="stylesheet" href="../assets/css/main.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
<div class="app-layout">
  <?php include '../assets/sidebar_patient.php'; ?>
  <div class="main-content">
    <div class="topbar">
      <div><div class="topbar-title">داده‌های جمعیتی NCD-RisC ایران</div>
      <div class="topbar-subtitle">دیابت | BMI | فشارخون | کلسترول — منبع: Lancet 2016/2017/2019, EJPC 2020</div></div>
      <span style="font-size:11px;color:var(--gray-500);margin:auto 0 auto auto"><?= $use_db?'📦 از دیتابیس':'📋 داده‌های hardcoded' ?></span>
    </div>
    <div class="page-body">

      <!-- Banner -->
      <div style="background:linear-gradient(135deg,var(--green-dark),var(--green));color:white;border-radius:var(--radius);padding:20px 24px;margin-bottom:20px;display:flex;gap:16px;align-items:center;flex-wrap:wrap">
        <div style="font-size:32px">🌍</div>
        <div style="flex:1">
          <div style="font-size:15px;font-weight:700">NCD Risk Factor Collaboration (NCD-RisC)</div>
          <div style="font-size:12px;opacity:0.9;margin-top:4px">
            ۴ مجموعه داده: دیابت (Lancet 2016) | BMI (Lancet 2017) | فشارخون (Lancet 2019) | کلسترول (EJPC 2020)<br>
            ایران ۱۹۸۰–۲۰۱۷ | مردان و زنان | با بازه اطمینان ۹۵٪
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
          <div style="background:rgba(255,255,255,0.15);border-radius:8px;padding:8px 12px;text-align:center"><div style="font-size:18px;font-weight:800">۱۲.۸٪</div><div style="font-size:10px;opacity:0.8">دیابت زنان ۲۰۱۴</div></div>
          <div style="background:rgba(255,255,255,0.15);border-radius:8px;padding:8px 12px;text-align:center"><div style="font-size:18px;font-weight:800">۳۰.۱</div><div style="font-size:10px;opacity:0.8">BMI زنان ۲۰۱۶</div></div>
          <div style="background:rgba(255,255,255,0.15);border-radius:8px;padding:8px 12px;text-align:center"><div style="font-size:18px;font-weight:800">۱۲۳.۸</div><div style="font-size:10px;opacity:0.8">SBP مردان ۲۰۱۵ mmHg</div></div>
          <div style="background:rgba(255,255,255,0.15);border-radius:8px;padding:8px 12px;text-align:center"><div style="font-size:18px;font-weight:800">۱۸۹.۶</div><div style="font-size:10px;opacity:0.8">کلسترول مردان ۲۰۱۷</div></div>
        </div>
      </div>

      <!-- Stats -->
      <div class="stats-row" style="grid-template-columns:repeat(4,1fr)">
        <div class="stat-card"><div class="stat-card-top"><div class="stat-icon red">📈</div><span class="stat-trend up">+۱۲۶٪</span></div><div class="stat-value text-orange">۱۱.۴٪</div><div class="stat-label">شیوع دیابت مردان ۲۰۱۴</div><div class="stat-sub">از ۵.۰٪ در ۱۹۸۰</div></div>
        <div class="stat-card"><div class="stat-card-top"><div class="stat-icon orange">📈</div><span class="stat-trend up">+۱۵۶٪</span></div><div class="stat-value text-red">۱۲.۸٪</div><div class="stat-label">شیوع دیابت زنان ۲۰۱۴</div><div class="stat-sub">از ۶.۰٪ در ۱۹۸۰</div></div>
        <div class="stat-card"><div class="stat-card-top"><div class="stat-icon blue">💉</div><span class="stat-trend down">↓ بهبود</span></div><div class="stat-value" style="color:var(--blue)">۱۲۳.۸</div><div class="stat-label">SBP مردان ۲۰۱۵ (mmHg)</div><div class="stat-sub">از ۱۲۶.۴ در ۱۹۹۰</div></div>
        <div class="stat-card"><div class="stat-card-top"><div class="stat-icon green">🧪</div><span class="stat-trend down">↓ کاهش</span></div><div class="stat-value text-green">۱۸۹.۶</div><div class="stat-label">کلسترول مردان ۲۰۱۷</div><div class="stat-sub">از ۱۹۸.۴ در ۲۰۰۰</div></div>
      </div>

      <!-- نمودار دیابت -->
      <div class="ncd-chart-card mb-20">
        <div class="ncd-chart-header"><div class="ncd-chart-title">روند شیوع دیابت ایران ۱۹۸۰–۲۰۱۴</div><div class="ncd-chart-meta">Age-standardised diabetes prevalence (%) | NCD-RisC Lancet 2016</div></div>
        <div class="ncd-chart-body">
          <div class="chart-container" style="height:280px"><canvas id="diabChart"></canvas></div>
          <div class="ncd-legend"><div class="legend-item"><div class="legend-dot" style="background:#2980B9"></div>مردان</div><div class="legend-item"><div class="legend-dot" style="background:#E74C3C"></div>زنان</div></div>
        </div>
      </div>

      <div class="grid-2">
        <!-- BMI -->
        <div class="ncd-chart-card">
          <div class="ncd-chart-header"><div class="ncd-chart-title">روند میانگین BMI ایران</div><div class="ncd-chart-meta">kg/m² | NCD-RisC Lancet 2017</div></div>
          <div class="ncd-chart-body">
            <div class="chart-container" style="height:220px"><canvas id="bmiChart"></canvas></div>
            <div class="ncd-legend"><div class="legend-item"><div class="legend-dot" style="background:#27AE60"></div>مردان</div><div class="legend-item"><div class="legend-dot" style="background:#8E44AD"></div>زنان</div></div>
          </div>
        </div>
        <!-- فشارخون و کلسترول -->
        <div class="ncd-chart-card">
          <div class="ncd-chart-header"><div class="ncd-chart-title">فشارخون سیستولیک و کلسترول</div><div class="ncd-chart-meta">SBP mmHg (Lancet 2019) | کلسترول mg/dL (EJPC 2020)</div></div>
          <div class="ncd-chart-body">
            <div class="chart-container" style="height:220px"><canvas id="bpCholChart"></canvas></div>
            <div class="ncd-legend">
              <div class="legend-item"><div class="legend-dot" style="background:#E74C3C"></div>SBP مردان</div>
              <div class="legend-item"><div class="legend-dot" style="background:#2980B9"></div>SBP زنان</div>
              <div class="legend-item"><div class="legend-dot" style="background:#F1C40F"></div>کلسترول مردان</div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
<script>
Chart.defaults.font.family = 'Vazirmatn, Tahoma';
const dyears = <?= json_encode($years_diab) ?>;
const menP   = <?= json_encode($men_prev) ?>;
const womenP = <?= json_encode($women_prev) ?>;
const bmiYrs = <?= json_encode([1985,1990,1995,2000,2005,2010,2016]) ?>;
const bmiM   = <?= json_encode($bmi_m) ?>;
const bmiF   = <?= json_encode($bmi_f) ?>;
const bpYrs  = <?= json_encode([1990,2000,2010,2015]) ?>;
const sbpM   = <?= json_encode($sbp_m) ?>;
const sbpF   = <?= json_encode($sbp_f) ?>;
const cholM  = <?= json_encode($chol_m) ?>;

new Chart(document.getElementById('diabChart'),{type:'line',data:{labels:dyears,datasets:[
  {label:'مردان',data:menP,borderColor:'#2980B9',backgroundColor:'rgba(41,128,185,0.08)',fill:true,tension:0.4,pointRadius:5},
  {label:'زنان', data:womenP,borderColor:'#E74C3C',backgroundColor:'rgba(231,76,60,0.08)',fill:true,tension:0.4,pointRadius:5}
]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},
  scales:{y:{title:{display:true,text:'شیوع (٪)'},ticks:{font:{size:10}}},x:{ticks:{font:{size:10}},grid:{display:false}}}}});

new Chart(document.getElementById('bmiChart'),{type:'line',data:{labels:bmiYrs,datasets:[
  {label:'مردان',data:bmiM,borderColor:'#27AE60',tension:0.4,pointRadius:4,fill:false},
  {label:'زنان', data:bmiF,borderColor:'#8E44AD',tension:0.4,pointRadius:4,fill:false}
]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},
  scales:{y:{min:20,max:35,title:{display:true,text:'BMI kg/m²'},ticks:{font:{size:10}}},x:{ticks:{font:{size:10}},grid:{display:false}}}}});

new Chart(document.getElementById('bpCholChart'),{type:'line',data:{labels:bpYrs,datasets:[
  {label:'SBP مردان',data:sbpM,borderColor:'#E74C3C',tension:0.4,pointRadius:5,yAxisID:'y'},
  {label:'SBP زنان', data:sbpF,borderColor:'#2980B9',tension:0.4,pointRadius:5,yAxisID:'y'},
  {label:'کلسترول مردان',data:cholM,borderColor:'#F1C40F',tension:0.4,pointRadius:5,borderDash:[4,3],yAxisID:'y2'},
]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},
  scales:{
    y:{title:{display:true,text:'SBP (mmHg)'},min:110,ticks:{font:{size:9}}},
    y2:{position:'left',title:{display:true,text:'کلسترول (mg/dL)'},min:170,ticks:{font:{size:9}}}
  }}});
</script>
<script src="../assets/js/db.js"></script>
<script src="../assets/js/sync.js"></script>
<script src="../assets/js/app.js"></script>
</body>
</html>
