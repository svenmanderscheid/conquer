<?php
declare(strict_types=1);

namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;

/**
 * Handles /api/auth/* endpoints.
 */
final class AuthHandler
{
    // Static-only handler — no instantiation.
    private function __construct() {}

    /**
     * GET /api/auth/me
     *
     * Returns the current player's public profile.
     * Also exposes the CSRF token so the frontend can store it for POST requests.
     *
     * Response data:
     *   player_id  int
     *   username   string
     *   vip_level  int
     *   gems       int
     *   csrf_token string
     */
    public static function me(array $params): void
    {
        $session = Session::current();

        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        Response::ok([
            'player_id'  => (int) $session['player_id'],
            'username'   => $session['username'],
            'vip_level'  => (int) $session['vip_level'],
            'gems'       => (int) $session['gems'],
            'csrf_token' => $session['csrf_token'],
        ]);
    }

    /**
     * POST /api/auth/logout
     *
     * Destroys the current session and clears the session cookie.
     * Requires a valid CSRF token in the X-CSRF-Token header or request body.
     */
    public static function logout(array $params): void
    {
        self::requireCsrf();
        Session::destroy();
        Response::ok(['logged_out' => true]);
    }

    // -------------------------------------------------------------------------

    /**
     * Aborts with 403 if the CSRF token is missing or invalid.
     *
     * The token is read from (in order of preference):
     *   1. X-CSRF-Token request header  (used by fetch/AJAX)
     *   2. csrf_token POST body field   (used by plain HTML forms)
     */
    private static function requireCsrf(): void
    {
        $session = Session::current();

        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $supplied = $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? (string) ($_POST['csrf_token'] ?? '');

        if ($supplied === '' || !hash_equals($session['csrf_token'], $supplied)) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }
    }
}
