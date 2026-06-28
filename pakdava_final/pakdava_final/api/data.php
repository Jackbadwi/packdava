<?php
/**
 * api/data.php v3 — ارسال JSON کامل برای IndexedDB
 * شامل: داده‌های بالینی کامل + NCD-RisC reference data
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }
require_once __DIR__ . '/../conn.php';

$user_id = (int)$_SESSION['user_id'];
$data = [];

function qGet($conn, $sql, $types='', ...$params) {
    $s = $conn->prepare($sql);
    if ($types && $params) $s->bind_param($types, ...$params);
    $s->execute();
    return $s->get_result()->fetch_all(MYSQLI_ASSOC);
}

$data['user'] = qGet($conn,"SELECT id,fullname,email,role,phone,gender,age,height,weight,bmi_value FROM users WHERE id=?",'i',$user_id)[0]??[];

if ($_SESSION['role']==='patient') {
    $pid = $user_id;
    $data['clinical']      = qGet($conn,"SELECT * FROM clinical_data WHERE patient_id=? ORDER BY record_date DESC LIMIT 10",'i',$pid);
    $data['bmi_history']   = qGet($conn,"SELECT * FROM bmi_records WHERE user_id=? ORDER BY record_date DESC LIMIT 12",'i',$pid);
    $data['daily']         = qGet($conn,"SELECT * FROM daily_plan WHERE patient_id=? AND plan_date>=DATE_SUB(CURDATE(),INTERVAL 7 DAY) ORDER BY plan_date DESC",'i',$pid);
    $data['progress']      = qGet($conn,"SELECT * FROM progress WHERE patient_id=? ORDER BY record_date DESC LIMIT 12",'i',$pid);
    $data['risk']          = qGet($conn,"SELECT * FROM risk_assessment WHERE patient_id=? ORDER BY assessment_date DESC LIMIT 5",'i',$pid);
    $data['soc']           = qGet($conn,"SELECT * FROM soc_assessment WHERE patient_id=? ORDER BY assessment_date DESC LIMIT 5",'i',$pid);
    $data['notifications'] = qGet($conn,"SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 20",'i',$user_id);
}

// داده‌های NCD-RisC برای cache آفلاین
$data['ncd_risc'] = qGet($conn,"SELECT indicator,year,sex,value,lower_95ci,upper_95ci,unit FROM ncd_risc_iran ORDER BY indicator,sex,year");

$data['_meta'] = ['fetched_at'=>date('Y-m-d H:i:s'),'cache_valid'=>date('Y-m-d H:i:s',strtotime('+1 hour'))];
echo json_encode($data, JSON_UNESCAPED_UNICODE);
?>
