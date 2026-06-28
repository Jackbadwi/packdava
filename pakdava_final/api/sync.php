<?php
/**
 * api/sync.php v3 — همگام‌سازی با دیتابیس اصلاح‌شده
 * ستون‌های جدید: fbs, hba1c, bp_systolic, bp_diastolic, cholesterol_total, ldl, hdl, triglycerides, waist
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'Forbidden']); exit;
}

require_once __DIR__ . '/../conn.php';

$input   = json_decode(file_get_contents('php://input'), true);
$store   = $input['store']   ?? '';
$records = $input['records'] ?? [];

if (empty($records)) { echo json_encode(['success'=>true,'synced'=>0]); exit; }

$user_id = (int)$_SESSION['user_id'];
$patient_id = $user_id;
$synced = 0; $errors = [];

foreach ($records as $rec) {
    unset($rec['local_id'], $rec['synced'], $rec['timestamp']);
    $d = $rec['data'] ?? $rec;
    try {
        switch ($store) {
            case 'clinical_queue':
                $stmt = $conn->prepare("
                    INSERT INTO clinical_data
                      (patient_id,record_date,fbs,ppg,hba1c,bp_systolic,bp_diastolic,
                       heart_rate,cholesterol_total,ldl,hdl,triglycerides,
                       weight,height,waist_circumference,creatinine,
                       symptoms,medications,notes,status)
                    VALUES (?,CURDATE(),?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'pending')
                ");
                $stmt->bind_param('idddiiiddddddddsss',
                    $patient_id,
                    $d['fbs']??null, $d['ppg']??null, $d['hba1c']??null,
                    $d['bp_systolic']??null, $d['bp_diastolic']??null, $d['heart_rate']??null,
                    $d['cholesterol_total']??null, $d['ldl']??null, $d['hdl']??null, $d['triglycerides']??null,
                    $d['weight']??null, $d['height']??null, $d['waist_circumference']??null, $d['creatinine']??null,
                    $d['symptoms']??'', $d['medications']??'', $d['notes']??''
                );
                if ($stmt->execute()) {
                    $synced++;
                    // بروز کردن بیمار و اطلاع به پزشک
                    if (($d['weight']??0) && ($d['height']??0)) {
                        $bmi_v = round($d['weight']/(($d['height']/100)**2),1);
                        $bmi_s = $bmi_v<18.5?'کم‌وزن':($bmi_v<25?'نرمال':($bmi_v<30?'اضافه‌وزن':'چاق'));
                        $conn->query("INSERT INTO bmi_records (user_id,record_date,height,weight,bmi_value,bmi_status)
                            VALUES ($patient_id,CURDATE(),{$d['height']},{$d['weight']},$bmi_v,'$bmi_s')
                            ON DUPLICATE KEY UPDATE weight=VALUES(weight),bmi_value=VALUES(bmi_value)");
                    }
                    $lid = $conn->insert_id;
                    $conn->query("INSERT INTO data_approval (patient_id,doctor_id,data_type,data_id,approval_status)
                        SELECT $patient_id,id,'clinical_data',$lid,'pending' FROM users WHERE role='doctor' LIMIT 1");
                    $conn->query("INSERT INTO notifications (user_id,type,title,message,url)
                        SELECT id,'clinical','داده بالینی جدید','بیمار داده بالینی جدید ثبت کرد','../doctor/approve_data.php'
                        FROM users WHERE role='doctor' LIMIT 1");
                    if (($d['fbs']??0)>=126)
                        $conn->query("INSERT INTO alerts (patient_id,type,message) VALUES ($patient_id,'clinical_threshold','FBS بالا: {$d['fbs']} mg/dL')");
                } else $errors[] = $stmt->error;
                $stmt->close();
                break;

            case 'soc_queue':
                $stage = $d['stage'] ?? 'contemplation';
                $stmt = $conn->prepare("INSERT INTO soc_assessment (patient_id,assessment_date,stage,stage_duration,main_barrier,comments) VALUES (?,CURDATE(),?,?,?,?)");
                $stmt->bind_param('issss', $patient_id, $stage, $d['duration']??'', $d['barrier']??'', $d['comments']??'');
                if ($stmt->execute()) $synced++;
                else $errors[] = $stmt->error;
                $stmt->close();
                break;

            case 'daily_queue':
                $stmt = $conn->prepare("INSERT INTO daily_plan (patient_id,plan_date,soc_stage,activities,medication,diet,notes,completed) VALUES (?,CURDATE(),?,?,?,?,?,?)");
                $done = (int)($d['completed']??0);
                $stmt->bind_param('issssssi', $patient_id, $d['soc_stage']??'', $d['activities']??'', $d['medication']??'', $d['diet']??'', $d['notes']??'', $done);
                if ($stmt->execute()) $synced++;
                else $errors[] = $stmt->error;
                $stmt->close();
                break;

            case 'risk_queue':
                $factors = json_encode($d['factors']??[], JSON_UNESCAPED_UNICODE);
                $stmt = $conn->prepare("INSERT INTO risk_assessment (patient_id,assessment_date,risk_score,risk_level,risk_probability,population_prev,relative_risk,factors,recommendations) VALUES (?,CURDATE(),?,?,?,?,?,?,?)");
                $stmt->bind_param('iisddds s',
                    $patient_id,
                    $d['score']??0, $d['risk_level']??'medium',
                    $d['probability']??0, $d['population_prev']??0, $d['relative_risk']??0,
                    $factors, $d['recommendations']??''
                );
                if ($stmt->execute()) $synced++;
                else $errors[] = $stmt->error;
                $stmt->close();
                break;
        }
    } catch (Exception $e) { $errors[] = $e->getMessage(); }
}

echo json_encode(['success'=>empty($errors),'synced'=>$synced,'errors'=>$errors,'store'=>$store]);
?>
