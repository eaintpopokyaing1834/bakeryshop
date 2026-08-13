<?php
require_once __DIR__ . '/config/db.php';
$db = getDB();

try {
    $db->exec("ALTER TABLE categories ADD COLUMN name_my VARCHAR(100) DEFAULT NULL AFTER name");
    echo "Added name_my. ";
} catch (Exception $e) {
    echo "name_my exists or error: " . $e->getMessage() . ". ";
}

try {
    $db->exec("ALTER TABLE categories ADD COLUMN description_my TEXT DEFAULT NULL AFTER description");
    echo "Added description_my. ";
} catch (Exception $e) {
    echo "description_my exists or error: " . $e->getMessage() . ". ";
}
