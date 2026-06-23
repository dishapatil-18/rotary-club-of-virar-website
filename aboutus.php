<?php
session_start();
include 'includes/db_connect.php';
require_once __DIR__ . '/config/club_settings.php';
require_once __DIR__ . '/includes/csrf_helper.php';

function e($s) {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function getSiteContent($conn, $page) {
    $r = $conn->query("SELECT section, content FROM site_content WHERE page = '$page'");
    $content = [];
    if ($r) {
        while ($row = $r->fetch_assoc()) $content[$row['section']] = $row['content'];
    }
    return $content;
}
$aboutContent = getSiteContent($conn, 'about');
$aboutDescription = $aboutContent['about_description'] ?? '';

// Query project count for impact section
$totalProjects = 0;
$r = $conn->query("SELECT COUNT(*) AS c FROM projects");
if ($r) { $totalProjects = (int)$r->fetch_assoc()['c']; }

$focusAreas = [
    ['icon' => 'fa-graduation-cap', 'title' => 'Education', 'desc' => 'Empowering students and youth through scholarships, digital literacy programs, and educational infrastructure support to build a brighter future.'],
    ['icon' => 'fa-heartbeat', 'title' => 'Healthcare', 'desc' => 'Organizing health check-up camps, blood donation drives, and wellness awareness programs for communities in need.'],
    ['icon' => 'fa-tree', 'title' => 'Environment', 'desc' => 'Promoting tree plantation drives, waste management awareness, and sustainability initiatives for a greener planet.'],
    ['icon' => 'fa-fist-raised', 'title' => 'Women Empowerment', 'desc' => 'Conducting skill development workshops, self-defense training, and awareness programs to empower women.'],
    ['icon' => 'fa-users', 'title' => 'Youth Development', 'desc' => 'Mentoring young leaders through leadership camps, career guidance sessions, and Rotary youth exchange programs.'],
    ['icon' => 'fa-home', 'title' => 'Community Welfare', 'desc' => 'Supporting local communities with food drives, disaster relief, sanitation projects, and infrastructure improvements.'],
    ['icon' => 'fa-dove', 'title' => 'Peace and Harmony', 'desc' => 'Promoting understanding, goodwill, and harmony through service, collaboration, and community engagement.'],
];
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Rotary Club of Virar</title>
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
            max-width: 750px;
            margin: 0 auto 32px;
            line-height: 1.7;
            animation: fadeInUp 0.8s ease-out 0.35s forwards;
            opacity: 0;
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

        /* ===== CONTENT CARD ===== */
        .content-card {
            background: white;
            border-radius: 20px;
            padding: 32px;
            border: 1px solid rgba(255, 192, 0, 0.15);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }
        .content-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--rotary-yellow), var(--rotary-indigo));
            background-size: 200% 100%;
            animation: shimmer 3s ease-in-out infinite;
        }
        @keyframes shimmer {
            0%, 100% { background-position: 0% 0%; }
            50% { background-position: 100% 0%; }
        }
        .content-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 50px -12px rgba(10, 35, 66, 0.2);
        }

        /* ===== IMPACT CARD ===== */
        .impact-card {
            background: white;
            border-radius: 20px;
            padding: 28px 24px;
            text-align: center;
            border: 1px solid rgba(255, 192, 0, 0.12);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }
        .impact-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 20px 40px -12px rgba(10, 35, 66, 0.2);
        }
        .impact-card .impact-icon {
            width: 56px;
            height: 56px;
            margin: 0 auto 14px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            transition: all 0.4s;
        }
        .impact-card:hover .impact-icon {
            transform: scale(1.1);
        }
        .impact-card h4 {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 4px;
            color: var(--rotary-blue);
        }
        .impact-card .impact-count {
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 2px;
        }
        .impact-card p {
            font-size: 0.8rem;
            color: #64748b;
            line-height: 1.5;
        }

        /* ===== FOCUS CARD ===== */
        .focus-card {
            background: white;
            border-radius: 20px;
            padding: 32px 24px;
            text-align: center;
            border: 1px solid rgba(255, 192, 0, 0.12);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            overflow: hidden;
        }
        .focus-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--rotary-yellow), #ffb347);
            background-size: 200% 100%;
            animation: shimmer 3s ease-in-out infinite;
        }
        .focus-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 50px -12px rgba(10, 35, 66, 0.2);
        }
        .focus-card .focus-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 16px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            background: rgba(255, 192, 0, 0.1);
            color: var(--rotary-yellow);
            transition: all 0.4s;
        }
        .focus-card:hover .focus-icon {
            background: var(--rotary-yellow);
            color: var(--rotary-blue);
            transform: scale(1.1) rotate(5deg);
        }
        .focus-card h4 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--rotary-blue);
            margin-bottom: 8px;
        }
        .focus-card p {
            font-size: 0.85rem;
            color: #64748b;
            line-height: 1.6;
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

        /* ===== CTA ===== */
        .cta-section {
            background: linear-gradient(135deg, var(--rotary-yellow), #ffb347);
            position: relative;
            overflow: hidden;
        }
        .cta-btn {
            background-color: var(--rotary-blue);
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(10, 35, 66, 0.3);
        }
        .cta-btn:hover {
            background-color: #1e3a5f;
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(10, 35, 66, 0.45);
        }
        .cta-btn-outline {
            border: 2px solid var(--rotary-blue);
            color: var(--rotary-blue);
            transition: all 0.3s ease;
        }
        .cta-btn-outline:hover {
            background-color: var(--rotary-blue);
            color: white;
            transform: translateY(-2px);
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
        }
    </style>
</head>
<body class="min-h-screen">

    <!-- LOGIN MODAL -->
    <div id="login-modal" class="fixed inset-0 z-50 hidden opacity-0 transition-opacity duration-300 modal-backdrop flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-md transform scale-95 transition-all duration-300">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold" style="color: var(--rotary-blue);">Login</h2>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
            </div>
            <form action="login.php" method="POST">
                <?= csrfField() ?>
                <div class="mb-4">
                    <label for="login-email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" id="login-email" name="email" class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500 transition duration-150" placeholder="you@example.com" required>
                </div>
                <div class="mb-6">
                    <label for="login-password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input type="password" id="login-password" name="password" required class="mt-1 block w-full px-4 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-yellow-500 focus:border-yellow-500 transition duration-150" placeholder="••••••••">
                </div>
                <button type="submit" class="w-full py-3 mt-4 text-lg font-semibold rounded-lg bg-yellow-500 hover:bg-yellow-400 transition duration-300 shadow-lg" style="color: var(--rotary-blue);">
                    Login
                </button>
            </form>
        </div>
    </div>

    <!-- HEADER -->
    <header class="sticky top-0 z-40 bg-white shadow-md">
        <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <img src="assets/uploads/Logo/rotary-icon.png" alt="Rotary Logo" class="w-10 h-10 rounded-full object-cover">
                <span class="text-xl font-extrabold tracking-tight" style="color: var(--rotary-blue);">Rotary Club of Virar</span>
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
                    <img src="assets/uploads/Logo/rotary-icon.png" alt="Rotary Logo" onerror="this.style.display='none'">
                    <span>Rotary Club of Virar</span>
                </div>
                <h1 class="hero-title">
                    About Rotary Club of <span class="highlight">Virar</span>
                </h1>
                <p class="hero-subtitle">
                    Dedicated to Service Above Self, creating lasting impact through community service, education, healthcare, environmental initiatives, and humanitarian projects.
                </p>
            </div>
            <div class="hero-scroll-indicator" onclick="document.getElementById('who-we-are').scrollIntoView({behavior:'smooth'})">
                <i class="fas fa-chevron-down"></i>
            </div>
        </section>

        <!-- WHO WE ARE -->
        <section class="py-20" id="who-we-are">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="section-title-wrap fade-in-up">
                    <div class="section-badge">Who We Are</div>
                    <h2 class="section-title">Our Club, Our Mission</h2>
                    <div class="section-title-line"></div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center mb-16">
                    <div class="fade-in-left">
                        <?php if (!empty($aboutDescription)): ?>
                        <p class="text-gray-600 leading-relaxed mb-6"><?= nl2br(e($aboutDescription)) ?></p>
                        <?php else: ?>
                        <p class="text-gray-600 leading-relaxed mb-6">
                            Rotary Club of Virar is a collective of passionate individuals committed to driving meaningful social change through innovation, compassion, and community service. We believe that small actions, when multiplied by many, can transform lives.
                        </p>
                        <p class="text-gray-600 leading-relaxed mb-6">
                            As part of Rotary International, we are guided by the timeless principle of <strong>"Service Above Self"</strong> — dedicating our time, talents, and resources to serve our community and foster goodwill across borders.
                        </p>
                        <p class="text-gray-600 leading-relaxed">
                            Our club focuses on sustainable solutions in education, health, and environment within the Virar area and beyond, creating lasting impact through fellowship, leadership, and humanitarian service.
                        </p>
                        <?php endif; ?>
                    </div>
                    <div class="fade-in-right">
                        <img src="assets/uploads/Logo/rotary-icon.png" alt="Rotary Club of Virar" class="w-48 h-48 rounded-full mx-auto object-cover shadow-2xl border-4 border-[var(--rotary-yellow)]">
                    </div>
                </div>

                <!-- Values Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div class="content-card fade-in-scale" style="transition-delay:0s">
                        <div class="flex items-center gap-4 mb-4">
                            <div class="w-12 h-12 rounded-full flex items-center justify-center text-xl" style="background:rgba(255,192,0,0.15);color:var(--rotary-yellow);">
                                <i class="fas fa-handshake"></i>
                            </div>
                            <h3 class="text-lg font-bold">Fellowship</h3>
                        </div>
                        <p class="text-sm text-gray-500">Building meaningful connections among members through camaraderie, mutual respect, and shared purpose to create a strong, united community of service leaders.</p>
                    </div>
                    <div class="content-card fade-in-scale" style="transition-delay:0.1s">
                        <div class="flex items-center gap-4 mb-4">
                            <div class="w-12 h-12 rounded-full flex items-center justify-center text-xl" style="background:rgba(255,192,0,0.15);color:var(--rotary-yellow);">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <h3 class="text-lg font-bold">Integrity</h3>
                        </div>
                        <p class="text-sm text-gray-500">Upholding the highest ethical standards in all our actions, ensuring transparency, honesty, and accountability in every service project we undertake.</p>
                    </div>
                    <div class="content-card fade-in-scale" style="transition-delay:0.2s">
                        <div class="flex items-center gap-4 mb-4">
                            <div class="w-12 h-12 rounded-full flex items-center justify-center text-xl" style="background:rgba(255,192,0,0.15);color:var(--rotary-yellow);">
                                <i class="fas fa-globe-asia"></i>
                            </div>
                            <h3 class="text-lg font-bold">Service</h3>
                        </div>
                        <p class="text-sm text-gray-500">Dedicating ourselves to humanitarian service that improves lives, strengthens communities, and advances global understanding and peace.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- OUR IMPACT -->
        <section class="py-20 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="section-title-wrap fade-in-up">
                    <div class="section-badge">Our Impact</div>
                    <h2 class="section-title">Making a Difference Together</h2>
                    <div class="section-title-line"></div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
                    <div class="impact-card fade-in-scale">
                        <div class="impact-icon" style="background: #FFC00020; color: #FFC000;">
                            <i class="fas fa-handshake"></i>
                        </div>
                        <div class="impact-count" style="color: #FFC000;"><?= $totalProjects ?></div>
                        <h4>Community Projects</h4>
                        <p>Driving meaningful change through service projects that strengthen communities and improve lives.</p>
                    </div>
                    <div class="impact-card fade-in-scale" style="transition-delay:0.08s">
                        <div class="impact-icon" style="background: #F8717120; color: #F87171;">
                            <i class="fas fa-heartbeat"></i>
                        </div>
                        <div class="impact-count" style="color: #F87171;">500+</div>
                        <h4>Health Beneficiaries</h4>
                        <p>Providing accessible healthcare services and wellness programs to underserved communities.</p>
                    </div>
                    <div class="impact-card fade-in-scale" style="transition-delay:0.16s">
                        <div class="impact-icon" style="background: #10B98120; color: #10B981;">
                            <i class="fas fa-tree"></i>
                        </div>
                        <div class="impact-count" style="color: #10B981;">500+</div>
                        <h4>Trees Planted</h4>
                        <p>Contributing to a greener planet through tree plantation drives and environmental awareness.</p>
                    </div>
                </div>

                <div class="text-center mt-12 fade-in-up">
                    <p class="text-gray-500 text-lg">Through <strong class="text-[var(--rotary-blue)]"><?= $totalProjects ?>+ projects</strong> and countless volunteer hours, we are making a lasting impact in the Virar region and beyond.</p>
                </div>
            </div>
        </section>

        <!-- AREAS OF FOCUS -->
        <section class="py-20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="section-title-wrap fade-in-up">
                    <div class="section-badge">Our Focus</div>
                    <h2 class="section-title">Areas of Impact</h2>
                    <div class="section-title-line"></div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
                    <?php foreach ($focusAreas as $fa): ?>
                    <div class="focus-card fade-in-scale">
                        <div class="focus-icon"><i class="fas <?= $fa['icon'] ?>"></i></div>
                        <h4><?= $fa['title'] ?></h4>
                        <p><?= $fa['desc'] ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- FOUNDER QUOTE -->
        <section class="quote-section py-20 md:py-28 px-4">
            <div class="relative z-10 max-w-4xl mx-auto text-center">
                <div class="flex justify-center mb-6">
                    <div class="w-16 h-16 flex items-center justify-center rounded-full bg-white/10 backdrop-blur-sm border border-white/20">
                        <i class="fas fa-quote-left text-2xl" style="color: var(--rotary-yellow);"></i>
                    </div>
                </div>
                <blockquote class="text-2xl md:text-4xl font-bold text-white leading-tight mb-6" style="font-family: 'Playfair Display', serif;">
                    "Success is not measured by wealth, but by the positive impact you have on others."
                </blockquote>
                <p class="text-lg text-white/60 font-light max-w-2xl mx-auto">
                    ~ Adv. Rtn. Paul Harris, Founder of Rotary
                </p>
            </div>
        </section>

        <!-- CTA SECTION -->
        <section class="cta-section py-16 md:py-20 px-4">
            <div class="relative z-10 max-w-4xl mx-auto text-center">
                <h2 class="text-3xl md:text-4xl font-bold mb-4" style="color: var(--rotary-blue); font-family: 'Playfair Display', serif;">
                    Ready to Be Part of Something Bigger?
                </h2>
                <p class="text-lg mb-8" style="color: var(--rotary-blue); opacity: 0.8;">
                    Join Rotary Club of Virar and help us create lasting change in our community.
                </p>
                <div class="flex flex-wrap justify-center gap-4">
                    <a href="https://form.jotform.com/203628680518460" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 px-8 py-4 rounded-full text-base font-bold cta-btn">
                        <i class="fas fa-user-plus"></i> Join Rotary Club of Virar
                    </a>
                    <a href="contact.php" class="inline-flex items-center gap-2 px-8 py-4 rounded-full text-base font-bold cta-btn-outline">
                        <i class="fas fa-envelope"></i> Contact Us
                    </a>
                </div>
            </div>
        </section>
    </main>

    <!-- SCROLL TO TOP -->
    <button id="scroll-to-top" class="fixed bottom-6 right-6 p-4 rounded-full text-2xl z-30 hidden" onclick="scrollToTop()" aria-label="Scroll to Top">
        <i class="fas fa-arrow-up"></i>
    </button>

    <?php include 'includes/footer.php'; ?>

    <script>
        // === LOGIN MODAL ===
        const loginModal = document.getElementById('login-modal');
        const loginModalContent = loginModal.querySelector('div');
        function openModal() {
            loginModal.classList.remove('hidden', 'opacity-0');
            loginModal.classList.add('flex');
            setTimeout(() => {
                loginModal.classList.add('opacity-100');
                loginModalContent.classList.remove('scale-95');
                loginModalContent.classList.add('scale-100');
            }, 50);
        }
        function closeModal() {
            loginModal.classList.remove('opacity-100');
            loginModalContent.classList.remove('scale-100');
            loginModalContent.classList.add('scale-95');
            setTimeout(() => {
                loginModal.classList.add('hidden', 'opacity-0');
                loginModal.classList.remove('flex');
            }, 300);
        }
        loginModal.addEventListener('click', (e) => { if (e.target === loginModal) closeModal(); });

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

        // === HERO PARALLAX ===
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
