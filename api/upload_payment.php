<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
$db = getDB();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] === 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$orderId = (int)($_POST['order_id'] ?? 0);
if (!$orderId) {
    echo json_encode(['success' => false, 'message' => 'Invalid order']);
    exit;
}

// Verify order belongs to user
$order = $db->prepare("SELECT id FROM orders WHERE id=? AND user_id=?");
$order->execute([$orderId, $_SESSION['user_id']]);
if (!$order->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit;
}

if (empty($_FILES['screenshot']) || $_FILES['screenshot']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Please select a file to upload.']);
    exit;
}

$ext = strtolower(pathinfo($_FILES['screenshot']['name'], PATHINFO_EXTENSION));
$allowed = ['jpg', 'jpeg', 'png', 'webp'];
if (!in_array($ext, $allowed)) {
    echo json_encode(['success' => false, 'message' => 'Only JPG, JPEG, PNG, and WEBP files are allowed.']);
    exit;
}

$uploadDir = __DIR__ . '/../uploads/payments/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

$filename = 'payment_' . $orderId . '_' . time() . '.' . $ext;
move_uploaded_file($_FILES['screenshot']['tmp_name'], $uploadDir . $filename);

// Check if payment record exists
$payCheck = $db->prepare("SELECT id FROM payment WHERE order_id=?");
$payCheck->execute([$orderId]);
$payRecord = $payCheck->fetch();

if ($payRecord) {
    $db->prepare("UPDATE payment SET screenshot=?, status='pending' WHERE order_id=?")
       ->execute(['uploads/payments/' . $filename, $orderId]);
} else {
    $db->prepare("INSERT INTO payment (order_id, payment_method_id, screenshot, status) VALUES (?, ?, ?, 'pending')")
       ->execute([$orderId, 0, 'uploads/payments/' . $filename]);
}

echo json_encode(['success' => true, 'message' => 'Payment screenshot uploaded successfully!']);
