<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: ../login.php"); exit; }
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/admin_functions.php';
require_once __DIR__ . '/../includes/website_settings.php';
require_once __DIR__ . '/../includes/audit_log.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
$_ws = getWebsiteSettings($conn);
if (!isSuperAdmin()) { echo "<script>alert('Access denied. Super Admin only.'); window.location.href='dashboard.php';</script>"; exit; }

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validateCsrfToken()) {
        $error = 'Invalid security token.';
    } elseif ($_POST['action'] === 'assign_role') {
        $yearId = (int)$_POST['rotary_year'];
        $role = trim($_POST['role'] ?? '');
        $memberId = (int)($_POST['member_id'] ?? 0);
        $validRoles = ['President', 'Secretary', 'Treasurer'];

        if (!in_array($role, $validRoles)) {
            $error = 'Invalid role selected.';
        } elseif ($yearId <= 0) {
            $error = 'Invalid year selected.';
        } elseif ($memberId <= 0) {
            $error = 'Please select a member.';
        } else {
            $check = $conn->prepare("SELECT role FROM leadership_assignments WHERE rotary_year_id = ? AND member_id = ?");
            $check->bind_param("ii", $yearId, $memberId);
            $check->execute();
            $existing = $check->get_result()->fetch_assoc();
            $check->close();

            if ($existing && $existing['role'] === $role) {
                $message = 'This member is already assigned as ' . $role . ' for this year. No changes needed.';
            } elseif ($existing) {
                $error = 'This member is already assigned as ' . $existing['role'] . ' for this year.';
            } else {
                $check2 = $conn->prepare("SELECT member_id FROM leadership_assignments WHERE rotary_year_id = ? AND role = ?");
                $check2->bind_param("is", $yearId, $role);
                $check2->execute();
                $existingRole = $check2->get_result()->fetch_assoc();
                $check2->close();

                if ($existingRole) {
                    $error = 'The ' . $role . ' role is already assigned for this year. Use Edit to change the member.';
                } else {
                    $stmt = $conn->prepare("INSERT INTO leadership_assignments (rotary_year_id, member_id, role) VALUES (?, ?, ?)");
                    $stmt->bind_param("iis", $yearId, $memberId, $role);
                    if ($stmt->execute() && $stmt->affected_rows > 0) {
                        $message = $role . ' assigned successfully.';
                        logAudit($conn, 'Administration', 'Leadership Assigned', $role . ' assigned to member ID ' . $memberId . ' for year ID ' . $yearId . '.', 'INFO', 'success');
                    } else {
                        $error = 'Failed to assign role.';
                    }
                    $stmt->close();
                }
            }
        }
    } elseif ($_POST['action'] === 'edit_assignment' && isset($_POST['assignment_id'])) {
        $assignmentId = (int)$_POST['assignment_id'];
        $newMemberId = (int)($_POST['member_id'] ?? 0);

        if ($assignmentId <= 0) {
            $error = 'Invalid assignment.';
        } elseif ($newMemberId <= 0) {
            $error = 'Please select a member.';
        } else {
            $check = $conn->prepare("SELECT rotary_year_id, role, member_id FROM leadership_assignments WHERE id = ?");
            $check->bind_param("i", $assignmentId);
            $check->execute();
            $assignment = $check->get_result()->fetch_assoc();
            $check->close();

            if (!$assignment) {
                $error = 'Assignment not found.';
            } elseif ($assignment['member_id'] == $newMemberId) {
                $message = 'No changes made.';
            } else {
                $check2 = $conn->prepare("SELECT role FROM leadership_assignments WHERE rotary_year_id = ? AND member_id = ? AND id != ?");
                $check2->bind_param("iii", $assignment['rotary_year_id'], $newMemberId, $assignmentId);
                $check2->execute();
                $existingMember = $check2->get_result()->fetch_assoc();
                $check2->close();

                if ($existingMember) {
                    $error = 'This member is already assigned as ' . $existingMember['role'] . ' for this year.';
                } else {
                    $stmt = $conn->prepare("UPDATE leadership_assignments SET member_id = ? WHERE id = ?");
                    $stmt->bind_param("ii", $newMemberId, $assignmentId);
                    if ($stmt->execute()) {
                        $message = $assignment['role'] . ' assignment updated successfully.';
                        logAudit($conn, 'Administration', 'Leadership Updated', $assignment['role'] . ' reassigned from member ID ' . $assignment['member_id'] . ' to member ID ' . $newMemberId . ' for year ID ' . $assignment['rotary_year_id'] . '.', 'INFO', 'success');
                    } else {
                        $error = 'Failed to update assignment.';
                    }
                    $stmt->close();
                }
            }
        }
    } elseif ($_POST['action'] === 'end_assignment' && isset($_POST['assignment_id'])) {
        $assignmentId = (int)$_POST['assignment_id'];

        if ($assignmentId <= 0) {
            $error = 'Invalid assignment.';
        } else {
            $check = $conn->prepare("SELECT rotary_year_id, role, member_id FROM leadership_assignments WHERE id = ?");
            $check->bind_param("i", $assignmentId);
            $check->execute();
            $assignment = $check->get_result()->fetch_assoc();
            $check->close();

            if (!$assignment) {
                $error = 'Assignment not found.';
            } else {
                $memberName = '';
                $mStmt = $conn->prepare("SELECT name FROM members WHERE member_id = ?");
                $mStmt->bind_param("i", $assignment['member_id']);
                $mStmt->execute();
                $mRow = $mStmt->get_result()->fetch_assoc();
                if ($mRow) $memberName = $mRow['name'];
                $mStmt->close();

                $stmt = $conn->prepare("DELETE FROM leadership_assignments WHERE id = ?");
                $stmt->bind_param("i", $assignmentId);
                if ($stmt->execute()) {
                    $message = $assignment['role'] . ' assignment ended.';
                    logAudit($conn, 'Administration', 'Leadership Removed', $assignment['role'] . ' (member: ' . $memberName . ', ID ' . $assignment['member_id'] . ') removed from year ID ' . $assignment['rotary_year_id'] . '.', 'WARNING', 'success');
                } else {
                    $error = 'Failed to end assignment.';
                }
                $stmt->close();
            }
        }
    }
}

