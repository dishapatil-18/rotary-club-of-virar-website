<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: ../login.php"); exit; }
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/admin_functions.php';
require_once __DIR__ . '/../includes/communication_engine.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
$_ws = getWebsiteSettings($conn);

// Validate skip_url is relative (prevent open redirect)
function validateSkipUrl($url) {
    $url = trim($url);
    if ($url === '' || str_starts_with($url, '/') || str_starts_with($url, 'http') || str_starts_with($url, '//')) {
        return 'dashboard.php';
    }
    return $url;
}

$sent = false;
$sentTo = '';
$error = '';

// Handle SEND
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_email') {

    if (!validateCsrfToken()) {
        $error = 'Invalid form submission. Please try again.';
    } else {
    $to      = trim($_POST['to'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $body    = trim($_POST['body'] ?? '');
    $module  = $_POST['module'] ?? 'System';
    $action  = $_POST['action_name'] ?? 'Email Sent';
    $skipUrl = validateSkipUrl($_POST['skip_url'] ?? 'dashboard.php');
    $messageId = intval($_POST['message_id'] ?? 0);

    if ($to === '' || $subject === '' || $body === '') {
        $error = 'To, Subject, and Message are required.';
    } elseif (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $result = commSendEmail($conn, $to, $subject, $body, $module, $action, "Email sent to \"$to\" via Composer");
        if ($result['success']) {
            $sent = true;
            $sentTo = $to;
            // Post-send callback: mark contact message as replied
            if ($messageId > 0 && $module === 'Contact Messages') {
                $conn->query("UPDATE contact_messages SET status = 'replied', replied_by = '" . $conn->real_escape_string($_SESSION['admin_name']) . "', replied_at = NOW() WHERE message_id = $messageId");
            }
        } else {
            $error = 'Failed to send email. ' . ($result['error'] ?? 'Check SMTP configuration.');
        }
    }
    } // end CSRF else
}

// Pre-fill from GET params
$prefillTo      = trim($_GET['to'] ?? $_POST['to'] ?? '');
$prefillSubject = trim($_GET['subject'] ?? $_POST['subject'] ?? '');
$prefillBody    = trim($_GET['body'] ?? $_POST['body'] ?? '');
$prefillModule  = $_GET['module'] ?? $_POST['module'] ?? 'System';
$prefillAction  = $_GET['action'] ?? $_POST['action_name'] ?? 'Email Sent';
$skipUrl        = validateSkipUrl($_GET['skip_url'] ?? $_POST['skip_url'] ?? 'dashboard.php');
$templateKey    = $_GET['template'] ?? '';
$messageId      = intval($_GET['message_id'] ?? $_POST['message_id'] ?? 0);

// Load template if provided
if ($templateKey && empty($prefillBody)) {
    $vars = [];
    foreach ($_GET as $k => $v) {
        if (!in_array($k, ['to', 'subject', 'body', 'module', 'action', 'skip_url', 'template', 'message_id'])) {
            $vars[$k] = $v;
        }
    }
    $rendered = commRenderTemplate($templateKey, $vars);
    if ($rendered) {
        if (empty($prefillSubject)) $prefillSubject = $rendered['subject'];
        if (empty($prefillBody))    $prefillBody    = $rendered['body'];
    }
}

$success = '';
if (isset($_GET['msg'])) {
    $success = $_GET['msg'] === 'replied' ? 'Reply sent and message marked as replied.' : htmlspecialchars($_GET['msg']);
}
?>

<?php
$pageTitle = 'Email Composer';
$activeNav = 'email-composer';
require __DIR__ . '/includes/admin_head.php';
require __DIR__ . '/includes/admin_header.php';
?>

<?php if ($success): ?>
<div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if ($sent): ?>
<div class="card">
    <div class="card-body" style="text-align:center;padding:48px 24px;">
        <div style="width:64px;height:64px;border-radius:50%;background:#dcfce7;display:inline-flex;align-items:center;justify-content:center;margin-bottom:16px;">
            <i data-lucide="check-circle" style="width:32px;height:32px;color:#16a34a;"></i>
        </div>
        <h2 style="font-size:20px;font-weight:700;color:#0A2342;margin:0 0 8px;">Email Sent Successfully</h2>
        <p style="color:#64748b;margin:0 0 24px;">The email has been sent to <strong><?= e($sentTo) ?></strong> and logged in Audit Logs.</p>
        <a href="<?= e($skipUrl) ?>" class="btn btn-yellow"><i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Continue</a>
    </div>
</div>
<?php else: ?>

<?php if ($error): ?>
<div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 380px;gap:20px;align-items:start;">
    <!-- Composer -->
    <div class="card">
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
            <h2 style="font-size:16px;font-weight:700;margin:0;"><i data-lucide="mail" style="width:18px;height:18px;display:inline;vertical-align:middle;"></i> Compose Email</h2>
            <span style="font-size:12px;color:#94a3b8;">Module: <?= e($prefillModule) ?></span>
        </div>
        <div class="card-body">
            <form method="POST" id="composerForm">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="send_email">
                <input type="hidden" name="module" value="<?= e($prefillModule) ?>">
                <input type="hidden" name="action_name" value="<?= e($prefillAction) ?>">
                <input type="hidden" name="skip_url" value="<?= e($skipUrl) ?>">
                <input type="hidden" name="message_id" value="<?= $messageId ?>">

                <div style="display:grid;gap:16px;">
                    <div>
                        <label class="form-label">To</label>
                        <input type="email" name="to" class="form-input" value="<?= e($prefillTo) ?>" required placeholder="recipient@example.com" style="font-size:14px;">
                    </div>
                    <div>
                        <label class="form-label">Subject</label>
                        <input type="text" name="subject" class="form-input" value="<?= e($prefillSubject) ?>" required placeholder="Email subject...">
                    </div>
                    <div>
                        <label class="form-label">Message</label>
                        <textarea name="body" class="form-textarea" rows="14" required placeholder="Type your message..."><?= e($prefillBody) ?></textarea>
                    </div>
                </div>

                <div style="display:flex;gap:10px;margin-top:20px;flex-wrap:wrap;">
                    <button type="button" onclick="previewEmail()" class="btn btn-ghost" style="color:#3b82f6;">
                        <i data-lucide="eye" style="width:16px;height:16px;"></i> Preview
                    </button>
                    <button type="submit" class="btn btn-yellow" id="sendBtn">
                        <i data-lucide="send" style="width:16px;height:16px;"></i> Send Email
                    </button>
                    <a href="<?= e($skipUrl) ?>" class="btn btn-ghost" style="color:#ef4444;margin-left:auto;">
                        <i data-lucide="x" style="width:16px;height:16px;"></i> Skip / Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Preview Panel -->
    <div class="card" id="previewPanel" style="position:sticky;top:80px;">
        <div class="card-header">
            <h2 style="font-size:16px;font-weight:700;margin:0;"><i data-lucide="monitor" style="width:18px;height:18px;display:inline;vertical-align:middle;"></i> Live Preview</h2>
        </div>
        <div class="card-body" style="padding:0;">
            <div style="background:#f1f5f9;padding:12px 16px;border-bottom:1px solid #e2e8f0;">
                <div style="font-size:11px;color:#94a3b8;margin-bottom:2px;">To:</div>
                <div id="prevTo" style="font-size:13px;font-weight:600;"><?= e($prefillTo) ?: '<span style="color:#94a3b8;">—</span>' ?></div>
                <div style="font-size:11px;color:#94a3b8;margin:8px 0 2px;">Subject:</div>
                <div id="prevSubject" style="font-size:13px;font-weight:600;"><?= e($prefillSubject) ?: '<span style="color:#94a3b8;">—</span>' ?></div>
            </div>
            <div id="prevBody" style="padding:16px;font-size:13px;line-height:1.7;min-height:300px;white-space:pre-wrap;word-wrap:break-word;"><?= e($prefillBody) ?: '<span style="color:#94a3b8;">Start typing to see preview...</span>' ?></div>
        </div>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal-overlay" id="previewModal" style="display:none;" onclick="this.style.display='none'">
    <div class="modal-content" onclick="event.stopPropagation()" style="max-width:650px;max-height:85vh;overflow:auto;">
        <div class="modal-header">
            <h2>Email Preview</h2>
            <button class="modal-close" onclick="document.getElementById('previewModal').style.display='none'"><i data-lucide="x" style="width:18px;height:18px;"></i></button>
        </div>
        <div class="modal-body" id="previewModalBody" style="background:#fff;border-radius:0 0 12px 12px;"></div>
        <div class="modal-footer">
            <button class="btn btn-ghost btn-sm" onclick="document.getElementById('previewModal').style.display='none'">Close</button>
            <button class="btn btn-yellow btn-sm" onclick="document.getElementById('previewModal').style.display='none';document.getElementById('composerForm').submit();"><i data-lucide="send" style="width:14px;height:14px;"></i> Send Now</button>
        </div>
    </div>
</div>

<script>
const fields = ['to', 'subject', 'body'];
fields.forEach(f => {
    const el = document.querySelector('[name="' + f + '"]');
    if (el) el.addEventListener('input', updatePreview);
});

function escapeHtml(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

function updatePreview() {
    const to = document.querySelector('[name="to"]').value;
    const subject = document.querySelector('[name="subject"]').value;
    const body = document.querySelector('[name="body"]').value;

    document.getElementById('prevTo').innerHTML = escapeHtml(to) || '<span style="color:#94a3b8;">—</span>';
    document.getElementById('prevSubject').innerHTML = escapeHtml(subject) || '<span style="color:#94a3b8;">—</span>';
    document.getElementById('prevBody').innerHTML = escapeHtml(body).replace(/\n/g, '<br>') || '<span style="color:#94a3b8;">Start typing to see preview...</span>';
}

function previewEmail() {
    const to = document.querySelector('[name="to"]').value;
    const subject = document.querySelector('[name="subject"]').value;
    const body = document.querySelector('[name="body"]').value;
    const moduleName = document.querySelector('[name="module"]').value;

    const html = `
        <div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
            <div style="text-align:center;margin-bottom:20px;">
                <h2 style="color:#0A2342;"><?= e($_ws['website_name']) ?></h2>
            </div>
            <div style="background:#f8fafc;border-radius:12px;padding:24px;border:1px solid #e2e8f0;">
                ${body.replace(/\n/g, '<br>').replace(/</g, '&lt;').replace(/>/g, '&gt;')}
            </div>
            <div style="margin-top:24px;padding-top:16px;border-top:1px solid #e2e8f0;">
                <p style="margin:0 0 4px;font-weight:700;color:#0A2342;"><?= e($_ws['website_name']) ?></p>
                <p style="margin:0 0 2px;font-size:13px;color:#475569;">📧 <?= e(CLUB_EMAIL) ?></p>
                <p style="margin:0 0 2px;font-size:13px;color:#475569;">📞 <?= e(CLUB_PHONE) ?></p>
                <p style="margin:8px 0 0;font-size:11px;color:#94a3b8;font-style:italic;">This email was sent using the Rotary Club Management Platform.</p>
            </div>
        </div>`;

    document.getElementById('previewModalBody').innerHTML = `
        <div style="padding:16px;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-size:13px;">
            <div><strong>To:</strong> ${to || '—'}</div>
            <div><strong>Subject:</strong> ${subject || '—'}</div>
        </div>
        <div style="padding:16px;background:#fff;">${html}</div>`;

    document.getElementById('previewModal').style.display = 'flex';
    lucide.createIcons();
}

document.getElementById('composerForm').addEventListener('submit', function(e) {
    const btn = document.getElementById('sendBtn');
    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader" style="width:16px;height:16px;animation:spin 1s linear infinite;"></i> Sending...';
});
</script>

<?php endif; ?>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
