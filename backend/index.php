<?php
/**
 * Front controller / API entry point.
 * All /api/* requests are routed here via .htaccess.
 */

// Load environment variables
require_once __DIR__ . '/config/env.php';
load_env(__DIR__ . '/.env');

// Composer dependencies (firebase/php-jwt, phpmailer). Installed by CI and
// shipped with the deploy, so a missing vendor/ means a broken deployment.
$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'Server dependencies are missing',
    ]);
    exit;
}
require_once $autoload;

// Core libraries
require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/Response.php';
require_once __DIR__ . '/lib/Router.php';
require_once __DIR__ . '/lib/Validator.php';
require_once __DIR__ . '/lib/Token.php';
require_once __DIR__ . '/lib/Auth.php';
require_once __DIR__ . '/lib/Schedule.php';
require_once __DIR__ . '/lib/Attendance.php';
require_once __DIR__ . '/lib/Preferences.php';
require_once __DIR__ . '/lib/Mailer.php';
require_once __DIR__ . '/lib/EmailTemplate.php';
require_once __DIR__ . '/lib/Csv.php';
require_once __DIR__ . '/lib/AdminStats.php';
require_once __DIR__ . '/lib/AdminQuery.php';
require_once __DIR__ . '/lib/MissedAttendance.php';
require_once __DIR__ . '/lib/BulkMail.php';
require_once __DIR__ . '/lib/RateLimiter.php';

// All date logic (check-in day/hour matching, reminders) must run in parish
// local time, not the server's UTC. Set this before anything touches dates.
date_default_timezone_set(env('APP_TIMEZONE', 'America/Vancouver'));

// Never leak stack traces or SQL to the client; handlers return JSON errors.
ini_set('display_errors', '0');
error_reporting(E_ALL);

set_exception_handler(function (Throwable $e): void {
    error_log('Unhandled: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    Response::error('An unexpected error occurred', 500);
});

// CORS headers — allow staging, production, and localhost origins
$allowedOrigins = [
    'https://staging.stanthonyadoration.com',
    'https://stanthonyadoration.com',
    'http://localhost:5173',
    'http://127.0.0.1:5173',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: {$origin}");
    header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Build router and register routes.
// Paths are relative to the mount point (this app is served from /api), so
// '/health' here is reachable at https://<host>/api/health.
$router = new Router();

// --- Health check ---
$router->get('/health', require __DIR__ . '/handlers/health.php');

// --- Public reference data ---
$router->get('/schedule/options', require __DIR__ . '/handlers/schedule_options.php');

// --- Adorer auth ---
$router->post('/auth/register', require __DIR__ . '/handlers/auth/register.php');
$router->post('/auth/login', require __DIR__ . '/handlers/auth/login.php');
$router->get('/auth/me', require __DIR__ . '/handlers/auth/me.php');

// --- Admin auth ---
$router->post('/admin/login', require __DIR__ . '/handlers/admin/login.php');
$router->get('/admin/me', require __DIR__ . '/handlers/admin/me.php');

// Logout is client-side only: the token is discarded by the browser. There is
// no server-side session to clear, and tokens are short-lived.

// --- Adorer features ---
$router->get('/adorer/dashboard', require __DIR__ . '/handlers/adorer/dashboard.php');
$router->post('/adorer/checkin', require __DIR__ . '/handlers/adorer/checkin.php');
$router->get('/adorer/attendance', require __DIR__ . '/handlers/adorer/attendance.php');
$router->get('/adorer/preferences', require __DIR__ . '/handlers/adorer/preferences_get.php');
$router->put('/adorer/preferences', require __DIR__ . '/handlers/adorer/preferences_update.php');

// --- Admin features ---
$router->get('/admin/stats', require __DIR__ . '/handlers/admin/stats.php');
$router->get('/admin/adorers', require __DIR__ . '/handlers/admin/adorers.php');
$router->get('/admin/adorer', require __DIR__ . '/handlers/admin/adorer_get.php');
$router->put('/admin/adorer', require __DIR__ . '/handlers/admin/adorer_update.php');
$router->get('/admin/attendance', require __DIR__ . '/handlers/admin/attendance.php');
$router->get('/admin/missed', require __DIR__ . '/handlers/admin/missed.php');
$router->post('/admin/missed/followup', require __DIR__ . '/handlers/admin/missed_followup.php');
$router->get('/admin/coverage', require __DIR__ . '/handlers/admin/coverage.php');
$router->get('/admin/export', require __DIR__ . '/handlers/admin/export.php');
$router->post('/admin/email/preview', require __DIR__ . '/handlers/admin/email_preview.php');
$router->post('/admin/email/send', require __DIR__ . '/handlers/admin/email_send.php');
$router->get('/admin/email/history', require __DIR__ . '/handlers/admin/email_history.php');

// Future routes will be registered here as features are built:
//   Phase 6:    /cron/...

$router->dispatch();
