# پک دوا — نسخه اندروید (Capacitor + React)
## راهنمای کامل ساخت APK

---

## معماری پازل‌گونه

```
┌─────────────────────────────┐     ┌─────────────────────────────┐
│   نسخه PHP (سرور شما)       │     │   نسخه JS (این پروژه)       │
│                             │     │                             │
│  patient/dashboard.php      │ ←→  │  src/screens/Dashboard.jsx  │
│  patient/clinical_data.php  │ ←→  │  src/screens/Clinical.jsx   │
│  patient/soc_assessment.php │ ←→  │  src/screens/SOC.jsx        │
│  patient/risk_assessment.php│ ←→  │  src/screens/Risk.jsx       │
│  patient/daily_plan.php     │ ←→  │  src/screens/DailyPlan.jsx  │
│  patient/population_data.php│ ←→  │  src/screens/Population.jsx │
│                             │     │                             │
│  ── همان API‌ها ──          │     │                             │
│  api/data.php               │ ←── │  services/api.js            │
│  api/sync.php               │ ←── │  services/api.js            │
│  api/auth_jwt.php  (جدید)  │ ←── │  services/api.js (JWT)      │
│  api/push_subscribe.php     │ ←── │  App.jsx (Push)             │
└─────────────────────────────┘     └─────────────────────────────┘
              ↑                                   ↑
           MySQL                             IndexedDB
        (روی سرور)                       (روی دستگاه)
```

---

## پیش‌نیازها

```bash
# Node.js 18+
node --version   # باید 18+ باشد

# Java 17 JDK
java -version    # باید 17+ باشد

# Android Studio
# دانلود: https://developer.android.com/studio
# بعد از نصب: Android SDK 34 را نصب کنید
```

---

## گام ۱: نصب وابستگی‌ها

```bash
cd pakdava_app
npm install
```

---

## گام ۲: تنظیم آدرس سرور

فایل `src/services/api.js` را باز کنید و URL سرور PHP خود را وارد کنید:

```javascript
const getBaseURL = () => {
  // این را به آدرس سرور خود تغییر دهید:
  return 'https://your-server.ir/pakdava';
};
```

---

## گام ۳: Build وب

```bash
npm run build
# خروجی در پوشه dist/
```

---

## گام ۴: اضافه کردن Android

```bash
npm install @capacitor/cli
npx cap add android
npx cap sync android
```

---

## گام ۵: باز کردن در Android Studio

```bash
npx cap open android
```

در Android Studio:
1. صبر کنید تا Gradle sync کامل شود
2. **Build → Generate Signed Bundle / APK**
3. **APK → Create new keystore:**
   - Key store path: `pakdava.keystore`
   - Alias: `pakdava`
   - Password: (رمز انتخابی خود)
   - Validity: 25 years
4. **Build → Release**

خروجی: `android/app/release/app-release.apk`

---

## گام ۶: نصب روی گوشی

```bash
# روش ۱: ADB (گوشی به کامپیوتر متصل)
adb install android/app/release/app-release.apk

# روش ۲: انتقال فایل
# فایل app-release.apk را به گوشی انتقال دهید
# Settings > Security > Install from unknown sources
```

---

## فایل جدید PHP که باید به سرور اضافه کنید

```
pakdava_final/api/auth_jwt.php  ← این فایل را آپلود کنید
```

این فایل احراز هویت JWT برای نسخه اندروید را مدیریت می‌کند.
نسخه وب همچنان با session PHP کار می‌کند.

---

## اولین اجرا در گوشی

1. اپ را باز کنید
2. **آدرس سرور PHP را وارد کنید:**
   ```
   https://your-server.ir/pakdava
   ```
3. این آدرس یک‌بار ذخیره می‌شود
4. با نام کاربری و رمز وارد شوید

---

## توسعه سریع (Live Reload)

```bash
# در capacitor.config.ts:
server: {
  url: 'https://192.168.1.X:5173',  # IP کامپیوتر شما
  cleartext: true,
}

# اجرا:
npm run dev     # در ترمینال اول
npx cap run android  # در ترمینال دوم
```

---

## ساختار پروژه

```
pakdava_app/
├── src/
│   ├── App.jsx                    # مسیریابی + احراز هویت
│   ├── main.jsx                   # entry point
│   ├── screens/
│   │   ├── LoginScreen.jsx
│   │   ├── DashboardScreen.jsx
│   │   ├── ClinicalScreen.jsx     # ← مطابق clinical_data.php
│   │   ├── SOCScreen.jsx          # ← مطابق soc_assessment.php
│   │   ├── RiskScreen.jsx         # ← مطابق risk_assessment.php
│   │   ├── DailyPlanScreen.jsx    # ← مطابق daily_plan.php
│   │   ├── ProgressScreen.jsx     # ← مطابق progress.php
│   │   ├── NotificationsScreen.jsx
│   │   ├── PeerScreen.jsx         # ← مطابق peer_compare.php
│   │   ├── PopulationScreen.jsx   # ← NCD-RisC داده‌های واقعی
│   │   ├── SettingsScreen.jsx
│   │   ├── ServerSetupScreen.jsx
│   │   └── doctor/
│   │       ├── DashboardScreen.jsx
│   │       ├── ApprovalsScreen.jsx
│   │       └── EnterClinicalScreen.jsx
│   ├── components/
│   │   └── AppShell.jsx           # Layout + Navigation
│   ├── services/
│   │   ├── api.js                 # ← همان api/data.php و api/sync.php
│   │   └── db.js                  # ← IndexedDB (آفلاین)
│   └── store/
│       └── appStore.js            # Zustand state management
├── capacitor.config.ts
├── vite.config.js
├── package.json
└── index.html
```
