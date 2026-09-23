<?php
// index.php - Home Page

// ================= SESSION & DB CONNECTION =================
require_once __DIR__ . '/includes/session_security.php';
secureSessionStart();
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/config/club_settings.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/website_settings.php';
$ws = getWebsiteSettings($conn);

function homeImg($path) {
    if (!$path) return '';
    if (str_starts_with($path, '../')) return substr($path, 3);
    return $path;
}

// ---------- Fetch Site Content ----------
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
$homeContent = getSiteContent($conn, 'home');
$aboutContent = getSiteContent($conn, 'about');
$aboutDescription = $homeContent['about_description'] ?? $aboutContent['about_description'] ?? '';

// ---------- Fetch Projects: latest Upcoming, Completed, Ongoing (one each) ----------
$project_cards = [
    'upcoming' => null,
    'completed' => null,
    'ongoing' => null,
];

$status_map = [
    'upcoming' => 'Upcoming',
    'completed' => 'Completed',
    'ongoing'   => 'Ongoing',
];

foreach ($status_map as $key => $statusVal) {
    $stmt = $conn->prepare("SELECT project_id, title, description, status, start_date, end_date, collaborator, COALESCE(image_url,'') AS image_url, created_at
                            FROM projects
                            WHERE status = ?
                            ORDER BY created_at DESC
                            LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $statusVal);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            $project_cards[$key] = $row;
        }
        $stmt->close();
    }
}

// ---------- Fetch Events: latest 3 ----------
$events = [];
$ev_sql = "SELECT event_id, title, description, location, COALESCE(image_url,'') AS image_url, 
                  start_date, end_date
           FROM events
           ORDER BY start_date DESC
           LIMIT 3";
if ($res = $conn->query($ev_sql)) {
    while ($r = $res->fetch_assoc()) $events[] = $r;
    $res->close();
}

// ---------- Fetch Gallery: latest 6 from media_gallery ----------
$gallery = [];
$g_sql = "SELECT media_id, title, COALESCE(description,'') AS caption,
                 COALESCE(media_path,'') AS file_path,
                 reference_type AS category, uploaded_by,
                 COALESCE(media_date, created_at) AS upload_date
          FROM media_gallery
          WHERE status = 'active'
          ORDER BY created_at DESC
          LIMIT 6";
if ($res = $conn->query($g_sql)) {
    while ($r = $res->fetch_assoc()) $gallery[] = $r;
    $res->close();
}

