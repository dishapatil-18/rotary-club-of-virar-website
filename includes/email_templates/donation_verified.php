<?php
require_once __DIR__ . '/../config/club_settings.php';

function getDonationVerifiedContent($donorName, $updatedBy = '', $updatedOn = '') {
    $subject = 'Donation Verification Update – ' . CLUB_NAME;

    $body = "Dear $donorName,\n\n"
          . "Thank you for supporting " . CLUB_NAME . ".\n\n"
          . "Your donation has been successfully verified by our team.\n\n"
          . "We sincerely appreciate your contribution towards community service and social impact.\n\n"
          . ($updatedBy ? "Updated By: $updatedBy\n" : '')
          . ($updatedOn ? "Updated On: $updatedOn\n\n" : "\n")
          . "Warm Regards,\n"
          . CLUB_NAME;

    return ['subject' => $subject, 'body' => $body];
}
