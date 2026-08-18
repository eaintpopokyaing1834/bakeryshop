<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
$db = getDB();

$orderId = (int)($_GET['order_id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);

if (!$orderId || !$userId) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$order = $db->prepare("
    SELECT o.*, u.name, u.email,
           pay.status AS pay_status
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN payment pay ON pay.order_id = o.id
    LEFT JOIN payment_methods pm ON pay.payment_method_id = pm.id
    WHERE o.id = ? AND o.user_id = ?
");
$order->execute([$orderId, $userId]);
$order = $order->fetch();

if (!$order) {
    http_response_code(403);
    echo json_encode(['error' => 'Voucher not available']);
    exit;
}

$items = $db->prepare("
    SELECT oi.quantity, oi.price AS discounted_price,
           COALESCE(p.name, 'Customize Cake') AS product_name,
           p.price AS original_price,
           oi.product_id,
           COALESCE(
               (SELECT image_url FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1),
               cr.reference_image
           ) as product_image
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    LEFT JOIN orders o ON oi.order_id = o.id
    LEFT JOIN customize_requests cr ON o.customize_request_id = cr.id
    WHERE oi.order_id = ?
");
$items->execute([$orderId]);
$items = $items->fetchAll();

$originalSubtotal = 0;
$discountedSubtotal = 0;
$hasCustomItem = false;
foreach ($items as &$item) {
    $origPrice = (float)($item['original_price'] ?? $item['discounted_price']);
    $discPrice = (float)$item['discounted_price'];
    $qty = (int)$item['quantity'];
    $originalSubtotal += $origPrice * $qty;
    $discountedSubtotal += $discPrice * $qty;
    $item['original_price'] = $origPrice;
    if ($item['product_id'] === null) {
        $hasCustomItem = true;
    }
}
unset($item);

$productDiscount = round($originalSubtotal - $discountedSubtotal, 2);

$orderCount = $db->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND id < ?");
$orderCount->execute([$userId, $orderId]);
$isFirstOrder = (int)$orderCount->fetchColumn() === 0;
$firstOrderDiscount = ($isFirstOrder && !$hasCustomItem) ? round($originalSubtotal * 0.05, 2) : 0;

$shippingCostMap = [
    'free_delivery' => 0,
    'pickup'        => 0,
    'express'       => 2000,
    'standard'      => 0,
];
$shippingFee = $shippingCostMap[$order['shipping_method']] ?? 0;

$voucher = [
    'order_id' => (int)$order['id'],
    'customer_name' => $order['name'],
    'email' => $order['email'],
    'phone' => $order['phone'] ?? '',
    'items' => $items,
    'original_subtotal' => $originalSubtotal,
    'product_discount' => $productDiscount,
    'first_order_discount' => $firstOrderDiscount,
    'shipping_method' => $order['shipping_method'],
    'shipping_fee' => $shippingFee,
    'total_amount' => (float)$order['total_amount'],
    'order_date' => $order['order_date'],
];

echo json_encode($voucher);
