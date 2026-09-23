<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}
include __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/admin_functions.php';
require_once __DIR__ . '/../includes/website_settings.php';
require_once __DIR__ . '/includes/action_center.php';
$_ws = getWebsiteSettings($conn);

$profile = getAdminProfileData($conn);
$adminRole = $_SESSION['admin_role'] ?? '';

$actionCards = getActionCards($conn, $adminRole);
$totalActions = count($actionCards);
$quickActions = getQuickActions($adminRole);

$totalMembers = 0;
$r = $conn->query("SELECT COUNT(*) as c FROM members");
if ($r) { $totalMembers = $r->fetch_assoc()['c']; }

$totalEvents = 0;
$r = $conn->query("SELECT COUNT(*) as c FROM events");
if ($r) { $totalEvents = $r->fetch_assoc()['c']; }

$totalProjects = 0;
$r = $conn->query("SELECT COUNT(*) as c FROM projects");
if ($r) { $totalProjects = $r->fetch_assoc()['c']; }

$totalDonations = 0;
$r = $conn->query("SELECT COUNT(*) as c FROM donations");
if ($r) { $totalDonations = $r->fetch_assoc()['c']; }

$totalDonationAmount = 0;
$r = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM donations WHERE donation_type = 'Monetary Donation'");
if ($r) { $totalDonationAmount = round($r->fetch_assoc()['total']); }
if ($totalDonationAmount >= 1000000) {
    $donationDisplay = '₹' . number_format($totalDonationAmount / 100000, 1) . 'L';
} elseif ($totalDonationAmount >= 1000) {
    $donationDisplay = '₹' . number_format($totalDonationAmount / 1000, 1) . 'K';
} else {
    $donationDisplay = '₹' . $totalDonationAmount;
}

$contactUnread = 0;
$r = $conn->query("SELECT COUNT(*) as c FROM contact_messages WHERE status = 'new'");
if ($r) { $contactUnread = $r->fetch_assoc()['c']; }

$upcomingEvents = [];
$r = $conn->query("SELECT event_id, title, start_date, location FROM events WHERE start_date >= CURDATE() ORDER BY start_date ASC LIMIT 5");
if ($r) { while ($row = $r->fetch_assoc()) { $upcomingEvents[] = $row; } }

