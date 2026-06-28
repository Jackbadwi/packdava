<?php
$current = basename($_SERVER['PHP_SELF'], '.php');
?>
<aside class="sidebar">
  <div class="sidebar-logo">
    <span class="sidebar-logo-icon">💊</span>
    <div><div class="sidebar-logo-text">پک دوا</div><div class="sidebar-logo-sub">پنل پزشک</div></div>
  </div>
  <div class="sidebar-user">
    <div class="user-avatar">👨‍⚕️</div>
    <div>
      <div class="user-name"><?= htmlspecialchars($_SESSION['name'] ?? 'پزشک') ?></div>
      <div class="user-role" style="color:var(--blue)">پزشک</div>
    </div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section-title">مدیریت</div>
    <a href="dashboard.php" class="nav-item <?= $current==='dashboard'?'active':'' ?>">
      <span class="nav-icon">🏠</span>داشبورد
    </a>
    <a href="patients.php" class="nav-item <?= $current==='patients'?'active':'' ?>">
      <span class="nav-icon">👥</span>بیماران
    </a>
    <a href="alerts.php" class="nav-item <?= $current==='alerts'?'active':'' ?>">
      <span class="nav-icon">⚠️</span>هشدارها
    </a>
    <div class="nav-section-title">داده‌های بالینی</div>
    <a href="enter_clinical.php" class="nav-item <?= $current==='enter_clinical'?'active':'' ?>">
      <span class="nav-icon">🧪</span>ورود داده بالینی
    </a>
    <a href="approve_data.php" class="nav-item <?= $current==='approve_data'?'active':'' ?>">
      <span class="nav-icon">✅</span>تأیید داده‌ها
    </a>
    <div class="nav-section-title">گزارشات</div>
    <a href="reports.php" class="nav-item <?= $current==='reports'?'active':'' ?>">
      <span class="nav-icon">📊</span>گزارشات
    </a>
  </nav>
  <div class="sidebar-footer">
    <a href="../api/auth.php?action=logout" class="btn-logout"><span>🚪</span>خروج</a>
  </div>
</aside>