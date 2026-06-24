<?php
session_start();
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/db.php';
$db = getDB();

$isAdmin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'count';

switch ($action) {
    case 'count':
        $stmt = $db->query("SELECT COUNT(*) FROM notifications WHERE is_seen = 0");
        echo json_encode(['count' => (int)$stmt->fetchColumn()]);
        break;

    case 'list':
        $stmt = $db->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 20");
        echo json_encode($stmt->fetchAll());
        break;

    case 'mark_seen':
        $db->exec("UPDATE notifications SET is_seen = 1 WHERE is_seen = 0");
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}
