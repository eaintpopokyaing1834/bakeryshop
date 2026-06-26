<?php
if (session_status() === PHP_SESSION_NONE)
    session_start();
require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../config/db.php';
$db = getDB();

$orderId = (int) ($_GET['order_id'] ?? 0);
$order = $db->prepare("
    SELECT o.*, u.name AS customer_name, pm.payment_name, pm.acc_name, pm.acc_no, pm.qr_image, p.status AS pay_status, p.screenshot
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN payment p ON p.order_id = o.id
    LEFT JOIN payment_methods pm ON pm.id = p.payment_method_id
    WHERE o.id=? AND o.user_id=?
");
$order->execute([$orderId, $_SESSION['user_id']]);
$order = $order->fetch();
if (!$order) {
    header('Location: /sweetheaven/user/index.php');
    exit;
}

$orderItems = $db->prepare("
    SELECT oi.*, p.name AS product_name FROM order_items oi JOIN products p ON oi.product_id=p.id WHERE oi.order_id=?
");
$orderItems->execute([$orderId]);
$orderItems = $orderItems->fetchAll();

// DB migration: ensure payment table has screenshot column
try {
    $db->exec("ALTER TABLE payment ADD COLUMN screenshot VARCHAR(255) DEFAULT NULL AFTER payment_method_id");
} catch (Exception $e) {
}
try {
    $db->exec("ALTER TABLE payment MODIFY COLUMN status ENUM('pending','approved','rejected') DEFAULT 'pending'");
} catch (Exception $e) {
}

$payStatusColors = [
    'pending' => 'bg-amber-100 text-amber-700',
    'approved' => 'bg-green-100 text-green-700',
    'rejected' => 'bg-red-100 text-red-700',
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmed! — Sweet Heaven Bakery</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }

        @keyframes checkmark {
            0% {
                stroke-dashoffset: 50
            }

            100% {
                stroke-dashoffset: 0
            }
        }

        .checkmark-path {
            stroke-dasharray: 50;
            stroke-dashoffset: 50;
            animation: checkmark 0.8s ease forwards 0.5s;
        }

        @keyframes scaleIn {
            0% {
                transform: scale(0)
            }

            100% {
                transform: scale(1)
            }
        }

        .scale-in {
            animation: scaleIn 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
        }
    </style>
</head>

<body class="bg-gradient-to-b from-stone-50 to-white min-h-screen">
    <?php require_once __DIR__ . '/../includes/header.php'; ?>

    <div class="max-w-2xl mx-auto px-6 py-16 text-center">
        <!-- Success Animation -->
        <div class="scale-in w-24 h-24 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
            <svg class="w-12 h-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path class="checkmark-path" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                    d="M5 13l4 4L19 7" />
            </svg>
        </div>

        <h1 class="text-3xl font-bold text-gray-800 mb-3">Order Placed! 🎉</h1>
        <p class="text-gray-500 mb-2">Thank you, <strong>
                <?= htmlspecialchars($order['customer_name']) ?>
            </strong>!</p>
        <p class="text-gray-400 text-sm mb-8">Your order <span class="text-rose-500 font-bold">#
                <?= str_pad($order['id'], 4, '0', STR_PAD_LEFT) ?>
            </span> has been received and is being processed.</p>

        <!-- Order Card -->
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8 text-left mb-8">
            <div class="grid sm:grid-cols-2 gap-4 mb-6 pb-6 border-b border-gray-100 text-sm">
                <div>
                    <p class="text-gray-400 text-xs mb-1">Order Number</p>
                    <p class="font-bold text-gray-800">#
                        <?= str_pad($order['id'], 4, '0', STR_PAD_LEFT) ?>
                    </p>
                </div>
                <div>
                    <p class="text-gray-400 text-xs mb-1">Date</p>
                    <p class="font-bold text-gray-800">
                        <?= date('M j, Y g:i A', strtotime($order['order_date'])) ?>
                    </p>
                </div>
                <div>
                    <p class="text-gray-400 text-xs mb-1">Shipping</p>
                    <p class="font-bold text-gray-800 capitalize">
                        <?= $order['shipping_method'] ?> delivery
                    </p>
                </div>
                <div>
                    <p class="text-gray-400 text-xs mb-1">Status</p>
                    <span
                        class="inline-flex bg-amber-100 text-amber-700 text-xs font-bold px-3 py-1 rounded-full">Pending</span>
                </div>
            </div>

            <!-- Items -->
            <h3 class="font-bold text-gray-700 mb-4 text-sm uppercase tracking-wider">Items Ordered</h3>
            <div class="space-y-3 mb-6 pb-6 border-b border-gray-100">
                <?php foreach ($orderItems as $item): ?>
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-gray-700">
                            <?= htmlspecialchars($item['product_name']) ?> <span class="text-gray-400">x
                                <?= $item['quantity'] ?>
                            </span>
                        </span>
                        <span class="font-semibold text-gray-800">
                            <?= number_format($item['price'] * $item['quantity']) ?> MMK
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="flex justify-between items-center font-bold text-gray-800">
                <span>Total Amount</span>
                <span class="text-xl text-rose-500">
                    <?= number_format($order['total_amount']) ?> MMK
                </span>
            </div>

            <!-- Payment Info -->
            <!-- <?php if ($order['payment_name']): ?>
            <div class="mt-6 pt-6 border-t border-gray-100 bg-amber-50 rounded-2xl p-5">
                <p class="font-bold text-amber-800 mb-2">📱 Payment Instructions</p>
                <p class="text-sm text-amber-700">Please transfer <strong>
                        <?= number_format($order['total_amount']) ?> MMK
                    </strong> to:</p>
                <p class="text-sm font-bold text-amber-800 mt-1">
                    <?= htmlspecialchars($order['payment_name']) ?>
                </p>
                <?php if ($order['acc_name']): ?>
                <p class="text-sm text-amber-700">Account Name:
                    <?= htmlspecialchars($order['acc_name']) ?>
                </p>
                <p class="text-sm text-amber-700">Account No: <strong>
                        <?= htmlspecialchars($order['acc_no']) ?>
                    </strong></p>
                <?php endif; ?>

                <?php if (!empty($order['qr_image'])): ?>
                <div class="mt-3 flex justify-center">
                    <img src="/sweetheaven/images/<?php echo htmlspecialchars($order['qr_image']); ?>"
                        class="w-36 h-36 object-contain border border-amber-200 rounded-xl bg-white" alt="Payment QR">
                </div>
                <?php endif; ?> -->
                </div>

                <!-- Payment Status & Upload -->
                <div class="mt-4 bg-white rounded-2xl border border-gray-100 p-5 text-left">
                    <p class="font-bold text-gray-800 mb-3">💳 Payment Status</p>
                    <?php if ($order['pay_status']): ?>
                        <span
                            class="inline-flex text-xs font-bold px-3 py-1.5 rounded-full <?= $payStatusColors[$order['pay_status']] ?? 'bg-gray-100 text-gray-600' ?>">
                            <?= ucfirst($order['pay_status']) ?>
                        </span>
                    <?php else: ?>
                        <span
                            class="inline-flex text-xs font-bold px-3 py-1.5 rounded-full bg-amber-100 text-amber-700">Pending</span>
                    <?php endif; ?>
                    <?php if (!empty($order['screenshot'])): ?>
                        <div class="mt-3">
                            <p class="text-xs text-gray-400 mb-1">Your uploaded receipt:</p>
                            <a href="/sweetheaven/<?= htmlspecialchars($order['screenshot']) ?>" target="_blank">
                                <img src="/sweetheaven/<?= htmlspecialchars($order['screenshot']) ?>"
                                    class="w-24 h-24 object-cover rounded-xl border border-gray-200">
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Actions -->
        <div class="flex flex-col sm:flex-row gap-4 justify-center mt-8">
            <a href="/sweetheaven/user/profile.php?tab=orders"
                class="bg-rose-500 hover:bg-rose-600 text-white font-semibold px-8 py-4 rounded-2xl transition-colors">
                View My Orders
            </a>
            <a href="/sweetheaven/user/products.php"
                class="border-2 border-stone-200 text-rose-500 font-semibold px-8 py-4 rounded-2xl hover:bg-rose-50 transition-colors">
                Continue Shopping
            </a>
        </div>
    </div>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</body>

</html>