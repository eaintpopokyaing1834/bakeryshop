<?php
if (session_status() === PHP_SESSION_NONE)
    session_start();
require_once __DIR__ . '/../includes/lang.php';
require_once __DIR__ . '/../config/db.php';

$db = getDB();

function imgUrl($url)
{
    if (!$url)
        return '/sweetheaven/images/maincake.jpg';
    if (strncmp($url, '../', 3) === 0)
        return '/sweetheaven/' . substr($url, 3);
    return '/sweetheaven/' . $url;
}

$productId = (int) ($_GET['id'] ?? 0);
if (!$productId) {
    header('Location: /sweetheaven/user/products.php');
    exit;
}

$product = $db->prepare("
    SELECT p.*, c.name AS category_name, c.name_my AS category_name_my,
           d.name AS discount_name, d.type AS discount_type, d.value AS discount_value
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN discounts d ON p.discount_id = d.id
    WHERE p.id = ?
");
$product->execute([$productId]);
$product = $product->fetch();
if (!$product) {
    header('Location: /sweetheaven/user/products.php');
    exit;
}

$images = $db->prepare("SELECT * FROM product_images WHERE product_id=? ORDER BY is_primary DESC");
$images->execute([$productId]);
$images = $images->fetchAll();

$reviews = $db->prepare("
    SELECT r.*, u.name AS reviewer_name
    FROM reviews r JOIN users u ON r.user_id = u.id
    WHERE r.product_id = ?
    ORDER BY r.created_at DESC
");
$reviews->execute([$productId]);
$reviews = $reviews->fetchAll();

$avgRating = count($reviews) ? array_sum(array_column($reviews, 'rating')) / count($reviews) : 0;
$wishlistIds = [];
$userReview = null;
$isCustomer = false;
if (isset($_SESSION['user_id'])) {
    $isCustomer = ($_SESSION['role'] === 'customer');
    if ($isCustomer) {
        $wl = $db->prepare("SELECT product_id FROM wishlist WHERE user_id=?");
        $wl->execute([$_SESSION['user_id']]);
        $wishlistIds = array_column($wl->fetchAll(), 'product_id');
    }
    foreach ($reviews as $r) {
        if ($r['user_id'] == $_SESSION['user_id']) {
            $userReview = $r;
            break;
        }
    }
}

$relatedProducts = $db->prepare("
    SELECT p.*, d.name AS discount_name, d.type AS discount_type, d.value AS discount_value,
           (SELECT image_url FROM product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) AS primary_image
    FROM products p
    LEFT JOIN discounts d ON p.discount_id = d.id
    WHERE p.category_id=? AND p.id!=? LIMIT 4
");
$relatedProducts->execute([$product['category_id'], $productId]);
$relatedProducts = $relatedProducts->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(getLocalizedProductName($product)) ?> — Sweet Heaven Bakery</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <style>
        * { font-family: 'Poppins', sans-serif; }

        /* Gallery main image — smooth fade when src swaps */
        #pdMainImage {
            transition: opacity 0.2s ease, transform 0.5s ease;
        }

        /* Thumbnail strip — hide scrollbar but keep scroll */
        #pdThumbStrip {
            scrollbar-width: none;
        }
        #pdThumbStrip::-webkit-scrollbar {
            display: none;
        }

        /* Thumbnail active ring pulse on first load */
        .pd-thumb:focus-visible {
            outline: 2px solid #f43f5e;
            outline-offset: 3px;
        }
    </style>
</head>

