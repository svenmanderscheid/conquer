<?php
declare(strict_types=1);

namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Db\Connection;

/**
 * Handles /api/alliance/* endpoints.
 */
final class AllianceHandler
{
    private const WORLD_ID      = 1;
    private const CHAT_COOLDOWN = 3; // seconds between messages per player

    private function __construct() {}

    // -------------------------------------------------------------------------
    // GET /api/alliance/my
    // -------------------------------------------------------------------------

    /**
     * Returns the current player's alliance data, or null when not a member.
     */
    public static function my(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $db       = Connection::getInstance();
        $playerId = (int) $session['player_id'];

        $member = $db->query(
            'SELECT am.alliance_id, am.role
             FROM   alliance_members am
             WHERE  am.player_id = ?',
            [$playerId],
        )->fetch();

        if ($member === false) {
            Response::ok(null);
        }

        $alliance = $db->query(
            'SELECT id, name, tag, description, leader_id, member_count, max_members, created_at
             FROM   alliances
             WHERE  id = ?',
            [(int) $member['alliance_id']],
        )->fetch();

        if ($alliance === false) {
            Response::ok(null);
        }

        Response::ok([
            'alliance'    => $alliance,
            'my_role'     => $member['role'],
        ]);
    }

    // -------------------------------------------------------------------------
    // POST /api/alliance/create
    // -------------------------------------------------------------------------

    /**
     * Creates a new alliance.
     * Body: { "name": "...", "tag": "...", "description": "..." }
     */
    public static function create(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $supplied = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($supplied === '' || !hash_equals($session['csrf_token'], $supplied)) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }

        $body        = json_decode(file_get_contents('php://input') ?: '', true) ?? [];
        $name        = trim((string) ($body['name']        ?? ''));
        $tag         = trim((string) ($body['tag']         ?? ''));
        $description = trim((string) ($body['description'] ?? ''));

        // Validate name
        if (mb_strlen($name) < 3 || mb_strlen($name) > 50) {
            Response::error(400, 'INVALID_NAME', 'Alliance name must be 3–50 characters.');
        }

        // Validate tag: 2–6 uppercase letters only
        if (!preg_match('/^[A-Z]{2,6}$/', $tag)) {
            Response::error(400, 'INVALID_TAG', 'Tag must be 2–6 uppercase letters (A–Z).');
        }

        $db       = Connection::getInstance();
        $playerId = (int) $session['player_id'];

        // Check: player must not already be in an alliance
        $alreadyMember = $db->query(
            'SELECT id FROM alliance_members WHERE player_id = ?',
            [$playerId],
        )->fetch();

        if ($alreadyMember !== false) {
            Response::error(409, 'ALREADY_MEMBER', 'You are already in an alliance. Leave first.');
        }

        // Check name/tag uniqueness within this world
        $nameConflict = $db->query(
            'SELECT id FROM alliances WHERE world_id = ? AND name = ?',
            [self::WORLD_ID, $name],
        )->fetch();
        if ($nameConflict !== false) {
            Response::error(409, 'NAME_TAKEN', 'An alliance with that name already exists.');
        }

        $tagConflict = $db->query(
            'SELECT id FROM alliances WHERE world_id = ? AND tag = ?',
            [self::WORLD_ID, $tag],
        )->fetch();
        if ($tagConflict !== false) {
            Response::error(409, 'TAG_TAKEN', 'An alliance with that tag already exists.');
        }

        $allianceId = $db->transaction(function (Connection $db) use ($playerId, $name, $tag, $description): int {
            $db->execute(
                'INSERT INTO alliances (world_id, name, tag, description, leader_id, member_count)
                 VALUES (?, ?, ?, ?, ?, 1)',
                [self::WORLD_ID, $name, $tag, $description ?: null, $playerId],
            );
            $allianceId = $db->lastInsertId();

            $db->execute(
                "INSERT INTO alliance_members (alliance_id, player_id, role)
                 VALUES (?, ?, 'leader')",
                [$allianceId, $playerId],
            );

            return $allianceId;
        });

