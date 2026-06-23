<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

require __DIR__ . '/../includes/db_connect.php';

/* ============================================
   COLLABORATION REPORT CRUD + CSV EXPORT
   Using your original UI theme
   ============================================ */

// ----------------------------
// DELETE REPORT
// ----------------------------
if (isset($_GET['delete'])) {
    $rid = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM collaboration_reports WHERE report_id = ?");
    $stmt->bind_param("i", $rid);
    $stmt->execute();
    header("Location: collaboration_report.php");
    exit;
}

// ----------------------------
// EXPORT CSV
// ----------------------------
if (isset($_GET['export'])) {
    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=collaboration_reports.csv");

    $output = fopen("php://output", "w");
    fputcsv($output, ["Report ID", "Collab ID", "Name", "Email", "Proposal", "Report Summary", "Submitted At", "Reported On"]);

    $sql = "
        SELECT cr.*, c.name, c.email, c.proposal, c.submitted_at 
        FROM collaboration_reports cr 
        JOIN collaborations c ON cr.collab_id = c.collab_id
        ORDER BY cr.created_on DESC
    ";
    $res = $conn->query($sql);

    while ($row = $res->fetch_assoc()) {
        fputcsv($output, [
            $row['report_id'], $row['collab_id'], $row['name'], $row['email'],
            $row['proposal'], $row['report_summary'],
            $row['submitted_at'], $row['created_on']
        ]);
    }

    fclose($output);
    exit;
}

// ----------------------------
// ADD REPORT
// ----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_report'])) {
    $collab_id = intval($_POST['collab_id']);
    $summary = trim($_POST['report_summary']);

    if ($collab_id > 0 && $summary !== "") {
        $stmt = $conn->prepare("INSERT INTO collaboration_reports (collab_id, report_summary) VALUES (?, ?)");
        $stmt->bind_param("is", $collab_id, $summary);
        $stmt->execute();
    }

    header("Location: collaboration_report.php");
    exit;
}

// ----------------------------
// EDIT REPORT
// ----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_report'])) {
    $rid = intval($_POST['report_id']);
    $summary = trim($_POST['report_summary']);

    $stmt = $conn->prepare("UPDATE collaboration_reports SET report_summary = ? WHERE report_id = ?");
    $stmt->bind_param("si", $summary, $rid);
    $stmt->execute();

    header("Location: collaboration_report.php");
    exit;
}

// ----------------------------
// FETCH DATA (reports + collab)
// ----------------------------
$reports = [];
$r = $conn->query("
    SELECT cr.*, c.name, c.email, c.proposal, c.submitted_at 
    FROM collaboration_reports cr 
    JOIN collaborations c ON cr.collab_id = c.collab_id
    ORDER BY cr.created_on DESC
");

while ($row = $r->fetch_assoc()) {
    $reports[] = $row;
}

// Collab list for dropdown
$collabs = $conn->query("SELECT * FROM collaborations ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Collaboration Reports';
$activeNav = 'collaboration-reports';
require __DIR__ . '/includes/admin_head.php';
require __DIR__ . '/includes/admin_header.php';
?>
<div class="card"><div class="card-body">
    
    <div class="flex justify-end mb-6">
        <a href="collaboration_report.php?export=1"
           class="btn btn-yellow">
           <i data-lucide="download" class="w-4 h-4"></i> Export CSV
        </a>
    </div>

    <!-- ADD REPORT FORM -->
    <div class="bg-indigo-50 p-4 rounded-lg mb-6">
        <form method="POST">
            <h2 class="text-xl font-bold text-indigo-600 mb-3">Add New Report</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <select name="collab_id" class="form-select" required>
                    <option value="">Select Collaboration</option>
                    <?php foreach ($collabs as $c): ?>
                        <option value="<?= $c['collab_id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>

                <textarea name="report_summary" class="form-input form-textarea" placeholder="Enter Report Summary"
                          required></textarea>
            </div>

            <button name="add_report"
                class="btn btn-indigo mt-3">
                <i data-lucide="plus" class="w-4 h-4"></i> Add Report
            </button>
        </form>
    </div>

    <!-- REPORT TABLE -->
    <table class="data-table">
        <thead>
            <tr>
                <th class="text-left">ID</th>
                <th class="text-left">Collab Name</th>
                <th class="text-left">Email</th>
                <th class="text-left">Proposal</th>
                <th class="text-left">Report Summary</th>
                <th class="text-left">Report Date</th>
                <th class="text-center">Actions</th>
            </tr>
        </thead>

        <tbody>
        <?php foreach ($reports as $r): ?>
            <tr>
                <td class="text-gray-600"><?= $r['report_id'] ?></td>
                <td class="text-gray-600"><?= htmlspecialchars($r['name']) ?></td>
                <td class="text-gray-600"><?= htmlspecialchars($r['email']) ?></td>
                <td class="text-gray-600"><?= htmlspecialchars($r['proposal']) ?></td>
                <td class="text-gray-600"><?= htmlspecialchars($r['report_summary']) ?></td>
                <td class="text-gray-600"><?= $r['created_on'] ?></td>

                <td class="text-center">
                    <!-- EDIT -->
                    <button onclick="openEdit(<?= $r['report_id'] ?>, `<?= htmlspecialchars($r['report_summary'], ENT_QUOTES) ?>`)"
                        class="btn btn-primary btn-sm">
                        <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Edit
                    </button>

                    <!-- DELETE -->
                    <a href="?delete=<?= $r['report_id'] ?>"
                       onclick="return confirm('Delete this report?')"
                       class="btn btn-delete btn-sm">
                       <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Delete
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfooter>
                <tr>
                    <td colspan="8" class="text-right text-gray-500 italic">
                    <div class="flex justify-end space-x-3">
                        <button onclick="window.location.href = 'dashboard.php'" class="btn btn-secondary">
                            <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Dashboard
                        </button>
                    </div>
                    </td>
                </tr>
        </tfooter>
    </table>

    </div>
</div>


<!-- EDIT MODAL -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center p-4 z-50">
    <div class="bg-white p-6 rounded-xl shadow-xl max-w-lg w-full relative">
        <button onclick="closeEdit()" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600 transition-colors duration-200"><i data-lucide="x" class="w-5 h-5"></i></button>

        <h2 class="text-2xl font-bold mb-4 text-indigo-600">Edit Report</h2>

        <form method="POST">
            <input type="hidden" name="report_id" id="edit_report_id">

            <textarea name="report_summary" id="edit_report_summary"
                      class="form-input form-textarea"></textarea>

            <button name="edit_report"
                class="btn btn-indigo mt-4">
                <i data-lucide="check" class="w-4 h-4"></i> Save Changes
            </button>
        </form>
    </div>
</div>

<script>
function openEdit(id, summary) {
    document.getElementById("edit_report_id").value = id;
    document.getElementById("edit_report_summary").value = summary;
    document.getElementById("editModal").classList.remove("hidden");
    document.getElementById("editModal").classList.add("flex");
}

function closeEdit() {
    document.getElementById("editModal").classList.add("hidden");
    document.getElementById("editModal").classList.remove("flex");
}
</script>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
