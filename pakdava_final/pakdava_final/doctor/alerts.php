<?php
session_start();
require_once __DIR__ . '/../conn.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'doctor' && $_SESSION['role'] !== 'expert')) {
    header('Location: ../index.php');
    exit;
}

$doctor_id = $_SESSION['user_id'];

// دریافت لیست بیماران تحت نظر پزشک
$assigned_patients = [];
try {
    $sql = "SELECT p.id, u.fullname, u.email, u.phone, u.age, u.gender 
            FROM doctor_patients dp 
            JOIN patients p ON dp.patient_id = p.id 
            JOIN users u ON p.user_id = u.id 
            WHERE dp.doctor_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $doctor_id);
    $stmt->execute();
    $assigned_patients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    // اگر جدول وجود نداشت، داده‌های mock
    $assigned_patients = [];
}

$patient_ids = array_column($assigned_patients, 'id');

// دریافت هشدارها
$alerts = [];
if (!empty($patient_ids)) {
    $placeholders = implode(',', array_fill(0, count($patient_ids), '?'));
    $sql = "SELECT a.*, u.fullname as patient_name 
            FROM alerts a 
            JOIN patients p ON a.patient_id = p.id 
            JOIN users u ON p.user_id = u.id 
            WHERE a.patient_id IN ($placeholders) 
            ORDER BY a.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(str_repeat('i', count($patient_ids)), ...$patient_ids);
    $stmt->execute();
    $alerts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// پردازش اقدام روی هشدار
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $alert_id = intval($_POST['alert_id']);
    $status = $_POST['status'] ?? 'resolved';
    $note = $_POST['note'] ?? '';
    
    $stmt = $conn->prepare("UPDATE alerts SET status = ?, resolved_at = NOW(), note = ? WHERE id = ?");
    $stmt->bind_param("ssi", $status, $note, $alert_id);
    $stmt->execute();
    $stmt->close();
    
    header('Location: alerts.php');
    exit;
}

