<?php
require_once __DIR__ . '/../middleware/admin_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/lang.php';

$db = getDB();
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$message = $error = '';
$activeTab = $_GET['tab'] ?? 'products';

// Flash message from session (supports PRG pattern)
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}

// ── Block cashiers from any write actions ──────────────
if (!$isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'msg' => 'Cashiers do not have permission to modify products.']);
    exit;
}

// ── AJAX: Handlers ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    $action = $_POST['ajax'];

    if ($action === 'delete_product') {
        $id = (int) $_POST['id'];
        $imgs = $db->prepare("SELECT image_url FROM product_images WHERE product_id=?");
        $imgs->execute([$id]);
        foreach ($imgs->fetchAll() as $img) {
            $path = __DIR__ . '/../' . ltrim($img['image_url'], './');
            if (file_exists($path))
                @unlink($path);
        }
        $db->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
        echo json_encode(['success' => true]);
    } elseif ($action === 'delete_category') {
        $id = (int) $_POST['id'];
        $db->prepare("DELETE FROM categories WHERE id=?")->execute([$id]);
        echo json_encode(['success' => true]);
    } elseif ($action === 'get_product_images') {
        $pid = (int) $_POST['id'];
        $imgs = $db->prepare("SELECT id, image_url, is_primary FROM product_images WHERE product_id=? ORDER BY is_primary DESC, id ASC");
        $imgs->execute([$pid]);
        echo json_encode($imgs->fetchAll());
    } elseif ($action === 'delete_product_image') {
        $imgId = (int) $_POST['image_id'];
        $stmt = $db->prepare("SELECT image_url FROM product_images WHERE id=?");
        $stmt->execute([$imgId]);
        $row = $stmt->fetch();
        if ($row) {
            $path = __DIR__ . '/../' . ltrim($row['image_url'], './');
            if (file_exists($path)) @unlink($path);
            $db->prepare("DELETE FROM product_images WHERE id=?")->execute([$imgId]);
        }
        echo json_encode(['success' => (bool)$row]);
    } elseif ($action === 'set_primary_image') {
        $imgId = (int) $_POST['image_id'];
        $pid = (int) $_POST['product_id'];
        $db->prepare("UPDATE product_images SET is_primary=0 WHERE product_id=?")->execute([$pid]);
        $db->prepare("UPDATE product_images SET is_primary=1 WHERE id=?")->execute([$imgId]);
        echo json_encode(['success' => true]);
    }
    exit;
}

// ── Save Product (Add / Edit) ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    $id = (int) ($_POST['product_id'] ?? 0);
    $name = trim($_POST['name']);
    $name_my = trim($_POST['name_my'] ?? '');
    $category_my = trim($_POST['category_my'] ?? ''); // This will update the categories table
    $category_id = (int) $_POST['category_id'];
    $price = (float) $_POST['price'];
    $stock = (int) $_POST['stock'];
    if ($stock > 100) $stock = 100;
    $description = trim($_POST['description']);
    $description_my = trim($_POST['description_my'] ?? '');

    $discount_id = !empty($_POST['discount_id']) ? (int)$_POST['discount_id'] : null;

    if ($id > 0) {
        $db->prepare("UPDATE products SET name=?,name_my=?,category_id=?,price=?,discount_id=?,stock=?,description=?,description_my=?,updated_at=NOW() WHERE id=?")
            ->execute([$name, $name_my, $category_id, $price, $discount_id, $stock, $description, $description_my, $id]);
        $productId = $id;
    } else {
        $db->prepare("INSERT INTO products (name,name_my,category_id,price,discount_id,stock,description,description_my) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$name, $name_my, $category_id, $price, $discount_id, $stock, $description, $description_my]);
        $productId = $db->lastInsertId();
    }

    // Update the categories table with the provided Myanmar category name
    if ($category_id > 0 && $category_my !== '') {
        $db->prepare("UPDATE categories SET name_my=? WHERE id=?")->execute([$category_my, $category_id]);
    }

    // ── Handle image uploads (appends; never deletes existing images) ──
    $uploadWarning = '';
    if (!empty($_FILES['images']['name'][0])) {
        $uploadDir = __DIR__ . '/../uploads/products/';
        if (!is_dir($uploadDir))
            mkdir($uploadDir, 0775, true);

        $allowed   = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $maxImages = 3;

        // Count how many images this product already has
        $cntStmt = $db->prepare("SELECT COUNT(*) FROM product_images WHERE product_id=?");
        $cntStmt->execute([$productId]);
        $existingCount = (int) $cntStmt->fetchColumn();

        $slots    = max(0, $maxImages - $existingCount); // remaining free slots
        $uploaded = 0;

        foreach ($_FILES['images']['tmp_name'] as $i => $tmp) {
            if ($uploaded >= $slots) break;                              // enforce cap
            if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;

            $ext = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) continue;

            // Unique filename — avoids collisions on simultaneous uploads
            $filename = 'product_' . $productId . '_' . time() . '_' . $i . '.' . $ext;
            if (!move_uploaded_file($tmp, $uploadDir . $filename)) {
                $uploadWarning = ' (Image upload failed — check folder permissions)';
                continue;
            }

            // Only mark as primary when this is the very first image ever for the product
            $isPrimary = ($existingCount === 0 && $uploaded === 0) ? 1 : 0;
            $db->prepare("INSERT INTO product_images (product_id, image_url, is_primary) VALUES (?,?,?)")
               ->execute([$productId, 'uploads/products/' . $filename, $isPrimary]);
            $uploaded++;
        }

        if ($uploaded === 0 && empty($uploadWarning)) {
            $uploadWarning = ' (No valid images were uploaded — please try again)';
        }
    }

    $_SESSION['flash_message'] = 'Product saved successfully!' . $uploadWarning;
    header('Location: ?tab=products');
    exit;
}

