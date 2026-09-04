<?php
/**
 * Shared bootstrap for cron scripts.
 *
 * Cron scripts are invoked by the GoDaddy cPanel cron scheduler via the PHP
 * CLI, not over HTTP. They need the same environment, autoloader, core
 * libraries, and timezone as index.php — but not the CORS headers, router,
 * or HTTP response handling.
 *
 * Each cron script calls cron_bootstrap() with the path to the API directory
 * (the one containing .env, vendor/, lib/, etc.), then does its work and
 * prints a one-line summary to stdout for the cron log.
 *
 * @param string $apiDir Absolute path to the API directory (where .env lives)
 */

function cron_bootstrap(string $apiDir): void
{
    require_once $apiDir . '/config/env.php';
    load_env($apiDir . '/.env');

    $autoload = $apiDir . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        fwrite(STDERR, "Cron: vendor/autoload.php not found in {$apiDir} — run composer install\n");
        exit(1);
    }
    require_once $autoload;

    // Core libraries needed by the cron jobs. Keep this in sync with index.php
    // minus the HTTP-only classes (Router, Auth, Validator, Response, Csv,
    // AdminStats, AdminQuery, BulkMail).
    $libs = [
        'Database.php',
        'Schedule.php',
        'Attendance.php',
        'Preferences.php',
        'Mailer.php',
        'EmailTemplate.php',
        'MissedAttendance.php',
        'SentReminders.php',
    ];
    foreach ($libs as $lib) {
        require_once $apiDir . '/lib/' . $lib;
    }

    // All date logic runs in parish local time, same as index.php.
    date_default_timezone_set(env('APP_TIMEZONE', 'America/Vancouver'));

    // Never dump errors to stdout (the cron log); write them to error_log
    // instead so the cron email stays clean.
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
}
