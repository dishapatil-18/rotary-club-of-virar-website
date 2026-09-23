<?php
// ===============================
// Project Report Management (Admin Only)
// ===============================

session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

// Role-based access
$allowedRoles = ['super_admin', 'President', 'Secretary', 'Treasurer'];
if (!isset($_SESSION['admin_role']) || !in_array($_SESSION['admin_role'], $allowedRoles)) {
    header("Location: dashboard.php");
    exit;
}

require __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/audit_log.php';

// Initialize vars
$report_id = $project_id = $year = $summary = $funds_raised = $expenditure = $achievements = "";
$editMode = false;

// ==========================
// EXPORT PROJECT REPORT CSV
// ==========================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {

    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=project_reports.csv");

    $output = fopen("php://output", "w");

    // CSV header row
    fputcsv($output, ["Project Title", "Year", "Funds Raised", "Expenditure", "Achievements", "Created At"]);

    $sql = "
        SELECT pr.year, pr.funds_raised, pr.expenditure, pr.achievements, pr.created_at,
               p.title AS project_title
        FROM project_reports pr
        JOIN projects p ON pr.project_id = p.project_id
        ORDER BY pr.created_at DESC
    ";

    $res = $conn->query($sql);

    while ($row = $res->fetch_assoc()) {
        fputcsv($output, [
            $row['project_title'],
            $row['year'],
            $row['funds_raised'],
            $row['expenditure'],
            $row['achievements'],
            $row['created_at']
        ]);
    }

    fclose($output);
    exit;
}

// =================== ADD / UPDATE REPORT ===================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_report']) || isset($_POST['update_report']))) {
    $project_id   = intval($_POST['project_id']);
    $year         = trim($_POST['year']);
    $summary      = trim($_POST['summary']);
    $funds_raised = trim($_POST['funds_raised']);
    $expenditure  = trim($_POST['expenditure']);
    $achievements = trim($_POST['achievements']);

    // ADD new report
    if (isset($_POST['add_report'])) {
        $stmt = $conn->prepare("INSERT INTO project_reports (project_id, year, summary, funds_raised, expenditure, achievements, created_at) 
                                VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("isssss", $project_id, $year, $summary, $funds_raised, $expenditure, $achievements);
        $stmt->execute();
        $stmt->close();
        logAudit($conn, 'Projects', 'Project Report Added', 'Added project report for project ID ' . $project_id . '.', 'INFO', 'success');
        echo "<script>alert('✅ Project report added successfully!'); window.location.href='project_report_action.php';</script>";
        exit;
    }

    // UPDATE existing report
    if (isset($_POST['update_report'])) {
        $rid = intval($_POST['report_id']);
        $stmt = $conn->prepare("UPDATE project_reports 
                                SET project_id=?, year=?, summary=?, funds_raised=?, expenditure=?, achievements=? 
                                WHERE report_id=?");
        $stmt->bind_param("isssssi", $project_id, $year, $summary, $funds_raised, $expenditure, $achievements, $rid);
        $stmt->execute();
        $stmt->close();
        logAudit($conn, 'Projects', 'Project Report Updated', 'Updated project report ID ' . $rid . '.', 'INFO', 'success');
        echo "<script>alert('✅ Report updated successfully!'); window.location.href='project_report_action.php';</script>";
        exit;
    }
}

// =================== DELETE REPORT ===================
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM project_reports WHERE report_id=?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->close();
    logAudit($conn, 'Projects', 'Project Report Deleted', 'Deleted project report ID ' . $delete_id . '.', 'WARNING', 'success');

    echo "<script>alert('🗑️ Report deleted successfully!'); window.location.href='project_report_action.php';</script>";
    exit;
}

// =================== LOAD REPORT FOR EDIT ===================
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM project_reports WHERE report_id=?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows === 1) {
        $editMode = true;
        $r = $result->fetch_assoc();
        $report_id   = $r['report_id'];
        $project_id  = $r['project_id'];
        $year        = $r['year'];
        $summary     = $r['summary'];
        $funds_raised = $r['funds_raised'];
        $expenditure  = $r['expenditure'];
        $achievements = $r['achievements'];
    }
    $stmt->close();
}

// =================== FETCH PROJECTS ===================
$projects = [];
$res = $conn->query("SELECT project_id, title FROM projects ORDER BY start_date DESC");
if ($res) {
    while ($p = $res->fetch_assoc()) $projects[] = $p;
}

