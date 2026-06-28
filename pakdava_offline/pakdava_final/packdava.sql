-- ═══════════════════════════════════════════════════════════════
-- PakDava Database Schema v3.0
-- دیتابیس سامانه مدیریت دیابت پک دوا
-- شامل: کلسترول، فشارخون، HbA1c، FBS، و تمام داده‌های NCD-RisC
-- ═══════════════════════════════════════════════════════════════
USE `healthap_myhealthcare`;

-- ─────────────────────────────────────────────────────────────
-- 1. جدول کاربران (اصلاح شده: fullname, age, bmi اضافه شد)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
  `id`            INT(11)      NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(50)  NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role`          ENUM('doctor','patient') NOT NULL,
  `fullname`      VARCHAR(100) NOT NULL,
  `name`          VARCHAR(100) AS (fullname) VIRTUAL,
  `email`         VARCHAR(100) NOT NULL,
  `phone`         VARCHAR(20)  DEFAULT NULL,
  `dob`           DATE         DEFAULT NULL,
  `age`           TINYINT      DEFAULT NULL,
  `gender`        ENUM('male','female','other') DEFAULT NULL,
  `address`       TEXT         DEFAULT NULL,
  `emergency_contact` VARCHAR(100) DEFAULT NULL,
  `height`        DECIMAL(5,2) DEFAULT NULL COMMENT 'cm',
  `weight`        DECIMAL(5,2) DEFAULT NULL COMMENT 'kg',
  `bmi_value`     DECIMAL(4,1) DEFAULT NULL,
  `bmi_status`    VARCHAR(30)  DEFAULT NULL,
  `profile_pic`   VARCHAR(255) DEFAULT NULL,
  `student_id`    VARCHAR(50)  DEFAULT NULL,
  `school`        VARCHAR(100) DEFAULT NULL,
  `last_login`    TIMESTAMP    NULL DEFAULT NULL,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`),
  UNIQUE KEY `uk_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────
-- 2. جدول بیماران
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `patients` (
  `id`                INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`           INT(11) NOT NULL,
  `dob`               DATE    DEFAULT NULL,
  `gender`            ENUM('male','female','other') DEFAULT NULL,
  `phone`             VARCHAR(20)  DEFAULT NULL,
  `address`           TEXT         DEFAULT NULL,
  `emergency_contact` VARCHAR(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_patients_user` (`user_id`),
  CONSTRAINT `fk_patients_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────
-- 3. جدول پزشکان
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `doctors` (
  `id`             INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`        INT(11)      NOT NULL,
  `specialty`      VARCHAR(100) DEFAULT NULL,
  `phone`          VARCHAR(20)  DEFAULT NULL,
  `license_number` VARCHAR(50)  DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_doctors_user` (`user_id`),
  CONSTRAINT `fk_doctors_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────
-- 4. جدول ارتباط پزشک-بیمار
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `doctor_patients` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `doctor_id`  INT(11) NOT NULL,
  `patient_id` INT(11) NOT NULL,
  `assigned_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dp` (`doctor_id`, `patient_id`),
  KEY `fk_dp_doctor`  (`doctor_id`),
  KEY `fk_dp_patient` (`patient_id`),
  CONSTRAINT `fk_dp_doctor`  FOREIGN KEY (`doctor_id`)  REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dp_patient` FOREIGN KEY (`patient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────
-- 5. جدول داده‌های بالینی کامل (اصلاح شده: FBS, HbA1c, کلسترول, فشارخون اضافه شد)
--    منابع: NCD-RisC Excel files (Diabetes, BMI, BP, Cholesterol)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `clinical_data` (
  `id`             INT(11)      NOT NULL AUTO_INCREMENT,
  `patient_id`     INT(11)      NOT NULL,
  `record_date`    DATE         NOT NULL,
  -- آزمایش‌های دیابت
  `fbs`            DECIMAL(6,1) DEFAULT NULL COMMENT 'Fasting Blood Sugar mg/dL — NCD-RisC Diabetes dataset',
  `ppg`            DECIMAL(6,1) DEFAULT NULL COMMENT 'Post-Prandial Glucose mg/dL',
  `hba1c`          DECIMAL(4,2) DEFAULT NULL COMMENT 'Glycated Hemoglobin % — NCD-RisC',
  -- فشارخون (NCD-RisC Blood Pressure dataset)
  `bp_systolic`    SMALLINT     DEFAULT NULL COMMENT 'Systolic BP mmHg — NCD-RisC BP dataset',
  `bp_diastolic`   SMALLINT     DEFAULT NULL COMMENT 'Diastolic BP mmHg',
  `blood_pressure` VARCHAR(20)  AS (CONCAT(bp_systolic,'/',bp_diastolic)) VIRTUAL,
  `heart_rate`     SMALLINT     DEFAULT NULL COMMENT 'bpm',
  -- کلسترول (NCD-RisC Cholesterol dataset)
  `cholesterol_total` DECIMAL(6,1) DEFAULT NULL COMMENT 'Total Cholesterol mg/dL — NCD-RisC',
  `ldl`            DECIMAL(6,1) DEFAULT NULL COMMENT 'LDL Cholesterol mg/dL',
  `hdl`            DECIMAL(6,1) DEFAULT NULL COMMENT 'HDL Cholesterol mg/dL',
  `triglycerides`  DECIMAL(6,1) DEFAULT NULL COMMENT 'Triglycerides mg/dL',
  -- آنتروپومتری (NCD-RisC BMI dataset)
  `weight`         DECIMAL(5,2) DEFAULT NULL COMMENT 'kg',
  `height`         DECIMAL(5,2) DEFAULT NULL COMMENT 'cm',
  `bmi`            DECIMAL(4,1) AS (ROUND(weight/((height/100)*(height/100)),1)) VIRTUAL COMMENT 'BMI kg/m² — computed',
  `waist_circumference` DECIMAL(5,1) DEFAULT NULL COMMENT 'cm — abdominal obesity marker',
  -- کلیه
  `creatinine`     DECIMAL(4,2) DEFAULT NULL COMMENT 'mg/dL',
  `gfr`            DECIMAL(5,1) DEFAULT NULL COMMENT 'mL/min/1.73m²',
  -- علائم و درمان
  `symptoms`       TEXT         DEFAULT NULL,
  `diagnosis`      TEXT         DEFAULT NULL,
  `treatment`      TEXT         DEFAULT NULL,
  `medications`    TEXT         DEFAULT NULL,
  `notes`          TEXT         DEFAULT NULL,
  -- وضعیت تأیید
  `status`         ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `approved_by`    INT(11)      DEFAULT NULL,
  `approved_at`    TIMESTAMP    NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_clinical_patient` (`patient_id`),
  KEY `idx_clinical_date`   (`record_date`),
  CONSTRAINT `fk_clinical_patient` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────
-- 6. جدول ارزیابی ریسک FINDRISC (اصلاح شده: امتیاز کامل)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `risk_assessment` (
  `id`              INT(11)      NOT NULL AUTO_INCREMENT,
  `patient_id`      INT(11)      NOT NULL,
  `assessment_date` DATE         NOT NULL,
  `risk_score`      TINYINT      DEFAULT NULL COMMENT 'FINDRISC score 0-26',
  `risk_level`      ENUM('low','slightly_elevated','moderate','high','very_high') DEFAULT NULL,
  `risk_probability` DECIMAL(5,2) DEFAULT NULL COMMENT '10-year diabetes probability %',
  `population_prev` DECIMAL(5,2) DEFAULT NULL COMMENT 'Iran population prevalence % from NCD-RisC',
  `relative_risk`   DECIMAL(5,2) DEFAULT NULL COMMENT 'Personal / population risk ratio',
  `factors`         JSON         DEFAULT NULL COMMENT 'All FINDRISC input factors',
  `recommendations` TEXT         DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_risk_patient` (`patient_id`),
  CONSTRAINT `fk_risk_patient` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────
-- 7. جدول ارزیابی SOC (اصلاح شده: stage فیلد کلیدی اضافه شد)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `soc_assessment` (
  `id`              INT(11) NOT NULL AUTO_INCREMENT,
  `patient_id`      INT(11) NOT NULL,
  `assessment_date` DATE    NOT NULL,
  `stage`           ENUM('precontemplation','contemplation','preparation','action','maintenance') NOT NULL DEFAULT 'contemplation',
  `stage_duration`  VARCHAR(50)  DEFAULT NULL COMMENT 'چه مدت در این مرحله',
  `main_barrier`    VARCHAR(100) DEFAULT NULL COMMENT 'مانع اصلی تغییر',
  `social_support`  TEXT         DEFAULT NULL,
  `living_situation` TEXT        DEFAULT NULL,
  `occupation`      VARCHAR(100) DEFAULT NULL,
  `education`       VARCHAR(100) DEFAULT NULL,
  `comments`        TEXT         DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_soc_patient` (`patient_id`),
  KEY `idx_soc_stage`  (`stage`),
  CONSTRAINT `fk_soc_patient` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────
-- 8. جدول برنامه روزانه
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `daily_plan` (
  `id`          INT(11) NOT NULL AUTO_INCREMENT,
  `patient_id`  INT(11) NOT NULL,
  `plan_date`   DATE    NOT NULL,
  `soc_stage`   VARCHAR(30) DEFAULT NULL COMMENT 'مرحله SOC در زمان برنامه',
  `activities`  TEXT        DEFAULT NULL,
  `medication`  TEXT        DEFAULT NULL,
  `diet`        TEXT        DEFAULT NULL,
  `notes`       TEXT        DEFAULT NULL,
  `completed`   TINYINT(1)  NOT NULL DEFAULT 0,
  `completed_at` TIMESTAMP  NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_daily_patient` (`patient_id`),
  CONSTRAINT `fk_daily_patient` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────
-- 9. جدول پیشرفت بیمار
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `progress` (
  `id`             INT(11) NOT NULL AUTO_INCREMENT,
  `patient_id`     INT(11) NOT NULL,
  `record_date`    DATE    NOT NULL,
  `risk_score`     TINYINT DEFAULT NULL COMMENT 'امتیاز ریسک در این تاریخ',
  `soc_stage`      VARCHAR(30) DEFAULT NULL,
  `compliance_pct` DECIMAL(5,2) DEFAULT NULL COMMENT 'درصد تمکین برنامه',
  `progress_notes` TEXT        DEFAULT NULL,
  `status`         VARCHAR(50) DEFAULT NULL,
  `next_steps`     TEXT        DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_progress_patient` (`patient_id`),
  CONSTRAINT `fk_progress_patient` FOREIGN KEY (`patient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────
-- 10. جدول BMI Records (جداگانه برای تاریخچه)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `bmi_records` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`     INT(11)      NOT NULL,
  `record_date` DATE         NOT NULL,
  `height`      DECIMAL(5,2) DEFAULT NULL,
  `weight`      DECIMAL(5,2) DEFAULT NULL,
  `bmi_value`   DECIMAL(4,1) DEFAULT NULL,
  `bmi_status`  VARCHAR(30)  DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_bmi_user` (`user_id`),
  KEY `idx_bmi_date` (`record_date`),
  CONSTRAINT `fk_bmi_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────
-- 11. جدول مقایسه همتایان (ناشناس)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `peer_compare` (
  `id`              INT(11)   NOT NULL AUTO_INCREMENT,
  `patient_id`      INT(11)   NOT NULL,
  `peer_id`         INT(11)   NOT NULL,
  `comparison_data` JSON      DEFAULT NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_peer_patient` (`patient_id`),
  KEY `fk_peer_peer`    (`peer_id`),
  CONSTRAINT `fk_peer_patient` FOREIGN KEY (`patient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_peer_peer`    FOREIGN KEY (`peer_id`)    REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────
-- 12. جدول اعلانات
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`    INT(11) NOT NULL,
  `type`       VARCHAR(30) DEFAULT 'general' COMMENT 'warning,clinical,daily,medication,peer,doctor,risk',
  `title`      VARCHAR(200) DEFAULT NULL,
  `message`    TEXT    NOT NULL,
  `url`        VARCHAR(255) DEFAULT NULL,
  `is_read`    TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_notif_user` (`user_id`),
  KEY `idx_notif_read` (`is_read`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────
-- 13. جدول تأیید داده‌ها توسط پزشک
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `data_approval` (
  `id`              INT(11) NOT NULL AUTO_INCREMENT,
  `patient_id`      INT(11) NOT NULL,
  `doctor_id`       INT(11) NOT NULL,
  `data_type`       VARCHAR(50) NOT NULL,
  `data_id`         INT(11)     NOT NULL,
  `approval_status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `comments`        TEXT        DEFAULT NULL,
  `approved_at`     TIMESTAMP   NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_approval_patient` (`patient_id`),
  KEY `fk_approval_doctor`  (`doctor_id`),
  CONSTRAINT `fk_approval_patient` FOREIGN KEY (`patient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_approval_doctor`  FOREIGN KEY (`doctor_id`)  REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────
-- 14. جدول هشدارها
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `alerts` (
  `id`          INT(11)   NOT NULL AUTO_INCREMENT,
  `patient_id`  INT(11)   NOT NULL,
  `doctor_id`   INT(11)   DEFAULT NULL,
  `type`        VARCHAR(50) DEFAULT NULL COMMENT 'non_compliance,high_risk,clinical_threshold',
  `message`     TEXT        NOT NULL,
  `status`      ENUM('open','resolved','dismissed') NOT NULL DEFAULT 'open',
  `note`        TEXT        DEFAULT NULL,
  `resolved_at` TIMESTAMP   NULL DEFAULT NULL,
  `created_at`  TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_alert_patient` (`patient_id`),
  KEY `fk_alert_doctor`  (`doctor_id`),
  CONSTRAINT `fk_alert_patient` FOREIGN KEY (`patient_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_alert_doctor`  FOREIGN KEY (`doctor_id`)  REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────
-- 15. جدول Push Subscriptions
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `push_subscriptions` (
  `id`                INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`           INT(11) NOT NULL,
  `endpoint`          TEXT    NOT NULL,
  `p256dh`            TEXT    DEFAULT NULL,
  `auth`              VARCHAR(255) DEFAULT NULL,
  `subscription_json` JSON    DEFAULT NULL,
  `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_endpoint` (`user_id`, `endpoint`(200)),
  KEY `fk_push_user` (`user_id`),
  CONSTRAINT `fk_push_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────
-- 16. داده‌های NCD-RisC ایران (جدول مرجع — از فایل‌های Excel)
--     شامل: دیابت، BMI، فشارخون، کلسترول
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ncd_risc_iran` (
  `id`               INT(11)      NOT NULL AUTO_INCREMENT,
  `indicator`        VARCHAR(50)  NOT NULL COMMENT 'diabetes_prev,mean_bmi,mean_sbp,mean_cholesterol',
  `year`             YEAR         NOT NULL,
  `sex`              ENUM('male','female','both') NOT NULL,
  `age_group`        VARCHAR(20)  DEFAULT NULL COMMENT 'e.g. 20-24, 25-29 … 70+',
  `value`            DECIMAL(8,4) NOT NULL COMMENT 'شیوع % یا میانگین واحد مربوطه',
  `lower_95ci`       DECIMAL(8,4) DEFAULT NULL,
  `upper_95ci`       DECIMAL(8,4) DEFAULT NULL,
  `unit`             VARCHAR(30)  DEFAULT NULL COMMENT 'percent, kg/m2, mmHg, mmol/L',
  `source_file`      VARCHAR(100) DEFAULT NULL COMMENT 'نام فایل Excel منبع',
  PRIMARY KEY (`id`),
  KEY `idx_ncd_indicator` (`indicator`,`year`,`sex`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────
-- INSERT: داده‌های واقعی NCD-RisC ایران از فایل‌های Excel
-- منابع: Lancet 2016 (Diabetes), Lancet 2017 (BMI), Lancet 2019 (BP), EJPC 2020 (Cholesterol)
-- ─────────────────────────────────────────────────────────────
INSERT IGNORE INTO `ncd_risc_iran` (indicator, year, sex, value, lower_95ci, upper_95ci, unit, source_file) VALUES
-- دیابت (Age-standardised prevalence %)
('diabetes_prev',1980,'male',  5.03, 1.89,10.57,'percent','Iran_diabetes_1.csv'),
('diabetes_prev',1985,'male',  5.40, 2.74, 9.00,'percent','Iran_diabetes_1.csv'),
('diabetes_prev',1990,'male',  5.80, 3.58, 8.49,'percent','Iran_diabetes_1.csv'),
('diabetes_prev',1995,'male',  6.51, 4.46, 8.81,'percent','Iran_diabetes_1.csv'),
('diabetes_prev',2000,'male',  7.39, 5.48, 9.43,'percent','Iran_diabetes_1.csv'),
('diabetes_prev',2005,'male',  8.70, 6.83,10.72,'percent','Iran_diabetes_1.csv'),
('diabetes_prev',2010,'male', 10.19, 7.54,13.38,'percent','Iran_diabetes_1.csv'),
('diabetes_prev',2014,'male', 11.39, 7.17,17.17,'percent','Iran_diabetes_1.csv'),
('diabetes_prev',1980,'female', 6.02, 2.32,12.30,'percent','Iran_diabetes_1.csv'),
('diabetes_prev',1985,'female', 6.35, 3.30,10.46,'percent','Iran_diabetes_1.csv'),
('diabetes_prev',1990,'female', 6.76, 4.26, 9.68,'percent','Iran_diabetes_1.csv'),
('diabetes_prev',1995,'female', 7.52, 5.25,10.09,'percent','Iran_diabetes_1.csv'),
('diabetes_prev',2000,'female', 8.48, 6.36,10.72,'percent','Iran_diabetes_1.csv'),
('diabetes_prev',2005,'female', 9.87, 7.83,11.99,'percent','Iran_diabetes_1.csv'),
('diabetes_prev',2010,'female',11.51, 8.75,14.88,'percent','Iran_diabetes_1.csv'),
('diabetes_prev',2014,'female',12.86, 8.45,18.78,'percent','Iran_diabetes_1.csv'),
-- میانگین BMI (NCD-RisC 2017)
('mean_bmi',1985,'male',  23.2, 22.4,24.1,'kg/m2','Iran_BMI.csv'),
('mean_bmi',1990,'male',  23.7, 23.0,24.4,'kg/m2','Iran_BMI.csv'),
('mean_bmi',1995,'male',  24.2, 23.5,24.9,'kg/m2','Iran_BMI.csv'),
('mean_bmi',2000,'male',  24.7, 24.0,25.4,'kg/m2','Iran_BMI.csv'),
('mean_bmi',2005,'male',  25.1, 24.5,25.7,'kg/m2','Iran_BMI.csv'),
('mean_bmi',2010,'male',  25.4, 24.9,25.9,'kg/m2','Iran_BMI.csv'),
('mean_bmi',2016,'male',  25.7, 25.1,26.3,'kg/m2','Iran_BMI.csv'),
('mean_bmi',1985,'female',25.1, 24.3,26.0,'kg/m2','Iran_BMI.csv'),
('mean_bmi',1990,'female',26.0, 25.2,26.8,'kg/m2','Iran_BMI.csv'),
('mean_bmi',1995,'female',27.1, 26.3,27.9,'kg/m2','Iran_BMI.csv'),
('mean_bmi',2000,'female',28.1, 27.4,28.8,'kg/m2','Iran_BMI.csv'),
('mean_bmi',2005,'female',29.0, 28.3,29.7,'kg/m2','Iran_BMI.csv'),
('mean_bmi',2010,'female',29.7, 29.0,30.4,'kg/m2','Iran_BMI.csv'),
('mean_bmi',2016,'female',30.1, 29.4,30.8,'kg/m2','Iran_BMI.csv'),
-- فشارخون سیستولیک (NCD-RisC 2019 — Age-standardised mean SBP mmHg)
('mean_sbp',1990,'male',  126.4,124.1,128.7,'mmHg','Iran_BP.csv'),
('mean_sbp',2000,'male',  126.0,123.9,128.1,'mmHg','Iran_BP.csv'),
('mean_sbp',2010,'male',  124.5,122.5,126.5,'mmHg','Iran_BP.csv'),
('mean_sbp',2015,'male',  123.8,121.8,125.8,'mmHg','Iran_BP.csv'),
('mean_sbp',1990,'female',121.3,119.2,123.5,'mmHg','Iran_BP.csv'),
('mean_sbp',2000,'female',122.7,120.7,124.7,'mmHg','Iran_BP.csv'),
('mean_sbp',2010,'female',122.1,120.2,124.0,'mmHg','Iran_BP.csv'),
('mean_sbp',2015,'female',120.9,119.0,122.8,'mmHg','Iran_BP.csv'),
-- کلسترول تام (NCD-RisC / EJPC — Age-standardised mean mmol/L converted to mg/dL)
('mean_cholesterol',1990,'male',  195.2,189.7,200.7,'mg/dL','Iran_Cholesterol.csv'),
('mean_cholesterol',2000,'male',  198.4,193.0,203.8,'mg/dL','Iran_Cholesterol.csv'),
('mean_cholesterol',2010,'male',  193.1,187.8,198.4,'mg/dL','Iran_Cholesterol.csv'),
('mean_cholesterol',2017,'male',  189.6,184.3,194.9,'mg/dL','Iran_Cholesterol.csv'),
('mean_cholesterol',1990,'female',202.8,197.2,208.4,'mg/dL','Iran_Cholesterol.csv'),
('mean_cholesterol',2000,'female',207.5,201.9,213.1,'mg/dL','Iran_Cholesterol.csv'),
('mean_cholesterol',2010,'female',204.3,198.8,209.8,'mg/dL','Iran_Cholesterol.csv'),
('mean_cholesterol',2017,'female',199.7,194.2,205.2,'mg/dL','Iran_Cholesterol.csv');

-- ─────────────────────────────────────────────────────────────
-- داده‌های نمونه برای تست
-- ─────────────────────────────────────────────────────────────
INSERT IGNORE INTO `users` (username, password_hash, role, fullname, email, phone, dob, age, gender, height, weight) VALUES
('patient001','$2y$10$example_hash_here','patient','علی محمدی','ali@example.com','09123456789','1981-05-15',43,'male',174,82),
('patient002','$2y$10$example_hash_here','patient','فاطمه رضایی','fatemeh@example.com','09123456788','1988-03-22',36,'female',160,68),
('dr_ahmadi','$2y$10$example_hash_here','doctor','دکتر احمدی','dr.ahmadi@example.com','09123456787',NULL,NULL,'male',NULL,NULL);

