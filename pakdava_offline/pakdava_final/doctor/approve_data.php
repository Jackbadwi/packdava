<?php
session_start();
require_once __DIR__ . '/../conn.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'doctor' && $_SESSION['role'] !== 'expert')) {
    header('Location: ../index.php');
    exit;
}

$doctor_id = $_SESSION['user_id'];

// ==================== دریافت بیماران تحت نظر (همانند enter_clinical) ====================
$patients = [];
$patient_ids = [];
try {
    // بررسی وجود جدول doctor_patients
    $check_table = $conn->query("SHOW TABLES LIKE 'doctor_patients'");
    $has_doctor_patients = $check_table && $check_table->num_rows > 0;
    
    if ($has_doctor_patients) {
        $sql = "SELECT u.id, u.fullname, u.age, u.gender, u.phone, u.email, u.student_id, u.school 
                FROM doctor_patients dp 
                JOIN users u ON dp.patient_id = u.id 
                WHERE dp.doctor_id = ? AND u.role = 'patient'
                ORDER BY u.fullname";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $doctor_id);
        $stmt->execute();
        $patients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        // اگر جدول doctor_patients وجود نداشت، همه بیماران را بگیر
        $sql = "SELECT id, fullname, age, gender, phone, email, student_id, school 
                FROM users 
                WHERE role = 'patient' 
                ORDER BY fullname";
        $patients = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    }
    $patient_ids = array_column($patients, 'id');
} catch (Exception $e) {
    $patients = [];
    $patient_ids = [];
}

// ==================== بررسی ساختار جدول clinical_data ====================
$table_columns = [];
try {
    $result = $conn->query("SHOW COLUMNS FROM clinical_data");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $table_columns[] = $row['Field'];
        }
    }
} catch (Exception $e) {
    die('جدول clinical_data وجود ندارد.');
}
$has_fbs = in_array('fbs', $table_columns);
$has_hba1c = in_array('hba1c', $table_columns);
$has_notes = in_array('notes', $table_columns);

// ==================== دریافت داده‌های بالینی بر اساس وضعیت ====================
$pending_data = [];
$approved_data = [];
$rejected_data = [];

if (!empty($patient_ids)) {
    $placeholders = implode(',', array_fill(0, count($patient_ids), '?'));
    $types = str_repeat('i', count($patient_ids));
    
    try {
        $select_fields = "c.id, c.patient_id, c.record_date, CONCAT(COALESCE(c.bp_systolic,'?'),'/',COALESCE(c.bp_diastolic,'?')) as blood_pressure, c.heart_rate, 
                          c.weight, c.height, c.symptoms, c.diagnosis, c.treatment, c.status, c.notes,
                          u.fullname as patient_name, u.age, u.gender, u.school";
        if ($has_fbs) $select_fields .= ", c.fbs";
        if ($has_hba1c) $select_fields .= ", c.hba1c";
        
        // در انتظار
        $sql = "SELECT $select_fields
                FROM clinical_data c 
                JOIN users u ON c.patient_id = u.id 
                WHERE c.patient_id IN ($placeholders) AND c.status = 'pending'
                ORDER BY c.record_date DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$patient_ids);
        $stmt->execute();
        $pending_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // تأیید شده
        $sql = "SELECT $select_fields, c.approved_at, c.doctor_notes
                FROM clinical_data c 
                JOIN users u ON c.patient_id = u.id 
                WHERE c.patient_id IN ($placeholders) AND c.status = 'approved'
                ORDER BY c.approved_at DESC LIMIT 20";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$patient_ids);
        $stmt->execute();
        $approved_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        // رد شده
        $sql = "SELECT $select_fields, c.approved_at, c.doctor_notes
                FROM clinical_data c 
                JOIN users u ON c.patient_id = u.id 
                WHERE c.patient_id IN ($placeholders) AND c.status = 'rejected'
                ORDER BY c.approved_at DESC LIMIT 20";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$patient_ids);
        $stmt->execute();
        $rejected_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
    } catch (Exception $e) {
        // خطا در کوئری
    }
}

