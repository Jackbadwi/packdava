<?php
/**
 * api/auth_jwt.php
 * احراز هویت JWT برای نسخه اندروید (Capacitor)
 * با فال‌بک به session PHP برای نسخه وب
 *
 * ACTIONS: login | verify | refresh | logout
 *
 * نصب: composer require firebase/php-jwt
 * یا از فایل jwt_simple.php داخلی استفاده کنید (زیر همین فایل)
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    { http_response_code(405); echo json_encode(['error'=>'Method Not Allowed']); exit; }

require_once __DIR__ . '/../conn.php';

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? 'login';

// ── JWT_SECRET: در production یک کلید تصادفی ۶۴ کاراکتری قرار دهید ──
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'pakdava_jwt_secret_change_in_production_2024');
define('JWT_EXPIRE',  60 * 60 * 24 * 7);   // 7 روز
define('JWT_REFRESH', 60 * 60 * 24 * 30);  // 30 روز

// ────────────────────────────────────────────────────────────────────────────
// Mini JWT implementation (بدون نیاز به composer)
// ────────────────────────────────────────────────────────────────────────────
function jwt_encode(array $payload): string {
    $header   = base64url_encode(json_encode(['typ'=>'JWT','alg'=>'HS256']));
    $payload  = base64url_encode(json_encode($payload));
    $sig      = base64url_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
    return "$header.$payload.$sig";
}

function jwt_decode(string $token): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$header, $payload, $sig] = $parts;
    $expected = base64url_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
    if (!hash_equals($expected, $sig)) return null;
    $data = json_decode(base64url_decode($payload), true);
    if (!$data || ($data['exp'] ?? 0) < time()) return null;
    return $data;
}

function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
function base64url_decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
}

function getBearerToken(): ?string {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.+)/i', $h, $m)) return $m[1];
    return null;
}

// ────────────────────────────────────────────────────────────────────────────
switch ($action) {

    // ── LOGIN ────────────────────────────────────────────────────────────────
    case 'login':
    case 'login_json':
        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';
        $role     = $input['role']     ?? 'patient';

        if (!$username || !$password) {
            echo json_encode(['success'=>false,'message'=>'نام کاربری و رمز عبور الزامی است']); break;
        }

        $stmt = $conn->prepare(
            "SELECT id, username, password_hash, role, fullname, email, phone, age, gender
             FROM users WHERE username=? AND role=? LIMIT 1"
        );
        $stmt->bind_param('ss', $username, $role);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            // Demo mode: اگر hash نداشت، مستقیم مقایسه کن (فقط برای تست)
            if ($user && $user['password_hash'] === $password) {
                // allow plain text for demo
            } else {
                http_response_code(401);
                echo json_encode(['success'=>false,'message'=>'نام کاربری یا رمز عبور اشتباه است']);
                break;
            }
        }

        // ذخیره session PHP (برای نسخه وب)
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['name']    = $user['fullname'];
        $_SESSION['role']    = $user['role'];

        // به‌روزرسانی last_login
        $conn->query("UPDATE users SET last_login=NOW() WHERE id={$user['id']}");

        // صدور JWT
        $now = time();
        $token = jwt_encode([
            'iss' => 'pakdava',
            'sub' => $user['id'],
            'usr' => $user['username'],
            'rol' => $user['role'],
            'iat' => $now,
            'exp' => $now + JWT_EXPIRE,
        ]);
        $refresh = jwt_encode([
            'iss' => 'pakdava',
            'sub' => $user['id'],
            'typ' => 'refresh',
            'iat' => $now,
            'exp' => $now + JWT_REFRESH,
        ]);

        // ذخیره refresh token در دیتابیس (optional)
        // $conn->query("INSERT INTO refresh_tokens (user_id, token, expires_at) VALUES ({$user['id']},'$refresh',FROM_UNIXTIME(".($now+JWT_REFRESH).")) ON DUPLICATE KEY UPDATE token=VALUES(token)");

        unset($user['password_hash']);
        echo json_encode([
            'success'       => true,
            'token'         => $token,
            'refresh_token' => $refresh,
            'expires_in'    => JWT_EXPIRE,
            'user'          => $user,
        ]);
        break;

    // ── VERIFY ───────────────────────────────────────────────────────────────
    case 'verify':
        $token   = getBearerToken();
        $payload = $token ? jwt_decode($token) : null;

        if (!$payload) {
            // fallback به session
            if (isset($_SESSION['user_id'])) {
                $u = $conn->query("SELECT id,fullname,email,role FROM users WHERE id={$_SESSION['user_id']}")->fetch_assoc();
                echo json_encode(['success'=>true,'user'=>$u,'source'=>'session']); break;
            }
            http_response_code(401);
            echo json_encode(['success'=>false,'message'=>'Token نامعتبر یا منقضی']);
            break;
        }

        $u = $conn->query("SELECT id,fullname,email,role,phone,age,gender,bmi_value FROM users WHERE id={$payload['sub']}")->fetch_assoc();
        echo json_encode(['success'=>true,'user'=>$u,'source'=>'jwt']);
        break;

    // ── REFRESH ──────────────────────────────────────────────────────────────
    case 'refresh':
        $rt      = $input['refresh_token'] ?? getBearerToken();
        $payload = $rt ? jwt_decode($rt) : null;

        if (!$payload || ($payload['typ'] ?? '') !== 'refresh') {
            http_response_code(401);
            echo json_encode(['success'=>false,'message'=>'Refresh token نامعتبر']);
            break;
        }

        $now   = time();
        $token = jwt_encode([
            'iss' => 'pakdava',
            'sub' => $payload['sub'],
            'usr' => $payload['usr'] ?? '',
            'rol' => $payload['rol'] ?? 'patient',
            'iat' => $now,
            'exp' => $now + JWT_EXPIRE,
        ]);

        echo json_encode(['success'=>true,'token'=>$token,'expires_in'=>JWT_EXPIRE]);
        break;

    // ── LOGOUT ───────────────────────────────────────────────────────────────
    case 'logout':
        session_destroy();
        // در production: refresh token را از دیتابیس حذف کن
        echo json_encode(['success'=>true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success'=>false,'message'=>'action نامعتبر']);
}
?>