// ── Save Category ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_category'])) {
    $id = (int) ($_POST['category_id'] ?? 0);
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $name_my = trim($_POST['name_my'] ?? '');
    $description_my = trim($_POST['description_my'] ?? '');
    if ($id > 0) {
        $db->prepare("UPDATE categories SET name=?,description=?,name_my=?,description_my=? WHERE id=?")
            ->execute([$name, $description, $name_my, $description_my, $id]);
    } else {
        $db->prepare("INSERT INTO categories (name,description,name_my,description_my) VALUES (?,?,?,?)")
            ->execute([$name, $description, $name_my, $description_my]);
    }
    $_SESSION['flash_message'] = 'Category saved successfully!';
    header('Location: ?tab=categories');
    exit;
}

// ── Pagination ────────────────────────────────────────
$perPage       = 10;
$page          = max(1, (int)($_GET['page'] ?? 1));
// Count using LEFT JOIN so products with deleted categories are still counted
$totalProducts = (int)$db->query("SELECT COUNT(*) FROM products p LEFT JOIN categories c ON p.category_id = c.id")->fetchColumn();
$totalProductPages = max(1, (int)ceil($totalProducts / $perPage));
$productPage   = min($page, $totalProductPages);
$productOffset = ($productPage - 1) * $perPage;

