<?php
require_once __DIR__ . '/../middleware/admin_check.php';
require_once __DIR__ . '/../config/db.php';

$db = getDB();

// Fetch categories for filter dropdown
$categories = $db->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

$pageTitle = 'Reports';
require_once __DIR__ . '/../includes/admin_header.php';

$statusColors = [
    'pending'    => 'bg-amber-100 text-amber-700',
    'processing' => 'bg-blue-100 text-blue-700',
    'shipped'    => 'bg-indigo-100 text-indigo-700',
    'delivered'  => 'bg-green-100 text-green-700',
    'cancelled'  => 'bg-red-100 text-red-700',
];
?>

<style>
@media print {
    /* Hide sidebar */
    body > aside { display: none !important; }

    /* Reset body */
    body {
        background: white !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    /* Remove sidebar offset */
    .flex-1.lg\:ml-64 { margin-left: 0 !important; padding: 0 !important; }
    .flex-1.lg\:ml-64 > header { display: none !important; }

    /* Hide main content except print summary */
    .flex-1.lg\:ml-64 > main > *:not(#printSummaryContainer) {
        display: none !important;
    }

    /* Show the print summary */
    #printSummaryContainer {
        display: block !important;
        padding: 20px !important;
    }
}
</style>

<!-- Print-Only Summary Container (shown only in print) -->
<div id="printSummaryContainer" style="display:none; font-family: Calibri, Arial, sans-serif; padding:20px;">
    <!-- Title -->
    <div style="text-align:center; margin-bottom:16px;">
        <h1 id="printSummaryTitle" style="font-size:22px; font-weight:700; margin-bottom:2px;">Report</h1>
        <p id="printSummaryDateRange" style="font-size:12px; color:#666;"></p>
    </div>

    <!-- Orders Table -->
    <table style="width:100%; border-collapse:collapse; font-size:11px;">
        <thead>
            <tr>
                <th style="background:#2563eb; color:white; padding:8px 10px; text-align:left; border:1px solid #1d4ed8;">Order ID</th>
                <th style="background:#2563eb; color:white; padding:8px 10px; text-align:left; border:1px solid #1d4ed8;">Customer</th>
                <th style="background:#2563eb; color:white; padding:8px 10px; text-align:right; border:1px solid #1d4ed8;">Amount</th>
                <th style="background:#2563eb; color:white; padding:8px 10px; text-align:left; border:1px solid #1d4ed8;">Status</th>
                <th style="background:#2563eb; color:white; padding:8px 10px; text-align:left; border:1px solid #1d4ed8;">Date</th>
            </tr>
        </thead>
        <tbody id="printOrdersBody">
            <tr><td colspan="5" style="padding:10px; text-align:center; color:#999;">Loading...</td></tr>
        </tbody>
    </table>
</div>

<!-- Filter Bar -->
<div id="filterBar" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-6 mx-4">
    <div class="flex flex-wrap items-end gap-4">
        <!-- Time Period -->
        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Time Period</label>
            <div class="flex gap-1 bg-gray-100 rounded-lg p-1" id="periodGroup">
                <button type="button" data-period="monthly" class="period-btn px-4 py-2 text-sm font-medium rounded-md transition-all bg-white shadow text-rose-600">Monthly</button>
                <button type="button" data-period="yearly" class="period-btn px-4 py-2 text-sm font-medium rounded-md transition-all text-gray-600 hover:text-gray-800">Yearly</button>
                <button type="button" data-period="custom" class="period-btn px-4 py-2 text-sm font-medium rounded-md transition-all text-gray-600 hover:text-gray-800">Custom</button>
            </div>
        </div>

        <!-- Custom Date Range (hidden by default) -->
        <div id="customDateRange" class="hidden flex gap-2 items-end">
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Start Date</label>
                <input type="date" id="startDate" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-rose-500 focus:border-rose-500 outline-none">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">End Date</label>
                <input type="date" id="endDate" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-rose-500 focus:border-rose-500 outline-none" value="<?= date('Y-m-d') ?>">
            </div>
        </div>

        <!-- Category Filter -->
        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Category</label>
            <select id="categoryFilter" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-rose-500 focus:border-rose-500 outline-none bg-white">
                <option value="0">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Apply Button -->
        <button onclick="fetchReport()" class="bg-rose-500 hover:bg-rose-600 text-white px-5 py-2 rounded-lg text-sm font-semibold transition-colors shadow-sm">
            Apply Filters
        </button>

        <!-- Export Button -->
        <button onclick="exportToExcel()" class="bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded-lg text-sm font-semibold transition-colors shadow-sm flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Export to Excel
        </button>

        <!-- Print Report Button -->
        <button onclick="printReport()" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg text-sm font-semibold transition-colors shadow-sm flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            Print Report
        </button>
    </div>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6 mb-6 px-4" id="summaryCards">
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-blue-100 rounded-2xl flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-800" id="statOrders">-</p>
        <p class="text-sm text-gray-400 mt-1">Total Orders</p>
    </div>

    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-green-100 rounded-2xl flex items-center justify-center">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-800" id="statRevenue">-</p>
        <p class="text-sm text-gray-400 mt-1">Total Revenue</p>
    </div>

    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-purple-100 rounded-2xl flex items-center justify-center">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-800" id="statProductsSold">-</p>
        <p class="text-sm text-gray-400 mt-1">Products Sold</p>
    </div>

    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-amber-100 rounded-2xl flex items-center justify-center">
                <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
            </div>
        </div>
        <p class="text-2xl font-bold text-gray-800" id="statTotalSales">-</p>
        <p class="text-sm text-gray-400 mt-1">Total Sales</p>
    </div>
