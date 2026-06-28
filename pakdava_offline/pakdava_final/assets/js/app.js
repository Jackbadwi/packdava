/**
 * PakDava App Shell v4.0
 * ═══════════════════════════════════════════════════════════════
 * مسئولیت‌ها:
 *   1. ثبت Service Worker
 *   2. نصب PWA (Install Banner)
 *   3. نوار وضعیت اتصال
 *   4. دکمه Sync در topbar
 *   5. درخواست مجوز Push
 *   6. بارگذاری داده آفلاین در UI (data-bind)
 */

// ── 1. ثبت Service Worker ────────────────────────────────────────────────
if ('serviceWorker' in navigator) {
  const swPath = _getRootPath() + 'sw.js';
  const swScope = _getRootPath() || '/';

  window.addEventListener('load', async () => {
    try {
      const reg = await navigator.serviceWorker.register(swPath, { scope: swScope });
      console.log('[App] SW registered, scope:', reg.scope);

      // بررسی آپدیت
      reg.addEventListener('updatefound', () => {
        const nw = reg.installing;
        nw.addEventListener('statechange', () => {
          if (nw.state === 'installed' && navigator.serviceWorker.controller) {
            showToast('🔄 نسخه جدید موجود است — صفحه را رفرش کنید', 8000);
          }
        });
      });

      // pre-cache صفحات PHP در background
      if (navigator.onLine) {
        _preCachePages(reg);
      }
    } catch (err) {
      console.warn('[App] SW registration failed:', err.message);
    }
  });
}

// pre-cache تمام صفحات PHP
async function _preCachePages(reg) {
  const root = _getRootPath();
  const pages = [
    root + 'index.php',
    root + 'patient/dashboard.php',
    root + 'patient/daily_plan.php',
    root + 'patient/risk_assessment.php',
    root + 'patient/soc_assessment.php',
    root + 'patient/clinical_data.php',
    root + 'patient/progress.php',
    root + 'patient/notifications.php',
    root + 'patient/peer_compare.php',
    root + 'patient/population_data.php',
    root + 'patient/user.php',
    root + 'doctor/dashboard.php',
    root + 'doctor/enter_clinical.php',
    root + 'doctor/approve_data.php',
    root + 'doctor/patients.php',
    root + 'doctor/alerts.php',
    root + 'doctor/reports.php',
  ];

  // کش در Cache API (برای SW استفاده)
  try {
    const cache = await caches.open('pakdava-pages-v4');
    for (const url of pages) {
      try {
        const res = await fetch(url, { credentials: 'include' });
        if (res.ok) await cache.put(url, res);
      } catch { /* skip */ }
    }
    console.log('[App] PHP pages pre-cached');
  } catch (err) {
    console.warn('[App] Pre-cache failed:', err);
  }
}

// ── 2. Install Banner ─────────────────────────────────────────────────────
let _installPrompt = null;

window.addEventListener('beforeinstallprompt', e => {
  e.preventDefault();
  _installPrompt = e;
  const dismissed = localStorage.getItem('pd-install-dismissed');
  if (!dismissed || Date.now() - parseInt(dismissed) > 86400000 * 7) {
    _showInstallBanner();
  }
});

window.addEventListener('appinstalled', () => {
  _installPrompt = null;
  _hideInstallBanner();
  showToast('✅ پک دوا روی دستگاه نصب شد!');
});

function _showInstallBanner() {
  if (document.getElementById('pd-install') || _isPWA()) return;
  const b = document.createElement('div');
  b.id = 'pd-install';
  b.innerHTML = `
    <div style="display:flex;align-items:center;gap:12px;flex:1">
      <span style="font-size:28px">💊</span>
      <div><div style="font-weight:700;font-size:13px">نصب پک دوا</div>
      <div style="font-size:11px;opacity:.85">آفلاین کار می‌کند | بدون نیاز به اپ‌استور</div></div>
    </div>
    <button id="pd-install-btn" style="background:#fff;color:#1A7A4A;border:none;padding:8px 16px;border-radius:8px;font-weight:700;font-size:13px;cursor:pointer;font-family:Vazirmatn,Tahoma">نصب</button>
    <button id="pd-install-x"  style="background:transparent;border:none;color:rgba(255,255,255,.7);font-size:22px;cursor:pointer;line-height:1">×</button>`;
  Object.assign(b.style, {
    position:'fixed',bottom:'0',left:'0',right:'0',
    background:'linear-gradient(135deg,#1A7A4A,#0D5C36)',
    color:'#fff',padding:'14px 20px',
    display:'flex',alignItems:'center',gap:'12px',
    zIndex:'1000',boxShadow:'0 -4px 20px rgba(0,0,0,.15)',
    fontFamily:'Vazirmatn,Tahoma',direction:'rtl',
  });
  document.body.appendChild(b);
  document.getElementById('pd-install-btn').onclick = _installPWA;
  document.getElementById('pd-install-x').onclick   = _hideInstallBanner;
}

