<?php
//-----------------------------------------------
// donation_report_action.php
// Manage Donation Reports (Add / Edit / Delete / Export CSV)
//-----------------------------------------------

session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

require __DIR__ . '/../includes/db_connect.php';

// Helper: sanitize output
function h($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// Handle Export CSV first (so it does not render HTML)
if (isset($_GET['export']) && $_GET['export'] == '1') {
    // Fetch joined rows
    $sql = "
        SELECT dr.report_id, dr.donation_id, d.donation_type, d.amount, d.date AS donation_date,
               dn.name AS donor_name, dr.report_summary, dr.verified_by, dr.created_on
        FROM donation_report dr
        JOIN donations d ON dr.donation_id = d.donation_id
        JOIN donors dn ON d.donor_id = dn.donor_id
        ORDER BY dr.created_on DESC
    ";
    $res = $conn->query($sql);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=donation_reports_' . date('Ymd_His') . '.csv');

    $out = fopen('php://output', 'w');
    // CSV header
    fputcsv($out, ['Report ID','Donation ID','Donor','Donation Type','Amount (INR)','Donation Date','Report Summary','Verified By','Report Created On']);

    while ($row = $res->fetch_assoc()) {
        fputcsv($out, [
            $row['report_id'],
            $row['donation_id'],
            $row['donor_name'],
            $row['donation_type'],
            number_format((float)$row['amount'], 2, '.', ''),
            $row['donation_date'],
            $row['report_summary'],
            $row['verified_by'],
            $row['created_on']
        ]);
    }
    fclose($out);
    exit;
}

// Default variables for form
$editingReport = null;
$error = '';
$success = '';

// --------------------
// Add / Update Report
// --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_report'])) {
    $report_id      = intval($_POST['report_id'] ?? 0);
    $donation_id    = intval($_POST['donation_id'] ?? 0);
    $report_summary = trim($_POST['report_summary'] ?? '');
    $verified_by    = trim($_POST['verified_by'] ?? '');

    // Basic validation
    if ($donation_id <= 0) {
        $error = "Please select a donation.";
    } elseif ($report_summary === '') {
        $error = "Please provide a report summary.";
    } elseif ($verified_by === '') {
        $error = "Please enter the name of the verifier.";
    } else {
        if ($report_id === 0) {
            // Insert
            $stmt = $conn->prepare("INSERT INTO donation_report (donation_id, report_summary, verified_by, created_on) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("iss", $donation_id, $report_summary, $verified_by);
            if ($stmt->execute()) {
                $success = "Donation report saved successfully.";
                header("Location: donation_report_action.php?msg=" . urlencode($success));
                exit;
            } else {
                $error = "Insert failed: " . $stmt->error;
            }
            $stmt->close();
        } else {
            // Update
            $stmt = $conn->prepare("UPDATE donation_report SET donation_id = ?, report_summary = ?, verified_by = ? WHERE report_id = ?");
            $stmt->bind_param("issi", $donation_id, $report_summary, $verified_by, $report_id);
            if ($stmt->execute()) {
                $success = "Donation report updated.";
                header("Location: donation_report_action.php?msg=" . urlencode($success));
                exit;
            } else {
                $error = "Update failed: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}

// --------------------
// Delete Report
// --------------------
if (isset($_GET['delete_report'])) {
    $rid = intval($_GET['delete_report']);
    if ($rid > 0) {
        $stmt = $conn->prepare("DELETE FROM donation_report WHERE report_id = ?");
        $stmt->bind_param("i", $rid);
        $stmt->execute();
        $stmt->close();
        header("Location: donation_report_action.php?msg=" . urlencode("Report deleted"));
        exit;
    }
}

// --------------------
// Edit Report - load data
// --------------------
if (isset($_GET['edit_report'])) {
    $rid = intval($_GET['edit_report']);
    if ($rid > 0) {
        $stmt = $conn->prepare("SELECT * FROM donation_report WHERE report_id = ?");
        $stmt->bind_param("i", $rid);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($r = $res->fetch_assoc()) {
            $editingReport = $r;
        }
        $stmt->close();
    }
}

// --------------------
// Fetch donations list (for dropdown) in Option A format: 
// "Donation #5 — A. Kulkarni — Money (₹2500)"
// --------------------
$donationsForSelect = [];
$sql = "
    SELECT d.donation_id, d.donation_type, d.amount, dn.name AS donor_name
    FROM donations d
    JOIN donors dn ON d.donor_id = dn.donor_id
    ORDER BY d.date DESC
";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    $donationsForSelect[] = $row;
}

// --------------------
// Fetch joined report rows for display table
// --------------------
$reportRows = [];
$sql = "
    SELECT dr.report_id, dr.donation_id, dr.report_summary, dr.verified_by, dr.created_on,
           d.donation_type, d.amount, d.date AS donation_date,
           dn.name AS donor_name, dn.phone_number, dn.email
    FROM donation_report dr
    JOIN donations d ON dr.donation_id = d.donation_id
    JOIN donors dn ON d.donor_id = dn.donor_id
    ORDER BY dr.created_on DESC
";
$res = $conn->query($sql);
while ($r = $res->fetch_assoc()) $reportRows[] = $r;

// Show messages if any
if (isset($_GET['msg'])) $success = $_GET['msg'];

$pageTitle = 'Donation Reports';
$activeNav = 'donation-reports';
require __DIR__ . '/includes/admin_head.php';
require __DIR__ . '/includes/admin_header.php';
?>
    <div class="card">
        <div class="card-body">
            <div class="flex justify-end mb-4">
                <a href="?export=1" class="btn btn-primary" title="Export CSV">
                    <i data-lucide="download" class="w-4 h-4"></i>
                    Export CSV
                </a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= h($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= h($success) ?></div>
            <?php endif; ?>

            <!-- Form -->
            <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <input type="hidden" name="report_id" value="<?= (int)($editingReport['report_id'] ?? 0) ?>">

                <div class="md:col-span-1">
                    <label class="form-label">Select Donation</label>
                    <select name="donation_id" required class="form-input">
                        <option value="">— Select Donation —</option>
                        <?php foreach ($donationsForSelect as $d): 
                            $optLabel = "Donation #{$d['donation_id']} — " . $d['donor_name'] . " — " . $d['donation_type'];
                            $amt = ($d['amount'] !== null && $d['amount'] !== '') ? " (₹" . number_format((float)$d['amount'],2) . ")" : "";
                            $optLabelFull = $optLabel . $amt;
                        ?>
                            <option value="<?= (int)$d['donation_id'] ?>"
                                <?= (isset($editingReport) && $editingReport['donation_id']==$d['donation_id']) ? 'selected' : '' ?>>
                                <?= h($optLabelFull) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="md:col-span-1">
                    <label class="form-label">Verified By</label>
                    <input type="text" name="verified_by" required class="form-input"
                           value="<?= h($editingReport['verified_by'] ?? ($_SESSION['admin_name'] ?? '')) ?>">
                </div>

                <div class="md:col-span-1">
                    <label class="form-label">Report Summary</label>
                    <textarea name="report_summary" rows="3" class="form-input"><?= h($editingReport['report_summary'] ?? '') ?></textarea>
                </div>

                <div class="md:col-span-3 flex justify-end space-x-3 mt-2">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        Back to Dashboard
                    </a>
                    <a href="donation_report_action.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" name="save_report" class="btn btn-primary">Save Report</button>
                </div>
            </form>

        </div>
    </div>

    <div class="card">
        <div class="card-body">

            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr class="text-left">
                            <th>ID</th>
                            <th>Donor</th>
                            <th>Type</th>
                            <th>Amount (₹)</th>
                            <th>Report Summary</th>
                            <th>Verified By</th>
                            <th>Report Created On</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($reportRows) === 0): ?>
                            <tr><td class="p-4 text-center" colspan="8">No reports found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($reportRows as $row): ?>
                                <tr class="align-top">
                                    <td><?= (int)$row['report_id'] ?></td>

                                    <!-- HIDDEN donation ID — kept only for reference -->
                                    <td class="hidden"><?= (int)$row['donation_id'] ?></td>

                                    <td><?= h($row['donor_name']) ?></td>
                                    <td><?= h($row['donation_type']) ?></td>
                                    <td>₹ <?= number_format((float)$row['amount'], 2) ?></td>
                                    <td><?= nl2br(h($row['report_summary'])) ?></td>
                                    <td><?= h($row['verified_by']) ?></td>
                                    <td><?= h($row['created_on']) ?></td>

                                    <td class="text-center">
                                        <a href="?edit_report=<?= (int)$row['report_id'] ?>" class="btn btn-yellow btn-xs">Edit</a>
                                        <a href="?delete_report=<?= (int)$row['report_id'] ?>" onclick="return confirm('Delete this report?')" class="btn btn-danger btn-xs">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
