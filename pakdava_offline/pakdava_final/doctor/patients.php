<?php
session_start();
require_once __DIR__ . '/../conn.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'doctor' && $_SESSION['role'] !== 'expert')) {
    header('Location: ../index.php');
    exit;
}

// دریافت پارامترهای جستجو و صفحه‌بندی
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// ساخت کوئری برای دریافت بیماران
$where = "WHERE role = 'patient'";
$params = [];
$types = '';

if (!empty($search)) {
    $where .= " AND (fullname LIKE ? OR username LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $searchTerm = "%$search%";
    $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
    $types = 'ssss';
}

// شمارش کل بیماران برای صفحه‌بندی
$countSql = "SELECT COUNT(*) as total FROM users $where";
$stmt = $conn->prepare($countSql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$totalResult = $stmt->get_result();
$totalPatients = $totalResult->fetch_assoc()['total'];
$stmt->close();

$totalPages = ceil($totalPatients / $limit);

// دریافت لیست بیماران
$sql = "SELECT id, fullname, username, email, phone, dob, gender, 
               height, weight, bmi_value, bmi_status, created_at, last_login
        FROM users 
        $where 
        ORDER BY id DESC 
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$params[] = $limit;
$params[] = $offset;
$types .= 'ii';
$stmt->bind_param($types, ...$params);
$stmt->execute();
$patients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// برای هر بیمار، آخرین داده بالینی را دریافت می‌کنیم
foreach ($patients as &$patient) {
    $patient['latest_clinical'] = null;
    try {
        $stmt = $conn->prepare("SELECT record_date, blood_pressure, weight, height, heart_rate FROM clinical_data WHERE patient_id = ? ORDER BY record_date DESC LIMIT 1");
        $stmt->bind_param("i", $patient['id']);
        $stmt->execute();
        $clinical = $stmt->get_result()->fetch_assoc();
        if ($clinical) {
            $patient['latest_clinical'] = $clinical;
        }
        $stmt->close();
    } catch (Exception $e) {
        // اگر جدول clinical_data وجود نداشت، نادیده بگیر
    }
    
    // آخرین مرحله SOC
    $patient['latest_soc'] = null;
    try {
        $stmt = $conn->prepare("SELECT stage, assessment_date FROM soc_assessment WHERE patient_id = ? ORDER BY assessment_date DESC LIMIT 1");
        $stmt->bind_param("i", $patient['id']);
        $stmt->execute();
        $soc = $stmt->get_result()->fetch_assoc();
        if ($soc) {
            $patient['latest_soc'] = $soc;
        }
        $stmt->close();
    } catch (Exception $e) {
        // نادیده بگیر
    }
}
unset($patient);

// نقش‌های SOC برای نمایش
$socLabels = [
    'PC' => 'پیش از تأمل',
    'C' => 'تأمل',
    'PR' => 'آماده‌سازی',
    'A' => 'عمل',
    'M' => 'نگهداری'
];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">  <link rel="manifest" href="../manifest.json">
  <meta name="theme-color" content="#1A7A4A">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="پک دوا">
  <meta name="viewport" content="width=device-width,initial-scale=1">
<title>لیست بیماران | پک دوا</title>
<link rel="stylesheet" href="../assets/css/main.css">
</head>
<body>
<div class="app-layout">
  <?php include '../assets/sidebar_doctor.php'; ?>
  <div class="main-content">
    <div class="topbar">
      <div>
        <div class="topbar-title">لیست بیماران</div>
        <div class="topbar-subtitle">مدیریت و مشاهده اطلاعات بیماران</div>
      </div>
      <div class="topbar-actions">
        <span style="font-size:14px;color:var(--gray-600)">تعداد: <?= number_format($totalPatients) ?> بیمار</span>
      </div>
    </div>
    <div class="page-body">
      <!-- جستجو -->
      <div class="card mb-20">
        <div class="card-body" style="padding:16px 20px">
          <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
            <div style="flex:1;min-width:200px">
              <input type="text" name="search" class="form-control" placeholder="جستجو در نام، ایمیل، تلفن..." value="<?= htmlspecialchars($search) ?>" style="width:100%;padding:8px 14px;border-radius:8px;border:1px solid var(--gray-300)">
            </div>
            <button type="submit" class="btn btn-primary">🔍 جستجو</button>
            <?php if ($search): ?>
              <a href="patients.php" class="btn btn-outline">✕ لغو فیلتر</a>
            <?php endif; ?>
          </form>
        </div>
      </div>

      <!-- جدول بیماران -->
      <div class="card">
        <div class="card-body" style="padding:0;overflow-x:auto">
          <table class="data-table" style="width:100%">
            <thead>
              <tr>
                <th>شناسه</th>
                <th>نام کامل</th>
                <th>نام کاربری</th>
                <th>ایمیل</th>
                <th>تلفن</th>
                <th>سن</th>
                <th>جنسیت</th>
                <th>BMI</th>
                <th>مرحله SOC</th>
                <th>آخرین داده</th>
                <th>عملیات</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($patients) > 0): ?>
                <?php foreach ($patients as $p): ?>
                  <tr>
                    <td><strong>#<?= $p['id'] ?></strong></td>
                    <td><strong><?= htmlspecialchars($p['fullname'] ?? '---') ?></strong></td>
                    <td><?= htmlspecialchars($p['username'] ?? '---') ?></td>
                    <td><?= htmlspecialchars($p['email'] ?? '---') ?></td>
                    <td><?= htmlspecialchars($p['phone'] ?? '---') ?></td>
                    <td>
                      <?php
                      if (!empty($p['dob'])) {
                          $dob = new DateTime($p['dob']);
                          $now = new DateTime();
                          echo $now->diff($dob)->y . ' سال';
                      } else {
                          echo '---';
                      }
                      ?>
                    </td>
                    <td><?= $p['gender'] === 'male' ? 'مرد' : ($p['gender'] === 'female' ? 'زن' : '---') ?></td>
                    <td>
                      <?php if (!empty($p['bmi_value'])): ?>
                        <span style="font-weight:700;color:<?= $p['bmi_value'] >= 30 ? 'var(--red)' : ($p['bmi_value'] >= 25 ? 'var(--orange)' : 'var(--green)') ?>">
                          <?= number_format($p['bmi_value'], 1) ?>
                        </span>
                      <?php else: ?>
                        ---
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($p['latest_soc'] && isset($socLabels[$p['latest_soc']['stage']])): ?>
                        <span style="background:rgba(230,126,34,0.15);padding:2px 10px;border-radius:99px;font-size:11px;font-weight:600">
                          <?= $socLabels[$p['latest_soc']['stage']] ?>
                        </span>
                      <?php else: ?>
                        <span style="color:var(--gray-400);font-size:11px">ثبت نشده</span>
                      <?php endif; ?>
                    </td>
                    <td style="font-size:11px;color:var(--gray-500)">
                      <?php if ($p['latest_clinical']): ?>
                        <?= htmlspecialchars($p['latest_clinical']['record_date']) ?><br>
                        <span style="font-size:10px;color:var(--gray-400)">
                          فشار: <?= htmlspecialchars($p['latest_clinical']['blood_pressure'] ?? '---') ?> |
                          وزن: <?= htmlspecialchars($p['latest_clinical']['weight'] ?? '---') ?>
                        </span>
                      <?php else: ?>
                        ---
                      <?php endif; ?>
                    </td>
                    <td>
                      <div style="display:flex;gap:4px;flex-wrap:wrap">
                        <a href="patient_detail.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline" style="padding:4px 10px;font-size:11px">👁️</a>
                        <a href="enter_clinical.php?patient_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline" style="padding:4px 10px;font-size:11px">🧪</a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="11" style="text-align:center;padding:30px;color:var(--gray-500)">هیچ بیماری یافت نشد.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <!-- صفحه‌بندی -->
        <?php if ($totalPages > 1): ?>
          <div style="padding:16px 20px;border-top:1px solid var(--gray-200);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
            <div style="font-size:12px;color:var(--gray-500)">
              نمایش <?= ($offset + 1) ?> تا <?= min($offset + $limit, $totalPatients) ?> از <?= number_format($totalPatients) ?>
            </div>
            <div style="display:flex;gap:4px">
              <?php if ($page > 1): ?>
                <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>" class="btn btn-sm btn-outline">قبلی</a>
              <?php endif; ?>
              <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php if ($i == $page): ?>
                  <span style="display:inline-block;padding:4px 10px;background:var(--blue);color:white;border-radius:4px;font-size:12px;font-weight:700"><?= $i ?></span>
                <?php elseif ($i <= 3 || $i > $totalPages - 3 || abs($i - $page) <= 2): ?>
                  <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" class="btn btn-sm btn-outline"><?= $i ?></a>
                <?php elseif ($i == 4 || $i == $totalPages - 3): ?>
                  <span style="padding:4px 8px;font-size:12px;color:var(--gray-400)">…</span>
                <?php endif; ?>
              <?php endfor; ?>
              <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>" class="btn btn-sm btn-outline">بعدی</a>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- نیاز به سایدبار پزشک -->
<?php
// ایجاد فایل سایدبار پزشک اگر وجود ندارد
// برای این صفحه از sidebar_doctor.php استفاده می‌کنیم که باید ایجاد شود
// یا می‌توانیم از سایدبار موجود در doctor/dashboard.php استفاده کنیم، اما بهتر است جداگانه باشد.
// در اینجا فرض می‌کنیم assets/sidebar_doctor.php وجود دارد.
// در غیر این صورت، می‌توانیم از همان سایدبار doctor/dashboard.php استفاده کنیم.
// اما برای سادگی، کد سایدبار را درون همین فایل قرار می‌دهیم (اگر نیاز باشد).
// در پروژه اصلی، سایدبار پزشک در assets/sidebar_doctor.php قرار دارد.
// از آنجا که موجود نیست، می‌توانیم از سایدبار مشابه استفاده کنیم.
?>
<script src="../assets/js/db.js"></script>
<script src="../assets/js/sync.js"></script>
<script src="../assets/js/notify.js"></script>
<script src="../assets/js/app.js"></script>
</body>
</html>