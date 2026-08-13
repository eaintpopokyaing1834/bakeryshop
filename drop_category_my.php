<?php
require_once __DIR__ . '/config/db.php';
$db = getDB();

try {
    $db->exec("ALTER TABLE products DROP COLUMN category_my");
    echo "Column category_my dropped successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
