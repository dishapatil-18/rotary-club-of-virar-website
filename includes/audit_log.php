<?php
/**
 * Audit Log Helper — RCMP v2.4.1
 *
 * Provides logAudit() for recording all meaningful admin actions.
 * Read-only by design: logs cannot be edited, updated, or deleted.
 */

if (!function_exists('logAudit')) {
    /**
     * Record an audit log entry.
     *
     * @param mysqli  $conn       Database connection
     * @param string  $module     Module name (e.g. 'Authentication', 'Administration')
     * @param string  $action     Action name (e.g. 'Login Success', 'Member Added')
     * @param string  $description Human-readable description
     * @param string  $severity   'INFO', 'WARNING', or 'CRITICAL'
     * @param string  $status     'success' or 'failed'
     * @param int     $adminId    Admin ID (null = auto-detect from session)
     * @param string  $adminName  Admin name (null = auto-detect from session)
     * @param string  $role       Admin role (null = auto-detect from session)
     * @return bool
     */
    function logAudit($conn, $module, $action, $description, $severity = 'INFO', $status = 'success', $adminId = null, $adminName = null, $role = null) {
        if (empty($module) || empty($action) || empty($description)) {
            return false;
        }

        $adminId   = $adminId   ?? ($_SESSION['admin_id']   ?? null);
        $adminName = $adminName ?? ($_SESSION['admin_name'] ?? 'System');
        $role      = $role      ?? ($_SESSION['admin_role'] ?? 'system');

        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if (strpos($ip, ',') !== false) $ip = trim(explode(',', $ip)[0]);

        $stmt = $conn->prepare("INSERT INTO audit_logs (admin_id, admin_name, role, module, action, description, severity, status, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) return false;

        $stmt->bind_param("issssssss", $adminId, $adminName, $role, $module, $action, $description, $severity, $status, $ip);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
}
