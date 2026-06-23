<?php
// Admin/collaboration_action.php

session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit;
}

require __DIR__ . '/../includes/db_connect.php';
require __DIR__ . '/../includes/send_email.php';

// ----------------------------
// DELETE Proposal
// ----------------------------
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);

    $stmt = $conn->prepare("DELETE FROM collaborations WHERE collab_id = ?");
    $stmt->bind_param("i", $del_id);

    if ($stmt->execute()) {
        echo "<script>alert('Proposal deleted successfully'); window.location.href='collaboration_action.php';</script>";
        exit;
    } else {
        echo "<script>alert('Error deleting proposal'); window.location.href='collaboration_action.php';</script>";
        exit;
    }
}

// ----------------------------
// APPROVE / REJECT PROPOSAL
// ----------------------------
if (isset($_GET['status']) && isset($_GET['id'])) {
    $newStatus = $_GET['status'] === 'Approved' ? 'Approved' : 'Rejected';
    $proposalId = intval($_GET['id']);
    $adminName = $_SESSION['admin_name'] ?? 'Admin';
    $now = date('Y-m-d H:i:s');

    // Fetch proposal info for email
    $stmt = $conn->prepare("SELECT name, email FROM collaborations WHERE collab_id = ?");
    $stmt->bind_param("i", $proposalId);
    $stmt->execute();
    $res = $stmt->get_result();
    $proposal = $res->fetch_assoc();
    $stmt->close();

    if ($proposal) {
        // Update status in database
        $upd = $conn->prepare("UPDATE collaborations SET status = ?, reviewed_by = ?, reviewed_at = ? WHERE collab_id = ?");
        $upd->bind_param("sssi", $newStatus, $adminName, $now, $proposalId);
        $upd->execute();
        $upd->close();

        // Send email notification using templates
        $emailSent = false;
        if (!empty($proposal['email'])) {
            $collabTemplate = $newStatus === 'Approved'
                ? ['file' => 'collaboration_approved.php', 'func' => 'getCollaborationApprovedContent']
                : ['file' => 'collaboration_rejected.php', 'func' => 'getCollaborationRejectedContent'];

            require_once __DIR__ . '/../includes/email_templates/' . $collabTemplate['file'];
            $content = $collabTemplate['func']($proposal['name']);
            $mailResult = sendEmail($proposal['email'], $content['subject'], $content['body']);
            $emailSent = $mailResult['success'];
        }

        $msg = $newStatus === 'Approved' ? 'approved' : 'rejected';
        $emailNote = $emailSent ? ' Email sent to proposer.' : '';
        echo "<script>alert('Proposal has been " . $msg . " successfully." . $emailNote . "'); window.location.href='collaboration_action.php';</script>";
    } else {
        echo "<script>alert('Proposal not found.'); window.location.href='collaboration_action.php';</script>";
    }
    exit;
}

// ----------------------------
// FETCH ALL PROPOSALS
// ----------------------------
$proposals = [];
$result = $conn->query("SELECT * FROM collaborations ORDER BY submitted_at DESC");

