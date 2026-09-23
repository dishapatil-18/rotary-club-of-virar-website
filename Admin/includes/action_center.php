<?php
/**
 * Action Center — Decision Dashboard
 *
 * Generates role-based action cards by reading existing modules.
 * No new tables. No data duplication. Cards appear/disappear
 * automatically based on database state.
 *
 * RCMP v2.6 — Action Center Module
 */

/**
 * Get all action cards for the given role.
 *
 * @param  mysqli $conn  Database connection
 * @param  string $role  Admin role (super_admin, President, Secretary, Treasurer)
 * @return array         Array of action card associative arrays
 */
function getActionCards($conn, $role) {
    $cards = [];

    switch ($role) {
        case 'super_admin':
            $cards = array_merge($cards, getSecurityCards($conn));
            $cards = array_merge($cards, getDonationCards($conn));
            $cards = array_merge($cards, getContactCards($conn));
            $cards = array_merge($cards, getEventCards($conn));
            $cards = array_merge($cards, getProjectCards($conn));
            break;

        case 'President':
            $cards = array_merge($cards, getDonationCards($conn));
            $cards = array_merge($cards, getContactCards($conn));
            $cards = array_merge($cards, getEventCards($conn));
            break;

        case 'Secretary':
            $cards = array_merge($cards, getProjectCards($conn));
            $cards = array_merge($cards, getEventCards($conn));
            $cards = array_merge($cards, getContactCards($conn));
            break;

        case 'Treasurer':
            $cards = array_merge($cards, getDonationCards($conn));
            $cards = array_merge($cards, getDonationReportCards($conn));
            break;
    }

    usort($cards, function ($a, $b) {
        $order = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
        return ($order[$a['priority']] ?? 4) - ($order[$b['priority']] ?? 4);
    });

    return $cards;
}

/**
 * SECURITY CARDS — Super Admin only
 */
function getSecurityCards($conn) {
    $cards = [];

    $smtpFailures = 0;
    $stmt = $conn->prepare(
        "SELECT COUNT(*) as c FROM audit_logs
         WHERE (module = 'Email' OR module = 'Communication')
         AND status = 'failed'
         AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
    );
    if ($stmt) {
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $smtpFailures = (int)($row['c'] ?? 0);
        $stmt->close();
    }

    if ($smtpFailures > 0) {
        $cards[] = [
            'icon'        => 'mail-x',
            'title'       => 'SMTP Failures Detected',
            'description' => "$smtpFailures email(s) failed to send in the last 24 hours",
            'count'       => $smtpFailures,
            'priority'    => 'critical',
            'color'       => 'red',
            'category'    => 'security',
            'action_url'  => 'audit_logs.php?module=Email&severity=WARNING',
            'action_text' => 'Investigate',
        ];
    }

    $failedLogins = 0;
    $stmt = $conn->prepare(
        "SELECT COUNT(*) as c FROM audit_logs
         WHERE (action LIKE '%Login%Fail%' OR action LIKE '%Failed Login%')
         AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
    );
    if ($stmt) {
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $failedLogins = (int)($row['c'] ?? 0);
        $stmt->close();
    }

    if ($failedLogins > 0) {
        $cards[] = [
            'icon'        => 'shield-alert',
            'title'       => 'Failed Login Attempts',
            'description' => "$failedLogins failed login attempt(s) detected in the last 24 hours",
            'count'       => $failedLogins,
            'priority'    => 'critical',
            'color'       => 'red',
            'category'    => 'security',
            'action_url'  => 'audit_logs.php?action=Login+Failed&severity=CRITICAL',
            'action_text' => 'Review',
        ];
    }

    return $cards;
}

/**
 * DONATION CARDS — All roles that have donation access
 */
function getDonationCards($conn) {
    $cards = [];

    $pending = 0;
    $stmt = $conn->prepare(
        "SELECT COUNT(*) as c FROM donations WHERE status = 'Pending Verification'"
    );
    if ($stmt) {
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $pending = (int)($row['c'] ?? 0);
        $stmt->close();
    }

    if ($pending > 0) {
        $cards[] = [
            'icon'        => 'heart-handshake',
            'title'       => 'Donations Pending Review',
            'description' => "$pending donation(s) awaiting verification",
            'count'       => $pending,
            'priority'    => 'high',
            'color'       => 'orange',
            'category'    => 'donations',
            'action_url'  => 'donation_action.php?status=Pending+Verification',
            'action_text' => 'Review Now',
        ];
    }

    $inProgress = 0;
    $stmt = $conn->prepare(
        "SELECT COUNT(*) as c FROM donations
         WHERE status NOT IN ('Pending Verification', 'Completed')"
    );
    if ($stmt) {
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $inProgress = (int)($row['c'] ?? 0);
        $stmt->close();
    }

    if ($inProgress > 0) {
        $cards[] = [
            'icon'        => 'clock',
            'title'       => 'Donations Needing Update',
            'description' => "$inProgress donation(s) in progress but not yet completed",
            'count'       => $inProgress,
            'priority'    => 'medium',
            'color'       => 'yellow',
            'category'    => 'donations',
            'action_url'  => 'donation_action.php',
            'action_text' => 'View All',
        ];
    }

    return $cards;
}

