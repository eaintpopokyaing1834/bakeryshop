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
                <a href="">
                    <div
                        class="w-9 h-9 bg-rose-500/20 rounded-full flex items-center justify-center text-rose-400 font-bold text-sm shrink-0">
                        <?= strtoupper(substr($_SESSION['name'] ?? 'A', 0, 1)) ?>
                    </div>
                </a>

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
        <main class="flex-1 p-6">