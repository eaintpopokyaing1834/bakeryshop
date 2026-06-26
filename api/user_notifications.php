<?php
// api/user_notifications.php — Per-user notification API
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
$db = getDB();

// Must be a logged-in customer
if (!isset($_SESSION['user_id']) || (isset($_SESSION['role']) && $_SESSION['role'] === 'admin')) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

switch ($action) {

    case 'list':
        $stmt = $db->prepare("
            SELECT id, order_id, type, message, is_seen, created_at
            FROM notifications
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 30
        ");
        $stmt->execute([$userId]);
        echo json_encode($stmt->fetchAll());
        break;

    case 'count':
        $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_seen = 0");
        $stmt->execute([$userId]);
        echo json_encode(['count' => (int)$stmt->fetchColumn()]);
        break;

    case 'mark_seen':
        $db->prepare("UPDATE notifications SET is_seen = 1 WHERE user_id = ? AND is_seen = 0")
           ->execute([$userId]);
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}