// ---------- Fetch total counts for hero stats ----------
$totalEvents = 0; $totalProjects = 0; $totalMembers = 0;
$resE = $conn->query("SELECT COUNT(*) AS c FROM events");
if ($resE) { $totalEvents = (int)$resE->fetch_assoc()['c']; }
$resP = $conn->query("SELECT COUNT(*) AS c FROM projects");
if ($resP) { $totalProjects = (int)$resP->fetch_assoc()['c']; }
$resM = $conn->query("SELECT COUNT(*) AS c FROM members WHERE status='Active'");
if ($resM) { $totalMembers = (int)$resM->fetch_assoc()['c']; }
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($ws['browser_title']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
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
            font-family: 'Poppins', sans-serif;
            background-color: #f8fafc;
            color: var(--rotary-blue);
            overflow-x: hidden;
        }
        .nav-link { color: var(--rotary-blue); transition: color 0.3s ease; font-weight: 500; white-space:nowrap; }
        .nav-link:hover { color: var(--rotary-yellow); }
        .donate-button {
            background-color: var(--rotary-yellow);
            color: var(--rotary-blue);
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(255, 192, 0, 0.4);
        }
        .donate-button:hover {
            background-color: #ffda6a;
            box-shadow: 0 6px 20px rgba(255, 192, 0, 0.6);
            transform: translateY(-1px);
        }
        .modal-backdrop { background-color:rgba(0,0,0,0.6); backdrop-filter:blur(5px); -webkit-backdrop-filter:blur(5px); }
        .social-icon { transition: all 0.3s ease; }
        .social-icon:hover { color: var(--rotary-yellow); text-shadow: 0 0 8px rgba(255,192,0,0.8); transform: scale(1.1); }

        /* ===== HERO ===== */
        .home-hero {
            position: relative;
            min-height: 85vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .home-hero .hero-bg {
            position: absolute; inset:0;
            background-size: cover; background-position: center;
            transform: scale(1.05);
            transition: transform 8s ease-out;
        }
        .home-hero.loaded .hero-bg { transform: scale(1); }
        .home-hero .hero-overlay {
            position: absolute; inset:0;
            background: linear-gradient(135deg, rgba(10,35,66,0.7) 0%, rgba(10,35,66,0.45) 50%, rgba(10,35,66,0.6) 100%);
        }
        .home-hero .hero-overlay-2 {
            position: absolute; inset:0;
            background: radial-gradient(circle at 20% 30%, rgba(255,192,0,0.06) 0%, transparent 50%),
                        radial-gradient(circle at 80% 70%, rgba(79,70,229,0.08) 0%, transparent 50%);
        }
        .hero-title {
            text-shadow: 0 4px 20px rgba(0,0,0,0.35), 0 2px 8px rgba(0,0,0,0.2);
        }
        .hero-sub {
            text-shadow: 0 2px 12px rgba(0,0,0,0.3);
        }
        .hero-content {
            padding-top: clamp(80px, 12vh, 160px);
        }
        @media (max-width:640px) {
            .hero-content { padding-top: 50px; }
        }
        .home-hero .hero-shapes { position:absolute; inset:0; overflow:hidden; pointer-events:none; }
        .home-hero .hero-shape {
            position:absolute; border-radius:50%; opacity:0.06;
        }
        .home-hero .hero-shape-1 {
            width:600px; height:600px; background:var(--rotary-yellow);
            top:-200px; right:-150px;
            animation:floatShape 12s ease-in-out infinite;
        }
        .home-hero .hero-shape-2 {
            width:400px; height:400px; background:#818cf8;
            bottom:-120px; left:-100px;
            animation:floatShape 16s ease-in-out infinite reverse;
        }
        .home-hero .hero-shape-3 {
            width:300px; height:300px; background:var(--rotary-green);
            top:40%; left:65%;
            animation:floatShape 10s ease-in-out infinite 2s;
        }
        @keyframes floatShape {
            0%,100% { transform:translate(0,0) scale(1); }
            33% { transform:translate(30px,-40px) scale(1.05); }
            66% { transform:translate(-20px,20px) scale(0.95); }
        }
        .home-hero .hero-grid-dots {
            position:absolute; inset:0;
            background-image:radial-gradient(rgba(255,255,255,0.04) 1px, transparent 1px);
            background-size:40px 40px;
        }
        .hero-badge {
            display:inline-flex; align-items:center; gap:10px;
            background:rgba(255,255,255,0.07); backdrop-filter:blur(10px);
            border:1px solid rgba(255,255,255,0.1);
            border-radius:100px; padding:6px 20px 6px 6px; margin-bottom:24px;
            animation:fadeInDown 0.8s ease-out forwards; opacity:0;
        }
        .hero-badge img {
            width:34px; height:34px; border-radius:50%;
            object-fit:contain; background:white; padding:4px;
        }
        .hero-badge span { font-size:0.82rem; font-weight:600; color:rgba(255,255,255,0.85); letter-spacing:0.5px; }
        .hero-title {
            font-family:'Playfair Display',serif;
            font-size:clamp(2rem, 5vw, 3.8rem);
            font-weight:800; line-height:1.15; color:white;
            margin-bottom:14px;
            animation:fadeInUp 0.8s ease-out 0.15s forwards; opacity:0;
        }
        .hero-title .highlight {
            background:linear-gradient(135deg, var(--rotary-yellow), #ffb347);
            -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;
        }
        .hero-sub {
            font-size:clamp(0.9rem, 1.6vw, 1.1rem);
            color:rgba(255,255,255,0.7); font-weight:300;
            max-width:580px; margin:0 auto 22px; line-height:1.6;
            animation:fadeInUp 0.8s ease-out 0.3s forwards; opacity:0;
        }
        .hero-actions {
            display:flex; flex-wrap:wrap; gap:16px; justify-content:center;
            animation:fadeInUp 0.8s ease-out 0.45s forwards; opacity:0;
        }
        .hero-btn-primary {
            padding:14px 36px; border-radius:100px; font-size:1rem; font-weight:600;
            background:linear-gradient(135deg, var(--rotary-yellow), #ffb347);
            color:var(--rotary-blue); transition:all 0.3s cubic-bezier(0.25,0.8,0.25,1);
            box-shadow:0 8px 25px rgba(255,192,0,0.35);
            display:inline-flex; align-items:center; gap:8px;
        }
        .hero-btn-primary:hover {
            transform:translateY(-3px) scale(1.02);
            box-shadow:0 12px 35px rgba(255,192,0,0.5);
        }
        .hero-btn-secondary {
            padding:14px 36px; border-radius:100px; font-size:1rem; font-weight:600;
            background:rgba(255,255,255,0.06); backdrop-filter:blur(8px);
            color:white; border:1.5px solid rgba(255,255,255,0.2);
            transition:all 0.3s cubic-bezier(0.25,0.8,0.25,1);
            display:inline-flex; align-items:center; gap:8px;
        }
        .hero-btn-secondary:hover {
            background:rgba(255,255,255,0.15);
            border-color:var(--rotary-yellow);
            transform:translateY(-3px);
        }
        .hero-stats {
            display:flex; gap:40px; justify-content:center; flex-wrap:wrap; margin-top:32px;
            animation:fadeInUp 0.8s ease-out 0.6s forwards; opacity:0;
        }
        .hero-stat-item { text-align:center; }
        .hero-stat-num { font-size:1.7rem; font-weight:800; color:var(--rotary-yellow); }
        .hero-stat-label { font-size:0.78rem; color:rgba(255,255,255,0.55); text-transform:uppercase; letter-spacing:1px; font-weight:500; }
        .hero-scroll-down {
            position:absolute; bottom:28px; left:50%; transform:translateX(-50%);
            animation:bounceDown 2.2s infinite; color:rgba(255,255,255,0.35); font-size:1.4rem; cursor:pointer;
        }
        @keyframes bounceDown {
            0%,100% { transform:translateX(-50%) translateY(0); opacity:0.35; }
            50% { transform:translateX(-50%) translateY(8px); opacity:1; }
        }

        @keyframes fadeInUp { to { opacity:1; transform:translateY(0); } }
        @keyframes fadeInDown { to { opacity:1; transform:translateY(0); } }

        .fade-in-up { opacity:0; transform:translateY(30px); transition:opacity 0.6s ease-out, transform 0.6s ease-out; }
        .fade-in-up.is-visible { opacity:1; transform:translateY(0); }
        .fade-in-scale { opacity:0; transform:scale(0.92); transition:opacity 0.5s ease-out, transform 0.5s ease-out; }
        .fade-in-scale.is-visible { opacity:1; transform:scale(1); }

        /* ===== SECTION TITLE ===== */
        .section-title-wrap { text-align:center; margin-bottom:32px; }
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

        /* ===== ABOUT SECTION ===== */
        .about-img-wrap {
            position:relative; border-radius:20px; overflow:hidden;
            box-shadow:0 20px 50px -12px rgba(10,35,66,0.15);
        }
        .about-img-wrap img {
            width:100%; height:100%; object-fit:cover;
            transition:transform 0.6s ease;
        }
        .about-img-wrap:hover img { transform:scale(1.05); }
        .about-img-wrap .about-img-accent {
            position:absolute; bottom:-8px; right:-8px;
            width:120px; height:120px;
            background:linear-gradient(135deg, var(--rotary-yellow), #ffb347);
            border-radius:20px; z-index:-1; opacity:0.3;
        }
        .about-quote {
            padding:20px 24px; border-radius:16px;
            border-left:4px solid var(--rotary-yellow);
            background:linear-gradient(135deg, rgba(255,192,0,0.06), rgba(255,192,0,0.02));
        }
        .btn-primary {
            display:inline-flex; align-items:center; gap:8px;
            padding:12px 32px; border-radius:100px; font-size:0.95rem; font-weight:600;
            background:var(--rotary-blue); color:white;
            transition:all 0.3s cubic-bezier(0.25,0.8,0.25,1);
            box-shadow:0 6px 20px rgba(10,35,66,0.15);
        }
        .btn-primary:hover { transform:translateY(-2px); box-shadow:0 10px 30px rgba(10,35,66,0.25); }
        .btn-yellow {
            display:inline-flex; align-items:center; gap:8px;
            padding:12px 32px; border-radius:100px; font-size:0.95rem; font-weight:600;
            background:linear-gradient(135deg, var(--rotary-yellow), #ffb347);
            color:var(--rotary-blue);
            transition:all 0.3s cubic-bezier(0.25,0.8,0.25,1);
            box-shadow:0 6px 20px rgba(255,192,0,0.25);
        }
        .btn-yellow:hover { transform:translateY(-2px); box-shadow:0 10px 30px rgba(255,192,0,0.4); }

        /* ===== AREAS OF ROTARY ===== */
        .area-card {
            position:relative; border-radius:18px; overflow:hidden;
            background:white; cursor:pointer;
            transition:all 0.4s cubic-bezier(0.175,0.885,0.32,1.275);
            box-shadow:0 4px 20px rgba(0,0,0,0.04);
            border:1px solid rgba(0,0,0,0.04);
        }
        .area-card:hover {
            transform:translateY(-8px);
            box-shadow:0 20px 50px -12px rgba(10,35,66,0.15);
            border-color:rgba(255,192,0,0.2);
        }
        .area-card .ac-img-wrap {
            position:relative; width:100%; height:180px; overflow:hidden;
        }
        .area-card .ac-img-wrap img {
            width:100%; height:100%; object-fit:cover;
            transition:transform 0.6s ease;
        }
        .area-card:hover .ac-img-wrap img { transform:scale(1.1); }
        .area-card .ac-overlay {
            position:absolute; inset:0;
            background:linear-gradient(to top, rgba(10,35,66,0.7) 0%, transparent 50%);
            opacity:0; transition:opacity 0.4s ease;
            display:flex; flex-direction:column; justify-content:flex-end; padding:16px;
        }
        .area-card:hover .ac-overlay { opacity:1; }
        .area-card .ac-overlay h4 { color:white; font-size:0.9rem; font-weight:700; }
        .area-card .ac-body { padding:16px 18px 18px; }
        .area-card .ac-body h3 { font-size:0.95rem; font-weight:700; color:var(--rotary-blue); margin-bottom:4px; }
        .area-card .ac-body p { font-size:0.8rem; color:#94a3b8; line-height:1.5; }

        /* ===== FEATURED ACTIVITIES CARDS ===== */
        .featured-grid {
            display:grid;
            grid-template-columns:repeat(3, 1fr);
            gap:24px;
        }
        .featured-card {
            position:relative; border-radius:18px; overflow:hidden;
            background:white; cursor:pointer;
            transition:all 0.4s cubic-bezier(0.175,0.885,0.32,1.275);
            box-shadow:0 4px 20px rgba(0,0,0,0.04);
            border:1px solid rgba(0,0,0,0.04);
        }
        .featured-card:hover {
            transform:translateY(-8px);
            box-shadow:0 20px 50px -12px rgba(10,35,66,0.15);
            border-color:rgba(255,192,0,0.2);
        }
        .featured-card .fc-img-wrap {
            position:relative; width:100%; height:200px; overflow:hidden; background:#e2e8f0;
        }
        .featured-card .fc-img-wrap img {
            width:100%; height:100%; object-fit:cover;
            transition:transform 0.6s ease;
        }
        .featured-card:hover .fc-img-wrap img { transform:scale(1.08); }
        .featured-card .fc-badge {
            position:absolute; top:12px; right:12px; z-index:2;
            font-size:0.65rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;
            padding:4px 14px; border-radius:100px;
            backdrop-filter:blur(8px);
            color:white; border:1px solid rgba(255,255,255,0.2);
        }
        .featured-card .fc-badge.event { background:rgba(79,70,229,0.6); }
        .featured-card .fc-badge.project { background:rgba(16,185,129,0.6); }
        .featured-card .fc-body { padding:18px 20px 20px; }
        .featured-card .fc-body .fc-type {
            font-size:0.7rem; font-weight:600; text-transform:uppercase; letter-spacing:1px;
            color:var(--rotary-indigo); margin-bottom:2px;
        }
        .featured-card .fc-body h3 {
            font-size:1.05rem; font-weight:700; color:var(--rotary-blue);
            margin-bottom:6px;
        }
        .featured-card .fc-body p {
            font-size:0.82rem; color:#64748b; line-height:1.6;
            display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;
        }
        .featured-card .fc-body .fc-meta {
            margin-top:12px; display:flex; gap:16px; font-size:0.75rem; color:#94a3b8;
        }
        .featured-card .fc-body .fc-meta i { width:14px; }
        .featured-card .fc-body .fc-link {
            display:inline-flex; align-items:center; gap:6px; margin-top:12px;
            font-size:0.8rem; font-weight:600; color:var(--rotary-blue);
            transition:color 0.3s;
        }
        .featured-card:hover .fc-body .fc-link { color:var(--rotary-yellow); }
        .featured-card .fc-body .fc-link i { font-size:0.65rem; transition:transform 0.3s; }
        .featured-card:hover .fc-body .fc-link i { transform:translateX(4px); }

        /* ===== GALLERY GRID ===== */
        .gallery-grid-home {
            display:grid;
            grid-template-columns:repeat(3, 1fr);
            gap:8px; border-radius:18px; overflow:hidden;
            box-shadow:0 8px 30px rgba(0,0,0,0.06);
        }
        @media (max-width:768px) {
            .gallery-grid-home { grid-template-columns:repeat(2, 1fr); gap:4px; }
        }
        .gallery-item {
            position:relative; aspect-ratio:4/3; overflow:hidden; cursor:pointer;
        }
        .gallery-item img {
            width:100%; height:100%; object-fit:cover;
            transition:transform 0.6s ease;
        }
        .gallery-item:hover img { transform:scale(1.1); }
        .gallery-item .gi-overlay {
            position:absolute; inset:0;
            background:linear-gradient(to top, rgba(10,35,66,0.7) 0%, transparent 50%);
            opacity:0; transition:opacity 0.35s ease;
            display:flex; align-items:flex-end; justify-content:center;
            color:white; font-size:0.85rem; font-weight:600; padding:16px;
        }
        .gallery-item:hover .gi-overlay { opacity:1; }
        .gallery-item .gi-overlay span {
            transform:translateY(8px); transition:transform 0.35s ease;
        }
        .gallery-item:hover .gi-overlay span { transform:translateY(0); }

        /* ===== CTA SECTION ===== */
        .cta-section {
            position:relative; overflow:hidden;
            background:linear-gradient(135deg, #0A2342 0%, #1e3a5f 50%, #2d1b69 100%);
        }
        .cta-section::before {
            content:''; position:absolute; inset:0;
            background:radial-gradient(circle at 30% 40%, rgba(255,192,0,0.06) 0%, transparent 50%),
                        radial-gradient(circle at 70% 60%, rgba(79,70,229,0.08) 0%, transparent 50%);
            pointer-events:none;
        }
        .cta-section .cta-shapes { position:absolute; inset:0; overflow:hidden; pointer-events:none; }
        .cta-section .cta-shape {
            position:absolute; border-radius:50%; opacity:0.05;
        }
        .cta-section .cta-shape-1 {
            width:400px; height:400px; background:var(--rotary-yellow);
            top:-150px; left:-100px; animation:floatShape 14s ease-in-out infinite;
        }
        .cta-section .cta-shape-2 {
            width:300px; height:300px; background:#818cf8;
            bottom:-80px; right:-60px; animation:floatShape 18s ease-in-out infinite reverse;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width:640px) {
            .hero-stats { gap:24px; }
            .featured-grid { grid-template-columns:1fr; gap:18px; }
        }
        @media (min-width:641px) and (max-width:1024px) {
            .featured-grid { grid-template-columns:repeat(2,1fr); }
        }
    </style>
</head>
<body class="text-gray-800">

    <!-- Login Modal -->
    <div id="login-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 modal-backdrop transition-opacity duration-300 opacity-0">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm p-8 relative transform scale-95 transition-transform duration-300">
            <button onclick="closeModal()" class="absolute top-4 right-4 text-gray-500 hover:text-gray-900 transition duration-150">
                <i class="fas fa-times text-xl"></i>
            </button>
            <h3 class="text-3xl font-bold mb-6 text-center" style="color: var(--rotary-blue);">Admin Login</h3>
            <p class="text-center text-gray-600 mb-6">Enter your credentials to access the admin dashboard.</p>
            <form class="space-y-4" method="POST" action="login_action.php" onsubmit="return validateLoginForm(event)">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email / Username</label>
                    <input type="text" id="email" name="email" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500 transition duration-150" placeholder="abc@example.org">
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                    <input type="password" id="password" name="password" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500 transition duration-150" placeholder="••••••••">
                </div>
                <button type="submit" class="w-full py-3 mt-4 text-lg font-semibold rounded-lg bg-yellow-500 hover:bg-yellow-700 transition duration-300 shadow-lg" style="color: var(--rotary-blue);">Login</button>
                <p id="login-error" class="text-red-600 text-center text-sm mt-2 hidden"></p>
            </form>
        </div>
    </div>

    <!-- Header / Sticky Navigation -->
    <header class="sticky top-0 z-40 bg-white shadow-md">
        <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex justify-between items-center">
            <div class="flex items-center space-x-2.5">
                <div class="w-12 h-12 flex items-center justify-center flex-shrink-0">
                    <img src="<?= e($ws['website_logo']) ?>" alt="<?= e($ws['website_short_name']) ?> Logo" class="w-full h-full object-contain rounded-full shadow-lg border-2 border-blue-900">
                </div>
                <span class="text-lg font-extrabold tracking-tight whitespace-nowrap" style="color: var(--rotary-blue);"><?= e($ws['website_name']) ?></span>
            </div>
            <div class="hidden lg:flex flex-1 justify-center space-x-5">
                <a href="#home" class="nav-link">Home</a>
                <a href="#about" class="nav-link">About Us</a>
                <a href="activities.php" class="nav-link">Activities</a>
                <a href="team.php" class="nav-link">Our Team</a>
                <a href="#gallery" class="nav-link">Media Gallery</a>
                <a href="contact.php" class="nav-link">Contact Us</a>
            </div>
            <div class="hidden lg:flex items-center space-x-4 flex-shrink-0">
                <button onclick="openModal()" class="nav-link font-semibold">Log In</button>
                <a href="donate.php" target="_blank" class="px-6 py-2 rounded-full text-base font-semibold donate-button">Donate</a>
            </div>
            <button id="mobile-menu-button" class="lg:hidden text-2xl focus:outline-none" style="color: var(--rotary-blue);" aria-label="Toggle Menu"><i class="fas fa-bars"></i></button>
        </nav>
        <div id="mobile-menu" class="hidden lg:hidden bg-white shadow-lg pb-4 transition-all duration-300">
            <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3 flex flex-col items-center">
                <a href="#home" class="nav-link block px-3 py-2 rounded-md text-base transition duration-150">Home</a>
                <a href="aboutus.php" class="nav-link block px-3 py-2 rounded-md text-base transition duration-150">About Us</a>
                <a href="activities.php" class="nav-link block px-3 py-2 rounded-md text-base transition duration-150">Activities</a>
                <a href="team.php" class="nav-link block px-3 py-2 rounded-md text-base transition duration-150">Our Team</a>
                <a href="#gallery" class="nav-link block px-3 py-2 rounded-md text-base transition duration-150">Media Gallery</a>
                <a href="contact.php" class="nav-link block px-3 py-2 rounded-md text-base transition duration-150">Contact Us</a>
                <button onclick="openModal(); toggleMobileMenu()" class="nav-link block px-3 py-2 rounded-md text-base transition duration-150">Log In</button>
                <a href="donate.php" target="_blank" class="w-3/4 text-center mt-2 px-6 py-2 rounded-full text-lg font-semibold donate-button">Donate</a>
            </div>
        </div>
    </header>

    <main>
        <!-- ===== HERO SECTION ===== -->
        <?php
        $heroBg = homeImg($homeContent['hero_image'] ?? 'assets/uploads/Logo/HeroSection3A.jpeg');
        $heroHeading = $homeContent['hero_heading'] ?? 'Serving with Gratitude,<br>Leading with <span class="highlight">Legacy</span>';
        $heroDescription = $homeContent['hero_description'] ?? 'Together, we make the world a better place. Creating impact, inspiring change, strengthening communities.';
        $heroPrimaryText = $homeContent['hero_primary_btn_text'] ?? 'View Our Activities';
        $heroPrimaryLink = $homeContent['hero_primary_btn_link'] ?? 'activities.php';
        $heroSecondaryText = $homeContent['hero_secondary_btn_text'] ?? 'Join the Movement';
        $heroSecondaryLink = $homeContent['hero_secondary_btn_link'] ?? '#contact-cta';
        $heroActivitiesCount = $homeContent['hero_activities_count'] ?? '';
        $heroMembersCount = $homeContent['hero_active_members_count'] ?? '';
        $heroYearsCount = $homeContent['hero_years_serving_count'] ?? '';
        ?>
        <section id="home" class="home-hero">
            <div class="hero-bg" style="background-image: url('<?= e($heroBg) ?>');"></div>
            <div class="hero-overlay"></div>
            <div class="hero-overlay-2"></div>
            <div class="hero-shapes">
                <div class="hero-shape hero-shape-1"></div>
                <div class="hero-shape hero-shape-2"></div>
                <div class="hero-shape hero-shape-3"></div>
            </div>
            <div class="hero-grid-dots"></div>
            <div class="hero-content relative z-10 text-base px-4 max-w-5xl mx-auto">
                
                <h3 class="hero-title"><?= $heroHeading ?></h3>
                <p class="hero-sub"><?= e($heroDescription) ?></p>
                <div class="hero-actions">
                    <a href="<?= e($heroPrimaryLink) ?>" class="hero-btn-primary"><i class="fas fa-calendar-check"></i> <?= e($heroPrimaryText) ?></a>
                    <a href="<?= e($heroSecondaryLink) ?>" class="hero-btn-secondary"><i class="fas fa-hand-holding-heart"></i> <?= e($heroSecondaryText) ?></a>
                </div>
                <div class="hero-stats">
                    <div class="hero-stat-item">
                        <div class="hero-stat-num"><?= $heroActivitiesCount !== '' ? e($heroActivitiesCount) : ($totalEvents + $totalProjects) ?></div>
                        <div class="hero-stat-label">Activities</div>
                    </div>
                    <div class="hero-stat-item">
                        <div class="hero-stat-num"><?= $heroMembersCount !== '' ? e($heroMembersCount) : $totalMembers ?></div>
                        <div class="hero-stat-label">Active Members</div>
                    </div>
                    <div class="hero-stat-item">
                        <div class="hero-stat-num"><?= $heroYearsCount !== '' ? e($heroYearsCount) : '2026' ?></div>
                        <div class="hero-stat-label">Years Serving</div>
                    </div>
                </div>
            </div>
            <div class="hero-scroll-down" onclick="document.getElementById('about').scrollIntoView({behavior:'smooth'})">
                <i class="fas fa-chevron-down"></i>
            </div>
        </section>

        <?php
        $aboutImg = homeImg($homeContent['about_image'] ?? 'assets/uploads/Logo/areas-of-focus.jpeg');
        $aboutBadge = $homeContent['about_badge'] ?? 'About Us';
        $aboutHeading = $homeContent['about_heading'] ?? 'Who We Are';
        $aboutQuote = $homeContent['about_quote_text'] ?? 'Together, we make the world a better place.';
        $aboutBtnText = $homeContent['about_btn_text'] ?? 'Learn More';
        $aboutBtnLink = $homeContent['about_btn_link'] ?? 'aboutus.php';
        ?>
        <!-- ===== WHO WE ARE ===== -->
        <section id="about" class="py-12 md:py-16 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center fade-in-up">
                    <div class="relative">
                        <div class="about-img-wrap">
                            <img src="<?= e($aboutImg) ?>" alt="Rotary Club Mission" class="w-full h-[400px] lg:h-[500px] object-cover">
                            <div class="about-img-accent"></div>
                        </div>
                    </div>
                    <div class="p-2">
                        <div class="section-badge"><?= e($aboutBadge) ?></div>
                        <h2 class="section-title text-left !mb-0"><?= e($aboutHeading) ?></h2>
                        <div class="section-line !mx-0 mb-6"></div>
                        <p class="text-base text-gray-600 mb-6 leading-relaxed"><?= e($aboutDescription ?: 'The Rotary Club of Virar is a dynamic collective of local professionals and leaders dedicated to serving humanity. We channel our passion into tangible action, focusing on sustainable projects that impact health, education, and community well-being right here in Virar and beyond.') ?></p>
                        <div class="about-quote mb-8">
                            <p class="text-lg font-semibold leading-relaxed" style="color: var(--rotary-blue);">"<?= e($aboutQuote) ?>"</p>
                        </div>
                        <a href="<?= e($aboutBtnLink) ?>" class="btn-primary"><i class="fas fa-arrow-right"></i> <?= e($aboutBtnText) ?></a>
                    </div>
                </div>
            </div>
        </section>

        <!-- ===== AREAS OF ROTARY ===== -->
        <section id="areas" class="py-12 md:py-16" style="background-color: #f7f9fc;">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 fade-in-up">
                <div class="section-title-wrap">
                    <div class="section-badge">Our Focus</div>
                    <h2 class="section-title">Areas of Rotary</h2>
                    <div class="section-line"></div>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-7 gap-2">
                    <?php
                    $areaIcons = ['fas fa-seedling', 'fas fa-graduation-cap', 'fas fa-leaf', 'fas fa-hand-holding-heart', 'fas fa-tint', 'fas fa-leaf', 'fas fa-heartbeat'];
                    $areaDefaultTitles = ['Livelihood', 'Education', 'Peace & Harmony', 'Community', 'Water & Sanitation', 'Environment', 'Health'];
                    $areaDefaultDescs = ['Skills & economic empowerment', 'School kits & literacy programs', 'Promoting peace and understanding', 'Service above self in action', 'Clean water for communities', 'Tree plantation & green drives', 'Medical camps & wellness drives'];
                    $areaDefaultImgs = ['assets/uploads/Logo/Livelihood.jpeg', 'assets/uploads/Logo/Education.jpeg', 'assets/uploads/Logo/Peace.jpeg', 'assets/uploads/Logo/Community.jpeg', 'assets/uploads/Logo/Water.jpeg', 'assets/uploads/Logo/Environment.jpeg', 'assets/uploads/Logo/Health Logo.jpeg'];
                    for ($i = 1; $i <= 7; $i++):
                        $aIcon = $homeContent['area_icon_' . $i] ?? $areaIcons[$i-1];
                        $aTitle = $homeContent['area_title_' . $i] ?? $areaDefaultTitles[$i-1];
                        $aDesc = $homeContent['area_desc_' . $i] ?? $areaDefaultDescs[$i-1];
                        $aImg = homeImg($homeContent['area_image_' . $i] ?? $areaDefaultImgs[$i-1]);
                    ?>
                    <div class="area-card">
                        <div class="ac-img-wrap">
                            <img src="<?= e($aImg) ?>" alt="<?= e($aTitle) ?>" loading="lazy">
                            <div class="ac-overlay">
                                <h4><?= e($aTitle) ?></h4>
                            </div>
                        </div>
                        <div class="ac-body text-center">
                            <i class="<?= e($aIcon) ?>" style="color:var(--rotary-yellow); font-size:1.2rem; margin-bottom:6px;"></i>
                            <h3><?= e($aTitle) ?></h3>
                            <p><?= e($aDesc) ?></p>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </section>

        <?php
        $activitiesBadge = $homeContent['activities_badge'] ?? 'Featured';
        $activitiesHeading = $homeContent['activities_heading'] ?? 'Featured Activities';
        $activitiesDesc = $homeContent['activities_description'] ?? 'Discover our latest events, projects, and community drives making an impact.';
        ?>
        <!-- ===== FEATURED ACTIVITIES ===== -->
        <section id="activities" class="py-12 md:py-16 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 fade-in-up">
                <div class="section-title-wrap">
                    <div class="section-badge"><?= e($activitiesBadge) ?></div>
                    <h2 class="section-title"><?= e($activitiesHeading) ?></h2>
                    <p class="text-gray-500 mt-3 max-w-2xl mx-auto text-sm"><?= e($activitiesDesc) ?></p>
                    <div class="section-line"></div>
                </div>

                <div class="featured-grid mb-8">
                    <?php
                    // Merge events and projects, limit to 3 total
                    $featured = [];

                    // Add events
                    foreach ($events as $ev) {
                        $badgeDate = '';
                        $sd = $ev['start_date'] ?? '';
                        $ed = $ev['end_date'] ?? '';
                        if (!empty($sd) && $sd !== '0000-00-00') {
                            $dt = strtotime($sd);
                            $badgeDate = date('M j, Y', $dt);
                            if (!empty($ed) && $ed !== $sd) {
                                $badgeDate .= ' — ' . date('M j, Y', strtotime($ed));
                            }
                        }
                        $ev_img = $ev['image_url'] ?: "https://placehold.co/600x400/e2e8f0/0A2342?text=Event";
                        $featured[] = [
                            'type' => 'event',
                            'id' => $ev['event_id'],
                            'title' => $ev['title'],
                            'desc' => $ev['description'],
                            'meta' => $badgeDate,
                            'metaIcon' => 'fas fa-calendar-alt',
                            'location' => $ev['location'] ?? '',
                            'img' => $ev_img,
                        ];
                    }

                    // Add projects
                    foreach ($project_cards as $key => $p) {
                        if ($p) {
                            $p_img = $p['image_url'] ?: "https://placehold.co/600x400/e2e8f0/0A2342?text=Project";
                            $featured[] = [
                                'type' => 'project',
                                'id' => $p['project_id'],
                                'title' => $p['title'],
                                'desc' => $p['description'],
                                'meta' => $p['status'],
                                'metaIcon' => 'fas fa-tasks',
                                'location' => '',
                                'img' => $p_img,
                            ];
                        }
                    }

                    // If nothing, show placeholders
                    if (empty($featured)):
                        for ($i=0; $i<3; $i++):
                    ?>
                    <div class="featured-card">
                        <div class="fc-img-wrap">
                            <img src="https://placehold.co/600x400/e2e8f0/0A2342?text=No+Activity" alt="No Activity" loading="lazy">
                            <span class="fc-badge event">Activity</span>
                        </div>
                        <div class="fc-body">
                            <div class="fc-type">Activity</div>
                            <h3>No Activity Available</h3>
                            <p>Check back soon for upcoming events and projects.</p>
                            <div class="fc-meta"><span><i class="fas fa-calendar-alt"></i> TBA</span></div>
                        </div>
                    </div>
                    <?php
                        endfor;
                    else:
                        $count = 0;
                        foreach ($featured as $item):
                            if ($count >= 3) break;
                            $badgeClass = $item['type'] === 'event' ? 'event' : 'project';
                            $typeLabel = $item['type'] === 'event' ? 'Event' : 'Project';
                    ?>
                    <div class="featured-card">
                        <div class="fc-img-wrap">
                            <img src="<?= e($item['img']) ?>" alt="<?= e($item['title']) ?>" loading="lazy">
                            <span class="fc-badge <?= $badgeClass ?>"><?= $typeLabel ?></span>
                        </div>
                        <div class="fc-body">
                            <div class="fc-type"><?= $typeLabel ?></div>
                            <h3><?= e(mb_strimwidth($item['title'],0,50,'...')) ?></h3>
                            <p><?= e(mb_strimwidth(strip_tags($item['desc']),0,120,'...')) ?></p>
                            <div class="fc-meta">
                                <span><i class="<?= $item['metaIcon'] ?>"></i> <?= e($item['meta'] ?: 'TBA') ?></span>
                                <?php if ($item['location']): ?>
                                <span><i class="fas fa-map-marker-alt"></i> <?= e($item['location']) ?></span>
                                <?php endif; ?>
                            </div>
                            <a href="activities.php" class="fc-link">View Details <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                    <?php
                            $count++;
                        endforeach;
                    endif;
                    ?>
                </div>

                <div class="text-center">
                    <a href="activities.php" class="btn-yellow"><i class="fas fa-calendar-check"></i> View All Activities</a>
                </div>
            </div>
        </section>

        <!-- ===== MOMENTS OF IMPACT (GALLERY) ===== -->
        <section id="gallery" class="py-12 md:py-16" style="background-color: #f7f9fc;">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 fade-in-up">
                <div class="section-title-wrap">
                    <div class="section-badge">Gallery</div>
                    <h2 class="section-title">Moments of Impact</h2>
                    <p class="text-gray-500 mt-3 max-w-2xl mx-auto text-sm">Snapshots of service, smiles, and shared success from our community initiatives.</p>
                    <div class="section-line"></div>
                </div>

                <div class="gallery-grid-home mb-8">
                    <?php
                    if (count($gallery) === 0):
                        for ($i=0;$i<6;$i++):
                    ?>
                        <div class="gallery-item">
                            <img src="https://placehold.co/300x300/e2e8f0/0A2342?text=No+Image" alt="Gallery" loading="lazy">
                            <div class="gi-overlay"><span>View More</span></div>
                        </div>
                    <?php
                        endfor;
                    else:
                        foreach ($gallery as $g):
                            $g_img = $g['file_path'] ?: 'https://placehold.co/300x300/e2e8f0/0A2342?text=No+Image';
                    ?>
                        <div class="gallery-item" onclick="openGalleryPreview(<?= (int)$g['media_id'] ?>)">
                            <img src="<?= e($g_img) ?>" alt="<?= e($g['title']) ?>" loading="lazy">
                            <div class="gi-overlay"><span>View More</span></div>
                            <div class="hidden gallery-meta" id="gallery-meta-<?= (int)$g['media_id'] ?>"
                                 data-title="<?= e($g['title']) ?>"
                                 data-caption="<?= e($g['caption']) ?>"
                                 data-src="<?= e($g_img) ?>"></div>
                        </div>
                    <?php
                        endforeach;
                    endif;
                    ?>
                </div>

                <div class="text-center">
                    <a href="mediaGallery.php" class="btn-yellow"><i class="fas fa-images"></i> View Full Gallery</a>
                </div>
            </div>
        </section>

        <?php
        $ctaBgImg = homeImg($homeContent['cta_bg_image'] ?? '');
        $ctaBadge = $homeContent['cta_badge'] ?? 'Get Involved';
        $ctaHeading = $homeContent['cta_heading'] ?? 'Join Hands. Spread Smiles. Make a Difference.';
        $ctaDesc = $homeContent['cta_description'] ?? 'Be part of something bigger. Whether you volunteer, partner, or donate — every action creates lasting change.';
        $ctaPrimaryText = $homeContent['cta_primary_btn_text'] ?? 'Contact Us';
        $ctaPrimaryLink = $homeContent['cta_primary_btn_link'] ?? 'contact.php';
        $ctaSecondaryText = $homeContent['cta_secondary_btn_text'] ?? 'Admin Login';
        $ctaSecondaryLink = $homeContent['cta_secondary_btn_link'] ?? '#login-modal';
        ?>
        <!-- ===== CTA SECTION ===== -->
        <section id="contact-cta" class="cta-section py-16 md:py-24">
            <?php if ($ctaBgImg): ?>
            <div class="absolute inset-0 bg-cover bg-center" style="background-image: url('<?= e($ctaBgImg) ?>'); opacity: 0.15;"></div>
            <?php endif; ?>
            <div class="cta-shapes">
                <div class="cta-shape cta-shape-1"></div>
                <div class="cta-shape cta-shape-2"></div>
            </div>
            <div class="relative z-10 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center fade-in-up">
                <div class="section-badge !bg-white/10 !text-yellow-400 !border !border-white/10"><?= e($ctaBadge) ?></div>
                <h2 class="text-3xl sm:text-4xl md:text-5xl font-extrabold text-white leading-tight mb-6 font-['Playfair_Display']"><?= e($ctaHeading) ?></h2>
                <p class="text-indigo-200 text-base max-w-2xl mx-auto mb-10"><?= e($ctaDesc) ?></p>
                <div class="flex flex-col sm:flex-row justify-center space-y-4 sm:space-y-0 sm:space-x-6">
                    <a href="<?= e($ctaPrimaryLink) ?>" class="hero-btn-primary"><i class="fas fa-envelope"></i> <?= e($ctaPrimaryText) ?></a>
                    <?php if (str_starts_with($ctaSecondaryLink, '#')): ?>
                    <button onclick="openModal()" class="hero-btn-secondary"><i class="fas fa-lock"></i> <?= e($ctaSecondaryText) ?></button>
                    <?php else: ?>
                    <a href="<?= e($ctaSecondaryLink) ?>" class="hero-btn-secondary"><i class="fas fa-lock"></i> <?= e($ctaSecondaryText) ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <!-- Gallery Preview Modal -->
    <div id="gallery-preview" class="fixed inset-0 z-50 hidden items-center justify-center p-4 modal-backdrop">
        <div class="bg-white rounded-xl shadow-2xl max-w-3xl w-full overflow-hidden">
            <div class="flex justify-between items-center p-4 border-b">
                <h4 id="gp-title" class="text-lg font-bold text-gray-800"></h4>
                <button onclick="closeGalleryPreview()" class="text-gray-600 hover:text-gray-900"><i class="fas fa-times"></i></button>
            </div>
            <div class="p-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <img id="gp-image" src="https://placehold.co/600x400/cccccc/0A2342?text=No+Image" alt="" class="w-full h-auto rounded">
                </div>
                <div>
                    <p id="gp-caption" class="text-gray-700"></p>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script>
        // --- Home Hero loaded class for bg zoom transition ---
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('.home-hero').classList.add('loaded');
        });

        // --- Login Modal ---
        const modal = document.getElementById('login-modal');
        const modalContent = modal.querySelector('div');

        function openModal() {
            modal.classList.remove('hidden', 'opacity-0');
            modal.classList.add('flex');
            setTimeout(() => {
                modal.classList.add('opacity-100');
                modalContent.classList.remove('scale-95');
                modalContent.classList.add('scale-100');
            }, 50);
        }

        function closeModal() {
            modal.classList.remove('opacity-100');
            modalContent.classList.remove('scale-100');
            modalContent.classList.add('scale-95');
            setTimeout(() => {
                modal.classList.add('hidden', 'opacity-0');
                modal.classList.remove('flex');
            }, 300);
        }

        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeModal();
            }
        });

        // --- Mobile Menu ---
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');

        function toggleMobileMenu() {
            mobileMenu.classList.toggle('hidden');
        }

        mobileMenuButton.addEventListener('click', toggleMobileMenu);

        const mobileLinks = mobileMenu.querySelectorAll('a, button');
        mobileLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (!mobileMenu.classList.contains('hidden')) {
                    toggleMobileMenu();
                }
            });
        });

        // --- Scroll Animation ---
        const sections = document.querySelectorAll('.fade-in-up, .fade-in-scale');
        const observerOptions = { root: null, rootMargin: '0px', threshold: 0.1 };
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);
        sections.forEach(section => { observer.observe(section); });

        // --- Gallery Preview ---
        function openGalleryPreview(id) {
            const meta = document.getElementById('gallery-meta-' + id);
            if (!meta) return;
            document.getElementById('gp-title').textContent = meta.dataset.title;
            document.getElementById('gp-image').src = meta.dataset.src;
            document.getElementById('gp-caption').textContent = meta.dataset.caption;
            const preview = document.getElementById('gallery-preview');
            preview.classList.remove('hidden');
            preview.classList.add('flex');
            setTimeout(() => preview.classList.add('opacity-100'), 50);
        }

        function closeGalleryPreview() {
            const preview = document.getElementById('gallery-preview');
            preview.classList.remove('opacity-100');
            setTimeout(() => {
                preview.classList.add('hidden');
                preview.classList.remove('flex');
            }, 300);
        }

        document.getElementById('gallery-preview').addEventListener('click', (e) => {
            if (e.target === document.getElementById('gallery-preview')) closeGalleryPreview();
        });

        // --- Login Form ---
        function validateLoginForm(event) {
            event.preventDefault();
            const email = document.getElementById("email").value.trim();
            const password = document.getElementById("password").value.trim();
            const errorBox = document.getElementById("login-error");
            errorBox.classList.add("hidden");
            fetch("login_action.php", {
                method: "POST",
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({ email, password })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.href = "Admin/dashboard.php";
                } else {
                    errorBox.textContent = data.message;
                    errorBox.classList.remove("hidden");
                }
            })
            .catch(() => {
                errorBox.textContent = "Server error. Try again.";
                errorBox.classList.remove("hidden");
            });
            return false;
        }
    </script>
</body>
</html>