<?php
/**
 * Per-adorer email notification toggles.
 *
 * Every adorer gets a row at registration, but this reads defensively: a
 * missing row is treated as "everything on" and created on first write, so a
 * user imported outside the normal flow still behaves sensibly.
 */

class Preferences
{
    /** The toggle columns, in display order. */
    public const KEYS = [
        'hour_reminders',
        'announcements',
        'attendance_notifications',
    ];

    /**
     * @return array{hour_reminders:bool, announcements:bool, attendance_notifications:bool}
     */
    public static function forUser(int $userId): array
    {
        $stmt = Database::getConnection()->prepare(
            'SELECT hour_reminders, announcements, attendance_notifications
             FROM email_preferences
             WHERE user_id = :user_id
             LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();

        if ($row === false) {
            return array_fill_keys(self::KEYS, true);
        }

        $prefs = [];
        foreach (self::KEYS as $key) {
            $prefs[$key] = (bool) (int) $row[$key];
        }
        return $prefs;
    }

    /**
     * Replace all three toggles for a user, creating the row if needed.
     *
     * @param array<string, bool> $prefs Must contain every key in self::KEYS.
     * @return array<string, bool> The stored values.
     */
    public static function save(int $userId, array $prefs): array
    {
        $stmt = Database::getConnection()->prepare(
            'INSERT INTO email_preferences
                (user_id, hour_reminders, announcements, attendance_notifications)
             VALUES (:user_id, :hour_reminders, :announcements, :attendance_notifications)
             ON DUPLICATE KEY UPDATE
                hour_reminders = VALUES(hour_reminders),
                announcements = VALUES(announcements),
                attendance_notifications = VALUES(attendance_notifications)'
        );

        $stmt->execute([
            'user_id' => $userId,
            'hour_reminders' => (int) $prefs['hour_reminders'],
            'announcements' => (int) $prefs['announcements'],
            'attendance_notifications' => (int) $prefs['attendance_notifications'],
        ]);

        return self::forUser($userId);
    }
}
