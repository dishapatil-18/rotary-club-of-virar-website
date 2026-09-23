<?php
session_start();
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/config/club_settings.php';
require_once __DIR__ . '/includes/csrf_helper.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/website_settings.php';
$ws = getWebsiteSettings($conn);

// Fetch all active members ordered by display_order
$members = [];
$sql = "SELECT * FROM members WHERE status = 'Active' ORDER BY display_order ASC, name ASC";
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) $members[] = $row;
}

// Separate leadership and BOD
$leadership = [];
$bod = [];
foreach ($members as $m) {
    if (in_array($m['role'], ['President', 'Secretary', 'Treasurer'])) {
        $leadership[] = $m;
    } else {
        $bod[] = $m;
    }
}

function getPhotoUrl($path) {
    if (!empty($path) && file_exists(__DIR__ . '/' . $path)) {
        return $path;
    }
    return '';
}

function getDefaultBio($role) {
    return match($role) {
        'President' => 'Leading with vision, guiding the club toward meaningful service and community impact.',
        'Secretary' => 'Ensuring seamless communication, records, and operational excellence for the club.',
        'Treasurer' => 'Managing club finances with transparency, integrity, and responsible stewardship.',
        default => 'Dedicated to serving the Rotary mission and our community.',
    };
}

// Fetch rotary years for dropdown
$rotaryYears = [];
$ryRes = $conn->query("SELECT id, year_name, is_current FROM rotary_years ORDER BY year_name DESC");
if ($ryRes) { while ($row = $ryRes->fetch_assoc()) $rotaryYears[] = $row; }

// Fetch leadership assignments for all years
$leadershipByYear = [];
if (!empty($rotaryYears)) {
    $sql = "SELECT la.id as assignment_id, la.rotary_year_id, la.role, m.member_id, m.name, m.email, m.phone_number, m.photo_url, m.short_bio, m.profession, m.address
            FROM leadership_assignments la
            JOIN members m ON la.member_id = m.member_id
            ORDER BY la.rotary_year_id, FIELD(la.role, 'President', 'Secretary', 'Treasurer')";
    $lRes = $conn->query($sql);
    if ($lRes) {
        while ($row = $lRes->fetch_assoc()) {
            $leadershipByYear[$row['rotary_year_id']][] = $row;
        }
    }
}