</div>

<!-- Charts Row -->
<div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-6 px-4">
    <!-- Best-Selling Products Chart -->
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
        <h3 class="text-lg font-bold text-gray-800 mb-1">Best-Selling Products</h3>
        <p class="text-sm text-gray-400 mb-6">By quantity sold</p>
        <div id="bestSellingChart">
            <div class="h-48 flex items-center justify-center text-gray-400 text-sm">Loading...</div>
        </div>
    </div>

    <!-- Order Status Chart -->
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-gray-100">
        <h3 class="text-lg font-bold text-gray-800 mb-1">Order Status Summary</h3>
        <p class="text-sm text-gray-400 mb-6">Current distribution</p>
        <div id="statusChart">
            <div class="h-48 flex items-center justify-center text-gray-400 text-sm">Loading...</div>
        </div>
    </div>
</div>

<!-- Best-Selling Products Table -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6 mx-4">
    <div class="px-6 py-5 border-b border-gray-100">
        <h3 class="text-lg font-bold text-gray-800">Best-Selling Products</h3>
        <p class="text-sm text-gray-400">Detailed breakdown</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-3 text-left">#</th>
                    <th class="px-6 py-3 text-left">Product</th>
                    <th class="px-6 py-3 text-left">Quantity Sold</th>
                    <th class="px-6 py-3 text-left">Revenue</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50" id="bestSellingTable">
                <tr><td colspan="4" class="px-6 py-10 text-center text-gray-400">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Order Status Summary Table -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6 mx-4">
    <div class="px-6 py-5 border-b border-gray-100">
        <h3 class="text-lg font-bold text-gray-800">Order Status Breakdown</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-3 text-left">Status</th>
                    <th class="px-6 py-3 text-left">Count</th>
                    <th class="px-6 py-3 text-left">Percentage</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50" id="statusTable">
                <tr><td colspan="3" class="px-6 py-10 text-center text-gray-400">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Orders Table -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6 mx-4">
    <div class="px-6 py-5 border-b border-gray-100">
        <h3 class="text-lg font-bold text-gray-800">Orders</h3>
        <p class="text-sm text-gray-400">Filtered results</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                <tr>
                    <th class="px-6 py-3 text-left">Order ID</th>
                    <th class="px-6 py-3 text-left">Customer</th>
                    <th class="px-6 py-3 text-left">Amount</th>
                    <th class="px-6 py-3 text-left">Status</th>
                    <th class="px-6 py-3 text-left">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50" id="ordersTable">
                <tr><td colspan="5" class="px-6 py-10 text-center text-gray-400">Loading...</td></tr>
            </tbody>
            <!-- Print-only: all orders -->
            <tbody id="ordersTableAll" style="display:none;"></tbody>
        </table>
    </div>
    <!-- Pagination -->
    <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between" id="pagination">
        <p class="text-sm text-gray-500" id="pageInfo"></p>
        <div class="flex gap-2" id="pageButtons"></div>
    </div>
</div>

<script>
let currentPeriod = 'monthly';
let currentPage = 1;
let bestSellingChartInstance = null;
let statusChartInstance = null;

