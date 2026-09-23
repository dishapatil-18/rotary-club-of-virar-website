<?php
require_once __DIR__ . '/../admin_functions.php';

// Dynamically load profile data for the current admin role + Rotary Year
$profile = getAdminProfileData($conn);

$navItems = [
    'overview' => ['label' => 'Dashboard', 'icon' => 'layout-dashboard', 'url' => 'dashboard.php', 'section' => 'Main'],
    'projects' => ['label' => 'Projects', 'icon' => 'folder', 'url' => 'project_action.php', 'section' => 'Management'],
    'project-reports' => ['label' => 'Project Reports', 'icon' => 'file-text', 'url' => 'project_report_action.php', 'section' => 'Management'],
    'events' => ['label' => 'Events', 'icon' => 'calendar', 'url' => 'event_action.php', 'section' => 'Management'],
    'event-reports' => ['label' => 'Event Reports', 'icon' => 'clipboard-list', 'url' => 'event_report_action.php', 'section' => 'Management'],
    'members' => ['label' => 'Members', 'icon' => 'users', 'url' => 'member_action.php', 'section' => 'Management'],
    'member-list' => ['label' => 'Member List', 'icon' => 'list', 'url' => 'member_List.php', 'section' => 'Management'],
    'donations' => ['label' => 'Donations', 'icon' => 'heart-handshake', 'url' => 'donation_action.php', 'section' => 'Management'],
    'donation-reports' => ['label' => 'Donation Reports', 'icon' => 'receipt', 'url' => 'donation_report_action.php', 'section' => 'Management'],
    'collaborations' => ['label' => 'Collaborations', 'icon' => 'handshake', 'url' => 'collaboration_action.php', 'section' => 'Management'],
    'collaboration-reports' => ['label' => 'Collab Reports', 'icon' => 'bar-chart-3', 'url' => 'collaboration_report.php', 'section' => 'Management'],
    'media' => ['label' => 'Upload Media', 'icon' => 'image', 'url' => 'admin_add_media.php', 'section' => 'Content'],
    'gallery' => ['label' => 'Gallery', 'icon' => 'images', 'url' => 'gallery_list.php', 'section' => 'Content'],
    'polls' => ['label' => 'Event Polls', 'icon' => 'vote', 'url' => 'addEvent_poll.php', 'section' => 'Content'],
    'voters' => ['label' => 'Poll Voters', 'icon' => 'users', 'url' => 'pollVoter_list.php', 'section' => 'Content'],
    'admins' => ['label' => 'Admin Accounts', 'icon' => 'shield', 'url' => 'admins_management.php', 'section' => 'Administration', 'roles' => ['super_admin']],
    'rotary-years' => ['label' => 'Rotary Years', 'icon' => 'calendar', 'url' => 'rotary_years.php', 'section' => 'Administration', 'roles' => ['super_admin']],
    'committees' => ['label' => 'Committees', 'icon' => 'users-round', 'url' => 'committee_action.php', 'section' => 'Administration', 'roles' => ['super_admin', 'Secretary']],
    'leadership' => ['label' => 'Leadership', 'icon' => 'users', 'url' => 'leadership_transfer.php', 'section' => 'Administration', 'roles' => ['super_admin']],
    'website-settings' => ['label' => 'Website Settings', 'icon' => 'settings', 'url' => 'website_settings.php', 'section' => 'Administration', 'roles' => ['super_admin']],
    'site-content' => ['label' => 'Website Content', 'icon' => 'edit', 'url' => 'site_content.php', 'section' => 'Administration', 'roles' => ['super_admin']],
    'contact-social' => ['label' => 'Contact & Social Links', 'icon' => 'share-2', 'url' => 'contact_social_links.php', 'section' => 'Administration', 'roles' => ['super_admin']],
    'contact-messages' => ['label' => 'Contact Messages', 'icon' => 'mail', 'url' => 'contact_messages.php', 'section' => 'Administration', 'roles' => ['super_admin', 'President', 'Secretary', 'Treasurer']],
    'audit-logs' => ['label' => 'Audit Logs', 'icon' => 'scroll', 'url' => 'audit_logs.php', 'section' => 'Administration', 'roles' => ['super_admin']],
    'password' => ['label' => 'Change Password', 'icon' => 'key', 'url' => 'change_password.php', 'section' => 'System'],
];

$currentSection = '';
foreach ($navItems as $id => $item) {
    if ($id === ($activeNav ?? '')) {
        $currentSection = $item['section'];
        break;
    }
}

