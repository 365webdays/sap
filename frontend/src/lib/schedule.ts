/**
 * Frontend mirror of backend/lib/Schedule.php.
 *
 * Used for immediate UI rendering (e.g. option labels) without a round-trip.
 * The server is still the source of truth — registration validates against
 * /api/schedule/options, which is built from the backend constants.
 */

export const DAYS = [
  "Sunday",
  "Monday",
  "Tuesday",
  "Wednesday",
  "Thursday",
  "Friday",
  "Saturday",
] as const;

/** The 24 hourly slots as stored in adoration_schedules.time_slot (HH:00:00). */
export const TIME_SLOTS: string[] = Array.from({ length: 24 }, (_, h) =>
  `${h.toString().padStart(2, "0")}:00:00`
);

/** Human label for a slot, e.g. '08:00:00' => '8:00 AM'. */
export function slotLabel(timeSlot: string): string {
  const match = /^(\d{2}):00:00$/.exec(timeSlot);
  if (!match) return timeSlot;
  const hour = parseInt(match[1], 10);
  const period = hour < 12 ? "AM" : "PM";
  const display = hour % 12 === 0 ? 12 : hour % 12;
  return `${display}:00 ${period}`;
}
