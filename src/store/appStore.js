import { create } from 'zustand';
import { DataAPI, NetworkService } from '../services/api';
import { DB } from '../services/db';

// ── App Store ─────────────────────────────────────────────────────────────
export const useAppStore = create((set, get) => ({
  // ── Auth ────────────────────────────────────────────────────────────────
  user:     null,
  isOnline: true,
  loading:  false,

  setUser:     (user)     => set({ user }),
  setOnline:   (isOnline) => set({ isOnline }),
  setLoading:  (loading)  => set({ loading }),

  // ── Clinical Data ────────────────────────────────────────────────────────
  clinicalData: [],
  bmiHistory:   [],
  setClinical:  (rows) => set({ clinicalData: rows }),
  setBMI:       (rows) => set({ bmiHistory: rows }),

  // ── SOC ─────────────────────────────────────────────────────────────────
  socRecords:   [],
  currentStage: 'contemplation',
  setSoc:       (rows)  => set({ socRecords: rows, currentStage: rows[0]?.stage || 'contemplation' }),

  // ── Risk ─────────────────────────────────────────────────────────────────
  riskRecords:  [],
  setRisk:      (rows) => set({ riskRecords: rows }),

  // ── Daily Plan ───────────────────────────────────────────────────────────
  dailyPlans:   [],
  setDaily:     (rows) => set({ dailyPlans: rows }),

  // ── Progress ─────────────────────────────────────────────────────────────
  progress:     [],
  setProgress:  (rows) => set({ progress: rows }),

  // ── Notifications ─────────────────────────────────────────────────────────
  notifications:  [],
  unreadCount:    0,
  setNotifications: (rows) => set({
    notifications: rows,
    unreadCount:   rows.filter(n => !n.is_read).length,
  }),

  // ── NCD-RisC ─────────────────────────────────────────────────────────────
  ncdData:    {},
  setNcdData: (rows) => {
    const grouped = {};
    rows.forEach(r => {
      if (!grouped[r.indicator])           grouped[r.indicator] = {};
      if (!grouped[r.indicator][r.sex])    grouped[r.indicator][r.sex] = {};
      grouped[r.indicator][r.sex][r.year] = r;
    });
    set({ ncdData: grouped });
  },

  // ── Peer ─────────────────────────────────────────────────────────────────
  peerData:   [],
  setPeer:    (rows) => set({ peerData: rows }),

  // ── Pending Sync ─────────────────────────────────────────────────────────
  pendingCount: 0,
  setPending:   (n) => set({ pendingCount: n }),

  // ══════════════════════════════════════════════════════════════════════════
  // ACTIONS
  // ══════════════════════════════════════════════════════════════════════════

  // بارگذاری همه داده‌ها از سرور + ذخیره در IDB
  async fetchAll() {
    set({ loading: true });
    try {
      const d = await DataAPI.fetchAll();

      // ذخیره در IDB
      await Promise.all([
        d.clinical?.length     && DB.bulkSave('clinical_data',   d.clinical),
        d.bmi_history?.length  && DB.bulkSave('bmi_history',     d.bmi_history),
        d.daily?.length        && DB.bulkSave('daily_plans',      d.daily),
        d.soc?.length          && DB.bulkSave('soc_records',      d.soc),
        d.risk?.length         && DB.bulkSave('risk_records',     d.risk),
        d.progress?.length     && DB.bulkSave('progress_records', d.progress),
        d.notifications?.length&& DB.bulkSave('notifications',    d.notifications),
        d.ncd_risc?.length     && DB.bulkSave('ncd_risc',         d.ncd_risc),
        d.peer?.length         && DB.bulkSave('peer_data',         d.peer),
        d.user                 && DB.saveUserState(d.user),
      ].filter(Boolean));

      // به‌روز کردن store
      const s = get();
      if (d.user)          set({ user: { ...s.user, ...d.user } });
      if (d.clinical)      s.setClinical(d.clinical);
      if (d.bmi_history)   s.setBMI(d.bmi_history);
      if (d.soc)           s.setSoc(d.soc);
      if (d.risk)          s.setRisk(d.risk);
      if (d.daily)         s.setDaily(d.daily);
      if (d.progress)      s.setProgress(d.progress);
      if (d.notifications) s.setNotifications(d.notifications);
      if (d.ncd_risc)      s.setNcdData(d.ncd_risc);
      if (d.peer)          s.setPeer(d.peer);

    } catch (err) {
      console.warn('[Store] fetchAll failed, loading from IDB:', err.message);
      await get().loadFromIDB();
    } finally {
      set({ loading: false });
      await get().updatePending();
    }
  },

  // بارگذاری از IDB (آفلاین)
  async loadFromIDB() {
    const [clinical, bmi, soc, risk, daily, progress, notifs, ncd, peer, user] =
      await Promise.all([
        DB.getAll('clinical_data'),
        DB.getAll('bmi_history'),
        DB.getAll('soc_records'),
        DB.getAll('risk_records'),
        DB.getAll('daily_plans'),
        DB.getAll('progress_records'),
        DB.getAll('notifications'),
        DB.getAll('ncd_risc'),
        DB.getAll('peer_data'),
        DB.getUserState(),
      ]);

    const s = get();
    s.setClinical(clinical);
    s.setBMI(bmi);
    s.setSoc(soc);
    s.setRisk(risk);
    s.setDaily(daily);
    s.setProgress(progress);
    s.setNotifications(notifs);
    s.setNcdData(ncd);
    s.setPeer(peer);
    if (user) set({ user: { ...s.user, ...user } });
  },

  // همگام‌سازی صف‌های آفلاین
  async syncQueues() {
    const queues = ['clinical_queue', 'soc_queue', 'daily_queue', 'risk_queue'];
    let total = 0;

    for (const store of queues) {
      const pending = await DB.getAll(store, { synced: 0 });
      if (!pending.length) continue;
      try {
        const result = await DataAPI.sync(store, pending);
        if (result.success) {
          for (const r of pending) {
            await DB.set(store, { ...r, synced: 1 });
          }
          total += pending.length;
        }
      } catch (err) {
        console.warn('[Store] sync failed:', store, err.message);
      }
    }

    await get().updatePending();
    if (total > 0) await get().fetchAll();
    return total;
  },

  async updatePending() {
    const count = await DB.totalPending();
    set({ pendingCount: count });
  },
}));
