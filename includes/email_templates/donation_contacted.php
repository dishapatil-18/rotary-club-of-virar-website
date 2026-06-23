<?php

function getDonationContactedContent($donorName) {
    $subject = 'Donation Status: Contacted – Rotary Club of Virar';

    $body = "Dear $donorName,\n\n"
          . "Thank you for your generous donation.\n\n"
          . "Our team has reviewed your contribution and may contact you shortly for further coordination.\n\n"
          . "We truly value your support.\n\n"
          . "Warm Regards,\n"
          . "Rotary Club of Virar";

    return ['subject' => $subject, 'body' => $body];
}
