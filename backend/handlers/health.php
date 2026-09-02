<?php
/**
 * GET /api/health
 * Returns service status and confirms database connectivity.
 */

return function (): void {
    $dbOk = false;
    $error = null;

    try {
        $db = Database::getConnection();
        $db->query('SELECT 1');
        $dbOk = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }

    $status = $dbOk ? 'ok' : 'degraded';

    Response::success([
        'status' => $status,
        'database' => $dbOk,
        'timestamp' => date('c'),
        'environment' => env('APP_ENV', 'unknown'),
    ]);
};
