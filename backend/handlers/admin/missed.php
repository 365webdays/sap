<?php
/**
 * GET /api/admin/missed?from=&to=&user_id=&followed_up=
 *
 * Adorers who did not check in during an assigned hour that has already
 * passed. Defaults to the last 30 days when no range is given.
 */

return function (): void {
    Auth::require(Token::ROLE_ADMIN);

    $now = new DateTimeImmutable('now');

    $to = AdminQuery::asDate((string) ($_GET['to'] ?? '')) ?? $now;
    $from = AdminQuery::asDate((string) ($_GET['from'] ?? '')) ?? $to->modify('-29 days');

    $userId = (int) ($_GET['user_id'] ?? 0);
    $records = MissedAttendance::between($from, $to, $now, $userId > 0 ? $userId : null);

    // 'yes' / 'no' narrow the list; anything else leaves it unfiltered.
    $followedUp = (string) ($_GET['followed_up'] ?? '');
    if ($followedUp === 'yes' || $followedUp === 'no') {
        $want = $followedUp === 'yes';
        $records = array_values(array_filter(
            $records,
            fn(array $r) => $r['followed_up'] === $want
        ));
    }

    $outstanding = count(array_filter($records, fn(array $r) => !$r['followed_up']));

    Response::success([
        'items' => $records,
        'range' => [
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
        ],
        'total' => count($records),
        'outstanding' => $outstanding,
    ]);
};
