import React, { useEffect, useState } from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { App as CapApp }    from '@capacitor/app';
import { SplashScreen }     from '@capacitor/splash-screen';
import { StatusBar, Style } from '@capacitor/status-bar';
import { AuthAPI, NetworkService } from './services/api.js';
import { useAppStore }     from './store/appStore.js';
import { Preferences }     from '@capacitor/preferences';

import LoginScreen         from './screens/LoginScreen.jsx';
import DashboardScreen     from './screens/DashboardScreen.jsx';
import ClinicalScreen      from './screens/ClinicalScreen.jsx';
import SOCScreen           from './screens/SOCScreen.jsx';
import RiskScreen          from './screens/RiskScreen.jsx';
import DailyPlanScreen     from './screens/DailyPlanScreen.jsx';
import ProgressScreen      from './screens/ProgressScreen.jsx';
import NotificationsScreen from './screens/NotificationsScreen.jsx';
import PeerScreen          from './screens/PeerScreen.jsx';
import PopulationScreen    from './screens/PopulationScreen.jsx';
import SettingsScreen      from './screens/SettingsScreen.jsx';
import DoctorDashboard     from './screens/doctor/DashboardScreen.jsx';
import ApprovalsScreen     from './screens/doctor/ApprovalsScreen.jsx';
import EnterClinicalScreen from './screens/doctor/EnterClinicalScreen.jsx';
import AppShell            from './components/AppShell.jsx';

export default function App() {
  const { user, setUser, setOnline, fetchAll, syncQueues, loadFromIDB } = useAppStore();
  const [ready, setReady] = useState(false);

  useEffect(() => { initApp(); }, []);

  async function initApp() {
    // StatusBar
    try {
      await StatusBar.setBackgroundColor({ color: '#1A7A4A' });
      await StatusBar.setStyle({ style: Style.Dark });
    } catch {}

    // سرور پیش‌فرض
    const { value: savedUrl } = await Preferences.get({ key: 'pd_server_url' });
    window.__PAKDAVA_SERVER_URL__ = savedUrl || 'https://myhealthcare.ir/packdava';
    if (!savedUrl) {
      await Preferences.set({ key: 'pd_server_url', value: window.__PAKDAVA_SERVER_URL__ });
    }

    // احراز هویت
    try {
      const online = await NetworkService.isOnline();
      setOnline(online);
      if (online) {
        const u = await AuthAPI.check();
        if (u) { setUser(u); await fetchAll(); }
      } else {
        await loadFromIDB();
        const { DB } = await import('./services/db.js');
        const saved = await DB.getUserState();
        if (saved) setUser(saved);
      }
    } catch (e) {
      console.warn('[Init] Auth error:', e.message);
      try { await loadFromIDB(); } catch {}
    }

    // Network changes
    NetworkService.onChange(async online => {
      setOnline(online);
      if (online) {
        try { const n = await syncQueues(); if (n > 0) fetchAll(); } catch {}
      }
    });

    // Push — بدون crash اگر Firebase نیست
    initPushSafe();

    // Back button
    try {
      CapApp.addListener('backButton', ({ canGoBack }) => {
        if (canGoBack) window.history.back();
        else CapApp.exitApp();
      });
    } catch {}

    setReady(true);
    try { await SplashScreen.hide(); } catch {}
  }

  // ══════════════════════════════════════════════════════════════════
  // PUSH — کاملاً ایمن، بدون crash حتی بدون google-services.json
  // ══════════════════════════════════════════════════════════════════
  async function initPushSafe() {
    try {
      const { PushNotifications } = await import('@capacitor/push-notifications');

      // بررسی مجوز
      const { receive } = await PushNotifications.checkPermissions();
      if (receive !== 'granted') {
        const { receive: req } = await PushNotifications.requestPermissions();
        if (req !== 'granted') {
          console.log('[Push] Permission denied — push disabled');
          return;
        }
      }

      // register — ممکن است با Firebase error مواجه شود
      await PushNotifications.register();

      PushNotifications.addListener('registration', ({ value: token }) => {
        console.log('[Push] ✅ Token received');
        import('./services/api.js').then(({ DataAPI }) => {
          DataAPI.subscribePush({ token, type: 'fcm' }).catch(() => {});
        });
      });

      // ── خطای Firebase را catch کن — بدون crash ──────────────
      PushNotifications.addListener('registrationError', ({ error }) => {
        console.warn('[Push] ⚠️ Firebase error (ignored):', error);
        // اپ به کار خود ادامه می‌دهد
      });

      PushNotifications.addListener('pushNotificationReceived', notif => {
        console.log('[Push] Notification:', notif.title);
      });

    } catch (err) {
      // هر خطایی در Push → فقط log، بدون crash
      console.warn('[Push] Disabled:', err.message);
    }
  }

  if (!ready) return (
    <div style={{
      display:'flex', flexDirection:'column', alignItems:'center',
      justifyContent:'center', minHeight:'100vh',
      background:'linear-gradient(160deg,#1A7A4A,#0D5C36)',
      color:'white', fontFamily:'Vazirmatn,Tahoma', gap:'16px'
    }}>
      <div style={{fontSize:'72px'}}>💊</div>
      <div style={{fontSize:'28px',fontWeight:'800'}}>پک دوا</div>
      <div style={{fontSize:'13px',opacity:.8}}>در حال بارگذاری...</div>
    </div>
  );

  if (!user) return <LoginScreen onLogin={async u => { setUser(u); fetchAll(); }} />;

  return (
    <BrowserRouter>
      <AppShell isDoctor={user.role === 'doctor'}>
        <Routes>
          {user.role === 'doctor' ? (
            <>
              <Route path="/"              element={<DoctorDashboard />} />
              <Route path="/approvals"     element={<ApprovalsScreen />} />
              <Route path="/enter-data"    element={<EnterClinicalScreen />} />
              <Route path="/notifications" element={<NotificationsScreen />} />
              <Route path="/settings"      element={<SettingsScreen />} />
              <Route path="*"              element={<Navigate to="/" replace />} />
            </>
          ) : (
            <>
              <Route path="/"              element={<DashboardScreen />} />
              <Route path="/clinical"      element={<ClinicalScreen />} />
              <Route path="/soc"           element={<SOCScreen />} />
              <Route path="/risk"          element={<RiskScreen />} />
              <Route path="/daily"         element={<DailyPlanScreen />} />
              <Route path="/progress"      element={<ProgressScreen />} />
              <Route path="/notifications" element={<NotificationsScreen />} />
              <Route path="/peer"          element={<PeerScreen />} />
              <Route path="/population"    element={<PopulationScreen />} />
              <Route path="/settings"      element={<SettingsScreen />} />
              <Route path="*"              element={<Navigate to="/" replace />} />
            </>
          )}
        </Routes>
      </AppShell>
    </BrowserRouter>
  );
}
