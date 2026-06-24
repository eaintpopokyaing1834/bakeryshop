<?php
require 'c:/wamp64/www/sweetheaven/config/db.php';
$db = getDB();
$cats = $db->query('SELECT * FROM categories ORDER BY name')->fetchAll();
foreach ($cats as $c) {
    $img = $c['image'] ?? '';
    $clean = ltrim(str_replace('../', '', $img), '/');
    $fullPath = 'c:/wamp64/www/sweetheaven/' . $clean;
    $exists = file_exists($fullPath) ? '✅ EXISTS' : '❌ MISSING';
    $url = '/sweetheaven/' . $clean;
    echo $c['name'] . "\n  DB value : " . $img . "\n  Clean    : " . $clean . "\n  URL      : " . $url . "\n  File     : " . $exists . "\n\n";
}
