<?php
// includes/admin_header.php — Admin layout partial
if (session_status() === PHP_SESSION_NONE)
    session_start();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

require_once __DIR__ . '/../config/db.php';
$db = getDB();
$newOrdersCount = (int) $db->query("SELECT COUNT(*) FROM notifications WHERE (type='new_order' OR type='customize_request') AND is_seen=0")->fetchColumn();
$totalNotifications = (int) $db->query("SELECT COUNT(*) FROM notifications WHERE (type='new_order' OR type='customize_request') AND is_seen=0")->fetchColumn();
$pendingReviewsCount = (int) $db->query("SELECT COUNT(*) FROM customer_reviews WHERE status='pending'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= $pageTitle ?? 'Admin' ?> — Sweet Heaven Admin
    </title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght=300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }

        /* Pure CSS fallback for the active link indicator line */
        .sidebar-link-active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 20px;
            background-color: #f43f5e;
            /* Tailwind rose-400 */
            border-top-right-radius: 9999px;
            border-bottom-right-radius: 9999px;
        }

        ::-webkit-scrollbar {
            width: 5px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: #475569;
            border-radius: 3px;
        }
    </style>
</head>

<body class="bg-stone-100 min-h-screen flex">

    <!-- Sidebar Overlay (mobile) -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-black/50 z-30 hidden lg:hidden" onclick="toggleSidebar()"></div>

    <!-- Sidebar -->
    <aside id="sidebar"
        class="w-64 min-h-screen bg-pink-400 flex flex-col fixed top-0 left-0 z-40 transition-transform duration-300 -translate-x-full lg:translate-x-0">

        <!-- Brand -->
        <div class="p-5 border-b border-slate-800">
            <a href="/sweetheaven/admin/dashboard.php" class="flex items-center gap-3 flex-nowrap">
                <div class="w-9 h-9 bg-rose-500/20 rounded-lg flex items-center justify-center shrink-0">
                    <!-- Removed brightness-0 invert so your real logo colors display -->
                    <img src="/sweetheaven/images/shoplogo.png" class="h-6 w-auto" alt="Logo">
                </div>
                <div class="min-w-0">
                    <p class="text-white font-bold text-lg leading-tight whitespace-nowrap">Sweet Heaven</p>
                    <p class="text-black text-md whitespace-nowrap">Admin Panel</p>
                </div>
            </a>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 p-4 space-y-1 overflow-y-auto">
            <p class="text-slate-600 text-[11px] font-semibold uppercase tracking-widest px-4 mb-3">Main</p>

            <?php
            // Core Tailwind layout setups
            $baseClass = "flex items-center gap-3 px-4 py-2.5 rounded-lg text-black hover:text-white hover:bg-slate-800/50 transition-all duration-200 text-sm font-medium relative";
            $activeClass = "bg-slate-500 text-rose-400 font-semibold sidebar-link-active";
            ?>

            <a href="/sweetheaven/admin/dashboard.php"
                class="<?= $baseClass ?> <?= $currentPage === 'dashboard' ? $activeClass : '' ?>">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                <span>Dashboard</span>
            </a>

            <a href="/sweetheaven/admin/product.php"
                class="<?= $baseClass ?> <?= $currentPage === 'product' ? $activeClass : '' ?>">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
                <span>Products</span>
            </a>

            <a href="/sweetheaven/admin/order.php"
                class="<?= $baseClass ?> <?= $currentPage === 'order' ? $activeClass : '' ?>">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                </svg>
                <span>Orders</span>
                <?php if ($newOrdersCount > 0): ?>
                    <!-- Changed positioning to ml-auto and fixed dimensioning for a perfect inline circle layout -->
                    <span
                        class="ml-auto bg-red-500 text-white text-[10px] font-bold rounded-full w-5 h-5 flex items-center justify-center shrink-0 shadow-lg shadow-red-500/30">
                        <?= $newOrdersCount > 99 ? '99+' : $newOrdersCount ?>
                    </span>
                <?php endif; ?>
            </a>

            <a href="/sweetheaven/admin/user.php"
                class="<?= $baseClass ?> <?= $currentPage === 'user' ? $activeClass : '' ?>">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <span>Users</span>
            </a>


            <a href="/sweetheaven/admin/customize.php"
                class="<?= $baseClass ?> <?= $currentPage === 'customize' ? $activeClass : '' ?>">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                <span>Customize</span>
                <?php
                $pendingCustomizeCount = (int)$db->query("SELECT COUNT(*) FROM customize_requests WHERE status='pending'")->fetchColumn();
                if ($pendingCustomizeCount > 0): ?>
                    <span
                        class="ml-auto bg-red-500 text-white text-[10px] font-bold rounded-full w-5 h-5 flex items-center justify-center shrink-0 shadow-lg shadow-red-500/30">
                        <?= $pendingCustomizeCount > 99 ? '99+' : $pendingCustomizeCount ?>
                    </span>
                <?php endif; ?>
            </a>

            <a href="/sweetheaven/admin/review.php"
                class="<?= $baseClass ?> <?= $currentPage === 'review' ? $activeClass : '' ?>">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
                <span>Reviews</span>
                <?php if ($pendingReviewsCount > 0): ?>
                    <span
                        class="ml-auto bg-red-500 text-white text-[10px] font-bold rounded-full w-5 h-5 flex items-center justify-center shrink-0 shadow-lg shadow-red-500/30">
                        <?= $pendingReviewsCount > 99 ? '99+' : $pendingReviewsCount ?>
                    </span>
                <?php endif; ?>
            </a>

            <a href="/sweetheaven/admin/discount.php"
                class="<?= $baseClass ?> <?= $currentPage === 'discount' ? $activeClass : '' ?>">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z" />
                </svg>
                <span>Discounts</span>
            </a>

            <a href="/sweetheaven/admin/payment_settings.php"
                class="<?= $baseClass ?> <?= $currentPage === 'payment_settings' ? $activeClass : '' ?>">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                </svg>
                <span>Payments</span>
            </a>

            <a href="/sweetheaven/admin/settings.php"
                class="<?= $baseClass ?> <?= $currentPage === 'settings' ? $activeClass : '' ?>">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span>Settings</span>
            </a>

            <div class="pt-5 mt-4 border-t border-slate-800">
                <p class="text-stone-500 text-[11px] font-semibold uppercase tracking-widest px-4 mb-3">Store</p>
                <a href="/sweetheaven/user/index.php" target="_blank" class="<?= $baseClass ?>">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <span>View Store</span>
                </a>
            </div>
        </nav>

        <!-- User Info -->
        <div class="p-4 border-t border-slate-800">
            <div class="flex items-center gap-3 bg-slate-800/50 rounded-xl p-3">
                <div
                    class="w-9 h-9 bg-rose-500/20 rounded-full flex items-center justify-center text-rose-400 font-bold text-sm shrink-0">
                    <?= strtoupper(substr($_SESSION['name'] ?? 'A', 0, 1)) ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-white text-lg font-semibold truncate">
                        <?= htmlspecialchars($_SESSION['name'] ?? 'Admin') ?>
                    </p>
                    <p class="text-stone-400 text-md">Administrator</p>
                </div>
                <a href="/sweetheaven/auth/logout.php"
                    class="text-slate-500 hover:text-rose-400 transition-colors shrink-0" title="Logout">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content Wrapper -->
    <div class="flex-1 lg:ml-64 flex flex-col min-h-screen">

        <!-- Top Bar -->
        <header
            class="bg-white/80 backdrop-blur-sm border-b border-stone-200/60 px-6 py-4 flex items-center justify-between sticky top-0 z-30">
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="text-stone-400 hover:text-stone-600 lg:hidden">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <div>
                    <h1 class="text-xl font-bold text-stone-800">
                        <?= $pageTitle ?? 'Dashboard' ?>
                    </h1>
                    <p class="text-xs text-stone-400">
                        <?= date('l, F j, Y') ?>
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <div
                    class="hidden sm:flex items-center gap-2 bg-emerald-50 rounded-lg px-3 py-1.5 text-xs text-emerald-600 font-medium">
                    <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span>
                    <span>System Online</span>
                </div>

                <!-- Notification Bell -->
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
                            <h4 class="font-bold text-stone-800 text-sm">Notifications</h4>
                            <button onclick="markAllSeen()"
                                class="text-xs text-rose-500 hover:text-rose-600 font-semibold">Mark all read</button>
                        </div>
                        <div id="notifList" class="max-h-80 overflow-y-auto">
                            <p class="text-center text-stone-400 text-sm py-6">Loading...</p>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page Content Slot -->
        <main class="flex-1 p-6"></main>