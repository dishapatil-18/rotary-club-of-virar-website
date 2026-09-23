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
        if ($_POST['action'] === 'add_admin') {
            $name = trim($_POST['name']);
            $email = trim($_POST['email']);
            $role = $_POST['role'];
            $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
            $phone = trim($_POST['phone'] ?? '');

            // Only one Super Admin allowed
            if ($role === 'super_admin') {
                $stmt = $conn->prepare("SELECT admin_id FROM admins WHERE role = 'super_admin' LIMIT 1");
                $stmt->execute();
                $saCheck = $stmt->get_result();
                if ($saCheck && $saCheck->num_rows > 0) {
                    $error = 'A Super Admin already exists. Only one Super Admin is allowed.';
                }
                $stmt->close();
            }

            if (empty($error)) {
            $stmt2 = $conn->prepare("SELECT admin_id FROM admins WHERE email = ?");
            $stmt2->bind_param("s", $email);
            $stmt2->execute();
            $check = $stmt2->get_result();
            if ($check && $check->num_rows > 0) {
                $error = 'An admin with this email already exists.';
            } else {
                $stmt2->close();
                $stmt3 = $conn->prepare("INSERT INTO admins (name, email, password, role, phone, photo_url, created_at) VALUES (?, ?, ?, ?, ?, '', NOW())");
                $stmt3->bind_param("sssss", $name, $email, $password, $role, $phone);
                if ($stmt3->execute()) {
                    $message = 'Admin account created successfully.';
                    logAudit($conn, 'Administration', 'Admin Added', 'Created admin "' . $name . '" (' . $email . ') with role "' . $role . '".', 'CRITICAL', 'success');
                } else {
                    $error = 'Failed to create admin: ' . $stmt3->error;
                }
                $stmt3->close();
            }
            }
        } elseif ($_POST['action'] === 'reset_password' && isset($_POST['admin_id'])) {
            $adminId = (int)$_POST['admin_id'];
            $newPassword = password_hash(trim($_POST['password']), PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE admin_id = ?");
            $stmt->bind_param("si", $newPassword, $adminId);
            if ($stmt->execute()) {
                $message = 'Password reset successfully.';
                logAudit($conn, 'Administration', 'Admin Password Reset', 'Super Admin reset password for admin ID ' . $adminId . '.', 'CRITICAL', 'success');
            } else {
                $error = 'Failed to reset password.';
            }
            $stmt->close();
        } elseif ($_POST['action'] === 'toggle_status' && isset($_POST['admin_id'])) {
            $adminId = (int)$_POST['admin_id'];
            // Prevent disabling Super Admin
            $stmt = $conn->prepare("SELECT role FROM admins WHERE admin_id = ?");
            $stmt->bind_param("i", $adminId);
            $stmt->execute();
            $saCheck = $stmt->get_result();
            $saRow = $saCheck ? $saCheck->fetch_assoc() : null;
            $stmt->close();
            if ($saRow && $saRow['role'] === 'super_admin') {
                $error = 'Super Admin account cannot be disabled.';
            } else {
            $stmt = $conn->prepare("SELECT status FROM admins WHERE admin_id = ?");
            $stmt->bind_param("i", $adminId);
            $stmt->execute();
            $res = $stmt->get_result();
            $currentStatus = $res ? $res->fetch_assoc() : null;
            $stmt->close();
            if ($currentStatus) {
                $newStatus = ($currentStatus['status'] ?? 'active') === 'active' ? 'inactive' : 'active';
                $stmt2 = $conn->prepare("UPDATE admins SET status = ? WHERE admin_id = ?");
                $stmt2->bind_param("si", $newStatus, $adminId);
                $stmt2->execute();
                $stmt2->close();
                $message = 'Admin status updated.';
                $accessAction = $newStatus === 'active' ? 'Admin Access Granted' : 'Admin Access Revoked';
                logAudit($conn, 'Administration', $accessAction, 'Admin ID ' . $adminId . ' status changed to "' . $newStatus . '".', 'WARNING', 'success');
            }
            }
        } elseif ($_POST['action'] === 'update_role' && isset($_POST['admin_id'])) {
            $adminId = (int)$_POST['admin_id'];
            // Prevent changing Super Admin role
            $stmt = $conn->prepare("SELECT role FROM admins WHERE admin_id = ?");
            $stmt->bind_param("i", $adminId);
            $stmt->execute();
            $saCheck = $stmt->get_result();
            $saRow = $saCheck ? $saCheck->fetch_assoc() : null;
            $stmt->close();
            if ($saRow && $saRow['role'] === 'super_admin') {
                $error = 'Super Admin role cannot be changed.';
            } else {
            $newRole = $_POST['role'];
            $stmt = $conn->prepare("UPDATE admins SET role = ? WHERE admin_id = ?");
            $stmt->bind_param("si", $newRole, $adminId);
            $stmt->execute();
            $stmt->close();
            $message = 'Admin role updated.';
            logAudit($conn, 'Administration', 'Admin Role Updated', 'Admin ID ' . $adminId . ' role changed to "' . $newRole . '".', 'WARNING', 'success');
            }
        }
    }
}

