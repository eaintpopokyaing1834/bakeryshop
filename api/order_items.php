<?php
// api/order_items.php — Returns order items for admin expand
if (session_status() === PHP_SESSION_NONE)
    session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

require_once __DIR__ . '/../config/db.php';
$db = getDB();

$orderId = (int) ($_GET['order_id'] ?? 0);
$items = $db->prepare("
    SELECT oi.quantity, oi.price, COALESCE(p.name, 'Custom Cake') AS product_name
    FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");

$items->execute([$orderId]);
echo json_encode($items->fetchAll());
