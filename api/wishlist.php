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
$alreadyWishlisted = $exists->fetch();

if ($alreadyWishlisted) {
    $db->prepare("DELETE FROM wishlist WHERE user_id=? AND product_id=?")->execute([$userId, $productId]);
    $prodName = '';
} else {
    $prod = $db->prepare("SELECT name FROM products WHERE id=?");
    $prod->execute([$productId]);
    $prodName = $prod->fetchColumn() ?: 'Item';
    $db->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?,?)")->execute([$userId, $productId]);
}

// Get updated wishlist count
$wcount = $db->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id=?");
$wcount->execute([$userId]);
$wishlistCount = (int)$wcount->fetchColumn();

if ($alreadyWishlisted) {
    echo json_encode(['success' => true, 'is_wishlisted' => false, 'message' => 'Removed from wishlist', 'wishlist_count' => $wishlistCount]);
} else {
    echo json_encode(['success' => true, 'is_wishlisted' => true, 'message' => $prodName . ' added to wishlist!', 'wishlist_count' => $wishlistCount]);
}
