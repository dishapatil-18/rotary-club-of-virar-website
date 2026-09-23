<?php
session_start();
require 'includes/db_connect.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/website_settings.php';
$ws = getWebsiteSettings($conn);

// Rotary Year logic
$allYears = [];
$yrRes = $conn->query("SELECT id, year_name, is_current FROM rotary_years ORDER BY year_name DESC");
if ($yrRes) {
    while ($y = $yrRes->fetch_assoc()) $allYears[] = $y;
}
$selectedYearId = null;
$selectedYearName = '';
if (!empty($_GET['ry'])) {
    foreach ($allYears as $y) {
        if ($y['id'] == $_GET['ry']) { $selectedYearId = $y['id']; $selectedYearName = $y['year_name']; break; }
    }
}
if (!$selectedYearId) {
    foreach ($allYears as $y) {
        if ($y['is_current']) { $selectedYearId = $y['id']; $selectedYearName = $y['year_name']; break; }
    }
}
if (!$selectedYearId && !empty($allYears)) {
    $selectedYearId = $allYears[0]['id'];
    $selectedYearName = $allYears[0]['year_name'];
}
$yearFilter = $selectedYearId ? " AND year_id = $selectedYearId" : '';

$media = [];
$sql = "SELECT
            media_id,
            title,
            description,
            media_type,
            reference_type,
            reference_id,
            media_path,
            uploaded_by,
            media_date
        FROM media_gallery
        WHERE status = 'active' $yearFilter
        ORDER BY reference_type, reference_id, media_id ASC";
if ($res = $conn->query($sql)) {
    while ($row = $res->fetch_assoc()) {
        $media[] = [
            'id' => (int)$row['media_id'],
            'title' => $row['title'] ?? '',
            'caption' => $row['description'] ?? '',
            'media_type' => $row['media_type'] ?? 'image',
            'category' => $row['reference_type'] ?? 'other',
            'reference_id' => $row['reference_id'],
            'url' => $row['media_path'],
            'thumbnail' => $row['media_path'],
            'uploaded_by' => $row['uploaded_by'] ?? '',
            'date' => isset($row['media_date']) ? date('Y-m-d', strtotime($row['media_date'])) : '',
        ];
    }
    $res->free();
} else {
    $media = [];
}

// Group by reference_id for event/project galleries
$grouped = [];
foreach ($media as $item) {
    $rid = $item['reference_id'] ?? 0;
    if (!isset($grouped[$rid])) $grouped[$rid] = [];
    $grouped[$rid][] = $item;
}

