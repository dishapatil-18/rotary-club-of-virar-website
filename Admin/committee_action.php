<?php
// ===============================
// Committee Management (Admin Only)
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

// Fetch rotary years for dropdown
$allYears = [];
$yrRes = $conn->query("SELECT id, year_name, is_current FROM rotary_years ORDER BY year_name DESC");
if ($yrRes) {
    while ($y = $yrRes->fetch_assoc()) $allYears[] = $y;
}

// Initialize variables
$editMode = false;
$committee_id = $committee_name = $description = $year_id = $is_active = "";

// ======================
// ADD / EDIT FORM SUBMIT
// ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $committee_name = trim($_POST['committee_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $year_id = !empty($_POST['year_id']) ? intval($_POST['year_id']) : null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Validation
    if ($committee_name === '') {
        echo "<script>alert('Please provide a committee name.'); window.history.back();</script>";
        exit;
    }
    if ($year_id === null) {
        echo "<script>alert('Please select a Rotary Year.'); window.history.back();</script>";
        exit;
    }

    // Check duplicate name within same year
    $checkSql = "SELECT COUNT(*) AS cnt FROM committees WHERE committee_name = ? AND year_id = ?";
    if ($editMode) {
        $checkSql .= " AND committee_id != ?";
    }
    $stmt = $conn->prepare($checkSql);
    if ($editMode) {
        $stmt->bind_param("sii", $committee_name, $year_id, $committee_id);
    } else {
        $stmt->bind_param("si", $committee_name, $year_id);
    }
    $stmt->execute();
    $chk = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($chk['cnt'] > 0) {
        echo "<script>alert('A committee with this name already exists for the selected year.'); window.history.back();</script>";
        exit;
    }

    // INSERT new committee
    if (isset($_POST['add_committee'])) {
        $stmt = $conn->prepare("INSERT INTO committees (committee_name, description, year_id, is_active) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssii", $committee_name, $description, $year_id, $is_active);
        if ($stmt->execute()) {
            $newId = $stmt->insert_id;
            $stmt->close();
            logAudit($conn, 'Committees', 'Committee Added', 'Added committee "' . $committee_name . '".', 'INFO', 'success');
            echo "<script>alert('✅ Committee added successfully!'); window.location.href='committee_action.php';</script>";
            exit;
        } else {
            $err = $stmt->error;
            $stmt->close();
            echo "<script>alert('DB error: " . addslashes($err) . "'); window.history.back();</script>";
            exit;
        }
    }

    // UPDATE existing committee
    if (isset($_POST['update_committee']) && isset($_POST['committee_id'])) {
        $committee_id = intval($_POST['committee_id']);
        $stmt = $conn->prepare("UPDATE committees SET committee_name=?, description=?, year_id=?, is_active=? WHERE committee_id=?");
        $stmt->bind_param("ssiii", $committee_name, $description, $year_id, $is_active, $committee_id);
        if ($stmt->execute()) {
            $stmt->close();
            logAudit($conn, 'Committees', 'Committee Updated', 'Updated committee ID ' . $committee_id . ' ("' . $committee_name . '").', 'INFO', 'success');
            echo "<script>alert('✅ Committee updated successfully!'); window.location.href='committee_action.php';</script>";
            exit;
        } else {
            $err = $stmt->error;
            $stmt->close();
            echo "<script>alert('DB error: " . addslashes($err) . "'); window.history.back();</script>";
            exit;
        }
    }
}

// ===================
// DELETE COMMITTEE
// ===================
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);

    // Check if committee has members
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM committee_members WHERE committee_id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $cnt = $stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();

    if ($cnt > 0) {
        echo "<script>alert('Cannot delete: this committee still has " . intval($cnt) . " member(s). Remove all members first.'); window.location.href='committee_action.php';</script>";
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM committees WHERE committee_id=?");
    $stmt->bind_param("i", $delete_id);
    if ($stmt->execute()) {
        $stmt->close();
        logAudit($conn, 'Committees', 'Committee Deleted', 'Deleted committee ID ' . $delete_id . '.', 'WARNING', 'success');
        echo "<script>alert('🗑️ Committee deleted successfully!'); window.location.href='committee_action.php';</script>";
        exit;
    } else {
        $err = $stmt->error;
        $stmt->close();
        echo "<script>alert('DB error: " . addslashes($err) . "'); window.history.back();</script>";
        exit;
    }
}

// ===================
// EDIT COMMITTEE (LOAD DATA)
// ===================
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM committees WHERE committee_id=?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows === 1) {
        $editMode = true;
        $row = $result->fetch_assoc();
        $committee_id = $row['committee_id'];
        $committee_name = $row['committee_name'];
        $description = $row['description'];
        $year_id = $row['year_id'];
        $is_active = $row['is_active'];
    }
    $stmt->close();
}

// ===================
// FETCH ALL COMMITTEES
// ===================
$filterYear = isset($_GET['year_id']) ? intval($_GET['year_id']) : 0;
$filterStatus = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

$sql = "SELECT c.*, ry.year_name,
        (SELECT COUNT(*) FROM committee_members cm WHERE cm.committee_id = c.committee_id) AS member_count
        FROM committees c
        LEFT JOIN rotary_years ry ON c.year_id = ry.id
        WHERE 1=1";

$params = [];
$types = '';

if ($filterYear > 0) {
    $sql .= " AND c.year_id = ?";
    $params[] = $filterYear;
    $types .= 'i';
}
if ($filterStatus === 'active') {
    $sql .= " AND c.is_active = 1";
} elseif ($filterStatus === 'inactive') {
    $sql .= " AND c.is_active = 0";
}
if ($search !== '') {
    $sql .= " AND c.committee_name LIKE ?";
    $params[] = '%' . $search . '%';
    $types .= 's';
}