if ($result) {
    while ($row = $result->fetch_assoc()) {

        // Add virtual fields for UI backwards compatibility
        $row['organization']   = "";
        $row['proposal_title'] = $row['proposal'];
        $row['phone']          = "";
        $row['message']        = $row['proposal'];
        if (!isset($row['status']) || $row['status'] === null || $row['status'] === '') {
            $row['status'] = "Pending";
        }

        $proposals[] = $row;
    }
}
?>
<?php
$pageTitle = 'Collaboration Proposals';
$activeNav = 'collaborations';
require __DIR__ . '/includes/admin_head.php';
require __DIR__ . '/includes/admin_header.php';
?>
<div class="card"><div class="card-body">

    
    
    <?php if (empty($proposals)): ?>
        <p class="text-gray-600">No collaboration proposals submitted yet.</p>

    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th> </th>
                    <th>Name</th>
                    <th>Title</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($proposals as $p): ?>
                    <tr>
                        <td class="font-mono text-gray-500"><?= $p['collab_id'] ?></td>
                        <td><?= htmlspecialchars($p['name']) ?></td>
                        <td><?= htmlspecialchars($p['proposal_title']) ?></td>
                        <td><?= htmlspecialchars($p['email']) ?></td>

                        <td><span class="badge 
                            <?= $p['status']=='Approved' ? 'badge-green' : ($p['status']=='Rejected' ? 'badge-red' : 'badge-yellow') ?>">
                            <?= $p['status'] ?>
                        </span></td>

                        <td class="text-right">
                            <div class="flex items-center gap-1.5 flex-wrap justify-end">
                                <!-- VIEW -->
                                <button onclick="openModal(<?= $p['collab_id'] ?>)"
                                   class="btn btn-indigo btn-sm"><i data-lucide="eye" class="w-3.5 h-3.5"></i> View
                                </button>

                                <!-- APPROVE -->
                                <a href="?status=Approved&id=<?= $p['collab_id'] ?>"
                                   class="btn btn-primary btn-sm"><i data-lucide="check-circle" class="w-3.5 h-3.5"></i> Approve
                                </a>

                                <!-- REJECT -->
                                <a href="?status=Rejected&id=<?= $p['collab_id'] ?>"
                                   class="btn btn-danger btn-sm"><i data-lucide="x-circle" class="w-3.5 h-3.5"></i> Reject
                                </a>

                                <!-- DELETE -->
                                <a href="?delete=<?= $p['collab_id'] ?>"
                                   onclick="return confirm('Delete this proposal?')"
                                   class="btn btn-delete btn-sm"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Delete
                                </a>
                            </div>
                        </td>
                    </tr>

                    <!-- Hidden Full Details for Modal -->
                    <div id="proposal-<?= $p['collab_id'] ?>" class="hidden"
                         data-info='<?= json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG) ?>'></div>

                <?php endforeach; ?>
            </tbody>

            <tfoot>
                <tr>
                    <td colspan="6" class="text-right">
                        <div class="flex justify-end gap-3 pt-4">
                            <button onclick="window.location.href = 'dashboard.php'"
                                    class="btn btn-secondary"><i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Dashboard
                            </button>
                            <a href="collaboration_action.php"
                               class="btn btn-secondary"><i data-lucide="x" class="w-4 h-4"></i> Cancel
                            </a>
                        </div>
                    </td>
                </tr>
            </tfoot>
        </table>
    <?php endif; ?>

</div></div>


<!-- DETAILS MODAL -->
<div id="detailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center p-4 z-50">
    <div class="bg-white p-6 rounded-xl shadow-xl max-w-lg w-full relative">
        <button onclick="closeModal()" class="absolute top-3 right-3 text-gray-400 hover:text-gray-600 transition-colors"><i data-lucide="x" class="w-5 h-5"></i></button>
        <h2 class="text-2xl font-bold mb-4 text-indigo-600">Proposal Details</h2>

        <div id="modalContent" class="text-gray-700 space-y-2"></div>

        <div class="mt-6 text-right">
            <button onclick="closeModal()" class="btn btn-indigo"><i data-lucide="x" class="w-4 h-4"></i> Close
            </button>
        </div>
    </div>
</div>

<script>
function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function openModal(id) {
    const dataDiv = document.getElementById("proposal-" + id);
    if (!dataDiv) return;
    const raw = dataDiv.getAttribute("data-info");
    const data = JSON.parse(raw);

    let html = `
        <p><strong>Name:</strong> ${escapeHtml(data.name)}</p>
        <p><strong>Email:</strong> ${escapeHtml(data.email)}</p>
        <p><strong>Title:</strong> ${escapeHtml(data.proposal_title)}</p>
        <p><strong>Message:</strong><br>${escapeHtml(data.message)}</p>
        <p><strong>Status:</strong> ${escapeHtml(data.status)}</p>
        <p><strong>Submitted At:</strong> ${escapeHtml(data.submitted_at)}</p>
    `;

    document.getElementById("modalContent").innerHTML = html;
    const modal = document.getElementById("detailsModal");
    modal.classList.remove("hidden");
    modal.classList.add("flex");
}

function closeModal() {
    const modal = document.getElementById("detailsModal");
    modal.classList.add("hidden");
    modal.classList.remove("flex");
}
</script>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