// ==================== پردازش درخواست‌های POST ====================
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $data_id = intval($_POST['data_id'] ?? 0);
    
    if ($action === 'approve' || $action === 'reject') {
        $status = ($action === 'approve') ? 'approved' : 'rejected';
        $doctor_notes = trim($_POST['doctor_notes'] ?? '');
        $sql = "UPDATE clinical_data SET status = ?, doctor_notes = ?, approved_at = NOW(), doctor_id = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssii", $status, $doctor_notes, $doctor_id, $data_id);
        if ($stmt->execute()) {
            $message = 'داده با موفقیت ' . ($status === 'approved' ? 'تأیید' : 'رد') . ' شد.';
        } else {
            $error = 'خطا در به‌روزرسانی: ' . $stmt->error;
        }
        $stmt->close();
    } 
    elseif ($action === 'edit') {
        // دریافت داده‌های فرم
        $patient_id = intval($_POST['patient_id']);
        $record_date = $_POST['record_date'];
        $blood_pressure = $_POST['blood_pressure'] ?? null;
        $heart_rate = isset($_POST['heart_rate']) && $_POST['heart_rate'] !== '' ? intval($_POST['heart_rate']) : null;
        $weight = isset($_POST['weight']) && $_POST['weight'] !== '' ? floatval($_POST['weight']) : null;
        $height = isset($_POST['height']) && $_POST['height'] !== '' ? floatval($_POST['height']) : null;
        $symptoms = $_POST['symptoms'] ?? '';
        $diagnosis = $_POST['diagnosis'] ?? '';
        $treatment = $_POST['treatment'] ?? '';
        $notes = $_POST['notes'] ?? '';
        $fbs = isset($_POST['fbs']) && $_POST['fbs'] !== '' ? floatval($_POST['fbs']) : null;
        $hba1c = isset($_POST['hba1c']) && $_POST['hba1c'] !== '' ? floatval($_POST['hba1c']) : null;
        
        // ساخت کوئری بر اساس ستون‌های موجود
        $fields = ['blood_pressure', 'heart_rate', 'weight', 'height', 'symptoms', 'diagnosis', 'treatment', 'notes'];
        $params = [$blood_pressure, $heart_rate, $weight, $height, $symptoms, $diagnosis, $treatment, $notes];
        $types = "siddssss"; // خون فشار (s), ضربان (i), وزن (d), قد (d), علائم (s), تشخیص (s), درمان (s), یادداشت (s)
        
        if ($has_fbs && $fbs !== null) {
            $fields[] = 'fbs';
            $params[] = $fbs;
            $types .= 'd';
        }
        if ($has_hba1c && $hba1c !== null) {
            $fields[] = 'hba1c';
            $params[] = $hba1c;
            $types .= 'd';
        }
        
        $set_parts = [];
        $idx = 0;
        foreach ($fields as $field) {
            $set_parts[] = "$field = ?";
        }
        $set_parts[] = "record_date = ?";
        $params[] = $record_date;
        $types .= 's';
        
        $sql = "UPDATE clinical_data SET " . implode(', ', $set_parts) . " WHERE id = ?";
        $params[] = $data_id;
        $types .= 'i';
        
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            if ($stmt->execute()) {
                $message = 'داده با موفقیت ویرایش شد.';
            } else {
                $error = 'خطا در ویرایش: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = 'خطا در آماده‌سازی کوئری: ' . $conn->error;
        }
    }
    elseif ($action === 'delete') {
        $sql = "DELETE FROM clinical_data WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $data_id);
        if ($stmt->execute()) {
            $message = 'داده با موفقیت حذف شد.';
        } else {
            $error = 'خطا در حذف: ' . $stmt->error;
        }
        $stmt->close();
    }
    
    // پس از پردازش، صفحه را رفرش کن
    header('Location: approve_data.php?msg=' . urlencode($message) . '&err=' . urlencode($error));
    exit;
}

