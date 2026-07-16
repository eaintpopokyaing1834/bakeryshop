<?php
// api/cart.php — Session-based cart AJAX handler
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
$db = getDB();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

function cartCount(): int {
    $count = 0;
    foreach ($_SESSION['cart'] ?? [] as $item) $count += $item['qty'];
    return $count;
}

switch ($action) {
    case 'add':
        if (!isset($_SESSION['user_id']) || in_array($_SESSION['role'] ?? '', ['admin', 'cashier'])) {
            echo json_encode(['success' => false, 'redirect' => true]);
            exit;
        }
        $productId = (int)($_POST['product_id'] ?? 0);
        $qty       = max(1, (int)($_POST['qty'] ?? 1));

        // Verify product exists and has stock
        $product = $db->prepare("SELECT id, name, price, stock FROM products WHERE id=?");
        $product->execute([$productId]);
        $product = $product->fetch();
        if (!$product) { echo json_encode(['success' => false, 'msg' => 'Product not found']); exit; }

        if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

        if (isset($_SESSION['cart'][$productId])) {
            $newQty = $_SESSION['cart'][$productId]['qty'] + $qty;
            $_SESSION['cart'][$productId]['qty'] = min($newQty, $product['stock']);
        } else {
            $_SESSION['cart'][$productId] = [
                'product_id' => $productId,
                'name'       => $product['name'],
                'price'      => $product['price'],
                'qty'        => min($qty, $product['stock']),
            ];
        }
        echo json_encode(['success' => true, 'cart_count' => cartCount()]);
        break;

    case 'remove':
        $productId = (int)($_POST['product_id'] ?? 0);
        unset($_SESSION['cart'][$productId]);
        $total = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $_SESSION['cart'] ?? []));
        echo json_encode(['success' => true, 'cart_count' => cartCount(), 'total' => $total]);
        break;

    case 'update':
        $productId = (int)($_POST['product_id'] ?? 0);
        $qty       = (int)($_POST['qty'] ?? 0);
        if ($qty <= 0) {
            unset($_SESSION['cart'][$productId]);
        } else {
            // Check stock
            $stock = $db->prepare("SELECT stock FROM products WHERE id=?");
            $stock->execute([$productId]);
            $stock = (int)$stock->fetchColumn();
            $_SESSION['cart'][$productId]['qty'] = min($qty, $stock);
        }
        $subtotal = 0;
        if (isset($_SESSION['cart'][$productId])) {
            $subtotal = $_SESSION['cart'][$productId]['price'] * $_SESSION['cart'][$productId]['qty'];
        }
        $total = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $_SESSION['cart'] ?? []));
        echo json_encode(['success' => true, 'cart_count' => cartCount(), 'subtotal' => $subtotal, 'total' => $total]);
        break;

    case 'get':
        echo json_encode(['success' => true, 'cart' => $_SESSION['cart'] ?? [], 'cart_count' => cartCount()]);
        break;

    case 'clear':
        $_SESSION['cart'] = [];
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'msg' => 'Invalid action']);
}
