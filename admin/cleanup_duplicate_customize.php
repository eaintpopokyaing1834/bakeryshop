<?php
/**
 * One-time cleanup: remove duplicate pending customize_requests.
 * Keeps the FIRST (lowest id) pending/approved request per user.
 * Run once from the browser, then delete this file.
 */
require_once __DIR__ . '/../middleware/admin_check.php';
require_once __DIR__ . '/../config/db.php';

$db = getDB();

// Find all users who have more than one pending/approved request
$dupes = $db->query("
    SELECT user_id, MIN(id) AS keep_id, COUNT(*) AS total
    FROM customize_requests
    WHERE status IN ('pending','approved')
    GROUP BY user_id
    HAVING total > 1
")->fetchAll(PDO::FETCH_ASSOC);

$deleted = 0;
foreach ($dupes as $row) {
    // Delete the extra rows (all pending duplicates that are NOT the kept one)
    $stmt = $db->prepare("
        DELETE FROM customize_requests
        WHERE user_id = ?
          AND id != ?
          AND status = 'pending'
    ");
    $stmt->execute([$row['user_id'], $row['keep_id']]);
    $deleted += $stmt->rowCount();
}

echo "<h2>Cleanup complete</h2>";
echo "<p>Deleted <strong>{$deleted}</strong> duplicate pending customize request(s).</p>";
echo "<p>Each user's earliest pending/approved request has been kept intact.</p>";
echo "<p><strong>Please delete this file now:</strong> <code>admin/cleanup_duplicate_customize.php</code></p>";