        Response::ok(['alliance_id' => $allianceId, 'name' => $name, 'tag' => $tag]);
    }

    // -------------------------------------------------------------------------
    // POST /api/alliance/join
    // -------------------------------------------------------------------------

    /**
     * Joins an existing alliance.
     * Body: { "alliance_id": 123 }
     */
    public static function join(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $supplied = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($supplied === '' || !hash_equals($session['csrf_token'], $supplied)) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }

        $body       = json_decode(file_get_contents('php://input') ?: '', true) ?? [];
        $allianceId = (int) ($body['alliance_id'] ?? 0);

        if ($allianceId <= 0) {
            Response::error(400, 'MISSING_FIELD', 'alliance_id is required.');
        }

        $db       = Connection::getInstance();
        $playerId = (int) $session['player_id'];

        // Check: player must not already be in an alliance
        $alreadyMember = $db->query(
            'SELECT id FROM alliance_members WHERE player_id = ?',
            [$playerId],
        )->fetch();

        if ($alreadyMember !== false) {
            Response::error(409, 'ALREADY_MEMBER', 'You are already in an alliance. Leave first.');
        }

        // Load alliance and check capacity
        $alliance = $db->query(
            'SELECT id, name, member_count, max_members FROM alliances WHERE id = ? AND world_id = ?',
            [$allianceId, self::WORLD_ID],
        )->fetch();

        if ($alliance === false) {
            Response::error(404, 'NOT_FOUND', 'Alliance not found.');
        }

        if ((int) $alliance['member_count'] >= (int) $alliance['max_members']) {
            Response::error(409, 'ALLIANCE_FULL', 'This alliance is full.');
        }

        $db->transaction(function (Connection $db) use ($allianceId, $playerId): void {
            $db->execute(
                "INSERT INTO alliance_members (alliance_id, player_id, role)
                 VALUES (?, ?, 'member')",
                [$allianceId, $playerId],
            );

            $db->execute(
                'UPDATE alliances SET member_count = member_count + 1 WHERE id = ?',
                [$allianceId],
            );
        });

        Response::ok(['alliance_id' => $allianceId, 'name' => $alliance['name']]);
    }

    // -------------------------------------------------------------------------
    // POST /api/alliance/leave
    // -------------------------------------------------------------------------

    /**
     * Leaves the current alliance.
     * Leader may only leave when sole member; otherwise must promote first.
     */
    public static function leave(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $supplied = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($supplied === '' || !hash_equals($session['csrf_token'], $supplied)) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }

        $db       = Connection::getInstance();
        $playerId = (int) $session['player_id'];

        $member = $db->query(
            'SELECT am.id, am.alliance_id, am.role, a.member_count, a.leader_id
             FROM   alliance_members am
             JOIN   alliances a ON a.id = am.alliance_id
             WHERE  am.player_id = ?',
            [$playerId],
        )->fetch();

        if ($member === false) {
            Response::error(409, 'NOT_MEMBER', 'You are not in an alliance.');
        }

        $allianceId  = (int) $member['alliance_id'];
        $memberCount = (int) $member['member_count'];
        $role        = (string) $member['role'];

        // Leader can only leave if they are the sole member
        if ($role === 'leader' && $memberCount > 1) {
            Response::error(
                409,
                'LEADER_CANNOT_LEAVE',
                'As leader you must promote another member to leader before leaving.',
            );
        }

        $db->transaction(function (Connection $db) use ($playerId, $allianceId, $memberCount): void {
            $db->execute(
                'DELETE FROM alliance_members WHERE player_id = ?',
                [$playerId],
            );

            if ($memberCount <= 1) {
                // Dissolve the alliance when last member leaves
                $db->execute('DELETE FROM alliances WHERE id = ?', [$allianceId]);
                $db->execute('DELETE FROM alliance_messages WHERE alliance_id = ?', [$allianceId]);
            } else {
                $db->execute(
                    'UPDATE alliances SET member_count = member_count - 1 WHERE id = ?',
                    [$allianceId],
                );
            }
        });

        Response::ok(['left' => true]);
    }

    // -------------------------------------------------------------------------
    // GET /api/alliance/search
    // -------------------------------------------------------------------------

    /**
     * Searches alliances by name or tag.
     * GET param: q (search string, optional — returns all if empty)
     */
    public static function search(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $q  = trim((string) ($_GET['q'] ?? ''));
        $db = Connection::getInstance();

        if ($q !== '') {
            $like    = '%' . $q . '%';
            $results = $db->query(
                'SELECT id, name, tag, member_count, max_members
                 FROM   alliances
                 WHERE  world_id = ?
                   AND  (name LIKE ? OR tag LIKE ?)
                 ORDER BY member_count DESC
                 LIMIT 30',
                [self::WORLD_ID, $like, $like],
            )->fetchAll();
        } else {
            $results = $db->query(
                'SELECT id, name, tag, member_count, max_members
                 FROM   alliances
                 WHERE  world_id = ?
                 ORDER BY member_count DESC
                 LIMIT 30',
                [self::WORLD_ID],
            )->fetchAll();
        }

        Response::ok(['alliances' => $results]);
    }

    // -------------------------------------------------------------------------
    // GET /api/alliance/members
    // -------------------------------------------------------------------------

    /**
     * Returns the member list of the current player's alliance.
     */
    public static function members(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $db       = Connection::getInstance();
        $playerId = (int) $session['player_id'];

        $member = $db->query(
            'SELECT alliance_id FROM alliance_members WHERE player_id = ?',
            [$playerId],
        )->fetch();

        if ($member === false) {
            Response::error(403, 'NOT_MEMBER', 'You are not in an alliance.');
        }

        $allianceId = (int) $member['alliance_id'];

        $members = $db->query(
            'SELECT am.player_id, p.username, am.role, am.joined_at
             FROM   alliance_members am
             JOIN   players p ON p.id = am.player_id
             WHERE  am.alliance_id = ?
             ORDER BY
               FIELD(am.role, \'leader\', \'vice_leader\', \'officer\', \'veteran\', \'member\'),
               am.joined_at ASC',
            [$allianceId],
        )->fetchAll();

        Response::ok(['members' => $members]);
    }

    // -------------------------------------------------------------------------
    // GET /api/alliance/chat
    // -------------------------------------------------------------------------

    /**
     * Returns the last 50 chat messages for the player's alliance.
     * GET param: since_id — when set, returns only messages with id > since_id (for polling).
     */
    public static function chat(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $db       = Connection::getInstance();
        $playerId = (int) $session['player_id'];

        $member = $db->query(
            'SELECT alliance_id FROM alliance_members WHERE player_id = ?',
            [$playerId],
        )->fetch();

        if ($member === false) {
            Response::error(403, 'NOT_MEMBER', 'You are not in an alliance.');
        }

        $allianceId = (int) $member['alliance_id'];
        $sinceId    = (int) ($_GET['since_id'] ?? 0);

        if ($sinceId > 0) {
            $messages = $db->query(
                'SELECT id, player_id, username, message, sent_at
                 FROM   alliance_messages
                 WHERE  alliance_id = ? AND id > ?
                 ORDER BY id ASC
                 LIMIT 50',
                [$allianceId, $sinceId],
            )->fetchAll();
        } else {
            // Return last 50 in ascending order for display
            $messages = $db->query(
                'SELECT id, player_id, username, message, sent_at
                 FROM   (
                     SELECT id, player_id, username, message, sent_at
                     FROM   alliance_messages
                     WHERE  alliance_id = ?
                     ORDER BY id DESC
                     LIMIT 50
                 ) sub
                 ORDER BY id ASC',
                [$allianceId],
            )->fetchAll();
        }

        Response::ok(['messages' => $messages]);
    }

    // -------------------------------------------------------------------------
    // POST /api/alliance/chat
    // -------------------------------------------------------------------------

    /**
     * Sends a chat message to the alliance chat.
     * Body: { "message": "..." }
     * Anti-spam: 1 message per 3 seconds per player.
     */
    public static function sendChat(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $supplied = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($supplied === '' || !hash_equals($session['csrf_token'], $supplied)) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }

        $body    = json_decode(file_get_contents('php://input') ?: '', true) ?? [];
        $message = trim((string) ($body['message'] ?? ''));

        if ($message === '') {
            Response::error(400, 'MISSING_FIELD', 'message is required.');
        }

        if (mb_strlen($message) > 200) {
            Response::error(400, 'MESSAGE_TOO_LONG', 'Message must not exceed 200 characters.');
        }

        $db       = Connection::getInstance();
        $playerId = (int) $session['player_id'];
        $username = (string) $session['username'];

        $member = $db->query(
            'SELECT alliance_id FROM alliance_members WHERE player_id = ?',
            [$playerId],
        )->fetch();

        if ($member === false) {
            Response::error(403, 'NOT_MEMBER', 'You are not in an alliance.');
        }

        $allianceId = (int) $member['alliance_id'];

        // Anti-spam: check last message timestamp for this player in this alliance
        $lastMsg = $db->query(
            'SELECT sent_at FROM alliance_messages
             WHERE  alliance_id = ? AND player_id = ?
             ORDER BY id DESC LIMIT 1',
            [$allianceId, $playerId],
        )->fetch();

        if ($lastMsg !== false) {
            $lastSentAt = strtotime($lastMsg['sent_at']);
            $elapsed    = time() - $lastSentAt;
            if ($elapsed < self::CHAT_COOLDOWN) {
                $wait = self::CHAT_COOLDOWN - $elapsed;
                Response::error(429, 'CHAT_COOLDOWN', "Please wait {$wait}s before sending another message.");
            }
        }

        $db->execute(
            'INSERT INTO alliance_messages (alliance_id, player_id, username, message)
             VALUES (?, ?, ?, ?)',
            [$allianceId, $playerId, $username, $message],
        );

        $newId = $db->lastInsertId();

        Response::ok(['id' => $newId, 'sent_at' => gmdate('Y-m-d H:i:s')]);
    }
}
