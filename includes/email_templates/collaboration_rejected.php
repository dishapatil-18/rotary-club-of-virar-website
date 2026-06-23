<?php

function getCollaborationRejectedContent($name) {
    $subject = 'Collaboration Proposal Update – Rotary Club of Virar';

    $body = "Dear $name,\n\n"
          . "Thank you for your interest in collaborating with Rotary Club of Virar.\n\n"
          . "After careful review, we regret to inform you that your proposal has not been approved at this time.\n\n"
          . "We appreciate your support and interest in our initiatives.\n\n"
          . "Warm Regards,\n"
          . "Rotary Club of Virar";

    return ['subject' => $subject, 'body' => $body];
}
