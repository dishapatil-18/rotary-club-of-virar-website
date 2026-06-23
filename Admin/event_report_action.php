<?php
// ============================
// Event Report Management Page
// ============================

session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

require __DIR__ . '/../includes/db_connect.php';

// --- Initialize variables ---
$editMode = false;
$report_id = $event_id = $details = $submitted_by = "";

// =============================
// ======================
// EXPORT EVENT REPORT CSV
// ======================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=event_reports.csv");

    $output = fopen("php://output", "w");

    // CSV Header Row
    fputcsv($output, ["Event", "Submitted By", "Details", "Date"]);

    // Query to fetch event report data
    $sql = "
        SELECT er.*, e.title AS event_title
        FROM event_reports er
        LEFT JOIN events e ON er.event_id = e.event_id
        ORDER BY er.created_at DESC
    ";
    $res = $conn->query($sql);

    while ($row = $res->fetch_assoc()) {
        fputcsv($output, [
            $row['event_title'],
            $row['submitted_by'] ?? 'Admin', // adjust if using member/guest
            $row['details'] ?? '',
            $row['created_at']
        ]);
    }

    fclose($output);
    exit;
}

// ADD / UPDATE REPORT
// =============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $event_id = intval($_POST['event_id'] ?? 0);
    $details = trim($_POST['details'] ?? '');
    $submitted_by = trim($_POST['submitted_by'] ?? '');

    if ($event_id <= 0 || $details === '' || $submitted_by === '') {
        echo "<script>alert('Please fill all fields.'); window.history.back();</script>";
        exit;
    }

    // ADD NEW REPORT
    if (isset($_POST['add_report'])) {
        $stmt = $conn->prepare("
            INSERT INTO event_reports (event_id, details, submitted_by, date) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->bind_param("iss", $event_id, $details, $submitted_by);
        $stmt->execute();
        $stmt->close();
        echo "<script>alert('✅ Report added successfully!'); window.location.href='event_report_action.php';</script>";
        exit;
    }

    // UPDATE EXISTING REPORT
    if (isset($_POST['update_report']) && isset($_POST['report_id'])) {
        $rid = intval($_POST['report_id']);

        $stmt = $conn->prepare("
            UPDATE event_reports 
            SET event_id=?, details=?, submitted_by=? 
            WHERE report_id=?
        ");
        $stmt->bind_param("issi", $event_id, $details, $submitted_by, $rid);
        $stmt->execute();
        $stmt->close();
        echo "<script>alert('✅ Report updated successfully!'); window.location.href='event_report_action.php';</script>";
        exit;
    }
}

// =============================
// DELETE REPORT
// =============================
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM event_reports WHERE report_id=?");
    $stmt->bind_param("i", $del_id);
    $stmt->execute();
    $stmt->close();

    echo "<script>alert('🗑️ Report deleted successfully!'); window.location.href='event_report_action.php';</script>";
    exit;
}

// =============================
// EDIT REPORT (LOAD DATA)
// =============================
if (isset($_GET['edit'])) {

    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM event_reports WHERE report_id=?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();

    $res = $stmt->get_result();
    if ($res && $res->num_rows === 1) {
        $editMode = true;
        $row = $res->fetch_assoc();

        $report_id = $row['report_id'];
        $event_id = $row['event_id'];
        $details = $row['details'];
        $submitted_by = $row['submitted_by'];
    }
    $stmt->close();
}

// =============================
// FETCH EVENTS (dropdown)
// =============================
$events = [];
$res = $conn->query("SELECT event_id, title, start_date FROM events ORDER BY start_date DESC");
if ($res) {
    while ($r = $res->fetch_assoc()) $events[] = $r;
}

// =============================
// FETCH ALL REPORTS
// =============================
$reports = [];
$res2 = $conn->query("
    SELECT er.report_id,
           er.details,
           er.submitted_by,
           er.date AS report_date,
           e.title AS event_title,
           e.start_date
    FROM event_reports er
    LEFT JOIN events e ON er.event_id = e.event_id
    ORDER BY er.date DESC
");

if ($res2) {
    while ($r = $res2->fetch_assoc()) $reports[] = $r;
}
?>
<?php
$pageTitle = 'Event Reports';
$activeNav = 'event-reports';
require __DIR__ . '/includes/admin_head.php';
require __DIR__ . '/includes/admin_header.php';
?>

<div class="card">
    <div class="card-body">
        <h3 class="text-lg font-bold text-gray-800 mb-4"><?= $editMode ? 'Edit Event Report' : 'Add New Event Report' ?></h3>

        <form method="POST" class="space-y-4">
            <?php if ($editMode): ?>
                <input type="hidden" name="report_id" value="<?= $report_id ?>">
            <?php endif; ?>

            <div>
                <label class="form-label">Select Event</label>
                <select name="event_id" required class="form-input">
                    <option value="">Select Event</option>
                    <?php foreach ($events as $ev): ?>
                        <option value="<?= $ev['event_id'] ?>" <?= $event_id == $ev['event_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($ev['title']) ?> (<?= htmlspecialchars($ev['start_date']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="form-label">Submitted By</label>
                <input type="text" name="submitted_by" required value="<?= htmlspecialchars($submitted_by) ?>" class="form-input">
            </div>

            <div>
                <label class="form-label">Details</label>
                <textarea name="details" rows="4" required class="form-input"><?= htmlspecialchars($details) ?></textarea>
            </div>

            <div class="flex justify-end space-x-3">
                <button onclick="window.location.href = 'dashboard.php'" class="btn btn-ghost btn-sm">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Dashboard
                </button>
                <a href="event_report_action.php" class="btn btn-ghost btn-sm">Cancel</a>
                <button type="submit" name="<?= $editMode ? 'update_report' : 'add_report' ?>" class="btn btn-primary btn-sm">
                    <?= $editMode ? 'Update Report' : 'Add Report' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- All Reports List -->
<div class="card mt-6">
    <div class="card-body">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-bold text-gray-800">Event Reports</h3>
            <a href="event_report_action.php?export=csv" class="btn btn-yellow btn-sm">
                <i data-lucide="download" class="w-4 h-4"></i> Export CSV
            </a>
        </div>

        <?php if (empty($reports)): ?>
            <p class="text-gray-500">No reports available.</p>
        <?php else: ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>  </th>
                        <th>Event</th>
                        <th>Submitted By</th>
                        <th>Details</th>
                        <th>Date</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($reports as $r): ?>
                        <tr>
                            <td class="text-center"><?= $r['report_id'] ?></td>
                            <td><?= htmlspecialchars($r['event_title'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($r['submitted_by']) ?></td>
                            <td><?= htmlspecialchars($r['details']) ?></td>
                            <td><?= htmlspecialchars($r['report_date']) ?></td>

                            <td class="text-center">
                                <a href="?edit=<?= $r['report_id'] ?>" class="btn btn-yellow btn-xs"><i data-lucide="edit" class="w-3 h-3"></i> Edit</a>
                                <a href="?delete=<?= $r['report_id'] ?>" onclick="return confirm('Delete this report?')" class="btn btn-danger btn-xs"><i data-lucide="trash-2" class="w-3 h-3"></i> Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
