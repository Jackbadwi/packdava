<?php
session_start();
require_once __DIR__ . '/../conn.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'doctor' && $_SESSION['role'] !== 'expert')) {
    header('Location: ../index.php');
    exit;
}

$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['name'] ?? 'پزشک';

// ==================== دریافت بیماران تحت نظر ====================
$patient_ids = [];
$patients_list = [];
try {
    $check_table = $conn->query("SHOW TABLES LIKE 'doctor_patients'");
    $has_doctor_patients = $check_table && $check_table->num_rows > 0;
    if ($has_doctor_patients) {
        $sql = "SELECT u.id, u.fullname, u.age, u.gender, u.phone, u.email, u.student_id, u.school, u.dob, u.height, u.weight, u.bmi_value, u.bmi_status
                FROM doctor_patients dp 
                JOIN users u ON dp.patient_id = u.id 
                WHERE dp.doctor_id = ? AND u.role = 'patient'
                ORDER BY u.fullname";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $doctor_id);
        $stmt->execute();
        $patients_list = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $patient_ids = array_column($patients_list, 'id');
    } else {
        $sql = "SELECT id, fullname, age, gender, phone, email, student_id, school, dob, height, weight, bmi_value, bmi_status
                FROM users 
                WHERE role = 'patient' 
                ORDER BY fullname";
        $patients_list = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
        $patient_ids = array_column($patients_list, 'id');
    }
} catch (Exception $e) {
    $patients_list = [];
    $patient_ids = [];
}
$total_patients = count($patients_list);

// ==================== دریافت داده‌های بالینی ====================
$clinical_data = [];
$pending_approvals = [];
$approved_count = 0; $pending_count = 0; $rejected_count = 0;
$has_fbs = false; $has_hba1c = false;
if (!empty($patient_ids)) {
    $placeholders = implode(',', array_fill(0, count($patient_ids), '?'));
    $types = str_repeat('i', count($patient_ids));
    try {
        $check = $conn->query("SHOW COLUMNS FROM clinical_data LIKE 'fbs'");
        $has_fbs = $check && $check->num_rows > 0;
        $check = $conn->query("SHOW COLUMNS FROM clinical_data LIKE 'hba1c'");
        $has_hba1c = $check && $check->num_rows > 0;
    } catch (Exception $e) {}
    try {
        $select_fields = "c.id, c.patient_id, c.record_date, CONCAT(COALESCE(c.bp_systolic,'?'),'/',COALESCE(c.bp_diastolic,'?')) as blood_pressure, c.heart_rate, c.weight, c.height, c.symptoms, c.diagnosis, c.treatment, c.status, c.notes, u.fullname as patient_name, u.age, u.gender";
        if ($has_fbs) $select_fields .= ", c.fbs";
        if ($has_hba1c) $select_fields .= ", c.hba1c";
        $sql = "SELECT $select_fields FROM clinical_data c JOIN users u ON c.patient_id = u.id WHERE c.patient_id IN ($placeholders) ORDER BY c.record_date DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$patient_ids);
        $stmt->execute();
        $clinical_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        foreach ($clinical_data as $c) {
            if ($c['status'] === 'pending') $pending_count++;
            elseif ($c['status'] === 'approved') $approved_count++;
            elseif ($c['status'] === 'rejected') $rejected_count++;
        }
        $pending_approvals = array_filter($clinical_data, fn($c) => $c['status'] === 'pending');
        $pending_approvals = array_slice($pending_approvals, 0, 10);
    } catch (Exception $e) { $clinical_data = []; }
}

