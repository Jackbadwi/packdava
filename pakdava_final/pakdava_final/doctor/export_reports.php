<?php
session_start();
require_once __DIR__ . '/../conn.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'doctor' && $_SESSION['role'] !== 'expert')) {
    header('Location: ../index.php');
    exit;
}

$doctor_id = $_SESSION['user_id'];
$patient_id_filter = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;

// دریافت بیماران تحت نظر
$patient_ids = [];
try {
    $sql = "SELECT patient_id FROM doctor_patients WHERE doctor_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $patient_ids[] = $row['patient_id'];
    }
    $stmt->close();
} catch (Exception $e) {
    $patient_ids = [];
}

// اگر فیلتر بیمار مشخص شده، فقط آن بیمار را در نظر بگیر
if ($patient_id_filter > 0 && in_array($patient_id_filter, $patient_ids)) {
    $patient_ids = [$patient_id_filter];
}

$data = [];
if (!empty($patient_ids)) {
    $placeholders = implode(',', array_fill(0, count($patient_ids), '?'));
    
    // بررسی وجود ستون‌های fbs و hba1c
    $has_fbs = false;
    $has_hba1c = false;
    try {
        $check = $conn->query("SHOW COLUMNS FROM clinical_data LIKE 'fbs'");
        $has_fbs = $check->num_rows > 0;
        $check = $conn->query("SHOW COLUMNS FROM clinical_data LIKE 'hba1c'");
        $has_hba1c = $check->num_rows > 0;
    } catch (Exception $e) {}

    $select_fields = "u.fullname as patient_name, u.age, u.gender, u.phone, u.school, 
                       c.record_date, CONCAT(COALESCE(c.bp_systolic,'?'),'/',COALESCE(c.bp_diastolic,'?')) as blood_pressure, c.heart_rate, c.weight, c.height, 
                       c.symptoms, c.diagnosis, c.treatment, c.status, c.notes";
    if ($has_fbs) $select_fields .= ", c.fbs";
    if ($has_hba1c) $select_fields .= ", c.hba1c";

    $sql = "SELECT $select_fields
            FROM clinical_data c 
            JOIN users u ON c.patient_id = u.id 
            WHERE c.patient_id IN ($placeholders) 
            ORDER BY u.fullname, c.record_date DESC";
    $stmt = $conn->prepare($sql);
    $types = str_repeat('i', count($patient_ids));
    $stmt->bind_param($types, ...$patient_ids);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

header('Content-Type: text/csv; charset=utf-8');
$filename = $patient_id_filter > 0 ? 'patient_report_' . $patient_id_filter . '_' . date('Y-m-d') : 'clinical_report_' . date('Y-m-d');
header('Content-Disposition: attachment; filename="' . $filename . '.csv"');

$output = fopen('php://output', 'w');
fwrite($output, "\xEF\xBB\xBF");

$headers = ['بیمار', 'سن', 'جنسیت', 'تلفن', 'مدرسه', 'تاریخ', 'فشار خون', 'ضربان قلب', 'وزن', 'قد', 'BMI', 'علائم', 'تشخیص', 'درمان', 'وضعیت', 'یادداشت'];
if ($has_fbs) $headers[] = 'FBS';
if ($has_hba1c) $headers[] = 'HbA1c';
fputcsv($output, $headers);

foreach ($data as $row) {
    $bmi = ($row['height'] && $row['weight']) ? round($row['weight'] / (($row['height']/100) ** 2), 1) : '';
    $gender = $row['gender'] === 'male' ? 'مرد' : ($row['gender'] === 'female' ? 'زن' : '');
    $status = $row['status'] === 'pending' ? 'در انتظار' : ($row['status'] === 'approved' ? 'تأیید شده' : 'رد شده');
    
    $csv_row = [
        $row['patient_name'],
        $row['age'],
        $gender,
        $row['phone'],
        $row['school'],
        $row['record_date'],
        $row['blood_pressure'],
        $row['heart_rate'],
        $row['weight'],
        $row['height'],
        $bmi,
        $row['symptoms'],
        $row['diagnosis'],
        $row['treatment'],
        $status,
        $row['notes']
    ];
    if ($has_fbs) $csv_row[] = $row['fbs'] ?? '';
    if ($has_hba1c) $csv_row[] = $row['hba1c'] ?? '';
    
    fputcsv($output, $csv_row);
}

fclose($output);
exit;