<?php
/**
 * POST /api/admin/email/preview
 *
 * Resolve a recipient group and return the count and list without sending.
 * Body: { group: "all"|"active"|"inactive"|"missed" }
 */

return function (): void {
    Auth::require(Token::ROLE_ADMIN);

    $v = Validator::fromJsonBody();
    $group = $v->inList('group', 'Recipient group', BulkMail::GROUPS);
    $v->stopIfInvalid();

    $recipients = BulkMail::recipients($group);

    Response::success([
        'group' => $group,
        'recipient_count' => count($recipients),
        // Names only, not addresses — the admin needs to see who, not harvest
        // a mailing list from the preview endpoint.
        'recipients' => array_map(fn($r) => ['id' => $r['id'], 'full_name' => $r['full_name']], $recipients),
    ]);
};
