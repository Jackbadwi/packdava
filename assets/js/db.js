/**
 * PakDava IndexedDB Manager
 * مدیریت پایگاه داده محلی برای حالت آفلاین
 */
const PakDavaDB = (() => {
  const DB_NAME    = 'PakDavaDB';
  const DB_VERSION = 4;
  const STORES     = [
    'clinical_queue', 'soc_queue', 'daily_queue', 'risk_queue',
    'notifications', 'user_profile', 'clinical_history',
    'peer_data', 'population_cache'
  ];

  let _db = null;

  function open() {
    if (_db) return Promise.resolve(_db);
    return new Promise((resolve, reject) => {
      const req = indexedDB.open(DB_NAME, DB_VERSION);
      req.onupgradeneeded = e => {
        const db = e.target.result;
        STORES.forEach(name => {
          if (!db.objectStoreNames.contains(name)) {
            const s = db.createObjectStore(name, { keyPath: 'local_id', autoIncrement: true });
            s.createIndex('synced',    'synced',    { unique: false });
            s.createIndex('timestamp', 'timestamp', { unique: false });
          }
        });
      };
      req.onsuccess = () => { _db = req.result; resolve(_db); };
      req.onerror   = () => reject(req.error);
    });
  }

  async function put(storeName, data) {
    const db    = await open();
    const entry = { ...data, synced: 0, timestamp: Date.now() };
    return new Promise((resolve, reject) => {
      const tx  = db.transaction(storeName, 'readwrite');
      const req = tx.objectStore(storeName).put(entry);
      req.onsuccess = () => resolve(req.result);
      req.onerror   = () => reject(req.error);
    });
  }

  async function getAll(storeName, filter = {}) {
    const db = await open();
    return new Promise((resolve, reject) => {
      const tx  = db.transaction(storeName, 'readonly');
      const req = tx.objectStore(storeName).getAll();
      req.onsuccess = () => {
        let rows = req.result;
        Object.entries(filter).forEach(([k, v]) => {
          rows = rows.filter(r => r[k] === v);
        });
        resolve(rows);
      };
      req.onerror = () => reject(req.error);
    });
  }

  async function get(storeName, key) {
    const db = await open();
    return new Promise((resolve, reject) => {
      const tx  = db.transaction(storeName, 'readonly');
      const req = tx.objectStore(storeName).get(key);
      req.onsuccess = () => resolve(req.result);
      req.onerror   = () => reject(req.error);
    });
  }

  async function del(storeName, key) {
    const db = await open();
    return new Promise((resolve, reject) => {
      const tx  = db.transaction(storeName, 'readwrite');
      const req = tx.objectStore(storeName).delete(key);
      req.onsuccess = () => resolve();
      req.onerror   = () => reject(req.error);
    });
  }

  async function clear(storeName) {
    const db = await open();
    return new Promise((resolve, reject) => {
      const tx  = db.transaction(storeName, 'readwrite');
      const req = tx.objectStore(storeName).clear();
      req.onsuccess = () => resolve();
      req.onerror   = () => reject(req.error);
    });
  }

  async function countPending(storeName) {
    const rows = await getAll(storeName, { synced: 0 });
    return rows.length;
  }

  async function totalPending() {
    const queues = ['clinical_queue', 'soc_queue', 'daily_queue', 'risk_queue'];
    const counts = await Promise.all(queues.map(s => countPending(s)));
    return counts.reduce((a, b) => a + b, 0);
  }

  return { open, put, getAll, get, del, clear, countPending, totalPending };
})();

window.PakDavaDB = PakDavaDB;