$gallery_json = json_encode($media, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$grouped_json = json_encode($grouped, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($ws['website_name']) ?> - Our Gallery</title>
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
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background: #f8fafc;
            color: var(--rotary-blue);
            overflow-x: hidden;
        }
        .nav-link { color: var(--rotary-blue); transition: color 0.3s ease; font-weight: 500; }
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
        .modal-backdrop { background-color: rgba(0,0,0,0.6); backdrop-filter: blur(5px); -webkit-backdrop-filter: blur(5px); }
        .social-icon { transition: all 0.3s ease; }
        .social-icon:hover { color: var(--rotary-yellow); text-shadow: 0 0 8px rgba(255, 192, 0, 0.8); transform: scale(1.1); }

        /* ===== HERO ===== */
        .gallery-hero {
            position: relative;
            min-height: 75vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: linear-gradient(135deg, #0A2342 0%, #1a2a6c 35%, #2d1b69 70%, #0A2342 100%);
        }
        .gallery-hero .hero-overlay {
            position: absolute; inset: 0;
            background: radial-gradient(circle at 30% 40%, rgba(255,192,0,0.06) 0%, transparent 50%),
                        radial-gradient(circle at 70% 60%, rgba(79,70,229,0.1) 0%, transparent 50%);
        }
        .gallery-hero .hero-bg-shapes {
            position: absolute; inset: 0; overflow: hidden; pointer-events: none;
        }
        .gallery-hero .hero-shape {
            position: absolute; border-radius: 50%; opacity: 0.06;
        }
        .gallery-hero .hero-shape-1 {
            width: 500px; height: 500px; background: var(--rotary-yellow);
            top: -150px; right: -100px;
            animation: floatShape 14s ease-in-out infinite;
        }
        .gallery-hero .hero-shape-2 {
            width: 350px; height: 350px; background: #818cf8;
            bottom: -80px; left: -80px;
            animation: floatShape 18s ease-in-out infinite reverse;
        }
        .gallery-hero .hero-shape-3 {
            width: 250px; height: 250px; background: var(--rotary-green);
            top: 30%; left: 70%;
            animation: floatShape 11s ease-in-out infinite 3s;
        }
        @keyframes floatShape {
            0%, 100% { transform: translate(0,0) scale(1); }
            33% { transform: translate(25px,-30px) scale(1.05); }
            66% { transform: translate(-15px,15px) scale(0.95); }
        }
        .gallery-hero .hero-grid {
            position: absolute; inset: 0;
            background-image: radial-gradient(rgba(255,255,255,0.04) 1px, transparent 1px);
            background-size: 40px 40px;
        }

        .hero-badge {
            display: inline-flex; align-items: center; gap: 10px;
            background: rgba(255,255,255,0.07); backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 100px; padding: 6px 20px 6px 6px; margin-bottom: 24px;
            animation: fadeInDown 0.8s ease-out forwards; opacity: 0;
        }
        .hero-badge img {
            width: 32px; height: 32px; border-radius: 50%;
            object-fit: contain; background: white; padding: 3px;
        }
        .hero-badge span { font-size: 0.8rem; font-weight: 600; color: rgba(255,255,255,0.8); letter-spacing: 0.5px; }

        .hero-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(2.4rem, 6vw, 4.5rem);
            font-weight: 800; line-height: 1.1; color: white;
            margin-bottom: 18px;
            animation: fadeInUp 0.8s ease-out 0.15s forwards; opacity: 0;
        }
        .hero-title .highlight {
            background: linear-gradient(135deg, var(--rotary-yellow), #ffb347);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        .hero-sub {
            font-size: clamp(0.95rem, 1.8vw, 1.2rem);
            color: rgba(255,255,255,0.7); font-weight: 300;
            max-width: 600px; margin: 0 auto 30px; line-height: 1.7;
            animation: fadeInUp 0.8s ease-out 0.3s forwards; opacity: 0;
        }

        .hero-stats {
            display: flex; gap: 36px; justify-content: center; flex-wrap: wrap;
            animation: fadeInUp 0.8s ease-out 0.45s forwards; opacity: 0;
        }
        .hero-stat-item { text-align: center; }
        .hero-stat-num { font-size: 1.8rem; font-weight: 800; color: var(--rotary-yellow); }
        .hero-stat-label { font-size: 0.75rem; color: rgba(255,255,255,0.55); text-transform: uppercase; letter-spacing: 1px; font-weight: 500; }

        .hero-scroll-down {
            position: absolute; bottom: 25px; left: 50%; transform: translateX(-50%);
            animation: bounceDown 2.2s infinite; color: rgba(255,255,255,0.35); font-size: 1.3rem; cursor: pointer;
        }
        @keyframes bounceDown {
            0%,100% { transform: translateX(-50%) translateY(0); opacity: 0.35; }
            50% { transform: translateX(-50%) translateY(8px); opacity: 1; }
        }

        @keyframes fadeInUp { to { opacity:1; transform:translateY(0); } }
        @keyframes fadeInDown { to { opacity:1; transform:translateY(0); } }

        .fade-in-up { opacity:0; transform:translateY(30px); transition:opacity 0.6s ease-out, transform 0.6s ease-out; }
        .fade-in-up.is-visible { opacity:1; transform:translateY(0); }
        .fade-in-scale { opacity:0; transform:scale(0.92); transition:opacity 0.5s ease-out, transform 0.5s ease-out; }
        .fade-in-scale.is-visible { opacity:1; transform:scale(1); }

        /* ===== SECTION TITLE ===== */
        .section-title-wrap { text-align:center; margin-bottom:44px; }
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
            display:flex; flex-wrap:wrap; justify-content:center; gap:10px; margin-bottom:44px;
        }
        .filter-btn {
            padding:9px 24px; border-radius:100px; font-size:0.85rem; font-weight:600;
            transition:all 0.3s cubic-bezier(0.25,0.8,0.25,1);
            background:white; color:var(--rotary-blue); border:1.5px solid #e2e8f0;
            cursor:pointer; position:relative; overflow:hidden;
        }
        .filter-btn:hover {
            transform:translateY(-2px);
            box-shadow:0 6px 20px rgba(10,35,66,0.08);
            border-color:var(--rotary-yellow);
        }
        .filter-btn.active {
            background:linear-gradient(135deg, var(--rotary-yellow), #ffb347);
            color:var(--rotary-blue); border-color:transparent;
            box-shadow:0 6px 20px rgba(255,192,0,0.3);
        }
        .filter-btn i { margin-right:6px; font-size:0.75rem; }

        /* ===== GALLERY CARDS ===== */
        #gallery-grid {
            display:grid;
            grid-template-columns:repeat(auto-fill, minmax(300px, 1fr));
            gap:28px;
        }
        .media-card {
            position:relative;
            background:white;
            border-radius:18px;
            overflow:hidden;
            cursor:pointer;
            transition:all 0.4s cubic-bezier(0.175,0.885,0.32,1.275);
            box-shadow:0 4px 20px rgba(0,0,0,0.04);
            border:1px solid rgba(0,0,0,0.04);
        }
        .media-card:hover {
            transform:translateY(-8px);
            box-shadow:0 20px 50px -12px rgba(10,35,66,0.15);
            border-color:rgba(255,192,0,0.2);
        }
        .media-card .card-img-wrap {
            position:relative;
            width:100%;
            height:220px;
            overflow:hidden;
            background:#e2e8f0;
        }
        .media-card .card-img-wrap img {
            width:100%; height:100%; object-fit:cover;
            transition:transform 0.6s ease;
        }
        .media-card:hover .card-img-wrap img { transform:scale(1.08); }
        .media-card .card-overlay {
            position:absolute; inset:0;
            background:linear-gradient(to top, rgba(0,0,0,0.6) 0%, transparent 50%);
            opacity:0;
            transition:opacity 0.4s ease;
            display:flex; flex-direction:column; justify-content:flex-end; padding:20px;
        }
        .media-card:hover .card-overlay { opacity:1; }
        .media-card .card-overlay h4 {
            color:white; font-size:1.1rem; font-weight:700;
            transform:translateY(10px); transition:transform 0.4s ease 0.05s;
        }
        .media-card:hover .card-overlay h4 { transform:translateY(0); }
        .media-card .card-overlay p {
            color:rgba(255,255,255,0.7); font-size:0.8rem;
            transform:translateY(10px); transition:transform 0.4s ease 0.1s;
        }
        .media-card:hover .card-overlay p { transform:translateY(0); }

        .media-card .card-badge {
            position:absolute; top:14px; right:14px; z-index:2;
            font-size:0.65rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;
            padding:4px 12px; border-radius:100px;
            backdrop-filter:blur(8px); background:rgba(255,255,255,0.15);
            color:white; border:1px solid rgba(255,255,255,0.2);
        }
        .media-card .card-count {
            position:absolute; top:14px; left:14px; z-index:2;
            font-size:0.7rem; font-weight:600;
            padding:4px 10px; border-radius:100px;
            background:rgba(0,0,0,0.5); backdrop-filter:blur(4px);
            color:white; display:flex; align-items:center; gap:4px;
        }
        .media-card .card-play {
            position:absolute; inset:0; z-index:2;
            display:flex; align-items:center; justify-content:center;
        }
        .media-card .card-play .play-btn {
            width:56px; height:56px; border-radius:50%;
            background:rgba(255,255,255,0.2); backdrop-filter:blur(8px);
            border:2px solid rgba(255,255,255,0.4);
            display:flex; align-items:center; justify-content:center;
            color:white; font-size:1.4rem;
            transition:all 0.3s;
        }
        .media-card:hover .card-play .play-btn {
            background:var(--rotary-yellow); border-color:var(--rotary-yellow);
            transform:scale(1.1); color:var(--rotary-blue);
        }

        .media-card .card-body {
            padding:16px 18px 18px;
        }
        .media-card .card-body h3 {
            font-size:1rem; font-weight:700; color:var(--rotary-blue);
            margin-bottom:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
        }
        .media-card .card-body .card-date {
            font-size:0.75rem; color:#94a3b8; display:flex; align-items:center; gap:5px;
        }

        /* ===== FULLSCREEN GALLERY MODAL (STACK VIEWER) ===== */
        .stack-modal {
            position:fixed; inset:0; z-index:200;
            display:flex; align-items:center; justify-content:center;
            opacity:0; visibility:hidden;
            transition:all 0.4s ease;
            background:rgba(0,0,0,0.85);
            backdrop-filter:blur(12px);
            -webkit-backdrop-filter:blur(12px);
        }
        .stack-modal.active { opacity:1; visibility:visible; }
        .stack-modal .sm-close {
            position:absolute; top:20px; right:20px; z-index:10;
            width:44px; height:44px; border-radius:50%;
            background:rgba(255,255,255,0.12);
            border:none; color:white; font-size:1.3rem;
            cursor:pointer; transition:all 0.3s;
            display:flex; align-items:center; justify-content:center;
        }
        .stack-modal .sm-close:hover { background:rgba(255,255,255,0.25); transform:rotate(90deg); }
        .stack-modal .sm-counter {
            position:absolute; top:20px; left:20px; z-index:10;
            font-size:0.8rem; font-weight:600; color:rgba(255,255,255,0.7);
            background:rgba(0,0,0,0.4); padding:6px 16px; border-radius:100px;
        }
        .stack-modal .sm-image-wrap {
            position:relative;
            max-width:90vw;
            max-height:85vh;
            display:flex; align-items:center; justify-content:center;
            transform:scale(0.92) translateY(20px);
            transition:all 0.5s cubic-bezier(0.175,0.885,0.32,1.275);
        }
        .stack-modal.active .sm-image-wrap { transform:scale(1) translateY(0); }
        .stack-modal .sm-image-wrap img, .stack-modal .sm-image-wrap video {
            max-width:100%; max-height:82vh;
            border-radius:16px;
            object-fit:contain;
            box-shadow:0 30px 80px rgba(0,0,0,0.5);
        }
        .stack-modal .sm-nav {
            position:absolute; top:50%; transform:translateY(-50%);
            z-index:10; width:48px; height:48px; border-radius:50%;
            background:rgba(255,255,255,0.1); backdrop-filter:blur(8px);
            border:1px solid rgba(255,255,255,0.15);
            color:white; font-size:1.2rem;
            cursor:pointer; transition:all 0.3s;
            display:flex; align-items:center; justify-content:center;
        }
        .stack-modal .sm-nav:hover { background:rgba(255,255,255,0.25); transform:translateY(-50%) scale(1.1); }
        .stack-modal .sm-nav.prev { left:16px; }
        .stack-modal .sm-nav.next { right:16px; }

        .stack-modal .sm-caption {
            position:absolute; bottom:0; left:0; right:0;
            padding:40px 24px 20px;
            background:linear-gradient(transparent, rgba(0,0,0,0.6));
            text-align:center;
        }
        .stack-modal .sm-caption h3 { color:white; font-size:1.2rem; font-weight:700; margin-bottom:2px; }
        .stack-modal .sm-caption p { color:rgba(255,255,255,0.6); font-size:0.85rem; }

        .stack-modal .sm-thumbs {
            position:absolute; bottom:16px; left:50%; transform:translateX(-50%);
            display:flex; gap:6px; z-index:10;
        }
        .stack-modal .sm-thumbs .thumb-dot {
            width:8px; height:8px; border-radius:50%;
            background:rgba(255,255,255,0.3);
            transition:all 0.3s; cursor:pointer;
        }
        .stack-modal .sm-thumbs .thumb-dot.active { background:var(--rotary-yellow); width:24px; border-radius:4px; }

        /* ===== VIDEO MODAL ===== */
        .video-modal {
            position:fixed; inset:0; z-index:200;
            display:flex; align-items:center; justify-content:center;
            opacity:0; visibility:hidden;
            transition:all 0.4s ease;
            background:rgba(0,0,0,0.9);
            backdrop-filter:blur(10px);
        }
        .video-modal.active { opacity:1; visibility:visible; }
        .video-modal .vm-content {
            max-width:800px; width:90%;
            transform:scale(0.9); transition:transform 0.4s ease;
        }
        .video-modal.active .vm-content { transform:scale(1); }
        .video-modal .vm-content video, .video-modal .vm-content iframe {
            width:100%; aspect-ratio:16/9; border-radius:16px;
            box-shadow:0 20px 60px rgba(0,0,0,0.4);
        }
        .video-modal .vm-close {
            position:absolute; top:20px; right:20px;
            width:44px; height:44px; border-radius:50%;
            background:rgba(255,255,255,0.1); border:none;
            color:white; font-size:1.3rem; cursor:pointer;
            transition:all 0.3s; display:flex; align-items:center; justify-content:center;
        }
        .video-modal .vm-close:hover { background:rgba(255,255,255,0.2); transform:rotate(90deg); }

        /* ===== ANNOUNCEMENT STRIP ===== */
        .announce-strip {
            background:linear-gradient(90deg, var(--rotary-yellow), #f59e0b);
            position:relative; overflow:hidden;
            border-radius:14px;
        }
        .announce-strip::before {
            content:''; position:absolute; inset:0;
            background: repeating-linear-gradient(45deg, transparent, transparent 20px, rgba(255,255,255,0.03) 20px, rgba(255,255,255,0.03) 40px);
        }
        .announce-track {
            display:flex; white-space:nowrap;
            animation: scrollAnnounce 40s linear infinite;
        }
        .announce-track:hover { animation-play-state:paused; }
        .announce-track .item {
            display:inline-flex; align-items:center; gap:10px;
            padding:12px 32px; font-size:0.9rem; font-weight:600;
            color:var(--rotary-blue); flex-shrink:0;
        }
        .announce-track .item i { color:rgba(10,35,66,0.6); font-size:1rem; width:18px; }
        .announce-track .item .glow {
            display:inline-block; width:8px; height:8px; border-radius:50%;
            background:rgba(10,35,66,0.5); animation:pulse 1.5s ease-in-out infinite;
        }
        @keyframes scrollAnnounce {
            0% { transform:translateX(0); }
            100% { transform:translateX(-50%); }
        }
        @keyframes pulse {
            0%,100% { opacity:0.4; transform:scale(0.8); }
            50% { opacity:1; transform:scale(1.2); }
        }

        /* ===== MEMORIES SECTION ===== */
        .memories-scroll {
            display:flex; gap:24px; overflow-x:auto; padding:20px 4px 24px;
            scroll-snap-type:x mandatory; -webkit-overflow-scrolling:touch;
            scrollbar-width:none; -ms-overflow-style:none;
        }
        .memories-scroll::-webkit-scrollbar { display:none; }
        .memory-card {
            flex-shrink:0; scroll-snap-align:start;
            width:220px; border-radius:16px; overflow:hidden;
            background:white; box-shadow:0 8px 30px rgba(0,0,0,0.06);
            transition:all 0.4s ease; cursor:pointer;
            transform:rotate(0deg);
        }
        .memory-card:nth-child(odd) { transform:rotate(-1deg); }
        .memory-card:nth-child(even) { transform:rotate(1deg); }
        .memory-card:hover {
            transform:rotate(0deg) translateY(-8px) scale(1.02);
            box-shadow:0 16px 40px rgba(10,35,66,0.12);
        }
        .memory-card .mem-img {
            width:100%; height:160px; object-fit:cover;
        }
        .memory-card .mem-body {
            padding:12px 14px 14px;
            position:relative;
        }
        .memory-card .mem-body::before {
            content:''; position:absolute; top:-8px; left:14px; right:14px; height:2px;
            background:linear-gradient(90deg, var(--rotary-yellow), var(--rotary-indigo));
            border-radius:2px;
        }
        .memory-card .mem-body h4 { font-size:0.85rem; font-weight:700; color:var(--rotary-blue); }
        .memory-card .mem-body p { font-size:0.7rem; color:#94a3b8; }

        /* ===== SCROLL TO TOP ===== */
        #scroll-to-top {
            background-color:var(--rotary-yellow); color:var(--rotary-blue);
            box-shadow:0 4px 15px rgba(255,192,0,0.5);
            transition:all 0.3s ease;
        }
        #scroll-to-top:hover {
            background-color:#ffda6a;
            box-shadow:0 6px 20px rgba(255,192,0,0.8);
            transform:scale(1.1);
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width:640px) {
            .gallery-hero { min-height:60vh; }
            #gallery-grid { grid-template-columns:1fr; gap:20px; }
            .media-card .card-img-wrap { height:200px; }
            .filter-tabs { gap:8px; }
            .filter-btn { padding:7px 16px; font-size:0.75rem; }
            .stack-modal .sm-nav { width:36px; height:36px; font-size:0.9rem; }
            .stack-modal .sm-nav.prev { left:6px; }
            .stack-modal .sm-nav.next { right:6px; }
            .stack-modal .sm-image-wrap { max-width:95vw; max-height:70vh; }
            .stack-modal .sm-thumbs { display:none; }
            .memory-card { width:180px; }
            .hero-stats { gap:20px; }
            .hero-stat-num { font-size:1.4rem; }
        }
        @media (min-width:641px) and (max-width:1024px) {
            #gallery-grid { grid-template-columns:repeat(2,1fr); gap:24px; }
            .memory-card { width:200px; }
        }
        @media (min-width:1025px) {
            #gallery-grid { grid-template-columns:repeat(3,1fr); }
        }
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
            <form class="space-y-4" action="login_action.php" method="POST">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email / Username</label>
                    <input type="text" id="email" name="email" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500 transition duration-150" placeholder="admin@rotaryvirar.org">
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                    <input type="password" id="password" name="password" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500 transition duration-150" placeholder="••••••••">
                </div>
                <button type="submit" class="w-full py-3 mt-4 text-lg font-semibold rounded-lg bg-yellow-500 hover:bg-yellow-400 transition duration-300 shadow-lg" style="color:var(--rotary-blue);">Login</button>
            </form>
        </div>
    </div>

    <!-- Header -->
    <header class="sticky top-0 z-40 bg-white shadow-md">
        <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <img src="<?= e($ws['website_logo']) ?>" alt="<?= e($ws['website_short_name']) ?> Logo" class="w-10 h-10 rounded-full object-cover">
                <span class="text-xl font-extrabold tracking-tight" style="color:var(--rotary-blue);"><?= e($ws['website_name']) ?></span>
            </div>
            <div class="hidden lg:flex flex-1 justify-center space-x-8">
                <a href="index.php" class="nav-link">Home</a>
                <a href="aboutus.php" class="nav-link">About Us</a>
                <a href="activities.php" class="nav-link"><i class="fas fa-calendar-check mr-1.5"></i>Activities</a>
                <a href="team.php" class="nav-link">Our Team</a>
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
                <a href="activities.php" class="nav-link block px-3 py-2 rounded-md text-base"><i class="fas fa-calendar-check mr-1.5"></i>Activities</a>
                <a href="team.php" class="nav-link block px-3 py-2 rounded-md text-base">Our Team</a>
                <a href="contact.php" class="nav-link block px-3 py-2 rounded-md text-base">Contact Us</a>
                <button onclick="openLoginModal(); toggleMobileMenu()" class="nav-link block px-3 py-2 rounded-md text-base">Log In</button>
                <a href="donate.php" target="_blank" class="w-3/4 text-center mt-2 px-6 py-2 rounded-full text-lg font-semibold donate-button">Donate</a>
            </div>
        </div>
    </header>

    <main>
        <!-- ===== HERO ===== -->
        <section class="gallery-hero" id="gallery-hero">
            <div class="hero-overlay"></div>
            <div class="hero-bg-shapes">
                <div class="hero-shape hero-shape-1"></div>
                <div class="hero-shape hero-shape-2"></div>
                <div class="hero-shape hero-shape-3"></div>
            </div>
            <div class="hero-grid"></div>

            <div class="relative z-10 text-center px-4 max-w-5xl mx-auto py-20 md:py-0">
                <div class="hero-badge">
                    <img src="<?= e($ws['website_logo']) ?>" alt="<?= e($ws['website_short_name']) ?>" onerror="this.style.display='none'">
                    <span><?= e($ws['website_name']) ?></span>
                </div>

                <h1 class="hero-title">
                    Capturing <span class="highlight">Service</span>,<br>
                    Fellowship &amp; <span class="highlight">Change</span>
                </h1>

                <p class="hero-sub">
                    Every photograph tells a story of hope, compassion, and community impact. Explore our journey through moments that define Rotary service.
                </p>

                <?php
                $catCounts = ['project' => 0, 'event' => 0, 'other' => 0];
                foreach ($media as $m) {
                    $c = $m['category'];
                    if (isset($catCounts[$c])) $catCounts[$c]++;
                    else $catCounts['other']++;
                }
                $uniqueRefs = [];
                foreach ($media as $m) { $uniqueRefs[$m['reference_id']] = true; }
                ?>
                <div class="hero-stats">
                    <div class="hero-stat-item">
                        <div class="hero-stat-num"><?= count($media) ?></div>
                        <div class="hero-stat-label">Media Items</div>
                    </div>
                    <div class="hero-stat-item">
                        <div class="hero-stat-num"><?= count($uniqueRefs) ?></div>
                        <div class="hero-stat-label">Events &amp; Projects</div>
                    </div>
                    <div class="hero-stat-item">
                        <div class="hero-stat-num"><?= $catCounts['project'] ?></div>
                        <div class="hero-stat-label">Community Drives</div>
                    </div>
                </div>
            </div>

            <div class="hero-scroll-down" onclick="document.getElementById('gallery-main').scrollIntoView({behavior:'smooth'})">
                <i class="fas fa-chevron-down"></i>
            </div>
        </section>

        <!-- ===== MAIN GALLERY CONTENT ===== -->
        <section id="gallery-main" class="py-16 md:py-20 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <!-- Section Title -->
                <div class="section-title-wrap fade-in-up">
                    <div class="section-badge">Our Gallery</div>
                    <h2 class="section-title">Moments That Matter</h2>
                    <div class="section-line"></div>
                </div>

                <!-- Rotary Year Selector -->
                <div class="flex flex-col sm:flex-row items-center justify-center mb-8 space-y-4 sm:space-y-0 sm:space-x-4 fade-in-up">
                    <label class="text-base font-semibold" style="color: var(--rotary-blue);">Select Rotary Year:</label>
                    <select onchange="window.location.href='?ry='+this.value" class="px-4 py-2 rounded-full border-2 border-[var(--rotary-blue)] text-[var(--rotary-blue)] font-semibold bg-white cursor-pointer outline-none focus:border-[var(--rotary-yellow)] focus:shadow-[0_0_0_3px_rgba(255,192,0,0.2)] transition-all min-w-[180px] text-center">
                        <?php foreach ($allYears as $y): ?>
                        <option value="<?= $y['id'] ?>" <?= $y['id'] == $selectedYearId ? 'selected' : '' ?>><?= htmlspecialchars($y['year_name']) ?> <?= $y['is_current'] ? '(Current)' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Filter Tabs -->
                <div class="filter-tabs fade-in-up">
                    <button data-category="all" class="filter-btn active"><i class="fas fa-th-large"></i> All</button>
                    <button data-category="event" class="filter-btn"><i class="fas fa-calendar-alt"></i> Events</button>
                    <button data-category="project" class="filter-btn"><i class="fas fa-hands-helping"></i> Projects</button>
                    <button data-category="announcement" class="filter-btn"><i class="fas fa-bullhorn"></i> Announcements</button>
                    <button data-category="donation" class="filter-btn"><i class="fas fa-gift"></i> Donations</button>
                    <button data-category="other" class="filter-btn"><i class="fas fa-ellipsis-h"></i> Other</button>
                </div>

                <!-- Gallery Grid -->
                <div id="gallery-grid" class="fade-in-up">
                    <!-- Rendered by JS -->
                </div>
            </div>
        </section>

        <!-- ===== ANNOUNCEMENT STRIP ===== -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-16 fade-in-up">
            <div class="announce-strip shadow-xl">
                <div class="announce-track">
                    <span class="item"><span class="glow"></span> <i class="fas fa-calendar-check"></i> Annual Charity Dinner — March 15th</span>
                    <span class="item"><span class="glow"></span> <i class="fas fa-backpack"></i> School Kit Distribution on 25th Nov — Volunteers needed</span>
                    <span class="item"><span class="glow"></span> <i class="fas fa-syringe"></i> Blood Donation Camp — December 10th. Sign up now!</span>
                    <span class="item"><span class="glow"></span> <i class="fas fa-leaf"></i> Tree Plantation Drive — 500 saplings planted!</span>
                    <span class="item"><span class="glow"></span> <i class="fas fa-hand-holding-heart"></i> Community Health Checkup Camp — January 20th</span>
                    <!-- Duplicate for seamless loop -->
                    <span class="item"><span class="glow"></span> <i class="fas fa-calendar-check"></i> Annual Charity Dinner — March 15th</span>
                    <span class="item"><span class="glow"></span> <i class="fas fa-backpack"></i> School Kit Distribution on 25th Nov — Volunteers needed</span>
                    <span class="item"><span class="glow"></span> <i class="fas fa-syringe"></i> Blood Donation Camp — December 10th. Sign up now!</span>
                    <span class="item"><span class="glow"></span> <i class="fas fa-leaf"></i> Tree Plantation Drive — 500 saplings planted!</span>
                    <span class="item"><span class="glow"></span> <i class="fas fa-hand-holding-heart"></i> Community Health Checkup Camp — January 20th</span>
                </div>
            </div>
        </section>

        <!-- ===== VIDEOS SECTION ===== -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-16 fade-in-up">
            <div class="section-title-wrap">
                <div class="section-badge">Featured Media</div>
                <h2 class="section-title">Highlight Reels &amp; Stories</h2>
                <div class="section-line"></div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="media-card" onclick="openVideoModal('https://www.youtube.com/embed/dQw4w9WgXcQ', 'Annual Service Highlight')">
                    <div class="card-img-wrap" style="height:240px;">
                        <img src="https://placehold.co/600x400/0A2342/FFC000?text=Annual+Service+Highlight" alt="Video">
                        <div class="card-play">
                            <div class="play-btn"><i class="fas fa-play"></i></div>
                        </div>
                    </div>
                    <div class="card-body">
                        <h3>Annual Service Highlight</h3>
                        <p class="card-date"><i class="far fa-calendar-alt"></i> A look back at our biggest projects this year.</p>
                    </div>
                </div>
                <div class="media-card" onclick="openVideoModal('https://www.youtube.com/embed/dQw4w9WgXcQ', 'President\'s Interview')">
                    <div class="card-img-wrap" style="height:240px;">
                        <img src="https://placehold.co/600x400/4F46E5/FFC000?text=President%27s+Interview" alt="Video">
                        <div class="card-play">
                            <div class="play-btn"><i class="fas fa-play"></i></div>
                        </div>
                    </div>
                    <div class="card-body">
                        <h3>President's Interview</h3>
                        <p class="card-date"><i class="far fa-calendar-alt"></i> Vision for the upcoming Rotarian year.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ===== CLUB MEMORIES ===== -->
        <section class="py-16" style="background:linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%);">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="section-title-wrap fade-in-up">
                    <div class="section-badge">Fellowship</div>
                    <h2 class="section-title">Club Memories &amp; Fellowship</h2>
                    <div class="section-line"></div>
                </div>
                <p class="text-center text-gray-500 max-w-xl mx-auto mb-8 fade-in-up" style="font-size:0.9rem;">
                    Cherished moments of friendship, collaboration, and shared purpose that make Rotary truly special.
                </p>

                <div class="memories-scroll fade-in-up">
                    <div class="memory-card">
                        <img src="https://placehold.co/400x300/FACC15/0A2342?text=Retreat+2024" alt="Club Retreat" class="mem-img" loading="lazy">
                        <div class="mem-body"><h4>Club Retreat 2024</h4><p>Strategic planning &amp; bonding</p></div>
                    </div>
                    <div class="memory-card">
                        <img src="https://placehold.co/400x300/3B82F6/FFFFFF?text=Founders+Day" alt="Founders Day" class="mem-img" loading="lazy">
                        <div class="mem-body"><h4>Founders Day</h4><p>Celebrating Rotary heritage</p></div>
                    </div>
                    <div class="memory-card">
                        <img src="https://placehold.co/400x300/10B981/FFFFFF?text=Project+Team" alt="Project Team" class="mem-img" loading="lazy">
                        <div class="mem-body"><h4>Project Team</h4><p>Volunteers in action</p></div>
                    </div>
                    <div class="memory-card">
                        <img src="https://placehold.co/400x300/F87171/FFFFFF?text=Conference" alt="Conference" class="mem-img" loading="lazy">
                        <div class="mem-body"><h4>District Conference</h4><p>Inspiring keynote sessions</p></div>
                    </div>
                    <div class="memory-card">
                        <img src="https://placehold.co/400x300/4F46E5/FFFFFF?text=Gala+2024" alt="Gala" class="mem-img" loading="lazy">
                        <div class="mem-body"><h4>New Year Gala</h4><p>Celebrating together</p></div>
                    </div>
                    <div class="memory-card">
                        <img src="https://placehold.co/400x300/0A2342/FFC000?text=Blood+Drive" alt="Blood Drive" class="mem-img" loading="lazy">
                        <div class="mem-body"><h4>Blood Donation Drive</h4><p>120 units collected</p></div>
                    </div>
                    <div class="memory-card">
                        <img src="https://placehold.co/400x300/059669/FFFFFF?text=Tree+Plant" alt="Tree Plantation" class="mem-img" loading="lazy">
                        <div class="mem-body"><h4>Tree Plantation</h4><p>500 saplings planted</p></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ===== QUOTE SECTION ===== -->
        <section class="py-16 md:py-20 text-center px-4" style="background:linear-gradient(135deg, var(--rotary-blue), #1e3a5f);">
            <div class="max-w-3xl mx-auto">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-white/10 backdrop-blur-sm border border-white/20 mb-6">
                    <i class="fas fa-camera text-xl" style="color:var(--rotary-yellow);"></i>
                </div>
                <blockquote class="text-2xl md:text-3xl font-bold text-white leading-snug mb-4" style="font-family:'Playfair Display',serif;">
                    "The best way to find yourself is to lose yourself in the service of others."
                </blockquote>
                <p class="text-white/50 text-sm font-light">— Mahatma Gandhi</p>
            </div>
        </section>

    </main>

    <!-- Scroll To Top -->
    <button id="scroll-to-top" class="fixed bottom-6 right-6 p-4 rounded-full text-2xl z-30 hidden" onclick="scrollToTop()" aria-label="Scroll to Top">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- ===== FULLSCREEN STACK GALLERY MODAL ===== -->
    <div id="stack-modal" class="stack-modal" onclick="closeStackModal(event)">
        <button class="sm-close" onclick="closeStackModal()"><i class="fas fa-times"></i></button>
        <div class="sm-counter" id="sm-counter">0 / 0</div>

        <button class="sm-nav prev" id="sm-prev" onclick="navigateStack(-1)"><i class="fas fa-chevron-left"></i></button>

        <div class="sm-image-wrap" onclick="event.stopPropagation()">
            <img id="sm-image" src="" alt="Gallery Image">
            <div class="sm-caption" id="sm-caption">
                <h3 id="sm-title"></h3>
                <p id="sm-subtitle"></p>
            </div>
        </div>

        <button class="sm-nav next" id="sm-next" onclick="navigateStack(1)"><i class="fas fa-chevron-right"></i></button>

        <div class="sm-thumbs" id="sm-thumbs"></div>

        <!-- Touch/Swipe area --><div id="sm-touch-area" style="position:absolute;inset:0;z-index:1;" onclick="event.stopPropagation()"></div>
    </div>

    <!-- ===== VIDEO MODAL ===== -->
    <div id="video-modal" class="video-modal" onclick="closeVideoModal(event)">
        <button class="vm-close" onclick="closeVideoModal()"><i class="fas fa-times"></i></button>
        <div class="vm-content" onclick="event.stopPropagation()">
            <iframe id="vm-iframe" src="" frameborder="0" allow="accelerometer;autoplay;encrypted-media;gyroscope;picture-in-picture" allowfullscreen></iframe>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script>
        // ==== DATA ====
        const galleryData = <?= $gallery_json ?: '[]' ?>;
        const groupedData = <?= $grouped_json ?: '{}' ?>;

        // ==== DOM REFS ====
        const grid = document.getElementById('gallery-grid');
        const stackModal = document.getElementById('stack-modal');
        const smImage = document.getElementById('sm-image');
        const smTitle = document.getElementById('sm-title');
        const smSubtitle = document.getElementById('sm-subtitle');
        const smCounter = document.getElementById('sm-counter');
        const smThumbs = document.getElementById('sm-thumbs');
        const videoModal = document.getElementById('video-modal');
        const vmIframe = document.getElementById('vm-iframe');

        let currentStack = [];
        let stackIndex = 0;
        let touchStartX = 0;

        // ==== RENDER CARDS (GROUPED BY reference_id) ====
        function renderGallery(data) {
            // Group by reference_id
            const groups = {};
            data.forEach(item => {
                const rid = item.reference_id || 'nogroup';
                if (!groups[rid]) groups[rid] = [];
                groups[rid].push(item);
            });

            const cards = Object.values(groups).map(group => {
                const first = group[0];
                const count = group.length;
                const safeTitle = (first.title || 'Untitled').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                const safeCaption = (first.caption || '').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                const imgUrl = first.url || 'https://placehold.co/600x400/e2e8f0/94a3b8?text=No+Image';
                const cat = first.category || 'other';
                const isVideo = first.media_type === 'video';

                return `
                <div class="media-card fade-in-scale" onclick="openStack(${JSON.stringify(first.reference_id).replace(/"/g,'&quot;')}, '${cat}')">
                    <div class="card-img-wrap">
                        <img src="${imgUrl}" alt="${safeTitle}" loading="lazy">
                        ${isVideo ? `
                        <div class="card-play"><div class="play-btn"><i class="fas fa-play"></i></div></div>
                        ` : ''}
                        <div class="card-overlay">
                            <h4>${safeTitle}</h4>
                            <p>${safeCaption}</p>
                        </div>
                        <span class="card-badge">${cat.charAt(0).toUpperCase() + cat.slice(1)}</span>
                        <span class="card-count"><i class="fas fa-images"></i> ${count}</span>
                    </div>
                    <div class="card-body">
                        <h3>${safeTitle}</h3>
                        <span class="card-date"><i class="far fa-calendar-alt"></i> ${first.date || 'Date not set'}</span>
                    </div>
                </div>`;
            });

            grid.innerHTML = cards.join('');

            // Observe new fade-in elements
            document.querySelectorAll('#gallery-grid .fade-in-scale').forEach(el => revealObserver.observe(el));
            // Trigger visibility for already visible
            document.querySelectorAll('#gallery-grid .fade-in-scale').forEach(el => {
                if (el.getBoundingClientRect().top < window.innerHeight - 100) el.classList.add('is-visible');
            });
        }

        // ==== FILTER ====
        function filterGallery(category) {
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            const activeBtn = document.querySelector(`.filter-btn[data-category="${category}"]`);
            if (activeBtn) activeBtn.classList.add('active');

            const filtered = category === 'all'
                ? galleryData
                : galleryData.filter(item => item.category === category);

            renderGallery(filtered);
        }

        // ==== OPEN STACK (FULLSCREEN GALLERY FOR A GROUP) ====
        function openStack(referenceId, category) {
            const rid = String(referenceId);
            let items = galleryData.filter(item => String(item.reference_id) === rid);
            if (items.length === 0) return;

            // Also include items from same group in filtered data if possible
            currentStack = items;
            stackIndex = 0;
            updateStackView();
            stackModal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function updateStackView() {
            const item = currentStack[stackIndex];
            if (!item) return;

            const isVideo = item.media_type === 'video';
            if (isVideo) {
                smImage.style.display = 'none';
                // For video in stack, show thumbnail + play indicator
                smImage.src = item.thumbnail || item.url;
                smImage.style.display = 'block';
            } else {
                smImage.src = item.url || 'https://placehold.co/800x600/e2e8f0/94a3b8?text=No+Image';
                smImage.style.display = 'block';
            }

            smTitle.textContent = item.title || 'Untitled';
            smSubtitle.textContent = item.caption || (item.date ? 'Date: ' + item.date : '');

            smCounter.textContent = (stackIndex + 1) + ' / ' + currentStack.length;

            // Thumb dots
            smThumbs.innerHTML = currentStack.map((_, i) =>
                `<span class="thumb-dot ${i === stackIndex ? 'active' : ''}" onclick="event.stopPropagation();goToStack(${i})"></span>`
            ).join('');
        }

        function navigateStack(dir) {
            stackIndex += dir;
            if (stackIndex < 0) stackIndex = currentStack.length - 1;
            if (stackIndex >= currentStack.length) stackIndex = 0;
            updateStackView();
        }

        function goToStack(index) {
            stackIndex = index;
            updateStackView();
        }

        function closeStackModal(e) {
            if (e && e.target !== stackModal && !e.target.closest('.sm-close') && !e.target.closest('#sm-touch-area')) return;
            stackModal.classList.remove('active');
            document.body.style.overflow = '';
        }

        // ==== VIDEO MODAL ====
        function openVideoModal(url, title) {
            vmIframe.src = url;
            videoModal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeVideoModal(e) {
            if (e && e.target !== videoModal && !e.target.closest('.vm-close')) return;
            videoModal.classList.remove('active');
            vmIframe.src = '';
            document.body.style.overflow = '';
        }

        // ==== LOGIN MODAL ====
        const loginModal = document.getElementById('login-modal');
        const loginContent = loginModal.querySelector('div');
        function openLoginModal() {
            loginModal.classList.remove('hidden', 'opacity-0');
            loginModal.classList.add('flex');
            setTimeout(() => {
                loginModal.classList.add('opacity-100');
                loginContent.classList.remove('scale-95');
                loginContent.classList.add('scale-100');
            }, 50);
        }
        function closeLoginModal() {
            loginModal.classList.remove('opacity-100');
            loginContent.classList.remove('scale-100');
            loginContent.classList.add('scale-95');
            setTimeout(() => {
                loginModal.classList.add('hidden', 'opacity-0');
                loginModal.classList.remove('flex');
            }, 300);
        }
        loginModal.addEventListener('click', (e) => { if (e.target === loginModal) closeLoginModal(); });

        // ==== MOBILE MENU ====
        const menuBtn = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');
        function toggleMobileMenu() { mobileMenu.classList.toggle('hidden'); }
        menuBtn.addEventListener('click', toggleMobileMenu);
        mobileMenu.querySelectorAll('a, button').forEach(link => {
            link.addEventListener('click', () => { if (!mobileMenu.classList.contains('hidden')) toggleMobileMenu(); });
        });

        // ==== SCROLL REVEAL ====
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

        // ==== KEYBOARD & TOUCH ====
        document.addEventListener('keydown', (e) => {
            if (stackModal.classList.contains('active')) {
                if (e.key === 'ArrowLeft') navigateStack(-1);
                if (e.key === 'ArrowRight') navigateStack(1);
                if (e.key === 'Escape') closeStackModal();
            }
            if (videoModal.classList.contains('active') && e.key === 'Escape') closeVideoModal();
            if (!loginModal.classList.contains('hidden') && e.key === 'Escape') closeLoginModal();
        });

        // Touch swipe for stack modal
        document.getElementById('sm-touch-area').addEventListener('touchstart', (e) => {
            touchStartX = e.touches[0].clientX;
        }, { passive: true });
        document.getElementById('sm-touch-area').addEventListener('touchend', (e) => {
            const diff = e.changedTouches[0].clientX - touchStartX;
            if (Math.abs(diff) > 50) {
                navigateStack(diff > 0 ? -1 : 1);
            }
        }, { passive: true });

        // ==== SCROLL TO TOP ====
        const scrollBtn = document.getElementById('scroll-to-top');
        window.onscroll = function() {
            if (document.body.scrollTop > 500 || document.documentElement.scrollTop > 500) {
                scrollBtn.classList.remove('hidden');
            } else {
                scrollBtn.classList.add('hidden');
            }
        };
        function scrollToTop() { window.scrollTo({ top: 0, behavior: 'smooth' }); }

        // ==== HERO PARALLAX ====
        document.getElementById('gallery-hero')?.addEventListener('mousemove', (e) => {
            const shapes = document.querySelectorAll('.gallery-hero .hero-shape');
            const x = (e.clientX / window.innerWidth - 0.5) * 20;
            const y = (e.clientY / window.innerHeight - 0.5) * 20;
            shapes.forEach((s, i) => {
                const factor = (i + 1) * 0.3;
                s.style.transform = `translate(${x * factor}px, ${y * factor}px)`;
            });
        });

        // ==== INIT ====
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof lucide !== 'undefined') lucide.createIcons();

            // Observe all animate-on-scroll elements
            document.querySelectorAll('.fade-in-up, .fade-in-scale').forEach(el => revealObserver.observe(el));

            // Initial render
            filterGallery('all');
            document.getElementById('gallery-grid').classList.add('is-visible');

            // Filter button listeners
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    filterGallery(btn.getAttribute('data-category'));
                });
            });
        });
    </script>
</body>
</html>