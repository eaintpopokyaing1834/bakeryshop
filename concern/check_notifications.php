<?php
// check_notifications.php — Diagnostic + auto-fix for notifications table
// DELETE AFTER USE!
require_once __DIR__ . '/../config/db.php';
$db = getDB();

echo "<pre style='font-family:monospace;font-size:14px;padding:20px;'>";
echo "=== Notifications Table Diagnostic ===\n\n";

try {
    $cols = $db->query("SHOW COLUMNS FROM notifications")->fetchAll(PDO::FETCH_COLUMN);
    echo "Current columns: " . implode(', ', $cols) . "\n\n";

    $needed = ['user_id','order_id','is_seen','type','message','title','created_at'];
    $missing = [];
    foreach ($needed as $col) {
        if (!in_array($col, $cols)) $missing[] = $col;
    }

    if ($missing) {
        echo "Missing columns: " . implode(', ', $missing) . "\n\n";
        echo "Auto-fixing...\n";

        if (in_array('user_id', $missing)) {
            $db->exec("ALTER TABLE notifications ADD COLUMN user_id INT NULL AFTER id");
            echo "✅ Added user_id\n";
        }
        if (in_array('order_id', $missing)) {
            $db->exec("ALTER TABLE notifications ADD COLUMN order_id INT NULL");
            echo "✅ Added order_id\n";
        }
        if (in_array('is_seen', $missing)) {
            $db->exec("ALTER TABLE notifications ADD COLUMN is_seen TINYINT(1) DEFAULT 0");
            echo "✅ Added is_seen\n";
        }
        if (in_array('type', $missing)) {
            $db->exec("ALTER TABLE notifications ADD COLUMN type VARCHAR(50) DEFAULT 'general'");
            echo "✅ Added type\n";
        }
        if (in_array('message', $missing)) {
            $db->exec("ALTER TABLE notifications ADD COLUMN message TEXT");
            echo "✅ Added message\n";
        }
        if (in_array('title', $missing)) {
            $db->exec("ALTER TABLE notifications ADD COLUMN title VARCHAR(200) NULL");
            echo "✅ Added title\n";
        }
        if (in_array('created_at', $missing)) {
            $db->exec("ALTER TABLE notifications ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
            echo "✅ Added created_at\n";
        }

        // Re-check
        $cols = $db->query("SHOW COLUMNS FROM notifications")->fetchAll(PDO::FETCH_COLUMN);
        echo "\nFinal columns: " . implode(', ', $cols) . "\n";
    } else {
        echo "✅ All required columns present!\n";
    }

    $count = $db->query("SELECT COUNT(*) FROM notifications")->fetchColumn();
    echo "\nTotal notification rows: $count\n";

    echo "\nRecent notifications:\n";
    $rows = $db->query("SELECT * FROM notifications ORDER BY id DESC LIMIT 10")->fetchAll();
    foreach ($rows as $r) {
        echo "  - ID:{$r['id']} user_id:" . ($r['user_id'] ?? 'NULL') . " type:{$r['type']} is_seen:{$r['is_seen']} msg:" . substr($r['message'] ?? '', 0, 60) . "\n";
    }

    echo "\n✅ Diagnostic complete! DELETE this file now.\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
echo "</pre>";
