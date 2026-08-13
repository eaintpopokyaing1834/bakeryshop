<?php
require_once __DIR__ . '/config/db.php';
$db = getDB();
try {
    $db->exec("ALTER TABLE products ADD COLUMN category_my VARCHAR(200) DEFAULT NULL AFTER category_id");
    echo "Column category_my added successfully.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
