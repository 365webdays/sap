<?php
/**
 * GET /api/schedule/options
 *
 * Public list of selectable adoration days and hourly slots, so the
 * registration form and the server validate against the same vocabulary.
 */

return function (): void {
    $slots = [];
    foreach (Schedule::timeSlots() as $slot) {
        $slots[] = [
            'value' => $slot,
            'label' => Schedule::label($slot),
        ];
    }

    Response::success([
        'days' => Schedule::DAYS,
        'time_slots' => $slots,
    ]);
};
