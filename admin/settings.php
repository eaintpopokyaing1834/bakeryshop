<?php
require_once __DIR__ . '/../middleware/admin_check.php';
require_once __DIR__ . '/../config/db.php';
$db = getDB();

$pageTitle = 'Settings';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="bg-white rounded-2xl shadow-sm border border-stone-200 p-6">
    <h2 class="text-lg font-bold text-stone-800 mb-4">General Settings</h2>
    <p class="text-stone-500">Settings page content goes here.</p>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
