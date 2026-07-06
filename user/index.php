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
            <div class="fade-up">
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
                                <div
                                    class="absolute top-0 left-0 bg-rose-500 text-white text-xs font-bold px-3 py-1 rounded-md shadow-md" viewBox="0 0 24 24">
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
                                        <button onclick="addToCart(<?= $product['id'] ?>, '<?= addslashes($product['name']) ?>')"
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
                    <div class="absolute -bottom-5 -right-5 bg-white rounded-2xl shadow-lg px-6 py-4 hidden md:block">
                        <p class="text-3xl font-bold text-rose-500">🎨</p>
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
                    <div class="flex flex-wrap gap-6 text-sm">
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
                        <?= __('review_display_label') ?></p>
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
                                                    <?= htmlspecialchars($r['name']) ?></p>
                                                <p class="text-xs text-gray-400">
                                                    <?= date('M j, Y', strtotime($r['created_at'])) ?></p>
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
                        window.location.href = '/sweetheaven/auth/login.php';
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
                    } else if (data.redirect) window.location.href = '/sweetheaven/auth/login.php';
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
</body>

</html>