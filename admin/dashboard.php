<?php
require_once __DIR__ . '/../middleware/admin_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/lang.php';

$db = getDB();

// ── Metrics ──────────────────────────────────────────
$totalRevenue = $db->query("SELECT COALESCE(SUM(total_amount),0) AS rev FROM orders WHERE status != 'cancelled'")->fetchColumn();
$totalOrders  = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$totalUsers   = $db->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();
$totalProducts = $db->query("SELECT COUNT(*) FROM products")->fetchColumn();
$lowStock     = $db->query("SELECT COUNT(*) FROM products WHERE stock < 10")->fetchColumn();
$pendingOrders = $db->query("SELECT COUNT(*) FROM orders WHERE status='pending'")->fetchColumn();
$completedOrders = $db->query("SELECT COUNT(*) FROM orders WHERE status='delivered'")->fetchColumn();

// ── Best-Selling Products (top 5) ────────────────────
$bestSellingProducts = $db->query("
    SELECT p.name, SUM(oi.quantity) AS total_sold
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    WHERE o.status != 'cancelled'
    GROUP BY p.id
    ORDER BY total_sold DESC
    LIMIT 5
")->fetchAll();
$bestSellingLabels = array_column($bestSellingProducts, 'name');
$bestSellingData = array_map('intval', array_column($bestSellingProducts, 'total_sold'));

// ── Monthly Revenue (current year) ───────────────────
$monthlyRev = $db->query("
    SELECT MONTH(order_date) as month, COALESCE(SUM(total_amount),0) as revenue
    FROM orders
    WHERE YEAR(order_date) = YEAR(NOW()) AND status != 'cancelled'
    GROUP BY MONTH(order_date)
    ORDER BY month
")->fetchAll();

$monthLabels   = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$revenueData   = array_fill(0, 12, 0);
foreach ($monthlyRev as $row) $revenueData[(int)$row['month'] - 1] = (float)$row['revenue'];

// ── Orders by Status ─────────────────────────────────
$statusCounts = $db->query("
    SELECT status, COUNT(*) as cnt FROM orders GROUP BY status
")->fetchAll();
$statusLabels = $statusData = [];
foreach ($statusCounts as $s) {
    $statusLabels[] = ucfirst(__("status_{$s['status']}"));
    $statusData[]   = (int)$s['cnt'];
}

// ── Recent Orders ────────────────────────────────────
$recentOrders = $db->query("
    SELECT o.id, u.name, o.total_amount, o.status, o.order_date
    FROM orders o JOIN users u ON o.user_id = u.id
    ORDER BY o.order_date DESC LIMIT 8
")->fetchAll();

// ── Customer Reviews (max 2) ──────────────────────────
$customerReviews = $db->query("
    SELECT u.name, r.comment AS message, r.rating, r.created_at
    FROM reviews r
    JOIN users u ON r.user_id = u.id
    WHERE r.status='approved'
    ORDER BY r.created_at DESC LIMIT 2
")->fetchAll();

// ── Low Stock Products ────────────────────────────────
$lowStockProducts = $db->query("
    SELECT p.name, p.stock, c.name AS category
    FROM products p JOIN categories c ON p.category_id = c.id
    WHERE p.stock < 10
    ORDER BY p.stock ASC LIMIT 5
")->fetchAll();

$pageTitle = __('admin_nav_dashboard');
require_once __DIR__ . '/../includes/admin_header.php';

$statusColors = [
    'pending'   => 'badge-pending',
    'processing'=> 'badge-processing',
    'shipped'   => 'badge-shipped',
    'delivered' => 'badge-delivered',
    'cancelled' => 'badge-cancelled',
];
?>

<!-- ── Metric Cards Row 1 ──────────────────────────── -->
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-5 px-4">

    <div class="metric-card rose">
        <div class="flex items-start justify-between mb-4">
            <div class="metric-icon rose">
                <svg class="w-6 h-6 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <span class="text-xs font-semibold text-green-600 bg-green-50 px-2 py-1 rounded-full"><?= __('admin_revenue') ?></span>
        </div>
        <p class="text-2xl font-bold text-gray-900 tracking-tight"><?= number_format($totalRevenue) ?> <span class="text-sm font-medium text-gray-400"><?= __('admin_mmk') ?></span></p>
        <p class="text-xs text-gray-400 mt-1 font-medium"><?= __('admin_total_sales_revenue') ?></p>
    </div>

    <div class="metric-card blue">
        <div class="flex items-start justify-between mb-4">
            <div class="metric-icon blue">
                <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            </div>
            <span class="text-xs font-semibold text-amber-600 bg-amber-50 px-2 py-1 rounded-full"><?= $pendingOrders ?> <?= __('admin_pending') ?></span>
        </div>
        <p class="text-2xl font-bold text-gray-900 tracking-tight"><?= $totalOrders ?></p>
        <p class="text-xs text-gray-400 mt-1 font-medium"><?= __('admin_total_orders') ?></p>
    </div>

    <div class="metric-card purple">
        <div class="flex items-start justify-between mb-4">
            <div class="metric-icon purple">
                <svg class="w-6 h-6 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-900 tracking-tight"><?= $totalUsers ?></p>
        <p class="text-xs text-gray-400 mt-1 font-medium"><?= __('admin_registered_customers') ?></p>
    </div>

    <div class="metric-card <?= $lowStock > 0 ? 'red' : 'green' ?>">
        <div class="flex items-start justify-between mb-4">
            <div class="metric-icon <?= $lowStock > 0 ? 'red' : 'green' ?>">
                <svg class="w-6 h-6 <?= $lowStock > 0 ? 'text-red-500' : 'text-green-500' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
            <?php if ($lowStock > 0): ?>
            <span class="text-xs font-semibold text-red-600 bg-red-50 px-2 py-1 rounded-full animate-pulse"><?= __('admin_alert') ?></span>
            <?php endif; ?>
        </div>
        <p class="text-2xl font-bold text-gray-900 tracking-tight"><?= $lowStock ?></p>
        <p class="text-xs text-gray-400 mt-1 font-medium"><?= __('admin_low_stock_products') ?></p>
    </div>
</div>

<!-- ── Metric Cards Row 2 ──────────────────────────── -->
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-6 px-4">

    <div class="metric-card indigo">
        <div class="flex items-start justify-between mb-4">
            <div class="metric-icon indigo">
                <svg class="w-6 h-6 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-900 tracking-tight"><?= $totalProducts ?></p>
        <p class="text-xs text-gray-400 mt-1 font-medium"><?= __('admin_total_products') ?></p>
    </div>

    <div class="metric-card amber">
        <div class="flex items-start justify-between mb-4">
            <div class="metric-icon amber">
                <svg class="w-6 h-6 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-900 tracking-tight"><?= $pendingOrders ?></p>
        <p class="text-xs text-gray-400 mt-1 font-medium"><?= __('admin_pending_orders') ?></p>
    </div>

    <div class="metric-card green">
        <div class="flex items-start justify-between mb-4">
            <div class="metric-icon green">
                <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-900 tracking-tight"><?= $completedOrders ?></p>
        <p class="text-xs text-gray-400 mt-1 font-medium"><?= __('admin_completed_orders') ?></p>
    </div>

    <div class="metric-card cyan">
        <div class="flex items-start justify-between mb-4">
            <div class="metric-icon cyan">
                <svg class="w-6 h-6 text-cyan-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-900 tracking-tight"><?= $totalUsers ?></p>
        <p class="text-xs text-gray-400 mt-1 font-medium"><?= __('admin_total_customers') ?></p>
    </div>
</div>

<!-- ── Charts Row ─────────────────────────────────── -->
<div class="grid grid-cols-1 xl:grid-cols-3 gap-5 mb-5 px-4">

    <!-- Revenue Chart -->
    <div class="chart-card xl:col-span-2">
        <div class="chart-card-header">
            <div>
                <h3><?= __('admin_monthly_revenue') ?></h3>
                <p><?= sprintf(__('admin_overview'), date('Y')) ?></p>
            </div>
            <span class="text-xs font-semibold text-rose-500 bg-rose-50 px-3 py-1.5 rounded-full"><?= date('Y') ?></span>
        </div>
        <div class="chart-card-body">
            <div id="revenueChart"></div>
        </div>
    </div>

    <!-- Status Donut -->
    <div class="chart-card">
        <div class="chart-card-header">
            <div>
                <h3><?= __('admin_orders_by_status') ?></h3>
                <p><?= __('admin_current_distribution') ?></p>
            </div>
        </div>
        <div class="chart-card-body">
            <?php if (empty($statusData)): ?>
                <div class="flex flex-col items-center justify-center h-48 text-gray-300">
                    <svg class="w-12 h-12 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    <p class="text-sm font-medium"><?= __('admin_no_orders_yet') ?></p>
                </div>
            <?php else: ?>
                <div id="statusChart"></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── Best-Selling + Top Products ───────────────── -->
<div class="grid grid-cols-1 xl:grid-cols-3 gap-5 mb-5 px-4">

    <!-- Best-Selling Chart -->
    <div class="chart-card xl:col-span-2">
        <div class="chart-card-header">
            <div>
                <h3><?= __('admin_best_selling') ?></h3>
                <p><?= __('admin_top_5_by_qty') ?></p>
            </div>
        </div>
        <div class="chart-card-body">
            <?php if (empty($bestSellingProducts)): ?>
                <div class="flex flex-col items-center justify-center h-48 text-gray-300">
                    <svg class="w-12 h-12 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <p class="text-sm font-medium"><?= __('admin_no_sales_data') ?></p>
                </div>
            <?php else: ?>
                <div id="bestSellingChart"></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top Products Quick List -->
    <div class="section-card">
        <div class="section-card-header">
            <div>
                <h3><?= __('admin_top_products') ?></h3>
                <p class="sub"><?= __('admin_by_units_sold') ?></p>
            </div>
        </div>
        <div class="p-4 space-y-2.5">
            <?php if (empty($bestSellingProducts)): ?>
            <div class="empty-state">
                <span class="empty-state-icon">📦</span>
                <p class="empty-state-text"><?= __('admin_no_sales_data') ?></p>
            </div>
            <?php else: ?>
            <?php foreach ($bestSellingProducts as $i => $product): ?>
            <?php
                $rankClass = match($i) { 0 => 'gold', 1 => 'silver', 2 => 'bronze', default => 'other' };
                $maxSold = max($bestSellingData) ?: 1;
                $barPct = round(($product['total_sold'] / $maxSold) * 100);
            ?>
            <div class="flex items-center gap-3 p-3 rounded-xl hover:bg-gray-50 transition-colors">
                <div class="rank-badge <?= $rankClass ?>"><?= $i + 1 ?></div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-700 truncate"><?= htmlspecialchars($product['name']) ?></p>
                    <div class="flex items-center gap-2 mt-1">
                        <div class="stock-bar-track flex-1">
                            <div class="stock-bar-fill bg-rose-400" style="width:<?= $barPct ?>%"></div>
                        </div>
                        <span class="text-xs text-gray-400 font-medium whitespace-nowrap"><?= $product['total_sold'] ?> <?= __('reports_units') ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ── Recent Orders + Low Stock + Reviews ────────── -->
<div class="grid grid-cols-1 xl:grid-cols-3 gap-5 px-4 pb-6">

    <!-- Recent Orders Table -->
    <div class="section-card xl:col-span-2">
        <div class="section-card-header">
            <div>
                <h3><?= __('admin_recent_orders') ?></h3>
                <p class="sub"><?= __('admin_latest_8_orders') ?? 'Latest 8 orders' ?></p>
            </div>
            <a href="/sweetheaven/admin/order.php" class="section-card-link"><?= __('admin_view_all') ?></a>
        </div>
        <div class="overflow-x-auto">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th><?= __('admin_order_id') ?></th>
                        <th><?= __('admin_customer') ?></th>
                        <th><?= __('admin_amount') ?></th>
                        <th><?= __('admin_status') ?></th>
                        <th><?= __('admin_date') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentOrders)): ?>
                    <tr>
                        <td colspan="5">
                            <div class="empty-state">
                                <span class="empty-state-icon">📋</span>
                                <p class="empty-state-text"><?= __('admin_no_orders_yet') ?></p>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($recentOrders as $order): ?>
                    <tr>
                        <td>
                            <span class="font-mono text-sm font-bold text-rose-500">#<?= str_pad($order['id'], 4, '0', STR_PAD_LEFT) ?></span>
                        </td>
                        <td>
                            <div class="flex items-center gap-2.5">
                                <div class="user-avatar"><?= strtoupper(substr($order['name'], 0, 1)) ?></div>
                                <span class="text-sm font-medium text-gray-700"><?= htmlspecialchars($order['name']) ?></span>
                            </div>
                        </td>
                        <td>
                            <span class="text-sm font-bold text-gray-800"><?= number_format($order['total_amount']) ?></span>
                            <span class="text-xs text-gray-400 ml-1"><?= __('admin_mmk') ?></span>
                        </td>
                        <td>
                            <span class="status-badge <?= $statusColors[$order['status']] ?? 'badge-pending' ?>">
                                <?= ucfirst(__("status_{$order['status']}")) ?>
                            </span>
                        </td>
                        <td>
                            <span class="text-sm text-gray-400"><?= date('M j, Y', strtotime($order['order_date'])) ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right Column: Low Stock + Reviews stacked -->
    <div class="flex flex-col gap-5">

        <!-- Low Stock Alert -->
        <div class="section-card">
            <div class="section-card-header">
                <div>
                    <h3><?= __('admin_low_stock') ?></h3>
                    <p class="sub"><?= __('admin_stock_below_10') ?? 'Stock below 10' ?></p>
                </div>
                <a href="/sweetheaven/admin/product.php" class="section-card-link"><?= __('admin_manage') ?></a>
            </div>
            <div class="p-4 space-y-2.5">
                <?php if (empty($lowStockProducts)): ?>
                <div class="empty-state">
                    <span class="empty-state-icon">✅</span>
                    <p class="empty-state-text"><?= __('admin_all_stocked') ?></p>
                </div>
                <?php else: ?>
                <?php foreach ($lowStockProducts as $product): ?>
                <div class="flex items-center justify-between p-3 rounded-xl bg-gray-50 hover:bg-gray-100 transition-colors">
                    <div class="min-w-0 flex-1 mr-3">
                        <p class="text-sm font-semibold text-gray-700 truncate"><?= htmlspecialchars($product['name']) ?></p>
                        <p class="text-xs text-gray-400"><?= htmlspecialchars($product['category']) ?></p>
                        <div class="stock-bar-track mt-1.5" style="width:80px">
                            <div class="stock-bar-fill <?= $product['stock'] == 0 ? 'bg-red-500' : ($product['stock'] < 5 ? 'bg-orange-400' : 'bg-amber-400') ?>"
                                 style="width:<?= min(100, $product['stock'] * 10) ?>%"></div>
                        </div>
                    </div>
                    <span class="text-xs font-bold px-2.5 py-1 rounded-full flex-shrink-0 <?= $product['stock'] == 0 ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700' ?>">
                        <?= sprintf(__('admin_left'), $product['stock']) ?>
                    </span>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Customer Reviews -->
        <div class="section-card">
            <div class="section-card-header">
                <div>
                    <h3><?= __('admin_customer_reviews') ?></h3>
                </div>
                <a href="/sweetheaven/admin/review.php" class="section-card-link"><?= __('admin_manage') ?></a>
            </div>
            <div class="p-4 space-y-3">
                <?php if (empty($customerReviews)): ?>
                <div class="empty-state">
                    <span class="empty-state-icon">💬</span>
                    <p class="empty-state-text"><?= __('admin_no_approved_reviews') ?></p>
                </div>
                <?php else: ?>
                <?php foreach ($customerReviews as $r): ?>
                <div class="p-4 rounded-2xl border border-gray-100 bg-gradient-to-br from-gray-50 to-white">
                    <div class="flex items-center gap-3 mb-2.5">
                        <div class="user-avatar"><?= strtoupper(substr($r['name'], 0, 1)) ?></div>
                        <div>
                            <p class="text-sm font-semibold text-gray-700"><?= htmlspecialchars($r['name']) ?></p>
                            <p class="text-xs text-gray-400"><?= date('M j, Y', strtotime($r['created_at'])) ?></p>
                        </div>
                    </div>
                    <?php if ($r['rating']): ?>
                    <div class="flex items-center gap-0.5 mb-2">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <svg class="w-3.5 h-3.5 <?= $i <= $r['rating'] ? 'text-amber-400' : 'text-gray-200' ?>" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                        </svg>
                        <?php endfor; ?>
                        <span class="text-xs text-gray-400 ml-1"><?= $r['rating'] ?>/5</span>
                    </div>
                    <?php endif; ?>
                    <p class="text-xs text-gray-500 leading-relaxed italic">"<?= htmlspecialchars($r['message']) ?>"</p>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /right column -->
</div><!-- /bottom row -->

<!-- ApexCharts Scripts -->
<script>
const revenueData   = <?= json_encode(array_values($revenueData)) ?>;
const monthLabels   = <?= json_encode($monthLabels) ?>;
const statusLabels  = <?= json_encode($statusLabels ?: [__('admin_no_sales_data')]) ?>;
const statusData    = <?= json_encode($statusData ?: [1]) ?>;
const bestLabels    = <?= json_encode($bestSellingLabels) ?>;
const bestData      = <?= json_encode($bestSellingData) ?>;

// Revenue Chart
new ApexCharts(document.querySelector('#revenueChart'), {
    series: [{ name: '<?= __('admin_revenue') ?> (<?= __('admin_mmk') ?>)', data: revenueData }],
    chart: { type: 'area', height: 260, toolbar: { show: false }, fontFamily: 'Poppins, sans-serif', animations: { enabled: true, easing: 'easeinout', speed: 700 } },
    xaxis: { categories: monthLabels, labels: { style: { fontSize: '11px', colors: '#94a3b8' } }, axisBorder: { show: false }, axisTicks: { show: false } },
    yaxis: { labels: { formatter: v => (v/1000).toFixed(0) + 'K', style: { fontSize: '11px', colors: '#94a3b8' } } },
    colors: ['#f43f5e'],
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.02, stops: [0, 100] } },
    stroke: { curve: 'smooth', width: 2.5 },
    dataLabels: { enabled: false },
    grid: { borderColor: '#f1f5f9', strokeDashArray: 4, padding: { left: 10, right: 10 } },
    tooltip: { y: { formatter: v => v.toLocaleString() + ' <?= __('admin_mmk') ?>' }, theme: 'light' },
    markers: { size: 0, hover: { size: 5 } }
}).render();

// Status Donut Chart
if (document.querySelector('#statusChart')) {
    new ApexCharts(document.querySelector('#statusChart'), {
        series: statusData,
        chart: { type: 'donut', height: 260, fontFamily: 'Poppins, sans-serif', animations: { enabled: true, easing: 'easeinout', speed: 700 } },
        labels: statusLabels,
        colors: ['#f59e0b','#3b82f6','#6366f1','#22c55e','#ef4444'],
        legend: { position: 'bottom', fontSize: '11px', fontWeight: 500, markers: { radius: 99 } },
        dataLabels: { enabled: false },
        plotOptions: { pie: { donut: { size: '68%', labels: { show: true, total: { show: true, fontSize: '14px', fontWeight: 700, color: '#111827', label: 'Total' } } } } },
        stroke: { width: 0 }
    }).render();
}

// Best-Selling Chart
if (document.querySelector('#bestSellingChart') && bestLabels.length > 0) {
    new ApexCharts(document.querySelector('#bestSellingChart'), {
        series: [{ name: '<?= __('reports_units_sold') ?>', data: bestData }],
        chart: { type: 'bar', height: 260, toolbar: { show: false }, fontFamily: 'Poppins, sans-serif', animations: { enabled: true, easing: 'easeinout', speed: 700 } },
        plotOptions: { bar: { horizontal: true, borderRadius: 6, dataLabels: { position: 'top' }, barHeight: '55%' } },
        xaxis: { categories: bestLabels, labels: { style: { fontSize: '11px', colors: '#94a3b8' } }, axisBorder: { show: false }, axisTicks: { show: false } },
        yaxis: { labels: { style: { fontSize: '11px', colors: '#475569', fontWeight: 500 } } },
        colors: ['#f43f5e'],
        dataLabels: { enabled: true, offsetX: 22, style: { fontSize: '11px', colors: ['#64748b'], fontWeight: 600 } },
        grid: { borderColor: '#f1f5f9', strokeDashArray: 4, xaxis: { lines: { show: true } }, yaxis: { lines: { show: false } } },
        tooltip: { y: { formatter: v => v + ' <?= __('reports_units') ?>' }, theme: 'light' }
    }).render();
}
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