$current = 'alerts';
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
    <title>هشدارها | پک دوا</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .alert-card {
            border-right: 4px solid var(--gray-400);
            transition: all 0.3s;
        }
        .alert-card.critical { border-right-color: #e74c3c; background: #fef2f2; }
        .alert-card.warning { border-right-color: #f39c12; background: #fffbeb; }
        .alert-card.info { border-right-color: #3498db; background: #eff6ff; }
        .alert-card.resolved { border-right-color: #27ae60; background: #f0fdf4; opacity: 0.7; }
        .alert-card:hover { transform: translateX(-4px); box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .alert-badge { display: inline-block; padding: 2px 10px; border-radius: 99px; font-size: 11px; font-weight: 700; }
        .alert-badge.critical { background: #fee2e2; color: #dc2626; }
        .alert-badge.warning { background: #fef3c7; color: #d97706; }
        .alert-badge.info { background: #dbeafe; color: #2563eb; }
        .alert-badge.resolved { background: #d1fae5; color: #059669; }
    </style>
</head>
<body>
<div class="app-layout">
    <?php include '../assets/sidebar_doctor.php'; ?>
    <div class="main-content">
        <div class="topbar">
            <div>
                <div class="topbar-title">هشدارهای بالینی</div>
                <div class="topbar-subtitle">مدیریت هشدارهای بیماران تحت نظر</div>
            </div>
            <div class="topbar-actions">
                <span style="font-size:14px;color:var(--gray-600)">تعداد هشدارها: <?= count($alerts) ?></span>
            </div>
        </div>
        <div class="page-body">
            <!-- فیلترها -->
            <div class="card mb-20">
                <div class="card-body" style="padding:16px 20px">
                    <div class="filter-row" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
                        <select id="statusFilter" class="form-select" style="width:auto;min-width:150px;padding:8px 14px;border-radius:8px;border:1px solid var(--gray-300)">
                            <option value="">همه وضعیت‌ها</option>
                            <option value="critical">بحرانی</option>
                            <option value="warning">هشدار</option>
                            <option value="info">اطلاعاتی</option>
                            <option value="resolved">برطرف شده</option>
                        </select>
                        <select id="patientFilter" class="form-select" style="width:auto;min-width:200px;padding:8px 14px;border-radius:8px;border:1px solid var(--gray-300)">
                            <option value="">همه بیماران</option>
                            <?php foreach ($assigned_patients as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['fullname']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-primary" onclick="applyFilters()">فیلتر</button>
                        <button class="btn btn-outline" onclick="resetFilters()">پاک کردن</button>
                    </div>
                </div>
            </div>

            <!-- لیست هشدارها -->
            <div id="alertsContainer">
                <?php if (empty($alerts)): ?>
                    <div class="card">
                        <div class="card-body text-center" style="padding:40px;color:var(--gray-500)">
                            <i class="fas fa-check-circle" style="font-size:48px;color:var(--green);margin-bottom:16px"></i>
                            <h4>هیچ هشداری وجود ندارد</h4>
                            <p>همه بیماران تحت نظر شما در وضعیت پایدار هستند.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($alerts as $alert): ?>
                        <?php
                        $statusClass = $alert['status'];
                        $statusText = [
                            'critical' => 'بحرانی',
                            'warning' => 'هشدار',
                            'info' => 'اطلاعاتی',
                            'resolved' => 'برطرف شده'
                        ][$statusClass] ?? $statusClass;
                        ?>
                        <div class="card alert-card <?= $statusClass ?> mb-20" data-alert-id="<?= $alert['id'] ?>">
                            <div class="card-body" style="padding:16px 20px">
                                <div style="display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap">
                                    <div style="flex:1;min-width:200px">
                                        <div style="display:flex;align-items:center;gap:12px;margin-bottom:6px">
                                            <span class="alert-badge <?= $statusClass ?>"><?= $statusText ?></span>
                                            <span style="font-size:14px;font-weight:700"><?= htmlspecialchars($alert['patient_name']) ?></span>
                                            <span style="font-size:12px;color:var(--gray-400)"><?= htmlspecialchars($alert['created_at']) ?></span>
                                        </div>
                                        <div style="font-size:14px;color:var(--gray-700);line-height:1.7;margin-bottom:8px">
                                            <?= htmlspecialchars($alert['message']) ?>
                                        </div>
                                        <?php if ($alert['note']): ?>
                                            <div style="font-size:12px;color:var(--gray-500);background:var(--gray-50);padding:8px 12px;border-radius:6px">
                                                <strong>یادداشت پزشک:</strong> <?= htmlspecialchars($alert['note']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div style="display:flex;flex-direction:column;gap:8px;flex-shrink:0">
                                        <?php if ($alert['status'] !== 'resolved'): ?>
                                            <button class="btn btn-green btn-sm" onclick="resolveAlert(<?= $alert['id'] ?>)">
                                                <i class="fas fa-check"></i> برطرف شد
                                            </button>
                                            <button class="btn btn-outline btn-sm" onclick="showNoteModal(<?= $alert['id'] ?>)">
                                                <i class="fas fa-edit"></i> یادداشت
                                            </button>
                                        <?php else: ?>
                                            <span style="font-size:12px;color:var(--green);font-weight:700">✓ برطرف شده</span>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-outline" onclick="viewPatient(<?= $alert['patient_id'] ?>)">
                                            <i class="fas fa-user"></i> پروفایل
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- مودال یادداشت -->
<div id="noteModal" class="modal" style="display:none;position:fixed;z-index:1050;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.5)">
    <div class="modal-content" style="max-width:500px;margin:10% auto;background:white;border-radius:12px;padding:24px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
            <h4>یادداشت پزشک</h4>
            <span class="close" onclick="closeNoteModal()" style="font-size:24px;cursor:pointer">&times;</span>
        </div>
        <form method="POST">
            <input type="hidden" name="alert_id" id="noteAlertId" value="">
            <input type="hidden" name="action" value="add_note">
            <div class="form-group">
                <label class="form-label">متن یادداشت</label>
                <textarea name="note" class="form-control" rows="3" required></textarea>
            </div>
            <div style="display:flex;gap:10px;margin-top:16px">
                <button type="submit" class="btn btn-primary">ذخیره</button>
                <button type="button" class="btn btn-secondary" onclick="closeNoteModal()">انصراف</button>
            </div>
        </form>
    </div>
</div>

<script>
function applyFilters() {
    const status = document.getElementById('statusFilter').value;
    const patient = document.getElementById('patientFilter').value;
    const cards = document.querySelectorAll('.alert-card');
    cards.forEach(card => {
        const cardStatus = card.className.includes('critical') ? 'critical' :
                          card.className.includes('warning') ? 'warning' :
                          card.className.includes('info') ? 'info' :
                          card.className.includes('resolved') ? 'resolved' : '';
        const cardPatient = card.querySelector('.alert-badge')?.closest('div')?.querySelector('span:nth-child(2)')?.textContent || '';
        let show = true;
        if (status && cardStatus !== status) show = false;
        if (patient && cardPatient !== patient) show = false;
        card.style.display = show ? 'block' : 'none';
    });
}

function resetFilters() {
    document.getElementById('statusFilter').value = '';
    document.getElementById('patientFilter').value = '';
    document.querySelectorAll('.alert-card').forEach(c => c.style.display = 'block');
}

function resolveAlert(id) {
    if (confirm('آیا از برطرف شدن این هشدار اطمینان دارید؟')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="alert_id" value="${id}">
            <input type="hidden" name="action" value="resolve">
            <input type="hidden" name="status" value="resolved">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function showNoteModal(id) {
    document.getElementById('noteAlertId').value = id;
    document.getElementById('noteModal').style.display = 'block';
}

function closeNoteModal() {
    document.getElementById('noteModal').style.display = 'none';
}

function viewPatient(id) {
    window.location.href = `patient_detail.php?id=${id}`;
}

// بستن مودال با کلیک خارج از آن
window.onclick = function(event) {
    const modal = document.getElementById('noteModal');
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