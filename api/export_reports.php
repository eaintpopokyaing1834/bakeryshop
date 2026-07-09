<?php
require_once __DIR__ . '/../middleware/admin_check.php';
require_once __DIR__ . '/../config/db.php';

$db = getDB();

$period = $_GET['period'] ?? 'monthly';
$categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

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
if ($endDate && $period !== 'custom') {
    $where .= " AND o.order_date <= ?";
    $params[] = $endDate;
}

$categoryJoin = "";
$categoryWhere = "";
if ($categoryId > 0) {
    $categoryJoin = " JOIN order_items oi ON o.id = oi.order_id JOIN products p ON oi.product_id = p.id";
    $categoryWhere = " AND p.category_id = ?";
    $params[] = $categoryId;
} else {
    $categoryJoin = " JOIN order_items oi ON o.id = oi.order_id JOIN products p ON oi.product_id = p.id";
}

// ── Fetch all order items with details ──────────────
$stmt = $db->prepare("
    SELECT
        o.id AS order_id,
        u.name AS customer_name,
        p.name AS product_name,
        c.name AS category,
        oi.quantity,
        oi.price,
        (oi.quantity * oi.price) AS line_total,
        o.total_amount,
        COALESCE(pm.payment_name, 'N/A') AS payment_method,
        o.status AS order_status,
        o.order_date
    FROM orders o
    JOIN users u ON o.user_id = u.id
    JOIN order_items oi ON o.id = oi.order_id
    JOIN products p ON oi.product_id = p.id
    JOIN categories c ON p.category_id = c.id
    LEFT JOIN payment pay ON o.id = pay.order_id
    LEFT JOIN payment_methods pm ON pay.payment_method_id = pm.id
    WHERE {$where}{$categoryWhere}
    ORDER BY o.order_date DESC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// ── Generate filename ───────────────────────────────
$dateStr = date('Y-m-d');
switch ($period) {
    case 'daily':
        $filename = "daily_report_{$dateStr}.xlsx";
        break;
    case 'weekly':
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $weekEnd = date('Y-m-d', strtotime('sunday this week'));
        $filename = "weekly_report_{$weekStart}_to_{$weekEnd}.xlsx";
        break;
    case 'monthly':
        $month = date('F_Y');
        $filename = "monthly_report_{$month}.xlsx";
        break;
    case 'yearly':
        $year = date('Y');
        $filename = "yearly_report_{$year}.xlsx";
        break;
    case 'custom':
        $s = $_GET['start_date'] ?? 'start';
        $e = $_GET['end_date'] ?? 'end';
        $filename = "custom_report_{$s}_to_{$e}.xlsx";
        break;
    default:
        $filename = "report_{$dateStr}.xlsx";
}

if ($categoryId > 0) {
    $catName = $db->prepare("SELECT name FROM categories WHERE id = ?");
    $catName->execute([$categoryId]);
    $catName = $catName->fetchColumn();
    $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($catName ?? 'category'));
    $filename = $safeName . '_' . $filename;
}

// ── Generate SpreadsheetML (Excel XML) ──────────────
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
$xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
$xml .= '  xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";

// ── Styles ──────────────────────────────────────────
$xml .= '<Styles>' . "\n";
$xml .= '  <Style ss:ID="Default" ss:Name="Normal">' . "\n";
$xml .= '    <Alignment ss:Vertical="Center"/>' . "\n";
$xml .= '    <Font ss:FontName="Calibri" ss:Size="11"/>' . "\n";
$xml .= '  </Style>' . "\n";
$xml .= '  <Style ss:ID="header">' . "\n";
$xml .= '    <Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/>' . "\n";
$xml .= '    <Interior ss:Color="#F43F5E" ss:Pattern="Solid"/>' . "\n";
$xml .= '    <Alignment ss:Vertical="Center" ss:Horizontal="Center"/>' . "\n";
$xml .= '  </Style>' . "\n";
$xml .= '  <Style ss:ID="currency">' . "\n";
$xml .= '    <NumberFormat ss:Format="#,##0"/>' . "\n";
$xml .= '  </Style>' . "\n";
$xml .= '  <Style ss:ID="date">' . "\n";
$xml .= '    <NumberFormat ss:Format="yyyy-mm-dd hh:mm:ss"/>' . "\n";
$xml .= '  </Style>' . "\n";
$xml .= '</Styles>' . "\n";

// ── Worksheet ───────────────────────────────────────
$xml .= '<Worksheet ss:Name="Report">' . "\n";
$xml .= '<Table ss:DefaultColumnWidth="120" ss:DefaultRowHeight="20">' . "\n";

// ── Column widths ───────────────────────────────────
$colWidths = [80, 140, 160, 120, 70, 120, 120, 120, 120, 80, 130];
foreach ($colWidths as $w) {
    $xml .= '<Column ss:Width="' . $w . '"/>' . "\n";
}

// ── Header row ──────────────────────────────────────
$headers = [
    'Order ID', 'Customer Name', 'Product Name', 'Category',
    'Quantity', 'Price', 'Line Total', 'Total Amount',
    'Payment Method', 'Status', 'Order Date'
];

$xml .= '<Row ss:AutoFitHeight="0" ss:Height="24">' . "\n";
foreach ($headers as $h) {
    $xml .= '  <Cell ss:StyleID="header"><Data ss:Type="String">' . htmlspecialchars($h) . '</Data></Cell>' . "\n";
}
$xml .= '</Row>' . "\n";

// ── Data rows ───────────────────────────────────────
foreach ($rows as $row) {
    $xml .= '<Row>' . "\n";
    $xml .= '  <Cell><Data ss:Type="Number">#' . str_pad($row['order_id'], 4, '0', STR_PAD_LEFT) . '</Data></Cell>' . "\n";
    $xml .= '  <Cell><Data ss:Type="String">' . htmlspecialchars($row['customer_name']) . '</Data></Cell>' . "\n";
    $xml .= '  <Cell><Data ss:Type="String">' . htmlspecialchars($row['product_name']) . '</Data></Cell>' . "\n";
    $xml .= '  <Cell><Data ss:Type="String">' . htmlspecialchars($row['category']) . '</Data></Cell>' . "\n";
    $xml .= '  <Cell><Data ss:Type="Number">' . $row['quantity'] . '</Data></Cell>' . "\n";
    $xml .= '  <Cell ss:StyleID="currency"><Data ss:Type="Number">' . $row['price'] . '</Data></Cell>' . "\n";
    $xml .= '  <Cell ss:StyleID="currency"><Data ss:Type="Number">' . $row['line_total'] . '</Data></Cell>' . "\n";
    $xml .= '  <Cell ss:StyleID="currency"><Data ss:Type="Number">' . $row['total_amount'] . '</Data></Cell>' . "\n";
    $xml .= '  <Cell><Data ss:Type="String">' . htmlspecialchars($row['payment_method']) . '</Data></Cell>' . "\n";
    $xml .= '  <Cell><Data ss:Type="String">' . ucfirst(htmlspecialchars($row['order_status'])) . '</Data></Cell>' . "\n";
    $xml .= '  <Cell ss:StyleID="date"><Data ss:Type="DateTime">' . date('Y-m-d\TH:i:s', strtotime($row['order_date'])) . '</Data></Cell>' . "\n";
    $xml .= '</Row>' . "\n";
}

$xml .= '</Table>' . "\n";
$xml .= '</Worksheet>' . "\n";
$xml .= '</Workbook>' . "\n";

echo $xml;
