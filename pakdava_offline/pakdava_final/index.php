<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/conn.php';

// اگر کاربر قبلاً لاگین کرده باشد، هدایت شود
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'patient') {
        header('Location: patient/dashboard.php');
        exit;
    } elseif ($_SESSION['role'] == 'doctor' || $_SESSION['role'] == 'expert') {
        header('Location: doctor/dashboard.php');
        exit;
    }
}

$error = '';
$success = '';

// ------------------------------------------------------------
// پردازش ورود (Login)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    // تبدیل doctor به expert برای سازگاری با دیتابیس
    if ($role === 'doctor') {
        $role = 'expert';
    }

    if (empty($username) || empty($password) || empty($role)) {
        $error = 'لطفاً تمام فیلدها را پر کنید.';
    } else {
        $stmt = $conn->prepare("SELECT id, username, password_hash, role, fullname FROM users WHERE username = ? AND role = ?");
        $stmt->bind_param("ss", $username, $role);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password_hash'])) {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['name'] = $row['fullname'];
                $_SESSION['role'] = $row['role'];
                $_SESSION['logged_in'] = true;
                if ($row['role'] == 'patient') {
                    header('Location: patient/dashboard.php');
                } else {
                    header('Location: doctor/dashboard.php');
                }
                exit;
            } else {
                $error = 'رمز عبور اشتباه است.';
            }
        } else {
            $error = 'نام کاربری یا نقش اشتباه است.';
        }
        $stmt->close();
    }
}

