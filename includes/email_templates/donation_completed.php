<?php

function getDonationCompletedContent($donorName) {
    $subject = 'Donation Process Completed – Rotary Club of Virar';

    $body = "Dear $donorName,\n\n"
          . "We are pleased to inform you that the donation process has been completed successfully.\n\n"
          . "On behalf of Rotary Club of Virar, thank you for your generous contribution. "
          . "Your support enables us to continue our service initiatives and create lasting impact in the community.\n\n"
          . "Warm Regards,\n"
          . "Rotary Club of Virar";

    return ['subject' => $subject, 'body' => $body];
}
