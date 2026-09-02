<?php
/**
 * POST /api/auth/register
 *
 * Creates an adorer account with its assigned adoration hour and default
 * notification preferences, then returns a signed token so the client is
 * logged in immediately.
 */

return function (): void {
    $v = Validator::fromJsonBody();

    $fullName = $v->string('full_name', 'Full name', 2, 255);
    $email = $v->email('email', 'Email address');
    $mobile = $v->optionalString('mobile_number', 'Mobile number', 32);
    $password = $v->password('password', 'Password');
    $day = $v->inList('day_of_week', 'Adoration day', Schedule::DAYS);
    $timeSlot = $v->inList('time_slot', 'Adoration time', Schedule::timeSlots());
    $v->accepted('privacy_consent', 'The privacy consent');

    $v->stopIfInvalid();

    $db = Database::getConnection();

    // Pre-check for a friendlier field-level error than a raw constraint
    // violation. The unique index below is still the source of truth.
    $exists = $db->prepare('SELECT 1 FROM users WHERE email = :email LIMIT 1');
    $exists->execute(['email' => $email]);
    if ($exists->fetchColumn() !== false) {
        http_response_code(409);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'That email address is already registered',
            'fields' => ['email' => 'That email address is already registered'],
        ]);
        exit;
    }

    $passwordHash = password_hash($password, PASSWORD_BCRYPT);

    try {
        $db->beginTransaction();

        $insertUser = $db->prepare(
            'INSERT INTO users
                (full_name, email, mobile_number, password_hash, privacy_consent, is_active)
             VALUES (:full_name, :email, :mobile_number, :password_hash, 1, 1)'
        );
        $insertUser->execute([
            'full_name' => $fullName,
            'email' => $email,
            'mobile_number' => $mobile,
            'password_hash' => $passwordHash,
        ]);

        $userId = (int) $db->lastInsertId();

        $insertSchedule = $db->prepare(
            'INSERT INTO adoration_schedules (user_id, day_of_week, time_slot)
             VALUES (:user_id, :day_of_week, :time_slot)'
        );
        $insertSchedule->execute([
            'user_id' => $userId,
            'day_of_week' => $day,
            'time_slot' => $timeSlot,
        ]);

        // Opt everything in by default; the adorer can change this later.
        $insertPrefs = $db->prepare(
            'INSERT INTO email_preferences
                (user_id, hour_reminders, announcements, attendance_notifications)
             VALUES (:user_id, 1, 1, 1)'
        );
        $insertPrefs->execute(['user_id' => $userId]);

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        // A concurrent request may have taken the email between the check
        // above and the insert.
        if ($e instanceof PDOException && $e->getCode() === '23000') {
            Response::error('That email address is already registered', 409);
        }

        error_log('register: ' . $e->getMessage());
        Response::error('Registration failed. Please try again.', 500);
    }

    // Best-effort welcome email — never fail the registration over this.
    $welcome = EmailTemplate::welcome($fullName, $day, $timeSlot);
    $emailSent = Mailer::send(
        $email,
        $fullName,
        $welcome['subject'],
        $welcome['html'],
        $welcome['text']
    );

    Response::success([
        'token' => Token::issue($userId, Token::ROLE_ADORER),
        'user' => [
            'id' => $userId,
            'full_name' => $fullName,
            'email' => $email,
            'mobile_number' => $mobile,
            'role' => Token::ROLE_ADORER,
        ],
        'schedule' => [
            'day_of_week' => $day,
            'time_slot' => $timeSlot,
            'label' => $day . ' at ' . Schedule::label($timeSlot),
        ],
        'welcome_email_sent' => $emailSent,
    ], 201);
};