// ── Period Button Toggle ─────────────────────────────
document.querySelectorAll('.period-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.period-btn').forEach(b => {
            b.classList.remove('bg-white', 'shadow', 'text-rose-600');
            b.classList.add('text-gray-600');
        });
        this.classList.add('bg-white', 'shadow', 'text-rose-600');
        this.classList.remove('text-gray-600');
        currentPeriod = this.dataset.period;

        document.getElementById('customDateRange').classList.toggle('hidden', currentPeriod !== 'custom');
        currentPage = 1;
        fetchReport();
    });
});

// ── Fetch Report Data ────────────────────────────────
function fetchReport() {
    let url = `/sweetheaven/api/reports.php?period=${currentPeriod}&page=${currentPage}`;

    if (currentPeriod === 'custom') {
        const start = document.getElementById('startDate').value;
        const end = document.getElementById('endDate').value;
        if (start) url += `&start_date=${start}`;
        if (end) url += `&end_date=${end}`;
    }

    const catId = document.getElementById('categoryFilter').value;
    if (catId > 0) url += `&category_id=${catId}`;

    fetch(url)
        .then(r => r.json())
        .then(data => {
            updateSummaryCards(data);
            updateBestSellingChart(data.best_selling_all_time);
            updateStatusChart(data.status_summary);
            updateBestSellingTable(data.best_selling_all_time);
            updateStatusTable(data.status_summary);
            updateOrdersTable(data.orders);
            updatePagination(data.total_pages, data.current_page, data.total_orders);
        })
        .catch(err => {
            console.error('Report fetch error:', err);
        });
}

// ── Update Summary Cards ─────────────────────────────
function updateSummaryCards(data) {
    document.getElementById('statOrders').textContent = data.total_orders.toLocaleString();
    document.getElementById('statRevenue').textContent = formatMMK(data.total_revenue);
    document.getElementById('statProductsSold').textContent = data.total_products_sold.toLocaleString();
    document.getElementById('statTotalSales').textContent = formatMMK(data.total_revenue);
}

// ── Best-Selling Chart ───────────────────────────────
function updateBestSellingChart(products) {
    const container = document.getElementById('bestSellingChart');

    if (!products || products.length === 0) {
        container.innerHTML = '<div class="h-48 flex items-center justify-center text-gray-400 text-sm">No sales data</div>';
        if (bestSellingChartInstance) { bestSellingChartInstance.destroy(); bestSellingChartInstance = null; }
        return;
    }

    container.innerHTML = '';

    if (bestSellingChartInstance) bestSellingChartInstance.destroy();

    bestSellingChartInstance = new ApexCharts(container, {
        series: [{ name: 'Units Sold', data: products.map(p => parseInt(p.total_sold)) }],
        chart: { type: 'bar', height: 300, toolbar: { show: false }, fontFamily: 'Poppins, sans-serif' },
        plotOptions: { bar: { horizontal: true, borderRadius: 4, dataLabels: { position: 'top' } } },
        xaxis: { categories: products.map(p => p.name.length > 20 ? p.name.substring(0, 20) + '...' : p.name) },
        yaxis: { labels: { style: { fontSize: '12px' } } },
        colors: ['#f43f5e'],
        dataLabels: { enabled: true, offsetX: 20, style: { fontSize: '12px', colors: ['#333'] } },
        grid: { borderColor: '#f1f5f9', strokeDashArray: 4 },
        tooltip: { y: { formatter: v => v + ' units' } }
    });
    bestSellingChartInstance.render();
}

// ── Status Donut Chart ───────────────────────────────
function updateStatusChart(statuses) {
    const container = document.getElementById('statusChart');
    const statusMap = {};
    statuses.forEach(s => { statusMap[s.status] = parseInt(s.cnt); });

    const allStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
    const labels = allStatuses.map(s => s.charAt(0).toUpperCase() + s.slice(1));
    const values = allStatuses.map(s => statusMap[s] || 0);

    if (values.every(v => v === 0)) {
        container.innerHTML = '<div class="h-48 flex items-center justify-center text-gray-400 text-sm">No orders</div>';
        if (statusChartInstance) { statusChartInstance.destroy(); statusChartInstance = null; }
        return;
    }

    container.innerHTML = '';

    if (statusChartInstance) statusChartInstance.destroy();

    statusChartInstance = new ApexCharts(container, {
        series: values,
        chart: { type: 'donut', height: 300, fontFamily: 'Poppins, sans-serif' },
        labels: labels,
        colors: ['#f59e0b', '#3b82f6', '#6366f1', '#22c55e', '#ef4444'],
        legend: { position: 'bottom', fontSize: '12px' },
        dataLabels: { enabled: false },
        plotOptions: { pie: { donut: { size: '65%' } } }
    });
    statusChartInstance.render();
}