$sectionOrder = ['Main', 'Management', 'Content', 'Administration', 'System'];
?>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon flex-center" style="background:rgba(255,192,0,0.12); border:none;">
            <img src="../<?= htmlspecialchars($_ws['website_logo']) ?>" alt="<?= htmlspecialchars($_ws['website_short_name']) ?> Logo" style="width:40px;height:40px;border-radius:10px;object-fit:cover;" onerror="this.style.display='none';document.getElementById('logoFallback').style.display='flex'">
            <div id="logoFallback" style="display:none;width:40px;height:40px;border-radius:10px;background:#FFC000;align-items:center;justify-content:center;font-weight:800;font-size:20px;color:#0A2342;flex-shrink:0;">R</div>
        </div>
        <div class="sidebar-logo-text">
            <h1><?= htmlspecialchars($_ws['website_name']) ?></h1>
            <p>Admin Dashboard</p>
        </div>
    </div>

    <!-- Admin Profile Mini -->
    <div style="padding:12px 16px 4px;border-bottom:1px solid rgba(255,255,255,0.06);display:flex;align-items:center;gap:10px;flex-shrink:0;">
        <img src="<?= htmlspecialchars($profile['photo']) ?>"
             alt="" style="width:32px;height:32px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,192,0,0.2);"
             onerror="this.style.display='none';document.getElementById('sideAvatarFallback').style.display='flex'">
        <div id="sideAvatarFallback" style="display:none;width:32px;height:32px;border-radius:50%;background:rgba(255,192,0,0.15);align-items:center;justify-content:center;font-size:14px;color:#FFC000;flex-shrink:0;">&#x1F464;</div>
        <div style="flex:1;min-width:0;">
            <div style="font-size:12px;font-weight:600;color:rgba(255,255,255,0.85);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($profile['name']) ?></div>
            <div style="font-size:10px;color:rgba(255,255,255,0.4);"><?= htmlspecialchars($profile['role']) ?></div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($sectionOrder as $section): $hasItems = false; ?>
            <?php foreach ($navItems as $id => $item): ?>
                <?php
                $showItem = true;
                if (isset($item['roles'])) {
                    $showItem = in_array($_SESSION['admin_role'], $item['roles']);
                }
                ?>
                <?php if ($item['section'] === $section && $showItem): $hasItems = true; break; endif; ?>
            <?php endforeach; ?>
            <?php if ($hasItems): ?>
                <div class="nav-section">
                    <div class="nav-section-title"><?= htmlspecialchars($section) ?></div>
                    <?php foreach ($navItems as $id => $item): ?>
                        <?php
                        $showItem = true;
                        if (isset($item['roles'])) {
                            $showItem = in_array($_SESSION['admin_role'], $item['roles']);
                        }
                        ?>
                        <?php if ($item['section'] === $section && $showItem): ?>
                            <a href="<?= htmlspecialchars($item['url']) ?>"
                               class="nav-item <?= ($activeNav ?? '') === $id ? 'active' : '' ?>"
                               title="<?= htmlspecialchars($item['label']) ?>">
                                <i data-lucide="<?= htmlspecialchars($item['icon']) ?>"></i>
                                <span class="nav-label"><?= htmlspecialchars($item['label']) ?></span>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <a href="../index.php" class="nav-item" title="Back to Website">
            <i data-lucide="globe"></i>
            <span class="nav-label">View Website</span>
        </a>
        <a href="logout.php" class="nav-item" title="Logout" style="color:rgba(255,100,100,0.5);">
            <i data-lucide="log-out"></i>
            <span class="nav-label">Logout</span>
        </a>
    </div>
</div>

<!-- Mobile sidebar overlay -->
<div id="sidebar-overlay" style="position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:90;display:none;" onclick="toggleSidebar()"></div>

<!-- Top Header -->
<header class="top-header">
    <div class="top-header-left">
        <button onclick="toggleSidebar()" style="display:none;" class="sidebar-toggle-btn">
            <i data-lucide="menu" style="width:20px;height:20px;"></i>
        </button>
        <div class="breadcrumb">
            <a href="dashboard.php">Dashboard</a>
            <i data-lucide="chevron-right" style="width:12px;height:12px;"></i>
            <span class="current"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></span>
        </div>
    </div>

    <div class="top-header-right">
        <a href="../index.php" class="btn btn-ghost btn-sm website-btn" style="display:none;">
            <i data-lucide="external-link" style="width:16px;height:16px;"></i>
            <span>Website</span>
        </a>
        <div class="admin-profile" onclick="window.location.href='change_password.php'" style="cursor:pointer;">
            <img src="<?= htmlspecialchars($profile['photo']) ?>"
                 alt="Admin"
                 class="admin-avatar"
                 onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><rect width=%22100%22 height=%22100%22 rx=%2250%22 fill=%22%23e2e8f0%22/><text x=%2250%22 y=%2265%22 text-anchor=%22middle%22 font-size=%2240%22 fill=%22%2394a3b8%22>👤</text></svg>'">
            <div style="display:block;">
                <div class="admin-info-name"><?= htmlspecialchars($profile['name']) ?></div>
                <div class="admin-info-role"><?= htmlspecialchars($profile['role']) ?></div>
            </div>
        </div>
        <a href="logout.php" class="btn btn-ghost btn-sm" style="color:#ef4444;padding:6px 10px;border-radius:8px;" title="Logout">
            <i data-lucide="log-out" style="width:16px;height:16px;"></i>
            <span style="display:none;">Logout</span>
        </a>
    </div>
</header>

<!-- Main Content -->
<main class="main-content">
    <div class="page-header">
        <h1 class="page-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h1>
        <?php if (isset($pageSubtitle)): ?>
            <p class="page-subtitle"><?= htmlspecialchars($pageSubtitle) ?></p>
        <?php endif; ?>
    </div>
