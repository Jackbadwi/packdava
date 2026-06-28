/**
 * PakDava PWA — app.js
 * ثبت Service Worker | نصب PWA | مدیریت آفلاین
 */

// ── Service Worker Registration ───────────────────────────────────────────
if ('serviceWorker' in navigator) {
  window.addEventListener('load', async () => {
    try {
      const reg = await navigator.serviceWorker.register('../sw.js', { scope: '../' });
      console.log('[App] SW registered:', reg.scope);

      // به‌روزرسانی خودکار SW
      reg.addEventListener('updatefound', () => {
        const newSW = reg.installing;
        newSW.addEventListener('statechange', () => {
          if (newSW.state === 'installed' && navigator.serviceWorker.controller) {
            showToast('🔄 نسخه جدید در دسترس است — صفحه را رفرش کنید', 8000);
          }
        });
      });
    } catch (err) {
      console.error('[App] SW registration failed:', err);
    }
  });
}

// ── Install Prompt (Add to Home Screen) ──────────────────────────────────
let deferredPrompt = null;

window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault();
  deferredPrompt = e;
  showInstallBanner();
});

window.addEventListener('appinstalled', () => {
  deferredPrompt = null;
  hideInstallBanner();
  showToast('✅ پک دوا روی دستگاه نصب شد!');
});

function showInstallBanner() {
  const existing = document.getElementById('install-banner');
  if (existing || isPWA()) return;

  const banner = document.createElement('div');
  banner.id = 'install-banner';
  banner.innerHTML = `
    <div style="display:flex;align-items:center;gap:12px;flex:1">
      <span style="font-size:24px">💊</span>
      <div>
        <div style="font-weight:700;font-size:13px">نصب پک دوا روی گوشی</div>
        <div style="font-size:11px;opacity:0.85">آفلاین کار می‌کند — مثل یک اپ واقعی</div>
      </div>
    </div>
    <button onclick="installPWA()" style="background:white;color:var(--green);border:none;padding:8px 16px;border-radius:8px;font-weight:700;font-size:13px;cursor:pointer;font-family:Vazirmatn,Tahoma">نصب</button>
    <button onclick="dismissInstall()" style="background:transparent;border:none;color:rgba(255,255,255,0.7);font-size:20px;cursor:pointer;padding:4px 8px">×</button>
  `;
  banner.style.cssText = `
    position:fixed;bottom:0;left:0;right:0;
    background:linear-gradient(135deg,var(--green),#0D5C36);
    color:white;padding:14px 20px;display:flex;align-items:center;gap:12px;
    z-index:1000;box-shadow:0 -4px 20px rgba(0,0,0,0.15);
    font-family:Vazirmatn,Tahoma;direction:rtl;
  `;
  document.body.appendChild(banner);
}

function hideInstallBanner() {
  const b = document.getElementById('install-banner');
  if (b) b.remove();
}

async function installPWA() {
  if (!deferredPrompt) return;
  deferredPrompt.prompt();
  const { outcome } = await deferredPrompt.userChoice;
  console.log('[App] Install outcome:', outcome);
  deferredPrompt = null;
  hideInstallBanner();
}

function dismissInstall() {
  hideInstallBanner();
  localStorage.setItem('install-dismissed', Date.now());
}

function isPWA() {
  return window.matchMedia('(display-mode: standalone)').matches
    || window.navigator.standalone
    || document.referrer.includes('android-app://');
}

// ── Connection Status Bar ─────────────────────────────────────────────────
function initConnectionBar() {
  const bar = document.createElement('div');
  bar.id = 'connection-status';
  bar.style.cssText = `
    position:fixed;bottom:${isPWA()?'env(safe-area-inset-bottom, 0px)':'0'};
    right:16px;
    padding:5px 12px;border-radius:99px 99px 0 0;
    font-size:11px;font-weight:700;color:white;
    font-family:Vazirmatn,Tahoma;z-index:500;
    transition:background 0.3s;
    display:flex;align-items:center;gap:6px;
  `;
  document.body.appendChild(bar);
  updateConnectionUI(navigator.onLine);
}

// ── Push Notification Permission ──────────────────────────────────────────
async function requestPushPermission() {
  if (!('Notification' in window)) return;
  if (Notification.permission === 'granted') return subscribeToNotifications();
  if (Notification.permission === 'denied') return;

  const permission = await Notification.requestPermission();
  if (permission === 'granted') {
    subscribeToNotifications();
    showToast('🔔 اعلانات فعال شد');
  }
}

async function subscribeToNotifications() {
  try {
    const reg = await navigator.serviceWorker.ready;
    const existing = await reg.pushManager.getSubscription();
    if (existing) return;

    // در production: VAPID public key واقعی جایگزین می‌شود
    const VAPID_PUBLIC = 'BEl62iUYgUivxIkv69yViEuiBIa-Ib9-SkvMeAtA3LFgDzkrxZJjSgSnfckjBJuBkr3qBUYIHBQFLXYp5Nksh8U';

    try {
      const sub = await reg.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC)
      });
      // ارسال subscription به سرور
      await fetch('../api/push_subscribe.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(sub)
      });
    } catch {
      // VAPID key demo — در production جایگزین شود
    }
  } catch (err) {
    console.warn('[App] Push subscription error:', err);
  }
}

function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - base64String.length % 4) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const rawData = window.atob(base64);
  return Uint8Array.from([...rawData].map(c => c.charCodeAt(0)));
}

// ── Sync Button ───────────────────────────────────────────────────────────
function addSyncButton() {
  const topbar = document.querySelector('.topbar-actions');
  if (!topbar) return;

  const btn = document.createElement('button');
  btn.className = 'btn btn-outline btn-sm';
  btn.innerHTML = '🔄 همگام‌سازی <span id="sync-badge" style="display:none;background:var(--red);color:white;padding:1px 6px;border-radius:99px;font-size:10px;margin-right:4px"></span>';
  btn.onclick = async () => {
    btn.disabled = true;
    btn.innerHTML = '⏳ در حال همگام‌سازی...';
    await PakDavaSync.syncAll();
    btn.disabled = false;
    btn.innerHTML = '🔄 همگام‌سازی <span id="sync-badge" style="display:none;background:var(--red);color:white;padding:1px 6px;border-radius:99px;font-size:10px;margin-right:4px"></span>';
    PakDavaSync.updateSyncBadge();
  };
  topbar.insertBefore(btn, topbar.firstChild);
}

// ── Offline Banner on pages ───────────────────────────────────────────────
function addOfflineCSS() {
  const style = document.createElement('style');
  style.textContent = `
    body.is-offline .topbar { border-bottom-color: var(--red) !important; }
    body.is-offline .topbar::after {
      content: '🔴 حالت آفلاین — داده‌ها ذخیره می‌شوند';
      display:block;font-size:11px;font-weight:600;color:var(--red);
      padding:4px 28px;background:#FEF2F2;border-bottom:1px solid #FECACA;
      font-family:Vazirmatn,Tahoma;
    }
  `;
  document.head.appendChild(style);
}

// ── DOMContentLoaded ──────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  addOfflineCSS();
  initConnectionBar();
  addSyncButton();

  // Init sync manager
  if (typeof PakDavaSync !== 'undefined') {
    PakDavaSync.init();
    // بارگذاری داده‌های تازه از سرور برای کش آفلاین
    if (navigator.onLine) {
      PakDavaSync.fetchFreshData();
    }
  }

  // درخواست مجوز Push (بعد از ۵ ثانیه)
  setTimeout(() => requestPushPermission(), 5000);
});
