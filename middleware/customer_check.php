<?php
// middleware/customer_check.php
require_once __DIR__ . '/auth_check.php';

if (in_array($_SESSION['role'] ?? '', ['admin', 'cashier'])) {
    header('Location: /sweetheaven/admin/dashboard.php');
    exit;
}
