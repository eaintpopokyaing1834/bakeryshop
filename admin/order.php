<?php
require_once __DIR__ . '/../middleware/admin_check.php';
require_once __DIR__ . '/../config/db.php';

$db = getDB();
$message = $error = '';

// ── Handle AJAX Status Update ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_status'])) {
    $orderId  = (int)$_POST['order_id'];
    $newStatus = $_POST['status'];
    $allowed   = ['pending','processing','shipped','delivered','cancelled'];
    if (in_array($newStatus, $allowed)) {
        $stmt = $db->prepare("UPDATE orders SET status=? WHERE id=?");
        $stmt->execute([$newStatus, $orderId]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

// ── Filters ──────────────────────────────────────────
$statusFilter = $_GET['status'] ?? 'all';
$search       = trim($_GET['search'] ?? '');
$where        = [];
$params       = [];
if ($statusFilter !== 'all') { $where[] = "o.status = ?"; $params[] = $statusFilter; }
if ($search !== '') { $where[] = "(u.name LIKE ? OR o.id LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$orders = $db->prepare("
    SELECT o.*, u.name AS customer_name, u.email AS customer_email,
           pm.payment_name, p.status AS pay_status
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN payment p ON p.order_id = o.id
    LEFT JOIN payment_methods pm ON pm.id = p.payment_method_id
    $whereSQL
    ORDER BY o.order_date DESC
");
$orders->execute($params);
$orders = $orders->fetchAll();

$pageTitle = 'Order Management';
require_once __DIR__ . '/../includes/admin_header.php';

$statusColors = [
    'pending'    => 'bg-amber-100 text-amber-700 border-amber-200',
    'processing' => 'bg-blue-100 text-blue-700 border-blue-200',
    'shipped'    => 'bg-indigo-100 text-indigo-700 border-indigo-200',
    'delivered'  => 'bg-green-100 text-green-700 border-green-200',
    'cancelled'  => 'bg-red-100 text-red-700 border-red-200',
];
?>

<!-- Filters Bar -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-6">
    <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between">
        <div class="flex flex-wrap gap-2">
            <?php foreach (['all','pending','processing','shipped','delivered','cancelled'] as $s): ?>
            <a href="?status=<?= $s ?>&search=<?= urlencode($search) ?>"
               class="px-4 py-1.5 rounded-full text-sm font-medium transition-colors
               <?= $statusFilter === $s ? 'bg-rose-500 text-white shadow-md shadow-rose-100' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
               <?= ucfirst($s) ?>
            </a>
            <?php endforeach; ?>
        </div>
        <form method="GET" class="flex gap-2">
            <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
            <input type="search" name="search" placeholder="Search order or customer..."
                value="<?= htmlspecialchars($search) ?>"
                class="border border-gray-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300 w-60">
            <button class="bg-rose-500 text-white px-4 py-2 rounded-xl text-sm font-medium hover:bg-rose-600">Search</button>
        </form>
    </div>
</div>

<!-- Orders Table -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="font-bold text-gray-800">Orders <span class="text-gray-400 font-normal text-sm ml-2">(<?= count($orders) ?> total)</span></h3>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-4 text-left">Order ID</th>
                    <th class="px-6 py-4 text-left">Customer</th>
                    <th class="px-6 py-4 text-left">Total</th>
                    <th class="px-6 py-4 text-left">Shipping</th>
                    <th class="px-6 py-4 text-left">Payment</th>
                    <th class="px-6 py-4 text-left">Status</th>
                    <th class="px-6 py-4 text-left">Date</th>
                    <th class="px-6 py-4 text-left">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50" id="ordersTableBody">
            <?php if (empty($orders)): ?>
                <tr><td colspan="8" class="px-6 py-16 text-center text-gray-400">
                    <p class="text-4xl mb-3">📋</p>
                    No orders found
                </td></tr>
            <?php else: ?>
            <?php foreach ($orders as $order): ?>
            <tr class="hover:bg-gray-50/50 transition-colors" id="order-row-<?= $order['id'] ?>">
                <td class="px-6 py-4">
                    <button onclick="toggleItems(<?= $order['id'] ?>)"
                        class="font-mono text-rose-500 font-bold hover:underline text-sm">
                        #<?= str_pad($order['id'], 4, '0', STR_PAD_LEFT) ?>
                    </button>
                </td>
                <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 bg-rose-50 rounded-full flex items-center justify-center text-rose-500 font-bold text-sm">
                            <?= strtoupper(substr($order['customer_name'],0,1)) ?>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700"><?= htmlspecialchars($order['customer_name']) ?></p>
                            <p class="text-xs text-gray-400"><?= htmlspecialchars($order['customer_email']) ?></p>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4 font-semibold text-gray-700 text-sm"><?= number_format($order['total_amount']) ?> MMK</td>
                <td class="px-6 py-4 text-sm text-gray-600 capitalize"><?= $order['shipping_method'] ?></td>
                <td class="px-6 py-4 text-sm text-gray-600"><?= $order['payment_name'] ?? 'N/A' ?></td>
                <td class="px-6 py-4">
                    <select onchange="updateStatus(<?= $order['id'] ?>, this.value, this)"
                        class="text-xs font-semibold px-3 py-1.5 rounded-full border cursor-pointer focus:outline-none focus:ring-2 focus:ring-rose-300 transition-colors
                        <?= $statusColors[$order['status']] ?? 'bg-gray-100 text-gray-600' ?>">
                        <?php foreach (['pending','processing','shipped','delivered','cancelled'] as $s): ?>
                        <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td class="px-6 py-4 text-sm text-gray-400"><?= date('M j, Y', strtotime($order['order_date'])) ?></td>
                <td class="px-6 py-4">
                    <button onclick="toggleItems(<?= $order['id'] ?>)"
                        class="text-sm text-rose-500 hover:text-rose-600 font-medium flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        Items
                    </button>
                </td>
            </tr>
            <!-- Expandable items row -->
            <tr id="items-<?= $order['id'] ?>" class="hidden bg-rose-50/30">
                <td colspan="8" class="px-8 py-4">
                    <div class="order-items-content" data-order-id="<?= $order['id'] ?>">
                        <p class="text-gray-400 text-sm italic">Loading items...</p>
                    </div>
                    <?php if ($order['shipping_address']): ?>
                    <p class="text-xs text-gray-500 mt-2">
                        📍 <?= htmlspecialchars($order['shipping_address']) ?>
                        <?php if ($order['phone']): ?> | 📞 <?= htmlspecialchars($order['phone']) ?><?php endif; ?>
                    </p>
                    <?php endif; ?>
                    <?php if ($order['request_note']): ?>
                    <p class="text-xs text-gray-500 mt-1">📝 <?= htmlspecialchars($order['request_note']) ?></p>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function updateStatus(orderId, newStatus, selectEl) {
    const original = selectEl.dataset.original || selectEl.value;
    fetch('/sweetheaven/admin/order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `ajax_status=1&order_id=${orderId}&status=${newStatus}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const colors = {
                pending:    'bg-amber-100 text-amber-700 border-amber-200',
                processing: 'bg-blue-100 text-blue-700 border-blue-200',
                shipped:    'bg-indigo-100 text-indigo-700 border-indigo-200',
                delivered:  'bg-green-100 text-green-700 border-green-200',
                cancelled:  'bg-red-100 text-red-700 border-red-200'
            };
            selectEl.className = selectEl.className.replace(/bg-\w+-100 text-\w+-700 border-\w+-200/g, '');
            selectEl.className += ' ' + (colors[newStatus] || 'bg-gray-100 text-gray-600');
            selectEl.dataset.original = newStatus;
            showToast('Order status updated!');
        }
    });
}

