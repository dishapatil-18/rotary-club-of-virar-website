<?php
session_start();
include 'includes/db_connect.php';
require_once __DIR__ . '/includes/send_email.php';
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
$contactContent = getSiteContent($conn, 'contact');
$contactDescription = $contactContent['contact_description'] ?? '';

$contactMessage = '';
$contactError = '';

// Ensure contact_messages table exists
$conn->query("CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    submitted_at DATETIME NOT NULL,
    is_read TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Handle contact form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'contact_submit') {
    header('Content-Type: application/json; charset=utf-8');

    if (!validateCsrfToken()) {
        echo json_encode(['success' => false, 'message' => 'Invalid form submission. Please refresh the page and try again.']);
        exit;
    }

    $fullName = trim($_POST['fullName'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($fullName === '' || $email === '' || $subject === '' || $message === '') {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
        exit;
    }

    // Store in database
    $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, subject, message, submitted_at) VALUES (?, ?, ?, ?, NOW())");
    if ($stmt) {
        $stmt->bind_param("ssss", $fullName, $email, $subject, $message);
        $stmt->execute();
        $stmt->close();
    }

    // Send email notification to club
    $emailBody = "New contact form submission:\n\n"
                . "Name: $fullName\n"
               . "Email: $email\n"
               . "Subject: $subject\n\n"
               . "Message:\n$message\n\n"
               . "Submitted at: " . date('Y-m-d H:i:s');

    sendEmail(CLUB_EMAIL, "Contact Form: $subject", nl2br($emailBody));

    echo json_encode(['success' => true]);
    exit;
}

// Fetch leadership from members
$leadership = [];
$sql = "SELECT name, role, email, phone_number, photo_url, short_bio AS bio FROM members WHERE role IN ('President','Secretary','Treasurer') AND status='Active' ORDER BY FIELD(role,'President','Secretary','Treasurer')";
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) $leadership[] = $row;
}

