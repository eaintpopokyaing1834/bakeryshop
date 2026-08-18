<?php
if (session_status() === PHP_SESSION_NONE)
    session_start();
require_once __DIR__ . '/../includes/lang.php';
require_once __DIR__ . '/../config/db.php';


$db = getDB();
$categoryId = (int) ($_GET['category_id'] ?? 0);
$search = trim($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'newest';

$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();

$bestSellers = $db->query("
    SELECT p.*,
           c.name AS category_name, c.name_my AS category_name_my,
           d.name AS discount_name, d.type AS discount_type, d.value AS discount_value,
           COALESCE(AVG(r.rating),0) AS avg_rating,
           COALESCE(SUM(oi.quantity), 0) AS total_sales,
           GROUP_CONCAT(
               DISTINCT CASE WHEN pi.is_primary = 1 THEN pi.image_url ELSE NULL END
               ORDER BY pi.id SEPARATOR '|'
           ) AS primary_image,
           GROUP_CONCAT(
               DISTINCT CASE WHEN pi.is_primary = 0 THEN pi.image_url ELSE NULL END
               ORDER BY pi.id SEPARATOR '|'
           ) AS extra_images
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN discounts d ON p.discount_id = d.id AND d.status = 1
    LEFT JOIN product_images pi ON pi.product_id = p.id
    LEFT JOIN reviews r ON r.product_id = p.id
    LEFT JOIN order_items oi ON oi.product_id = p.id
    GROUP BY p.id
    ORDER BY total_sales DESC, p.created_at DESC
    LIMIT 4
")->fetchAll();

$latestProducts = $db->query("
    SELECT p.*,
           pi.image_url AS primary_image
    FROM products p
    LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT 9
")->fetchAll();

// Reviews are now displayed per-product on the product detail page.
// $customerReviews = $db->query("
//     SELECT u.name, u.profile_image, p.name AS product_name, r.comment AS message, r.rating, MAX(r.created_at) as created_at
//     FROM reviews r
//     JOIN users u ON r.user_id = u.id
//     LEFT JOIN products p ON r.product_id = p.id
//     WHERE r.status='approved'
//     GROUP BY u.name, u.profile_image, p.name, r.comment, r.rating
//     ORDER BY created_at DESC
// ")->fetchAll();

// Fetch all discounted products (with all images)
$discountedProducts = $db->query("
    SELECT p.*,
           c.name AS category_name, c.name_my AS category_name_my,
           d.name AS discount_name, d.type AS discount_type, d.value AS discount_value,
           GROUP_CONCAT(
               CASE WHEN pi.is_primary = 1 THEN pi.image_url ELSE NULL END
               ORDER BY pi.id SEPARATOR '|'
           ) AS primary_image,
           GROUP_CONCAT(
               CASE WHEN pi.is_primary = 0 THEN pi.image_url ELSE NULL END
               ORDER BY pi.id SEPARATOR '|'
           ) AS extra_images
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    JOIN discounts d ON p.discount_id = d.id AND d.status = 1
    LEFT JOIN product_images pi ON pi.product_id = p.id
    WHERE p.discount_id IS NOT NULL
    GROUP BY p.id
    ORDER BY d.value DESC, p.created_at DESC
")->fetchAll();

$isAdmin = isset($_SESSION['user_id']) && in_array($_SESSION['role'] ?? '', ['admin', 'cashier']);
$isLoggedIn = isset($_SESSION['user_id']);
$currentUser = null;
if ($isLoggedIn) {
    $stmtUser = $db->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmtUser->execute([$_SESSION['user_id']]);
    $currentUser = $stmtUser->fetch();
}

$maxDiscountRow = $db->query("SELECT MAX(value) as max_val FROM discounts WHERE type = 'percentage' AND status = 1")->fetch();
$maxDiscountPercent = $maxDiscountRow['max_val'] ? (int) $maxDiscountRow['max_val'] : 15;

$activeOrderRules = $db->query("SELECT * FROM discounts WHERE scope = 'order' AND status = 1")->fetchAll();
$firstOrderRule = null;
$freeGiftRule = null;
foreach ($activeOrderRules as $rule) {
    if ($rule['is_first_order'] == 1)
        $firstOrderRule = $rule;
    if ($rule['type'] === 'free_gift')
        $freeGiftRule = $rule;
}

$firstOrderValue = $firstOrderRule ? ($firstOrderRule['type'] === 'percentage' ? (int) $firstOrderRule['value'] . '%' : formatPrice($firstOrderRule['value'])) : '5%';
$freeGiftAmount = $freeGiftRule ? formatPrice($freeGiftRule['min_order_amount']) : '50,000 MMK';
$freeGiftName = $freeGiftRule ? htmlspecialchars($freeGiftRule['name']) : __('promo_free_gift_title', '50,000 MMK');

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sweet Heaven Bakery — Fresh Baked with Love</title>
    <meta name="description"
        content="Sweet Heaven Bakery — Freshly baked cakes, cupcakes, pastries, and more. Order online for delivery.">
    <script src="https://cdn.tailwindcss.com"></script>
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Inter:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        display: ['DM Serif Display', 'serif'],
                    }
                }
            }
        }
    </script>
    <style>
        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(22px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
                transform: scale(1);
            }

            50% {
                opacity: .6;
                transform: scale(1.3);
            }
        }

        @keyframes pulseEffect {
            0% {
                transform: scale(1);
            }

            30% {
                transform: scale(1.05);
            }

            60% {
                transform: scale(1);
            }
        }

        @keyframes floatEffect {
            0% {
                transform: translateY(0);
            }

            50% {
                transform: translateY(-10px);
            }

            80% {
                transform: translateY(0);
            }
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(32px) scale(0.96);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .btn-slide-hover {
            position: relative;
            overflow: hidden;
            z-index: 1;
        }

        .btn-slide-hover::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #e8746a;
            transform: scaleX(0);
            transform-origin: left;
            transition: transform .35s ease;
            z-index: -1;
            border-radius: inherit;
        }

        .btn-slide-hover:hover::before {
            transform: scaleX(1);
        }

        .btn-slide-hover:hover {
            color: #fff;
            border-color: #e8746a;
        }
    </style>
</head>

