<?php
if (!isset($pageTitle)) $pageTitle = 'Rotary Club Admin';

// Session timeout check (30 min)
require_once __DIR__ . '/../../includes/session_security.php';
checkSessionTimeout(30);

// Load website settings for dynamic favicon/title
require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/website_settings.php';
$_ws = getWebsiteSettings($conn);
$_wsTitle = $wsTitle ?? $_ws['browser_title'];
$_wsFavicon = $_ws['website_favicon'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($_wsFavicon) ?>">
    <title><?= htmlspecialchars($pageTitle) ?> | <?= htmlspecialchars($_ws['website_short_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --rotary-blue: #0A2342;
            --rotary-blue-light: #1a365d;
            --rotary-blue-dark: #07162e;
            --rotary-yellow: #FFC000;
            --rotary-yellow-light: #ffd966;
            --rotary-gold: #e6a800;
            --sidebar-width: 260px;
            --header-height: 64px;
            --card-radius: 14px;
            --transition-base: 0.2s ease;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
            --shadow-md: 0 4px 12px rgba(0,0,0,0.07), 0 1px 3px rgba(0,0,0,0.04);
            --shadow-lg: 0 10px 30px rgba(0,0,0,0.08), 0 4px 8px rgba(0,0,0,0.04);
            --shadow-xl: 0 20px 50px rgba(0,0,0,0.1);
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f0f2f5;
            color: #1e293b;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        /* ─── SIDEBAR ─── */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--rotary-blue);
            background-image: linear-gradient(180deg, #0A2342 0%, #0d2b52 60%, #0f3260 100%);
            z-index: 100;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 2px 0 20px rgba(0,0,0,0.15);
            transition: transform var(--transition-base);
        }

        .sidebar-logo {
            padding: 20px 18px 18px;
            display: flex;
            align-items: center;
            gap: 14px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            flex-shrink: 0;
        }

        .sidebar-logo .logo-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            object-fit: cover;
            border: 2px solid rgba(255,192,0,0.25);
            background: rgba(255,255,255,0.05);
            flex-shrink: 0;
        }

        .sidebar-logo-text h1 {
            font-size: 15px;
            font-weight: 800;
            color: white;
            margin: 0;
            line-height: 1.25;
            letter-spacing: -0.01em;
        }

        .sidebar-logo-text p {
            font-size: 10px;
            color: rgba(255,255,255,0.4);
            margin: 0;
            font-weight: 500;
            letter-spacing: 0.02em;
        }

        .sidebar-nav {
            flex: 1;
            overflow-y: auto;
            padding: 14px 10px 10px;
        }

        .sidebar-nav::-webkit-scrollbar { width: 3px; }
        .sidebar-nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 2px; }

        .nav-section {
            margin-bottom: 6px;
        }

        .nav-section-title {
            font-size: 9.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: rgba(255,255,255,0.3);
            padding: 10px 12px 5px;
        }

        .nav-item {
            position: relative;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 9px 12px;
            border-radius: 9px;
            color: rgba(255,255,255,0.55);
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all var(--transition-base);
            cursor: pointer;
            margin-bottom: 1px;
        }

        .nav-item:hover {
            background: rgba(255,255,255,0.07);
            color: rgba(255,255,255,0.9);
        }

        .nav-item.active {
            background: rgba(255,192,0,0.1);
            color: #FFC000;
        }

        .nav-item.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 18px;
            background: #FFC000;
            border-radius: 0 4px 4px 0;
            box-shadow: 0 0 8px rgba(255,192,0,0.4);
        }

        .nav-item svg {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
            stroke-width: 1.8;
        }

        .nav-item .nav-label {
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .sidebar-footer {
            padding: 10px 12px 14px;
            border-top: 1px solid rgba(255,255,255,0.06);
            flex-shrink: 0;
        }

        .sidebar-footer .nav-item {
            color: rgba(255,255,255,0.4);
            font-size: 12px;
            border-radius: 8px;
            padding: 8px 12px;
        }

        .sidebar-footer .nav-item:hover {
            color: rgba(255,255,255,0.8);
            background: rgba(255,255,255,0.06);
        }

        /* ─── TOP HEADER ─── */
        .top-header {
            position: fixed;
            top: 0;
            left: var(--sidebar-width);
            right: 0;
            height: var(--header-height);
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(226,232,240,0.7);
            z-index: 90;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 28px;
        }

        .top-header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .breadcrumb {
            font-size: 13px;
            color: #94a3b8;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .breadcrumb a {
            color: #64748b;
            text-decoration: none;
            transition: color var(--transition-base);
        }

        .breadcrumb a:hover {
            color: var(--rotary-blue);
        }

        .breadcrumb .current {
            color: #0f172a;
            font-weight: 600;
        }

        .top-header-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .admin-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 5px 10px 5px 5px;
            border-radius: 10px;
            cursor: pointer;
            transition: all var(--transition-base);
            border: 1px solid transparent;
        }

        .admin-profile:hover {
            background: #f8fafc;
            border-color: #e2e8f0;
        }

        .admin-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e2e8f0;
            flex-shrink: 0;
        }

        .admin-info-name {
            font-size: 13px;
            font-weight: 600;
            color: #0f172a;
            line-height: 1.3;
        }

        .admin-info-role {
            font-size: 11px;
            color: #94a3b8;
        }

        /* ─── MAIN CONTENT ─── */
        .main-content {
            margin-left: var(--sidebar-width);
            margin-top: var(--header-height);
            padding: 28px 32px;
            min-height: calc(100vh - var(--header-height));
        }

        .page-header {
            margin-bottom: 28px;
        }

        .page-title {
            font-size: 26px;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 4px;
            letter-spacing: -0.02em;
        }

        .page-subtitle {
            font-size: 14px;
            color: #64748b;
            margin: 0;
        }

        /* ─── CARDS ─── */
        .card {
            background: white;
            border-radius: var(--card-radius);
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(241,245,249,0.8);
            transition: box-shadow var(--transition-base), transform var(--transition-base);
        }

        .card:hover {
            box-shadow: var(--shadow-md);
        }

        .card-header {
            padding: 22px 26px 0;
        }

        .card-body {
            padding: 22px 26px;
        }

        .card-footer {
            padding: 16px 26px;
            border-top: 1px solid #f1f5f9;
            background: #fafbfc;
            border-radius: 0 0 var(--card-radius) var(--card-radius);
        }

        /* ─── STAT CARDS ─── */
        .stat-card {
            background: white;
            border-radius: var(--card-radius);
            padding: 22px 24px;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(241,245,249,0.8);
            transition: all 0.25s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-3px);
        }

        .stat-card .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 14px;
        }

        .stat-card .stat-icon svg {
            width: 24px;
            height: 24px;
        }

        .stat-card .stat-value {
            font-size: 28px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.2;
            letter-spacing: -0.03em;
        }

        .stat-card .stat-label {
            font-size: 13px;
            font-weight: 500;
            color: #64748b;
            margin-top: 3px;
        }

        .stat-card .stat-trend {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 6px;
        }

        /* ─── TABLES ─── */
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .data-table thead th {
            padding: 12px 16px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
        }

        .data-table thead th:first-child {
            border-top-left-radius: 10px;
        }

        .data-table thead th:last-child {
            border-top-right-radius: 10px;
        }

        .data-table tbody td {
            padding: 13px 16px;
            font-size: 13px;
            color: #334155;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .data-table tbody tr {
            transition: background var(--transition-base);
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        .data-table tbody tr:hover {
            background: #f8fafc;
        }

        /* ─── STATUS BADGES ─── */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 3px 11px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.01em;
            line-height: 1.4;
        }

        .badge-green { background: #dcfce7; color: #166534; }
        .badge-red { background: #fee2e2; color: #991b1b; }
        .badge-yellow { background: #fef9c3; color: #854d0e; }
        .badge-blue { background: #dbeafe; color: #1e40af; }
        .badge-purple { background: #f3e8ff; color: #6b21a8; }
        .badge-gray { background: #f1f5f9; color: #475569; }
        .badge-cyan { background: #cffafe; color: #155e75; }

        /* ─── BUTTONS ─── */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 8px 16px;
            border-radius: 9px;
            font-weight: 600;
            font-size: 13px;
            transition: all var(--transition-base);
            cursor: pointer;
            border: none;
            text-decoration: none;
            line-height: 1.4;
            white-space: nowrap;
            font-family: inherit;
        }

        .btn:active {
            transform: scale(0.97);
        }

        .btn-primary {
            background: var(--rotary-blue);
            color: white;
            box-shadow: 0 2px 6px rgba(10,35,66,0.15);
        }
        .btn-primary:hover { background: var(--rotary-blue-light); box-shadow: 0 4px 12px rgba(10,35,66,0.2); }

        .btn-yellow {
            background: var(--rotary-yellow);
            color: var(--rotary-blue);
            box-shadow: 0 2px 6px rgba(255,192,0,0.2);
        }
        .btn-yellow:hover { background: var(--rotary-gold); box-shadow: 0 4px 12px rgba(255,192,0,0.3); }

        .btn-danger { background: #ef4444; color: white; box-shadow: 0 2px 6px rgba(239,68,68,0.15); }
        .btn-danger:hover { background: #dc2626; box-shadow: 0 4px 12px rgba(239,68,68,0.25); }

        .btn-ghost { background: transparent; color: #64748b; }
        .btn-ghost:hover { background: #f1f5f9; color: #1e293b; }

        .btn-outline {
            background: transparent;
            color: var(--rotary-blue);
            border: 1.5px solid #e2e8f0;
        }
        .btn-outline:hover { background: #f8fafc; border-color: #cbd5e1; }

        .btn-secondary { background: #f1f5f9; color: #475569; }
        .btn-secondary:hover { background: #e2e8f0; color: #1e293b; }

        .btn-edit { background: #f59e0b; color: white; box-shadow: 0 2px 6px rgba(245,158,11,0.15); }
        .btn-edit:hover { background: #d97706; box-shadow: 0 4px 12px rgba(245,158,11,0.25); }

        .btn-delete { background: #ef4444; color: white; box-shadow: 0 2px 6px rgba(239,68,68,0.15); }
        .btn-delete:hover { background: #dc2626; box-shadow: 0 4px 12px rgba(239,68,68,0.25); }

        .btn-indigo { background: #4f46e5; color: white; box-shadow: 0 2px 6px rgba(79,70,229,0.15); }
        .btn-indigo:hover { background: #4338ca; box-shadow: 0 4px 12px rgba(79,70,229,0.25); }

        .btn-success { background: #10b981; color: white; box-shadow: 0 2px 6px rgba(16,185,129,0.15); }
        .btn-success:hover { background: #059669; box-shadow: 0 4px 12px rgba(16,185,129,0.25); }

        .btn-sm { padding: 6px 12px; font-size: 12px; border-radius: 7px; }
        .btn-xs { padding: 4px 9px; font-size: 11px; border-radius: 6px; }
        .btn-lg { padding: 11px 22px; font-size: 14px; border-radius: 10px; }

        /* ─── FORM INPUTS ─── */
        .form-group { margin-bottom: 18px; }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 10px 13px;
            border: 1.5px solid #e2e8f0;
            border-radius: 9px;
            font-size: 13px;
            color: #1e293b;
            background: white;
            transition: all var(--transition-base);
            font-family: inherit;
            line-height: 1.5;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--rotary-yellow);
            box-shadow: 0 0 0 3px rgba(255,192,0,0.12);
        }

        .form-input::placeholder { color: #94a3b8; }

        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 11px center;
            padding-right: 34px;
        }

        /* ─── ALERTS ─── */
        .alert {
            padding: 13px 17px;
            border-radius: 10px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 9px;
            font-weight: 500;
        }

        .alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .alert-info { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; }

        /* ─── EMPTY STATE ─── */
        .empty-state {
            text-align: center;
            padding: 48px 24px;
            color: #94a3b8;
        }

        .empty-state svg {
            width: 52px;
            height: 52px;
            margin: 0 auto 16px;
            opacity: 0.35;
        }

        .empty-state h3 {
            font-size: 16px;
            font-weight: 600;
            color: #64748b;
            margin: 0 0 4px;
        }

        .empty-state p {
            font-size: 13px;
            margin: 0;
        }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
                box-shadow: 4px 0 30px rgba(0,0,0,0.2);
            }
            .sidebar.open { transform: translateX(0); }
            .top-header { left: 0; padding: 0 18px; }
            .main-content { margin-left: 0; padding: 20px 16px; }
            .page-title { font-size: 22px; }
            .stat-card .stat-value { font-size: 24px; }
        }

        /* ─── ANIMATIONS ─── */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            animation: fadeIn 0.35s ease-out forwards;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-16px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .slide-in {
            animation: slideIn 0.3s ease-out forwards;
        }

        /* ─── TABS ─── */
        .tab-bar {
            display: flex;
            gap: 2px;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 24px;
        }

        .tab-item {
            padding: 11px 18px;
            font-size: 13px;
            font-weight: 500;
            color: #64748b;
            border-bottom: 2.5px solid transparent;
            cursor: pointer;
            transition: all var(--transition-base);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: -1px;
        }

        .tab-item:hover { color: #1e293b; }
        .tab-item.active { color: var(--rotary-blue); border-bottom-color: var(--rotary-yellow); font-weight: 600; }

        /* ─── MODAL ─── */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            z-index: 200;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        .modal-overlay.open { display: flex; }

        .modal-content {
            background: white;
            border-radius: 16px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.15);
            max-width: 540px;
            width: 100%;
            max-height: 85vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 22px 26px 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header h2 {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            margin: 0;
        }

        .modal-body { padding: 18px 26px; }

        .modal-footer {
            padding: 16px 26px;
            border-top: 1px solid #f1f5f9;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .modal-close {
            width: 34px;
            height: 34px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 9px;
            border: none;
            background: transparent;
            color: #94a3b8;
            cursor: pointer;
            transition: all var(--transition-base);
        }

        .modal-close:hover {
            background: #f1f5f9;
            color: #1e293b;
        }

        /* ─── UTILITIES ─── */
        .flex-center { display: flex; align-items: center; justify-content: center; }
        .gap-1 { gap: 4px; }
        .gap-2 { gap: 8px; }
        .gap-3 { gap: 12px; }
        .gap-4 { gap: 16px; }
    </style>
</head>
<body>