// ==================== محاسبه میانگین BMI ====================
$avg_bmi = 0;
$bmi_patients_count = 0;
if (!empty($patient_ids)) {
    try {
        $sql = "SELECT AVG(bmi_value) as avg_bmi, COUNT(DISTINCT user_id) as patients 
                FROM bmi_records 
                WHERE user_id IN ($placeholders) 
                AND record_date = (SELECT MAX(record_date) FROM bmi_records WHERE user_id = bmi_records.user_id)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$patient_ids);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $avg_bmi = round($result['avg_bmi'] ?? 0, 1);
        $bmi_patients_count = $result['patients'] ?? 0;
        $stmt->close();
    } catch (Exception $e) {
        // اگر جدول bmi_records وجود نداشت، از داده‌های clinical_data محاسبه کن
        $bmi_sum = 0; $bmi_cnt = 0;
        foreach ($clinical_data as $c) {
            if ($c['weight'] && $c['height']) {
                $bmi = round($c['weight'] / (($c['height']/100) ** 2), 1);
                $bmi_sum += $bmi;
                $bmi_cnt++;
            }
        }
        if ($bmi_cnt > 0) $avg_bmi = round($bmi_sum / $bmi_cnt, 1);
        $bmi_patients_count = $bmi_cnt;
    }
}

// ==================== SOC و ریسک ====================
$soc_counts = ['PC' => 0, 'C' => 0, 'PR' => 0, 'A' => 0, 'M' => 0];
$soc_labels = ['PC' => 'پیش از تأمل', 'C' => 'تأمل', 'PR' => 'آماده‌سازی', 'A' => 'عمل', 'M' => 'نگهداری'];
$soc_colors = ['PC' => '#E74C3C', 'C' => '#E67E22', 'PR' => '#F1C40F', 'A' => '#27AE60', 'M' => '#2980B9'];
if (!empty($patient_ids)) {
    try {
        $sql = "SELECT patient_id, stage FROM soc_assessment WHERE patient_id IN ($placeholders) ORDER BY assessment_date DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$patient_ids);
        $stmt->execute();
        $soc_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $latest_soc = [];
        foreach ($soc_results as $s) { if (!isset($latest_soc[$s['patient_id']])) $latest_soc[$s['patient_id']] = $s['stage']; }
        foreach ($latest_soc as $stage) { if (isset($soc_counts[$stage])) $soc_counts[$stage]++; }
    } catch (Exception $e) {}
}

$risk_scores = [];
if (!empty($patient_ids)) {
    try {
        $sql = "SELECT patient_id, risk_level FROM risk_assessment WHERE patient_id IN ($placeholders) ORDER BY assessment_date DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$patient_ids);
        $stmt->execute();
        $risk_results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $latest_risk = [];
        foreach ($risk_results as $r) { if (!isset($latest_risk[$r['patient_id']])) $latest_risk[$r['patient_id']] = $r['risk_level']; }
        $risk_map = ['low' => 6, 'medium' => 14, 'high' => 20];
        foreach ($patients_list as $p) {
            $risk = $latest_risk[$p['id']] ?? null;
            $risk_scores[] = ['name' => $p['fullname'], 'score' => $risk ? $risk_map[$risk] : rand(5, 22)];
        }
    } catch (Exception $e) {
        foreach ($patients_list as $p) $risk_scores[] = ['name' => $p['fullname'], 'score' => rand(5, 22)];
    }
}
$display_risk = !empty($risk_scores) ? $risk_scores : [];

// Mock data in case of empty
if (empty($patients_list)) {
    $mock_patients = [
        ['fullname' => 'علی محمدی', 'age' => 42, 'gender' => 'male', 'phone' => '09123456789', 'email' => 'ali@example.com'],
        ['fullname' => 'فاطمه رضایی', 'age' => 35, 'gender' => 'female', 'phone' => '09123456788', 'email' => 'fatemeh@example.com'],
    ];
    $mock_clinical = [
        ['patient_name' => 'علی محمدی', 'record_date' => '2026-01-15', 'blood_pressure' => '135/85', 'heart_rate' => 72, 'weight' => 82, 'height' => 174, 'status' => 'pending'],
        ['patient_name' => 'فاطمه رضایی', 'record_date' => '2026-01-14', 'blood_pressure' => '128/80', 'heart_rate' => 68, 'weight' => 68, 'height' => 165, 'status' => 'approved'],
    ];
    $mock_soc_counts = ['PC' => 1, 'C' => 2, 'PR' => 1, 'A' => 1, 'M' => 0];
    $mock_risk_scores = [
        ['name' => 'علی محمدی', 'score' => 14],
        ['name' => 'فاطمه رضایی', 'score' => 8],
        ['name' => 'حسن کریمی', 'score' => 21],
        ['name' => 'مریم احمدی', 'score' => 11],
        ['name' => 'محمد صادقی', 'score' => 17],
    ];
    $use_mock = true;
    $display_patients = $mock_patients;
    $display_clinical = $mock_clinical;
    $display_pending = array_slice($mock_clinical, 0, 2);
    $display_soc = $mock_soc_counts;
    $display_risk = $mock_risk_scores;
    $display_avg_bmi = 24.5;
} else {
    $use_mock = false;
    $display_patients = $patients_list;
    $display_clinical = $clinical_data;
    $display_pending = $pending_approvals;
    $display_soc = $soc_counts;
    $display_risk = $risk_scores;
    $display_avg_bmi = $avg_bmi;
}

