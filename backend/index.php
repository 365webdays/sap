<?php
/**
 * Front controller / API entry point.
 * All /api/* requests are routed here via .htaccess.
 */

// Load environment variables
require_once __DIR__ . '/config/env.php';
load_env(__DIR__ . '/.env');

// Core libraries
require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/Response.php';
require_once __DIR__ . '/lib/Router.php';

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

// Future routes will be registered here as features are built:
//   Auth:        /auth/register, /auth/login, /admin/login
//   Adorer:      /adorer/dashboard, /adorer/checkin, /adorer/preferences
//   Admin:       /admin/dashboard, /admin/adorers, /admin/attendance, ...

$router->dispatch();
