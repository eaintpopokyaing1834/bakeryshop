<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';

$db = getDB();

// ── Filters ──────────────────────────────────────────
$categoryId = (int)($_GET['category_id'] ?? 0);
$search     = trim($_GET['search'] ?? '');
$sort       = $_GET['sort'] ?? 'newest';
$minPrice   = (int)($_GET['min_price'] ?? 0);
$maxPrice   = (int)($_GET['max_price'] ?? 999999);

$where  = ["p.stock > 0"];
$params = [];
if ($categoryId > 0) { $where[] = "p.category_id = ?"; $params[] = $categoryId; }
if ($search !== '') { $where[] = "(p.name LIKE ? OR p.description LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($minPrice > 0) { $where[] = "p.price >= ?"; $params[] = $minPrice; }
if ($maxPrice < 999999) { $where[] = "p.price <= ?"; $params[] = $maxPrice; }

$sortSQL = match($sort) {
    'price_asc'  => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'popular'    => 'total_sold DESC',
    default      => 'p.created_at DESC',
};

$products = $db->prepare("
    SELECT p.*,
           c.name AS category_name,
           (SELECT image_url FROM product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) AS primary_image,
           COALESCE(AVG(r.rating),0) AS avg_rating,
           COUNT(DISTINCT oi.id) AS total_sold
    FROM products p
    JOIN categories c ON p.category_id = c.id
    LEFT JOIN reviews r ON r.product_id = p.id
    LEFT JOIN order_items oi ON oi.product_id = p.id
    WHERE " . implode(' AND ', $where) . "
    GROUP BY p.id
    ORDER BY $sortSQL
");
$products->execute($params);
$products = $products->fetchAll();

$categories       = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$currentCategory  = $categoryId ? $db->prepare("SELECT name FROM categories WHERE id=?") : null;
if ($currentCategory) { $currentCategory->execute([$categoryId]); $currentCategory = $currentCategory->fetchColumn(); }

$isLoggedIn = isset($_SESSION['user_id']);
$isAdmin = ($isLoggedIn && $_SESSION['role'] === 'admin');

// Wishlist IDs for current user
$wishlistIds = [];
if ($isLoggedIn && !$isAdmin) {
    $wl = $db->prepare("SELECT product_id FROM wishlist WHERE user_id=?");
    $wl->execute([$_SESSION['user_id']]);
    $wishlistIds = array_column($wl->fetchAll(), 'product_id');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products — Sweet Heaven Bakery</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>* { font-family: 'Poppins', sans-serif; }</style>
</head>
<body class="bg-gray-50">
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="max-w-7xl mx-auto px-6 py-10">
    <div class="flex flex-col lg:flex-row gap-8">

        <!-- Sidebar Filters -->
        <aside class="lg:w-64 shrink-0">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sticky top-24 space-y-6">

                <!-- Categories -->
                <div>
                    <h4 class="font-bold text-gray-700 mb-4 text-sm uppercase tracking-wider">Categories</h4>
                    <ul class="space-y-1">
                        <li>
                            <a href="/sweetheaven/user/products.php?search=<?= urlencode($search) ?>&sort=<?= $sort ?>"
                               class="flex items-center justify-between px-3 py-2.5 rounded-xl text-sm transition-colors
                               <?= $categoryId === 0 ? 'bg-rose-500 text-white font-semibold' : 'text-gray-600 hover:bg-rose-50 hover:text-rose-500' ?>">
                               <span>All Products</span>
                            </a>
                        </li>
                        <?php foreach ($categories as $cat): ?>
                        <li>
                            <a href="/sweetheaven/user/products.php?category_id=<?= $cat['id'] ?>&search=<?= urlencode($search) ?>&sort=<?= $sort ?>"
                               class="flex items-center justify-between px-3 py-2.5 rounded-xl text-sm transition-colors
                               <?= $categoryId === (int)$cat['id'] ? 'bg-rose-500 text-white font-semibold' : 'text-gray-600 hover:bg-rose-50 hover:text-rose-500' ?>">
                               <span><?= htmlspecialchars($cat['name']) ?></span>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Price Filter -->
                <div>
                    <h4 class="font-bold text-gray-700 mb-4 text-sm uppercase tracking-wider">Price Range</h4>
                    <form method="GET" id="priceForm">
                        <input type="hidden" name="category_id" value="<?= $categoryId ?>">
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                        <input type="hidden" name="sort" value="<?= $sort ?>">
                        <div class="space-y-3">
                            <div>
                                <label class="text-xs text-gray-500 mb-1 block">Min Price (MMK)</label>
                                <input type="number" name="min_price" value="<?= $minPrice ?>" step="500" min="0"
                                    class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300">
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 mb-1 block">Max Price (MMK)</label>
                                <input type="number" name="max_price" value="<?= $maxPrice < 999999 ? $maxPrice : '' ?>" step="500" min="0"
                                    class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300">
                            </div>
                            <button type="submit" class="w-full bg-rose-500 text-white py-2 rounded-xl text-sm font-semibold hover:bg-rose-600 transition-colors">Apply Filter</button>
                        </div>
                    </form>
                </div>

                <!-- Clear Filters -->
                <?php if ($categoryId || $search || $minPrice || $maxPrice < 999999): ?>
                <a href="/sweetheaven/user/products.php" class="block text-center text-sm text-red-500 hover:text-red-700 font-medium">✕ Clear Filters</a>
                <?php endif; ?>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1">
            <!-- Toolbar -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-6 flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between">
                <div>
                    <h1 class="font-bold text-gray-800 text-lg">
                        <?= $currentCategory ? htmlspecialchars($currentCategory) : ($search ? "Search: \"$search\"" : 'All Products') ?>
                    </h1>
                    <p class="text-sm text-gray-400"><?= count($products) ?> product<?= count($products) !== 1 ? 's' : '' ?> found</p>
                </div>
                <div class="flex items-center gap-3">
                    <!-- Search -->
                    <form method="GET" class="flex gap-2">
                        <input type="hidden" name="category_id" value="<?= $categoryId ?>">
                        <input type="hidden" name="sort" value="<?= $sort ?>">
                        <input type="search" name="search" placeholder="🔍 Search products..."
                            value="<?= htmlspecialchars($search) ?>"
                            class="border border-gray-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300 w-48">
                    </form>
                    <!-- Sort -->
                    <form method="GET" id="sortForm">
                        <input type="hidden" name="category_id" value="<?= $categoryId ?>">
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                        <select name="sort" onchange="this.form.submit()"
                            class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300">
                            <option value="newest" <?= $sort==='newest'?'selected':'' ?>>Newest</option>
                            <option value="popular" <?= $sort==='popular'?'selected':'' ?>>Most Popular</option>
                            <option value="price_asc" <?= $sort==='price_asc'?'selected':'' ?>>Price: Low to High</option>
                            <option value="price_desc" <?= $sort==='price_desc'?'selected':'' ?>>Price: High to Low</option>
                        </select>
                    </form>
                </div>
            </div>

            <!-- Product Grid -->
            <?php if (empty($products)): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-16 text-center">
                <p class="text-5xl mb-4">🔍</p>
                <h3 class="text-xl font-bold text-gray-700 mb-2">No products found</h3>
                <p class="text-gray-400 text-sm mb-6">Try adjusting your filters or search terms.</p>
                <a href="/sweetheaven/user/products.php" class="bg-rose-500 text-white px-6 py-3 rounded-full text-sm font-semibold hover:bg-rose-600 transition-colors">Clear Filters</a>
            </div>
            <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6">
                <?php foreach ($products as $product): ?>
                <?php
                $imgSrc   = $product['primary_image'] ? '/sweetheaven/' . $product['primary_image'] : '/sweetheaven/images/maincake.jpg';
                $isWished = in_array($product['id'], $wishlistIds);
                $stars    = round($product['avg_rating']);
                ?>
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden group hover:shadow-md hover:-translate-y-1 transition-all duration-300">
                    <div class="relative h-52 overflow-hidden bg-rose-50">
                        <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($product['name']) ?>"
                             class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
                        <button onclick="toggleWishlist(<?= $product['id'] ?>, this)"
                            class="absolute top-3 right-3 w-9 h-9 rounded-full <?= $isWished ? 'bg-rose-500 text-white' : 'bg-white text-gray-400' ?> shadow-md flex items-center justify-center hover:bg-rose-500 hover:text-white transition-all duration-200">
                            <svg class="w-4 h-4" fill="<?= $isWished ? 'currentColor' : 'none' ?>" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                        </button>
                        <?php if ($product['stock'] === 0): ?>
                        <div class="absolute inset-0 bg-black/40 flex items-center justify-center">
                            <span class="bg-red-600 text-white text-sm font-bold px-4 py-2 rounded-full">Out of Stock</span>
                        </div>
                        <?php elseif ($product['stock'] < 10): ?>
                        <div class="absolute top-3 left-3 bg-amber-500 text-white text-xs font-bold px-2 py-1 rounded-full">Only <?= $product['stock'] ?> left</div>
                        <?php endif; ?>
                    </div>

                    <div class="p-5">
                        <p class="text-xs text-rose-400 font-semibold mb-1"><?= htmlspecialchars($product['category_name']) ?></p>
                        <a href="/sweetheaven/user/product_detail.php?id=<?= $product['id'] ?>">
                            <h3 class="font-bold text-gray-800 mb-2 hover:text-rose-500 transition-colors line-clamp-1"><?= htmlspecialchars($product['name']) ?></h3>
                        </a>
                        <p class="text-xs text-gray-400 mb-3 line-clamp-2"><?= htmlspecialchars($product['description']) ?></p>

                        <div class="flex items-center gap-1 mb-4">
                            <?php for ($s=1;$s<=5;$s++): ?>
                            <svg class="w-3 h-3 <?= $s<=$stars?'text-amber-400':'text-gray-200' ?>" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <?php endfor; ?>
                            <span class="text-xs text-gray-400 ml-1">(<?= number_format($product['avg_rating'],1) ?>)</span>
                        </div>

                        <div class="flex items-center justify-between">
                            <span class="text-lg font-bold text-rose-500"><?= number_format($product['price']) ?> <span class="text-xs font-normal text-gray-400">MMK</span></span>
                            <div class="flex gap-2">
                                <a href="/sweetheaven/user/product_detail.php?id=<?= $product['id'] ?>"
                                   class="border border-stone-200 text-rose-500 px-3 py-2 rounded-xl text-xs font-semibold hover:bg-rose-50 transition-colors">
                                    View
                                </a>
                                <?php if ($product['stock'] > 0 && !$isAdmin): ?>
                                <button onclick="addToCart(<?= $product['id'] ?>, '<?= addslashes($product['name']) ?>')"
                                    class="bg-rose-500 hover:bg-rose-600 text-white px-3 py-2 rounded-xl text-xs font-semibold transition-colors">
                                    + Cart
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Toast -->
<div id="toast" class="hidden fixed bottom-6 right-6 bg-stone-800 text-white px-5 py-3 rounded-xl shadow-md text-sm font-medium z-50 items-center gap-2">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
    <span id="toastMsg"></span>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
function addToCart(productId, name) {
    fetch('/sweetheaven/api/cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=add&product_id=${productId}&qty=1`
    }).then(r=>r.json()).then(data=>{
        if (data.success) {
            showToast(`${name} added to cart!`);
            const badge = document.getElementById('cartBadge');
            if (badge) { badge.textContent = data.cart_count; badge.classList.remove('hidden'); }
        } else if (data.redirect) window.location.href='/sweetheaven/auth/login.php';
    });
}

function toggleWishlist(productId, btn) {
    fetch('/sweetheaven/api/wishlist.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `product_id=${productId}`
    }).then(r=>r.json()).then(data=>{
        if (data.success) {
            const svg = btn.querySelector('svg');
            btn.classList.toggle('bg-rose-500', data.is_wishlisted);
            btn.classList.toggle('text-white', data.is_wishlisted);
            svg.setAttribute('fill', data.is_wishlisted ? 'currentColor' : 'none');
            showToast(data.is_wishlisted ? '❤️ Added to wishlist' : '💔 Removed from wishlist');
        } else if (data.redirect) window.location.href='/sweetheaven/auth/login.php';
    });
}

function showToast(msg) {
    const t = document.getElementById('toast');
    document.getElementById('toastMsg').textContent = msg;
    t.classList.remove('hidden'); t.classList.add('flex');
    setTimeout(()=>{ t.classList.add('hidden'); t.classList.remove('flex'); }, 3000);
}
</script>
</body>
</html>
