<?php
/**
 * Cron: Missed attendance notification
 *
 * Runs once daily (shortly after midnight) to notify adorers who missed their
 * scheduled hour(s) yesterday. One email per adorer listing all their missed
 * hours for that day, sent only if they have attendance_notifications enabled.
 *
 * Running after midnight (not at end of day) ensures every hour of the
 * previous day has fully elapsed before we evaluate it.
 *
 * Schedule in cPanel (daily at 00:30):
 *   30 0 * * * /usr/local/bin/php /home/USER/public_html/staging/api/cron/missed_notification.php
 */

require_once __DIR__ . '/../lib/CronBootstrap.php';
cron_bootstrap(__DIR__ . '/..');

if (!Mailer::isConfigured()) {
    fwrite(STDERR, "missed_notification: SMTP not configured — skipping\n");
    exit(0);
}

$now = new DateTimeImmutable('now');
$yesterday = $now->modify('-1 day')->setTime(0, 0);
$yesterdayDate = $yesterday->format('Y-m-d');
$yesterdayLabel = $yesterday->format('l, F j, Y');

// Reuse the same missed-hour detection the admin report uses.
$missed = MissedAttendance::between($yesterday, $yesterday, $now);

if ($missed === []) {
    echo "missed_notification: no missed hours for {$yesterdayDate}\n";
    exit(0);
}

// Group missed hours by user — one email per adorer.
$byUser = [];
foreach ($missed as $record) {
    $userId = (int) $record['user_id'];
    $byUser[$userId][] = $record;
}

// Batch-fetch the attendance_notifications preference for all affected users.
$userIds = array_keys($byUser);
$placeholders = implode(',', array_map('intval', $userIds));
$prefStmt = Database::getConnection()->prepare(
    "SELECT user_id, attendance_notifications
     FROM email_preferences
     WHERE user_id IN ({$placeholders})"
);
$prefStmt->execute();
$prefMap = [];
foreach ($prefStmt->fetchAll() as $row) {
    $prefMap[(int) $row['user_id']] = (bool) (int) $row['attendance_notifications'];
}

$sent = 0;
$skipped = 0;
$failed = 0;

foreach ($byUser as $userId => $hours) {
    // A missing preference row defaults to "on" (same convention as Preferences::forUser).
    $wantsNotification = $prefMap[$userId] ?? true;
    if (!$wantsNotification) {
        $skipped++;
        continue;
    }

    // Dedup: one missed notification per user per day.
    if (SentReminders::alreadySent($userId, SentReminders::TYPE_MISSED, $yesterdayDate)) {
        $skipped++;
        continue;
    }

    $first = $hours[0];

    // Build the list of missed hours for the template.
    $missedHours = array_map(fn(array $h) => [
        'day_of_week' => $h['day_of_week'],
        'time_label' => $h['time_label'],
    ], $hours);

    $template = EmailTemplate::missedNotification(
        (string) $first['full_name'],
        $yesterdayLabel,
        $missedHours
    );

    $ok = Mailer::send(
        (string) $first['email'],
        (string) $first['full_name'],
        $template['subject'],
        $template['html'],
        $template['text']
    );

    if ($ok) {
        SentReminders::log($userId, SentReminders::TYPE_MISSED, $yesterdayDate);
        $sent++;
    } else {
        $failed++;
    }
}

// Keep the dedup table bounded.
SentReminders::prune(90);

echo "missed_notification: sent={$sent} skipped={$skipped} failed={$failed} date={$yesterdayDate} candidates=" . count($byUser) . "\n";
