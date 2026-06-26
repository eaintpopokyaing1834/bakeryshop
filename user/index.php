<?php
if (session_status() === PHP_SESSION_NONE)
    session_start();
require_once __DIR__ . '/../config/db.php';

$db = getDB();
$categoryId = (int) ($_GET['category_id'] ?? 0);
$search = trim($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'newest';

$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();

$bestSellers = $db->query("
    SELECT p.*,
           c.name AS category_name,
           pi.image_url AS primary_image,
           COALESCE(AVG(r.rating),0) AS avg_rating,
           COUNT(DISTINCT oi.id) AS total_sold
    FROM products p
    JOIN categories c ON p.category_id = c.id
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

$customerReviews = $db->query("SELECT name, message, created_at FROM customer_reviews WHERE status='approved' ORDER BY created_at DESC LIMIT 2")->fetchAll();
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
    </style>
</head>

<body class="overflow-x-hidden text-gray-700">

    <?php require_once __DIR__ . '/../includes/header.php'; ?>

    <!-- ═══════════════════════════════════════ HERO ═══════════════════════════════════════ -->
    <form method="GET" class="flex gap-2 items-center justify-center py-6">
        <input type="hidden" name="category_id" value="<?= $categoryId ?>">
        <input type="hidden" name="sort" value="<?= $sort ?>">
        <input type="search" name="search" placeholder="🔍 Search products..." value="<?= htmlspecialchars($search) ?>"
            class="border border-gray-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300 w-48">
    </form>
    <section class="relative overflow-hidden bg-[#fdf8f3]">

        <div class="max-w-7xl mx-auto px-6 py-10 md:py-10 grid md:grid-cols-2 gap-12 items-center">


            <!-- Left: Text -->
            <div class="fade-up">
                <span class="pill mb-6">
                    <span class="pill-dot"></span>
                    Freshly Baked Every Morning
                </span>

                <h1 class="serif text-5xl md:text-[3.8rem] leading-[1.1] text-gray-800 mt-5 mb-5">
                    A Taste of Heaven<br> in Every Bite
                </h1>

                <p class="text-gray-500 text-[15px] leading-7 mb-8 max-w-md">
                    Handcrafted with love and the finest ingredients. From celebration cakes to everyday treats — our
                    bakery brings joy to every table.
                </p>

                <div class="flex flex-wrap gap-3 mb-10">
                    <a href="/sweetheaven/user/products.php" style="background:#e8746a;"
                        class="inline-flex items-center gap-2 text-white px-7 py-3.5 rounded-full font-semibold text-sm hover:opacity-90 hover:-translate-y-0.5 transition-all duration-200 shadow-md shadow-rose-200">
                        Shop Now
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                    </a>
                    <a href="#categories"
                        class="inline-flex items-center gap-2 border border-rose-200 text-rose-500 bg-white px-7 py-3.5 rounded-full font-semibold text-sm hover:bg-rose-50 hover:-translate-y-0.5 transition-all duration-200">
                        Browse Categories
                    </a>
                </div>

                <!-- Stats row -->
                <div class="flex items-center gap-8">
                    <div>
                        <p class="text-2xl font-bold text-gray-800">50+</p>
                        <p class="text-xs text-gray-400 mt-0.5">Products</p>
                    </div>
                    <div class="w-px h-8 bg-gray-200"></div>
                    <div>
                        <p class="text-2xl font-bold text-gray-800">500+</p>
                        <p class="text-xs text-gray-400 mt-0.5">Happy Customer</p>
                    </div>
                    <div class="w-px h-8 bg-gray-200"></div>
                    <div>
                        <p class="text-2xl font-bold text-gray-800">⭐ 4.9</p>
                        <p class="text-xs text-gray-400 mt-0.5">Rating</p>
                    </div>
                </div>
            </div>

            <!-- Right: Photo Collage -->
            <div class="fade-up fade-up-d2 relative hidden md:block">
                <!-- Decorative circle -->
                <div class="absolute -top-8 -right-8 w-72 h-72 rounded-full"
                    style="background:var(--rose-light);z-index:0;"></div>
                <div class="relative z-10 grid grid-cols-2 gap-3">
                    <img src="../images/ceremony.jpg" alt="Beautiful cake" class="collage-img w-full h-52 shadow-md">
                    <img src="../images/pudd.jpg" alt="Croissant" class="collage-img w-full h-52 shadow-md mt-8">
                    <img src="../images/lemon.jpg" alt="Cupcakes" class="collage-img w-full h-52 shadow-md">
                    <img src="../images/donut.jpg" alt="Fresh bread" class="collage-img w-full h-52 shadow-md mt-8">
                </div>
            </div>
        </div>
    </section>

    <!-- ═════════════════════════ FEATURE BAR ═════════════════════════ -->
    <section class="bg-white border-y border-gray-100 py-7">
        <div class="max-w-7xl mx-auto px-6 grid grid-cols-2 md:grid-cols-4 gap-6">
            <?php foreach ([
                ['🌿', 'Natural Ingredients', 'Premium organic quality'],
                ['🔥', 'Baked Fresh Daily', 'Made every morning'],
                ['🚚', 'Fast Delivery', 'Same-day available'],
                ['💝', 'Made with Love', 'Passion in every bite'],
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
                        What We Offer
                    </p>
                    <h2 class="serif text-4xl text-gray-800">Our Categories</h2>
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
                        Customers
                        Love</p>
                    <h2 class="serif text-4xl text-gray-800">Best Sellers</h2>
                </div>

            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                <?php foreach ($bestSellers as $product): ?>
                    <?php
                    $imgSrc = $product['primary_image']
                        ? '/sweetheaven/' . $product['primary_image']
                        : '/sweetheaven/images/maincake.jpg';
                    $rating = round($product['avg_rating']);
                    ?>
                    <div class="product-card group bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-sm hover:shadow-xl transition-all duration-500 hover:-translate-y-2 cursor-pointer"
                        onclick="toggleProductName(<?= $product['id'] ?>)">
                        <div class="relative overflow-hidden bg-gradient-to-br from-rose-50 to-amber-50 aspect-[4/3]">
                            <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($product['name']) ?>"
                                class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
                            <button onclick="event.stopPropagation(); toggleWishlist(<?= $product['id'] ?>)"
                                class="absolute top-3 right-3 w-9 h-9 rounded-full bg-white/90 text-gray-400 shadow-md flex items-center justify-center hover:bg-rose-500 hover:text-white transition-all duration-200 backdrop-blur-sm"
                                title="Wishlist">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                </svg>
                            </button>
                            <?php if ($product['stock'] < 5): ?>
                                <div
                                    class="absolute top-3 left-3 bg-gradient-to-r from-amber-400 to-orange-500 text-white text-xs font-bold px-3 py-1 rounded-full shadow-md">
                                    Low Stock</div>
                            <?php endif; ?>
                            <div
                                class="absolute inset-0 bg-gradient-to-t from-black/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none">
                            </div>
                        </div>

                        <div class="p-4">
                            <p class="text-xs font-semibold uppercase tracking-wider text-rose-400 mb-2">
                                <?= htmlspecialchars($product['category_name']) ?>
                            </p>

                            <div class="flex items-center gap-0.5 mb-3">
                                <?php for ($s = 1; $s <= 5; $s++): ?>
                                    <svg class="w-3.5 h-3.5 <?= $s <= $rating ? 'text-amber-400' : 'text-gray-200' ?>"
                                        fill="currentColor" viewBox="0 0 20 20">
                                        <path
                                            d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                    </svg>
                                <?php endfor; ?>
                            </div>

                            <div id="productName_<?= $product['id'] ?>"
                                class="overflow-hidden transition-all duration-300 max-h-0 opacity-0 mb-0">
                                <a href="/sweetheaven/user/product_detail.php?id=<?= $product['id'] ?>"
                                    onclick="event.stopPropagation()">
                                    <h3 class="font-bold text-gray-800 text-sm hover:text-rose-500 transition-colors">
                                        <?= htmlspecialchars($product['name']) ?>
                                    </h3>
                                </a>
                            </div>

                            <div class="flex items-center justify-between pt-3 mt-1 border-t border-gray-50">
                                <span class="font-bold text-[15px] text-rose-500"><?= number_format($product['price']) ?>
                                    <span class="text-xs font-normal text-gray-400">MMK</span></span>
                                <button
                                    onclick="event.stopPropagation(); addToCart(<?= $product['id'] ?>, '<?= addslashes($product['name']) ?>')"
                                    class="bg-rose-500 hover:bg-rose-600 text-white px-4 py-1.5 rounded-full text-xs font-semibold transition-colors shadow-sm shadow-rose-200">
                                    Add to Cart
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="flex items-center justify-center mt-6">
            <a href="/sweetheaven/user/products.php"
                class="hidden sm:inline-flex items-center justify-center gap-1.5 text-center text-sm font-semibold text-rose-500 hover:text-rose-600 transition-colors bg-pink-200 rounded-xl px-4 py-2">
                See All Products
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13 7l5 5m0 0l-5 5m5-5H6" />
                </svg>
            </a>
        </div>
    </section>

    <!-- ═════════════════════════ WHY CHOOSE US ═════════════════════════ -->
    <section class="py-20 bg-[#fdf8f3]">
        <div class="max-w-7xl mx-auto px-6 grid md:grid-cols-2 gap-12 items-center">
            <!-- Image -->
            <div class="rounded-3xl overflow-hidden shadow-lg fade-up">
                <img src="https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=700&q=80&auto=format&fit=crop"
                    alt="Baker at work" class="w-full h-80 object-cover">
            </div>
            <!-- Text -->
            <div class="fade-up fade-up-d2">
                <p class="text-xs font-semibold uppercase tracking-widest mb-2" style="color:#e8746a;">Our Story</p>
                <h2 class="serif text-3xl text-gray-800 mb-5">Baked with Passion,<br>Served with Love</h2>
                <p class="text-gray-500 text-[15px] leading-7 mb-6">
                    Every item on our menu starts with a simple promise — to use only the finest, freshest ingredients.
                    Our bakers arrive before dawn so you always have something warm and wonderful waiting.
                </p>
                <div class="space-y-3">
                    <?php foreach ([
                        ['🌾', 'Locally sourced flour and dairy'],
                        ['🥚', 'Free-range eggs, always fresh'],
                        ['🍓', 'Real fruit fillings, no artificial flavors'],
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

    <!-- ═════════════════════════ PROMOTIONS ═════════════════════════ -->
    <section class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-6">

            <div class="grid lg:grid-cols-2  gap-6">

                <!-- Promo 1 -->

                <div class="flex flex-col gap-6">
                    <div class="text-3xl font-semibold text-center">Special Promotion</div>
                    <div class="promo-card rounded-2xl overflow-hidden flex flex-col md:flex-row border border-rose-100"
                        style="background:var(--rose-light);">
                        <div class="p-12 flex-1">
                            <span class="text-3xl mb-3 block">🎉</span>
                            <h3 class="font-bold text-gray-800 text-xl mb-2">10% Off Your First Order!</h3>
                            <p class="text-gray-500 text-sm leading-relaxed mb-5">Sign up and get an instant discount on
                                your very first purchase. No code needed!</p>
                            <a href="/sweetheaven/auth/register.php"
                                class="inline-block text-white font-semibold px-6 py-2.5 rounded-full text-sm hover:opacity-90 transition-opacity"
                                style="background:#e8746a;">
                                Claim Discount →
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
                            <h3 class="font-bold text-gray-800 text-xl mb-2">Free Gift Over 50,000 MMK</h3>
                            <p class="text-gray-500 text-sm leading-relaxed mb-5">Spend 50,000 MMK or more and we'll add
                                a
                                delicious free treat to your order!</p>
                            <a href="/sweetheaven/user/products.php"
                                class="inline-block text-white font-semibold px-6 py-2.5 rounded-full text-sm hover:opacity-90 transition-opacity"
                                style="background:#f59e0b;">
                                Shop Now →
                            </a>
                        </div>
                        <div class="hidden md:block w-40 flex-shrink-0">
                            <img src="https://images.unsplash.com/photo-1551024601-bec78aea704b?w=300&q=80&auto=format&fit=crop"
                                alt="Donuts" class="w-full h-full object-cover">
                        </div>
                    </div>
                </div>
                <article class="flex flex-col gap-6">
                    <div class="text-center text-3xl font-semibold">Latest Products</div>
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

    <!-- ═════════════════════════ CUSTOMER REVIEWS ═════════════════════════ -->
    <section id="customerReviews" class="py-20 bg-[#fdf8f3]">
        <div class="max-w-2xl mx-auto px-6">
            <div class="text-center mb-12 fade-up">
                <p class="text-xs font-semibold uppercase tracking-widest mb-2" style="color:#e8746a;">Reviews</p>
                <h2 class="serif text-3xl text-gray-800">Loved by Our Customers</h2>
                <p class="text-gray-400 text-sm mt-2">Here's what our sweet community has to say</p>
            </div>

            <div class="grid md:grid-cols-2 gap-5 mb-12" id="reviewsList">
                <?php if (empty($customerReviews)): ?>
                    <div class="md:col-span-2 text-center text-gray-400 py-10">
                        <p class="text-4xl mb-3">💬</p>
                        <p class="text-sm">No reviews yet. Be the first to share your experience!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($customerReviews as $r): ?>
                        <div class="review-card bg-white border border-gray-100 rounded-2xl p-6 shadow-sm">
                            <div class="flex items-center gap-3 mb-4">
                                <div
                                    class="w-10 h-10 bg-rose-100 rounded-full flex items-center justify-center text-rose-500 font-bold text-sm">
                                    <?= strtoupper(substr($r['name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-700 text-sm"><?= htmlspecialchars($r['name']) ?></p>
                                    <p class="text-xs text-gray-400"><?= date('M j, Y', strtotime($r['created_at'])) ?></p>
                                </div>
                            </div>
                            <p class="text-gray-500 text-sm leading-7">"<?= htmlspecialchars($r['message']) ?>"</p>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Review Form -->
            <div class="max-w-2xl mx-auto bg-white border border-gray-100 rounded-2xl p-8 shadow-sm">
                <h3 class="font-bold text-gray-800 text-lg mb-2">Share Your Experience</h3>
                <p class="text-gray-400 text-sm mb-6">We'd love to hear your thoughts about our products and service.
                </p>
                <form id="reviewForm" class="space-y-4">
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Full Name *</label>
                            <input type="text" id="reviewName" required
                                class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm"
                                placeholder="Your name">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Email Address *</label>
                            <input type="email" id="reviewEmail" required
                                class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm"
                                placeholder="you@example.com">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Your Review *</label>
                        <textarea id="reviewMessage" rows="4" required
                            class="w-full px-4 py-3 rounded-xl border border-gray-200 focus:outline-none focus:ring-2 focus:ring-rose-300 text-sm resize-none"
                            placeholder="Tell us about your experience..."></textarea>
                    </div>
                    <button type="submit"
                        class="w-full sm:w-auto bg-rose-500 hover:bg-rose-600 text-white font-semibold px-8 py-3 rounded-xl transition-colors text-sm">
                        Submit Review
                    </button>
                </form>
                <div id="reviewFormMsg" class="mt-4 hidden"></div>
            </div>
        </div>
    </section>

    <script>
        document.getElementById('reviewForm')?.addEventListener('submit', function (e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            const msgBox = document.getElementById('reviewFormMsg');
            btn.disabled = true;
            btn.textContent = 'Submitting...';

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
                    btn.textContent = 'Submit Review';
                })
                .catch(() => {
                    msgBox.classList.remove('hidden');
                    msgBox.className = 'mt-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm';
                    msgBox.textContent = 'Something went wrong. Please try again.';
                    btn.disabled = false;
                    btn.textContent = 'Submit Review';
                });
        });
    </script>

    <!-- Toast -->
    <div id="toast"
        class="hidden fixed bottom-6 right-6 text-white px-5 py-3 rounded-xl shadow-lg text-sm font-medium z-50 flex items-center gap-2"
        style="background:#e8746a;">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        <span id="toastMsg">Added to cart!</span>
    </div>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>

    <script>
        let activeProductId = null;

        function toggleProductName(productId) {
            const prevEl = document.getElementById('productName_' + activeProductId);
            if (prevEl) {
                prevEl.style.maxHeight = '0';
                prevEl.style.opacity = '0';
            }

            if (activeProductId === productId) {
                activeProductId = null;
                return;
            }

            activeProductId = productId;
            const el = document.getElementById('productName_' + productId);
            if (el) {
                el.style.maxHeight = '48px';
                el.style.opacity = '1';
            }
        }

        function addToCart(productId, productName) {
            fetch('/sweetheaven/api/cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=add&product_id=${productId}&qty=1`
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showToast(`${productName} added to cart!`);
                        const badge = document.getElementById('cartBadge');
                        if (badge) { badge.textContent = data.cart_count; badge.classList.remove('hidden'); }
                    } else if (data.redirect) {
                        window.location.href = '/sweetheaven/auth/login.php';
                    }
                });
        }

        function toggleWishlist(productId) {
            fetch('/sweetheaven/api/wishlist.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `product_id=${productId}`
            })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.is_wishlisted ? '❤️ ' + (data.message || 'Added to wishlist!') : '💔 Removed from wishlist');
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