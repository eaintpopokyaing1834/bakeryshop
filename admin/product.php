<?php
require_once __DIR__ . '/../middleware/admin_check.php';
require_once __DIR__ . '/../config/db.php';

$db = getDB();
$message = $error = '';
$activeTab = $_GET['tab'] ?? 'products';

// Flash message from session (supports PRG pattern)
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
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
    $category_id = (int) $_POST['category_id'];
    $price = (float) $_POST['price'];
    $stock = (int) $_POST['stock'];
    $description = trim($_POST['description']);

    $discount_id = !empty($_POST['discount_id']) ? (int)$_POST['discount_id'] : null;

    if ($id > 0) {
        $db->prepare("UPDATE products SET name=?,category_id=?,price=?,discount_id=?,stock=?,description=?,updated_at=NOW() WHERE id=?")
            ->execute([$name, $category_id, $price, $discount_id, $stock, $description, $id]);
        $productId = $id;
    } else {
        $db->prepare("INSERT INTO products (name,category_id,price,discount_id,stock,description) VALUES (?,?,?,?,?,?)")
            ->execute([$name, $category_id, $price, $discount_id, $stock, $description]);
        $productId = $db->lastInsertId();
    }

    // Handle image uploads
    if (!empty($_FILES['images']['name'][0])) {
        $uploadDir = __DIR__ . '/../uploads/products/';
        if (!is_dir($uploadDir))
            mkdir($uploadDir, 0775, true);

        // When editing, remove old images before adding new ones
        if ($id > 0) {
            $oldImgs = $db->prepare("SELECT image_url FROM product_images WHERE product_id=?");
            $oldImgs->execute([$productId]);
            foreach ($oldImgs->fetchAll() as $old) {
                $path = __DIR__ . '/../' . ltrim($old['image_url'], './');
                if (file_exists($path)) @unlink($path);
            }
            $db->prepare("DELETE FROM product_images WHERE product_id=?")->execute([$productId]);
        }

        foreach ($_FILES['images']['tmp_name'] as $i => $tmp) {
            if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK)
                continue;
            $ext = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            if (!in_array($ext, $allowed))
                continue;
            $filename = 'product_' . $productId . '_' . time() . '_' . $i . '.' . $ext;
            move_uploaded_file($tmp, $uploadDir . $filename);
            $primary = ($i === 0) ? 1 : 0;
            $db->prepare("INSERT INTO product_images (product_id, image_url, is_primary) VALUES (?,?,?)")
                ->execute([$productId, 'uploads/products/' . $filename, $primary]);
        }
    }

    $_SESSION['flash_message'] = 'Product saved successfully!';
    header('Location: ?tab=products');
    exit;
}

// ── Save Category ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_category'])) {
    $id = (int) ($_POST['category_id'] ?? 0);
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    if ($id > 0) {
        $db->prepare("UPDATE categories SET name=?,description=? WHERE id=?")
            ->execute([$name, $description, $id]);
    } else {
        $db->prepare("INSERT INTO categories (name,description) VALUES (?,?)")
            ->execute([$name, $description]);
    }
    $_SESSION['flash_message'] = 'Category saved successfully!';
    header('Location: ?tab=categories');
    exit;
}

