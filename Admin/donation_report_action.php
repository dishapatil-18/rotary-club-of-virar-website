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

// Role-based access: only Super Admin, President, Secretary, Treasurer
$allowedRoles = ['super_admin', 'President', 'Secretary', 'Treasurer'];
if (!isset($_SESSION['admin_role']) || !in_array($_SESSION['admin_role'], $allowedRoles)) {
    header("Location: dashboard.php");
    exit;
}

require __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/audit_log.php';

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
                logAudit($conn, 'Donations', 'Donation Report Added', 'Added donation report for donation #' . $donation_id . '.', 'INFO', 'success');
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
                logAudit($conn, 'Donations', 'Donation Report Updated', 'Updated donation report ID ' . $report_id . '.', 'INFO', 'success');
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
        logAudit($conn, 'Donations', 'Donation Report Deleted', 'Deleted donation report ID ' . $rid . '.', 'WARNING', 'success');
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
           d.donation_type, d.amount, d.date AS donation_date, d.status AS current_status,
           d.status_updated_by, d.status_updated_role, d.status_updated_at,
           dn.name AS donor_name, dn.phone_number, dn.email
    FROM donation_report dr
    JOIN donations d ON dr.donation_id = d.donation_id
    JOIN donors dn ON d.donor_id = dn.donor_id
    ORDER BY dr.created_on DESC
";
$res = $conn->query($sql);
while ($r = $res->fetch_assoc()) $reportRows[] = $r;

