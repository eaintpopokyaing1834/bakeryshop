<?php
// api/review.php — Review submission handler
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'msg' => __('review_login_required')]);
    exit;
}

require_once __DIR__ . '/../includes/lang.php';
require_once __DIR__ . '/../config/db.php';
$db = getDB();

$productId = (int)($_POST['product_id'] ?? 0);
$rating    = (int)($_POST['rating'] ?? 0);
$comment   = trim($_POST['comment'] ?? '');
$userId    = (int)$_SESSION['user_id'];

if (!$productId || $rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'msg' => __('review_invalid_rating')]);
    exit;
}

// Check if user has purchased this product
$hasPurchased = $db->prepare("
    SELECT COUNT(*) FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE o.user_id=? AND oi.product_id=? AND o.status='delivered'
");
$hasPurchased->execute([$userId, $productId]);
if (!(int)$hasPurchased->fetchColumn()) {
    echo json_encode(['success' => false, 'msg' => __('review_only_purchased')]);
    exit;
}

// Upsert review
$existing = $db->prepare("SELECT id FROM reviews WHERE user_id=? AND product_id=?");
$existing->execute([$userId, $productId]);

if ($existing->fetch()) {
    $db->prepare("UPDATE reviews SET rating=?,comment=?,created_at=NOW() WHERE user_id=? AND product_id=?")
       ->execute([$rating, $comment, $userId, $productId]);
    $msg = __('review_updated');
} else {
    $db->prepare("INSERT INTO reviews (user_id,product_id,rating,comment,status) VALUES (?,?,?,?,'approved')")
       ->execute([$userId, $productId, $rating, $comment]);
    $msg = __('review_submitted');
}

echo json_encode(['success' => true, 'msg' => $msg, 'reviewer' => $_SESSION['name'], 'rating' => $rating, 'comment' => htmlspecialchars($comment)]);
