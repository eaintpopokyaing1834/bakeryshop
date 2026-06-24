<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../middleware/customer_check.php';
require_once __DIR__ . '/../config/db.php';
$db = getDB();
$userId = (int)$_SESSION['user_id'];

$wishlist = $db->prepare("
    SELECT p.*, pi.image_url AS primary_image, COALESCE(AVG(r.rating),0) AS avg_rating
    FROM wishlist w
    JOIN products p ON w.product_id = p.id
    LEFT JOIN product_images pi ON pi.product_id=p.id AND pi.is_primary=1
    LEFT JOIN reviews r ON r.product_id=p.id
    WHERE w.user_id=?
    GROUP BY p.id, pi.image_url
    ORDER BY w.created_at DESC
");
$wishlist->execute([$userId]);
$wishlist = $wishlist->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wishlist — Sweet Heaven Bakery</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>* { font-family: 'Poppins', sans-serif; }</style>
</head>
<body class="bg-gray-50">
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="max-w-6xl mx-auto px-6 py-10">
    <div class="flex items-center gap-3 mb-8">
        <h1 class="text-3xl font-bold text-gray-800">My Wishlist</h1>
        <span class="bg-rose-50 text-rose-600 text-sm font-semibold px-3 py-1 rounded-full"><?= count($wishlist) ?> item<?= count($wishlist) !== 1 ? 's' : '' ?></span>
    </div>

    <?php if (empty($wishlist)): ?>
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-20 text-center">
        <p class="text-6xl mb-6">❤️</p>
        <h2 class="text-2xl font-bold text-gray-700 mb-3">Your wishlist is empty</h2>
        <p class="text-gray-400 mb-8">Save items you love by clicking the heart icon on any product.</p>
        <a href="/sweetheaven/user/products.php" class="bg-rose-500 hover:bg-rose-600 text-white px-8 py-4 rounded-2xl font-semibold transition-colors shadow-sm shadow-rose-100">
            Explore Products
        </a>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <?php foreach ($wishlist as $item): ?>
        <?php $imgSrc = $item['primary_image'] ? '/sweetheaven/'.$item['primary_image'] : '/sweetheaven/images/maincake.jpg'; ?>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md hover:-translate-y-1 transition-all duration-300" id="wishlist-item-<?= $item['id'] ?>">
            <div class="relative h-48 overflow-hidden bg-rose-50">
                <img src="<?= htmlspecialchars($imgSrc) ?>" class="w-full h-full object-cover" alt="<?= htmlspecialchars($item['name']) ?>">
                <button onclick="removeFromWishlist(<?= $item['id'] ?>, this)"
                    class="absolute top-3 right-3 w-9 h-9 rounded-full bg-rose-500 text-white shadow-md flex items-center justify-center hover:bg-red-600 transition-colors">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                </button>
            </div>
            <div class="p-5">
                <a href="/sweetheaven/user/product_detail.php?id=<?= $item['id'] ?>">
                    <h3 class="font-bold text-gray-800 mb-1 hover:text-rose-500 transition-colors line-clamp-1"><?= htmlspecialchars($item['name']) ?></h3>
                </a>
                <div class="flex gap-0.5 mb-3">
                    <?php $stars = round($item['avg_rating']); for($s=1;$s<=5;$s++): ?>
                    <svg class="w-3 h-3 <?= $s<=$stars?'text-amber-400':'text-gray-200' ?>" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    <?php endfor; ?>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-lg font-bold text-rose-500"><?= number_format($item['price']) ?> <span class="text-xs font-normal text-gray-400">MMK</span></span>
                    <?php if ($item['stock'] > 0): ?>
                    <button onclick="addToCart(<?= $item['id'] ?>, '<?= addslashes($item['name']) ?>')"
                        class="bg-rose-500 hover:bg-rose-600 text-white px-4 py-2 rounded-full text-xs font-semibold transition-colors">
                        Add to Cart
                    </button>
                    <?php else: ?>
                    <span class="text-xs text-red-500 font-semibold">Out of Stock</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

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
        if (data.success) { showToast(`${name} added to cart!`); const b=document.getElementById('cartBadge'); if(b){b.textContent=data.cart_count;b.classList.remove('hidden');} }
    });
}

function removeFromWishlist(productId, btn) {
    fetch('/sweetheaven/api/wishlist.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `product_id=${productId}`
    }).then(r=>r.json()).then(data=>{
        if (data.success) { document.getElementById(`wishlist-item-${productId}`)?.remove(); showToast('Removed from wishlist'); }
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
