<?php
// includes/admin_header.php — Admin layout partial
if (session_status() === PHP_SESSION_NONE)
    session_start();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

require_once __DIR__ . '/lang.php';
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }

        .sidebar-link-active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 20px;
            background-color: #f43f5e;
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

        /* Language dropdown */
        .lang-dropdown-wrap {
            position: relative;
        }

        .lang-globe-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 34px;
            height: 34px;
            border-radius: 8px;
            border: 1px solid rgba(244, 63, 94, .18);
            background: rgba(255, 255, 255, .7);
            color: #78716c;
            cursor: pointer;
            transition: background .2s, border-color .2s, color .2s;
        }

        .lang-globe-btn:hover {
            background: rgba(255, 255, 255, .95);
            border-color: rgba(244, 63, 94, .4);
            color: #e11d48;
        }

        .lang-menu {
            display: none;
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            min-width: 120px;
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
            gap: 8px;
            width: 100%;
            padding: 9px 14px;
            font-size: 13px;
            font-weight: 500;
            color: #57534e;
            background: none;
            border: none;
            cursor: pointer;
            text-align: left;
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
</head>

<body class="bg-stone-100 min-h-screen flex">

    <?php require_once __DIR__ . '/sidebar.php'; ?>

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
                <!-- Language Selector -->
                <form method="POST" action="" id="adminLangForm" style="display:none">
                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                    <input type="hidden" name="set_lang" id="adminLangInput" value="<?= htmlspecialchars(currentLang()) ?>">
                </form>

                <div class="lang-dropdown-wrap" id="adminLangDropdownWrap">
                    <button type="button" class="lang-globe-btn" id="adminLangGlobeBtn"
                        onclick="toggleAdminLangMenu()" aria-haspopup="true" aria-expanded="false"
                        title="Select language">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7"
                                d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                        </svg>
                    </button>

                    <div class="lang-menu" id="adminLangMenu" role="menu">
                        <button type="button" class="lang-menu-item <?= currentLang() === 'en' ? 'active' : '' ?>"
                            onclick="setAdminLang('en')" role="menuitem">
                            <span>🌐</span> ENG
                        </button>
                        <button type="button" class="lang-menu-item <?= currentLang() === 'my' ? 'active' : '' ?>"
                            onclick="setAdminLang('my')" role="menuitem">
                            <span>🌐</span> မြန်မာ
                        </button>
                    </div>
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
                            <h4 class="font-bold text-stone-800 text-sm"><?= __('admin_notifications') ?></h4>
                            <button onclick="markAllSeen()"
                                class="text-xs text-rose-500 hover:text-rose-600 font-semibold"><?= __('admin_mark_all_read') ?></button>
                        </div>
                        <div id="notifList" class="max-h-80 overflow-y-auto">
                            <p class="text-center text-stone-400 text-sm py-6"><?= __('admin_loading') ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page Content Slot -->
        <main class="flex-1 p-6">