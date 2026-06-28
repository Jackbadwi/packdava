// ── Push Notifications — Firebase-free version ─────────────────────────────
// فقط وقتی google-services.json موجود باشد فعال کن
async function initPush() {
  try {
    // بررسی اینکه Firebase آماده است یا نه
    const { PushNotifications } = await import('@capacitor/push-notifications');
    const perm = await PushNotifications.checkPermissions();
    if (perm.receive !== 'granted') {
      const req = await PushNotifications.requestPermissions();
      if (req.receive !== 'granted') return; // بدون اجازه → رد شو
    }
    await PushNotifications.register();
    PushNotifications.addListener('registration', token => {
      console.log('[Push] Token:', token.value);
    });
    PushNotifications.addListener('pushNotificationReceived', notif => {
      console.log('[Push] Received:', notif);
    });
    PushNotifications.addListener('registrationError', err => {
      console.warn('[Push] Registration error — Firebase may not be configured:', err);
    });
  } catch (err) {
    // Firebase نصب نیست یا google-services.json ندارد — بدون crash ادامه بده
    console.warn('[Push] Disabled — Firebase not initialized:', err.message);
  }
}
