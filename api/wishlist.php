<?php
// api/wishlist.php — Wishlist toggle handler
if (session_status() === PHP_SESSION_NONE)
    session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] === 'admin') {
    echo json_encode(['success' => false, 'redirect' => true]);
    exit;
}

require_once __DIR__ . '/../config/db.php';
$db = getDB();

$productId = (int) ($_POST['product_id'] ?? 0);
$userId = (int) $_SESSION['user_id'];

if (!$productId) {
    echo json_encode(['success' => false]);
    exit;
}

// Check if already wishlisted
$exists = $db->prepare("SELECT id FROM wishlist WHERE user_id=? AND product_id=?");
$exists->execute([$userId, $productId]);

if ($exists->fetch()) {
    $db->prepare("DELETE FROM wishlist WHERE user_id=? AND product_id=?")->execute([$userId, $productId]);
    echo json_encode(['success' => true, 'is_wishlisted' => false]);
} else {
    $db->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?,?)")->execute([$userId, $productId]);
    echo json_encode(['success' => true, 'is_wishlisted' => true]);
}
