<?php
require_once __DIR__ . '/includes/session_security.php';
secureSessionStart();
require __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/csrf_helper.php';

$message = '';
$error = '';
$showForm = false;
$validToken = false;

// Clear stale tokens
$conn->query("DELETE FROM password_reset_tokens WHERE expires_at < NOW()");

if (isset($_GET['token'])) {
    $token = trim($_GET['token']);

    $stmt = $conn->prepare("SELECT prt.id, prt.admin_id, prt.token, prt.expires_at, a.name, a.email
        FROM password_reset_tokens prt
        JOIN admins a ON prt.admin_id = a.admin_id
        WHERE prt.token = ? AND prt.used = 0 AND prt.expires_at > NOW()
        LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $tokenData = $result->fetch_assoc();
        $validToken = true;
        $showForm = true;
    } else {
        $error = "Invalid or expired reset link. Please request a new one.";
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_password') {

    if (!validateCsrfToken()) {
        $error = "Invalid form submission. Please try again.";
    } else {

    $token = trim($_POST['token'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');

    if (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match.";
    } else {

        // Verify token again
        $stmt = $conn->prepare("SELECT prt.id, prt.admin_id
            FROM password_reset_tokens prt
            WHERE prt.token = ? AND prt.used = 0 AND prt.expires_at > NOW()
            LIMIT 1");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $tokenRow = $result->fetch_assoc();

            // Update password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $updateStmt = $conn->prepare("UPDATE admins SET password = ? WHERE admin_id = ?");
            $updateStmt->bind_param("si", $hashedPassword, $tokenRow['admin_id']);
            if ($updateStmt->execute()) {

                // Mark token as used
                $markStmt = $conn->prepare("UPDATE password_reset_tokens SET used = 1 WHERE id = ?");
                $markStmt->bind_param("i", $tokenRow['id']);
                $markStmt->execute();
                $markStmt->close();

                $message = "Password has been reset successfully. You can now login.";
                $showForm = false;

            } else {
                $error = "Failed to reset password. Please try again.";
            }
            $updateStmt->close();

        } else {
            $error = "Invalid or expired reset link. Please request a new one.";
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
<title>Reset Password - Rotary Club Virar</title>
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
.card {
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
</style>
</head>
<body>

<div class="card w-full max-w-md p-8 sm:p-10 bg-white rounded-2xl shadow-2xl">

    <div class="flex flex-col items-center mb-8">
        <div class="w-16 h-16 bg-[var(--dark-blue)] rounded-full flex items-center justify-center mb-3">
            <i data-lucide="lock-reset" class="w-8 h-8 text-yellow-400"></i>
        </div>
        <h1 class="text-2xl font-extrabold text-[var(--dark-blue)] text-center">Reset Password</h1>
        <p class="text-sm text-gray-500 mt-1">Choose a new password for your admin account</p>
    </div>

    <?php if (!empty($message)): ?>
    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4 text-sm flex items-center gap-2">
        <i data-lucide="check-circle" class="w-4 h-4 flex-shrink-0"></i>
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4 text-sm flex items-center gap-2">
        <i data-lucide="alert-circle" class="w-4 h-4 flex-shrink-0"></i>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <?php if ($showForm && $validToken): ?>
    <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="reset_password">
        <input type="hidden" name="token" value="<?= htmlspecialchars($_GET['token'] ?? '') ?>">

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
            <div class="relative">
                <i data-lucide="lock" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"></i>
                <input type="password" name="password" required minlength="8" placeholder="Min 8 characters"
                    class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg text-sm">
            </div>
        </div>

        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
            <div class="relative">
                <i data-lucide="lock" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"></i>
                <input type="password" name="confirm_password" required minlength="8" placeholder="Repeat password"
                    class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg text-sm">
            </div>
        </div>

        <button type="submit"
            class="w-full px-6 py-3 bg-yellow-500 text-white font-bold rounded-lg hover:bg-yellow-600 transition-colors shadow-md flex items-center justify-center gap-2">
            <i data-lucide="check" class="w-4 h-4"></i>
            Reset Password
        </button>

    </form>
    <?php elseif (!$showForm && !$message): ?>
    <div class="text-center py-4">
        <p class="text-gray-500 mb-4">This link is invalid or has expired.</p>
    </div>
    <?php endif; ?>

    <div class="mt-6 text-center">
        <a href="login.php"
            class="px-6 py-3 border border-[var(--dark-blue)] text-[var(--dark-blue)] rounded-lg w-full inline-block hover:bg-gray-50 transition-colors text-sm">
            Back to Login
        </a>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', () => { lucide.createIcons(); });
</script>

</body>
</html>
