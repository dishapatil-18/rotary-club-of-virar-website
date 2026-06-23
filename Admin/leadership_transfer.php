<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: ../login.php"); exit; }
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/admin_functions.php';
if (!isSuperAdmin()) { echo "<script>alert('Access denied. Super Admin only.'); window.location.href='dashboard.php';</script>"; exit; }

$message = '';
$error = '';

$years = getAllRotaryYears($conn);
$currentYear = getCurrentRotaryYear($conn);

// Get all active members for assignment dropdowns
$members = [];
$r = $conn->query("SELECT member_id, name, role FROM members WHERE status = 'Active' ORDER BY name ASC");
if ($r) { while ($row = $r->fetch_assoc()) $members[] = $row; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_leadership') {
    $selectedYearId = (int)$_POST['rotary_year'];
    $presidentId = (int)$_POST['president'];
    $secretaryId = (int)$_POST['secretary'];
    $treasurerId = (int)$_POST['treasurer'];

    $conn->begin_transaction();
    try {
        $assignments = [
            'President' => $presidentId,
            'Secretary' => $secretaryId,
            'Treasurer' => $treasurerId,
        ];
        foreach ($assignments as $role => $memberId) {
            $stmt = $conn->prepare("INSERT INTO leadership_assignments (rotary_year_id, member_id, role) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE member_id = VALUES(member_id)");
            $stmt->bind_param("iis", $selectedYearId, $memberId, $role);
            $stmt->execute();
            $stmt->close();

            // Update member role in members table
            $stmt2 = $conn->prepare("UPDATE members SET role = ? WHERE member_id = ?");
            $stmt2->bind_param("si", $role, $memberId);
            $stmt2->execute();
            $stmt2->close();
        }

        $conn->commit();
        $message = 'Leadership saved successfully for the selected year.';
    } catch (Exception $e) {
        $conn->rollback();
        $error = 'Failed to save leadership: ' . $e->getMessage();
    }
}

