<?php
/**
 * api/data.php v4.0
 * خروجی JSON کامل برای IndexedDB — همه store‌های آفلاین
 * شامل: clinical, bmi, daily, soc, risk, progress, notifications, ncd_risc, peer
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-PakDava-Offline-Cache: true');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error'=>'Unauthorized','offline'=>false]);
    exit;
}

require_once __DIR__ . '/../conn.php';

$uid  = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'patient';
$data = ['_meta' => [
    'fetched_at'  => date('Y-m-d H:i:s'),
    'user_id'     => $uid,
    'role'        => $role,
    'cache_valid' => date('Y-m-d H:i:s', strtotime('+1 hour')),
    'offline'     => false,
]];

function qAll($conn, $sql, $types='', ...$params) {
    $s = $conn->prepare($sql);
    if ($types && $params) $s->bind_param($types, ...$params);
    $s->execute();
    return $s->get_result()->fetch_all(MYSQLI_ASSOC);
}
function qOne($conn, $sql, $types='', ...$params) {
    $r = qAll($conn, $sql, $types, ...$params);
    return $r[0] ?? null;
}

// ── اطلاعات کاربر ──────────────────────────────────────────────────────
$data['user'] = qOne($conn,
    "SELECT id, fullname, email, role, phone, gender, age, height, weight, bmi_value, bmi_status FROM users WHERE id=?",
    'i', $uid
);

if ($role === 'patient') {
    $pid = $uid;

    // ── داده‌های بالینی — ۱۲ رکورد آخر ───────────────────────────────
    $data['clinical'] = qAll($conn,
        "SELECT id, patient_id, record_date,
            fbs, ppg, hba1c,
            bp_systolic, bp_diastolic, heart_rate,
            cholesterol_total, ldl, hdl, triglycerides,
            weight, height,
            ROUND(weight/((height/100)*(height/100)),1) as bmi,
            waist_circumference, creatinine,
            symptoms, medications, notes, status
         FROM clinical_data WHERE patient_id=? ORDER BY record_date DESC LIMIT 12",
        'i', $pid
    );

    // ── تاریخچه BMI ────────────────────────────────────────────────
    $data['bmi_history'] = qAll($conn,
        "SELECT id, user_id, record_date, height, weight, bmi_value, bmi_status
         FROM bmi_records WHERE user_id=? ORDER BY record_date DESC LIMIT 24",
        'i', $pid
    );

    // ── برنامه‌های روزانه — ۱۴ روز ────────────────────────────────
    $data['daily'] = qAll($conn,
        "SELECT id, patient_id, plan_date, soc_stage, activities, medication, diet, notes, completed
         FROM daily_plan WHERE patient_id=? AND plan_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
         ORDER BY plan_date DESC",
        'i', $pid
    );

    // ── ارزیابی‌های SOC ─────────────────────────────────────────────
    $data['soc'] = qAll($conn,
        "SELECT id, patient_id, assessment_date, stage, stage_duration, main_barrier, comments
         FROM soc_assessment WHERE patient_id=? ORDER BY assessment_date DESC LIMIT 10",
        'i', $pid
    );

    // ── ارزیابی‌های ریسک ────────────────────────────────────────────
    $data['risk'] = qAll($conn,
        "SELECT id, patient_id, assessment_date, risk_score, risk_level,
                risk_probability, population_prev, relative_risk, factors, recommendations
         FROM risk_assessment WHERE patient_id=? ORDER BY assessment_date DESC LIMIT 10",
        'i', $pid
    );

    // ── پیشرفت ─────────────────────────────────────────────────────
    $data['progress'] = qAll($conn,
        "SELECT id, patient_id, record_date, risk_score, soc_stage, compliance_pct,
                progress_notes, status, next_steps
         FROM progress WHERE patient_id=? ORDER BY record_date DESC LIMIT 12",
        'i', $pid
    );

    // ── اعلانات — ۳۰ مورد آخر ────────────────────────────────────
    $data['notifications'] = qAll($conn,
        "SELECT id, user_id, type, title, message, url, is_read, created_at
         FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 30",
        'i', $uid
    );

    // ── مقایسه همتا ─────────────────────────────────────────────────
    $data['peer'] = qAll($conn,
        "SELECT pc.id, CONCAT('کاربر ', (pc.peer_id + 100)) as peer_name,
                pc.comparison_data, pc.created_at
         FROM peer_compare pc WHERE pc.patient_id=? ORDER BY pc.created_at DESC LIMIT 12",
        'i', $pid
    );

} elseif ($role === 'doctor') {
    $did = $uid;

    // ── بیماران پزشک ────────────────────────────────────────────────
    $data['patients'] = qAll($conn,
        "SELECT u.id, u.fullname, u.age, u.gender, u.phone, u.bmi_value,
            (SELECT risk_level FROM risk_assessment WHERE patient_id=u.id ORDER BY assessment_date DESC LIMIT 1) as risk_level,
            (SELECT stage FROM soc_assessment WHERE patient_id=u.id ORDER BY assessment_date DESC LIMIT 1) as soc_stage,
            (SELECT record_date FROM clinical_data WHERE patient_id=u.id ORDER BY record_date DESC LIMIT 1) as last_clinical
         FROM users u WHERE u.role='patient' ORDER BY u.fullname"
    );

    // ── داده‌های در انتظار تأیید ────────────────────────────────────
    $data['pending_approvals'] = qAll($conn,
        "SELECT da.id, da.patient_id, da.data_type, da.data_id, da.approval_status, da.created_at,
                u.fullname as patient_name,
                cd.fbs, cd.hba1c, cd.bp_systolic, cd.bp_diastolic, cd.cholesterol_total, cd.weight
         FROM data_approval da
         JOIN users u ON da.patient_id = u.id
         LEFT JOIN clinical_data cd ON cd.id = da.data_id AND da.data_type='clinical_data'
         WHERE da.approval_status='pending' ORDER BY da.created_at DESC LIMIT 20"
    );

    // ── هشدارها ─────────────────────────────────────────────────────
    $data['alerts'] = qAll($conn,
        "SELECT a.id, a.patient_id, a.type, a.message, a.status, a.created_at,
                u.fullname as patient_name
         FROM alerts a JOIN users u ON a.patient_id=u.id
         WHERE a.status='open' ORDER BY a.created_at DESC LIMIT 20"
    );

    $data['notifications'] = qAll($conn,
        "SELECT id, user_id, type, title, message, url, is_read, created_at
         FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 20",
        'i', $uid
    );
}

// ── داده‌های NCD-RisC — برای همه (مرجع جمعیتی) ────────────────────────
$data['ncd_risc'] = qAll($conn,
    "SELECT indicator, year, sex, value, lower_95ci, upper_95ci, unit
     FROM ncd_risc_iran ORDER BY indicator, sex, year"
);

echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
