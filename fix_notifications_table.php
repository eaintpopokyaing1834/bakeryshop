<?php
// fix_notifications_table.php
// Run this ONCE in your browser, then DELETE the file!
require_once __DIR__ . '/config/db.php';
$db = getDB();

echo "<!DOCTYPE html><html><head><title>Notifications Fix</title>";
echo "<style>body{font-family:monospace;padding:30px;background:#1a1a2e;color:#e0e0e0;}";
echo ".ok{color:#4ade80;}.err{color:#f87171;}.info{color:#60a5fa;}";
echo "h1{color:#f43f5e;}pre{background:#0f0f23;padding:20px;border-radius:10px;}</style></head><body>";
echo "<h1>🔔 Notifications Table Fix</h1><pre>";

$steps = [];

try {
    // Step 1: Check if notifications table exists
    $tables = $db->query("SHOW TABLES LIKE 'notifications'")->fetchAll();

    if (empty($tables)) {
        // Table doesn't exist — create it with all columns
        $db->exec("
            CREATE TABLE notifications (
                id         INT AUTO_INCREMENT PRIMARY KEY,
                user_id    INT NULL,
                order_id   INT NULL,
                type       VARCHAR(50) NOT NULL DEFAULT 'general',
                title      VARCHAR(200) NULL,
                message    TEXT,
                is_seen    TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_seen (user_id, is_seen),
                FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
                FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "<span class='ok'>✅ Created notifications table from scratch.</span>\n";
    } else {
        echo "<span class='info'>ℹ️  Notifications table already exists — checking columns...</span>\n";

        // Step 2: Check and add missing columns
        $cols = $db->query("SHOW COLUMNS FROM notifications")->fetchAll(PDO::FETCH_COLUMN);
        echo "   Current columns: " . implode(', ', $cols) . "\n\n";

        $fixes = [
            'user_id'    => "ALTER TABLE notifications ADD COLUMN user_id  INT NULL AFTER id",
            'order_id'   => "ALTER TABLE notifications ADD COLUMN order_id INT NULL AFTER user_id",
            'type'       => "ALTER TABLE notifications ADD COLUMN type     VARCHAR(50) NOT NULL DEFAULT 'general'",
            'title'      => "ALTER TABLE notifications ADD COLUMN title    VARCHAR(200) NULL",
            'message'    => "ALTER TABLE notifications ADD COLUMN message  TEXT",
            'is_seen'    => "ALTER TABLE notifications ADD COLUMN is_seen  TINYINT(1) NOT NULL DEFAULT 0",
            'created_at' => "ALTER TABLE notifications ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
        ];

        foreach ($fixes as $col => $sql) {
            if (!in_array($col, $cols)) {
                $db->exec($sql);
                echo "<span class='ok'>   ✅ Added missing column: $col</span>\n";
            } else {
                echo "<span class='info'>   ℹ️  Column exists: $col</span>\n";
            }
        }

        // Step 3: Add foreign keys if missing (ignore errors if FK already exists)
        foreach ([
            'fk_notif_user'  => "ALTER TABLE notifications ADD CONSTRAINT fk_notif_user  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE",
            'fk_notif_order' => "ALTER TABLE notifications ADD CONSTRAINT fk_notif_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL",
        ] as $name => $sql) {
            try { $db->exec($sql); echo "<span class='ok'>   ✅ Added FK: $name</span>\n"; }
            catch (Exception $e) { echo "<span class='info'>   ℹ️  FK $name already exists or skipped.</span>\n"; }
        }

        // Step 4: Add index for performance
        try {
            $db->exec("ALTER TABLE notifications ADD INDEX idx_user_seen (user_id, is_seen)");
            echo "<span class='ok'>   ✅ Added index: idx_user_seen</span>\n";
        } catch (Exception $e) {
            echo "<span class='info'>   ℹ️  Index already exists.</span>\n";
        }
    }

    // Step 5: Show final schema
    echo "\n<span class='info'>📋 Final Schema:</span>\n";
    $schema = $db->query("SHOW COLUMNS FROM notifications")->fetchAll();
    foreach ($schema as $col) {
        printf("   %-15s %-20s %s\n", $col['Field'], $col['Type'], $col['Null'] === 'YES' ? 'NULL' : 'NOT NULL');
    }

    // Step 6: Show existing notification count
    $count = $db->query("SELECT COUNT(*) FROM notifications")->fetchColumn();
    echo "\n<span class='info'>📊 Total rows in notifications: $count</span>\n";

    if ($count > 0) {
        echo "\n<span class='info'>📄 Recent notifications:</span>\n";
        $rows = $db->query("SELECT id, user_id, order_id, type, is_seen, LEFT(message,70) AS msg, created_at FROM notifications ORDER BY id DESC LIMIT 10")->fetchAll();
        foreach ($rows as $r) {
            printf("   id=%-3d user=%-5s order=%-5s type=%-15s seen=%d  %s\n",
                $r['id'],
                $r['user_id'] ?? 'NULL',
                $r['order_id'] ?? 'NULL',
                $r['type'],
                $r['is_seen'],
                $r['msg']
            );
        }
    }

    echo "\n<span class='ok'>✅ All done! DELETE this file now: fix_notifications_table.php</span>\n";

} catch (Exception $e) {
    echo "<span class='err'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</span>\n";
}

echo "</pre>";
echo "<p style='color:#f43f5e;font-weight:bold;font-size:16px;'>⚠️ DELETE this file after running!</p>";
echo "</body></html>";