<body class="bg-gray-50">
    <?php require_once __DIR__ . '/../includes/header.php'; ?>

    <div class="max-w-7xl mx-auto px-6 py-10">
        <!-- Breadcrumb -->
        <nav class="text-sm text-gray-400 mb-8 flex items-center gap-2">
            <a href="/sweetheaven/user/index.php" class="hover:text-rose-500"><?= __('nav_home') ?></a>
            <span>/</span>
            <a href="/sweetheaven/user/products.php" class="hover:text-rose-500"><?= __('nav_products') ?></a>
            <span>/</span>
            <a href="/sweetheaven/user/products.php?category_id=<?= $product['category_id'] ?>"
                class="hover:text-rose-500"><?= htmlspecialchars(getLocalizedCategoryName($product, 'category_name', 'category_name_my')) ?></a>
            <span>/</span>
            <span class="text-gray-600"><?= htmlspecialchars(getLocalizedProductName($product)) ?></span>
        </nav>

        <!-- Product Detail -->
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8 mb-10">
            <div class="grid md:grid-cols-2 gap-10 items-start">

        <!-- Image Gallery -->
                <div class="flex flex-col gap-3">

                    <?php
                        // Build ordered image list: primary first, then extras
                        $galleryImages = $images;
                        if (empty($galleryImages)) {
                            // Fallback placeholder so gallery always renders
                            $galleryImages = [['image_url' => null]];
                        }
                        $totalImgs = count($galleryImages);
                    ?>

                    <!-- ── Main large display image ── -->
                    <div class="relative rounded-2xl overflow-hidden bg-gradient-to-br from-rose-50 to-amber-50 aspect-[4/3] group shadow-sm">
                        <img
                            id="pdMainImage"
                            src="<?= imgUrl($galleryImages[0]['image_url']) ?>"
                            alt="<?= htmlspecialchars(getLocalizedProductName($product)) ?>"
                            class="w-full h-full object-cover transition-all duration-500 ease-in-out group-hover:scale-105">

                        <!-- Image counter badge (e.g. "1 / 3") -->
                        <?php if ($totalImgs > 1): ?>
                        <span id="pdImgCounter"
                            class="absolute bottom-3 right-3 bg-black/50 text-white text-xs font-semibold px-2.5 py-1 rounded-full backdrop-blur-sm">
                            1 / <?= $totalImgs ?>
                        </span>
                        <?php endif; ?>
                    </div>

                    <!-- ── Thumbnail strip (only when 2+ images) ── -->
                    <?php if ($totalImgs > 1): ?>
                    <div class="flex gap-2.5 overflow-x-auto pb-1" id="pdThumbStrip">
                        <?php foreach ($galleryImages as $idx => $img): ?>
                        <button
                            type="button"
                            onclick="pdSwitchImage(this, '<?= imgUrl($img['image_url']) ?>', <?= $idx + 1 ?>, <?= $totalImgs ?>)"
                            title="Image <?= $idx + 1 ?>"
                            class="pd-thumb group/thumb relative shrink-0 rounded-xl overflow-hidden transition-all duration-200
                                <?= $idx === 0
                                    ? 'ring-2 ring-rose-400 ring-offset-2 opacity-100'
                                    : 'ring-2 ring-transparent ring-offset-2 opacity-60 hover:opacity-100 hover:ring-stone-300' ?>"
                            style="width:80px; height:80px;">
                            <img
                                src="<?= imgUrl($img['image_url']) ?>"
                                alt="<?= htmlspecialchars(getLocalizedProductName($product)) ?> — view <?= $idx + 1 ?>"
                                class="w-full h-full object-cover transition-transform duration-300 group-hover/thumb:scale-110">
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <script>
                    function pdSwitchImage(btn, src, num, total) {
                        const main = document.getElementById('pdMainImage');
                        const counter = document.getElementById('pdImgCounter');

                        // Fade out → swap src → fade in
                        main.style.opacity = '0';
                        setTimeout(() => {
                            main.src = src;
                            main.style.opacity = '1';
                        }, 200);

                        // Update counter
                        if (counter) counter.textContent = num + ' / ' + total;

                        // Reset all thumb rings
                        document.querySelectorAll('.pd-thumb').forEach(b => {
                            b.classList.remove('ring-rose-400', 'opacity-100');
                            b.classList.add('ring-transparent', 'opacity-60');
                        });

                        // Highlight clicked thumb
                        btn.classList.remove('ring-transparent', 'opacity-60');
                        btn.classList.add('ring-rose-400', 'opacity-100');
                    }
                    </script>

                </div>


                <!-- Product Info -->
                <div>
                    <div>
                        <div class="flex justify-between">

                            <div class="flex items-center gap-2 mb-3 flex-wrap">
                                <span
                                    class="bg-rose-50 text-rose-600 text-xs font-semibold px-3 py-1 rounded-full"><?= htmlspecialchars(getLocalizedCategoryName($product, 'category_name', 'category_name_my')) ?></span>
                                <span
                                    class="<?= $product['stock'] > 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?> text-xs font-semibold px-3 py-1 rounded-full">
                                    <?= $product['stock'] > 0 ? sprintf(__('detail_in_stock_qty'), localizeNumber($product['stock'])) : __('detail_out_of_stock') ?>
                                </span>
                                <?php if ($product['discount_name'] && $product['discount_value']): ?>
                                    <span class="bg-green-100 text-green-700 text-xs font-semibold px-3 py-1 rounded-full">
                                        🏷️ <?= htmlspecialchars(getLocalizedDiscountLabel($product)) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <button onclick="toggleWishlist(<?= $product['id'] ?>, this)" id="wishlistBtn"
                                    class="p-2 rounded-2xl border-2 <?= in_array($product['id'], $wishlistIds) ? 'border-rose-400 bg-rose-50 text-rose-500' : 'border-gray-200 text-gray-400 hover:border-stone-200' ?> transition-colors">
                                    <svg class="w-6 h-6"
                                        fill="<?= in_array($product['id'], $wishlistIds) ? 'currentColor' : 'none' ?>"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <h1 class="text-3xl font-bold text-gray-800 mb-3"><?= htmlspecialchars(getLocalizedProductName($product)) ?></h1>

                        <!-- Rating summary -->
                        <div class="flex items-center gap-3 mb-4">
                            <div class="flex gap-1">
                                <?php $starInt = round($avgRating);
                                for ($s = 1; $s <= 5; $s++): ?>
                                    <svg class="w-5 h-5 <?= $s <= $starInt ? 'text-amber-400' : 'text-gray-200' ?>"
                                        fill="currentColor" viewBox="0 0 20 20">
                                        <path
                                            d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                    </svg>
                                <?php endfor; ?>
                            </div>
                            <span class="text-sm text-gray-500"><?= localizeNumber(number_format($avgRating, 1)) ?>
                                (<?= sprintf(__('detail_reviews_count'), localizeNumber(count($reviews)), count($reviews) !== 1 ? 's' : '') ?>)</span>
                        </div>

                        <div class="text-4xl font-bold text-rose-500 mb-6">
                            <?php if ($product['discount_name'] && $product['discount_value']): ?>
                                <?php $finalPrice = $product['discount_type'] === 'percentage'
                                    ? $product['price'] * (1 - $product['discount_value'] / 100)
                                    : max(0, $product['price'] - $product['discount_value']); ?>
                                <span class="text-xl line-through text-gray-400 font-normal mr-2"><?= formatPrice($product['price']) ?></span>
                                <?= formatPrice($finalPrice) ?>
                            <?php else: ?>
                                <?= formatPrice($product['price']) ?>
                            <?php endif; ?>
                        </div>

                        <p class="text-gray-500 leading-relaxed mb-8">
                            <?= nl2br(htmlspecialchars(getLocalizedProductDescription($product))) ?>
                        </p>

                        <!-- Qty + Actions -->
                        <?php if ($product['stock'] > 0): ?>
                            <div class="flex items-center gap-4 mb-4">
                                <div class="flex items-center border border-gray-200 rounded-xl overflow-hidden">
                                    <button onclick="changeQty(-1)"
                                        class="px-4 py-3 text-gray-600 hover:bg-gray-100 transition-colors font-bold text-lg">−</button>
                                    <input type="number" id="qty" value="1" min="1" max="<?= $product['stock'] ?>"
                                        class="w-16 text-center border-none focus:outline-none text-gray-800 font-semibold py-3">
                                    <button onclick="changeQty(1)"
                                        class="px-4 py-3 text-gray-600 hover:bg-gray-100 transition-colors font-bold text-lg">+</button>
                                </div>
                            </div>
                            <?php if ($isCustomer): ?>
                                <div class="flex gap-3 flex-wrap">
                                    <button onclick="addToCart(<?= $product['id'] ?>)"
                                        class="flex-1 bg-rose-500 hover:bg-rose-600 text-white font-semibold py-4 rounded-2xl transition-colors flex items-center justify-center gap-2 shadow-sm shadow-rose-100">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                                        </svg>
                                        <?= __('detail_add_cart') ?>
                                    </button>
                                </div>
                            <?php elseif (!isset($_SESSION['user_id'])): ?>
                                <div class="bg-amber-50 border border-amber-200 p-5 rounded-2xl text-center">
                                    <svg class="w-8 h-8 text-amber-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                    <p class="text-gray-700 font-semibold text-sm mb-1"><?= __('detail_members_only') ?></p>
                                    <p class="text-gray-500 text-xs mb-4"><?= __('detail_members_only_desc') ?></p>
                                    <div class="flex gap-3 justify-center flex-wrap">
                                        <a href="/sweetheaven/auth/login.php"
                                           onclick="if(typeof openAuthModal==='function'){event.preventDefault();openAuthModal('login');}"
                                           class="inline-flex items-center gap-1.5 bg-rose-500 hover:bg-rose-600 text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition-colors shadow-sm">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                                            </svg>
                                            <?= __('nav_login') ?>
                                        </a>
                                        <a href="/sweetheaven/auth/register.php"
                                           onclick="if(typeof openAuthModal==='function'){event.preventDefault();openAuthModal('register');}"
                                           class="inline-flex items-center gap-1.5 bg-white hover:bg-gray-50 text-gray-700 text-sm font-semibold px-5 py-2.5 rounded-xl transition-colors border border-gray-200 shadow-sm">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                                            </svg>
                                            <?= __('nav_signup') ?>
                                        </a>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="bg-blue-50 border border-blue-100 text-blue-700 p-4 rounded-2xl text-center font-semibold text-sm">
                                    <?= __('detail_admin_no_purchase') ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="bg-red-50 text-red-600 p-4 rounded-2xl text-center font-semibold"><?= __('detail_out_of_stock_msg') ?></div>
                        <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Reviews Section -->
        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8 mb-10">
            <h2 class="text-2xl font-bold text-gray-800 mb-8"><?= __('detail_customer_reviews') ?></h2>

            <!-- Submit Review Form -->
            <?php if (isset($_SESSION['user_id']) && $isCustomer && !$userReview): ?>
                <div class="bg-rose-50 rounded-2xl p-6 mb-8 border border-stone-100">
                    <h3 class="font-bold text-gray-700 mb-4"><?= __('detail_write_review') ?></h3>
                    <div class="flex items-center gap-2 mb-4" id="starPicker">
                        <?php for ($s = 1; $s <= 5; $s++): ?>
                            <button onclick="setRating(<?= $s ?>)" data-star="<?= $s ?>"
                                class="text-3xl text-gray-300 hover:text-amber-400 transition-colors star-btn">★</button>
                        <?php endfor; ?>
                    </div>
                    <input type="hidden" id="ratingInput" value="0">
                    <textarea id="reviewComment" rows="3" placeholder="<?= __('detail_review_placeholder') ?>"
                        class="w-full px-4 py-3 rounded-xl border border-stone-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm resize-none mb-4"></textarea>
                    <button onclick="submitReview(<?= $product['id'] ?>)"
                        class="bg-rose-500 hover:bg-rose-600 text-white px-6 py-3 rounded-xl text-sm font-semibold transition-colors">
                        <?= __('detail_submit_review') ?>
                    </button>
                    <p id="reviewMsg" class="text-sm mt-2"></p>
                </div>
            <?php elseif (!isset($_SESSION['user_id'])): ?>
                <div class="bg-gray-50 rounded-2xl p-6 mb-8 text-center">
                    <p class="text-gray-500 mb-3"><?= __('detail_login_to_review') ?></p>
                    <a href="/sweetheaven/user/index.php?show_login=1"
                        class="bg-rose-500 text-white px-6 py-2 rounded-full text-sm font-semibold hover:bg-rose-600 transition-colors"><?= __('nav_login') ?></a>
                </div>
            <?php endif; ?>

            <!-- Reviews List -->
            <div id="reviewsList" class="space-y-6">
                <?php if (empty($reviews)): ?>
                    <p class="text-gray-400 text-center py-8"><?= __('detail_no_reviews_yet') ?></p>
                <?php else: ?>
                    <?php foreach ($reviews as $review): ?>
                        <div class="border-b border-gray-50 pb-6">
                            <div class="flex items-center gap-3 mb-2">
                                <div
                                    class="w-9 h-9 rounded-full bg-gradient-to-br from-stone-300 to-stone-600 flex items-center justify-center text-white font-bold text-sm">
                                    <?= strtoupper(substr($review['reviewer_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-700 text-sm">
                                        <?= htmlspecialchars($review['reviewer_name']) ?>
                                    </p>
                                    <p class="text-xs text-gray-400"><?= date('M j, Y', strtotime($review['created_at'])) ?></p>
                                </div>
                                <div class="flex gap-0.5 ml-auto">
                                    <?php for ($s = 1; $s <= 5; $s++): ?>
                                        <svg class="w-4 h-4 <?= $s <= $review['rating'] ? 'text-amber-400' : 'text-gray-200' ?>"
                                            fill="currentColor" viewBox="0 0 20 20">
                                            <path
                                                d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                        </svg>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <p class="text-gray-600 text-sm pl-12"><?= nl2br(htmlspecialchars($review['comment'] ?? '')) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Related Products -->
        <?php if (!empty($relatedProducts)): ?>
            <div>
                <h2 class="text-2xl font-bold text-gray-800 mb-6"><?= __('detail_related_products') ?></h2>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
                    <?php foreach ($relatedProducts as $rp): ?>
                        <?php
                        $imgSrc = $rp['primary_image'] ? '/sweetheaven/' . $rp['primary_image'] : '/sweetheaven/images/maincake.jpg';
                        $rpDiscount = $rp['discount_name'] && $rp['discount_value'];
                        if ($rpDiscount) {
                            $rpPrice = $rp['discount_type'] === 'percentage'
                                ? $rp['price'] * (1 - $rp['discount_value'] / 100)
                                : max(0, $rp['price'] - $rp['discount_value']);
                        } else {
                            $rpPrice = $rp['price'];
                        }
                        ?>
                        <a href="/sweetheaven/user/product_detail.php?id=<?= $rp['id'] ?>"
                            class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-sm hover:-translate-y-0.5 transition-all duration-300 relative">
                            <?php if ($rpDiscount): ?>
                                <div
                                    class="absolute top-2 left-2 bg-gradient-to-r from-green-400 to-emerald-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-md z-10">
                                    <?= htmlspecialchars(getLocalizedDiscountLabel($rp)) ?>
                                </div>
                            <?php endif; ?>
                            <img src="<?= htmlspecialchars($imgSrc) ?>" class="w-full h-60 object-cover"
                                alt="<?= htmlspecialchars(getLocalizedProductName($rp)) ?>">
                            <div class="p-4">
                                <p class="font-semibold text-gray-700 text-sm mb-1 line-clamp-1">
                                    <?= htmlspecialchars(getLocalizedProductName($rp)) ?>
                                </p>
                                <p class="text-rose-500 font-bold text-sm">
                                    <?php if ($rpDiscount): ?>
                                        <span class="text-xs line-through text-gray-400 font-normal mr-1"><?= formatPrice($rp['price']) ?></span>
                                    <?php endif; ?>
                                    <?= formatPrice($rpPrice) ?>
                                </p>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Toast -->
    <div id="toast"
        class="hidden fixed bottom-6 right-6 bg-stone-800 text-white px-5 py-3 rounded-xl shadow-md text-sm font-medium z-50 items-center gap-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        <span id="toastMsg"></span>
    </div>
    </div>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
    <?php require_once __DIR__ . '/../includes/auth_modal.php'; ?>

    <script>
        function changeQty(delta) {
            const input = document.getElementById('qty');
            const max = parseInt(input.getAttribute('max'));
            let val = parseInt(input.value) + delta;
            input.value = Math.max(1, Math.min(val, max));
        }

        /* Override shared addToCart for product detail page (supports quantity) */
        function addToCart(productId, productName, qty) {
            qty = qty || document.getElementById('qty')?.value || 1;
            fetch('/sweetheaven/api/cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=add&product_id=${productId}&qty=${qty}`
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    if (typeof showToast === 'function') showToast('Added to cart!');
                    const badge = document.getElementById('cartBadge');
                    if (badge) { badge.textContent = data.cart_count; badge.classList.remove('hidden'); }
                } else if (data.redirect) {
                    setPendingAction({ type: 'cart', productId, productName: productName || 'Product', qty });
                    openAuthModal('login');
                }
            });
        }

        let selectedRating = 0;
        function setRating(r) {
            selectedRating = r;
            document.getElementById('ratingInput').value = r;
            document.querySelectorAll('.star-btn').forEach((btn, i) => {
                btn.classList.toggle('text-amber-400', i < r);
                btn.classList.toggle('text-gray-300', i >= r);
            });
        }

        function submitReview(productId) {
            const rating = selectedRating;
            const comment = document.getElementById('reviewComment').value.trim();
            const msgEl = document.getElementById('reviewMsg');
            if (!rating) { msgEl.textContent = 'Please select a rating.'; msgEl.className = 'text-sm mt-2 text-red-500'; return; }
            fetch('/sweetheaven/api/review.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `product_id=${productId}&rating=${rating}&comment=${encodeURIComponent(comment)}`
            }).then(r => r.json()).then(data => {
                if (data.success) { msgEl.textContent = data.msg; msgEl.className = 'text-sm mt-2 text-green-600'; showToast(data.msg); }
                else { msgEl.textContent = data.msg; msgEl.className = 'text-sm mt-2 text-red-500'; }
            });
        }

        function showToast(msg) {
            const t = document.getElementById('toast');
            document.getElementById('toastMsg').textContent = msg;
            t.classList.remove('hidden'); t.classList.add('flex');
            setTimeout(() => { t.classList.add('hidden'); t.classList.remove('flex'); }, 3000);
        }
    </script>
</body>

</html>