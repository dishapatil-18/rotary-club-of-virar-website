<?php
require_once __DIR__ . '/includes/session_security.php';
secureSessionStart();
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/website_settings.php';
$_ws = getWebsiteSettings($conn);

// If already confirmed, destroy session now
if (isset($_GET['confirm']) && $_GET['confirm'] === '1') {
    destroySession();
    header("Location: index.php?logged_out=1");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logout - <?= e($_ws['website_short_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
    <style>
        :root { --primary-yellow: #facc15; --dark-blue: #1a365d; }
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f7f9fb;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .fade-out {
            animation: fadeOut 0.5s forwards;
        }
        @keyframes fadeOut {
            from { opacity: 1; transform: scale(1); }
            to { opacity: 0; transform: scale(0.9); }
        }
    </style>
</head>
<body>

    <div id="logout-card" class="w-full max-w-sm p-8 bg-white rounded-2xl shadow-xl text-center transition duration-300">
        <div class="flex flex-col items-center mb-6">
            <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mb-3">
                <i data-lucide="log-out" class="w-6 h-6 text-red-600"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-800">Are you sure you want to log out?</h1>
        </div>
        <div class="space-y-3">
            <button onclick="handleLogout(true)"
                class="w-full px-6 py-3 bg-red-500 text-white font-bold rounded-lg hover:bg-red-600 transition duration-150 shadow-md">
                <i data-lucide="check" class="w-4 h-4 inline mr-2"></i>
                Yes, Logout
            </button>
            <button onclick="handleLogout(false)"
                class="w-full px-6 py-3 bg-gray-200 text-gray-700 font-semibold rounded-lg hover:bg-gray-300 transition duration-150 shadow-sm">
                <i data-lucide="x" class="w-4 h-4 inline mr-2"></i>
                Cancel
            </button>
        </div>
    </div>

    <div id="success-card" class="w-full max-w-sm p-8 bg-white rounded-2xl shadow-xl text-center hidden">
        <div class="flex flex-col items-center">
            <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-4">
                <i data-lucide="smile" class="w-8 h-8 text-green-600"></i>
            </div>
            <h3 class="text-2xl font-bold text-gray-800 mb-2">Logged Out Successfully!</h3>
            <p class="text-gray-600 mb-6">You have been securely logged out.</p>
            <p class="text-sm text-gray-500 mt-4">Redirecting to Home in <span id="redirect-timer">2</span> seconds...</p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => { lucide.createIcons(); });

        const logoutCard = document.getElementById('logout-card');
        const successCard = document.getElementById('success-card');
        const redirectTimer = document.getElementById('redirect-timer');

        const handleLogout = (confirmLogout) => {
            if (confirmLogout) {
                logoutCard.classList.add('fade-out');
                setTimeout(() => {
                    logoutCard.classList.add('hidden');
                    successCard.classList.remove('hidden');
                    window.location.href = 'logout.php?confirm=1';
                }, 500);
            } else {
                window.location.href = 'Admin/dashboard.php';
            }
        };
    </script>
</body>
</html>
