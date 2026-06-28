/**
 * PakDava Sync Manager
 * همگام‌سازی داده‌های آفلاین با سرور
 */
const PakDavaSync = (() => {

  // ── ثبت داده برای ارسال بعدی (آفلاین یا آنلاین) ──────────────────────
  async function queue(storeName, data) {
    await PakDavaDB.put(storeName, data);

    // اگر آنلاین هستیم، سعی کن همین الان sync کن
    if (navigator.onLine) {
      try {
        await flushStore(storeName);
      } catch { /* ذخیره ماند — Background Sync انجام می‌دهد */ }
    } else {
      // Background Sync را ثبت کن
      if ('serviceWorker' in navigator && 'SyncManager' in window) {
        const reg = await navigator.serviceWorker.ready;
        await reg.sync.register('sync-' + storeName.replace('_queue', ''));
      }
    }
  }

  // ── ارسال همه رکوردهای pending یک store ───────────────────────────────
  async function flushStore(storeName) {
    const pending = await PakDavaDB.getAll(storeName, { synced: 0 });
    if (!pending.length) return { synced: 0 };

    const res = await fetch('../api/sync.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ store: storeName, records: pending }),
    });

    if (!res.ok) throw new Error('Server error: ' + res.status);

    const result = await res.json();
    if (result.success) {
      // علامت sync شده
      for (const rec of pending) {
        await PakDavaDB.put(storeName, { ...rec, synced: 1 });
      }
      updateSyncBadge();
    }
    return { synced: pending.length, result };
  }

  // ── همگام‌سازی همه صف‌ها ──────────────────────────────────────────────
  async function syncAll() {
    if (!navigator.onLine) {
      showToast('⚠️ اتصال اینترنت ندارید — داده‌ها ذخیره شده‌اند');
      return;
    }

    const stores = ['clinical_queue', 'soc_queue', 'daily_queue', 'risk_queue'];
    let totalSynced = 0;

    for (const store of stores) {
      try {
        const result = await flushStore(store);
        totalSynced += result.synced || 0;
      } catch (err) {
        console.warn('[Sync] Failed:', store, err);
      }
    }

    // دریافت داده‌های تازه از سرور
    await fetchFreshData();

    if (totalSynced > 0) {
      showToast(`✅ ${totalSynced} رکورد با سرور همگام شد`);
    } else {
      showToast('✅ همه داده‌ها همگام هستند');
    }

    updateSyncBadge();
    return totalSynced;
  }

  // ── دریافت داده‌های تازه از سرور برای کش آفلاین ─────────────────────
  async function fetchFreshData() {
    try {
      const res = await fetch('../api/data.php');
      if (!res.ok) return;
      const data = await res.json();

      if (data.clinical)     { await PakDavaDB.clear('clinical_history'); for(const r of data.clinical) await PakDavaDB.put('clinical_history', {...r, synced:1}); }
      if (data.notifications){ await PakDavaDB.clear('notifications');    for(const r of data.notifications) await PakDavaDB.put('notifications', {...r, synced:1}); }
      if (data.daily)        { await PakDavaDB.clear('daily_queue');       /* don't clear pending queue */ }
      if (data.peer)         { await PakDavaDB.clear('peer_data');         for(const r of data.peer) await PakDavaDB.put('peer_data', {...r, synced:1}); }

      document.dispatchEvent(new CustomEvent('pakdava:data-refreshed', { detail: data }));
    } catch (err) {
      console.warn('[Sync] fetchFreshData error:', err);
    }
  }

  // ── به‌روز کردن badge تعداد pending ──────────────────────────────────
  async function updateSyncBadge() {
    const count = await PakDavaDB.totalPending();
    const badge = document.getElementById('sync-badge');
    if (badge) {
      badge.textContent  = count > 0 ? count : '';
      badge.style.display = count > 0 ? 'inline' : 'none';
    }
    // Navigator badge API (Android)
    if ('setAppBadge' in navigator) {
      count > 0 ? navigator.setAppBadge(count) : navigator.clearAppBadge();
    }
  }

  // ── init: رویدادهای آنلاین/آفلاین ────────────────────────────────────
  function init() {
    window.addEventListener('online', async () => {
      updateConnectionUI(true);
      await syncAll();
    });
    window.addEventListener('offline', () => {
      updateConnectionUI(false);
    });

    // شنود پیام‌های Service Worker
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.addEventListener('message', e => {
        if (e.data?.type === 'SYNC_DONE') {
          showToast(`✅ ${e.data.count} رکورد همگام شد`);
          updateSyncBadge();
        }
      });
    }

    updateConnectionUI(navigator.onLine);
    updateSyncBadge();
  }

  return { queue, syncAll, fetchFreshData, updateSyncBadge, init, flushStore };
})();

// ── نمایش وضعیت اتصال در UI ──────────────────────────────────────────────
function updateConnectionUI(online) {
  const el = document.getElementById('connection-status');
  if (!el) return;
  el.textContent  = online ? '🟢 آنلاین' : '🔴 آفلاین';
  el.style.background = online ? 'var(--green)' : 'var(--red)';
  document.body.classList.toggle('is-offline', !online);
}

// ── Toast ─────────────────────────────────────────────────────────────────
function showToast(msg, duration = 3000) {
  let t = document.getElementById('pakdava-toast');
  if (!t) {
    t = document.createElement('div');
    t.id = 'pakdava-toast';
    t.style.cssText = `
      position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(80px);
      background:#212529;color:white;padding:12px 24px;border-radius:99px;
      font-size:13px;font-weight:600;z-index:9999;
      font-family:Vazirmatn,Tahoma;box-shadow:0 4px 20px rgba(0,0,0,0.25);
      transition:transform 0.35s cubic-bezier(0.34,1.56,0.64,1);white-space:nowrap;
    `;
    document.body.appendChild(t);
  }
  t.textContent = msg;
  t.style.transform = 'translateX(-50%) translateY(0)';
  clearTimeout(t._timer);
  t._timer = setTimeout(() => { t.style.transform = 'translateX(-50%) translateY(80px)'; }, duration);
}

window.PakDavaSync = PakDavaSync;
window.showToast   = showToast;
