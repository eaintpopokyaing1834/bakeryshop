<?php
// middleware/auth_check.php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    // Detect if this is an admin/cashier page or a customer page
    $currentPath = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($currentPath, '/admin/') !== false || strpos($currentPath, '/cashier/') !== false) {
        header('Location: /sweetheaven/admin/login.php');
    } else {
        header('Location: /sweetheaven/user/index.php?show_login=1');
    }
    exit;
}

// ── Live status check: kick out users whose account was deactivated ──
require_once __DIR__ . '/../config/db.php';
$_authDb = getDB();
$_authStmt = $_authDb->prepare("SELECT status FROM users WHERE id = ?");
$_authStmt->execute([$_SESSION['user_id']]);
$_authUser = $_authStmt->fetch(PDO::FETCH_ASSOC);

if (!$_authUser || ($_authUser['status'] ?? 'active') === 'inactive') {
    // Destroy session and redirect
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();

    $currentPath = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($currentPath, '/admin/') !== false || strpos($currentPath, '/cashier/') !== false) {
        header('Location: /sweetheaven/admin/login.php?reason=inactive');
    } else {
        header('Location: /sweetheaven/user/index.php?show_login=1&reason=inactive');
    }
    exit;
}
