<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_SESSION['user_id']) || in_array($_SESSION['role'] ?? '', ['admin', 'cashier'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$comment = trim($_POST['comment'] ?? '');
$rating  = (int) ($_POST['rating'] ?? 0);

if (empty($comment)) {
    echo json_encode(['success' => false, 'message' => 'Review comment is required.']);
    exit;
}

if (strlen($comment) < 10) {
    echo json_encode(['success' => false, 'message' => 'Review must be at least 10 characters.']);
    exit;
}

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5.']);
    exit;
}

$userId = (int) $_SESSION['user_id'];

// Check if user already has a general review (no product_id)
$existing = $db->prepare("SELECT id FROM reviews WHERE user_id = ? AND product_id IS NULL");
$existing->execute([$userId]);

if ($existing->fetch()) {
    $stmt = $db->prepare("UPDATE reviews SET rating = ?, comment = ?, created_at = NOW() WHERE user_id = ? AND product_id IS NULL");
    $stmt->execute([$rating, htmlspecialchars($comment), $userId]);
    echo json_encode(['success' => true, 'message' => 'Your review has been updated.']);
} else {
    $stmt = $db->prepare("INSERT INTO reviews (user_id, rating, comment, status) VALUES (?, ?, ?, 'approved')");
    $stmt->execute([$userId, $rating, htmlspecialchars($comment)]);
    echo json_encode(['success' => true, 'message' => 'Thank you! Your review has been submitted.']);
}