function _hideInstallBanner() {
  const b = document.getElementById('pd-install');
  if (b) b.remove();
  localStorage.setItem('pd-install-dismissed', Date.now());
}

async function _installPWA() {
  if (!_installPrompt) return;
  _installPrompt.prompt();
  const { outcome } = await _installPrompt.userChoice;
  _installPrompt = null;
  _hideInstallBanner();
  if (outcome === 'accepted') showToast('✅ در حال نصب...');
}

function _isPWA() {
  return window.matchMedia('(display-mode: standalone)').matches ||
         window.navigator.standalone ||
         document.referrer.includes('android-app://');
}

// ── 3. نوار وضعیت اتصال ──────────────────────────────────────────────────
function _initConnectionBar() {
  let bar = document.getElementById('connection-status');
  if (!bar) {
    bar = document.createElement('div');
    bar.id = 'connection-status';
    Object.assign(bar.style, {
      position:'fixed', bottom:'0', right:'16px',
      padding:'4px 12px',
      borderRadius:'8px 8px 0 0',
      fontSize:'11px', fontWeight:'700', color:'#fff',
      fontFamily:'Vazirmatn,Tahoma',
      zIndex:'500', transition:'background .3s',
    });
    document.body.appendChild(bar);
  }
  _setConnectionUI(navigator.onLine);
}

function _setConnectionUI(online) {
  document.body.classList.toggle('is-offline', !online);
  const bar = document.getElementById('connection-status');
  if (bar) {
    bar.textContent = online ? '🟢 آنلاین' : '🔴 آفلاین';
    bar.style.background = online ? '#1A7A4A' : '#E74C3C';
  }
}

// ── 4. دکمه Sync در topbar ───────────────────────────────────────────────
function _addSyncButton() {
  const topbar = document.querySelector('.topbar-actions');
  if (!topbar || document.getElementById('pd-sync-btn')) return;

  const btn = document.createElement('button');
  btn.id        = 'pd-sync-btn';
  btn.className = 'btn btn-outline btn-sm';
  btn.style.gap = '6px';
  btn.innerHTML = `🔄 <span id="sync-badge" style="display:none;background:#E74C3C;color:#fff;padding:1px 7px;border-radius:99px;font-size:10px;margin-right:4px;"></span>`;
  btn.title = 'همگام‌سازی با سرور';

  btn.onclick = async () => {
    btn.disabled    = true;
    btn.textContent = '⏳';
    if (typeof PakDavaSync !== 'undefined') await PakDavaSync.syncAll();
    btn.disabled    = false;
    btn.innerHTML   = `🔄 <span id="sync-badge" style="display:none;background:#E74C3C;color:#fff;padding:1px 7px;border-radius:99px;font-size:10px;margin-right:4px;"></span>`;
    if (typeof PakDavaSync !== 'undefined') PakDavaSync.updateBadge();
  };

  topbar.insertBefore(btn, topbar.firstChild);
}

// ── 5. CSS آفلاین ────────────────────────────────────────────────────────
function _injectOfflineCSS() {
  const s = document.createElement('style');
  s.textContent = `
    body.is-offline .topbar { border-bottom: 2px solid #E74C3C !important; }
    body.is-offline .topbar::after {
      content: '🔴 حالت آفلاین — داده‌ها روی دستگاه ذخیره می‌شوند';
      display: block; font-size: 11px; font-weight: 600;
      color: #E74C3C; padding: 4px 28px;
      background: #FEF2F2; border-bottom: 1px solid #FECACA;
      font-family: Vazirmatn,Tahoma;
    }
    .offline-badge {
      display: inline-block; background: #E74C3C; color: #fff;
      padding: 2px 8px; border-radius: 99px; font-size: 10px;
      font-weight: 700; margin-right: 6px;
    }
  `;
  document.head.appendChild(s);
}

