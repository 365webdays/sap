<?php
/**
 * Adoration schedule vocabulary.
 *
 * Days and hourly slots are fixed options, kept here so registration
 * validation and any future scheduling endpoints agree on the same set.
 * The frontend mirrors this list in src/lib/schedule.ts.
 */

class Schedule
{
    public const DAYS = [
        'Sunday',
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
    ];

    /**
     * The 24 hourly slots as stored in adoration_schedules.time_slot (TIME).
     *
     * @return list<string> e.g. ['00:00:00', '01:00:00', ... '23:00:00']
     */
    public static function timeSlots(): array
    {
        $slots = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $slots[] = sprintf('%02d:00:00', $hour);
        }
        return $slots;
    }

    /** Human label for a slot, e.g. '08:00:00' => '8:00 AM'. */
    public static function label(string $timeSlot): string
    {
        $ts = strtotime($timeSlot);
        return $ts === false ? $timeSlot : date('g:i A', $ts);
    }
}
