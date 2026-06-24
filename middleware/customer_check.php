<?php
// middleware/customer_check.php
require_once __DIR__ . '/auth_check.php';

if ($_SESSION['role'] === 'admin') {
    header('Location: /sweetheaven/admin/dashboard.php');
    exit;
}
