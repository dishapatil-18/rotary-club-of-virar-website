<?php
// ===============================
// Committee Member Management (Admin Only)
// ===============================

// Start session and check access
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

// Include DB connection
require __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/audit_log.php';
require_once __DIR__ . '/admin_functions.php';
requireCommitteeAccess();

// Get committee ID from query string
$committeeId = isset($_GET['committee_id']) ? intval($_GET['committee_id']) : 0;
if ($committeeId <= 0) {
    echo "<script>alert('No committee selected.'); window.location.href='committee_action.php';</script>";
    exit;
}

// Load committee details
$stmt = $conn->prepare("SELECT c.*, ry.year_name FROM committees c LEFT JOIN rotary_years ry ON c.year_id = ry.id WHERE c.committee_id = ?");
$stmt->bind_param("i", $committeeId);
$stmt->execute();
$committee = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$committee) {
    echo "<script>alert('Committee not found.'); window.location.href='committee_action.php';</script>";
    exit;
}

// Fetch active members for dropdown
$members = [];
$r = $conn->query("SELECT member_id, name, email FROM members WHERE status = 'Active' ORDER BY name ASC");
if ($r) { while ($row = $r->fetch_assoc()) $members[] = $row; }

// Fetch positions for dropdown
$positions = [];
$r = $conn->query("SELECT position_id, position_name FROM committee_position WHERE is_active = 1 ORDER BY display_order ASC");
if ($r) { while ($row = $r->fetch_assoc()) $positions[] = $row; }

// ======================
// ADD MEMBER TO COMMITTEE
// ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_member') {
    $memberId = intval($_POST['member_id'] ?? 0);
    $positionId = intval($_POST['position_id'] ?? 0);
    $joinedDate = $_POST['joined_date'] ?: null;

    // Validation
    if ($memberId <= 0) {
        echo "<script>alert('Please select a member.'); window.history.back();</script>";
        exit;
    }
    if ($positionId <= 0) {
        echo "<script>alert('Please select a position.'); window.history.back();</script>";
        exit;
    }

    // Check if member already assigned to this committee
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM committee_members WHERE committee_id = ? AND member_id = ?");
    $stmt->bind_param("ii", $committeeId, $memberId);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();

    if ($exists > 0) {
        echo "<script>alert('This member is already assigned to this committee.'); window.history.back();</script>";
        exit;
    }

    // Get member name for audit
    $memberName = '';
    foreach ($members as $m) {
        if ($m['member_id'] == $memberId) { $memberName = $m['name']; break; }
    }

    $stmt = $conn->prepare("INSERT INTO committee_members (committee_id, member_id, position_id, joined_date) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiis", $committeeId, $memberId, $positionId, $joinedDate);
    if ($stmt->execute()) {
        $stmt->close();
        logAudit($conn, 'Committees', 'Member Assigned', 'Assigned "' . $memberName . '" to committee "' . $committee['committee_name'] . '".', 'INFO', 'success');
        echo "<script>alert('✅ Member assigned successfully!'); window.location.href='committee_members.php?committee_id=" . $committeeId . "';</script>";
        exit;
    } else {
        $err = $stmt->error;
        $stmt->close();
        echo "<script>alert('DB error: " . addslashes($err) . "'); window.history.back();</script>";
        exit;
    }
}

// ========================
// REMOVE MEMBER FROM COMMITTEE
// ========================
if (isset($_GET['remove'])) {
    $removeId = intval($_GET['remove']);

    // Get member name for audit
    $stmt = $conn->prepare("SELECT m.name FROM committee_members cm JOIN members m ON cm.member_id = m.member_id WHERE cm.id = ?");
    $stmt->bind_param("i", $removeId);
    $stmt->execute();
    $rRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $removedName = $rRow ? $rRow['name'] : 'Unknown';

    $stmt = $conn->prepare("DELETE FROM committee_members WHERE id = ? AND committee_id = ?");
    $stmt->bind_param("ii", $removeId, $committeeId);
    if ($stmt->execute()) {
        $stmt->close();
        logAudit($conn, 'Committees', 'Member Removed', 'Removed "' . $removedName . '" from committee "' . $committee['committee_name'] . '".', 'WARNING', 'success');
        echo "<script>alert('🗑️ Member removed successfully!'); window.location.href='committee_members.php?committee_id=" . $committeeId . "';</script>";
        exit;
    } else {
        $err = $stmt->error;
        $stmt->close();
        echo "<script>alert('DB error: " . addslashes($err) . "'); window.history.back();</script>";
        exit;
    }
}

