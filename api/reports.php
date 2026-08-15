<?php
require_once __DIR__ . '/../middleware/admin_check.php';
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

$db = getDB();

$period = $_GET['period'] ?? 'monthly';
$categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$printMode = isset($_GET['print']) && $_GET['print'] === '1';

// ── Date Range Calculation ──────────────────────────
$startDate = null;
$endDate = date('Y-m-d 23:59:59');
$today = date('Y-m-d');

switch ($period) {
    case 'daily':
        $startDate = $today . ' 00:00:00';
        break;
    case 'weekly':
        $startDate = date('Y-m-d 00:00:00', strtotime('monday this week'));
        break;
    case 'monthly':
        $startDate = date('Y-m-01 00:00:00');
        break;
    case 'yearly':
        $startDate = date('Y-01-01 00:00:00');
        break;
    case 'custom':
        $startDate = ($_GET['start_date'] ?? '') . ' 00:00:00';
        $endDate = ($_GET['end_date'] ?? date('Y-m-d')) . ' 23:59:59';
        if (empty($_GET['start_date'])) $startDate = null;
        break;
    default:
        $startDate = date('Y-m-01 00:00:00');
}

// ── Build WHERE clause ──────────────────────────────
$where = "o.status != 'cancelled'";
$params = [];

if ($startDate) {
    $where .= " AND o.order_date >= ?";
    $params[] = $startDate;
}
if ($endDate) {
    $where .= " AND o.order_date <= ?";
    $params[] = $endDate;
}

$categoryJoin = "";
$categoryWhere = "";
if ($categoryId > 0) {
    $categoryWhere = " AND EXISTS (SELECT 1 FROM order_items oi_filter JOIN products p_filter ON oi_filter.product_id = p_filter.id WHERE oi_filter.order_id = o.id AND p_filter.category_id = ?)";
    $params[] = $categoryId;
}

// ── Total Orders ────────────────────────────────────
$totalOrdersWhere = str_replace("o.status != 'cancelled'", "1=1", $where);
$stmt = $db->prepare("SELECT COUNT(DISTINCT o.id) FROM orders o{$categoryJoin} WHERE {$totalOrdersWhere}{$categoryWhere}");
$stmt->execute($params);
$totalOrders = (int)$stmt->fetchColumn();

// ── Total Revenue ───────────────────────────────────
$stmt = $db->prepare("SELECT COALESCE(SUM(o.total_amount), 0) FROM orders o{$categoryJoin} WHERE {$where}{$categoryWhere}");
$stmt->execute($params);
$totalRevenue = (float)$stmt->fetchColumn();

// ── Total Products Sold ─────────────────────────────
$productSoldWhere = str_replace("o.status != 'cancelled'", "o.status != 'cancelled'", $where);
$stmt = $db->prepare("
    SELECT COALESCE(SUM(oi.quantity), 0)
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    {$categoryJoin}
    WHERE {$productSoldWhere}{$categoryWhere}
");
$stmt->execute($params);
$totalProductsSold = (int)$stmt->fetchColumn();

// ── Best-Selling Products (period-filtered) ──────────
$paramsBest = array_merge($params);
$stmt = $db->prepare("
    SELECT p.name, SUM(oi.quantity) AS total_sold, SUM(oi.quantity * oi.price) AS revenue
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    {$categoryJoin}
    WHERE {$productSoldWhere}{$categoryWhere}
    GROUP BY p.id
    ORDER BY total_sold DESC
    LIMIT 10
");
$stmt->execute($paramsBest);
$bestSelling = $stmt->fetchAll();

// ── Best-Selling Products (all-time, for chart) ─────
$allTimeParams = [];
$allTimeCategoryJoin = "";
$allTimeCategoryWhere = "";
if ($categoryId > 0) {
    $allTimeCategoryWhere = " AND EXISTS (SELECT 1 FROM order_items oi_at JOIN products p_at ON oi_at.product_id = p_at.id WHERE oi_at.order_id = o.id AND p_at.category_id = ?)";
    $allTimeParams[] = $categoryId;
}
$stmt = $db->prepare("
    SELECT p.name, SUM(oi.quantity) AS total_sold, SUM(oi.quantity * oi.price) AS revenue
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN orders o ON oi.order_id = o.id
    {$allTimeCategoryJoin}
    WHERE o.status != 'cancelled'{$allTimeCategoryWhere}
    GROUP BY p.id
    ORDER BY total_sold DESC
    LIMIT 10
");
$stmt->execute($allTimeParams);
$bestSellingAllTime = $stmt->fetchAll();

// ── Order Status Summary ────────────────────────────
$statusParams = array_merge($params);
$statusWhere = $where;
if ($startDate) {
    $statusWhere .= " AND o.order_date >= ?";
    array_splice($statusParams, count($statusParams) - ($categoryId > 0 ? 1 : 0), 0, [$startDate]);
} else {
    $statusParams = $params;
}
// Simpler approach: rebuild status query
$statusWhereSimple = "1=1";
$statusParamsSimple = [];
if ($startDate) {
    $statusWhereSimple .= " AND o.order_date >= ?";
    $statusParamsSimple[] = $startDate;
}
if ($endDate) {
    $statusWhereSimple .= " AND o.order_date <= ?";
    $statusParamsSimple[] = $endDate;
}

$stmt = $db->prepare("
    SELECT status, COUNT(*) as cnt
    FROM orders o
    WHERE {$statusWhereSimple}
    GROUP BY status
");
$stmt->execute($statusParamsSimple);
$statusSummary = $stmt->fetchAll();

// ── Orders (paginated or full for print) ────────────
$orderParams = array_merge($params);

if ($printMode) {
    // Print mode: only shipped and delivered orders
    $printWhere = str_replace("o.status != 'cancelled'", "o.status IN ('shipped', 'delivered')", $where);
    $orderSql = "
        SELECT o.id, u.name AS customer, o.total_amount, o.status, o.order_date
        FROM orders o
        JOIN users u ON o.user_id = u.id
        {$categoryJoin}
        WHERE {$printWhere}{$categoryWhere}
        ORDER BY o.order_date DESC
    ";
} else {
    $orderSql = "
        SELECT o.id, u.name AS customer, o.total_amount, o.status, o.order_date
        FROM orders o
        JOIN users u ON o.user_id = u.id
        {$categoryJoin}
        WHERE {$where}{$categoryWhere}
        ORDER BY o.order_date DESC
    ";
    $offset = ($page - 1) * $perPage;
    $orderSql .= " LIMIT {$perPage} OFFSET {$offset}";
}

$stmt = $db->prepare($orderSql);
$stmt->execute($orderParams);
$orders = $stmt->fetchAll();

$totalPages = $printMode ? 1 : max(1, ceil($totalOrders / $perPage));

// ── Response ────────────────────────────────────────
echo json_encode([
    'total_orders' => $totalOrders,
    'total_revenue' => $totalRevenue,
    'total_products_sold' => $totalProductsSold,
    'best_selling' => $bestSelling,
    'best_selling_all_time' => $bestSellingAllTime,
    'status_summary' => $statusSummary,
    'orders' => $orders,
    'total_pages' => $totalPages,
    'current_page' => $page,
]);
