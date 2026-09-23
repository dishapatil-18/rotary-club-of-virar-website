<?php
require_once __DIR__ . '/../includes/helpers.php';

function isSuperAdmin() {
    return isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin';
}

function isContactMessagesAllowed() {
    if (isSuperAdmin()) return true;
    $allowedRoles = ['President', 'Secretary', 'Treasurer'];
    return isset($_SESSION['admin_role']) && in_array($_SESSION['admin_role'], $allowedRoles);
}

function requireSuperAdmin() {
    if (!isSuperAdmin()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Access denied. Super Admin only.']);
        exit;
    }
}

function isCommitteeManagementAllowed() {
    if (isSuperAdmin()) return true;
    $allowed = ['Secretary'];
    return isset($_SESSION['admin_role']) && in_array($_SESSION['admin_role'], $allowed);
}

function requireCommitteeAccess() {
    if (!isCommitteeManagementAllowed()) {
        echo "<script>alert('Access denied. Super Admin or Secretary only.'); window.location.href='dashboard.php';</script>";
        exit;
    }
}

function getCurrentRotaryYear($conn) {
    $r = $conn->query("SELECT id, year_name FROM rotary_years WHERE is_current = 1 LIMIT 1");
    if ($r && $row = $r->fetch_assoc()) {
        return $row;
    }
    return null;
}

function getAllRotaryYears($conn) {
    $r = $conn->query("SELECT id, year_name, is_current, created_at FROM rotary_years ORDER BY year_name DESC");
    $years = [];
    if ($r) {
        while ($row = $r->fetch_assoc()) $years[] = $row;
    }
    return $years;
}

function getLeadershipForYear($conn, $yearId) {
    $sql = "SELECT la.id as assignment_id, la.role, m.member_id, m.name, m.email, m.phone_number, m.photo_url, m.short_bio
            FROM leadership_assignments la
            JOIN members m ON la.member_id = m.member_id
            WHERE la.rotary_year_id = ?
            ORDER BY FIELD(la.role, 'President', 'Secretary', 'Treasurer')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $yearId);
    $stmt->execute();
    $res = $stmt->get_result();
    $leaders = [];
    while ($row = $res->fetch_assoc()) $leaders[] = $row;
    $stmt->close();
    return $leaders;
}

function getSiteContent($conn, $page) {
    $stmt = $conn->prepare("SELECT section, content FROM site_content WHERE page = ?");
    $stmt->bind_param("s", $page);
    $stmt->execute();
    $r = $stmt->get_result();
    $content = [];
    if ($r) {
        while ($row = $r->fetch_assoc()) $content[$row['section']] = $row['content'];
    }
    $stmt->close();
    return $content;
}

/**
 * Dynamically fetch the profile photo, name, and role for the currently logged-in admin.
 *
 * - Super Admin always sees the Rotary Club logo.
 * - President / Secretary / Treasurer are looked up from the current year's
 *   leadership assignment, pulling their name and photo from the members table.
 *
 * Returns: ['photo' => string, 'name' => string, 'role' => string]
 */
function getAdminProfileData($conn) {
    $role = $_SESSION['admin_role'] ?? '';

    // Super Admin → Rotary logo
    if ($role === 'super_admin') {
        return [
            'photo' => '../assets/uploads/Logo/rotary-icon.png',
            'name'  => 'Super Admin',
            'role'  => 'Super Administrator',
        ];
    }

    // Office bearers → look up current year leadership assignment
    $allowed = ['President', 'Secretary', 'Treasurer'];
    if (in_array($role, $allowed, true)) {
        $year = getCurrentRotaryYear($conn);
        if ($year) {
            $leaders = getLeadershipForYear($conn, $year['id']);
            foreach ($leaders as $l) {
                if ($l['role'] === $role) {
                    $photo = $l['photo_url']
                        ? ('../' . $l['photo_url'])
                        : '';
                    return [
                        'photo' => $photo,
                        'name'  => $l['name'] ?: ($_SESSION['admin_name'] ?? 'Admin'),
                        'role'  => $role,
                    ];
                }
            }
        }
    }

    // Fallback – use what is stored in session
    return [
        'photo' => $_SESSION['admin_photo'] ?? '',
        'name'  => $_SESSION['admin_name'] ?? 'Admin',
        'role'  => $_SESSION['admin_role'] ?? 'Administrator',
    ];
}
