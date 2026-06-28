# اصلاحات Android

بعد از اینکه `npx cap add android` را اجرا کردید،
فایل‌های زیر را جایگزین کنید:

## 1. MainActivity.java
```
android/app/src/main/java/ir/pakdava/app/MainActivity.java
```
← با `android_patches/MainActivity.java` جایگزین کنید

## 2. build.gradle
در `android/app/build.gradle` این خط را comment کنید:
```gradle
// apply plugin: 'com.google.gms.google-services'
```

## چرا؟
این اصلاحات از crash شدن اپ هنگام نبود `google-services.json` جلوگیری می‌کند.
