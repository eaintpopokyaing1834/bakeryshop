<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../middleware/customer_check.php';
require_once __DIR__ . '/../config/db.php';
$db = getDB();

$cart = $_SESSION['cart'] ?? [];
$cartProducts = [];
$subtotal = 0;

if (!empty($cart)) {
    $ids  = implode(',', array_map('intval', array_keys($cart)));
    $rows = $db->query("
        SELECT p.id, p.name, p.price, p.stock,
               (SELECT image_url FROM product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) AS primary_image
        FROM products p WHERE p.id IN ($ids)
    ")->fetchAll();
    foreach ($rows as $row) {
        $qty  = $cart[$row['id']]['qty'];
        $row['qty'] = $qty;
        $row['item_total'] = $row['price'] * $qty;
        $subtotal += $row['item_total'];
        $cartProducts[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Cart — Sweet Heaven Bakery</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>* { font-family: 'Poppins', sans-serif; }</style>
</head>
<body class="bg-gray-50">
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="max-w-6xl mx-auto px-6 py-10">
    <div class="flex items-center gap-3 mb-8">
        <h1 class="text-3xl font-bold text-gray-800">My Cart</h1>
        <span id="cartCountText" class="bg-rose-50 text-rose-600 text-sm font-semibold px-3 py-1 rounded-full"><?= count($cartProducts) ?> item<?= count($cartProducts) !== 1 ? 's' : '' ?></span>
    </div>

    <?php if (empty($cartProducts)): ?>
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-20 text-center">
        <p class="text-6xl mb-6">🛒</p>
        <h2 class="text-2xl font-bold text-gray-700 mb-3">Your cart is empty</h2>
        <p class="text-gray-400 mb-8">Looks like you haven't added anything yet. Let's fix that!</p>
        <a href="/sweetheaven/user/products.php" class="bg-rose-500 hover:bg-rose-600 text-white px-8 py-4 rounded-2xl font-semibold transition-colors shadow-sm shadow-rose-100">
            Start Shopping
        </a>
    </div>

    <?php else: ?>
    <div class="grid lg:grid-cols-3 gap-8">

        <!-- Cart Items -->
        <div class="lg:col-span-2 space-y-4" id="cartItemsContainer">
            <?php foreach ($cartProducts as $item): ?>
            <?php $imgSrc = $item['primary_image'] ? '/sweetheaven/'.$item['primary_image'] : '/sweetheaven/images/maincake.jpg'; ?>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex gap-5 items-center" id="cart-item-<?= $item['id'] ?>">
                <div class="w-20 h-20 rounded-xl overflow-hidden bg-rose-50 shrink-0">
                    <img src="<?= htmlspecialchars($imgSrc) ?>" class="w-full h-full object-cover" alt="<?= htmlspecialchars($item['name']) ?>">
                </div>

                <div class="flex-1 min-w-0">
                    <h3 class="font-bold text-gray-800 mb-1 line-clamp-1"><?= htmlspecialchars($item['name']) ?></h3>
                    <p class="text-rose-500 font-semibold text-sm"><?= number_format($item['price']) ?> MMK / each</p>
                </div>

                <div class="flex items-center gap-2">
                    <button onclick="updateQty(<?= $item['id'] ?>, <?= $item['qty'] - 1 ?>)"
                        class="w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-600 font-bold flex items-center justify-center transition-colors">−</button>
                    <span class="w-10 text-center font-bold text-gray-800" id="qty-<?= $item['id'] ?>"><?= $item['qty'] ?></span>
                    <button onclick="updateQty(<?= $item['id'] ?>, <?= $item['qty'] + 1 ?>)"
                        class="w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-600 font-bold flex items-center justify-center transition-colors">+</button>
                </div>

                <div class="text-right min-w-[100px]">
                    <p class="font-bold text-gray-800" id="subtotal-<?= $item['id'] ?>"><?= number_format($item['item_total']) ?></p>
                    <p class="text-xs text-gray-400">MMK</p>
                </div>

                <button onclick="removeItem(<?= $item['id'] ?>)"
                    class="text-gray-300 hover:text-red-500 transition-colors ml-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Order Summary -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sticky top-24">
                <h3 class="font-bold text-gray-800 text-lg mb-6">Order Summary</h3>

                <div class="space-y-3 text-sm mb-6">
                    <div class="flex justify-between text-gray-600">
                        <span>Subtotal</span>
                        <span id="totalDisplay"><?= number_format($subtotal) ?> MMK</span>
                    </div>
                    <div class="flex justify-between text-gray-600">
                        <span>Shipping</span>
                        <span class="text-green-600 font-medium">Calculated at checkout</span>
                    </div>
                    <div class="border-t border-gray-100 pt-3 flex justify-between font-bold text-gray-800 text-base">
                        <span>Total</span>
                        <span id="grandTotal"><?= number_format($subtotal) ?> MMK</span>
                    </div>
                </div>

                <?php if (isset($_SESSION['user_id'])): ?>
                <a href="/sweetheaven/user/checkout.php"
                   class="block w-full bg-rose-500 hover:bg-rose-600 text-white font-bold py-4 rounded-2xl text-center transition-colors shadow-sm shadow-rose-100">
                    Proceed to Checkout →
                </a>
                <?php else: ?>
                <a href="/sweetheaven/auth/login.php"
                   class="block w-full bg-rose-500 hover:bg-rose-600 text-white font-bold py-4 rounded-2xl text-center transition-colors">
                    Login to Checkout
                </a>
                <?php endif; ?>

                <a href="/sweetheaven/user/products.php" class="block text-center text-sm text-gray-400 hover:text-rose-500 mt-4 transition-colors">
                    ← Continue Shopping
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
function updateQty(productId, newQty) {
    fetch('/sweetheaven/api/cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=update&product_id=${productId}&qty=${newQty}`
    }).then(r=>r.json()).then(data=>{
        if (!data.success) return;
        if (newQty <= 0) {
            document.getElementById(`cart-item-${productId}`)?.remove();
            updateCartCountText();
        } else {
            const qtyEl = document.getElementById(`qty-${productId}`);
            const subEl = document.getElementById(`subtotal-${productId}`);
            if (qtyEl) qtyEl.textContent = newQty;
            if (subEl) subEl.textContent = Number(data.subtotal).toLocaleString('en');
        }
        document.getElementById('totalDisplay').textContent = Number(data.total).toLocaleString('en') + ' MMK';
        document.getElementById('grandTotal').textContent   = Number(data.total).toLocaleString('en') + ' MMK';
        const badge = document.getElementById('cartBadge');
        if (badge) { badge.textContent = data.cart_count; if(data.cart_count===0)badge.classList.add('hidden'); }
    });
}

function removeItem(productId) {
    fetch('/sweetheaven/api/cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=remove&product_id=${productId}`
    }).then(r=>r.json()).then(data=>{
        if (data.success) {
            document.getElementById(`cart-item-${productId}`)?.remove();
            updateCartCountText();
            const badge = document.getElementById('cartBadge');
            if (badge) { badge.textContent = data.cart_count; if(data.cart_count===0)badge.classList.add('hidden'); }
            if (data.cart_count === 0) location.reload();
        }
    });
}

function updateCartCountText() {
    const el = document.getElementById('cartCountText');
    if (!el) return;
    const count = document.querySelectorAll('#cartItemsContainer > div').length;
    el.textContent = count + ' item' + (count !== 1 ? 's' : '');
}
</script>
</body>
</html>