// ── Best-Selling Table ───────────────────────────────
function updateBestSellingTable(products) {
    const tbody = document.getElementById('bestSellingTable');
    if (!products || products.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="px-6 py-10 text-center text-gray-400">No sales data</td></tr>';
        return;
    }
    tbody.innerHTML = products.map((p, i) => `
        <tr class="hover:bg-gray-50 transition-colors">
            <td class="px-6 py-4 text-sm text-gray-500">${i + 1}</td>
            <td class="px-6 py-4 text-sm font-semibold text-gray-700">${escapeHtml(p.name)}</td>
            <td class="px-6 py-4 text-sm text-gray-700">${parseInt(p.total_sold).toLocaleString()}</td>
            <td class="px-6 py-4 text-sm font-semibold text-gray-700">${formatMMK(p.revenue)}</td>
        </tr>
    `).join('');
}

// ── Status Table ─────────────────────────────────────
function updateStatusTable(statuses) {
    const tbody = document.getElementById('statusTable');
    const allStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
    const statusMap = {};
    statuses.forEach(s => { statusMap[s.status] = parseInt(s.cnt); });

    const statusColors = {
        pending: 'bg-amber-100 text-amber-700',
        processing: 'bg-blue-100 text-blue-700',
        shipped: 'bg-indigo-100 text-indigo-700',
        delivered: 'bg-green-100 text-green-700',
        cancelled: 'bg-red-100 text-red-700'
    };

    const totalCount = statuses.reduce((sum, s) => sum + parseInt(s.cnt), 0);

    if (totalCount === 0) {
        tbody.innerHTML = '<tr><td colspan="3" class="px-6 py-10 text-center text-gray-400">No orders</td></tr>';
        return;
    }

    tbody.innerHTML = allStatuses.map(status => {
        const count = statusMap[status] || 0;
        const pct = ((count / totalCount) * 100).toFixed(1);
        return `
            <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-6 py-4">
                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold ${statusColors[status] || 'bg-gray-100 text-gray-600'}">
                        ${status.charAt(0).toUpperCase() + status.slice(1)}
                    </span>
                </td>
                <td class="px-6 py-4 text-sm font-semibold text-gray-700">${count.toLocaleString()}</td>
                <td class="px-6 py-4 text-sm text-gray-500">${pct}%</td>
            </tr>
        `;
    }).join('');
}

// ── Orders Table ─────────────────────────────────────
function updateOrdersTable(orders) {
    const tbody = document.getElementById('ordersTable');
    if (!orders || orders.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-10 text-center text-gray-400">No orders found</td></tr>';
        return;
    }

    const statusColors = {
        pending: 'bg-amber-100 text-amber-700',
        processing: 'bg-blue-100 text-blue-700',
        shipped: 'bg-indigo-100 text-indigo-700',
        delivered: 'bg-green-100 text-green-700',
        cancelled: 'bg-red-100 text-red-700'
    };

    tbody.innerHTML = orders.map(o => `
        <tr class="hover:bg-gray-50 transition-colors">
            <td class="px-6 py-4 font-mono text-sm text-gray-600">#${String(o.id).padStart(4, '0')}</td>
            <td class="px-6 py-4">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-rose-50 rounded-full flex items-center justify-center text-rose-500 font-semibold text-sm">
                        ${escapeHtml(o.customer).charAt(0).toUpperCase()}
                    </div>
                    <span class="text-sm font-medium text-gray-700">${escapeHtml(o.customer)}</span>
                </div>
            </td>
            <td class="px-6 py-4 text-sm font-semibold text-gray-700">${formatMMK(o.total_amount)}</td>
            <td class="px-6 py-4">
                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold ${statusColors[o.status] || 'bg-gray-100 text-gray-600'}">
                    ${o.status.charAt(0).toUpperCase() + o.status.slice(1)}
                </span>
            </td>
            <td class="px-6 py-4 text-sm text-gray-400">${new Date(o.order_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</td>
        </tr>
    `).join('');
}

