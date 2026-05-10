<?php
declare(strict_types=1);

namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Db\Connection;
use Conquer\Game\Player\ActionPoints;
use Conquer\Game\Player\LordLevel;

/**
 * Handles /api/player/* endpoints.
 *
 * GET  /api/player/me                — own full profile
 * GET  /api/player/profile/:id       — public profile of any player
 * GET  /api/player/formations        — get 4 saved troop formations
 * POST /api/player/formations/:slot  — save a troop formation (slot 1-4)
 * POST /api/player/emoji             — set active emoji (5 seconds)
 * GET  /api/player/skins             — list owned skins
 * POST /api/player/skin/equip        — equip a skin
 */
final class PlayerHandler
{
    private function __construct() {}

    // ── GET /api/player/me ────────────────────────────────────────────────────

    public static function me(array $params): void
    {
        $session = Session::current();
        if ($session === null) Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');

        $playerId = (int) $session['player_id'];
        $db       = Connection::getInstance();

        $player = $db->query(
            'SELECT p.id, p.username, p.lord_xp, p.lord_level, p.vip_level, p.kill_count,
                    c.castle_level, c.power, c.coord_x, c.coord_y,
                    a.tag AS alliance_tag, a.name AS alliance_name
             FROM   players p
             LEFT JOIN cities c ON c.player_id = p.id AND c.world_id = 1
             LEFT JOIN alliance_members am ON am.player_id = p.id
             LEFT JOIN alliances a ON a.id = am.alliance_id
             WHERE  p.id = ?',
            [$playerId],
        )->fetch();

        if ($player === false) Response::error(404, 'NOT_FOUND', 'Spieler nicht gefunden.');

        $ap     = ActionPoints::get($playerId);
        $lordXp = (int) $player['lord_xp'];
        $lordLv = (int) $player['lord_level'];

        Response::ok([
            'id'             => $playerId,
            'username'       => $player['username'],
            'alliance_tag'   => $player['alliance_tag'],
            'alliance_name'  => $player['alliance_name'],
            'castle_level'   => (int) ($player['castle_level'] ?? 1),
            'power'          => (int) ($player['power'] ?? 0),
            'kill_count'     => (int) $player['kill_count'],
            'vip_level'      => (int) $player['vip_level'],
            'lord_level'     => $lordLv,
            'lord_xp'        => $lordXp,
            'lord_xp_into'   => LordLevel::xpIntoCurrentLevel($lordXp),
            'lord_xp_next'   => LordLevel::xpForNextLevel($lordLv),
            'action_points'  => $ap['current'],
            'ap_max'         => $ap['max'],
            'ap_regen_per_h' => $ap['regen_per_hour'],
            'coord_x'        => (int) ($player['coord_x'] ?? 0),
            'coord_y'        => (int) ($player['coord_y'] ?? 0),
            'world_id'       => 1,
        ]);
    }

    // ── GET /api/player/profile/:id ───────────────────────────────────────────

    public static function profile(array $params): void
    {
        $session = Session::current();
        if ($session === null) Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');

        $targetId = (int) ($params['id'] ?? 0);
        if ($targetId <= 0) Response::error(400, 'INVALID_INPUT', 'Ungültige Spieler-ID.');

        $db = Connection::getInstance();

        $player = $db->query(
            'SELECT p.id, p.username, p.lord_level, p.kill_count, p.vip_level,
                    c.castle_level, c.power,
                    a.tag AS alliance_tag, a.name AS alliance_name
             FROM   players p
             LEFT JOIN cities c ON c.player_id = p.id AND c.world_id = 1
             LEFT JOIN alliance_members am ON am.player_id = p.id
             LEFT JOIN alliances a ON a.id = am.alliance_id
             WHERE  p.id = ?',
            [$targetId],
        )->fetch();

        if ($player === false) Response::error(404, 'NOT_FOUND', 'Spieler nicht gefunden.');

        // Troops visible on public profile (for context / scout display)
        $troopRows = $db->query(
            'SELECT ct.troop_code, ct.count
             FROM   city_troops ct
             JOIN   cities c ON c.id = ct.city_id
             WHERE  c.player_id = ? AND c.world_id = 1 AND ct.count > 0',
            [$targetId],
        )->fetchAll();

        $troops = [];
        foreach ($troopRows as $r) {
            $troops[(int) $r['troop_code']] = (int) $r['count'];
        }

        Response::ok([
            'id'            => $targetId,
            'username'      => $player['username'],
            'alliance_tag'  => $player['alliance_tag'],
            'alliance_name' => $player['alliance_name'],
            'castle_level'  => (int) ($player['castle_level'] ?? 1),
            'power'         => (int) ($player['power'] ?? 0),
            'kill_count'    => (int) $player['kill_count'],
            'vip_level'     => (int) $player['vip_level'],
            'lord_level'    => (int) $player['lord_level'],
            'troops'        => $troops,
            'world_id'      => 1,
        ]);
    }

    // ── GET /api/player/formations ────────────────────────────────────────────

    public static function formations(array $params): void
    {
        $session = Session::current();
        if ($session === null) Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');

        $playerId = (int) $session['player_id'];
        $db       = Connection::getInstance();

        $rows = [];
        try {
            $rows = $db->query(
                'SELECT slot, troops_json FROM troop_formations WHERE player_id = ? ORDER BY slot',
                [$playerId],
            )->fetchAll();
        } catch (\PDOException) {}

        $formations = [1 => [], 2 => [], 3 => [], 4 => []];
        foreach ($rows as $r) {
            $slot = (int) $r['slot'];
            if ($slot >= 1 && $slot <= 4) {
                $formations[$slot] = json_decode($r['troops_json'], true) ?? [];
            }
        }

        Response::ok(['formations' => $formations]);
    }

