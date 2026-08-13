<?php
require_once __DIR__ . '/config/db.php';
$db = getDB();

try {
    $db->prepare("UPDATE categories SET name_my = 'မြန်မာ' WHERE id = 1")->execute();
    $cat = $db->query("SELECT * FROM categories WHERE id = 1")->fetch();
    echo "Updated id 1. name_my = " . $cat['name_my'];
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
