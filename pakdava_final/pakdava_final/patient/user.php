<?php
session_start();
require_once __DIR__ . '/../conn.php';
require_once __DIR__ . '/../assets/js/jdf.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ==================== مدیریت دسترسی ====================
$access_type = 'user'; // user, admin, doctor
$target_user_id = null;
$is_editable = false;

// بررسی دسترسی ادمین
if (isset($_GET['admin_access']) && $_GET['admin_access'] == '1' && isset($_GET['target_user_id'])) {
    if (isset($_SESSION['auth_user']) && $_SESSION['auth_user']['role'] === 'admin') {
        $access_type = 'admin';
        $target_user_id = (int)$_GET['target_user_id'];
        $is_editable = true;
    } else {
        header("Location: ../auth/login.php");
        exit;
    }
}
// بررسی دسترسی پزشک
elseif (isset($_GET['doctor_access']) && $_GET['doctor_access'] == '1' && isset($_GET['target_user_id'])) {
    if (isset($_SESSION['user_id']) && ($_SESSION['role'] === 'doctor' || $_SESSION['role'] === 'expert')) {
        $access_type = 'doctor';
        $target_user_id = (int)$_GET['target_user_id'];
        $is_editable = false; // پزشک فقط مشاهده می‌کند
        // بررسی اینکه بیمار تحت نظر این پزشک است (اختیاری)
        // می‌توانید چک کنید که آیا این بیمار به این پزشک تخصیص داده شده است
    } else {
        header("Location: ../auth/login.php");
        exit;
    }
}
// کاربر عادی
else {
    if (!isset($_SESSION['user_id'])) {
        // نمایش پیام خطا و هدایت به لاگین
        echo '<!DOCTYPE html>
        <html lang="fa" dir="rtl">
        <head><meta charset="UTF-8"><title>خطای دسترسی</title>
        <style>body{font-family:Tahoma;padding:50px;text-align:center;}</style>
        </head>
        <body>
        <h2>❌ دسترسی غیرمجاز</h2>
        <p>لطفاً ابتدا وارد سیستم شوید</p>
        <a href="../auth/login.php" class="btn" style="background:#3498db;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;">ورود</a>
        <script src="../assets/js/db.js"></script>
<script src="../assets/js/sync.js"></script>
<script src="../assets/js/notify.js"></script>
<script src="../assets/js/app.js"></script>
</body>
        </html>';
        exit;
    }
    $target_user_id = (int)$_SESSION['user_id'];
    $access_type = 'user';
    $is_editable = true;
}

// ==================== دریافت اطلاعات کاربر ====================
$user = null;
if ($target_user_id) {
    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $target_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
}

if (!$user) {
    die('کاربر مورد نظر یافت نشد.');
}

// ==================== توابع کمکی ====================
function convert_gregorian_to_jalali($gregorian_date) {
    if (empty($gregorian_date) || $gregorian_date == '0000-00-00') return '';
    list($year, $month, $day) = explode('-', $gregorian_date);
    list($jy, $jm, $jd) = gregorian_to_jalali($year, $month, $day);
    return sprintf("%04d/%02d/%02d", $jy, $jm, $jd);
}

function convert_jalali_to_gregorian($jalali_date) {
    if (empty($jalali_date)) return '';
    $jalali_date = fa_to_en($jalali_date);
    if (!preg_match('/^\d{4}\/\d{2}\/\d{2}$/', $jalali_date)) return false;
    list($jy, $jm, $jd) = explode('/', $jalali_date);
    list($gy, $gm, $gd) = jalali_to_gregorian((int)$jy, (int)$jm, (int)$jd);
    return sprintf("%04d-%02d-%02d", $gy, $gm, $gd);
}

function validate_jalali_date($date) {
    if (empty($date)) return false;
    $date = fa_to_en($date);
    if (!preg_match('/^\d{4}\/\d{2}\/\d{2}$/', $date)) return false;
    list($year, $month, $day) = explode('/', $date);
    $year = (int)$year; $month = (int)$month; $day = (int)$day;
    if ($month < 1 || $month > 12 || $day < 1 || $day > 31) return false;
    $month_days = [31,31,31,31,31,31,30,30,30,30,30,29];
    if ($month == 12 && is_jalali_leap_year($year)) $month_days[11] = 30;
    return $day <= $month_days[$month-1];
}

