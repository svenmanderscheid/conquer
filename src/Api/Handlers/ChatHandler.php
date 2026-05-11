<?php
declare(strict_types=1);

namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;

/**
 * Handles /api/chat/* endpoints.
 *
 * GET  /api/chat/world         — world chat (last 50 messages)
 * POST /api/chat/world         — send world chat message
 * GET  /api/chat/alliance      — alliance chat (last 50 or since_id)
 * POST /api/chat/alliance      — send alliance chat message
 */
final class ChatHandler
{
    private function __construct() {}

    // -------------------------------------------------------------------------
    // GET /api/chat/world
    // -------------------------------------------------------------------------

    /**
     * Returns world chat messages.
     * Supports ?since_id=N for incremental polling.
     */
    public static function worldChat(array $session): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $db      = \Conquer\Db\Connection::getInstance();
        $sinceId = (int) ($_GET['since_id'] ?? 0);

        try {
            if ($sinceId > 0) {
                $messages = $db->query(
                    'SELECT id, player_id, username, alliance_tag, message, created_at
                     FROM world_chat
                     WHERE world_id = 1 AND id > ?
                     ORDER BY id ASC LIMIT 50',
                    [$sinceId],
                )->fetchAll();
            } else {
                $messages = $db->query(
                    'SELECT id, player_id, username, alliance_tag, message, created_at
                     FROM (
                         SELECT id, player_id, username, alliance_tag, message, created_at
                         FROM world_chat WHERE world_id = 1
                         ORDER BY id DESC LIMIT 50
                     ) sub ORDER BY id ASC',
                )->fetchAll();
            }
        } catch (\PDOException) {
            $messages = [];
        }

        Response::ok(['messages' => $messages]);
    }

    // -------------------------------------------------------------------------
    // POST /api/chat/world
    // -------------------------------------------------------------------------

    /**
     * Sends a message to the world chat (anti-spam: 1 msg / 3s).
     * Delegates to the existing AllianceHandler implementation.
     */
    public static function sendWorldChat(array $session): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }
        AllianceHandler::sendWorldChat($session);
    }

    // -------------------------------------------------------------------------
    // GET /api/chat/alliance
    // -------------------------------------------------------------------------

    /**
     * Returns alliance chat messages for the player's alliance.
     * Delegates to the existing AllianceHandler implementation.
     */
    public static function allianceChat(array $session): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }
        AllianceHandler::chat($session);
    }

    // -------------------------------------------------------------------------
    // POST /api/chat/alliance
    // -------------------------------------------------------------------------

    /**
     * Sends a message to the alliance chat.
     * Delegates to the existing AllianceHandler implementation.
     */
    public static function sendAllianceChat(array $session): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }
        AllianceHandler::sendChat($session);
    }
}
