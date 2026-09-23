<?php
require_once __DIR__ . '/../config/club_settings.php';

function getDonationContactedContent($donorName, $updatedBy = '', $updatedOn = '') {
    $subject = 'Donation Status: Contacted – ' . CLUB_NAME;

    $body = "Dear $donorName,\n\n"
          . "Thank you for your generous donation.\n\n"
          . "Our team has reviewed your contribution and may contact you shortly for further coordination.\n\n"
          . "We truly value your support.\n\n"
          . ($updatedBy ? "Updated By: $updatedBy\n" : '')
          . ($updatedOn ? "Updated On: $updatedOn\n\n" : "\n")
          . "Warm Regards,\n"
          . CLUB_NAME;

    return ['subject' => $subject, 'body' => $body];
}