$stmt = $db->prepare("
    SELECT p.*, c.name AS category_name, c.name_my AS category_name_my, d.name AS discount_name, d.type AS discount_type, d.value AS discount_value,
           (SELECT image_url FROM product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) AS primary_image
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN discounts d ON p.discount_id = d.id
    ORDER BY p.created_at DESC
    LIMIT $perPage OFFSET $productOffset
");
$stmt->execute();
$products = $stmt->fetchAll();

// Categories Pagination
$totalCategories = (int)$db->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$totalCategoryPages = max(1, (int)ceil($totalCategories / $perPage));
$categoryPage = min($page, $totalCategoryPages);
$categoryOffset = ($categoryPage - 1) * $perPage;

$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll(); // For dropdowns
$pagedCategories = $db->query("SELECT * FROM categories ORDER BY name LIMIT $perPage OFFSET $categoryOffset")->fetchAll(); // For table

$discounts  = $db->query("SELECT * FROM discounts WHERE status=1 ORDER BY name")->fetchAll();

$pageTitle = __('product_page_title');
require_once __DIR__ . '/../includes/admin_header.php';
?>

<?php if ($message): ?>
    <div
        class="mb-5 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl text-sm flex items-center gap-2">
        ✅ <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- Tabs -->
<div class="flex gap-4 mb-6 px-4">
    <a href="?tab=products"
        class="px-6 py-2.5 rounded-xl font-semibold text-sm transition-colors
        <?= $activeTab === 'products' ? 'bg-rose-500 text-white shadow-md shadow-rose-100' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50' ?>">
        📦 <?= __('product_tab_products') ?>
    </a>
    <a href="?tab=categories"
        class="px-6 py-2.5 rounded-xl font-semibold text-sm transition-colors
        <?= $activeTab === 'categories' ? 'bg-rose-500 text-white shadow-md shadow-rose-100' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50' ?>">
        🏷️ <?= __('product_tab_categories') ?>
    </a>
</div>

<!-- PRODUCTS TAB -->
 <div class="px-4">
<?php if ($activeTab === 'products'): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden ">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between ">
            <h3 class="font-bold text-gray-800"><?= __('product_all_products') ?> <span
                    class="text-gray-400 font-normal text-sm ml-2">(<?= localizeNumber($totalProducts) ?> <?= __('admin_total') ?>)</span></h3>
            <?php if ($isAdmin): ?>
            <button onclick="openProductModal()"
                class="bg-rose-500 hover:bg-rose-600 text-white px-4 py-2 rounded-xl text-sm font-semibold transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <?= __('product_add') ?>
            </button>
            <?php endif; ?>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider whitespace-nowrap">
                    <tr>
                        <th class="px-6 py-4 text-left">No.</th>
                        <th class="px-6 py-4 text-left"><?= __('product_col_product') ?></th>
                        <th class="px-6 py-4 text-left"><?= __('product_col_category') ?></th>
                        <th class="px-6 py-4 text-left"><?= __('product_col_price') ?></th>
                        <th class="px-6 py-4 text-left"><?= __('product_col_discount') ?></th>
                        <th class="px-6 py-4 text-left"><?= __('product_col_stock') ?></th>
                        <th class="px-6 py-4 text-left"><?= __('admin_actions') ?></th>
                    </tr>
                </thead>
                <tbody id="productTableBody" class="divide-y divide-gray-50">
                    <?php foreach ($products as $loopIdx => $p): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors" data-product-id="<?= $p['id'] ?>">
                            <td class="px-6 py-4 font-semibold text-gray-500 text-sm row-no"><?= localizeNumber($productOffset + $loopIdx + 1) ?></td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 rounded-xl overflow-hidden bg-rose-50 shrink-0">
                                        <?php if ($p['primary_image']): ?>
                                            <img src="/sweetheaven/<?= htmlspecialchars($p['primary_image']) ?>"
                                                class="w-full h-full object-cover" alt="">
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center text-stone-400 text-lg">🍰
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-700 text-sm"><?= htmlspecialchars($p['name']) ?></p>
                                        <p class="text-xs text-gray-400 line-clamp-1">
                                            <?= htmlspecialchars(substr($p['description'], 0, 60)) ?>...</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars(getLocalizedCategoryName($p, 'category_name', 'category_name_my')) ?></td>
                            <td class="px-6 py-4 font-bold text-gray-700 text-sm"><?= formatPrice($p['price']) ?></td>
                            <td class="px-6 py-4">
                                <?php if ($p['discount_type'] && $p['discount_value']): ?>
                                    <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-green-100 text-green-700">
                                        <?= htmlspecialchars(getLocalizedDiscountLabel($p)) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    class="text-xs font-bold px-2.5 py-1 rounded-full <?= $p['stock'] < 10 ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' ?>">
                                    <?= localizeNumber($p['stock']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($isAdmin): ?>
                                <div class="flex items-center gap-2">
                                    <button onclick="editProduct(<?= htmlspecialchars(json_encode($p)) ?>)"
                                        class="text-blue-600 hover:text-blue-800 text-sm font-medium px-3 py-1.5 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                                        <?= __('admin_edit') ?>
                                    </button>
                                    <button onclick="deleteProduct(<?= $p['id'] ?>, '<?= addslashes($p['name']) ?>')"
                                        class="text-red-600 hover:text-red-800 text-sm font-medium px-3 py-1.5 bg-red-50 rounded-lg hover:bg-red-100 transition-colors">
                                        <?= __('admin_delete') ?>
                                    </button>
                                </div>
                                <?php else: ?>
                                <span class="text-xs text-gray-400"><?= __('product_view_only') ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalProductPages > 1): ?>
        <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between">
            <p class="text-sm text-gray-400"><?= sprintf(__('admin_page_of'), $productPage, $totalProductPages) ?></p>
            <div class="flex items-center gap-1">
                <?php if ($productPage > 1): ?>
                <a href="?tab=products&page=<?= $productPage - 1 ?>" class="px-3 py-1.5 rounded-lg text-sm font-medium bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors">← <?= __('admin_prev') ?></a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $totalProductPages; $i++): ?>
                <a href="?tab=products&page=<?= $i ?>" class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors <?= $i === $productPage ? 'bg-rose-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($productPage < $totalProductPages): ?>
                <a href="?tab=products&page=<?= $productPage + 1 ?>" class="px-3 py-1.5 rounded-lg text-sm font-medium bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors"><?= __('admin_next') ?> →</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

