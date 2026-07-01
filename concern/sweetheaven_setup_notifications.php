<?php
/**
 * sweetheaven_setup_notifications.php
 * ─────────────────────────────────────
 * Run this ONCE in your browser, then DELETE it.
 * It will:
 *   1. Create the notifications table if it doesn't exist
 *   2. Add any missing columns to an existing table
 *   3. Send a live test notification to every customer who has an order
 *   4. Show you the full notification list so you can verify
 */
if (session_status() === PHP_SESSION_NONE)
    session_start();
require_once __DIR__ . '/../config/db.php';
$db = getDB();

$log = [];
$ok = true;

// ─── STEP 1: Ensure table exists ──────────────────────────────
$tableExists = $db->query("SHOW TABLES LIKE 'notifications'")->rowCount() > 0;

if (!$tableExists) {
    $db->exec("
        CREATE TABLE notifications (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            user_id    INT NULL,
            order_id   INT NULL,
            type       VARCHAR(50)  NOT NULL DEFAULT 'general',
            title      VARCHAR(200) NULL,
            message    TEXT,
            is_seen    TINYINT(1)   NOT NULL DEFAULT 0,
            created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_seen (user_id, is_seen)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $log[] = ['ok', '✅ Created <b>notifications</b> table from scratch.'];

    // Add foreign keys separately so errors don't abort table creation
    foreach ([
        "ALTER TABLE notifications ADD CONSTRAINT fk_notif_user  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE",
        "ALTER TABLE notifications ADD CONSTRAINT fk_notif_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL",
    ] as $fk) {
        try {
            $db->exec($fk);
        } catch (Exception $e) { /* ignore */
        }
    }
} else {
    $log[] = ['info', 'ℹ️  Table <b>notifications</b> already exists — checking columns…'];

    $cols = $db->query("SHOW COLUMNS FROM notifications")->fetchAll(PDO::FETCH_COLUMN);

    $needed = [
        'user_id' => "ALTER TABLE notifications ADD COLUMN user_id    INT NULL          AFTER id",
        'order_id' => "ALTER TABLE notifications ADD COLUMN order_id   INT NULL",
        'type' => "ALTER TABLE notifications ADD COLUMN type        VARCHAR(50)  NOT NULL DEFAULT 'general'",
        'title' => "ALTER TABLE notifications ADD COLUMN title       VARCHAR(200) NULL",
        'message' => "ALTER TABLE notifications ADD COLUMN message     TEXT",
        'is_seen' => "ALTER TABLE notifications ADD COLUMN is_seen     TINYINT(1)   NOT NULL DEFAULT 0",
        'created_at' => "ALTER TABLE notifications ADD COLUMN created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
    ];

    foreach ($needed as $col => $sql) {
        if (!in_array($col, $cols)) {
            $db->exec($sql);
            $log[] = ['ok', "✅ Added missing column: <b>$col</b>"];
        } else {
            $log[] = ['info', "   ✔ Column <b>$col</b> exists"];
        }
    }

    // Try adding index + foreign keys (safe to fail if already present)
    foreach ([
        "ALTER TABLE notifications ADD INDEX idx_user_seen (user_id, is_seen)",
        "ALTER TABLE notifications ADD CONSTRAINT fk_notif_user  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE",
        "ALTER TABLE notifications ADD CONSTRAINT fk_notif_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL",
    ] as $sql) {
        try {
            $db->exec($sql);
        } catch (Exception $e) { /* already exists — fine */
        }
    }
}

// ─── STEP 2: Show final table schema ──────────────────────────
$schema = $db->query("SHOW COLUMNS FROM notifications")->fetchAll();
$cols = array_column($schema, 'Field');
$log[] = ['info', '📋 Final columns: <b>' . implode(', ', $cols) . '</b>'];

// ─── STEP 3: Send a test notification to every customer ───────
$action = $_POST['action'] ?? '';
$testSent = false;
if ($action === 'send_test') {
    $customers = $db->query("
        SELECT DISTINCT o.user_id, o.id AS order_id
        FROM orders o
        JOIN users u ON u.id = o.user_id
        WHERE u.role = 'customer'
        ORDER BY o.id DESC
        LIMIT 20
    ")->fetchAll();

    if ($customers) {
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, order_id, type, message, is_seen)
            VALUES (?, ?, 'order_status', ?, 0)
        ");
        foreach ($customers as $c) {
            $orderNo = '#' . str_pad($c['order_id'], 4, '0', STR_PAD_LEFT);
            $stmt->execute([
                $c['user_id'],
                $c['order_id'],
                "Your order $orderNo is confirmed and being processed 🛠️. Thank you for shopping with Sweet Heaven! 🍰"
            ]);
        }
        $log[] = ['ok', '✅ Sent test notifications to <b>' . count($customers) . '</b> customer order(s).'];
        $testSent = true;
    } else {
        $log[] = ['warn', '⚠️ No customer orders found. Place an order first, then re-run.'];
    }
}

// ─── STEP 4: Show current notifications ───────────────────────
$rows = $db->query("
    SELECT n.*, u.name AS user_name
    FROM notifications n
    LEFT JOIN users u ON u.id = n.user_id
    ORDER BY n.id DESC
    LIMIT 20
")->fetchAll();

?><!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Sweet Heaven — Notification Setup</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen p-8">

    <div class="max-w-4xl mx-auto">
        <div class="bg-gradient-to-br from-rose-500 to-stone-800 text-white rounded-2xl p-8 mb-6">
            <h1 class="text-2xl font-bold mb-1">🔔 Notification System Setup</h1>
            <p class="text-rose-200 text-sm">Sweet Heaven Bakery — Run once, then delete this file.</p>
        </div>

        <!-- Log -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
            <h2 class="font-bold text-gray-800 mb-4">Step 1 — Database Fix</h2>
            <div class="space-y-1 font-mono text-sm">
                <?php foreach ($log as [$type, $msg]): ?>
                    <div
                        class="<?= $type === 'ok' ? 'text-green-700' : ($type === 'warn' ? 'text-amber-600' : 'text-gray-500') ?>">
                        <?= $msg ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Send Test Notification -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
            <h2 class="font-bold text-gray-800 mb-2">Step 2 — Send a Test Notification</h2>
            <p class="text-gray-500 text-sm mb-4">
                This injects a <b>"processing"</b> notification for every existing customer order so you can verify the
                bell works immediately.
            </p>
            <?php if ($testSent): ?>
                <div
                    class="bg-green-50 border border-green-200 text-green-700 rounded-xl px-4 py-3 text-sm font-semibold mb-4">
                    ✅ Test notifications sent! Log in as a customer and check the 🔔 bell.
                </div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="action" value="send_test">
                <button type="submit"
                    class="bg-rose-500 hover:bg-rose-600 text-white font-semibold px-6 py-3 rounded-xl transition-colors">
                    📨 Send Test Notification to All Customers
                </button>
            </form>
        </div>

        <!-- Current Notifications Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
            <h2 class="font-bold text-gray-800 mb-4">Step 3 — Current Notifications in DB
                <span class="text-gray-400 font-normal text-sm ml-2">(<?= count($rows) ?> rows)</span>
            </h2>
            <?php if (empty($rows)): ?>
                <p class="text-gray-400 text-sm text-center py-8">No notifications yet. Use the button above to send a test
                    one.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                            <tr>
                                <th class="px-3 py-2 text-left">ID</th>
                                <th class="px-3 py-2 text-left">User</th>
                                <th class="px-3 py-2 text-left">Order</th>
                                <th class="px-3 py-2 text-left">Type</th>
                                <th class="px-3 py-2 text-left">Seen</th>
                                <th class="px-3 py-2 text-left">Message</th>
                                <th class="px-3 py-2 text-left">Created</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <?php foreach ($rows as $r): ?>
                                <tr class="hover:bg-gray-50/50">
                                    <td class="px-3 py-2 font-mono text-gray-500"><?= $r['id'] ?></td>
                                    <td class="px-3 py-2">
                                        <?php if ($r['user_id']): ?>
                                            <span class="bg-blue-50 text-blue-700 text-xs px-2 py-0.5 rounded-full font-semibold">
                                                <?= htmlspecialchars($r['user_name'] ?? 'User ' . $r['user_id']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-gray-300 text-xs">admin-only</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-3 py-2 font-mono text-gray-500">
                                        <?= $r['order_id'] ? '#' . str_pad($r['order_id'], 4, '0', STR_PAD_LEFT) : '—' ?></td>
                                    <td class="px-3 py-2">
                                        <span
                                            class="bg-rose-50 text-rose-600 text-xs px-2 py-0.5 rounded-full font-semibold"><?= htmlspecialchars($r['type']) ?></span>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <?= $r['is_seen'] ? '<span class="text-green-500">✔</span>' : '<span class="text-rose-500 font-bold">●</span>' ?>
                                    </td>
                                    <td class="px-3 py-2 text-gray-600 max-w-xs truncate">
                                        <?= htmlspecialchars($r['message'] ?? '') ?></td>
                                    <td class="px-3 py-2 text-gray-400 text-xs whitespace-nowrap"><?= $r['created_at'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- How It Works -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
            <h2 class="font-bold text-gray-800 mb-4">✅ How The Notification System Works</h2>
            <ol class="space-y-3 text-sm text-gray-600">
                <li class="flex gap-3">
                    <span
                        class="w-6 h-6 bg-rose-500 text-white rounded-full flex items-center justify-center text-xs font-bold shrink-0">1</span>
                    <span>Admin changes order status (e.g. <b>processing</b>, <b>shipped</b>, <b>delivered</b>) in <code
                            class="bg-gray-100 px-1 rounded">admin/order.php</code></span>
                </li>
                <li class="flex gap-3">
                    <span
                        class="w-6 h-6 bg-rose-500 text-white rounded-full flex items-center justify-center text-xs font-bold shrink-0">2</span>
                    <span>PHP inserts a row into <code class="bg-gray-100 px-1 rounded">notifications</code> with the
                        customer's <b>user_id</b>, <b>order_id</b>, <b>type='order_status'</b>, and a friendly
                        message</span>
                </li>
                <li class="flex gap-3">
                    <span
                        class="w-6 h-6 bg-rose-500 text-white rounded-full flex items-center justify-center text-xs font-bold shrink-0">3</span>
                    <span>Customer's browser polls <code
                            class="bg-gray-100 px-1 rounded">/api/user_notifications.php?action=count</code> every <b>30
                            seconds</b> — badge animates in if new ones appear</span>
                </li>
                <li class="flex gap-3">
                    <span
                        class="w-6 h-6 bg-rose-500 text-white rounded-full flex items-center justify-center text-xs font-bold shrink-0">4</span>
                    <span>Customer clicks the 🔔 bell → dropdown loads notifications from <code
                            class="bg-gray-100 px-1 rounded">/api/user_notifications.php?action=list</code></span>
                </li>
                <li class="flex gap-3">
                    <span
                        class="w-6 h-6 bg-rose-500 text-white rounded-full flex items-center justify-center text-xs font-bold shrink-0">5</span>
                    <span>Clicking any notification → marks all as seen (<code
                            class="bg-gray-100 px-1 rounded">is_seen=1</code>) and navigates to the orders tab</span>
                </li>
            </ol>
        </div>

        <div class="bg-red-50 border border-red-200 rounded-2xl p-5 text-center">
            <p class="text-red-700 font-bold text-sm">⚠️ DELETE this file after setup is complete!</p>
            <p class="text-red-500 text-xs mt-1">File: <code>sweetheaven_setup_notifications.php</code></p>
        </div>
    </div>

</body>

</html>