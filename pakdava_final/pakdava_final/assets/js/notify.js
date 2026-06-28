/**
 * PakDava Notifications Manager
 * مدیریت اعلانات Push و محلی
 */
const PakDavaNotify = (() => {

  // ── نمایش notification محلی (بدون push server) ────────────────────────
  function local(title, options = {}) {
    if (!('Notification' in window) || Notification.permission !== 'granted') return;
    const defaults = {
      icon:   '../assets/icons/icon-192.svg',
      badge:  '../assets/icons/icon-72.svg',
      vibrate:[200, 100, 200],
      tag:    'pakdava-local',
      data:   { url: '../patient/notifications.php' }
    };
    return new Notification(title, { ...defaults, ...options });
  }

  // ── یادآور دارو ────────────────────────────────────────────────────────
  function scheduleMedication(time, drugName) {
    const [h, m] = time.split(':').map(Number);
    const now    = new Date();
    const target = new Date();
    target.setHours(h, m, 0, 0);
    if (target < now) target.setDate(target.getDate() + 1);

    const delay = target - now;
    setTimeout(() => {
      local(`💊 یادآور دارو — ${drugName}`, {
        body:    `زمان مصرف ${drugName} فرا رسیده است`,
        tag:     'medication',
        vibrate: [200, 100, 200, 100, 200],
        requireInteraction: true,
        actions: [
          { action: 'done',    title: '✓ مصرف کردم' },
          { action: 'snooze',  title: '⏰ ۱۵ دقیقه دیگر' }
        ]
      });
      scheduleMedication(time, drugName); // reschedule برای روز بعد
    }, delay);

    console.log(`[Notify] Scheduled "${drugName}" at ${time} (in ${Math.round(delay/60000)} min)`);
  }

  // ── هشدار عدم تمکین ────────────────────────────────────────────────────
  function checkCompliance() {
    const lastActive = localStorage.getItem('pakdava-last-active');
    if (!lastActive) return;
    const daysSince = (Date.now() - parseInt(lastActive)) / 86400000;
    if (daysSince >= 2) {
      local('⚠️ هشدار انحراف از برنامه', {
        body: `شما ${Math.floor(daysSince)} روز است برنامه را تکمیل نکرده‌اید. برگردید!`,
        tag: 'warning',
        requireInteraction: true,
        vibrate: [200, 100, 200, 100, 200]
      });
    }
  }

  // ── ذخیره زمان فعالیت ─────────────────────────────────────────────────
  function markActive() {
    localStorage.setItem('pakdava-last-active', Date.now().toString());
  }

  // ── init ──────────────────────────────────────────────────────────────
  function init() {
    markActive();
    // بررسی تمکین هر ۱ ساعت
    setInterval(checkCompliance, 3600000);
    // یادآور داروهای پیش‌فرض
    scheduleMedication('08:00', 'متفورمین صبح');
    scheduleMedication('21:00', 'متفورمین شب');
  }

  return { local, scheduleMedication, checkCompliance, markActive, init };
})();

window.PakDavaNotify = PakDavaNotify;
document.addEventListener('DOMContentLoaded', () => {
  if (Notification.permission === 'granted') PakDavaNotify.init();
});
