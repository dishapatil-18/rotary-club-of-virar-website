<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: ../login.php"); exit; }
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/admin_functions.php';
require_once __DIR__ . '/../includes/upload_helper.php';
require_once __DIR__ . '/../includes/website_settings.php';
require_once __DIR__ . '/../includes/audit_log.php';
if (!isSuperAdmin()) { echo "<script>alert('Access denied. Super Admin only.'); window.location.href='dashboard.php';</script>"; exit; }

$message = '';
$error = '';

$uploadDir = __DIR__ . '/../uploads/settings/';
if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }

function saveSetting($conn, $section, $content) {
    $stmt = $conn->prepare("INSERT INTO site_content (page, section, content) VALUES ('settings', ?, ?) ON DUPLICATE KEY UPDATE content = VALUES(content), updated_at = NOW()");
    if ($stmt) {
        $stmt->bind_param("ss", $section, $content);
        $stmt->execute();
        $stmt->close();
        return true;
    }
    return false;
}

function handleSettingsUpload($file, $section, $allowedExts, $maxSize) {
    global $uploadDir;
    $check = validateUpload($file, $allowedExts, $maxSize);
    if (!$check['valid']) return ['ok' => false, 'error' => $check['errors'][0]];
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) return ['ok' => true, 'path' => null];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = $section . '_' . time() . '.' . $ext;
    $dest = $uploadDir . $filename;
    if (move_uploaded_file($file['tmp_name'], $dest)) {
        return ['ok' => true, 'path' => 'uploads/settings/' . $filename];
    }
    return ['ok' => false, 'error' => 'Failed to move uploaded file.'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    if (!isset($_POST['_csrf_token']) || !hash_equals($_SESSION['_csrf_token'] ?? '', $_POST['_csrf_token'])) {
        $error = 'Invalid form submission. Please refresh the page and try again.';
    } else {
        $fields = [
            'website_name'       => trim($_POST['website_name'] ?? ''),
            'website_short_name' => trim($_POST['website_short_name'] ?? ''),
            'browser_title'      => trim($_POST['browser_title'] ?? ''),
            'timezone'           => trim($_POST['timezone'] ?? 'Asia/Kolkata'),
            'date_format'        => trim($_POST['date_format'] ?? 'dd/mm/yyyy'),
            'copyright_year'     => trim($_POST['copyright_year'] ?? ''),
            // Club Profile
            'club_name'          => trim($_POST['club_name'] ?? ''),
            'club_district'      => trim($_POST['club_district'] ?? ''),
            'club_number'        => trim($_POST['club_number'] ?? ''),
            'club_charter_date'  => trim($_POST['club_charter_date'] ?? ''),
            'club_meeting_day'   => trim($_POST['club_meeting_day'] ?? ''),
            'club_meeting_time'  => trim($_POST['club_meeting_time'] ?? ''),
            'club_meeting_venue' => trim($_POST['club_meeting_venue'] ?? ''),
            'club_address'       => trim($_POST['club_address'] ?? ''),
            'club_email'         => trim($_POST['club_email'] ?? ''),
            'club_phone'         => trim($_POST['club_phone'] ?? ''),
            'club_website'       => trim($_POST['club_website'] ?? ''),
            // System Defaults
            'default_upload_size'  => trim($_POST['default_upload_size'] ?? '10'),
            'image_quality'        => trim($_POST['image_quality'] ?? '85'),
            'default_pagination'   => trim($_POST['default_pagination'] ?? '10'),
            'items_per_page'       => trim($_POST['items_per_page'] ?? '12'),
        ];

        $errors = [];
        if ($fields['website_name'] === '') $errors[] = 'Website Name is required.';
        if (strlen($fields['website_name']) > 100) $errors[] = 'Website Name must be 100 characters or fewer.';
        if (strlen($fields['website_short_name']) > 30) $errors[] = 'Short Name must be 30 characters or fewer.';
        if (strlen($fields['browser_title']) > 150) $errors[] = 'Browser Title must be 150 characters or fewer.';
        $validTimezones = ['Asia/Kolkata', 'UTC', 'America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles', 'Europe/London', 'Europe/Paris', 'Asia/Dubai', 'Asia/Singapore', 'Australia/Sydney'];
        if (!in_array($fields['timezone'], $validTimezones)) $errors[] = 'Invalid timezone selected.';
        $validFormats = ['dd/mm/yyyy', 'dd-mm-yyyy', 'Month dd, yyyy', 'dd Month yyyy'];
        if (!in_array($fields['date_format'], $validFormats)) $errors[] = 'Invalid date format selected.';
        if ($fields['copyright_year'] !== '' && (!ctype_digit($fields['copyright_year']) || strlen($fields['copyright_year']) !== 4)) {
            $errors[] = 'Copyright Year must be a 4-digit year.';
        }
        if ($fields['club_email'] !== '' && !filter_var($fields['club_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Club Email must be a valid email address.';
        }
        if ($fields['club_website'] !== '' && !filter_var($fields['club_website'], FILTER_VALIDATE_URL)) {
            $errors[] = 'Club Website must be a valid URL.';
        }
        foreach (['default_upload_size', 'image_quality', 'default_pagination', 'items_per_page'] as $numField) {
            if ($fields[$numField] !== '' && (!ctype_digit($fields[$numField]) || intval($fields[$numField]) < 1)) {
                $errors[] = ucfirst(str_replace('_', ' ', $numField)) . ' must be a positive integer.';
            }
        }

        if (empty($errors)) {
            $saved = true;
            foreach ($fields as $section => $value) {
                if (!saveSetting($conn, $section, $value)) { $saved = false; break; }
            }

            // Handle logo upload
            if ($saved && isset($_FILES['website_logo']) && $_FILES['website_logo']['error'] === UPLOAD_ERR_OK) {
                $up = handleSettingsUpload($_FILES['website_logo'], 'website_logo', ['jpg', 'jpeg', 'png', 'webp', 'svg'], 2097152);
                if ($up['ok'] && $up['path']) { $saved = saveSetting($conn, 'website_logo', $up['path']); }
                elseif (!$up['ok']) { $errors[] = 'Logo: ' . $up['error']; $saved = false; }
            }

            // Handle favicon upload
            if ($saved && isset($_FILES['website_favicon']) && $_FILES['website_favicon']['error'] === UPLOAD_ERR_OK) {
                $up = handleSettingsUpload($_FILES['website_favicon'], 'website_favicon', ['ico', 'png', 'svg'], 1048576);
                if ($up['ok'] && $up['path']) { $saved = saveSetting($conn, 'website_favicon', $up['path']); }
                elseif (!$up['ok']) { $errors[] = 'Favicon: ' . $up['error']; $saved = false; }
            }

            if ($saved && empty($errors)) {
                $message = 'Website Settings updated successfully.';
                logAudit($conn, 'Website Settings', 'Website Settings Updated', 'All website settings (branding, general, club profile, system defaults) updated.', 'CRITICAL', 'success');
            } elseif (!empty($errors)) {
                $error = $errors;
            }
        } else {
            $error = $errors;
        }
    }
}

$ws = getWebsiteSettings($conn);

// Email config status (read-only)
$emailConfigured = false;
$smtpStatus = 'Not Configured';
$smtpEncryption = 'N/A';
$lastEmailTest = 'Never';
$emailLogPath = __DIR__ . '/../logs/email_log.txt';
if (defined('SMTP_HOST') && SMTP_HOST !== '') {
    $smtpEncryption = defined('SMTP_ENCRYPTION') ? strtoupper(SMTP_ENCRYPTION) : 'N/A';
    $emailConfigured = true;
    $smtpStatus = 'Configured';
}
if (file_exists($emailLogPath)) {
    $lastModified = filemtime($emailLogPath);
    if ($lastModified > 0) {
        $lastEmailTest = date('d M Y, h:i A', $lastModified);
    }
}

$pageTitle = 'Website Settings';
$activeNav = 'website-settings';
require __DIR__ . '/../includes/csrf_helper.php';
require __DIR__ . '/includes/admin_head.php';
require __DIR__ . '/includes/admin_header.php';
?>

<?php if ($message): ?>
<div class="alert alert-success"><?= e($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-error"><?php
    if (is_array($error)) {
        echo implode('<br>', array_map(function($x) { return e($x); }, $error));
    } else {
        echo e($error);
    }
?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" id="wsForm">
    <input type="hidden" name="action" value="update_settings">
    <?= csrfField() ?>

    <!-- ═══ SECTION 1: BRANDING ═══ -->
    <div class="card" style="margin-bottom:24px;">
        <div class="card-header">
            <div style="display:flex;align-items:center;gap:8px;">
                <i data-lucide="palette" style="width:18px;height:18px;color:var(--rotary-blue);"></i>
                <h2 style="font-size:16px;font-weight:700;margin:0;">Branding</h2>
            </div>
        </div>
        <div class="card-body" style="display:grid;gap:24px;">

            <!-- Logo -->
            <div>
                <label class="form-label">Website Logo</label>
                <div style="display:flex;align-items:center;gap:20px;">
                    <div id="logoPreview" style="width:80px;height:80px;border-radius:12px;border:2px solid #e2e8f0;background:#f8fafc;display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;">
                        <img src="<?= e($ws['website_logo']) ?>" alt="Current Logo" style="max-width:100%;max-height:100%;object-fit:contain;" onerror="this.style.display='none'">
                    </div>
                    <div style="flex:1;">
                        <input type="file" name="website_logo" id="logoInput" accept="image/jpeg,image/png,image/webp,image/svg+xml" class="form-input" style="padding:8px;">
                        <div style="font-size:11px;color:#94a3b8;margin-top:4px;">PNG, JPG, WebP or SVG. Max 2MB. Leave empty to keep current.</div>
                    </div>
                </div>
            </div>

            <!-- Favicon -->
            <div>
                <label class="form-label">Favicon</label>
                <div style="display:flex;align-items:center;gap:20px;">
                    <div id="faviconPreview" style="width:48px;height:48px;border-radius:8px;border:2px solid #e2e8f0;background:#f8fafc;display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;">
                        <img src="<?= e($ws['website_favicon']) ?>" alt="Current Favicon" style="max-width:100%;max-height:100%;object-fit:contain;" onerror="this.style.display='none'">
                    </div>
                    <div style="flex:1;">
                        <input type="file" name="website_favicon" id="faviconInput" accept="image/x-icon,image/png,image/svg+xml" class="form-input" style="padding:8px;">
                        <div style="font-size:11px;color:#94a3b8;margin-top:4px;">.ico, .png or .svg. Max 1MB. Leave empty to keep current.</div>
                    </div>
                </div>
            </div>

            <!-- Names -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div class="form-group">
                    <label class="form-label">Website Name</label>
                    <input type="text" name="website_name" class="form-input" value="<?= e($ws['website_name']) ?>" maxlength="100" required placeholder="Rotary Club of Virar">
                    <div style="font-size:11px;color:#94a3b8;margin-top:4px;">Full name shown in navbar, footer, and emails.</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Short Website Name</label>
                    <input type="text" name="website_short_name" class="form-input" value="<?= e($ws['website_short_name']) ?>" maxlength="30" placeholder="RC Virar">
                    <div style="font-size:11px;color:#94a3b8;margin-top:4px;">Abbreviated name for tight spaces.</div>
                </div>
            </div>

            <!-- Browser Title -->
            <div class="form-group">
                <label class="form-label">Browser Title</label>
                <input type="text" name="browser_title" class="form-input" value="<?= e($ws['browser_title']) ?>" maxlength="150" placeholder="Rotary Club of Virar - Service Above Self">
                <div style="font-size:11px;color:#94a3b8;margin-top:4px;">Title shown in the browser tab on every page.</div>
            </div>

        </div>
    </div>

    <!-- ═══ SECTION 2: GENERAL SETTINGS ═══ -->
    <div class="card" style="margin-bottom:24px;">
        <div class="card-header">
            <div style="display:flex;align-items:center;gap:8px;">
                <i data-lucide="settings" style="width:18px;height:18px;color:var(--rotary-blue);"></i>
                <h2 style="font-size:16px;font-weight:700;margin:0;">General Settings</h2>
            </div>
        </div>
        <div class="card-body" style="display:grid;gap:20px;">
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;">
                <div class="form-group">
                    <label class="form-label">Timezone</label>
                    <select name="timezone" class="form-select">
                        <?php
                        $timezones = [
                            'Asia/Kolkata' => 'Asia/Kolkata (IST)',
                            'UTC' => 'UTC',
                            'America/New_York' => 'America/New_York (ET)',
                            'America/Chicago' => 'America/Chicago (CT)',
                            'America/Denver' => 'America/Denver (MT)',
                            'America/Los_Angeles' => 'America/Los_Angeles (PT)',
                            'Europe/London' => 'Europe/London (GMT)',
                            'Europe/Paris' => 'Europe/Paris (CET)',
                            'Asia/Dubai' => 'Asia/Dubai (GST)',
                            'Asia/Singapore' => 'Asia/Singapore (SGT)',
                            'Australia/Sydney' => 'Australia/Sydney (AEST)',
                        ];
                        foreach ($timezones as $tz => $label):
                        ?>
                        <option value="<?= $tz ?>" <?= $ws['timezone'] === $tz ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Date Format</label>
                    <select name="date_format" class="form-select">
                        <?php
                        $formats = ['dd/mm/yyyy', 'dd-mm-yyyy', 'Month dd, yyyy', 'dd Month yyyy'];
                        foreach ($formats as $fmt):
                        ?>
                        <option value="<?= $fmt ?>" <?= $ws['date_format'] === $fmt ? 'selected' : '' ?>><?= $fmt ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Copyright Year</label>
                    <input type="text" name="copyright_year" class="form-input" value="<?= e($ws['copyright_year']) ?>" maxlength="4" placeholder="<?= date('Y') ?>">
                    <div style="font-size:11px;color:#94a3b8;margin-top:4px;">4-digit year. Leave blank to use current year.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ SECTION 3: ROTARY CLUB PROFILE ═══ -->
    <div class="card" style="margin-bottom:24px;">
        <div class="card-header">
            <div style="display:flex;align-items:center;gap:8px;">
                <i data-lucide="building-2" style="width:18px;height:18px;color:var(--rotary-blue);"></i>
                <h2 style="font-size:16px;font-weight:700;margin:0;">Rotary Club Profile</h2>
            </div>
        </div>
        <div class="card-body" style="display:grid;gap:20px;">
            <div style="font-size:12px;color:#94a3b8;margin:-8px 0 8px;">Permanent Rotary Club information. Future modules will read from here.</div>

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;">
                <div class="form-group">
                    <label class="form-label">Club Name</label>
                    <input type="text" name="club_name" class="form-input" value="<?= e($ws['club_name']) ?>" maxlength="100" placeholder="Rotary Club of Virar">
                </div>
                <div class="form-group">
                    <label class="form-label">District</label>
                    <input type="text" name="club_district" class="form-input" value="<?= e($ws['club_district']) ?>" maxlength="50" placeholder="District 3131">
                </div>
                <div class="form-group">
                    <label class="form-label">Club Number</label>
                    <input type="text" name="club_number" class="form-input" value="<?= e($ws['club_number']) ?>" maxlength="20" placeholder="e.g. 12345">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;">
                <div class="form-group">
                    <label class="form-label">Charter Date</label>
                    <input type="date" name="club_charter_date" class="form-input" value="<?= e($ws['club_charter_date']) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Meeting Day</label>
                    <select name="club_meeting_day" class="form-select">
                        <option value="">-- Select --</option>
                        <?php foreach (['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $day): ?>
                        <option value="<?= $day ?>" <?= $ws['club_meeting_day'] === $day ? 'selected' : '' ?>><?= $day ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Meeting Time</label>
                    <input type="time" name="club_meeting_time" class="form-input" value="<?= e($ws['club_meeting_time']) ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Meeting Venue</label>
                <input type="text" name="club_meeting_venue" class="form-input" value="<?= e($ws['club_meeting_venue']) ?>" maxlength="200" placeholder="e.g. Hotel Royal, Virar West">
            </div>

            <div class="form-group">
                <label class="form-label">Official Address</label>
                <textarea name="club_address" class="form-textarea" rows="2" maxlength="500" placeholder="Full official address"><?= e($ws['club_address']) ?></textarea>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;">
                <div class="form-group">
                    <label class="form-label">Official Email</label>
                    <input type="email" name="club_email" class="form-input" value="<?= e($ws['club_email']) ?>" maxlength="200" placeholder="rotaryclubofvirar@gmail.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Official Phone</label>
                    <input type="text" name="club_phone" class="form-input" value="<?= e($ws['club_phone']) ?>" maxlength="50" placeholder="+91 77969 31555">
                </div>
                <div class="form-group">
                    <label class="form-label">Club Website</label>
                    <input type="url" name="club_website" class="form-input" value="<?= e($ws['club_website']) ?>" maxlength="200" placeholder="https://rotaryclubofvirar.org">
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ SECTION 4: EMAIL CONFIGURATION (READ-ONLY) ═══ -->
    <div class="card" style="margin-bottom:24px;">
        <div class="card-header">
            <div style="display:flex;align-items:center;gap:8px;">
                <i data-lucide="mail" style="width:18px;height:18px;color:var(--rotary-blue);"></i>
                <h2 style="font-size:16px;font-weight:700;margin:0;">Email Configuration</h2>
            </div>
        </div>
        <div class="card-body" style="display:grid;gap:16px;">
            <div style="font-size:12px;color:#94a3b8;margin:-8px 0 8px;">Read-only. SMTP credentials are managed in environment configuration.</div>

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:16px;">
                <div style="padding:16px;background:#f8fafc;border-radius:10px;border:1px solid #e2e8f0;">
                    <div style="font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;">Official Email</div>
                    <div style="font-size:14px;font-weight:600;color:#0f172a;"><?= e($ws['club_email'] ?: 'Not Set') ?></div>
                </div>
                <div style="padding:16px;background:#f8fafc;border-radius:10px;border:1px solid #e2e8f0;">
                    <div style="font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;">SMTP Status</div>
                    <div style="font-size:14px;font-weight:600;color:<?= $emailConfigured ? '#16a34a' : '#dc2626' ?>;">
                        <?= $emailConfigured ? '&#9679; Active' : '&#9679; Not Configured' ?>
                    </div>
                </div>
                <div style="padding:16px;background:#f8fafc;border-radius:10px;border:1px solid #e2e8f0;">
                    <div style="font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;">Encryption</div>
                    <div style="font-size:14px;font-weight:600;color:#0f172a;"><?= e($smtpEncryption) ?></div>
                </div>
                <div style="padding:16px;background:#f8fafc;border-radius:10px;border:1px solid #e2e8f0;">
                    <div style="font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;">Last Email Test</div>
                    <div style="font-size:14px;font-weight:600;color:#0f172a;"><?= e($lastEmailTest) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ SECTION 5: SYSTEM DEFAULTS ═══ -->
    <div class="card" style="margin-bottom:24px;">
        <div class="card-header">
            <div style="display:flex;align-items:center;gap:8px;">
                <i data-lucide="sliders" style="width:18px;height:18px;color:var(--rotary-blue);"></i>
                <h2 style="font-size:16px;font-weight:700;margin:0;">System Defaults</h2>
            </div>
        </div>
        <div class="card-body" style="display:grid;gap:20px;">
            <div style="font-size:12px;color:#94a3b8;margin:-8px 0 8px;">Default values used by modules across the platform.</div>

            <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:16px;">
                <div class="form-group">
                    <label class="form-label">Default Upload Size (MB)</label>
                    <input type="number" name="default_upload_size" class="form-input" value="<?= e($ws['default_upload_size']) ?>" min="1" max="100">
                    <div style="font-size:11px;color:#94a3b8;margin-top:4px;">Max upload size for media.</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Image Quality (%)</label>
                    <input type="number" name="image_quality" class="form-input" value="<?= e($ws['image_quality']) ?>" min="10" max="100">
                    <div style="font-size:11px;color:#94a3b8;margin-top:4px;">Compression quality for uploads.</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Default Pagination</label>
                    <input type="number" name="default_pagination" class="form-input" value="<?= e($ws['default_pagination']) ?>" min="1" max="100">
                    <div style="font-size:11px;color:#94a3b8;margin-top:4px;">Items per page in lists.</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Items Per Page</label>
                    <input type="number" name="items_per_page" class="form-input" value="<?= e($ws['items_per_page']) ?>" min="1" max="100">
                    <div style="font-size:11px;color:#94a3b8;margin-top:4px;">Gallery and card grids.</div>
                </div>
            </div>
        </div>
    </div>

    <div style="text-align:right;margin-bottom:40px;">
        <button type="submit" class="btn btn-primary btn-lg"><i data-lucide="save" style="width:18px;height:18px;"></i> Save All Settings</button>
    </div>
</form>

<script>
document.getElementById('logoInput')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function(ev) {
        const preview = document.getElementById('logoPreview');
        preview.innerHTML = '<img src="' + ev.target.result + '" alt="Logo Preview" style="max-width:100%;max-height:100%;object-fit:contain;">';
    };
    reader.readAsDataURL(file);
});
document.getElementById('faviconInput')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function(ev) {
        const preview = document.getElementById('faviconPreview');
        preview.innerHTML = '<img src="' + ev.target.result + '" alt="Favicon Preview" style="max-width:100%;max-height:100%;object-fit:contain;">';
    };
    reader.readAsDataURL(file);
});
</script>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