// Get current leadership assignments for the current year
$currentLeaders = [];
if ($currentYear) {
    $currentLeaders = getLeadershipForYear($conn, $currentYear['id']);
}
$currentLeadershipMap = [];
foreach ($currentLeaders as $l) {
    $currentLeadershipMap[$l['role']] = $l;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leadership Management - Rotary Club Virar</title>
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
        .btn-lg { padding: 12px 24px; font-size: 15px; }
        .btn-block { width: 100%; justify-content: center; }
        select, input { width: 100%; padding: 10px 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px; font-family: inherit; outline: none; transition: all 0.2s; background: white; }
        select:focus, input:focus { border-color: var(--rotary-yellow); box-shadow: 0 0 0 4px rgba(255,192,0,0.1); }
        label { display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 4px; }
        .role-tag { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 9999px; font-size: 12px; font-weight: 600; }
        .role-president { background: #dbeafe; color: #1e40af; }
        .role-secretary { background: #d1fae5; color: #166534; }
        .role-treasurer { background: #fef9c3; color: #854d0e; }
        .step-number { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; background: var(--rotary-yellow); color: var(--rotary-blue); font-weight: 800; font-size: 13px; flex-shrink: 0; }
        .leader-card { text-align: center; padding: 20px; background: #f8fafc; border-radius: 12px; border: 2px solid #f1f5f9; }
        .leader-card .name { font-weight: 700; color: #0f172a; font-size: 15px; margin-top: 8px; }
        .leader-card .email { font-size: 12px; color: #64748b; }
        @media (max-width: 768px) { .page-wrap { padding: 16px; } }
    </style>
</head>
<body>
<div class="page-wrap">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">
        <a href="dashboard.php" class="btn btn-ghost btn-sm"><i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Back</a>
        <h1 style="font-size:24px;font-weight:800;color:#0f172a;margin:0;">Leadership Management</h1>
    </div>

    <?php if ($currentYear): ?>
    <div style="background:#fef9c3;border:1px solid #fde68a;padding:12px 20px;border-radius:10px;margin-bottom:20px;display:flex;align-items:center;gap:10px;">
        <i data-lucide="calendar-check" style="width:20px;height:20px;color:#854d0e;flex-shrink:0;"></i>
        <span style="font-size:14px;font-weight:600;color:#854d0e;">Current Rotary Year: <strong><?= e($currentYear['year_name']) ?></strong></span>
    </div>
    <?php endif; ?>

    <?php if ($message): ?><div style="background:#dcfce7;border:1px solid #bbf7d0;color:#166534;padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:14px;font-weight:500;"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div style="background:#fee2e2;border:1px solid #fecaca;color:#991b1b;padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:14px;font-weight:500;"><?= e($error) ?></div><?php endif; ?>

    <!-- Current Leadership Display -->
    <?php if ($currentYear && !empty($currentLeadershipMap)): ?>
    <div class="card" style="margin-bottom:24px;">
        <div class="card-header">
            <h2 style="font-size:16px;font-weight:700;margin:0;">Current Leadership Team — <?= e($currentYear['year_name']) ?></h2>
        </div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;">
                <?php foreach (['President', 'Secretary', 'Treasurer'] as $role): 
                    $leader = $currentLeadershipMap[$role] ?? null;
                    $badgeClass = match($role) { 'President' => 'role-president', 'Secretary' => 'role-secretary', 'Treasurer' => 'role-treasurer', default => '' };
                ?>
                <div class="leader-card">
                    <span class="role-tag <?= $badgeClass ?>"><?= $role ?></span>
                    <div class="name"><?= e($leader['name'] ?? 'Not assigned') ?></div>
                    <?php if ($leader && $leader['email']): ?>
                    <div class="email"><?= e($leader['email']) ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Assign New Leadership -->
    <div class="card" style="margin-bottom:24px;">
        <div class="card-header">
            <div style="display:flex;align-items:center;gap:10px;">
                <span class="step-number">1</span>
                <h2 style="font-size:16px;font-weight:700;margin:0;">Select Rotary Year</h2>
            </div>
        </div>
        <div class="card-body">
            <p style="font-size:13px;color:#64748b;margin:0 0 12px;">Choose the Rotary Year you want to assign leadership for.</p>
            <form method="POST" id="leadership-form">
                <input type="hidden" name="action" value="save_leadership">
                <select name="rotary_year" required style="max-width:300px;">
                    <option value="">-- Select a year --</option>
                    <?php foreach ($years as $y): ?>
                    <option value="<?= $y['id'] ?>" <?= $y['is_current'] ? 'selected' : '' ?>><?= e($y['year_name']) ?> <?= $y['is_current'] ? '(Current)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
        </div>
    </div>

    <div class="card" style="margin-bottom:24px;">
        <div class="card-header">
            <div style="display:flex;align-items:center;gap:10px;">
                <span class="step-number">2</span>
                <h2 style="font-size:16px;font-weight:700;margin:0;">Assign Club Officers</h2>
            </div>
        </div>
        <div class="card-body">
            <p style="font-size:13px;color:#64748b;margin:0 0 16px;">Select the members who will serve as President, Secretary, and Treasurer for this year.</p>

                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:20px;margin-bottom:24px;">
                    <div>
                        <label style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                            <span class="role-tag role-president">President</span>
                        </label>
                        <select name="president" required>
                            <option value="">-- Choose President --</option>
                            <?php foreach ($members as $m): ?>
                            <option value="<?= $m['member_id'] ?>" <?= ($m['role'] ?? '') === 'President' ? 'selected' : '' ?>><?= e($m['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                            <span class="role-tag role-secretary">Secretary</span>
                        </label>
                        <select name="secretary" required>
                            <option value="">-- Choose Secretary --</option>
                            <?php foreach ($members as $m): ?>
                            <option value="<?= $m['member_id'] ?>" <?= ($m['role'] ?? '') === 'Secretary' ? 'selected' : '' ?>><?= e($m['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                            <span class="role-tag role-treasurer">Treasurer</span>
                        </label>
                        <select name="treasurer" required>
                            <option value="">-- Choose Treasurer --</option>
                            <?php foreach ($members as $m): ?>
                            <option value="<?= $m['member_id'] ?>" <?= ($m['role'] ?? '') === 'Treasurer' ? 'selected' : '' ?>><?= e($m['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="background:#f0f9ff;border:1px solid #bae6fd;padding:14px 18px;border-radius:10px;margin-bottom:20px;display:flex;align-items:flex-start;gap:10px;">
                    <i data-lucide="info" style="width:18px;height:18px;color:#0369a1;flex-shrink:0;margin-top:2px;"></i>
                    <div style="font-size:13px;color:#0369a1;line-height:1.5;">
                        <strong>Note:</strong> Previous leadership records for this year will be updated. All past assignments are preserved in history.
                    </div>
                </div>

                <div style="display:flex;gap:12px;justify-content:flex-end;">
                    <span class="step-number" style="display:inline-flex;">3</span>
                    <button type="submit" class="btn btn-yellow btn-lg" onclick="return confirm('Save this leadership team for the selected year?')">
                        <i data-lucide="save" style="width:18px;height:18px;"></i> Save Leadership
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Previous Leadership History -->
    <?php
    $historyYears = $conn->query("SELECT id, year_name FROM rotary_years WHERE is_current = 0 ORDER BY year_name DESC LIMIT 5");
    if ($historyYears && $historyYears->num_rows > 0):
    ?>
    <div class="card">
        <div class="card-header">
            <h2 style="font-size:16px;font-weight:700;margin:0;">Previous Leadership Teams</h2>
        </div>
        <div class="card-body" style="padding:0;">
            <table style="width:100%;border-collapse:collapse;font-size:14px;">
                <thead>
                    <tr style="background:#f8fafc;text-align:left;">
                        <th style="padding:12px 16px;font-weight:600;color:#64748b;font-size:12px;text-transform:uppercase;">Year</th>
                        <th style="padding:12px 16px;font-weight:600;color:#64748b;font-size:12px;text-transform:uppercase;">President</th>
                        <th style="padding:12px 16px;font-weight:600;color:#64748b;font-size:12px;text-transform:uppercase;">Secretary</th>
                        <th style="padding:12px 16px;font-weight:600;color:#64748b;font-size:12px;text-transform:uppercase;">Treasurer</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($hy = $historyYears->fetch_assoc()): 
                        $leaders = getLeadershipForYear($conn, $hy['id']);
                        $lmap = [];
                        foreach ($leaders as $l) $lmap[$l['role']] = $l['name'];
                    ?>
                    <tr style="border-top:1px solid #f1f5f9;">
                        <td style="padding:12px 16px;font-weight:600;color:#0f172a;"><?= e($hy['year_name']) ?></td>
                        <td style="padding:12px 16px;"><?= e($lmap['President'] ?? '-') ?></td>
                        <td style="padding:12px 16px;"><?= e($lmap['Secretary'] ?? '-') ?></td>
                        <td style="padding:12px 16px;"><?= e($lmap['Treasurer'] ?? '-') ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
lucide.createIcons();
</script>
</body>
</html>
