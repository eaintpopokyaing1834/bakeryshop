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