// Fallback to admins table if members table has no data
if (empty($leadership)) {
    $sql2 = "SELECT name, email, role, phone, '' AS photo_url, '' AS bio FROM admins WHERE role IN ('President','Secretary','Treasurer') ORDER BY FIELD(role,'President','Secretary','Treasurer')";
    $res2 = $conn->query($sql2);
    if ($res2) {
        while ($row = $res2->fetch_assoc()) {
            $row['phone_number'] = $row['phone'];
            $leadership[] = $row;
        }
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
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= generateCsrfToken() ?>">
    <title>Contact Rotary Club of Virar</title>
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
        .leader-card .photo-placeholder {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--rotary-blue);
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
        .leader-card h4 {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--rotary-blue);
            margin-bottom: 4px;
        }
        .leader-card .contact-info {
            margin-top: 12px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .leader-card .contact-info a,
        .leader-card .contact-info span {
            font-size: 0.85rem;
            color: #64748b;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: color 0.3s;
        }
        .leader-card .contact-info a:hover {
            color: var(--rotary-yellow);
        }

        /* ===== CONTACT INFO CARDS ===== */
        .contact-info-card {
            background: white;
            border-radius: 20px;
            padding: 32px 24px;
            text-align: center;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border: 1px solid rgba(255, 192, 0, 0.15);
            position: relative;
            overflow: hidden;
        }
        .contact-info-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--rotary-yellow), var(--rotary-indigo));
            background-size: 200% 100%;
            animation: shimmer 3s ease-in-out infinite;
        }
        .contact-info-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 50px -12px rgba(10, 35, 66, 0.2);
        }
        .contact-info-card .icon-wrap {
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
        .contact-info-card:hover .icon-wrap {
            background: var(--rotary-yellow);
            color: var(--rotary-blue);
            transform: scale(1.1);
        }
        .contact-info-card h4 {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--rotary-blue);
        }
        .contact-info-card p,
        .contact-info-card a {
            font-size: 0.9rem;
            color: #64748b;
            text-decoration: none;
            transition: color 0.3s;
        }
        .contact-info-card a:hover {
            color: var(--rotary-yellow);
        }

        /* ===== FORM ===== */
        .form-card {
            background: white;
            border-radius: 20px;
            padding: 48px 40px;
            border: 1px solid rgba(255, 192, 0, 0.15);
            position: relative;
            overflow: hidden;
        }
        .form-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--rotary-yellow), #ffb347, var(--rotary-yellow));
            background-size: 200% 100%;
            animation: shimmer 3s ease-in-out infinite;
        }
        .form-card input,
        .form-card textarea {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            background: #f8fafc;
            outline: none;
        }
        .form-card input:focus,
        .form-card textarea:focus {
            border-color: var(--rotary-yellow);
            box-shadow: 0 0 0 4px rgba(255, 192, 0, 0.15);
            background: white;
        }
        .form-card textarea {
            resize: none;
        }
        .form-card label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--rotary-blue);
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .form-card label i {
            color: var(--rotary-yellow);
            width: 16px;
            text-align: center;
        }
        .btn-submit {
            width: 100%;
            padding: 16px 32px;
            background: linear-gradient(135deg, var(--rotary-yellow), #ffb347);
            color: var(--rotary-blue);
            font-weight: 700;
            font-size: 1rem;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(255, 192, 0, 0.3);
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(255, 192, 0, 0.45);
        }
        .btn-submit:active {
            transform: translateY(0);
        }

        /* ===== SOCIAL MEDIA ===== */
        .social-media-card {
            background: white;
            border-radius: 20px;
            padding: 32px;
            text-align: center;
            border: 1px solid rgba(255, 192, 0, 0.15);
        }
        .social-icon-lg {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--rotary-blue);
            color: white;
            font-size: 1.4rem;
            transition: all 0.3s ease;
        }
        .social-icon-lg:hover {
            background: var(--rotary-yellow);
            color: var(--rotary-blue);
            transform: scale(1.1) rotate(5deg);
        }

        /* ===== MAP ===== */
        .map-card {
            background: white;
            border-radius: 20px;
            padding: 32px;
            border: 1px solid rgba(255, 192, 0, 0.15);
            position: relative;
            overflow: hidden;
        }
        .map-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--rotary-yellow), var(--rotary-indigo));
            background-size: 200% 100%;
            animation: shimmer 3s ease-in-out infinite;
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

        /* ===== SUCCESS MODAL ===== */
        .success-modal-overlay {
            background-color: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
        }

        /* ===== FORM ERROR ===== */
        .form-error {
            border-color: #ef4444 !important;
            box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.1) !important;
        }
    </style>
    <noscript>
        <style>
            .fade-in-up, .fade-in-left, .fade-in-right, .fade-in-scale { opacity: 1 !important; transform: none !important; }
        </style>
    </noscript>
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
                    Let's <span class="highlight">Connect</span>
                </h1>
                <p class="hero-subtitle">
                    <?= e($contactDescription ?: "We're here to listen, collaborate, and make a difference. Reach out to us anytime — your ideas and feedback matter.") ?>
                </p>
            </div>
            <div class="hero-scroll-indicator" onclick="document.getElementById('contact-section').scrollIntoView({behavior:'smooth'})">
                <i class="fas fa-chevron-down"></i>
            </div>
        </section>

        <!-- CONTACT INFO CARDS -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20" id="contact-section">
            <div class="section-title-wrap fade-in-up">
                <div class="section-badge">Get In Touch</div>
                <h2 class="section-title">How to Reach Us</h2>
                <div class="section-title-line"></div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-4xl mx-auto">
                <div class="contact-info-card fade-in-scale" style="transition-delay: 0s">
                    <div class="icon-wrap"><i class="fas fa-phone-alt"></i></div>
                    <h4>Call Us</h4>
                    <a href="tel:<?= CLUB_PHONE ?>"><?= CLUB_PHONE ?></a>
                </div>
                <div class="contact-info-card fade-in-scale" style="transition-delay: 0.1s">
                    <div class="icon-wrap"><i class="fas fa-envelope"></i></div>
                    <h4>Email Us</h4>
                    <a href="mailto:<?= CLUB_EMAIL ?>"><?= CLUB_EMAIL ?></a>
                </div>
                <div class="contact-info-card fade-in-scale" style="transition-delay: 0.2s">
                    <div class="icon-wrap"><i class="fas fa-map-marker-alt"></i></div>
                    <h4>Visit Us</h4>
                    <p><?= CLUB_ADDRESS ?></p>
                </div>
            </div>
        </section>

        <!-- FORM + SOCIAL -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-20">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
                <!-- Contact Form -->
                <div class="lg:col-span-2 form-card fade-in-left">
                    <div class="section-title-wrap text-left mb-8" style="text-align:left;">
                        <div class="section-badge">Send a Message</div>
                        <h2 class="section-title" style="font-size:1.8rem;">We'd Love to Hear From You</h2>
                        <div class="section-title-line" style="margin:12px 0 0;"></div>
                    </div>

                    <form id="contact-form" onsubmit="handleFormSubmit(event)">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label for="full-name"><i class="fas fa-user"></i> Full Name</label>
                                <input type="text" id="full-name" name="fullName" required>
                            </div>
                            <div>
                                <label for="email-address"><i class="fas fa-envelope"></i> Email Address</label>
                                <input type="email" id="email-address" name="email" required>
                            </div>
                        </div>
                        <div class="mb-6">
                            <label for="subject"><i class="fas fa-tag"></i> Subject</label>
                            <input type="text" id="subject" name="subject" required>
                        </div>
                        <div class="mb-8">
                            <label for="message"><i class="fas fa-comment-dots"></i> Message</label>
                            <textarea id="message" name="message" rows="5" required></textarea>
                        </div>
                        <button type="submit" class="btn-submit">
                            Send Message <i class="fas fa-paper-plane ml-2"></i>
                        </button>
                    </form>
                </div>

                <!-- Social Media Sidebar -->
                <div class="flex flex-col gap-6">
                    <div class="social-media-card fade-in-right">
                        <div class="section-badge mb-4">Follow Us</div>
                        <h3 class="text-xl font-bold mb-6" style="color:var(--rotary-blue);">Stay Connected</h3>
                        <div class="flex justify-center gap-6">
                            <a href="<?= CLUB_FACEBOOK_URL ?>" target="_blank" rel="noopener noreferrer" class="social-icon-lg" aria-label="Facebook">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <a href="<?= CLUB_INSTAGRAM_URL ?>" target="_blank" rel="noopener noreferrer" class="social-icon-lg" aria-label="Instagram">
                                <i class="fab fa-instagram"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Official Emails -->
                    <div class="social-media-card fade-in-right" style="transition-delay:0.1s;">
                        <div class="section-badge mb-4">Official</div>
                        <h3 class="text-xl font-bold mb-4" style="color:var(--rotary-blue);">Other Inquiries</h3>
                        <div class="space-y-4 text-left">
                            <div>
                                <p class="text-sm text-gray-500 font-medium">General</p>
                                <a href="mailto:<?= CLUB_EMAIL ?>" class="text-sm font-semibold hover:text-[var(--rotary-yellow)] transition"><?= CLUB_EMAIL ?></a>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 font-medium">Youth</p>
                                <a href="mailto:rotaractclubvirar3141@gmail.com" class="text-sm font-semibold hover:text-[var(--rotary-yellow)] transition">rotaryvirar.youth@gmail.com</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- LEADERSHIP SECTION -->
        <section class="bg-gray-50 py-20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="section-title-wrap fade-in-up">
                    <div class="section-badge">Core Leadership</div>
                    <h2 class="section-title">Connect with Our Leaders</h2>
                    <div class="section-title-line"></div>
                </div>

                <div class="leadership-grid">
                    <?php if (!empty($leadership)): ?>
                        <?php foreach ($leadership as $idx => $m): 
                            $img = getPhotoUrl($m['photo_url'] ?? '');
                            $initials = '';
                            if (!empty($m['name'])) {
                                $parts = explode(' ', $m['name']);
                                foreach ($parts as $p) if (!empty(trim($p))) $initials .= strtoupper($p[0]);
                            }
                            $bio = !empty($m['bio']) ? $m['bio'] : getDefaultBio($m['role']);
                            $email = $m['email'] ?? '';
                            $phone = $m['phone_number'] ?? '';
                        ?>
                        <div class="leader-card fade-in-scale" style="transition-delay: <?= $idx * 0.1 ?>s">
                            <div class="card-glow"></div>
                            <div class="photo-wrap">
                                <?php if ($img): ?>
                                    <img src="<?= e($img) ?>" alt="<?= e($m['name']) ?>">
                                <?php else: ?>
                                    <div class="photo-placeholder"><?= e($initials ?: '?') ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="role-badge"><?= e($m['role']) ?></div>
                            <h4><?= e($m['name']) ?></h4>
                            <p class="text-sm text-gray-500 px-2"><?= e($bio) ?></p>
                            <div class="contact-info">
                                <?php if ($email): ?>
                                    <a href="mailto:<?= e($email) ?>"><i class="fas fa-envelope"></i> <?= e($email) ?></a>
                                <?php endif; ?>
                                <?php if ($phone): ?>
                                    <span><i class="fas fa-phone"></i> <?= e($phone) ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Static fallback if no DB data -->
                        <div class="leader-card fade-in-scale">
                            <div class="card-glow"></div>
                            <div class="photo-wrap"><div class="photo-placeholder">UK</div></div>
                            <div class="role-badge">President</div>
                            <h4>Rtn. Urmila Katkar</h4>
                            <p class="text-sm text-gray-500 px-2">Leading with vision, guiding the club toward meaningful service and community impact.</p>
                            <div class="contact-info">
                                <a href="mailto:urmilaask555@gmail.com"><i class="fas fa-envelope"></i> urmilaask555@gmail.com</a>
                            </div>
                        </div>
                        <div class="leader-card fade-in-scale" style="transition-delay:0.1s">
                            <div class="card-glow"></div>
                            <div class="photo-wrap"><div class="photo-placeholder">PS</div></div>
                            <div class="role-badge">Secretary</div>
                            <h4>Rtn. Prashant Satvi</h4>
                            <p class="text-sm text-gray-500 px-2">Ensuring seamless communication, records, and operational excellence for the club.</p>
                            <div class="contact-info">
                                <a href="mailto:prashantsatvi@gmail.com"><i class="fas fa-envelope"></i> prashantsatvi@gmail.com</a>
                            </div>
                        </div>
                        <div class="leader-card fade-in-scale" style="transition-delay:0.2s">
                            <div class="card-glow"></div>
                            <div class="photo-wrap"><div class="photo-placeholder">PP</div></div>
                            <div class="role-badge">Treasurer</div>
                            <h4>Rtn. Priya Purandare</h4>
                            <p class="text-sm text-gray-500 px-2">Managing club finances with transparency, integrity, and responsible stewardship.</p>
                            <div class="contact-info">
                                <a href="mailto:purandarepriya@gmail.com"><i class="fas fa-envelope"></i> purandarepriya@gmail.com</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- MAP SECTION -->
        <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
            <div class="section-title-wrap fade-in-up">
                <div class="section-badge">Our Location</div>
                <h2 class="section-title">Find Us Here</h2>
                <div class="section-title-line"></div>
            </div>

            <div class="map-card max-w-5xl mx-auto fade-in-up">
                <div class="w-full h-80 rounded-xl overflow-hidden mb-6">
                    <iframe src="https://www.google.com/maps?q=<?= urlencode(CLUB_ADDRESS) ?>&output=embed" width="100%" height="100%" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
                </div>
                <p class="text-center text-gray-600 font-medium flex items-center justify-center gap-2 mb-4">
                    <i class="fas fa-map-pin text-red-500"></i>
                    <?= CLUB_ADDRESS ?>
                </p>
                <div class="flex justify-center">
                    <a href="<?= CLUB_MAP_URL ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 px-6 py-3 bg-[var(--rotary-blue)] text-white rounded-xl hover:bg-blue-900 transition duration-300 font-medium text-sm shadow-lg">
                        <i class="fas fa-map-marked-alt"></i> View on Map
                    </a>
                </div>
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

    <!-- SCROLL TO TOP -->
    <button id="scroll-to-top" class="fixed bottom-6 right-6 p-4 rounded-full text-2xl z-30 hidden" onclick="scrollToTop()" aria-label="Scroll to Top">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- SUCCESS MODAL -->
    <div id="success-modal" class="fixed inset-0 hidden items-center justify-center z-50 p-4 success-modal-overlay" onclick="closeSuccessModal()">
        <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-sm w-full transform transition-all duration-300 text-center" onclick="event.stopPropagation()">
            <div class="flex flex-col items-center">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-4">
                    <i class="fas fa-check-circle text-3xl text-green-600"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-800 mb-2">Message Sent Successfully!</h3>
                <p class="text-gray-600 mb-6">We appreciate your message and will get back to you soon.</p>
                <button onclick="closeSuccessModal()" class="px-6 py-2 bg-yellow-500 text-white font-semibold rounded-lg hover:bg-yellow-600 transition duration-150">
                    Close
                </button>
            </div>
        </div>
    </div>

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

        // === SUCCESS MODAL ===
        const successModal = document.getElementById('success-modal');
        function showSuccessModal() {
            successModal.classList.remove('hidden');
            successModal.classList.add('flex');
        }
        function closeSuccessModal() {
            successModal.classList.add('hidden');
            successModal.classList.remove('flex');
        }

        // === FORM SUBMISSION ===
        const handleFormSubmit = (event) => {
            event.preventDefault();
            
            const form = document.getElementById('contact-form');
            const submitBtn = form.querySelector('.btn-submit');
            let isValid = true;
            
            form.querySelectorAll('[required]').forEach(input => {
                if (input.value.trim() === '') {
                    input.classList.add('form-error');
                    isValid = false;
                } else {
                    input.classList.remove('form-error');
                }
            });

            if (isValid) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = 'Sending... <i class="fas fa-spinner fa-spin ml-2"></i>';

                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const formData = new FormData();
                formData.append('action', 'contact_submit');
                formData.append('_csrf_token', csrfToken);
                formData.append('fullName', document.getElementById('full-name').value);
                formData.append('email', document.getElementById('email-address').value);
                formData.append('subject', document.getElementById('subject').value);
                formData.append('message', document.getElementById('message').value);

                fetch(window.location.href, { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = 'Send Message <i class="fas fa-paper-plane ml-2"></i>';
                        if (data.success) {
                            form.reset();
                            showSuccessModal();
                        } else {
                            alert(data.message || 'Failed to send message. Please try again.');
                        }
                    })
                    .catch(() => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = 'Send Message <i class="fas fa-paper-plane ml-2"></i>';
                        alert('Network error. Please try again.');
                    });
            }
        };

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