<body class="overflow-x-hidden text-gray-700 bg-[#fdf8f3]">

    <?php require_once __DIR__ . '/../includes/header.php'; ?>

    <!-- ═══════════════════════════════════════ HERO ═══════════════════════════════════════ -->
    <!-- <form method="GET" class="flex gap-2 items-center justify-center py-6">
        <input type="hidden" name="category_id" value="<?= $categoryId ?>">
        <input type="hidden" name="sort" value="<?= $sort ?>">
        <input type="search" name="search" placeholder="🔍 Search products..." value="<?= htmlspecialchars($search) ?>"
            class="border border-gray-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300 w-48">
    </form> -->
    <section class="relative overflow-hidden bg-[#fdf8f3]">

        <div class="max-w-7xl mx-auto px-6 py-10 md:py-10 grid md:grid-cols-2 gap-12 items-center">


            <!-- Left: Text -->
            <div class="animate-[fadeUp_0.7s_ease_both] z-10 [animation-delay:.2s] relative">
                <div><img src="../images/spot2.png" class="absolute -top-28 -left-36 w-80 h-80 opacity-80">
                </div>

                <div
                    class="absolute z-[-1] top-1/2 -left-20 -translate-x-1/2  w-80 h-80 opacity-20 rounded-full bg-[#e8746a]">
                </div>

                <span
                    class="inline-flex items-center gap-1.5 backdrop-blur-md bg-white/30 border border-white/40 text-[#c45c55] px-3.5 py-[5px] rounded-full text-[11px] font-semibold tracking-[.06em] uppercase mb-6 shadow-[0_4px_16px_rgba(232,116,106,.12)]">
                    <span class="w-[7px] h-[7px] rounded-full bg-[#e8746a] animate-[pulse_2s_infinite]"></span>
                    <?= __('hero_pill') ?>
                </span>

                <h1 class="font-display text-5xl md:text-[3.8rem] leading-[1.1] text-gray-800 mt-5 mb-5">
                    <?= __('hero_title') ?>
                </h1>

                <p class="text-gray-500 text-[15px] leading-7 mb-8 max-w-md">
                    <?= __('hero_desc') ?>
                </p>

                <div class="flex flex-wrap gap-3 mb-10">
                    <a href="/sweetheaven/user/products.php"
                        class="inline-flex items-center gap-2 text-white px-7 py-3.5 rounded-full font-semibold text-sm hover:opacity-90 hover:-translate-y-0.5 transition-all duration-200 shadow-md shadow-rose-200 bg-[#e8746a]">
                        <?= __('hero_shop_now') ?>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                    </a>
                    <a href="#categories"
                        class="btn-slide-hover inline-flex items-center gap-2 border border-rose-200 text-rose-500 bg-white px-7 py-3.5 rounded-full font-semibold text-sm hover:-translate-y-0.5 transition-all duration-200">
                        <?= __('hero_browse_cat') ?>
                    </a>
                </div>

                <!-- Stats row -->
                <div class="flex items-center gap-8">

                </div>
            </div>

            <!-- Right: Photo Collage -->
            <div class="animate-[fadeUp_0.7s_ease_both] [animation-delay:.2s] relative mt-10 md:mt-0">
                <!-- Decorative circle -->
                <div class="absolute -top-8 -right-8 w-72 h-72 rounded-full bg-[#fdf0ee] z-0"></div>
                <div><img src="../images/balloon.png" class="absolute -top-14 -left-36 w-72 h-70 opacity-50">
                </div>

                <!-- <div><img src="../images/ribbon.png" class="absolute -bottom-20 -left-40 w-60 h-60 opacity-70">
                    </div> -->

                <div class="relative z-10 grid grid-cols-2 gap-4">
                    <img src="../images/heropincake.jpg" alt="Beautiful cake"
                        class="animate-[pulseEffect_3s_infinite_ease-in-out] rounded-[20px] object-cover w-full h-52 shadow-md">
                    <img src="../images/donutgrop.jpg" alt="Croissant"
                        class="animate-[floatEffect_3s_infinite_ease-in-out] rounded-[20px] object-cover w-full h-52 shadow-md mt-8">
                    <img src="../images/cro.jpg" alt="Cupcakes"
                        class="animate-[floatEffect_3s_infinite_ease-in-out] rounded-[20px] object-cover w-full h-52 shadow-md">
                    <img src="../images/minicake.jpg" alt="Fresh bread"
                        class="animate-[pulseEffect_3s_infinite_ease-in-out] rounded-[20px] object-cover w-full h-52 shadow-md mt-8">
                </div>
            </div>
        </div>
    </section>

    <!-- ═════════════════════════ FEATURE BAR ═════════════════════════ -->
    <section class="bg-[#fdf8f3] border-y border-gray-100 py-7">
        <div class="max-w-7xl mx-auto px-6 grid grid-cols-2 md:grid-cols-4 gap-6">
            <?php foreach ([
                ['🌿', __('feature_natural'), __('feature_natural_sub')],
                ['🔥', __('feature_fresh'), __('feature_fresh_sub')],
                ['🚚', __('feature_delivery'), __('feature_delivery_sub')],
                ['💝', __('feature_love'), __('feature_love_sub')],
            ] as $f): ?>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xl flex-shrink-0 bg-[#fdf0ee]">
                        <?= $f[0] ?>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-700 text-sm"><?= $f[1] ?></p>
                        <p class="text-gray-400 text-xs"><?= $f[2] ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- ═════════════════════════ CATEGORIES CAROUSEL ═════════════════════════ -->
    <section id="categories" class="py-20 bg-[#fdf8f3]">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex  items-center justify-center mb-10 animate-[fadeUp_0.7s_ease_both]">
                <div class="text-center">
                    <p class="text-md font-semibold uppercase tracking-widest mb-1 text-[#e8746a]">
                        <?= __('cat_what_we_offer') ?>
                    </p>
                    <h2 class="font-display text-4xl text-gray-800"><?= __('cat_our_categories') ?></h2>
                </div>

            </div>

            <div class="flex items-center gap-4">
                <button id="catPrev"
                    class="flex-shrink-0 w-10 h-10 rounded-full bg-white shadow-md flex items-center justify-center text-stone-600 hover:bg-white hover:text-rose-500 transition-all opacity-0 md:opacity-100 hover:shadow-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <div class="overflow-hidden rounded-2xl flex-1 min-w-0">
                    <div class="flex transition-transform duration-500 ease-in-out [will-change:transform]"
                        id="categoryTrack">
                        <?php foreach ($categories as $cat): ?>
                            <?php
                            $catImg = $cat['image'] ?? '';
                            $catImg = ltrim(str_replace('../', '', $catImg), '/');
                            ?>
                            <div class="flex-shrink-0 px-2 [transition:width_0.3s_ease]">
                                <a href="/sweetheaven/user/products.php?category_id=<?= $cat['id'] ?>"
                                    class="group bg-white border border-gray-100 rounded-2xl p-5 text-center shadow-sm block transition-all duration-[250ms] ease-in-out hover:shadow-[0_8px_32px_rgba(232,116,106,.12)] hover:-translate-y-0.5">
                                    <div
                                        class="w-20 h-20 mx-auto rounded-2xl overflow-hidden mb-3 group-hover:scale-105 transition-transform duration-300 bg-[#fdf0ee]">
                                        <?php if ($catImg): ?>
                                            <img src="/sweetheaven/<?= htmlspecialchars($catImg) ?>"
                                                class="w-full h-full object-cover" alt="<?= htmlspecialchars($cat['name']) ?>"
                                                onerror="this.parentElement.innerHTML='<div class=\'w-full h-full flex items-center justify-center text-3xl\'>🍰</div>'">
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center text-3xl">🍰</div>
                                        <?php endif; ?>
                                    </div>
                                    <p
                                        class="font-semibold text-gray-600 text-sm group-hover:text-rose-500 transition-colors">
                                        <?= htmlspecialchars(getLocalizedCategoryName($cat)) ?>
                                    </p>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <button id="catNext"
                    class="flex-shrink-0 w-10 h-10 rounded-full bg-white shadow-md flex items-center justify-center text-stone-600 hover:bg-white hover:text-rose-500 transition-all opacity-0 md:opacity-100 hover:shadow-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </div>

            <div class="flex justify-center gap-2 mt-6" id="catDots"></div>
        </div>
    </section>


    <script>
            (function () {
                const track = document.getElementById('categoryTrack');
                const prevBtn = document.getElementById('catPrev');
                const nextBtn = document.getElementById('catNext');
                const dotsContainer = document.getElementById('catDots');
                const originalItems = Array.from(track.children);
                const realCount = originalItems.length;

                function getItemsPerView() {
                    if (window.innerWidth < 640) return 1;
                    if (window.innerWidth < 1024) return 2;
                    return 4;
                }

                const cloneCount = realCount;
                // Append clones
                originalItems.forEach(item => {
                    const clone = item.cloneNode(true);
                    track.appendChild(clone);
                });
                // Prepend clones
                for (let i = realCount - 1; i >= 0; i--) {
                    const clone = originalItems[i].cloneNode(true);
                    track.insertBefore(clone, track.firstChild);
                }

                const allItems = Array.from(track.children);
                const totalItems = allItems.length;
                const startIndex = realCount; // first real item position

                let currentIndex = startIndex;
                let autoPlayTimer = null;
                const autoPlayDelay = 3500;

                function getTotalRealPages() {
                    return Math.ceil(realCount / getItemsPerView());
                }

                function getCurrentPage() {
                    const ipv = getItemsPerView();
                    const rawPage = Math.floor(currentIndex / ipv);
                    const totalRealPages = getTotalRealPages();
                    const leadingPages = Math.floor(realCount / ipv);
                    return ((rawPage - leadingPages) % totalRealPages + totalRealPages) % totalRealPages;
                }

                function render() {
                    const ipv = getItemsPerView();
                    const itemWidth = 100 / ipv;
                    allItems.forEach(item => item.style.width = itemWidth + '%');
                    updatePosition(false);
                }

                function updatePosition(animate = true) {
                    const ipv = getItemsPerView();
                    const offset = -(currentIndex / ipv) * 100;
                    if (!animate) track.style.transition = 'none';
                    track.style.transform = `translateX(${offset}%)`;
                    if (!animate) {
                        track.offsetHeight;
                        track.style.transition = '';
                    }
                    updateDots();
                }

                function goTo(index, animate = true) {
                    const maxIndex = totalItems - getItemsPerView();
                    index = Math.max(0, Math.min(index, maxIndex));
                    currentIndex = index;
                    updatePosition(animate);
                }

                function next() {
                    const ipv = getItemsPerView();
                    const nextIndex = currentIndex + ipv;
                    const maxRealIndex = startIndex + realCount - ipv;
                    if (nextIndex >= startIndex + realCount) {
                        // Reached end of real items, loop back
                        goTo(nextIndex, true);
                        setTimeout(() => goTo(startIndex, false), 550);
                    } else {
                        goTo(nextIndex);
                    }
                }

                function prev() {
                    const ipv = getItemsPerView();
                    const prevIndex = currentIndex - ipv;
                    if (prevIndex < startIndex) {
                        goTo(prevIndex, true);
                        setTimeout(() => goTo(startIndex + realCount - ipv, false), 550);
                    } else {
                        goTo(prevIndex);
                    }
                }

                function updateDots() {
                    const ipv = getItemsPerView();
                    const totalPages = getTotalRealPages();
                    const currentPage = getCurrentPage();

                    dotsContainer.innerHTML = '';
                    for (let i = 0; i < totalPages; i++) {
                        const dot = document.createElement('button');
                        dot.className = 'w-2.5 h-2.5 rounded-full bg-stone-300 hover:bg-rose-300 transition-all duration-300';
                        if (i === currentPage) { dot.style.background = '#f43f5e'; dot.style.width = '24px'; dot.style.borderRadius = '999px'; }
                        dot.setAttribute('aria-label', `Go to page ${i + 1}`);
                        dot.addEventListener('click', () => {
                            const targetIndex = startIndex + i * ipv;
                            goTo(targetIndex);
                            resetAutoPlay();
                        });
                        dotsContainer.appendChild(dot);
                    }
                }

                function startAutoPlay() {
                    stopAutoPlay();
                    autoPlayTimer = setInterval(next, autoPlayDelay);
                }

                function stopAutoPlay() {
                    if (autoPlayTimer) {
                        clearInterval(autoPlayTimer);
                        autoPlayTimer = null;
                    }
                }

                function resetAutoPlay() {
                    startAutoPlay();
                }

                prevBtn.addEventListener('click', () => { prev(); resetAutoPlay(); });
                nextBtn.addEventListener('click', () => { next(); resetAutoPlay(); });

                let resizeTimer;
                window.addEventListener('resize', () => {
                    clearTimeout(resizeTimer);
                    resizeTimer = setTimeout(() => {
                        const ipv = getItemsPerView();
                        const itemWidth = 100 / ipv;
                        allItems.forEach(item => item.style.width = itemWidth + '%');
                        // Snap to nearest valid position
                        const maxIndex = totalItems - ipv;
                        if (currentIndex > maxIndex) currentIndex = maxIndex;
                        updatePosition(false);
                        updateDots();
                    }, 200);
                });

                // Pause on hover
                track.addEventListener('mouseenter', stopAutoPlay);
                track.addEventListener('mouseleave', startAutoPlay);

                render();
                startAutoPlay();
                updateDots();
            })();


    </script>

    <!-- ═════════════════════════ BEST SELLERS ═════════════════════════ -->
    <section class="py-20 bg-[#fdf8f3]">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex flex-col items-center justify-center mb-10 animate-[fadeUp_0.7s_ease_both] space-y-4">
                <div>
                    <p class="text-md text-center font-semibold uppercase tracking-widest mb-1" text-[#e8746a]">
                        <?= __('bestsellers_subtitle') ?>
                    </p>
                    <h2 class="font-display text-4xl text-gray-800"><?= __('bestsellers_title') ?></h2>
                </div>

            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                <?php foreach ($bestSellers as $product): ?>
                    <?php
                    // Normalize a DB image_url to a full web path.
                    // Admin-uploaded images are stored as relative paths (e.g. "uploads/products/...").
                    // Legacy/seed images start with "/" already (e.g. "/sweetheaven/images/...").
                    $normImg = function (string $url): string {
                        return (str_starts_with($url, '/') ? '' : '/sweetheaven/') . $url;
                    };
                    // primary_image may contain duplicate URLs due to multiple LEFT JOINs
                    // (reviews + order_items multiply rows). Extract only the first unique value.
                    $primaryImgUrl = $product['primary_image']
                        ? (array_values(array_unique(array_filter(explode('|', $product['primary_image']))))[0] ?? null)
                        : null;
                    $imgSrc = $primaryImgUrl
                        ? $normImg($primaryImgUrl)
                        : '/sweetheaven/images/maincake.jpg';
                    $hasDiscount = $product['discount_name'] && $product['discount_value'];
                    if ($hasDiscount) {
                        $discountedPrice = $product['discount_type'] === 'percentage'
                            ? $product['price'] * (1 - $product['discount_value'] / 100)
                            : max(0, $product['price'] - $product['discount_value']);
                    }
                    $extraImgs = array_filter(explode('|', $product['extra_images'] ?? ''));
                    $allCardImgs = array_filter(array_merge([$imgSrc], array_map($normImg, $extraImgs)));
                    $cardId = 'bs-card-' . $product['id'];
                    ?>
                    <div
                        class="product-card group bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-md hover:shadow-xl transition-all duration-500 hover:-translate-y-2">
                        <!-- ── Image gallery area ── -->
                        <div class="relative bg-gradient-to-br from-rose-50 to-amber-50" style="aspect-ratio:4/3">
                            <!-- Main image -->
                            <img id="<?= $cardId ?>-main" src="<?= htmlspecialchars($imgSrc) ?>"
                                alt="<?= htmlspecialchars(getLocalizedProductName($product)) ?>"
                                class="w-full h-full object-cover transition-all duration-500">

                            <!-- Thumbnails (only shown if extra images exist) -->
                            <?php if (count($allCardImgs) > 1): ?>
                                <div class="absolute bottom-2 left-0 right-0 flex justify-center gap-1.5 px-2">
                                    <?php foreach ($allCardImgs as $ti => $tSrc): ?>
                                        <button type="button"
                                            onclick="event.stopPropagation(); switchCardImage('<?= $cardId ?>', '<?= htmlspecialchars($tSrc) ?>', this)"
                                            class="card-thumb w-10 h-10 rounded-lg overflow-hidden border-2 shadow transition-all duration-200 <?= $ti === 0 ? 'border-white scale-105' : 'border-white/50 opacity-75 hover:opacity-100 hover:scale-105' ?>"
                                            title="Image <?= $ti + 1 ?>">
                                            <img src="<?= htmlspecialchars($tSrc) ?>" class="w-full h-full object-cover" alt="">
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Wishlist button -->
                            <?php if (!$isAdmin): ?>
                                <button onclick="event.stopPropagation(); toggleWishlist(<?= $product['id'] ?>, this)"
                                    class="absolute top-2 right-2 w-9 h-9 rounded-full bg-white/90 text-gray-400 shadow-md flex items-center justify-center hover:bg-rose-500 hover:text-white transition-all duration-200 backdrop-blur-sm"
                                    title="Wishlist">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                    </svg>
                                </button>
                            <?php endif; ?>

                            <?php if ($product['stock'] === 0): ?>
                                <div class="absolute inset-0 bg-black/50 flex items-center justify-center backdrop-blur-sm">
                                    <span
                                        class="bg-red-600 text-white text-sm font-bold px-5 py-2 rounded-full shadow-lg"><?= __('products_out_of_stock') ?></span>
                                </div>
                            <?php endif; ?>

                            <!-- Badge -->
                            <?php if ($hasDiscount): ?>
                                <div
                                    class="absolute top-0 left-0 bg-rose-500 text-white text-xs font-bold px-3 py-1 rounded-br-lg shadow-md">
                                    <?= htmlspecialchars(getLocalizedDiscountLabel($product)) ?>
                                </div>
                            <?php elseif ($product['stock'] > 0 && $product['stock'] < 5): ?>
                                <div
                                    class="absolute top-0 left-0 bg-gradient-to-r from-amber-400 to-orange-500 text-white text-xs font-bold px-3 py-1 rounded-br-lg shadow-md">
                                    <?= __('bestsellers_low_stock', localizeNumber($product['stock'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="p-4">
                            <p class="text-xs font-semibold uppercase tracking-wider text-rose-400 mb-2">
                                <?= htmlspecialchars(getLocalizedCategoryName($product, 'category_name', 'category_name_my')) ?>
                            </p>

                            <a href="/sweetheaven/user/product_detail.php?id=<?= $product['id'] ?>">
                                <h3 class="font-bold text-gray-800 text-sm hover:text-rose-500 transition-colors mb-3">
                                    <?= htmlspecialchars(getLocalizedProductName($product)) ?>
                                </h3>
                            </a>

                            <div class="flex flex-col gap-3  border-t border-gray-50">
                                <span class="font-bold text-[15px] text-rose-500">
                                    <?php if ($hasDiscount): ?>
                                        <span
                                            class="text-xs line-through text-gray-400 font-normal mr-1"><?= formatPrice($product['price']) ?></span>
                                        <?= formatPrice($discountedPrice) ?>
                                    <?php else: ?>
                                        <?= formatPrice($product['price']) ?>
                                    <?php endif; ?>
                                </span>


                                <div class="flex gap-2">
                                    <a href="/sweetheaven/user/product_detail.php?id=<?= $product['id'] ?>"
                                        class="flex-1 text-center border py-2 rounded-xl text-xs font-semibold hover:bg-rose-50 transition-colors"
                                        border-[#e8746a] text-[#e8746a]">
                                        <?= __('common_view') ?>
                                    </a>
                                    <?php if (!$isAdmin && $product['stock'] > 0): ?>
                                        <button
                                            onclick="addToCart(<?= $product['id'] ?>, '<?= addslashes(getLocalizedProductName($product)) ?>')"
                                            class="bg-rose-400 flex-1 flex items-center justify-center gap-1 py-2 rounded-xl text-xs font-bold text-white transition-all duration-200 hover:opacity-90 shadow">

                                            <img src="../images/cart2.png" class="w-5 h-5">
                                            <?= __('common_add_cart') ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="flex items-center justify-center mt-6">
            <a href="/sweetheaven/user/products.php"
                class="hidden sm:inline-flex items-center justify-center gap-1.5 text-center text-sm font-semibold text-rose-500 hover:text-rose-600 transition-colors bg-pink-200 rounded-2xl px-4 py-4">
                <?= __('bestsellers_see_all') ?>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13 7l5 5m0 0l-5 5m5-5H6" />
                </svg>
            </a>
        </div>
    </section>

    <!-- ═════════════════════════ CUSTOMIZE CAKE ═════════════════════════ -->
    <section class="py-20 bg-[#fdf8f3]">
        <div class="max-w-7xl mx-auto px-6">
            <div class="grid md:grid-cols-2 gap-12 items-center">
                <div class="relative">
                    <div class="rounded-3xl overflow-hidden shadow-xl">
                        <img src="/sweetheaven/images/customize4.jpg" alt="Customize your cake"
                            class="w-full h-96 object-cover">
                    </div>
                    <div class="absolute -bottom-5 -right-5 bg-white rounded-full shadow-lg px-6 py-4 hidden md:block">
                        <p class="text-3xl text-center font-bold text-rose-500">🎨</p>
                        <p class="text-sm font-semibold text-gray-700"><?= __('customize_your_design') ?></p>
                        <p class="text-xs text-gray-400"><?= __('customize_well_bake') ?></p>
                    </div>
                </div>
                <div class="space-y-6">
                    <div>
                        <p class="text-md font-semibold uppercase tracking-widest mb-2" text-[#e8746a]">
                            <?= __('customize_label') ?>
                        </p>
                        <h2 class="font-display text-4xl text-gray-800"><?= __('customize_title') ?></h2>
                    </div>
                    <p class="text-gray-500 leading-relaxed text-lg">
                        <?= __('customize_desc') ?>
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5 sm:gap-3 text-sm">
                        <div class="flex items-center gap-3">
                            <span
                                class="w-10 h-10 bg-rose-100 rounded-xl flex items-center justify-center text-rose-500 text-lg">🎂</span>
                            <div>
                                <p class="font-semibold text-gray-700"><?= __('customize_any_size') ?></p>
                                <p class="text-gray-400 text-xs"><?= __('customize_from_1lb') ?></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span
                                class="w-10 h-10 bg-rose-100 rounded-xl flex items-center justify-center text-rose-500 text-lg">✏️</span>
                            <div>
                                <p class="font-semibold text-gray-700"><?= __('customize_message') ?></p>
                                <p class="text-gray-400 text-xs"><?= __('customize_message_sub') ?></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span
                                class="w-10 h-10 bg-rose-100 rounded-xl flex items-center justify-center text-rose-500 text-lg">🖼️</span>
                            <div>
                                <p class="font-semibold text-gray-700"><?= __('customize_image') ?></p>
                                <p class="text-gray-400 text-xs"><?= __('customize_image_sub') ?></p>
                            </div>
                        </div>
                    </div>
                    <a href="<?= $isAdmin ? '/sweetheaven/admin/dashboard.php' : '/sweetheaven/user/customize.php' ?>"
                        class="inline-flex items-center gap-2 bg-rose-500 hover:bg-rose-600 text-white font-bold px-8 py-4 rounded-2xl transition-all duration-300 shadow-lg shadow-rose-200 hover:shadow-xl hover:-translate-y-0.5 text-base">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        <?= __('customize_btn') ?>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- ═════════════════════════ PROMOTIONS ═════════════════════════ -->
    <?php $hasAnyPromo = $firstOrderRule || $freeGiftRule; ?>
    <section class="py-16 bg-[#fdf8f3]">
        <div class="max-w-7xl mx-auto px-6">

            <div class="grid <?= $hasAnyPromo ? 'lg:grid-cols-2' : 'grid-cols-1' ?>  gap-6">
                <?php if ($hasAnyPromo): ?>
                    <div class="flex flex-col gap-6">
                        <div class="text-3xl font-semibold text-center"><?= __('promo_special') ?></div>

                        <?php if ($firstOrderRule): ?>
                            <!-- Promo 1 -->
                            <div
                                class="rounded-2xl transition-all duration-200 ease-in-out hover:-translate-y-[3px] hover:shadow-[0_12px_36px_rgba(0,0,0,.07)] overflow-hidden flex flex-col md:flex-row border border-rose-100 bg-[#fdf0ee]">
                                <div class="p-12 flex-1">
                                    <span class="text-3xl mb-3 block">🎉</span>
                                    <h3 class="font-bold text-gray-800 text-xl mb-2">
                                        <?= __('promo_first_order', localizeNumber($firstOrderValue)) ?></h3>
                                    <p class="text-gray-500 text-sm leading-relaxed mb-5">
                                        <?= __('promo_first_desc', localizeNumber($firstOrderValue)) ?></p>
                                    <?php if ($isLoggedIn): ?>
                                        <a href="/sweetheaven/user/products.php"
                                            class="inline-block text-white font-semibold px-6 py-2.5 rounded-full text-sm hover:opacity-90 transition-opacity bg-[#e8746a]">
                                            <?= __('promo_claim') ?>
                                        </a>
                                    <?php else: ?>
                                        <a href="/sweetheaven/auth/login.php" id="claimDiscountBtn"
                                            onclick="if(typeof openAuthModal==='function'){event.preventDefault();openAuthModal('login');}"
                                            class="inline-block text-white font-semibold px-6 py-2.5 rounded-full text-sm hover:opacity-90 transition-opacity bg-[#e8746a]">
                                            <?= __('promo_claim') ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div class="hidden md:block w-40 flex-shrink-0">
                                    <img src="https://images.unsplash.com/photo-1563729784474-d77dbb933a9e?w=300&q=80&auto=format&fit=crop"
                                        alt="Cake slice" class="w-full h-full object-cover">
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($freeGiftRule): ?>
                            <!-- Promo 2 -->
                            <div class="rounded-2xl transition-all duration-200 ease-in-out hover:-translate-y-[3px] hover:shadow-[0_12px_36px_rgba(0,0,0,.07)] overflow-hidden flex flex-col md:flex-row border border-amber-100
                        bg-[#fffbf0]">
                                <div class="p-12 flex-1">
                                    <span class="text-3xl mb-3 block">🎁</span>
                                    <h3 class="font-bold text-gray-800 text-xl mb-2">
                                        <?= __('promo_free_gift_title', $freeGiftName) ?></h3>
                                    <p class="text-gray-500 text-sm leading-relaxed mb-5">
                                        <?= __('promo_free_gift_desc', localizeNumber($freeGiftAmount)) ?></p>
                                    <a href="/sweetheaven/user/products.php" class="inline-block text-white font-semibold px-6 py-2.5 rounded-full text-sm hover:opacity-90 transition-opacity
                                bg-amber-500">
                                        <?= __('promo_shop_now') ?>
                                    </a>
                                </div>
                                <div class="hidden md:block w-40 flex-shrink-0">
                                    <img src="https://images.unsplash.com/photo-1551024601-bec78aea704b?w=300&q=80&auto=format&fit=crop"
                                        alt="Donuts" class="w-full h-full object-cover">
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <article class="flex flex-col gap-6">
                    <div class="text-center text-3xl font-semibold"><?= __('promo_latest_products') ?></div>
                    <div class="grid <?= $hasAnyPromo ? 'grid-cols-2 sm:grid-cols-3' : 'grid-cols-2 sm:grid-cols-4 lg:grid-cols-5' ?> gap-4"
                        bg-[#fffbf0]">
                        <?php foreach ($latestProducts as $product): ?>
                            <?php
                            $imgSrc = $product['primary_image']
                                ? '/sweetheaven/' . $product['primary_image']
                                : '/sweetheaven/images/maincake.jpg';
                            ?>
                            <a href="/sweetheaven/user/product_detail.php?id=<?= $product['id'] ?>"
                                class="group relative overflow-hidden rounded-2xl bg-white border border-rose-200/50 shadow-sm hover:shadow-lg transition-all duration-500 aspect-square block">
                                <img src="<?= htmlspecialchars($imgSrc) ?>"
                                    alt="<?= htmlspecialchars(getLocalizedProductName($product)) ?>"
                                    class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
                            </a>
                        <?php endforeach; ?>
                    </div>
                </article>

            </div>
        </div>
    </section>



    <!-- ═════════════════════════ SPECIAL DISCOUNTS ═════════════════════════ -->
    <section id="special-discounts" class="py-16 bg-[#fdf8f3]">
        <div class="max-w-7xl mx-auto px-6">

            <!-- ── Section Header ── -->
            <div class="text-center mb-10">
                <div class="flex items-center justify-center gap-3 mb-3">
                    <span class="h-px w-10 bg-rose-300 inline-block"></span>
                    <span class="text-xs font-bold uppercase tracking-[.2em]" text-[#e8746a]">
                        <?= __('discount_section_label') ?>
                    </span>
                    <span class="h-px w-10 bg-rose-300 inline-block"></span>
                </div>
                <h2 class="font-display text-4xl md:text-5xl font-extrabold text-gray-800 mb-2">
                    <?= __('discount_section_title') ?>
                </h2>
                <p class="text-gray-400 text-sm"><?= __('discount_section_desc') ?></p>
            </div>

            <!-- ── Big Pink Banner ── -->
            <div
                class="relative rounded-3xl overflow-hidden mb-12 shadow-2xl bg-gradient-to-br from-[#ffe4ef] from-0% via-[#ffc2d9] via-45% to-[#ffaac8] to-100% min-h-[320px]">


                <!-- Confetti / decorative specks -->
                <div class="absolute inset-0 pointer-events-none overflow-hidden">
                    <div class="absolute top-5  left-8   w-3 h-3 rounded-full bg-yellow-400 opacity-70"></div>
                    <div class="absolute top-14 left-24  w-2 h-2 rounded-full bg-rose-400   opacity-60"></div>
                    <div class="absolute top-9  left-44  w-5 h-1 rounded-full bg-pink-300   opacity-80"></div>
                    <div class="absolute bottom-10 left-20 w-2 h-2 rounded-full bg-amber-400 opacity-70"></div>
                    <div class="absolute bottom-20 left-36 w-4 h-1 rounded-full bg-rose-300  opacity-60"></div>
                    <div class="absolute top-5  right-8   w-3 h-3 rounded-full bg-amber-400  opacity-70"></div>
                    <div class="absolute top-16 right-32  w-2 h-2 rounded-full bg-pink-400   opacity-60"></div>
                    <div class="absolute bottom-12 right-20 w-5 h-1 rounded-full bg-yellow-300 opacity-80"></div>
                    <div class="absolute bottom-24 right-44 w-3 h-3 rounded-full bg-rose-400  opacity-50"></div>
                    <!-- ribbons -->
                    <div class="absolute top-0 left-1/3  w-px h-24 bg-amber-300/35 rotate-12"></div>
                    <div class="absolute top-0 right-1/3 w-px h-20 bg-rose-300/35 -rotate-12"></div>
                    <!-- large soft circle glow right -->
                    <div class="absolute -right-20 top-1/2 -translate-y-1/2 w-72 h-72 rounded-full opacity-10"
                        class="absolute -right-20 top-1/2 -translate-y-1/2 w-72 h-72 rounded-full opacity-10 bg-[radial-gradient(circle,#fff,transparent)]">
                    </div>
                </div>

                <!--
                    4-column grid on desktop:
                      col-1 (5/12) : offer text
                      col-2 (3/12) : main cake (larger, shifted right via padding-left)
                      col-3 (2/12) : stacked accessory images
                      col-4 (2/12) : circle badge + button
                -->
                <div class="relative z-10 grid items-stretch
                            grid-cols-1
                            md:grid-cols-[5fr_3fr_2fr_2fr]
                            gap-0 min-h-[320px]">

                    <!-- ① Left: offer text ─────────────────────────────────── -->
                    <div class="flex flex-col justify-center p-8 md:pl-12 md:pr-6 md:py-10">

                        <!-- "Limited Time Offer" pill -->
                        <span class="inline-flex self-start items-center gap-1.5 mb-5 px-4 py-1.5 rounded-full
                                     text-xs font-extrabold uppercase tracking-widest text-white shadow bg-[#e8746a]">
                            <?= __('discount_limited_offer') ?>
                        </span>


                        <!-- Giant percentage -->
                        <div class="mb-3">
                            <span
                                class="block text-gray-800 text-3xl font-black uppercase tracking-wide leading-tight mb-1"><?= __('discount_up_to') ?></span>
                            <span
                                class="block font-black text-[clamp(5rem,10vw,7rem)] text-[#e8746a] leading-none drop-shadow-md"><?= localizeNumber($maxDiscountPercent) ?>%</span>
                            <span
                                class="block text-gray-800 text-[clamp(2rem,4vw,3rem)] font-black uppercase tracking-tight leading-tight mt-1"><?= __('discount_off') ?></span>
                        </div>

                        <p class="font-extrabold text-gray-600 uppercase tracking-widest text-xs mt-1 mb-6">
                            <?= __('discount_on_selected') ?>
                        </p>


                        <!-- Feature micro-badges -->
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ([
                                ['🎂', __('discount_feat_quality'), __('discount_feat_quality_sub')],
                                ['🚚', __('discount_feat_delivery'), __('discount_feat_delivery_sub')],
                                ['✅', __('discount_feat_fresh'), __('discount_feat_fresh_sub')],
                            ] as $feat): ?>
                                <div
                                    class="flex items-center gap-2 bg-white/65 backdrop-blur-sm rounded-xl px-3 py-2 shadow-sm">
                                    <span class="text-sm"><?= $feat[0] ?></span>
                                    <div class="leading-none">
                                        <p class="text-[10px] font-bold text-gray-700"><?= $feat[1] ?></p>
                                        <p class="text-[9px]  text-gray-500 mt-0.5"><?= $feat[2] ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- ② Main cake image ──────────────────────────────────── -->
                    <!-- padding-left shifts the image to the right; items-end pushes it to bottom -->

                    <div class="hidden md:flex items-center justify-center pl-4 overflow-visible">
                        <img src="/sweetheaven/images/removepink.png" alt="Featured Discount Cake"
                            class="w-auto drop-shadow-2xl" class="max-h-[500px] mb-[-2px] object-contain">
                    </div>

                    <!-- ③ Stacked accessory images (plain, no card) ── -->
                    <div class="hidden md:flex flex-col justify-center gap-2 pl-3 pr-1 py-6">
                        <!-- <img src="/sweetheaven/images/4accessorycake.png" alt="Cake accessory"
                            class="w-full object-contain drop-shadow-xl" style="max-height:300px;">
                        <img src="/sweetheaven/images/gitbox.png" alt="Gift box"
                            class="w-full object-contain drop-shadow-xl" style="max-height:300px;"> -->
                        <img src="/sweetheaven/images/ballon3.png" alt="Featured Discount Cake"
                            class="w-auto drop-shadow-2xl" class="max-h-[500px] mb-[-2px] object-contain">

                    </div>


                    <!-- ④ Right: compact circle badge + CTA ────────────────── -->
                    <div class="flex flex-col items-center justify-center gap-5 p-6 md:pr-8">

                        <!-- Circle badge -->
                        <div
                            class="relative flex items-center justify-center w-36 h-36 rounded-full shadow-xl flex-shrink-0 bg-[#e8746a]">
                            <div class="absolute inset-2 rounded-full border-2 border-white/40"></div>
                            <div class="text-center text-white px-2 z-10 space-y-0.5">
                                <p class="text-[9px] font-bold uppercase tracking-wider leading-none">
                                    <?= __('discount_badge_week') ?>
                                </p>
                                <p class="text-[10px] font-semibold leading-snug"><?= __('discount_badge_save') ?></p>
                                <p class="font-display text-xl font-black leading-none">
                                    <?= __('discount_badge_cakes') ?></p>
                                <span class="text-base">❤️</span>
                            </div>
                        </div>

                        <!-- Shop Now -->
                        <a href="/sweetheaven/user/products.php?discounted=1"
                            class="inline-flex items-center gap-1.5 font-extrabold px-6 py-3 rounded-full text-xs shadow-lg
                                  hover:-translate-y-0.5 transition-all duration-200 uppercase tracking-widest whitespace-nowrap bg-[#e8746a] text-white">
                            <?= __('discount_shop_now') ?>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                                    d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                    </div>

                </div>
            </div>
            <!-- ── Products Sub-header ── -->
            <div id="discounted-products" class="text-center mb-8">
                <div class="flex items-center justify-center gap-3 mb-1">
                    <svg class="w-4 h-4 text-rose-400" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 17l-6.2 4.3 2.4-7.4L2 9.4h7.6z" />
                    </svg>
                    <h3 class="font-display text-3xl font-bold text-gray-800"><?= __('discount_shop_the_deals') ?></h3>
                    <svg class="w-4 h-4 text-rose-400" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 17l-6.2 4.3 2.4-7.4L2 9.4h7.6z" />
                    </svg>
                </div>
                <p class="text-gray-400 text-sm"><?= __('discount_grab_deals') ?></p>
            </div>

            <!-- ── Discounted Product Cards ── -->
            <?php if (empty($discountedProducts)): ?>
                <div class="text-center py-20">
                    <div class="text-6xl mb-5">🏷️</div>
                    <h3 class="font-display text-2xl text-gray-700 mb-3"><?= __('discount_no_products') ?></h3>
                    <p class="text-gray-400 text-sm max-w-sm mx-auto leading-relaxed">
                        <?= __('discount_no_products_desc') ?>
                    </p>
                    <a href="/sweetheaven/user/products.php"
                        class="inline-flex items-center gap-2 mt-8 text-white font-semibold px-7 py-3.5 rounded-full text-sm hover:opacity-90 transition-all duration-200 shadow-md bg-[#e8746a]">
                        <?= __('discount_browse_all') ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    <?php foreach ($discountedProducts as $dp):
                        $dpImgSrc = $dp['primary_image']
                            ? '/sweetheaven/' . $dp['primary_image']
                            : '/sweetheaven/images/maincake.jpg';
                        $dpFinalPrice = $dp['discount_type'] === 'percentage'
                            ? $dp['price'] * (1 - $dp['discount_value'] / 100)
                            : max(0, $dp['price'] - $dp['discount_value']);
                        $dpBadgeLabel = getLocalizedDiscountLabel($dp);
                        ?>
                        <?php
                        $dpExtraImgs = array_filter(explode('|', $dp['extra_images'] ?? ''));
                        $dpAllImgs = array_filter(array_merge([$dpImgSrc], array_map(fn($u) => '/sweetheaven/' . $u, $dpExtraImgs)));
                        $dpCardId = 'dp-card-' . $dp['id'];
                        ?>
                        <div
                            class="group bg-white rounded-2xl overflow-hidden shadow-md hover:shadow-xl transition-all duration-300 hover:-translate-y-1 border border-pink-100">
                            <!-- Image gallery area -->
                            <div class="relative bg-pink-50 h-[180px] overflow-hidden">
                                <!-- Main image -->
                                <img id="<?= $dpCardId ?>-main" src="<?= htmlspecialchars($dpImgSrc) ?>"
                                    alt="<?= htmlspecialchars(getLocalizedProductName($dp)) ?>"
                                    class="w-full h-full object-cover transition-all duration-500">

                                <!-- Thumbnails -->
                                <?php if (count($dpAllImgs) > 1): ?>
                                    <div class="absolute bottom-1.5 left-0 right-0 flex justify-center gap-1.5 px-2">
                                        <?php foreach ($dpAllImgs as $ti => $tSrc): ?>
                                            <button type="button"
                                                onclick="event.stopPropagation(); switchCardImage('<?= $dpCardId ?>', '<?= htmlspecialchars($tSrc) ?>', this)"
                                                class="card-thumb w-9 h-9 rounded-md overflow-hidden border-2 shadow transition-all duration-200 <?= $ti === 0 ? 'border-white scale-105' : 'border-white/50 opacity-70 hover:opacity-100 hover:scale-105' ?>"
                                                title="Image <?= $ti + 1 ?>">
                                                <img src="<?= htmlspecialchars($tSrc) ?>" class="w-full h-full object-cover" alt="">
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Discount badge -->
                                <div
                                    class="absolute top-0 left-0 px-3 py-1.5 text-xs font-extrabold text-white rounded-br-xl shadow bg-[#e8746a]">
                                    <?= htmlspecialchars($dpBadgeLabel) ?>
                                </div>
                                <!-- Wishlist -->
                                <?php if (!$isAdmin): ?>
                                    <button onclick="event.stopPropagation(); toggleWishlist(<?= $dp['id'] ?>, this)"
                                        class="absolute top-2 right-2 w-8 h-8 rounded-full bg-white/90 text-gray-400 shadow flex items-center justify-center hover:bg-rose-500 hover:text-white transition-all duration-200"
                                        title="Wishlist">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                        </svg>
                                    </button>
                                <?php endif; ?>
                            </div>
                            <!-- Card body -->
                            <div class="p-4">
                                <a href="/sweetheaven/user/product_detail.php?id=<?= $dp['id'] ?>">
                                    <h3
                                        class="font-bold text-gray-800 text-sm hover:text-rose-500 transition-colors mb-2 leading-snug">
                                        <?= htmlspecialchars(getLocalizedProductName($dp)) ?>
                                    </h3>
                                </a>
                                <div class="flex items-baseline gap-2 mb-3">
                                    <span class="text-xs line-through text-gray-400">
                                        <?= currentLang() === 'my' ? convertToMyanmarDigits(number_format($dp['price'])) : number_format($dp['price']) ?>
                                        <?= __('common_mmk') ?>
                                    </span>
                                    <span class="font-extrabold text-base text-[#e8746a]">
                                        <?= currentLang() === 'my' ? convertToMyanmarDigits(number_format($dpFinalPrice)) : number_format($dpFinalPrice) ?>
                                        <span class="text-xs font-normal text-gray-400"><?= __('common_mmk') ?></span>
                                    </span>
                                </div>
                                <div class="flex gap-2">
                                    <a href="/sweetheaven/user/product_detail.php?id=<?= $dp['id'] ?>"
                                        class="flex-1 text-center border py-2 rounded-xl text-xs font-semibold hover:bg-rose-50 transition-colors"
                                        border-[#e8746a] text-[#e8746a]">
                                        <?= __('discount_view') ?>
                                    </a>
                                    <?php if (!$isAdmin): ?>
                                        <button
                                            onclick="addToCart(<?= $dp['id'] ?>, '<?= addslashes(getLocalizedProductName($dp)) ?>')"
                                            class="flex-1 flex items-center justify-center gap-1 py-2 rounded-xl text-xs font-bold text-white transition-all duration-200 hover:opacity-90 shadow bg-[#e8746a]">
                                            <!-- <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13l-1.5 7H19M9 21a1 1 0 100-2 1 1 0 000 2zm10 0a1 1 0 100-2 1 1 0 000 2z" />
                                            </svg> -->
                                            <img src="../images/cart2.png" class="w-5 h-5">
                                            <?= __('discount_add_cart') ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- View All Offers button -->
                <div class="flex justify-center mt-10">
                    <a href="/sweetheaven/user/products.php?discounted=1"
                        class="inline-flex items-center gap-2 border-2 font-bold px-8 py-3 rounded-full text-sm hover:bg-rose-50 transition-all duration-200 uppercase tracking-wide"
                        border-[#e8746a] text-[#e8746a]">
                        <?= __('discount_view_all_offers') ?>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                </div>
            <?php endif; ?>

        </div>
    </section>



    <!-- ═════════════════════════ WHY CHOOSE US ═════════════════════════ -->
    <!-- <section class="py-20 bg-[#fdf8f3]">
        <div class="max-w-7xl mx-auto px-6 grid md:grid-cols-2 gap-12 items-center">
            
            <div class="rounded-3xl overflow-hidden shadow-lg animate-[fadeUp_0.7s_ease_both]">
                <img src="../images/baker.jpg" alt="Baker at work" class="w-full h-80 object-cover">
            </div>
            
            <div class="animate-[fadeUp_0.7s_ease_both] [animation-delay:.2s]">
                <p class="text-xs font-semibold uppercase tracking-widest mb-2 text-[#e8746a]">
                    <?= __('story_label') ?>
                </p>
                <h2 class="font-display text-3xl text-gray-800 mb-5"><?= __('story_title') ?></h2>
                <p class="text-gray-500 text-[15px] leading-7 mb-6">
                    <?= __('story_desc') ?>
                </p>
                <div class="space-y-3">
                    <?php foreach ([
                        ['🌾', __('story_flour')],
                        ['🥚', __('story_eggs')],
                        ['🍓', __('story_fruit')],
                    ] as $pt): ?>
                        <div class="flex items-center gap-3 text-sm text-gray-600">
                            <span class="text-lg"><?= $pt[0] ?></span>
                            <?= $pt[1] ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section> -->

    <!-- ═════════════════════════ REVIEWS DISPLAY (removed — reviews now shown per-product on detail page) ═════════════════════════ -->
    <!-- <section id="reviews-display" class="py-20 bg-[#fdf8f3]">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex items-center justify-center mb-10 animate-[fadeUp_0.7s_ease_both]">
                <div class="text-center">
                    <p class="text-xs font-semibold uppercase tracking-widest mb-2" text-[#e8746a]>
                        <?= __('review_display_label') ?>
                    </p>

                    <h2 class="font-display text-3xl text-gray-800"><?= __('review_display_title') ?></h2>
                    <p class="text-gray-400 text-sm mt-2"><?= __('review_display_desc') ?></p>
                </div>
            </div>

            <?php if (empty($customerReviews)): ?>
                <div class="text-center text-gray-400 py-10">
                    <p class="text-4xl mb-3">💬</p>
                    <p class="text-sm"><?= __('review_no_reviews') ?></p>
                </div>
            <?php elseif (count($customerReviews) <= 4): ?>
                <div class="flex flex-wrap justify-center gap-6">
                    <?php foreach ($customerReviews as $r): ?>
                        <div class="w-full sm:w-[300px]">
                            <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm text-left h-full">
                                <div class="flex items-center gap-3 mb-4">
                                    <div
                                        class="w-10 h-10 bg-rose-100 rounded-full flex items-center justify-center text-rose-500 font-bold text-sm flex-shrink-0 overflow-hidden">
                                        <?php if (!empty($r['profile_image'])): ?>
                                            <img src="/sweetheaven/<?= htmlspecialchars($r['profile_image']) ?>"
                                                class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <?= strtoupper(substr($r['name'], 0, 1)) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-semibold text-gray-700 text-sm truncate">
                                            <?= htmlspecialchars($r['name']) ?></p>
                                        <p class="text-xs text-gray-400"><?= date('M j, Y', strtotime($r['created_at'])) ?></p>
                                    </div>
                                </div>
                                <?php if ($r['rating']): ?>
                                    <div class="flex items-center gap-1 mb-3">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <svg class="w-4 h-4 <?= $i <= $r['rating'] ? 'text-amber-400' : 'text-gray-200' ?>"
                                                fill="currentColor" viewBox="0 0 20 20">
                                                <path
                                                    d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                            </svg>
                                        <?php endfor; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($r['product_name'])): ?>
                                    <p class="text-xs font-semibold text-rose-500 mb-2 truncate">
                                        <?= htmlspecialchars($r['product_name']) ?>
                                    </p>
                                <?php endif; ?>
                                <p class="text-gray-500 text-sm leading-7">"<?= htmlspecialchars($r['message']) ?>"</p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="flex items-center gap-4">
                    <button id="reviewPrev"
                        class="flex-shrink-0 w-10 h-10 rounded-full bg-white shadow-md flex items-center justify-center text-stone-600 hover:bg-white hover:text-rose-500 transition-all opacity-0 md:opacity-100 hover:shadow-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    <div class="overflow-hidden rounded-2xl flex-1 min-w-0">
                        <div class="flex transition-transform duration-500 ease-in-out [will-change:transform]"
                            id="reviewTrack">
                            <?php foreach ($customerReviews as $r): ?>
                                <div class="flex-shrink-0 px-2 [transition:width_0.3s_ease]">
                                    <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm h-full text-left">
                                        <div class="flex items-center gap-3 mb-4">
                                            <div
                                                class="w-10 h-10 bg-rose-100 rounded-full flex items-center justify-center text-rose-500 font-bold text-sm flex-shrink-0 overflow-hidden">
                                                <?php if (!empty($r['profile_image'])): ?>
                                                    <img src="/sweetheaven/<?= htmlspecialchars($r['profile_image']) ?>"
                                                        class="w-full h-full object-cover">
                                                <?php else: ?>
                                                    <?= strtoupper(substr($r['name'], 0, 1)) ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="font-semibold text-gray-700 text-sm truncate">
                                                    <?= htmlspecialchars($r['name']) ?>
                                                </p>
                                                <p class="text-xs text-gray-400">
                                                    <?= date('M j, Y', strtotime($r['created_at'])) ?>
                                                </p>
                                            </div>
                                        </div>
                                        <?php if ($r['rating']): ?>
                                            <div class="flex items-center gap-1 mb-3">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <svg class="w-4 h-4 <?= $i <= $r['rating'] ? 'text-amber-400' : 'text-gray-200' ?>"
                                                        fill="currentColor" viewBox="0 0 20 20">
                                                        <path
                                                            d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                                    </svg>
                                                <?php endfor; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($r['product_name'])): ?>
                                            <p class="text-xs font-semibold text-rose-500 mb-2 truncate">
                                                <?= htmlspecialchars($r['product_name']) ?>
                                            </p>
                                        <?php endif; ?>
                                        <p class="text-gray-500 text-sm leading-7">"<?= htmlspecialchars($r['message']) ?>"</p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <button id="reviewNext"
                        class="flex-shrink-0 w-10 h-10 rounded-full bg-white shadow-md flex items-center justify-center text-stone-600 hover:bg-white hover:text-rose-500 transition-all opacity-0 md:opacity-100 hover:shadow-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>

                <div class="flex justify-center gap-2 mt-6" id="reviewDots"></div>
            <?php endif; ?>
        </div>
    </section> -->

    <!-- ═════════════════════════ CONTACT US ═════════════════════════ -->
    <section id="contact-us" class="py-10 lg:py-14 bg-[#fdf8f3]">
        <div class="max-w-7xl mx-auto px-6">

            <!-- Section Title -->
            <div class="text-center mb-10">
                <p class="text-xs font-semibold uppercase tracking-widest text-[#e8746a] mb-2">
                    <?= __('contact_us_label') ?></p>
                <h2 class="font-display text-4xl text-gray-800"><?= __('contact_us_title') ?></h2>
                <p class="text-gray-400 text-sm mt-2"><?= __('contact_us_desc') ?></p>
            </div>

            <div class="grid lg:grid-cols-2 gap-8 items-center">

                <!-- Left: Form with light pink background -->
                <div class="bg-pink-100 rounded-3xl p-8 sm:p-10 shadow-sm">
                    <!-- Header -->
                    <div class="mb-8">
                        <p class="text-xs font-semibold uppercase tracking-widest text-[#e8746a] mb-2">
                            <?= __('contact_us_form_label') ?></p>
                        <h3 class="font-display text-3xl sm:text-4xl text-gray-800 mb-3">
                            <?= __('contact_us_form_title') ?></h3>
                        <p class="text-gray-500 text-sm"><?= __('contact_us_form_desc') ?></p>
                    </div>

                    <?php if ($isAdmin): ?>
                        <!-- Admin/Cashier: not allowed notice -->
                        <div class="flex flex-col items-center justify-center text-center py-10 px-4">
                            <div class="w-16 h-16 rounded-full bg-blue-100 flex items-center justify-center mb-4">
                                <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                </svg>
                            </div>
                            <p class="text-gray-700 font-bold text-base mb-1"><?= __('contact_us_restricted_title') ?></p>
                            <p class="text-gray-500 text-sm leading-relaxed">
                                <?= __('contact_us_restricted_desc') ?>
                            </p>
                        </div>
                    <?php elseif (!$isLoggedIn): ?>
                        <!-- Guest: not logged in notice -->
                        <div class="flex flex-col items-center justify-center text-center py-10 px-4">
                            <div class="w-16 h-16 rounded-full bg-rose-100 flex items-center justify-center mb-4">
                                <svg class="w-8 h-8 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                            <p class="text-gray-700 font-bold text-lg mb-2"><?= __('contact_us_signin_title') ?></p>
                            <p class="text-gray-500 text-sm leading-relaxed mb-6">
                                <?= __('contact_us_signin_desc') ?>
                            </p>
                            <a href="#"
                                onclick="if(typeof openAuthModal==='function'){event.preventDefault(); openAuthModal('login');}"
                                class="bg-[#e8746a] hover:bg-[#d4635a] text-white font-bold px-8 py-3 rounded-xl transition-all shadow-md hover:shadow-lg">
                                <?= __('contact_us_signin_btn') ?>
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Form — visible to logged-in customers -->
                        <form id="contactForm" class="space-y-5">
                            <div class="grid sm:grid-cols-2 gap-4">
                                <div class="bg-gray-50 rounded-xl px-4 py-3 border border-gray-100">
                                    <label
                                        class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1"><?= __('contact_us_name_label') ?></label>
                                    <p class="text-sm font-semibold text-gray-700 truncate">
                                        <?= htmlspecialchars($currentUser['name'] ?? '') ?></p>
                                </div>
                                <div class="bg-gray-50 rounded-xl px-4 py-3 border border-gray-100">
                                    <label
                                        class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1"><?= __('contact_us_email_label') ?></label>
                                    <p class="text-sm font-semibold text-gray-700 truncate">
                                        <?= htmlspecialchars($currentUser['email'] ?? '') ?></p>
                                </div>
                            </div>
                            <div>
                                <label
                                    class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2"><?= __('contact_us_message_label') ?>
                                    <span class="text-rose-400">*</span></label>
                                <textarea id="contactMessage" rows="4" required
                                    class="w-full px-4 py-3.5 rounded-xl bg-white border border-pink-200 text-gray-700 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm resize-none transition-all"
                                    placeholder="<?= htmlspecialchars(__('contact_us_message_ph')) ?>"></textarea>
                            </div>
                            <button type="submit" id="contactSubmitBtn"
                                class="w-full sm:w-auto bg-[#e8746a] hover:bg-[#d4635a] text-white font-bold px-10 py-3.5 rounded-xl transition-all text-sm shadow-md hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0"
                                data-submit-text="<?= htmlspecialchars(__('contact_us_submit')) ?>"
                                data-submitting-text="<?= htmlspecialchars(__('contact_us_submitting')) ?>">
                                <?= __('contact_us_submit') ?>
                            </button>
                        </form>
                    <?php endif; ?>

                    <!-- Message -->
                    <div id="contactFormMsg" class="mt-5 hidden"></div>
                </div>

                <!-- Right: Image only, no background -->
                <div class="hidden lg:flex justify-center items-center">
                    <img src="/sweetheaven/images/review.jpg" alt="Sweet Heaven Bakery"
                        class="w-80 h-80 lg:w-[420px] lg:h-[420px] object-cover rounded-[2rem] shadow-xl">
                </div>

            </div>
        </div>
    </section>

    <!-- ═════════════════════════ ABOUT US ═════════════════════════ -->
    <section class="py-20 bg-[#fdf8f3]">
        <div class="max-w-7xl mx-auto px-6">
            <div class="grid md:grid-cols-2 gap-12 items-center">
                <div class="rounded-3xl overflow-hidden shadow-lg animate-[fadeUp_0.7s_ease_both]">
                    <img src="../images/aboutus.jpg" alt="About Sweet Heaven Bakery" class="w-full h-96 object-cover">
                </div>
                <div class="animate-[fadeUp_0.7s_ease_both] [animation-delay:.2s]">
                    <p class="text-xs font-semibold uppercase tracking-widest mb-2" text-[#e8746a]">
                        <?= __('about_label') ?>
                    </p>
                    <h2 class="font-display text-4xl text-gray-800 mb-6"><?= __('about_title') ?></h2>
                    <p class="text-gray-500 text-[15px] leading-7 mb-6">
                        <?= __('about_desc') ?>
                    </p>
                    <!-- <div class="flex items-center gap-4 text-sm">
                        <div class="flex items-center gap-2">
                            <span
                                class="w-8 h-8 bg-rose-100 rounded-full flex items-center justify-center text-rose-500">🎂</span>
                            <span class="font-semibold text-gray-700"><?= __('about_exp') ?></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                class="w-8 h-8 bg-rose-100 rounded-full flex items-center justify-center text-rose-500">❤️</span>
                            <span class="font-semibold text-gray-700"><?= __('about_love') ?></span>
                        </div>
                    </div> -->
                </div>
            </div>
        </div>
    </section>

    <script>
        document.getElementById('contactForm')?.addEventListener('submit', function (e) {
            e.preventDefault();
            const btn = document.getElementById('contactSubmitBtn');
            const msgBox = document.getElementById('contactFormMsg');
            const submitText = btn.dataset.submitText || 'Send Message';
            const submittingText = btn.dataset.submittingText || 'Sending...';
            const errTimeout = <?= json_encode(__('contact_us_err_timeout')) ?>;
            const errGeneral = <?= json_encode(__('contact_us_err_general')) ?>;

            btn.disabled = true;
            btn.textContent = submittingText;
            msgBox.classList.add('hidden');

            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 30000);

            fetch('/sweetheaven/api/contact_us.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    message: document.getElementById('contactMessage').value

                }),
                signal: controller.signal
            })
                .then(r => {
                    clearTimeout(timeoutId);
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(data => {
                    msgBox.classList.remove('hidden');
                    if (data.success) {
                        msgBox.className = 'mt-5 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl text-sm font-medium';
                        msgBox.textContent = data.message;
                        document.getElementById('contactForm').reset();
                    } else {
                        msgBox.className = 'mt-5 bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-xl text-sm font-medium';
                        msgBox.textContent = data.message || errGeneral;
                    }
                    btn.disabled = false;
                    btn.textContent = submitText;
                })
                .catch(err => {
                    clearTimeout(timeoutId);
                    msgBox.classList.remove('hidden');
                    msgBox.className = 'mt-5 bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-xl text-sm font-medium';
                    if (err.name === 'AbortError') {
                        msgBox.textContent = errTimeout;
                    } else {
                        msgBox.textContent = errGeneral + ' (' + err.message + ')';
                    }
                    btn.disabled = false;
                    btn.textContent = submitText;
                });
        });
    </script>

    <!-- Review carousel script removed (reviews section commented out)
    <script>
        (function () {
            const track = document.getElementById('reviewTrack');
            const prevBtn = document.getElementById('reviewPrev');
            const nextBtn = document.getElementById('reviewNext');
            const dotsContainer = document.getElementById('reviewDots');
            if (!track || !prevBtn || !nextBtn || !dotsContainer) return;
            const originalItems = Array.from(track.children);
            const realCount = originalItems.length;

            function getItemsPerView() {
                if (window.innerWidth < 640) return 1;
                if (window.innerWidth < 1024) return 2;
                return 4;
            }

            const cloneCount = realCount;
            originalItems.forEach(item => {
                const clone = item.cloneNode(true);
                track.appendChild(clone);
            });
            for (let i = realCount - 1; i >= 0; i--) {
                const clone = originalItems[i].cloneNode(true);
                track.insertBefore(clone, track.firstChild);
            }

            const allItems = Array.from(track.children);
            const totalItems = allItems.length;
            const startIndex = realCount;

            let currentIndex = startIndex;
            let autoPlayTimer = null;
            const autoPlayDelay = 3500;

            function getTotalRealPages() {
                return Math.ceil(realCount / getItemsPerView());
            }

            function getCurrentPage() {
                const ipv = getItemsPerView();
                const rawPage = Math.floor(currentIndex / ipv);
                const totalRealPages = getTotalRealPages();
                const leadingPages = Math.floor(realCount / ipv);
                return ((rawPage - leadingPages) % totalRealPages + totalRealPages) % totalRealPages;
            }

            function render() {
                const ipv = getItemsPerView();
                const itemWidth = 100 / ipv;
                allItems.forEach(item => item.style.width = itemWidth + '%');
                updatePosition(false);
            }

            function updatePosition(animate = true) {
                const ipv = getItemsPerView();
                const offset = -(currentIndex / ipv) * 100;
                if (!animate) track.style.transition = 'none';
                track.style.transform = `translateX(${offset}%)`;
                if (!animate) {
                    track.offsetHeight;
                    track.style.transition = '';
                }
                updateDots();
            }

            function goTo(index, animate = true) {
                const maxIndex = totalItems - getItemsPerView();
                index = Math.max(0, Math.min(index, maxIndex));
                currentIndex = index;
                updatePosition(animate);
            }

            function next() {
                const ipv = getItemsPerView();
                const nextIndex = currentIndex + ipv;
                const maxRealIndex = startIndex + realCount - ipv;
                if (nextIndex >= startIndex + realCount) {
                    goTo(nextIndex, true);
                    setTimeout(() => goTo(startIndex, false), 550);
                } else {
                    goTo(nextIndex);
                }
            }

            function prev() {
                const ipv = getItemsPerView();
                const prevIndex = currentIndex - ipv;
                if (prevIndex < startIndex) {
                    goTo(prevIndex, true);
                    setTimeout(() => goTo(startIndex + realCount - ipv, false), 550);
                } else {
                    goTo(prevIndex);
                }
            }

            function updateDots() {
                const ipv = getItemsPerView();
                const totalPages = getTotalRealPages();
                const currentPage = getCurrentPage();

                dotsContainer.innerHTML = '';
                for (let i = 0; i < totalPages; i++) {
                    const dot = document.createElement('button');
                    dot.className = 'w-2.5 h-2.5 rounded-full bg-stone-300 hover:bg-rose-300 transition-all duration-300';
                    if (i === currentPage) { dot.style.background = '#f43f5e'; dot.style.width = '24px'; dot.style.borderRadius = '999px'; }
                    dot.setAttribute('aria-label', `Go to page ${i + 1}`);
                    dot.addEventListener('click', () => {
                        const targetIndex = startIndex + i * ipv;
                        goTo(targetIndex);
                        resetAutoPlay();
                    });
                    dotsContainer.appendChild(dot);
                }
            }

            function startAutoPlay() {
                stopAutoPlay();
                autoPlayTimer = setInterval(next, autoPlayDelay);
            }

            function stopAutoPlay() {
                if (autoPlayTimer) {
                    clearInterval(autoPlayTimer);
                    autoPlayTimer = null;
                }
            }

            function resetAutoPlay() {
                startAutoPlay();
            }

            prevBtn.addEventListener('click', () => { prev(); resetAutoPlay(); });
            nextBtn.addEventListener('click', () => { next(); resetAutoPlay(); });

            let resizeTimer;
            window.addEventListener('resize', () => {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(() => {
                    const ipv = getItemsPerView();
                    const itemWidth = 100 / ipv;
                    allItems.forEach(item => item.style.width = itemWidth + '%');
                    const maxIndex = totalItems - ipv;
                    if (currentIndex > maxIndex) currentIndex = maxIndex;
                    updatePosition(false);
                    updateDots();
                }, 200);
            });

            track.addEventListener('mouseenter', stopAutoPlay);
            track.addEventListener('mouseleave', startAutoPlay);

            render();
            startAutoPlay();
            updateDots();
        })();
    </script>
    -->

    <!-- Toast -->
    <div id="toast"
        class="hidden fixed bottom-6 right-6 text-white px-5 py-3 rounded-xl shadow-lg text-sm font-medium z-50 flex items-center gap-2 bg-[#e8746a]">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        <span id="toastMsg"><?= __('toast_added_cart') ?></span>
    </div>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>

    <script>
        /* ── Pending Action State ──────────────────────────────────────────────
           Stores the cart/wishlist action a guest attempted so it can be
           executed automatically after they log in or register.
        ─────────────────────────────────────────────────────────────────── */
        let _pendingAction = null;

        function setPendingAction(action) {
            _pendingAction = action;
        }

        function clearPendingAction() {
            _pendingAction = null;
        }

        function executePendingAction() {
            if (!_pendingAction) return Promise.resolve();
            const action = _pendingAction;
            clearPendingAction();

            if (action.type === 'cart') {
                // Execute the cart add that was blocked; return the Promise so the
                // caller can wait for completion before reloading the page
                return fetch('/sweetheaven/api/cart.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=add&product_id=${action.productId}&qty=1`
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            showToast(`<?= __('toast_added_cart_js') ?>`.replace('%s', action.productName));
                            const badge = document.getElementById('cartBadge');
                            if (badge) { badge.textContent = data.cart_count; badge.classList.remove('hidden'); }
                        }
                    });

            } else if (action.type === 'wishlist') {
                // Execute the wishlist toggle that was blocked
                return fetch('/sweetheaven/api/wishlist.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `product_id=${action.productId}`
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            // Update the button UI if the element reference is still valid
                            const btn = action.btn;
                            if (btn && document.body.contains(btn)) {
                                const svg = btn.querySelector('svg');
                                btn.classList.toggle('bg-rose-500', data.is_wishlisted);
                                btn.classList.toggle('bg-white/90', !data.is_wishlisted);
                                btn.classList.toggle('text-white', data.is_wishlisted);
                                btn.classList.toggle('text-gray-400', !data.is_wishlisted);
                                if (svg) svg.setAttribute('fill', data.is_wishlisted ? 'currentColor' : 'none');
                            }
                            showToast(data.is_wishlisted
                                ? '❤️ ' + (data.message || '<?= __('toast_added_wishlist') ?>')
                                : '💔 <?= __('toast_removed_wishlist') ?>');
                            if (typeof updateWishlistBadge === 'function') updateWishlistBadge(data.wishlist_count);
                        }
                    });
            }

            // Fallthrough: no recognised action type
            return Promise.resolve();
        }

        /* ── Card Image Gallery ─────────────────────────────────────────────── */
        function switchCardImage(cardId, src, thumbBtn) {
            // Swap main image
            const main = document.getElementById(cardId + '-main');
            if (main) main.src = src;
            // Reset all siblings in the same thumbnail row
            const strip = thumbBtn.closest('.flex');
            if (strip) {
                strip.querySelectorAll('.card-thumb').forEach(btn => {
                    btn.classList.remove('border-white', 'scale-105');
                    btn.classList.add('border-white/50', 'opacity-75');
                });
            }
            // Highlight active thumb
            thumbBtn.classList.remove('border-white/50', 'opacity-75');
            thumbBtn.classList.add('border-white', 'scale-105');
        }

        /* ── Cart ──────────────────────────────────────────────────────────── */
        function addToCart(productId, productName) {

            fetch('/sweetheaven/api/cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=add&product_id=${productId}&qty=1`
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showToast(`<?= __('toast_added_cart_js') ?>`.replace('%s', productName));
                        const badge = document.getElementById('cartBadge');
                        if (badge) { badge.textContent = data.cart_count; badge.classList.remove('hidden'); }
                    } else if (data.redirect) {
                        // Store intent, then prompt login
                        setPendingAction({ type: 'cart', productId, productName });
                        openAuthModal('login');
                    }
                });
        }

        /* ── Wishlist ───────────────────────────────────────────────────────── */
        function toggleWishlist(productId, btn) {
            fetch('/sweetheaven/api/wishlist.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `product_id=${productId}`
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const svg = btn.querySelector('svg');
                        btn.classList.toggle('bg-rose-500', data.is_wishlisted);
                        btn.classList.toggle('bg-white/90', !data.is_wishlisted);
                        btn.classList.toggle('text-white', data.is_wishlisted);
                        btn.classList.toggle('text-gray-400', !data.is_wishlisted);
                        svg.setAttribute('fill', data.is_wishlisted ? 'currentColor' : 'none');
                        showToast(data.is_wishlisted ? '❤️ ' + (data.message || '<?= __('toast_added_wishlist') ?>') : '💔 <?= __('toast_removed_wishlist') ?>');
                        if (typeof updateWishlistBadge === 'function') updateWishlistBadge(data.wishlist_count);
                    } else if (data.redirect) {
                        // Store intent (keep btn reference for UI update after login)
                        setPendingAction({ type: 'wishlist', productId, btn });
                        openAuthModal('login');
                    }
                });
        }

        /* ── Toast ─────────────────────────────────────────────────────────── */
        function showToast(msg) {
            const t = document.getElementById('toast');
            document.getElementById('toastMsg').textContent = msg;
            t.classList.remove('hidden');
            t.classList.add('flex');
            setTimeout(() => { t.classList.add('hidden'); t.classList.remove('flex'); }, 3000);
        }
    </script>

    <!-- ═══════════════ AUTH MODAL ═══════════════ -->
    <div id="authModal" class="fixed inset-0 z-[999] flex items-center justify-center p-4 hidden" role="dialog"
        aria-modal="true" aria-label="Authentication">
        <!-- Backdrop -->
        <div id="authBackdrop" class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeAuthModal()"></div>

        <!-- Card -->
        <div class="relative w-full max-w-md overflow-hidden bg-[#fff9f9] rounded-[28px] shadow-[0_24px_64px_rgba(180,80,80,.14),0_4px_16px_rgba(200,100,100,.08)] border border-[#f5dede]"
            style="animation: modalSlideIn 0.38s cubic-bezier(0.34,1.46,0.64,1) both">

            <!-- Close button -->
            <button onclick="closeAuthModal()" id="authCloseBtn"
                class="absolute top-[18px] right-[18px] w-8 h-8 rounded-full bg-[#fce8e8] text-[#b87070] border-none cursor-pointer flex items-center justify-center transition-all duration-200 hover:bg-[#f9d4d4] hover:text-[#9a4f4f] hover:scale-110 z-10"
                aria-label="Close">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                    <path d="M1 1l12 12M13 1L1 13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                </svg>
            </button>

            <!-- Brand header -->
            <div class="flex items-center gap-[14px] px-[30px] pt-[30px] pb-0">
                <div
                    class="w-[52px] h-[52px] rounded-2xl shrink-0 bg-gradient-to-br from-[#fce8e8] to-[#fdf0f0] border border-[#f5d5d5] flex items-center justify-center">
                    <!-- <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#c97878" stroke-width="1.6">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M21 15.546c-.523 0-1.046.151-1.5.454a2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.701 2.701 0 00-1.5-.454M9 6v2m3-2v2m3-2v2M9 3h.01M12 3h.01M15 3h.01M21 21v-7a2 2 0 00-2-2H5a2 2 0 00-2 2v7h18z" />
                    </svg> -->
                    <img src="../images/cake.png" class="w-5 h-5">
                </div>
                <div>
                    <h2 id="authModalTitle" class="text-xl font-bold text-[#3d2020] leading-[1.3] m-0">
                        <?= __('login_welcome') ?? 'Welcome back' ?></h2>
                    <p id="authModalSubtitle" class="text-[.78rem] text-[#b08080] mt-[3px] mb-0 mx-0">
                        <?= __('login_subtitle') ?></p>
                </div>
            </div>

            <div class="px-[30px] pt-[22px] pb-[28px]">

                <!-- LOGIN PANEL -->
                <div id="loginPanel">
                    <div id="loginError"
                        class="flex items-start gap-[9px] px-3.5 py-[11px] rounded-xl text-[.8rem] leading-[1.45] mb-2.5 bg-[#fff0f0] border border-[#f5c8c8] text-[#a85050] hidden">
                        <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor" class="shrink-0">
                            <path fill-rule="evenodd"
                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                                clip-rule="evenodd" />
                        </svg>
                        <span id="loginErrorMsg"></span>
                    </div>

                    <form id="modalLoginForm" class="flex flex-col gap-3 mt-1" onsubmit="submitLogin(event)">
                        <div class="relative flex items-center">
                            <span
                                class="absolute left-[13px] text-[#d4a0a0] pointer-events-none flex transition-colors duration-200 peer-focus:text-[#c97878]">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </span>
                            <input type="email" id="modalEmail" name="email" required autocomplete="email"
                                placeholder="<?= __('login_email_ph') ?>"
                                class="w-full py-3 pl-10 pr-3.5 rounded-[14px] border-[1.5px] border-[#f0d8d8] bg-white text-[.855rem] text-[#3d2020] outline-none font-inherit transition-all duration-200 placeholder:text-[#d4adad] focus:border-[#e8a0a0] focus:shadow-[0_0_0_3.5px_rgba(220,130,130,.14)] focus:bg-[#fffbfb] peer">
                        </div>
                        <div class="relative flex items-center">
                            <span
                                class="absolute left-[13px] text-[#d4a0a0] pointer-events-none flex transition-colors duration-200 peer-focus:text-[#c97878]">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </span>
                            <input type="password" id="modalPassword" name="password" required
                                autocomplete="current-password" placeholder="<?= __('login_password_ph') ?>"
                                class="w-full py-3 pl-10 pr-[42px] rounded-[14px] border-[1.5px] border-[#f0d8d8] bg-white text-[.855rem] text-[#3d2020] outline-none font-inherit transition-all duration-200 placeholder:text-[#d4adad] focus:border-[#e8a0a0] focus:shadow-[0_0_0_3.5px_rgba(220,130,130,.14)] focus:bg-[#fffbfb] peer">
                            <button type="button" onclick="toggleModalPassword('modalPassword',this)"
                                class="absolute right-[13px] bg-transparent border-none cursor-pointer text-[#d4a0a0] p-0.5 flex transition-colors duration-200 hover:text-[#c97878]"
                                tabindex="-1">
                                <svg width="17" height="17" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </button>
                        </div>
                        <button type="submit" id="loginSubmitBtn"
                            class="w-full py-3 px-5 rounded-[14px] bg-gradient-to-br from-[#e8918a] to-[#d97070] text-white text-[.9rem] font-semibold border-none cursor-pointer flex items-center justify-center gap-2 mt-1 font-inherit tracking-[.01em] transition-all duration-200 shadow-[0_4px_16px_rgba(210,100,100,.25)] hover:opacity-[.92] hover:-translate-y-px hover:shadow-[0_8px_22px_rgba(210,100,100,.3)] active:scale-[.98] disabled:opacity-[.65] disabled:cursor-not-allowed disabled:transform-none">
                            <span id="loginBtnText"><?= __('login_btn') ?></span>
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </button>
                    </form>

                    <!-- <div class="auth-demo-box">
                        <p class="auth-demo-title">&#10022; Demo Credentials</p>
                        <p>Admin: admin@sweetheaven.com <span class="auth-demo-sep">/</span> admin123</p>
                        <p>Customer: customer@sweetheaven.com <span class="auth-demo-sep">/</span> customer123</p>
                    </div> -->

                    <p class="text-center text-[.8rem] text-[#b08080] mt-[18px]">
                        <?= __('login_no_account') ?>
                        <button type="button" onclick="switchTab('register')"
                            class="bg-transparent border-none cursor-pointer font-bold text-[#d97070] text-inherit font-inherit p-0 ml-[3px] transition-colors duration-200 hover:text-[#b85555] hover:underline"><?= __('login_signup_link') ?></button>
                    </p>
                </div>

                <!-- REGISTER PANEL -->
                <div id="registerPanel" class="hidden">
                    <div id="registerError"
                        class="flex items-start gap-[9px] px-3.5 py-[11px] rounded-xl text-[.8rem] leading-[1.45] mb-2.5 bg-[#fff0f0] border border-[#f5c8c8] text-[#a85050] hidden">
                        <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor" class="shrink-0">
                            <path fill-rule="evenodd"
                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                                clip-rule="evenodd" />
                        </svg>
                        <span id="registerErrorMsg"></span>
                    </div>
                    <div id="registerSuccess"
                        class="flex items-start gap-[9px] px-3.5 py-[11px] rounded-xl text-[.8rem] leading-[1.45] mb-2.5 bg-[#f0faf4] border border-[#b8e6c8] text-[#3a7a55] hidden">
                        <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor" class="shrink-0">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd" />
                        </svg>
                        <span id="registerSuccessMsg"></span>
                    </div>

                    <form id="modalRegisterForm" class="flex flex-col gap-3 mt-1" onsubmit="submitRegister(event)">
                        <div class="relative flex items-center">
                            <span
                                class="absolute left-[13px] text-[#d4a0a0] pointer-events-none flex transition-colors duration-200 peer-focus:text-[#c97878]">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </span>
                            <input type="text" id="regName" name="name" required autocomplete="name"
                                placeholder="<?= __('register_name_ph') ?>"
                                class="w-full py-3 pl-10 pr-3.5 rounded-[14px] border-[1.5px] border-[#f0d8d8] bg-white text-[.855rem] text-[#3d2020] outline-none font-inherit transition-all duration-200 placeholder:text-[#d4adad] focus:border-[#e8a0a0] focus:shadow-[0_0_0_3.5px_rgba(220,130,130,.14)] focus:bg-[#fffbfb] peer">
                        </div>
                        <div class="relative flex items-center">
                            <span
                                class="absolute left-[13px] text-[#d4a0a0] pointer-events-none flex transition-colors duration-200 peer-focus:text-[#c97878]">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </span>
                            <input type="email" id="regEmail" name="email" required autocomplete="email"
                                placeholder="<?= __('register_email_ph') ?>"
                                class="w-full py-3 pl-10 pr-3.5 rounded-[14px] border-[1.5px] border-[#f0d8d8] bg-white text-[.855rem] text-[#3d2020] outline-none font-inherit transition-all duration-200 placeholder:text-[#d4adad] focus:border-[#e8a0a0] focus:shadow-[0_0_0_3.5px_rgba(220,130,130,.14)] focus:bg-[#fffbfb] peer">
                        </div>
                        <div class="relative flex items-center">
                            <span
                                class="absolute left-[13px] text-[#d4a0a0] pointer-events-none flex transition-colors duration-200 peer-focus:text-[#c97878]">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </span>
                            <input type="password" id="regPassword" name="password" required autocomplete="new-password"
                                placeholder="<?= __('register_pass_ph') ?>"
                                class="w-full py-3 pl-10 pr-[42px] rounded-[14px] border-[1.5px] border-[#f0d8d8] bg-white text-[.855rem] text-[#3d2020] outline-none font-inherit transition-all duration-200 placeholder:text-[#d4adad] focus:border-[#e8a0a0] focus:shadow-[0_0_0_3.5px_rgba(220,130,130,.14)] focus:bg-[#fffbfb] peer">
                            <button type="button" onclick="toggleModalPassword('regPassword',this)"
                                class="absolute right-[13px] bg-transparent border-none cursor-pointer text-[#d4a0a0] p-0.5 flex transition-colors duration-200 hover:text-[#c97878]"
                                tabindex="-1">
                                <svg width="17" height="17" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </button>
                        </div>
                        <div class="relative flex items-center">
                            <span
                                class="absolute left-[13px] text-[#d4a0a0] pointer-events-none flex transition-colors duration-200 peer-focus:text-[#c97878]">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </span>
                            <input type="password" id="regConfirm" name="confirm_password" required
                                autocomplete="new-password" placeholder="<?= __('register_confirm_ph') ?>"
                                class="w-full py-3 pl-10 pr-3.5 rounded-[14px] border-[1.5px] border-[#f0d8d8] bg-white text-[.855rem] text-[#3d2020] outline-none font-inherit transition-all duration-200 placeholder:text-[#d4adad] focus:border-[#e8a0a0] focus:shadow-[0_0_0_3.5px_rgba(220,130,130,.14)] focus:bg-[#fffbfb] peer">
                        </div>
                        <button type="submit" id="registerSubmitBtn"
                            class="w-full py-3 px-5 rounded-[14px] bg-gradient-to-br from-[#e8918a] to-[#d97070] text-white text-[.9rem] font-semibold border-none cursor-pointer flex items-center justify-center gap-2 mt-1 font-inherit tracking-[.01em] transition-all duration-200 shadow-[0_4px_16px_rgba(210,100,100,.25)] hover:opacity-[.92] hover:-translate-y-px hover:shadow-[0_8px_22px_rgba(210,100,100,.3)] active:scale-[.98] disabled:opacity-[.65] disabled:cursor-not-allowed disabled:transform-none">
                            <span id="registerBtnText"><?= __('register_btn') ?></span>
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7" />
                            </svg>
                        </button>
                    </form>

                    <p class="text-center text-[.8rem] text-[#b08080] mt-[18px]">
                        <?= __('register_have_account') ?>
                        <button type="button" onclick="switchTab('login')"
                            class="bg-transparent border-none cursor-pointer font-bold text-[#d97070] text-inherit font-inherit p-0 ml-[3px] transition-colors duration-200 hover:text-[#b85555] hover:underline"><?= __('register_signin_link') ?></button>
                    </p>
                </div>

            </div>
        </div>
    </div>



    <script>
        /* ── Auth Modal ── */
        function openAuthModal(tab = 'login') {
            switchTab(tab);
            document.getElementById('authModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            // focus first input after animation
            setTimeout(() => {
                const el = tab === 'login'
                    ? document.getElementById('modalEmail')
                    : document.getElementById('regName');
                el && el.focus();
            }, 100);
        }

        function closeAuthModal() {
            document.getElementById('authModal').classList.add('hidden');
            document.body.style.overflow = '';
            // Clear all alert states
            setLoginError('');
            setRegisterError('');
            document.getElementById('registerSuccess').classList.add('hidden');
            // Reset both forms so they're fresh on next open
            document.getElementById('modalLoginForm').reset();
            document.getElementById('modalRegisterForm').reset();
            // Discard any stored pending action so stale intent doesn't linger
            clearPendingAction();
        }

        function switchTab(tab) {
            const isLogin = tab === 'login';
            document.getElementById('loginPanel').classList.toggle('hidden', !isLogin);
            document.getElementById('registerPanel').classList.toggle('hidden', isLogin);

            // Update title and subtitle
            const title = document.getElementById('authModalTitle');
            const subtitle = document.getElementById('authModalSubtitle');
            if (title) title.textContent = isLogin ? '<?= __('login_welcome') ?? 'Welcome back' ?>' : '<?= __('register_title_short') ?? 'Create an account' ?>';
            if (subtitle) subtitle.textContent = isLogin
                ? '<?= __('login_subtitle') ?>'
                : '<?= __('register_subtitle2') ?? 'Join us and enjoy exclusive treats' ?>';

            // Clear ALL alerts whenever the panel changes
            setLoginError('');
            setRegisterError('');
            document.getElementById('registerSuccess').classList.add('hidden');
        }

        function setLoginError(msg) {
            const el = document.getElementById('loginError');
            document.getElementById('loginErrorMsg').textContent = msg || '';
            if (msg) {
                el.classList.remove('hidden');
            } else {
                el.classList.add('hidden');
            }
        }

        function setRegisterError(msg) {
            const el = document.getElementById('registerError');
            document.getElementById('registerErrorMsg').textContent = msg || '';
            if (msg) {
                el.classList.remove('hidden');
            } else {
                el.classList.add('hidden');
            }
        }

        function setBtnLoading(btnId, textId, loading, defaultText) {
            const btn = document.getElementById(btnId);
            const txt = document.getElementById(textId);
            btn.disabled = loading;
            btn.style.opacity = loading ? '0.7' : '1';
            txt.textContent = loading ? 'Please wait…' : defaultText;
        }

        function submitLogin(e) {
            e.preventDefault();
            setLoginError('');
            setBtnLoading('loginSubmitBtn', 'loginBtnText', true, '<?= __('login_btn') ?>');

            const body = new URLSearchParams({
                action: 'login',
                email: document.getElementById('modalEmail').value,
                password: document.getElementById('modalPassword').value,
            });

            fetch('/sweetheaven/api/auth_modal.php', { method: 'POST', body })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        if (_pendingAction) {
                            // Reset button FIRST so it's not frozen if modal is reopened
                            setBtnLoading('loginSubmitBtn', 'loginBtnText', false, '<?= __('login_btn') ?>');
                            // Execute the pending action BEFORE closeAuthModal() — because
                            // closeAuthModal() calls clearPendingAction(), which would null it out.
                            // Wait for the API call to finish, then reload so PHP re-renders
                            // the full logged-in page (header, nav, cart/wishlist counts).
                            executePendingAction().then(() => {
                                closeAuthModal();
                                // Admins go to dashboard; regular users reload in place
                                if (data.redirect && data.redirect.includes('admin')) {
                                    window.location.href = data.redirect;
                                } else {
                                    window.location.reload();
                                }
                            });
                        } else {
                            // Normal login with no pending action — follow the redirect
                            window.location.href = data.redirect;
                        }
                    } else {
                        setLoginError(data.error);
                        setBtnLoading('loginSubmitBtn', 'loginBtnText', false, '<?= __('login_btn') ?>');
                    }
                })
                .catch(() => {
                    setLoginError('<?= __('login_err_network') ?? "Network error. Please try again." ?>');
                    setBtnLoading('loginSubmitBtn', 'loginBtnText', false, '<?= __('login_btn') ?>');
                });
        }

        function submitRegister(e) {
            e.preventDefault();
            setRegisterError('');
            document.getElementById('registerSuccess').classList.add('hidden');
            setBtnLoading('registerSubmitBtn', 'registerBtnText', true, '<?= __('register_btn') ?>');

            const body = new URLSearchParams({
                action: 'register',
                name: document.getElementById('regName').value,
                email: document.getElementById('regEmail').value,
                password: document.getElementById('regPassword').value,
                confirm_password: document.getElementById('regConfirm').value,
            });

            fetch('/sweetheaven/api/auth_modal.php', { method: 'POST', body })
                .then(r => r.json())
                .then(data => {
                    setBtnLoading('registerSubmitBtn', 'registerBtnText', false, '<?= __('register_btn') ?>');
                    if (data.success) {
                        document.getElementById('modalRegisterForm').reset();
                        const successEl = document.getElementById('registerSuccess');
                        document.getElementById('registerSuccessMsg').textContent = data.message;
                        successEl.classList.remove('hidden');
                        // Auto-switch to login after 2 seconds
                        setTimeout(() => switchTab('login'), 2000);
                    } else {
                        setRegisterError(data.error);
                    }
                })
                .catch(() => {
                    setRegisterError('<?= __('login_err_network') ?? "Network error. Please try again." ?>');
                    setBtnLoading('registerSubmitBtn', 'registerBtnText', false, '<?= __('register_btn') ?>');
                });
        }

        function toggleModalPassword(inputId, btn) {
            const input = document.getElementById(inputId);
            input.type = input.type === 'password' ? 'text' : 'password';
        }

        // Close on Escape key
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') closeAuthModal();
        });

        // Auto-open login modal when redirected from auth middleware
        if (new URLSearchParams(window.location.search).get('show_login') === '1') {
            openAuthModal('login');
            // Clean the query param from URL without reload
            window.history.replaceState({}, '', window.location.pathname);
        }
    </script>
</body>

</html>