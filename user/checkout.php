<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../middleware/customer_check.php';
require_once __DIR__ . '/../config/db.php';
$db = getDB();

$cart = $_SESSION['cart'] ?? [];
if (empty($cart)) { header('Location: /sweetheaven/user/cart.php'); exit; }

$error = '';

// ── Place Order (POST) ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name            = trim($_POST['full_name'] ?? '');
    $phone           = trim($_POST['phone'] ?? '');
    $address         = trim($_POST['shipping_address'] ?? '');
    $shippingMethod  = $_POST['shipping_method'] ?? 'standard';
    $requestNote     = trim($_POST['request_note'] ?? '');
    $paymentMethodId = (int)($_POST['payment_method_id'] ?? 0);

    if (!$name || !$phone || !$address || !$paymentMethodId) {
        $error = 'Please fill in all required fields.';
    } else {
        $shippingFee    = $shippingMethod === 'express' ? 5000 : 2000;
        $subtotal       = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $cart));
        $totalAmount    = $subtotal + $shippingFee;

        $db->beginTransaction();
        try {
            // Insert order
            $stmt = $db->prepare("INSERT INTO orders (user_id,phone,shipping_method,shipping_address,total_amount,status,request_note)
                                  VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$_SESSION['user_id'], $phone, $shippingMethod, $address, $totalAmount, 'pending', $requestNote]);
            $orderId = $db->lastInsertId();

            // Insert order items
            foreach ($cart as $item) {
                $db->prepare("INSERT INTO order_items (order_id,product_id,quantity,price) VALUES (?,?,?,?)")
                   ->execute([$orderId, $item['product_id'], $item['qty'], $item['price']]);
                // Decrement stock
                $db->prepare("UPDATE products SET stock = stock - ? WHERE id=?")->execute([$item['qty'], $item['product_id']]);
            }

            // Insert payment record
            $db->prepare("INSERT INTO payment (order_id,payment_method_id,status) VALUES (?,?,'pending')")
               ->execute([$orderId, $paymentMethodId]);

            // Insert notification for admin
            $db->prepare("INSERT INTO notifications (type, title, message) VALUES ('new_order', ?, ?)")
               ->execute([
                   'New Order Received',
                   "Customer " . htmlspecialchars($name) . " placed order #$orderId for " . number_format($totalAmount) . " MMK"
               ]);

            $db->commit();
            $_SESSION['cart'] = [];
            header("Location: /sweetheaven/user/order_confirmation.php?order_id=$orderId");
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'An error occurred while placing your order. Please try again.';
        }
    }
}

// ── Fetch Data ────────────────────────────────────────
$paymentMethods = $db->query("SELECT * FROM payment_methods")->fetchAll();
$user           = $db->prepare("SELECT * FROM users WHERE id=?");
$user->execute([$_SESSION['user_id']]);
$user = $user->fetch();

