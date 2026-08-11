<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/lang.php';
require_once __DIR__ . '/../middleware/customer_check.php';
require_once __DIR__ . '/../config/db.php';
$db = getDB();

$cart = $_SESSION['cart'] ?? [];
$cartProducts = [];
$subtotal = 0;

if (!empty($cart)) {
    $ids  = implode(',', array_map('intval', array_keys($cart)));
    $rows = $db->query("
        SELECT p.id, p.name, p.name_my, p.price, p.stock,
               d.name  AS discount_name,
               d.type  AS discount_type,
               d.value AS discount_value,
               (SELECT image_url FROM product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) AS primary_image
        FROM products p
        LEFT JOIN discounts d ON p.discount_id = d.id AND d.status = 1
        WHERE p.id IN ($ids)
    ")->fetchAll();
    foreach ($rows as $row) {
        $qty  = $cart[$row['id']]['qty'];
        $row['qty'] = $qty;
        // Apply per-product discount
        $unitPrice = (float)$row['price'];
        if (!empty($row['discount_value'])) {
            if ($row['discount_type'] === 'percentage') {
                $unitPrice = $unitPrice * (1 - $row['discount_value'] / 100);
            } else {
                $unitPrice = max(0, $unitPrice - $row['discount_value']);
            }
        }
        $row['savings_per_unit'] = (float)$row['price'] - $unitPrice; // savings per single unit
        $row['unit_price']        = $unitPrice;
        $row['item_total']        = (float)$row['price'] * $qty;  // always original price × qty
        $subtotal                += $row['item_total'];  // $subtotal = discounted grand total
        $cartProducts[]           = $row;
    }
}

// Original price total (before any product discounts)
$originalSubtotal = array_sum(array_map(
    fn($i) => (float)$i['price'] * $i['qty'],
    $cartProducts
));

// Product-level savings = original − discounted, across all qty
$totalSavings = array_sum(array_map(
    fn($i) => $i['savings_per_unit'] * $i['qty'],
    $cartProducts
));
// $subtotal already equals $originalSubtotal - $totalSavings

// First-order 5 % discount applies on the original subtotal
$firstOrderDiscount = 0;
if (!empty($cartProducts) && isset($_SESSION['user_id'])) {
    $ocStmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE user_id=?");
    $ocStmt->execute([$_SESSION['user_id']]);
    if ($ocStmt->fetchColumn() == 0) {
        $firstOrderDiscount = $originalSubtotal * 0.05;
    }
}
$grandTotal = $originalSubtotal - $totalSavings - $firstOrderDiscount;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('cart_title') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>* { font-family: 'Poppins', sans-serif; }</style>
</head>
<body class="bg-gray-50">
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="max-w-6xl mx-auto px-6 py-10">
    <div class="flex items-center gap-3 mb-8">
        <h1 class="text-3xl font-bold text-gray-800"><?= __('cart_heading') ?></h1>
        <span id="cartCountText" class="bg-rose-50 text-rose-600 text-sm font-semibold px-3 py-1 rounded-full"><?= __('cart_items', localizeNumber(count($cartProducts)), count($cartProducts) !== 1 ? 's' : '') ?></span>
    </div>

    <?php if (empty($cartProducts)): ?>
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-20 text-center">
        <p class="text-6xl mb-6">🛒</p>
        <h2 class="text-2xl font-bold text-gray-700 mb-3"><?= __('cart_empty_title') ?></h2>
        <p class="text-gray-400 mb-8"><?= __('cart_empty_desc') ?></p>
        <a href="/sweetheaven/user/products.php" class="bg-rose-500 hover:bg-rose-600 text-white px-8 py-4 rounded-2xl font-semibold transition-colors shadow-sm shadow-rose-100">
            <?= __('cart_start_shopping') ?>
        </a>
    </div>

    <?php else: ?>
    <div class="grid lg:grid-cols-3 gap-8">

        <!-- Cart Items -->
        <div class="lg:col-span-2 space-y-4" id="cartItemsContainer">
            <?php foreach ($cartProducts as $item): ?>
            <?php $imgSrc = $item['primary_image'] ? '/sweetheaven/'.$item['primary_image'] : '/sweetheaven/images/maincake.jpg'; ?>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 sm:p-5 flex flex-wrap sm:flex-nowrap gap-4 sm:gap-5 items-center relative"
                 id="cart-item-<?= $item['id'] ?>"
                 data-savings-per-unit="<?= $item['savings_per_unit'] ?>">
                
                <div class="flex items-center gap-4 w-full sm:w-auto sm:flex-1">
                    <div class="w-20 h-20 rounded-xl overflow-hidden bg-rose-50 shrink-0">
                        <img src="<?= htmlspecialchars($imgSrc) ?>" class="w-full h-full object-cover" alt="<?= htmlspecialchars($item['name']) ?>">
                    </div>

                    <div class="flex-1 min-w-0 pr-6 sm:pr-0">
                        <h3 class="font-bold text-gray-800 mb-1 line-clamp-2 sm:line-clamp-1"><?= htmlspecialchars(getLocalizedProductName($item)) ?></h3>
                        <p class="text-rose-500 font-semibold text-sm"><?= formatPrice($item['price']) ?> <?= __('cart_each') ?></p>
                    </div>
                </div>

                <div class="flex items-center justify-between w-full sm:w-auto gap-4">
                    <div class="flex items-center gap-2">
                        <button onclick="updateQty(<?= $item['id'] ?>, parseInt(document.getElementById('qty-<?= $item['id'] ?>').dataset.qty) - 1, <?= $item['stock'] ?>)"
                            class="w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-600 font-bold flex items-center justify-center transition-colors">−</button>
                        <span class="w-10 text-center font-bold text-gray-800" id="qty-<?= $item['id'] ?>" data-qty="<?= $item['qty'] ?>"><?= localizeNumber($item['qty']) ?></span>
                        <button onclick="updateQty(<?= $item['id'] ?>, parseInt(document.getElementById('qty-<?= $item['id'] ?>').dataset.qty) + 1, <?= $item['stock'] ?>)"
                            class="w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-600 font-bold flex items-center justify-center transition-colors">+</button>
                    </div>

                    <div class="flex justify-end items-center gap-1 text-right min-w-[100px]">
                        <p class="font-bold text-gray-800" id="subtotal-<?= $item['id'] ?>"><?= formatPrice($item['item_total']) ?></p>
                    </div>
                </div>

                <button onclick="removeItem(<?= $item['id'] ?>)"
                    class="absolute top-4 right-4 sm:relative sm:top-auto sm:right-auto text-gray-300 hover:text-red-500 transition-colors sm:ml-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Order Summary -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sticky top-24">
                <h3 class="font-bold text-gray-800 text-lg mb-5"><?= __('cart_order_summary') ?></h3>

                <!-- Product list -->
                <div class="space-y-4 mb-5 max-h-72 overflow-y-auto pr-1" id="summaryProductList">
                    <?php foreach ($cartProducts as $item):
                        $imgSrc = $item['primary_image'] ? '/sweetheaven/'.$item['primary_image'] : '/sweetheaven/images/maincake.jpg';
                        $discountLabel = '';
                        if (!empty($item['discount_value'])) {
                            $discountLabel = ' (' . htmlspecialchars(getLocalizedDiscountLabel($item)) . ')';
                        }
                    ?>
                    <div class="flex items-center gap-3" id="summary-item-<?= $item['id'] ?>"
                         data-unit-price="<?= $item['price'] ?>"
                         data-discount-label="<?= htmlspecialchars($discountLabel) ?>">
                        <div class="w-12 h-12 rounded-xl overflow-hidden bg-rose-50 shrink-0">
                            <img src="<?= htmlspecialchars($imgSrc) ?>" class="w-full h-full object-cover" alt="<?= htmlspecialchars(getLocalizedProductName($item)) ?>">
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-700 line-clamp-1"><?= htmlspecialchars(getLocalizedProductName($item)) ?></p>
                            <p class="text-xs text-gray-400">
                                <span id="summary-price-<?= $item['id'] ?>"><?= formatPrice($item['price']) ?> × <?= localizeNumber($item['qty']) ?></span><?= $discountLabel ?>
                            </p>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-sm font-bold text-gray-700" id="summary-total-<?= $item['id'] ?>"><?= formatPrice($item['item_total']) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Totals -->
                <div class="border-t border-gray-100 pt-4 space-y-2 text-sm">
                    <div class="flex justify-between text-gray-500">
                        <span><?= __('cart_subtotal') ?></span>
                        <span id="subtotalDisplay"><?= formatPrice($originalSubtotal) ?></span>
                    </div>

                    <?php if ($totalSavings > 0): ?>
                    <div class="flex justify-between text-green-600 font-medium" id="discountSavingsRow">
                        <span><?= __('checkout_product_discounts') ?></span>
                        <span id="discountDisplay">-<?= formatPrice($totalSavings) ?></span>
                    </div>
                    <?php else: ?>
                    <div class="flex justify-between text-green-600 font-medium hidden" id="discountSavingsRow">
                        <span><?= __('checkout_product_discounts') ?></span>
                        <span id="discountDisplay"></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($firstOrderDiscount > 0): ?>
                    <div class="flex justify-between text-blue-600 font-medium" id="firstOrderDiscountRow">
                        <span><?= __('checkout_first_order_discount') ?></span>
                        <span id="firstOrderDiscountDisplay">-<?= formatPrice($firstOrderDiscount) ?></span>
                    </div>
                    <?php else: ?>
                    <div class="flex justify-between text-blue-600 font-medium hidden" id="firstOrderDiscountRow">
                        <span><?= __('checkout_first_order_discount') ?></span>
                        <span id="firstOrderDiscountDisplay"></span>
                    </div>
                    <?php endif; ?>

                    <div class="flex justify-between text-gray-400 text-xs italic">
                        <span><?= __('cart_shipping') ?></span>
                        <span class="text-green-600 font-medium"><?= __('cart_shipping_calc') ?></span>
                    </div>

                    <div class="flex justify-between font-bold text-gray-800 text-base border-t border-gray-100 pt-2">
                        <span><?= __('cart_total') ?></span>
                        <span id="grandTotal"><?= formatPrice($grandTotal) ?></span>
                    </div>
                </div>

                <!-- Actions -->
                <div class="mt-6 space-y-3">
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="/sweetheaven/user/checkout.php"
                       id="proceedToCheckoutBtn"
                       class="block w-full bg-rose-500 hover:bg-rose-600 text-white font-bold py-4 rounded-2xl text-center transition-colors shadow-sm shadow-rose-100">
                        <?= __('cart_checkout_btn') ?>
                    </a>
                    <?php else: ?>
                    <a href="/sweetheaven/user/index.php?show_login=1"
                       class="block w-full bg-rose-500 hover:bg-rose-600 text-white font-bold py-4 rounded-2xl text-center transition-colors shadow-sm shadow-rose-100">
                        <?= __('cart_login_checkout') ?>
                    </a>
                    <?php endif; ?>

                    <a href="/sweetheaven/user/products.php" class="block text-center text-sm text-gray-400 hover:text-rose-500 transition-colors">
                        <?= __('cart_continue') ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
const _isFirstOrder = <?= $firstOrderDiscount > 0 ? 'true' : 'false' ?>;

// Recalculate total product-level savings from DOM data attributes
function recalcSavings() {
    let total = 0;
    document.querySelectorAll('#cartItemsContainer > div[data-savings-per-unit]').forEach(row => {
        const id  = row.id.replace('cart-item-', '');
        const qty = parseInt(document.getElementById('qty-' + id)?.dataset.qty) || 0;
        total    += (parseFloat(row.dataset.savingsPerUnit) || 0) * qty;
    });
    return total;
}

function updateSummary(originalTotal) {
    const savings           = recalcSavings();
    const discounted        = originalTotal - savings;
    const firstOrderDiscount = _isFirstOrder ? originalTotal * 0.05 : 0;
    const grand             = discounted - firstOrderDiscount;

    const subtotalEl  = document.getElementById('subtotalDisplay');
    const discountRow = document.getElementById('discountSavingsRow');
    const discountEl  = document.getElementById('discountDisplay');
    const firstOrderRow = document.getElementById('firstOrderDiscountRow');
    const firstOrderEl  = document.getElementById('firstOrderDiscountDisplay');
    const grandEl     = document.getElementById('grandTotal');

    if (subtotalEl)     subtotalEl.textContent     = formatPriceJS(originalTotal);
    if (discountEl)     discountEl.textContent     = '-' + formatPriceJS(Math.round(savings));
    if (discountRow)    discountRow.classList.toggle('hidden', savings <= 0);
    if (firstOrderEl)   firstOrderEl.textContent   = '-' + formatPriceJS(Math.round(firstOrderDiscount));
    if (firstOrderRow)  firstOrderRow.classList.toggle('hidden', firstOrderDiscount <= 0);
    if (grandEl)        grandEl.textContent        = formatPriceJS(Math.round(grand));
}

function updateQty(productId, newQty, maxStock) {
    if (typeof maxStock !== 'undefined' && newQty > maxStock) {
        const msg = '<?= addslashes(__('cart_err_stock_limit')) ?>'.replace('%s', maxStock);
        if (typeof showToast === 'function') {
            showToast(msg);
        } else {
            alert(msg);
        }
        return;
    }
    fetch('/sweetheaven/api/cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=update&product_id=${productId}&qty=${newQty}`
    }).then(r=>r.json()).then(data=>{
        if (!data.success) return;
        if (newQty <= 0) {
            document.getElementById(`cart-item-${productId}`)?.remove();
            document.getElementById(`summary-item-${productId}`)?.remove();
            updateCartCountText();
        } else {
            // Update left-side cart item
            const qtyEl = document.getElementById(`qty-${productId}`);
            const subEl = document.getElementById(`subtotal-${productId}`);
            if (qtyEl) {
                qtyEl.dataset.qty = newQty;
                qtyEl.textContent = window.localizeNumberJS ? window.localizeNumberJS(newQty) : newQty;
            }
            if (subEl) subEl.textContent = formatPriceJS(data.cart_item.price * newQty);

            // Update right-side order summary item
            const summaryItem = document.getElementById(`summary-item-${productId}`);
            if (summaryItem) {
                const unitPrice = parseFloat(summaryItem.dataset.unitPrice) || 0;
                const summaryPriceEl = document.getElementById(`summary-price-${productId}`);
                const summaryTotalEl = document.getElementById(`summary-total-${productId}`);
                if (summaryPriceEl) {
                    let summaryQty = window.localizeNumberJS ? window.localizeNumberJS(newQty) : newQty;
                    summaryPriceEl.textContent = `${formatPriceJS(unitPrice)} × ${summaryQty}`;
                }
                if (summaryTotalEl) summaryTotalEl.textContent = formatPriceJS(unitPrice * newQty);
            }
        }
        // data.total = sum of (original session price × qty) across all items
        updateSummary(data.total);
        const badge = document.getElementById('cartBadge');
        if (badge) { badge.textContent = data.cart_count; if(data.cart_count===0)badge.classList.add('hidden'); }
    });
}

function numberFormat(n) {
    return Math.round(n).toLocaleString('en');
}

function removeItem(productId) {
    fetch('/sweetheaven/api/cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=remove&product_id=${productId}`
    }).then(r=>r.json()).then(data=>{
        if (data.success) {
            document.getElementById(`cart-item-${productId}`)?.remove();
            document.getElementById(`summary-item-${productId}`)?.remove();
            updateCartCountText();
            updateSummary(data.total);
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
    let itemsStr = '<?= addslashes(__('cart_items', '%d', '%s')) ?>';
    let locCount = window.localizeNumberJS ? window.localizeNumberJS(count) : count;
    el.textContent = itemsStr.replace('%d', locCount).replace('%s', count !== 1 ? 's' : '');
}
</script>
</body>
</html>