$admins = [];
$r = $conn->query("SELECT admin_id, name, email, role, COALESCE(status, 'active') as status, phone, created_at FROM admins ORDER BY FIELD(role, 'super_admin', 'President', 'Secretary', 'Treasurer', 'IT Admin'), name ASC");
if ($r) { while ($row = $r->fetch_assoc()) $admins[] = $row; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Management - <?= e($_ws['website_short_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root { --rotary-blue: #0A2342; --rotary-yellow: #FFC000; --rotary-gold: #e6a800; }
        * { box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; color: #1e293b; margin: 0; }
        .page-wrap { max-width: 1200px; margin: 0 auto; padding: 32px 24px; }
        .card { background: white; border-radius: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #f1f5f9; overflow: hidden; }
        .card-header { padding: 20px 24px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; }
        .card-body { padding: 20px 24px; }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 9px; font-weight: 600; font-size: 13px; transition: all 0.15s; cursor: pointer; border: none; text-decoration: none; font-family: inherit; }
        .btn-primary { background: var(--rotary-blue); color: #fff; }
        .btn-primary:hover { background: #1a365d; }
        .btn-yellow { background: var(--rotary-yellow); color: var(--rotary-blue); }
        .btn-yellow:hover { background: var(--rotary-gold); }
        .btn-danger { background: #ef4444; color: #fff; }
        .btn-danger:hover { background: #dc2626; }
        .btn-ghost { background: transparent; color: #64748b; }
        .btn-ghost:hover { background: #f1f5f9; }
        .btn-sm { padding: 6px 12px; font-size: 12px; border-radius: 7px; }
        .badge { display: inline-flex; align-items: center; padding: 3px 11px; border-radius: 9999px; font-size: 11px; font-weight: 600; }
        .badge-super { background: #fef9c3; color: #854d0e; }
        .badge-pres { background: #dbeafe; color: #1e40af; }
        .badge-sec { background: #d1fae5; color: #166534; }
        .badge-tres { background: #f3e8ff; color: #6b21a8; }
        .badge-it { background: #f1f5f9; color: #475569; }
        .badge-active { background: #dcfce7; color: #166534; }
        .badge-inactive { background: #fee2e2; color: #991b1b; }
        input, select { width: 100%; padding: 10px 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px; font-family: inherit; outline: none; transition: all 0.2s; }
        input:focus, select:focus { border-color: var(--rotary-yellow); box-shadow: 0 0 0 4px rgba(255,192,0,0.1); }
        @media (max-width: 768px) { .page-wrap { padding: 16px; } }
    </style>
</head>
<body>
<div class="page-wrap">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">
        <a href="dashboard.php" class="btn btn-ghost btn-sm"><i data-lucide="arrow-left" style="width:16px;height:16px;"></i> Back</a>
        <h1 style="font-size:24px;font-weight:800;color:#0f172a;margin:0;">Admin Account Management</h1>
    </div>

    <?php if ($message): ?><div style="background:#dcfce7;border:1px solid #bbf7d0;color:#166534;padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:14px;font-weight:500;"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div style="background:#fee2e2;border:1px solid #fecaca;color:#991b1b;padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:14px;font-weight:500;"><?= e($error) ?></div><?php endif; ?>

    <div class="card" style="margin-bottom:24px;">
        <div class="card-header">
            <h2 style="font-size:16px;font-weight:700;margin:0;">Create New Admin</h2>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="add_admin">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:4px;">Full Name</label>
                        <input type="text" name="name" required placeholder="e.g. Rtn. John Doe">
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:4px;">Email</label>
                        <input type="email" name="email" required placeholder="admin@example.com">
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:4px;">Role</label>
                        <select name="role" required>
                            <option value="President">President</option>
                            <option value="Secretary">Secretary</option>
                            <option value="Treasurer">Treasurer</option>
                            <option value="IT Admin">IT Admin</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:4px;">Password</label>
                        <input type="password" name="password" required placeholder="Min 8 characters" minlength="8">
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:4px;">Phone</label>
                        <input type="text" name="phone" placeholder="+91 9XXXXXXXXX">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary"><i data-lucide="user-plus" style="width:16px;height:16px;"></i> Create Admin Account</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 style="font-size:16px;font-weight:700;margin:0;">All Admin Accounts</h2>
            <span style="font-size:13px;color:#94a3b8;"><?= count($admins) ?> total</span>
        </div>
        <div class="card-body" style="padding:0;overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:14px;">
                <thead>
                    <tr style="background:#f8fafc;text-align:left;">
                        <th style="padding:12px 16px;font-weight:600;color:#64748b;font-size:12px;text-transform:uppercase;">Name</th>
                        <th style="padding:12px 16px;font-weight:600;color:#64748b;font-size:12px;text-transform:uppercase;">Email</th>
                        <th style="padding:12px 16px;font-weight:600;color:#64748b;font-size:12px;text-transform:uppercase;">Role</th>
                        <th style="padding:12px 16px;font-weight:600;color:#64748b;font-size:12px;text-transform:uppercase;">Status</th>
                        <th style="padding:12px 16px;font-weight:600;color:#64748b;font-size:12px;text-transform:uppercase;">Created</th>
                        <th style="padding:12px 16px;font-weight:600;color:#64748b;font-size:12px;text-transform:uppercase;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($admins as $a): 
                        $roleBadgeClass = match($a['role']) {
                            'super_admin' => 'badge-super',
                            'President' => 'badge-pres',
                            'Secretary' => 'badge-sec',
                            'Treasurer' => 'badge-tres',
                            default => 'badge-it'
                        };
                    ?>
                    <tr style="border-top:1px solid #f1f5f9;">
                        <td style="padding:12px 16px;font-weight:600;color:#0f172a;"><?= e($a['name']) ?></td>
                        <td style="padding:12px 16px;color:#64748b;"><?= e($a['email']) ?></td>
                        <td style="padding:12px 16px;"><span class="badge <?= $roleBadgeClass ?>"><?= e($a['role']) ?></span></td>
                        <td style="padding:12px 16px;"><span class="badge <?= ($a['status'] ?? 'active') === 'active' ? 'badge-active' : 'badge-inactive' ?>"><?= ($a['status'] ?? 'active') === 'active' ? 'Active' : 'Inactive' ?></span></td>
                        <td style="padding:12px 16px;color:#94a3b8;font-size:13px;"><?= e($a['created_at'] ?? '-') ?></td>
                        <td style="padding:12px 16px;">
                            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                <button onclick="showResetPassword(<?= $a['admin_id'] ?>,'<?= e($a['name']) ?>')" class="btn btn-ghost btn-sm"><i data-lucide="key" style="width:14px;height:14px;"></i> Reset Pwd</button>
                                <?php if ($a['role'] !== 'super_admin'): ?>
                                <button onclick="showChangeRole(<?= $a['admin_id'] ?>,'<?= e($a['name']) ?>','<?= e($a['role']) ?>')" class="btn btn-ghost btn-sm"><i data-lucide="shuffle" style="width:14px;height:14px;"></i> Role</button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Toggle status for <?= e($a['name']) ?>?')">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="admin_id" value="<?= $a['admin_id'] ?>">
                                    <button type="submit" class="btn btn-ghost btn-sm"><i data-lucide="<?= ($a['status'] ?? 'active') === 'active' ? 'pause-circle' : 'play-circle' ?>" style="width:14px;height:14px;"></i> <?= ($a['status'] ?? 'active') === 'active' ? 'Deactivate' : 'Activate' ?></button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="reset-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:100;align-items:center;justify-content:center;padding:16px;" onclick="closeResetModal(event)">
    <div style="background:white;border-radius:14px;padding:24px;max-width:400px;width:100%;box-shadow:0 25px 50px rgba(0,0,0,0.2);" onclick="event.stopPropagation()">
        <h3 style="font-size:18px;font-weight:700;margin:0 0 4px;">Reset Password</h3>
        <p style="font-size:14px;color:#64748b;margin:0 0 16px;" id="reset-admin-name"></p>
        <form method="POST">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="admin_id" id="reset-admin-id">
            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:4px;">New Password</label>
                <input type="password" name="password" required minlength="8" placeholder="Min 8 characters">
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="closeResetModal(event)" class="btn btn-ghost">Cancel</button>
                <button type="submit" class="btn btn-primary">Reset Password</button>
            </div>
        </form>
    </div>
</div>

<!-- Change Role Modal -->
<div id="role-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:100;align-items:center;justify-content:center;padding:16px;" onclick="closeRoleModal(event)">
    <div style="background:white;border-radius:14px;padding:24px;max-width:400px;width:100%;box-shadow:0 25px 50px rgba(0,0,0,0.2);" onclick="event.stopPropagation()">
        <h3 style="font-size:18px;font-weight:700;margin:0 0 4px;">Change Role</h3>
        <p style="font-size:14px;color:#64748b;margin:0 0 16px;" id="role-admin-name"></p>
        <form method="POST">
            <input type="hidden" name="action" value="update_role">
            <input type="hidden" name="admin_id" id="role-admin-id">
            <div style="margin-bottom:16px;">
                <label style="display:block;font-size:13px;font-weight:600;color:#475569;margin-bottom:4px;">New Role</label>
                <select name="role" id="role-select" required>
                    <option value="President">President</option>
                    <option value="Secretary">Secretary</option>
                    <option value="Treasurer">Treasurer</option>
                    <option value="IT Admin">IT Admin</option>
                </select>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="closeRoleModal(event)" class="btn btn-ghost">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Role</button>
            </div>
        </form>
    </div>
</div>

<script>
lucide.createIcons();

function showResetPassword(id, name) {
    document.getElementById('reset-admin-id').value = id;
    document.getElementById('reset-admin-name').textContent = 'Reset password for ' + name;
    document.getElementById('reset-modal').style.display = 'flex';
}
function closeResetModal(e) { document.getElementById('reset-modal').style.display = 'none'; }
function showChangeRole(id, name, currentRole) {
    document.getElementById('role-admin-id').value = id;
    document.getElementById('role-admin-name').textContent = 'Change role for ' + name;
    document.getElementById('role-select').value = currentRole;
    document.getElementById('role-modal').style.display = 'flex';
}
function closeRoleModal(e) { document.getElementById('role-modal').style.display = 'none'; }
</script>
</body>
</html>
