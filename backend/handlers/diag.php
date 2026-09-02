<?php
/**
 * GET /api/diag — TEMPORARY diagnostic endpoint.
 * Checks table existence and schema. DELETE before go-live.
 */
return function (): void {
    header('Content-Type: text/plain; charset=utf-8');

    echo "=== DB config ===\n";
    echo "DB_HOST=" . (env('DB_HOST', '') ?: '(empty)') . "\n";
    echo "DB_NAME=" . (env('DB_NAME', '') ?: '(empty)') . "\n";
    echo "DB_USER=" . (env('DB_USER', '') ?: '(empty)') . "\n";
    echo "DB_PASS=" . (env('DB_PASS', '') ? '***set***' : '(empty)') . "\n";
    echo "JWT_SECRET=" . (strlen(env('JWT_SECRET', '') ?? '') >= 32 ? '***ok***' : '***SHORT/MISSING***') . "\n\n";

    try {
        $dsn = "mysql:host=" . env('DB_HOST', 'localhost') . ";dbname=" . env('DB_NAME', '') . ";charset=utf8mb4";
        $db = new PDO($dsn, env('DB_USER', ''), env('DB_PASS', ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        echo "=== DB connection: OK ===\n\n";

        echo "=== Tables ===\n";
        $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        if (empty($tables)) {
            echo "(no tables — migrations have NOT been run)\n";
        } else {
            foreach ($tables as $t) {
                try {
                    $count = $db->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
                    echo "  {$t} ({$count} rows)\n";
                } catch (Throwable $e) {
                    echo "  {$t} (error: {$e->getMessage()})\n";
                }
            }
        }

        foreach (['users', 'admins'] as $tbl) {
            if (in_array($tbl, $tables)) {
                echo "\n=== {$tbl} columns ===\n";
                $cols = $db->query("SHOW COLUMNS FROM {$tbl}")->fetchAll();
                foreach ($cols as $c) {
                    echo "  {$c['Field']} {$c['Type']}\n";
                }
            }
        }
    } catch (Throwable $e) {
        echo "=== DB ERROR ===\n" . $e->getMessage() . "\n";
    }

    exit;
};
