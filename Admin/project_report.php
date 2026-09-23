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

// Initialize vars
$report_id = $project_id = $year = $summary = $funds_raised = $expenditure = $achievements = "";
$editMode = false;


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
        <div class="flex justify-between items-center mb-6">
            <a href="project_report_action.php?export=csv" class="btn btn-yellow">
                <i data-lucide="download" class="w-4 h-4"></i> Export CSV
            </a>
        </div>
    <?php if (empty($reports)): ?>
        <p class="text-gray-500">No reports found.</p>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th> </th>
                    <th>Project</th>
                    <th>Year</th>
                    <th>Funds Raised</th>
                    <th>Expenditure</th>
                    <th>Achievements</th>
                    <th>Created At</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reports as $r): ?>
                    <tr>
                        <td class="text-center"><?= $r['report_id'] ?></td>
                        <td><?= htmlspecialchars($r['project_title']) ?></td>
                        <td><?= htmlspecialchars($r['year']) ?></td>
                        <td><?= htmlspecialchars($r['funds_raised']) ?></td>
                        <td><?= htmlspecialchars($r['expenditure']) ?></td>
                        <td><?= htmlspecialchars($r['achievements']) ?></td>
                        <td><?= htmlspecialchars($r['created_at']) ?></td>
                        <td class="text-center flex gap-2 justify-center">
                            <a href="?edit=<?= $r['report_id'] ?>" class="btn btn-edit"><i data-lucide="pencil" class="w-4 h-4"></i>Edit</a>
                            <a href="?delete=<?= $r['report_id'] ?>" onclick="return confirm('Delete this report?')" class="btn btn-delete"><i data-lucide="trash-2" class="w-4 h-4"></i>Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>