function is_jalali_leap_year($year) {
    $leap = $year % 33;
    return in_array($leap, [1,5,9,13,17,22,26,30]);
}

function calculate_age($birth_date) {
    if (empty($birth_date) || $birth_date == '0000-00-00') return 0;
    $birth = new DateTime($birth_date);
    $today = new DateTime();
    return $today->diff($birth)->y;
}

function en_to_fa($number) {
    $english = ['0','1','2','3','4','5','6','7','8','9'];
    $persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    return str_replace($english, $persian, $number);
}

function fa_to_en($number) {
    $persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    $english = ['0','1','2','3','4','5','6','7','8','9'];
    return str_replace($persian, $english, $number);
}

function getSmartProfilePic($user_id) {
    $base_filename = 'profile_' . $user_id;
    $upload_dir = '../uploads/profile_pics/';
    $formats = ['.jpg', '.jpeg', '.png', '.gif'];
    foreach ($formats as $format) {
        $profile_path = $upload_dir . $base_filename . $format;
        if (file_exists($profile_path)) return $profile_path;
    }
    return '../assets/Images/default-avatar.png';
}

// ==================== دریافت داده‌های بالینی (برای پزشک) ====================
$clinical_latest = null;
$bmi = null;
if ($access_type === 'doctor' || $access_type === 'admin') {
    $sql = "SELECT * FROM clinical_data WHERE patient_id = ? ORDER BY record_date DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $target_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $clinical_latest = $result->fetch_assoc();
    $stmt->close();
    if ($clinical_latest && $clinical_latest['height'] && $clinical_latest['weight']) {
        $bmi = round($clinical_latest['weight'] / (($clinical_latest['height']/100) ** 2), 1);
    }
}

// ==================== پردازش ویرایش (فقط برای کاربر و ادمین) ====================
$message = '';
$error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && $is_editable) {
    // دریافت داده‌ها
    $fullname = trim($_POST['fullname'] ?? '');
    $dob_jalali = trim($_POST['dob'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $student_id = trim($_POST['student_id'] ?? '');
    $school = trim($_POST['school'] ?? '');
    $class = trim($_POST['class'] ?? '');
    $gender = $_POST['gender'] ?? '';

    // اعتبارسنجی
    $errors = [];
    if (empty($fullname) || strlen($fullname) < 3) $errors[] = "نام کامل باید حداقل ۳ کاراکتر باشد";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "فرمت ایمیل نامعتبر است";
    if (!in_array($gender, ['male', 'female'])) $errors[] = "جنسیت نامعتبر است";
    
    $dob = convert_jalali_to_gregorian($dob_jalali);
    if (!$dob) $errors[] = "تاریخ تولد نامعتبر است";
    else {
        $age = calculate_age($dob);
        if ($age < 1 || $age > 100) $errors[] = "سن باید بین ۱ تا ۱۰۰ باشد";
    }

    // آپلود عکس
    $profile_pic = $user['profile_pic'];
    if (isset($_POST['remove_profile_pic']) && $_POST['remove_profile_pic'] == '1') {
        $upload_dir = '../uploads/profile_pics/';
        $base_filename = 'profile_' . $target_user_id;
        $formats = ['.jpg', '.jpeg', '.png', '.gif'];
        foreach ($formats as $format) {
            $file_path = $upload_dir . $base_filename . $format;
            if (file_exists($file_path)) unlink($file_path);
        }
        $profile_pic = '';
        $message = "✅ عکس پروفایل با موفقیت حذف شد";
    }
    
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/profile_pics/';
        if (!file_exists($upload_dir)) mkdir($upload_dir, 0755, true);
        $file = $_FILES['profile_pic'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($file_extension, $allowed)) $errors[] = "فرمت فایل مجاز نیست";
        if ($file['size'] > 2*1024*1024) $errors[] = "حجم فایل نباید بیشتر از 2 مگابایت باشد";
        
        if (empty($errors)) {
            $base_filename = 'profile_' . $target_user_id;
            $old_files = glob($upload_dir . $base_filename . '.*');
            foreach ($old_files as $old) if (is_file($old)) unlink($old);
            $new_filename = $base_filename . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $profile_pic = $new_filename;
                $message = "✅ عکس پروفایل با موفقیت بروزرسانی شد";
            } else $errors[] = "خطا در آپلود فایل";
        }
    }

    if (empty($errors)) {
        $update_sql = "UPDATE users SET fullname=?, dob=?, email=?, address=?, phone=?, 
                      student_id=?, school=?, class=?, age=?, gender=?, profile_pic=?, updated_at=NOW() 
                      WHERE id=?";
        $stmt = $conn->prepare($update_sql);
        if ($stmt) {
            $stmt->bind_param("ssssssssissi", $fullname, $dob, $email, $address, $phone, 
                             $student_id, $school, $class, $age, $gender, $profile_pic, $target_user_id);
            if ($stmt->execute()) {
                $message = isset($message) ? $message : "✅ اطلاعات با موفقیت بروزرسانی شد";
                // بارگذاری مجدد اطلاعات
                $sql = "SELECT * FROM users WHERE id = ?";
                $stmt_reload = $conn->prepare($sql);
                $stmt_reload->bind_param("i", $target_user_id);
                $stmt_reload->execute();
                $result = $stmt_reload->get_result();
                $user = $result->fetch_assoc();
                $stmt_reload->close();
            } else {
                $error = "❌ خطا در بروزرسانی اطلاعات: " . $conn->error;
            }
            $stmt->close();
        } else {
            $error = "❌ خطا در آماده‌سازی کوئری: " . $conn->error;
        }
    } else {
        $error = "❌ " . implode("<br>❌ ", $errors);
    }
}

