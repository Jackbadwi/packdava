/**
 * PakDava Dashboard Offline Layer
 * بارگذاری داده از IndexedDB هنگام آفلاین بودن
 * این فایل در انتهای patient/dashboard.php include می‌شود
 */
(async function dashboardOfflineInit() {
  if (typeof PakDavaDB === 'undefined') return;

  // ── بارگذاری داده از IDB ─────────────────────────────────────────────
  async function loadFromIDB() {
    try {
      const [clinical, soc, risk, notifs, user] = await Promise.all([
        PakDavaDB.getAll('clinical_data'),
        PakDavaDB.getAll('soc_records'),
        PakDavaDB.getAll('risk_records'),
        PakDavaDB.getAll('notifications'),
        PakDavaDB.getUserState(),
      ]);

      // ── به‌روزرسانی نام کاربر ────────────────────────────────────
      if (user?.fullname) {
        document.querySelectorAll('.user-name,.topbar-subtitle').forEach(el => {
          if (el.textContent.includes('خوش آمدید') || el.classList.contains('user-name')) {
            el.textContent = el.textContent.replace(/خوش آمدید.*$/, `خوش آمدید، ${user.fullname}`);
          }
        });
      }

      // ── به‌روزرسانی badge اعلانات ────────────────────────────────
      const unread = notifs.filter(n => !n.is_read).length;
      document.querySelectorAll('.notif-count,.nav-badge').forEach(el => {
        if (el.closest('[href*="notifications"]') || el.id === 'notif-badge') {
          el.textContent = unread || '';
          el.style.display = unread > 0 ? 'inline' : 'none';
        }
      });

      // ── به‌روزرسانی امتیاز ریسک ──────────────────────────────────
      if (risk.length > 0) {
        const latest = risk[0];
        const scoreEl = document.getElementById('idb-risk-score');
        const labelEl = document.getElementById('idb-risk-label');
        if (scoreEl) scoreEl.textContent = latest.risk_score ?? '—';
        if (labelEl) labelEl.textContent = latest.risk_level ?? '—';
      }

      // ── به‌روزرسانی مرحله SOC ────────────────────────────────────
      if (soc.length > 0) {
        const latest = soc[0];
        const stageEl = document.getElementById('idb-soc-stage');
        const stageMap = {
          'precontemplation': 'پیش از تأمل',
          'contemplation':    'تأمل',
          'preparation':      'آماده‌سازی',
          'action':           'عمل',
          'maintenance':      'نگهداری',
        };
        if (stageEl) stageEl.textContent = stageMap[latest.stage] ?? latest.stage ?? '—';
      }

      // ── به‌روزرسانی آخرین داده بالینی ───────────────────────────
      if (clinical.length > 0) {
        const latest = clinical[0];
        const fields = {
          'idb-fbs':   latest.fbs    ? `${latest.fbs} mg/dL`  : null,
          'idb-hba1c': latest.hba1c  ? `${latest.hba1c}%`     : null,
          'idb-bp':    latest.bp_systolic ? `${latest.bp_systolic}/${latest.bp_diastolic}` : null,
          'idb-chol':  latest.cholesterol_total ? `${latest.cholesterol_total} mg/dL` : null,
          'idb-bmi':   latest.bmi    ? `${latest.bmi}`         : null,
        };
        Object.entries(fields).forEach(([id, val]) => {
          const el = document.getElementById(id);
          if (el && val) el.textContent = val;
        });
      }

      // ── نمایش badge آفلاین ───────────────────────────────────────
      if (!navigator.onLine) {
        const pending = await PakDavaDB.totalPending();
        if (pending > 0) {
          const badge = document.createElement('div');
          badge.style.cssText = 'position:fixed;top:70px;left:50%;transform:translateX(-50%);background:#E67E22;color:white;padding:8px 20px;border-radius:99px;font-size:12px;font-weight:700;z-index:100;font-family:Vazirmatn,Tahoma';
          badge.textContent = `⚠️ آفلاین — ${pending} رکورد در انتظار همگام‌سازی`;
          document.body.appendChild(badge);
        }
      }

      console.log('[Dashboard] IDB data loaded:', { clinical: clinical.length, soc: soc.length, risk: risk.length });
    } catch (err) {
      console.warn('[Dashboard] IDB load error:', err);
    }
  }

  // اجرا
  await loadFromIDB();

  // گوش به رویداد data-ready (وقتی sync.js داده تازه دریافت کرد)
  document.addEventListener('pakdava:data-ready', () => loadFromIDB());
  document.addEventListener('pakdava:idb-loaded', () => loadFromIDB());
})();
