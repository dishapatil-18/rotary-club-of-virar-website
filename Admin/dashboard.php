<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}
include __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/admin_functions.php';

// Fetch real dashboard stats
$totalProjects = 0;
$r = $conn->query("SELECT COUNT(*) as c FROM projects");
if ($r) { $totalProjects = $r->fetch_assoc()['c']; }

$totalEvents = 0;
$r = $conn->query("SELECT COUNT(*) as c FROM events");
if ($r) { $totalEvents = $r->fetch_assoc()['c']; }

$totalMembers = 0;
$r = $conn->query("SELECT COUNT(*) as c FROM members");
if ($r) { $totalMembers = $r->fetch_assoc()['c']; }

$totalDonations = 0;
$r = $conn->query("SELECT COUNT(*) as c FROM donations");
if ($r) { $totalDonations = $r->fetch_assoc()['c']; }

$totalCollaborations = 0;
$r = $conn->query("SELECT COUNT(*) as c FROM collaborations");
if ($r) { $totalCollaborations = $r->fetch_assoc()['c']; }

$contactMessages = 0;
$contactUnread = 0;
$r = $conn->query("SELECT COUNT(*) as c FROM contact_messages");
if ($r) { $contactMessages = $r->fetch_assoc()['c']; }
$r = $conn->query("SHOW COLUMNS FROM contact_messages LIKE 'status'");
$hasStatusCol = $r && $r->num_rows > 0;
if ($hasStatusCol) {
    $r = $conn->query("SELECT COUNT(*) as c FROM contact_messages WHERE status = 'Unread'");
} else {
    $r = $conn->query("SELECT COUNT(*) as c FROM contact_messages WHERE is_read = 0");
}
if ($r) { $contactUnread = $r->fetch_assoc()['c']; }