<?php else: // CATEGORIES TAB ?>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-bold text-gray-800"><?= __('product_all_categories') ?> <span
                    class="text-gray-400 font-normal text-sm ml-2">(<?= localizeNumber($totalCategories) ?> <?= __('admin_total') ?>)</span></h3>
            <?php if ($isAdmin): ?>
            <button onclick="openCategoryModal()"
                class="bg-rose-500 hover:bg-rose-600 text-white px-4 py-2 rounded-xl text-sm font-semibold transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <?= __('product_add_category') ?>
            </button>
            <?php endif; ?>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider whitespace-nowrap">
                    <tr>
                        <th class="px-6 py-4 text-left">No.</th>
                        <th class="px-6 py-4 text-left"><?= __('product_col_name') ?></th>
                        <th class="px-6 py-4 text-left"><?= __('product_label_desc') ?></th>
                        <th class="px-6 py-4 text-left"><?= __('admin_actions') ?></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($pagedCategories as $loopIdx => $cat): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-6 py-4 font-semibold text-gray-500 text-sm"><?= localizeNumber($categoryOffset + $loopIdx + 1) ?></td>
                            <td class="px-6 py-4 font-semibold text-gray-700 text-sm"><?= htmlspecialchars(getLocalizedCategoryName($cat)) ?></td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <?= htmlspecialchars(substr($cat['description'] ?? '', 0, 80)) ?></td>
                            <td class="px-6 py-4">
                                <?php if ($isAdmin): ?>
                                <div class="flex items-center gap-2">
                                    <button
                                        onclick="editCategory(<?= $cat['id'] ?>, '<?= addslashes($cat['name']) ?>', '<?= addslashes($cat['description'] ?? '') ?>', '<?= addslashes($cat['name_my'] ?? '') ?>', '<?= addslashes($cat['description_my'] ?? '') ?>')"
                                        class="text-blue-600 hover:text-blue-800 text-sm font-medium px-3 py-1.5 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors"><?= __('admin_edit') ?></button>
                                    <button onclick="deleteCategory(<?= $cat['id'] ?>, '<?= addslashes($cat['name']) ?>')"
                                        class="text-red-600 hover:text-red-800 text-sm font-medium px-3 py-1.5 bg-red-50 rounded-lg hover:bg-red-100 transition-colors"><?= __('admin_delete') ?></button>
                                </div>
                                <?php else: ?>
                                <span class="text-xs text-gray-400"><?= __('product_view_only') ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($totalCategoryPages > 1): ?>
        <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between">
            <p class="text-sm text-gray-400"><?= sprintf(__('admin_page_of'), $categoryPage, $totalCategoryPages) ?></p>
            <div class="flex items-center gap-1">
                <?php if ($categoryPage > 1): ?>
                <a href="?tab=categories&page=<?= $categoryPage - 1 ?>" class="px-3 py-1.5 rounded-lg text-sm font-medium bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors">← <?= __('admin_prev') ?></a>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $totalCategoryPages; $i++): ?>
                <a href="?tab=categories&page=<?= $i ?>" class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors <?= $i === $categoryPage ? 'bg-rose-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($categoryPage < $totalCategoryPages): ?>
                <a href="?tab=categories&page=<?= $categoryPage + 1 ?>" class="px-3 py-1.5 rounded-lg text-sm font-medium bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors"><?= __('admin_next') ?> →</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
