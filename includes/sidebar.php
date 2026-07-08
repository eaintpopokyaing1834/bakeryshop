<?php
// includes/sidebar.php — Dynamic sidebar based on user role
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$role = $_SESSION['role'] ?? '';

$baseClass = "flex items-center gap-3 px-4 py-2.5 rounded-lg text-black hover:text-white hover:bg-slate-800/50 transition-all duration-200 text-sm font-medium relative";
$activeClass = "bg-white/60 text-rose-400 font-semibold sidebar-link-active";
?>

<!-- Sidebar Overlay (mobile) -->
<div id="sidebarOverlay" class="fixed inset-0 bg-black/50 z-30 hidden lg:hidden" onclick="toggleSidebar()"></div>

<!-- Sidebar -->
<aside id="sidebar"
    class="w-64 min-h-screen bg-pink-300 flex flex-col fixed top-0 left-0 z-40 transition-transform duration-300 -translate-x-full lg:translate-x-0">

    <!-- Brand -->
    <div class="p-5 border-b border-slate-800">
        <a href="/sweetheaven/admin/dashboard.php" class="flex items-center gap-3 flex-nowrap">
            <div class="w-9 h-9 bg-rose-500/20 rounded-lg flex items-center justify-center shrink-0">
                <img src="/sweetheaven/images/shoplogo.png" class="h-6 w-auto" alt="Logo">
            </div>
            <div class="min-w-0">
                <p class="text-pink-700 font-bold text-lg leading-tight whitespace-nowrap">Sweet Heaven</p>
                <p class="text-slate-500 text-sm whitespace-nowrap">
                    <?= $role === 'cashier' ? 'Cashier Panel' : 'Admin Panel' ?></p>
            </div>
        </a>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 p-4 space-y-1 overflow-y-auto">
        <p class="text-slate-600 text-[11px] font-semibold uppercase tracking-widest px-4 mb-3">Main</p>

        <!-- Dashboard — both roles -->
        <a href="/sweetheaven/admin/dashboard.php"
            class="<?= $baseClass ?> <?= $currentPage === 'dashboard' ? $activeClass : '' ?>">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
            <span>Dashboard</span>
        </a>

        <!-- Orders — both roles -->
        <a href="/sweetheaven/admin/order.php"
            class="<?= $baseClass ?> <?= $currentPage === 'order' ? $activeClass : '' ?>">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
            </svg>
            <span>Orders</span>
            <?php if ($newOrdersCount > 0): ?>
                <span
                    class="ml-auto bg-red-500 text-white text-[10px] font-bold rounded-full w-5 h-5 flex items-center justify-center shrink-0 shadow-lg shadow-red-500/30">
                    <?= $newOrdersCount > 99 ? '99+' : $newOrdersCount ?>
                </span>
            <?php endif; ?>
        </a>

        <!-- Products — both roles -->
        <a href="/sweetheaven/admin/product.php"
            class="<?= $baseClass ?> <?= $currentPage === 'product' ? $activeClass : '' ?>">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
            <span>Products</span>
        </a>
        <!-- Customize — both roles -->
        <a href="/sweetheaven/admin/customize.php"
            class="<?= $baseClass ?> <?= $currentPage === 'customize' ? $activeClass : '' ?>">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            <span>Customize</span>
            <?php
            $pendingCustomizeCount = (int) $db->query("SELECT COUNT(*) FROM customize_requests WHERE status='pending'")->fetchColumn();
            if ($pendingCustomizeCount > 0): ?>
                <span
                    class="ml-auto bg-red-500 text-white text-[10px] font-bold rounded-full w-5 h-5 flex items-center justify-center shrink-0 shadow-lg shadow-red-500/30">
                    <?= $pendingCustomizeCount > 99 ? '99+' : $pendingCustomizeCount ?>
                </span>
            <?php endif; ?>
        </a>

        <!-- Reviews — both roles -->
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
    </nav>

    <!-- View Store — both roles -->
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

    <?php if ($role === 'admin'): ?>
        <!-- Users — admin only -->
        <a href="/sweetheaven/admin/user.php" class="<?= $baseClass ?> <?= $currentPage === 'user' ? $activeClass : '' ?>">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
            <span>Users</span>
        </a>

        <!-- Cashiers — admin only -->
        <a href="/sweetheaven/admin/manage_cashiers.php"
            class="<?= $baseClass ?> <?= $currentPage === 'manage_cashiers' ? $activeClass : '' ?>">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <span>Cashiers</span>
        </a>

        <!-- Discounts — admin only -->
        <a href="/sweetheaven/admin/discount.php"
            class="<?= $baseClass ?> <?= $currentPage === 'discount' ? $activeClass : '' ?>">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z" />
            </svg>
            <span>Discounts</span>
        </a>

        <!-- Payments — admin only -->
        <a href="/sweetheaven/admin/payment_settings.php"
            class="<?= $baseClass ?> <?= $currentPage === 'payment_settings' ? $activeClass : '' ?>">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
            </svg>
            <span>Payments</span>
        </a>

        <!-- Settings — admin only -->
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
    <?php endif; ?>

    

    <!-- User Info -->
    <div class="p-4 border-t border-slate-800">
        <a href="/sweetheaven/admin/profile_edit.php"
            class="text-slate-500 hover:text-rose-400 transition-colors shrink-0" title="Edit Profile">
            <div class="flex items-center gap-3 bg-rose-400/30 rounded-xl p-3">
                <div
                    class="w-9 h-9 bg-pink-500 rounded-full flex items-center justify-center text-rose-800 font-bold text-sm shrink-0">
                    <?= strtoupper(substr($_SESSION['name'] ?? 'A', 0, 1)) ?>
                </div>o
                <div class="flex-1 min-w-0">
                    <p class="text-pink-700 text-lg font-semibold truncate">
                        <?= htmlspecialchars($_SESSION['name'] ?? 'User') ?>
                    </p>
                    <p class="text-slate-500 text-sm"><?= $role === 'cashier' ? 'Cashier' : 'Administrator' ?></p>
                </div>
                <img src="../images/log.png" class="w-6 h-6">
            </div>
        </a>
        <!-- <a href="/sweetheaven/auth/logout.php"
            class="mt-2 flex items-center justify-center gap-2 px-4 py-2 rounded-lg text-slate-500 hover:text-white hover:bg-slate-800/50 transition-all duration-200 text-sm font-medium">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
            <span>Log Out</span>
        </a> -->
    </div>
</aside>