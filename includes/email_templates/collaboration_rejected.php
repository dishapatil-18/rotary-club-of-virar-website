<?php
require_once __DIR__ . '/../config/club_settings.php';

function getCollaborationRejectedContent($name) {
    $subject = 'Collaboration Proposal Update – ' . CLUB_NAME;

    $body = "Dear $name,\n\n"
          . "Thank you for your interest in collaborating with " . CLUB_NAME . ".\n\n"
          . "After careful review, we regret to inform you that your proposal has not been approved at this time.\n\n"
          . "We appreciate your support and interest in our initiatives.\n\n"
          . "Warm Regards,\n"
          . CLUB_NAME;

    return ['subject' => $subject, 'body' => $body];
}
