import React, { useEffect, useState } from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { App as CapApp }   from '@capacitor/app';
import { SplashScreen }    from '@capacitor/splash-screen';
import { StatusBar, Style } from '@capacitor/status-bar';
import { PushNotifications } from '@capacitor/push-notifications';
import { AuthAPI, NetworkService } from './services/api';
import { useAppStore }    from './store/appStore';
import { Preferences }    from '@capacitor/preferences';

// Screens
import LoginScreen       from './screens/LoginScreen';
import DashboardScreen   from './screens/DashboardScreen';
import ClinicalScreen    from './screens/ClinicalScreen';
import SOCScreen         from './screens/SOCScreen';
import RiskScreen        from './screens/RiskScreen';
import DailyPlanScreen   from './screens/DailyPlanScreen';
import ProgressScreen    from './screens/ProgressScreen';
import NotificationsScreen from './screens/NotificationsScreen';
import PeerScreen        from './screens/PeerScreen';
import PopulationScreen  from './screens/PopulationScreen';
import SettingsScreen    from './screens/SettingsScreen';
import ServerSetupScreen from './screens/ServerSetupScreen';
// Doctor screens
import DoctorDashboard   from './screens/doctor/DashboardScreen';
import ApprovalsScreen   from './screens/doctor/ApprovalsScreen';
import EnterClinicalScreen from './screens/doctor/EnterClinicalScreen';

// Layout
import AppShell from './components/AppShell';

export default function App() {
  const { user, setUser, setOnline, isOnline, fetchAll, syncQueues, loadFromIDB } = useAppStore();
  const [authChecked, setAuthChecked] = useState(false);
  const [serverSet,   setServerSet]   = useState(false);

  useEffect(() => {
    initApp();
  }, []);

  async function initApp() {
    // ── StatusBar ──────────────────────────────────────────────────────
    try {
      await StatusBar.setBackgroundColor({ color: '#1A7A4A' });
      await StatusBar.setStyle({ style: Style.Dark });
    } catch {}

    // ── بررسی تنظیم سرور ─────────────────────────────────────────────
    const { value: serverUrl } = await Preferences.get({ key: 'pd_server_url' });
    if (serverUrl) {
      window.__PAKDAVA_SERVER_URL__ = serverUrl;
      setServerSet(true);
    } else {
      setServerSet(false);
      await SplashScreen.hide();
      setAuthChecked(true);
      return;
    }

    // ── بررسی احراز هویت ─────────────────────────────────────────────
    try {
      const loggedUser = await AuthAPI.check();
      if (loggedUser) {
        setUser(loggedUser);
        // بارگذاری داده
        const online = await NetworkService.isOnline();
        setOnline(online);
        if (online) await fetchAll();
        else        await loadFromIDB();
      }
    } catch {
      await loadFromIDB();
    }

    // ── Network listener ──────────────────────────────────────────────
    NetworkService.onChange(async connected => {
      setOnline(connected);
      if (connected) {
        const synced = await syncQueues();
        if (synced > 0) await fetchAll();
      }
    });

    // ── Push Notifications ────────────────────────────────────────────
    initPush();

    // ── Android back button ───────────────────────────────────────────
    CapApp.addListener('backButton', ({ canGoBack }) => {
      if (canGoBack) window.history.back();
      else CapApp.exitApp();
    });

    setAuthChecked(true);
    await SplashScreen.hide();
  }

  async function initPush() {
    try {
      const perm = await PushNotifications.checkPermissions();
      if (perm.receive !== 'granted') {
        await PushNotifications.requestPermissions();
      }
      await PushNotifications.register();
      PushNotifications.addListener('registration', token => {
        // ارسال token به سرور PHP
        import('./services/api').then(({ DataAPI }) => {
          DataAPI.subscribePush({ token: token.value, type: 'fcm' }).catch(() => {});
        });
      });
      PushNotifications.addListener('pushNotificationReceived', notif => {
        console.log('[Push] Received:', notif);
      });
    } catch (err) {
      console.warn('[Push] Init error:', err);
    }
  }

  if (!authChecked) {
    return (
      <div style={{
        display:'flex', alignItems:'center', justifyContent:'center',
        minHeight:'100vh', background:'#1A7A4A', color:'white',
        fontFamily:'Vazirmatn,Tahoma', flexDirection:'column', gap:'16px',
      }}>
        <div style={{ fontSize:'64px' }}>💊</div>
        <div style={{ fontSize:'24px', fontWeight:'800' }}>پک دوا</div>
        <div style={{ fontSize:'14px', opacity:'.8' }}>در حال بارگذاری...</div>
      </div>
    );
  }

  if (!serverSet) return <ServerSetupScreen onSet={() => { setServerSet(true); window.location.reload(); }} />;
  if (!user)      return <LoginScreen onLogin={u => { setUser(u); fetchAll(); }} />;

  const isDoctor = user.role === 'doctor';

  return (
    <BrowserRouter>
      <AppShell isDoctor={isDoctor} isOnline={isOnline}>
        <Routes>
          {isDoctor ? (
            <>
              <Route path="/"             element={<DoctorDashboard />} />
              <Route path="/approvals"    element={<ApprovalsScreen />} />
              <Route path="/enter-data"   element={<EnterClinicalScreen />} />
              <Route path="/notifications"element={<NotificationsScreen />} />
              <Route path="/settings"     element={<SettingsScreen />} />
              <Route path="*"             element={<Navigate to="/" replace />} />
            </>
          ) : (
            <>
              <Route path="/"             element={<DashboardScreen />} />
              <Route path="/clinical"     element={<ClinicalScreen />} />
              <Route path="/soc"          element={<SOCScreen />} />
              <Route path="/risk"         element={<RiskScreen />} />
              <Route path="/daily"        element={<DailyPlanScreen />} />
              <Route path="/progress"     element={<ProgressScreen />} />
              <Route path="/notifications"element={<NotificationsScreen />} />
              <Route path="/peer"         element={<PeerScreen />} />
              <Route path="/population"   element={<PopulationScreen />} />
              <Route path="/settings"     element={<SettingsScreen />} />
              <Route path="*"             element={<Navigate to="/" replace />} />
            </>
          )}
        </Routes>
      </AppShell>
    </BrowserRouter>
  );
}