// ------------------------------------------------------------
// پردازش ثبت‌نام (Register)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    $username = trim($_POST['reg_username'] ?? '');
    $password = $_POST['reg_password'] ?? '';
    $fullname = trim($_POST['reg_name'] ?? '');
    $email = trim($_POST['reg_email'] ?? '');
    $role = $_POST['reg_role'] ?? '';
    $reg_code = trim($_POST['reg_code'] ?? '');

    // تبدیل doctor به expert برای ذخیره در دیتابیس
    $db_role = ($role === 'doctor') ? 'expert' : $role;

    if (empty($username) || empty($password) || empty($fullname) || empty($email) || empty($role)) {
        $error = 'لطفاً تمام فیلدها را پر کنید.';
    } elseif ($role == 'doctor' && $reg_code !== 'DOC2026') {
        $error = 'کد ساخت پزشک نامعتبر است.';
    } else {
        // بررسی یکتایی
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $error = 'نام کاربری یا ایمیل قبلاً استفاده شده است.';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            // درج با مقادیر پیش‌فرض برای فیلدهای اجباری
            $stmt = $conn->prepare("INSERT INTO users (username, password_hash, role, fullname, email, dob, address, phone, created_at, updated_at, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 'active')");
            $dob = '2000-01-01';
            $address = '';
            $phone = '';
            $stmt->bind_param("ssssssss", $username, $hashed_password, $db_role, $fullname, $email, $dob, $address, $phone);
            if ($stmt->execute()) {
                $user_id = $conn->insert_id;
                // درج در جدول مربوطه (در صورت وجود)
                try {
                    if ($role == 'patient') {
                        $stmt2 = $conn->prepare("INSERT INTO patients (user_id) VALUES (?)");
                        $stmt2->bind_param("i", $user_id);
                        $stmt2->execute();
                        $stmt2->close();
                    } elseif ($role == 'doctor') {
                        $stmt2 = $conn->prepare("INSERT INTO doctors (user_id) VALUES (?)");
                        $stmt2->bind_param("i", $user_id);
                        $stmt2->execute();
                        $stmt2->close();
                    }
                } catch (Exception $e) {
                    // اگر جدول وجود نداشت، نادیده بگیر
                }
                $success = 'حساب کاربری با موفقیت ایجاد شد. اکنون وارد شوید.';
            } else {
                $error = 'خطا در ثبت‌نام: ' . $conn->error;
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <link rel="manifest" href="manifest.json">
  <meta name="theme-color" content="#1A7A4A">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پک دوا - ورود / ثبت‌نام</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <style>
        body { background: #f0f4f8; font-family: Tahoma, sans-serif; }
        .card { margin-top: 50px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border-radius: 16px; }
        .card-header { background: linear-gradient(135deg, #1a5276, #2980b9); color: white; border-radius: 16px 16px 0 0; padding: 20px; }
        .tab-content { padding: 20px 0; }
        .btn-primary, .btn-success { border-radius: 50px; padding: 10px; }
        .form-control { border-radius: 10px; }
        .role-card { cursor: pointer; padding: 12px; border: 2px solid #dee2e6; border-radius: 12px; text-align: center; transition: 0.2s; background: white; }
        .role-card.active { border-color: #2980b9; background: #eaf4fb; }
        .role-card input[type="radio"] { display: none; }
        .role-icon { font-size: 28px; display: block; }
        .role-label { font-weight: 600; font-size: 14px; margin-top: 4px; }
    </style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card">
                <div class="card-header text-center">
                    <h3 class="mb-0">💊 پک دوا</h3>
                    <small>سامانه مدیریت دیابت</small>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>

                    <ul class="nav nav-tabs nav-fill" id="myTab" role="tablist">
                        <li class="nav-item"><button class="nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#login" type="button">ورود</button></li>
                        <li class="nav-item"><button class="nav-link" id="register-tab" data-bs-toggle="tab" data-bs-target="#register" type="button">ثبت‌نام</button></li>
                    </ul>
                    <div class="tab-content">
                        <!-- فرم ورود -->
                        <div class="tab-pane fade show active" id="login">
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="username" class="form-label">نام کاربری</label>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                </div>
                                <div class="mb-3">
                                    <label for="password" class="form-label">رمز عبور</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label d-block">نقش خود را انتخاب کنید</label>
                                    <div class="d-flex gap-3">
                                        <label class="role-card active" id="login-role-patient">
                                            <input type="radio" name="role" value="patient" checked>
                                            <span class="role-icon">🤒</span>
                                            <span class="role-label">بیمار</span>
                                        </label>
                                        <label class="role-card" id="login-role-doctor">
                                            <input type="radio" name="role" value="doctor">
                                            <span class="role-icon">👨‍⚕️</span>
                                            <span class="role-label">پزشک</span>
                                        </label>
                                    </div>
                                </div>
                                <button type="submit" name="login" class="btn btn-primary w-100">ورود</button>
                            </form>
                            <div class="mt-3 text-center text-muted" style="font-size:12px">
                                حساب‌های آزمایشی:<br>
                                بیمار: patient001 / 1234 &nbsp;|&nbsp; پزشک: dr_ahmadi / 1234
                            </div>
                        </div>

                        <!-- فرم ثبت‌نام -->
                        <div class="tab-pane fade" id="register">
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="reg_name" class="form-label">نام کامل</label>
                                    <input type="text" class="form-control" id="reg_name" name="reg_name" required>
                                </div>
                                <div class="mb-3">
                                    <label for="reg_username" class="form-label">نام کاربری</label>
                                    <input type="text" class="form-control" id="reg_username" name="reg_username" required>
                                </div>
                                <div class="mb-3">
                                    <label for="reg_email" class="form-label">ایمیل</label>
                                    <input type="email" class="form-control" id="reg_email" name="reg_email" required>
                                </div>
                                <div class="mb-3">
                                    <label for="reg_password" class="form-label">رمز عبور</label>
                                    <input type="password" class="form-control" id="reg_password" name="reg_password" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label d-block">نقش خود را انتخاب کنید</label>
                                    <div class="d-flex gap-3">
                                        <label class="role-card active" id="reg-role-patient">
                                            <input type="radio" name="reg_role" value="patient" checked>
                                            <span class="role-icon">🤒</span>
                                            <span class="role-label">بیمار</span>
                                        </label>
                                        <label class="role-card" id="reg-role-doctor">
                                            <input type="radio" name="reg_role" value="doctor">
                                            <span class="role-icon">👨‍⚕️</span>
                                            <span class="role-label">پزشک</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="mb-3" id="reg_code_div" style="display:none;">
                                    <label for="reg_code" class="form-label">کد ساخت پزشک</label>
                                    <input type="text" class="form-control" id="reg_code" name="reg_code" placeholder="کد ساخت را وارد کنید">
                                    <small class="text-muted">کد پیش‌فرض: DOC2026</small>
                                </div>
                                <button type="submit" name="register" class="btn btn-success w-100">ثبت‌نام</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.querySelectorAll('#login input[name="role"]').forEach(r => {
        r.addEventListener('change', function() {
            document.querySelectorAll('#login .role-card').forEach(c => c.classList.remove('active'));
            this.closest('.role-card').classList.add('active');
        });
    });
    document.querySelectorAll('#register input[name="reg_role"]').forEach(r => {
        r.addEventListener('change', function() {
            document.querySelectorAll('#register .role-card').forEach(c => c.classList.remove('active'));
            this.closest('.role-card').classList.add('active');
            const codeDiv = document.getElementById('reg_code_div');
            if (this.value === 'doctor') codeDiv.style.display = 'block';
            else codeDiv.style.display = 'none';
        });
    });
    document.querySelector('#register input[name="reg_role"][value="patient"]').dispatchEvent(new Event('change'));
</script>

<script>
// Service Worker registration — root scope
if ('serviceWorker' in navigator) {
  window.addEventListener('load', async () => {
    try {
      const reg = await navigator.serviceWorker.register('./sw.js', { scope: './' });
      console.log('[PakDava] SW registered:', reg.scope);
    } catch(err) {
      console.warn('[PakDava] SW failed:', err);
    }
  });
}
</script>
</body>
</html>