$recentAudit = [];
if (isSuperAdmin()) {
    $recentAudit = getRecentAuditActivity($conn, 5);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($_ws['browser_title']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        :root {
            --rotary-blue: #0A2342;
            --rotary-blue-light: #1a365d;
            --rotary-yellow: #FFC000;
            --rotary-gold: #e6a800;
            --sidebar-w: 260px;
            --header-h: 68px;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f0f2f5;
            color: #1e293b;
            margin: 0;
            -webkit-font-smoothing: antialiased;
        }
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }

        .sidebar {
            position: fixed; top: 0; left: 0;
            width: var(--sidebar-w); height: 100vh;
            background: linear-gradient(180deg, #0A2342 0%, #0d2b52 60%, #0f3260 100%);
            z-index: 100; display: flex; flex-direction: column;
            box-shadow: 2px 0 20px rgba(0,0,0,0.12);
            transition: transform 0.25s ease;
        }
        .sidebar-logo {
            padding: 22px 20px 18px;
            display: flex; align-items: center; gap: 14px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            flex-shrink: 0;
        }
        .sidebar-logo .logo-wrap {
            width: 44px; height: 44px;
            border-radius: 12px;
            background: rgba(255,192,0,0.12);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .sidebar-logo .logo-wrap img { width: 40px; height: 40px; border-radius: 10px; object-fit: cover; }
        .sidebar-logo-text h1 { font-size: 15px; font-weight: 800; color: #fff; margin: 0; line-height: 1.25; }
        .sidebar-logo-text p { font-size: 10px; color: rgba(255,255,255,0.4); margin: 0; font-weight: 500; }
        .sidebar-profile {
            padding: 14px 18px 12px;
            display: flex; align-items: center; gap: 10px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            flex-shrink: 0;
        }
        .sidebar-profile img {
            width: 32px; height: 32px; border-radius: 50%;
            object-fit: cover; border: 2px solid rgba(255,192,0,0.2);
        }
        .sidebar-profile .sp-name { font-size: 12px; font-weight: 600; color: rgba(255,255,255,0.85); line-height: 1.3; }
        .sidebar-profile .sp-role { font-size: 10px; color: rgba(255,255,255,0.4); }
        .sidebar-nav { flex: 1; overflow-y: auto; padding: 10px 10px; }
        .sidebar-nav::-webkit-scrollbar { width: 3px; }
        .sidebar-nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.12); }
        .nav-section { margin-bottom: 4px; }
        .nav-section-title {
            font-size: 9px; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.1em; color: rgba(255,255,255,0.28);
            padding: 10px 12px 4px;
        }
        .nav-item {
            display: flex; align-items: center; gap: 11px;
            padding: 8px 12px; border-radius: 8px;
            color: rgba(255,255,255,0.5);
            text-decoration: none; font-size: 13px; font-weight: 500;
            transition: all 0.15s ease; cursor: pointer; margin-bottom: 1px;
            position: relative;
        }
        .nav-item:hover { background: rgba(255,255,255,0.07); color: rgba(255,255,255,0.85); }
        .nav-item.active {
            background: rgba(255,192,0,0.1); color: #FFC000;
        }
        .nav-item.active::before {
            content: '';
            position: absolute; left: 0; top: 50%;
            transform: translateY(-50%);
            width: 3px; height: 16px;
            background: #FFC000; border-radius: 0 4px 4px 0;
            box-shadow: 0 0 8px rgba(255,192,0,0.3);
        }
        .nav-item svg { width: 18px; height: 18px; flex-shrink: 0; }
        .nav-item .nav-label { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sidebar-footer {
            padding: 10px 14px 14px;
            border-top: 1px solid rgba(255,255,255,0.05);
            flex-shrink: 0;
        }
        .sidebar-footer .nav-item { font-size: 12px; padding: 7px 12px; color: rgba(255,255,255,0.35); }
        .sidebar-footer .nav-item:hover { color: rgba(255,255,255,0.7); background: rgba(255,255,255,0.05); }

        .top-header {
            position: fixed; top: 0; left: var(--sidebar-w); right: 0;
            height: var(--header-h);
            background: rgba(255,255,255,0.92); backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(226,232,240,0.6);
            z-index: 90;
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 30px;
        }
        .top-header-left { display: flex; align-items: center; gap: 12px; }
        .breadcrumb { font-size: 13px; color: #94a3b8; display: flex; align-items: center; gap: 8px; }
        .breadcrumb a { color: #64748b; text-decoration: none; transition: color 0.15s; }
        .breadcrumb a:hover { color: var(--rotary-blue); }
        .breadcrumb .current { color: #0f172a; font-weight: 600; }
        .top-header-right { display: flex; align-items: center; gap: 8px; }
        .h-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 7px 12px; border-radius: 8px;
            font-size: 12px; font-weight: 500;
            color: #64748b; text-decoration: none;
            transition: all 0.15s;
        }
        .h-btn:hover { background: #f1f5f9; color: #1e293b; }
        .h-btn-danger { color: #ef4444; }
        .h-btn-danger:hover { background: #fef2f2; color: #dc2626; }
        .admin-profile {
            display: flex; align-items: center; gap: 10px;
            padding: 4px 10px 4px 4px; border-radius: 10px;
            cursor: pointer; transition: all 0.15s;
            border: 1px solid transparent;
        }
        .admin-profile:hover { background: #f8fafc; border-color: #e2e8f0; }
        .admin-avatar { width: 34px; height: 34px; border-radius: 50%; object-fit: cover; border: 2px solid #e2e8f0; }
        .admin-info-name { font-size: 13px; font-weight: 600; color: #0f172a; }
        .admin-info-role { font-size: 11px; color: #94a3b8; }

        .main-content {
            margin-left: var(--sidebar-w); margin-top: var(--header-h);
            padding: 28px 32px;
            min-height: calc(100vh - var(--header-h));
        }
        .page-title {
            font-size: 26px; font-weight: 800; color: #0f172a;
            margin: 0 0 4px; letter-spacing: -0.02em;
        }
        .page-subtitle { font-size: 14px; color: #64748b; margin: 0; }

        .stat-card {
            background: white; border-radius: 14px; padding: 22px 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border: 1px solid rgba(241,245,249,0.7);
            transition: all 0.25s ease;
            position: relative; overflow: hidden;
        }
        .stat-card:hover {
            box-shadow: 0 10px 30px rgba(0,0,0,0.07), 0 4px 8px rgba(0,0,0,0.03);
            transform: translateY(-3px);
        }
        .stat-card .stat-icon {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 14px;
        }
        .stat-card .stat-icon svg { width: 24px; height: 24px; }
        .stat-card .stat-value {
            font-size: 28px; font-weight: 800; color: #0f172a;
            line-height: 1.2; letter-spacing: -0.03em;
        }
        .stat-card .stat-label {
            font-size: 13px; font-weight: 500; color: #64748b; margin-top: 3px;
        }

        .panel-card {
            background: white; border-radius: 14px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border: 1px solid rgba(241,245,249,0.7);
            transition: box-shadow 0.2s;
            overflow: hidden;
        }
        .panel-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
        .panel-header {
            padding: 20px 24px 0;
            display: flex; align-items: center; justify-content: space-between;
        }
        .panel-body { padding: 18px 24px 22px; }

        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            gap: 6px; padding: 8px 16px; border-radius: 9px;
            font-weight: 600; font-size: 13px;
            transition: all 0.15s ease; cursor: pointer;
            border: none; text-decoration: none; line-height: 1.4;
            font-family: inherit;
        }
        .btn:active { transform: scale(0.97); }
        .btn-primary { background: var(--rotary-blue); color: #fff; }
        .btn-primary:hover { background: var(--rotary-blue-light); }
        .btn-yellow { background: var(--rotary-yellow); color: var(--rotary-blue); }
        .btn-yellow:hover { background: var(--rotary-gold); }
        .btn-ghost { background: transparent; color: #64748b; }
        .btn-ghost:hover { background: #f1f5f9; color: #1e293b; }
        .btn-outline { background: transparent; color: var(--rotary-blue); border: 1.5px solid #e2e8f0; }
        .btn-outline:hover { background: #f8fafc; border-color: #cbd5e1; }
        .btn-secondary { background: #f1f5f9; color: #475569; }
        .btn-secondary:hover { background: #e2e8f0; }
        .btn-sm { padding: 6px 12px; font-size: 12px; border-radius: 7px; }
        .btn-lg { padding: 11px 22px; font-size: 14px; border-radius: 10px; }
        .btn-block { width: 100%; justify-content: center; }

        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); box-shadow: 4px 0 30px rgba(0,0,0,0.2); }
            .sidebar.open { transform: translateX(0); }
            .top-header { left: 0; padding: 0 16px; }
            .main-content { margin-left: 0; padding: 20px 16px; }
            .page-title { font-size: 22px; }
            .stat-card .stat-value { font-size: 24px; }
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-up { animation: fadeInUp 0.4s ease-out forwards; }
        .fade-up:nth-child(1) { animation-delay: 0.05s; }
        .fade-up:nth-child(2) { animation-delay: 0.1s; }
        .fade-up:nth-child(3) { animation-delay: 0.15s; }
        .fade-up:nth-child(4) { animation-delay: 0.2s; }
        .fade-up:nth-child(5) { animation-delay: 0.25s; }
        .fade-up:nth-child(6) { animation-delay: 0.3s; }

        .grid-quick { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; }
        @media (min-width: 768px) { .grid-quick { grid-template-columns: repeat(3, 1fr); } }
        @media (min-width: 1024px) { .grid-quick { grid-template-columns: repeat(5, 1fr); } }

        .badge {
            display: inline-flex; align-items: center;
            padding: 3px 11px; border-radius: 9999px;
            font-size: 11px; font-weight: 600;
        }
        .badge-green { background: #dcfce7; color: #166534; }
        .badge-yellow { background: #fef9c3; color: #854d0e; }
        .badge-blue { background: #dbeafe; color: #1e40af; }
        .badge-red { background: #fee2e2; color: #991b1b; }
        .badge-gray { background: #f1f5f9; color: #475569; }

        /* ─── ACTION CENTER ─── */
        .action-card {
            background: white;
            border-radius: 14px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            border: 1px solid rgba(241,245,249,0.7);
            border-left: 4px solid #e2e8f0;
            padding: 20px 22px;
            transition: all 0.25s ease;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .action-card:hover {
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
            transform: translateY(-2px);
        }
        .action-card-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }
        .action-card-icon {
            width: 44px; height: 44px; border-radius: 11px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .action-card-icon svg { width: 22px; height: 22px; }
        .action-card-count {
            font-size: 22px; font-weight: 800; color: #0f172a;
            line-height: 1; letter-spacing: -0.03em;
        }
        .action-card-title {
            font-size: 14px; font-weight: 700; color: #0f172a;
            margin: 0;
        }
        .action-card-desc {
            font-size: 12.5px; color: #64748b; margin: 0;
            line-height: 1.45;
        }
        .action-card-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-top: 4px;
        }
        .action-card-priority {
            font-size: 9.5px; font-weight: 700; text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 2px 8px; border-radius: 4px;
        }
        .action-btn {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 6px 14px; border-radius: 7px;
            font-size: 12px; font-weight: 600;
            text-decoration: none; transition: all 0.15s ease;
            border: none; cursor: pointer; font-family: inherit;
        }
        .action-btn:hover { transform: scale(1.03); }
        .action-btn svg { width: 14px; height: 14px; }

        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 16px;
        }
        @media (max-width: 768px) {
            .action-grid { grid-template-columns: 1fr; }
        }

        .section-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 18px;
        }
        .section-title {
            font-size: 18px; font-weight: 700; color: #0f172a; margin: 0;
            display: flex; align-items: center; gap: 10px;
        }
        .section-title svg { width: 22px; height: 22px; color: var(--rotary-yellow); }

        .empty-actions {
            text-align: center; padding: 40px 20px;
            background: white; border-radius: 14px;
            border: 1px dashed #e2e8f0;
        }
        .empty-actions svg { width: 48px; height: 48px; color: #10b981; margin-bottom: 12px; opacity: 0.6; }
        .empty-actions h3 { font-size: 16px; font-weight: 600; color: #10b981; margin: 0 0 4px; }
        .empty-actions p { font-size: 13px; color: #94a3b8; margin: 0; }

        .quick-action-card {
            display: flex; align-items: center; gap: 12px;
            padding: 16px 18px; border-radius: 12px;
            text-decoration: none; transition: all 0.2s ease;
            background: white;
            border: 1px solid rgba(241,245,249,0.7);
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .quick-action-card:hover {
            box-shadow: 0 6px 16px rgba(0,0,0,0.07);
            transform: translateY(-2px);
        }
        .quick-action-icon {
            width: 40px; height: 40px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .quick-action-icon svg { width: 20px; height: 20px; }
        .quick-action-label { font-size: 13.5px; font-weight: 600; color: #0f172a; }

        .audit-row {
            display: flex; align-items: flex-start; gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .audit-row:last-child { border-bottom: none; }
        .audit-dot {
            width: 8px; height: 8px; border-radius: 50%;
            margin-top: 5px; flex-shrink: 0;
        }
        .audit-info { flex: 1; min-width: 0; }
        .audit-action { font-size: 13px; font-weight: 600; color: #1e293b; }
        .audit-desc { font-size: 12px; color: #64748b; margin-top: 2px; line-height: 1.4; }
        .audit-meta { font-size: 11px; color: #94a3b8; margin-top: 4px; }
    </style>
</head>
<body>

<!-- ═══ SIDEBAR ═══ -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="logo-wrap">
            <img src="../<?= htmlspecialchars($_ws['website_logo']) ?>" alt="<?= htmlspecialchars($_ws['website_short_name']) ?>" onerror="this.outerHTML='<span style=font-size:20px;font-weight:800;color:#0A2342;>R</span>'">
        </div>
        <div class="sidebar-logo-text">
            <h1><?= htmlspecialchars($_ws['website_name']) ?></h1>
            <p>Admin Dashboard</p>
        </div>
    </div>
    <div class="sidebar-profile">
        <img src="<?= htmlspecialchars($profile['photo']) ?>" alt=""
             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
        <div style="display:none;width:32px;height:32px;border-radius:50%;background:rgba(255,192,0,0.15);align-items:center;justify-content:center;color:#FFC000;font-size:14px;flex-shrink:0;">&#x1F464;</div>
        <div style="flex:1;min-width:0;">
            <div class="sp-name"><?= htmlspecialchars($profile['name']) ?></div>
            <div class="sp-role"><?= htmlspecialchars($profile['role']) ?></div>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section"><div class="nav-section-title">Main</div>
            <a href="dashboard.php" class="nav-item active"><i data-lucide="layout-dashboard"></i><span class="nav-label">Dashboard</span></a>
        </div>
        <div class="nav-section"><div class="nav-section-title">Management</div>
            <a href="project_action.php" class="nav-item"><i data-lucide="folder"></i><span class="nav-label">Projects</span></a>
            <a href="project_report_action.php" class="nav-item"><i data-lucide="file-text"></i><span class="nav-label">Project Reports</span></a>
            <a href="event_action.php" class="nav-item"><i data-lucide="calendar"></i><span class="nav-label">Events</span></a>
            <a href="event_report_action.php" class="nav-item"><i data-lucide="clipboard-list"></i><span class="nav-label">Event Reports</span></a>
            <a href="member_action.php" class="nav-item"><i data-lucide="users"></i><span class="nav-label">Members</span></a>
            <a href="member_List.php" class="nav-item"><i data-lucide="list"></i><span class="nav-label">Member List</span></a>
            <a href="donation_action.php" class="nav-item"><i data-lucide="heart-handshake"></i><span class="nav-label">Donations</span></a>
            <a href="donation_report_action.php" class="nav-item"><i data-lucide="receipt"></i><span class="nav-label">Donation Reports</span></a>
            <a href="collaboration_action.php" class="nav-item"><i data-lucide="handshake"></i><span class="nav-label">Collaborations</span></a>
            <a href="collaboration_report.php" class="nav-item"><i data-lucide="bar-chart-3"></i><span class="nav-label">Collab Reports</span></a>
        </div>
        <div class="nav-section"><div class="nav-section-title">Content</div>
            <a href="admin_add_media.php" class="nav-item"><i data-lucide="image"></i><span class="nav-label">Upload Media</span></a>
            <a href="gallery_list.php" class="nav-item"><i data-lucide="images"></i><span class="nav-label">Gallery</span></a>
            <a href="addEvent_poll.php" class="nav-item"><i data-lucide="vote"></i><span class="nav-label">Event Polls</span></a>
            <a href="pollVoter_list.php" class="nav-item"><i data-lucide="users"></i><span class="nav-label">Poll Voters</span></a>
        </div>
        <?php
        $showContactMsgs = isSuperAdmin() || in_array($_SESSION['admin_role'], ['President', 'Secretary', 'Treasurer']);
        if (isSuperAdmin() || $showContactMsgs):
        ?>
        <div class="nav-section"><div class="nav-section-title">Administration</div>
            <?php if (isSuperAdmin()): ?>
            <a href="admins_management.php" class="nav-item"><i data-lucide="shield"></i><span class="nav-label">Admin Accounts</span></a>
            <a href="rotary_years.php" class="nav-item"><i data-lucide="calendar"></i><span class="nav-label">Rotary Years</span></a>
            <a href="leadership_transfer.php" class="nav-item"><i data-lucide="users"></i><span class="nav-label">Leadership Management</span></a>
            <a href="website_settings.php" class="nav-item"><i data-lucide="settings"></i><span class="nav-label">Website Settings</span></a>
            <a href="site_content.php" class="nav-item"><i data-lucide="edit"></i><span class="nav-label">Website Content</span></a>
            <a href="contact_social_links.php" class="nav-item"><i data-lucide="share-2"></i><span class="nav-label">Contact & Social Links</span></a>
            <?php endif; ?>
            <?php if ($showContactMsgs): ?>
            <a href="contact_messages.php" class="nav-item"><i data-lucide="mail"></i><span class="nav-label">Contact Messages</span></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="nav-section"><div class="nav-section-title">System</div>
            <a href="change_password.php" class="nav-item"><i data-lucide="settings"></i><span class="nav-label">Settings</span></a>
        </div>
    </nav>
    <div class="sidebar-footer">
        <a href="../index.php" class="nav-item"><i data-lucide="globe"></i><span class="nav-label">View Website</span></a>
        <a href="logout.php" class="nav-item" style="color:rgba(255,100,100,0.4);"><i data-lucide="log-out"></i><span class="nav-label">Logout</span></a>
    </div>
</div>
<div id="sidebar-overlay" style="position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:90;display:none;" onclick="toggleSidebar()"></div>

<!-- ═══ HEADER ═══ -->
<header class="top-header">
    <div class="top-header-left">
        <button onclick="toggleSidebar()" id="mobileToggle" style="display:none;padding:6px;border:none;background:none;cursor:pointer;border-radius:8px;color:#64748b;">
            <i data-lucide="menu" style="width:20px;height:20px;"></i>
        </button>
        <div class="breadcrumb">
            <a href="dashboard.php">Dashboard</a>
            <i data-lucide="chevron-right" style="width:12px;height:12px;"></i>
            <span class="current">Dashboard Overview</span>
        </div>
    </div>
    <div class="top-header-right">
        <a href="../index.php" class="h-btn" id="websiteBtn">
            <i data-lucide="external-link" style="width:15px;height:15px;"></i>
            <span>Website</span>
        </a>
        <a href="logout.php" class="h-btn h-btn-danger">
            <i data-lucide="log-out" style="width:15px;height:15px;"></i>
            <span style="display:block;">Logout</span>
        </a>
        <div class="admin-profile" onclick="window.location.href='change_password.php'">
            <img src="<?= htmlspecialchars($profile['photo']) ?>" alt="" class="admin-avatar"
                 onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><rect width=%22100%22 height=%22100%22 rx=%2250%22 fill=%22%23e2e8f0%22/><text x=%2250%22 y=%2265%22 text-anchor=%22middle%22 font-size=%2240%22 fill=%22%2394a3b8%22>👤</text></svg>'">
            <div>
                <div class="admin-info-name"><?= htmlspecialchars($profile['name']) ?></div>
                <div class="admin-info-role"><?= htmlspecialchars($profile['role']) ?></div>
            </div>
        </div>
    </div>
</header>

<!-- ═══ MAIN CONTENT ═══ -->
<main class="main-content">
    <div style="margin-bottom:28px;">
        <h1 class="page-title">Welcome, <?= htmlspecialchars($profile['name']) ?></h1>
        <p class="page-subtitle">Here's what requires your attention right now.</p>
    </div>

    <!-- ═══ ACTION CENTER ═══ -->
    <div style="margin-bottom:32px;">
        <div class="section-header">
            <h2 class="section-title">
                <i data-lucide="zap"></i>
                Action Center
            </h2>
            <?php if ($totalActions > 0): ?>
                <span class="badge badge-red" style="font-size:12px;padding:4px 14px;"><?= (int)$totalActions ?> pending</span>
            <?php endif; ?>
        </div>

        <?php if ($totalActions === 0): ?>
            <div class="empty-actions fade-up">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                <h3>All caught up!</h3>
                <p>No actions required at this time. Great work!</p>
            </div>
        <?php else: ?>
            <div class="action-grid">
                <?php foreach ($actionCards as $idx => $card):
                    $ps = getPriorityStyles($card['priority']);
                ?>
                <div class="action-card fade-up" style="border-left-color:<?= htmlspecialchars($ps['border']) ?>;animation-delay:<?= round($idx * 0.05, 2) ?>s;">
                    <div class="action-card-top">
                        <div class="action-card-icon" style="background:<?= htmlspecialchars($ps['icon_bg']) ?>;">
                            <i data-lucide="<?= htmlspecialchars($card['icon']) ?>" style="color:<?= htmlspecialchars($ps['icon_color']) ?>;"></i>
                        </div>
                        <div class="action-card-count"><?= (int)$card['count'] ?></div>
                    </div>
                    <div>
                        <h3 class="action-card-title"><?= htmlspecialchars($card['title']) ?></h3>
                        <p class="action-card-desc"><?= htmlspecialchars($card['description']) ?></p>
                    </div>
                    <div class="action-card-bottom">
                        <span class="action-card-priority badge <?= htmlspecialchars($ps['badge']) ?>"><?= htmlspecialchars($ps['label']) ?></span>
                        <a href="<?= htmlspecialchars($card['action_url']) ?>" class="action-btn" style="background:<?= htmlspecialchars($ps['border']) ?>;color:white;">
                            <?= htmlspecialchars($card['action_text']) ?>
                            <i data-lucide="arrow-right"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ═══ QUICK ACTIONS ═══ -->
    <?php if (!empty($quickActions)): ?>
    <div style="margin-bottom:32px;">
        <div class="section-header">
            <h2 class="section-title">
                <i data-lucide="rocket"></i>
                Quick Actions
            </h2>
        </div>
        <div class="action-grid" style="grid-template-columns:repeat(auto-fill,minmax(220px,1fr));">
            <?php foreach ($quickActions as $qa): ?>
            <a href="<?= htmlspecialchars($qa['url']) ?>" class="quick-action-card fade-up">
                <div class="quick-action-icon" style="background:<?= htmlspecialchars($qa['bg']) ?>;">
                    <i data-lucide="<?= htmlspecialchars($qa['icon']) ?>" style="color:<?= htmlspecialchars($qa['color']) ?>;"></i>
                </div>
                <span class="quick-action-label"><?= htmlspecialchars($qa['label']) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ═══ ROLE-SPECIFIC SECTIONS ═══ -->
    <?php if (isSuperAdmin()): ?>
    <!-- SUPER ADMIN: Statistics + Charts + Audit Activity -->
    <div style="margin-bottom:32px;">
        <div class="section-header">
            <h2 class="section-title">
                <i data-lucide="bar-chart-3"></i>
                Club Statistics
            </h2>
        </div>
        <div class="grid-quick" style="grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;">
            <div class="stat-card fade-up">
                <div class="stat-icon" style="background:#eef2ff;">
                    <i data-lucide="users" style="width:24px;height:24px;color:#4f46e5;"></i>
                </div>
                <div class="stat-value"><?= (int)$totalMembers ?></div>
                <div class="stat-label">Total Members</div>
            </div>
            <div class="stat-card fade-up">
                <div class="stat-icon" style="background:#fef9c3;">
                    <i data-lucide="calendar" style="width:24px;height:24px;color:#eab308;"></i>
                </div>
                <div class="stat-value"><?= (int)$totalEvents ?></div>
                <div class="stat-label">Total Events</div>
            </div>
            <div class="stat-card fade-up">
                <div class="stat-icon" style="background:#dbeafe;">
                    <i data-lucide="folder" style="width:24px;height:24px;color:#2563eb;"></i>
                </div>
                <div class="stat-value"><?= (int)$totalProjects ?></div>
                <div class="stat-label">Total Projects</div>
            </div>
            <div class="stat-card fade-up">
                <div class="stat-icon" style="background:#d1fae5;">
                    <i data-lucide="heart-handshake" style="width:24px;height:24px;color:#10b981;"></i>
                </div>
                <div class="stat-value"><?= (int)$totalDonations ?></div>
                <div class="stat-label">Total Donations</div>
            </div>
            <div class="stat-card fade-up">
                <div class="stat-icon" style="background:#fce7f3;">
                    <i data-lucide="indian-rupee" style="width:24px;height:24px;color:#ec4899;"></i>
                </div>
                <div class="stat-value"><?= htmlspecialchars($donationDisplay) ?></div>
                <div class="stat-label">Donation Value</div>
            </div>
            <div class="stat-card fade-up">
                <div class="stat-icon" style="background:#fef2f2;">
                    <i data-lucide="mail" style="width:24px;height:24px;color:#ef4444;"></i>
                </div>
                <div class="stat-value"><?= (int)$contactUnread ?></div>
                <div class="stat-label">Unread Messages</div>
            </div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:22px;margin-bottom:32px;">
        <div class="panel-card fade-up">
            <div class="panel-header">
                <h3 style="font-size:16px;font-weight:700;color:#0f172a;margin:0;">Monthly Engagement</h3>
            </div>
            <div class="panel-body">
                <div style="height:260px;">
                    <canvas id="engagementChart"></canvas>
                </div>
            </div>
        </div>
        <div class="panel-card fade-up">
            <div class="panel-header">
                <h3 style="font-size:16px;font-weight:700;color:#0f172a;margin:0;">Donation Type Ratio</h3>
            </div>
            <div class="panel-body">
                <div style="height:260px;display:flex;align-items:center;justify-content:center;">
                    <canvas id="donationPieChart" style="max-height:240px;"></canvas>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($recentAudit)): ?>
    <div style="margin-bottom:32px;">
        <div class="section-header">
            <h2 class="section-title">
                <i data-lucide="scroll"></i>
                Recent Audit Activity
            </h2>
            <a href="audit_logs.php" class="btn btn-ghost btn-sm">View All</a>
        </div>
        <div class="panel-card fade-up">
            <div class="panel-body" style="padding:8px 24px 4px;">
                <?php foreach ($recentAudit as $log):
                    $dotColor = '#10b981';
                    if ($log['severity'] === 'CRITICAL') { $dotColor = '#ef4444'; }
                    elseif ($log['severity'] === 'WARNING') { $dotColor = '#f97316'; }
                    elseif ($log['status'] === 'failed') { $dotColor = '#ef4444'; }
                    $timeDiff = '';
                    $now = new DateTime();
                    $logTime = new DateTime($log['created_at']);
                    $diffMins = $now->getTimestamp() - $logTime->getTimestamp();
                    if ($diffMins < 60) { $timeDiff = max(1, intval($diffMins / 60)) . 'm ago'; }
                    elseif ($diffMins < 86400) { $timeDiff = intval($diffMins / 3600) . 'h ago'; }
                    else { $timeDiff = intval($diffMins / 86400) . 'd ago'; }
                ?>
                <div class="audit-row">
                    <div class="audit-dot" style="background:<?= $dotColor ?>;"></div>
                    <div class="audit-info">
                        <div class="audit-action"><?= htmlspecialchars($log['action']) ?></div>
                        <div class="audit-desc"><?= htmlspecialchars($log['description']) ?></div>
                        <div class="audit-meta"><?= htmlspecialchars($log['admin_name']) ?> &middot; <?= htmlspecialchars($log['module']) ?> &middot; <?= htmlspecialchars($timeDiff) ?></div>
                    </div>
                    <span class="badge <?= $log['status'] === 'success' ? 'badge-green' : 'badge-red' ?>"><?= htmlspecialchars($log['status']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php elseif ($_SESSION['admin_role'] === 'President'): ?>
    <!-- PRESIDENT: Stats + Upcoming Events + Donations -->
    <div style="margin-bottom:32px;">
        <div class="section-header">
            <h2 class="section-title">
                <i data-lucide="bar-chart-3"></i>
                Club Statistics
            </h2>
        </div>
        <div class="grid-quick" style="grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;">
            <div class="stat-card fade-up">
                <div class="stat-icon" style="background:#eef2ff;"><i data-lucide="users" style="width:24px;height:24px;color:#4f46e5;"></i></div>
                <div class="stat-value"><?= (int)$totalMembers ?></div>
                <div class="stat-label">Total Members</div>
            </div>
            <div class="stat-card fade-up">
                <div class="stat-icon" style="background:#fef9c3;"><i data-lucide="calendar" style="width:24px;height:24px;color:#eab308;"></i></div>
                <div class="stat-value"><?= (int)$totalEvents ?></div>
                <div class="stat-label">Total Events</div>
            </div>
            <div class="stat-card fade-up">
                <div class="stat-icon" style="background:#dbeafe;"><i data-lucide="folder" style="width:24px;height:24px;color:#2563eb;"></i></div>
                <div class="stat-value"><?= (int)$totalProjects ?></div>
                <div class="stat-label">Total Projects</div>
            </div>
            <div class="stat-card fade-up">
                <div class="stat-icon" style="background:#d1fae5;"><i data-lucide="heart-handshake" style="width:24px;height:24px;color:#10b981;"></i></div>
                <div class="stat-value"><?= (int)$totalDonations ?></div>
                <div class="stat-label">Total Donations</div>
            </div>
        </div>
    </div>

    <?php if (!empty($upcomingEvents)): ?>
    <div style="margin-bottom:32px;">
        <div class="section-header">
            <h2 class="section-title">
                <i data-lucide="calendar-clock"></i>
                Upcoming Events
            </h2>
            <a href="event_action.php" class="btn btn-ghost btn-sm">View All</a>
        </div>
        <div class="panel-card fade-up">
            <div class="panel-body" style="padding:8px 24px 4px;">
                <?php foreach ($upcomingEvents as $ev):
                    $startDt = new DateTime($ev['start_date']);
                    $now = new DateTime();
                    $daysUntil = $now->diff($startDt)->days;
                    $dateLabel = $daysUntil === 0 ? 'Today' : ($daysUntil === 1 ? 'Tomorrow' : "In $daysUntil days");
                ?>
                <div class="audit-row">
                    <div class="audit-dot" style="background:<?= $daysUntil <= 1 ? '#ef4444' : '#3b82f6' ?>;"></div>
                    <div class="audit-info">
                        <div class="audit-action"><?= htmlspecialchars($ev['title']) ?></div>
                        <div class="audit-desc"><?= htmlspecialchars($ev['location'] ?: 'No location set') ?></div>
                        <div class="audit-meta"><?= htmlspecialchars($startDt->format('M d, Y')) ?> &middot; <?= htmlspecialchars($dateLabel) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php elseif ($_SESSION['admin_role'] === 'Secretary'): ?>
    <!-- SECRETARY: Events + Projects + Contacts -->
    <?php if (!empty($upcomingEvents)): ?>
    <div style="margin-bottom:32px;">
        <div class="section-header">
            <h2 class="section-title">
                <i data-lucide="calendar-clock"></i>
                Upcoming Events
            </h2>
            <a href="event_action.php" class="btn btn-ghost btn-sm">View All</a>
        </div>
        <div class="panel-card fade-up">
            <div class="panel-body" style="padding:8px 24px 4px;">
                <?php foreach ($upcomingEvents as $ev):
                    $startDt = new DateTime($ev['start_date']);
                    $now = new DateTime();
                    $daysUntil = $now->diff($startDt)->days;
                    $dateLabel = $daysUntil === 0 ? 'Today' : ($daysUntil === 1 ? 'Tomorrow' : "In $daysUntil days");
                ?>
                <div class="audit-row">
                    <div class="audit-dot" style="background:<?= $daysUntil <= 1 ? '#ef4444' : '#3b82f6' ?>;"></div>
                    <div class="audit-info">
                        <div class="audit-action"><?= htmlspecialchars($ev['title']) ?></div>
                        <div class="audit-desc"><?= htmlspecialchars($ev['location'] ?: 'No location set') ?></div>
                        <div class="audit-meta"><?= htmlspecialchars($startDt->format('M d, Y')) ?> &middot; <?= htmlspecialchars($dateLabel) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div style="margin-bottom:32px;">
        <div class="section-header">
            <h2 class="section-title">
                <i data-lucide="folder-open"></i>
                Active Projects
            </h2>
            <a href="project_action.php" class="btn btn-ghost btn-sm">View All</a>
        </div>
        <div class="grid-quick" style="grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;">
            <div class="stat-card fade-up">
                <div class="stat-icon" style="background:#dbeafe;"><i data-lucide="folder" style="width:24px;height:24px;color:#2563eb;"></i></div>
                <div class="stat-value"><?= (int)$totalProjects ?></div>
                <div class="stat-label">Total Projects</div>
            </div>
            <div class="stat-card fade-up">
                <div class="stat-icon" style="background:#fef9c3;"><i data-lucide="calendar" style="width:24px;height:24px;color:#eab308;"></i></div>
                <div class="stat-value"><?= (int)$totalEvents ?></div>
                <div class="stat-label">Total Events</div>
            </div>
            <div class="stat-card fade-up">
                <div class="stat-icon" style="background:#fce7f3;"><i data-lucide="mail" style="width:24px;height:24px;color:#ec4899;"></i></div>
                <div class="stat-value"><?= (int)$contactUnread ?></div>
                <div class="stat-label">Unread Messages</div>
            </div>
        </div>
    </div>

    <?php elseif ($_SESSION['admin_role'] === 'Treasurer'): ?>
    <!-- TREASURER: Donations + Reports -->
    <div style="margin-bottom:32px;">
        <div class="section-header">
            <h2 class="section-title">
                <i data-lucide="heart-handshake"></i>
                Donations Overview
            </h2>
        </div>
        <div class="grid-quick" style="grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;">
            <div class="stat-card fade-up">
                <div class="stat-icon" style="background:#d1fae5;"><i data-lucide="heart-handshake" style="width:24px;height:24px;color:#10b981;"></i></div>
                <div class="stat-value"><?= (int)$totalDonations ?></div>
                <div class="stat-label">Total Donations</div>
            </div>
            <div class="stat-card fade-up">
                <div class="stat-icon" style="background:#fce7f3;"><i data-lucide="indian-rupee" style="width:24px;height:24px;color:#ec4899;"></i></div>
                <div class="stat-value"><?= htmlspecialchars($donationDisplay) ?></div>
                <div class="stat-label">Total Value</div>
            </div>
        </div>
    </div>

    <?php endif; ?>

    <!-- ═══ FOOTER ═══ -->
    <div style="text-align:center;padding:20px 0 8px;border-top:1px solid #e2e8f0;margin-top:8px;">
        <p style="font-size:12px;color:#94a3b8;margin:0;">
            &copy; <?= htmlspecialchars($_ws['copyright_year']) ?> <?= htmlspecialchars($_ws['website_name']) ?> &middot; Service Above Self
        </p>
    </div>
</main>

<!-- ═══ SCRIPTS ═══ -->
<script>
    lucide.createIcons();

    function toggleSidebar() {
        const s = document.getElementById('sidebar');
        const o = document.getElementById('sidebar-overlay');
        s.classList.toggle('open');
        o.style.display = o.style.display === 'block' ? 'none' : 'block';
    }

    function handleResize() {
        const mt = document.getElementById('mobileToggle');
        const wb = document.getElementById('websiteBtn');
        if (window.innerWidth < 1024) {
            if (mt) mt.style.display = 'inline-flex';
            if (wb) wb.style.display = 'none';
        } else {
            if (mt) mt.style.display = 'none';
            if (wb) wb.style.display = 'inline-flex';
        }
    }
    window.addEventListener('resize', handleResize);
    handleResize();

    <?php if (isSuperAdmin()): ?>
    let engagementChart, donationPieChart;

    function initCharts() {
        if (engagementChart) engagementChart.destroy();
        if (donationPieChart) donationPieChart.destroy();

        const eCtx = document.getElementById('engagementChart');
        if (eCtx) {
            engagementChart = new Chart(eCtx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug'],
                    datasets: [
                        {
                            label: 'Event Participation',
                            data: [65, 59, 80, 81, 56, 55, 40, 70],
                            borderColor: '#4F46E5',
                            backgroundColor: 'rgba(79,70,229,0.08)',
                            tension: 0.4, fill: true,
                            pointRadius: 3, pointBackgroundColor: '#4F46E5',
                        },
                        {
                            label: 'New Members',
                            data: [5, 7, 3, 8, 4, 6, 2, 9],
                            borderColor: '#10B981',
                            backgroundColor: 'rgba(16,185,129,0.05)',
                            tension: 0.4, fill: false,
                            pointRadius: 3, pointBackgroundColor: '#10B981',
                        }
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 6, font: { size: 11 } } } },
                    scales: { y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' } }, x: { grid: { display: false } } }
                }
            });
        }

        const dCtx = document.getElementById('donationPieChart');
        if (dCtx) {
            donationPieChart = new Chart(dCtx.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['Money', 'Goods', 'Services'],
                    datasets: [{
                        data: [65, 25, 10],
                        backgroundColor: ['#10B981', '#F59E0B', '#3B82F6'],
                        borderWidth: 0,
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, font: { size: 11 } } } },
                    cutout: '60%',
                }
            });
        }
    }

    document.addEventListener('DOMContentLoaded', initCharts);
    <?php endif; ?>
</script>
</body>
</html>
