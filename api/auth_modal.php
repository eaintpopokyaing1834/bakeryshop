<?php
// api/auth_modal.php — AJAX handler for homepage login/register modal
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/lang.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

// ── LOGIN ─────────────────────────────────────────────────────────────────
if ($action === 'login') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'error' => __('login_err_empty')]);
        exit;
    }

    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role']    = $user['role'];
        $_SESSION['name']    = $user['name'];
        $_SESSION['cart']    = $_SESSION['cart'] ?? [];

        $redirect = ($user['role'] === 'admin')
            ? '/sweetheaven/admin/dashboard.php'
            : '/sweetheaven/user/index.php';

        echo json_encode(['success' => true, 'redirect' => $redirect, 'name' => $user['name']]);
    } else {
        echo json_encode(['success' => false, 'error' => __('login_err_invalid')]);
    }
    exit;
}

// ── REGISTER ──────────────────────────────────────────────────────────────
if ($action === 'register') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (empty($name) || empty($email) || empty($password) || empty($confirm)) {
        echo json_encode(['success' => false, 'error' => __('register_err_empty')]);
        exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => __('register_err_email')]);
        exit;
    }
    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'error' => __('register_err_short')]);
        exit;
    }
    if ($password !== $confirm) {
        echo json_encode(['success' => false, 'error' => __('register_err_match')]);
        exit;
    }

    $db    = getDB();
    $check = $db->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) {
        echo json_encode(['success' => false, 'error' => __('register_err_exists')]);
        exit;
    }

    $hashed = password_hash($password, PASSWORD_BCRYPT);
    $db->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'customer')")
       ->execute([$name, $email, $hashed]);

    echo json_encode(['success' => true, 'message' => __('register_success')]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action']);
