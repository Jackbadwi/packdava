<?php
session_start();
require_once __DIR__ . '/../conn.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'doctor' && $_SESSION['role'] !== 'expert')) {
    header('Location: ../index.php');
    exit;
}

$doctor_id = $_SESSION['user_id'];

// ==================== دریافت بیماران تحت نظر ====================
$patients = [];
try {
    $sql = "SELECT u.id, u.fullname, u.age, u.gender, u.phone, u.email, u.student_id, u.school, u.dob, u.height, u.weight, u.bmi_value, u.bmi_status
            FROM doctor_patients dp 
            JOIN users u ON dp.patient_id = u.id 
            WHERE dp.doctor_id = ? AND u.role = 'patient'
            ORDER BY u.fullname";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $patients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    try {
        $sql = "SELECT id, fullname, age, gender, phone, email, student_id, school, dob, height, weight, bmi_value, bmi_status 
                FROM users 
                WHERE role = 'patient' 
                ORDER BY fullname";
        $patients = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e2) { $patients = []; }
}
$patient_ids = array_column($patients, 'id');

// ==================== دریافت داده‌های بالینی ====================
$clinical_data = [];
if (!empty($patient_ids)) {
    $placeholders = implode(',', array_fill(0, count($patient_ids), '?'));
    try {
        $has_fbs = false; $has_hba1c = false;
        try {
            $check = $conn->query("SHOW COLUMNS FROM clinical_data LIKE 'fbs'");
            $has_fbs = $check && $check->num_rows > 0;
            $check = $conn->query("SHOW COLUMNS FROM clinical_data LIKE 'hba1c'");
            $has_hba1c = $check && $check->num_rows > 0;
        } catch (Exception $e) {}

        $select_fields = "c.id, c.patient_id, c.record_date, CONCAT(COALESCE(c.bp_systolic,'?'),'/',COALESCE(c.bp_diastolic,'?')) as blood_pressure, c.heart_rate, 
                          c.weight, c.height, c.symptoms, c.diagnosis, c.treatment, c.status, c.notes";
        if ($has_fbs) $select_fields .= ", c.fbs";
        if ($has_hba1c) $select_fields .= ", c.hba1c";

        $sql = "SELECT $select_fields, u.fullname as patient_name, u.age, u.gender, u.school
                FROM clinical_data c 
                JOIN users u ON c.patient_id = u.id 
                WHERE c.patient_id IN ($placeholders) 
                ORDER BY c.record_date DESC";
        $stmt = $conn->prepare($sql);
        $types = str_repeat('i', count($patient_ids));
        $stmt->bind_param($types, ...$patient_ids);
        $stmt->execute();
        $clinical_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } catch (Exception $e) { $clinical_data = []; }
}

// ==================== گروه‌بندی داده‌ها ====================
$grouped_data = [];
foreach ($clinical_data as $row) {
    $pid = $row['patient_id'];
    if (!isset($grouped_data[$pid])) {
        $grouped_data[$pid] = [
            'patient_id' => $pid,
            'name' => $row['patient_name'],
            'age' => $row['age'],
            'gender' => $row['gender'],
            'school' => $row['school'] ?? '',
            'records' => []
        ];
    }
    $grouped_data[$pid]['records'][] = $row;
}
foreach ($patients as $p) {
    if (!isset($grouped_data[$p['id']])) {
        $grouped_data[$p['id']] = [
            'patient_id' => $p['id'],
            'name' => $p['fullname'],
            'age' => $p['age'],
            'gender' => $p['gender'],
            'school' => $p['school'] ?? '',
            'records' => []
        ];
    }
}

