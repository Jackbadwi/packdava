<?php
/**
 * api/cache_status.php
 * وضعیت آفلاین/آنلاین + اطلاعات کش برای SW
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$status = [
    'online'      => true,
    'server_time' => date('Y-m-d H:i:s'),
    'session'     => isset($_SESSION['user_id']),
    'version'     => '4.0',
];

if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../conn.php';
    $uid = (int)$_SESSION['user_id'];

    // تعداد اعلانات خوانده‌نشده
    $r = $conn->query("SELECT COUNT(*) as c FROM notifications WHERE user_id=$uid AND is_read=0");
    $status['unread_notifications'] = $r ? $r->fetch_assoc()['c'] : 0;

    // تعداد داده‌های در انتظار تأیید
    if ($_SESSION['role'] === 'doctor') {
        $r2 = $conn->query("SELECT COUNT(*) as c FROM data_approval WHERE approval_status='pending'");
        $status['pending_approvals'] = $r2 ? $r2->fetch_assoc()['c'] : 0;
    }
}

echo json_encode($status);
?>
