<?php
session_start();
require_once __DIR__ . '/../conn.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'doctor' && $_SESSION['role'] !== 'expert')) {
    http_response_code(403);
    exit('Unauthorized');
}

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'شناسه نامعتبر']);
    exit;
}

// بررسی اینکه پزشک دسترسی به این داده را دارد
$doctor_id = $_SESSION['user_id'];
try {
    // بررسی وجود جدول doctor_patients
    $check_table = $conn->query("SHOW TABLES LIKE 'doctor_patients'");
    $has_doctor_patients = $check_table && $check_table->num_rows > 0;
    
    if ($has_doctor_patients) {
        // بررسی اینکه بیمار این داده تحت نظر این پزشک است
        $sql = "SELECT c.* FROM clinical_data c 
                JOIN doctor_patients dp ON c.patient_id = dp.patient_id 
                WHERE c.id = ? AND dp.doctor_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $id, $doctor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'msg' => 'دسترسی غیرمجاز']);
            exit;
        }
        $stmt->close();
    } else {
        // بدون بررسی دسترسی (همه داده‌ها قابل مشاهده)
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'خطای سرور']);
    exit;
}

// دریافت داده
$sql = "SELECT * FROM clinical_data WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'msg' => 'داده یافت نشد']);
    exit;
}
$row = $result->fetch_assoc();
$stmt->close();

header('Content-Type: application/json');
echo json_encode(['ok' => true, 'data' => $row]);