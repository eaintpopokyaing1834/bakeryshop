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
$customerReviews = $db->query("SELECT name, message, created_at FROM customer_reviews WHERE status='approved' ORDER BY created_at DESC LIMIT 2")->fetchAll();

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
    'pending'   => 'bg-amber-100 text-amber-700',
    'processing'=> 'bg-blue-100 text-blue-700',
    'shipped'   => 'bg-indigo-100 text-indigo-700',
    'delivered' => 'bg-green-100 text-green-700',
    'cancelled' => 'bg-red-100 text-red-700',
];
?>

<!-- Metrics Cards Row 1 -->
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6 mb-6 px-4">

    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-rose-50 rounded-2xl flex items-center justify-center">
                <svg class="w-6 h-6 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <span class="text-green-600 bg-green-50 text-xs font-semibold px-2 py-1 rounded-full"><?= __('admin_revenue') ?></span>
        </div>
        <p class="text-2xl font-bold text-gray-800"><?= number_format($totalRevenue) ?> <?= __('admin_mmk') ?></p>
        <p class="text-sm text-gray-400 mt-1"><?= __('admin_total_sales_revenue') ?></p>
    </div>

    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-blue-100 rounded-2xl flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            </div>
            <span class="text-amber-600 bg-amber-50 text-xs font-semibold px-2 py-1 rounded-full"><?= $pendingOrders ?> <?= __('admin_pending') ?></span>
        </div>
        <p class="text-2xl font-bold text-gray-800"><?= $totalOrders ?></p>
        <p class="text-sm text-gray-400 mt-1"><?= __('admin_total_orders') ?></p>
    </div>

    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-purple-100 rounded-2xl flex items-center justify-center">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-800"><?= $totalUsers ?></p>
        <p class="text-sm text-gray-400 mt-1"><?= __('admin_registered_customers') ?></p>
    </div>

    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 hover:shadow-md transition-shadow <?= $lowStock > 0 ? 'border-l-4 border-l-red-500' : '' ?>">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-red-100 rounded-2xl flex items-center justify-center">
                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
            <?php if ($lowStock > 0): ?>
            <span class="text-red-600 bg-red-50 text-xs font-semibold px-2 py-1 rounded-full animate-pulse"><?= __('admin_alert') ?></span>
            <?php endif; ?>
        </div>
        <p class="text-2xl font-bold text-gray-800"><?= $lowStock ?></p>
        <p class="text-sm text-gray-400 mt-1"><?= __('admin_low_stock_products') ?></p>
    </div>
</div>

<!-- Metrics Cards Row 2 -->
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6 mb-8 px-4">

    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-indigo-100 rounded-2xl flex items-center justify-center">
                <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-800"><?= $totalProducts ?></p>
        <p class="text-sm text-gray-400 mt-1"><?= __('admin_total_products') ?></p>
    </div>

    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-amber-100 rounded-2xl flex items-center justify-center">
                <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-800"><?= $pendingOrders ?></p>
        <p class="text-sm text-gray-400 mt-1"><?= __('admin_pending_orders') ?></p>
    </div>

    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-green-100 rounded-2xl flex items-center justify-center">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-800"><?= $completedOrders ?></p>
        <p class="text-sm text-gray-400 mt-1"><?= __('admin_completed_orders') ?></p>
    </div>

    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-cyan-100 rounded-2xl flex items-center justify-center">
                <svg class="w-6 h-6 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-800"><?= $totalUsers ?></p>
        <p class="text-sm text-gray-400 mt-1"><?= __('admin_total_customers') ?></p>
    </div>
</div>

<!-- Charts Row -->
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-8 px-4">

    <!-- Revenue Chart -->
    <div class="xl:col-span-2 bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="text-lg font-bold text-gray-800"><?= __('admin_monthly_revenue') ?></h3>
                <p class="text-sm text-gray-400"><?= sprintf(__('admin_overview'), date('Y')) ?></p>
            </div>
        </div>
        <div id="revenueChart"></div>
    </div>

    <!-- Status Donut -->
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
        <h3 class="text-lg font-bold text-gray-800 mb-1"><?= __('admin_orders_by_status') ?></h3>
        <p class="text-sm text-gray-400 mb-6"><?= __('admin_current_distribution') ?></p>
        <?php if (empty($statusData)): ?>
            <div class="h-48 flex items-center justify-center text-gray-400 text-sm"><?= __('admin_no_orders_yet') ?></div>
        <?php else: ?>
            <div id="statusChart"></div>
        <?php endif; ?>
    </div>
