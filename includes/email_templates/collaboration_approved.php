<?php
require_once __DIR__ . '/../config/club_settings.php';

function getCollaborationApprovedContent($name) {
    $subject = 'Collaboration Proposal Approved – ' . CLUB_NAME;

    $body = "Dear $name,\n\n"
          . "Thank you for your interest in collaborating with " . CLUB_NAME . ".\n\n"
          . "We are pleased to inform you that your collaboration proposal has been approved.\n\n"
          . "Our team will contact you shortly regarding the next steps.\n\n"
          . "Warm Regards,\n"
          . CLUB_NAME;

    return ['subject' => $subject, 'body' => $body];
}
