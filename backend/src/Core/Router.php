<?php

namespace App\Core;

final class Router
{
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler, array $middleware = []): void
    {
        $this->routes[] = compact('method', 'pattern', 'handler', 'middleware');
    }

    public function dispatch(Request $request): void
    {
        $path = rtrim($request->path(), '/') ?: '/';
        $method = $request->method();

        foreach ($this->routes as $route) {
            $pattern = '#^' . preg_replace('#\{([a-zA-Z_]+)\}#', '(?P<$1>[^/]+)', rtrim($route['pattern'], '/') ?: '/') . '$#';

            if ($route['method'] !== $method) {
                continue;
            }

            if (!preg_match($pattern, $path, $matches)) {
                continue;
            }

            foreach ($route['middleware'] as $middleware) {
                $middleware($request);
            }

            $params = array_filter($matches, static fn ($key) => !is_int($key), ARRAY_FILTER_USE_KEY);
            ($route['handler'])($request, $params);
            return;
        }

        Response::json([
            'success' => false,
            'message' => 'Route not found',
            'path' => $path,
        ], 404);
    }
}
