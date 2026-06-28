<?php
/**
 * api/push_subscribe.php
 * ذخیره Push Subscription در دیتابیس
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    http_response_code(403); echo json_encode(['error'=>'Forbidden']); exit;
}

require_once __DIR__ . '/../conn.php';

$sub     = json_decode(file_get_contents('php://input'), true);
$user_id = (int)$_SESSION['user_id'];

if (!$sub || empty($sub['endpoint'])) {
    echo json_encode(['success' => false]); exit;
}

$endpoint = $sub['endpoint'];
$p256dh   = $sub['keys']['p256dh']  ?? '';
$auth     = $sub['keys']['auth']    ?? '';
$sub_json = json_encode($sub);

// ذخیره یا به‌روزرسانی
$stmt = $conn->prepare("
    INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth, subscription_json)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE p256dh=VALUES(p256dh), auth=VALUES(auth), subscription_json=VALUES(subscription_json)
");
if ($stmt) {
    $stmt->bind_param('issss', $user_id, $endpoint, $p256dh, $auth, $sub_json);
    $stmt->execute();
    $stmt->close();
}

echo json_encode(['success' => true]);
?>