// Find current year ID
$currentYearId = null;
$currentYearName = '';
foreach ($rotaryYears as $y) { if ($y['is_current']) { $currentYearId = $y['id']; $currentYearName = $y['year_name']; break; } }
if (!$currentYearId && !empty($rotaryYears)) { $currentYearId = $rotaryYears[0]['id']; $currentYearName = $rotaryYears[0]['year_name']; }
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($ws['website_name']) ?> - Meet Our Team</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --rotary-blue: #0A2342;
            --rotary-indigo: #4F46E5;
            --rotary-yellow: #FFC000;
            --rotary-green: #10B981;
            --rotary-coral: #F87171;
            --gold: #FFD700;
            --royal: #1a2a6c;
            --dark: #0f172a;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8fafc;
            color: var(--rotary-blue);
            overflow-x: hidden;
        }
        .nav-link {
            color: var(--rotary-blue);
            transition: color 0.3s ease;
            font-weight: 500;
        }
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
        .modal-backdrop {
            background-color: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
        }
        .social-icon { transition: all 0.3s ease; }
        .social-icon:hover {
            color: var(--rotary-yellow);
            text-shadow: 0 0 8px rgba(255, 192, 0, 0.8);
            transform: scale(1.1);
        }
        @media (max-width: 640px) {
            .donate-button { display: inline-block; }
        }

        /* ===== HERO ===== */
        .hero-section {
            position: relative;
            min-height: 70vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: linear-gradient(135deg, #0A2342 0%, #1e3a5f 40%, #2d1b69 100%);
        }
        .hero-overlay {
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 20% 50%, rgba(255, 192, 0, 0.08) 0%, transparent 50%),
                        radial-gradient(circle at 80% 20%, rgba(79, 70, 229, 0.15) 0%, transparent 50%);
        }
        .hero-bg-shapes {
            position: absolute;
            inset: 0;
            overflow: hidden;
            pointer-events: none;
        }
        .hero-shape {
            position: absolute;
            border-radius: 50%;
            opacity: 0.07;
        }
        .hero-shape-1 {
            width: 600px; height: 600px;
            background: var(--rotary-yellow);
            top: -200px; right: -150px;
            animation: floatShape 12s ease-in-out infinite;
        }
        .hero-shape-2 {
            width: 400px; height: 400px;
            background: #818cf8;
            bottom: -100px; left: -100px;
            animation: floatShape 16s ease-in-out infinite reverse;
        }
        .hero-shape-3 {
            width: 300px; height: 300px;
            background: var(--rotary-green);
            top: 50%; left: 60%;
            animation: floatShape 10s ease-in-out infinite 2s;
        }
        @keyframes floatShape {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(30px, -40px) scale(1.05); }
            66% { transform: translate(-20px, 20px) scale(0.95); }
        }

        .hero-grid-dots {
            position: absolute;
            inset: 0;
            background-image: radial-gradient(rgba(255,255,255,0.06) 1px, transparent 1px);
            background-size: 40px 40px;
        }
        .hero-rotary-badge {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            background: rgba(255,255,255,0.08);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 100px;
            padding: 8px 24px 8px 8px;
            margin-bottom: 28px;
            animation: fadeInDown 0.8s ease-out forwards;
            opacity: 0;
        }
        .hero-rotary-badge img {
            width: 36px; height: 36px;
            border-radius: 50%;
            object-fit: contain;
            background: white;
            padding: 4px;
        }
        .hero-rotary-badge span {
            font-size: 0.85rem;
            font-weight: 600;
            color: rgba(255,255,255,0.85);
            letter-spacing: 0.5px;
        }
        .hero-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(2.5rem, 7vw, 5rem);
            font-weight: 800;
            line-height: 1.1;
            color: white;
            margin-bottom: 20px;
            animation: fadeInUp 0.8s ease-out 0.2s forwards;
            opacity: 0;
        }
        .hero-title .highlight {
            background: linear-gradient(135deg, var(--rotary-yellow), #ffb347);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .hero-subtitle {
            font-size: clamp(1rem, 2vw, 1.3rem);
            color: rgba(255,255,255,0.75);
            font-weight: 300;
            max-width: 650px;
            margin: 0 auto 32px;
            line-height: 1.7;
            animation: fadeInUp 0.8s ease-out 0.35s forwards;
            opacity: 0;
        }
        .hero-stats {
            display: flex;
            gap: 40px;
            justify-content: center;
            flex-wrap: wrap;
            animation: fadeInUp 0.8s ease-out 0.5s forwards;
            opacity: 0;
        }
        .hero-stat-item {
            text-align: center;
        }
        .hero-stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--rotary-yellow);
        }
        .hero-stat-label {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.6);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 500;
        }

        .hero-scroll-indicator {
            position: absolute;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            animation: bounceDown 2s infinite;
            color: rgba(255,255,255,0.4);
            font-size: 1.5rem;
            cursor: pointer;
        }
        @keyframes bounceDown {
            0%, 100% { transform: translateX(-50%) translateY(0); opacity: 0.4; }
            50% { transform: translateX(-50%) translateY(8px); opacity: 1; }
        }

        @keyframes fadeInUp {
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeInDown {
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in-up {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.6s ease-out, transform 0.6s ease-out;
        }
        .fade-in-up.is-visible {
            opacity: 1;
            transform: translateY(0);
        }
        .fade-in-left {
            opacity: 0;
            transform: translateX(-40px);
            transition: opacity 0.6s ease-out, transform 0.6s ease-out;
        }
        .fade-in-left.is-visible {
            opacity: 1;
            transform: translateX(0);
        }
        .fade-in-right {
            opacity: 0;
            transform: translateX(40px);
            transition: opacity 0.6s ease-out, transform 0.6s ease-out;
        }
        .fade-in-right.is-visible {
            opacity: 1;
            transform: translateX(0);
        }
        .fade-in-scale {
            opacity: 0;
            transform: scale(0.9);
            transition: opacity 0.5s ease-out, transform 0.5s ease-out;
        }
        .fade-in-scale.is-visible {
            opacity: 1;
            transform: scale(1);
        }

        /* ===== SECTION TITLE ===== */
        .section-title-wrap {
            text-align: center;
            margin-bottom: 50px;
        }
        .section-badge {
            display: inline-block;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 2px;
            padding: 6px 18px;
            border-radius: 100px;
            background: rgba(255, 192, 0, 0.15);
            color: var(--rotary-yellow);
            margin-bottom: 12px;
        }
        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(1.8rem, 4vw, 3rem);
            font-weight: 800;
            color: var(--rotary-blue);
        }
        .section-title-line {
            width: 60px;
            height: 3px;
            background: linear-gradient(90deg, var(--rotary-yellow), var(--rotary-indigo));
            margin: 12px auto 0;
            border-radius: 2px;
        }

        /* ===== LEADERSHIP CARDS ===== */
        .leadership-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 32px;
            max-width: 1000px;
            margin: 0 auto;
        }
        .leader-card {
            position: relative;
            background: white;
            border-radius: 20px;
            padding: 40px 24px 32px;
            text-align: center;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border: 1px solid rgba(255, 192, 0, 0.2);
            overflow: hidden;
        }
        .leader-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--rotary-yellow), #ffb347, var(--rotary-yellow));
            background-size: 200% 100%;
            animation: shimmer 3s ease-in-out infinite;
        }
        @keyframes shimmer {
            0%, 100% { background-position: 0% 0%; }
            50% { background-position: 100% 0%; }
        }
        .leader-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 25px 50px -12px rgba(10, 35, 66, 0.25), 0 0 0 1px rgba(255, 192, 0, 0.3);
        }
        .leader-card .card-glow {
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,192,0,0.06) 0%, transparent 60%);
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.4s;
        }
        .leader-card:hover .card-glow { opacity: 1; }
        .leader-card .crown-icon {
            position: absolute;
            top: 12px;
            right: 16px;
            font-size: 1.2rem;
            color: var(--rotary-yellow);
            opacity: 0.6;
        }
        .leader-card .photo-wrap {
            width: 120px;
            height: 120px;
            margin: 0 auto 16px;
            border-radius: 50%;
            padding: 4px;
            background: linear-gradient(135deg, var(--rotary-yellow), #ffb347);
            box-shadow: 0 0 25px rgba(255, 192, 0, 0.3);
            transition: all 0.4s;
        }
        .leader-card:hover .photo-wrap {
            box-shadow: 0 0 40px rgba(255, 192, 0, 0.5);
            transform: scale(1.05);
        }
        .leader-card .photo-wrap img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            background: #e2e8f0;
        }
        .leader-card .role-badge {
            display: inline-block;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            padding: 5px 14px;
            border-radius: 100px;
            background: linear-gradient(135deg, var(--rotary-yellow), #ffb347);
            color: var(--rotary-blue);
            margin-bottom: 10px;
        }
        .leader-card h3 {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--rotary-blue);
            margin-bottom: 2px;
        }
        .leader-card .desc {
            font-size: 0.85rem;
            color: #64748b;
            margin-bottom: 14px;
            line-height: 1.5;
        }
        .leader-card .social-row {
            display: flex;
            justify-content: center;
            gap: 12px;
            font-size: 1.1rem;
        }
        .leader-card .social-row a {
            color: var(--rotary-blue);
            transition: all 0.3s;
        }
        .leader-card .social-row a:hover {
            color: var(--rotary-yellow);
            transform: scale(1.2);
        }

        /* ===== BOD CARDS ===== */
        .bod-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 24px;
        }
        .bod-card {
            background: white;
            border-radius: 16px;
            padding: 28px 18px 22px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 1px solid rgba(0,0,0,0.04);
            box-shadow: 0 4px 16px rgba(0,0,0,0.04);
        }
        .bod-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px -12px rgba(10, 35, 66, 0.15);
        }
        .bod-card .photo-wrap {
            width: 90px;
            height: 90px;
            margin: 0 auto 12px;
            border-radius: 50%;
            padding: 3px;
            background: linear-gradient(135deg, #e2e8f0, #cbd5e1);
            transition: all 0.3s;
        }
        .bod-card:hover .photo-wrap {
            background: linear-gradient(135deg, var(--rotary-yellow), #ffb347);
            transform: scale(1.05);
        }
        .bod-card .photo-wrap img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            background: #e2e8f0;
        }
        .bod-card .bod-role {
            font-size: 0.65rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--rotary-indigo);
            margin-bottom: 4px;
        }
        .bod-card h3 {
            font-size: 1rem;
            font-weight: 700;
            color: var(--rotary-blue);
            margin-bottom: 2px;
        }
        .bod-card .desc {
            font-size: 0.75rem;
            color: #94a3b8;
            line-height: 1.4;
        }

        /* ===== SECTION DIVIDER ===== */
        .section-divider {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            margin: 60px 0 40px;
            color: #cbd5e1;
        }
        .section-divider .line {
            flex: 1;
            max-width: 120px;
            height: 1px;
            background: linear-gradient(90deg, transparent, #cbd5e1, transparent);
        }

        /* ===== MODAL ===== */
        .member-modal {
            position: fixed;
            inset: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            opacity: 0;
            visibility: hidden;
            transition: all 0.4s ease;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
        }
        .member-modal.active {
            opacity: 1;
            visibility: visible;
        }
        .member-modal .modal-content {
            background: white;
            border-radius: 24px;
            max-width: 520px;
            width: 100%;
            padding: 0;
            overflow: hidden;
            box-shadow: 0 40px 80px rgba(0,0,0,0.3);
            transform: scale(0.9) translateY(20px);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .member-modal.active .modal-content {
            transform: scale(1) translateY(0);
        }
        .modal-close {
            position: absolute;
            top: 16px;
            right: 16px;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(0,0,0,0.5);
            color: white;
            border: none;
            font-size: 1.1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            z-index: 2;
        }
        .modal-close:hover {
            background: rgba(0,0,0,0.7);
            transform: rotate(90deg);
        }
        .modal-img-wrap {
            width: 100%;
            height: 280px;
            overflow: hidden;
            position: relative;
            background: linear-gradient(135deg, var(--rotary-blue), #1e3a5f);
        }
        .modal-img-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .modal-img-wrap .img-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 60%;
            background: linear-gradient(transparent, rgba(0,0,0,0.6));
        }
        .modal-body {
            padding: 28px 32px 32px;
            position: relative;
        }
        .modal-body .modal-avatar {
            position: absolute;
            top: -50px;
            left: 32px;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 4px solid white;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            object-fit: cover;
            background: #e2e8f0;
        }
        .modal-body h2 {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--rotary-blue);
            margin-bottom: 2px;
            margin-top: 24px;
        }
        .modal-body .modal-role {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--rotary-yellow);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
        }
        .modal-body .modal-bio {
            font-size: 0.9rem;
            color: #64748b;
            line-height: 1.7;
            margin-bottom: 16px;
        }
        .modal-body .modal-info {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 18px;
        }
        .modal-body .modal-info-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.85rem;
            color: #475569;
        }
        .modal-body .modal-info-item i {
            width: 18px;
            color: var(--rotary-yellow);
        }
        .modal-body .modal-social {
            display: flex;
            gap: 14px;
            font-size: 1.3rem;
            border-top: 1px solid #e2e8f0;
            padding-top: 16px;
        }
        .modal-body .modal-social a {
            color: var(--rotary-blue);
            transition: all 0.3s;
        }
        .modal-body .modal-social a:hover {
            color: var(--rotary-yellow);
            transform: scale(1.15);
        }

        /* ===== QUOTE ===== */
        .quote-section {
            background: linear-gradient(135deg, var(--rotary-blue), #1e3a5f);
            position: relative;
            overflow: hidden;
        }
        .quote-section::before {
            content: '\201C';
            position: absolute;
            top: -40px;
            left: 20px;
            font-size: 18rem;
            color: rgba(255,255,255,0.04);
            font-family: serif;
            line-height: 1;
        }
        .quote-section::after {
            content: '\201D';
            position: absolute;
            bottom: -80px;
            right: 20px;
            font-size: 18rem;
            color: rgba(255,255,255,0.04);
            font-family: serif;
            line-height: 1;
        }

        /* ===== YEAR SELECTOR ===== */
        .year-selector-wrap select {
            font-size: 1rem;
            padding: 10px 20px;
            border-radius: 100px;
            border: 2px solid var(--rotary-blue);
            color: var(--rotary-blue);
            font-weight: 600;
            background: white;
            cursor: pointer;
            outline: none;
            transition: all 0.3s;
            min-width: 200px;
            text-align: center;
        }
        .year-selector-wrap select:focus {
            border-color: var(--rotary-yellow);
            box-shadow: 0 0 0 3px rgba(255, 192, 0, 0.2);
        }

        .year-section {
            transition: opacity 0.4s ease, transform 0.4s ease;
        }
        .hidden-section {
            display: none;
            opacity: 0;
        }

        /* ===== SCROLL TO TOP ===== */
        #scroll-to-top {
            background-color: var(--rotary-yellow);
            color: var(--rotary-blue);
            box-shadow: 0 4px 15px rgba(255, 192, 0, 0.5);
            transition: all 0.3s ease;
        }
        #scroll-to-top:hover {
            background-color: #ffda6a;
            box-shadow: 0 6px 20px rgba(255, 192, 0, 0.8);
            transform: scale(1.1);
        }

        @media (max-width: 640px) {
            .leadership-grid {
                grid-template-columns: 1fr;
            }
            .bod-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 16px;
            }
            .bod-card { padding: 20px 12px 16px; }
            .bod-card .photo-wrap { width: 70px; height: 70px; }
            .member-modal { padding: 12px; align-items: flex-end; }
            .member-modal .modal-content {
                border-radius: 20px 20px 0 0;
                max-height: 90vh;
                overflow-y: auto;
            }
            .modal-img-wrap { height: 200px; }
            .modal-body .modal-avatar { width: 60px; height: 60px; top: -40px; left: 20px; }
            .modal-body { padding: 20px 20px 24px; }
            .modal-body h2 { font-size: 1.3rem; margin-top: 16px; }
            .hero-stats { gap: 24px; }
        }
        @media (min-width: 641px) and (max-width: 1024px) {
            .leadership-grid {
                grid-template-columns: repeat(2, 1fr);
                max-width: 700px;
            }
            .bod-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        @media (min-width: 1025px) {
            .leadership-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            .bod-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }
    </style>
</head>
<body>

    <!-- Login Modal -->
    <div id="login-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 modal-backdrop transition-opacity duration-300 opacity-0">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm p-8 relative transform scale-95 transition-transform duration-300">
            <button onclick="closeModal()" class="absolute top-4 right-4 text-gray-500 hover:text-gray-900 transition duration-150">
                <i class="fas fa-times text-xl"></i>
            </button>
            <h3 class="text-3xl font-bold mb-6 text-center" style="color: var(--rotary-blue);">Admin Login</h3>
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
                <button type="submit" class="w-full py-3 mt-4 text-lg font-semibold rounded-lg bg-yellow-500 hover:bg-yellow-400 transition duration-300 shadow-lg" style="color: var(--rotary-blue);">
                    Login
                </button>
            </form>
        </div>
    </div>

    <!-- Header -->
    <header class="sticky top-0 z-40 bg-white shadow-md">
        <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <img src="<?= e($ws['website_logo']) ?>" alt="<?= e($ws['website_short_name']) ?> Logo" class="w-10 h-10 rounded-full object-cover">
                <span class="text-xl font-extrabold tracking-tight" style="color: var(--rotary-blue);"><?= e($ws['website_name']) ?></span>
            </div>
            <div class="hidden lg:flex flex-1 justify-center space-x-8">
                <a href="index.php" class="nav-link">Home</a>
                <a href="aboutus.php" class="nav-link">About Us</a>
                <a href="activities.php" class="nav-link"><i class="fas fa-calendar-check mr-1.5"></i>Activities</a>
                <a href="team.php" class="nav-link">Our Team</a>
                <a href="mediaGallery.php" class="nav-link">Media Gallery</a>
                <a href="contact.php" class="nav-link">Contact Us</a>
            </div>
            <div class="hidden lg:flex items-center space-x-4">
                <button onclick="openModal()" class="nav-link font-semibold">Log In</button>
                <a href="donate.php" target="_blank" class="px-6 py-2 rounded-full text-base font-semibold donate-button">Donate</a>
            </div>
            <button id="mobile-menu-button" class="lg:hidden text-2xl focus:outline-none" style="color: var(--rotary-blue);" aria-label="Toggle Menu">
                <i class="fas fa-bars"></i>
            </button>
        </nav>
        <div id="mobile-menu" class="hidden lg:hidden bg-white shadow-lg pb-4 transition-all duration-300">
            <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3 flex flex-col items-center">
                <a href="index.php" class="nav-link block px-3 py-2 rounded-md text-base">Home</a>
                <a href="aboutus.php" class="nav-link block px-3 py-2 rounded-md text-base">About Us</a>
                <a href="activities.php" class="nav-link block px-3 py-2 rounded-md text-base"><i class="fas fa-calendar-check mr-1.5"></i>Activities</a>
                <a href="team.php" class="nav-link block px-3 py-2 rounded-md text-base">Our Team</a>
                <a href="mediaGallery.php" class="nav-link block px-3 py-2 rounded-md text-base">Media Gallery</a>
                <a href="contact.php" class="nav-link block px-3 py-2 rounded-md text-base">Contact Us</a>
                <button onclick="openModal(); toggleMobileMenu()" class="nav-link block px-3 py-2 rounded-md text-base">Log In</button>
                <a href="donate.php" target="_blank" class="w-3/4 text-center mt-2 px-6 py-2 rounded-full text-lg font-semibold donate-button">Donate</a>
            </div>
        </div>
    </header>

    <main>
        <!-- HERO -->
        <section class="hero-section" id="hero">
            <div class="hero-overlay"></div>
            <div class="hero-bg-shapes">
                <div class="hero-shape hero-shape-1"></div>
                <div class="hero-shape hero-shape-2"></div>
                <div class="hero-shape hero-shape-3"></div>
            </div>
            <div class="hero-grid-dots"></div>

            <div class="relative z-10 text-center px-4 max-w-5xl mx-auto py-20 md:py-0">
                <div class="hero-rotary-badge">
                    <img src="<?= e($ws['website_logo']) ?>" alt="<?= e($ws['website_short_name']) ?>" onerror="this.style.display='none'">
                    <span><?= e($ws['website_name']) ?></span>
                </div>

                <h1 class="hero-title">
                    Meet Our <span class="highlight">Leadership</span> Team
                </h1>

                <p class="hero-subtitle">
                    Dedicated leaders united by a common purpose — to serve humanity, build lasting relationships, and create positive change in our community through action and integrity.
                </p>

                <div class="hero-stats">
                    <div class="hero-stat-item">
                        <div class="hero-stat-number"><?= count($leadership) + count($bod) ?></div>
                        <div class="hero-stat-label">Active Members</div>
                    </div>
                    <div class="hero-stat-item">
                        <div class="hero-stat-number"><?= count($leadership) ?></div>
                        <div class="hero-stat-label">Leadership</div>
                    </div>
                    <div class="hero-stat-item">
                        <div class="hero-stat-number"><?= count($bod) ?></div>
                        <div class="hero-stat-label">Board Members</div>
                    </div>
                </div>
            </div>

            <div class="hero-scroll-indicator" onclick="document.getElementById('team-section').scrollIntoView({behavior:'smooth'})">
                <i class="fas fa-chevron-down"></i>
            </div>
        </section>

        <!-- TEAM CONTENT -->
        <section id="team-section" class="py-16 md:py-24 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Year Selector -->
                <div class="year-selector-wrap flex flex-col sm:flex-row items-center justify-center mb-12 space-y-4 sm:space-y-0 sm:space-x-4">
                    <label class="text-lg font-semibold" style="color: var(--rotary-blue);">Select Rotary Year:</label>
                    <select id="year-selector" onchange="showTeamYear(this.value)" class="px-4 py-2 rounded-full border-2 shadow-md">
                        <?php foreach ($rotaryYears as $y): ?>
                        <option value="team-ry-<?= $y['id'] ?>" <?= $y['is_current'] ? 'selected' : '' ?>><?= e($y['year_name']) ?> <?= $y['is_current'] ? '(Current)' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php foreach ($rotaryYears as $yi => $y): 
                    $yearLeaders = $leadershipByYear[$y['id']] ?? [];
                ?>
                <div id="team-ry-<?= $y['id'] ?>" class="year-section <?= $y['is_current'] ? '' : 'hidden-section' ?>">
                    <!-- LEADERSHIP -->
                    <div class="section-title-wrap fade-in-up">
                        <div class="section-badge">Core Leadership</div>
                        <h2 class="section-title">Executive Committee — <?= e($y['year_name']) ?></h2>
                        <div class="section-title-line"></div>
                    </div>

                    <?php if (!empty($yearLeaders)): ?>
                    <div class="leadership-grid">
                        <?php foreach ($yearLeaders as $idx => $m): 
                            $img = getPhotoUrl($m['photo_url'] ?? '');
                            $initials = implode('', array_map(fn($w) => $w[0] ?? '', explode(' ', $m['name'] ?? '')));
                            $desc = ($m['short_bio'] ?? '') ?: getDefaultBio($m['role']);
                            $prof = $m['profession'] ?? '';
                        ?>
                        <div class="leader-card fade-in-scale" style="transition-delay: <?= $idx * 0.1 ?>s" onclick="openMemberModal('<?= e($m['name']) ?>', '<?= e($m['role']) ?>', '<?= e($img) ?>', '<?= e($desc) ?>', '<?= e($m['email'] ?? '') ?>', '<?= e($m['phone_number'] ?? '') ?>', '<?= e($m['address'] ?? '') ?>', '<?= e($initials) ?>', '<?= e($prof) ?>')">
                            <div class="card-glow"></div>
                            <div class="crown-icon"><i class="fas fa-crown"></i></div>
                            <div class="photo-wrap">
                                <?php if ($img): ?>
                                    <img src="<?= e($img) ?>" alt="<?= e($m['name']) ?>" loading="lazy">
                                <?php else: ?>
                                    <div style="width:100%;height:100%;border-radius:50%;background:linear-gradient(135deg,var(--rotary-blue),#1e3a5f);display:flex;align-items:center;justify-content:center;color:var(--rotary-yellow);font-size:2rem;font-weight:800;"><?= e($initials) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="role-badge"><?= e($m['role']) ?></div>
                            <h3><?= e($m['name']) ?></h3>
                            <?php if ($prof): ?><p class="desc font-medium !text-gray-500 !mb-1"><?= e($prof) ?></p><?php endif; ?>
                            <p class="desc"><?= e($desc) ?></p>
                            <div class="social-row">
                                <a href="<?= CLUB_INSTAGRAM_URL ?>" target="_blank" rel="noopener noreferrer" onclick="event.stopPropagation()" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                                <a href="<?= CLUB_FACEBOOK_URL ?>" target="_blank" rel="noopener noreferrer" onclick="event.stopPropagation()" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                                <a href="mailto:<?= e($m['email']) ?>" onclick="event.stopPropagation()" aria-label="Email"><i class="fas fa-envelope"></i></a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="flex items-center justify-center min-h-[200px]">
                        <p class="text-gray-400 text-lg">Leadership data for <?= e($y['year_name']) ?> not yet assigned.</p>
                    </div>
                    <?php endif; ?>

                    <!-- Divider -->
                    <div class="section-divider fade-in-up">
                        <span class="line"></span>
                        <span style="color: var(--rotary-yellow); font-size: 1.2rem;"><i class="fas fa-star"></i></span>
                        <span class="line"></span>
                    </div>

                    <!-- BOD (only shown for current year) -->
                    <?php if ($y['is_current'] && !empty($bod)): ?>
                    <div class="section-title-wrap fade-in-up">
                        <div class="section-badge">Board of Directors</div>
                        <h2 class="section-title">Our Esteemed Board</h2>
                        <div class="section-title-line"></div>
                    </div>

                    <div class="bod-grid">
                        <?php foreach ($bod as $idx => $m): 
                            $img = getPhotoUrl($m['photo_url'] ?? '');
                            $initials = implode('', array_map(fn($w) => $w[0] ?? '', explode(' ', $m['name'] ?? '')));
                            $desc = ($m['short_bio'] ?? '') ?: 'Dedicated to serving the Rotary mission and our community.';
                            $prof = $m['profession'] ?? '';
                            $showAll = intval($m['show_contact'] ?? 0);
                        ?>
                        <div class="bod-card fade-in-scale" style="transition-delay: <?= ($idx % 8) * 0.05 ?>s" onclick="openMemberModal('<?= e($m['name']) ?>', 'Board Member', '<?= e($img) ?>', '<?= e($desc) ?>', '<?= $showAll ? e($m['email'] ?? '') : '' ?>', '<?= $showAll ? e($m['phone_number'] ?? '') : '' ?>', '<?= e($m['address'] ?? '') ?>', '<?= e($initials) ?>', '<?= e($prof) ?>')">
                            <div class="photo-wrap">
                                <?php if ($img): ?>
                                    <img src="<?= e($img) ?>" alt="<?= e($m['name']) ?>" loading="lazy">
                                <?php else: ?>
                                    <div style="width:100%;height:100%;border-radius:50%;background:linear-gradient(135deg,var(--rotary-blue),#1e3a5f);display:flex;align-items:center;justify-content:center;color:var(--rotary-yellow);font-size:1.4rem;font-weight:800;"><?= e($initials) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="bod-role">Board Member</div>
                            <h3><?= e($m['name']) ?></h3>
                            <?php if ($prof): ?><p class="desc font-medium !text-gray-500 !mb-1"><?= e($prof) ?></p><?php endif; ?>
                            <p class="desc"><?= e($desc) ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- QUOTE SECTION -->
        <section class="quote-section py-20 md:py-28 px-4">
            <div class="relative z-10 max-w-4xl mx-auto text-center">
                <div class="flex justify-center mb-6">
                    <div class="w-16 h-16 flex items-center justify-center rounded-full bg-white/10 backdrop-blur-sm border border-white/20">
                        <i class="fas fa-hands-helping text-2xl" style="color: var(--rotary-yellow);"></i>
                    </div>
                </div>
                <blockquote class="text-2xl md:text-4xl font-bold text-white leading-tight mb-6" style="font-family: 'Playfair Display', serif;">
                    "Service Above Self"
                </blockquote>
                <p class="text-lg text-white/60 font-light max-w-2xl mx-auto">
                    The heart of Rotary beats through the dedication of its members — ordinary people doing extraordinary things for the greater good.
                </p>
            </div>
        </section>
    </main>

    <!-- Scroll To Top -->
    <button id="scroll-to-top" class="fixed bottom-6 right-6 p-4 rounded-full text-2xl z-30 hidden" onclick="scrollToTop()" aria-label="Scroll to Top">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- Member Detail Modal -->
    <div id="member-modal" class="member-modal" onclick="closeMemberModal(event)">
        <div class="modal-content" onclick="event.stopPropagation()">
            <button class="modal-close" onclick="closeMemberModal()"><i class="fas fa-times"></i></button>
            <div class="modal-img-wrap">
                <img id="modal-img" src="" alt="Member Photo">
                <div class="img-overlay"></div>
            </div>
            <div class="modal-body">
                <img id="modal-avatar" class="modal-avatar" src="" alt="">
                <h2 id="modal-name"></h2>
                <div id="modal-role" class="modal-role"></div>
                <p id="modal-bio" class="modal-bio"></p>
                <div id="modal-info" class="modal-info"></div>
                <div id="modal-social" class="modal-social">
                    <a href="<?= CLUB_FACEBOOK_URL ?>" target="_blank" rel="noopener noreferrer" id="modal-facebook" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="<?= CLUB_INSTAGRAM_URL ?>" target="_blank" rel="noopener noreferrer" id="modal-instagram" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" id="modal-email" aria-label="Email"><i class="fas fa-envelope"></i></a>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script>
        // === MODAL LOGIN ===
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
        modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

        // === MOBILE MENU ===
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');
        function toggleMobileMenu() { mobileMenu.classList.toggle('hidden'); }
        mobileMenuButton.addEventListener('click', toggleMobileMenu);
        mobileMenu.querySelectorAll('a, button').forEach(link => {
            link.addEventListener('click', () => {
                if (!mobileMenu.classList.contains('hidden')) toggleMobileMenu();
            });
        });

        // === MEMBER MODAL ===
        const memberModal = document.getElementById('member-modal');
        function openMemberModal(name, role, img, desc, email, phone, address, initials, profession) {
            document.getElementById('modal-name').textContent = name;
            document.getElementById('modal-role').textContent = role;
            document.getElementById('modal-bio').textContent = desc;
            document.querySelector('.modal-extra-bio')?.remove();
            const bioP = document.getElementById('modal-bio');
            if (profession) {
                const pDiv = document.createElement('p');
                pDiv.className = 'modal-extra-bio text-sm font-semibold text-gray-500 mb-2';
                pDiv.textContent = profession;
                bioP.parentNode.insertBefore(pDiv, bioP);
            }

            const imgEl = document.getElementById('modal-img');
            const avatarEl = document.getElementById('modal-avatar');
            if (img) {
                imgEl.src = img;
                imgEl.style.display = 'block';
                avatarEl.src = img;
                avatarEl.style.display = 'block';
            } else {
                imgEl.style.display = 'none';
                avatarEl.style.display = 'none';
                document.querySelector('.modal-img-wrap').style.background = 'linear-gradient(135deg, var(--rotary-blue), #1e3a5f)';
                document.querySelector('.modal-img-wrap').innerHTML +=
                    `<div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:5rem;font-weight:800;color:var(--rotary-yellow);">${initials}</div>`;
            }

            const infoDiv = document.getElementById('modal-info');
            infoDiv.innerHTML = '';
            if (email) infoDiv.innerHTML += `<div class="modal-info-item"><i class="fas fa-envelope"></i> ${email}</div>`;
            if (phone) infoDiv.innerHTML += `<div class="modal-info-item"><i class="fas fa-phone"></i> ${phone}</div>`;
            if (address) infoDiv.innerHTML += `<div class="modal-info-item"><i class="fas fa-map-marker-alt"></i> ${address}</div>`;

            document.getElementById('modal-email').href = email ? `mailto:${email}` : '#';

            memberModal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        function closeMemberModal(e) {
            if (e && e.target !== memberModal && e.target.closest('.modal-content')) return;
            memberModal.classList.remove('active');
            document.body.style.overflow = '';
        }
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeMemberModal();
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeModal();
        });

        // === YEAR SWITCHING ===
        function showTeamYear(val) {
            document.querySelectorAll('.year-section').forEach(s => s.classList.add('hidden-section'));
            const target = document.getElementById(val);
            if (target) target.classList.remove('hidden-section');
        }

        // === SCROLL REVEAL ===
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                }
            });
        }, { threshold: 0.15, rootMargin: '0px 0px -50px 0px' });

        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.fade-in-up, .fade-in-left, .fade-in-right, .fade-in-scale').forEach(el => {
                revealObserver.observe(el);
            });
            // Default visible section - match dropdown selection
            const selector = document.getElementById('year-selector');
            if (selector) showTeamYear(selector.value);
            // Ensure hero badge shows
            document.querySelector('.hero-rotary-badge').style.opacity = '1';
        });

        // === SCROLL TO TOP ===
        const scrollBtn = document.getElementById('scroll-to-top');
        window.onscroll = function() {
            if (document.body.scrollTop > 500 || document.documentElement.scrollTop > 500) {
                scrollBtn.classList.remove('hidden');
            } else {
                scrollBtn.classList.add('hidden');
            }
        };
        function scrollToTop() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // === HERO MOUSE PARALLAX ===
        document.getElementById('hero')?.addEventListener('mousemove', (e) => {
            const shapes = document.querySelectorAll('.hero-shape');
            const x = (e.clientX / window.innerWidth - 0.5) * 20;
            const y = (e.clientY / window.innerHeight - 0.5) * 20;
            shapes.forEach((s, i) => {
                const factor = (i + 1) * 0.3;
                s.style.transform = `translate(${x * factor}px, ${y * factor}px)`;
            });
        });
    </script>
</body>
</html>