// ===================
// FETCH CURRENT MEMBERS
// ===================
$assignedMembers = [];
$stmt = $conn->prepare("
    SELECT cm.id, cm.joined_date, cm.created_at,
           m.member_id, m.name, m.email, m.photo_url,
           cp.position_name
    FROM committee_members cm
    JOIN members m ON cm.member_id = m.member_id
    JOIN committee_position cp ON cm.position_id = cp.position_id
    WHERE cm.committee_id = ?
    ORDER BY cp.display_order ASC, m.name ASC
");
$stmt->bind_param("i", $committeeId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $assignedMembers[] = $row;
$stmt->close();

// Get IDs of already-assigned members (to exclude from dropdown)
$assignedIds = array_column($assignedMembers, 'member_id');
?>

<?php
$pageTitle = 'Committee Members — ' . $committee['committee_name'];
$activeNav = 'committees';
require __DIR__ . '/includes/admin_head.php';
require __DIR__ . '/includes/admin_header.php';
?>
    <!-- Back link + Committee Info -->
    <div class="card" style="margin-bottom:20px;">
        <div class="card-body" style="padding:16px 22px;">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <a href="committee_action.php" class="btn btn-ghost btn-sm"><i data-lucide="arrow-left" class="w-4 h-4"></i> Back</a>
                    <div>
                        <h1 class="text-lg font-bold text-gray-800"><?= htmlspecialchars($committee['committee_name']) ?></h1>
                        <p class="text-sm text-gray-500"><?= htmlspecialchars($committee['year_name'] ?? 'No year') ?> <?= $committee['is_active'] ? '' : ' · <span class="text-gray-400">Inactive</span>' ?></p>
                    </div>
                </div>
                <span class="badge badge-blue"><?= count($assignedMembers) ?> member<?= count($assignedMembers) !== 1 ? 's' : '' ?></span>
            </div>
            <?php if ($committee['description']): ?>
                <p class="text-sm text-gray-600 mt-2"><?= htmlspecialchars($committee['description']) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Member Form -->
    <div class="card" style="margin-bottom:20px;">
        <div class="card-body">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Add Member</h2>

            <?php if (empty($members)): ?>
                <div class="alert alert-info">
                    <i data-lucide="info" class="w-4 h-4"></i>
                    No active members available. Add members first via the <a href="member_action.php" class="font-semibold underline">Members</a> module.
                </div>
            <?php elseif (empty($positions)): ?>
                <div class="alert alert-info">
                    <i data-lucide="info" class="w-4 h-4"></i>
                    No positions available. Contact Super Admin to set up committee positions.
                </div>
            <?php else: ?>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add_member">

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="form-label">Member</label>
                        <select name="member_id" required class="form-select">
                            <option value="">-- Select Member --</option>
                            <?php foreach ($members as $m): ?>
                                <?php if (!in_array($m['member_id'], $assignedIds)): ?>
                                <option value="<?= $m['member_id'] ?>"><?= htmlspecialchars($m['name']) ?> (<?= htmlspecialchars($m['email']) ?>)</option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Position</label>
                        <select name="position_id" required class="form-select">
                            <option value="">-- Select Position --</option>
                            <?php foreach ($positions as $p): ?>
                            <option value="<?= $p['position_id'] ?>"><?= htmlspecialchars($p['position_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Joined Date (Optional)</label>
                        <input type="date" name="joined_date" class="form-input">
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="btn btn-primary">
                        <i data-lucide="user-plus" class="w-4 h-4"></i> Assign Member
                    </button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Current Members List -->
    <div class="card">
        <div class="card-body">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Current Members</h2>

        <?php if (empty($assignedMembers)): ?>
            <div class="empty-state">
                <i data-lucide="users"></i>
                <h3>No members assigned</h3>
                <p>Assign members to this committee using the form above.</p>
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th class="text-left">Name</th>
                        <th class="text-left">Email</th>
                        <th class="text-left">Position</th>
                        <th class="text-left">Joined</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assignedMembers as $i => $am): ?>
                        <tr>
                            <td class="text-center"><?= $i + 1 ?></td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <?php if ($am['photo_url']): ?>
                                        <img src="../<?= htmlspecialchars($am['photo_url']) ?>" class="w-8 h-8 rounded-full object-cover" alt="">
                                    <?php else: ?>
                                        <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 text-xs font-bold">%</div>
                                    <?php endif; ?>
                                    <span class="font-semibold text-gray-800"><?= htmlspecialchars($am['name']) ?></span>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($am['email']) ?></td>
                            <td>
                                <span class="badge badge-purple"><?= htmlspecialchars($am['position_name']) ?></span>
                            </td>
                            <td><?= $am['joined_date'] ? htmlspecialchars($am['joined_date']) : '<span class="text-gray-400">—</span>' ?></td>
                            <td class="text-center">
                                <a href="?committee_id=<?= $committeeId ?>&remove=<?= $am['id'] ?>" onclick="return confirm('Remove <?= htmlspecialchars($am['name']) ?> from this committee?')" class="btn btn-delete btn-sm">
                                    <i data-lucide="user-minus" class="w-3 h-3"></i> Remove
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        </div>
    </div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
