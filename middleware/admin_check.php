<?php
// middleware/admin_check.php — Allows admin and cashier roles
require_once __DIR__ . '/auth_check.php';

if (!in_array($_SESSION['role'] ?? '', ['admin', 'cashier'])) {
    header('Location: /sweetheaven/user/index.php');
    exit;
}
