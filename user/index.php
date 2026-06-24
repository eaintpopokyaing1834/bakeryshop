<?php
if (session_status() === PHP_SESSION_NONE)
    session_start();
require_once __DIR__ . '/../config/db.php';

$db = getDB();

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
    LIMIT 8
")->fetchAll();
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

        /* Product card overlay */
        .pcard .overlay {
            opacity: 0;
            transition: opacity .25s ease;
        }

        .pcard:hover .overlay {
            opacity: 1;
        }

        .pcard:hover img {
            transform: scale(1.06);
        }

        .pcard img {
            transition: transform .45s ease;
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
    <section class="relative overflow-hidden bg-[#fdf8f3]">
        <div class="max-w-7xl mx-auto px-6 py-16 md:py-24 grid md:grid-cols-2 gap-12 items-center">

            <!-- Left: Text -->
            <div class="fade-up">
                <span class="pill mb-6">
                    <span class="pill-dot"></span>
                    Freshly Baked Every Morning
                </span>

                <h1 class="serif text-5xl md:text-[3.8rem] leading-[1.1] text-gray-800 mt-5 mb-5">
                    A Taste of<br>
                    <em class="not-italic" style="color:#e8746a;">Heaven</em><br>
                    in Every Bite
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

    <!-- ═════════════════════════ CATEGORIES ═════════════════════════ -->
    <section id="categories" class="py-20 bg-[#fdf8f3]">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex items-end justify-between mb-10 fade-up">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color:#e8746a;">What We Offer
                    </p>
                    <h2 class="serif text-3xl text-gray-800">Our Categories</h2>
                </div>
                <a href="/sweetheaven/user/products.php"
                    class="hidden sm:inline-flex items-center gap-1.5 text-sm font-semibold text-rose-500 hover:text-rose-600 transition-colors">
                    View All
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>
                </a>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                <?php foreach ($categories as $cat): ?>
                    <a href="/sweetheaven/user/products.php?category_id=<?= $cat['id'] ?>"
                        class="cat-card group bg-white border border-gray-100 rounded-2xl p-5 text-center shadow-sm">
                        <div class="w-20 h-20 mx-auto rounded-2xl overflow-hidden mb-3 group-hover:scale-105 transition-transform duration-300"
                            style="background:var(--rose-light);">
                            <?php
                            $catImg = $cat['image'] ?? '';
                            $catImg = ltrim(str_replace('../', '', $catImg), '/');
                            ?>
                            <?php if ($catImg): ?>
                                <img src="/sweetheaven/<?= htmlspecialchars($catImg) ?>" class="w-full h-full object-cover"
                                    alt="<?= htmlspecialchars($cat['name']) ?>"
                                    onerror="this.parentElement.innerHTML='<div class=\'w-full h-full flex items-center justify-center text-3xl\'>🍰</div>'">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center text-3xl">🍰</div>
                            <?php endif; ?>
                        </div>
                        <p class="font-semibold text-gray-600 text-sm group-hover:text-rose-500 transition-colors">
                            <?= htmlspecialchars($cat['name']) ?>
                        </p>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ═════════════════════════ BEST SELLERS ═════════════════════════ -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex items-end justify-between mb-10 fade-up">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-widest mb-1" style="color:#e8746a;">Customers
                        Love</p>
                    <h2 class="serif text-3xl text-gray-800">Best Sellers</h2>
                </div>
                <a href="/sweetheaven/user/products.php"
                    class="hidden sm:inline-flex items-center gap-1.5 text-sm font-semibold text-rose-500 hover:text-rose-600 transition-colors">
                    See All
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>
                </a>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                <?php foreach ($bestSellers as $product): ?>
                    <div
                        class="pcard group bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden hover:shadow-md transition-all duration-300 hover:-translate-y-0.5">
                        <div class="relative h-48 overflow-hidden" style="background:var(--rose-light);">
                            <?php
                            $imgSrc = $product['primary_image']
                                ? '/sweetheaven/' . $product['primary_image']
                                : '/sweetheaven/images/maincake.jpg';
                            ?>
                            <img src="<?= htmlspecialchars($imgSrc) ?>" alt="<?= htmlspecialchars($product['name']) ?>"
                                class="w-full h-full object-cover">

                            <!-- Hover overlay -->
                            <div class="overlay absolute inset-0 flex items-center justify-center gap-2"
                                style="background:rgba(0,0,0,.18);">
                                <a href="/sweetheaven/user/product_detail.php?id=<?= $product['id'] ?>"
                                    class="bg-white text-rose-500 rounded-full p-2.5 hover:bg-rose-500 hover:text-white transition-colors shadow"
                                    title="View">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </a>
                                <button onclick="addToCart(<?= $product['id'] ?>, '<?= addslashes($product['name']) ?>')"
                                    class="bg-white text-rose-500 rounded-full p-2.5 hover:bg-rose-500 hover:text-white transition-colors shadow"
                                    title="Add to Cart">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                </button>
                                <button onclick="toggleWishlist(<?= $product['id'] ?>)"
                                    class="bg-white text-rose-500 rounded-full p-2.5 hover:bg-rose-500 hover:text-white transition-colors shadow"
                                    title="Wishlist">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                    </svg>
                                </button>
                            </div>

                            <?php if ($product['stock'] < 5): ?>
                                <div class="absolute top-2.5 left-2.5 text-white text-xs font-semibold px-2.5 py-0.5 rounded-full"
                                    style="background:#e8746a;">
                                    Low Stock
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="p-4">
                            <a href="/sweetheaven/user/product_detail.php?id=<?= $product['id'] ?>">
                                <h3
                                    class="font-semibold text-gray-700 text-sm mb-0.5 hover:text-rose-500 transition-colors line-clamp-1">
                                    <?= htmlspecialchars($product['name']) ?>
                                </h3>
                            </a>
                            <p class="text-xs text-gray-400 mb-2"><?= htmlspecialchars($product['category_name']) ?></p>

                            <div class="flex items-center gap-0.5 mb-3">
                                <?php $rating = round($product['avg_rating']);
                                for ($s = 1; $s <= 5; $s++): ?>
                                    <svg class="w-3 h-3 <?= $s <= $rating ? 'text-amber-400' : 'text-gray-200' ?>"
                                        fill="currentColor" viewBox="0 0 20 20">
                                        <path
                                            d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                    </svg>
                                <?php endfor; ?>
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="font-bold text-[15px]"
                                    style="color:#e8746a;"><?= number_format($product['price']) ?> <span
                                        class="text-xs font-normal text-gray-400">MMK</span></span>
                                <button onclick="addToCart(<?= $product['id'] ?>, '<?= addslashes($product['name']) ?>')"
                                    class="text-white px-4 py-1.5 rounded-full text-xs font-semibold transition-colors hover:opacity-90"
                                    style="background:#e8746a;">
                                    Add to Cart
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
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
            <div class="grid lg:grid-cols-2 gap-5">

                <!-- Promo 1 -->
                <div class="promo-card rounded-2xl overflow-hidden flex flex-col md:flex-row border border-rose-100"
                    style="background:var(--rose-light);">
                    <div class="p-8 flex-1">
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
                    <div class="p-8 flex-1">
                        <span class="text-3xl mb-3 block">🎁</span>
                        <h3 class="font-bold text-gray-800 text-xl mb-2">Free Gift Over 50,000 MMK</h3>
                        <p class="text-gray-500 text-sm leading-relaxed mb-5">Spend 50,000 MMK or more and we'll add a
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
        </div>
    </section>

    <!-- ═════════════════════════ TESTIMONIALS ═════════════════════════ -->
    <section class="py-20 bg-[#fdf8f3]">
        <div class="max-w-7xl mx-auto px-6">
            <div class="text-center mb-12 fade-up">
                <p class="text-xs font-semibold uppercase tracking-widest mb-2" style="color:#e8746a;">Reviews</p>
                <h2 class="serif text-3xl text-gray-800">Loved by Our Customers</h2>
                <p class="text-gray-400 text-sm mt-2">Here's what our sweet community has to say</p>
            </div>
            <div class="grid md:grid-cols-3 gap-5">
                <?php foreach ([
                    ['Sarah Jenkins', 'Croissant Enthusiast', 'The almond croissants are life-changing! Perfectly flaky and rich. I stop by every Saturday without fail.', 5, 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?q=80&w=80&auto=format&fit=crop&facepad=3&faces=1'],
                    ['David Miller', 'Local Food Guide', 'We ordered our daughter\'s wedding cake here and it exceeded all expectations — stunningly beautiful and delicious.', 5, 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?q=80&w=80&auto=format&fit=crop'],
                    ['Elena Rostova', 'Home Chef', 'Their sourdough bread is the best in the city. Perfectly tangy with a beautiful crust. The staff is always warm.', 4, 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?q=80&w=80&auto=format&fit=crop'],
                ] as $t): ?>
                    <div
                        class="bg-white border border-gray-100 rounded-2xl p-6 shadow-sm hover:shadow-md transition-shadow duration-300">
                        <!-- Stars -->
                        <div class="flex gap-0.5 mb-4">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <svg class="w-4 h-4 <?= $i <= $t[3] ? 'text-amber-400' : 'text-gray-200' ?>" fill="currentColor"
                                    viewBox="0 0 20 20">
                                    <path
                                        d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
                                </svg>
                            <?php endfor; ?>
                        </div>
                        <p class="text-gray-500 text-sm leading-7 mb-5">"<?= $t[2] ?>"</p>
                        <div class="flex items-center gap-3 border-t border-gray-50 pt-4">
                            <img src="<?= $t[4] ?>" class="w-10 h-10 rounded-full object-cover" alt="<?= $t[0] ?>">
                            <div>
                                <p class="font-semibold text-gray-700 text-sm"><?= $t[0] ?></p>
                                <p class="text-xs text-gray-400"><?= $t[1] ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

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
                    if (data.success) showToast(data.is_wishlisted ? '❤️ Added to wishlist!' : '💔 Removed from wishlist');
                    else if (data.redirect) window.location.href = '/sweetheaven/auth/login.php';
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