// ── Fetch Data ────────────────────────────────────────
$products = $db->query("
    SELECT p.*, c.name AS category_name, d.name AS discount_name, d.type AS discount_type, d.value AS discount_value,
           (SELECT image_url FROM product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) AS primary_image
    FROM products p
    JOIN categories c ON p.category_id = c.id
    LEFT JOIN discounts d ON p.discount_id = d.id
    ORDER BY p.created_at DESC
")->fetchAll();

$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$discounts = $db->query("SELECT * FROM discounts WHERE status=1 ORDER BY name")->fetchAll();

$pageTitle = 'Product Management';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<?php if ($message): ?>
    <div
        class="mb-5 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl text-sm flex items-center gap-2">
        ✅ <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<!-- Tabs -->
<div class="flex gap-2 mb-6">
    <a href="?tab=products"
        class="px-6 py-2.5 rounded-xl font-semibold text-sm transition-colors
        <?= $activeTab === 'products' ? 'bg-rose-500 text-white shadow-md shadow-rose-100' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50' ?>">
        📦 Products
    </a>
    <a href="?tab=categories"
        class="px-6 py-2.5 rounded-xl font-semibold text-sm transition-colors
        <?= $activeTab === 'categories' ? 'bg-rose-500 text-white shadow-md shadow-rose-100' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50' ?>">
        🏷️ Categories
    </a>
</div>

<!-- PRODUCTS TAB -->
<?php if ($activeTab === 'products'): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-bold text-gray-800">All Products <span
                    class="text-gray-400 font-normal text-sm ml-2">(<?= count($products) ?>)</span></h3>
            <button onclick="openProductModal()"
                class="bg-rose-500 hover:bg-rose-600 text-white px-4 py-2 rounded-xl text-sm font-semibold transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Add Product
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-4 text-left">Product</th>
                        <th class="px-6 py-4 text-left">Category</th>
                        <th class="px-6 py-4 text-left">Price</th>
                        <th class="px-6 py-4 text-left">Discount</th>
                        <th class="px-6 py-4 text-left">Stock</th>
                        <th class="px-6 py-4 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($products as $p): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors">
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
                            <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($p['category_name']) ?></td>
                            <td class="px-6 py-4 font-bold text-gray-700 text-sm"><?= number_format($p['price']) ?> MMK</td>
                            <td class="px-6 py-4">
                                <?php if ($p['discount_name']): ?>
                                    <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-green-100 text-green-700">
                                        <?= htmlspecialchars($p['discount_name']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-xs text-gray-400">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    class="text-xs font-bold px-2.5 py-1 rounded-full <?= $p['stock'] < 10 ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' ?>">
                                    <?= $p['stock'] ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <button onclick="editProduct(<?= htmlspecialchars(json_encode($p)) ?>)"
                                        class="text-blue-600 hover:text-blue-800 text-sm font-medium px-3 py-1.5 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                                        Edit
                                    </button>
                                    <button onclick="deleteProduct(<?= $p['id'] ?>, '<?= addslashes($p['name']) ?>')"
                                        class="text-red-600 hover:text-red-800 text-sm font-medium px-3 py-1.5 bg-red-50 rounded-lg hover:bg-red-100 transition-colors">
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php else: // CATEGORIES TAB ?>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-bold text-gray-800">All Categories <span
                    class="text-gray-400 font-normal text-sm ml-2">(<?= count($categories) ?>)</span></h3>
            <button onclick="openCategoryModal()"
                class="bg-rose-500 hover:bg-rose-600 text-white px-4 py-2 rounded-xl text-sm font-semibold transition-colors flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Add Category
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                    <tr>
                        <th class="px-6 py-4 text-left">ID</th>
                        <th class="px-6 py-4 text-left">Name</th>
                        <th class="px-6 py-4 text-left">Description</th>
                        <th class="px-6 py-4 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($categories as $cat): ?>
                        <tr class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-6 py-4 font-mono text-gray-500 text-sm"><?= $cat['id'] ?></td>
                            <td class="px-6 py-4 font-semibold text-gray-700 text-sm"><?= htmlspecialchars($cat['name']) ?></td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <?= htmlspecialchars(substr($cat['description'] ?? '', 0, 80)) ?></td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <button
                                        onclick="editCategory(<?= $cat['id'] ?>, '<?= addslashes($cat['name']) ?>', '<?= addslashes($cat['description'] ?? '') ?>')"
                                        class="text-blue-600 hover:text-blue-800 text-sm font-medium px-3 py-1.5 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">Edit</button>
                                    <button onclick="deleteCategory(<?= $cat['id'] ?>, '<?= addslashes($cat['name']) ?>')"
                                        class="text-red-600 hover:text-red-800 text-sm font-medium px-3 py-1.5 bg-red-50 rounded-lg hover:bg-red-100 transition-colors">Delete</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- Product Modal -->
<div id="productModal"
    class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-sm w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-lg font-bold text-gray-800" id="productModalTitle">Add Product</h3>
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
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Product Name *</label>
                    <input type="text" name="name" id="productName" required placeholder="e.g. Chocolate Birthday Cake"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Category *</label>
                    <select name="category_id" id="productCategory" required
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Price (MMK) *</label>
                    <input type="text" name="price" id="productPrice" required min="0" step="100" placeholder="5000"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Discount</label>
                    <select name="discount_id" id="productDiscount"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
                        <option value="">No Discount</option>
                        <?php foreach ($discounts as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Stock Quantity *</label>
                    <input type="number" name="stock" id="productStock" required min="0" placeholder="50"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                    <textarea name="description" id="productDescription" rows="3" placeholder="Describe the product..."
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm resize-none"></textarea>
                </div>
                <div class="col-span-2" id="existingImagesSection" style="display:none;">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Current Images</label>
                    <div id="existingImages" class="flex flex-wrap gap-3"></div>
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Product Images</label>
                    <input type="file" name="images[]" id="productImages" multiple accept="image/*"
                        class="w-full px-4 py-3 rounded-xl border border-gray-200 text-sm file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-rose-50 file:text-rose-600 hover:file:bg-rose-50">
                    <p class="text-xs text-gray-400 mt-1">Upload new images to replace existing ones. First image will be primary.</p>
                </div>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit"
                    class="flex-1 bg-rose-500 hover:bg-rose-600 text-white font-semibold py-3 rounded-xl transition-colors">Save
                    Product</button>
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
            <h3 class="text-lg font-bold text-gray-800" id="categoryModalTitle">Add Category</h3>
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
                <label class="block text-sm font-semibold text-gray-700 mb-2">Category Name *</label>
                <input type="text" name="name" id="categoryName" required placeholder="e.g. Cupcakes"
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Description</label>
                <textarea name="description" id="categoryDescription" rows="3" placeholder="Brief description..."
                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm resize-none"></textarea>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit"
                    class="flex-1 bg-rose-500 hover:bg-rose-600 text-white font-semibold py-3 rounded-xl transition-colors">Save
                    Category</button>
                <button type="button" onclick="closeModal('categoryModal')"
                    class="px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-600 font-semibold rounded-xl transition-colors">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openProductModal(data = null) {
        document.getElementById('productId').value = data ? data.id : 0;
        document.getElementById('productName').value = data ? data.name : '';
        document.getElementById('productCategory').value = data ? data.category_id : '';
        document.getElementById('productPrice').value = data ? data.price : '';
        document.getElementById('productStock').value = data ? data.stock : '';
        document.getElementById('productDiscount').value = data ? (data.discount_id || '') : '';
        document.getElementById('productDescription').value = data ? (data.description || '') : '';
        document.getElementById('productModalTitle').textContent = data ? 'Edit Product' : 'Add Product';
        document.getElementById('productModal').classList.remove('hidden');
    }
    function editProduct(data) { openProductModal(data); }
    function openCategoryModal() {
        document.getElementById('categoryId').value = 0;
        document.getElementById('categoryName').value = '';
        document.getElementById('categoryDescription').value = '';
        document.getElementById('categoryModalTitle').textContent = 'Add Category';
        document.getElementById('categoryModal').classList.remove('hidden');
    }
    function editCategory(id, name, desc) {
        document.getElementById('categoryId').value = id;
        document.getElementById('categoryName').value = name;
        document.getElementById('categoryDescription').value = desc;
        document.getElementById('categoryModalTitle').textContent = 'Edit Category';
        document.getElementById('categoryModal').classList.remove('hidden');
    }
    function closeModal(id) { document.getElementById(id).classList.add('hidden'); }

    function deleteProduct(id, name) {
        if (!confirm(`Delete product "${name}"? This cannot be undone.`)) return;
        fetch('/sweetheaven/admin/product.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `ajax=delete_product&id=${id}`
        }).then(r => r.json()).then(d => { if (d.success) window.location.href = window.location.href; });
    }

    function deleteCategory(id, name) {
        if (!confirm(`Delete category "${name}"? All products in this category will also be deleted.`)) return;
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
                    badge = '<span class="absolute top-0 left-0 bg-rose-500 text-white text-[10px] px-1.5 py-0.5 rounded-tl-lg rounded-br-lg font-semibold">Primary</span>';
                } else {
                    actions = `<button type="button" onclick="setPrimaryImage(${img.id}, ${productId})" class="absolute -bottom-2 -right-2 w-6 h-6 bg-blue-500 hover:bg-blue-600 text-white rounded-full text-xs flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity shadow-sm" title="Set as primary">★</button>`;
                }
                return `<div class="relative group">
                    <img src="/sweetheaven/${img.image_url}" class="w-20 h-20 object-cover rounded-lg border-2 ${img.is_primary == 1 ? 'border-rose-500' : 'border-gray-200'}">
                    ${badge}
                    <button type="button" onclick="deleteProductImage(${img.id})" class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 hover:bg-red-600 text-white rounded-full text-xs flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity shadow-sm" title="Delete image">×</button>
                    ${actions}
                </div>`;
            }).join('');
        });
    }

    function deleteProductImage(imageId) {
        if (!confirm('Delete this image?')) return;
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