// ==================== آماده‌سازی داده‌ها برای نمایش ====================
$dob_jalali = '';
if (!empty($user['dob']) && $user['dob'] != '0000-00-00') {
    $dob_jalali = en_to_fa(convert_gregorian_to_jalali($user['dob']));
}
$current_age = calculate_age($user['dob']);
$profile_pic_path = getSmartProfilePic($target_user_id);
$has_profile_pic = strpos($profile_pic_path, 'default-avatar.png') === false;

// تعیین عنوان صفحه
$page_title = ($access_type === 'admin') ? 'مدیریت پروفایل کاربر' : 
              (($access_type === 'doctor') ? 'پروفایل بیمار' : 'پروفایل کاربر');
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
      <link rel="manifest" href="../manifest.json">
  <meta name="theme-color" content="#1A7A4A">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="پک دوا">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - MyHealthcare</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.4.1/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ===== استایل‌های مشابه فایل اصلی با بهینه‌سازی ===== */
        :root {
            --main-color: #e02f6b;
            --blue: #3498db;
            --blue-dark: #18293c;
            --orange: #ffa500;
            --white: #ffffff;
            --glass-bg: rgba(255,255,255,0.9);
            --glass-border: rgba(255,255,255,0.2);
            --shadow-light: 0 4px 15px rgba(0,0,0,0.08);
            --shadow-medium: 0 8px 25px rgba(0,0,0,0.12);
        }
        * { box-sizing: border-box; margin:0; padding:0; }
        body {
            background: #f5f7fb;
            font-family: 'B Nazanin', Tahoma, Arial, sans-serif;
            padding-top: 80px;
        }
        .container-main {
            max-width: 1000px;
            margin: 0 auto 50px;
            background: var(--glass-bg);
            border-radius: 20px;
            box-shadow: var(--shadow-medium);
            overflow: hidden;
            backdrop-filter: blur(10px);
        }
        .glass-header {
            padding: 25px 30px;
            border-bottom: 1px solid var(--glass-border);
            text-align: center;
            background: var(--glass-bg);
        }
        .glass-header h1 { font-size: 2rem; color: var(--blue-dark); }
        .user-info { color: var(--blue-dark); margin-top: 10px; }
        .action-buttons { display: flex; gap: 10px; flex-wrap: wrap; justify-content: center; margin: 15px 0; }
        .action-btn {
            padding: 10px 20px;
            border-radius: 30px;
            font-weight: 500;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: var(--shadow-light);
        }
        .action-btn:hover { transform: translateY(-2px); box-shadow: var(--shadow-medium); color: white; text-decoration: none; }
        .btn-back { background: var(--blue); }
        .btn-logout { background: var(--main-color); }
        .btn-admin { background: #6c757d; }
        .content-section { padding: 30px; }
        .alert { padding: 15px 20px; border-radius: 12px; margin-bottom: 25px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .profile-table { width: 100%; border-collapse: collapse; }
        .profile-table th, .profile-table td { padding: 15px 20px; border-bottom: 1px solid #e9ecef; text-align: right; vertical-align: middle; }
        .profile-table th { width: 30%; background: #f8f9fa; font-weight: 600; }
        .form-input, .form-select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 15px;
            transition: 0.3s;
            background: white;
        }
        .form-input:focus, .form-select:focus { border-color: var(--main-color); box-shadow: 0 0 0 3px rgba(224,47,107,0.2); outline: none; }
        .form-input[readonly] { background: #f8f9fa; cursor: not-allowed; }
        .info-text { font-size: 13px; color: #6c757d; margin-top: 5px; }
        .submit-btn {
            background: var(--main-color);
            color: white;
            padding: 15px 45px;
            border: none;
            border-radius: 30px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: var(--shadow-medium);
        }
        .submit-btn:hover { background: var(--blue-dark); transform: translateY(-2px); }
        .profile-image-container { position: relative; display: inline-block; }
        .profile-image-preview { width: 140px; height: 140px; border-radius: 50%; object-fit: cover; border: 3px solid var(--main-color); }
        .edit-photo-overlay {
            position: absolute; bottom: 10px; right: 10px;
            background: rgba(224,47,107,0.8);
            width: 35px; height: 35px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: white; cursor: pointer;
            transition: 0.3s;
        }
        .edit-photo-overlay:hover { background: rgba(224,47,107,1); transform: scale(1.1); }
        .btn-upload-photo, .btn-remove-photo {
            background: var(--blue);
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 30px;
            cursor: pointer;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.3s;
            box-shadow: var(--shadow-light);
            margin: 5px 0;
        }
        .btn-remove-photo { background: #dc3545; }
        .btn-upload-photo:hover, .btn-remove-photo:hover { transform: translateY(-2px); box-shadow: var(--shadow-medium); }
        .admin-mode-banner { background: linear-gradient(135deg, var(--main-color), #c2185b); color: white; padding: 12px 20px; text-align: center; font-weight: bold; }
        .doctor-mode-banner { background: linear-gradient(135deg, #2c3e50, #3498db); color: white; padding: 12px 20px; text-align: center; font-weight: bold; }
        .clinical-info-box { background: #f8f9fa; border-radius: 12px; padding: 20px; margin-top: 20px; border: 1px solid #e9ecef; }
        .clinical-info-box h4 { margin-bottom: 15px; color: var(--blue-dark); }
        .clinical-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px; }
        .clinical-item { background: white; padding: 12px; border-radius: 8px; text-align: center; box-shadow: var(--shadow-light); }
        .clinical-item .value { font-weight: bold; font-size: 18px; color: var(--main-color); }
        .clinical-item .label { font-size: 12px; color: #6c757d; }
        @media (max-width: 768px) {
            .profile-table th, .profile-table td { display: block; width: 100%; }
            .profile-table th { background: #f1f3f5; margin-top: 10px; border-bottom: none; }
            .profile-table td { border-bottom: 1px solid #dee2e6; }
        }
    </style>
</head>
<body>
    <div class="container-main">
        <?php if ($access_type === 'admin'): ?>
            <div class="admin-mode-banner"><i class="fas fa-user-cog"></i> حالت مدیریت - ویرایش پروفایل کاربر</div>
        <?php elseif ($access_type === 'doctor'): ?>
            <div class="doctor-mode-banner"><i class="fas fa-user-md"></i> حالت پزشک - مشاهده پروفایل بیمار</div>
        <?php endif; ?>

        <div class="glass-header">
            <?php if ($access_type === 'admin'): ?>
                <div class="action-buttons">
                    <a href="../admin/admin_pannel.php" class="action-btn btn-back"><i class="fas fa-arrow-left"></i> بازگشت به پنل</a>
                </div>
                <h1><i class="fas fa-user-cog"></i> <?= $page_title ?></h1>
                <div class="user-info">
                    کاربر: <strong><?= htmlspecialchars($user['fullname']) ?></strong> | 
                    نام کاربری: <strong><?= htmlspecialchars($user['username']) ?></strong> | 
                    نقش: <strong><?= htmlspecialchars($user['role']) ?></strong>
                </div>
            <?php elseif ($access_type === 'doctor'): ?>
                <div class="action-buttons">
                    <a href="reports.php" class="action-btn btn-back"><i class="fas fa-arrow-left"></i> بازگشت به گزارشات</a>
                </div>
                <h1><i class="fas fa-user-injured"></i> پروفایل بیمار</h1>
                <div class="user-info">
                    <strong><?= htmlspecialchars($user['fullname']) ?></strong> | 
                    کد بیمار: <?= htmlspecialchars($user['student_id'] ?? 'ندارد') ?>
                </div>
            <?php else: ?>
                <div class="action-buttons">
                    <a href="../dashboard/user.php" class="action-btn btn-back"><i class="fas fa-arrow-left"></i> بازگشت</a>
                    <a href="../auth/logout.php" class="action-btn btn-logout" onclick="return confirm('خروج؟')"><i class="fas fa-sign-out-alt"></i> خروج</a>
                </div>
                <h1><i class="fas fa-user"></i> پروفایل کاربر</h1>
                <div class="user-info">خوش آمدید، <strong><?= htmlspecialchars($user['fullname']) ?></strong></div>
            <?php endif; ?>
        </div>

        <div class="content-section">
            <?php if ($message): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $message ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <?php if ($access_type === 'admin'): ?>
                    <input type="hidden" name="target_user_id" value="<?= $target_user_id ?>">
                <?php endif; ?>
                <table class="profile-table">
                    <tr>
                        <th>عکس پروفایل</th>
                        <td>
                            <div style="display:flex;flex-wrap:wrap;gap:20px;align-items:center;">
                                <div class="profile-image-container">
                                    <img id="profileImagePreview" src="<?= $profile_pic_path ?>" alt="پروفایل" class="profile-image-preview">
                                    <?php if ($is_editable): ?>
                                        <div id="editPhotoOverlay" class="edit-photo-overlay"><i class="fas fa-camera"></i></div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <?php if ($is_editable): ?>
                                        <input type="file" name="profile_pic" id="profile_pic" accept="image/*" style="display:none;" onchange="previewImage(this)">
                                        <button type="button" onclick="document.getElementById('profile_pic').click()" class="btn-upload-photo"><i class="fas fa-upload"></i> تغییر عکس</button>
                                        <?php if ($has_profile_pic): ?>
                                            <button type="button" id="removePhotoBtn" class="btn-remove-photo"><i class="fas fa-trash"></i> حذف عکس</button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="info-text">(فقط مشاهده)</div>
                                    <?php endif; ?>
                                    <div class="info-text">فرمت‌های مجاز: JPG, PNG, GIF | حداکثر ۲ مگابایت</div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th>نام و نام خانوادگی</th>
                        <td>
                            <?php if ($is_editable): ?>
                                <input type="text" name="fullname" value="<?= htmlspecialchars($user['fullname']) ?>" class="form-input" required>
                            <?php else: ?>
                                <input type="text" value="<?= htmlspecialchars($user['fullname']) ?>" class="form-input" readonly>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>تاریخ تولد (شمسی)</th>
                        <td>
                            <?php if ($is_editable): ?>
                                <input type="text" name="dob" id="dob" value="<?= $dob_jalali ?>" class="form-input" placeholder="۱۳۸۰/۰۱/۰۱" required>
                            <?php else: ?>
                                <input type="text" value="<?= $dob_jalali ?>" class="form-input" readonly>
                            <?php endif; ?>
                            <div class="info-text">فرمت: سال/ماه/روز</div>
                        </td>
                    </tr>
                    <tr>
                        <th>سن</th>
                        <td><input type="text" value="<?= en_to_fa($current_age) ?> سال" class="form-input" readonly></td>
                    </tr>
                    <tr>
                        <th>ایمیل</th>
                        <td>
                            <?php if ($is_editable): ?>
                                <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" class="form-input english-text" required>
                            <?php else: ?>
                                <input type="text" value="<?= htmlspecialchars($user['email']) ?>" class="form-input" readonly>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>آدرس</th>
                        <td>
                            <?php if ($is_editable): ?>
                                <input type="text" name="address" value="<?= htmlspecialchars($user['address']) ?>" class="form-input">
                            <?php else: ?>
                                <input type="text" value="<?= htmlspecialchars($user['address']) ?>" class="form-input" readonly>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>شماره تلفن</th>
                        <td>
                            <?php if ($is_editable): ?>
                                <input type="text" name="phone" value="<?= htmlspecialchars($user['phone']) ?>" class="form-input" required>
                            <?php else: ?>
                                <input type="text" value="<?= htmlspecialchars($user['phone']) ?>" class="form-input" readonly>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>کد کاربری</th>
                        <td>
                            <?php if ($is_editable): ?>
                                <input type="text" name="student_id" value="<?= htmlspecialchars($user['student_id']) ?>" class="form-input">
                            <?php else: ?>
                                <input type="text" value="<?= htmlspecialchars($user['student_id']) ?>" class="form-input" readonly>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>مدرسه</th>
                        <td>
                            <?php if ($is_editable): ?>
                                <input type="text" name="school" value="<?= htmlspecialchars($user['school']) ?>" class="form-input">
                            <?php else: ?>
                                <input type="text" value="<?= htmlspecialchars($user['school']) ?>" class="form-input" readonly>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>کلاس</th>
                        <td>
                            <?php if ($is_editable): ?>
                                <input type="text" name="class" value="<?= htmlspecialchars($user['class']) ?>" class="form-input">
                            <?php else: ?>
                                <input type="text" value="<?= htmlspecialchars($user['class']) ?>" class="form-input" readonly>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>جنسیت</th>
                        <td>
                            <?php if ($is_editable): ?>
                                <select name="gender" class="form-select" required>
                                    <option value="">انتخاب</option>
                                    <option value="male" <?= $user['gender'] == 'male' ? 'selected' : '' ?>>پسر</option>
                                    <option value="female" <?= $user['gender'] == 'female' ? 'selected' : '' ?>>دختر</option>
                                </select>
                            <?php else: ?>
                                <input type="text" value="<?= $user['gender'] == 'male' ? 'مرد' : ($user['gender'] == 'female' ? 'زن' : 'نامشخص') ?>" class="form-input" readonly>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if ($access_type === 'admin' || $access_type === 'doctor'): ?>
                        <tr>
                            <th>نام کاربری</th>
                            <td><input type="text" value="<?= htmlspecialchars($user['username']) ?>" class="form-input english-text" readonly></td>
                        </tr>
                        <tr>
                            <th>نقش</th>
                            <td><input type="text" value="<?= htmlspecialchars($user['role']) ?>" class="form-input english-text" readonly></td>
                        </tr>
                        <tr>
                            <th>وضعیت حساب</th>
                            <td><input type="text" value="<?= htmlspecialchars($user['status']) ?>" class="form-input english-text" readonly></td>
                        </tr>
                    <?php endif; ?>
                </table>

                <?php if ($is_editable): ?>
                    <button type="submit" class="submit-btn"><i class="fas fa-save"></i> ذخیره تغییرات</button>
                <?php endif; ?>
            </form>

            <?php if ($access_type === 'doctor' && $clinical_latest): ?>
                <div class="clinical-info-box">
                    <h4><i class="fas fa-heartbeat"></i> آخرین داده‌های بالینی</h4>
                    <div class="clinical-grid">
                        <div class="clinical-item">
                            <div class="value"><?= htmlspecialchars($clinical_latest['record_date']) ?></div>
                            <div class="label">تاریخ ثبت</div>
                        </div>
                        <?php if ($clinical_latest['blood_pressure']): ?>
                        <div class="clinical-item">
                            <div class="value"><?= htmlspecialchars($clinical_latest['blood_pressure']) ?> mmHg</div>
                            <div class="label">فشار خون</div>
                        </div>
                        <?php endif; ?>
                        <?php if ($clinical_latest['heart_rate']): ?>
                        <div class="clinical-item">
                            <div class="value"><?= htmlspecialchars($clinical_latest['heart_rate']) ?> bpm</div>
                            <div class="label">ضربان قلب</div>
                        </div>
                        <?php endif; ?>
                        <?php if ($clinical_latest['weight']): ?>
                        <div class="clinical-item">
                            <div class="value"><?= htmlspecialchars($clinical_latest['weight']) ?> kg</div>
                            <div class="label">وزن</div>
                        </div>
                        <?php endif; ?>
                        <?php if ($bmi): ?>
                        <div class="clinical-item">
                            <div class="value"><?= $bmi ?></div>
                            <div class="label">BMI</div>
                        </div>
                        <?php endif; ?>
                        <?php if (isset($clinical_latest['fbs']) && $clinical_latest['fbs']): ?>
                        <div class="clinical-item">
                            <div class="value"><?= htmlspecialchars($clinical_latest['fbs']) ?> mg/dL</div>
                            <div class="label">FBS</div>
                        </div>
                        <?php endif; ?>
                        <?php if (isset($clinical_latest['hba1c']) && $clinical_latest['hba1c']): ?>
                        <div class="clinical-item">
                            <div class="value"><?= htmlspecialchars($clinical_latest['hba1c']) ?>%</div>
                            <div class="label">HbA1c</div>
                        </div>
                        <?php endif; ?>
                        <div class="clinical-item">
                            <div class="value"><span class="badge badge-<?= $clinical_latest['status'] === 'pending' ? 'warning' : ($clinical_latest['status'] === 'approved' ? 'success' : 'danger') ?>">
                                <?= $clinical_latest['status'] === 'pending' ? 'در انتظار' : ($clinical_latest['status'] === 'approved' ? 'تأیید شده' : 'رد شده') ?>
                            </span></div>
                            <div class="label">وضعیت</div>
                        </div>
                    </div>
                    <?php if ($clinical_latest['diagnosis']): ?>
                        <div style="margin-top:15px;"><strong>تشخیص:</strong> <?= htmlspecialchars($clinical_latest['diagnosis']) ?></div>
                    <?php endif; ?>
                    <?php if ($clinical_latest['treatment']): ?>
                        <div><strong>درمان:</strong> <?= htmlspecialchars($clinical_latest['treatment']) ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.4.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.4.1/dist/js/bootstrap.min.js"></script>
    <script>
        // پیش‌نمایش عکس
        function previewImage(input) {
            const preview = document.getElementById('profileImagePreview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) { preview.src = e.target.result; };
                reader.readAsDataURL(input.files[0]);
            }
        }
        // کلیک روی آیکون دوربین
        document.getElementById('editPhotoOverlay')?.addEventListener('click', function() {
            document.getElementById('profile_pic').click();
        });
        // حذف عکس
        document.getElementById('removePhotoBtn')?.addEventListener('click', function() {
            if (confirm('آیا از حذف عکس اطمینان دارید؟')) {
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'remove_profile_pic';
                hidden.value = '1';
                document.querySelector('form').appendChild(hidden);
                document.getElementById('profileImagePreview').src = '../assets/Images/default-avatar.png';
                this.style.display = 'none';
                alert('عکس برای حذف علامت‌گذاری شد. با ذخیره فرم حذف خواهد شد.');
            }
        });
    </script>
<script src="../assets/js/db.js"></script>
<script src="../assets/js/sync.js"></script>
<script src="../assets/js/notify.js"></script>
<script src="../assets/js/app.js"></script>
</body>
</html>