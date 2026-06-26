<?php
// migrate_notifications.php — Run once to update the notifications table
// DELETE THIS FILE after running!
require_once __DIR__ . '/config/db.php';
$db = getDB();

$steps = [];

try {
    // 1. Add user_id column if missing
    $cols = $db->query("SHOW COLUMNS FROM notifications")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('user_id', $cols)) {
        $db->exec("ALTER TABLE notifications ADD COLUMN user_id INT NULL AFTER id");
        $steps[] = "✅ Added user_id column";
    } else {
        $steps[] = "ℹ️ user_id already exists";
    }

    if (!in_array('order_id', $cols)) {
        $db->exec("ALTER TABLE notifications ADD COLUMN order_id INT NULL AFTER user_id");
        $steps[] = "✅ Added order_id column";
    } else {
        $steps[] = "ℹ️ order_id already exists";
    }

    // 2. Add foreign key for user_id (ignore if already exists)
    try {
        $db->exec("ALTER TABLE notifications ADD CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE");
        $steps[] = "✅ Added FK for user_id";
    } catch (Exception $e) {
        $steps[] = "ℹ️ FK user_id already exists or skipped: " . $e->getMessage();
    }

    // 3. Add foreign key for order_id (ignore if already exists)
    try {
        $db->exec("ALTER TABLE notifications ADD CONSTRAINT fk_notif_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL");
        $steps[] = "✅ Added FK for order_id";
    } catch (Exception $e) {
        $steps[] = "ℹ️ FK order_id already exists or skipped: " . $e->getMessage();
    }

    // 4. Show final schema
    $schema = $db->query("SHOW COLUMNS FROM notifications")->fetchAll();
    $steps[] = "📋 Final schema: " . implode(', ', array_column($schema, 'Field'));

    echo "<h2>Migration Complete ✅</h2><ul>";
    foreach ($steps as $s)
        echo "<li>$s</li>";
    echo "</ul>";
    echo "<p><strong>Please delete this file now!</strong></p>";

} catch (Exception $e) {
    echo "<h2>❌ Error</h2><p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
