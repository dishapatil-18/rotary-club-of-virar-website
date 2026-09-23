<?php
/**
 * Communication Engine — RCMP v2.5
 *
 * Centralized email system: send, signature, templates, audit.
 * Every email in the platform MUST go through this engine.
 */

require_once __DIR__ . '/send_email.php';
require_once __DIR__ . '/website_settings.php';
require_once __DIR__ . '/audit_log.php';
require_once __DIR__ . '/../config/club_settings.php';

/**
 * Generate the official email signature HTML.
 */
function commGetSignature($conn = null) {
    $ws = null;
    if ($conn) $ws = getWebsiteSettings($conn);
    $name    = $ws['website_name'] ?? CLUB_NAME;
    $email   = CLUB_EMAIL;
    $phone   = CLUB_PHONE;
    $website = $ws['club_website'] ?? '';

    $sig  = "<div style='margin-top:24px;padding-top:16px;border-top:1px solid #e2e8f0;'>";
    $sig .= "<p style='margin:0 0 4px;font-weight:700;color:#0A2342;'>" . htmlspecialchars($name) . "</p>";
    $sig .= "<p style='margin:0 0 2px;font-size:13px;color:#475569;'>📧 " . htmlspecialchars($email) . "</p>";
    $sig .= "<p style='margin:0 0 2px;font-size:13px;color:#475569;'>📞 " . htmlspecialchars($phone) . "</p>";
    if ($website) $sig .= "<p style='margin:0 0 2px;font-size:13px;color:#475569;'>🌐 " . htmlspecialchars($website) . "</p>";
    $sig .= "<p style='margin:8px 0 0;font-size:11px;color:#94a3b8;font-style:italic;'>This email was sent using the Rotary Club Management Platform.</p>";
    $sig .= "</div>";
    return $sig;
}

/**
 * Wrap email body HTML with club branding + signature.
 */
function commWrapBody($bodyHtml, $conn = null) {
    $ws = null;
    if ($conn) $ws = getWebsiteSettings($conn);
    $name = $ws['website_name'] ?? CLUB_NAME;
    $signature = commGetSignature($conn);

    $html  = "<div style='font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;padding:20px;'>";
    $html .= "<div style='text-align:center;margin-bottom:20px;'>";
    $html .= "<h2 style='color:#0A2342;margin:0;'>" . htmlspecialchars($name) . "</h2>";
    $html .= "</div>";
    $html .= "<div style='background:#f8fafc;border-radius:12px;padding:24px;border:1px solid #e2e8f0;'>";
    $html .= $bodyHtml;
    $html .= $signature;
    $html .= "</div></div>";
    return $html;
}

/**
 * Send email via Communication Engine.
 * Automatically wraps body, sends, and logs to Audit Log.
 *
 * @param mysqli  $conn
 * @param string  $to       Recipient email
 * @param string  $subject  Email subject
 * @param string  $bodyHtml Email body (HTML)
 * @param string  $module   Audit module name
 * @param string  $action   Audit action name
 * @param string  $desc     Audit description (auto-generated if empty)
 * @return array ['success' => bool, 'error' => string]
 */
function commSendEmail($conn, $to, $subject, $bodyHtml, $module = 'System', $action = 'Email Sent', $desc = '') {
    $fullBody = commWrapBody($bodyHtml, $conn);
    $result   = sendEmail($to, $subject, $fullBody);

    $adminId   = $_SESSION['admin_id']   ?? null;
    $adminName = $_SESSION['admin_name'] ?? 'System';
    $role      = $_SESSION['admin_role'] ?? 'system';

    if ($result['success']) {
        $auditDesc = $desc ?: "Email sent to \"$to\"";
        logAudit($conn, $module, $action, $auditDesc, 'INFO', 'success', $adminId, $adminName, $role);
    } else {
        $auditDesc = $desc ?: "Failed to send email to \"$to\": " . ($result['error'] ?? 'Unknown');
        logAudit($conn, $module, $action . ' Failed', $auditDesc, 'WARNING', 'failed', $adminId, $adminName, $role);
    }

    return $result;
}

/**
 * Get all master email templates.
 */