if (isset($_GET['msg'])) $message = urldecode($_GET['msg']);
if (isset($_GET['err'])) $error = urldecode($_GET['err']);

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

function getGenderText($gender) {
    if ($gender === 'male') return 'مرد';
    if ($gender === 'female') return 'زن';
    return '';
}

function calcBMI($weight, $height) {
    if ($weight && $height) {
        return round($weight / (($height/100) ** 2), 1);
    }
    return null;
}

$current = 'approve_data';
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
    <title>تأیید داده‌ها | پک دوا</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .status-badge { padding: 2px 10px; border-radius: 99px; font-size: 11px; font-weight: 600; display: inline-block; }
        .badge-pending { background: #fef3c7; color: #d97706; }
        .badge-approved { background: #d1fae5; color: #059669; }
        .badge-rejected { background: #fee2e2; color: #dc2626; }
        .approval-card { border-right: 3px solid var(--gray-400); margin-bottom: 16px; transition: all 0.2s; }
        .approval-card.pending { border-right-color: var(--orange); }
        .approval-card .header { display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; cursor: pointer; background: var(--gray-50); border-radius: 8px 8px 0 0; }
        .approval-card .header:hover { background: var(--gray-100); }
        .approval-card .body { padding: 16px; display: none; background: white; border-radius: 0 0 8px 8px; }
        .approval-card .body.open { display: block; }
        .approval-card .body .info-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 8px; margin-bottom: 12px; }
        .approval-card .body .info-grid .item { font-size: 13px; }
        .approval-card .body .info-grid .item strong { color: var(--gray-700); }
        .filter-section { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; padding: 12px 16px; background: var(--gray-50); border-radius: 8px; margin-bottom: 16px; }
        .filter-section select, .filter-section input { padding: 6px 12px; border: 1px solid var(--gray-300); border-radius: 6px; font-size: 13px; }
        .filter-section input { flex: 1; min-width: 150px; }
        .action-buttons { display: flex; gap: 8px; flex-wrap: wrap; }
        .tabs { display: flex; border-bottom: 2px solid var(--gray-200); margin-bottom: 20px; }
        .tab { padding: 8px 16px; cursor: pointer; border-bottom: 2px solid transparent; font-weight: 600; transition: all 0.2s; }
        .tab.active { border-bottom-color: var(--blue); color: var(--blue); }
        .tab .count { background: var(--gray-200); color: var(--gray-600); padding: 0 6px; border-radius: 99px; font-size: 11px; margin-right: 4px; }
        .tab.active .count { background: var(--blue); color: white; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .data-table-mini { width: 100%; border-collapse: collapse; font-size: 12px; }
        .data-table-mini th { background: var(--gray-100); padding: 6px 10px; text-align: center; }
        .data-table-mini td { padding: 6px 10px; text-align: center; border-bottom: 1px solid var(--gray-100); }
        .no-data { text-align: center; padding: 40px; color: var(--gray-500); }
        .no-data i { font-size: 48px; color: var(--gray-300); margin-bottom: 16px; display: block; }
        .note-box { background: var(--gray-50); padding: 8px 12px; border-radius: 6px; font-size: 12px; margin-top: 6px; }
        .modal { display: none; position: fixed; z-index: 1050; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); overflow: auto; }
        .modal-content { background: #fff; margin: 5% auto; border-radius: 12px; width: 90%; max-width: 700px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
        .modal-header { padding: 15px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .modal-body { padding: 20px; max-height: 70vh; overflow-y: auto; }
        .modal-footer { padding: 15px 20px; border-top: 1px solid #eee; display: flex; justify-content: flex-end; gap: 10px; }
        .form-row { display: flex; gap: 16px; flex-wrap: wrap; margin-bottom: 10px; }
        .form-row .field { flex: 1; min-width: 150px; }
        .field label { display: block; font-size: 12px; font-weight: 600; color: var(--gray-600); margin-bottom: 4px; }
        .field input, .field textarea { width: 100%; padding: 8px 12px; border: 1px solid var(--gray-300); border-radius: 6px; font-size: 13px; }
        .field textarea { resize: vertical; min-height: 50px; }
        @media (max-width: 768px) {
            .filter-section { flex-direction: column; align-items: stretch; }
            .filter-section input { min-width: auto; }
            .approval-card .header { flex-wrap: wrap; gap: 8px; }
            .action-buttons { flex-direction: column; }
            .modal-content { width: 95%; margin: 10% auto; }
            .form-row { flex-direction: column; }
        }
    </style>
</head>
<body>
<div class="app-layout">
    <?php include '../assets/sidebar_doctor.php'; ?>
    <div class="main-content">
        <div class="topbar">
            <div>
                <div class="topbar-title">تأیید داده‌های بالینی</div>
                <div class="topbar-subtitle">بررسی، ویرایش، تأیید یا رد داده‌های وارد شده</div>
            </div>
            <div class="topbar-actions">
                <span style="font-size:14px;color:var(--gray-600);">
                    <i class="fas fa-clock"></i> <?= count($pending_data) ?> در انتظار
                </span>
            </div>
        </div>
        <div class="page-body">
            <?php if ($message): ?>
                <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <!-- ==================== فیلترها ==================== -->
            <div class="filter-section">
                <label style="font-weight:600;font-size:13px;"><i class="fas fa-search"></i> جستجو:</label>
                <input type="text" id="searchInput" placeholder="نام بیمار..." onkeyup="applyFilters()">
                <select id="patientFilter" onchange="applyFilters()">
                    <option value="">همه بیماران</option>
                    <?php foreach ($patients as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['fullname']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-outline btn-sm" onclick="resetFilters()"><i class="fas fa-times"></i> پاک کردن</button>
                <span style="font-size:12px;color:var(--gray-400);margin-right:auto;" id="resultCount"><?= count($pending_data) ?> مورد</span>
            </div>

            <!-- ==================== تب‌ها ==================== -->
            <div class="tabs" id="approvalTabs">
                <div class="tab active" data-tab="pending" onclick="switchTab('pending')">
                    در انتظار <span class="count"><?= count($pending_data) ?></span>
                </div>
                <div class="tab" data-tab="approved" onclick="switchTab('approved')">
                    تأیید شده <span class="count"><?= count($approved_data) ?></span>
                </div>
                <div class="tab" data-tab="rejected" onclick="switchTab('rejected')">
                    رد شده <span class="count"><?= count($rejected_data) ?></span>
                </div>
            </div>

            <!-- ==================== محتوای تب در انتظار ==================== -->
            <div class="tab-content active" id="tab-pending">
                <?php if (empty($pending_data)): ?>
                    <div class="no-data">
                        <i class="fas fa-check-circle" style="color:var(--green);"></i>
                        <h4>هیچ داده‌ای در انتظار تأیید نیست</h4>
                        <p>همه داده‌های بالینی بررسی شده‌اند.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($pending_data as $row): ?>
                        <div class="approval-card pending" data-patient="<?= $row['patient_id'] ?>" data-name="<?= htmlspecialchars(strtolower($row['patient_name'])) ?>">
                            <div class="header" onclick="toggleCard(this)">
                                <div>
                                    <strong><?= htmlspecialchars($row['patient_name']) ?></strong>
                                    <span style="font-size:12px;color:var(--gray-400);margin-right:8px;">
                                        <?= $row['age'] ?? '?' ?> سال | <?= getGenderText($row['gender']) ?>
                                        | <?= htmlspecialchars($row['record_date']) ?>
                                    </span>
                                    <?= getStatusBadge($row['status']) ?>
                                </div>
                                <span><i class="fas fa-chevron-down chevron-icon"></i></span>
                            </div>
                            <div class="body">
                                <div class="info-grid">
                                    <?php if ($row['blood_pressure']): ?>
                                        <div class="item"><strong>فشار خون:</strong> <?= htmlspecialchars($row['blood_pressure']) ?> mmHg</div>
                                    <?php endif; ?>
                                    <?php if ($row['heart_rate']): ?>
                                        <div class="item"><strong>ضربان قلب:</strong> <?= htmlspecialchars($row['heart_rate']) ?> bpm</div>
                                    <?php endif; ?>
                                    <?php if ($row['weight']): ?>
                                        <div class="item"><strong>وزن:</strong> <?= htmlspecialchars($row['weight']) ?> kg</div>
                                    <?php endif; ?>
                                    <?php if ($row['height']): ?>
                                        <div class="item"><strong>قد:</strong> <?= htmlspecialchars($row['height']) ?> cm</div>
                                    <?php endif; ?>
                                    <?php $bmi = calcBMI($row['weight'], $row['height']); if ($bmi): ?>
                                        <div class="item"><strong>BMI:</strong> <?= $bmi ?></div>
                                    <?php endif; ?>
                                    <?php if (isset($row['fbs']) && $row['fbs']): ?>
                                        <div class="item"><strong>FBS:</strong> <?= htmlspecialchars($row['fbs']) ?> mg/dL</div>
                                    <?php endif; ?>
                                    <?php if (isset($row['hba1c']) && $row['hba1c']): ?>
                                        <div class="item"><strong>HbA1c:</strong> <?= htmlspecialchars($row['hba1c']) ?>%</div>
                                    <?php endif; ?>
                                    <?php if ($row['symptoms']): ?>
                                        <div class="item"><strong>علائم:</strong> <?= htmlspecialchars($row['symptoms']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($row['diagnosis']): ?>
                                        <div class="item"><strong>تشخیص:</strong> <?= htmlspecialchars($row['diagnosis']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($row['treatment']): ?>
                                        <div class="item"><strong>درمان:</strong> <?= htmlspecialchars($row['treatment']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($row['notes']): ?>
                                        <div class="item" style="grid-column:1/-1;"><strong>یادداشت:</strong> <?= htmlspecialchars($row['notes']) ?></div>
                                    <?php endif; ?>
                                </div>

                                <!-- دکمه‌های عملیات -->
                                <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
                                    <button class="btn btn-green btn-sm" onclick="approveData(<?= $row['id'] ?>)">
                                        <i class="fas fa-check"></i> تأیید
                                    </button>
                                    <button class="btn btn-red btn-sm" onclick="rejectData(<?= $row['id'] ?>)">
                                        <i class="fas fa-times"></i> رد
                                    </button>
                                    <button class="btn btn-primary btn-sm" onclick="editData(<?= $row['id'] ?>)">
                                        <i class="fas fa-edit"></i> ویرایش
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteData(<?= $row['id'] ?>)">
                                        <i class="fas fa-trash"></i> حذف
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- ==================== محتوای تب تأیید شده ==================== -->
            <div class="tab-content" id="tab-approved">
                <?php if (empty($approved_data)): ?>
                    <div class="no-data">
                        <i class="fas fa-inbox" style="color:var(--gray-300);"></i>
                        <h4>هیچ داده‌ای تأیید نشده است</h4>
                    </div>
                <?php else: ?>
                    <div style="overflow-x:auto;">
                        <table class="data-table-mini">
                            <thead>
                                <tr>
                                    <th>بیمار</th>
                                    <th>تاریخ</th>
                                    <th>فشار خون</th>
                                    <th>وزن</th>
                                    <th>BMI</th>
                                    <th>یادداشت پزشک</th>
                                    <th>تاریخ تأیید</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($approved_data as $row): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($row['patient_name']) ?></strong></td>
                                        <td><?= htmlspecialchars($row['record_date']) ?></td>
                                        <td><?= htmlspecialchars($row['blood_pressure'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($row['weight'] ?? '-') ?></td>
                                        <td><?= calcBMI($row['weight'], $row['height']) ?? '-' ?></td>
                                        <td style="max-width:150px;font-size:11px;"><?= htmlspecialchars($row['doctor_notes'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($row['approved_at'] ?? '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ==================== محتوای تب رد شده ==================== -->
            <div class="tab-content" id="tab-rejected">
                <?php if (empty($rejected_data)): ?>
                    <div class="no-data">
                        <i class="fas fa-inbox" style="color:var(--gray-300);"></i>
                        <h4>هیچ داده‌ای رد نشده است</h4>
                    </div>
                <?php else: ?>
                    <div style="overflow-x:auto;">
                        <table class="data-table-mini">
                            <thead>
                                <tr>
                                    <th>بیمار</th>
                                    <th>تاریخ</th>
                                    <th>فشار خون</th>
                                    <th>وزن</th>
                                    <th>دلیل رد</th>
                                    <th>تاریخ رد</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rejected_data as $row): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($row['patient_name']) ?></strong></td>
                                        <td><?= htmlspecialchars($row['record_date']) ?></td>
                                        <td><?= htmlspecialchars($row['blood_pressure'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($row['weight'] ?? '-') ?></td>
                                        <td style="max-width:150px;font-size:11px;"><?= htmlspecialchars($row['doctor_notes'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($row['approved_at'] ?? '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ==================== مودال ویرایش ==================== -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h4><i class="fas fa-edit"></i> ویرایش داده بالینی</h4>
            <span class="close" onclick="closeEditModal()" style="font-size:24px;cursor:pointer;">&times;</span>
        </div>
        <form method="POST" id="editForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="data_id" id="editDataId">
            <input type="hidden" name="patient_id" id="editPatientId">
            <div class="modal-body">
                <div class="form-row">
                    <div class="field">
                        <label>تاریخ ثبت</label>
                        <input type="date" name="record_date" id="editRecordDate" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="field">
                        <label>فشار خون (سیستولیک/دیاستولیک)</label>
                        <input type="text" name="blood_pressure" id="editBloodPressure" placeholder="مثال: 135/85">
                    </div>
                    <div class="field">
                        <label>ضربان قلب (bpm)</label>
                        <input type="number" name="heart_rate" id="editHeartRate" placeholder="مثال: 72">
                    </div>
                </div>
                <div class="form-row">
                    <div class="field">
                        <label>وزن (kg)</label>
                        <input type="number" step="0.1" name="weight" id="editWeight" placeholder="مثال: 82">
                    </div>
                    <div class="field">
                        <label>قد (cm)</label>
                        <input type="number" step="0.1" name="height" id="editHeight" placeholder="مثال: 174">
                    </div>
                </div>
                <?php if ($has_fbs): ?>
                <div class="form-row">
                    <div class="field">
                        <label>FBS (mg/dL)</label>
                        <input type="number" step="0.1" name="fbs" id="editFbs" placeholder="مثال: 118">
                    </div>
                    <div class="field">
                        <label>HbA1c (%)</label>
                        <input type="number" step="0.1" name="hba1c" id="editHba1c" placeholder="مثال: 5.9">
                    </div>
                </div>
                <?php endif; ?>
                <div class="form-row">
                    <div class="field">
                        <label>علائم</label>
                        <textarea name="symptoms" id="editSymptoms" rows="2" placeholder="علائم بالینی..."></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <div class="field">
                        <label>تشخیص</label>
                        <textarea name="diagnosis" id="editDiagnosis" rows="2" placeholder="تشخیص..."></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <div class="field">
                        <label>درمان تجویزی</label>
                        <textarea name="treatment" id="editTreatment" rows="2" placeholder="درمان..."></textarea>
                    </div>
                </div>
                <?php if ($has_notes): ?>
                <div class="form-row">
                    <div class="field">
                        <label>یادداشت</label>
                        <textarea name="notes" id="editNotes" rows="2" placeholder="یادداشت..."></textarea>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">انصراف</button>
                <button type="submit" class="btn btn-success">ذخیره تغییرات</button>
            </div>
        </form>
    </div>
</div>

<script>
// ==================== توابع عمومی ====================

function toggleCard(el) {
    const body = el.nextElementSibling;
    body.classList.toggle('open');
    const icon = el.querySelector('.chevron-icon');
    if (icon) icon.classList.toggle('open');
}

function switchTab(tab) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelector(`.tab[data-tab="${tab}"]`).classList.add('active');
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    document.getElementById('tab-' + tab).classList.add('active');
    localStorage.setItem('approve_tab', tab);
}

function applyFilters() {
    const search = document.getElementById('searchInput').value.toLowerCase().trim();
    const patient = document.getElementById('patientFilter').value;
    const cards = document.querySelectorAll('#tab-pending .approval-card');
    let visibleCount = 0;
    cards.forEach(card => {
        const name = card.dataset.name || '';
        const cardPatient = card.dataset.patient || '';
        let show = true;
        if (search && !name.includes(search)) show = false;
        if (patient && cardPatient !== patient) show = false;
        card.style.display = show ? 'block' : 'none';
        if (show) visibleCount++;
    });
    document.getElementById('resultCount').textContent = visibleCount + ' مورد';
}

function resetFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('patientFilter').value = '';
    applyFilters();
}

// ==================== عملیات تأیید/رد ====================

function approveData(id) {
    if (confirm('آیا از تأیید این داده اطمینان دارید؟')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="approve">
            <input type="hidden" name="data_id" value="${id}">
            <input type="hidden" name="doctor_notes" value="">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function rejectData(id) {
    const note = prompt('لطفاً دلیل رد را وارد کنید (اختیاری):');
    if (note !== null) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="data_id" value="${id}">
            <input type="hidden" name="doctor_notes" value="${note}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// ==================== ویرایش داده ====================

function editData(id) {
    // دریافت داده‌ها از طریق AJAX
    fetch(`get_clinical_data.php?id=${id}`)
        .then(res => res.json())
        .then(data => {
            if (data.ok) {
                const row = data.data;
                document.getElementById('editDataId').value = row.id;
                document.getElementById('editPatientId').value = row.patient_id;
                document.getElementById('editRecordDate').value = row.record_date;
                document.getElementById('editBloodPressure').value = row.blood_pressure || '';
                document.getElementById('editHeartRate').value = row.heart_rate || '';
                document.getElementById('editWeight').value = row.weight || '';
                document.getElementById('editHeight').value = row.height || '';
                <?php if ($has_fbs): ?>
                document.getElementById('editFbs').value = row.fbs || '';
                document.getElementById('editHba1c').value = row.hba1c || '';
                <?php endif; ?>
                document.getElementById('editSymptoms').value = row.symptoms || '';
                document.getElementById('editDiagnosis').value = row.diagnosis || '';
                document.getElementById('editTreatment').value = row.treatment || '';
                <?php if ($has_notes): ?>
                document.getElementById('editNotes').value = row.notes || '';
                <?php endif; ?>
                document.getElementById('editModal').style.display = 'block';
            } else {
                alert('خطا در دریافت اطلاعات: ' + data.msg);
            }
        })
        .catch(err => {
            alert('خطا در ارتباط با سرور.');
            console.error(err);
        });
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

// ==================== حذف داده ====================

function deleteData(id) {
    if (confirm('آیا از حذف این داده اطمینان دارید؟ این عملیات قابل بازگشت نیست.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="data_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// ==================== بارگذاری اولیه ====================
document.addEventListener('DOMContentLoaded', function() {
    const firstCard = document.querySelector('#tab-pending .approval-card');
    if (firstCard) {
        const header = firstCard.querySelector('.header');
        if (header) toggleCard(header);
    }
    const savedTab = localStorage.getItem('approve_tab');
    if (savedTab) switchTab(savedTab);
});

// بستن مودال با کلیک خارج از آن
window.onclick = function(event) {
    const modal = document.getElementById('editModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}
</script>
<script src="../assets/js/db.js"></script>
<script src="../assets/js/sync.js"></script>
<script src="../assets/js/notify.js"></script>
<script src="../assets/js/app.js"></script>
</body>
</html>