# ساخت خودکار APK با GitHub Actions
## بدون نیاز به Android Studio روی کامپیوتر شخصی

---

## گام ۱: ساخت keystore (یک‌بار)

روی هر کامپیوتری که Java نصب است:

```bash
keytool -genkey -v \
  -keystore pakdava.keystore \
  -alias pakdava \
  -keyalg RSA -keysize 2048 \
  -validity 10000 \
  -dname "CN=PakDava,O=Tabriz University,C=IR"

# رمز را یادداشت کنید!
```

تبدیل به base64:
```bash
# Linux/Mac:
base64 -w 0 pakdava.keystore

# Windows (PowerShell):
[Convert]::ToBase64String([IO.File]::ReadAllBytes("pakdava.keystore"))
```

---

## گام ۲: ایجاد Repository در GitHub

```bash
git init
git add .
git commit -m "PakDava Android App"
git remote add origin https://github.com/YOUR_USERNAME/pakdava-app.git
git push -u origin main
```

---

## گام ۳: تنظیم Secrets

در GitHub → Settings → Secrets → Actions:

| Secret | مقدار |
|--------|-------|
| `KEYSTORE_BASE64` | خروجی دستور base64 بالا |
| `KEYSTORE_PASSWORD` | رمز keystore |
| `KEY_ALIAS` | `pakdava` |
| `KEY_PASSWORD` | رمز key (معمولاً همان keystore) |

---

## گام ۴: اجرا

هر بار که کد را push کنید، GitHub:
1. React را build می‌کند
2. Capacitor sync می‌کند
3. APK امضاشده می‌سازد
4. در Releases قرار می‌دهد

**مسیر دانلود:** GitHub → Actions → آخرین run → Artifacts → `pakdava-release-apk`

---

## نکات مهم

- APK debug برای تست، Release برای انتشار
- برای Google Play از **AAB** استفاده کنید (workflow را تغییر دهید)
- keystore را در جای امن نگه دارید — بدون آن نمی‌توانید اپ را آپدیت کنید