// =================== FETCH ALL REPORTS ===================
$reports = [];
$res = $conn->query("SELECT r.*, p.title AS project_title 
                     FROM project_reports r 
                     JOIN projects p ON r.project_id = p.project_id 
                     ORDER BY r.created_at DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) $reports[] = $row;
}
$pageTitle = 'Project Reports';
$activeNav = 'project-reports';
require __DIR__ . '/includes/admin_head.php';
require __DIR__ . '/includes/admin_header.php';
?>
<div class="card">
    <div class="card-body">
    <form method="POST" enctype="multipart/form-data" class="space-y-4">
        <?php if ($editMode): ?>
            <input type="hidden" name="report_id" value="<?= intval($report_id) ?>">
        <?php endif; ?>

        <div>
            <label class="form-label">Select Project</label>
            <select name="project_id" required class="form-input">
                <option value="">-- Choose Project --</option>
                <?php foreach ($projects as $p): ?>
                    <option value="<?= $p['project_id'] ?>" <?= ($project_id == $p['project_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($p['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label class="form-label">Year</label>
            <input type="text" name="year" required value="<?= htmlspecialchars($year) ?>" class="form-input" placeholder="e.g. 2025-26">
        </div>

        <div>
            <label class="form-label">Summary</label>
            <textarea name="summary" rows="3" required class="form-input"><?= htmlspecialchars($summary) ?></textarea>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="form-label">Funds Raised</label>
                <input type="text" name="funds_raised" value="<?= htmlspecialchars($funds_raised) ?>" class="form-input" placeholder="e.g. ₹50000">
            </div>
            <div>
                <label class="form-label">Expenditure</label>
                <input type="text" name="expenditure" value="<?= htmlspecialchars($expenditure) ?>" class="form-input" placeholder="e.g. ₹45000">
            </div>
        </div>

        <div>
            <label class="form-label">Achievements</label>
            <textarea name="achievements" rows="3" class="form-input"><?= htmlspecialchars($achievements) ?></textarea>
        </div>

        <div class="flex justify-end space-x-3">
            <button onclick="window.location.href = 'dashboard.php'" class="btn btn-secondary">
                <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Dashboard
            </button>
            <a href="project_report_action.php" class="btn btn-secondary">
                <i data-lucide="x" class="w-4 h-4"></i> Cancel
            </a>
            <button type="submit" name="<?= $editMode ? 'update_report' : 'add_report' ?>" class="btn btn-indigo">
                <i data-lucide="save" class="w-4 h-4"></i> <?= $editMode ? 'Update Report' : 'Add Report' ?>
            </button>
        </div>
    </form>
    </div>
</div>

<!-- All Reports -->
<div class="card">
    <div class="card-body">
    <div class="flex justify-between items-center mb-6">
        <div>
            <a href="project_report_action.php?export=csv"
                class="btn btn-yellow">
                <i data-lucide="download" class="w-4 h-4"></i> Export CSV
            </a>
        </div>
    </div>
    <?php if (empty($reports)): ?>
        <p class="text-gray-500">No reports found.</p>
    <?php else: ?>
        <table class="data-table">
            <thead class="bg-gray-100">
                <tr>
                    <th>#</th>
                    <th>Project</th>
                    <th>Year</th>
                    <th>Funds Raised</th>
                    <th>Expenditure</th>
                    <th>Achievements</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reports as $r): ?>
                    <tr class="hover:bg-gray-50">
                        <td><?= $r['report_id'] ?></td>
                        <td><?= htmlspecialchars($r['project_title']) ?></td>
                        <td><?= htmlspecialchars($r['year']) ?></td>
                        <td><?= htmlspecialchars($r['funds_raised']) ?></td>
                        <td><?= htmlspecialchars($r['expenditure']) ?></td>
                        <td><?= htmlspecialchars($r['achievements']) ?></td>
                        <td><?= htmlspecialchars($r['created_at']) ?></td>
                        <td class="flex items-center gap-2 justify-center">
                            <a href="?edit=<?= $r['report_id'] ?>" class="btn btn-yellow btn-xs">
                                <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Edit
                            </a>
                            <a href="?delete=<?= $r['report_id'] ?>" onclick="return confirm('Delete this report?')" class="btn btn-danger btn-xs">
                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Delete
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
