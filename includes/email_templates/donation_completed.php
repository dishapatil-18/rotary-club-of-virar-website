<?php
require_once __DIR__ . '/../config/club_settings.php';

function getDonationCompletedContent($donorName, $updatedBy = '', $updatedOn = '') {
    $subject = 'Donation Process Completed – ' . CLUB_NAME;

    $body = "Dear $donorName,\n\n"
          . "We are pleased to inform you that the donation process has been completed successfully.\n\n"
          . "On behalf of " . CLUB_NAME . ", thank you for your generous contribution. "
          . "Your support enables us to continue our service initiatives and create lasting impact in the community.\n\n"
          . ($updatedBy ? "Updated By: $updatedBy\n" : '')
          . ($updatedOn ? "Updated On: $updatedOn\n\n" : "\n")
          . "Warm Regards,\n"
          . CLUB_NAME;

    return ['subject' => $subject, 'body' => $body];
}
