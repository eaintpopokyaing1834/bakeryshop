<?php
if (session_status() === PHP_SESSION_NONE)
    session_start();
require_once __DIR__ . '/../includes/lang.php';
require_once __DIR__ . '/../middleware/customer_check.php';
require_once __DIR__ . '/../config/db.php';
$db = getDB();

$customizeId = (int)($_GET['customize_id'] ?? 0);
$customizeRequest = null;

if ($customizeId) {
    // Customize cake order flow
    $stmt = $db->prepare("SELECT * FROM customize_requests WHERE id=? AND user_id=? AND status='approved'");
    $stmt->execute([$customizeId, $_SESSION['user_id']]);
    $customizeRequest = $stmt->fetch();
    if (!$customizeRequest) {
        header('Location: /sweetheaven/user/customize.php');
        exit;
    }
}

$cart = $_SESSION['cart'] ?? [];
if (empty($cart) && !$customizeRequest) {
    header('Location: /sweetheaven/user/cart.php');
    exit;
}

$error = '';

// ── Place Order (POST) ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['shipping_address'] ?? '');
    $shippingMethod = $_POST['shipping_method'] ?? 'standard';
    $requestNote = trim($_POST['request_note'] ?? '');
    $paymentMethodId = (int) ($_POST['payment_method_id'] ?? 0);
    $customizeId = (int)($_POST['customize_id'] ?? 0);

    if (!$name || !$phone || !$address || !$paymentMethodId) {
        $error = 'Please fill in all required fields.';
    } elseif (empty($_FILES['payment_screenshot']) || $_FILES['payment_screenshot']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload your payment screenshot.';
    } else {
        $file = $_FILES['payment_screenshot'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowed)) {
            $error = 'Only JPG, JPEG, PNG & WEBP files are allowed.';
        } else {
            $shippingFee = $shippingMethod === 'express' ? 5000 : 2000;

            if ($customizeId) {
                $crStmt = $db->prepare("SELECT * FROM customize_requests WHERE id=? AND user_id=? AND status='approved'");
                $crStmt->execute([$customizeId, $_SESSION['user_id']]);
                $cr = $crStmt->fetch();
                if (!$cr) {
                    $error = 'Invalid customize request.';
                } else {
                    $subtotal = (float)$cr['admin_price'];
                    $totalAmount = $subtotal + $shippingFee;
                }
            } else {
                $subtotal = 0;
                foreach ($cart as $cid => $citem) {
                    $pStmt = $db->prepare("SELECT p.price, d.type AS discount_type, d.value AS discount_value FROM products p LEFT JOIN discounts d ON p.discount_id = d.id WHERE p.id=?");
                    $pStmt->execute([$cid]);
                    $pData = $pStmt->fetch();
                    $unitPrice = (float)$pData['price'];
                    if ($pData['discount_value']) {
                        if ($pData['discount_type'] === 'percentage') {
                            $unitPrice = $unitPrice * (1 - $pData['discount_value'] / 100);
                        } else {
                            $unitPrice = max(0, $unitPrice - $pData['discount_value']);
                        }
                    }
                    $subtotal += $unitPrice * $citem['qty'];
                }
                $orderCount = $db->prepare("SELECT COUNT(*) FROM orders WHERE user_id=?");
                $orderCount->execute([$_SESSION['user_id']]);
                $isFirstOrder = $orderCount->fetchColumn() == 0;
                $firstOrderDiscount = $isFirstOrder ? $subtotal * 0.10 : 0;
                $totalAmount = $subtotal - $firstOrderDiscount + $shippingFee;
            }

            if (!$error) {
                $db->beginTransaction();
                try {
                    if ($customizeId) {
                        $stmt = $db->prepare("INSERT INTO orders (user_id,phone,shipping_method,shipping_address,total_amount,status,request_note,customize_request_id)
                                              VALUES (?,?,?,?,?,?,?,?)");
                        $stmt->execute([$_SESSION['user_id'], $phone, $shippingMethod, $address, $totalAmount, 'pending', $requestNote, $customizeId]);
                    } else {
                        $stmt = $db->prepare("INSERT INTO orders (user_id,phone,shipping_method,shipping_address,total_amount,status,request_note)
                                              VALUES (?,?,?,?,?,?,?)");
                        $stmt->execute([$_SESSION['user_id'], $phone, $shippingMethod, $address, $totalAmount, 'pending', $requestNote]);
                    }
                    $orderId = $db->lastInsertId();

                    if ($customizeId) {
                        $db->prepare("INSERT INTO order_items (order_id,product_id,quantity,price) VALUES (?,NULL,1,?)")
                            ->execute([$orderId, $subtotal]);
                        $db->prepare("UPDATE customize_requests SET status='ordered' WHERE id=?")
                            ->execute([$customizeId]);
                    } else {
                        foreach ($cart as $cid => $citem) {
                            $pStmt = $db->prepare("SELECT p.price, d.type AS discount_type, d.value AS discount_value FROM products p LEFT JOIN discounts d ON p.discount_id = d.id WHERE p.id=?");
                            $pStmt->execute([$cid]);
                            $pData = $pStmt->fetch();
                            $unitPrice = (float)$pData['price'];
                            if ($pData['discount_value']) {
                                if ($pData['discount_type'] === 'percentage') {
                                    $unitPrice = $unitPrice * (1 - $pData['discount_value'] / 100);
                                } else {
                                    $unitPrice = max(0, $unitPrice - $pData['discount_value']);
                                }
                            }
                            $db->prepare("INSERT INTO order_items (order_id,product_id,quantity,price) VALUES (?,?,?,?)")
                                ->execute([$orderId, $cid, $citem['qty'], $unitPrice]);
                            $db->prepare("UPDATE products SET stock = stock - ? WHERE id=?")->execute([$citem['qty'], $cid]);
                        }
                    }

                    // Save screenshot
                    $uploadDir = __DIR__ . '/../uploads/payments/';
                    if (!is_dir($uploadDir))
                        mkdir($uploadDir, 0777, true);
                    $filename = 'payment_' . $orderId . '_' . time() . '.' . $ext;
                    move_uploaded_file($file['tmp_name'], $uploadDir . $filename);
                    $screenshotPath = 'uploads/payments/' . $filename;

                    // Insert payment record with screenshot
                    $db->prepare("INSERT INTO payment (order_id,payment_method_id,screenshot,status) VALUES (?,?,?,'pending')")
                        ->execute([$orderId, $paymentMethodId, $screenshotPath]);

                    // Admin notification
                    $orderLabel = $customizeId ? 'Custom Cake Order' : 'New Order Received';
                    $db->prepare("INSERT INTO notifications (type, title, message) VALUES ('new_order', ?, ?)")
                        ->execute([
                            $orderLabel,
                            "Customer " . htmlspecialchars($name) . " placed order #$orderId for " . number_format($totalAmount) . " MMK"
                        ]);

                    // Customer notification
                    $orderNo = '#' . str_pad($orderId, 4, '0', STR_PAD_LEFT);
                    $db->prepare("INSERT INTO notifications (user_id, order_id, type, message, is_seen) VALUES (?, ?, 'new_order', ?, 0)")
                        ->execute([
                            $_SESSION['user_id'],
                            $orderId,
                            "Your order $orderNo has been placed successfully! Total: " . number_format($totalAmount) . " MMK. We'll notify you when it's processed. 🎉"
                        ]);

                    $db->commit();
                    if (!$customizeId) $_SESSION['cart'] = [];
                    header("Location: /sweetheaven/user/order_confirmation.php?order_id=$orderId");
                    exit;
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = 'Database Error: ' . $e->getMessage();
                }
            }
        }
    }
}

