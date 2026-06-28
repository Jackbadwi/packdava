/**
 * PakDava IndexedDB v4.0 — کامل‌ترین لایه داده محلی
 * ─────────────────────────────────────────────────────
 * Stores:
 *   cache_pages      — HTML کامل صفحات PHP (برای آفلاین)
 *   user_state       — وضعیت کاربر (session، نقش، نام)
 *   clinical_data    — داده‌های بالینی (کش از سرور)
 *   bmi_history      — تاریخچه BMI
 *   daily_plans      — برنامه‌های روزانه
 *   soc_records      — ارزیابی‌های SOC
 *   risk_records     — ارزیابی‌های ریسک
 *   progress_records — پیشرفت
 *   notifications    — اعلانات
 *   ncd_risc         — داده‌های جمعیتی NCD-RisC
 *   peer_data        — مقایسه همتا
 *   clinical_queue   — صف ارسال داده بالینی
 *   soc_queue        — صف ارسال SOC
 *   daily_queue      — صف ارسال برنامه روزانه
 *   risk_queue       — صف ارسال ریسک
 */
const PakDavaDB = (() => {
  const NAME    = 'PakDavaDB';
  const VERSION = 5;
  let _db = null;

  const STORES = {
    // کش داده‌های سرور
    cache_pages:       { key: 'url' },
    user_state:        { key: 'key' },
    clinical_data:     { key: 'id',       auto: false },
    bmi_history:       { key: 'id',       auto: false },
    daily_plans:       { key: 'id',       auto: false },
    soc_records:       { key: 'id',       auto: false },
    risk_records:      { key: 'id',       auto: false },
    progress_records:  { key: 'id',       auto: false },
    notifications:     { key: 'id',       auto: false },
    ncd_risc:          { key: 'local_id', auto: true  },
    peer_data:         { key: 'id',       auto: false },
    // صف‌های همگام‌سازی
    clinical_queue:    { key: 'local_id', auto: true  },
    soc_queue:         { key: 'local_id', auto: true  },
    daily_queue:       { key: 'local_id', auto: true  },
    risk_queue:        { key: 'local_id', auto: true  },
  };

  function open() {
    if (_db) return Promise.resolve(_db);
    return new Promise((resolve, reject) => {
      const req = indexedDB.open(NAME, VERSION);
      req.onupgradeneeded = e => {
        const db = e.target.result;
        Object.entries(STORES).forEach(([name, cfg]) => {
          if (!db.objectStoreNames.contains(name)) {
            const s = db.createObjectStore(name, {
              keyPath:       cfg.key,
              autoIncrement: cfg.auto || false
            });
            if (name.endsWith('_queue')) {
              s.createIndex('synced',    'synced',    { unique: false });
              s.createIndex('timestamp', 'timestamp', { unique: false });
            }
          }
        });
      };
      req.onsuccess = () => { _db = req.result; resolve(_db); };
      req.onerror   = () => reject(req.error);
    });
  }

  // ── عملیات پایه ────────────────────────────────────────────────
  async function set(store, data) {
    const db = await open();
    return new Promise((res, rej) => {
      const tx  = db.transaction(store, 'readwrite');
      const req = tx.objectStore(store).put(data);
      req.onsuccess = () => res(req.result);
      req.onerror   = () => rej(req.error);
    });
  }

  async function get(store, key) {
    const db = await open();
    return new Promise((res, rej) => {
      const tx  = db.transaction(store, 'readonly');
      const req = tx.objectStore(store).get(key);
      req.onsuccess = () => res(req.result || null);
      req.onerror   = () => rej(req.error);
    });
  }

  async function getAll(store, filter = {}) {
    const db = await open();
    return new Promise((res, rej) => {
      const tx  = db.transaction(store, 'readonly');
      const req = tx.objectStore(store).getAll();
      req.onsuccess = () => {
        let rows = req.result || [];
        Object.entries(filter).forEach(([k, v]) => {
          rows = rows.filter(r => r[k] === v);
        });
        // مرتب‌سازی بر اساس timestamp یا date (جدیدترین اول)
        rows.sort((a, b) => {
          const da = a.timestamp || a.record_date || a.assessment_date || 0;
          const db2 = b.timestamp || b.record_date || b.assessment_date || 0;
          return da > db2 ? -1 : 1;
        });
        res(rows);
      };
      req.onerror = () => rej(req.error);
    });
  }

  async function del(store, key) {
    const db = await open();
    return new Promise((res, rej) => {
      const tx  = db.transaction(store, 'readwrite');
      const req = tx.objectStore(store).delete(key);
      req.onsuccess = () => res();
      req.onerror   = () => rej(req.error);
    });
  }

  async function clear(store) {
    const db = await open();
    return new Promise((res, rej) => {
      const tx  = db.transaction(store, 'readwrite');
      const req = tx.objectStore(store).clear();
      req.onsuccess = () => res();
      req.onerror   = () => rej(req.error);
    });
  }

  // ── صف‌های همگام‌سازی ──────────────────────────────────────────
  async function enqueue(store, data) {
    return set(store, { ...data, synced: 0, timestamp: Date.now() });
  }

  async function pendingCount(store) {
    const rows = await getAll(store, { synced: 0 });
    return rows.length;
  }

  async function totalPending() {
    const queues = ['clinical_queue', 'soc_queue', 'daily_queue', 'risk_queue'];
    const counts = await Promise.all(queues.map(pendingCount));
    return counts.reduce((a, b) => a + b, 0);
  }

  // ── کش صفحات ───────────────────────────────────────────────────
  async function cachePage(url, html) {
    return set('cache_pages', { url, html, cached_at: Date.now() });
  }

  async function getCachedPage(url) {
    const row = await get('cache_pages', url);
    return row ? row.html : null;
  }

  // ── وضعیت کاربر ────────────────────────────────────────────────
  async function saveUserState(state) {
    return set('user_state', { key: 'session', ...state, saved_at: Date.now() });
  }

  async function getUserState() {
    return get('user_state', 'session');
  }

  // ── ذخیره انبوه داده سرور ──────────────────────────────────────
  async function bulkSave(store, rows) {
    if (!rows || !rows.length) return;
    const db = await open();
    return new Promise((res, rej) => {
      const tx = db.transaction(store, 'readwrite');
      const s  = tx.objectStore(store);
      rows.forEach(r => s.put({ ...r, _cached: Date.now() }));
      tx.oncomplete = () => res(rows.length);
      tx.onerror    = () => rej(tx.error);
    });
  }

  return {
    open, set, get, getAll, del, clear,
    enqueue, pendingCount, totalPending,
    cachePage, getCachedPage,
    saveUserState, getUserState,
    bulkSave,
  };
})();

window.PakDavaDB = PakDavaDB;
