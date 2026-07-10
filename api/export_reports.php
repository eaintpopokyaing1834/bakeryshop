<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../middleware/admin_check.php';
require_once __DIR__ . '/../config/db.php';

$db = getDB();

$period = $_GET['period'] ?? 'monthly';
$categoryId = isset($_GET['category_id']) ? (int) $_GET['category_id'] : 0;

// ── Date Range Calculation ──────────────────────────
$startDate = null;
$endDate = date('Y-m-d 23:59:59');

switch ($period) {
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
if ($period === 'custom' && !empty($_GET['end_date'])) {
    $where .= " AND o.order_date <= ?";
    $params[] = date('Y-m-d 23:59:59', strtotime($_GET['end_date']));
} elseif ($period !== 'custom' && $endDate) {
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

// ── Fetch all order items ───────────────────────────
try {
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
} catch (Exception $e) {
    $rows = [];
}

// ── Generate filename ───────────────────────────────
switch ($period) {
    case 'monthly':
        $filename = "monthly_report_" . date('F_Y') . ".xls";
        break;
    case 'yearly':
        $filename = "yearly_report_" . date('Y') . ".xls";
        break;
    case 'custom':
        $s = $_GET['start_date'] ?? 'start';
        $e = $_GET['end_date'] ?? 'end';
        $filename = "custom_report_{$s}_to_{$e}.xls";
        break;
    default:
        $filename = "report_" . date('Y-m-d') . ".xls";
}

if ($categoryId > 0) {
    $catStmt = $db->prepare("SELECT name FROM categories WHERE id = ?");
    $catStmt->execute([$categoryId]);
    $catName = $catStmt->fetchColumn();
    $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($catName ?? 'category'));
    $filename = $safeName . '_' . $filename;
}

// ── Generate Excel XML (SpreadsheetML) ──────────────
ob_clean();
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
echo ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";

// Styles
echo '<Styles>' . "\n";
echo '  <Style ss:ID="Default" ss:Name="Normal"><Alignment ss:Vertical="Center"/><Font ss:FontName="Calibri" ss:Size="11"/></Style>' . "\n";
echo '  <Style ss:ID="hdr"><Font ss:FontName="Calibri" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#2563EB" ss:Pattern="Solid"/><Alignment ss:Vertical="Center" ss:Horizontal="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#1D4ED8"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#1D4ED8"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#1D4ED8"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#1D4ED8"/></Borders></Style>' . "\n";
echo '  <Style ss:ID="cell"><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/></Borders></Style>' . "\n";
echo '  <Style ss:ID="num"><NumberFormat ss:Format="#,##0"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CCCCCC"/></Borders></Style>' . "\n";
echo '</Styles>' . "\n";

// Worksheet
echo '<Worksheet ss:Name="Report">' . "\n";
echo '<Table ss:DefaultRowHeight="20">' . "\n";
echo '<Column ss:Width="80"/><Column ss:Width="140"/><Column ss:Width="160"/><Column ss:Width="120"/><Column ss:Width="70"/><Column ss:Width="100"/><Column ss:Width="100"/><Column ss:Width="100"/><Column ss:Width="110"/><Column ss:Width="90"/><Column ss:Width="110"/>' . "\n";

// Header row
$headers = ['Order ID', 'Customer Name', 'Product Name', 'Category', 'Qty', 'Price', 'Line Total', 'Total', 'Payment', 'Status', 'Date'];
echo '<Row ss:AutoFitHeight="0" ss:Height="24">';
foreach ($headers as $h) {
    echo '<Cell ss:StyleID="hdr"><Data ss:Type="String">' . htmlspecialchars($h) . '</Data></Cell>';
}
echo '</Row>' . "\n";

// Data rows
foreach ($rows as $row) {
    echo '<Row>';
    echo '<Cell ss:StyleID="cell"><Data ss:Type="String">#' . str_pad($row['order_id'], 4, '0', STR_PAD_LEFT) . '</Data></Cell>';
    echo '<Cell ss:StyleID="cell"><Data ss:Type="String">' . htmlspecialchars($row['customer_name']) . '</Data></Cell>';
    echo '<Cell ss:StyleID="cell"><Data ss:Type="String">' . htmlspecialchars($row['product_name']) . '</Data></Cell>';
    echo '<Cell ss:StyleID="cell"><Data ss:Type="String">' . htmlspecialchars($row['category']) . '</Data></Cell>';
    echo '<Cell ss:StyleID="num"><Data ss:Type="Number">' . $row['quantity'] . '</Data></Cell>';
    echo '<Cell ss:StyleID="num"><Data ss:Type="Number">' . $row['price'] . '</Data></Cell>';
    echo '<Cell ss:StyleID="num"><Data ss:Type="Number">' . $row['line_total'] . '</Data></Cell>';
    echo '<Cell ss:StyleID="num"><Data ss:Type="Number">' . $row['total_amount'] . '</Data></Cell>';
    echo '<Cell ss:StyleID="cell"><Data ss:Type="String">' . htmlspecialchars($row['payment_method']) . '</Data></Cell>';
    echo '<Cell ss:StyleID="cell"><Data ss:Type="String">' . ucfirst(htmlspecialchars($row['order_status'])) . '</Data></Cell>';
    echo '<Cell ss:StyleID="cell"><Data ss:Type="String">' . date('Y-m-d', strtotime($row['order_date'])) . '</Data></Cell>';
    echo '</Row>' . "\n";
}

echo '</Table>' . "\n";
echo '</Worksheet>' . "\n";
echo '</Workbook>';
