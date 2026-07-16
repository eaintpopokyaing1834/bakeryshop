<?php
// includes/header.php — Pure nav partial (no <html>/<body> wrapper)
if (session_status() === PHP_SESSION_NONE)
    session_start();

// Language loader (must come first)
require_once __DIR__ . '/lang.php';

$cartCount = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item)
        $cartCount += $item['qty'];
}
$isLoggedIn = isset($_SESSION['user_id']);
$isAdmin = ($isLoggedIn && in_array($_SESSION['role'] ?? '', ['admin', 'cashier']));
require_once __DIR__ . '/../config/db.php';
$db = getDB();
// Per-user unread count (customers only)
$totalNotifications = 0;
$wishlistCount = 0;
if ($isLoggedIn && !$isAdmin) {
    $userId = (int) $_SESSION['user_id'];
    $nstmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_seen=0");
    $nstmt->execute([$userId]);
    $totalNotifications = (int) $nstmt->fetchColumn();
    $wstmt = $db->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id=?");
    $wstmt->execute([$userId]);
    $wishlistCount = (int) $wstmt->fetchColumn();
}
$_currentLang = currentLang();
$categoryId = (int)($_GET['category_id'] ?? 0);
$search     = trim($_GET['search'] ?? '');
$discounted = (int)($_GET['discounted'] ?? 0);
$sort       = trim($_GET['sort'] ?? '');

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

    /* Language dropdown */
    .lang-dropdown-wrap {
        position: relative;
    }

    .lang-globe-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 4px;
        height: 34px;
        padding: 0 8px;
        border-radius: 8px;
        border: none;
        background: transparent;
        color: #78716c;
        cursor: pointer;
        white-space: nowrap;
        transition: color .2s;
    }

    .lang-globe-btn:hover {
        color: #e11d48;
    }

    .lang-menu {
        display: none;
        position: absolute;
        top: calc(100% + 8px);
        right: 0;
        width: 110px;
        background: #fff;
        border: 1px solid #f1e3e6;
        border-radius: 12px;
        box-shadow: 0 8px 24px rgba(180, 60, 80, .12);
        overflow: hidden;
        z-index: 200;
    }

    .lang-menu.open {
        display: block;
    }

    .lang-menu-item {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        width: 100%;
        padding: 9px 14px;
        font-size: 13px;
        font-weight: 500;
        color: #57534e;
        background: none;
        border: none;
        cursor: pointer;
        text-align: center;
        transition: background .15s, color .15s;
        font-family: inherit;
    }

    .lang-menu-item:hover {
        background: #fff0f3;
        color: #e11d48;
    }

    .lang-menu-item.active {
        color: #e11d48;
        font-weight: 700;
    }

    .lang-menu-item+.lang-menu-item {
        border-top: 1px solid #fce7eb;
    }
</style>

