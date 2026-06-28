/**
 * PakDava Service Worker v4.0
 * ═══════════════════════════════════════════════════════════════
 * استراتژی: App Shell + Runtime Cache + Background Sync
 *
 * مشکل اصلی PHP + آفلاین:
 *   PHP روی سرور اجرا می‌شود و در حالت آفلاین قابل اجرا نیست.
 *   راه‌حل: کش کردن HTML خروجی PHP در هنگام آنلاین، و سرو از کش در آفلاین.
 *
 * لایه‌های کش:
 *   SHELL  — فایل‌های ایستا (CSS, JS, SVG, offline.html) — Cache First
 *   PAGES  — خروجی HTML صفحات PHP — Stale-While-Revalidate + IDB fallback
 *   API    — پاسخ‌های JSON — Network First + IDB fallback
 *   FONTS  — فونت‌های Google — Cache First با expiry یک هفته
 */

const V           = 'v4.0';
const SHELL_CACHE = `pakdava-shell-${V}`;
const PAGES_CACHE = `pakdava-pages-${V}`;
const API_CACHE   = `pakdava-api-${V}`;
const FONT_CACHE  = `pakdava-fonts-${V}`;

// ── فایل‌های Shell — کش در install ────────────────────────────────────────
const SHELL_FILES = [
  './pwa/offline.html',
  './assets/css/main.css',
  './assets/js/db.js',
  './assets/js/sync.js',
  './assets/js/app.js',
  './assets/js/notify.js',
  './manifest.json',
  './assets/icons/icon-72.svg',
  './assets/icons/icon-96.svg',
  './assets/icons/icon-192.svg',
  './assets/icons/icon-512.svg',
  'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
];

// ── صفحات PHP برای pre-cache در اولین بارگذاری (runtime) ─────────────────
const PHP_PAGES = [
  './index.php',
  './patient/dashboard.php',
  './patient/daily_plan.php',
  './patient/risk_assessment.php',
  './patient/soc_assessment.php',
  './patient/clinical_data.php',
  './patient/progress.php',
  './patient/notifications.php',
  './patient/peer_compare.php',
  './patient/population_data.php',
  './patient/user.php',
  './doctor/dashboard.php',
  './doctor/enter_clinical.php',
  './doctor/approve_data.php',
  './doctor/patients.php',
  './doctor/alerts.php',
  './doctor/reports.php',
];

// ═══════════════════════════════════════════════════════════════
// INSTALL — کش فایل‌های shell
// ═══════════════════════════════════════════════════════════════
self.addEventListener('install', e => {
  console.log('[SW] Install', V);
  e.waitUntil(
    caches.open(SHELL_CACHE)
      .then(c => c.addAll(SHELL_FILES.filter(u => !u.startsWith('http') || u.includes('cdn.jsdelivr'))))
      .then(() => self.skipWaiting())
      .catch(err => { console.warn('[SW] Shell cache partial fail:', err); self.skipWaiting(); })
  );
});