// ── 6. Data Binding از IDB ───────────────────────────────────────────────
// هنگامی که صفحه از کش بارگذاری می‌شود، داده‌ها از IDB به UI bind می‌شوند
async function _bindOfflineData() {
  // فقط اگر IDB موجود باشد
  if (typeof PakDavaDB === 'undefined') return;

  // رویداد data-ready (از sync.js)
  document.addEventListener('pakdava:data-ready', e => {
    const d = e.detail;
    _updateDashboardUI(d);
  });

  // بارگذاری از IDB برای نمایش فوری (حتی آفلاین)
  try {
    const clinical = await PakDavaDB.getAll('clinical_data');
    const daily    = await PakDavaDB.getAll('daily_plans');
    const soc      = await PakDavaDB.getAll('soc_records');
    const risk     = await PakDavaDB.getAll('risk_records');
    const notifs   = await PakDavaDB.getAll('notifications');
    const user     = await PakDavaDB.getUserState();

    if (user && user.fullname) {
      document.querySelectorAll('.user-name').forEach(el => {
        if (el.textContent.trim() === 'کاربر') el.textContent = user.fullname;
      });
    }

    // dispatch برای صفحات که به این داده نیاز دارند
    document.dispatchEvent(new CustomEvent('pakdava:idb-loaded', {
      detail: { clinical, daily, soc, risk, notifications: notifs, user }
    }));

    // نمایش badge داده آفلاین اگر صفحه از کش است
    if (!navigator.onLine) {
      const latest = clinical[0];
      if (latest) {
        console.log('[App] Showing offline data:', latest);
      }
    }
  } catch (err) {
    console.warn('[App] IDB bind error:', err);
  }
}

function _updateDashboardUI(data) {
  // به‌روزرسانی اعلانات badge
  const unread = (data.notifications || []).filter(n => !n.is_read).length;
  document.querySelectorAll('.notif-count').forEach(el => {
    el.textContent = unread > 0 ? unread : '';
    el.style.display = unread > 0 ? 'inline' : 'none';
  });
}

// ── 7. Push Notification Permission ──────────────────────────────────────
async function _requestPushPermission() {
  if (!('Notification' in window)) return;
  if (Notification.permission === 'granted') { _subscribePush(); return; }
  if (Notification.permission === 'denied')  return;

  // فقط بعد از تعامل کاربر درخواست کن
  const perm = await Notification.requestPermission();
  if (perm === 'granted') {
    showToast('🔔 اعلانات فعال شد');
    _subscribePush();
  }
}

async function _subscribePush() {
  try {
    const reg = await navigator.serviceWorker.ready;
    const existing = await reg.pushManager.getSubscription();
    if (existing) return;

    // در production: VAPID key واقعی جایگزین شود
    const VAPID = 'BEl62iUYgUivxIkv69yViEuiBIa-Ib9-SkvMeAtA3LFgDzkrxZJjSgSnfckjBJuBkr3qBUYIHBQFLXYp5Nksh8U';
    const key   = _vapidKey(VAPID);

    const sub = await reg.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: key,
    }).catch(() => null);

    if (sub) {
      const root = _getRootPath();
      fetch(root + 'api/push_subscribe.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(sub),
      }).catch(() => {});
    }
  } catch (err) {
    console.warn('[App] Push subscribe error:', err.message);
  }
}

function _vapidKey(b64) {
  const pad = '='.repeat((4 - b64.length % 4) % 4);
  const raw = atob((b64 + pad).replace(/-/g, '+').replace(/_/g, '/'));
  return Uint8Array.from([...raw].map(c => c.charCodeAt(0)));
}

// ── helper: مسیر root ────────────────────────────────────────────────────
function _getRootPath() {
  const p = window.location.pathname;
  if (p.includes('/patient/') || p.includes('/doctor/')) return '../';
  return './';
}

// ── DOMContentLoaded ──────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  _injectOfflineCSS();
  _initConnectionBar();
  _addSyncButton();

  // Init sync engine
  if (typeof PakDavaSync !== 'undefined') {
    PakDavaSync.init();
  }

  // Bind IDB data
  _bindOfflineData();

  // Push permission (با تأخیر)
  setTimeout(_requestPushPermission, 6000);
});
