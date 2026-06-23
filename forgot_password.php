<?php
require_once __DIR__ . '/includes/session_security.php';
secureSessionStart();
require __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/send_email.php';
require_once __DIR__ . '/includes/csrf_helper.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!validateCsrfToken()) {
        $error = "Invalid form submission. Please try again.";
    } else {

    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {

        $stmt = $conn->prepare("SELECT admin_id, name, email FROM admins WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $admin = $result->fetch_assoc();

            // Generate secure token
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));

            $insertStmt = $conn->prepare("INSERT INTO password_reset_tokens (admin_id, token, expires_at) VALUES (?, ?, ?)");
            $insertStmt->bind_param("iss", $admin['admin_id'], $token, $expiresAt);
            $insertStmt->execute();
            $insertStmt->close();

            $resetLink = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . "://$_SERVER[HTTP_HOST]" . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . "/reset_password.php?token=" . urlencode($token);

            $emailBody = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <div style='text-align: center; margin-bottom: 20px;'>
                        <h2 style='color: #0A2342;'>Rotary Club of Virar</h2>
                        <p style='color: #64748b;'>Admin Password Reset</p>
                    </div>
                    <div style='background: #f8fafc; border-radius: 12px; padding: 24px; border: 1px solid #e2e8f0;'>
                        <p>Hello " . htmlspecialchars($admin['name']) . ",</p>
                        <p>We received a request to reset your admin password. Click the button below to set a new password:</p>
                        <div style='text-align: center; margin: 24px 0;'>
                            <a href='" . $resetLink . "' style='display: inline-block; padding: 14px 32px; background: #FFC000; color: #0A2342; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 16px;'>Reset Password</a>
                        </div>
                        <p style='color: #94a3b8; font-size: 14px;'>This link expires in 30 minutes. If you did not request this, please ignore this email.</p>
                    </div>
                    <div style='text-align: center; margin-top: 20px; color: #94a3b8; font-size: 12px;'>
                        <p>Rotary Club of Virar &bull; Service Above Self</p>
                    </div>
                </div>
            ";

            $sendResult = sendEmail($email, 'Password Reset - Rotary Club Virar Admin', $emailBody);

            if ($sendResult['success']) {
                $message = "Password reset link has been sent to your email.";
            } else {
                $error = "Failed to send email. Please try again later.";
            }

        } else {
            // Don't reveal whether email exists
            $message = "If an account with that email exists, a reset link has been sent.";
        }

        $stmt->close();
    }
    }
}

// Clear any stale tokens older than 30 minutes
$conn->query("DELETE FROM password_reset_tokens WHERE expires_at < NOW()");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Forgot Password - Rotary Club Virar</title>
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
            <i data-lucide="key-round" class="w-8 h-8 text-yellow-400"></i>
        </div>
        <h1 class="text-2xl font-extrabold text-[var(--dark-blue)] text-center">Forgot Password</h1>
        <p class="text-sm text-gray-500 mt-1">Enter your email to receive a reset link</p>
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

    <form method="POST">
        <?= csrfField() ?>

        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
            <div class="relative">
                <i data-lucide="mail" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"></i>
                <input type="email" name="email" required placeholder="Enter your admin email"
                    class="w-full pl-10 pr-4 py-3 border border-gray-300 rounded-lg text-sm">
            </div>
        </div>

        <button type="submit"
            class="w-full px-6 py-3 bg-yellow-500 text-white font-bold rounded-lg hover:bg-yellow-600 transition-colors shadow-md flex items-center justify-center gap-2">
            <i data-lucide="send" class="w-4 h-4"></i>
            Send Reset Link
        </button>

    </form>

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
