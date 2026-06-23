<?php
require_once __DIR__ . '/includes/send_email.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Send POST request with email parameter.']);
    exit;
}

$email = trim($_POST['email'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Valid email address required.']);
    exit;
}

$result = sendEmail(
    $email,
    'Test Email – Rotary Club of Virar SMTP Configuration',
    'This is a test email to verify that the SMTP configuration is working correctly.<br><br>'
    . 'If you received this email, the SMTP setup is functioning properly.<br><br>'
    . 'Warm Regards,<br>Rotary Club of Virar'
);

echo json_encode($result);