// Build cart items from DB
$ids   = implode(',', array_map('intval', array_keys($cart)));
$items = $db->query("
    SELECT p.id, p.name, p.price, (SELECT image_url FROM product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) AS primary_image
    FROM products p WHERE p.id IN ($ids)
")->fetchAll();
$cartDetails = [];
$subtotal = 0;
foreach ($items as $item) {
    $qty = $cart[$item['id']]['qty'];
    $item['qty'] = $qty;
    $item['item_total'] = $item['price'] * $qty;
    $subtotal += $item['item_total'];
    $cartDetails[] = $item;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout — Sweet Heaven Bakery</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>* { font-family: 'Poppins', sans-serif; }</style>
</head>
<body class="bg-gray-50">
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="max-w-6xl mx-auto px-6 py-10">
    <h1 class="text-3xl font-bold text-gray-800 mb-8">Checkout</h1>

    <?php if ($error): ?>
    <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" id="checkoutForm">
        <div class="grid lg:grid-cols-3 gap-8">

            <!-- Left: Checkout Form -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Delivery Info -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h2 class="font-bold text-gray-800 text-lg mb-5 flex items-center gap-2">
                        <span class="w-7 h-7 bg-rose-500 text-white rounded-full flex items-center justify-center text-sm font-bold">1</span>
                        Delivery Information
                    </h2>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Full Name *</label>
                            <input type="text" name="full_name" required
                                value="<?= htmlspecialchars($user['name']) ?>"
                                class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Phone Number *</label>
                            <input type="tel" name="phone" required placeholder="09 XXXX XXXXX"
                                class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                            <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled
                                class="w-full px-4 py-3 rounded-xl border border-gray-100 bg-gray-50 text-gray-400 text-sm">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Shipping Address *</label>
                            <textarea name="shipping_address" required rows="3" placeholder="Street, Township, City..."
                                class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm resize-none"></textarea>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Special Request / Note</label>
                            <input type="text" name="request_note" placeholder="Any special instructions..."
                                class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
                        </div>
                    </div>
                </div>

                <!-- Shipping Method -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h2 class="font-bold text-gray-800 text-lg mb-5 flex items-center gap-2">
                        <span class="w-7 h-7 bg-rose-500 text-white rounded-full flex items-center justify-center text-sm font-bold">2</span>
                        Shipping Method
                    </h2>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <label class="cursor-pointer">
                            <input type="radio" name="shipping_method" value="standard" checked class="sr-only peer">
                            <div class="border-2 border-gray-200 peer-checked:border-rose-400 peer-checked:bg-rose-50 rounded-2xl p-5 transition-all">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="font-bold text-gray-700">Standard</span>
                                    <span class="text-rose-500 font-bold">2,000 MMK</span>
                                </div>
                                <p class="text-xs text-gray-400">Delivered in 3–5 business days</p>
                            </div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="shipping_method" value="express" class="sr-only peer">
                            <div class="border-2 border-gray-200 peer-checked:border-rose-400 peer-checked:bg-rose-50 rounded-2xl p-5 transition-all">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="font-bold text-gray-700">Express</span>
                                    <span class="text-rose-500 font-bold">5,000 MMK</span>
                                </div>
                                <p class="text-xs text-gray-400">Same day or next day delivery</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Payment Method -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h2 class="font-bold text-gray-800 text-lg mb-5 flex items-center gap-2">
                        <span class="w-7 h-7 bg-rose-500 text-white rounded-full flex items-center justify-center text-sm font-bold">3</span>
                        Payment Method
                    </h2>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <?php foreach ($paymentMethods as $pm): ?>
                        <label class="cursor-pointer">
                            <input type="radio" name="payment_method_id" value="<?= $pm['id'] ?>" <?= reset($paymentMethods)['id']===$pm['id']?'checked':'' ?> required class="sr-only peer">
                            <div class="border-2 border-gray-200 peer-checked:border-rose-400 peer-checked:bg-rose-50 rounded-2xl p-5 transition-all">
                                <p class="font-bold text-gray-700 mb-1"><?= htmlspecialchars($pm['payment_name']) ?></p>
                                <?php if ($pm['acc_name']): ?>
                                <p class="text-xs text-gray-500">Account: <?= htmlspecialchars($pm['acc_name']) ?></p>
                                <p class="text-xs text-rose-500 font-semibold"><?= htmlspecialchars($pm['acc_no']) ?></p>
                                <?php endif; ?>
                                <p class="text-xs text-amber-600 mt-2 font-medium">📱 Transfer after order placement</p>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-4 bg-amber-50 border border-amber-200 rounded-xl p-4 text-xs text-amber-700">
                        ⚠️ <strong>Note:</strong> After placing your order, please transfer the total amount to your selected payment method. Your order will be processed upon confirmation.
                    </div>
                </div>
            </div>

            <!-- Right: Order Summary -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sticky top-24">
                    <h3 class="font-bold text-gray-800 text-lg mb-5">Order Summary</h3>

                    <div class="space-y-4 mb-5 max-h-72 overflow-y-auto pr-1">
                        <?php foreach ($cartDetails as $item): ?>
                        <?php $imgSrc = $item['primary_image'] ? '/sweetheaven/'.$item['primary_image'] : '/sweetheaven/images/maincake.jpg'; ?>
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-xl overflow-hidden bg-rose-50 shrink-0">
                                <img src="<?= htmlspecialchars($imgSrc) ?>" class="w-full h-full object-cover">
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-700 line-clamp-1"><?= htmlspecialchars($item['name']) ?></p>
                                <p class="text-xs text-gray-400">x<?= $item['qty'] ?></p>
                            </div>
                            <p class="text-sm font-bold text-gray-700"><?= number_format($item['item_total']) ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="border-t border-gray-100 pt-4 space-y-2 text-sm">
                        <div class="flex justify-between text-gray-500">
                            <span>Subtotal</span>
                            <span><?= number_format($subtotal) ?> MMK</span>
                        </div>
                        <div class="flex justify-between text-gray-500">
                            <span>Shipping</span>
                            <span id="shippingDisplay">2,000 MMK</span>
                        </div>
                        <div class="flex justify-between font-bold text-gray-800 text-base border-t border-gray-100 pt-2">
                            <span>Total</span>
                            <span id="totalDisplay"><?= number_format($subtotal + 2000) ?> MMK</span>
                        </div>
                    </div>

                    <button type="submit"
                        class="mt-6 w-full bg-rose-500 hover:bg-rose-600 text-white font-bold py-4 rounded-2xl transition-colors shadow-sm shadow-rose-100 text-base">
                        Place Order 🎉
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
const subtotal = <?= $subtotal ?>;
const shippingFees = { standard: 2000, express: 5000 };

document.querySelectorAll('input[name="shipping_method"]').forEach(radio => {
    radio.addEventListener('change', () => {
        const fee   = shippingFees[radio.value] || 2000;
        const total = subtotal + fee;
        document.getElementById('shippingDisplay').textContent = fee.toLocaleString('en') + ' MMK';
        document.getElementById('totalDisplay').textContent    = total.toLocaleString('en') + ' MMK';
    });
});
</script>
</body>
</html>
