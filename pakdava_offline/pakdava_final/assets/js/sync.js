/**
 * PakDava Sync Engine v4.0
 * ═══════════════════════════════════════════════════════════════
 * مسئولیت‌ها:
 *   1. fetch داده از سرور → ذخیره در IDB (pre-cache)
 *   2. ارسال صف‌های pending به سرور
 *   3. مدیریت رویدادهای online/offline
 *   4. Badge به‌روزرسانی
 */

const PakDavaSync = (() => {

  // ─────────────────────────────────────────────────────────────
  // 1. PRE-FETCH — دریافت داده از سرور و ذخیره در IDB
  // ─────────────────────────────────────────────────────────────
  async function prefetch() {
    if (!navigator.onLine) return;
    try {
      const basePath = _getBasePath();
      const res = await fetch(basePath + 'api/data.php');
      if (!res.ok) return;
      const d = await res.json();

      // ذخیره هر دسته در store مربوطه
      if (d.clinical?.length)     await PakDavaDB.bulkSave('clinical_data',     d.clinical);
      if (d.bmi_history?.length)  await PakDavaDB.bulkSave('bmi_history',       d.bmi_history);
      if (d.daily?.length)        await PakDavaDB.bulkSave('daily_plans',        d.daily);
      if (d.soc?.length)          await PakDavaDB.bulkSave('soc_records',        d.soc);
      if (d.risk?.length)         await PakDavaDB.bulkSave('risk_records',       d.risk);
      if (d.progress?.length)     await PakDavaDB.bulkSave('progress_records',   d.progress);
      if (d.notifications?.length)await PakDavaDB.bulkSave('notifications',      d.notifications);
      if (d.ncd_risc?.length)     await PakDavaDB.bulkSave('ncd_risc',           d.ncd_risc);
      if (d.peer?.length)         await PakDavaDB.bulkSave('peer_data',           d.peer);

      // ذخیره وضعیت کاربر
      if (d.user) await PakDavaDB.saveUserState(d.user);

      console.log('[Sync] Prefetch complete:', Object.keys(d).join(', '));
      document.dispatchEvent(new CustomEvent('pakdava:data-ready', { detail: d }));
    } catch (err) {
      console.warn('[Sync] Prefetch failed:', err.message);
    }
  }

  // ─────────────────────────────────────────────────────────────
  // 2. ENQUEUE — ذخیره داده در IDB + ثبت sync
  // ─────────────────────────────────────────────────────────────
  async function enqueue(storeName, data) {
    await PakDavaDB.enqueue(storeName, data);
    console.log('[Sync] Enqueued to', storeName, data);

    if (navigator.onLine) {
      try { await flushStore(storeName); }
      catch { await registerBgSync(storeName); }
    } else {
      await registerBgSync(storeName);
      updateBadge();
      showToast(`📥 آفلاین — ذخیره شد (ارسال هنگام اتصال)`);
    }
  }

  // ─────────────────────────────────────────────────────────────
  // 3. FLUSH — ارسال یک store به سرور
  // ─────────────────────────────────────────────────────────────
  async function flushStore(storeName) {
    const pending = await PakDavaDB.getAll(storeName, { synced: 0 });
    if (!pending.length) return { synced: 0 };

    const basePath = _getBasePath();
    const res = await fetch(basePath + 'api/sync.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ store: storeName, records: pending }),
    });

    if (!res.ok) throw new Error(`HTTP ${res.status}`);

    const result = await res.json();
    if (result.success) {
      for (const r of pending) {
        await PakDavaDB.set(storeName, { ...r, synced: 1 });
      }
      console.log(`[Sync] ✅ Flushed ${pending.length} from ${storeName}`);
    }
    return { synced: pending.length };
  }

  // ─────────────────────────────────────────────────────────────
  // 4. SYNC ALL — همه صف‌ها + pre-fetch تازه
  // ─────────────────────────────────────────────────────────────
  async function syncAll() {
    if (!navigator.onLine) {
      showToast('⚠️ آفلاین — داده‌ها ذخیره شده‌اند');
      return 0;
    }

    const stores = ['clinical_queue', 'soc_queue', 'daily_queue', 'risk_queue'];
    let total = 0;

    for (const s of stores) {
      try {
        const { synced } = await flushStore(s);
        total += synced;
      } catch (err) {
        console.warn('[Sync] flushStore failed:', s, err.message);
      }
    }

    // دریافت داده‌های تازه از سرور
    await prefetch();
    updateBadge();

    if (total > 0) showToast(`✅ ${total} رکورد با سرور همگام شد`);
    else           showToast('✅ همه داده‌ها به‌روز است');

    return total;
  }

  // ─────────────────────────────────────────────────────────────
  // 5. BADGE & UI
  // ─────────────────────────────────────────────────────────────
  async function updateBadge() {
    const count = await PakDavaDB.totalPending();
    const badge = document.getElementById('sync-badge');
    if (badge) {
      badge.textContent   = count > 0 ? count : '';
      badge.style.display = count > 0 ? 'inline-flex' : 'none';
    }
    // Android home screen badge
    if ('setAppBadge' in navigator) {
      count > 0 ? navigator.setAppBadge(count) : navigator.clearAppBadge();
    }
  }

  // ─────────────────────────────────────────────────────────────
  // 6. BACKGROUND SYNC REGISTRATION
  // ─────────────────────────────────────────────────────────────
  async function registerBgSync(storeName) {
    if (!('serviceWorker' in navigator) || !('SyncManager' in window)) return;
    try {
      const reg = await navigator.serviceWorker.ready;
      const tag = 'sync-' + storeName.replace('_queue', '');
      await reg.sync.register(tag);
      await reg.sync.register('sync-all');
      console.log('[Sync] BG sync registered:', tag);
    } catch (err) {
      console.warn('[Sync] BG sync registration failed:', err);
    }
  }

  // ─────────────────────────────────────────────────────────────
  // 7. INIT — رویدادهای آنلاین/آفلاین + SW messages
  // ─────────────────────────────────────────────────────────────
  function init() {
    // رویداد آنلاین شدن
    window.addEventListener('online', async () => {
      _updateConnectionUI(true);
      showToast('🟢 اتصال برقرار شد — در حال همگام‌سازی...');
      await syncAll();
    });

    // رویداد آفلاین شدن
    window.addEventListener('offline', () => {
      _updateConnectionUI(false);
      showToast('🔴 آفلاین — داده‌ها به‌صورت محلی ذخیره می‌شوند');
    });

    // پیام از Service Worker
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.addEventListener('message', e => {
        const msg = e.data;
        if (!msg) return;

        if (msg.type === 'SYNC_DONE') {
          showToast(`✅ ${msg.count} رکورد همگام شد`);
          updateBadge();
          prefetch(); // دریافت داده تازه
        }
        if (msg.type === 'PAGE_CACHED') {
          console.log('[App] Page cached:', msg.url);
        }
      });
    }

    // وضعیت اولیه
    _updateConnectionUI(navigator.onLine);
    updateBadge();

    // pre-fetch در صورت آنلاین بودن
    if (navigator.onLine) {
      setTimeout(prefetch, 1500); // تأخیر کوتاه تا UI لود شود
    }
  }

  // ─────────────────────────────────────────────────────────────
  // helpers
  // ─────────────────────────────────────────────────────────────
  function _getBasePath() {
    const p = window.location.pathname;
    if (p.includes('/patient/') || p.includes('/doctor/')) return '../';
    return './';
  }

  function _updateConnectionUI(online) {
    document.body.classList.toggle('is-offline', !online);
    const bar = document.getElementById('connection-status');
    if (bar) {
      bar.textContent        = online ? '🟢 آنلاین' : '🔴 آفلاین';
      bar.style.background   = online ? '#1A7A4A' : '#E74C3C';
    }
  }

  return { init, enqueue, syncAll, prefetch, updateBadge, flushStore };
})();

// ── Toast helper ─────────────────────────────────────────────────────────
function showToast(msg, ms = 3500) {
  let t = document.getElementById('pd-toast');
  if (!t) {
    t = document.createElement('div');
    t.id = 'pd-toast';
    Object.assign(t.style, {
      position:   'fixed', bottom: '24px', left: '50%',
      transform:  'translateX(-50%) translateY(80px)',
      background: '#212529', color: '#fff',
      padding:    '11px 22px', borderRadius: '99px',
      fontSize:   '13px', fontWeight: '600',
      fontFamily: 'Vazirmatn,Tahoma', zIndex: '9999',
      boxShadow:  '0 4px 20px rgba(0,0,0,.25)',
      transition: 'transform .35s cubic-bezier(.34,1.56,.64,1)',
      whiteSpace: 'nowrap', maxWidth: '90vw',
    });
    document.body.appendChild(t);
  }
  t.textContent = msg;
  t.style.transform = 'translateX(-50%) translateY(0)';
  clearTimeout(t._t);
  t._t = setTimeout(() => { t.style.transform = 'translateX(-50%) translateY(80px)'; }, ms);
}

window.PakDavaSync = PakDavaSync;
window.showToast   = showToast;