</div>
<!-- Product Modal -->
<div id="productModal"
    class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-sm w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-lg font-bold text-gray-800" id="productModalTitle"><?= __('product_add_title') ?></h3>
            <button onclick="closeModal('productModal')" class="text-gray-400 hover:text-gray-600 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <form method="POST" enctype="multipart/form-data" action="?tab=products" class="p-6 space-y-5">
            <input type="hidden" name="save_product" value="1">
            <input type="hidden" name="product_id" id="productId" value="0">
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2"><?= __('product_label_name') ?> *</label>
                    <input type="text" name="name" id="productName" required placeholder="<?= __('product_ph_name') ?>"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2"><?= __('product_label_name') ?> (Myanmar)</label>
                    <input type="text" name="name_my" id="productNameMy" placeholder="အမည် (မြန်မာ)"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2"><?= __('product_label_category') ?> *</label>
                    <select name="category_id" id="productCategory" required
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars(getLocalizedCategoryName($cat)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2"><?= __('product_label_category') ?> (Myanmar)</label>
                    <input type="text" name="category_my" id="productCategoryMy" placeholder="အမျိုးအစား (မြန်မာ)"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2"><?= __('product_label_price') ?> *</label>
                    <input type="text" name="price" id="productPrice" required min="0" step="100" placeholder="<?= __('product_ph_price') ?>"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2"><?= __('product_label_discount') ?></label>
                    <select name="discount_id" id="productDiscount"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
                        <option value=""><?= __('product_no_discount') ?></option>
                        <?php foreach ($discounts as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2"><?= __('product_label_stock') ?> *</label>
                    <input type="number" name="stock" id="productStock" required min="0" max="100" oninput="if(this.value > 100) this.value = 100;" placeholder="<?= __('product_ph_stock') ?>"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2"><?= __('product_label_desc') ?></label>
                    <textarea name="description" id="productDescription" rows="3" placeholder="<?= __('product_ph_desc') ?>"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm resize-none"></textarea>
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2"><?= __('product_label_desc') ?> (Myanmar)</label>
                    <textarea name="description_my" id="productDescriptionMy" rows="3" placeholder="ဖော်ပြချက် (မြန်မာ)"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm resize-none"></textarea>
                </div>
                <div class="col-span-2" id="existingImagesSection" style="display:none;">
                    <label class="block text-sm font-semibold text-gray-700 mb-2"><?= __('product_label_current') ?></label>
                    <div id="existingImages" class="flex flex-wrap gap-3"></div>
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <?= __('product_label_images') ?>
                        <span class="text-gray-400 font-normal text-xs ml-1">(max 3 images)</span>
                    </label>

                    <!-- Hidden real file input -->
                    <input type="file" name="images[]" id="productImages" multiple accept="image/jpeg,image/png,image/webp,image/gif" class="sr-only">

                    <!-- Drag-and-drop upload zone -->
                    <div id="uploadZone"
                         onclick="document.getElementById('productImages').click()"
                         ondragover="event.preventDefault();this.classList.add('border-rose-400','bg-rose-50')"
                         ondragleave="this.classList.remove('border-rose-400','bg-rose-50')"
                         ondrop="handleDrop(event)"
                         class="relative w-full border-2 border-dashed border-gray-200 rounded-xl p-6 text-center cursor-pointer hover:border-rose-300 hover:bg-rose-50/50 transition-all duration-200">
                        <div id="uploadPlaceholder">
                            <svg class="mx-auto w-10 h-10 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <p class="text-sm text-gray-500">Click or drag &amp; drop images here</p>
                            <p class="text-xs text-gray-400 mt-1">JPG, PNG, WebP, GIF &mdash; up to 3 images</p>
                        </div>
                    </div>

                    <!-- Live preview thumbnails -->
                    <div id="imagePreviewWrap" class="flex flex-wrap gap-3 mt-3 hidden"></div>

                    <!-- Validation message -->
                    <p id="imageCountMsg" class="text-xs text-rose-500 mt-1 hidden"></p>
                    <p class="text-xs text-gray-400 mt-1"><?= __('product_image_help') ?></p>
                </div>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit"
                    class="flex-1 bg-rose-500 hover:bg-rose-600 text-white font-semibold py-3 rounded-xl transition-colors"><?= __('product_save') ?></button>
                <button type="button" onclick="closeModal('productModal')"
                    class="px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-600 font-semibold rounded-xl transition-colors">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Category Modal -->
<div id="categoryModal"
    class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-sm w-full max-w-md">
        <div class="p-6 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-lg font-bold text-gray-800" id="categoryModalTitle"><?= __('product_cat_add_title') ?></h3>
            <button onclick="closeModal('categoryModal')" class="text-gray-400 hover:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <form method="POST" action="?tab=categories" class="p-6 space-y-4">
            <input type="hidden" name="save_category" value="1">
            <input type="hidden" name="category_id" id="categoryId" value="0">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2"><?= __('product_cat_label_name') ?> (EN) *</label>
                <input type="text" name="name" id="categoryName" required placeholder="<?= __('product_cat_ph_name') ?>"
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2"><?= __('product_cat_label_name') ?> (Myanmar)</label>
                <input type="text" name="name_my" id="categoryNameMy" placeholder="အမည် (မြန်မာ)"
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2"><?= __('product_label_desc') ?> (EN)</label>
                <textarea name="description" id="categoryDescription" rows="3" placeholder="<?= __('product_cat_ph_desc') ?>"
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm resize-none"></textarea>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2"><?= __('product_label_desc') ?> (Myanmar)</label>
                <textarea name="description_my" id="categoryDescriptionMy" rows="3" placeholder="ဖော်ပြချက် (မြန်မာ)"
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm resize-none"></textarea>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit"
                    class="flex-1 bg-rose-500 hover:bg-rose-600 text-white font-semibold py-3 rounded-xl transition-colors"><?= __('product_cat_save') ?></button>
                <button type="button" onclick="closeModal('categoryModal')"
                    class="px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-600 font-semibold rounded-xl transition-colors">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    const T = <?= json_encode([
        'editProduct' => __('product_edit_title'),
        'addProduct' => __('product_add_title'),
        'addCategory' => __('product_cat_add_title'),
        'editCategory' => __('product_cat_edit_title'),
        'confirmDelete' => __('product_confirm_delete'),
        'confirmCatDelete' => __('product_confirm_cat_delete'),
        'confirmDeleteImage' => __('product_delete_image'),
        'primary' => __('product_primary'),
        'setPrimary' => __('product_set_primary'),
        'deleteImage' => __('product_delete_image'),
    ]) ?>;

    // ── Image preview & drag-drop ─────────────────────────
    // Uses a simple approach: keep selected files in a DataTransfer object
    // and always read from it on render. We do NOT reset input.value after
    // selection — instead we let the browser keep the real file reference.
    const MAX_IMAGES = 3;
    let selectedFiles = []; // plain array of File objects for previews
    let fileInputDT = new DataTransfer(); // keeps real File refs for the input

    function syncInputFiles() {
        // Rebuild DataTransfer from selectedFiles so the input always reflects current state
        fileInputDT = new DataTransfer();
        selectedFiles.forEach(f => fileInputDT.items.add(f));
        try {
            document.getElementById('productImages').files = fileInputDT.files;
        } catch(e) {
            // Fallback: browser may block programmatic assignment — files still in selectedFiles
        }
    }

    function renderPreviews() {
        const wrap = document.getElementById('imagePreviewWrap');
        const placeholder = document.getElementById('uploadPlaceholder');
        const msg = document.getElementById('imageCountMsg');

        wrap.innerHTML = '';
        if (selectedFiles.length === 0) {
            wrap.classList.add('hidden');
            placeholder.style.display = '';
            msg.classList.add('hidden');
            return;
        }
        placeholder.style.display = 'none';
        wrap.classList.remove('hidden');

        selectedFiles.forEach((file, idx) => {
            const url = URL.createObjectURL(file);
            const div = document.createElement('div');
            div.className = 'relative group';
            div.innerHTML = `
                <img src="${url}" class="w-20 h-20 object-cover rounded-xl border-2 border-gray-200 shadow-sm">
                <span class="absolute bottom-0 left-0 right-0 bg-black/40 text-white text-[9px] text-center rounded-b-xl py-0.5 truncate px-1">${file.name}</span>
                <button type="button" onclick="removePreview(${idx})"
                    class="absolute -top-2 -right-2 w-5 h-5 bg-red-500 hover:bg-red-600 text-white rounded-full text-xs flex items-center justify-center shadow opacity-0 group-hover:opacity-100 transition-opacity">&times;</button>
            `;
            wrap.appendChild(div);
        });

        const pid = parseInt(document.getElementById('productId').value);
        if (pid > 0) {
            msg.textContent = `${selectedFiles.length} new image(s) selected. They will be added to existing images (max 3 total).`;
        } else {
            msg.textContent = `${selectedFiles.length} / ${MAX_IMAGES} image(s) selected.`;
        }
        msg.classList.remove('hidden');
        msg.className = msg.className.replace('text-rose-500', selectedFiles.length > MAX_IMAGES ? 'text-rose-500' : 'text-blue-500');
    }

    function addFiles(fileList) {
        const msg = document.getElementById('imageCountMsg');
        const pid = parseInt(document.getElementById('productId').value);
        const isEdit = pid > 0;
        const existingShown = document.querySelectorAll('#existingImages img').length;
        const available = isEdit ? MAX_IMAGES - existingShown : MAX_IMAGES;

        Array.from(fileList).forEach(file => {
            if (selectedFiles.length >= available) {
                msg.textContent = `Maximum ${MAX_IMAGES} images allowed per product. Some files were skipped.`;
                msg.classList.remove('hidden');
                return;
            }
            if (!file.type.startsWith('image/')) return;
            selectedFiles.push(file);
        });
        syncInputFiles();
        renderPreviews();
    }

    function removePreview(idx) {
        selectedFiles.splice(idx, 1);
        syncInputFiles();
        renderPreviews();
    }

    function handleDrop(event) {
        event.preventDefault();
        document.getElementById('uploadZone').classList.remove('border-rose-400', 'bg-rose-50');
        addFiles(event.dataTransfer.files);
    }

    // On file input change, add files WITHOUT resetting .value
    // (resetting .value after DataTransfer assignment breaks submission in Firefox/Safari)
    document.getElementById('productImages').addEventListener('change', function () {
        addFiles(this.files);
        // Do NOT do this.value = '' — it clears the file reference and breaks upload
    });

    // Intercept form submit to guarantee files are attached via a hidden clone
    // This is the most reliable cross-browser approach
    document.querySelector('#productModal form').addEventListener('submit', function(e) {
        if (selectedFiles.length === 0) return; // no files to attach

        // Remove any previously injected hidden containers
        this.querySelectorAll('.js-file-clone-wrap').forEach(el => el.remove());

        // Create one individual <input type="file"> per selected file
        // and inject it so the browser submits the real File objects
        // Note: we can't programmatically assign .files to a new input directly,
        // so we ensure the main input has them via DataTransfer sync above.
        // The main input already has the files from syncInputFiles().
        // Nothing extra needed if DataTransfer worked. But as a fallback,
        // we keep selectedFiles accessible for verification.
    });

    function resetUploadZone() {
        selectedFiles = [];
        syncInputFiles();
        renderPreviews();
    }

    // ── Modal open/close ─────────────────────────────────
    function openProductModal(data = null) {
        document.getElementById('productId').value = data ? data.id : 0;
        document.getElementById('productName').value = data ? data.name : '';
        document.getElementById('productNameMy').value = data ? (data.name_my || '') : '';
        document.getElementById('productCategory').value = data ? data.category_id : '';
        document.getElementById('productCategoryMy').value = data ? (data.category_name_my || '') : '';
        document.getElementById('productPrice').value = data ? data.price : '';
        document.getElementById('productStock').value = data ? data.stock : '';
        document.getElementById('productDiscount').value = data ? (data.discount_id || '') : '';
        document.getElementById('productDescription').value = data ? (data.description || '') : '';
        document.getElementById('productDescriptionMy').value = data ? (data.description_my || '') : '';
        document.getElementById('productModalTitle').textContent = data ? T.editProduct : T.addProduct;
        resetUploadZone();
        // Hide existing images section for new products
        if (!data) document.getElementById('existingImagesSection').style.display = 'none';
        document.getElementById('productModal').classList.remove('hidden');
    }
    function editProduct(data) { openProductModal(data); }
    function openCategoryModal() {
        document.getElementById('categoryId').value = 0;
        document.getElementById('categoryName').value = '';
        document.getElementById('categoryNameMy').value = '';
        document.getElementById('categoryDescription').value = '';
        document.getElementById('categoryDescriptionMy').value = '';
        document.getElementById('categoryModalTitle').textContent = T.addCategory;
        document.getElementById('categoryModal').classList.remove('hidden');
    }
    function editCategory(id, name, desc, nameMy, descMy) {
        document.getElementById('categoryId').value = id;
        document.getElementById('categoryName').value = name;
        document.getElementById('categoryNameMy').value = nameMy;
        document.getElementById('categoryDescription').value = desc;
        document.getElementById('categoryDescriptionMy').value = descMy;
        document.getElementById('categoryModalTitle').textContent = T.editCategory;
        document.getElementById('categoryModal').classList.remove('hidden');
    }
    function closeModal(id) {
        document.getElementById(id).classList.add('hidden');
        if (id === 'productModal') resetUploadZone();
    }

    function deleteProduct(id, name) {
        if (!confirm(T.confirmDelete.replace('{name}', name))) return;
        fetch('/sweetheaven/admin/product.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `ajax=delete_product&id=${id}`
        }).then(r => r.json()).then(d => {
            if (!d.success) return;
            // Remove the deleted row from the DOM
            const row = document.querySelector(`tr[data-product-id="${id}"]`);
            if (row) row.remove();
            // Re-number all remaining rows sequentially
            reNumberRows();
            // Update the total count badge
            const badge = document.querySelector('h3 span.text-gray-400');
            if (badge) {
                const current = parseInt(badge.textContent.replace(/\D/g, ''), 10);
                if (!isNaN(current)) badge.textContent = `(${current - 1} total)`;
            }
            // If table is now empty, reload to show the empty state
            const tbody = document.getElementById('productTableBody');
            if (tbody && tbody.querySelectorAll('tr').length === 0) {
                window.location.href = window.location.href;
            }
        });
    }

    function reNumberRows() {
        // Read the starting number from the first row's current value so
        // pagination offset is preserved (e.g. page 2 starts at 11)
        const tbody = document.getElementById('productTableBody');
        if (!tbody) return;
        const firstCell = tbody.querySelector('.row-no');
        const startNum = firstCell ? parseInt(firstCell.textContent, 10) : 1;
        tbody.querySelectorAll('tr').forEach((tr, idx) => {
            const cell = tr.querySelector('.row-no');
            if (cell) cell.textContent = startNum + idx;
        });
    }

    function deleteCategory(id, name) {
        if (!confirm(T.confirmCatDelete.replace('{name}', name))) return;
        fetch('/sweetheaven/admin/product.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `ajax=delete_category&id=${id}`
        }).then(r => r.json()).then(d => { if (d.success) window.location.href = window.location.href; });
    }

    // ── Image management ─────────────────────────────────
    function fetchProductImages(productId) {
        const section = document.getElementById('existingImagesSection');
        const container = document.getElementById('existingImages');
        if (!productId) { section.style.display = 'none'; return; }
        fetch('/sweetheaven/admin/product.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `ajax=get_product_images&id=${productId}`
        }).then(r => r.json()).then(images => {
            if (images.length === 0) { section.style.display = 'none'; return; }
            section.style.display = 'block';
            container.innerHTML = images.map(img => {
                let badge = '', actions = '';
                if (img.is_primary == 1) {
                    badge = `<span class="absolute top-0 left-0 bg-rose-500 text-white text-[10px] px-1.5 py-0.5 rounded-tl-lg rounded-br-lg font-semibold">${T.primary}</span>`;
                } else {
                    actions = `<button type="button" onclick="setPrimaryImage(${img.id}, ${productId})" class="absolute -bottom-2 -right-2 w-6 h-6 bg-blue-500 hover:bg-blue-600 text-white rounded-full text-xs flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity shadow-sm" title="${T.setPrimary}">★</button>`;
                }
                return `<div class="relative group">
                    <img src="/sweetheaven/${img.image_url}" class="w-20 h-20 object-cover rounded-lg border-2 ${img.is_primary == 1 ? 'border-rose-500' : 'border-gray-200'}">
                    ${badge}
                    <button type="button" onclick="deleteProductImage(${img.id})" class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 hover:bg-red-600 text-white rounded-full text-xs flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity shadow-sm" title="${T.deleteImage}">×</button>
                    ${actions}
                </div>`;
            }).join('');
        });
    }

    function deleteProductImage(imageId) {
        if (!confirm(T.confirmDeleteImage)) return;
        fetch('/sweetheaven/admin/product.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `ajax=delete_product_image&image_id=${imageId}`
        }).then(r => r.json()).then(d => {
            if (d.success) fetchProductImages(document.getElementById('productId').value);
        });
    }

    function setPrimaryImage(imageId, productId) {
        fetch('/sweetheaven/admin/product.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `ajax=set_primary_image&image_id=${imageId}&product_id=${productId}`
        }).then(r => r.json()).then(d => {
            if (d.success) fetchProductImages(productId);
        });
    }

    // Override editProduct to load images
    const origEditProduct = editProduct;
    editProduct = function(data) {
        origEditProduct(data);
        fetchProductImages(data.id);
    };

    // Close modals on backdrop click
    document.querySelectorAll('#productModal, #categoryModal').forEach(m => {
        m.addEventListener('click', e => { if (e.target === m) closeModal(m.id); });
    });
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>