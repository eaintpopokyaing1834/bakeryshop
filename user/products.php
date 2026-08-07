<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/lang.php';
require_once __DIR__ . '/../config/db.php';

$db = getDB();

// ── Filters ──────────────────────────────────────────
$categoryId = (int)($_GET['category_id'] ?? 0);
$search     = trim($_GET['search'] ?? '');
$sort       = $_GET['sort'] ?? 'newest';
$minPrice   = (int)($_GET['min_price'] ?? 0);
$maxPrice   = (int)($_GET['max_price'] ?? 999999);
$discounted = (int)($_GET['discounted'] ?? 0);

$where  = ["1=1"]; // Show all products including out-of-stock (card UI shows "Out of Stock" overlay)
$params = [];
if ($categoryId > 0) { $where[] = "p.category_id = ?"; $params[] = $categoryId; }
if ($search !== '') { $where[] = "(p.name LIKE ? OR p.description LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($minPrice > 0) { $where[] = "p.price >= ?"; $params[] = $minPrice; }
if ($maxPrice < 999999) { $where[] = "p.price <= ?"; $params[] = $maxPrice; }
if ($discounted) { $where[] = "p.discount_id IS NOT NULL"; }

$sortSQL = match($sort) {
    'price_asc'  => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'popular'    => 'total_sold DESC',
    default      => 'p.created_at DESC',
};

$products = $db->prepare("
    SELECT p.*,
           c.name AS category_name,
           d.name AS discount_name, d.type AS discount_type, d.value AS discount_value,
           (SELECT image_url FROM product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) AS primary_image,
           COALESCE(AVG(r.rating),0) AS avg_rating,
           COUNT(DISTINCT oi.id) AS total_sold
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN discounts d ON p.discount_id = d.id
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
    <title><?= __('products_page_title') ?></title>
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

                <!-- Categories & Filters -->
                <div>
                    <h4 class="font-bold text-gray-700 mb-4 text-sm uppercase tracking-wider"><?= __('products_categories') ?></h4>
                    <ul class="space-y-1">
                        <li>
                            <a href="/sweetheaven/user/products.php?search=<?= urlencode($search) ?>&sort=<?= $sort ?>"
                               class="flex items-center justify-between px-3 py-2.5 rounded-xl text-sm transition-colors
                               <?= $categoryId === 0 && !$discounted ? 'bg-rose-500 text-white font-semibold' : 'text-gray-600 hover:bg-rose-50 hover:text-rose-500' ?>">
                               <span><?= __('products_all') ?></span>
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
                        <li class="pt-2 border-t border-gray-100 mt-2">
                            <a href="/sweetheaven/user/products.php?discounted=1&search=<?= urlencode($search) ?>&sort=<?= $sort ?>"
                               class="flex items-center justify-between px-3 py-2.5 rounded-xl text-sm transition-colors
                               <?= $discounted ? 'bg-rose-500 text-white font-semibold' : 'text-gray-600 hover:bg-rose-50 hover:text-rose-500' ?>">
                               <span><?= __('products_discounted') ?></span>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Price Filter -->
                <div>
                    <h4 class="font-bold text-gray-700 mb-4 text-sm uppercase tracking-wider"><?= __('products_price_range') ?></h4>
                    <form method="GET" id="priceForm">
                        <input type="hidden" name="category_id" value="<?= $categoryId ?>">
                        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                        <input type="hidden" name="sort" value="<?= $sort ?>">
                        <?php if ($discounted): ?><input type="hidden" name="discounted" value="1"><?php endif; ?>
                        <div class="space-y-3">
                            <div>
                                <label class="text-xs text-gray-500 mb-1 block"><?= __('products_min_price') ?></label>
                                <input type="number" name="min_price" value="<?= $minPrice ?>" step="500" min="0"
                                    class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300">
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 mb-1 block"><?= __('products_max_price') ?></label>
                                <input type="number" name="max_price" value="<?= $maxPrice < 999999 ? $maxPrice : '' ?>" step="500" min="0"
                                    class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300">
                            </div>
                            <button type="submit" class="w-full bg-rose-500 text-white py-2 rounded-xl text-sm font-semibold hover:bg-rose-600 transition-colors"><?= __('products_apply') ?></button>
                        </div>
                    </form>
                </div>

                <!-- Clear Filters -->
                <?php if ($categoryId || $search || $minPrice || $maxPrice < 999999 || $discounted): ?>
                <a href="/sweetheaven/user/products.php" class="block text-center text-sm text-red-500 hover:text-red-700 font-medium"><?= __('products_clear') ?></a>
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
                    <p class="text-sm text-gray-400"><?=  currentLang() === 'my' ? 'ထုတ်ကုန် ' . count($products) . ' ခု တွေ့ရှိသည်' :  ' product' . (count($products) !== 1 ? 's' : '') . ' found' ?></p>
                </div>
                <div class="flex items-center gap-3">
                    <form method="GET" class="flex items-center gap-3">
                        <input type="hidden" name="category_id" value="<?= $categoryId ?>">
                        <?php if ($discounted): ?><input type="hidden" name="discounted" value="1"><?php endif; ?>
                        <!-- Search Input -->
                        <div class="relative">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-stone-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                            </svg>
                            <input type="search" name="search" placeholder="<?= __('products_search_ph') ?>"
                                value="<?= htmlspecialchars($search) ?>"
                                class="border border-gray-200 rounded-xl pl-9 pr-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300 w-48">
                        </div>
                        <!-- Search Button -->
                        <button type="submit"
                            class="bg-rose-500 text-white px-4 py-2 rounded-xl text-sm font-semibold hover:bg-rose-600 transition-colors shadow-sm shadow-rose-200">
                            <?= __('products_search_btn') ?>
                        </button>
                        <!-- Sort Dropdown -->
                        <select name="sort" onchange="this.form.submit()"
                            class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300">
                            <option value="newest" <?= $sort==='newest'?'selected':'' ?>><?= __('products_sort_newest') ?></option>
                            <option value="popular" <?= $sort==='popular'?'selected':'' ?>><?= __('products_sort_popular') ?></option>
                            <option value="price_asc" <?= $sort==='price_asc'?'selected':'' ?>><?= __('products_sort_asc') ?></option>
                            <option value="price_desc" <?= $sort==='price_desc'?'selected':'' ?>><?= __('products_sort_desc') ?></option>
                        </select>
                    </form>
                </div>
            </div>

            <!-- Product Grid -->
            <?php if (empty($products)): ?>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-16 text-center">
                <p class="text-5xl mb-4">🔍</p>
                <h3 class="text-xl font-bold text-gray-700 mb-2"><?= __('products_not_found') ?></h3>
                <p class="text-gray-400 text-sm mb-6"><?= __('products_adjust') ?></p>
                <a href="/sweetheaven/user/products.php" class="bg-rose-500 text-white px-6 py-3 rounded-full text-sm font-semibold hover:bg-rose-600 transition-colors"><?= __('products_clear') ?></a>
            </div>
            <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6 items-stretch">
                <?php foreach ($products as $product): ?>
                <?php
                $imgSrc   = $product['primary_image'] ? '/sweetheaven/' . $product['primary_image'] : '/sweetheaven/images/maincake.jpg';
                $isWished = in_array($product['id'], $wishlistIds);
                $hasDiscount = $product['discount_name'] && $product['discount_value'];
                if ($hasDiscount) {
                    $discountedPrice = $product['discount_type'] === 'percentage'
                        ? $product['price'] * (1 - $product['discount_value'] / 100)
                        : max(0, $product['price'] - $product['discount_value']);
                }
                ?>
                <div class="product-card group bg-white rounded-2xl border border-gray-100 overflow-hidden transition-all duration-500 shadow-md cursor-pointer flex flex-col h-full">
                    <div class="relative overflow-hidden bg-gradient-to-br from-rose-50 to-amber-50 aspect-[4/3]">
                        <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($product['name']) ?>"
                             class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
                        <button onclick="event.stopPropagation(); toggleWishlist(<?= $product['id'] ?>, this)"
                            class="absolute top-3 right-3 w-9 h-9 rounded-full <?= $isWished ? 'bg-rose-500 text-white' : 'bg-white/90 text-gray-400' ?> shadow-md flex items-center justify-center hover:bg-rose-500 hover:text-white transition-all duration-200 backdrop-blur-sm">
                            <svg class="w-4 h-4" fill="<?= $isWished ? 'currentColor' : 'none' ?>" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                        </button>
                        <?php if ($hasDiscount): ?>
                        <div class="absolute top-3 left-3 bg-gradient-to-r from-green-400 to-emerald-500 text-white text-xs font-bold px-3 py-1 rounded-full shadow-md">
                            <?= htmlspecialchars($product['discount_name']) ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($product['stock'] === 0): ?>
                        <div class="absolute inset-0 bg-black/50 flex items-center justify-center backdrop-blur-sm">
                            <span class="bg-red-600 text-white text-sm font-bold px-5 py-2 rounded-full shadow-lg"><?= __('products_out_of_stock') ?></span>
                        </div>
                        <?php elseif ($product['stock'] < 10 && !$hasDiscount): ?>
                        <div class="absolute top-3 left-3 bg-gradient-to-r from-amber-400 to-orange-500 text-white text-xs font-bold px-3 py-1 rounded-full shadow-md">Only <?= $product['stock'] ?> left</div>
                        <?php endif; ?>
                        <div class="absolute inset-0 bg-gradient-to-t from-black/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none"></div>
                    </div>

                    <div class="p-5 flex flex-col flex-1">
                        <p class="text-xs font-semibold uppercase tracking-wider text-rose-400 mb-1"><?= htmlspecialchars($product['category_name'] ?? 'Uncategorized') ?></p>

                        <a href="/sweetheaven/user/product_detail.php?id=<?= $product['id'] ?>" onclick="event.stopPropagation()">
                            <h3 class="font-bold text-gray-800 text-base hover:text-rose-500 transition-colors mb-3 line-clamp-2"><?= htmlspecialchars($product['name']) ?></h3>
                        </a>

                        <div class="flex items-center justify-between pt-3 mt-auto border-t border-gray-50">
                            <span class="text-lg font-bold text-rose-500">
                                <?php if ($hasDiscount): ?>
                                    <span class="text-xs line-through text-gray-400 font-normal mr-1"><?= number_format($product['price']) ?></span>
                                    <?= number_format($discountedPrice) ?>
                                <?php else: ?>
                                    <?= number_format($product['price']) ?>
                                <?php endif; ?>
                                <span class="text-xs font-normal text-gray-400"><?= __('common_mmk') ?></span>
                            </span>
                            <div class="flex gap-2">
                                <a href="/sweetheaven/user/product_detail.php?id=<?= $product['id'] ?>"
                                   onclick="event.stopPropagation()"
                                   class="border border-stone-200 text-rose-500 px-3 py-2 rounded-xl text-xs font-semibold hover:bg-rose-50 transition-colors">
                                    <?= __('products_view') ?>
                                </a>
                                <?php if ($product['stock'] > 0 && !$isAdmin): ?>
                                <button onclick="event.stopPropagation(); addToCart(<?= $product['id'] ?>, '<?= addslashes($product['name']) ?>')"
                                    class="bg-rose-500 hover:bg-rose-600 text-white px-3 py-2 rounded-xl text-xs font-semibold transition-colors shadow-sm shadow-rose-200">
                                    <?= __('products_add_cart') ?>
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
<?php require_once __DIR__ . '/../includes/auth_modal.php'; ?>

<script>
function showToast(msg) {
    const t = document.getElementById('toast');
    document.getElementById('toastMsg').textContent = msg;
    t.classList.remove('hidden'); t.classList.add('flex');
    setTimeout(()=>{ t.classList.add('hidden'); t.classList.remove('flex'); }, 3000);
}
</script>
</body>
</html>
