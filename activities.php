<?php
session_start();
include 'includes/db_connect.php';
require_once __DIR__ . '/includes/csrf_helper.php';

// ===================== USER / ROLE LOGIC =====================
$userId   = isset($_SESSION['admin_id']) ? intval($_SESSION['admin_id']) : null;
$userRoleRaw = $_SESSION['admin_role'] ?? null;
$userName = $_SESSION['admin_name'] ?? 'Guest';

if (in_array($userRoleRaw, ['President','Secretary','Treasurer'], true)) {
    $jsUserRole = 'admin';
    $isAdmin = true;
} elseif ($userRoleRaw === 'Member') {
    $jsUserRole = 'member';
    $isAdmin = false;
} else {
    $jsUserRole = 'guest';
    $isAdmin = false;
    $userId = null;
}

// ===================== AJAX ENDPOINTS =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    function execOrFail($stmt) {
        if (!$stmt->execute()) {
            echo json_encode(['status'=>'error','message'=>'Database error: '.$stmt->error]);
            exit;
        }
        return $stmt;
    }

    try {

    // --- EVENT POLLING: Submit vote ---
    if (isset($_POST['poll_event_id']) && isset($_POST['vote'])) {
        $event_id = intval($_POST['poll_event_id']);
        $vote = trim($_POST['vote']);
        $allowed = ['Yes','No','Maybe'];
        if (!in_array($vote, $allowed, true)) {
            echo json_encode(['status'=>'error','message'=>'Invalid vote']);
            exit;
        }

        if ($jsUserRole === 'member' || $jsUserRole === 'admin') {
            if (!$userId) {
                echo json_encode(['status'=>'error','message'=>'Not logged in as member']);
                exit;
            }
            $stmt = $conn->prepare("SELECT poll_id FROM event_polling WHERE event_id = ? AND member_id = ?");
            $stmt->bind_param("ii", $event_id, $userId);
            execOrFail($stmt);
            $res = $stmt->get_result();

            if ($res && $res->num_rows > 0) {
                $stmt2 = $conn->prepare("UPDATE event_polling SET is_attending = ?, guest_name = NULL, submitted_on = NOW() WHERE event_id = ? AND member_id = ?");
                $stmt2->bind_param("sii", $vote, $event_id, $userId);
                execOrFail($stmt2);
                $stmt2->close();
            } else {
                $stmt2 = $conn->prepare("INSERT INTO event_polling (event_id, member_id, is_attending, guest_name, submitted_on) VALUES (?, ?, ?, NULL, NOW())");
                $stmt2->bind_param("iis", $event_id, $userId, $vote);
                execOrFail($stmt2);
                $stmt2->close();
            }
            $stmt->close();
        } else {
            $guest_name = isset($_POST['guest_name']) ? trim($_POST['guest_name']) : '';
            if ($guest_name === '') {
                echo json_encode(['status'=>'error','message'=>'Guest name required']);
                exit;
            }
            $stmtG = $conn->prepare("INSERT INTO event_polling (event_id, member_id, is_attending, guest_name, submitted_on) VALUES (?, NULL, ?, ?, NOW())");
            $stmtG->bind_param("iss", $event_id, $vote, $guest_name);
            execOrFail($stmtG);
            $stmtG->close();
        }

        $counts = ['Yes'=>0,'No'=>0,'Maybe'=>0];
        $stmtC = $conn->prepare("SELECT is_attending, COUNT(*) as cnt FROM event_polling WHERE event_id = ? GROUP BY is_attending");
        $stmtC->bind_param("i", $event_id);
        execOrFail($stmtC);
        $resC = $stmtC->get_result();
        while ($r = $resC->fetch_assoc()) {
            $k = $r['is_attending'];
            $counts[$k] = (int)$r['cnt'];
        }
        $stmtC->close();

        echo json_encode(['status'=>'success','counts'=>$counts]);
        exit;
    }

    // --- EVENT POLLING: Fetch counts only ---
    if (isset($_POST['fetch_counts']) && isset($_POST['event_id'])) {
        $event_id = intval($_POST['event_id']);
        $counts = ['Yes'=>0,'No'=>0,'Maybe'=>0];
        $stmtC = $conn->prepare("SELECT is_attending, COUNT(*) as cnt FROM event_polling WHERE event_id = ? GROUP BY is_attending");
        $stmtC->bind_param("i", $event_id);
        execOrFail($stmtC);
        $resC = $stmtC->get_result();
        while ($r = $resC->fetch_assoc()) {
            $k = $r['is_attending'];
            $counts[$k] = (int)$r['cnt'];
        }
        $stmtC->close();
        echo json_encode(['status'=>'success','counts'=>$counts]);
        exit;
    }

    // --- PROJECT COLLABORATION: Submit proposal ---
    if (isset($_POST['action']) && $_POST['action'] === 'submit_collab') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $proposal = trim($_POST['proposal'] ?? '');
        if ($name === '' || $email === '' || $proposal === '') {
            echo json_encode(['success' => false, 'message' => 'All fields are required.']);
            exit;
        }
        $stmt = $conn->prepare("INSERT INTO collaborations (name, email, proposal, submitted_on) VALUES (?, ?, ?, NOW())");
        if ($stmt === false) {
            echo json_encode(['success' => false, 'message' => 'DB prepare failed.']);
            exit;
        }
        $stmt->bind_param('sss', $name, $email, $proposal);
        $ok = $stmt->execute();
        if ($ok) {
            echo json_encode(['success' => true, 'message' => 'Proposal submitted. Thank you!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'DB insert failed.']);
        }
        $stmt->close();
        exit;
    }

    echo json_encode(['status'=>'error','message'=>'Unknown POST request']);
    exit;

    } catch (Throwable $e) {
        echo json_encode(['status'=>'error','message'=>'Server error: '.$e->getMessage()]);
        exit;
    }
}

// ===================== FETCH DATA =====================

