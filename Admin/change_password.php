<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}
require __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/communication_engine.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../config/club_settings.php';

$message = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!validateCsrfToken()) {
        $message = 'Invalid form submission. Please try again.';
        $msgType = 'error';
    } else {
    $current    = $_POST['current_password'] ?? '';
    $new        = $_POST['new_password'] ?? '';
    $confirm    = $_POST['confirm_password'] ?? '';

    if ($current === '' || $new === '' || $confirm === '') {
        $message = 'All fields are required.';
        $msgType = 'error';
    } elseif ($new !== $confirm) {
        $message = 'New password and confirmation do not match.';
        $msgType = 'error';
    } elseif (strlen($new) < 8) {
        $message = 'New password must be at least 8 characters.';
        $msgType = 'error';
    } else {
        $stmt = $conn->prepare("SELECT password FROM admins WHERE admin_id = ?");
        $stmt->bind_param("i", $_SESSION['admin_id']);
        $stmt->execute();
        $res = $stmt->get_result();
        $admin = $res->fetch_assoc();
        $stmt->close();

        if (!password_verify($current, $admin['password'])) {
            $message = 'Current password is incorrect.';
            $msgType = 'error';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $upd = $conn->prepare("UPDATE admins SET password = ? WHERE admin_id = ?");
            $upd->bind_param("si", $hash, $_SESSION['admin_id']);
            if ($upd->execute()) {
                $message = 'Password changed successfully!';
                $msgType = 'success';
                logAudit($conn, 'Authentication', 'Password Changed', 'Admin changed their own password.', 'CRITICAL', 'success');

                // Send password change notification email
                $stmt2 = $conn->prepare("SELECT name, email FROM admins WHERE admin_id = ?");
                $stmt2->bind_param("i", $_SESSION['admin_id']);
                $stmt2->execute();
                $adminInfo = $stmt2->get_result()->fetch_assoc();
                $stmt2->close();

                if ($adminInfo && !empty($adminInfo['email'])) {
                    $subject = "Your " . CLUB_NAME . " Admin Password Was Changed";
                    $body = "
                    <p>Hi " . htmlspecialchars($adminInfo['name']) . ",</p>
                    <p>Your admin account password for " . CLUB_NAME . " was just changed.</p>
                    <p>If you made this change, no further action is needed.</p>
                    <p>If you did <strong>not</strong> make this change, please contact the club administrator immediately.</p>
                    <p style='color:#999;font-size:12px;'>This is an automated security notification from the " . CLUB_NAME . " website.</p>
                    ";
                    commSendEmail($conn, $adminInfo['email'], $subject, $body, 'Authentication', 'Password Change Notification', 'Password change notification sent to ' . $adminInfo['email']);
                }
            } else {
                $message = 'Failed to update password. Try again.';
                $msgType = 'error';
            }
            $upd->close();
        }
    }
    }
}
?>
<?php
$pageTitle = 'Change Password';
$activeNav = 'settings';
require __DIR__ . '/includes/admin_head.php';
require __DIR__ . '/includes/admin_header.php';
?>

<div class="card">
    <div class="card-body">
        <?php if ($message): ?>
        <div class="mb-5 px-4 py-3 rounded-lg text-sm flex items-center gap-2 <?= $msgType === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700' ?>">
            <i data-lucide="<?= $msgType === 'success' ? 'check-circle' : 'alert-circle' ?>" class="w-4 h-4 flex-shrink-0"></i>
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <div class="bg-white rounded-2xl shadow-xl p-6 sm:p-8 border-t-4 border-yellow-400">
            <form method="POST" class="space-y-5">
                <?= csrfField() ?>
                <div>
                    <label class="form-label">Current Password</label>
                    <div class="relative">
                        <i data-lucide="key" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"></i>
                        <input type="password" name="current_password" required placeholder="Enter current password" class="form-input pl-10">
                    </div>
                </div>

                <hr class="border-gray-100">

                <div>
                    <label class="form-label">New Password</label>
                    <div class="relative">
                        <i data-lucide="lock" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"></i>
                        <input type="password" name="new_password" required placeholder="At least 8 characters" minlength="8" class="form-input pl-10">
                    </div>
                </div>

                <div>
                    <label class="form-label">Confirm New Password</label>
                    <div class="relative">
                        <i data-lucide="shield-check" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"></i>
                        <input type="password" name="confirm_password" required placeholder="Re-enter new password" minlength="8" class="form-input pl-10">
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button type="submit" class="btn btn-yellow">
                        <i data-lucide="save" class="w-4 h-4"></i>Update Password
                    </button>
                    <a href="dashboard.php" class="btn btn-secondary">
                        Cancel
                    </a>
                </div>
            </form>

            <div class="mt-6 p-4 bg-blue-50 rounded-lg border border-blue-100">
                <p class="text-xs font-semibold text-blue-800 mb-1.5 flex items-center gap-1.5">
                    <i data-lucide="info" class="w-3.5 h-3.5"></i>Password Requirements
                </p>
                <ul class="text-xs text-blue-700 space-y-0.5 list-disc list-inside">
                    <li>Minimum 8 characters</li>
                    <li>Use a mix of letters, numbers &amp; symbols</li>
                    <li>Avoid common or easily guessable passwords</li>
                </ul>
            </div>
        </div>

        <div class="mt-5">
            <a href="dashboard.php" class="text-sm text-[var(--rotary-blue)] font-semibold hover:text-yellow-600 transition-colors inline-flex items-center gap-1">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Dashboard
            </a>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
