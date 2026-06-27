/**
 * PakDava Local DB — IndexedDB
 * دقیقاً همان store‌های نسخه PHP
 */

const DB_NAME    = 'PakDavaDB';
const DB_VERSION = 5;

const STORES = {
  user_state:        { key: 'key' },
  clinical_data:     { key: 'id' },
  bmi_history:       { key: 'id' },
  daily_plans:       { key: 'id' },
  soc_records:       { key: 'id' },
  risk_records:      { key: 'id' },
  progress_records:  { key: 'id' },
  notifications:     { key: 'id' },
  ncd_risc:          { key: 'local_id', auto: true },
  peer_data:         { key: 'id' },
  clinical_queue:    { key: 'local_id', auto: true },
  soc_queue:         { key: 'local_id', auto: true },
  daily_queue:       { key: 'local_id', auto: true },
  risk_queue:        { key: 'local_id', auto: true },
};

let _db = null;

function openDB() {
  if (_db) return Promise.resolve(_db);
  return new Promise((resolve, reject) => {
    const req = indexedDB.open(DB_NAME, DB_VERSION);
    req.onupgradeneeded = e => {
      const db = e.target.result;
      Object.entries(STORES).forEach(([name, cfg]) => {
        if (db.objectStoreNames.contains(name)) return;
        const s = db.createObjectStore(name, {
          keyPath:       cfg.key,
          autoIncrement: cfg.auto || false,
        });
        if (name.endsWith('_queue')) {
          s.createIndex('synced',    'synced',    { unique: false });
          s.createIndex('timestamp', 'timestamp', { unique: false });
        }
      });
    };
    req.onsuccess = () => { _db = req.result; resolve(_db); };
    req.onerror   = () => reject(req.error);
  });
}

export const DB = {
  async set(store, data) {
    const db = await openDB();
    return new Promise((res, rej) => {
      const tx  = db.transaction(store, 'readwrite');
      const req = tx.objectStore(store).put(data);
      req.onsuccess = () => res(req.result);
      req.onerror   = () => rej(req.error);
    });
  },

  async get(store, key) {
    const db = await openDB();
    return new Promise((res, rej) => {
      const tx  = db.transaction(store, 'readonly');
      const req = tx.objectStore(store).get(key);
      req.onsuccess = () => res(req.result || null);
      req.onerror   = () => rej(req.error);
    });
  },

  async getAll(store, filter = {}) {
    const db = await openDB();
    return new Promise((res, rej) => {
      const tx  = db.transaction(store, 'readonly');
      const req = tx.objectStore(store).getAll();
      req.onsuccess = () => {
        let rows = req.result || [];
        Object.entries(filter).forEach(([k, v]) => {
          rows = rows.filter(r => r[k] === v);
        });
        rows.sort((a, b) => {
          const ka = a.timestamp || a.record_date || a.assessment_date || 0;
          const kb = b.timestamp || b.record_date || b.assessment_date || 0;
          return ka < kb ? 1 : -1;
        });
        res(rows);
      };
      req.onerror = () => rej(req.error);
    });
  },

  async del(store, key) {
    const db = await openDB();
    return new Promise((res, rej) => {
      const tx  = db.transaction(store, 'readwrite');
      const req = tx.objectStore(store).delete(key);
      req.onsuccess = () => res();
      req.onerror   = () => rej(req.error);
    });
  },

  async clear(store) {
    const db = await openDB();
    return new Promise((res, rej) => {
      const tx  = db.transaction(store, 'readwrite');
      const req = tx.objectStore(store).clear();
      req.onsuccess = () => res();
      req.onerror   = () => rej(req.error);
    });
  },

  async enqueue(store, data) {
    return this.set(store, { ...data, synced: 0, timestamp: Date.now() });
  },

  async pendingCount(store) {
    const rows = await this.getAll(store, { synced: 0 });
    return rows.length;
  },

  async totalPending() {
    const queues  = ['clinical_queue', 'soc_queue', 'daily_queue', 'risk_queue'];
    const counts  = await Promise.all(queues.map(s => this.pendingCount(s)));
    return counts.reduce((a, b) => a + b, 0);
  },

  async bulkSave(store, rows) {
    if (!rows?.length) return 0;
    const db = await openDB();
    return new Promise((res, rej) => {
      const tx = db.transaction(store, 'readwrite');
      const s  = tx.objectStore(store);
      rows.forEach(r => s.put({ ...r, _cached: Date.now() }));
      tx.oncomplete = () => res(rows.length);
      tx.onerror    = () => rej(tx.error);
    });
  },

  async saveUserState(state) {
    return this.set('user_state', { key: 'session', ...state, saved_at: Date.now() });
  },

  async getUserState() {
    return this.get('user_state', 'session');
  },
};