$totalDonationAmount = 0;
$r = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM donations WHERE donation_type = 'Money'");
if ($r) { $totalDonationAmount = round($r->fetch_assoc()['total']); }
if ($totalDonationAmount >= 1000000) {
    $donationDisplay = '₹' . number_format($totalDonationAmount / 100000, 1) . 'L';
} elseif ($totalDonationAmount >= 1000) {
    $donationDisplay = '₹' . number_format($totalDonationAmount / 1000, 1) . 'K';
} else {
    $donationDisplay = '₹' . $totalDonationAmount;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rotary Club Admin Dashboard</title>
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

        /* ─── SIDEBAR ─── */
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

        /* ─── HEADER ─── */
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

        /* ─── MAIN ─── */
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

        /* ─── STAT CARDS ─── */
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

        /* ─── CARDS ─── */
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

        /* ─── BUTTONS ─── */
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

        /* ─── RESPONSIVE ─── */
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

        /* Badge helpers */
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
    </style>
</head>
<body>

<!-- ═══ SIDEBAR ═══ -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="logo-wrap">
            <img src="../assets/uploads/Logo/rotary-icon.png" alt="Rotary" onerror="this.outerHTML='<span style=font-size:20px;font-weight:800;color:#0A2342;>R</span>'">
        </div>
        <div class="sidebar-logo-text">
            <h1>Rotary Club Virar</h1>
            <p>Admin Dashboard</p>
        </div>
    </div>
    <div class="sidebar-profile">
        <img src="<?= htmlspecialchars($_SESSION['admin_photo'] ?? '../assets/uploads/admins/Admin.jpg') ?>" alt=""
             onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
        <div style="display:none;width:32px;height:32px;border-radius:50%;background:rgba(255,192,0,0.15);align-items:center;justify-content:center;color:#FFC000;font-size:14px;flex-shrink:0;">&#x1F464;</div>
        <div style="flex:1;min-width:0;">
            <div class="sp-name"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></div>
            <div class="sp-role"><?= htmlspecialchars($_SESSION['admin_role'] ?? 'Administrator') ?></div>
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
        <?php if (isSuperAdmin()): ?>
        <div class="nav-section"><div class="nav-section-title">Administration</div>
            <a href="admins_management.php" class="nav-item"><i data-lucide="shield"></i><span class="nav-label">Admin Accounts</span></a>
            <a href="rotary_years.php" class="nav-item"><i data-lucide="calendar"></i><span class="nav-label">Rotary Years</span></a>
            <a href="leadership_transfer.php" class="nav-item"><i data-lucide="users"></i><span class="nav-label">Leadership Management</span></a>
            <a href="site_content.php" class="nav-item"><i data-lucide="edit"></i><span class="nav-label">Website Content</span></a>
            <a href="contact_messages.php" class="nav-item"><i data-lucide="mail"></i><span class="nav-label">Contact Messages</span></a>
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
            <img src="<?= htmlspecialchars($_SESSION['admin_photo'] ?? '../assets/uploads/admins/Admin.jpg') ?>" alt="" class="admin-avatar"
                 onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><rect width=%22100%22 height=%22100%22 rx=%2250%22 fill=%22%23e2e8f0%22/><text x=%2250%22 y=%2265%22 text-anchor=%22middle%22 font-size=%2240%22 fill=%22%2394a3b8%22>👤</text></svg>'">
            <div>
                <div class="admin-info-name"><?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?></div>
                <div class="admin-info-role"><?= htmlspecialchars($_SESSION['admin_role'] ?? 'Administrator') ?></div>
            </div>
        </div>
    </div>
</header>

<!-- ═══ MAIN CONTENT ═══ -->
<main class="main-content">
    <div style="margin-bottom:28px;">
        <h1 class="page-title">Dashboard Overview</h1>
        <p class="page-subtitle">Welcome back, <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?>. Here's your club at a glance.</p>
    </div>

    <!-- ─── STAT CARDS ─── -->
    <div class="grid-quick" style="margin-bottom:32px;">
        <div class="stat-card fade-up">
            <div class="stat-icon" style="background:#eef2ff;">
                <i data-lucide="users" style="width:24px;height:24px;color:#4f46e5;"></i>
            </div>
            <div class="stat-value"><?= $totalMembers ?></div>
            <div class="stat-label">Total Members</div>
        </div>
        <div class="stat-card fade-up">
            <div class="stat-icon" style="background:#fef9c3;">
                <i data-lucide="calendar" style="width:24px;height:24px;color:#eab308;"></i>
            </div>
            <div class="stat-value"><?= $totalEvents ?></div>
            <div class="stat-label">Total Events</div>
        </div>
        <div class="stat-card fade-up">
            <div class="stat-icon" style="background:#dbeafe;">
                <i data-lucide="folder" style="width:24px;height:24px;color:#2563eb;"></i>
            </div>
            <div class="stat-value"><?= $totalProjects ?></div>
            <div class="stat-label">Total Projects</div>
        </div>
        <div class="stat-card fade-up">
            <div class="stat-icon" style="background:#d1fae5;">
                <i data-lucide="heart-handshake" style="width:24px;height:24px;color:#10b981;"></i>
            </div>
            <div class="stat-value"><?= $totalDonations ?></div>
            <div class="stat-label">Total Donations</div>
        </div>
        <div class="stat-card fade-up">
            <div class="stat-icon" style="background:#f3e8ff;">
                <i data-lucide="handshake" style="width:24px;height:24px;color:#9333ea;"></i>
            </div>
            <div class="stat-value"><?= $totalCollaborations ?></div>
            <div class="stat-label">Collaborations</div>
        </div>
        <div class="stat-card fade-up">
            <div class="stat-icon" style="background:#fce7f3;">
                <i data-lucide="mail" style="width:24px;height:24px;color:#ec4899;"></i>
            </div>
            <div class="stat-value"><?= $contactMessages ?><span style="font-size:14px;font-weight:500;color:#94a3b8;margin-left:4px;">/ <?= $contactUnread ?> unread</span></div>
            <div class="stat-label">Contact Messages</div>
        </div>
    </div>

    <!-- ─── CHARTS ROW ─── -->
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

    <!-- ─── QUICK ACCESS PANELS ─── -->
    <h2 style="font-size:18px;font-weight:700;color:#0f172a;margin:0 0 16px;">Quick Actions</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px;margin-bottom:32px;">
        <a href="project_action.php" class="panel-card" style="display:block;text-decoration:none;border-left:4px solid #eab308;padding:18px 20px;">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:42px;height:42px;border-radius:10px;background:#fef9c3;display:flex;align-items:center;justify-content:center;">
                    <i data-lucide="folder" style="width:20px;height:20px;color:#eab308;"></i>
                </div>
                <div>
                    <div style="font-size:14px;font-weight:600;color:#0f172a;">Manage Projects</div>
                    <div style="font-size:12px;color:#64748b;">Add, edit & track projects</div>
                </div>
            </div>
        </a>
        <a href="event_action.php" class="panel-card" style="display:block;text-decoration:none;border-left:4px solid #3b82f6;padding:18px 20px;">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:42px;height:42px;border-radius:10px;background:#dbeafe;display:flex;align-items:center;justify-content:center;">
                    <i data-lucide="calendar" style="width:20px;height:20px;color:#3b82f6;"></i>
                </div>
                <div>
                    <div style="font-size:14px;font-weight:600;color:#0f172a;">Manage Events</div>
                    <div style="font-size:12px;color:#64748b;">Plan & organize events</div>
                </div>
            </div>
        </a>
        <a href="member_action.php" class="panel-card" style="display:block;text-decoration:none;border-left:4px solid #10b981;padding:18px 20px;">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:42px;height:42px;border-radius:10px;background:#d1fae5;display:flex;align-items:center;justify-content:center;">
                    <i data-lucide="users" style="width:20px;height:20px;color:#10b981;"></i>
                </div>
                <div>
                    <div style="font-size:14px;font-weight:600;color:#0f172a;">Manage Members</div>
                    <div style="font-size:12px;color:#64748b;">Add & manage club members</div>
                </div>
            </div>
        </a>
        <a href="donation_action.php" class="panel-card" style="display:block;text-decoration:none;border-left:4px solid #ef4444;padding:18px 20px;">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:42px;height:42px;border-radius:10px;background:#fee2e2;display:flex;align-items:center;justify-content:center;">
                    <i data-lucide="heart-handshake" style="width:20px;height:20px;color:#ef4444;"></i>
                </div>
                <div>
                    <div style="font-size:14px;font-weight:600;color:#0f172a;">Manage Donations</div>
                    <div style="font-size:12px;color:#64748b;">Track donations & donors</div>
                </div>
            </div>
        </a>
        <a href="admin_add_media.php" class="panel-card" style="display:block;text-decoration:none;border-left:4px solid #8b5cf6;padding:18px 20px;">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:42px;height:42px;border-radius:10px;background:#f3e8ff;display:flex;align-items:center;justify-content:center;">
                    <i data-lucide="image" style="width:20px;height:20px;color:#8b5cf6;"></i>
                </div>
                <div>
                    <div style="font-size:14px;font-weight:600;color:#0f172a;">Upload Media</div>
                    <div style="font-size:12px;color:#64748b;">Add images to gallery</div>
                </div>
            </div>
        </a>
        <a href="change_password.php" class="panel-card" style="display:block;text-decoration:none;border-left:4px solid #64748b;padding:18px 20px;">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:42px;height:42px;border-radius:10px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;">
                    <i data-lucide="settings" style="width:20px;height:20px;color:#64748b;"></i>
                </div>
                <div>
                    <div style="font-size:14px;font-weight:600;color:#0f172a;">Settings</div>
                    <div style="font-size:12px;color:#64748b;">Update password & profile</div>
                </div>
            </div>
        </a>
    </div>

    <!-- ─── FOOTER ─── -->
    <div style="text-align:center;padding:20px 0 8px;border-top:1px solid #e2e8f0;margin-top:8px;">
        <p style="font-size:12px;color:#94a3b8;margin:0;">
            &copy; <?= date('Y') ?> Rotary Club of Virar &middot; Service Above Self
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

    // Responsive toggle visibility
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

    // ─── CHARTS ───
    let engagementChart, donationPieChart;

    function initCharts() {
        if (engagementChart) engagementChart.destroy();
        if (donationPieChart) donationPieChart.destroy();

        const eCtx = document.getElementById('engagementChart').getContext('2d');
        engagementChart = new Chart(eCtx, {
            type: 'line',
            data: {
                labels: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug'],
                datasets: [
                    {
                        label: 'Event Participation',
                        data: [65, 59, 80, 81, 56, 55, 40, 70],
                        borderColor: '#4F46E5',
                        backgroundColor: 'rgba(79,70,229,0.08)',
                        tension: 0.4,
                        fill: true,
                        pointRadius: 3,
                        pointBackgroundColor: '#4F46E5',
                    },
                    {
                        label: 'New Members',
                        data: [5, 7, 3, 8, 4, 6, 2, 9],
                        borderColor: '#10B981',
                        backgroundColor: 'rgba(16,185,129,0.05)',
                        tension: 0.4,
                        fill: false,
                        pointRadius: 3,
                        pointBackgroundColor: '#10B981',
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 6, font: { size: 11 } } }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' } },
                    x: { grid: { display: false } }
                }
            }
        });

        const dCtx = document.getElementById('donationPieChart');
        if (dCtx) {
            donationPieChart = new Chart(dCtx, {
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
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, font: { size: 11 } } }
                    },
                    cutout: '60%',
                }
            });
        }
    }

    document.addEventListener('DOMContentLoaded', initCharts);

    // ─── SIMULATED FEEDBACK ───
    function simulateAction(action, isSuccess = false) {
        if (isSuccess) {
            Swal.fire({ icon: 'success', title: 'Success!', text: action + ' completed!', showConfirmButton: false, timer: 2000, customClass: { popup: 'rounded-xl shadow-2xl' } });
        } else {
            Swal.fire({ icon: 'info', title: 'Action Triggered', text: 'Simulating ' + action + ' action.', showCancelButton: true, confirmButtonText: 'Proceed', cancelButtonText: 'Dismiss', customClass: { popup: 'rounded-xl shadow-2xl', confirmButton: 'bg-yellow-500 hover:bg-yellow-600', cancelButton: 'bg-gray-300 hover:bg-gray-400' } });
        }
    }

    function simulateLogout() {
        Swal.fire({
            title: 'Confirm Logout', text: 'Are you sure you want to exit?', icon: 'warning',
            showCancelButton: true, confirmButtonColor: '#F87171', cancelButtonColor: '#4F46E5', confirmButtonText: 'Logout',
            customClass: { popup: 'rounded-xl shadow-2xl' }
        }).then((r) => {
            if (r.isConfirmed) {
                Swal.fire({ title: 'Logged Out!', text: 'Redirecting...', icon: 'info', showConfirmButton: false, timer: 1500, customClass: { popup: 'rounded-xl shadow-2xl' } });
                setTimeout(() => window.location.href = 'logout.php', 1500);
            }
        });
    }
</script>
</body>
</html>