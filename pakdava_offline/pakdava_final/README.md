# پک دوا — سامانه مدیریت یکپارچه دیابت
## PakDava Diabetes Management Web Application

---

## معرفی
وب‌اپلیکیشن PHP-based برای پیشگیری، کنترل و ارتقای سلامت بیماران دیابتی
بر اساس **مدل مراحل تغییر رفتاری (SOC)** — Prochaska & DiClemente
داده‌های جمعیتی: **NCD Risk Factor Collaboration — Lancet 2016**

---

## ساختار پروژه
```
pakdava_web/
├── index.php                    # صفحه ورود (login)
├── api/
│   └── auth.php                 # احراز هویت و session
├── assets/
│   ├── css/main.css             # استایل کامل RTL فارسی
│   └── sidebar_patient.php      # sidebar بیمار
├── patient/
│   ├── dashboard.php            # داشبورد بیمار
│   ├── risk_assessment.php      # ارزیابی ریسک FINDRISC
│   ├── soc_assessment.php       # ارزیابی مرحله SOC
│   ├── daily_plan.php           # برنامه روزانه مداخله
│   ├── clinical_data.php        # ورود داده‌های بالینی
│   ├── progress.php             # نمودار پیشرفت
│   ├── population_data.php      # داده‌های NCD-RisC ایران ✓ واقعی
│   └── peer_compare.php         # مقایسه ناشناس با همتایان
└── doctor/
    └── dashboard.php            # داشبورد پزشک
```

---

## نصب و راه‌اندازی

### پیش‌نیازها
- PHP 7.4 یا بالاتر
- Web server: Apache یا Nginx
- (اختیاری) MySQL برای ذخیره داده واقعی

### نصب روی localhost
```bash
# با XAMPP
cp -r pakdava_web/ /xampp/htdocs/pakdava/
# سپس: http://localhost/pakdava/

# با PHP built-in server
cd pakdava_web
php -S localhost:8080
# سپس: http://localhost:8080/
```

### نصب روی سرور
```bash
cp -r pakdava_web/ /var/www/html/pakdava/
chown -R www-data:www-data /var/www/html/pakdava/
# سپس: http://yourserver.com/pakdava/
```

---

## حساب‌های آزمایشی
| نقش | نام کاربری | رمز |
|-----|-----------|-----|
| بیمار | patient001 | 1234 |
| پزشک | dr_ahmadi | 1234 |

---

## داده‌های NCD-RisC استفاده‌شده
- **Iran (1).csv** — شیوع دیابت ایران ۱۹۸۰–۲۰۱۴ (Lancet 2016)
- **NCD_RisC_Lancet_2017_BMI_age_standardised_Iran.csv** — BMI ایران
- **NCD_RisC_Nature_2019_age_standardised_country-Iran.csv** — BMI شهری/روستایی

---

## ماژول‌های پیاده‌سازی‌شده

### پنل بیمار
- [x] ارزیابی ریسک FINDRISC (تطبیق‌یافته برای ایران)
- [x] تعیین مرحله SOC (5 مرحله)
- [x] برنامه روزانه مداخله (بر اساس SOC)
- [x] ورود داده‌های بالینی (FBS, HbA1c, BMI, BP)
- [x] نمودارهای NCD-RisC style
- [x] مقایسه ناشناس با همتایان
- [x] داده‌های جمعیتی واقعی ایران

### پنل پزشک
- [x] داشبورد نظارتی
- [x] لیست بیماران با ریسک و SOC
- [x] تأیید داده‌های بالینی
- [x] هشدارهای بیمار پرریسک
- [x] توزیع SOC بیماران

---

## توسعه آتی (Backend کامل)
برای تبدیل به سیستم واقعی:
1. اتصال MySQL: `api/db.php`
2. CRUD کامل بیماران: `api/patients.php`
3. API ذخیره داده: `api/clinical.php`
4. سیستم notification: push notifications
5. Export PDF گزارشات

---

منبع: پایان‌نامه دکتری — سعید بدخشان
دانشگاه علوم پزشکی تبریز — ۱۴۰۴
