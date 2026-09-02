<?php
/**
 * PDO database connection singleton.
 * Uses prepared statements throughout — see lib/Response and endpoint handlers.
 */

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $host = env('DB_HOST', 'localhost');
            $name = env('DB_NAME', '');
            $user = env('DB_USER', '');
            $pass = env('DB_PASS', '');

            $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";

            self::$instance = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }

        return self::$instance;
    }
}
