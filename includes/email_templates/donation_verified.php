<?php

function getDonationVerifiedContent($donorName) {
    $subject = 'Donation Verification Update – Rotary Club of Virar';

    $body = "Dear $donorName,\n\n"
          . "Thank you for supporting Rotary Club of Virar.\n\n"
          . "Your donation has been successfully verified by our team.\n\n"
          . "We sincerely appreciate your contribution towards community service and social impact.\n\n"
          . "Warm Regards,\n"
          . "Rotary Club of Virar";

    return ['subject' => $subject, 'body' => $body];
}