$sql .= " ORDER BY ry.year_name DESC, c.committee_name ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();
$committees = [];
while ($row = $res->fetch_assoc()) $committees[] = $row;
$stmt->close();
?>

<?php
$pageTitle = 'Manage Committees';
$activeNav = 'committees';
require __DIR__ . '/includes/admin_head.php';
require __DIR__ . '/includes/admin_header.php';
?>
    <!-- Committee Form Card -->
    <div class="card">
        <div class="card-body">
            <h2 class="text-xl font-bold text-gray-800 mb-6"><?= $editMode ? 'Edit Committee' : 'Add New Committee' ?></h2>

        <form method="POST" class="space-y-4">
            <?php if ($editMode): ?>
                <input type="hidden" name="committee_id" value="<?= $committee_id ?>">
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Committee Name</label>
                    <input type="text" name="committee_name" required value="<?= htmlspecialchars($committee_name) ?>" class="form-input" placeholder="e.g. Club Service Committee">
                </div>
                <div>
                    <label class="form-label">Rotary Year</label>
                    <select name="year_id" required class="form-select">
                        <option value="">-- Select Year --</option>
                        <?php foreach ($allYears as $y): ?>
                        <option value="<?= $y['id'] ?>" <?= ($year_id == $y['id'] || (empty($year_id) && $y['is_current'])) ? 'selected' : '' ?>><?= htmlspecialchars($y['year_name']) ?> <?= $y['is_current'] ? '(Current)' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="form-label">Description (Optional)</label>
                <textarea name="description" rows="2" class="form-textarea" placeholder="Brief description of this committee's purpose"><?= htmlspecialchars($description) ?></textarea>
            </div>

            <div class="flex items-center gap-3">
                <label class="form-label" style="margin-bottom:0;">Active</label>
                <input type="checkbox" name="is_active" value="1" <?= $is_active === "" || $is_active ? 'checked' : '' ?> class="w-4 h-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <span class="text-xs text-gray-500">Inactive committees are hidden from member assignment</span>
            </div>

            <div class="flex justify-end space-x-3">
                <a href="committee_action.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" name="<?= $editMode ? 'update_committee' : 'add_committee' ?>" class="btn btn-primary">
                    <i data-lucide="<?= $editMode ? 'save' : 'plus' ?>" class="w-4 h-4"></i> <?= $editMode ? 'Update Committee' : 'Add Committee' ?>
                </button>
            </div>
        </form>
        </div>
    </div>

    <!-- All Committees List -->
    <div class="card">
        <div class="card-body">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-gray-800">All Committees</h2>
                <span class="text-sm text-gray-500"><?= count($committees) ?> committee<?= count($committees) !== 1 ? 's' : '' ?></span>
            </div>

            <!-- Filters -->
            <form method="GET" class="flex flex-wrap gap-3 mb-4">
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search committees..." class="form-input" style="max-width:220px;">
                <select name="year_id" class="form-select" style="max-width:180px;">
                    <option value="0">All Years</option>
                    <?php foreach ($allYears as $y): ?>
                    <option value="<?= $y['id'] ?>" <?= $filterYear == $y['id'] ? 'selected' : '' ?>><?= htmlspecialchars($y['year_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="status" class="form-select" style="max-width:150px;">
                    <option value="">All Status</option>
                    <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $filterStatus === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
                <button type="submit" class="btn btn-secondary btn-sm"><i data-lucide="search" class="w-3 h-3"></i> Filter</button>
                <?php if ($search || $filterYear || $filterStatus): ?>
                    <a href="committee_action.php" class="btn btn-ghost btn-sm"><i data-lucide="x" class="w-3 h-3"></i> Clear</a>
                <?php endif; ?>
            </form>

        <?php if (empty($committees)): ?>
            <div class="empty-state">
                <i data-lucide="users-round"></i>
                <h3>No committees found</h3>
                <p><?= $search || $filterYear || $filterStatus ? 'Try adjusting your filters.' : 'Create your first committee above.' ?></p>
            </div>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th class="text-left">Committee Name</th>
                        <th class="text-left">Year</th>
                        <th class="text-center">Members</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($committees as $c): ?>
                        <tr>
                            <td class="text-center"><?= $c['committee_id'] ?></td>
                            <td>
                                <div class="font-semibold text-gray-800"><?= htmlspecialchars($c['committee_name']) ?></div>
                                <?php if ($c['description']): ?>
                                    <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars(mb_strimwidth($c['description'], 0, 80, '...')) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($c['year_name'] ?? '-') ?></td>
                            <td class="text-center">
                                <a href="committee_members.php?committee_id=<?= $c['committee_id'] ?>" class="badge badge-blue" style="text-decoration:none;cursor:pointer;">
                                    <?= intval($c['member_count']) ?> member<?= intval($c['member_count']) !== 1 ? 's' : '' ?>
                                </a>
                            </td>
                            <td class="text-center">
                                <?php if ($c['is_active']): ?>
                                    <span class="badge badge-green">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-gray">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center space-x-2">
                                <a href="committee_members.php?committee_id=<?= $c['committee_id'] ?>" class="btn btn-indigo btn-sm" title="Manage Members"><i data-lucide="user-plus" class="w-3 h-3"></i> Members</a>
                                <a href="?edit=<?= $c['committee_id'] ?>" class="btn btn-edit btn-sm"><i data-lucide="edit" class="w-3 h-3"></i> Edit</a>
                                <a href="?delete=<?= $c['committee_id'] ?>" onclick="return confirm('Delete this committee? This is only possible if it has no members.')" class="btn btn-delete btn-sm"><i data-lucide="trash-2" class="w-3 h-3"></i> Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        </div>
    </div>
<?php require __DIR__ . '/includes/admin_footer.php'; ?>