// ==================== آمار کلی ====================
$stats = [
    'total_patients' => count($patients),
    'total_records' => count($clinical_data),
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'avg_bmi' => 0,
    'avg_fbs' => 0,
    'avg_hba1c' => 0,
    'bmi_patients' => 0,
    'fbs_patients' => 0,
];
$bmi_sum = 0; $fbs_sum = 0; $hba1c_sum = 0;
foreach ($clinical_data as $c) {
    if ($c['status'] === 'pending') $stats['pending']++;
    elseif ($c['status'] === 'approved') $stats['approved']++;
    elseif ($c['status'] === 'rejected') $stats['rejected']++;
    if ($c['weight'] && $c['height']) {
        $bmi = round($c['weight'] / (($c['height']/100) ** 2), 1);
        $bmi_sum += $bmi;
        $stats['bmi_patients']++;
    }
    if (isset($c['fbs']) && $c['fbs']) {
        $fbs_sum += $c['fbs'];
        $stats['fbs_patients']++;
    }
    if (isset($c['hba1c']) && $c['hba1c']) $hba1c_sum += $c['hba1c'];
}
$stats['avg_bmi'] = $stats['bmi_patients'] > 0 ? round($bmi_sum / $stats['bmi_patients'], 1) : 0;
$stats['avg_fbs'] = $stats['fbs_patients'] > 0 ? round($fbs_sum / $stats['fbs_patients'], 1) : 0;
$stats['avg_hba1c'] = $stats['fbs_patients'] > 0 ? round($hba1c_sum / $stats['fbs_patients'], 1) : 0;

// ==================== توابع کمکی ====================
function getStatusBadge($status) {
    $map = [
        'pending' => ['class' => 'badge-pending', 'text' => 'در انتظار'],
        'approved' => ['class' => 'badge-approved', 'text' => 'تأیید شده'],
        'rejected' => ['class' => 'badge-rejected', 'text' => 'رد شده'],
    ];
    $s = $map[$status] ?? ['class' => 'badge-pending', 'text' => $status];
    return '<span class="status-badge ' . $s['class'] . '">' . $s['text'] . '</span>';
}
function getGenderText($gender) { if ($gender === 'male') return 'مرد'; if ($gender === 'female') return 'زن'; return ''; }
function calcBMI($weight, $height) { if ($weight && $height) return round($weight / (($height/100) ** 2), 1); return null; }

