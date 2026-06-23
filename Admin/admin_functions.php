<?php
function isSuperAdmin() {
    return isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'super_admin';
}

function requireSuperAdmin() {
    if (!isSuperAdmin()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Access denied. Super Admin only.']);
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

function e($s) {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}
