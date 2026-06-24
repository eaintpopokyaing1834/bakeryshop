<?php
// includes/header.php — Pure nav partial (no <html>/<body> wrapper)
if (session_status() === PHP_SESSION_NONE)
    session_start();
$cartCount = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item)
        $cartCount += $item['qty'];
}
$isLoggedIn = isset($_SESSION['user_id']);
$isAdmin = ($isLoggedIn && $_SESSION['role'] === 'admin');
?>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
    rel="stylesheet">
<style>
    * {
        font-family: 'Poppins', sans-serif;
    }

    .nav-link {
        position: relative;
    }

    .nav-link::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        width: 0;
        height: 1.5px;
        background: #f43f5e;
        transition: width .3s;
    }

    .nav-link:hover::after {
        width: 100%;
    }
</style>

<nav class="bg-pink-200 backdrop-blur-md border-b border-stone-100 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <div class="flex items-center justify-between h-16">

            <!-- Logo -->
            <a href="/sweetheaven/user/index.php" class="flex items-center gap-2 shrink-0">
                <img src="../images/shoplogo.png" class="h-10 w-auto" alt="Sweet Heaven">
                <span class="text-2xl font-bold text-stone-800 hidden sm:block">Sweet Heaven</span>
            </a>

            <!-- Desktop Nav -->
            <ul class="hidden md:flex items-center gap-6 text-stone-600 font-medium text-sm">
                <li><a href="/sweetheaven/user/index.php"
                        class="nav-link hover:text-rose-500 transition-colors">Home</a></li>
                <li><a href="/sweetheaven/user/products.php"
                        class="nav-link hover:text-rose-500 transition-colors">Products</a></li>
                <li><a href="/sweetheaven/user/products.php#categories"
                        class="nav-link hover:text-rose-500 transition-colors">Categories</a></li>
                <?php if ($isAdmin): ?>
                    <li><a href="/sweetheaven/admin/dashboard.php"
                            class="bg-rose-50 text-rose-600 px-4 py-1.5 rounded-lg text-xs font-semibold hover:bg-rose-100 transition-colors">Admin
                            Panel</a></li>
                <?php endif; ?>
            </ul>

            <!-- Right Actions -->
            <div class="flex items-center gap-3">
                <?php if ($isLoggedIn): ?>
                    <?php if (!$isAdmin): ?>
                        <!-- Wishlist -->
                        <a href="/sweetheaven/user/wishlist.php"
                            class="relative p-2 text-stone-400 hover:text-rose-500 transition-colors" title="Wishlist">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                            </svg>
                        </a>

                        <!-- Cart -->
                        <a href="/sweetheaven/user/cart.php"
                            class="relative p-2 text-stone-400 hover:text-rose-500 transition-colors" title="Cart">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <?php if ($cartCount > 0): ?>
                                <span id="cartBadge"
                                    class="absolute -top-1 -right-1 bg-rose-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-bold"><?= $cartCount ?></span>
                            <?php else: ?>
                                <span id="cartBadge"
                                    class="absolute -top-1 -right-1 bg-rose-500 text-white text-xs rounded-full w-5 h-5 <?= $cartCount > 0 ? 'flex' : 'hidden' ?> items-center justify-center font-bold"><?= $cartCount ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>

                    <!-- Profile Dropdown -->
                    <div class="relative" id="profileDropdown">
                        <button onclick="toggleProfile()"
                            class="flex items-center gap-2 bg-stone-50 hover:bg-stone-100 text-stone-600 px-3 py-2 rounded-lg transition-colors text-sm font-medium border border-stone-200/60">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <span class="max-w-[100px] truncate"><?= htmlspecialchars($_SESSION['name']) ?></span>
                            <svg class="w-3 h-3 text-stone-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div id="profileMenu"
                            class="hidden absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-lg border border-stone-100 overflow-hidden z-50">
                            <a href="/sweetheaven/user/profile.php"
                                class="flex items-center gap-2 px-4 py-3 text-sm text-stone-600 hover:bg-stone-50 hover:text-rose-500 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                My Profile
                            </a>
                            <a href="/sweetheaven/user/wishlist.php"
                                class="flex items-center gap-2 px-4 py-3 text-sm text-stone-600 hover:bg-stone-50 hover:text-rose-500 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                </svg>
                                Wishlist
                            </a>
                            <?php if ($isAdmin): ?>
                                <a href="/sweetheaven/admin/dashboard.php"
                                    class="flex items-center gap-2 px-4 py-3 text-sm text-stone-600 hover:bg-stone-50 hover:text-rose-500 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                            d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
                                    </svg>
                                    Admin Panel
                                </a>
                            <?php endif; ?>
                            <div class="border-t border-stone-100">
                                <a href="/sweetheaven/auth/logout.php"
                                    class="flex items-center gap-2 px-4 py-3 text-sm text-stone-500 hover:bg-rose-50 hover:text-rose-500 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                    Logout
                                </a>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <a href="/sweetheaven/auth/login.php"
                        class="text-stone-600 hover:text-rose-500 font-medium text-sm transition-colors">Login</a>
                    <a href="/sweetheaven/auth/register.php"
                        class="bg-rose-500 hover:bg-rose-600 text-white px-5 py-2 rounded-lg text-sm font-semibold transition-colors">Sign
                        Up</a>
                <?php endif; ?>

                <!-- Mobile Menu Button -->
                <button onclick="toggleMobileMenu()" class="md:hidden p-2 text-stone-400 hover:text-stone-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile Menu -->
        <div id="mobileMenu" class="hidden md:hidden pb-4 border-t border-stone-100 mt-2">
            <ul class="space-y-1 pt-3">
                <li><a href="/sweetheaven/user/index.php"
                        class="block px-4 py-2.5 text-stone-600 hover:text-rose-500 font-medium rounded-lg hover:bg-stone-50 text-sm">Home</a>
                </li>
                <li><a href="/sweetheaven/user/products.php"
                        class="block px-4 py-2.5 text-stone-600 hover:text-rose-500 font-medium rounded-lg hover:bg-stone-50 text-sm">Products</a>
                </li>
                <?php if (!$isAdmin): ?>
                    <li><a href="/sweetheaven/user/cart.php"
                            class="block px-4 py-2.5 text-stone-600 hover:text-rose-500 font-medium rounded-lg hover:bg-stone-50 text-sm">Cart
                            (<?= $cartCount ?>)</a></li>
                <?php endif; ?>
                <?php if ($isLoggedIn): ?>
                    <li><a href="/sweetheaven/user/profile.php"
                            class="block px-4 py-2.5 text-stone-600 hover:text-rose-500 font-medium rounded-lg hover:bg-stone-50 text-sm">Profile</a>
                    </li>
                    <li><a href="/sweetheaven/auth/logout.php"
                            class="block px-4 py-2.5 text-stone-500 font-medium rounded-lg hover:bg-rose-50 hover:text-rose-500 text-sm">Logout</a>
                    </li>
                <?php else: ?>
                    <li><a href="/sweetheaven/auth/login.php"
                            class="block px-4 py-2.5 text-stone-600 hover:text-rose-500 font-medium rounded-lg hover:bg-stone-50 text-sm">Login</a>
                    </li>
                    <li><a href="/sweetheaven/auth/register.php"
                            class="block px-4 py-2.5 text-rose-500 font-semibold rounded-lg hover:bg-rose-50 text-sm">Sign
                            Up</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<script>
    function toggleProfile() {
        document.getElementById('profileMenu').classList.toggle('hidden');
    }
    function toggleMobileMenu() {
        document.getElementById('mobileMenu').classList.toggle('hidden');
    }
    document.addEventListener('click', function (e) {
        const dropdown = document.getElementById('profileDropdown');
        if (dropdown && !dropdown.contains(e.target)) {
            document.getElementById('profileMenu')?.classList.add('hidden');
        }
    });
</script>