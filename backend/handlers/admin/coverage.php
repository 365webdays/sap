<?php
/**
 * GET /api/admin/coverage?only_gaps=1
 *
 * Every day/hour slot with the adorers assigned to it. Slots with nobody
 * assigned are the coverage gaps the parish needs to fill.
 */

return function (): void {
    Auth::require(Token::ROLE_ADMIN);

    $slots = AdminStats::coverage();

    $gaps = array_values(array_filter($slots, fn(array $s) => $s['active_count'] === 0));

    if ((string) ($_GET['only_gaps'] ?? '') === '1') {
        $slots = $gaps;
    }

    Response::success([
        'slots' => $slots,
        'total_slots' => count(Schedule::DAYS) * count(Schedule::timeSlots()),
        'covered_slots' => count(Schedule::DAYS) * count(Schedule::timeSlots()) - count($gaps),
        'gap_count' => count($gaps),
        'days' => Schedule::DAYS,
        'time_slots' => array_map(
            fn(string $s) => ['value' => $s, 'label' => Schedule::label($s)],
            Schedule::timeSlots()
        ),
    ]);
};
