<?php
declare(strict_types=1);

namespace Conquer\Api;

/**
 * JSON response factory.
 *
 * Every method sets the Content-Type header, writes JSON, and exits.
 * Callers never need to call exit themselves.
 */
final class Response
{
    // Static-only helper — no instantiation.
    private function __construct() {}

    /**
     * 200 OK with a payload.
     *
     * @param mixed $data Anything JSON-serialisable (array, object, null).
     */
    public static function ok(mixed $data = null): never
    {
        self::send(200, ['ok' => true, 'data' => $data, 'error' => null]);
    }

    /**
     * Error response with a machine-readable code.
     *
     * @param int    $status  HTTP status code (400, 401, 403, 404, 422, 500 …)
     * @param string $code    SCREAMING_SNAKE_CASE error identifier, e.g. "NOT_FOUND"
     * @param string $message Optional human-readable explanation
     */
    public static function error(int $status, string $code, string $message = ''): never
    {
        self::send($status, [
            'ok'    => false,
            'data'  => null,
            'error' => [
                'code'    => $code,
                'message' => $message,
            ],
        ]);
    }

    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $body
     */
    private static function send(int $status, array $body): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        exit;
    }
}
