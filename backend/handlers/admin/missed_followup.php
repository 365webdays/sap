<?php
/**
 * POST /api/admin/missed/followup
 *
 * Marks a missed hour as followed up, or clears the mark.
 * Body: { user_id, schedule_id, missed_date, note?, followed_up? }
 *
 * followed_up defaults to true; send false to undo.
 */

return function (): void {
    $admin = Auth::require(Token::ROLE_ADMIN);

    $v = Validator::fromJsonBody();
    $raw = $v->all();

    $userId = (int) ($raw['user_id'] ?? 0);
    $scheduleId = (int) ($raw['schedule_id'] ?? 0);
    $missedDate = AdminQuery::asDate((string) ($raw['missed_date'] ?? ''));

    if ($userId <= 0 || $scheduleId <= 0) {
        Response::error('A user id and schedule id are required', 422);
    }
    if ($missedDate === null) {
        Response::error('missed_date must be a valid YYYY-MM-DD date', 422);
    }

    $note = $v->optionalString('note', 'Note', 500);
    $followedUp = $v->has('followed_up') ? $v->boolean('followed_up', 'Followed up') : true;
    $v->stopIfInvalid();

    // Confirm the pair actually belongs together before recording anything, so
    // a malformed request cannot create a follow-up for an unrelated schedule.
    $check = Database::getConnection()->prepare(
        'SELECT 1 FROM adoration_schedules WHERE id = :sid AND user_id = :uid LIMIT 1'
    );
    $check->execute(['sid' => $scheduleId, 'uid' => $userId]);
    if ($check->fetchColumn() === false) {
        Response::error('That schedule does not belong to that adorer', 422);
    }

    try {
        if ($followedUp) {
            MissedAttendance::markFollowedUp(
                $userId,
                $scheduleId,
                $missedDate->format('Y-m-d'),
                (int) $admin['id'],
                $note
            );
        } else {
            MissedAttendance::clearFollowUp($userId, $scheduleId, $missedDate->format('Y-m-d'));
        }
    } catch (Throwable $e) {
        error_log('missed followup: ' . $e->getMessage());
        Response::error('Could not update the follow-up. Please try again.', 500);
    }

    Response::success([
        'followed_up' => $followedUp,
        'message' => $followedUp ? 'Marked as followed up.' : 'Follow-up cleared.',
    ]);
};
