<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: ../login.php"); exit; }
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/admin_functions.php';
require_once __DIR__ . '/../includes/website_settings.php';
require_once __DIR__ . '/../includes/audit_log.php';
$_ws = getWebsiteSettings($conn);
if (!isSuperAdmin()) { echo "<script>alert('Access denied. Super Admin only.'); window.location.href='dashboard.php';</script>"; exit; }

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_year') {
            $yearName = trim($_POST['year_name']);
            if (preg_match('/^\d{4}-\d{2}$/', $yearName)) {
                $stmt = $conn->prepare("INSERT IGNORE INTO rotary_years (year_name, is_current) VALUES (?, 0)");
                $stmt->bind_param("s", $yearName);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    $message = "Rotary year '$yearName' added.";
                    logAudit($conn, 'Administration', 'Rotary Year Added', 'Created rotary year "' . $yearName . '".', 'INFO', 'success');
                } else {
                    $error = "Year already exists or invalid.";
                }
                $stmt->close();
            } else {
                $error = "Invalid format. Use YYYY-YY (e.g. 2027-28).";
            }
        } elseif ($_POST['action'] === 'set_current' && isset($_POST['year_id'])) {
            $yearId = (int)$_POST['year_id'];
            $conn->query("UPDATE rotary_years SET is_current = 0");
            $conn->query("UPDATE rotary_years SET is_current = 1 WHERE id = $yearId");
            $message = "Current rotary year updated.";
            logAudit($conn, 'Administration', 'Rotary Year Activated', 'Rotary year ID ' . $yearId . ' set as current.', 'WARNING', 'success');
        } elseif ($_POST['action'] === 'edit_year' && isset($_POST['year_id'])) {
            $yearId = (int)$_POST['year_id'];
            $yearName = trim($_POST['year_name']);
            if (preg_match('/^\d{4}-\d{2}$/', $yearName)) {
                $stmt = $conn->prepare("UPDATE rotary_years SET year_name = ? WHERE id = ?");
                $stmt->bind_param("si", $yearName, $yearId);
                $stmt->execute();
                $stmt->close();
                $message = "Rotary year updated.";
                logAudit($conn, 'Administration', 'Rotary Year Updated', 'Rotary year ID ' . $yearId . ' renamed to "' . $yearName . '".', 'INFO', 'success');
            } else {
                $error = "Invalid format. Use YYYY-YY.";
            }
        }
    }
}

$years = getAllRotaryYears($conn);
$currentYear = getCurrentRotaryYear($conn);

$pageTitle = 'Rotary Year Management';
$activeNav = 'rotary-years';
require __DIR__ . '/includes/admin_head.php';
require __DIR__ . '/includes/admin_header.php';
?>

<?php if ($message): ?>
<div class="alert alert-success"><?= e($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($currentYear): ?>
<div class="alert alert-info" style="background:#fef9c3;border:1px solid #fde68a;color:#854d0e;">
    <i data-lucide="calendar-check" style="width:18px;height:18px;"></i>
    Current Rotary Year: <strong><?= e($currentYear['year_name']) ?></strong>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:24px;">
    <div class="card-header">
        <h2 style="font-size:16px;font-weight:700;margin:0;">Add New Rotary Year</h2>
    </div>
    <div class="card-body">
        <form method="POST" style="display:flex;gap:12px;align-items:end;">
            <input type="hidden" name="action" value="add_year">
            <div style="flex:1;">
                <label class="form-label">Year (e.g. 2027-28)</label>
                <input type="text" name="year_name" class="form-input" required placeholder="e.g. 2027-28" pattern="^\d{4}-\d{2}$" style="max-width:220px;">
            </div>
            <button type="submit" class="btn btn-primary"><i data-lucide="plus" style="width:16px;height:16px;"></i> Add Year</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 style="font-size:16px;font-weight:700;margin:0;">All Rotary Years</h2>
        <span style="font-size:13px;color:#94a3b8;"><?= count($years) ?> year<?= count($years) !== 1 ? 's' : '' ?></span>
    </div>
    <div class="card-body" style="padding:0;">
        <?php foreach ($years as $y): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 24px;border-bottom:1px solid #f1f5f9;">
            <div style="display:flex;align-items:center;gap:12px;">
                <span style="font-size:16px;font-weight:700;color:#0f172a;"><?= e($y['year_name']) ?></span>
                <?php if ($y['is_current']): ?>
                    <span class="badge badge-green"><i data-lucide="check" style="width:12px;height:12px;display:inline;vertical-align:middle;"></i> Current</span>
                <?php else: ?>
                    <span class="badge badge-gray">Previous</span>
                <?php endif; ?>
            </div>
            <div style="display:flex;gap:8px;align-items:center;">
                <button onclick="openEditModal(<?= $y['id'] ?>, '<?= e($y['year_name']) ?>')" class="btn btn-edit btn-sm">
                    <i data-lucide="pencil" style="width:14px;height:14px;"></i> Edit
                </button>
                <?php if (!$y['is_current']): ?>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Make <?= e($y['year_name']) ?> the current Rotary year? This will change the active year.')">
                        <input type="hidden" name="action" value="set_current">
                        <input type="hidden" name="year_id" value="<?= $y['id'] ?>">
                        <button type="submit" class="btn btn-yellow btn-sm"><i data-lucide="check-circle" style="width:14px;height:14px;"></i> Make Current</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Edit Rotary Year Modal -->
<div id="edit-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:100;align-items:center;justify-content:center;padding:16px;" onclick="closeEditModal(event)">
    <div style="background:white;border-radius:14px;padding:24px;max-width:400px;width:100%;box-shadow:0 25px 50px rgba(0,0,0,0.2);" onclick="event.stopPropagation()">
        <h3 style="font-size:18px;font-weight:700;margin:0 0 4px;">Edit Rotary Year</h3>
        <p style="font-size:14px;color:#64748b;margin:0 0 16px;">Update the year name below.</p>
        <form method="POST">
            <input type="hidden" name="action" value="edit_year">
            <input type="hidden" name="year_id" id="edit-year-id">
            <div style="margin-bottom:16px;">
                <label class="form-label">Year (e.g. 2027-28)</label>
                <input type="text" name="year_name" id="edit-year-name" class="form-input" required pattern="^\d{4}-\d{2}$" placeholder="e.g. 2027-28">
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="closeEditModal(event)" class="btn btn-ghost">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(id, name) {
    document.getElementById('edit-year-id').value = id;
    document.getElementById('edit-year-name').value = name;
    document.getElementById('edit-modal').style.display = 'flex';
}
function closeEditModal(e) {
    document.getElementById('edit-modal').style.display = 'none';
}
</script>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
