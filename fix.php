<?php
require_once __DIR__ . '/config/db.php';
$db = getDB();
$db->query("UPDATE discounts SET is_first_order=1 WHERE name LIKE '%First Order%'");
echo "DB Fixed!";
