<?php
/**
 * JSON response helper.
 * Enforces the consistent API contract:
 *   { "success": true,  "data": ... }
 *   { "success": false, "error": "..." }
 */

class Response
{
    public static function success($data = null, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }

    public static function error(string $message, int $code = 400): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => $message]);
        exit;
    }
}
