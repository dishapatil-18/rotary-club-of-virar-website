<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: ../login.php"); exit; }
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/admin_functions.php';
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
        } elseif ($_POST['action'] === 'edit_year' && isset($_POST['year_id'])) {
            $yearId = (int)$_POST['year_id'];
            $yearName = trim($_POST['year_name']);
            if (preg_match('/^\d{4}-\d{2}$/', $yearName)) {
                $stmt = $conn->prepare("UPDATE rotary_years SET year_name = ? WHERE id = ?");
                $stmt->bind_param("si", $yearName, $yearId);
                $stmt->execute();
                $stmt->close();
                $message = "Rotary year updated.";
            } else {
                $error = "Invalid format. Use YYYY-YY.";
            }
        }
    }
}

$years = getAllRotaryYears($conn);
$currentYear = getCurrentRotaryYear($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rotary Year Management - Rotary Club Virar</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root { --rotary-blue: #0A2342; --rotary-yellow: #FFC000; --rotary-gold: #e6a800; }
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; color: #1e293b; margin: 0; }
        .page-wrap { max-width: 900px; margin: 0 auto; padding: 32px 24px; }
        .card { background: white; border-radius: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; overflow: hidden; }
        .card-header { padding: 20px 24px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; }
        .card-body { padding: 20px 24px; }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 9px; font-weight: 600; font-size: 13px; transition: all 0.15s; cursor: pointer; border: none; text-decoration: none; font-family: inherit; }
        .btn-primary { background: var(--rotary-blue); color: #fff; }
        .btn-primary:hover { background: #1a365d; }
        .btn-yellow { background: var(--rotary-yellow); color: var(--rotary-blue); }
        .btn-yellow:hover { background: var(--rotary-gold); }
        .btn-ghost { background: transparent; color: #64748b; }
        .btn-ghost:hover { background: #f1f5f9; }
        .btn-sm { padding: 6px 12px; font-size: 12px; border-radius: 7px; }
        .badge { display: inline-flex; align-items: center; padding: 3px 11px; border-radius: 9999px; font-size: 11px; font-weight: 600; }
        .badge-current { background: #dcfce7; color: #166534; }
        .badge-previous { background: #f1f5f9; color: #64748b; }
        input { width: 100%; padding: 10px 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px; font-family: inherit; outline: none; transition: all 0.2s; }
        input:focus { border-color: var(--rotary-yellow); box-shadow: 0 0 0 4px rgba(255,192,0,0.1); }
        .year-list { list-style: none; padding: 0; margin: 0; }
        .year-item { display: flex; align-items: center; justify-content: space-between; padding: 16px 24px; border-bottom: 1px solid #f1f5f9; transition: background 0.15s; }
        .year-item:last-child { border-bottom: none; }
        .year-item:hover { background: #f8fafc; }
        .year-name { font-size: 16px; font-weight: 700; color: #0f172a; }
        .year-actions { display: flex; gap: 8px; align-items: center; }
        @media (max-width: 768px) { .page-wrap { padding: 16px; } }
    </style>
</head>
<body>
<div class="page-wrap">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">
        <a href="dashboard.php" class="btn btn-ghost btn-sm"><i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Back</a>
        <h1 style="font-size:24px;font-weight:800;color:#0f172a;margin:0;">Rotary Year Management</h1>
    </div>

    <?php if ($currentYear): ?>
    <div style="background:#fef9c3;border:1px solid #fde68a;padding:12px 20px;border-radius:10px;margin-bottom:20px;display:flex;align-items:center;gap:10px;">
        <i data-lucide="calendar-check" style="width:20px;height:20px;color:#854d0e;flex-shrink:0;"></i>
        <span style="font-size:14px;font-weight:600;color:#854d0e;">Current Rotary Year: <strong><?= e($currentYear['year_name']) ?></strong></span>
    </div>
    <?php endif; ?>

    <?php if ($message): ?><div style="background:#dcfce7;border:1px solid #bbf7d0;color:#166534;padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:14px;font-weight:500;"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div style="background:#fee2e2;border:1px solid #fecaca;color:#991b1b;padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:14px;font-weight:500;"><?= e($error) ?></div><?php endif; ?>

    <div class="card" style="margin-bottom:24px;">
        <div class="card-header">
            <h2 style="font-size:16px;font-weight:700;margin:0;">Add New Rotary Year</h2>
        </div>
        <div class="card-body">
            <form method="POST" style="display:flex;gap:12px;align-items:end;">
                <div style="flex:1;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:4px;">Year (e.g. 2027-28)</label>
                    <input type="text" name="year_name" required placeholder="e.g. 2027-28" pattern="^\d{4}-\d{2}$" style="max-width:220px;">
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
            <div class="year-list">
                <?php foreach ($years as $y): ?>
                <div class="year-item">
                    <div style="display:flex;align-items:center;gap:12px;">
                        <span class="year-name"><?= e($y['year_name']) ?></span>
                        <?php if ($y['is_current']): ?>
                            <span class="badge badge-current"><i data-lucide="check" style="width:12px;height:12px;display:inline;vertical-align:middle;"></i> Current</span>
                        <?php else: ?>
                            <span class="badge badge-previous">Previous</span>
                        <?php endif; ?>
                    </div>
                    <div class="year-actions">
                        <?php if (!$y['is_current']): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="set_current">
                                <input type="hidden" name="year_id" value="<?= $y['id'] ?>">
                                <button type="submit" class="btn btn-yellow btn-sm" onclick="return confirm('Make <?= e($y['year_name']) ?> the current Rotary year? This will change the active year.')"><i data-lucide="check-circle" style="width:14px;height:14px;"></i> Make Current</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
lucide.createIcons();
</script>
</body>
</html>
