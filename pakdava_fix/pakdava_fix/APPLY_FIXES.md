# راهنمای اعمال اصلاحات Firebase Crash

## مشکل
```
java.lang.IllegalStateException: Default FirebaseApp is not initialized
```
`@capacitor/push-notifications` به Firebase نیاز دارد اما `google-services.json` در پروژه نیست.

---

## فایل‌هایی که باید در GitHub تغییر کنند

### ۱. `src/App.jsx` ← کاملاً جایگزین کنید
فایل `App.jsx` ارسالی را جایگزین کنید.
تغییر اصلی: `initPushSafe()` به جای `initPush()` که crash نمی‌کند.

### ۲. `package.json` ← جایگزین کنید
`@capacitor/push-notifications` به `optionalDependencies` منتقل شد.

### ۳. `capacitor.config.js` ← جایگزین کنید
PushNotifications از plugins حذف شد.

### ۴. `.github/workflows/build-apk.yml` ← جایگزین کنید
گام جدید اضافه شد: **dummy google-services.json** می‌سازد
تا build بدون Firebase crash نکند.

### ۵. `android/app/build.gradle` ← اصلاح کنید
خط `apply plugin: 'com.google.gms.google-services'` را comment کنید:
```gradle
// apply plugin: 'com.google.gms.google-services'
```

### ۶. `android/app/src/main/java/ir/pakdava/app/MainActivity.java` ← جایگزین کنید
Firebase را با reflection ایمن init می‌کند.

---

## روش سریع: فقط ۳ فایل را عوض کن

اگر می‌خواهی سریع‌ترین راه‌حل را اعمال کنی، فقط این ۳ فایل را تغییر بده:

**۱. `.github/workflows/build-apk.yml`** ← مهم‌ترین فایل
workflow جدید dummy google-services.json می‌سازد.

**۲. `src/App.jsx`**
push notification را با try/catch ایمن می‌کند.

**۳. `android/app/build.gradle`**
یک خط را comment کن:
```
// apply plugin: 'com.google.gms.google-services'
```

---

## بعداً — اگر خواستی Push Notification واقعی

۱. Firebase Console → New Project → PakDava
۲. Add Android App → `ir.pakdava.app`
۳. دانلود `google-services.json`
۴. اضافه کردن به `android/app/google-services.json`
۵. در `build.gradle` خط را uncomment کن
۶. در `capacitor.config.js` plugin را فعال کن