// ── Fetch Data ────────────────────────────────────────
$paymentMethods = $db->query("SELECT * FROM payment_methods")->fetchAll();
$user = $db->prepare("SELECT * FROM users WHERE id=?");
$user->execute([$_SESSION['user_id']]);
$user = $user->fetch();

$cartDetails = [];
$subtotal = 0;
$firstOrderDiscount = 0;
$totalSavings = 0;

if ($customizeRequest) {
    $cartDetails[] = [
        'id' => 0,
        'name' => 'Custom ' . $customizeRequest['size'] . ' ' . $customizeRequest['flavor'] . ' Cake',
        'price' => (float)$customizeRequest['admin_price'],
        'qty' => 1,
        'item_total' => (float)$customizeRequest['admin_price'],
        'primary_image' => $customizeRequest['reference_image'],
    ];
    $subtotal = (float)$customizeRequest['admin_price'];
} else {
    // Build cart items from DB
    $ids = implode(',', array_map('intval', array_keys($cart)));
    $items = $db->query("
        SELECT p.id, p.name, p.price,
               d.name AS discount_name, d.type AS discount_type, d.value AS discount_value,
               (SELECT image_url FROM product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) AS primary_image
        FROM products p
        LEFT JOIN discounts d ON p.discount_id = d.id
        WHERE p.id IN ($ids)
    ")->fetchAll();
    foreach ($items as $item) {
        $qty = $cart[$item['id']]['qty'];
        $item['qty'] = $qty;
        $unitPrice = (float)$item['price'];
        if ($item['discount_value']) {
            $item['discount_name_display'] = $item['discount_name'];
            if ($item['discount_type'] === 'percentage') {
                $unitPrice = $unitPrice * (1 - $item['discount_value'] / 100);
            } else {
                $unitPrice = max(0, $unitPrice - $item['discount_value']);
            }
        }
        $item['unit_price'] = $unitPrice;
        $item['item_total'] = $unitPrice * $qty;
        $subtotal += $item['item_total'];
        $cartDetails[] = $item;
    }

    // First-order discount
    $orderCount = $db->prepare("SELECT COUNT(*) FROM orders WHERE user_id=?");
    $orderCount->execute([$_SESSION['user_id']]);
    $isFirstOrder = $orderCount->fetchColumn() == 0;
    $firstOrderDiscount = $isFirstOrder ? $subtotal * 0.10 : 0;
    $totalSavings = $subtotal ? array_sum(array_map(fn($i) => ($i['price'] * $i['qty']) - $i['item_total'], $cartDetails)) : 0;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout — Sweet Heaven Bakery</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght=300;400;500;600;700&display=swap"
        rel="stylesheet">
    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>

<body class="bg-gray-50">
    <?php require_once __DIR__ . '/../includes/header.php'; ?>

    <div class="max-w-6xl mx-auto px-6 py-10">
        <h1 class="text-3xl font-bold text-gray-800 mb-8">Checkout</h1>

        <?php if ($error): ?>
            <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">⚠️
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="checkoutForm" enctype="multipart/form-data">
            <?php if ($customizeRequest): ?>
                <input type="hidden" name="customize_id" value="<?= $customizeRequest['id'] ?>">
            <?php endif; ?>
            <div class="grid lg:grid-cols-3 gap-8">

                <!-- Left: Checkout Form -->
                <div class="lg:col-span-2 space-y-6">

                    <!-- Delivery Info -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h2 class="font-bold text-gray-800 text-lg mb-5 flex items-center gap-2">
                            <span
                                class="w-7 h-7 bg-rose-500 text-white rounded-full flex items-center justify-center text-sm font-bold">1</span>
                            Delivery Information
                        </h2>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Full Name *</label>
                                <input type="text" name="full_name" required
                                    value="<?= htmlspecialchars($user['name'] ?? '') ?>"
                                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Phone Number *</label>
                                <input type="tel" name="phone" required placeholder="09 XXXX XXXXX"
                                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                                <input type="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" disabled
                                    class="w-full px-4 py-3 rounded-xl border border-gray-100 bg-gray-50 text-gray-400 text-sm">
                            </div>
                            <div class="col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Shipping Address *</label>
                                <textarea name="shipping_address" required rows="3"
                                    placeholder="Street, Township, City..."
                                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm resize-none"></textarea>
                            </div>
                            <div class="col-span-2">
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Special Request /
                                    Note</label>
                                <input type="text" name="request_note" placeholder="Any special instructions..."
                                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
                            </div>
                        </div>
                    </div>

                    <!-- Shipping Method -->
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                        <h2 class="font-bold text-gray-800 text-lg mb-5 flex items-center gap-2">
                            <span
                                class="w-7 h-7 bg-rose-500 text-white rounded-full flex items-center justify-center text-sm font-bold">2</span>
                            Shipping Method
                        </h2>
                        <div class="grid sm:grid-cols-2 gap-4">
                            <label class="cursor-pointer">
                                <input type="radio" name="shipping_method" value="standard" checked
                                    class="sr-only peer">
                                <div
                                    class="border-2 border-gray-200 peer-checked:border-rose-400 peer-checked:bg-rose-50 rounded-2xl p-5 transition-all">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="font-bold text-gray-700">Standard</span>
                                        <span class="text-rose-500 font-bold">2,000 MMK</span>
                                    </div>
                                    <p class="text-xs text-gray-400">Delivered in 3–5 business days</p>
                                </div>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="shipping_method" value="express" class="sr-only peer">
                                <div
                                    class="border-2 border-gray-200 peer-checked:border-rose-400 peer-checked:bg-rose-50 rounded-2xl p-5 transition-all">
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
                            <span
                                class="w-7 h-7 bg-rose-500 text-white rounded-full flex items-center justify-center text-sm font-bold">3</span>
                            Payment Method
                        </h2>
                        <!-- <div class="grid sm:grid-cols-2 gap-4 mb-5">
                            <?php foreach ($paymentMethods as $index => $pm): ?>
                                <label class="cursor-pointer payment-method-label" data-method-id="<?= $pm['id'] ?>">
                                    <input type="radio" name="payment_method_id" value="<?= $pm['id'] ?>" <?= $index === 0 ? 'checked' : '' ?> required class="sr-only peer">
                                    <div
                                        class="border-2 border-gray-200 peer-checked:border-green-400 peer-checked:bg-rose-50 rounded-2xl p-5 transition-all">
                                        <p class="font-bold text-gray-700"><?= htmlspecialchars($pm['payment_name']) ?>
                                        </p>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div> -->
                        <div class="grid sm:grid-cols-2 gap-4 mb-5">
                            <?php foreach ($paymentMethods as $index => $pm):
                                // 1. Normalize the name or use an existing code/slug from your DB
                                $methodKey = strtolower(trim($pm['payment_name']));

                                // 2. Map your payment methods to specific Tailwind peer-checked classes
                                $colorClass = 'peer-checked:border-rose-400 peer-checked:bg-rose-50'; // Default fallback
                            
                                if (strpos($methodKey, 'kbz') !== false) {
                                    $colorClass = 'peer-checked:border-blue-800 peer-checked:bg-blue-100';
                                } elseif (strpos($methodKey, 'wave') !== false) {
                                    $colorClass = 'peer-checked:border-yellow-800 peer-checked:bg-yellow-100';
                                }
                                ?>
                                <label class="cursor-pointer payment-method-label" data-method-id="<?= $pm['id'] ?>">
                                    <input type="radio" name="payment_method_id" value="<?= $pm['id'] ?>" <?= $index === 0 ? 'checked' : '' ?> required class="sr-only peer">
                                    <!-- 3. Inject the dynamic $colorClass here -->
                                    <div class="border-2 border-gray-200 <?= $colorClass ?> rounded-2xl p-5 transition-all">
                                        <p class="font-bold text-slate-500">
                                            <?= htmlspecialchars($pm['payment_name']) ?>
                                        </p>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <!-- Payment Details (dynamic) -->
                        <div id="paymentDetails"
                            class="hidden bg-amber-50 border border-amber-200 rounded-2xl p-5 mb-5">
                            <p class="font-bold text-amber-800 mb-3">💳 Transfer to:</p>
                            <div id="paymentDetailsContent"></div>
                        </div>

                        <!-- Screenshot Upload -->
                        <div id="screenshotUploadSection" class="hidden">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Payment Screenshot *</label>
                            <p class="text-xs text-gray-400 mb-3">Please transfer the exact total amount and upload the
                                screenshot below.</p>
                            <div class="border-2 border-dashed border-gray-200 rounded-2xl p-6 text-center hover:border-rose-300 transition-colors cursor-pointer"
                                id="uploadDropzone">
                                <input type="file" name="payment_screenshot" id="paymentScreenshot"
                                    accept="image/jpeg,image/png,image/webp" required class="hidden">
                                <div id="uploadPlaceholder">
                                    <svg class="w-10 h-10 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <p class="text-sm text-gray-400">Click to upload payment screenshot</p>
                                    <p class="text-xs text-gray-300 mt-1">JPG, JPEG, PNG, WEBP</p>
                                </div>
                                <div id="uploadPreview" class="hidden relative">
                                    <img id="previewImage" class="max-h-48 mx-auto rounded-xl shadow-sm">
                                    <button type="button" id="removeScreenshot"
                                        class="absolute -top-2 -right-2 w-7 h-7 bg-red-500 text-white rounded-full text-sm font-bold hover:bg-red-600 transition-colors shadow-md">✕</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Order Summary -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sticky top-24">
                        <h3 class="font-bold text-gray-800 text-lg mb-5">Order Summary</h3>

                        <div class="space-y-4 mb-5 max-h-72 overflow-y-auto pr-1">
                            <?php foreach ($cartDetails as $item): ?>
                                <?php $imgSrc = $item['primary_image'] ? '/sweetheaven/' . $item['primary_image'] : '/sweetheaven/images/maincake.jpg'; ?>
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 rounded-xl overflow-hidden bg-rose-50 shrink-0">
                                        <img src="<?= htmlspecialchars($imgSrc) ?>" class="w-full h-full object-cover">
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-700 line-clamp-1">
                                            <?= htmlspecialchars($item['name']) ?>
                                        </p>
                                        <p class="text-xs text-gray-400">x<?= $item['qty'] ?>
                                            <?php if (!empty($item['discount_name_display'])): ?>
                                                <span class="text-green-600 font-semibold"> • <?= htmlspecialchars($item['discount_name_display']) ?></span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-bold text-gray-700"><?= number_format($item['item_total']) ?></p>
                                        <?php if (!empty($item['discount_name_display'])): ?>
                                            <p class="text-[10px] line-through text-gray-400"><?= number_format($item['price'] * $item['qty']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="border-t border-gray-100 pt-4 space-y-2 text-sm">
                            <div class="flex justify-between text-gray-500">
                                <span>Subtotal</span>
                                <span><?= number_format($subtotal) ?> MMK</span>
                            </div>
                            <?php if ($totalSavings > 0): ?>
                            <div class="flex justify-between text-green-600 font-medium">
                                <span>🤑 Product Discounts</span>
                                <span>-<?= number_format($totalSavings) ?> MMK</span>
                            </div>
                            <?php endif; ?>
                            <?php if ($firstOrderDiscount > 0): ?>
                            <div class="flex justify-between text-blue-600 font-medium">
                                <span>🎉 First Order Discount (10%)</span>
                                <span>-<?= number_format($firstOrderDiscount) ?> MMK</span>
                            </div>
                            <?php endif; ?>
                            <div class="flex justify-between text-gray-500">
                                <span>Shipping</span>
                                <span id="shippingDisplay">2,000 MMK</span>
                            </div>
                            <div
                                class="flex justify-between font-bold text-gray-800 text-base border-t border-gray-100 pt-2">
                                <span>Total</span>
                                <span id="totalDisplay"><?= number_format($subtotal - $firstOrderDiscount + 2000) ?> MMK</span>
                            </div>
                        </div>

                        <button type="submit"
                            class="mt-6 w-full bg-rose-500 hover:bg-rose-600 text-white font-bold py-4 rounded-2xl transition-colors shadow-sm shadow-rose-100 text-base">
                            Place Order
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>

    <script>
        const subtotal = <?= $subtotal ?>;
        const firstOrderDiscount = <?= $firstOrderDiscount ?>;
        const shippingFees = { standard: 2000, express: 5000 };
        const paymentMethods = <?= json_encode($paymentMethods) ?>;

        // ── Shipping method toggle ────────────────────────
        document.querySelectorAll('input[name="shipping_method"]').forEach(radio => {
            radio.addEventListener('change', () => {
                const fee = shippingFees[radio.value] || 2000;
                const total = subtotal - firstOrderDiscount + fee;
                document.getElementById('shippingDisplay').textContent = fee.toLocaleString('en') + ' MMK';
                document.getElementById('totalDisplay').textContent = total.toLocaleString('en') + ' MMK';
            });
        });

        // ── Payment method toggle ─────────────────────────
        function showPaymentDetails(methodId) {
            const details = document.getElementById('paymentDetails');
            const content = document.getElementById('paymentDetailsContent');
            const uploadSection = document.getElementById('screenshotUploadSection');
            const pm = paymentMethods.find(p => p.id == methodId);
            if (!pm) { details.classList.add('hidden'); uploadSection.classList.add('hidden'); return; }
            let html = `
                <p class="text-sm font-bold text-amber-800">${pm.payment_name}</p>
                <p class="text-sm text-amber-700 mt-1">Account Name: <strong>${pm.acc_name || 'N/A'}</strong></p>
                <p class="text-sm text-amber-700">Phone Number: <strong>${pm.acc_no || 'N/A'}</strong></p>
            `;
            if (pm.qr_image) {
                html += `<div class="mt-3 flex justify-center">
                    <img src="/sweetheaven/${pm.qr_image}" class="w-36 h-36 object-contain border border-amber-200 rounded-xl bg-white" alt="${pm.payment_name} QR">
                </div>`;
            }
            content.innerHTML = html;
            details.classList.remove('hidden');
            uploadSection.classList.remove('hidden');
        }

        document.querySelectorAll('input[name="payment_method_id"]').forEach(radio => {
            radio.addEventListener('change', () => showPaymentDetails(radio.value));
        });
        showPaymentDetails(document.querySelector('input[name="payment_method_id"]:checked')?.value);

        // ── Screenshot preview ────────────────────────────
        const fileInput = document.getElementById('paymentScreenshot');
        const dropzone = document.getElementById('uploadDropzone');
        const placeholder = document.getElementById('uploadPlaceholder');
        const preview = document.getElementById('uploadPreview');
        const previewImg = document.getElementById('previewImage');

        dropzone.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', () => {
            const file = fileInput.files[0];
            if (!file) { placeholder.classList.remove('hidden'); preview.classList.add('hidden'); return; }
            const reader = new FileReader();
            reader.onload = e => { previewImg.src = e.target.result; placeholder.classList.add('hidden'); preview.classList.remove('hidden'); };
            reader.readAsDataURL(file);
        });
        document.getElementById('removeScreenshot').addEventListener('click', (e) => {
            e.stopPropagation();
            fileInput.value = '';
            placeholder.classList.remove('hidden');
            preview.classList.add('hidden');
        });
    </script>
</body>

</html>