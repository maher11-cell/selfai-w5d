/* SelfAI — IndexedDB cache layer
 * One DB per browser session, with stores for dashboard summary + per-clone histories.
 */
(function (global) {
  const DB_NAME = 'selfai-cache';
  const DB_VER  = 1;

  function openDB() {
    return new Promise((resolve, reject) => {
      const req = indexedDB.open(DB_NAME, DB_VER);
      req.onupgradeneeded = (e) => {
        const db = e.target.result;
        if (!db.objectStoreNames.contains('dashboard')) db.createObjectStore('dashboard');
        if (!db.objectStoreNames.contains('history'))   db.createObjectStore('history');
        if (!db.objectStoreNames.contains('clones'))    db.createObjectStore('clones');
      };
      req.onsuccess = () => resolve(req.result);
      req.onerror   = () => reject(req.error);
    });
  }

  async function txGet(store, key) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
      const tx = db.transaction(store, 'readonly');
      const r = tx.objectStore(store).get(key);
      r.onsuccess = () => resolve(r.result || null);
      r.onerror   = () => reject(r.error);
    });
  }
  async function txPut(store, key, val) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
      const tx = db.transaction(store, 'readwrite');
      tx.objectStore(store).put(val, key);
      tx.oncomplete = () => resolve(true);
      tx.onerror    = () => reject(tx.error);
    });
  }

  global.SelfAICache = {
    async getDashboard()       { return txGet('dashboard', 'me'); },
    async setDashboard(data)   { return txPut('dashboard', 'me', { ...data, _cachedAt: Date.now() }); },
    async getClones()          { return txGet('clones', 'list'); },
    async setClones(data)      { return txPut('clones', 'list', { ...data, _cachedAt: Date.now() }); },
    async getHistory(cloneId)  { return txGet('history', cloneId); },
    async setHistory(cloneId, m) { return txPut('history', cloneId, { messages: m, _cachedAt: Date.now() }); },
  };
})(window);
