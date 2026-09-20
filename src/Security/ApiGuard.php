<?php
declare(strict_types=1);
namespace Conquer\Security;

use Conquer\Api\Response;
use Conquer\Bootstrap;

final class ApiGuard
{
    public static function limit(string $scope, string $identity, int $capacity, int $period): void
    {
        try { $retry = RateLimit::consume($scope, $identity, $capacity, $period); }
        catch (\Throwable $e) {
            \Conquer\Logger::getInstance()->error('Security limiter unavailable: ' . $e->getMessage());
            header('Retry-After: 30');
            Response::error(503, 'SECURITY_UNAVAILABLE', 'Das Spiel ist kurz nicht verfügbar. Bitte versuche es gleich erneut.');
        }
        if ($retry > 0) {
            header('Retry-After: ' . $retry);
            Response::error(429, 'RATE_LIMITED', 'Zu viele Anfragen. Bitte warte kurz und versuche es erneut.');
        }
    }

    /** Runs before session queries, player locks and parsing request bodies. */
    public static function beforeSession(): void
    {
        self::limit('api.ip', RateLimit::ip(), 2400, 60);
        $path=(string)(parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH)??'');
        $limit=$path==='/api/bug-reports'?1300000:65536;
        if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > $limit) {
            Response::error(413, 'REQUEST_TOO_LARGE', 'Die Anfrage ist zu groß.');
        }
    }

    public static function authenticated(array $session, string $method, string $path): void
    {
        $player = (string)$session['player_id'];
        $capacity = max(1, (int)(Bootstrap::getConfig()['rate_limit_per_minute'] ?? 120));
        self::limit('api.player', $player, $capacity, 60);
        if ($path === '/api/map/search') self::limit('api.search', $player, 15, 60);
        if (in_array($method, ['POST','PUT','PATCH','DELETE'], true)) {
            // Ten immediate actions, replenishing at one action/second. Polling uses a separate budget.
            self::limit('api.write', $player, 10, 10);
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($path === '/api/auth/logout' ? ($_POST['csrf_token'] ?? '') : '');
            if (!is_string($token) || $token === '' || !hash_equals((string)$session['csrf_token'], $token)) {
                Response::error(403, 'CSRF_INVALID', 'Bitte lade das Spiel neu.');
            }
        }
    }
}
