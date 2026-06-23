<?php
require_once __DIR__ . '/includes/session_security.php';
secureSessionStart();
require __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/csrf_helper.php';

$error = "";

if (!isset($_SESSION['form_login_attempts'])) {
    $_SESSION['form_login_attempts'] = 0;
}

if ($_SESSION['form_login_attempts'] >= 10) {
    $error = "Too many failed attempts. Try again later.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SESSION['form_login_attempts'] < 10) {

    if (!validateCsrfToken()) {
        $error = "Invalid form submission. Please try again.";
        $_SESSION['form_login_attempts']++;
    } else {

    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = "Please fill in both fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {

        $stmt = $conn->prepare("SELECT admin_id, name, role, photo_url, password FROM admins WHERE email = ? AND status = 'active'");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $admin = $result->fetch_assoc();

            if (password_verify($password, $admin['password'])) {

                $_SESSION['form_login_attempts'] = 0;

                regenerateSession();

                $_SESSION['admin_id']     = $admin['admin_id'];
                $_SESSION['admin_name']   = $admin['name'];
                $_SESSION['admin_role']   = $admin['role'];
                $_SESSION['admin_photo']  = $admin['photo_url'];
                $_SESSION['is_logged_in'] = true;
                $_SESSION['last_activity'] = time();

                header("Location: Admin/dashboard.php");
                exit;

            } else {
                $_SESSION['form_login_attempts']++;
                $error = "Invalid password.";
            }

        } else {
            $_SESSION['form_login_attempts']++;
            $error = "Invalid email.";
        }

        $stmt->close();
    }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rotary Club Admin Login</title>

<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">

<style>
:root {
    --primary-yellow: #facc15;
    --dark-blue: #1a365d;
}
body {
    font-family: 'Inter', sans-serif;
    background: linear-gradient(135deg, #f7f9fb 0%, #e8edf2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    margin: 0;
    padding: 1rem;
}
.login-card {
    animation: fadeIn 0.5s ease-out;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
}
input:focus {
    border-color: var(--primary-yellow) !important;
    box-shadow: 0 0 0 3px rgba(250, 202, 21, 0.4);
}
.role-badge {
    background: linear-gradient(135deg, var(--dark-blue), #2a4a7f);
}
</style>
</head>

<body>

<div class="login-card w-full max-w-md p-8 sm:p-10 bg-white rounded-2xl shadow-2xl">

    <div class="flex flex-col items-center mb-8">
        <div class="w-16 h-16 bg-[var(--dark-blue)] rounded-full flex items-center justify-center mb-3">
            <i data-lucide="shield-check" class="w-8 h-8 text-yellow-400"></i>
        </div>
        <h1 class="text-2xl font-extrabold text-[var(--dark-blue)] text-center">Rotary Club Virar</h1>
        <p class="text-sm text-gray-500 mt-1">Admin Dashboard Login</p>
    </div>

    <?php if (!empty($error)): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4 text-sm flex items-center gap-2">
        <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0"></i>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <?php if ($_SESSION['form_login_attempts'] >= 10): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4 text-sm flex items-center gap-2">
        <i data-lucide="lock" class="w-4 h-4 flex-shrink-0"></i>
        Account locked due to too many failed attempts. Please try later.
    </div>
    <?php endif; ?>

    <form method="POST">
        <?= csrfField() ?>

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <div class="relative">
                <i data-lucide="mail" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"></i>
                <input type="email" name="email" required placeholder="Enter your email"
                    class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg text-sm">
            </div>
        </div>

        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
            <div class="relative">
                <i data-lucide="lock" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"></i>
                <input type="password" name="password" required placeholder="Enter your password"
                    class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg text-sm">
            </div>
        </div>

        <button type="submit"
            class="w-full px-6 py-3 bg-yellow-500 text-white font-bold rounded-lg hover:bg-yellow-600 transition-colors shadow-md flex items-center justify-center gap-2">
            <i data-lucide="log-in" class="w-4 h-4"></i>
            Sign In
        </button>

        <div class="mt-4 text-center">
            <a href="forgot_password.php" class="text-sm text-gray-500 hover:text-[var(--dark-blue)] transition-colors">
                Forgot Password?
            </a>
        </div>

    </form>

    <div class="mt-6 text-center">
        <button onclick="window.location.href='index.php'"
            class="px-6 py-3 border border-[var(--dark-blue)] text-[var(--dark-blue)] rounded-lg w-full hover:bg-gray-50 transition-colors text-sm">
            Back to Home
        </button>
    </div>

    <div class="mt-6 pt-4 border-t border-gray-100">
        <p class="text-xs text-gray-400 text-center mb-2">Authorized Admin Roles</p>
        <div class="flex justify-center gap-2 flex-wrap">
            <span class="role-badge text-yellow-300 text-xs px-3 py-1 rounded-full font-medium">Super Admin</span>
            <span class="role-badge text-yellow-300 text-xs px-3 py-1 rounded-full font-medium">President</span>
            <span class="role-badge text-yellow-300 text-xs px-3 py-1 rounded-full font-medium">Secretary</span>
            <span class="role-badge text-yellow-300 text-xs px-3 py-1 rounded-full font-medium">Treasurer</span>
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', () => { lucide.createIcons(); });
</script>

</body>
</html>
