<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: ../login.php"); exit; }
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/audit_log.php';
require_once __DIR__ . '/admin_functions.php';
require_once __DIR__ . '/../includes/communication_engine.php';

if (!isContactMessagesAllowed()) {
    echo "<script>alert('Access denied.'); window.location.href='dashboard.php';</script>";
    exit;
}

// Auto-create table if it doesn't exist
$tableCheck = $conn->query("SHOW TABLES LIKE 'contact_messages'");
if ($tableCheck && $tableCheck->num_rows === 0) {
    $conn->query("CREATE TABLE contact_messages (
        message_id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        status ENUM('new','read','replied') NOT NULL DEFAULT 'new',
        submitted_on TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// Auto-migrate: add status column if missing
$colCheck = $conn->query("SHOW COLUMNS FROM contact_messages LIKE 'status'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE contact_messages ADD COLUMN status ENUM('new','read','replied') NOT NULL DEFAULT 'new'");
}

// Auto-migrate: add tracking columns if missing
$seenByCheck = $conn->query("SHOW COLUMNS FROM contact_messages LIKE 'seen_by'");
if ($seenByCheck && $seenByCheck->num_rows === 0) {
    $conn->query("ALTER TABLE contact_messages ADD COLUMN seen_by VARCHAR(255) DEFAULT NULL AFTER status");
}
$seenAtCheck = $conn->query("SHOW COLUMNS FROM contact_messages LIKE 'seen_at'");
if ($seenAtCheck && $seenAtCheck->num_rows === 0) {
    $conn->query("ALTER TABLE contact_messages ADD COLUMN seen_at DATETIME DEFAULT NULL AFTER seen_by");
}
$repliedByCheck = $conn->query("SHOW COLUMNS FROM contact_messages LIKE 'replied_by'");
if ($repliedByCheck && $repliedByCheck->num_rows === 0) {
    $conn->query("ALTER TABLE contact_messages ADD COLUMN replied_by VARCHAR(255) DEFAULT NULL AFTER seen_at");
}
$repliedAtCheck = $conn->query("SHOW COLUMNS FROM contact_messages LIKE 'replied_at'");
if ($repliedAtCheck && $repliedAtCheck->num_rows === 0) {
    $conn->query("ALTER TABLE contact_messages ADD COLUMN replied_at DATETIME DEFAULT NULL AFTER replied_by");
}

$message = '';
$error   = '';

// ─── HANDLE ACTIONS ───

// View detail
$viewMessage = null;
if (isset($_GET['view']) && ctype_digit($_GET['view'])) {
    $vid = (int)$_GET['view'];
    $stmt = $conn->prepare("SELECT * FROM contact_messages WHERE message_id = ?");
    $stmt->bind_param("i", $vid);
    $stmt->execute();
    $res = $stmt->get_result();
    $viewMessage = $res->fetch_assoc();
    $stmt->close();
    if ($viewMessage && $viewMessage['status'] === 'new') {
        $updates = ["status = 'read'"];
        if ($viewMessage['seen_by'] === null) {
            $updates[] = "seen_by = '" . $conn->real_escape_string($_SESSION['admin_name']) . "'";
            $viewMessage['seen_by'] = $_SESSION['admin_name'];
        }
        if ($viewMessage['seen_at'] === null) {
            $updates[] = "seen_at = NOW()";
            $viewMessage['seen_at'] = date('Y-m-d H:i:s');
        }
        $conn->query("UPDATE contact_messages SET " . implode(', ', $updates) . " WHERE message_id = $vid");
        logAudit($conn, 'Contact Messages', 'Message Read', 'Viewed contact message #' . $vid . ' from "' . ($viewMessage['name'] ?? '') . '".', 'INFO', 'success');
        $viewMessage['status'] = 'read';
    }
}

// Mark as read
if (isset($_GET['read']) && ctype_digit($_GET['read'])) {
    $rid = (int)$_GET['read'];
    $conn->query("UPDATE contact_messages SET status = 'read' WHERE message_id = $rid");
    logAudit($conn, 'Contact Messages', 'Message Read', 'Marked message #' . $rid . ' as read.', 'INFO', 'success');
    header("Location: contact_messages.php");
    exit;
}

// Delete (Super Admin only)
if (isset($_GET['delete']) && ctype_digit($_GET['delete'])) {
    if (!isSuperAdmin()) {
        $error = 'Access denied. Only Super Admin can delete messages.';
    } else {
        $did = (int)$_GET['delete'];
        $stmt = $conn->prepare("DELETE FROM contact_messages WHERE message_id = ?");
        $stmt->bind_param("i", $did);
        if ($stmt->execute()) {
            $message = 'Message deleted permanently.';
            logAudit($conn, 'Contact Messages', 'Message Deleted', 'Deleted contact message #' . $did . '.', 'WARNING', 'success');
        } else {
            $error = 'Failed to delete message.';
        }
        $stmt->close();
    }
}

// Reply via email — redirect to Email Composer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reply') {
    $replyId = (int)$_POST['message_id'];
    $replyBody = trim($_POST['reply_body'] ?? '');
    $replySubject = trim($_POST['reply_subject'] ?? '');
    $composerParams = [
        'module'     => 'Contact Messages',
        'action'     => 'Message Replied',
        'message_id' => $replyId,
        'skip_url'   => 'contact_messages.php',
    ];
    if ($replyBody !== '') $composerParams['body'] = $replyBody;
    if ($replySubject !== '') $composerParams['subject'] = $replySubject;
    header("Location: " . commComposerUrl($composerParams));
    exit;
}

