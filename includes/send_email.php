<?php

require_once __DIR__ . '/email_config.php';
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';
require_once __DIR__ . '/phpmailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendEmail($to, $subject, $body) {
    $mail = new PHPMailer(true);
    $result = ['success' => false, 'error' => ''];

    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = nl2br($body);
        $mail->AltBody = strip_tags($body);

        $mail->send();
        $result['success'] = true;

    } catch (Exception $e) {
        $result['error'] = $mail->ErrorInfo;
        logEmailFailure($to, $subject, $result['error']);
    }

    return $result;
}

function logEmailFailure($to, $subject, $error) {
    if (!EMAIL_LOG_ENABLED) return;

    $logDir = dirname(EMAIL_LOG_FILE);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    $entry = sprintf(
        "[%s] TO: %s | SUBJECT: %s | ERROR: %s\n",
        date('Y-m-d H:i:s'),
        $to,
        $subject,
        $error
    );

    file_put_contents(EMAIL_LOG_FILE, $entry, FILE_APPEND | LOCK_EX);
}