// ═══════════════════════════════════════════════════════════════
// ACTIVATE — پاک‌سازی کش‌های قدیمی + claim
// ═══════════════════════════════════════════════════════════════
self.addEventListener('activate', e => {
  console.log('[SW] Activate', V);
  const VALID = [SHELL_CACHE, PAGES_CACHE, API_CACHE, FONT_CACHE];
  e.waitUntil(
    caches.keys()
      .then(keys => Promise.all(keys.filter(k => !VALID.includes(k)).map(k => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

// ═══════════════════════════════════════════════════════════════
// FETCH — قلب سیستم آفلاین
// ═══════════════════════════════════════════════════════════════
self.addEventListener('fetch', e => {
  const req = e.request;
  const url = new URL(req.url);

  // فقط GET
  if (req.method !== 'GET') return;

  // فونت‌های Google — Cache First با expiry
  if (url.hostname.includes('fonts.g') || url.hostname.includes('fonts.google')) {
    e.respondWith(fontStrategy(req));
    return;
  }

  // CDN — Cache First
  if (url.hostname.includes('cdn.jsdelivr') || url.hostname.includes('cdnjs')) {
    e.respondWith(cacheFirst(req, SHELL_CACHE));
    return;
  }

  // API endpoints — Network First + JSON fallback از IDB
  if (url.pathname.includes('/api/')) {
    e.respondWith(apiStrategy(req));
    return;
  }

  // فایل‌های ایستا (CSS, JS, SVG, PNG) — Cache First
  if (/\.(css|js|svg|png|jpg|gif|ico|woff2?|ttf)$/.test(url.pathname)) {
    e.respondWith(cacheFirst(req, SHELL_CACHE));
    return;
  }

  // صفحات PHP — Stale-While-Revalidate + offline fallback
  if (url.pathname.endsWith('.php') || url.pathname === '/' || url.pathname.endsWith('/')) {
    e.respondWith(pageStrategy(req));
    return;
  }

  // بقیه — Network با fallback
  e.respondWith(fetch(req).catch(() => caches.match('./pwa/offline.html')));
});

// ── استراتژی صفحات PHP ────────────────────────────────────────────────────
async function pageStrategy(req) {
  const cache  = await caches.open(PAGES_CACHE);
  const cached = await cache.match(req);

  // همزمان fetch در background
  const fetchPromise = fetch(req).then(res => {
    if (res.ok && res.status === 200) {
      // فقط text/html را کش کن
      const ct = res.headers.get('content-type') || '';
      if (ct.includes('text/html')) {
        cache.put(req, res.clone());
        // اطلاع به کلاینت‌ها برای به‌روزرسانی IDB
        notifyClients({ type: 'PAGE_CACHED', url: req.url });
      }
    }
    return res;
  }).catch(() => null);

  // کش موجود → برگردان فوری + revalidate در background
  if (cached) {
    fetchPromise.catch(() => {}); // suppress error
    return cached;
  }

  // کش ندارد → صبر برای fetch
  const fresh = await fetchPromise;
  if (fresh) return fresh;

  // کاملاً آفلاین → offline page
  return offlinePage();
}

// ── استراتژی API ──────────────────────────────────────────────────────────
async function apiStrategy(req) {
  try {
    const res = await fetch(req);
    if (res.ok) {
      const cache = await caches.open(API_CACHE);
      cache.put(req, res.clone());
    }
    return res;
  } catch {
    // آفلاین → از cache
    const cached = await caches.match(req, { cacheName: API_CACHE });
    if (cached) return cached;

    // JSON fallback
    const url    = new URL(req.url);
    const offline_json = await buildOfflineApiResponse(url.pathname);
    return new Response(JSON.stringify(offline_json), {
      status:  200,
      headers: {
        'Content-Type':     'application/json; charset=utf-8',
        'X-Offline-Source': 'IndexedDB',
      }
    });
  }
}

// ── ساخت پاسخ JSON از IDB (در SW context) ────────────────────────────────
async function buildOfflineApiResponse(pathname) {
  // SW به IDB دسترسی دارد
  const db = await openIDB();
  if (pathname.includes('data.php')) {
    const clinical = await idbGetAll(db, 'clinical_data');
    const daily    = await idbGetAll(db, 'daily_plans');
    const notifs   = await idbGetAll(db, 'notifications');
    const soc      = await idbGetAll(db, 'soc_records');
    const risk     = await idbGetAll(db, 'risk_records');
    const ncd      = await idbGetAll(db, 'ncd_risc');
    const user     = await idbGet(db, 'user_state', 'session');
    return { offline: true, clinical, daily, notifications: notifs, soc, risk, ncd_risc: ncd, user };
  }
  return { offline: true, success: false, message: 'آفلاین — داده از IndexedDB' };
}

// ── Cache First ────────────────────────────────────────────────────────────
async function cacheFirst(req, cacheName) {
  const cached = await caches.match(req);
  if (cached) return cached;
  try {
    const res = await fetch(req);
    if (res.ok) (await caches.open(cacheName)).put(req, res.clone());
    return res;
  } catch {
    return offlinePage();
  }
}

// ── Font Strategy ─────────────────────────────────────────────────────────
async function fontStrategy(req) {
  const cached = await caches.match(req, { cacheName: FONT_CACHE });
  if (cached) return cached;
  try {
    const res = await fetch(req);
    if (res.ok) (await caches.open(FONT_CACHE)).put(req, res.clone());
    return res;
  } catch {
    return new Response('', { status: 503 });
  }
}

async function offlinePage() {
  return (
    await caches.match('./pwa/offline.html') ||
    new Response(
      `<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="UTF-8">
       <meta name="viewport" content="width=device-width,initial-scale=1">
       <title>آفلاین | پک دوا</title>
       <style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:Tahoma;background:#F0F4F8;display:flex;align-items:center;justify-content:center;min-height:100vh;direction:rtl}
       .box{background:#fff;border-radius:20px;padding:40px;text-align:center;max-width:380px;box-shadow:0 8px 40px rgba(0,0,0,.12)}</style>
       </head><body><div class="box"><div style="font-size:60px">📡</div>
       <h2 style="margin:16px 0 10px;font-size:22px">حالت آفلاین</h2>
       <p style="color:#666;line-height:1.7">اتصال به سرور برقرار نیست.<br>داده‌های ذخیره‌شده در دسترس است.</p>
       <button onclick="location.reload()" style="margin-top:20px;padding:12px 24px;background:#1A7A4A;color:#fff;border:none;border-radius:8px;font-size:15px;cursor:pointer">🔄 تلاش مجدد</button>
       </div></body></html>`,
      { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
    )
  );
}

// ═══════════════════════════════════════════════════════════════
// BACKGROUND SYNC
// ═══════════════════════════════════════════════════════════════
self.addEventListener('sync', e => {
  console.log('[SW] Background sync:', e.tag);
  const map = {
    'sync-clinical': () => doSync('clinical_queue'),
    'sync-soc':      () => doSync('soc_queue'),
    'sync-daily':    () => doSync('daily_queue'),
    'sync-risk':     () => doSync('risk_queue'),
    'sync-all':      () => Promise.all(['clinical_queue','soc_queue','daily_queue','risk_queue'].map(doSync)),
  };
  if (map[e.tag]) e.waitUntil(map[e.tag]());
});

async function doSync(storeName) {
  console.log('[SW] Syncing:', storeName);
  try {
    const db      = await openIDB();
    const all     = await idbGetAll(db, storeName);
    const pending = all.filter(r => r.synced === 0);
    if (!pending.length) { console.log('[SW] Nothing pending in', storeName); return; }

    const res = await fetch('./api/sync.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ store: storeName, records: pending }),
    });

    if (res.ok) {
      const data = await res.json();
      if (data.success) {
        for (const r of pending) {
          await idbPut(db, storeName, { ...r, synced: 1 });
        }
        notifyClients({ type: 'SYNC_DONE', store: storeName, count: pending.length });
        console.log(`[SW] ✅ Synced ${pending.length} records from ${storeName}`);
      }
    }
  } catch (err) {
    console.warn('[SW] Sync failed:', storeName, err.message);
    throw err; // retry
  }
}

// ═══════════════════════════════════════════════════════════════
// PUSH NOTIFICATIONS
// ═══════════════════════════════════════════════════════════════
self.addEventListener('push', e => {
  let d = { title: 'پک دوا', body: 'اعلان جدید دارید', type: 'general', url: './patient/notifications.php' };
  try { if (e.data) d = { ...d, ...e.data.json() }; } catch {}

  const vibrations = {
    warning:    [200, 100, 200, 100, 200],
    clinical:   [300, 100, 300],
    medication: [200, 100, 200],
    default:    [200],
  };

  e.waitUntil(
    self.registration.showNotification(d.title, {
      body:               d.body,
      icon:               './assets/icons/icon-192.svg',
      badge:              './assets/icons/icon-72.svg',
      tag:                d.type || 'general',
      renotify:           true,
      requireInteraction: ['warning', 'clinical'].includes(d.type),
      vibrate:            vibrations[d.type] || vibrations.default,
      data:               { url: d.url, type: d.type },
      actions: [
        { action: 'open',    title: 'مشاهده' },
        { action: 'dismiss', title: 'رد'     },
      ],
    })
  );
});

self.addEventListener('notificationclick', e => {
  e.notification.close();
  if (e.action === 'dismiss') return;
  const url = e.notification.data?.url || './patient/notifications.php';
  e.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(list => {
      const open = list.find(c => c.url.includes('pakdava'));
      if (open) { open.focus(); return open.navigate(url); }
      return clients.openWindow(url);
    })
  );
});

// ═══════════════════════════════════════════════════════════════
// IndexedDB helpers (SW context — بدون window.PakDavaDB)
// ═══════════════════════════════════════════════════════════════
const IDB_NAME    = 'PakDavaDB';
const IDB_VERSION = 5;
const IDB_STORES  = [
  'cache_pages', 'user_state',
  'clinical_data', 'bmi_history', 'daily_plans', 'soc_records',
  'risk_records', 'progress_records', 'notifications', 'ncd_risc', 'peer_data',
  'clinical_queue', 'soc_queue', 'daily_queue', 'risk_queue',
];

function openIDB() {
  return new Promise((res, rej) => {
    const r = indexedDB.open(IDB_NAME, IDB_VERSION);
    r.onupgradeneeded = e => {
      const db = e.target.result;
      const autoStores = ['clinical_queue','soc_queue','daily_queue','risk_queue','ncd_risc'];
      IDB_STORES.forEach(name => {
        if (db.objectStoreNames.contains(name)) return;
        const key  = name === 'cache_pages' ? 'url' : name === 'user_state' ? 'key' : autoStores.includes(name) ? 'local_id' : 'id';
        const auto = autoStores.includes(name);
        const s    = db.createObjectStore(name, { keyPath: key, autoIncrement: auto });
        if (name.endsWith('_queue')) {
          s.createIndex('synced',    'synced',    { unique: false });
          s.createIndex('timestamp', 'timestamp', { unique: false });
        }
      });
    };
    r.onsuccess = () => res(r.result);
    r.onerror   = () => rej(r.error);
  });
}

function idbGetAll(db, storeName) {
  return new Promise((res, rej) => {
    if (!db.objectStoreNames.contains(storeName)) { res([]); return; }
    const tx  = db.transaction(storeName, 'readonly');
    const req = tx.objectStore(storeName).getAll();
    req.onsuccess = () => res(req.result || []);
    req.onerror   = () => rej(req.error);
  });
}

function idbGet(db, storeName, key) {
  return new Promise((res, rej) => {
    if (!db.objectStoreNames.contains(storeName)) { res(null); return; }
    const tx  = db.transaction(storeName, 'readonly');
    const req = tx.objectStore(storeName).get(key);
    req.onsuccess = () => res(req.result || null);
    req.onerror   = () => rej(req.error);
  });
}

function idbPut(db, storeName, data) {
  return new Promise((res, rej) => {
    if (!db.objectStoreNames.contains(storeName)) { res(); return; }
    const tx  = db.transaction(storeName, 'readwrite');
    const req = tx.objectStore(storeName).put(data);
    req.onsuccess = () => res();
    req.onerror   = () => rej(req.error);
  });
}

function notifyClients(msg) {
  self.clients.matchAll({ includeUncontrolled: true })
    .then(list => list.forEach(c => c.postMessage(msg)));
}

console.log(`[SW] PakDava ${V} loaded`);