// Reply opened but skipped (Email Composer Skip button)
if (isset($_GET['skip_log']) && ctype_digit($_GET['skip_log'])) {
    $skipId = (int)$_GET['skip_log'];
    logAudit($conn, 'Contact Messages', 'Contact Message Reply Opened (Email Skipped)', 'Admin opened reply for message #' . $skipId . ' but skipped sending email.', 'INFO', 'success');
    header("Location: contact_messages.php");
    exit;
}

// ─── STATS ───
$totalMessages   = $conn->query("SELECT COUNT(*) as c FROM contact_messages")->fetch_assoc()['c'] ?? 0;
$unreadMessages  = $conn->query("SELECT COUNT(*) as c FROM contact_messages WHERE status = 'new'")->fetch_assoc()['c'] ?? 0;
$readMessages    = $conn->query("SELECT COUNT(*) as c FROM contact_messages WHERE status = 'read'")->fetch_assoc()['c'] ?? 0;
$repliedMessages = $conn->query("SELECT COUNT(*) as c FROM contact_messages WHERE status = 'replied'")->fetch_assoc()['c'] ?? 0;

// ─── FILTERS & SEARCH ───
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$statusFilterMap = ['Unread' => 'new', 'Read' => 'read', 'Replied' => 'replied'];

$where = '';
$params = [];
$types = '';

if ($filter !== 'all') {
    $dbFilter = $statusFilterMap[$filter] ?? null;
    if ($dbFilter) {
        $where = "WHERE status = ?";
        $params[] = $dbFilter;
        $types .= 's';
    } else {
        $filter = 'all';
    }
}

if ($search !== '') {
    $searchTerm = '%' . $search . '%';
    if ($where) {
        $where .= " AND (name LIKE ? OR email LIKE ? OR subject LIKE ?)";
    } else {
        $where = "WHERE (name LIKE ? OR email LIKE ? OR subject LIKE ?)";
    }
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'sss';
}

$sql = "SELECT * FROM contact_messages $where ORDER BY submitted_on DESC";
$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$messagesList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ─── REPLY FORM DATA (no longer used — redirects to Email Composer) ───
$replyMessage = null;
?>
<?php
$pageTitle = 'Contact Messages';
$activeNav = 'contact-messages';
require __DIR__ . '/includes/admin_head.php';
require __DIR__ . '/includes/admin_header.php';
?>

