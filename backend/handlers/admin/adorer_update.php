<?php
/**
 * PUT /api/admin/adorer
 *
 * Edit an adorer's details, active status, and assigned hours.
 * Body: { id, full_name?, email?, mobile_number?, is_active?, schedules?: [{day_of_week, time_slot}] }
 *
 * Only the keys present are changed, so the admin UI can send a partial patch.
 */

return function (): void {
    $admin = Auth::require(Token::ROLE_ADMIN);

    $v = Validator::fromJsonBody();
    $raw = $v->all();

    $id = (int) ($raw['id'] ?? 0);
    if ($id <= 0) {
        Response::error('An adorer id is required', 422);
    }

    $db = Database::getConnection();

    $exists = $db->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
    $exists->execute(['id' => $id]);
    if ($exists->fetchColumn() === false) {
        Response::error('Adorer not found', 404);
    }

    $fields = [];
    $params = ['id' => $id];

    if (array_key_exists('full_name', $raw)) {
        $name = $v->string('full_name', 'Full name', 2, 255);
        $fields[] = 'full_name = :full_name';
        $params['full_name'] = $name;
    }

    if (array_key_exists('email', $raw)) {
        $email = $v->email('email', 'Email address');
        $fields[] = 'email = :email';
        $params['email'] = $email;
    }

    if (array_key_exists('mobile_number', $raw)) {
        $fields[] = 'mobile_number = :mobile_number';
        $params['mobile_number'] = $v->optionalString('mobile_number', 'Mobile number', 32);
    }

    if (array_key_exists('is_active', $raw)) {
        $fields[] = 'is_active = :is_active';
        $params['is_active'] = (int) (bool) $v->boolean('is_active', 'Active status');
    }

    // Schedules are replaced wholesale when provided.
    $schedules = null;
    if (array_key_exists('schedules', $raw)) {
        if (!is_array($raw['schedules'])) {
            Response::error('Schedules must be a list', 422);
        }

        $schedules = [];
        foreach ($raw['schedules'] as $i => $entry) {
            $day = is_array($entry) ? ($entry['day_of_week'] ?? null) : null;
            $slot = is_array($entry) ? ($entry['time_slot'] ?? null) : null;

            if (!is_string($day) || !in_array($day, Schedule::DAYS, true)) {
                Response::error("Schedule #" . ($i + 1) . " has an invalid day", 422);
            }
            if (!is_string($slot) || !in_array($slot, Schedule::timeSlots(), true)) {
                Response::error("Schedule #" . ($i + 1) . " has an invalid time", 422);
            }
            $schedules[$day . '|' . $slot] = ['day_of_week' => $day, 'time_slot' => $slot];
        }
        // Keyed above, so duplicate day/time pairs collapse.
        $schedules = array_values($schedules);
    }

    $v->stopIfInvalid();

    if ($fields === [] && $schedules === null) {
        Response::error('Nothing to update', 422);
    }

    try {
        $db->beginTransaction();

        if ($fields !== []) {
            $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id';
            $db->prepare($sql)->execute($params);
        }

        if ($schedules !== null) {
            // Existing attendance rows point at these schedules; the FK is ON
            // DELETE SET NULL, so history survives as off-schedule visits
            // rather than disappearing.
            $db->prepare('DELETE FROM adoration_schedules WHERE user_id = :id')
               ->execute(['id' => $id]);

            $insert = $db->prepare(
                'INSERT INTO adoration_schedules (user_id, day_of_week, time_slot)
                 VALUES (:user_id, :day_of_week, :time_slot)'
            );
            foreach ($schedules as $s) {
                $insert->execute([
                    'user_id' => $id,
                    'day_of_week' => $s['day_of_week'],
                    'time_slot' => $s['time_slot'],
                ]);
            }
        }

        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        if ($e instanceof PDOException && $e->getCode() === '23000') {
            Response::error('That email address is already in use', 409);
        }

        error_log('admin adorer update: ' . $e->getMessage());
        Response::error('Could not save the changes. Please try again.', 500);
    }

    error_log(sprintf(
        'admin %d updated adorer %d (%s)',
        (int) $admin['id'],
        $id,
        implode(', ', array_keys(array_diff_key($params, ['id' => null])))
    ));

    Response::success([
        'id' => $id,
        'schedules' => AdminQuery::schedulesFor([$id])[$id] ?? [],
        'message' => 'Adorer updated.',
    ]);
};
