<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: ../login.php"); exit; }
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/admin_functions.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/audit_log.php';
if (!isSuperAdmin()) { echo "<script>alert('Access denied. Super Admin only.'); window.location.href='dashboard.php';</script>"; exit; }

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_contact_social') {
    if (!validateCsrfToken()) {
        $error = 'Invalid form submission. Please refresh the page and try again.';
    } else {
        $fields = [
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'map_url' => trim($_POST['map_url'] ?? ''),
            'map_embed_url' => trim($_POST['map_embed_url'] ?? ''),
            'facebook_url' => trim($_POST['facebook_url'] ?? ''),
            'instagram_url' => trim($_POST['instagram_url'] ?? ''),
        ];

        $errors = [];

        if ($fields['email'] !== '' && !filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format.';
        }
        if ($fields['phone'] !== '') {
            $digits = preg_replace('/\D/', '', $fields['phone']);
            if (strlen($digits) < 10) {
                $errors[] = 'Phone number must have at least 10 digits.';
            }
        }
        foreach (['map_url', 'map_embed_url', 'facebook_url', 'instagram_url'] as $urlField) {
            if ($fields[$urlField] !== '' && !filter_var($fields[$urlField], FILTER_VALIDATE_URL)) {
                $labels = ['map_url' => 'Google Maps URL', 'map_embed_url' => 'Google Maps Embed URL', 'facebook_url' => 'Facebook URL', 'instagram_url' => 'Instagram URL'];
                $errors[] = 'Invalid ' . $labels[$urlField] . ' format.';
            }
        }

        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO site_content (page, section, content) VALUES ('contact_social', ?, ?) ON DUPLICATE KEY UPDATE content = VALUES(content), updated_at = NOW()");
            if ($stmt) {
                foreach ($fields as $section => $value) {
                    $stmt->bind_param("ss", $section, $value);
                    $stmt->execute();
                }
                $stmt->close();
                $message = 'Contact & Social Links updated successfully.';
                logAudit($conn, 'Contact & Social Links', 'Contact & Social Links Updated', 'Contact and social media links updated.', 'CRITICAL', 'success');
            } else {
                $error = 'Database error.';
            }
        } else {
            $error = $errors;
        }
    }
}

$contactSocial = [];
$r = $conn->query("SELECT section, content FROM site_content WHERE page = 'contact_social'");
if ($r) {
    while ($row = $r->fetch_assoc()) $contactSocial[$row['section']] = $row['content'];
}

$pageTitle = 'Contact & Social Links';
$activeNav = 'contact-social';
require __DIR__ . '/includes/admin_head.php';
require __DIR__ . '/includes/admin_header.php';
?>

<?php if ($message): ?>
<div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-error"><?php
    if (is_array($error)) {
        echo implode('<br>', array_map(function($e) { return htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); }, $error));
    } else {
        echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8');
    }
?></div>
<?php endif; ?>

<div class="alert alert-info" style="margin-bottom:20px;">
    <strong>Note:</strong> Values saved here override all page-specific and footer-specific contact and social media settings across the entire website.
</div>

<div class="card">
    <div class="card-header">
        <h2 style="font-size:16px;font-weight:700;margin:0;">Contact Information</h2>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="update_contact_social">
            <?= csrfField() ?>
            <div style="display:grid;gap:16px;margin-bottom:6px;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div class="form-group">
                        <label class="form-label">Official Club Email</label>
                        <input type="email" name="email" class="form-input" value="<?= htmlspecialchars($contactSocial['email'] ?? '') ?>" maxlength="200" placeholder="rotaryclubofvirar@gmail.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Primary Contact Number</label>
                        <input type="text" name="phone" class="form-input" value="<?= htmlspecialchars($contactSocial['phone'] ?? '') ?>" maxlength="50" placeholder="+91 77969 31555">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Office Address</label>
                    <textarea name="address" class="form-textarea" rows="3" maxlength="500"><?= htmlspecialchars($contactSocial['address'] ?? '') ?></textarea>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div class="form-group">
                        <label class="form-label">Google Maps "View on Map" URL</label>
                        <input type="url" name="map_url" class="form-input" value="<?= htmlspecialchars($contactSocial['map_url'] ?? '') ?>" maxlength="500" placeholder="https://maps.google.com/...">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Google Maps Embed URL</label>
                        <input type="url" name="map_embed_url" class="form-input" value="<?= htmlspecialchars($contactSocial['map_embed_url'] ?? '') ?>" maxlength="500" placeholder="https://www.google.com/maps?q=...&output=embed">
                    </div>
                </div>
            </div>

            <div style="border-top:1px solid #f1f5f9;padding-top:20px;margin-top:4px;">
                <h3 style="font-size:15px;font-weight:700;color:#0f172a;margin:0 0 4px;">Social Media</h3>
                <p style="font-size:13px;color:#94a3b8;margin:0 0 16px;">Only platforms currently used by Rotary Club of Virar.</p>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:6px;">
                    <div class="form-group">
                        <label class="form-label">Facebook URL</label>
                        <input type="url" name="facebook_url" class="form-input" value="<?= htmlspecialchars($contactSocial['facebook_url'] ?? '') ?>" maxlength="500" placeholder="https://facebook.com/...">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Instagram URL</label>
                        <input type="url" name="instagram_url" class="form-input" value="<?= htmlspecialchars($contactSocial['instagram_url'] ?? '') ?>" maxlength="500" placeholder="https://instagram.com/...">
                    </div>
                </div>
            </div>

            <div style="border-top:1px solid #f1f5f9;padding-top:20px;margin-top:4px;text-align:right;">
                <button type="submit" class="btn btn-primary btn-lg"><i data-lucide="save" style="width:18px;height:18px;"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
