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

        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #475569; border-radius: 3px; }

        /* ── Entry animations ───────────────────────────── */
        @keyframes fadeSlideUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }

        /* ── Page background ────────────────────────────── */
        .admin-content-wrapper {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 50%, #f8fafc 100%);
        }

        /* ── Topbar ─────────────────────────────────────── */
        .admin-topbar {
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border-bottom: 1px solid rgba(0,0,0,0.05);
            box-shadow: 0 1px 3px rgba(0,0,0,0.03), 0 4px 16px rgba(0,0,0,0.02);
        }

        /* ── Generic card ───────────────────────────────── */
        .admin-card {
            background: #fff;
            border-radius: 20px;
            border: 1px solid rgba(0,0,0,0.04);
            box-shadow: 0 1px 4px rgba(0,0,0,0.04), 0 2px 8px rgba(0,0,0,0.02);
            transition: box-shadow 0.28s ease, transform 0.28s ease;
        }
        .admin-card:hover {
            box-shadow: 0 6px 24px rgba(0,0,0,0.07), 0 2px 6px rgba(0,0,0,0.03);
            transform: translateY(-2px);
        }
        .admin-card-static {
            background: #fff;
            border-radius: 20px;
            border: 1px solid rgba(0,0,0,0.04);
            box-shadow: 0 1px 4px rgba(0,0,0,0.04), 0 2px 8px rgba(0,0,0,0.02);
        }

        /* ── Metric cards ───────────────────────────────── */
        .metric-card {
            background: #fff;
            border-radius: 20px;
            border: 1px solid rgba(0,0,0,0.04);
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
            padding: 24px;
            transition: all 0.32s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            animation: fadeSlideUp 0.45s ease both;
        }
        .metric-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            border-radius: 20px 20px 0 0;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .metric-card:hover {
            box-shadow: 0 12px 32px rgba(0,0,0,0.09), 0 4px 12px rgba(0,0,0,0.04);
            transform: translateY(-3px);
        }
        .metric-card:hover::before { opacity: 1; }

        .metric-card:nth-child(1) { animation-delay: 0.05s; }
        .metric-card:nth-child(2) { animation-delay: 0.10s; }
        .metric-card:nth-child(3) { animation-delay: 0.15s; }
        .metric-card:nth-child(4) { animation-delay: 0.20s; }

        .metric-card.rose::before   { background: linear-gradient(90deg,#f43f5e,#fb7185); }
        .metric-card.blue::before   { background: linear-gradient(90deg,#3b82f6,#60a5fa); }
        .metric-card.purple::before { background: linear-gradient(90deg,#8b5cf6,#a78bfa); }
        .metric-card.green::before  { background: linear-gradient(90deg,#22c55e,#4ade80); }
        .metric-card.amber::before  { background: linear-gradient(90deg,#f59e0b,#fbbf24); }
        .metric-card.red::before    { background: linear-gradient(90deg,#ef4444,#f87171); }
        .metric-card.indigo::before { background: linear-gradient(90deg,#6366f1,#818cf8); }
        .metric-card.cyan::before   { background: linear-gradient(90deg,#06b6d4,#22d3ee); }

        /* ── Metric icon ────────────────────────────────── */
        .metric-icon {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .metric-card:hover .metric-icon { transform: scale(1.1) rotate(-4deg); }

        .metric-icon.rose   { background: linear-gradient(135deg,#fff1f2,#ffe4e6); box-shadow: 0 4px 12px rgba(244,63,94,0.15); }
        .metric-icon.blue   { background: linear-gradient(135deg,#eff6ff,#dbeafe); box-shadow: 0 4px 12px rgba(59,130,246,0.15); }
        .metric-icon.purple { background: linear-gradient(135deg,#f5f3ff,#ede9fe); box-shadow: 0 4px 12px rgba(139,92,246,0.15); }
        .metric-icon.green  { background: linear-gradient(135deg,#f0fdf4,#dcfce7); box-shadow: 0 4px 12px rgba(34,197,94,0.15); }
        .metric-icon.amber  { background: linear-gradient(135deg,#fffbeb,#fef3c7); box-shadow: 0 4px 12px rgba(245,158,11,0.15); }
        .metric-icon.red    { background: linear-gradient(135deg,#fff5f5,#fee2e2); box-shadow: 0 4px 12px rgba(239,68,68,0.15); }
        .metric-icon.indigo { background: linear-gradient(135deg,#eef2ff,#e0e7ff); box-shadow: 0 4px 12px rgba(99,102,241,0.15); }
        .metric-icon.cyan   { background: linear-gradient(135deg,#ecfeff,#cffafe); box-shadow: 0 4px 12px rgba(6,182,212,0.15); }

        /* ── Chart cards ────────────────────────────────── */
        .chart-card {
            background: #fff;
            border-radius: 20px;
            border: 1px solid rgba(0,0,0,0.04);
            box-shadow: 0 1px 4px rgba(0,0,0,0.04), 0 2px 8px rgba(0,0,0,0.02);
            overflow: hidden;
            animation: fadeSlideUp 0.5s ease both;
        }
        .chart-card-header {
            padding: 20px 24px 16px;
            border-bottom: 1px solid rgba(0,0,0,0.04);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .chart-card-header h3 { font-size: 15px; font-weight: 700; color: #111827; letter-spacing: -0.01em; }
        .chart-card-header p  { font-size: 12px; color: #94a3b8; margin-top: 2px; }
        .chart-card-body { padding: 20px 24px; }

        /* ── Premium table ──────────────────────────────── */
        .admin-table { width: 100%; border-collapse: separate; border-spacing: 0; }
        .admin-table thead { background: linear-gradient(180deg,#f8fafc 0%,#f1f5f9 100%); }
        .admin-table thead th {
            padding: 13px 20px;
            text-align: left;
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: #64748b;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            white-space: nowrap;
        }
        .admin-table thead th:first-child { padding-left: 24px; }
        .admin-table thead th:last-child  { padding-right: 24px; }
        .admin-table tbody tr { transition: background-color 0.14s ease; }
        .admin-table tbody tr:hover { background-color: #fafbff; }
        .admin-table tbody td {
            padding: 14px 20px;
            border-bottom: 1px solid rgba(0,0,0,0.032);
            vertical-align: middle;
        }
        .admin-table tbody td:first-child { padding-left: 24px; }
        .admin-table tbody td:last-child  { padding-right: 24px; }
        .admin-table tbody tr:last-child td { border-bottom: none; }

        /* ── User avatar ────────────────────────────────── */
        .user-avatar {
            width: 34px; height: 34px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px; font-weight: 700; flex-shrink: 0;
            background: linear-gradient(135deg,#fff1f2,#ffe4e6);
            color: #f43f5e;
            box-shadow: 0 1px 4px rgba(244,63,94,0.15);
        }

        /* ── Status badges ──────────────────────────────── */
        .status-badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 10px 4px 8px;
            border-radius: 9999px;
            font-size: 11.5px; font-weight: 600; letter-spacing: 0.01em;
            white-space: nowrap;
        }
        .status-badge::before {
            content: ''; width: 6px; height: 6px;
            border-radius: 50%; flex-shrink: 0;
        }
        .badge-pending    { background:#fffbeb; color:#92400e; }
        .badge-pending::before    { background:#f59e0b; }
        .badge-processing { background:#eff6ff; color:#1e40af; }
        .badge-processing::before { background:#3b82f6; }
        .badge-shipped    { background:#eef2ff; color:#3730a3; }
        .badge-shipped::before    { background:#6366f1; }
        .badge-delivered  { background:#f0fdf4; color:#166534; }
        .badge-delivered::before  { background:#22c55e; }
        .badge-cancelled  { background:#fff5f5; color:#991b1b; }
        .badge-cancelled::before  { background:#ef4444; }
        .badge-approved   { background:#f0fdf4; color:#166534; }
        .badge-approved::before   { background:#22c55e; }
        .badge-rejected   { background:#fff5f5; color:#991b1b; }
        .badge-rejected::before   { background:#ef4444; }
        .badge-active     { background:#f0fdf4; color:#166534; }
        .badge-active::before     { background:#22c55e; }
        .badge-inactive   { background:#f8fafc; color:#64748b; }
        .badge-inactive::before   { background:#94a3b8; }

        /* ── Section card ───────────────────────────────── */
        .section-card {
            background: #fff; border-radius: 20px;
            border: 1px solid rgba(0,0,0,0.04);
            box-shadow: 0 1px 4px rgba(0,0,0,0.04), 0 2px 8px rgba(0,0,0,0.02);
            overflow: hidden;
            animation: fadeSlideUp 0.5s ease both;
        }
        .section-card-header {
            padding: 18px 24px;
            border-bottom: 1px solid rgba(0,0,0,0.04);
            display: flex; align-items: center; justify-content: space-between;
        }
        .section-card-header h3 { font-size: 15px; font-weight: 700; color: #111827; letter-spacing: -0.01em; }
        .section-card-header .sub { font-size: 12px; color: #94a3b8; margin-top: 1px; }
        .section-card-link {
            font-size: 12.5px; font-weight: 600; color: #f43f5e;
            text-decoration: none; padding: 5px 12px;
            border-radius: 8px; background: #fff1f2;
            transition: background 0.2s, color 0.2s;
        }
        .section-card-link:hover { background: #ffe4e6; color: #e11d48; }

        /* ── Section header ─────────────────────────────── */
        .section-header {
            padding: 18px 24px;
            border-bottom: 1px solid rgba(0,0,0,0.04);
            display: flex; align-items: center; justify-content: space-between;
        }

        /* ── Pagination ─────────────────────────────────── */
        .pagination-wrap {
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 24px; border-top: 1px solid rgba(0,0,0,0.04);
        }
        .pagination-info { font-size: 12.5px; color: #94a3b8; font-weight: 500; }
        .pagination-pills { display: flex; align-items: center; gap: 4px; }
        .pg-btn {
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 34px; height: 34px; padding: 0 10px;
            border-radius: 10px; font-size: 13px; font-weight: 500;
            text-decoration: none; transition: all 0.2s ease; border: 1px solid transparent;
        }
        .pg-btn-default { background: #f1f5f9; color: #64748b; border-color: rgba(0,0,0,0.04); }
        .pg-btn-default:hover { background: #e2e8f0; color: #334155; transform: translateY(-1px); }
        .pg-btn-active  {
            background: linear-gradient(135deg,#f43f5e,#e11d48); color: #fff;
            box-shadow: 0 3px 10px rgba(244,63,94,0.3); border-color: transparent;
        }
        .pg-btn-nav { background: #fff; color: #64748b; border-color: rgba(0,0,0,0.07); }
        .pg-btn-nav:hover { background: #f8fafc; color: #334155; border-color: rgba(0,0,0,0.1); transform: translateY(-1px); }

        /* ── Filter pills ───────────────────────────────── */
        .filter-pill {
            padding: 7px 16px; border-radius: 9999px;
            font-size: 12.5px; font-weight: 500;
            text-decoration: none; transition: all 0.2s ease;
            display: inline-flex; align-items: center; gap: 5px;
        }
        .filter-pill-default { background: #f1f5f9; color: #64748b; }
        .filter-pill-default:hover { background: #e2e8f0; color: #334155; transform: translateY(-1px); }
        .filter-pill-active {
            background: linear-gradient(135deg,#f43f5e,#e11d48); color: #fff;
            box-shadow: 0 3px 10px rgba(244,63,94,0.28);
        }

        /* ── Action buttons ─────────────────────────────── */
        .btn-primary {
            background: linear-gradient(135deg,#f43f5e 0%,#e11d48 100%); color: #fff;
            padding: 10px 20px; border-radius: 12px; font-size: 13px; font-weight: 600;
            display: inline-flex; align-items: center; gap: 8px;
            transition: all 0.2s ease; border: none; cursor: pointer; text-decoration: none; white-space: nowrap;
        }
        .btn-primary:hover { box-shadow: 0 4px 14px rgba(244,63,94,0.38); transform: translateY(-1px); }
        .btn-secondary {
            background: #f1f5f9; color: #475569; padding: 10px 20px;
            border-radius: 12px; font-size: 13px; font-weight: 600;
            transition: all 0.2s ease; border: none; cursor: pointer; text-decoration: none;
        }
        .btn-secondary:hover { background: #e2e8f0; transform: translateY(-1px); }
        .btn-edit {
            color: #2563eb; background: #eff6ff; padding: 6px 13px;
            border-radius: 8px; font-size: 12px; font-weight: 600;
            transition: all 0.2s ease; border: none; cursor: pointer; text-decoration: none;
            display: inline-flex; align-items: center; gap: 4px;
        }
        .btn-edit:hover { background: #dbeafe; color: #1d4ed8; transform: translateY(-1px); }
        .btn-delete {
            color: #dc2626; background: #fef2f2; padding: 6px 13px;
            border-radius: 8px; font-size: 12px; font-weight: 600;
            transition: all 0.2s ease; border: none; cursor: pointer; text-decoration: none;
            display: inline-flex; align-items: center; gap: 4px;
        }
        .btn-delete:hover { background: #fee2e2; color: #b91c1c; transform: translateY(-1px); }
        .btn-approve {
            color: #15803d; background: #f0fdf4; padding: 6px 13px;
            border-radius: 8px; font-size: 12px; font-weight: 600;
            transition: all 0.2s ease; border: none; cursor: pointer; text-decoration: none;
            display: inline-flex; align-items: center; gap: 4px;
        }
        .btn-approve:hover { background: #dcfce7; transform: translateY(-1px); }
        .btn-reject {
            color: #dc2626; background: #fef2f2; padding: 6px 13px;
            border-radius: 8px; font-size: 12px; font-weight: 600;
            transition: all 0.2s ease; border: none; cursor: pointer; text-decoration: none;
            display: inline-flex; align-items: center; gap: 4px;
        }
        .btn-reject:hover { background: #fee2e2; transform: translateY(-1px); }

        /* ── Flash messages ─────────────────────────────── */
        .flash-success {
            background: linear-gradient(135deg,#f0fdf4,#ecfdf5);
            border: 1px solid #bbf7d0; color: #166534;
            padding: 14px 18px; border-radius: 14px; font-size: 13px; font-weight: 500;
            display: flex; align-items: center; gap: 10px;
            box-shadow: 0 1px 3px rgba(22,163,74,0.06);
            animation: fadeIn 0.3s ease;
        }
        .flash-info {
            background: linear-gradient(135deg,#eff6ff,#dbeafe);
            border: 1px solid #bfdbfe; color: #1e40af;
            padding: 14px 18px; border-radius: 14px; font-size: 13px; font-weight: 500;
            display: flex; align-items: center; gap: 10px; animation: fadeIn 0.3s ease;
        }

        /* ── Empty state ────────────────────────────────── */
        .empty-state { padding: 56px 24px; text-align: center; }
        .empty-state-icon { font-size: 52px; margin-bottom: 14px; opacity: 0.65; display: block; }
        .empty-state-text { font-size: 14px; color: #94a3b8; font-weight: 500; }

        /* ── Language dropdown ──────────────────────────── */
        .lang-dropdown-wrap { position: relative; }
        .lang-globe-btn {
            display: flex; align-items: center; justify-content: center;
            width: 36px; height: 36px; border-radius: 10px;
            border: 1px solid rgba(0,0,0,0.06); background: rgba(255,255,255,0.8);
            color: #64748b; cursor: pointer; transition: all 0.2s ease;
        }
        .lang-globe-btn:hover {
            background: #fff; border-color: rgba(244,63,94,0.3);
            color: #e11d48; box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .lang-menu {
            display: none; position: absolute; top: calc(100% + 8px); right: 0;
            min-width: 130px; background: #fff;
            border: 1px solid rgba(0,0,0,0.06); border-radius: 14px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08), 0 4px 8px rgba(0,0,0,0.04);
            overflow: hidden; z-index: 200;
        }
        .lang-menu.open { display: block; }
        .lang-menu-item {
            display: flex; align-items: center; gap: 8px; width: 100%; padding: 10px 16px;
            font-size: 13px; font-weight: 500; color: #475569;
            background: none; border: none; cursor: pointer; text-align: left;
            transition: all 0.15s ease; font-family: inherit;
        }
        .lang-menu-item:hover { background: #fff1f2; color: #e11d48; }
        .lang-menu-item.active { color: #e11d48; font-weight: 700; }
        .lang-menu-item+.lang-menu-item { border-top: 1px solid rgba(0,0,0,0.03); }

        /* ── Notification bell ──────────────────────────── */
        .notif-bell {
            position: relative; padding: 8px; border-radius: 10px;
            color: #64748b; transition: all 0.2s ease;
        }
        .notif-bell:hover { background: #f1f5f9; color: #475569; }
        .notif-dropdown {
            position: absolute; right: 0; margin-top: 8px; width: 340px;
            background: #fff; border-radius: 16px;
            border: 1px solid rgba(0,0,0,0.05);
            box-shadow: 0 12px 40px rgba(0,0,0,0.1), 0 4px 12px rgba(0,0,0,0.04);
            z-index: 50; overflow: hidden;
        }

        /* ── Modal ──────────────────────────────────────── */
        .modal-overlay { background: rgba(15,23,42,0.5); backdrop-filter: blur(4px); }
        .modal-content {
            background: #fff; border-radius: 22px;
            box-shadow: 0 24px 48px rgba(0,0,0,0.12), 0 12px 24px rgba(0,0,0,0.06);
        }

        /* ── Toast ──────────────────────────────────────── */
        .toast {
            position: fixed; bottom: 24px; right: 24px;
            background: linear-gradient(135deg,#1e293b,#0f172a); color: #fff;
            padding: 14px 24px; border-radius: 14px;
            font-size: 13px; font-weight: 500;
            box-shadow: 0 8px 24px rgba(0,0,0,0.2); z-index: 100;
            animation: fadeSlideUp 0.3s ease;
        }

        /* ── Stock progress bar ─────────────────────────── */
        .stock-bar-track {
            height: 5px; background: #f1f5f9; border-radius: 99px; overflow: hidden; margin-top: 4px;
        }
        .stock-bar-fill { height: 100%; border-radius: 99px; transition: width 0.6s cubic-bezier(0.4,0,0.2,1); }

        /* ── Rank badges ────────────────────────────────── */
        .rank-badge {
            width: 30px; height: 30px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 700; flex-shrink: 0;
        }
        .rank-badge.gold   { background: linear-gradient(135deg,#fef3c7,#fde68a); color: #92400e; }
        .rank-badge.silver { background: linear-gradient(135deg,#f1f5f9,#e2e8f0); color: #475569; }
        .rank-badge.bronze { background: linear-gradient(135deg,#fef3c7,#fcd9a8); color: #78350f; }
        .rank-badge.other  { background: #f1f5f9; color: #64748b; }

        /* ── Search input ───────────────────────────────── */
        .search-input {
            border: 1.5px solid #e2e8f0; border-radius: 12px;
            padding: 9px 14px 9px 38px; font-size: 13px;
            color: #334155; background: #f8fafc; outline: none;
            transition: all 0.2s ease; width: 220px;
        }
        .search-input:focus {
            border-color: #f43f5e; background: #fff;
            box-shadow: 0 0 0 3px rgba(244,63,94,0.08); width: 260px;
        }
        .search-input::placeholder { color: #94a3b8; }

        @media (prefers-reduced-motion: no-preference) {
            .metric-card { transition: all 0.32s cubic-bezier(0.4,0,0.2,1); }
            .metric-card:hover { transform: translateY(-3px); }
        }
    </style>
</head>
<body class="admin-content-wrapper min-h-screen flex">

    <?php require_once __DIR__ . '/sidebar.php'; ?>

    <!-- Main Content Wrapper -->
    <div class="flex-1 lg:ml-64 flex flex-col min-h-screen">

        <!-- Top Bar -->
        <header class="admin-topbar px-8 py-4 flex items-center justify-between sticky top-0 z-30">
            <div class="flex items-center gap-4">
                <button onclick="toggleSidebar()" class="text-gray-400 hover:text-gray-600 lg:hidden p-2 rounded-xl hover:bg-gray-100 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <div>
                    <h1 class="text-lg font-bold text-gray-900">
                        <?= $pageTitle ?? 'Dashboard' ?>
                    </h1>
                    <p class="text-xs text-gray-400 font-medium">
                        <?= date('l, F j, Y') ?>
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
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
                    <button onclick="toggleNotifDropdown()" class="notif-bell relative">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        <?php if ($totalNotifications > 0): ?>
                            <span id="notifBadge"
                                class="absolute -top-0.5 -right-0.5 bg-rose-500 text-white text-[10px] font-bold rounded-full min-w-[20px] h-5 flex items-center justify-center px-1">
                                <?= $totalNotifications > 99 ? '99+' : $totalNotifications ?>
                            </span>
                        <?php endif; ?>
                    </button>

                    <!-- Notification Dropdown -->
                    <div id="notifDropdown"
                        class="hidden notif-dropdown">
                        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                            <h4 class="font-bold text-gray-800 text-sm"><?= __('admin_notifications') ?></h4>
                            <button onclick="markAllSeen()"
                                class="text-xs text-rose-500 hover:text-rose-600 font-semibold transition-colors"><?= __('admin_mark_all_read') ?></button>
                        </div>
                        <div id="notifList" class="max-h-80 overflow-y-auto">
                            <p class="text-center text-gray-400 text-sm py-8"><?= __('admin_loading') ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page Content Slot -->
        <main class="flex-1 p-6">
