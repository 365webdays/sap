<?php
/**
 * POST /api/admin/email/send
 *
 * Send a bulk announcement and log the outcome.
 * Body: { group, subject, body }
 *
 * The body is HTML from the admin's compose form. It is not re-escaped here
 * because the admin is a trusted sender; the EmailTemplate wraps it in the
 * parish shell.
 */

return function (): void {
    $admin = Auth::require(Token::ROLE_ADMIN);

    $v = Validator::fromJsonBody();
    $group = $v->inList('group', 'Recipient group', BulkMail::GROUPS);
    $subject = $v->string('subject', 'Subject', 1, 255);
    $body = $v->string('body', 'Message', 1, 20000);
    $v->stopIfInvalid();

    $recipients = BulkMail::recipients($group);

    if ($recipients === []) {
        Response::error('There are no adorers in that group to email.', 422);
    }

    // Refuse to send when SMTP is not configured, rather than logging a send
    // with zero delivered and confusing the admin.
    if (!Mailer::isConfigured()) {
        Response::error(
            'Email sending is not configured on the server. ' .
            'Set SMTP_HOST, SMTP_USER, and SMTP_PASS before sending announcements.',
            503
        );
    }

    set_time_limit(120); // Generous, since each SMTP send is a network call.
    $result = BulkMail::send($recipients, $group, $subject, $body, (int) $admin['id']);

    Response::success([
        'sent' => $result['sent'],
        'failed' => $result['failed'],
        'recipient_count' => count($recipients),
        'failures' => $result['failures'],
        'message' => $result['failed'] === 0
            ? "Sent to {$result['sent']} adorer(s)."
            : "Sent to {$result['sent']} of " . count($recipients)
                . " adorer(s); {$result['failed']} failed.",
    ], 201);
};
