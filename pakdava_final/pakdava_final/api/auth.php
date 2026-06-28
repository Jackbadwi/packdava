<?php
session_start();
require_once __DIR__ . '/../conn.php';

// خروج
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    $conn->query("UPDATE users SET last_login=NOW() WHERE id={$row['id']}");
    header('Location: ../index.php');
    exit;
}

// ورود (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? '';

    // تبدیل doctor به expert
    if ($role === 'doctor') {
        $role = 'expert';
    }

    if (empty($username) || empty($password) || empty($role)) {
        $conn->query("UPDATE users SET last_login=NOW() WHERE id={$row['id']}");
    header('Location: ../index.php?error=1');
        exit;
    }

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

            if ($row['role'] === 'patient') {
                $conn->query("UPDATE users SET last_login=NOW() WHERE id={$row['id']}");
    header('Location: ../patient/dashboard.php');
            } else {
                $conn->query("UPDATE users SET last_login=NOW() WHERE id={$row['id']}");
    header('Location: ../doctor/dashboard.php');
            }
            exit;
        }
    }
    $conn->query("UPDATE users SET last_login=NOW() WHERE id={$row['id']}");
    header('Location: ../index.php?error=1');
    exit;
}
?>