/**
 * DONATION REPORT CARDS — Treasurer only
 */
function getDonationReportCards($conn) {
    $cards = [];

    $missing = 0;
    $stmt = $conn->prepare(
        "SELECT COUNT(*) as c FROM donations d
         WHERE d.status = 'Completed'
         AND NOT EXISTS (
             SELECT 1 FROM donation_report dr WHERE dr.donation_id = d.donation_id
         )"
    );
    if ($stmt) {
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $missing = (int)($row['c'] ?? 0);
        $stmt->close();
    }

    if ($missing > 0) {
        $cards[] = [
            'icon'        => 'file-text',
            'title'       => 'Donation Reports Pending',
            'description' => "$missing completed donation(s) missing reports",
            'count'       => $missing,
            'priority'    => 'medium',
            'color'       => 'yellow',
            'category'    => 'donations',
            'action_url'  => 'donation_report_action.php',
            'action_text' => 'Create Reports',
        ];
    }

    return $cards;
}

/**
 * CONTACT MESSAGE CARDS — SA, President, Secretary, Treasurer
 */
function getContactCards($conn) {
    $cards = [];

    $unread = 0;
    $stmt = $conn->prepare(
        "SELECT COUNT(*) as c FROM contact_messages WHERE status = 'new'"
    );
    if ($stmt) {
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $unread = (int)($row['c'] ?? 0);
        $stmt->close();
    }

    if ($unread > 0) {
        $cards[] = [
            'icon'        => 'mail',
            'title'       => 'New Contact Messages',
            'description' => "$unread new message(s) awaiting your attention",
            'count'       => $unread,
            'priority'    => 'medium',
            'color'       => 'yellow',
            'category'    => 'contacts',
            'action_url'  => 'contact_messages.php',
            'action_text' => 'View Messages',
        ];
    }

    $readNoReply = 0;
    $stmt = $conn->prepare(
        "SELECT COUNT(*) as c FROM contact_messages WHERE status = 'read'"
    );
    if ($stmt) {
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $readNoReply = (int)($row['c'] ?? 0);
        $stmt->close();
    }

    if ($readNoReply > 0) {
        $cards[] = [
            'icon'        => 'reply',
            'title'       => 'Messages Awaiting Reply',
            'description' => "$readNoReply message(s) read but not yet replied",
            'count'       => $readNoReply,
            'priority'    => 'medium',
            'color'       => 'yellow',
            'category'    => 'contacts',
            'action_url'  => 'contact_messages.php?filter=read',
            'action_text' => 'Reply Now',
        ];
    }

    return $cards;
}

/**
 * EVENT CARDS — SA, President, Secretary
 */
function getEventCards($conn) {
    $cards = [];

    $upcoming = 0;
    $stmt = $conn->prepare(
        "SELECT COUNT(*) as c FROM events
         WHERE start_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)"
    );
    if ($stmt) {
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $upcoming = (int)($row['c'] ?? 0);
        $stmt->close();
    }

    if ($upcoming > 0) {
        $cards[] = [
            'icon'        => 'calendar-clock',
            'title'       => 'Upcoming Events',
            'description' => "$upcoming event(s) scheduled within the next 7 days",
            'count'       => $upcoming,
            'priority'    => 'low',
            'color'       => 'blue',
            'category'    => 'events',
            'action_url'  => 'event_action.php',
            'action_text' => 'View Events',
        ];
    }

    $missingReports = 0;
    $stmt = $conn->prepare(
        "SELECT COUNT(*) as c FROM events e
         WHERE e.end_date < CURDATE()
         AND NOT EXISTS (
             SELECT 1 FROM event_reports er WHERE er.event_id = e.event_id
         )"
    );
    if ($stmt) {
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $missingReports = (int)($row['c'] ?? 0);
        $stmt->close();
    }

    if ($missingReports > 0) {
        $cards[] = [
            'icon'        => 'clipboard-list',
            'title'       => 'Event Reports Pending',
            'description' => "$missingReports completed event(s) missing reports",
            'count'       => $missingReports,
            'priority'    => 'medium',
            'color'       => 'yellow',
            'category'    => 'events',
            'action_url'  => 'event_report_action.php',
            'action_text' => 'Submit Reports',
        ];
    }

    return $cards;
}

/**
 * PROJECT CARDS — SA, Secretary
 */
