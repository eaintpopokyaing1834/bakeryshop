<?php
require_once __DIR__ . '/../middleware/admin_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/lang.php';

$db = getDB();
$message = $error = '';

// DB migration: ensure payment table has screenshot column and correct ENUM
try {
    $db->exec("ALTER TABLE payment ADD COLUMN screenshot VARCHAR(255) DEFAULT NULL AFTER payment_method_id");
} catch (Exception $e) {}
try {
    $db->exec("ALTER TABLE payment MODIFY COLUMN status ENUM('pending','approved','rejected') DEFAULT 'pending'");
} catch (Exception $e) {}

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

// Handle payment approve/reject (cashier only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_action'])) {
    if ($isAdmin) {
        echo json_encode(['success' => false, 'error' => 'View-only access']);
        exit;
    }
    $orderId  = (int)$_POST['order_id'];
    $newStatus = $_POST['payment_action'] === 'approve' ? 'approved' : 'rejected';
    $db->prepare("UPDATE payment SET status=? WHERE order_id=?")->execute([$newStatus, $orderId]);
    echo json_encode(['success' => true]);
    exit;
}

// ── Handle AJAX Status Update (cashier only) ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_status'])) {
    if ($isAdmin) {
        echo json_encode(['success' => false, 'error' => 'View-only access']);
        exit;
    }
    $orderId   = (int)$_POST['order_id'];
    $newStatus = $_POST['status'];
    $allowed   = ['pending','processing','shipped','delivered','cancelled'];

    if (in_array($newStatus, $allowed)) {
        // Fetch old status + owner before updating
        $order = $db->prepare("SELECT user_id, status FROM orders WHERE id=?");
        $order->execute([$orderId]);
        $order = $order->fetch();

        // Update order status
        $db->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$newStatus, $orderId]);

        if ($order && $order['status'] !== $newStatus) {
            // Build a friendly notification message with emoji
            $statusLabels = [
                'pending'    => __('order_notif_pending'),
                'processing' => __('order_notif_confirmed'),
                'shipped'    => __('order_notif_shipped'),
                'delivered'  => __('order_notif_delivered'),
                'cancelled'  => __('order_notif_cancelled'),
            ];
            $label   = $statusLabels[$newStatus] ?? "status changed to $newStatus";
            $orderNo = '#' . str_pad($orderId, 4, '0', STR_PAD_LEFT);
            $message = sprintf(__('order_notif_msg'), $orderNo, $label);

            $db->prepare("
                INSERT INTO notifications (user_id, order_id, type, message, is_seen)
                VALUES (?, ?, 'order_status', ?, 0)
            ")->execute([$order['user_id'], $orderId, $message]);
        }

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

// Count for pagination
$countStmt = $db->prepare("
    SELECT COUNT(*)
    FROM orders o
    JOIN users u ON o.user_id = u.id
    $whereSQL
");
$countStmt->execute($params);
$totalOrders = (int)$countStmt->fetchColumn();

$perPage     = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$totalPages  = max(1, (int)ceil($totalOrders / $perPage));
$page = min($page, $totalPages);
$offset      = ($page - 1) * $perPage;

$stmt = $db->prepare("
    SELECT o.*, u.name AS customer_name, u.email AS customer_email,
           pm.payment_name, p.status AS pay_status, p.screenshot
    FROM orders o
    JOIN users u ON o.user_id = u.id
    LEFT JOIN payment p ON p.order_id = o.id
    LEFT JOIN payment_methods pm ON pm.id = p.payment_method_id
    $whereSQL
    ORDER BY o.order_date DESC
    LIMIT $perPage OFFSET $offset
");
foreach ($params as $i => $val) {
    $stmt->bindValue($i + 1, $val);
}
$stmt->execute();
$orders = $stmt->fetchAll();

$pageTitle = __('order_page_title');
require_once __DIR__ . '/../includes/admin_header.php';

$statusColors = [
    'pending'    => 'bg-amber-100 text-amber-700 border-amber-200',
    'processing' => 'bg-blue-100 text-blue-700 border-blue-200',
    'shipped'    => 'bg-indigo-100 text-indigo-700 border-indigo-200',
    'delivered'  => 'bg-green-100 text-green-700 border-green-200',
    'cancelled'  => 'bg-red-100 text-red-700 border-red-200',
];
?>
<div class="px-4 mb-5">
<div class="admin-card-static p-5">
    <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between">
        <div class="flex flex-wrap gap-2">
            <?php foreach (['all','pending','processing','shipped','delivered','cancelled'] as $s): ?>
            <a href="?status=<?= $s ?>&search=<?= urlencode($search) ?>"
               class="filter-pill <?= $statusFilter === $s ? 'filter-pill-active' : 'filter-pill-default' ?>">
               <?= $s === 'all' ? __('admin_all') : ucfirst(__("status_$s")) ?>
            </a>
            <?php endforeach; ?>
        </div>
        <form method="GET" class="flex items-center gap-2">
            <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
            <div class="relative">
                <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="search" name="search" placeholder="<?= __('order_search_ph') ?>"
                    value="<?= htmlspecialchars($search) ?>"
                    class="search-input">
            </div>
            <button type="submit" class="btn-primary" style="padding:9px 18px"><?= __('admin_search') ?></button>
        </form>
    </div>
</div>
</div>

<section class="px-4 pb-6">
<div class="section-card">
    <div class="section-card-header">
        <div>
            <h3><?= __('admin_nav_orders') ?></h3>
            <p class="sub"><?= $totalOrders ?> <?= __('admin_total') ?></p>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="admin-table">
            <thead>
                <tr>
                    <th><?= __('admin_order_id') ?></th>
                    <th><?= __('admin_customer') ?></th>
                    <th><?= __('admin_amount') ?></th>
                    <th><?= __('order_shipping') ?></th>
                    <th><?= __('order_payment') ?></th>
                    <th><?= __('admin_status') ?></th>
                    <th><?= __('admin_date') ?></th>
                    <th><?= __('admin_actions') ?></th>
                </tr>
            </thead>
            <tbody id="ordersTableBody">
            <?php if (empty($orders)): ?>
                <tr><td colspan="8">
                    <div class="empty-state"><span class="empty-state-icon">📋</span><p class="empty-state-text"><?= __('order_no_orders') ?></p></div>
                </td></tr>
            <?php else: ?>
            <?php foreach ($orders as $order): ?>
            <tr id="order-row-<?= $order['id'] ?>">
                <td>
                    <button onclick="toggleItems(<?= $order['id'] ?>)"
                        class="font-mono text-sm font-bold text-rose-500 hover:underline">
                        #<?= str_pad($order['id'], 4, '0', STR_PAD_LEFT) ?>
                    </button>
                </td>
                <td>
                    <div class="flex items-center gap-2.5">
                        <div class="user-avatar"><?= strtoupper(substr($order['customer_name'],0,1)) ?></div>
                        <div>
                            <p class="text-sm font-semibold text-gray-700"><?= htmlspecialchars($order['customer_name']) ?></p>
                            <p class="text-xs text-gray-400"><?= htmlspecialchars($order['customer_email']) ?></p>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="text-sm font-bold text-gray-800"><?= formatPrice($order['total_amount']) ?></span>
                </td>
                <td>
                    <span class="text-sm text-gray-600"><?= match($order['shipping_method']) {
                        'standard' => __('order_ship_standard'),
                        'express'  => __('order_ship_express'),
                        'pickup'   => __('order_ship_pickup'),
                        'free'     => __('order_ship_free'),
                        default    => ucfirst($order['shipping_method']),
                    } ?></span>
                </td>
                <td>
                    <p class="text-sm text-gray-600"><?= htmlspecialchars($order['payment_name'] ?? __('admin_n_a')) ?></p>
                    <?php if ($order['pay_status']): ?>
                    <span class="status-badge badge-<?= $order['pay_status'] ?> mt-1">
                        <?= ucfirst(__("status_{$order['pay_status']}")) ?>
                    </span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($isAdmin): ?>
                        <span class="status-badge badge-<?= $order['status'] ?>">
                            <?= ucfirst(__("status_{$order['status']}")) ?>
                        </span>
                    <?php else: ?>
                        <select onchange="updateStatus(<?= $order['id'] ?>, this.value, this)"
                            class="text-xs font-semibold px-3 py-1.5 rounded-full border cursor-pointer focus:outline-none focus:ring-2 focus:ring-rose-300 transition-colors
                            <?= $statusColors[$order['status']] ?? 'bg-gray-100 text-gray-600' ?>"
                            <?= (!empty($order['pay_status']) && $order['pay_status'] === 'pending') ? 'disabled' : '' ?>>
                            <?php foreach (['pending','processing','shipped','delivered','cancelled'] as $s): ?>
                                <?php 
                                    $disabled = false;
                                    if (!empty($order['pay_status'])) {
                                        if ($order['pay_status'] === 'pending' && $s !== 'pending') $disabled = true;
                                        if ($order['pay_status'] === 'approved' && in_array($s, ['pending', 'cancelled'])) $disabled = true;
                                        if ($order['pay_status'] === 'rejected' && $s !== 'cancelled') $disabled = true;
                                    }
                                ?>
                                <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?> <?= $disabled ? 'disabled' : '' ?>><?= ucfirst(__("status_$s")) ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="text-sm text-gray-400 whitespace-nowrap"><?= date('M j, Y', strtotime($order['order_date'])) ?></span>
                </td>
                <td>
                    <button onclick="toggleItems(<?= $order['id'] ?>)"
                        class="flex items-center gap-1.5 text-xs font-semibold text-rose-500 hover:text-rose-600 bg-rose-50 hover:bg-rose-100 px-3 py-1.5 rounded-lg transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        <?= __('order_items') ?>
                    </button>
                </td>
            </tr>
            <tr id="items-<?= $order['id'] ?>" class="hidden" style="background: linear-gradient(135deg,#fff8f8,#fef2f2)">
                <td colspan="8" class="px-8 py-4">
                    <div class="order-items-content" data-order-id="<?= $order['id'] ?>">
                        <p class="text-gray-400 text-sm italic"><?= __('order_loading_items') ?></p>
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
                    
                    <?php if ($order['payment_name']): ?>
                    <div class="mt-3 pt-3 border-t border-stone-200">
                        <p class="text-xs font-semibold text-stone-600 mb-2">💳 <?= __('order_payment_details') ?></p>
                        <p class="text-xs text-gray-500"><?= __('order_method') ?> <?= htmlspecialchars($order['payment_name']) ?></p>
                        
                        <?php if (!empty($order['screenshot'])): ?>
                        <div class="mt-2">
                            <p class="text-xs text-gray-500 mb-1"><?= __('order_receipt_screenshot') ?></p>
                            <a href="/sweetheaven/<?= htmlspecialchars($order['screenshot']) ?>" target="_blank">
                                <img src="/sweetheaven/<?= htmlspecialchars($order['screenshot']) ?>"
                                     class="w-24 h-24 object-cover rounded-lg border border-stone-200">
                            </a>
                        </div>
                        <?php endif; ?>

                        <div class="mt-2 flex gap-2" id="paymentActions-<?= $order['id'] ?>">
                            <?php if ($isAdmin): ?>
                                <?php if ($order['pay_status'] === 'approved'): ?>
                                    <span class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-green-100 text-green-700"><?= __('order_payment_approved') ?></span>
                                <?php elseif ($order['pay_status'] === 'rejected'): ?>
                                    <span class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-red-100 text-red-700"><?= __('order_payment_rejected') ?></span>
                                <?php elseif ($order['pay_status'] === 'pending'): ?>
                                    <span class="text-xs text-gray-400 italic"><?= __('order_awaiting_receipt') ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if ($order['pay_status'] === 'pending' && !empty($order['screenshot'])): ?>
                                <button onclick="updatePayment(<?= $order['id'] ?>, 'approve')"
                                    class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-emerald-100 text-emerald-700 hover:bg-emerald-200 transition-colors">
                                    <?= __('order_approve_payment') ?>
                                </button>
                                <button onclick="updatePayment(<?= $order['id'] ?>, 'reject')"
                                    class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-red-100 text-red-700 hover:bg-red-200 transition-colors">
                                    <?= __('order_reject_payment') ?>
                                </button>
                                <?php elseif ($order['pay_status'] === 'approved'): ?>
                                <span class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-green-100 text-green-700"><?= __('order_payment_approved') ?></span>
                                <?php elseif ($order['pay_status'] === 'rejected'): ?>
                                <span class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-red-100 text-red-700"><?= __('order_payment_rejected') ?></span>
                                <?php else: ?>
                                <span class="text-xs text-gray-400 italic"><?= __('order_awaiting_receipt') ?></span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="pagination-wrap">
        <p class="pagination-info"><?= sprintf(__('admin_page_of'), $page, $totalPages) ?></p>
        <div class="pagination-pills">
            <?php if ($page > 1): ?>
            <a href="?status=<?= urlencode($statusFilter) ?>&search=<?= urlencode($search) ?>&page=<?= $page - 1 ?>" class="pg-btn pg-btn-nav">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                <?= __('admin_prev') ?>
            </a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?status=<?= urlencode($statusFilter) ?>&search=<?= urlencode($search) ?>&page=<?= $i ?>" class="pg-btn <?= $i === $page ? 'pg-btn-active' : 'pg-btn-default' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
            <a href="?status=<?= urlencode($statusFilter) ?>&search=<?= urlencode($search) ?>&page=<?= $page + 1 ?>" class="pg-btn pg-btn-nav">
                <?= __('admin_next') ?>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
</section>

<script>
function updatePayment(orderId, action) {
    fetch('/sweetheaven/admin/order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `payment_action=${action}&order_id=${orderId}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(action === 'approve' ? '<?= __("order_toast_approved") ?>' : '<?= __("order_toast_rejected") ?>');
            document.getElementById('paymentActions-' + orderId).innerHTML =
                `<span class="text-xs font-semibold px-3 py-1.5 rounded-lg ${action === 'approve' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}">${action === 'approve' ? '<?= __("order_payment_approved") ?>' : '<?= __("order_payment_rejected") ?>'}</span>`;
            // Update the badge in the main table too
            const row = document.querySelector(`#order-row-${orderId} td:nth-child(5) span`);
            if (row) {
                row.textContent = action === 'approve' ? '<?= __("status_approved") ?>' : '<?= __("status_rejected") ?>';
                row.className = `text-xs font-semibold px-2 py-0.5 rounded-full inline-block mt-1 ${action === 'approve' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`;
            }

            // Update the status dropdown dynamically
            const selectEl = document.querySelector(`select[onchange*="updateStatus(${orderId}"]`);
            if (selectEl) {
                selectEl.disabled = false;
                Array.from(selectEl.options).forEach(opt => {
                    if (action === 'approve') {
                        opt.disabled = (opt.value === 'cancelled' || opt.value === 'pending');
                    } else if (action === 'reject') {
                        opt.disabled = (opt.value !== 'cancelled');
                    }
                });
                
                // Auto-update to valid status if current is disabled
                if (action === 'approve' && selectEl.value === 'pending') {
                    selectEl.value = 'processing';
                    updateStatus(orderId, 'processing', selectEl);
                } else if (action === 'reject' && selectEl.value !== 'cancelled') {
                    selectEl.value = 'cancelled';
                    updateStatus(orderId, 'cancelled', selectEl);
                }
            }
        }
    });
}

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
            showToast('<?= __("order_toast_updated") ?>');
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
                if (!items.length) { content.innerHTML = '<p class="text-gray-400 text-sm"><?= __("order_no_items") ?></p>'; return; }
                content.innerHTML = `<div class="flex flex-wrap gap-3">${items.map(i =>
                    `<div class="bg-white rounded-xl px-4 py-2 border border-stone-100 text-sm">
                        <span class="font-semibold text-gray-700">${i.product_name}</span>
                        <span class="text-gray-400 ml-2">x${i.quantity}</span>
                        <span class="text-rose-500 font-bold ml-2">${Number(i.price * i.quantity).toLocaleString()} <?= __('admin_mmk') ?></span>
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

// ── Highlight order from reports.php ?highlight=X ────
(function() {
    const params = new URLSearchParams(window.location.search);
    const highlightId = params.get('highlight');
    if (!highlightId) return;
    const row = document.getElementById('order-row-' + highlightId);
    if (!row) return;
    // Scroll into view
    setTimeout(() => {
        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
        // Flash highlight ring
        row.style.transition = 'box-shadow 0.3s ease, background 0.3s ease';
        row.style.background = '#fefce8';
        row.style.boxShadow = 'inset 0 0 0 2px #f59e0b';
        setTimeout(() => {
            row.style.background = '';
            row.style.boxShadow = '';
        }, 2500);
    }, 200);
})();

</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
