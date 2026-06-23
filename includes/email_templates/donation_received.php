<?php

function getDonationReceivedContent($donorName) {
    $subject = 'Donation Received – Rotary Club of Virar';

    $body = "Dear $donorName,\n\n"
          . "We are happy to inform you that your donation has been officially received by Rotary Club of Virar.\n\n"
          . "Your generosity helps us make a meaningful difference in our community.\n\n"
          . "Warm Regards,\n"
          . "Rotary Club of Virar";

    return ['subject' => $subject, 'body' => $body];
}