<nav class="bg-pink-200 backdrop-blur-md border-b border-stone-100 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <div class="flex items-center justify-between h-16">

            <!-- Logo -->
            <a href="/sweetheaven/user/index.php" class="flex items-center gap-2 shrink-0">
                <img src="/sweetheaven/images/9102671.png" class="h-10 w-auto" alt="Sweet Heaven">
                <span class="text-2xl font-bold text-stone-800 hidden sm:block">Sweet Heaven</span>
            </a>

            <!-- Desktop Nav -->
            <ul class="hidden md:flex items-center gap-6 text-stone-600 font-medium text-sm">
                <li><a href="/sweetheaven/user/index.php"
                        class="nav-link hover:text-rose-500 transition-colors"><?= __('nav_home') ?></a></li>
                <li><a href="/sweetheaven/user/products.php"
                        class="nav-link hover:text-rose-500 transition-colors"><?= __('nav_products') ?></a></li>
                <?php if (!$isAdmin): ?>
                    <li><a href="/sweetheaven/user/customize.php"
                            class="nav-link hover:text-rose-500 transition-colors"><?= __('nav_customize') ?></a></li>
                <?php endif; ?>
                <?php if ($isAdmin): ?>
                    <li><a href="/sweetheaven/admin/dashboard.php"
                            class="bg-rose-50 text-rose-600 px-4 py-1.5 rounded-lg text-xs font-semibold hover:bg-rose-100 transition-colors"><?= ($_SESSION['role'] ?? '') === 'cashier' ? 'Cashier Panel' : __('nav_admin_panel') ?></a>
                    </li>
                <?php endif; ?>
                <!-- Search -->
                <form method="GET" action="products.php" class="flex gap-2">
                    <input type="hidden" name="category_id" value="<?= $categoryId ?>">
                    <input type="hidden" name="sort" value="<?= $sort ?>">
                    <?php if ($discounted): ?><input type="hidden" name="discounted" value="1"><?php endif; ?>
                    <div class="relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-stone-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                        </svg>
                        <input type="search" name="search" placeholder="<?= __('products_search_ph') ?>"
                            value="<?= htmlspecialchars($search) ?>"
                            class="border border-gray-200 rounded-xl pl-9 pr-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300 w-48">
                    </div>
                </form>
            </ul>

            <!-- Right Actions -->
            <div class="flex flex-row items-center gap-6">

                <!-- Language Selector (icon-only, dropdown on click) -->
                <form method="POST" action="" id="langForm" style="display:none">
                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                    <input type="hidden" name="set_lang" id="langInput" value="<?= htmlspecialchars($_currentLang) ?>">
                </form>

                <div class="lang-dropdown-wrap" id="langDropdownWrap">
                    <!-- Globe icon button -->
                    <button type="button" class="lang-globe-btn" id="langGlobeBtn"
                        onclick="toggleLangMenu()" aria-haspopup="true" aria-expanded="false"
                        title="Select language">
                        <svg width="16" height="16" fill="none" stroke="#3b82f6" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                        </svg>
                        <span id="langLabel"><?= $_currentLang === 'my' ? ' မြန်မာ' : ' ENG' ?></span>
                    </button>

                    <!-- Dropdown menu -->
                    <div class="lang-menu" id="langMenu" role="menu">
                        <button type="button" class="lang-menu-item <?= $_currentLang === 'en' ? 'active' : '' ?>"
                            onclick="setLang('en')" role="menuitem">
                            <span>🌐</span> ENG
                        </button>
                        <button type="button" class="lang-menu-item <?= $_currentLang === 'my' ? 'active' : '' ?>"
                            onclick="setLang('my')" role="menuitem">
                            <span>🌐</span> မြန်မာ
                        </button>
                    </div>
                </div>

                <script>
                    function toggleLangMenu() {
                        const menu = document.getElementById('langMenu');
                        const btn = document.getElementById('langGlobeBtn');
                        const open = menu.classList.toggle('open');
                        btn.setAttribute('aria-expanded', open);
                    }

                    function setLang(code) {
                        document.getElementById('langInput').value = code;
                        document.getElementById('langLabel').textContent = code === 'my' ? ' မြန်မာ' : ' ENG';
                        document.getElementById('langForm').submit();
                    }
                    // Close when clicking outside
                    document.addEventListener('click', function(e) {
                        const wrap = document.getElementById('langDropdownWrap');
                        if (wrap && !wrap.contains(e.target)) {
                            document.getElementById('langMenu').classList.remove('open');
                            document.getElementById('langGlobeBtn').setAttribute('aria-expanded', 'false');
                        }
                    });
                </script>

                <?php if ($isLoggedIn): ?>
                    <?php if (!$isAdmin): ?>
                        <!-- notification bell -->
                        <div class="relative" id="notifWrapper">
                            <button onclick="toggleNotifDropdown()"
                                class="relative p-2 rounded-lg hover:bg-stone-100 transition-colors text-stone-500">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                </svg>
                                <?php if ($totalNotifications > 0): ?>
                                    <span id="notifBadge"
                                        class="absolute -top-0.5 -right-0.5 bg-rose-500 text-white text-[10px] font-bold rounded-full w-5 h-5 flex items-center justify-center">
                                        <?= $totalNotifications > 99 ? '99+' : $totalNotifications ?>
                                    </span>
                                <?php endif; ?>
                            </button>

                            <!-- Notification Dropdown -->
                            <div id="notifDropdown"
                                class="hidden absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-lg border border-stone-100 z-50 overflow-hidden">
                                <div class="flex items-center justify-between px-4 py-3 border-b border-stone-100">
                                    <h4 class="font-bold text-stone-800 text-sm"><?= __('nav_notifications') ?></h4>
                                    <button onclick="markAllSeen()"
                                        class="text-xs text-rose-500 hover:text-rose-600 font-semibold"><?= __('nav_mark_all_read') ?></button>
                                </div>
                                <div id="notifList" class="max-h-80 overflow-y-auto">
                                    <p class="text-center text-stone-400 text-sm py-6"><?= __('nav_notif_loading') ?></p>
                                </div>
                            </div>
                        </div><!-- /notifWrapper -->

                        <!-- Wishlist -->
                        <a href="/sweetheaven/user/wishlist.php"
                            class="relative p-2 text-stone-400 hover:text-rose-500 transition-colors"
                            title="<?= __('nav_wishlist') ?>">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                            </svg>
                            <?php if ($wishlistCount > 0): ?>
                                <span id="wishlistBadge"
                                    class="absolute -top-1 -right-1 bg-rose-500 text-white text-[11px] font-bold rounded-full w-5 h-5 grid place-items-center text-center leading-none">
                                    <?= $wishlistCount ?>
                                </span>
                            <?php else: ?>
                                <span id="wishlistBadge"
                                    class="absolute -top-1 -right-1 bg-rose-500 text-white text-[11px] font-bold rounded-full w-5 h-5 hidden grid place-items-center text-center leading-none">
                                    <?= $wishlistCount ?>
                                </span>
                            <?php endif; ?>
                        </a>

                        <!-- Cart -->
                        <a href="/sweetheaven/user/cart.php"
                            class="relative p-2 text-stone-400 hover:text-rose-500 transition-colors"
                            title="<?= __('nav_cart') ?>">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            <?php if ($cartCount > 0): ?>
                                <span id="cartBadge"
                                    class="absolute -top-1 -right-1 bg-rose-500 text-white text-[11px] font-bold rounded-full w-5 h-5 grid place-items-center text-center leading-none"><?= $cartCount ?></span>
                            <?php else: ?>
                                <span id="cartBadge"
                                    class="absolute -top-1 -right-1 bg-rose-500 text-white text-[11px] font-bold rounded-full w-5 h-5 hidden grid place-items-center text-center leading-none"><?= $cartCount ?></span>
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
                                <?= __('nav_my_profile') ?>
                            </a>
                            <?php if (!$isAdmin): ?>
                                <a href="/sweetheaven/user/wishlist.php"
                                    class="flex items-center gap-2 px-4 py-3 text-sm text-stone-600 hover:bg-stone-50 hover:text-rose-500 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                            d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                    </svg>
                                    <?= __('nav_wishlist') ?>
                                </a>
                                <a href="/sweetheaven/user/profile.php?tab=orders"
                                    class="flex items-center gap-2 px-4 py-3 text-sm text-stone-600 hover:bg-stone-50 hover:text-rose-500 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                    </svg>
                                    <?= __('nav_my_orders') ?>
                                </a>
                            <?php endif; ?>

                            <?php if ($isAdmin): ?>
                                <a href="/sweetheaven/admin/dashboard.php"
                                    class="flex items-center gap-2 px-4 py-3 text-sm text-stone-600 hover:bg-stone-50 hover:text-rose-500 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                            d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
                                    </svg>
                                    <?= ($_SESSION['role'] ?? '') === 'cashier' ? 'Cashier Panel' : __('nav_admin_panel') ?>
                                </a>
                            <?php endif; ?>
                            <div class="border-t border-stone-100">
                                <a href="/sweetheaven/auth/logout.php"
                                    onclick="event.preventDefault(); if(confirm('Are you sure you want to log out?')){ window.location.href='/sweetheaven/auth/logout.php'; }"
                                    class="flex items-center gap-2 px-4 py-3 text-sm text-stone-500 hover:bg-rose-50 hover:text-rose-500 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                    <?= __('nav_logout') ?>
                                </a>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <a href="/sweetheaven/auth/login.php"
                        onclick="if(typeof openAuthModal==='function'){event.preventDefault();openAuthModal('login');}"
                        class="text-stone-600 hover:text-rose-500 font-medium text-sm transition-colors"><?= __('nav_login') ?></a>
                    <a href="/sweetheaven/auth/register.php"
                        onclick="if(typeof openAuthModal==='function'){event.preventDefault();openAuthModal('register');}"
                        class="bg-rose-500 hover:bg-rose-600 text-white px-5 py-2 rounded-lg text-sm font-semibold transition-colors"><?= __('nav_signup') ?></a>
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
                        class="block px-4 py-2.5 text-stone-600 hover:text-rose-500 font-medium rounded-lg hover:bg-stone-50 text-sm"><?= __('nav_home') ?></a>
                </li>
                <li><a href="/sweetheaven/user/products.php"
                        class="block px-4 py-2.5 text-stone-600 hover:text-rose-500 font-medium rounded-lg hover:bg-stone-50 text-sm"><?= __('nav_products') ?></a>
                </li>
                <?php if (!$isAdmin): ?>
                    <li><a href="/sweetheaven/user/customize.php"
                            class="block px-4 py-2.5 text-stone-600 hover:text-rose-500 font-medium rounded-lg hover:bg-stone-50 text-sm"><?= __('nav_customize') ?></a>
                    </li>
                <?php endif; ?>
                <?php if (!$isAdmin): ?>
                    <li><a href="/sweetheaven/user/cart.php"
                            class="block px-4 py-2.5 text-stone-600 hover:text-rose-500 font-medium rounded-lg hover:bg-stone-50 text-sm"><?= __('nav_cart') ?>
                            (<?= $cartCount ?>)</a></li>
                <?php endif; ?>
                <?php if ($isLoggedIn): ?>
                    <li><a href="/sweetheaven/user/profile.php"
                            class="block px-4 py-2.5 text-stone-600 hover:text-rose-500 font-medium rounded-lg hover:bg-stone-50 text-sm"><?= __('nav_profile') ?></a>
                    </li>
                    <li><a href="/sweetheaven/auth/logout.php"
                            onclick="event.preventDefault(); if(confirm('Are you sure you want to log out?')){ window.location.href='/sweetheaven/auth/logout.php'; }"
                            class="block px-4 py-2.5 text-stone-500 font-medium rounded-lg hover:bg-rose-50 hover:text-rose-500 text-sm"><?= __('nav_logout') ?></a>
                    </li>
                <?php else: ?>
                    <li><a href="/sweetheaven/auth/login.php"
                            onclick="if(typeof openAuthModal==='function'){event.preventDefault();closeAuthModal&&closeAuthModal();toggleMobileMenu();openAuthModal('login');}"
                            class="block px-4 py-2.5 text-stone-600 hover:text-rose-500 font-medium rounded-lg hover:bg-stone-50 text-sm"><?= __('nav_login') ?></a>
                    </li>
                    <li><a href="/sweetheaven/auth/register.php"
                            onclick="if(typeof openAuthModal==='function'){event.preventDefault();toggleMobileMenu();openAuthModal('register');}"
                            class="block px-4 py-2.5 text-rose-500 font-semibold rounded-lg hover:bg-rose-50 text-sm"><?= __('nav_signup') ?></a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<script>
    /* ── Profile dropdown ── */
    function toggleProfile() {
        document.getElementById('profileMenu').classList.toggle('hidden');
        // close notif if open
        document.getElementById('notifDropdown')?.classList.add('hidden');
    }

    function toggleMobileMenu() {
        document.getElementById('mobileMenu').classList.toggle('hidden');
    }

    /* ── Notification bell ── */
    let notifLoaded = false;

    function toggleNotifDropdown() {
        const dd = document.getElementById('notifDropdown');
        if (!dd) return;
        const isHidden = dd.classList.contains('hidden');
        dd.classList.toggle('hidden');
        // close profile if open
        document.getElementById('profileMenu')?.classList.add('hidden');
        if (isHidden) {
            notifLoaded = false; // always fresh-load on open
            loadNotifications();
        }
    }

    function loadNotifications() {
        fetch('/sweetheaven/api/user_notifications.php?action=list')
            .then(r => r.json())
            .then(data => {
                notifLoaded = true;
                const list = document.getElementById('notifList');
                if (!list) return;
                if (!Array.isArray(data) || data.length === 0) {
                    list.innerHTML = `<div class="flex flex-col items-center py-10 text-stone-400">
                        <svg class="w-10 h-10 mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        <p class="text-sm font-medium"><?= __('nav_no_notifications') ?></p>
                    </div>`;
                    return;
                }
                const statusIcons = {
                    'order_status': '📦',
                    'new_order': '🛒',
                };
                list.innerHTML = data.map(n => {
                    const icon = statusIcons[n.type] || '🔔';
                    const time = timeAgo(n.created_at);
                    const unread = n.is_seen == 0 ? 'bg-rose-50 border-l-4 border-rose-400' : '';
                    let link = '#';
                    if (n.order_id) link = `/sweetheaven/user/profile.php?tab=orders`;
                    return `<a href="${link}" onclick="markAllSeen()" class="flex items-start gap-3 px-4 py-3 hover:bg-stone-50 transition-colors ${unread} cursor-pointer">
                        <span class="text-xl mt-0.5">${icon}</span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-stone-700 leading-snug">${escHtml(n.message)}</p>
                            <p class="text-xs text-stone-400 mt-1">${time}</p>
                        </div>
                        ${n.is_seen == 0 ? '<span class="w-2 h-2 rounded-full bg-rose-500 mt-1.5 shrink-0"></span>' : ''}
                    </a>`;
                }).join('');
            })
            .catch(() => {
                const list = document.getElementById('notifList');
                if (list) list.innerHTML = '<p class="text-center text-stone-400 text-sm py-6"><?= __('nav_notif_error') ?></p>';
            });
    }

    function markAllSeen() {
        fetch('/sweetheaven/api/user_notifications.php?action=mark_seen', {
                method: 'POST'
            })
            .then(r => r.json())
            .then(() => {
                // hide badge
                const badge = document.getElementById('notifBadge');
                if (badge) badge.remove();
                // remove unread highlights
                document.querySelectorAll('#notifList a').forEach(el => {
                    el.classList.remove('bg-rose-50', 'border-l-4', 'border-rose-400');
                    const dot = el.querySelector('.bg-rose-500.rounded-full');
                    if (dot) dot.remove();
                });
                notifLoaded = false; // allow reload next open
            });
    }

    /* ── Live badge polling (every 30 seconds) ── */
    <?php if ($isLoggedIn && !$isAdmin): ?>
        let _lastNotifCount = <?= $totalNotifications ?>;

        function pollNotifCount() {
            fetch('/sweetheaven/api/user_notifications.php?action=count')
                .then(r => r.json())
                .then(data => {
                    const count = data.count || 0;
                    const badge = document.getElementById('notifBadge');
                    if (count > 0) {
                        if (badge) {
                            badge.textContent = count > 99 ? '99+' : count;
                        } else {
                            // Create badge if it's new
                            const bellBtn = document.querySelector('#notifWrapper button');
                            if (bellBtn) {
                                const span = document.createElement('span');
                                span.id = 'notifBadge';
                                span.className = 'absolute -top-0.5 -right-0.5 bg-rose-500 text-white text-[10px] font-bold rounded-full w-5 h-5 flex items-center justify-center animate-bounce';
                                span.textContent = count > 99 ? '99+' : count;
                                bellBtn.appendChild(span);
                                // Animate in briefly then settle
                                setTimeout(() => span.classList.remove('animate-bounce'), 3000);
                            }
                        }
                        // If new notifications came in while dropdown is open, reload the list
                        if (count > _lastNotifCount) {
                            const dd = document.getElementById('notifDropdown');
                            if (dd && !dd.classList.contains('hidden')) {
                                notifLoaded = false;
                                loadNotifications();
                            }
                        }
                    } else {
                        if (badge) badge.remove();
                    }
                    _lastNotifCount = count;
                })
                .catch(() => {}); // silently ignore network errors
        }

        // Poll every 30 seconds
        setInterval(pollNotifCount, 30000);
    <?php endif; ?>

    function updateWishlistBadge(count) {
        const badge = document.getElementById('wishlistBadge');
        if (!badge) return;
        badge.textContent = count;
        badge.classList.toggle('hidden', count === 0);
    }

    function timeAgo(dateStr) {
        const now = new Date();
        const then = new Date(dateStr);
        const secs = Math.floor((now - then) / 1000);
        if (secs < 60) return 'Just now';
        if (secs < 3600) return Math.floor(secs / 60) + 'm ago';
        if (secs < 86400) return Math.floor(secs / 3600) + 'h ago';
        return Math.floor(secs / 86400) + 'd ago';
    }

    function escHtml(str) {
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    /* ── Close dropdowns on outside click ── */
    document.addEventListener('click', function(e) {
        // Profile
        const profileDd = document.getElementById('profileDropdown');
        if (profileDd && !profileDd.contains(e.target)) {
            document.getElementById('profileMenu')?.classList.add('hidden');
        }
        // Notifications
        const notifWrapper = document.getElementById('notifWrapper');
        if (notifWrapper && !notifWrapper.contains(e.target)) {
            document.getElementById('notifDropdown')?.classList.add('hidden');
        }
    });
</script>