$years = getAllRotaryYears($conn);
$currentYear = getCurrentRotaryYear($conn);

$selectedYearId = isset($_GET['year']) ? (int)$_GET['year'] : 0;
if ($selectedYearId <= 0 && $currentYear) { $selectedYearId = $currentYear['id']; }
if ($selectedYearId <= 0 && !empty($years)) { $selectedYearId = $years[0]['id']; }

$selectedYearName = '';
foreach ($years as $y) {
    if ($y['id'] == $selectedYearId) { $selectedYearName = $y['year_name']; break; }
}

$members = [];
$r = $conn->query("SELECT member_id, name, role FROM members WHERE status = 'Active' ORDER BY name ASC");
if ($r) { while ($row = $r->fetch_assoc()) $members[] = $row; }

$assignments = [];
if ($selectedYearId > 0) {
    $assignments = getLeadershipForYear($conn, $selectedYearId);
}

$assignedRoles = [];
foreach ($assignments as $a) { $assignedRoles[$a['role']] = $a; }

$pageTitle = 'Leadership Management';
$activeNav = 'leadership';
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
        <h2 style="font-size:16px;font-weight:700;margin:0;">Select Rotary Year</h2>
    </div>
    <div class="card-body">
        <form method="GET" style="display:flex;gap:12px;align-items:end;">
            <div style="flex:1;">
                <label class="form-label">Rotary Year</label>
                <select name="year" class="form-select" onchange="this.form.submit()" style="max-width:300px;">
                    <?php foreach ($years as $y): ?>
                    <option value="<?= $y['id'] ?>" <?= $y['id'] == $selectedYearId ? 'selected' : '' ?>><?= e($y['year_name']) ?> <?= $y['is_current'] ? '(Current)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<div class="card" style="margin-bottom:24px;">
    <div class="card-header">
        <h2 style="font-size:16px;font-weight:700;margin:0;">Assign New Role</h2>
    </div>
    <div class="card-body">
        <div style="font-size:13px;color:#64748b;margin-bottom:12px;">Assigning to: <strong><?= e($selectedYearName) ?></strong></div>
        <form method="POST" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap;">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="assign_role">
            <input type="hidden" name="rotary_year" value="<?= $selectedYearId ?>">
            <div style="flex:1;min-width:180px;">
                <label class="form-label">Role</label>
                <select name="role" class="form-select" required>
                    <option value="">-- Select Role --</option>
                    <?php foreach (['President', 'Secretary', 'Treasurer'] as $role): ?>
                    <option value="<?= $role ?>" <?= isset($assignedRoles[$role]) ? 'disabled' : '' ?>><?= $role ?><?= isset($assignedRoles[$role]) ? ' (Assigned)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex:2;min-width:220px;">
                <label class="form-label">Member</label>
                <select name="member_id" class="form-select" required>
                    <option value="">-- Select Member --</option>
                    <?php foreach ($members as $m): ?>
                    <option value="<?= $m['member_id'] ?>"><?= e($m['name']) ?><?= $m['role'] ? ' (' . e($m['role']) . ')' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary"><i data-lucide="user-plus" style="width:16px;height:16px;"></i> Assign</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 style="font-size:16px;font-weight:700;margin:0;">Leadership — <?= e($selectedYearName) ?></h2>
        <span style="font-size:13px;color:#94a3b8;"><?= count($assignments) ?> assigned</span>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Role</th>
                    <th>Member</th>
                    <th>Email</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (['President', 'Secretary', 'Treasurer'] as $role):
                    $a = $assignedRoles[$role] ?? null;
                    $badgeClass = match($role) { 'President' => 'badge-blue', 'Secretary' => 'badge-green', 'Treasurer' => 'badge-yellow', default => 'badge-gray' };
                ?>
                <tr>
                    <td><span class="badge <?= $badgeClass ?>"><?= e($role) ?></span></td>
                    <?php if ($a): ?>
                    <td style="font-weight:600;color:#0f172a;"><?= e($a['name']) ?></td>
                    <td style="color:#64748b;"><?= e($a['email']) ?></td>
                    <td style="text-align:right;">
                        <div style="display:flex;gap:6px;justify-content:flex-end;">
                            <button onclick="openEditModal(<?= $a['assignment_id'] ?>, '<?= e($role) ?>', <?= $a['member_id'] ?>)" class="btn btn-edit btn-sm"><i data-lucide="pencil" style="width:14px;height:14px;"></i> Edit</button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this <?= e($role) ?> assignment? This action cannot be undone.')">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="end_assignment">
                                <input type="hidden" name="assignment_id" value="<?= $a['assignment_id'] ?>">
                                <button type="submit" class="btn btn-delete btn-sm"><i data-lucide="trash-2" style="width:14px;height:14px;"></i> End</button>
                            </form>
                        </div>
                    </td>
                    <?php else: ?>
                    <td colspan="2" style="color:#94a3b8;font-style:italic;">Not assigned</td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="edit-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:100;align-items:center;justify-content:center;padding:16px;" onclick="closeEditModal(event)">
    <div style="background:white;border-radius:14px;padding:24px;max-width:400px;width:100%;box-shadow:0 25px 50px rgba(0,0,0,0.2);" onclick="event.stopPropagation()">
        <h3 style="font-size:18px;font-weight:700;margin:0 0 4px;">Edit Assignment</h3>
        <p style="font-size:14px;color:#64748b;margin:0 0 16px;" id="edit-role-label"></p>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="edit_assignment">
            <input type="hidden" name="assignment_id" id="edit-assignment-id">
            <div style="margin-bottom:16px;">
                <label class="form-label">Member</label>
                <select name="member_id" id="edit-member-id" class="form-select" required>
                    <option value="">-- Select Member --</option>
                    <?php foreach ($members as $m): ?>
                    <option value="<?= $m['member_id'] ?>"><?= e($m['name']) ?><?= $m['role'] ? ' (' . e($m['role']) . ')' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="closeEditModal(event)" class="btn btn-ghost">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(id, roleName, memberId) {
    document.getElementById('edit-assignment-id').value = id;
    document.getElementById('edit-role-label').textContent = 'Update ' + roleName + ' assignment for <?= e($selectedYearName) ?>';
    document.getElementById('edit-member-id').value = memberId;
    document.getElementById('edit-modal').style.display = 'flex';
}
function closeEditModal(e) {
    document.getElementById('edit-modal').style.display = 'none';
}
</script>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