function commGetTemplates() {
    return [
        'contact_reply' => [
            'name'    => 'Contact Message Reply',
            'subject' => 'Re: {original_subject} – ' . CLUB_NAME,
            'body'    => "Dear {recipient_name},\n\n"
                      . "Thank you for contacting " . CLUB_NAME . ".\n\n"
                      . "We appreciate you taking the time to reach out to us.\n\n"
                      . "Regarding your inquiry:\n\n"
                      . "\"{original_message}\"\n\n"
                      . "Our Response:\n\n"
                      . "--------------------------------------------------\n\n"
                      . "(Administrator may write the reply here.)\n\n"
                      . "--------------------------------------------------\n\n"
                      . "If you have any further questions, please feel free to reply to this email.\n\n"
                      . "Thank you for connecting with " . CLUB_NAME . ".\n\n"
                      . "Warm Regards,\n"
                      . CLUB_NAME,
        ],
        'donation_received' => [
            'name'    => 'Donation Received',
            'subject' => 'Donation Received – ' . CLUB_NAME,
            'body'    => "Dear {recipient_name},\n\nThank you for your generous donation to " . CLUB_NAME . ".\n\nWe have received your donation and it is currently being reviewed by our team.\n\nDonation Type: {donation_type}\nAmount: {amount}\n\nWe appreciate your contribution towards community service.\n\nWarm Regards,",
        ],
        'donation_verified' => [
            'name'    => 'Donation Verified',
            'subject' => 'Donation Verification Update – ' . CLUB_NAME,
            'body'    => "Dear {recipient_name},\n\nYour donation has been successfully verified by our team.\n\nWe sincerely appreciate your contribution towards community service and social impact.\n\nWarm Regards,",
        ],
        'donation_contacted' => [
            'name'    => 'Donation Under Review',
            'subject' => 'Donation Update – ' . CLUB_NAME,
            'body'    => "Dear {recipient_name},\n\nYour donation is currently under review by our team.\n\nWe will update you once the verification is complete.\n\nWarm Regards,",
        ],
        'donation_completed' => [
            'name'    => 'Donation Completed',
            'subject' => 'Donation Completed – ' . CLUB_NAME,
            'body'    => "Dear {recipient_name},\n\nYour donation has been successfully completed and processed.\n\nThank you for your generous support towards " . CLUB_NAME . ".\n\nWarm Regards,",
        ],
        'donation_rejected' => [
            'name'    => 'Donation Rejected',
            'subject' => 'Donation Update – ' . CLUB_NAME,
            'body'    => "Dear {recipient_name},\n\nUnfortunately, your donation could not be processed at this time.\n\nIf you have any questions, please contact us.\n\nWarm Regards,",
        ],
        'password_reset' => [
            'name'    => 'Password Reset',
            'subject' => 'Password Reset – ' . CLUB_NAME . ' Admin',
            'body'    => "Hello {recipient_name},\n\nWe received a request to reset your admin password.\n\n{reset_link_html}\n\nThis link expires in 30 minutes. If you did not request this, please ignore this email.\n\nWarm Regards,",
        ],
        'password_changed' => [
            'name'    => 'Password Changed Notification',
            'subject' => 'Your Admin Password Was Changed – ' . CLUB_NAME,
            'body'    => "Hi {recipient_name},\n\nYour admin account password for " . CLUB_NAME . " was just changed.\n\nIf you made this change, no further action is needed.\n\nIf you did NOT make this change, please contact the club administrator immediately.\n\nWarm Regards,",
        ],
        'admin_created' => [
            'name'    => 'Admin Account Created',
            'subject' => 'Your Admin Account – ' . CLUB_NAME,
            'body'    => "Dear {recipient_name},\n\nAn admin account has been created for you on the " . CLUB_NAME . " Management Platform.\n\nEmail: {admin_email}\nPassword: {admin_password}\n\nPlease login and change your password immediately.\n\nWarm Regards,",
        ],
    ];
}

/**
 * Get a specific template by key.
 */
function commGetTemplate($key) {
    $templates = commGetTemplates();
    return $templates[$key] ?? null;
}

/**
 * Render a template with variable substitution.
 */
function commRenderTemplate($key, $vars = []) {
    $template = commGetTemplate($key);
    if (!$template) return null;

    $subject = $template['subject'];
    $body    = $template['body'];

    foreach ($vars as $k => $v) {
        $subject = str_replace('{' . $k . '}', $v, $subject);
        $body    = str_replace('{' . $k . '}', $v, $body);
    }

    return ['subject' => $subject, 'body' => $body];
}

/**
 * Build Email Composer URL with pre-filled parameters.
 */
function commComposerUrl($params = []) {
    $base = 'email_composer.php';
    $defaults = ['module' => 'System', 'action' => 'Email Sent', 'skip_url' => 'dashboard.php'];
    $merged = array_merge($defaults, $params);
    return $base . '?' . http_build_query($merged);
}
