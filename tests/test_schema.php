<?php
require_once __DIR__ . '/config/db.php';
$db = getDB();

$stmt = $db->query("SHOW CREATE TABLE categories");
$row = $stmt->fetch();
echo $row['Create Table'];
