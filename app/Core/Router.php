<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<string, callable> */
    private array $getRoutes = [];
    /** @var array<string, callable> */
    private array $postRoutes = [];

    public function get(string $path, callable $handler): void
    {
        $this->getRoutes[$this->normalize($path)] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->postRoutes[$this->normalize($path)] = $handler;
    }

    public function dispatch(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $path = $this->normalize((string)$uri);

        $routes = $method === 'POST' ? $this->postRoutes : $this->getRoutes;
        $handler = $routes[$path] ?? null;
        if (!$handler) {
            http_response_code(404);
            echo '<h3>یافت نشد</h3>';
            return;
        }
        $handler();
    }

    private function normalize(string $path): string
    {
        if ($path === '') { return '/'; }
        $norm = '/' . ltrim($path, '/');
        return rtrim($norm, '/') ?: '/';
    }
}