// ── Pagination ───────────────────────────────────────
function updatePagination(totalPages, current, totalOrders) {
    document.getElementById('pageInfo').textContent = `Page ${current} of ${totalPages} (${totalOrders} orders)`;

    const btns = document.getElementById('pageButtons');
    if (totalPages <= 1) { btns.innerHTML = ''; return; }

    let html = '';
    if (current > 1) {
        html += `<button onclick="goToPage(${current - 1})" class="px-3 py-1 text-sm border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">Prev</button>`;
    }

    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= current - 2 && i <= current + 2)) {
            html += `<button onclick="goToPage(${i})" class="px-3 py-1 text-sm rounded-lg transition-colors ${i === current ? 'bg-rose-500 text-white' : 'border border-gray-200 hover:bg-gray-50'}">${i}</button>`;
        } else if (i === current - 3 || i === current + 3) {
            html += `<span class="px-2 py-1 text-sm text-gray-400">...</span>`;
        }
    }

    if (current < totalPages) {
        html += `<button onclick="goToPage(${current + 1})" class="px-3 py-1 text-sm border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">Next</button>`;
    }

    btns.innerHTML = html;
}

function goToPage(page) {
    currentPage = page;
    fetchReport();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ── Export to Excel ──────────────────────────────────
function exportToExcel() {
    let url = `/sweetheaven/api/export_reports.php?period=${currentPeriod}`;

    if (currentPeriod === 'custom') {
        const start = document.getElementById('startDate').value;
        const end = document.getElementById('endDate').value;
        if (start) url += `&start_date=${start}`;
        if (end) url += `&end_date=${end}`;
    }

    const catId = document.getElementById('categoryFilter').value;
    if (catId > 0) url += `&category_id=${catId}`;

    window.location.href = url;
}

// ── Helpers ──────────────────────────────────────────
function formatMMK(val) {
    return parseFloat(val || 0).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) + ' MMK';
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// ── Print Report ─────────────────────────────────────
function getPeriodLabel() {
    if (currentPeriod === 'yearly') return 'Yearly Report';
    if (currentPeriod === 'custom') return 'Custom Report';
    return 'Monthly Report';
}

function getDateRangeText() {
    const today = new Date();
    if (currentPeriod === 'yearly') return today.getFullYear().toString();
    if (currentPeriod === 'custom') {
        const s = document.getElementById('startDate').value;
        const e = document.getElementById('endDate').value;
        return (s || 'Start') + ' to ' + (e || 'End');
    }
    return today.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
}

function printReport() {
    document.getElementById('printSummaryTitle').textContent = getPeriodLabel();
    document.getElementById('printSummaryDateRange').textContent = getDateRangeText();
    document.getElementById('printOrdersBody').innerHTML = '<tr><td colspan="5" style="padding:10px; text-align:center; color:#999;">Loading...</td></tr>';

    let url = `/sweetheaven/api/reports.php?period=${currentPeriod}&print=1`;
    if (currentPeriod === 'custom') {
        const start = document.getElementById('startDate').value;
        const end = document.getElementById('endDate').value;
        if (start) url += `&start_date=${start}`;
        if (end) url += `&end_date=${end}`;
    }
    const catId = document.getElementById('categoryFilter').value;
    if (catId > 0) url += `&category_id=${catId}`;

    fetch(url)
        .then(r => r.json())
        .then(data => {
            const tbody = document.getElementById('printOrdersBody');
            if (!data.orders || data.orders.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" style="padding:10px; text-align:center; color:#999;">No shipped or delivered orders found</td></tr>';
            } else {
                tbody.innerHTML = data.orders.map(o => {
                    const sc = { shipped:'#4f46e5', delivered:'#16a34a' }[o.status] || '#666';
                    return `<tr>
                        <td style="padding:6px 10px; border:1px solid #ccc;">#${String(o.id).padStart(4, '0')}</td>
                        <td style="padding:6px 10px; border:1px solid #ccc;">${escapeHtml(o.customer)}</td>
                        <td style="padding:6px 10px; border:1px solid #ccc; text-align:right;">${formatMMK(o.total_amount)}</td>
                        <td style="padding:6px 10px; border:1px solid #ccc;"><span style="padding:1px 6px; border:1px solid ${sc}; color:${sc}; border-radius:3px; font-size:10px;">${o.status.charAt(0).toUpperCase() + o.status.slice(1)}</span></td>
                        <td style="padding:6px 10px; border:1px solid #ccc;">${new Date(o.order_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</td>
                    </tr>`;
                }).join('');
            }
            setTimeout(() => window.print(), 150);
        })
        .catch(err => {
            console.error('Print error:', err);
            window.print();
        });
}

// ── Initial Load ─────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    fetchReport();
});
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