<?php if ($message): ?>
<div class="alert alert-success"><?= e($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<!-- ─── STAT CARDS ─── -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:14px;margin-bottom:24px;">
    <div class="stat-card fade-in" style="border-left:4px solid #3b82f6;">
        <div class="stat-value" style="font-size:22px;"><?= $totalMessages ?></div>
        <div class="stat-label">Total Messages</div>
    </div>
    <div class="stat-card fade-in" style="border-left:4px solid #ef4444;animation-delay:0.05s;">
        <div class="stat-value" style="font-size:22px;color:#ef4444;"><?= $unreadMessages ?></div>
        <div class="stat-label">Unread</div>
    </div>
    <div class="stat-card fade-in" style="border-left:4px solid #10b981;animation-delay:0.1s;">
        <div class="stat-value" style="font-size:22px;color:#10b981;"><?= $readMessages ?></div>
        <div class="stat-label">Read</div>
    </div>
    <div class="stat-card fade-in" style="border-left:4px solid #8b5cf6;animation-delay:0.15s;">
        <div class="stat-value" style="font-size:22px;color:#8b5cf6;"><?= $repliedMessages ?></div>
        <div class="stat-label">Replied</div>
    </div>
</div>

<!-- ─── FILTER & SEARCH BAR ─── -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-body" style="padding:16px 20px;">
        <form method="GET" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
            <div style="display:flex;gap:4px;flex-wrap:wrap;">
                <a href="contact_messages.php?filter=all<?= $search ? '&search='.urlencode($search) : '' ?>" class="btn <?= $filter === 'all' ? 'btn-yellow' : 'btn-ghost' ?> btn-sm">All</a>
                <a href="contact_messages.php?filter=Unread<?= $search ? '&search='.urlencode($search) : '' ?>" class="btn <?= $filter === 'Unread' ? 'btn-yellow' : 'btn-ghost' ?> btn-sm">Unread</a>
                <a href="contact_messages.php?filter=Read<?= $search ? '&search='.urlencode($search) : '' ?>" class="btn <?= $filter === 'Read' ? 'btn-yellow' : 'btn-ghost' ?> btn-sm">Read</a>
                <a href="contact_messages.php?filter=Replied<?= $search ? '&search='.urlencode($search) : '' ?>" class="btn <?= $filter === 'Replied' ? 'btn-yellow' : 'btn-ghost' ?> btn-sm">Replied</a>

            </div>
            <div style="flex:1;min-width:160px;display:flex;gap:6px;">
                <input type="text" name="search" placeholder="Search name, email, subject..." value="<?= e($search) ?>" class="form-input" style="padding:8px 12px;font-size:13px;">
                <button type="submit" class="btn btn-primary btn-sm"><i data-lucide="search" style="width:14px;height:14px;"></i></button>
                <?php if ($search): ?>
                <a href="contact_messages.php?filter=<?= e($filter) ?>" class="btn btn-ghost btn-sm">Clear</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- ─── MESSAGE TABLE ─── -->
<div class="card">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
        <h2 style="font-size:16px;font-weight:700;margin:0;">Messages</h2>
        <span style="font-size:13px;color:#94a3b8;"><?= count($messagesList) ?> message<?= count($messagesList) !== 1 ? 's' : '' ?></span>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <?php if (empty($messagesList)): ?>
        <div class="empty-state">
            <i data-lucide="inbox" style="width:48px;height:48px;opacity:0.3;margin-bottom:12px;"></i>
            <h3>No messages found</h3>
            <p>No contact messages match your current filter.</p>
        </div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Subject</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Seen By</th>
                    <th>Replied By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($messagesList as $msg): 
                    $displayStatus = match($msg['status']) {
                        'new' => 'Unread',
                        'read' => 'Read',
                        'replied' => 'Replied',
                        default => ucfirst($msg['status'])
                    };
                    $badgeClass = match($msg['status']) {
                        'new' => 'badge-red',
                        'read' => 'badge-blue',
                        'replied' => 'badge-purple',
                        default => 'badge-gray'
                    };
                ?>
                <tr>
                    <td style="font-weight:600;"><?= e($msg['name']) ?></td>
                    <td><a href="mailto:<?= e($msg['email']) ?>" style="color:#64748b;text-decoration:none;"><?= e($msg['email']) ?></a></td>
                    <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($msg['subject']) ?></td>
                    <td style="white-space:nowrap;color:#94a3b8;font-size:13px;"><?= date('d M Y, H:i', strtotime($msg['submitted_on'])) ?></td>
                    <td><span class="badge <?= $badgeClass ?>"><?= e($displayStatus) ?></span></td>
                    <td style="font-size:13px;color:#64748b;"><?= e($msg['seen_by'] ?? '—') ?></td>
                    <td style="font-size:13px;color:#64748b;"><?= e($msg['replied_by'] ?? '—') ?></td>
                    <td>
                        <div style="display:flex;gap:4px;flex-wrap:nowrap;">
                            <a href="?view=<?= $msg['message_id'] ?>" class="btn btn-ghost btn-xs" title="View"><i data-lucide="eye" style="width:14px;height:14px;"></i></a>
                            <a href="<?= commComposerUrl(['to' => $msg['email'], 'subject' => (!empty($msg['subject']) ? 'Re: ' . $msg['subject'] . ' – ' : 'Regarding Your Inquiry – ') . CLUB_NAME, 'template' => 'contact_reply', 'recipient_name' => $msg['name'], 'original_subject' => $msg['subject'] ?? '', 'original_message' => $msg['message'] ?? '', 'module' => 'Contact Messages', 'action' => 'Contact Message Reply (Email Sent)', 'message_id' => $msg['message_id'], 'skip_url' => 'contact_messages.php?skip_log=' . $msg['message_id']]) ?>" class="btn btn-ghost btn-xs" title="Reply"><i data-lucide="reply" style="width:14px;height:14px;"></i></a>

                            <a href="?delete=<?= $msg['message_id'] ?>" class="btn btn-ghost btn-xs" style="color:#ef4444;" title="Delete" onclick="return confirm('Permanently delete this message?')"><i data-lucide="trash-2" style="width:14px;height:14px;"></i></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- ─── VIEW MODAL ─── -->
<?php if ($viewMessage): ?>
<div class="modal-overlay open" onclick="this.style.display='none'">
    <div class="modal-content" onclick="event.stopPropagation()" style="max-width:600px;">
        <div class="modal-header">
            <h2>Message Details</h2>
            <button class="modal-close" onclick="this.closest('.modal-overlay').style.display='none'"><i data-lucide="x" style="width:18px;height:18px;"></i></button>
        </div>
        <div class="modal-body">
            <div style="display:grid;gap:12px;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <label style="font-size:12px;font-weight:600;color:#64748b;display:block;margin-bottom:2px;">Name</label>
                        <div style="font-weight:600;"><?= e($viewMessage['name']) ?></div>
                    </div>
                    <div>
                        <label style="font-size:12px;font-weight:600;color:#64748b;display:block;margin-bottom:2px;">Email</label>
                        <div><a href="mailto:<?= e($viewMessage['email']) ?>" style="color:#2563eb;text-decoration:none;"><?= e($viewMessage['email']) ?></a></div>
                    </div>
                    <div style="grid-column:span 2;">
                        <label style="font-size:12px;font-weight:600;color:#64748b;display:block;margin-bottom:2px;">Subject</label>
                        <div style="font-weight:500;"><?= e($viewMessage['subject']) ?></div>
                    </div>
                    <div style="grid-column:span 2;">
                        <label style="font-size:12px;font-weight:600;color:#64748b;display:block;margin-bottom:2px;">Date Submitted</label>
                        <div style="color:#64748b;"><?= date('d F Y, h:i A', strtotime($viewMessage['submitted_on'])) ?></div>
                    </div>
                    <div style="grid-column:span 2;">
                        <label style="font-size:12px;font-weight:600;color:#64748b;display:block;margin-bottom:2px;">Status</label>
                        <div><span class="badge <?= match($viewMessage['status']) { 'new' => 'badge-red', 'read' => 'badge-blue', 'replied' => 'badge-purple', default => 'badge-gray' } ?>"><?= e(match($viewMessage['status']) { 'new' => 'Unread', 'read' => 'Read', 'replied' => 'Replied', default => ucfirst($viewMessage['status']) }) ?></span></div>
                    </div>
                    <div style="grid-column:span 2;">
                        <label style="font-size:12px;font-weight:600;color:#64748b;display:block;margin-bottom:2px;">Seen By</label>
                        <div style="color:#64748b;"><?= e($viewMessage['seen_by'] ?? '—') ?></div>
                    </div>
                    <div style="grid-column:span 2;">
                        <label style="font-size:12px;font-weight:600;color:#64748b;display:block;margin-bottom:2px;">Seen At</label>
                        <div style="color:#64748b;"><?= isset($viewMessage['seen_at']) && $viewMessage['seen_at'] ? date('d F Y, h:i A', strtotime($viewMessage['seen_at'])) : '—' ?></div>
                    </div>
                    <div style="grid-column:span 2;">
                        <label style="font-size:12px;font-weight:600;color:#64748b;display:block;margin-bottom:2px;">Replied By</label>
                        <div style="color:#64748b;"><?= e($viewMessage['replied_by'] ?? '—') ?></div>
                    </div>
                    <div style="grid-column:span 2;">
                        <label style="font-size:12px;font-weight:600;color:#64748b;display:block;margin-bottom:2px;">Replied At</label>
                        <div style="color:#64748b;"><?= isset($viewMessage['replied_at']) && $viewMessage['replied_at'] ? date('d F Y, h:i A', strtotime($viewMessage['replied_at'])) : '—' ?></div>
                    </div>
                    <div style="grid-column:span 2;">
                        <label style="font-size:12px;font-weight:600;color:#64748b;display:block;margin-bottom:4px;">Message</label>
                        <div style="background:#f8fafc;padding:16px;border-radius:10px;border:1px solid #f1f5f9;line-height:1.6;white-space:pre-wrap;"><?= e($viewMessage['message']) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <a href="<?= commComposerUrl(['to' => $viewMessage['email'], 'subject' => (!empty($viewMessage['subject']) ? 'Re: ' . $viewMessage['subject'] . ' – ' : 'Regarding Your Inquiry – ') . CLUB_NAME, 'template' => 'contact_reply', 'recipient_name' => $viewMessage['name'], 'original_subject' => $viewMessage['subject'] ?? '', 'original_message' => $viewMessage['message'] ?? '', 'module' => 'Contact Messages', 'action' => 'Contact Message Reply (Email Sent)', 'message_id' => $viewMessage['message_id'], 'skip_url' => 'contact_messages.php?skip_log=' . $viewMessage['message_id']]) ?>" class="btn btn-yellow btn-sm"><i data-lucide="reply" style="width:14px;height:14px;"></i> Reply</a>

            <button class="btn btn-ghost btn-sm" onclick="this.closest('.modal-overlay').style.display='none'">Close</button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
