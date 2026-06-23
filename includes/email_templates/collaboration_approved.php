<?php

function getCollaborationApprovedContent($name) {
    $subject = 'Collaboration Proposal Approved – Rotary Club of Virar';

    $body = "Dear $name,\n\n"
          . "Thank you for your interest in collaborating with Rotary Club of Virar.\n\n"
          . "We are pleased to inform you that your collaboration proposal has been approved.\n\n"
          . "Our team will contact you shortly regarding the next steps.\n\n"
          . "Warm Regards,\n"
          . "Rotary Club of Virar";

    return ['subject' => $subject, 'body' => $body];
}