// Fetch all status history for displayed donations
$historyByDonation = [];
if (count($reportRows) > 0) {
    $donationIds = array_unique(array_column($reportRows, 'donation_id'));
    $idPlaceholders = implode(',', array_fill(0, count($donationIds), '?'));
    $types = str_repeat('i', count($donationIds));
    $hstmt = $conn->prepare("SELECT h.*, a.name AS admin_display_name 
                             FROM donation_status_history h 
                             LEFT JOIN admins a ON h.admin_id = a.admin_id 
                             WHERE h.donation_id IN ($idPlaceholders) 
                             ORDER BY h.updated_at ASC");
    $hstmt->bind_param($types, ...$donationIds);
    $hstmt->execute();
    $hRes = $hstmt->get_result();
    while ($hRow = $hRes->fetch_assoc()) {
        $did = $hRow['donation_id'];
        if (!isset($historyByDonation[$did])) $historyByDonation[$did] = [];
        $historyByDonation[$did][] = $hRow;
    }
    $hstmt->close();
}

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
                <table class="data-table" id="report-table">
                    <thead>
                        <tr class="text-left">
                            <th>ID</th>
                            <th>Donor</th>
                            <th>Type</th>
                            <th>Amount (₹)</th>
                            <th>Current Status</th>
                            <th>Updated By</th>
                            <th>Role</th>
                            <th>Updated On</th>
                            <th>Report Summary</th>
                            <th>Verified By</th>
                            <th>Report Created On</th>
                            <th class="text-center">Status History</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($reportRows) === 0): ?>
                            <tr><td class="p-4 text-center" colspan="13">No reports found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($reportRows as $row): 
                                $did = (int)$row['donation_id'];
                                $hist = $historyByDonation[$did] ?? [];
                            ?>
                                <tr class="align-top" data-donation-id="<?= $did ?>">
                                    <td><?= (int)$row['report_id'] ?></td>

                                    <!-- HIDDEN donation ID — kept only for reference -->
                                    <td class="hidden"><?= $did ?></td>

                                    <td><?= h($row['donor_name']) ?></td>
                                    <td><?= h($row['donation_type']) ?></td>
                                    <td>₹ <?= number_format((float)$row['amount'], 2) ?></td>
                                    <td>
                                        <?php
                                        $sc = 'badge-gray';
                                        $s = $row['current_status'] ?? '';
                                        if ($s === 'Pending Verification') $sc = 'badge-yellow';
                                        elseif ($s === 'Verified') $sc = 'badge-green';
                                        elseif ($s === 'Contacted') $sc = 'badge-blue';
                                        elseif ($s === 'Received') $sc = 'badge-purple';
                                        elseif ($s === 'Completed') $sc = 'badge-green';
                                        ?>
                                        <span class="badge <?= $sc ?>"><?= h($s) ?></span>
                                    </td>
                                    <td><?= h($row['status_updated_by'] ?? '-') ?></td>
                                    <td><?= h($row['status_updated_role'] ?? '-') ?></td>
                                    <td><?= !empty($row['status_updated_at']) ? date('d M Y, h:i A', strtotime($row['status_updated_at'])) : '-' ?></td>
                                    <td><?= nl2br(h($row['report_summary'])) ?></td>
                                    <td><?= h($row['verified_by']) ?></td>
                                    <td><?= h($row['created_on']) ?></td>

                                    <td class="text-center">
                                        <?php if (count($hist) > 0): ?>
                                        <button onclick="showHistory(<?= $did ?>)" class="btn btn-indigo btn-xs">
                                            <i data-lucide="clock" class="w-3 h-3"></i> <?= count($hist) ?> updates
                                        </button>
                                        <?php else: ?>
                                        <span class="text-gray-400 text-xs">—</span>
                                        <?php endif; ?>
                                    </td>

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

            <!-- Status History Modal -->
            <div id="history-modal" class="modal-overlay" onclick="if(event.target===this)closeHistory()">
                <div class="modal-content" style="max-width:600px;">
                    <div class="modal-header">
                        <h2>Status History — Donation #<span id="history-donation-id"></span></h2>
                        <button onclick="closeHistory()" class="modal-close" style="background:none;border:none;font-size:22px;cursor:pointer;color:#94a3b8;">&times;</button>
                    </div>
                    <div class="modal-body" id="history-modal-body">
                        <div class="text-center text-gray-400 py-8">Loading...</div>
                    </div>
                    <div class="modal-footer">
                        <button onclick="closeHistory()" class="btn btn-secondary">Close</button>
                    </div>
                </div>
            </div>

            <style>
            .modal-overlay.open { display: flex !important; }
            </style>

            <script>
            const historyData = <?= json_encode($historyByDonation) ?>;

            function showHistory(donationId) {
                const hist = historyData[donationId] || [];
                document.getElementById('history-donation-id').textContent = donationId;

                const statusColors = {
                    'Pending Verification': '#f59e0b',
                    'Verified': '#10b981',
                    'Contacted': '#3b82f6',
                    'Received': '#8b5cf6',
                    'Completed': '#059669',
                };

                let html = '<div style="position:relative;padding-left:28px;">';
                hist.forEach(function(h, idx) {
                    const prevColor = statusColors[h.previous_status] || '#94a3b8';
                    const newColor = statusColors[h.new_status] || '#94a3b8';
                    const dispName = h.admin_display_name || h.admin_name || 'Website';
                    const isLast = idx === hist.length - 1;

                    if (!isLast) {
                        html += '<div style="position:absolute;left:-16px;top:20px;bottom:0;width:2px;background:#e2e8f0;"></div>';
                    }
                    html += '<div style="position:relative;padding-bottom:' + (isLast ? '0' : '24px') + ';">';
                    html += '<div style="position:absolute;left:-22px;top:4px;width:14px;height:14px;border-radius:50%;background:' + newColor + ';border:2px solid white;box-shadow:0 1px 3px rgba(0,0,0,0.15);"></div>';
                    html += '<div style="font-size:13px;color:#334155;">';
                    html += '<span style="font-weight:600;">' + (h.previous_status || 'Submitted') + '</span>';
                    html += '<span style="color:#94a3b8;margin:0 6px;">→</span>';
                    html += '<span style="font-weight:700;color:' + newColor + ';">' + h.new_status + '</span>';
                    html += '</div>';
                    html += '<div style="font-size:12px;color:#64748b;margin-top:2px;">';
                    html += '<span>By: ' + dispName + '</span>';
                    if (h.admin_role) {
                        html += '<span style="margin-left:12px;">Role: ' + h.admin_role + '</span>';
                    }
                    html += '<span style="margin-left:12px;">' + new Date(h.updated_at).toLocaleString('en-IN', {day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit'}) + '</span>';
                    html += '</div>';
                    if (h.remarks) {
                        html += '<div style="font-size:12px;color:#64748b;margin-top:2px;font-style:italic;">' + h.remarks + '</div>';
                    }
                    html += '</div>';
                });
                html += '</div>';

                document.getElementById('history-modal-body').innerHTML = html;
                document.getElementById('history-modal').classList.add('open');
                document.body.style.overflow = 'hidden';
            }

            function closeHistory() {
                document.getElementById('history-modal').classList.remove('open');
                document.body.style.overflow = '';
            }
            </script>
        </div>
    </div>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
