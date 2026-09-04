<?php
/**
 * Cron: Pre-adoration hour reminder
 *
 * Sends a reminder email to active adorers whose scheduled hour starts within
 * the next REMINDER_LEAD_MINUTES (default 60). Designed to run every 15
 * minutes from the cPanel cron scheduler; the sent_reminders table dedupes
 * so multiple runs in the same window don't send twice.
 *
 * Schedule in cPanel (every 15 minutes):
 *   0,15,30,45 * * * * /usr/local/bin/php /home/USER/public_html/staging/api/cron/hour_reminder.php
 *
 * Or hourly:
 *   0 * * * * /usr/local/bin/php /home/USER/public_html/staging/api/cron/hour_reminder.php
 */

require_once __DIR__ . '/../lib/CronBootstrap.php';
cron_bootstrap(__DIR__ . '/..');

if (!Mailer::isConfigured()) {
    fwrite(STDERR, "hour_reminder: SMTP not configured — skipping\n");
    exit(0);
}

// How far ahead to look for upcoming hours. Default 60 minutes.
$leadMinutes = (int) env('REMINDER_LEAD_MINUTES', '60');
if ($leadMinutes <= 0) {
    $leadMinutes = 60;
}

// A small backward grace so a cron firing a few minutes after the top of the
// hour still catches a slot that just started. This is not configurable — it
// only matters when the cron jitter exceeds a couple of minutes.
$graceMinutes = 5;

$now = new DateTimeImmutable('now');
$windowStart = $now->modify("-{$graceMinutes} minutes");
$windowEnd = $now->modify("+{$leadMinutes} minutes");

$today = $now->format('l');           // e.g. "Monday"
$todayDate = $now->format('Y-m-d');

// Active adorers with hour_reminders enabled who have a schedule today.
$db = Database::getConnection();
$stmt = $db->prepare(
    'SELECT s.id AS schedule_id, s.user_id, s.time_slot,
            u.full_name, u.email
     FROM adoration_schedules s
     JOIN users u ON u.id = s.user_id AND u.is_active = 1
     JOIN email_preferences ep ON ep.user_id = u.id
     WHERE s.day_of_week = :day AND ep.hour_reminders = 1'
);
$stmt->execute(['day' => $today]);
$schedules = $stmt->fetchAll();

$sent = 0;
$skipped = 0;
$failed = 0;

foreach ($schedules as $sched) {
    // The slot start time today, e.g. "2026-09-04 10:00:00".
    $hour = (int) substr((string) $sched['time_slot'], 0, 2);
    $slotStart = $now->setTime($hour, 0, 0);

    // Only remind for hours starting within the window.
    if ($slotStart < $windowStart || $slotStart >= $windowEnd) {
        continue;
    }

    $userId = (int) $sched['user_id'];
    $timeSlot = (string) $sched['time_slot'];

    // Dedup: one reminder per user per scheduled hour per day.
    if (SentReminders::alreadySent($userId, SentReminders::TYPE_HOUR, $todayDate, $timeSlot)) {
        $skipped++;
        continue;
    }

    $template = EmailTemplate::hourReminder(
        (string) $sched['full_name'],
        $today,
        $timeSlot
    );

    $ok = Mailer::send(
        (string) $sched['email'],
        (string) $sched['full_name'],
        $template['subject'],
        $template['html'],
        $template['text']
    );

    if ($ok) {
        SentReminders::log($userId, SentReminders::TYPE_HOUR, $todayDate, $timeSlot);
        $sent++;
    } else {
        $failed++;
    }
}

// Keep the dedup table from growing forever.
SentReminders::prune(90);

echo "hour_reminder: sent={$sent} skipped={$skipped} failed={$failed} window={$windowStart->format('H:i')}-{$windowEnd->format('H:i')} day={$today}\n";
