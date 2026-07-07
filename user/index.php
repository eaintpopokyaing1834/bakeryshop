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
           c.name AS category_name,
           d.name AS discount_name, d.type AS discount_type, d.value AS discount_value,
           pi.image_url AS primary_image,
           COALESCE(AVG(r.rating),0) AS avg_rating,
           COUNT(DISTINCT oi.id) AS total_sold
    FROM products p
    JOIN categories c ON p.category_id = c.id
    LEFT JOIN discounts d ON p.discount_id = d.id
    LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
    LEFT JOIN reviews r ON r.product_id = p.id
    LEFT JOIN order_items oi ON oi.product_id = p.id
    GROUP BY p.id
    ORDER BY total_sold DESC, p.created_at DESC
    LIMIT 4
")->fetchAll();

// Ensure customer_reviews table exists
$db->exec("CREATE TABLE IF NOT EXISTS customer_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$latestProducts = $db->query("
    SELECT p.*,
           pi.image_url AS primary_image
    FROM products p
    LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT 9
")->fetchAll();

$customerReviews = $db->query("SELECT name, message, created_at FROM customer_reviews WHERE status='approved' ORDER BY created_at DESC")->fetchAll();

// Fetch all discounted products
$discountedProducts = $db->query("
    SELECT p.*,
           c.name AS category_name,
           d.name AS discount_name, d.type AS discount_type, d.value AS discount_value,
           pi.image_url AS primary_image
    FROM products p
    JOIN categories c ON p.category_id = c.id
    JOIN discounts d ON p.discount_id = d.id AND d.status = 1
    LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
    WHERE p.discount_id IS NOT NULL
    ORDER BY d.value DESC, p.created_at DESC
")->fetchAll();

$isAdmin = isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin';

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
    <style>
        :root {
            --cream: #fdf8f3;
            --rose: #e8746a;
            --rose-light: #fdf0ee;
            --rose-mid: #f5c3be;
        }

        * {
            font-family: 'Inter', sans-serif;
        }

        .serif {
            font-family: 'DM Serif Display', serif;
        }

        body {
            background: var(--cream);
        }

        /* Fade-in on scroll feel */
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

        .fade-up {
            animation: fadeUp 0.7s ease both;
        }

        .fade-up-d1 {
            animation-delay: .1s;
        }

        .fade-up-d2 {
            animation-delay: .2s;
        }

        .fade-up-d3 {
            animation-delay: .3s;
        }

        /* Category card */
        .cat-card:hover {
            box-shadow: 0 8px 32px rgba(232, 116, 106, .12);
            transform: translateY(-2px);
        }

        .cat-card {
            transition: all .25s ease;
        }

        /* Hero image collage */
        .collage-img {
            border-radius: 20px;
            object-fit: cover;
        }

        /* Subtle pill badge */
        .pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #fff;
            border: 1px solid var(--rose-mid);
            color: #c45c55;
            padding: 5px 14px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .pill-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: #e8746a;
            animation: pulse 2s infinite;
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

        /* Promo card hover */
        .promo-card {
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .promo-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 36px rgba(0, 0, 0, .07);
        }

        /* --- ၁။ Pulse Effect (ကြီးလိုက်သေးလိုက်) --- */
        @keyframes pulseEffect {
            0% {
                transform: scale(1);
            }

            30% {
                transform: scale(1.05);
            }

            /* ၅% ပိုကြီးလာမယ် */
            60% {
                transform: scale(1);
            }
        }

        .animated-pulse {
            animation: pulseEffect 3s infinite ease-in-out;
        }

        /* --- ၃။ Float Effect (ရေပေါ်မျောသလို အပေါ်အောက် ငြိမ့်ငြိမ့်လေး လှုပ်မယ်) --- */
        @keyframes floatEffect {
            0% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-10px);
            }

            /* အပေါ်ကို 10px တက်မယ် */
            80% {
                transform: translateY(0px);
            }
        }

        .animated-float {
            animation: floatEffect 3s infinite ease-in-out;
        }
    </style>
</head>