</div>

<!-- Best-Selling Products Chart -->
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-8 px-4">
    <div class="xl:col-span-2 bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h3 class="text-lg font-bold text-gray-800"><?= __('admin_best_selling') ?></h3>
                <p class="text-sm text-gray-400"><?= __('admin_top_5_by_qty') ?></p>
            </div>
        </div>
        <?php if (empty($bestSellingProducts)): ?>
            <div class="h-48 flex items-center justify-center text-gray-400 text-sm"><?= __('admin_no_sales_data') ?></div>
        <?php else: ?>
            <div id="bestSellingChart"></div>
        <?php endif; ?>
    </div>

    <!-- Top Product Quick Stats -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-100">
            <h3 class="text-lg font-bold text-gray-800"><?= __('admin_top_products') ?></h3>
            <p class="text-sm text-gray-400"><?= __('admin_by_units_sold') ?></p>
        </div>
        <div class="p-4 space-y-3">
            <?php if (empty($bestSellingProducts)): ?>
            <div class="py-8 text-center text-gray-400 text-sm"><?= __('admin_no_sales_data') ?></div>
            <?php else: ?>
            <?php foreach ($bestSellingProducts as $i => $product): ?>
            <div class="flex items-center gap-3 p-3 rounded-xl bg-gray-50 border border-gray-100">
                <div class="w-8 h-8 bg-rose-100 rounded-full flex items-center justify-center text-rose-600 font-bold text-sm shrink-0">
                    <?= $i + 1 ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-700 truncate"><?= htmlspecialchars($product['name']) ?></p>
                    <p class="text-xs text-gray-400"><?= sprintf(__('admin_sold'), $product['total_sold']) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Bottom Row -->