$current = 'reports';
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
    <title>گزارشات بالینی | پک دوا</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .stat-box { background: white; border-radius: 10px; padding: 16px 20px; text-align: center; border: 1px solid var(--gray-200); height: 100%; transition: all 0.2s; }
        .stat-box:hover { transform: translateY(-3px); box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
        .stat-box .number { font-size: 26px; font-weight: 800; }
        .stat-box .label { font-size: 12px; color: var(--gray-500); margin-top: 4px; }
        .stat-box .icon { font-size: 28px; margin-bottom: 6px; }
        .status-badge { padding: 2px 10px; border-radius: 99px; font-size: 11px; font-weight: 600; display: inline-block; }
        .badge-pending { background: #fef3c7; color: #d97706; }
        .badge-approved { background: #d1fae5; color: #059669; }
        .badge-rejected { background: #fee2e2; color: #dc2626; }
        .report-card { border-right: 3px solid var(--blue); margin-bottom: 16px; transition: all 0.2s; }
        .report-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
        .report-card .header { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; cursor: pointer; background: var(--gray-50); border-radius: 8px 8px 0 0; }
        .report-card .header:hover { background: var(--gray-100); }
        .report-card .header .name { font-weight: 700; font-size: 15px; }
        .report-card .body { padding: 16px; overflow-x: auto; display: none; background: white; border-radius: 0 0 8px 8px; }
        .report-card .body.open { display: block; }
        .report-card .body .latest-info { display: flex; gap: 20px; flex-wrap: wrap; padding: 10px 14px; background: var(--gray-50); border-radius: 8px; margin-bottom: 12px; }
        .report-card .body .latest-info .item { font-size: 13px; }
        .report-card .body .latest-info .item strong { color: var(--gray-700); }
        .chart-container { height: 200px; margin-top: 12px; }
        .filter-section { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; padding: 12px 16px; background: var(--gray-50); border-radius: 8px; margin-bottom: 16px; }
        .filter-section select, .filter-section input { padding: 6px 12px; border: 1px solid var(--gray-300); border-radius: 6px; font-size: 13px; }
        .filter-section input { flex: 1; min-width: 150px; }
        .export-btn { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; border: none; transition: all 0.2s; }
        .export-btn:hover { opacity: 0.85; transform: translateY(-1px); }
        .export-btn.excel { background: #217346; color: white; }
        .export-btn.pdf { background: #d32f2f; color: white; }
        .export-btn.print { background: var(--gray-600); color: white; }
        .data-table-mini { font-size: 12px; width: 100%; border-collapse: collapse; }
        .data-table-mini th { background: var(--gray-100); font-weight: 600; padding: 6px 10px; text-align: center; }
        .data-table-mini td { padding: 6px 10px; text-align: center; border-bottom: 1px solid var(--gray-100); }
        .no-data { text-align: center; padding: 40px; color: var(--gray-500); }
        .no-data i { font-size: 48px; color: var(--gray-300); margin-bottom: 16px; display: block; }
        .patient-count-badge { background: var(--blue); color: white; padding: 1px 8px; border-radius: 99px; font-size: 11px; margin-right: 8px; }
        .chevron-icon { transition: transform 0.3s; }
        .chevron-icon.open { transform: rotate(180deg); }
        .stats-row-custom { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 20px; }
        @media (max-width: 768px) {
            .stats-row-custom { grid-template-columns: repeat(2, 1fr); }
            .filter-section { flex-direction: column; align-items: stretch; }
            .filter-section input { min-width: auto; }
            .report-card .header { flex-wrap: wrap; gap: 8px; }
            .report-card .header .name { font-size: 14px; }
            .report-card .body .latest-info { flex-direction: column; gap: 6px; }
        }
    </style>
</head>
<body>
<div class="app-layout">
    <?php include '../assets/sidebar_doctor.php'; ?>
    <div class="main-content">
        <div class="topbar">
            <div><div class="topbar-title">گزارشات بالینی</div><div class="topbar-subtitle">مشاهده و تحلیل داده‌های بالینی بیماران تحت نظر</div></div>
            <div class="topbar-actions">
                <button class="export-btn excel" onclick="exportExcel()"><i class="fas fa-file-excel"></i> خروجی اکسل</button>
                <button class="export-btn print" onclick="window.print()"><i class="fas fa-print"></i> چاپ</button>
            </div>
        </div>
        <div class="page-body">
            <!-- ==================== آمار ==================== -->
            <div class="stats-row-custom">
                <div class="stat-box"><div class="icon">👥</div><div class="number" style="color:var(--blue);"><?= number_format($stats['total_patients']) ?></div><div class="label">تعداد بیماران</div></div>
                <div class="stat-box"><div class="icon">📋</div><div class="number" style="color:var(--gray-700);"><?= number_format($stats['total_records']) ?></div><div class="label">کل داده‌های بالینی</div></div>
                <div class="stat-box" style="border-right-color:var(--orange);"><div class="icon">⏳</div><div class="number" style="color:var(--orange);"><?= number_format($stats['pending']) ?></div><div class="label">در انتظار تأیید</div></div>
                <div class="stat-box" style="border-right-color:var(--green);"><div class="icon">✅</div><div class="number" style="color:var(--green);"><?= number_format($stats['approved']) ?></div><div class="label">تأیید شده</div></div>
                <?php if ($stats['avg_bmi'] > 0): ?><div class="stat-box" style="border-right-color:var(--purple);"><div class="icon">📊</div><div class="number" style="color:var(--purple);"><?= $stats['avg_bmi'] ?></div><div class="label">میانگین BMI</div></div><?php endif; ?>
                <?php if ($stats['avg_fbs'] > 0): ?><div class="stat-box" style="border-right-color:var(--red);"><div class="icon">🩸</div><div class="number" style="color:var(--red);"><?= $stats['avg_fbs'] ?></div><div class="label">میانگین FBS</div></div><?php endif; ?>
            </div>

            <!-- ==================== فیلترها ==================== -->
            <div class="filter-section">
                <label style="font-weight:600;font-size:13px;"><i class="fas fa-search"></i> جستجو:</label>
                <input type="text" id="searchInput" placeholder="نام بیمار..." onkeyup="applyFilters()">
                <select id="statusFilter" onchange="applyFilters()">
                    <option value="">همه وضعیت‌ها</option>
                    <option value="pending">در انتظار</option>
                    <option value="approved">تأیید شده</option>
                    <option value="rejected">رد شده</option>
                </select>
                <select id="genderFilter" onchange="applyFilters()">
                    <option value="">همه جنسیت</option>
                    <option value="male">مرد</option>
                    <option value="female">زن</option>
                </select>
                <button class="btn btn-outline btn-sm" onclick="resetFilters()"><i class="fas fa-times"></i> پاک کردن</button>
                <span style="font-size:12px;color:var(--gray-400);margin-right:auto;" id="resultCount"><?= count($grouped_data) ?> بیمار</span>
            </div>

            <!-- ==================== گزارشات ==================== -->
            <?php if (empty($grouped_data)): ?>
                <div class="card no-data"><i class="fas fa-chart-bar"></i><h4>هیچ داده‌ای برای نمایش وجود ندارد</h4><p style="color:var(--gray-500);">برای بیماران تحت نظر خود داده بالینی ثبت کنید.</p><a href="enter_clinical.php" class="btn btn-primary"><i class="fas fa-plus"></i> ورود داده جدید</a></div>
            <?php else: ?>
                <?php foreach ($grouped_data as $pid => $data): ?>
                    <?php
                    $records = $data['records'];
                    $total = count($records);
                    $latest = $records[0] ?? null;
                    $pending_count = count(array_filter($records, fn($r) => $r['status'] === 'pending'));
                    $approved_count = count(array_filter($records, fn($r) => $r['status'] === 'approved'));
                    $rejected_count = count(array_filter($records, fn($r) => $r['status'] === 'rejected'));
                    $latest_bmi = $latest ? calcBMI($latest['weight'], $latest['height']) : null;
                    $has_chart_data = count($records) >= 2;
                    // دریافت آخرین BMI از bmi_records
                    $latest_bmi_record = null;
                    try {
                        $stmt_bmi = $conn->prepare("SELECT bmi_value, bmi_status, record_date FROM bmi_records WHERE user_id = ? ORDER BY record_date DESC LIMIT 1");
                        $stmt_bmi->bind_param("i", $pid);
                        $stmt_bmi->execute();
                        $latest_bmi_record = $stmt_bmi->get_result()->fetch_assoc();
                        $stmt_bmi->close();
                    } catch (Exception $e) {}
                    ?>
                    <div class="report-card card" data-patient="<?= $pid ?>" data-name="<?= htmlspecialchars(strtolower($data['name'])) ?>" data-gender="<?= $data['gender'] ?? '' ?>" data-status="<?= $latest ? $latest['status'] : '' ?>">
                        <div class="header" onclick="toggleReport(this)">
                            <div>
                                <span class="name"><?= htmlspecialchars($data['name']) ?></span>
                                <span style="font-size:12px;color:var(--gray-400);margin-right:8px;">
                                    <?= $data['age'] ?? '?' ?> سال | <?= getGenderText($data['gender']) ?>
                                    <?php if ($data['school']): ?> | <?= htmlspecialchars($data['school']) ?><?php endif; ?>
                                    | <?= $total ?> رکورد
                                </span>
                                <?php if ($pending_count > 0): ?><span class="status-badge badge-pending"><?= $pending_count ?> در انتظار</span><?php endif; ?>
                                <?php if ($approved_count > 0): ?><span class="status-badge badge-approved"><?= $approved_count ?> تأیید</span><?php endif; ?>
                                <?php if ($rejected_count > 0): ?><span class="status-badge badge-rejected"><?= $rejected_count ?> رد</span><?php endif; ?>
                            </div>
                            <span><i class="fas fa-chevron-down chevron-icon"></i></span>
                        </div>
                        <div class="body">
                            <?php if ($latest): ?>
                                <div class="latest-info">
                                    <div class="item"><strong>آخرین ثبت:</strong> <?= htmlspecialchars($latest['record_date']) ?></div>
                                    <?php if ($latest['blood_pressure']): ?><div class="item"><strong>فشار خون:</strong> <?= htmlspecialchars($latest['blood_pressure']) ?> mmHg</div><?php endif; ?>
                                    <?php if ($latest['heart_rate']): ?><div class="item"><strong>ضربان قلب:</strong> <?= htmlspecialchars($latest['heart_rate']) ?> bpm</div><?php endif; ?>
                                    <?php if ($latest['weight']): ?><div class="item"><strong>وزن:</strong> <?= htmlspecialchars($latest['weight']) ?> kg</div><?php endif; ?>
                                    <?php if ($latest_bmi): ?><div class="item"><strong>BMI:</strong> <?= $latest_bmi ?></div><?php endif; ?>
                                    <?php if (isset($latest['fbs']) && $latest['fbs']): ?><div class="item"><strong>FBS:</strong> <?= $latest['fbs'] ?> mg/dL</div><?php endif; ?>
                                    <?php if (isset($latest['hba1c']) && $latest['hba1c']): ?><div class="item"><strong>HbA1c:</strong> <?= $latest['hba1c'] ?>%</div><?php endif; ?>
                                    <div class="item"><strong>وضعیت:</strong> <?= getStatusBadge($latest['status']) ?></div>
                                </div>
                            <?php else: ?>
                                <div style="padding:12px;background:var(--gray-50);border-radius:8px;margin-bottom:12px;text-align:center;color:var(--gray-500);"><i class="fas fa-info-circle"></i> این بیمار هنوز داده بالینی ثبت‌شده‌ای ندارد.</div>
                            <?php endif; ?>

                            <?php if ($has_chart_data && $latest): ?>
                                <div class="chart-container"><canvas id="chart-<?= $pid ?>"></canvas></div>
                                <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    const records = <?= json_encode(array_reverse($records)) ?>;
                                    const labels = records.map(r => r.record_date);
                                    const datasets = [];
                                    const bpData = records.map(r => { if (r.blood_pressure) { const parts = r.blood_pressure.split('/'); return parts.length === 2 ? parseInt(parts[0]) : null; } return null; });
                                    const validBp = bpData.filter(v => v !== null);
                                    if (validBp.length >= 2) {
                                        datasets.push({ label: 'فشار سیستولیک', data: bpData, borderColor: '#e74c3c', backgroundColor: 'rgba(231,76,60,0.05)', tension: 0.3, pointRadius: 4, pointBackgroundColor: '#e74c3c', fill: true });
                                    }
                                    const weightData = records.map(r => r.weight);
                                    const validWeight = weightData.filter(v => v !== null && v > 0);
                                    if (validWeight.length >= 2) {
                                        datasets.push({ label: 'وزن (kg)', data: weightData, borderColor: '#3498db', backgroundColor: 'rgba(52,152,219,0.05)', tension: 0.3, pointRadius: 4, pointBackgroundColor: '#3498db', fill: true, yAxisID: 'y1' });
                                    }
                                    <?php if (isset($records[0]['fbs'])): ?>
                                    const fbsData = records.map(r => r.fbs);
                                    const validFbs = fbsData.filter(v => v !== null && v > 0);
                                    if (validFbs.length >= 2) {
                                        datasets.push({ label: 'FBS (mg/dL)', data: fbsData, borderColor: '#e67e22', backgroundColor: 'rgba(230,126,34,0.05)', tension: 0.3, pointRadius: 4, pointBackgroundColor: '#e67e22', fill: true, borderDash: [5,3] });
                                    }
                                    <?php endif; ?>
                                    if (datasets.length > 0) {
                                        new Chart(document.getElementById('chart-<?= $pid ?>').getContext('2d'), {
                                            type: 'line',
                                            data: { labels: labels, datasets: datasets },
                                            options: {
                                                responsive: true, maintainAspectRatio: false,
                                                plugins: { legend: { position: 'top', labels: { font: { size: 10 }, boxWidth: 12, padding: 8 } } },
                                                scales: {
                                                    y: { beginAtZero: false, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { font: { size: 9 } } },
                                                    y1: { position: 'right', beginAtZero: false, grid: { display: false }, ticks: { font: { size: 9 } } },
                                                    x: { ticks: { font: { size: 9 }, maxRotation: 45 } }
                                                }
                                            }
                                        });
                                    }
                                });
                                </script>
                            <?php endif; ?>

                            <?php if ($total > 0): ?>
                                <div style="margin-top:12px;overflow-x:auto;">
                                    <table class="data-table-mini">
                                        <thead><tr><th>تاریخ</th><th>فشار خون</th><th>ضربان</th><th>وزن</th><th>BMI</th><?php if (isset($records[0]['fbs'])): ?><th>FBS</th><?php endif; ?><?php if (isset($records[0]['hba1c'])): ?><th>HbA1c</th><?php endif; ?><th>وضعیت</th></tr></thead>
                                        <tbody>
                                            <?php foreach (array_slice($records, 0, 15) as $r): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($r['record_date']) ?></td>
                                                    <td><?= htmlspecialchars($r['blood_pressure'] ?? '-') ?></td>
                                                    <td><?= htmlspecialchars($r['heart_rate'] ?? '-') ?></td>
                                                    <td><?= htmlspecialchars($r['weight'] ?? '-') ?></td>
                                                    <td><?= calcBMI($r['weight'], $r['height']) ?? '-' ?></td>
                                                    <?php if (isset($r['fbs'])): ?><td><?= htmlspecialchars($r['fbs'] ?? '-') ?></td><?php endif; ?>
                                                    <?php if (isset($r['hba1c'])): ?><td><?= htmlspecialchars($r['hba1c'] ?? '-') ?></td><?php endif; ?>
                                                    <td><?= getStatusBadge($r['status']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <?php if ($total > 15): ?><tr><td colspan="10" style="text-align:center;color:var(--gray-400);font-size:11px;padding:8px;">+ <?= $total - 15 ?> رکورد دیگر</td></tr><?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>

                            <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap;">
                                <a href="enter_clinical.php?patient_id=<?= $pid ?>" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> ثبت داده جدید</a>
                                <a href="../user.php?doctor_access=1&target_user_id=<?= $pid ?>" class="btn btn-outline btn-sm"><i class="fas fa-user"></i> پروفایل بیمار</a>
                                <button class="btn btn-outline btn-sm" onclick="exportPatientExcel(<?= $pid ?>, '<?= htmlspecialchars($data['name']) ?>')"><i class="fas fa-file-excel"></i> خروجی اکسل</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleReport(el) {
    const body = el.nextElementSibling;
    body.classList.toggle('open');
    const icon = el.querySelector('.chevron-icon');
    if (body.classList.contains('open')) icon.classList.add('open'); else icon.classList.remove('open');
}
function applyFilters() {
    const search = document.getElementById('searchInput').value.toLowerCase().trim();
    const status = document.getElementById('statusFilter').value;
    const gender = document.getElementById('genderFilter').value;
    const cards = document.querySelectorAll('.report-card');
    let visibleCount = 0;
    cards.forEach(card => {
        const name = card.dataset.name || '';
        const cardGender = card.dataset.gender || '';
        const cardStatus = card.dataset.status || '';
        let show = true;
        if (search && !name.includes(search)) show = false;
        if (gender && cardGender !== gender) show = false;
        if (status && cardStatus !== status) show = false;
        card.style.display = show ? 'block' : 'none';
        if (show) visibleCount++;
    });
    document.getElementById('resultCount').textContent = visibleCount + ' بیمار';
}
function resetFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('statusFilter').value = '';
    document.getElementById('genderFilter').value = '';
    applyFilters();
}
function exportExcel() { window.location.href = 'export_reports.php?format=excel'; }
function exportPatientExcel(patientId, patientName) { window.location.href = 'export_reports.php?format=excel&patient_id=' + patientId; }
document.addEventListener('DOMContentLoaded', function() {
    const firstCard = document.querySelector('.report-card');
    if (firstCard) {
        const header = firstCard.querySelector('.header');
        if (header) toggleReport(header);
    }
});
</script>
<script src="../assets/js/db.js"></script>
<script src="../assets/js/sync.js"></script>
<script src="../assets/js/notify.js"></script>
<script src="../assets/js/app.js"></script>
</body>
</html>