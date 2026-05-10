<?php
declare(strict_types=1);

namespace Conquer;

/**
 * Minimal URL router.
 *
 * Supports static segments and named captures (:param).
 * Route handlers receive an array of named URL params.
 *
 * Usage:
 *   $router = new Router();
 *   $router->get('/api/auth/me', [AuthHandler::class, 'me']);
 *   $router->post('/api/city/upgrade-building', [CityHandler::class, 'upgradeBuilding']);
 *
 *   if (!$router->dispatch($method, $path)) {
 *       Response::error(404, 'NOT_FOUND', 'Endpoint not found.');
 *   }
 */
final class Router
{
    /** @var array<int, array{method: string, pattern: string, handler: callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function delete(string $pattern, callable $handler): void
    {
        $this->add('DELETE', $pattern, $handler);
    }

    /**
     * Attempt to match the current request to a registered route.
     *
     * @param array<string, mixed> $params Named URL captures, passed to the handler.
     */
    public function dispatch(string $method, string $path): bool
    {
        $method = strtoupper($method);

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $regex = $this->patternToRegex($route['pattern']);

            if (preg_match($regex, $path, $matches) !== 1) {
                continue;
            }

            // Extract only named captures (string keys) from the match array.
            $params = array_filter(
                $matches,
                static fn (int|string $key): bool => is_string($key),
                ARRAY_FILTER_USE_KEY,
            );

            ($route['handler'])($params);
            return true;
        }

        return false;
    }

    // -------------------------------------------------------------------------

    private function add(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = [
            'method'  => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    /**
     * Converts `:param` segments into named regex groups.
     * E.g. `/api/city/cancel-build/:queue_id` becomes
     *      `#^/api/city/cancel-build/(?P<queue_id>[^/]+)$#`
     *
     * NOTE: :param replacement must happen BEFORE preg_quote because
     * PHP 7.3+ quotes ':' → '\:'. Quoting after replacement would corrupt
     * the named-group syntax. Route patterns only contain slashes, letters,
     * digits and hyphens (no regex special chars), so preg_quote is unnecessary.
     */
    private function patternToRegex(string $pattern): string
    {
        $regex = preg_replace('#:([a-zA-Z_][a-zA-Z0-9_]*)#', '(?P<$1>[^/]+)', $pattern);

        return '#^' . $regex . '$#';
    }
}
