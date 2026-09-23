<?php
session_start();
if (!isset($_SESSION['admin_id'])) { header("Location: ../login.php"); exit; }
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/admin_functions.php';
require_once __DIR__ . '/../includes/website_settings.php';
require_once __DIR__ . '/../includes/audit_log.php';
$_ws = getWebsiteSettings($conn);

if (!isSuperAdmin()) {
    echo "<script>alert('Access denied.'); window.location.href='dashboard.php';</script>";
    exit;
}

// ─── CSV EXPORT ───
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $sql = "SELECT log_id, admin_name, role, module, action, description, severity, status, ip_address, created_at FROM audit_logs ORDER BY created_at DESC";
    $result = $conn->query($sql);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="audit_logs_' . date('Y-m-d_H-i-s') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Admin', 'Role', 'Module', 'Action', 'Description', 'Severity', 'Status', 'IP Address', 'Date']);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['log_id'], $row['admin_name'], $row['role'], $row['module'],
                $row['action'], $row['description'], $row['severity'], $row['status'],
                $row['ip_address'], date('d M Y, H:i:s', strtotime($row['created_at']))
            ]);
        }
    }
    fclose($output);
    exit;
}

// ─── STATS ───
$totalLogs    = $conn->query("SELECT COUNT(*) as c FROM audit_logs")->fetch_assoc()['c'] ?? 0;
$todayLogs    = $conn->query("SELECT COUNT(*) as c FROM audit_logs WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['c'] ?? 0;
$successLogs  = $conn->query("SELECT COUNT(*) as c FROM audit_logs WHERE status = 'success'")->fetch_assoc()['c'] ?? 0;
$failedLogs   = $conn->query("SELECT COUNT(*) as c FROM audit_logs WHERE status = 'failed'")->fetch_assoc()['c'] ?? 0;

// ─── FILTERS ───
$search       = trim($_GET['search'] ?? '');
$filterRole   = $_GET['role'] ?? '';
$filterModule = $_GET['module'] ?? '';
$filterSeverity = $_GET['severity'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$dateFrom     = $_GET['date_from'] ?? '';
$dateTo       = $_GET['date_to'] ?? '';
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 25;
$offset       = ($page - 1) * $perPage;

$where  = [];
$params = [];
$types  = '';

if ($search !== '') {
    $where[] = "(admin_name LIKE ? OR description LIKE ? OR action LIKE ? OR module LIKE ?)";
    $s = '%' . $search . '%';
    $params = array_merge($params, [$s, $s, $s, $s]);
    $types .= 'ssss';
}
if ($filterRole !== '') {
    $where[] = "role = ?";
    $params[] = $filterRole;
    $types .= 's';
}
if ($filterModule !== '') {
    $where[] = "module = ?";
    $params[] = $filterModule;
    $types .= 's';
}
if ($filterSeverity !== '') {
    $where[] = "severity = ?";
    $params[] = $filterSeverity;
    $types .= 's';
}
if ($filterStatus !== '') {
    $where[] = "status = ?";
    $params[] = $filterStatus;
    $types .= 's';
}
if ($dateFrom !== '') {
    $where[] = "DATE(created_at) >= ?";
    $params[] = $dateFrom;
    $types .= 's';
}
if ($dateTo !== '') {
    $where[] = "DATE(created_at) <= ?";
    $params[] = $dateTo;
    $types .= 's';
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $conn->prepare("SELECT COUNT(*) as c FROM audit_logs $whereSQL");
if ($params) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalRows = $countStmt->get_result()->fetch_assoc()['c'];
$countStmt->close();
$totalPages = max(1, ceil($totalRows / $perPage));

$sql = "SELECT * FROM audit_logs $whereSQL ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$fullTypes = $types . 'ii';
$allParams = array_merge($params, [$perPage, $offset]);
$stmt->bind_param($fullTypes, ...$allParams);
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Distinct values for filter dropdowns
$distinctRoles   = array_column($conn->query("SELECT DISTINCT role FROM audit_logs ORDER BY role")->fetch_all(MYSQLI_NUM), 0);
$distinctModules = array_column($conn->query("SELECT DISTINCT module FROM audit_logs ORDER BY module")->fetch_all(MYSQLI_NUM), 0);
?>

<?php
$pageTitle = 'Audit Logs';
$activeNav = 'audit-logs';
require __DIR__ . '/includes/admin_head.php';
require __DIR__ . '/includes/admin_header.php';
?>

<!-- ─── STAT CARDS ─── -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:14px;margin-bottom:24px;">
    <div class="stat-card fade-in" style="border-left:4px solid #3b82f6;">
        <div class="stat-value" style="font-size:22px;"><?= number_format($totalLogs) ?></div>
        <div class="stat-label">Total Logs</div>
    </div>
    <div class="stat-card fade-in" style="border-left:4px solid #f59e0b;animation-delay:0.05s;">
        <div class="stat-value" style="font-size:22px;color:#f59e0b;"><?= number_format($todayLogs) ?></div>
        <div class="stat-label">Today's Logs</div>
    </div>
    <div class="stat-card fade-in" style="border-left:4px solid #10b981;animation-delay:0.1s;">
        <div class="stat-value" style="font-size:22px;color:#10b981;"><?= number_format($successLogs) ?></div>
        <div class="stat-label">Successful</div>
    </div>
    <div class="stat-card fade-in" style="border-left:4px solid #ef4444;animation-delay:0.15s;">
        <div class="stat-value" style="font-size:22px;color:#ef4444;"><?= number_format($failedLogs) ?></div>
        <div class="stat-label">Failed</div>
    </div>
</div>

<!-- ─── FILTERS & SEARCH ─── -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-body" style="padding:16px 20px;">
        <form method="GET" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
            <div style="flex:1;min-width:160px;">
                <label style="font-size:11px;font-weight:600;color:#64748b;display:block;margin-bottom:3px;">Search</label>
                <input type="text" name="search" placeholder="Admin, action, description..." value="<?= e($search) ?>" class="form-input" style="padding:8px 12px;font-size:13px;">
            </div>
            <div style="min-width:110px;">
                <label style="font-size:11px;font-weight:600;color:#64748b;display:block;margin-bottom:3px;">Role</label>
                <select name="role" class="form-input" style="padding:8px 12px;font-size:13px;">
                    <option value="">All Roles</option>
                    <?php foreach ($distinctRoles as $r): ?>
                    <option value="<?= e($r) ?>" <?= $filterRole === $r ? 'selected' : '' ?>><?= e($r) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="min-width:110px;">
                <label style="font-size:11px;font-weight:600;color:#64748b;display:block;margin-bottom:3px;">Module</label>
                <select name="module" class="form-input" style="padding:8px 12px;font-size:13px;">
                    <option value="">All Modules</option>
                    <?php foreach ($distinctModules as $m): ?>
                    <option value="<?= e($m) ?>" <?= $filterModule === $m ? 'selected' : '' ?>><?= e($m) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="min-width:100px;">
                <label style="font-size:11px;font-weight:600;color:#64748b;display:block;margin-bottom:3px;">Severity</label>
                <select name="severity" class="form-input" style="padding:8px 12px;font-size:13px;">
                    <option value="">All</option>
                    <option value="INFO" <?= $filterSeverity === 'INFO' ? 'selected' : '' ?>>INFO</option>
                    <option value="WARNING" <?= $filterSeverity === 'WARNING' ? 'selected' : '' ?>>WARNING</option>
                    <option value="CRITICAL" <?= $filterSeverity === 'CRITICAL' ? 'selected' : '' ?>>CRITICAL</option>
                </select>
            </div>
            <div style="min-width:100px;">
                <label style="font-size:11px;font-weight:600;color:#64748b;display:block;margin-bottom:3px;">Status</label>
                <select name="status" class="form-input" style="padding:8px 12px;font-size:13px;">
                    <option value="">All</option>
                    <option value="success" <?= $filterStatus === 'success' ? 'selected' : '' ?>>Success</option>
                    <option value="failed" <?= $filterStatus === 'failed' ? 'selected' : '' ?>>Failed</option>
                </select>
            </div>
            <div style="min-width:130px;">
                <label style="font-size:11px;font-weight:600;color:#64748b;display:block;margin-bottom:3px;">From</label>
                <input type="date" name="date_from" value="<?= e($dateFrom) ?>" class="form-input" style="padding:8px 12px;font-size:13px;">
            </div>
            <div style="min-width:130px;">
                <label style="font-size:11px;font-weight:600;color:#64748b;display:block;margin-bottom:3px;">To</label>
                <input type="date" name="date_to" value="<?= e($dateTo) ?>" class="form-input" style="padding:8px 12px;font-size:13px;">
            </div>
            <div style="display:flex;gap:6px;align-self:flex-end;">
                <button type="submit" class="btn btn-primary btn-sm"><i data-lucide="filter" style="width:14px;height:14px;"></i> Filter</button>
                <?php if ($search || $filterRole || $filterModule || $filterSeverity || $filterStatus || $dateFrom || $dateTo): ?>
                <a href="audit_logs.php" class="btn btn-ghost btn-sm">Clear</a>
                <?php endif; ?>
                <a href="audit_logs.php?export=csv<?= $search ? '&search=' . urlencode($search) : '' ?><?= $filterRole ? '&role=' . urlencode($filterRole) : '' ?><?= $filterModule ? '&module=' . urlencode($filterModule) : '' ?><?= $filterSeverity ? '&severity=' . urlencode($filterSeverity) : '' ?><?= $filterStatus ? '&status=' . urlencode($filterStatus) : '' ?><?= $dateFrom ? '&date_from=' . urlencode($dateFrom) : '' ?><?= $dateTo ? '&date_to=' . urlencode($dateTo) : '' ?>" class="btn btn-ghost btn-sm" style="color:#10b981;"><i data-lucide="download" style="width:14px;height:14px;"></i> CSV</a>
            </div>
        </form>
    </div>
</div>

<!-- ─── LOGS TABLE ─── -->
<div class="card">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
        <h2 style="font-size:16px;font-weight:700;margin:0;">Audit Logs</h2>
        <span style="font-size:13px;color:#94a3b8;"><?= number_format($totalRows) ?> record<?= $totalRows !== 1 ? 's' : '' ?></span>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <?php if (empty($logs)): ?>
        <div class="empty-state">
            <i data-lucide="scroll" style="width:48px;height:48px;opacity:0.3;margin-bottom:12px;"></i>
            <h3>No audit logs found</h3>
            <p>No records match your current filters.</p>
        </div>
        <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date & Time</th>
                    <th>Admin</th>
                    <th>Role</th>
                    <th>Module</th>
                    <th>Action</th>
                    <th>Description</th>
                    <th>Severity</th>
                    <th>Status</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log):
                    $sevClass = match($log['severity']) {
                        'CRITICAL' => 'badge-red',
                        'WARNING'  => 'badge-yellow',
                        default    => 'badge-blue'
                    };
                    $statClass = $log['status'] === 'success' ? 'badge-green' : 'badge-red';
                ?>
                <tr>
                    <td style="white-space:nowrap;font-size:13px;color:#64748b;"><?= date('d M Y, H:i:s', strtotime($log['created_at'])) ?></td>
                    <td style="font-weight:600;font-size:13px;"><?= e($log['admin_name']) ?></td>
                    <td><span class="badge badge-gray" style="font-size:11px;"><?= e($log['role']) ?></span></td>
                    <td style="font-size:13px;"><?= e($log['module']) ?></td>
                    <td style="font-size:13px;font-weight:500;"><?= e($log['action']) ?></td>
                    <td style="max-width:300px;font-size:13px;color:#475569;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= e($log['description']) ?>"><?= e($log['description']) ?></td>
                    <td><span class="badge <?= $sevClass ?>" style="font-size:11px;"><?= e($log['severity']) ?></span></td>
                    <td><span class="badge <?= $statClass ?>" style="font-size:11px;"><?= e(ucfirst($log['status'])) ?></span></td>
                    <td style="font-size:12px;color:#94a3b8;white-space:nowrap;"><?= e($log['ip_address']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- ─── PAGINATION ─── -->
<?php if ($totalPages > 1): ?>
<div style="display:flex;align-items:center;justify-content:center;gap:8px;margin-top:20px;flex-wrap:wrap;">
    <?php
    $baseParams = http_build_query(array_filter([
        'search' => $search ?: null, 'role' => $filterRole ?: null,
        'module' => $filterModule ?: null, 'severity' => $filterSeverity ?: null,
        'status' => $filterStatus ?: null, 'date_from' => $dateFrom ?: null,
        'date_to' => $dateTo ?: null
    ]));
    $baseURL = 'audit_logs.php?' . ($baseParams ? $baseParams . '&' : '');
    ?>
    <?php if ($page > 1): ?>
    <a href="<?= $baseURL ?>page=<?= $page - 1 ?>" class="btn btn-ghost btn-sm"><i data-lucide="chevron-left" style="width:14px;height:14px;"></i></a>
    <?php endif; ?>
    <?php
    $startPage = max(1, $page - 2);
    $endPage = min($totalPages, $page + 2);
    if ($startPage > 1): ?>
    <a href="<?= $baseURL ?>page=1" class="btn btn-ghost btn-sm">1</a>
    <?php if ($startPage > 2): ?><span style="color:#94a3b8;">...</span><?php endif; ?>
    <?php endif; ?>
    <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
    <a href="<?= $baseURL ?>page=<?= $i ?>" class="btn <?= $i === $page ? 'btn-yellow' : 'btn-ghost' ?> btn-sm"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($endPage < $totalPages): ?>
    <?php if ($endPage < $totalPages - 1): ?><span style="color:#94a3b8;">...</span><?php endif; ?>
    <a href="<?= $baseURL ?>page=<?= $totalPages ?>" class="btn btn-ghost btn-sm"><?= $totalPages ?></a>
    <?php endif; ?>
    <?php if ($page < $totalPages): ?>
    <a href="<?= $baseURL ?>page=<?= $page + 1 ?>" class="btn btn-ghost btn-sm"><i data-lucide="chevron-right" style="width:14px;height:14px;"></i></a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/admin_footer.php'; ?>
