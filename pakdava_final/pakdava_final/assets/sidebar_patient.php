<?php
$current = basename($_SERVER['PHP_SELF'], '.php');
$unread = 0;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $unread = $res['cnt'] ?? 0;
    $stmt->close();
}
?>
<aside class="sidebar">
  <div class="sidebar-logo">
    <span class="sidebar-logo-icon">💊</span>
    <div>
      <div class="sidebar-logo-text">پک دوا</div>
      <div class="sidebar-logo-sub">مدیریت دیابت</div>
    </div>
  </div>
  <div class="sidebar-user">
    <div class="user-avatar">🤒</div>
    <div>
<div class="user-name"><?= htmlspecialchars($_SESSION['name'] ?? $_SESSION['fullname'] ?? 'کاربر') ?></div>>
      <div class="user-role">بیمار</div>
    </div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section-title">منو اصلی</div>
    <a href="dashboard.php" class="nav-item <?= $current==='dashboard'?'active':'' ?>">
      <span class="nav-icon">🏠</span> داشبورد
    </a>
    <a href="notifications.php" class="nav-item <?= $current==='notifications'?'active':'' ?>">
      <span class="nav-icon">🔔</span> اعلانات
      <?php if ($unread): ?><span class="nav-badge"><?= $unread ?></span><?php endif; ?>
    </a>
    <a href="risk_assessment.php" class="nav-item <?= $current==='risk_assessment'?'active':'' ?>">
      <span class="nav-icon">📊</span> ارزیابی ریسک
    </a>
    <a href="soc_assessment.php" class="nav-item <?= $current==='soc_assessment'?'active':'' ?>">
      <span class="nav-icon">🔄</span> مرحله تغییر (SOC)
    </a>
    <a href="daily_plan.php" class="nav-item <?= $current==='daily_plan'?'active':'' ?>">
      <span class="nav-icon">📋</span> برنامه روزانه
    </a>
    <a href="clinical_data.php" class="nav-item <?= $current==='clinical_data'?'active':'' ?>">
      <span class="nav-icon">🧪</span> داده‌های بالینی
    </a>
    <a href="progress.php" class="nav-item <?= $current==='progress'?'active':'' ?>">
      <span class="nav-icon">📈</span> پیشرفت من
    </a>
    <div class="nav-section-title">آمار و مقایسه</div>
    <a href="population_data.php" class="nav-item <?= $current==='population_data'?'active':'' ?>">
      <span class="nav-icon">🌍</span> داده‌های NCD-RisC ایران
    </a>
    <a href="peer_compare.php" class="nav-item <?= $current==='peer_compare'?'active':'' ?>">
      <span class="nav-icon">👥</span> مقایسه با همتایان
    </a>
  </nav>
  <div class="sidebar-footer">
    <a href="../api/auth.php?action=logout" class="btn-logout">
      <span>🚪</span> خروج
    </a>
  </div>
</aside>