    // ── POST /api/player/formations/:slot ─────────────────────────────────────

    public static function saveFormation(array $params): void
    {
        $session = Session::current();
        if ($session === null) Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');

        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($csrf === '' || !hash_equals($session['csrf_token'], $csrf)) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }

        $slot = (int) ($params['slot'] ?? 0);
        if ($slot < 1 || $slot > 4) Response::error(400, 'INVALID_INPUT', 'Slot muss 1-4 sein.');

        $body   = json_decode((string) file_get_contents('php://input'), true) ?? [];
        $troops = (array) ($body['troops'] ?? []);

        $clean = [];
        foreach ($troops as $code => $count) {
            $count = (int) $count;
            if ($count > 0) $clean[(int) $code] = $count;
        }

        $playerId = (int) $session['player_id'];
        Connection::getInstance()->execute(
            'INSERT INTO troop_formations (player_id, slot, troops_json)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE troops_json = VALUES(troops_json), updated_at = UTC_TIMESTAMP()',
            [$playerId, $slot, json_encode($clean)],
        );

        Response::ok(['saved' => true]);
    }

    // ── POST /api/player/emoji ────────────────────────────────────────────────

    public static function setEmoji(array $params): void
    {
        $session = Session::current();
        if ($session === null) Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');

        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($csrf === '' || !hash_equals($session['csrf_token'], $csrf)) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }

        $body      = json_decode((string) file_get_contents('php://input'), true) ?? [];
        $emojiCode = trim((string) ($body['emoji_code'] ?? ''));

        if ($emojiCode === '' || strlen($emojiCode) > 32) {
            Response::error(400, 'INVALID_INPUT', 'Ungültiger Emoji-Code.');
        }

        $playerId = (int) $session['player_id'];
        Connection::getInstance()->execute(
            'INSERT INTO player_emojis (player_id, emoji_code, expires_at)
             VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 5 SECOND))
             ON DUPLICATE KEY UPDATE
                 emoji_code = VALUES(emoji_code),
                 expires_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 5 SECOND)',
            [$playerId, $emojiCode],
        );

        Response::ok(['emoji_set' => true]);
    }

    // ── GET /api/player/skins ─────────────────────────────────────────────────

    public static function skins(array $params): void
    {
        $session = Session::current();
        if ($session === null) Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');

        $playerId = (int) $session['player_id'];
        $db       = Connection::getInstance();

        $rows = [];
        try {
            $rows = $db->query(
                'SELECT skin_code, is_equipped FROM player_skins WHERE player_id = ? ORDER BY acquired_at ASC',
                [$playerId],
            )->fetchAll();
        } catch (\PDOException) {}

        // Default "noskin" entry is always present
        $skins    = [['skin_code' => 'noskin', 'is_equipped' => 1]];
        $equipped = 'noskin';

        foreach ($rows as $r) {
            $skins[] = ['skin_code' => $r['skin_code'], 'is_equipped' => (int) $r['is_equipped']];
            if ((int) $r['is_equipped'] === 1) $equipped = $r['skin_code'];
        }

        // If another skin is equipped, mark noskin as not equipped
        if ($equipped !== 'noskin') {
            $skins[0]['is_equipped'] = 0;
        }

        Response::ok(['skins' => $skins, 'equipped' => $equipped]);
    }

    // ── POST /api/player/skin/equip ───────────────────────────────────────────

    public static function equipSkin(array $params): void
    {
        $session = Session::current();
        if ($session === null) Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');

        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($csrf === '' || !hash_equals($session['csrf_token'], $csrf)) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }

        $body     = json_decode((string) file_get_contents('php://input'), true) ?? [];
        $skinCode = trim((string) ($body['skin_code'] ?? ''));

        if ($skinCode === '') Response::error(400, 'INVALID_INPUT', 'skin_code ist erforderlich.');

        $playerId = (int) $session['player_id'];
        $db       = Connection::getInstance();

        // Verify ownership (noskin is always allowed without a DB row)
        if ($skinCode !== 'noskin') {
            $owned = $db->query(
                'SELECT id FROM player_skins WHERE player_id = ? AND skin_code = ?',
                [$playerId, $skinCode],
            )->fetch();

            if ($owned === false) {
                Response::error(403, 'NOT_OWNED', 'Du besitzt diesen Skin nicht.');
            }
        }

        $db->transaction(function (Connection $db) use ($playerId, $skinCode): void {
            // Unequip all skins first
            $db->execute(
                'UPDATE player_skins SET is_equipped = 0 WHERE player_id = ?',
                [$playerId],
            );

            // Equip the selected skin (noskin = no DB row to update)
            if ($skinCode !== 'noskin') {
                $db->execute(
                    'UPDATE player_skins SET is_equipped = 1 WHERE player_id = ? AND skin_code = ?',
                    [$playerId, $skinCode],
                );
            }

            // Mirror to cities table for fast map lookups
            $db->execute(
                'UPDATE cities SET skin_code = ? WHERE player_id = ? AND world_id = 1',
                [$skinCode, $playerId],
            );
        });

        Response::ok(['equipped' => $skinCode]);
    }
}