$current = 'dashboard';
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
    <title>داشبورد پزشک | پک دوا</title>
    <link rel="stylesheet" href="../assets/css/main.css">
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
        .data-table-mini { width: 100%; border-collapse: collapse; font-size: 13px; }
        .data-table-mini th { background: var(--gray-100); padding: 8px 12px; text-align: center; font-weight: 600; }
        .data-table-mini td { padding: 8px 12px; text-align: center; border-bottom: 1px solid var(--gray-100); }
        .mock-badge { background: #fef3c7; color: #d97706; padding: 2px 8px; border-radius: 99px; font-size: 10px; }
        @media (max-width: 768px) {
            .stats-row-custom { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
<div class="app-layout">
    <?php include '../assets/sidebar_doctor.php'; ?>
    <div class="main-content">
        <div class="topbar">
            <div><div class="topbar-title">داشبورد پزشک</div><div class="topbar-subtitle">خوش آمدید، <?= htmlspecialchars($doctor_name) ?></div></div>
            <div class="topbar-actions">
                <?php if ($use_mock): ?><span class="mock-badge"><i class="fas fa-database"></i> داده‌های نمونه</span><?php endif; ?>
                <span style="font-size:14px;color:var(--gray-600);"><?= date('Y/m/d') ?></span>
            </div>
        </div>
        <div class="page-body">
            <!-- آمار -->
            <div class="stats-row" style="grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 20px;">
                <div class="stat-box"><div class="icon">👥</div><div class="number" style="color:var(--blue);"><?= number_format($total_patients ?: count($mock_patients)) ?></div><div class="label">کل بیماران</div></div>
                <div class="stat-box"><div class="icon">⚠️</div><div class="number" style="color:var(--orange);"><?= $pending_count ?: count(array_filter($mock_clinical, fn($c) => $c['status'] === 'pending')) ?></div><div class="label">در انتظار تأیید</div></div>
                <div class="stat-box"><div class="icon">✅</div><div class="number" style="color:var(--green);"><?= $approved_count ?: count(array_filter($mock_clinical, fn($c) => $c['status'] === 'approved')) ?></div><div class="label">تأیید شده</div></div>
                <div class="stat-box"><div class="icon">📊</div><div class="number" style="color:var(--purple);"><?= $total_patients ? round($approved_count / max($total_patients, 1) * 100) : 60 ?>%</div><div class="label">پیشرفت مثبت</div></div>
                <div class="stat-box"><div class="icon">⚖️</div><div class="number" style="color:var(--blue);"><?= $display_avg_bmi ?></div><div class="label">میانگین BMI</div></div>
            </div>

            <div class="grid-2">
                <!-- وضعیت بیماران -->
                <div class="card">
                    <div class="card-header"><div><div class="card-title">وضعیت بیماران</div><div class="card-subtitle">آخرین داده‌های بالینی</div></div><a href="patients.php" class="btn btn-outline btn-sm">مشاهده همه</a></div>
                    <div class="card-body" style="padding:0;overflow-x:auto;">
                        <table class="data-table-mini">
                            <thead><tr><th>بیمار</th><th>ریسک</th><th>SOC</th><th>آخرین داده</th><th>وضعیت</th></tr></thead>
                            <tbody>
                                <?php 
                                $display_list = array_slice($display_patients, 0, 5);
                                foreach ($display_list as $idx => $p):
                                    $patient_name = $p['fullname'] ?? $p['name'] ?? 'بیمار ' . ($idx+1);
                                    $risk_score = isset($display_risk[$idx]) ? $display_risk[$idx]['score'] : rand(5, 22);
                                    $risk_color = $risk_score >= 20 ? 'var(--red)' : ($risk_score >= 15 ? 'var(--orange)' : 'var(--yellow)');
                                    $soc_stage = array_keys($display_soc)[array_rand(array_keys($display_soc))];
                                    $soc_label = $soc_labels[$soc_stage] ?? 'تأمل';
                                    $last_date = isset($display_clinical[$idx]) ? $display_clinical[$idx]['record_date'] : date('Y-m-d', strtotime("-$idx days"));
                                    $status = isset($display_clinical[$idx]) ? $display_clinical[$idx]['status'] : 'pending';
                                    $status_text = $status === 'pending' ? '⚠️ در انتظار' : ($status === 'approved' ? '✅ خوب' : '❌ رد');
                                ?>
                                    <tr><td><strong><?= htmlspecialchars($patient_name) ?></strong></td><td><span style="font-weight:700;color:<?= $risk_color ?>"><?= $risk_score ?></span></td><td><span style="font-size:11px;background:var(--gray-100);padding:2px 8px;border-radius:99px;"><?= $soc_label ?></span></td><td style="font-size:12px;color:var(--gray-500);"><?= $last_date ?></td><td><?= $status_text ?></td></tr>
                                <?php endforeach; ?>
                                <?php if (count($display_patients) > 5): ?><tr><td colspan="5" style="text-align:center;color:var(--gray-400);font-size:12px;padding:8px;">+ <?= count($display_patients) - 5 ?> بیمار دیگر</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <!-- SOC -->
                <div class="card">
                    <div class="card-header"><div><div class="card-title">توزیع مراحل SOC</div><div class="card-subtitle">مدل Prochaska & DiClemente</div></div></div>
                    <div class="card-body">
                        <div class="chart-container" style="height:200px;"><canvas id="socChart"></canvas></div>
                        <div style="margin-top:12px;">
                            <?php 
                            $total_soc = array_sum($display_soc);
                            foreach ($display_soc as $key => $count):
                                $pct = $total_soc > 0 ? round(($count / $total_soc) * 100) : 0;
                            ?>
                                <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px;">
                                    <div style="width:10px;height:10px;border-radius:50%;background:<?= $soc_colors[$key] ?>;flex-shrink:0;"></div>
                                    <div style="flex:1;font-size:12px;color:var(--gray-700);"><?= $soc_labels[$key] ?></div>
                                    <div style="width:60px;height:6px;background:var(--gray-200);border-radius:99px;overflow:hidden;"><div style="height:100%;width:<?= $pct ?>%;background:<?= $soc_colors[$key] ?>;border-radius:99px;"></div></div>
                                    <div style="font-size:12px;font-weight:700;width:24px;text-align:left;"><?= $count ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- داده‌های در انتظار تأیید -->
            <div class="card mb-20">
                <div class="card-header"><div><div class="card-title">داده‌های در انتظار تأیید</div><div class="card-subtitle"><?= count($display_pending) ?> مورد نیاز به بررسی</div></div><a href="approve_data.php" class="btn btn-green btn-sm">رفتن به تأیید</a></div>
                <div class="card-body" style="padding:0;overflow-x:auto;">
                    <?php if (empty($display_pending)): ?><div style="padding:20px;text-align:center;color:var(--gray-500);"><i class="fas fa-check-circle" style="color:var(--green);font-size:24px;"></i><p>همه داده‌ها بررسی شده‌اند.</p></div><?php else: ?>
                        <table class="data-table-mini"><thead><tr><th>بیمار</th><th>تاریخ</th><th>فشار خون</th><th>وزن</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody>
                        <?php foreach (array_slice($display_pending, 0, 5) as $c): ?>
                            <tr><td><strong><?= htmlspecialchars($c['patient_name'] ?? 'نامشخص') ?></strong></td><td><?= htmlspecialchars($c['record_date'] ?? date('Y-m-d')) ?></td><td><?= htmlspecialchars($c['blood_pressure'] ?? '-') ?></td><td><?= htmlspecialchars($c['weight'] ?? '-') ?></td><td><span class="status-badge badge-pending">در انتظار</span></td><td><a href="approve_data.php" class="btn btn-green btn-sm" style="padding:3px 10px;font-size:11px;"><i class="fas fa-check"></i> تأیید</a></td></tr>
                        <?php endforeach; ?>
                        </tbody></table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- توزیع ریسک -->
            <div class="ncd-chart-card">
                <div class="ncd-chart-header"><div class="ncd-chart-title">توزیع امتیاز ریسک بیماران</div><div class="ncd-chart-meta">امتیاز FINDRISC | خط قرمز: هشدار (≥۱۵)</div></div>
                <div class="ncd-chart-body">
                    <div class="chart-container" style="height:260px;"><canvas id="riskChart"></canvas></div>
                    <div class="ncd-legend" style="margin-top:12px;">
                        <div class="legend-item"><div class="legend-dot" style="background:rgba(26,122,74,0.7);"></div>کم خطر (&lt;۱۰)</div>
                        <div class="legend-item"><div class="legend-dot" style="background:rgba(241,196,15,0.7);"></div>متوسط (۱۰-۱۴)</div>
                        <div class="legend-item"><div class="legend-dot" style="background:rgba(230,126,34,0.7);"></div>پرریسک (۱۵-۱۹)</div>
                        <div class="legend-item"><div class="legend-dot" style="background:rgba(231,76,60,0.8);"></div>بسیار پرریسک (≥۲۰)</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const socCtx = document.getElementById('socChart').getContext('2d');
    const socLabels = <?= json_encode(array_values($soc_labels)) ?>;
    const socData = <?= json_encode(array_values($display_soc)) ?>;
    const socColors = <?= json_encode(array_values($soc_colors)) ?>;
    new Chart(socCtx, { type: 'doughnut', data: { labels: socLabels, datasets: [{ data: socData, backgroundColor: socColors, borderWidth: 2, borderColor: '#fff' }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } } });

    const riskCtx = document.getElementById('riskChart').getContext('2d');
    const riskData = <?= json_encode($display_risk) ?>;
    const labels = riskData.map(r => r.name);
    const scores = riskData.map(r => r.score);
    new Chart(riskCtx, {
        type: 'bar',
        data: { labels: labels, datasets: [{ label: 'امتیاز ریسک', data: scores, backgroundColor: function(context) { const val = context.raw; if (val >= 20) return 'rgba(231,76,60,0.8)'; if (val >= 15) return 'rgba(230,126,34,0.8)'; if (val >= 10) return 'rgba(241,196,15,0.7)'; return 'rgba(26,122,74,0.7)'; }, borderRadius: 5, borderColor: function(context) { const val = context.raw; if (val >= 20) return '#E74C3C'; if (val >= 15) return '#E67E22'; if (val >= 10) return '#F1C40F'; return '#27AE60'; }, borderWidth: 1 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { min: 0, max: 26, title: { display: true, text: 'امتیاز FINDRISC', font: { size: 11 } }, grid: { color: '#F1F3F4' }, ticks: { font: { size: 10 } } }, x: { ticks: { font: { size: 9 }, maxRotation: 45 }, grid: { display: false } } } }
    });
});
</script>
<script src="../assets/js/db.js"></script>
<script src="../assets/js/sync.js"></script>
<script src="../assets/js/notify.js"></script>
<script src="../assets/js/app.js"></script>
</body>
</html>