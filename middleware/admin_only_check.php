<?php
// middleware/admin_only_check.php — Strictly admin only, blocks cashier and customer
require_once __DIR__ . '/auth_check.php';

if ($_SESSION['role'] !== 'admin') {
    header('Location: /sweetheaven/user/index.php');
    exit;
}