// --- Events ---
function map_event_row_to_js($row, $conn, $isAdmin = false) {
    $counts = ['Yes'=>0,'No'=>0,'Maybe'=>0];
    $stmt = $conn->prepare("SELECT is_attending, COUNT(*) AS cnt FROM event_polling WHERE event_id = ? GROUP BY is_attending");
    $stmt->bind_param("i", $row['event_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $counts[$r['is_attending']] = (int)$r['cnt'];
    }
    $stmt->close();

    $details = [];
    if ($isAdmin) {
        $stmt2 = $conn->prepare("SELECT ep.poll_id, ep.member_id, ep.is_attending, ep.guest_name, ep.submitted_on, m.name AS member_name
                                 FROM event_polling ep
                                 LEFT JOIN members m ON ep.member_id = m.member_id
                                 WHERE ep.event_id = ?
                                 ORDER BY ep.submitted_on DESC");
        $stmt2->bind_param("i", $row['event_id']);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        while ($d = $res2->fetch_assoc()) {
            $details[] = [
                'poll_id' => (int)$d['poll_id'],
                'member_id' => $d['member_id'] !== null ? (int)$d['member_id'] : null,
                'name' => $d['member_name'] ?? null,
                'is_attending' => $d['is_attending'],
                'guest_name' => $d['guest_name'],
                'submitted_on' => $d['submitted_on'],
            ];
        }
        $stmt2->close();
    }

    $isPolling = !empty($row['poll_enabled']) ? (bool)$row['poll_enabled'] : false;
    $today = strtotime('today');
    $startTs = !empty($row['start_date']) ? strtotime($row['start_date']) : null;
    $endTs = !empty($row['end_date']) ? strtotime($row['end_date']) : $startTs;

    $computedStatus = 'upcoming';
    if ($endTs !== null && $endTs < $today) {
        $computedStatus = 'completed';
    } elseif ($startTs !== null && $startTs <= $today && $endTs !== null && $endTs >= $today) {
        $computedStatus = 'ongoing';
    }

    if ($computedStatus !== 'upcoming') $isPolling = false;

    return [
        'id' => (int)$row['event_id'],
        'title' => $row['title'],
        'date' => !empty($row['start_date']) ? date('M j, Y', strtotime($row['start_date'])) : '',
        'startDate' => !empty($row['start_date']) ? $row['start_date'] : '',
        'endDate' => !empty($row['end_date']) ? $row['end_date'] : '',
        'location' => $row['location'] ?? '',
        'isPolling' => $isPolling,
        'hasCertificates' => !empty($row['certificates_enabled']) ? (bool)$row['certificates_enabled'] : false,
        'description' => $row['description'] ?? '',
        'image_url' => $row['image_url'] ?? '',
        'pollingCount' => ['yes' => $counts['Yes'], 'no' => $counts['No'], 'maybe' => $counts['Maybe']],
        'pollingDetails' => $details,
        '_type' => 'event',
        '_status' => $computedStatus
    ];
}

$allEvents = ['upcoming'=>[], 'ongoing'=>[], 'completed'=>[]];
$sql = "SELECT * FROM events ORDER BY start_date ASC";
if ($res = $conn->query($sql)) {
    while ($row = $res->fetch_assoc()) {
        $mapped = map_event_row_to_js($row, $conn, $isAdmin);
        $allEvents[$mapped['_status']][] = $mapped;
    }
    $res->free();
}

// --- Projects ---
$projects = [];
$today = date('Y-m-d');
$sql = "SELECT project_id, title, description, status, start_date, end_date, collaborator, image_url, created_at FROM projects ORDER BY created_at DESC";
if ($res = $conn->query($sql)) {
    while ($row = $res->fetch_assoc()) {
        $ps = 'upcoming';
        $sd = $row['start_date'] ?? '';
        $ed = $row['end_date'] ?? '';
        if ($ed !== '' && $ed < $today) {
            $ps = 'completed';
        } elseif ($sd !== '' && $sd <= $today && $ed !== '' && $ed >= $today) {
            $ps = 'ongoing';
        }
        $projects[] = [
            'id' => (int)$row['project_id'],
            'title' => $row['title'],
            'description' => $row['description'],
            'status' => $ps,
            'startDate' => $sd,
            'endDate' => $ed,
            'collaborator' => $row['collaborator'],
            'imageUrl' => $row['image_url'] ?: '',
            'createdAt' => $row['created_at'],
            '_type' => 'project'
        ];
    }
    $res->close();
}

// Counts for hero stats
$totalEvents = count($allEvents['upcoming']) + count($allEvents['ongoing']) + count($allEvents['completed']);
$totalProjects = count($projects);
$upcomingCount = count($allEvents['upcoming']) + count(array_filter($projects, fn($p) => $p['status'] === 'upcoming'));
$ongoingCount = count($allEvents['ongoing']) + count(array_filter($projects, fn($p) => $p['status'] === 'ongoing'));

$jsAllEvents = json_encode($allEvents, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
$jsProjects = json_encode($projects, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
$jsUserRoleEscaped = htmlspecialchars($jsUserRole, ENT_QUOTES, 'UTF-8');
$jsUserNameEscaped = htmlspecialchars($userName, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rotary Club of Virar - Our Activities</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --rotary-blue: #0A2342;
            --rotary-indigo: #4F46E5;
            --rotary-yellow: #FFC000;
            --rotary-green: #10B981;
            --rotary-coral: #F87171;
            --gold: #FFD700;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family:'Poppins',sans-serif;
            background:#f8fafc;
            color:var(--rotary-blue);
            overflow-x:hidden;
        }
        .nav-link { color:var(--rotary-blue); transition:color 0.3s; font-weight:500; }
        .nav-link:hover { color:var(--rotary-yellow); }
        .donate-button {
            background:var(--rotary-yellow); color:var(--rotary-blue);
            transition:all 0.3s; box-shadow:0 4px 15px rgba(255,192,0,0.4);
        }
        .donate-button:hover {
            background:#ffda6a; box-shadow:0 6px 20px rgba(255,192,0,0.6);
            transform:translateY(-1px);
        }
        .modal-backdrop { background:rgba(0,0,0,0.6); backdrop-filter:blur(5px); -webkit-backdrop-filter:blur(5px); }
        .social-icon { transition:all 0.3s; }
        .social-icon:hover { color:var(--rotary-yellow); text-shadow:0 0 8px rgba(255,192,0,0.8); transform:scale(1.1); }

        /* ===== HERO ===== */
        .activities-hero {
            position:relative; min-height:72vh;
            display:flex; align-items:center; justify-content:center;
            overflow:hidden;
            background:linear-gradient(135deg, #0A2342 0%, #1a2a6c 40%, #2d1b69 100%);
        }
        .activities-hero .hero-overlay {
            position:absolute; inset:0;
            background:radial-gradient(circle at 25% 45%, rgba(255,192,0,0.07) 0%, transparent 50%),
                        radial-gradient(circle at 75% 55%, rgba(79,70,229,0.1) 0%, transparent 50%);
        }
        .activities-hero .hero-shapes {
            position:absolute; inset:0; overflow:hidden; pointer-events:none;
        }
        .activities-hero .hshape {
            position:absolute; border-radius:50%; opacity:0.06;
        }
        .activities-hero .hshape-1 {
            width:550px; height:550px; background:var(--rotary-yellow);
            top:-180px; right:-120px;
            animation:floatAct 13s ease-in-out infinite;
        }
        .activities-hero .hshape-2 {
            width:380px; height:380px; background:#818cf8;
            bottom:-100px; left:-100px;
            animation:floatAct 17s ease-in-out infinite reverse;
        }
        .activities-hero .hshape-3 {
            width:280px; height:280px; background:var(--rotary-green);
            top:40%; left:60%;
            animation:floatAct 12s ease-in-out infinite 2s;
        }
        @keyframes floatAct {
            0%,100% { transform:translate(0,0) scale(1); }
            33% { transform:translate(25px,-35px) scale(1.05); }
            66% { transform:translate(-15px,20px) scale(0.95); }
        }
        .activities-hero .hero-grid {
            position:absolute; inset:0;
            background-image:radial-gradient(rgba(255,255,255,0.04) 1px, transparent 1px);
            background-size:40px 40px;
        }

        .act-badge {
            display:inline-flex; align-items:center; gap:10px;
            background:rgba(255,255,255,0.07); backdrop-filter:blur(10px);
            border:1px solid rgba(255,255,255,0.1);
            border-radius:100px; padding:6px 20px 6px 6px; margin-bottom:24px;
            animation:fadeDown 0.8s ease-out forwards; opacity:0;
        }
        .act-badge img { width:32px; height:32px; border-radius:50%; object-fit:contain; background:white; padding:3px; }
        .act-badge span { font-size:0.8rem; font-weight:600; color:rgba(255,255,255,0.8); letter-spacing:0.5px; }

        .act-title {
            font-family:'Playfair Display',serif;
            font-size:clamp(2.2rem,5.5vw,4.2rem);
            font-weight:800; line-height:1.1; color:white;
            margin-bottom:16px;
            animation:fadeUp 0.8s ease-out 0.15s forwards; opacity:0;
        }
        .act-title .highlight {
            background:linear-gradient(135deg, var(--rotary-yellow), #ffb347);
            -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
        }
        .act-sub {
            font-size:clamp(0.9rem,1.6vw,1.15rem);
            color:rgba(255,255,255,0.7); font-weight:300;
            max-width:580px; margin:0 auto 28px; line-height:1.7;
            animation:fadeUp 0.8s ease-out 0.3s forwards; opacity:0;
        }
        .act-stats {
            display:flex; gap:32px; justify-content:center; flex-wrap:wrap;
            animation:fadeUp 0.8s ease-out 0.45s forwards; opacity:0;
        }
        .act-stat { text-align:center; }
        .act-stat-num { font-size:1.8rem; font-weight:800; color:var(--rotary-yellow); }
        .act-stat-label { font-size:0.75rem; color:rgba(255,255,255,0.55); text-transform:uppercase; letter-spacing:1px; font-weight:500; }

        .act-scroll {
            position:absolute; bottom:25px; left:50%; transform:translateX(-50%);
            animation:bounceAct 2.2s infinite; color:rgba(255,255,255,0.35); font-size:1.3rem; cursor:pointer;
        }
        @keyframes bounceAct {
            0%,100% { transform:translateX(-50%) translateY(0); opacity:0.35; }
            50% { transform:translateX(-50%) translateY(8px); opacity:1; }
        }
        @keyframes fadeUp { to { opacity:1; transform:translateY(0); } }
        @keyframes fadeDown { to { opacity:1; transform:translateY(0); } }

        .fade-in-up { opacity:0; transform:translateY(30px); transition:opacity 0.6s ease-out, transform 0.6s ease-out; }
        .fade-in-up.is-visible { opacity:1; transform:translateY(0); }
        .fade-in-scale { opacity:0; transform:scale(0.92); transition:opacity 0.5s ease-out, transform 0.5s ease-out; }
        .fade-in-scale.is-visible { opacity:1; transform:scale(1); }

        /* ===== SECTION TITLE ===== */
        .section-title-wrap { text-align:center; margin-bottom:40px; }
        .section-badge {
            display:inline-block; font-size:0.7rem; font-weight:600; text-transform:uppercase;
            letter-spacing:2px; padding:5px 16px; border-radius:100px;
            background:rgba(255,192,0,0.13); color:var(--rotary-yellow); margin-bottom:10px;
        }
        .section-title {
            font-family:'Playfair Display',serif;
            font-size:clamp(1.6rem,3.5vw,2.8rem); font-weight:800; color:var(--rotary-blue);
        }
        .section-line {
            width:55px; height:3px;
            background:linear-gradient(90deg,var(--rotary-yellow),var(--rotary-indigo));
            margin:10px auto 0; border-radius:2px;
        }

        /* ===== FILTER TABS ===== */
        .filter-tabs {
            display:flex; flex-wrap:wrap; justify-content:center; gap:8px; margin-bottom:40px;
        }
        .filter-btn {
            padding:9px 22px; border-radius:100px; font-size:0.8rem; font-weight:600;
            transition:all 0.3s cubic-bezier(0.25,0.8,0.25,1);
            background:white; color:var(--rotary-blue); border:1.5px solid #e2e8f0;
            cursor:pointer; position:relative; overflow:hidden;
        }
        .filter-btn:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(10,35,66,0.08); border-color:var(--rotary-yellow); }
        .filter-btn.active {
            background:linear-gradient(135deg,var(--rotary-yellow),#ffb347);
            color:var(--rotary-blue); border-color:transparent;
            box-shadow:0 6px 20px rgba(255,192,0,0.3);
        }
        .filter-btn i { margin-right:5px; font-size:0.7rem; }

        /* ===== UNIFIED CARDS ===== */
        #activities-grid {
            display:grid;
            grid-template-columns:repeat(auto-fill, minmax(310px, 1fr));
            gap:26px;
        }
        .act-card {
            position:relative; background:white; border-radius:18px; overflow:hidden;
            cursor:pointer;
            transition:all 0.4s cubic-bezier(0.175,0.885,0.32,1.275);
            box-shadow:0 4px 20px rgba(0,0,0,0.04);
            border:1px solid rgba(0,0,0,0.04);
        }
        .act-card:hover {
            transform:translateY(-8px);
            box-shadow:0 20px 50px -12px rgba(10,35,66,0.15);
            border-color:rgba(255,192,0,0.2);
        }
        .act-card .ac-img-wrap {
            position:relative; width:100%; height:200px; overflow:hidden; background:#e2e8f0;
        }
        .act-card .ac-img-wrap img {
            width:100%; height:100%; object-fit:cover;
            transition:transform 0.6s ease;
        }
        .act-card:hover .ac-img-wrap img { transform:scale(1.08); }
        .act-card .ac-overlay {
            position:absolute; inset:0;
            background:linear-gradient(to top, rgba(0,0,0,0.55) 0%, transparent 50%);
            opacity:0; transition:opacity 0.4s ease;
            display:flex; flex-direction:column; justify-content:flex-end; padding:18px;
        }
        .act-card:hover .ac-overlay { opacity:1; }
        .act-card .ac-overlay h4 { color:white; font-size:1rem; font-weight:700; transform:translateY(8px); transition:transform 0.4s ease 0.05s; }
        .act-card:hover .ac-overlay h4 { transform:translateY(0); }
        .act-card .ac-overlay p { color:rgba(255,255,255,0.7); font-size:0.75rem; transform:translateY(8px); transition:transform 0.4s ease 0.1s; }
        .act-card:hover .ac-overlay p { transform:translateY(0); }

        .act-card .ac-type {
            position:absolute; top:12px; left:12px; z-index:2;
            font-size:0.6rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;
            padding:4px 12px; border-radius:100px;
            backdrop-filter:blur(8px); border:1px solid rgba(255,255,255,0.2);
            color:white;
        }
        .act-card .ac-type.event-type { background:rgba(79,70,229,0.4); }
        .act-card .ac-type.project-type { background:rgba(16,185,129,0.4); }

        .act-card .ac-status {
            position:absolute; top:12px; right:12px; z-index:2;
            font-size:0.6rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;
            padding:4px 10px; border-radius:100px;
            backdrop-filter:blur(8px); border:1px solid rgba(255,255,255,0.2);
            color:white;
        }
        .act-card .ac-body { padding:16px 18px 18px; }
        .act-card .ac-body h3 { font-size:1rem; font-weight:700; color:var(--rotary-blue); margin-bottom:3px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .act-card .ac-body .ac-meta { font-size:0.75rem; color:#94a3b8; display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
        .act-card .ac-body .ac-meta i { color:var(--rotary-yellow); width:14px; }

        /* ===== FULL DETAIL MODAL ===== */
        .detail-modal {
            position:fixed; inset:0; z-index:200;
            display:flex; align-items:center; justify-content:center;
            opacity:0; visibility:hidden;
            transition:all 0.4s ease;
            background:rgba(0,0,0,0.8); backdrop-filter:blur(12px); -webkit-backdrop-filter:blur(12px);
        }
        .detail-modal.active { opacity:1; visibility:visible; }
        .detail-modal .dm-content {
            background:white; border-radius:24px;
            max-width:560px; width:100%; max-height:90vh; overflow-y:auto;
            transform:scale(0.9) translateY(20px);
            transition:all 0.4s cubic-bezier(0.175,0.885,0.32,1.275);
        }
        .detail-modal.active .dm-content { transform:scale(1) translateY(0); }
        .detail-modal .dm-close {
            position:absolute; top:16px; right:16px; z-index:3;
            width:36px; height:36px; border-radius:50%;
            background:rgba(0,0,0,0.4); border:none; color:white; font-size:1.1rem;
            cursor:pointer; transition:all 0.3s;
            display:flex; align-items:center; justify-content:center;
        }
        .detail-modal .dm-close:hover { background:rgba(0,0,0,0.6); transform:rotate(90deg); }
        .detail-modal .dm-img {
            width:100%; height:240px; object-fit:cover;
            background:linear-gradient(135deg, var(--rotary-blue), #1e3a5f);
        }
        .detail-modal .dm-body { padding:24px 28px 28px; }
        .detail-modal .dm-body h2 { font-size:1.5rem; font-weight:800; color:var(--rotary-blue); margin-bottom:2px; }
        .detail-modal .dm-body .dm-role { font-size:0.8rem; font-weight:600; color:var(--rotary-yellow); text-transform:uppercase; letter-spacing:1px; margin-bottom:10px; }
        .detail-modal .dm-body .dm-desc { font-size:0.9rem; color:#64748b; line-height:1.7; margin-bottom:14px; }
        .detail-modal .dm-body .dm-info { display:flex; flex-direction:column; gap:8px; margin-bottom:16px; }
        .detail-modal .dm-body .dm-info-item { display:flex; align-items:center; gap:10px; font-size:0.85rem; color:#475569; }
        .detail-modal .dm-body .dm-info-item i { width:18px; color:var(--rotary-yellow); }

        /* Polling buttons inside modal */
        .poll-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; margin:12px 0; }
        .poll-btn { padding:12px; border-radius:12px; font-weight:700; font-size:0.9rem; border:none; cursor:pointer; transition:all 0.3s; color:white; }
        .poll-btn:hover { transform:translateY(-3px); box-shadow:0 8px 20px rgba(0,0,0,0.15); }
        .poll-btn.yes { background:var(--rotary-green); }
        .poll-btn.maybe { background:var(--rotary-yellow); color:var(--rotary-blue); }
        .poll-btn.no { background:var(--rotary-coral); }

        .poll-bar-wrap { margin:6px 0; }
        .poll-bar-label { display:flex; justify-content:space-between; font-size:0.8rem; font-weight:600; margin-bottom:2px; }
        .poll-bar { height:8px; border-radius:4px; background:#e2e8f0; overflow:hidden; }
        .poll-bar-fill { height:100%; border-radius:4px; transition:width 0.5s ease; }

        /* Collab form */
        .collab-form input, .collab-form textarea {
            width:100%; padding:10px 14px; border:1.5px solid #e2e8f0; border-radius:10px;
            font-size:0.85rem; transition:all 0.3s; outline:none;
        }
        .collab-form input:focus, .collab-form textarea:focus { border-color:var(--rotary-yellow); box-shadow:0 0 0 3px rgba(255,192,0,0.15); }

        /* ===== ANNOUNCEMENT STRIP ===== */
        .announce-wrap {
            position:relative; z-index:10;
            padding:0 16px; margin-top:-10px; margin-bottom:40px;
        }
        @media (min-width:768px) {
            .announce-wrap { padding:0 32px; margin-top:-14px; margin-bottom:48px; }
        }
        .announce-strip {
            position:relative; overflow:hidden; border-radius:16px;
            background:rgba(255,255,255,0.55);
            backdrop-filter:blur(16px); -webkit-backdrop-filter:blur(16px);
            border:1px solid rgba(255,255,255,0.25);
            box-shadow:0 8px 32px rgba(10,35,66,0.06), inset 0 1px 0 rgba(255,255,255,0.6);
            display:flex; align-items:center;
        }
        .announce-strip::before {
            content:''; position:absolute; inset:0;
            background:linear-gradient(90deg, rgba(10,35,66,0.03) 0%, rgba(255,192,0,0.05) 50%, rgba(10,35,66,0.03) 100%);
            pointer-events:none;
        }
        .announce-strip::after {
            content:''; position:absolute; inset:0; pointer-events:none; z-index:2;
            background:linear-gradient(90deg, #f8fafc 0%, transparent 6%, transparent 94%, #f8fafc 100%);
        }
        .announce-glow {
            position:absolute; inset:-1px; border-radius:16px; pointer-events:none; z-index:-1;
            background:linear-gradient(135deg, rgba(255,192,0,0.2), rgba(79,70,229,0.08), rgba(255,192,0,0.2));
            opacity:0.6; animation:glowPulse 4s ease-in-out infinite;
        }
        @keyframes glowPulse {
            0%,100% { opacity:0.4; }
            50% { opacity:0.9; }
        }
        .announce-track {
            display:flex; white-space:nowrap; position:relative; z-index:1;
            animation:scrollAnn 50s linear infinite;
            padding:10px 0;
        }
        .announce-track:hover { animation-play-state:paused; }
        .announce-track .item {
            display:inline-flex; align-items:center; gap:12px;
            padding:6px 32px; font-size:0.82rem; font-weight:500;
            color:var(--rotary-blue); flex-shrink:0; letter-spacing:0.01em;
        }
        .announce-track .item .dot {
            width:6px; height:6px; border-radius:50%; flex-shrink:0;
            background:linear-gradient(135deg, var(--rotary-yellow), #f59e0b);
            animation:pulseDot 1.8s ease-in-out infinite;
        }
        .announce-track .item .sep {
            width:1px; height:14px; background:rgba(10,35,66,0.1); flex-shrink:0; margin-left:4px;
        }
        @keyframes scrollAnn {
            0% { transform:translateX(0); }
            100% { transform:translateX(-50%); }
        }
        @keyframes pulseDot {
            0%,100% { opacity:0.5; transform:scale(0.8); }
            50% { opacity:1; transform:scale(1.2); }
        }
        .announce-strip .announce-label { display:none; }
        @media (min-width:1024px) {
            .announce-strip .announce-label {
                display:inline-flex; align-items:center; gap:8px;
                position:relative; z-index:3; padding:6px 18px 6px 20px; margin:8px 0 8px 12px;
                border-radius:10px; font-size:0.7rem; font-weight:700; text-transform:uppercase; letter-spacing:0.8px;
                background:linear-gradient(135deg, var(--rotary-blue), #1e3a5f);
                color:var(--rotary-yellow); flex-shrink:0;
                box-shadow:0 2px 12px rgba(10,35,66,0.15);
            }
            .announce-strip .announce-label i { -webkit-text-fill-color:var(--rotary-yellow); color:var(--rotary-yellow); }
        }

        /* ===== IMPACT SECTION ===== */
        .impact-grid {
            display:grid;
            grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));
            gap:20px;
        }
        .impact-card {
            background:white; border-radius:16px; padding:28px 20px; text-align:center;
            box-shadow:0 4px 20px rgba(0,0,0,0.04); border:1px solid rgba(0,0,0,0.04);
            transition:all 0.3s;
        }
        .impact-card:hover { transform:translateY(-4px); box-shadow:0 12px 30px rgba(10,35,66,0.1); }
        .impact-card .ic-num { font-size:2.2rem; font-weight:800; background:linear-gradient(135deg,var(--rotary-yellow),#ffb347); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }
        .impact-card .ic-label { font-size:0.8rem; color:#94a3b8; font-weight:500; margin-top:2px; }

        /* ===== SCROLL TO TOP ===== */
        #scroll-to-top {
            background:var(--rotary-yellow); color:var(--rotary-blue);
            box-shadow:0 4px 15px rgba(255,192,0,0.5);
            transition:all 0.3s;
        }
        #scroll-to-top:hover {
            background:#ffda6a; box-shadow:0 6px 20px rgba(255,192,0,0.8);
            transform:scale(1.1);
        }

        /* Success modal */
        .success-modal-overlay {
            position:fixed; inset:0; z-index:300;
            background:rgba(0,0,0,0.5); backdrop-filter:blur(4px);
            display:none; align-items:center; justify-content:center;
        }
        .success-modal-overlay.active { display:flex; }

        @media (max-width:640px) {
            .activities-hero { min-height:60vh; }
            #activities-grid { grid-template-columns:1fr; gap:18px; }
            .act-card .ac-img-wrap { height:180px; }
            .filter-tabs { gap:6px; }
            .filter-btn { padding:7px 14px; font-size:0.7rem; }
            .detail-modal .dm-content { max-width:100%; margin:0 10px; max-height:85vh; border-radius:18px; }
            .detail-modal .dm-img { height:180px; }
            .detail-modal .dm-body { padding:16px 18px 22px; }
            .act-stats { gap:16px; }
            .act-stat-num { font-size:1.4rem; }
            .impact-grid { grid-template-columns:repeat(2,1fr); }
        }
        @media (min-width:641px) and (max-width:1024px) {
            #activities-grid { grid-template-columns:repeat(2,1fr); gap:22px; }
            .impact-grid { grid-template-columns:repeat(2,1fr); }
        }
        @media (min-width:1025px) {
            #activities-grid { grid-template-columns:repeat(3,1fr); }
            .impact-grid { grid-template-columns:repeat(4,1fr); }
        }

        /* Slide-in detail panel for projects */
        #project-detail-panel {
            position:fixed; top:0; right:-100%;
            height:100vh; width:min(540px,95%);
            background:#fff; box-shadow:-20px 0 50px rgba(10,35,66,0.2);
            z-index:60; transition:right 300ms ease; overflow-y:auto;
        }
        #project-detail-panel.open { right:0; }
        #project-detail-panel .pd-header {
            background:linear-gradient(90deg, #0A2342, #4F46E5); color:#fff; padding:24px;
        }
        #project-detail-panel .pd-body { padding:20px; }
        #project-detail-panel img { max-width:100%; border-radius:8px; }
    </style>
</head>
<body>

    <!-- Login Modal -->
    <div id="login-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 modal-backdrop transition-opacity duration-300 opacity-0">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm p-8 relative transform scale-95 transition-transform duration-300">
            <button onclick="closeLoginModal()" class="absolute top-4 right-4 text-gray-500 hover:text-gray-900 transition duration-150">
                <i class="fas fa-times text-xl"></i>
            </button>
            <h3 class="text-3xl font-bold mb-6 text-center" style="color:var(--rotary-blue);">Admin Login</h3>
            <form class="space-y-4" action="login.php" method="POST">
                <?= csrfField() ?>
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email / Username</label>
                    <input type="text" id="email" name="email" required class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500 transition duration-150" placeholder="admin@rotaryvirar.org">
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                    <input type="password" id="password" name="password" required class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500 transition duration-150" placeholder="••••••••">
                </div>
                <button type="submit" class="w-full py-3 mt-4 text-lg font-semibold rounded-lg bg-yellow-500 hover:bg-yellow-400 transition duration-300 shadow-lg" style="color:var(--rotary-blue);">Login</button>
            </form>
        </div>
    </div>

    <!-- Header -->
    <header class="sticky top-0 z-40 bg-white shadow-md">
        <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <img src="assets/uploads/Logo/rotary-icon.png" alt="Rotary Logo" class="w-10 h-10 rounded-full object-cover">
                <span class="text-xl font-extrabold tracking-tight" style="color:var(--rotary-blue);">Rotary Club of Virar</span>
            </div>
            <div class="hidden lg:flex flex-1 justify-center space-x-8">
                <a href="index.php" class="nav-link">Home</a>
                <a href="aboutus.php" class="nav-link">About Us</a>
                <a href="team.php" class="nav-link">Our Team</a>
                <a href="mediaGallery.php" class="nav-link">Media Gallery</a>
                <a href="contact.php" class="nav-link">Contact Us</a>
            </div>
            <div class="hidden lg:flex items-center space-x-4">
                <button onclick="openLoginModal()" class="nav-link font-semibold">Log In</button>
                <a href="donate.php" target="_blank" class="px-6 py-2 rounded-full text-base font-semibold donate-button">Donate</a>
            </div>
            <button id="mobile-menu-button" class="lg:hidden text-2xl focus:outline-none" style="color:var(--rotary-blue);" aria-label="Toggle Menu">
                <i class="fas fa-bars"></i>
            </button>
        </nav>
        <div id="mobile-menu" class="hidden lg:hidden bg-white shadow-lg pb-4 transition-all duration-300">
            <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3 flex flex-col items-center">
                <a href="index.php" class="nav-link block px-3 py-2 rounded-md text-base">Home</a>
                <a href="aboutus.php" class="nav-link block px-3 py-2 rounded-md text-base">About Us</a>
                <a href="team.php" class="nav-link block px-3 py-2 rounded-md text-base">Our Team</a>
                <a href="mediaGallery.php" class="nav-link block px-3 py-2 rounded-md text-base">Media Gallery</a>
                <a href="contact.php" class="nav-link block px-3 py-2 rounded-md text-base">Contact Us</a>
                <button onclick="openLoginModal(); toggleMobileMenu()" class="nav-link block px-3 py-2 rounded-md text-base">Log In</button>
                <a href="donate.php" target="_blank" class="w-3/4 text-center mt-2 px-6 py-2 rounded-full text-lg font-semibold donate-button">Donate</a>
            </div>
        </div>
    </header>

    <main>
        <!-- ===== HERO ===== -->
        <section class="activities-hero" id="act-hero">
            <div class="hero-overlay"></div>
            <div class="hero-shapes">
                <div class="hshape hshape-1"></div>
                <div class="hshape hshape-2"></div>
                <div class="hshape hshape-3"></div>
            </div>
            <div class="hero-grid"></div>

            <div class="relative z-10 text-center px-4 max-w-5xl mx-auto py-20 md:py-0">
                <div class="act-badge">
                    <img src="assets/uploads/Logo/rotary-icon.png" alt="Rotary Logo" onerror="this.style.display='none'">
                    <span>Rotary Club of Virar</span>
                </div>
                <h1 class="act-title">
                    Our Activities &amp; <span class="highlight">Community Impact</span>
                </h1>
                <p class="act-sub">
                    From service projects to fellowship events — explore how we create lasting change through collective action, compassion, and Rotary values.
                </p>
                <div class="act-stats">
                    <div class="act-stat">
                        <div class="act-stat-num"><?= $totalEvents + $totalProjects ?></div>
                        <div class="act-stat-label">Total Activities</div>
                    </div>
                    <div class="act-stat">
                        <div class="act-stat-num"><?= $upcomingCount + $ongoingCount ?></div>
                        <div class="act-stat-label">Active Now</div>
                    </div>
                    <div class="act-stat">
                        <div class="act-stat-num"><?= count($allEvents['completed']) + count(array_filter($projects, fn($p) => strtolower($p['status'] ?? '') === 'completed')) ?></div>
                        <div class="act-stat-label">Completed</div>
                    </div>
                </div>
            </div>
            <div class="act-scroll" onclick="document.getElementById('activities-section').scrollIntoView({behavior:'smooth'})">
                <i class="fas fa-chevron-down"></i>
            </div>
        </section>

        <!-- ===== ANNOUNCEMENT STRIP ===== -->
        <section class="announce-wrap fade-in-up">
            <div class="announce-strip shadow-lg" role="marquee" aria-label="Latest updates">
                <div class="announce-glow"></div>
                <span class="announce-label"><i class="fas fa-bullhorn"></i> Highlights</span>
                <div class="announce-track">
                    <span class="item"><span class="dot"></span> <i class="fas fa-calendar-check"></i> Annual Charity Dinner — March 15th <span class="sep"></span></span>
                    <span class="item"><span class="dot"></span> <i class="fas fa-backpack"></i> School Kit Distribution — Volunteers needed <span class="sep"></span></span>
                    <span class="item"><span class="dot"></span> <i class="fas fa-syringe"></i> Blood Donation Camp — December 10th <span class="sep"></span></span>
                    <span class="item"><span class="dot"></span> <i class="fas fa-leaf"></i> Tree Plantation Drive — 500 saplings planted <span class="sep"></span></span>
                    <span class="item"><span class="dot"></span> <i class="fas fa-hand-holding-heart"></i> Community Health Camp — January 20th <span class="sep"></span></span>
                    <span class="item"><span class="dot"></span> <i class="fas fa-calendar-check"></i> Annual Charity Dinner — March 15th <span class="sep"></span></span>
                    <span class="item"><span class="dot"></span> <i class="fas fa-backpack"></i> School Kit Distribution — Volunteers needed <span class="sep"></span></span>
                    <span class="item"><span class="dot"></span> <i class="fas fa-syringe"></i> Blood Donation Camp — December 10th <span class="sep"></span></span>
                    <span class="item"><span class="dot"></span> <i class="fas fa-leaf"></i> Tree Plantation Drive — 500 saplings planted <span class="sep"></span></span>
                    <span class="item"><span class="dot"></span> <i class="fas fa-hand-holding-heart"></i> Community Health Camp — January 20th <span class="sep"></span></span>
                </div>
            </div>
        </section>

        <!-- ===== ACTIVITIES SECTION ===== -->
        <section id="activities-section" class="pb-16 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="section-title-wrap fade-in-up">
                    <div class="section-badge">What We Do</div>
                    <h2 class="section-title">Events &amp; Projects</h2>
                    <div class="section-line"></div>
                </div>

                <!-- Filter Tabs -->
                <div class="filter-tabs fade-in-up">
                    <button data-filter="all" class="filter-btn active"><i class="fas fa-th-large"></i> All</button>
                    <button data-filter="event" class="filter-btn"><i class="fas fa-calendar-alt"></i> Events</button>
                    <button data-filter="project" class="filter-btn"><i class="fas fa-hands-helping"></i> Projects</button>
                    <button data-filter="upcoming" class="filter-btn"><i class="fas fa-clock"></i> Upcoming</button>
                    <button data-filter="ongoing" class="filter-btn"><i class="fas fa-sync-alt"></i> Ongoing</button>
                    <button data-filter="completed" class="filter-btn"><i class="fas fa-check-circle"></i> Completed</button>
                </div>

                <!-- Grid -->
                <div id="activities-grid" class="fade-in-up"></div>
            </div>
        </section>

        <!-- ===== IMPACT SECTION ===== -->
        <section class="py-16" style="background:linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%);">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="section-title-wrap fade-in-up">
                    <div class="section-badge">Our Impact</div>
                    <h2 class="section-title">Making a Difference Together</h2>
                    <div class="section-line"></div>
                </div>
                <div class="impact-grid fade-in-up">
                    <div class="impact-card"><div class="ic-num"><?= ($totalEvents + $totalProjects + 12) ?></div><div class="ic-label">Total Initiatives</div></div>
                    <div class="impact-card"><div class="ic-num">500+</div><div class="ic-label">Trees Planted</div></div>
                    <div class="impact-card"><div class="ic-num">2000+</div><div class="ic-label">Lives Impacted</div></div>
                    <div class="impact-card"><div class="ic-num"><?= (count($projects) + count($allEvents['upcoming']) + count($allEvents['completed']) + 8) ?></div><div class="ic-label">Community Drives</div></div>
                </div>
            </div>
        </section>

        <!-- ===== CTA / QUOTE SECTION ===== -->
        <section class="py-20 text-center px-4" style="background:linear-gradient(135deg, var(--rotary-blue), #1e3a5f);">
            <div class="max-w-3xl mx-auto">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-white/10 backdrop-blur-sm border border-white/20 mb-6">
                    <i class="fas fa-hands-helping text-xl" style="color:var(--rotary-yellow);"></i>
                </div>
                <blockquote class="text-2xl md:text-3xl font-bold text-white leading-snug mb-4" style="font-family:'Playfair Display',serif;">
                    "Service Above Self — Every activity is an opportunity to make a difference."
                </blockquote>
                <p class="text-white/50 text-sm font-light mb-8">Join us in creating lasting change.</p>
                <a href="contact.php" class="inline-block px-8 py-3 rounded-full font-bold text-base transition-all duration-300 hover:transform hover:scale-105" style="background:var(--rotary-yellow);color:var(--rotary-blue);box-shadow:0 6px 25px rgba(255,192,0,0.3);">
                    Get Involved
                </a>
            </div>
        </section>
    </main>

    <!-- Scroll To Top -->
    <button id="scroll-to-top" class="fixed bottom-6 right-6 p-4 rounded-full text-2xl z-30 hidden" onclick="scrollToTop()" aria-label="Scroll to Top">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- ===== DETAIL MODAL (for both events and projects) ===== -->
    <div id="detail-modal" class="detail-modal" onclick="closeDetailModal(event)">
        <button class="dm-close" onclick="closeDetailModal()"><i class="fas fa-times"></i></button>
        <div class="dm-content" onclick="event.stopPropagation()">
            <img id="dm-img" class="dm-img" src="" alt="">
            <div class="dm-body">
                <h2 id="dm-title"></h2>
                <div id="dm-role" class="dm-role"></div>
                <p id="dm-desc" class="dm-desc"></p>
                <div id="dm-info" class="dm-info"></div>
                <div id="dm-report-link" class="mt-3 mb-2"></div>
                <!-- Event polling area -->
                <div id="dm-poll-area" style="display:none;">
                    <hr class="my-4 border-gray-100">
                    <p class="font-semibold text-sm mb-3" style="color:var(--rotary-blue);">Will you attend this event?</p>
                    <div id="dm-poll-buttons" class="poll-grid">
                        <button class="poll-btn yes" onclick="submitVote('Yes')"><i class="fas fa-check mr-1"></i> Yes</button>
                        <button class="poll-btn maybe" onclick="submitVote('Maybe')"><i class="fas fa-question mr-1"></i> Maybe</button>
                        <button class="poll-btn no" onclick="submitVote('No')"><i class="fas fa-times mr-1"></i> No</button>
                    </div>
                    <div id="dm-poll-guest" style="display:none;">
                        <input type="text" id="dm-guest-name" placeholder="Enter your name" class="w-full px-4 py-2 border border-gray-300 rounded-lg text-sm mb-2 mt-2">
                        <div class="poll-grid">
                            <button class="poll-btn yes" onclick="submitGuestVote('Yes')">Yes</button>
                            <button class="poll-btn maybe" onclick="submitGuestVote('Maybe')">Maybe</button>
                            <button class="poll-btn no" onclick="submitGuestVote('No')">No</button>
                        </div>
                    </div>
                    <div id="dm-poll-results" class="mt-3 space-y-2"></div>
                    <div id="dm-poll-admin-details" style="display:none;" class="mt-4 pt-3 border-t border-gray-200">
                        <p class="font-semibold text-sm mb-2" style="color:var(--rotary-blue);">Detailed Responses</p>
                        <div id="dm-poll-admin-list" class="max-h-48 overflow-y-auto space-y-1"></div>
                    </div>
                </div>
                <!-- Project collab area -->
                <div id="dm-collab-area" style="display:none;">
                    <hr class="my-4 border-gray-100">
                    <p class="font-semibold text-sm mb-3" style="color:var(--rotary-blue);">Want to collaborate on this project?</p>
                    <div class="collab-form">
                        <input type="text" id="dm-collab-name" placeholder="Your Name / Organization" class="mb-2">
                        <input type="email" id="dm-collab-email" placeholder="Your Email" class="mb-2">
                        <textarea id="dm-collab-proposal" rows="2" maxlength="150" placeholder="Your proposal (max 150 chars)" class="mb-2 resize-none"></textarea>
                        <p id="dm-char-count" class="text-xs text-gray-400 text-right mb-2">150 characters remaining</p>
                        <button onclick="submitCollabProposal()" class="w-full py-2.5 rounded-lg font-bold text-sm transition-all duration-300 hover:shadow-lg" style="background:var(--rotary-yellow);color:var(--rotary-blue);">Submit Proposal</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== Slide-in Panel for Projects ===== -->
    <div id="project-detail-panel" aria-hidden="true">
        <div class="pd-header flex items-center justify-between">
            <div>
                <h3 id="pd-title" class="text-2xl font-bold"></h3>
                <p id="pd-status" class="text-sm opacity-90"></p>
            </div>
            <button id="pd-close" title="Close" class="px-3 py-2 rounded bg-white/10 hover:bg-white/20 text-white">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <div class="pd-body">
            <img id="pd-image" src="" alt="" class="mb-4" onerror="this.src='https://placehold.co/600x300/0A2342/FFC000?text=No+Image'">
            <p id="pd-desc" class="text-gray-700 mb-4"></p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm text-gray-600">
                <div><strong>Start:</strong> <span id="pd-start"></span></div>
                <div><strong>End:</strong> <span id="pd-end"></span></div>
                <div class="sm:col-span-2"><strong>Collaborator:</strong> <span id="pd-collab"></span></div>
            </div>
            <div class="mt-6 flex gap-3">
                <a id="pd-report-link" href="#" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg font-semibold text-sm transition-all duration-300 hover:shadow-lg" style="background:var(--rotary-indigo);color:white;"><i class="fas fa-file-alt"></i> View Full Report</a>
                <button onclick="closeProjectPanel()" class="px-4 py-2 rounded-lg font-semibold text-sm" style="background:var(--rotary-yellow);color:var(--rotary-blue);">Close</button>
            </div>
        </div>
    </div>

    <!-- ===== Success Modal (for collab) ===== -->
    <div id="success-modal" class="success-modal-overlay" onclick="closeSuccessModal(event)">
        <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-sm w-full text-center transform transition-all duration-300 scale-100" onclick="event.stopPropagation()">
            <div class="w-16 h-16 mx-auto mb-4 flex items-center justify-center rounded-full bg-green-100">
                <i class="fas fa-heart text-3xl" style="color:var(--rotary-coral);"></i>
            </div>
            <h3 class="text-2xl font-bold text-gray-800 mb-2">Proposal Submitted!</h3>
            <p class="text-gray-600 mb-6 text-sm">Thank you for your interest. We'll review and get back to you soon.</p>
            <button onclick="closeSuccessModal()" class="px-6 py-2 rounded-lg font-bold text-sm transition-all" style="background:var(--rotary-yellow);color:var(--rotary-blue);">Close</button>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script>
        // ===== SERVER DATA =====
        const SERVER_EVENTS = <?= $jsAllEvents ?: '{"upcoming":[],"completed":[]}' ?>;
        const SERVER_PROJECTS = <?= $jsProjects ?: '[]' ?>;
        const SERVER_USER_ROLE = "<?= $jsUserRoleEscaped ?>";
        const SERVER_USER_NAME = "<?= $jsUserNameEscaped ?>";

        const hasServerEvents = SERVER_EVENTS && (SERVER_EVENTS.upcoming.length || SERVER_EVENTS.ongoing.length || SERVER_EVENTS.completed.length);
        const hasServerProjects = SERVER_PROJECTS && SERVER_PROJECTS.length;

        function buildEventList() {
            const r = { upcoming:[], ongoing:[], completed:[] };
            if (hasServerEvents) {
                (SERVER_EVENTS.upcoming||[]).forEach(e => r.upcoming.push(Object.assign({}, e, { _type:'event', _status:'upcoming', imageUrl:e.image_url||'https://placehold.co/600x400/0A2342/FFC000?text=Event', isPolling:typeof e.isPolling!=='undefined'?e.isPolling:true, pollingCount:e.pollingCount||{yes:0,no:0,maybe:0}, pollingDetails:e.pollingDetails||[] })));
                (SERVER_EVENTS.ongoing||[]).forEach(e => r.ongoing.push(Object.assign({}, e, { _type:'event', _status:'ongoing', imageUrl:e.image_url||'https://placehold.co/600x400/0A2342/FFC000?text=Event', isPolling:false, pollingCount:e.pollingCount||{yes:0,no:0,maybe:0}, pollingDetails:e.pollingDetails||[] })));
                (SERVER_EVENTS.completed||[]).forEach(e => r.completed.push(Object.assign({}, e, { _type:'event', _status:'completed', imageUrl:e.image_url||'https://placehold.co/600x400/0A2342/FFC000?text=Event', isPolling:false, pollingCount:e.pollingCount||{yes:0,no:0,maybe:0}, pollingDetails:e.pollingDetails||[] })));
            }
            return r;
        }
        function buildProjectList() {
            if (!hasServerProjects) return [];
            return SERVER_PROJECTS.map(p => Object.assign({}, p, { _type:'project', imageUrl:p.imageUrl||'https://placehold.co/600x400/0A2342/FFC000?text=Project' }));
        }

        const DATA_EVENTS = buildEventList();
        const DATA_PROJECTS = buildProjectList();
        const ALL_ITEMS = [...DATA_EVENTS.upcoming, ...DATA_EVENTS.ongoing, ...DATA_EVENTS.completed, ...DATA_PROJECTS];

        // ===== DOM REFS =====
        const grid = document.getElementById('activities-grid');
        const detailModal = document.getElementById('detail-modal');
        const projectPanel = document.getElementById('project-detail-panel');

        let selectedPollEventId = null;
        let currentPollResults = { Yes:0, No:0, Maybe:0 };

        function escapeHtml(s) {
            if (!s && s!==0) return '';
            return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        // ===== CARD RENDERING =====
        function renderCards(items) {
            if (!items.length) {
                grid.innerHTML = `<div class="col-span-full text-center py-16"><i class="fas fa-folder-open text-4xl text-gray-300 mb-4"></i><p class="text-gray-400">No activities found.</p></div>`;
                return;
            }
            grid.innerHTML = items.map(item => {
                const isEvent = item._type === 'event';
                const typeClass = isEvent ? 'event-type' : 'project-type';
                const typeLabel = isEvent ? 'Event' : 'Project';
                const status = isEvent ? (item._status||'upcoming') : (item.status||'upcoming').toLowerCase();
                const imgUrl = item.imageUrl || item.image_url || 'https://placehold.co/600x400/e2e8f0/94a3b8?text=No+Image';
                const title = escapeHtml(item.title||'Untitled');
                const desc = escapeHtml((item.description||'').substring(0,80));
                const dateStr = isEvent ? (item.date||'') : (item.startDate||'');
                const location = isEvent ? (item.location||'') : (item.collaborator||'');
                return `
                <div class="act-card fade-in-scale" onclick="openDetail('${item.id}', '${item._type}')">
                    <div class="ac-img-wrap">
                        <img src="${imgUrl}" alt="${title}" loading="lazy">
                        <div class="ac-overlay">
                            <h4>${title}</h4>
                            <p>${desc}...</p>
                        </div>
                        <span class="ac-type ${typeClass}">${typeLabel}</span>
                        <span class="ac-status" style="background:${status==='completed'?'rgba(16,185,129,0.4)':status==='ongoing'?'rgba(79,70,229,0.4)':'rgba(255,192,0,0.4)'}">${status}</span>
                    </div>
                    <div class="ac-body">
                        <h3>${title}</h3>
                        <div class="ac-meta">
                            ${dateStr ? `<span><i class="fas fa-calendar-alt"></i> ${dateStr}</span>` : ''}
                            ${location ? `<span><i class="fas fa-map-marker-alt"></i> ${location}</span>` : ''}
                        </div>
                    </div>
                </div>`;
            }).join('');
            document.querySelectorAll('#activities-grid .fade-in-scale').forEach(el => revealObserver.observe(el));
        }

        // ===== FILTER =====
        function filterActivities(filter) {
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            const btn = document.querySelector(`.filter-btn[data-filter="${filter}"]`);
            if (btn) btn.classList.add('active');

            let items = [];
            if (filter === 'all') {
                items = ALL_ITEMS;
            } else if (filter === 'event') {
                items = [...DATA_EVENTS.upcoming, ...DATA_EVENTS.ongoing, ...DATA_EVENTS.completed];
            } else if (filter === 'project') {
                items = DATA_PROJECTS;
            } else if (filter === 'upcoming') {
                items = [...DATA_EVENTS.upcoming, ...DATA_PROJECTS.filter(p => p.status === 'upcoming')];
            } else if (filter === 'ongoing') {
                items = [...DATA_EVENTS.ongoing, ...DATA_PROJECTS.filter(p => p.status === 'ongoing')];
            } else if (filter === 'completed') {
                items = [...DATA_EVENTS.completed, ...DATA_PROJECTS.filter(p => p.status === 'completed')];
            }
            renderCards(items);
        }

        // ===== OPEN DETAIL =====
        function openDetail(id, type) {
            let item;
            if (type === 'event') {
                item = [...DATA_EVENTS.upcoming, ...DATA_EVENTS.ongoing, ...DATA_EVENTS.completed].find(e => e.id == id);
            } else {
                item = DATA_PROJECTS.find(p => p.id == id);
                if (item) {
                    openProjectPanel(item);
                    return;
                }
            }
            if (!item) return;

            selectedPollEventId = type === 'event' ? item.id : null;

            const img = item.imageUrl || item.image_url || 'https://placehold.co/600x400/0A2342/FFC000?text=No+Image';
            document.getElementById('dm-img').src = img;
            document.getElementById('dm-title').textContent = item.title || 'Untitled';
            document.getElementById('dm-role').textContent = type === 'event' ? (item._status||'Event').toUpperCase() : (item.status||'Project').toUpperCase();
            document.getElementById('dm-desc').textContent = item.description || '';

            const infoDiv = document.getElementById('dm-info');
            infoDiv.innerHTML = '';
            if (type === 'event') {
                if (item.startDate && item.endDate && item.startDate !== item.endDate) {
                    infoDiv.innerHTML += `<div class="dm-info-item"><i class="fas fa-calendar-alt"></i> ${item.startDate} — ${item.endDate}</div>`;
                } else if (item.date) {
                    infoDiv.innerHTML += `<div class="dm-info-item"><i class="fas fa-calendar-alt"></i> ${item.date}</div>`;
                }
                if (item.location) infoDiv.innerHTML += `<div class="dm-info-item"><i class="fas fa-map-marker-alt"></i> ${item.location}</div>`;
            } else {
                if (item.startDate) infoDiv.innerHTML += `<div class="dm-info-item"><i class="fas fa-play-circle"></i> Start: ${item.startDate}</div>`;
                if (item.endDate) infoDiv.innerHTML += `<div class="dm-info-item"><i class="fas fa-flag-checkered"></i> End: ${item.endDate}</div>`;
                if (item.collaborator) infoDiv.innerHTML += `<div class="dm-info-item"><i class="fas fa-handshake"></i> ${item.collaborator}</div>`;
            }

            // Report links
            const reportLinkEl = document.getElementById('dm-report-link');
            if (type === 'event') {
                reportLinkEl.innerHTML = `<a href="view_event_report.php?id=${item.id}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold transition-all duration-300 hover:shadow-lg" style="background:var(--rotary-indigo);color:white;"><i class="fas fa-file-alt"></i> View Full Report</a>`;
                reportLinkEl.style.display = 'block';
            } else {
                reportLinkEl.style.display = 'none';
            }

            document.getElementById('dm-poll-area').style.display = type === 'event' ? 'block' : 'none';
            document.getElementById('dm-collab-area').style.display = type === 'project' ? 'block' : 'none';

            if (type === 'event') {
                const isPolling = item.isPolling !== false;
                const role = SERVER_USER_ROLE;
                document.getElementById('dm-poll-buttons').style.display = (isPolling && role !== 'guest') ? 'grid' : 'none';
                document.getElementById('dm-poll-guest').style.display = (isPolling && role === 'guest') ? 'block' : 'none';

                const counts = item.pollingCount || {yes:0,no:0,maybe:0};
                currentPollResults = { Yes:counts.yes||0, No:counts.no||0, Maybe:counts.maybe||0 };
                renderPollResults();

                if (!isPolling) {
                    document.getElementById('dm-poll-buttons').style.display = 'none';
                    document.getElementById('dm-poll-guest').style.display = 'none';
                }

                const adminDetailsEl = document.getElementById('dm-poll-admin-details');
                const adminListEl = document.getElementById('dm-poll-admin-list');
                if (role === 'admin') {
                    const details = item.pollingDetails || [];
                    if (details.length > 0) {
                        adminListEl.innerHTML = details.map(d => {
                            const name = escapeHtml(d.name || d.guest_name || 'Unknown');
                            const vote = d.is_attending || '';
                            const voteColor = vote==='Yes' ? 'text-green-600 bg-green-50' : vote==='No' ? 'text-red-600 bg-red-50' : 'text-yellow-600 bg-yellow-50';
                            return `<div class="flex justify-between items-center py-1.5 px-2 rounded text-xs ${voteColor}">
                                <span class="font-medium">${name}</span>
                                <span class="font-semibold">${vote}</span>
                            </div>`;
                        }).join('');
                        adminDetailsEl.style.display = 'block';
                    } else {
                        adminListEl.innerHTML = '<p class="text-xs text-gray-400">No responses yet.</p>';
                        adminDetailsEl.style.display = 'block';
                    }
                } else {
                    adminDetailsEl.style.display = 'none';
                }

                const charEl = document.getElementById('dm-char-count');
                const textarea = document.getElementById('dm-collab-proposal');
                textarea.oninput = function() {
                    const rem = 150 - this.value.length;
                    charEl.textContent = rem + ' characters remaining';
                    charEl.style.color = rem <= 10 ? '#ef4444' : '#9ca3af';
                };
                charEl.textContent = '150 characters remaining';
            }

            detailModal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeDetailModal(e) {
            if (e && e.target !== detailModal && !e.target.closest('.dm-close')) return;
            detailModal.classList.remove('active');
            document.body.style.overflow = '';
        }

        // ===== POLLING =====
        function renderPollResults() {
            const el = document.getElementById('dm-poll-results');
            const total = (currentPollResults.Yes||0) + (currentPollResults.No||0) + (currentPollResults.Maybe||0);
            if (total === 0) {
                el.innerHTML = '<p class="text-xs text-gray-400 mt-2">No votes yet.</p>';
                return;
            }
            el.innerHTML = ['Yes','Maybe','No'].map(v => {
                const cnt = currentPollResults[v] || 0;
                const pct = total ? Math.round(cnt/total*100) : 0;
                const color = v==='Yes' ? '#10B981' : v==='Maybe' ? '#FFC000' : '#F87171';
                return `<div class="poll-bar-wrap">
                    <div class="poll-bar-label"><span>${v}</span><span>${cnt} (${pct}%)</span></div>
                    <div class="poll-bar"><div class="poll-bar-fill" style="width:${pct}%;background:${color}"></div></div>
                </div>`;
            }).join('');
        }

        function submitVote(vote) {
            if (!selectedPollEventId) return;
            fetch('', {
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body: new URLSearchParams({ poll_event_id: selectedPollEventId, vote: vote })
            }).then(r=>r.json()).then(j=>{
                if (j.status==='success' && j.counts) {
                    currentPollResults = { Yes:j.counts.Yes||0, No:j.counts.No||0, Maybe:j.counts.Maybe||0 };
                    renderPollResults();
                } else alert('Error: '+(j.message||'Vote failed'));
            }).catch(e=>alert('Error: '+e.message));
        }

        function submitGuestVote(vote) {
            const name = document.getElementById('dm-guest-name').value.trim();
            if (!name) { alert('Please enter your name.'); return; }
            if (!selectedPollEventId) return;
            fetch('', {
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body: new URLSearchParams({ poll_event_id: selectedPollEventId, vote: vote, guest_name: name })
            }).then(r=>r.json()).then(j=>{
                if (j.status==='success' && j.counts) {
                    currentPollResults = { Yes:j.counts.Yes||0, No:j.counts.No||0, Maybe:j.counts.Maybe||0 };
                    renderPollResults();
                } else alert('Error: '+(j.message||'Vote failed'));
            }).catch(e=>alert('Error: '+e.message));
        }

        // ===== PROJECT PANEL =====
        function openProjectPanel(project) {
            document.getElementById('pd-title').textContent = project.title||'Untitled';
            document.getElementById('pd-status').textContent = project.status||'';
            document.getElementById('pd-image').src = project.imageUrl||'https://placehold.co/600x300/0A2342/FFC000?text=No+Image';
            document.getElementById('pd-desc').textContent = project.description||'';
            document.getElementById('pd-start').textContent = project.startDate||'N/A';
            document.getElementById('pd-end').textContent = project.endDate||'N/A';
            document.getElementById('pd-collab').textContent = project.collaborator||'N/A';
            document.getElementById('pd-report-link').href = 'view_project_report.php?id=' + project.id;
            projectPanel.classList.add('open');
            projectPanel.setAttribute('aria-hidden','false');
            document.body.style.overflow = 'hidden';
        }
        function closeProjectPanel() {
            projectPanel.classList.remove('open');
            projectPanel.setAttribute('aria-hidden','true');
            document.body.style.overflow = '';
        }
        document.getElementById('pd-close')?.addEventListener('click', closeProjectPanel);

        // ===== COLLAB PROPOSAL =====
        function submitCollabProposal() {
            const name = document.getElementById('dm-collab-name').value.trim();
            const email = document.getElementById('dm-collab-email').value.trim();
            const proposal = document.getElementById('dm-collab-proposal').value.trim();
            if (!name || !email || !proposal) { alert('Please fill in all fields.'); return; }
            fetch('', {
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body: new URLSearchParams({ action:'submit_collab', name, email, proposal })
            }).then(r=>r.json()).then(d=>{
                if (d.success) {
                    document.getElementById('dm-collab-name').value = '';
                    document.getElementById('dm-collab-email').value = '';
                    document.getElementById('dm-collab-proposal').value = '';
                    document.getElementById('dm-char-count').textContent = '150 characters remaining';
                    document.getElementById('success-modal').classList.add('active');
                } else alert('Error: '+(d.message||'Submission failed'));
            }).catch(e=>alert('Error: '+e.message));
        }

        function closeSuccessModal(e) {
            if (e && e.target !== document.getElementById('success-modal') && !e.target.closest('.success-modal-overlay > div')) return;
            document.getElementById('success-modal').classList.remove('active');
        }

        // ===== LOGIN MODAL =====
        const loginModal = document.getElementById('login-modal');
        const loginContent = loginModal.querySelector('div');
        function openLoginModal() {
            loginModal.classList.remove('hidden','opacity-0');
            loginModal.classList.add('flex');
            setTimeout(() => { loginModal.classList.add('opacity-100'); loginContent.classList.remove('scale-95'); loginContent.classList.add('scale-100'); }, 50);
        }
        function closeLoginModal() {
            loginModal.classList.remove('opacity-100'); loginContent.classList.remove('scale-100'); loginContent.classList.add('scale-95');
            setTimeout(() => { loginModal.classList.add('hidden','opacity-0'); loginModal.classList.remove('flex'); }, 300);
        }
        loginModal.addEventListener('click', (e) => { if (e.target===loginModal) closeLoginModal(); });

        // ===== MOBILE MENU =====
        const menuBtn = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');
        function toggleMobileMenu() { mobileMenu.classList.toggle('hidden'); }
        menuBtn.addEventListener('click', toggleMobileMenu);
        mobileMenu.querySelectorAll('a, button').forEach(l => l.addEventListener('click', () => { if (!mobileMenu.classList.contains('hidden')) toggleMobileMenu(); }));

        // ===== SCROLL REVEAL =====
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('is-visible'); });
        }, { threshold:0.1, rootMargin:'0px 0px -40px 0px' });

        // ===== KEYBOARD =====
        document.addEventListener('keydown', (e) => {
            if (detailModal.classList.contains('active') && e.key==='Escape') closeDetailModal();
            if (projectPanel.classList.contains('open') && e.key==='Escape') closeProjectPanel();
            if (document.getElementById('success-modal').classList.contains('active') && e.key==='Escape') closeSuccessModal();
            if (!loginModal.classList.contains('hidden') && e.key==='Escape') closeLoginModal();
        });

        // ===== HERO PARALLAX =====
        document.getElementById('act-hero')?.addEventListener('mousemove', (e) => {
            const shapes = document.querySelectorAll('.activities-hero .hshape');
            const x = (e.clientX/window.innerWidth-0.5)*20;
            const y = (e.clientY/window.innerHeight-0.5)*20;
            shapes.forEach((s,i) => { const f=(i+1)*0.3; s.style.transform=`translate(${x*f}px,${y*f}px)`; });
        });

        // ===== SCROLL TO TOP =====
        const scrollBtn = document.getElementById('scroll-to-top');
        window.onscroll = function() {
            if (document.body.scrollTop>500 || document.documentElement.scrollTop>500) scrollBtn.classList.remove('hidden');
            else scrollBtn.classList.add('hidden');
        };
        function scrollToTop() { window.scrollTo({ top:0, behavior:'smooth' }); }

        // ===== INIT =====
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof lucide !== 'undefined') lucide.createIcons();
            document.querySelectorAll('.fade-in-up, .fade-in-scale').forEach(el => revealObserver.observe(el));
            filterActivities('all');
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.addEventListener('click', () => filterActivities(btn.getAttribute('data-filter')));
            });
            window.submitVote = submitVote;
            window.submitGuestVote = submitGuestVote;
        });
    </script>
</body>
</html>
