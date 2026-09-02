<?php
/**
 * Lightweight request router.
 * Parses the request URI and HTTP method, dispatches to registered handlers.
 *
 * Usage:
 *   $router = new Router();
 *   $router->get('/health', fn() => Response::success(['status' => 'ok']));
 *   $router->dispatch();
 */

class Router
{
    /** @var array<string, callable> Key format: "GET /path" */
    private array $routes = [];

    private function add(string $method, string $path, callable $handler): void
    {
        $this->routes["{$method} {$path}"] = $handler;
    }

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function put(string $path, callable $handler): void
    {
        $this->add('PUT', $path, $handler);
    }

    public function delete(string $path, callable $handler): void
    {
        $this->add('DELETE', $path, $handler);
    }

    /**
     * Extract the route path from the request URI, relative to wherever the
     * front controller is mounted.
     *
     * The mount point is derived from SCRIPT_NAME, so routes resolve correctly
     * regardless of how the app is deployed:
     *   subdomain root   /api/index.php   -> mount /api   -> /api/health   => /health
     *   subdirectory     /staging/api/... -> mount /staging/api            => /health
     *
     * Routes are therefore registered without the mount prefix (e.g. '/health').
     */
    private function getPath(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        // Remove query string
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';

        // Strip the directory the front controller lives in
        $mount = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        if ($mount !== '' && $mount !== '.' && str_starts_with($path, $mount)) {
            $path = substr($path, strlen($mount));
        }

        // Normalize: ensure leading slash, drop trailing slash (except root)
        $path = '/' . ltrim($path, '/');
        $path = rtrim($path, '/');
        if ($path === '') {
            $path = '/';
        }
        return $path;
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = $this->getPath();
        $key = "{$method} {$path}";

        // Handle CORS preflight
        if ($method === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        // Exact match
        if (isset($this->routes[$key])) {
            ($this->routes[$key])();
            return;
        }

        // No match — 404
        Response::error('Not found', 404);
    }
}
