<?php
require_once __DIR__ . '/../config/club_settings.php';

function getDonationReceivedContent($donorName, $updatedBy = '', $updatedOn = '') {
    $subject = 'Donation Received – ' . CLUB_NAME;

    $body = "Dear $donorName,\n\n"
          . "We are happy to inform you that your donation has been officially received by " . CLUB_NAME . ".\n\n"
          . "Your generosity helps us make a meaningful difference in our community.\n\n"
          . ($updatedBy ? "Updated By: $updatedBy\n" : '')
          . ($updatedOn ? "Updated On: $updatedOn\n\n" : "\n")
          . "Warm Regards,\n"
          . CLUB_NAME;

    return ['subject' => $subject, 'body' => $body];
}