<body class="overflow-x-hidden text-gray-700">

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
            <div class="fade-up z-10 fade-up-d2  relative hidden md:block">
                <div><img src="../images/spot2.png" class="absolute -top-28 -left-36 w-80 h-80 opacity-80">
                    </div>

                    <div class="absolute z-[-1] top-1/2 -left-20 -translate-x-1/2  w-80 h-80 opacity-20 rounded-full "
                    style="background:var(--rose);"></div>

                <span class="pill mb-6">
                    <span class="pill-dot"></span>
                    <?= __('hero_pill') ?>
                </span>

                <h1 class="serif text-5xl md:text-[3.8rem] leading-[1.1] text-gray-800 mt-5 mb-5">
                    <?= __('hero_title') ?>
                </h1>

                <p class="text-gray-500 text-[15px] leading-7 mb-8 max-w-md">
                    <?= __('hero_desc') ?>
                </p>

                <div class="flex flex-wrap gap-3 mb-10">
                    <a href="/sweetheaven/user/products.php" style="background:#e8746a;"
                        class="inline-flex items-center gap-2 text-white px-7 py-3.5 rounded-full font-semibold text-sm hover:opacity-90 hover:-translate-y-0.5 transition-all duration-200 shadow-md shadow-rose-200">
                        <?= __('hero_shop_now') ?>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                    </a>
                    <a href="#categories"
                        class="inline-flex items-center gap-2 border border-rose-200 text-rose-500 bg-white px-7 py-3.5 rounded-full font-semibold text-sm hover:bg-rose-50 hover:-translate-y-0.5 transition-all duration-200">
                        <?= __('hero_browse_cat') ?>
                    </a>
                </div>

                <!-- Stats row -->
                <div class="flex items-center gap-8">

                </div>
            </div>

            <!-- Right: Photo Collage -->
            <div class="fade-up fade-up-d2 relative hidden md:block">
                <!-- Decorative circle -->
                <div class="absolute -top-8 -right-8 w-72 h-72 rounded-full"
                    style="background:var(--rose-light);z-index:0;"></div>
                    <div><img src="../images/balloon.png" class="absolute -top-14 -left-36 w-72 h-70 opacity-50">
                    </div>

                    <div><img src="../images/ribbon.png" class="absolute -bottom-20 -left-40 w-60 h-60 opacity-70">
                    </div>

                <div class="relative z-10 grid grid-cols-2 gap-4">
                    <img src="../images/heropincake.jpg" alt="Beautiful cake"
                        class="animated-pulse collage-img w-full h-52 shadow-md">
                    <img src="../images/donutgrop.jpg" alt="Croissant"
                        class="animated-float collage-img w-full h-52 shadow-md mt-8">
                    <img src="../images/cro.jpg" alt="Cupcakes"
                        class="animated-float collage-img w-full h-52 shadow-md">
                    <img src="../images/minicake.jpg" alt="Fresh bread"
                        class="animated-pulse collage-img w-full h-52 shadow-md mt-8">
                </div>
            </div>
        </div>
    </section>

    <!-- ═════════════════════════ FEATURE BAR ═════════════════════════ -->
    <section class="bg-white border-y border-gray-100 py-7">
        <div class="max-w-7xl mx-auto px-6 grid grid-cols-2 md:grid-cols-4 gap-6">
            <?php foreach ([
                ['🌿', __('feature_natural'), __('feature_natural_sub')],
                ['🔥', __('feature_fresh'), __('feature_fresh_sub')],
                ['🚚', __('feature_delivery'), __('feature_delivery_sub')],
                ['💝', __('feature_love'), __('feature_love_sub')],
            ] as $f): ?>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xl flex-shrink-0"
                        style="background:var(--rose-light);">
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
            <div class="flex  items-center justify-center mb-10 fade-up">
                <div class="text-center">
                    <p class="text-md font-semibold uppercase tracking-widest mb-1" style="color:#e8746a;">
                        <?= __('cat_what_we_offer') ?>
                    </p>
                    <h2 class="serif text-4xl text-gray-800"><?= __('cat_our_categories') ?></h2>
                </div>

            </div>

            <div class="relative">
                <div class="overflow-hidden rounded-2xl">
                    <div class="flex transition-transform duration-500 ease-in-out" id="categoryTrack">
                        <?php foreach ($categories as $cat): ?>
                            <?php
                            $catImg = $cat['image'] ?? '';
                            $catImg = ltrim(str_replace('../', '', $catImg), '/');
                            ?>
                            <div class="category-card-wrapper flex-shrink-0 px-2">
                                <a href="/sweetheaven/user/products.php?category_id=<?= $cat['id'] ?>"
                                    class="cat-card group bg-white border border-gray-100 rounded-2xl p-5 text-center shadow-sm block">
                                    <div class="w-20 h-20 mx-auto rounded-2xl overflow-hidden mb-3 group-hover:scale-105 transition-transform duration-300"
                                        style="background:var(--rose-light);">
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
                                        <?= htmlspecialchars($cat['name']) ?>
                                    </p>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button id="catPrev"
                    class="absolute left-2 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/90 shadow-md flex items-center justify-center text-stone-600 hover:bg-white hover:text-rose-500 transition-all z-10 opacity-0 md:opacity-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <button id="catNext"
                    class="absolute right-2 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/90 shadow-md flex items-center justify-center text-stone-600 hover:bg-white hover:text-rose-500 transition-all z-10 opacity-0 md:opacity-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </div>

            <div class="flex justify-center gap-2 mt-6" id="catDots"></div>
        </div>
    </section>

    <style>
        #categoryTrack {
            will-change: transform;
        }

        .category-card-wrapper {
            transition: width 0.3s ease;
        }

        #catDots button {
            transition: all 0.3s ease;
        }

        #catDots button.active {
            background: #f43f5e;
            width: 24px;
            border-radius: 999px;
        }

        #reviewTrack {
            will-change: transform;
        }

        .review-card-wrapper {
            transition: width 0.3s ease;
        }

        #reviewDots button {
            transition: all 0.3s ease;
        }

        #reviewDots button.active {
            background: #e8746a;
            width: 24px;
            border-radius: 999px;
        }
    </style>

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
                    dot.className = 'w-2.5 h-2.5 rounded-full bg-stone-300 hover:bg-rose-300';
                    if (i === currentPage) dot.classList.add('active');
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
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex flex-col items-center justify-center mb-10 fade-up space-y-4">
                <div>
                    <p class="text-md text-center font-semibold uppercase tracking-widest mb-1" style="color:#e8746a;">
                        <?= __('bestsellers_subtitle') ?>
                    </p>
                    <h2 class="serif text-4xl text-gray-800"><?= __('bestsellers_title') ?></h2>
                </div>

            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                <?php foreach ($bestSellers as $product): ?>
                    <?php
                    $imgSrc = $product['primary_image']
                        ? '/sweetheaven/' . $product['primary_image']
                        : '/sweetheaven/images/maincake.jpg';
                    $hasDiscount = $product['discount_name'] && $product['discount_value'];
                    if ($hasDiscount) {
                        $discountedPrice = $product['discount_type'] === 'percentage'
                            ? $product['price'] * (1 - $product['discount_value'] / 100)
                            : max(0, $product['price'] - $product['discount_value']);
                    }
                    ?>
                    <div
                        class="product-card group bg-white rounded-2xl border border-gray-100 overflow-hidden rouned-2xl shadow-md hover:shadow-xl transition-all duration-500 hover:-translate-y-2">
                        <div class="relative overflow-hidden bg-gradient-to-br from-rose-50 to-amber-50 aspect-[4/3]">
                            <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($product['name']) ?>"
                                class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
                            <button onclick="event.stopPropagation(); toggleWishlist(<?= $product['id'] ?>, this)"
                                class="absolute top-2 right-2 w-9 h-9 rounded-full bg-white/90 text-gray-400 shadow-md flex items-center justify-center hover:bg-rose-500 hover:text-white transition-all duration-200 backdrop-blur-sm"
                                title="Wishlist">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                </svg>
                            </button>
                            <?php if ($hasDiscount): ?>
                                <div class="absolute top-0 left-0 bg-rose-500 text-white text-xs font-bold px-3 py-1 rounded-md shadow-md"
                                    viewBox="0 0 24 24">
                                    <?= htmlspecialchars($product['discount_name']) ?>
                                </div>
                            <?php elseif ($product['stock'] < 5): ?>
                                <div
                                    class="absolute top-0 left-0 bg-gradient-to-r from-amber-400 to-orange-500 text-white text-xs font-bold px-3 py-1 rounded-full shadow-md">
                                    <?= __('bestsellers_low_stock') ?>
                                </div>
                            <?php endif; ?>
                            <div
                                class="absolute inset-0 bg-gradient-to-t from-black/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none">
                            </div>
                        </div>

                        <div class="p-4">
                            <p class="text-xs font-semibold uppercase tracking-wider text-rose-400 mb-2">
                                <?= htmlspecialchars($product['category_name']) ?>
                            </p>

                            <a href="/sweetheaven/user/product_detail.php?id=<?= $product['id'] ?>">
                                <h3 class="font-bold text-gray-800 text-sm hover:text-rose-500 transition-colors mb-3">
                                    <?= htmlspecialchars($product['name']) ?>
                                </h3>
                            </a>

                            <div class="flex flex-col gap-3  border-t border-gray-50">
                                <span class="font-bold text-[15px] text-rose-500">
                                    <?php if ($hasDiscount): ?>
                                        <span
                                            class="text-xs line-through text-gray-400 font-normal mr-1"><?= number_format($product['price']) ?></span>
                                        <?= number_format($discountedPrice) ?>
                                    <?php else: ?>
                                        <?= number_format($product['price']) ?>
                                    <?php endif; ?>
                                    <span class="text-xs font-normal text-gray-400"><?= __('common_mmk') ?></span></span>


                                <div class="flex gap-2">
                                    <a href="/sweetheaven/user/product_detail.php?id=<?= $product['id'] ?>"
                                        class="flex-1 text-center border py-2 rounded-xl text-xs font-semibold hover:bg-rose-50 transition-colors"
                                        style="border-color:#e8746a; color:#e8746a;">
                                        <?= __('common_view') ?>
                                    </a>
                                    <?php if (!$isAdmin): ?>
                                        <button
                                            onclick="addToCart(<?= $product['id'] ?>, '<?= addslashes($product['name']) ?>')"
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
    <section class="py-20 bg-gradient-to-br from-pink-50 via-white to-rose-50">
        <div class="max-w-7xl mx-auto px-6">
            <div class="grid md:grid-cols-2 gap-12 items-center">
                <div class="relative">
                    <div class="rounded-3xl overflow-hidden shadow-xl">
                        <img src="/sweetheaven/images/bow.jpg" alt="Customize your cake"
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
                        <p class="text-md font-semibold uppercase tracking-widest mb-2" style="color:#e8746a;">
                            <?= __('customize_label') ?>
                        </p>
                        <h2 class="serif text-4xl text-gray-800"><?= __('customize_title') ?></h2>
                    </div>
                    <p class="text-gray-500 leading-relaxed text-lg">
                        <?= __('customize_desc') ?>
                    </p>
                    <div class="grid grid-cols-3 gap-3 text-sm">
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
                    <a href="/sweetheaven/user/customize.php"
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
    <section class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-6">

            <div class="grid lg:grid-cols-2  gap-6">

                <!-- Promo 1 -->

                <div class="flex flex-col gap-6">
                    <div class="text-3xl font-semibold text-center"><?= __('promo_special') ?></div>
                    <div class="promo-card rounded-2xl overflow-hidden flex flex-col md:flex-row border border-rose-100"
                        style="background:var(--rose-light);">
                        <div class="p-12 flex-1">
                            <span class="text-3xl mb-3 block">🎉</span>
                            <h3 class="font-bold text-gray-800 text-xl mb-2"><?= __('promo_first_order') ?></h3>
                            <p class="text-gray-500 text-sm leading-relaxed mb-5"><?= __('promo_first_desc') ?></p>
                            <a href="/sweetheaven/auth/register.php"
                                class="inline-block text-white font-semibold px-6 py-2.5 rounded-full text-sm hover:opacity-90 transition-opacity"
                                style="background:#e8746a;">
                                <?= __('promo_claim') ?>
                            </a>
                        </div>
                        <div class="hidden md:block w-40 flex-shrink-0">
                            <img src="https://images.unsplash.com/photo-1563729784474-d77dbb933a9e?w=300&q=80&auto=format&fit=crop"
                                alt="Cake slice" class="w-full h-full object-cover">
                        </div>
                    </div>

                    <!-- Promo 2 -->
                    <div class="promo-card rounded-2xl overflow-hidden flex flex-col md:flex-row border border-amber-100"
                        style="background:#fffbf0;">
                        <div class="p-12 flex-1">
                            <span class="text-3xl mb-3 block">🎁</span>
                            <h3 class="font-bold text-gray-800 text-xl mb-2"><?= __('promo_free_gift_title') ?></h3>
                            <p class="text-gray-500 text-sm leading-relaxed mb-5"><?= __('promo_free_gift_desc') ?></p>
                            <a href="/sweetheaven/user/products.php"
                                class="inline-block text-white font-semibold px-6 py-2.5 rounded-full text-sm hover:opacity-90 transition-opacity"
                                style="background:#f59e0b;">
                                <?= __('promo_shop_now') ?>
                            </a>
                        </div>
                        <div class="hidden md:block w-40 flex-shrink-0">
                            <img src="https://images.unsplash.com/photo-1551024601-bec78aea704b?w=300&q=80&auto=format&fit=crop"
                                alt="Donuts" class="w-full h-full object-cover">
                        </div>
                    </div>
                </div>
                <article class="flex flex-col gap-6">
                    <div class="text-center text-3xl font-semibold"><?= __('promo_latest_products') ?></div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4" style="background:#fffbf0;">
                        <?php foreach ($latestProducts as $product): ?>
                            <?php
                            $imgSrc = $product['primary_image']
                                ? '/sweetheaven/' . $product['primary_image']
                                : '/sweetheaven/images/maincake.jpg';
                            ?>
                            <div class="group relative overflow-hidden rounded-2xl bg-white border border-rose-200/50 shadow-sm hover:shadow-lg transition-all duration-500 aspect-square cursor-pointer"
                                onclick="this.classList.toggle('active')">
                                <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($product['name']) ?>"
                                    class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
                                <div
                                    class="absolute inset-0 bg-black/45 opacity-0 group-hover:opacity-100 group-[.active]:opacity-100 transition-opacity duration-300 flex items-end p-4">
                                    <span
                                        class="text-white font-semibold text-sm text-center w-full translate-y-3 opacity-0 group-hover:translate-y-0 group-hover:opacity-100 group-[.active]:translate-y-0 group-[.active]:opacity-100 transition-all duration-300 ease-out">
                                        <?= htmlspecialchars($product['name']) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>

            </div>
        </div>
    </section>



    <!-- ═════════════════════════ SPECIAL DISCOUNTS ═════════════════════════ -->
    <section id="special-discounts" class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-6">

            <!-- ── Section Header ── -->
            <div class="text-center mb-10">
                <div class="flex items-center justify-center gap-3 mb-3">
                    <span class="h-px w-10 bg-rose-300 inline-block"></span>
                    <span class="text-xs font-bold uppercase tracking-[.2em]" style="color:#e8746a;">
                        <?= __('discount_section_label') ?>
                    </span>
                    <span class="h-px w-10 bg-rose-300 inline-block"></span>
                </div>
                <h2 class="serif text-4xl md:text-5xl font-extrabold text-gray-800 mb-2">
                    <?= __('discount_section_title') ?>
                </h2>
                <p class="text-gray-400 text-sm"><?= __('discount_section_desc') ?></p>
            </div>

            <!-- ── Big Pink Banner ── -->
            <div class="relative rounded-3xl overflow-hidden mb-12 shadow-2xl"
                style="background: linear-gradient(130deg, #ffe4ef 0%, #ffc2d9 45%, #ffaac8 100%); min-height: 320px;">


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
                        style="background:radial-gradient(circle,#fff,transparent);"></div>
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
                                     text-xs font-extrabold uppercase tracking-widest text-white shadow"
                            style="background:#e8746a;">
                            <?= __('discount_limited_offer') ?>
                        </span>


                        <!-- Giant percentage -->
                        <div class="mb-3">
                            <span
                                class="block text-gray-700 text-xl font-bold leading-none"><?= __('discount_up_to') ?></span>
                            <span class="block font-black"
                                style="font-size: clamp(4rem,8vw,6rem); color:#e8746a; line-height:1;">15%</span>
                            <span class="block text-gray-700 font-black tracking-tight"
                                style="font-size: clamp(1.5rem,3vw,2rem); line-height:1.1;"><?= __('discount_off') ?></span>
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
                            class="w-auto drop-shadow-2xl"
                            style="max-height:500px; margin-bottom:-2px; object-fit:contain;">
                    </div>

                    <!-- ③ Stacked accessory images (plain, no card) ── -->
                    <div class="hidden md:flex flex-col justify-center gap-2 pl-3 pr-1 py-6">
                        <!-- <img src="/sweetheaven/images/4accessorycake.png" alt="Cake accessory"
                            class="w-full object-contain drop-shadow-xl" style="max-height:300px;">
                        <img src="/sweetheaven/images/gitbox.png" alt="Gift box"
                            class="w-full object-contain drop-shadow-xl" style="max-height:300px;"> -->
                        <img src="/sweetheaven/images/ballon3.png" alt="Featured Discount Cake"
                            class="w-auto drop-shadow-2xl"
                            style="max-height:500px; margin-bottom:-2px; object-fit:contain;">

                    </div>


                    <!-- ④ Right: compact circle badge + CTA ────────────────── -->
                    <div class="flex flex-col items-center justify-center gap-5 p-6 md:pr-8">

                        <!-- Circle badge -->
                        <div class="relative flex items-center justify-center w-36 h-36 rounded-full shadow-xl flex-shrink-0"
                            style="background:#e8746a;">
                            <div class="absolute inset-2 rounded-full border-2 border-white/40"></div>
                            <div class="text-center text-white px-2 z-10 space-y-0.5">
                                <p class="text-[9px] font-bold uppercase tracking-wider leading-none">
                                    <?= __('discount_badge_week') ?>
                                </p>
                                <p class="text-[10px] font-semibold leading-snug"><?= __('discount_badge_save') ?></p>
                                <p class="serif text-xl font-black leading-none"><?= __('discount_badge_cakes') ?></p>
                                <span class="text-base">❤️</span>
                            </div>
                        </div>

                        <!-- Shop Now -->
                        <a href="/sweetheaven/user/products.php?discounted=1"
                            class="inline-flex items-center gap-1.5 font-extrabold px-6 py-3 rounded-full text-xs shadow-lg
                                  hover:-translate-y-0.5 transition-all duration-200 uppercase tracking-widest whitespace-nowrap"
                            style="background:#e8746a; color:#fff;">
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
                    <h3 class="serif text-3xl font-bold text-gray-800"><?= __('discount_shop_the_deals') ?></h3>
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
                    <h3 class="serif text-2xl text-gray-700 mb-3"><?= __('discount_no_products') ?></h3>
                    <p class="text-gray-400 text-sm max-w-sm mx-auto leading-relaxed">
                        <?= __('discount_no_products_desc') ?>
                    </p>
                    <a href="/sweetheaven/user/products.php"
                        class="inline-flex items-center gap-2 mt-8 text-white font-semibold px-7 py-3.5 rounded-full text-sm hover:opacity-90 transition-all duration-200 shadow-md"
                        style="background:#e8746a;">
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
                        $dpBadgeLabel = $dp['discount_type'] === 'percentage'
                            ? sprintf(__('discount_percent_off'), (int) $dp['discount_value'])
                            : sprintf(__('discount_mmk_off'), number_format($dp['discount_value']));
                        ?>
                        <div
                            class="group bg-white rounded-2xl overflow-hidden shadow-md hover:shadow-xl transition-all duration-300 hover:-translate-y-1 border border-pink-100">
                            <!-- Image area -->
                            <div class="relative bg-pink-50 flex items-center justify-center"
                                style="height:180px; overflow:hidden;">
                                <img src="<?= htmlspecialchars($dpImgSrc) ?>" alt="<?= htmlspecialchars($dp['name']) ?>"
                                    class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
                                <!-- Discount badge -->
                                <div class="absolute top-0 left-0 px-3 py-1.5 text-xs font-extrabold text-white rounded-br-xl shadow"
                                    style="background:#e8746a;">
                                    <?= htmlspecialchars($dpBadgeLabel) ?>
                                </div>
                                <!-- Wishlist -->
                                <button onclick="event.stopPropagation(); toggleWishlist(<?= $dp['id'] ?>, this)"
                                    class="absolute top-2 right-2 w-8 h-8 rounded-full bg-white/90 text-gray-400 shadow flex items-center justify-center hover:bg-rose-500 hover:text-white transition-all duration-200"
                                    title="Wishlist">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                    </svg>
                                </button>
                            </div>
                            <!-- Card body -->
                            <div class="p-4">
                                <a href="/sweetheaven/user/product_detail.php?id=<?= $dp['id'] ?>">
                                    <h3
                                        class="font-bold text-gray-800 text-sm hover:text-rose-500 transition-colors mb-2 leading-snug">
                                        <?= htmlspecialchars($dp['name']) ?>
                                    </h3>
                                </a>
                                <div class="flex items-baseline gap-2 mb-3">
                                    <span class="text-xs line-through text-gray-400">
                                        <?= number_format($dp['price']) ?>         <?= __('common_mmk') ?>
                                    </span>
                                    <span class="font-extrabold text-base" style="color:#e8746a;">
                                        <?= number_format($dpFinalPrice) ?> <span
                                            class="text-xs font-normal text-gray-400"><?= __('common_mmk') ?></span>
                                    </span>
                                </div>
                                <div class="flex gap-2">
                                    <a href="/sweetheaven/user/product_detail.php?id=<?= $dp['id'] ?>"
                                        class="flex-1 text-center border py-2 rounded-xl text-xs font-semibold hover:bg-rose-50 transition-colors"
                                        style="border-color:#e8746a; color:#e8746a;">
                                        <?= __('discount_view') ?>
                                    </a>
                                    <?php if (!$isAdmin): ?>
                                        <button onclick="addToCart(<?= $dp['id'] ?>, '<?= addslashes($dp['name']) ?>')"
                                            class="flex-1 flex items-center justify-center gap-1 py-2 rounded-xl text-xs font-bold text-white transition-all duration-200 hover:opacity-90 shadow"
                                            style="background:#e8746a;">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13l-1.5 7H19M9 21a1 1 0 100-2 1 1 0 000 2zm10 0a1 1 0 100-2 1 1 0 000 2z" />
                                            </svg>
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
                        style="border-color:#e8746a; color:#e8746a;">
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
    <section class="py-20 bg-[#fdf8f3]">
        <div class="max-w-7xl mx-auto px-6 grid md:grid-cols-2 gap-12 items-center">
            <!-- Image -->
            <div class="rounded-3xl overflow-hidden shadow-lg fade-up">
                <img src="../images/onegirl.jpg" alt="Baker at work" class="w-full h-80 object-cover">
            </div>
            <!-- Text -->
            <div class="fade-up fade-up-d2">
                <p class="text-xs font-semibold uppercase tracking-widest mb-2" style="color:#e8746a;">
                    <?= __('story_label') ?>
                </p>
                <h2 class="serif text-3xl text-gray-800 mb-5"><?= __('story_title') ?></h2>
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
    </section>

    <!-- ═════════════════════════ REVIEWS DISPLAY ═════════════════════════ -->
    <section id="reviews-display" class="py-20 bg-[#fdf8f3]">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex items-center justify-center mb-10 fade-up">
                <div class="text-center">
                    <p class="text-xs font-semibold uppercase tracking-widest mb-2" style="color:#e8746a;">
                        <?= __('review_display_label') ?>
                    </p>
                    <h2 class="serif text-3xl text-gray-800"><?= __('review_display_title') ?></h2>
                    <p class="text-gray-400 text-sm mt-2"><?= __('review_display_desc') ?></p>
                </div>
            </div>

            <?php if (empty($customerReviews)): ?>
                <div class="text-center text-gray-400 py-10">
                    <p class="text-4xl mb-3">💬</p>
                    <p class="text-sm"><?= __('review_no_reviews') ?></p>
                </div>
            <?php else: ?>
                <div class="relative">
                    <div class="overflow-hidden rounded-2xl">
                        <div class="flex transition-transform duration-500 ease-in-out" id="reviewTrack">
                            <?php foreach ($customerReviews as $r): ?>
                                <div class="review-card-wrapper flex-shrink-0 px-2">
                                    <div class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm h-full text-left">
                                        <div class="flex items-center gap-3 mb-4">
                                            <div
                                                class="w-10 h-10 bg-rose-100 rounded-full flex items-center justify-center text-rose-500 font-bold text-sm flex-shrink-0">
                                                <?= strtoupper(substr($r['name'], 0, 1)) ?>
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
                                        <p class="text-gray-500 text-sm leading-7">"<?= htmlspecialchars($r['message']) ?>"</p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <button id="reviewPrev"
                        class="absolute left-2 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/90 shadow-md flex items-center justify-center text-stone-600 hover:bg-white hover:text-rose-500 transition-all z-10 opacity-0 md:opacity-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                    <button id="reviewNext"
                        class="absolute right-2 top-1/2 -translate-y-1/2 w-10 h-10 rounded-full bg-white/90 shadow-md flex items-center justify-center text-stone-600 hover:bg-white hover:text-rose-500 transition-all z-10 opacity-0 md:opacity-100">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>

                <div class="flex justify-center gap-2 mt-6" id="reviewDots"></div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ═════════════════════════ REVIEW FORM ═════════════════════════ -->
    <?php if (isset($_SESSION['user_id']) && $_SESSION['role'] !== 'admin'): ?>
        <section id="review-form" class="pb-20 bg-[#fdf8f3]">
            <div class="max-w-2xl mx-auto px-6">
                <div class="border-t border-gray-200 pt-16">
                    <div class="bg-white border border-gray-100 rounded-2xl p-8 shadow-sm">
                        <h3 class="font-bold text-gray-800 text-lg mb-2"><?= __('review_form_title') ?></h3>
                        <p class="text-gray-400 text-sm mb-6"><?= __('review_form_desc') ?></p>
                        <form id="reviewForm" class="space-y-4">
                            <div class="grid sm:grid-cols-2 gap-4">
                                <div>
                                    <label
                                        class="block text-sm font-semibold text-gray-700 mb-2"><?= __('review_form_name_label') ?></label>
                                    <input type="text" id="reviewName" required
                                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm"
                                        placeholder="<?= __('review_form_name_ph') ?>">
                                </div>
                                <div>
                                    <label
                                        class="block text-sm font-semibold text-gray-700 mb-2"><?= __('review_form_email_label') ?></label>
                                    <input type="email" id="reviewEmail" required
                                        class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm"
                                        placeholder="<?= __('review_form_email_ph') ?>">
                                </div>
                            </div>
                            <div>
                                <label
                                    class="block text-sm font-semibold text-gray-700 mb-2"><?= __('review_form_message_label') ?></label>
                                <textarea id="reviewMessage" rows="4" required
                                    class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm resize-none"
                                    placeholder="<?= __('review_form_message_ph') ?>"></textarea>
                            </div>
                            <button type="submit"
                                class="w-full sm:w-auto bg-rose-500 hover:bg-rose-600 text-white font-semibold px-8 py-3 rounded-xl transition-colors text-sm">
                                <?= __('review_form_submit') ?>
                            </button>
                        </form>
                        <div id="reviewFormMsg" class="mt-4 hidden"></div>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- ═════════════════════════ ABOUT US ═════════════════════════ -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-6">
            <div class="grid md:grid-cols-2 gap-12 items-center">
                <div class="rounded-3xl overflow-hidden shadow-lg fade-up">
                    <img src="../images/about.jpg" alt="About Sweet Heaven Bakery" class="w-full h-96 object-cover">
                </div>
                <div class="fade-up fade-up-d2">
                    <p class="text-xs font-semibold uppercase tracking-widest mb-2" style="color:#e8746a;">
                        <?= __('about_label') ?>
                    </p>
                    <h2 class="serif text-4xl text-gray-800 mb-6"><?= __('about_title') ?></h2>
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
        document.getElementById('reviewForm')?.addEventListener('submit', function (e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            const msgBox = document.getElementById('reviewFormMsg');
            btn.disabled = true;
            btn.textContent = '<?= __('review_form_submitting') ?>';

            fetch('/sweetheaven/api/customer_review.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    name: document.getElementById('reviewName').value,
                    email: document.getElementById('reviewEmail').value,
                    message: document.getElementById('reviewMessage').value
                })
            })
                .then(r => r.json())
                .then(data => {
                    msgBox.classList.remove('hidden', 'bg-red-50', 'border-red-200', 'text-red-700', 'bg-green-50', 'border-green-200', 'text-green-700');
                    if (data.success) {
                        msgBox.className = 'mt-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl text-sm';
                        msgBox.textContent = data.message;
                        document.getElementById('reviewForm').reset();
                    } else {
                        msgBox.className = 'mt-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm';
                        msgBox.textContent = data.message;
                    }
                    btn.disabled = false;
                    btn.textContent = '<?= __('review_form_submit') ?>';
                })
                .catch(() => {
                    msgBox.classList.remove('hidden');
                    msgBox.className = 'mt-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm';
                    msgBox.textContent = '<?= __('review_form_error') ?>';
                    btn.disabled = false;
                    btn.textContent = '<?= __('review_form_submit') ?>';
                });
        });
    </script>

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
                    dot.className = 'w-2.5 h-2.5 rounded-full bg-stone-300 hover:bg-rose-300';
                    if (i === currentPage) dot.classList.add('active');
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

    <!-- Toast -->
    <div id="toast"
        class="hidden fixed bottom-6 right-6 text-white px-5 py-3 rounded-xl shadow-lg text-sm font-medium z-50 flex items-center gap-2"
        style="background:#e8746a;">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        <span id="toastMsg"><?= __('toast_added_cart') ?></span>
    </div>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>

    <script>
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
                        openAuthModal('login');
                    }
                });
        }

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
                    } else if (data.redirect) openAuthModal('login');
                });
        }

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
        <div class="relative w-full max-w-md overflow-hidden auth-card"
            style="animation: modalSlideIn 0.38s cubic-bezier(0.34,1.46,0.64,1) both">

            <!-- Close button -->
            <button onclick="closeAuthModal()" id="authCloseBtn" class="auth-close-btn" aria-label="Close">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                    <path d="M1 1l12 12M13 1L1 13" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                </svg>
            </button>

            <!-- Brand header -->
            <div class="auth-header">
                <div class="auth-logo-ring">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#c97878" stroke-width="1.6">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M21 15.546c-.523 0-1.046.151-1.5.454a2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.701 2.701 0 00-1.5-.454M9 6v2m3-2v2m3-2v2M9 3h.01M12 3h.01M15 3h.01M21 21v-7a2 2 0 00-2-2H5a2 2 0 00-2 2v7h18z" />
                    </svg>
                </div>
                <div>
                    <h2 id="authModalTitle" class="auth-title">Welcome back</h2>
                    <p id="authModalSubtitle" class="auth-subtitle">Sign in to your Sweet Heaven account</p>
                </div>
            </div>

            <div class="auth-body">

                <!-- LOGIN PANEL -->
                <div id="loginPanel">
                    <div id="loginError" class="auth-alert auth-alert-error hidden">
                        <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor" class="shrink-0">
                            <path fill-rule="evenodd"
                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                                clip-rule="evenodd" />
                        </svg>
                        <span id="loginErrorMsg"></span>
                    </div>

                    <form id="modalLoginForm" class="auth-form" onsubmit="submitLogin(event)">
                        <div class="auth-field">
                            <span class="auth-field-icon">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </span>
                            <input type="email" id="modalEmail" name="email" required autocomplete="email"
                                placeholder="Email address" class="auth-input">
                        </div>
                        <div class="auth-field">
                            <span class="auth-field-icon">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </span>
                            <input type="password" id="modalPassword" name="password" required
                                autocomplete="current-password" placeholder="Password" class="auth-input auth-input-pr">
                            <button type="button" onclick="toggleModalPassword('modalPassword',this)"
                                class="auth-eye-btn" tabindex="-1">
                                <svg width="17" height="17" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </button>
                        </div>
                        <button type="submit" id="loginSubmitBtn" class="auth-btn">
                            <span id="loginBtnText">Sign In</span>
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M13 7l5 5m0 0l-5 5m5-5H6" />
                            </svg>
                        </button>
                    </form>

                    <div class="auth-demo-box">
                        <p class="auth-demo-title">&#10022; Demo Credentials</p>
                        <p>Admin: admin@sweetheaven.com <span class="auth-demo-sep">/</span> admin123</p>
                        <p>Customer: customer@sweetheaven.com <span class="auth-demo-sep">/</span> customer123</p>
                    </div>

                    <p class="auth-switch-text">
                        Don't have an account?
                        <button onclick="switchTab('register')" class="auth-switch-link">Create one free</button>
                    </p>
                </div>

                <!-- REGISTER PANEL -->
                <div id="registerPanel" class="hidden">
                    <div id="registerError" class="auth-alert auth-alert-error hidden">
                        <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor" class="shrink-0">
                            <path fill-rule="evenodd"
                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
                                clip-rule="evenodd" />
                        </svg>
                        <span id="registerErrorMsg"></span>
                    </div>
                    <div id="registerSuccess" class="auth-alert auth-alert-success hidden">
                        <svg width="15" height="15" viewBox="0 0 20 20" fill="currentColor" class="shrink-0">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd" />
                        </svg>
                        <span id="registerSuccessMsg"></span>
                    </div>

                    <form id="modalRegisterForm" class="auth-form" onsubmit="submitRegister(event)">
                        <div class="auth-field">
                            <span class="auth-field-icon">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </span>
                            <input type="text" id="regName" name="name" required autocomplete="name"
                                placeholder="Full name" class="auth-input">
                        </div>
                        <div class="auth-field">
                            <span class="auth-field-icon">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </span>
                            <input type="email" id="regEmail" name="email" required autocomplete="email"
                                placeholder="Email address" class="auth-input">
                        </div>
                        <div class="auth-field">
                            <span class="auth-field-icon">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </span>
                            <input type="password" id="regPassword" name="password" required autocomplete="new-password"
                                placeholder="Password (min 6 chars)" class="auth-input auth-input-pr">
                            <button type="button" onclick="toggleModalPassword('regPassword',this)" class="auth-eye-btn"
                                tabindex="-1">
                                <svg width="17" height="17" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </button>
                        </div>
                        <div class="auth-field">
                            <span class="auth-field-icon">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </span>
                            <input type="password" id="regConfirm" name="confirm_password" required
                                autocomplete="new-password" placeholder="Confirm password" class="auth-input">
                        </div>
                        <button type="submit" id="registerSubmitBtn" class="auth-btn">
                            <span id="registerBtnText">Create Account</span>
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7" />
                            </svg>
                        </button>
                    </form>

                    <p class="auth-switch-text">
                        Already have an account?
                        <button onclick="switchTab('login')" class="auth-switch-link">Sign in</button>
                    </p>
                </div>

            </div>
        </div>
    </div>

    <style>
        .auth-card {
            background: #fff9f9;
            border-radius: 28px;
            box-shadow: 0 24px 64px rgba(180, 80, 80, .14), 0 4px 16px rgba(200, 100, 100, .08);
            border: 1px solid #f5dede;
        }

        .auth-close-btn {
            position: absolute;
            top: 18px;
            right: 18px;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #fce8e8;
            color: #b87070;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background .2s, color .2s, transform .15s;
            z-index: 10;
        }

        .auth-close-btn:hover {
            background: #f9d4d4;
            color: #9a4f4f;
            transform: scale(1.1);
        }

        .auth-header {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 30px 30px 0;
        }

        .auth-logo-ring {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            flex-shrink: 0;
            background: linear-gradient(135deg, #fce8e8, #fdf0f0);
            border: 1px solid #f5d5d5;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .auth-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #3d2020;
            line-height: 1.3;
            margin: 0;
        }

        .auth-subtitle {
            font-size: .78rem;
            color: #b08080;
            margin: 3px 0 0;
        }

        .auth-body {
            padding: 22px 30px 28px;
        }

        .auth-form {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 4px;
        }

        .auth-field {
            position: relative;
            display: flex;
            align-items: center;
        }

        .auth-field-icon {
            position: absolute;
            left: 13px;
            color: #d4a0a0;
            pointer-events: none;
            display: flex;
            transition: color .2s;
        }

        .auth-field:focus-within .auth-field-icon {
            color: #c97878;
        }

        .auth-input {
            width: 100%;
            padding: 12px 14px 12px 40px;
            border-radius: 14px;
            border: 1.5px solid #f0d8d8;
            background: #fff;
            font-size: .855rem;
            color: #3d2020;
            outline: none;
            font-family: inherit;
            transition: border-color .22s, box-shadow .22s, background .22s;
        }

        .auth-input-pr {
            padding-right: 42px;
        }

        .auth-input::placeholder {
            color: #d4adad;
        }

        .auth-input:focus {
            border-color: #e8a0a0;
            box-shadow: 0 0 0 3.5px rgba(220, 130, 130, .14);
            background: #fffbfb;
        }

        .auth-eye-btn {
            position: absolute;
            right: 13px;
            background: none;
            border: none;
            cursor: pointer;
            color: #d4a0a0;
            padding: 2px;
            display: flex;
            transition: color .2s;
        }

        .auth-eye-btn:hover {
            color: #c97878;
        }

        .auth-btn {
            width: 100%;
            padding: 13px 20px;
            border-radius: 14px;
            background: linear-gradient(135deg, #e8918a, #d97070);
            color: #fff;
            font-size: .9rem;
            font-weight: 600;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 4px;
            font-family: inherit;
            letter-spacing: .01em;
            transition: opacity .2s, transform .15s, box-shadow .2s;
            box-shadow: 0 4px 16px rgba(210, 100, 100, .25);
        }

        .auth-btn:hover {
            opacity: .92;
            transform: translateY(-1px);
            box-shadow: 0 8px 22px rgba(210, 100, 100, .3);
        }

        .auth-btn:active {
            transform: scale(.98);
        }

        .auth-btn:disabled {
            opacity: .65;
            cursor: not-allowed;
            transform: none;
        }

        .auth-alert {
            display: none;        /* hidden by default — JS removes .hidden to show */
            align-items: flex-start;
            gap: 9px;
            padding: 11px 14px;
            border-radius: 12px;
            font-size: .8rem;
            line-height: 1.45;
            margin-bottom: 10px;
        }
        /* When .hidden is removed by JS, flex layout kicks in */
        .auth-alert:not(.hidden) {
            display: flex;
        }

        .auth-alert-error {
            background: #fff0f0;
            border: 1px solid #f5c8c8;
            color: #a85050;
        }

        .auth-alert-success {
            background: #f0faf4;
            border: 1px solid #b8e6c8;
            color: #3a7a55;
        }

        .auth-demo-box {
            margin-top: 14px;
            padding: 11px 14px;
            border-radius: 13px;
            background: linear-gradient(135deg, #fdf3f3, #fce8e8);
            border: 1px solid #f5d8d8;
            font-size: .72rem;
            color: #a07070;
            line-height: 1.7;
        }

        .auth-demo-title {
            font-weight: 700;
            color: #c97878;
            margin-bottom: 3px;
        }

        .auth-demo-sep {
            opacity: .5;
            margin: 0 3px;
        }

        .auth-switch-text {
            text-align: center;
            font-size: .8rem;
            color: #b08080;
            margin-top: 18px;
        }

        .auth-switch-link {
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 700;
            color: #d97070;
            font-size: inherit;
            font-family: inherit;
            padding: 0;
            margin-left: 3px;
            transition: color .18s;
        }

        .auth-switch-link:hover {
            color: #b85555;
            text-decoration: underline;
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
    </style>

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
        }

        function switchTab(tab) {
            const isLogin = tab === 'login';
            document.getElementById('loginPanel').classList.toggle('hidden', !isLogin);
            document.getElementById('registerPanel').classList.toggle('hidden', isLogin);

            // Update title and subtitle
            const title    = document.getElementById('authModalTitle');
            const subtitle = document.getElementById('authModalSubtitle');
            if (title)    title.textContent    = isLogin ? 'Welcome back'     : 'Create an account';
            if (subtitle) subtitle.textContent = isLogin
                ? 'Sign in to your Sweet Heaven account'
                : 'Join us and enjoy exclusive treats';

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
            setBtnLoading('loginSubmitBtn', 'loginBtnText', true, 'Sign In');

            const body = new URLSearchParams({
                action: 'login',
                email: document.getElementById('modalEmail').value,
                password: document.getElementById('modalPassword').value,
            });

            fetch('/sweetheaven/api/auth_modal.php', { method: 'POST', body })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = data.redirect;
                    } else {
                        setLoginError(data.error);
                        setBtnLoading('loginSubmitBtn', 'loginBtnText', false, 'Sign In');
                    }
                })
                .catch(() => {
                    setLoginError('Network error. Please try again.');
                    setBtnLoading('loginSubmitBtn', 'loginBtnText', false, 'Sign In');
                });
        }
       
        function submitRegister(e) {
            e.preventDefault();
            setRegisterError('');
            document.getElementById('registerSuccess').classList.add('hidden');
            setBtnLoading('registerSubmitBtn', 'registerBtnText', true, 'Create Account');

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
                    setBtnLoading('registerSubmitBtn', 'registerBtnText', false, 'Create Account');
                    if (data.success) {
                        document.getElementById('modalRegisterForm').reset();
                        const successEl = document.getElementById('registerSuccess');
                        document.getElementById('registerSuccessMsg').textContent = data.message + ' You can now sign in.';
                        successEl.classList.remove('hidden');
                        // Auto-switch to login after 2 seconds
                        setTimeout(() => switchTab('login'), 2000);
                    } else {
                        setRegisterError(data.error);
                    }
                })
                .catch(() => {
                    setRegisterError('Network error. Please try again.');
                    setBtnLoading('registerSubmitBtn', 'registerBtnText', false, 'Create Account');
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
    </script>
</body>

</html>