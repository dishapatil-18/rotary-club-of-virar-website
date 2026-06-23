<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: ../login.php"); exit; }
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/admin_functions.php';
require_once __DIR__ . '/../includes/send_email.php';

if (!isSuperAdmin()) {
    echo "<script>alert('Access denied. Super Admin only.'); window.location.href='dashboard.php';</script>";
    exit;
}

// Auto-create table if it doesn't exist
$tableCheck = $conn->query("SHOW TABLES LIKE 'contact_messages'");
if ($tableCheck && $tableCheck->num_rows === 0) {
    $conn->query("CREATE TABLE contact_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        status ENUM('Unread','Read','Replied','Archived') NOT NULL DEFAULT 'Unread',
        submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// Auto-migrate: add status column if missing, migrate is_read data
$colCheck = $conn->query("SHOW COLUMNS FROM contact_messages LIKE 'status'");
if ($colCheck && $colCheck->num_rows === 0) {
    $conn->query("ALTER TABLE contact_messages ADD COLUMN status ENUM('Unread','Read','Replied','Archived') NOT NULL DEFAULT 'Unread' AFTER is_read");
    $conn->query("UPDATE contact_messages SET status = 'Read' WHERE is_read = 1 AND status = 'Unread'");
    $conn->query("ALTER TABLE contact_messages DROP COLUMN is_read");
}

$message = '';
$error   = '';

// ─── HANDLE ACTIONS ───

// View detail
$viewMessage = null;
if (isset($_GET['view']) && ctype_digit($_GET['view'])) {
    $vid = (int)$_GET['view'];
    $stmt = $conn->prepare("SELECT * FROM contact_messages WHERE id = ?");
    $stmt->bind_param("i", $vid);
    $stmt->execute();
    $res = $stmt->get_result();
    $viewMessage = $res->fetch_assoc();
    $stmt->close();
    if ($viewMessage && $viewMessage['status'] === 'Unread') {
        $conn->query("UPDATE contact_messages SET status = 'Read' WHERE id = $vid");
        $viewMessage['status'] = 'Read';
    }
}

// Mark as read
if (isset($_GET['read']) && ctype_digit($_GET['read'])) {
    $rid = (int)$_GET['read'];
    $conn->query("UPDATE contact_messages SET status = 'Read' WHERE id = $rid");
    header("Location: contact_messages.php");
    exit;
}

// Archive
if (isset($_GET['archive']) && ctype_digit($_GET['archive'])) {
    $aid = (int)$_GET['archive'];
    $conn->query("UPDATE contact_messages SET status = 'Archived' WHERE id = $aid");
    header("Location: contact_messages.php");
    exit;
}

// Delete (Super Admin only)
if (isset($_GET['delete']) && ctype_digit($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM contact_messages WHERE id = ?");
    $stmt->bind_param("i", $did);
    if ($stmt->execute()) {
        $message = 'Message deleted permanently.';
    } else {
        $error = 'Failed to delete message.';
    }
    $stmt->close();
}

// Reply via email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reply') {
    $replyId    = (int)$_POST['message_id'];
    $replyBody  = trim($_POST['reply_body'] ?? '');
    $replySubject = trim($_POST['reply_subject'] ?? '');

    if ($replyBody === '' || $replySubject === '') {
        $error = 'Please provide both subject and reply message.';
    } else {
        $stmt = $conn->prepare("SELECT name, email, subject FROM contact_messages WHERE id = ?");
        $stmt->bind_param("i", $replyId);
        $stmt->execute();
        $msgData = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($msgData) {
            $fullBody = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <div style='text-align: center; margin-bottom: 20px;'>
                        <h2 style='color: #0A2342;'>Rotary Club of Virar</h2>
                    </div>
                    <div style='background: #f8fafc; border-radius: 12px; padding: 24px; border: 1px solid #e2e8f0;'>
                        <p>Dear " . htmlspecialchars($msgData['name']) . ",</p>
                        " . nl2br(htmlspecialchars($replyBody)) . "
                        <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
                        <p style='color: #94a3b8; font-size: 13px;'>
                            <strong>Original Message:</strong><br>
                            Subject: " . htmlspecialchars($msgData['subject']) . "
                        </p>
                    </div>
                    <div style='text-align: center; margin-top: 20px; color: #94a3b8; font-size: 12px;'>
                        <p>Rotary Club of Virar &bull; Service Above Self</p>
                    </div>
                </div>
            ";

            $sendResult = sendEmail($msgData['email'], $replySubject, $fullBody);
            if ($sendResult['success']) {
                $conn->query("UPDATE contact_messages SET status = 'Replied' WHERE id = $replyId");
                $message = 'Reply sent successfully to ' . htmlspecialchars($msgData['email']) . '.';
            } else {
                $error = 'Failed to send email. Check SMTP configuration.';
            }
        } else {
            $error = 'Message not found.';
        }
    }
}

// ─── STATS ───
$totalMessages   = $conn->query("SELECT COUNT(*) as c FROM contact_messages")->fetch_assoc()['c'] ?? 0;
$unreadMessages  = $conn->query("SELECT COUNT(*) as c FROM contact_messages WHERE status = 'Unread'")->fetch_assoc()['c'] ?? 0;
$readMessages    = $conn->query("SELECT COUNT(*) as c FROM contact_messages WHERE status = 'Read'")->fetch_assoc()['c'] ?? 0;
$repliedMessages = $conn->query("SELECT COUNT(*) as c FROM contact_messages WHERE status = 'Replied'")->fetch_assoc()['c'] ?? 0;
$archivedMessages = $conn->query("SELECT COUNT(*) as c FROM contact_messages WHERE status = 'Archived'")->fetch_assoc()['c'] ?? 0;

// ─── FILTERS & SEARCH ───
$filter = $_GET['filter'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$where = '';
$params = [];
$types = '';

if ($filter !== 'all') {
    $where = "WHERE status = ?";
    $params[] = $filter;
    $types .= 's';
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

$sql = "SELECT * FROM contact_messages $where ORDER BY submitted_at DESC";
$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$messagesList = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ─── REPLY FORM DATA (if coming from reply link) ───
$replyMessage = null;
if (isset($_GET['reply']) && ctype_digit($_GET['reply'])) {
    $rid = (int)$_GET['reply'];
    $stmt = $conn->prepare("SELECT * FROM contact_messages WHERE id = ?");
    $stmt->bind_param("i", $rid);
    $stmt->execute();
    $replyMessage = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
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
    <div class="stat-card fade-in" style="border-left:4px solid #64748b;animation-delay:0.2s;">
        <div class="stat-value" style="font-size:22px;color:#64748b;"><?= $archivedMessages ?></div>
        <div class="stat-label">Archived</div>
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
                <a href="contact_messages.php?filter=Archived<?= $search ? '&search='.urlencode($search) : '' ?>" class="btn <?= $filter === 'Archived' ? 'btn-yellow' : 'btn-ghost' ?> btn-sm">Archived</a>
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
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($messagesList as $msg): 
                    $badgeClass = match($msg['status']) {
                        'Unread' => 'badge-red',
                        'Read' => 'badge-blue',
                        'Replied' => 'badge-purple',
                        'Archived' => 'badge-gray',
                        default => 'badge-gray'
                    };
                ?>
                <tr>
                    <td style="font-weight:600;"><?= e($msg['name']) ?></td>
                    <td><a href="mailto:<?= e($msg['email']) ?>" style="color:#64748b;text-decoration:none;"><?= e($msg['email']) ?></a></td>
                    <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($msg['subject']) ?></td>
                    <td style="white-space:nowrap;color:#94a3b8;font-size:13px;"><?= date('d M Y, H:i', strtotime($msg['submitted_at'])) ?></td>
                    <td><span class="badge <?= $badgeClass ?>"><?= e($msg['status']) ?></span></td>
                    <td>
                        <div style="display:flex;gap:4px;flex-wrap:nowrap;">
                            <a href="?view=<?= $msg['id'] ?>" class="btn btn-ghost btn-xs" title="View"><i data-lucide="eye" style="width:14px;height:14px;"></i></a>
                            <a href="?reply=<?= $msg['id'] ?>" class="btn btn-ghost btn-xs" title="Reply"><i data-lucide="reply" style="width:14px;height:14px;"></i></a>
                            <?php if ($msg['status'] !== 'Archived'): ?>
                            <a href="?archive=<?= $msg['id'] ?>" class="btn btn-ghost btn-xs" title="Archive" onclick="return confirm('Archive this message?')"><i data-lucide="archive" style="width:14px;height:14px;"></i></a>
                            <?php endif; ?>
                            <a href="?delete=<?= $msg['id'] ?>" class="btn btn-ghost btn-xs" style="color:#ef4444;" title="Delete" onclick="return confirm('Permanently delete this message?')"><i data-lucide="trash-2" style="width:14px;height:14px;"></i></a>
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
                        <div style="color:#64748b;"><?= date('d F Y, h:i A', strtotime($viewMessage['submitted_at'])) ?></div>
                    </div>
                    <div style="grid-column:span 2;">
                        <label style="font-size:12px;font-weight:600;color:#64748b;display:block;margin-bottom:2px;">Status</label>
                        <div><span class="badge <?= match($viewMessage['status']) { 'Unread' => 'badge-red', 'Read' => 'badge-blue', 'Replied' => 'badge-purple', 'Archived' => 'badge-gray', default => 'badge-gray' } ?>"><?= e($viewMessage['status']) ?></span></div>
                    </div>
                    <div style="grid-column:span 2;">
                        <label style="font-size:12px;font-weight:600;color:#64748b;display:block;margin-bottom:4px;">Message</label>
                        <div style="background:#f8fafc;padding:16px;border-radius:10px;border:1px solid #f1f5f9;line-height:1.6;white-space:pre-wrap;"><?= e($viewMessage['message']) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <a href="?reply=<?= $viewMessage['id'] ?>" class="btn btn-yellow btn-sm"><i data-lucide="reply" style="width:14px;height:14px;"></i> Reply</a>
            <?php if ($viewMessage['status'] !== 'Archived'): ?>
            <a href="?archive=<?= $viewMessage['id'] ?>" class="btn btn-ghost btn-sm" onclick="return confirm('Archive this message?')"><i data-lucide="archive" style="width:14px;height:14px;"></i> Archive</a>
            <?php endif; ?>
            <button class="btn btn-ghost btn-sm" onclick="this.closest('.modal-overlay').style.display='none'">Close</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ─── REPLY FORM ─── -->
<?php if ($replyMessage): 
    $defaultSubject = 'Re: ' . $replyMessage['subject'];
?>
<div class="modal-overlay open" onclick="this.style.display='none'">
    <div class="modal-content" onclick="event.stopPropagation()" style="max-width:600px;">
        <div class="modal-header">
            <h2>Reply to <?= e($replyMessage['name']) ?></h2>
            <button class="modal-close" onclick="this.closest('.modal-overlay').style.display='none'"><i data-lucide="x" style="width:18px;height:18px;"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="reply">
            <input type="hidden" name="message_id" value="<?= $replyMessage['id'] ?>">
            <div class="modal-body">
                <div style="display:grid;gap:14px;">
                    <div>
                        <label class="form-label">To</label>
                        <div style="padding:10px 13px;background:#f8fafc;border-radius:9px;border:1px solid #e2e8f0;font-size:13px;"><?= e($replyMessage['name']) ?> &lt;<?= e($replyMessage['email']) ?>&gt;</div>
                    </div>
                    <div>
                        <label class="form-label">Subject</label>
                        <input type="text" name="reply_subject" class="form-input" value="<?= e($defaultSubject) ?>" required>
                    </div>
                    <div>
                        <label class="form-label">Your Reply</label>
                        <textarea name="reply_body" class="form-textarea" rows="8" required placeholder="Type your reply here..."></textarea>
                    </div>
                    <div style="background:#f0f9ff;border:1px solid #bae6fd;padding:12px 16px;border-radius:10px;font-size:13px;color:#0369a1;">
                        <strong>Original Message:</strong><br>
                        <?= e($replyMessage['subject']) ?> — <?= nl2br(e(mb_strimwidth($replyMessage['message'], 0, 200, '...'))) ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" onclick="this.closest('.modal-overlay').style.display='none'">Cancel</button>
                <button type="submit" class="btn btn-yellow"><i data-lucide="send" style="width:16px;height:16px;"></i> Send Reply</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
