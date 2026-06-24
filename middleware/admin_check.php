<?php
// middleware/admin_check.php
require_once __DIR__ . '/auth_check.php';

if ($_SESSION['role'] !== 'admin') {
    header('Location: /sweetheaven/user/index.php');
    exit;
}
