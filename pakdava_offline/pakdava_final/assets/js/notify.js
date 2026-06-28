/**
 * PakDava Notifications v4.0
 * یادآور دارو + هشدار عدم تمکین + اعلانات آفلاین
 */
const PakDavaNotify = (() => {

  // ── نمایش Notification محلی ─────────────────────────────────────────
  function local(title, options = {}) {
    if (!('Notification' in window) || Notification.permission !== 'granted') return;
    const defaults = {
      icon:    '../assets/icons/icon-192.svg',
      badge:   '../assets/icons/icon-72.svg',
      vibrate: [200, 100, 200],
      tag:     'pakdava-local',
      data:    { url: '../patient/notifications.php' },
      lang:    'fa',
    };
    return new Notification(title, { ...defaults, ...options });
  }

  // ── یادآور دارو با setTimeout ────────────────────────────────────────
  const _timers = [];
  function scheduleMedication(timeStr, drugName) {
    const [h, m] = timeStr.split(':').map(Number);
    const now    = new Date();
    const target = new Date();
    target.setHours(h, m, 0, 0);
    if (target <= now) target.setDate(target.getDate() + 1);

    const delay = target - now;
    const tid = setTimeout(async () => {
      local(`💊 ${drugName}`, {
        body:               `زمان مصرف ${drugName} فرا رسیده است`,
        tag:                'medication-' + drugName,
        vibrate:            [200, 100, 200, 100, 200],
        requireInteraction: true,
        actions: [
          { action: 'done',   title: '✓ مصرف کردم' },
          { action: 'snooze', title: '⏰ ۱۵ دقیقه دیگر' },
        ],
      });

      // ثبت در IDB برای tracking
      if (typeof PakDavaDB !== 'undefined') {
        const notifs = await PakDavaDB.getAll('notifications').catch(() => []);
        // فقط لاگ محلی — نیازی به sync ندارد
        console.log('[Notify] Medication reminder fired:', drugName);
      }

      // برنامه برای فردا
      scheduleMedication(timeStr, drugName);
    }, delay);

    _timers.push(tid);
    console.log(`[Notify] Scheduled "${drugName}" at ${timeStr} (in ${Math.round(delay/60000)}min)`);
  }

  // ── هشدار عدم تمکین ──────────────────────────────────────────────────
  async function checkCompliance() {
    const lastStr = localStorage.getItem('pd-last-active');
    if (!lastStr) return;
    const daysSince = (Date.now() - parseInt(lastStr)) / 86400000;

    if (daysSince >= 2) {
      local('⚠️ هشدار انحراف از برنامه', {
        body:               `${Math.floor(daysSince)} روز است برنامه را تکمیل نکرده‌اید. برگردید!`,
        tag:                'non-compliance',
        requireInteraction: true,
        vibrate:            [200, 100, 200, 100, 200],
      });
    }
  }

  function markActive() {
    localStorage.setItem('pd-last-active', Date.now().toString());
  }

  // ── بررسی اعلانات آفلاین از IDB ──────────────────────────────────────
  async function checkOfflineNotifications() {
    if (!navigator.onLine || typeof PakDavaDB === 'undefined') return;
    try {
      const notifs = await PakDavaDB.getAll('notifications');
      const unread = notifs.filter(n => !n.is_read);
      if (unread.length > 0) {
        local(`${unread.length} اعلان جدید`, {
          body: unread.slice(0,2).map(n => n.title || n.message).join(' | '),
          tag:  'bulk-notif',
        });
      }
    } catch {}
  }

  // ── init ─────────────────────────────────────────────────────────────
  function init(config = {}) {
    markActive();
    checkCompliance();
    setTimeout(checkOfflineNotifications, 3000);

    // یادآور داروها (از config یا پیش‌فرض)
    const meds = config.medications || [
      { time: '08:00', name: 'متفورمین صبح' },
      { time: '21:00', name: 'متفورمین شب'  },
    ];
    meds.forEach(m => scheduleMedication(m.time, m.name));

    // بررسی تمکین هر ساعت
    setInterval(checkCompliance, 3600000);

    return () => _timers.forEach(t => clearTimeout(t));
  }

  return { init, local, scheduleMedication, checkCompliance, markActive };
})();

window.PakDavaNotify = PakDavaNotify;

document.addEventListener('DOMContentLoaded', () => {
  if (Notification.permission === 'granted') {
    PakDavaNotify.init();
  }
});
