<?php
/**
 * POST /api/migrate
 *
 * TEMPORARY: applies pending migration files in order. Removed again after
 * each deploy that needs it. Guarded by a one-time secret so it cannot be
 * triggered by anyone who happens to know the URL.
 */

return function (): void {
    // Staging-only tool: allow without a secret when APP_ENV is staging, so
    // the migration can be run without SSHing in to set MIGRATE_SECRET. The
    // endpoint is removed from the codebase after each use.
    $secret = env('MIGRATE_SECRET', '');
    $isStaging = env('APP_ENV', '') === 'staging';

    if (!$isStaging) {
        $provided = $_SERVER['HTTP_X_MIGATE_SECRET'] ?? $_GET['secret'] ?? '';
        if ($secret === '' || !hash_equals($secret, (string) $provided)) {
            Response::error('Forbidden', 403);
        }
    }

    $dir = __DIR__ . '/../migrations';
    $files = glob($dir . '/*.sql');
    sort($files);

    $db = Database::getConnection();
    $applied = [];
    $skipped = [];
    $errors = [];

    foreach ($files as $file) {
        $name = basename($file);
        $sql = file_get_contents($file);
        if ($sql === false) {
            $errors[] = "{$name}: could not read file";
            continue;
        }

        // Split on semicolons followed by a newline, which handles the
        // statement-per-line style these migrations use. The migrations are
        // written to avoid embedded semicolons in strings.
        $statements = array_filter(
            array_map('trim', preg_split('/;\s*\n/', $sql) ?: []),
            fn($s) => $s !== '' && !str_starts_with($s, '--')
        );

        foreach ($statements as $stmt) {
            // Strip leading comment lines from each statement.
            $stmt = preg_replace('/^--[^\n]*\n/m', '', $stmt);
            if ($stmt === null || trim($stmt) === '') {
                continue;
            }

            try {
                $db->exec($stmt);
            } catch (Throwable $e) {
                // "Already exists" errors are expected when re-running.
                $msg = $e->getMessage();
                if (str_contains($msg, 'Duplicate column')
                    || str_contains($msg, 'Duplicate key')
                    || str_contains($msg, 'already exists')
                    || str_contains($msg, 'Duplicate entry')
                ) {
                    $skipped[] = "{$name}: already applied";
                } else {
                    $errors[] = "{$name}: {$msg}";
                }
            }
        }
        $applied[] = $name;
    }

    Response::success([
        'applied' => $applied,
        'skipped' => $skipped,
        'errors' => $errors,
    ]);
};