function toggleItems(orderId) {
    const row     = document.getElementById(`items-${orderId}`);
    const content = row.querySelector('.order-items-content');
    row.classList.toggle('hidden');
    if (!row.classList.contains('hidden') && content.dataset.loaded !== '1') {
        content.dataset.loaded = '1';
        fetch(`/sweetheaven/api/order_items.php?order_id=${orderId}`)
            .then(r => r.json())
            .then(items => {
                if (!items.length) { content.innerHTML = '<p class="text-gray-400 text-sm">No items</p>'; return; }
                content.innerHTML = `<div class="flex flex-wrap gap-3">${items.map(i =>
                    `<div class="bg-white rounded-xl px-4 py-2 border border-stone-100 text-sm">
                        <span class="font-semibold text-gray-700">${i.product_name}</span>
                        <span class="text-gray-400 ml-2">x${i.quantity}</span>
                        <span class="text-rose-500 font-bold ml-2">${Number(i.price * i.quantity).toLocaleString()} MMK</span>
                    </div>`
                ).join('')}</div>`;
            });
    }
}

function showToast(msg) {
    const t = document.createElement('div');
    t.textContent = msg;
    t.className = 'fixed bottom-6 right-6 bg-stone-800 text-white px-5 py-3 rounded-xl shadow-md text-sm font-medium z-50 transition-all duration-300';
    document.body.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, 3000);
}
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