function getProjectCards($conn) {
    $cards = [];

    $active = 0;
    $stmt = $conn->prepare(
        "SELECT COUNT(*) as c FROM projects
         WHERE status IN ('Ongoing', 'Upcoming')"
    );
    if ($stmt) {
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $active = (int)($row['c'] ?? 0);
        $stmt->close();
    }

    if ($active > 0) {
        $cards[] = [
            'icon'        => 'folder-open',
            'title'       => 'Active Projects',
            'description' => "$active project(s) currently ongoing or upcoming",
            'count'       => $active,
            'priority'    => 'low',
            'color'       => 'blue',
            'category'    => 'projects',
            'action_url'  => 'project_action.php',
            'action_text' => 'View Projects',
        ];
    }

    $missingReports = 0;
    $stmt = $conn->prepare(
        "SELECT COUNT(*) as c FROM projects p
         WHERE p.status = 'Completed'
         AND NOT EXISTS (
             SELECT 1 FROM project_reports pr WHERE pr.project_id = p.project_id
         )"
    );
    if ($stmt) {
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $missingReports = (int)($row['c'] ?? 0);
        $stmt->close();
    }

    if ($missingReports > 0) {
        $cards[] = [
            'icon'        => 'file-warning',
            'title'       => 'Project Reports Pending',
            'description' => "$missingReports completed project(s) missing reports",
            'count'       => $missingReports,
            'priority'    => 'medium',
            'color'       => 'yellow',
            'category'    => 'projects',
            'action_url'  => 'project_report_action.php',
            'action_text' => 'Submit Reports',
        ];
    }

    return $cards;
}

/**
 * QUICK ACTIONS — Role-based shortcut links
 */
function getQuickActions($role) {
    $all = [
        'add-event'       => ['label' => 'Add Event',       'icon' => 'calendar-plus',   'url' => 'event_action.php',       'color' => '#3b82f6', 'bg' => '#dbeafe', 'roles' => ['super_admin', 'President', 'Secretary']],
        'add-project'     => ['label' => 'Add Project',     'icon' => 'folder-plus',      'url' => 'project_action.php',     'color' => '#eab308', 'bg' => '#fef9c3', 'roles' => ['super_admin', 'President', 'Secretary']],
        'add-donation'    => ['label' => 'Add Donation',    'icon' => 'heart-plus',       'url' => 'donation_action.php',    'color' => '#ef4444', 'bg' => '#fee2e2', 'roles' => ['super_admin', 'President', 'Treasurer']],
        'view-contacts'   => ['label' => 'View Contacts',   'icon' => 'mail',             'url' => 'contact_messages.php',   'color' => '#ec4899', 'bg' => '#fce7f3', 'roles' => ['super_admin', 'President', 'Secretary', 'Treasurer']],
        'view-audit'      => ['label' => 'View Audit Logs', 'icon' => 'scroll',           'url' => 'audit_logs.php',         'color' => '#64748b', 'bg' => '#f1f5f9', 'roles' => ['super_admin']],
        'manage-donations'=> ['label' => 'Manage Donations','icon' => 'heart-handshake',  'url' => 'donation_action.php',    'color' => '#10b981', 'bg' => '#d1fae5', 'roles' => ['Treasurer']],
        'donation-reports'=> ['label' => 'Donation Reports','icon' => 'receipt',          'url' => 'donation_report_action.php','color' => '#8b5cf6','bg' => '#f3e8ff', 'roles' => ['Treasurer']],
        'manage-members'  => ['label' => 'Manage Members',  'icon' => 'users',            'url' => 'member_action.php',      'color' => '#10b981', 'bg' => '#d1fae5', 'roles' => ['super_admin', 'President', 'Secretary']],
        'view-members'    => ['label' => 'Member List',     'icon' => 'list',             'url' => 'member_List.php',        'color' => '#6366f1', 'bg' => '#eef2ff', 'roles' => ['super_admin', 'President', 'Secretary']],
    ];

    $actions = [];
    foreach ($all as $key => $item) {
        if (in_array($role, $item['roles'])) {
            $actions[$key] = $item;
        }
    }
    return $actions;
}

/**
 * RECENT AUDIT ACTIVITY — Latest N entries (read-only)
 */
function getRecentAuditActivity($conn, $limit = 5) {
    $logs = [];
    $stmt = $conn->prepare(
        "SELECT log_id, admin_name, role, module, action, description, severity, status, created_at
         FROM audit_logs
         ORDER BY created_at DESC
         LIMIT ?"
    );
    if ($stmt) {
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
        $stmt->close();
    }
    return $logs;
}

/**
 * PRIORITY COLOR MAP — Returns CSS values for each priority level
 */
function getPriorityStyles($priority) {
    $map = [
        'critical' => [
            'border'  => '#ef4444',
            'bg'      => '#fef2f2',
            'icon_bg' => '#fee2e2',
            'icon_color' => '#ef4444',
            'badge'   => 'badge-red',
            'label'   => 'CRITICAL',
        ],
        'high' => [
            'border'  => '#f97316',
            'bg'      => '#fff7ed',
            'icon_bg' => '#ffedd5',
            'icon_color' => '#f97316',
            'badge'   => 'badge-yellow',
            'label'   => 'HIGH',
        ],
        'medium' => [
            'border'  => '#eab308',
            'bg'      => '#fefce8',
            'icon_bg' => '#fef9c3',
            'icon_color' => '#eab308',
            'badge'   => 'badge-yellow',
            'label'   => 'MEDIUM',
        ],
        'low' => [
            'border'  => '#3b82f6',
            'bg'      => '#eff6ff',
            'icon_bg' => '#dbeafe',
            'icon_color' => '#3b82f6',
            'badge'   => 'badge-blue',
            'label'   => 'LOW',
        ],
    ];
    return $map[$priority] ?? $map['medium'];
}