<div class="grid grid-cols-1 xl:grid-cols-3 gap-6 p-6">

    <!-- Recent Orders -->
    <div class="xl:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-lg font-bold text-gray-800"><?= __('admin_recent_orders') ?></h3>
            <a href="/sweetheaven/admin/order.php" class="text-rose-500 text-sm font-medium hover:underline"><?= __('admin_view_all') ?></a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-3 text-left"><?= __('admin_order_id') ?></th>
                        <th class="px-6 py-3 text-left"><?= __('admin_customer') ?></th>
                        <th class="px-6 py-3 text-left"><?= __('admin_amount') ?></th>
                        <th class="px-6 py-3 text-left"><?= __('admin_status') ?></th>
                        <th class="px-6 py-3 text-left"><?= __('admin_date') ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php if (empty($recentOrders)): ?>
                    <tr><td colspan="5" class="px-6 py-10 text-center text-gray-400"><?= __('admin_no_orders_yet') ?></td></tr>
                    <?php else: ?>
                    <?php foreach ($recentOrders as $order): ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 font-mono text-sm text-gray-600">#<?= str_pad($order['id'], 4, '0', STR_PAD_LEFT) ?></td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 bg-rose-50 rounded-full flex items-center justify-center text-rose-500 font-semibold text-sm">
                                    <?= strtoupper(substr($order['name'], 0, 1)) ?>
                                </div>
                                <span class="text-sm font-medium text-gray-700"><?= htmlspecialchars($order['name']) ?></span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm font-semibold text-gray-700"><?= number_format($order['total_amount']) ?> <?= __('admin_mmk') ?></td>
                        <td class="px-6 py-4">
                            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold <?= $statusColors[$order['status']] ?? 'bg-gray-100 text-gray-600' ?>">
                                <?= ucfirst(__("status_{$order['status']}")) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-400"><?= date('M j', strtotime($order['order_date'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Low Stock Alert -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-lg font-bold text-gray-800"><?= __('admin_low_stock') ?></h3>
            <a href="/sweetheaven/admin/product.php" class="text-rose-500 text-sm font-medium hover:underline"><?= __('admin_manage') ?></a>
        </div>
        <div class="p-4 space-y-3">
            <?php if (empty($lowStockProducts)): ?>
            <div class="py-8 text-center text-gray-400 text-sm">
                <p class="text-3xl mb-2">✅</p>
                <?= __('admin_all_stocked') ?>
            </div>
            <?php else: ?>
            <?php foreach ($lowStockProducts as $product): ?>
            <div class="flex items-center justify-between p-3 rounded-xl bg-gray-50 border border-gray-100">
                <div>
                    <p class="text-sm font-semibold text-gray-700"><?= htmlspecialchars($product['name']) ?></p>
                    <p class="text-xs text-gray-400"><?= htmlspecialchars($product['category']) ?></p>
                </div>
                <span class="<?= $product['stock'] == 0 ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700' ?> text-xs font-bold px-2.5 py-1 rounded-full">
                    <?= sprintf(__('admin_left'), $product['stock']) ?>
                </span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <!-- Customer Reviews -->
    <div class="xl:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-lg font-bold text-gray-800"><?= __('admin_customer_reviews') ?></h3>
            <a href="/sweetheaven/admin/review.php" class="text-rose-500 text-sm font-medium hover:underline"><?= __('admin_manage') ?></a>
        </div>
        <div class="p-5 grid md:grid-cols-2 gap-5">
            <?php if (empty($customerReviews)): ?>
                <div class="md:col-span-2 text-center text-gray-400 py-8">
                    <p class="text-4xl mb-3">💬</p>
                    <p class="text-sm"><?= __('admin_no_approved_reviews') ?></p>
                </div>
            <?php else: ?>
                <?php foreach ($customerReviews as $r): ?>
                    <div class="border border-gray-100 rounded-xl p-5 bg-gray-50/50">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-9 h-9 bg-rose-100 rounded-full flex items-center justify-center text-rose-500 font-bold text-sm">
                                <?= strtoupper(substr($r['name'], 0, 1)) ?>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-700 text-sm"><?= htmlspecialchars($r['name']) ?></p>
                                <p class="text-xs text-gray-400"><?= date('M j, Y', strtotime($r['created_at'])) ?></p>
                            </div>
                        </div>
                        <p class="text-gray-500 text-sm leading-relaxed">"<?= htmlspecialchars($r['message']) ?>"</p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

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
    chart: { type: 'area', height: 280, toolbar: { show: false }, fontFamily: 'Poppins, sans-serif' },
    xaxis: { categories: monthLabels },
    yaxis: { labels: { formatter: v => (v/1000).toFixed(0) + 'K' } },
    colors: ['#f43f5e'],
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } },
    stroke: { curve: 'smooth', width: 3 },
    dataLabels: { enabled: false },
    grid: { borderColor: '#f1f5f9', strokeDashArray: 4 },
    tooltip: { y: { formatter: v => v.toLocaleString() + ' <?= __('admin_mmk') ?>' } }
}).render();

// Status Donut Chart
if (document.querySelector('#statusChart')) {
    new ApexCharts(document.querySelector('#statusChart'), {
        series: statusData,
        chart: { type: 'donut', height: 280, fontFamily: 'Poppins, sans-serif' },
        labels: statusLabels,
        colors: ['#f59e0b','#3b82f6','#6366f1','#22c55e','#ef4444'],
        legend: { position: 'bottom', fontSize: '12px' },
        dataLabels: { enabled: false },
        plotOptions: { pie: { donut: { size: '65%' } } }
    }).render();
}

// Best-Selling Products Chart
if (document.querySelector('#bestSellingChart') && bestLabels.length > 0) {
    new ApexCharts(document.querySelector('#bestSellingChart'), {
        series: [{ name: '<?= __('reports_units_sold') ?>', data: bestData }],
        chart: { type: 'bar', height: 280, toolbar: { show: false }, fontFamily: 'Poppins, sans-serif' },
        plotOptions: { bar: { horizontal: true, borderRadius: 4, dataLabels: { position: 'top' } } },
        xaxis: { categories: bestLabels },
        yaxis: { labels: { style: { fontSize: '12px' } } },
        colors: ['#f43f5e'],
        dataLabels: { enabled: true, offsetX: 20, style: { fontSize: '12px', colors: ['#333'] } },
        grid: { borderColor: '#f1f5f9', strokeDashArray: 4 },
        tooltip: { y: { formatter: v => v + ' <?= __('reports_units') ?>' } }
    }).render();
}
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
