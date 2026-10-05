<?php
declare(strict_types=1);

namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Db\Connection;
use Conquer\Game\Defense\DefenseService;
use Conquer\Game\Player\ActionPoints;
use Conquer\Game\Player\LordLevel;

/**
 * Handles /api/player/* endpoints.
 *
 * GET  /api/player/me                — own full profile
 * GET  /api/player/profile/:id       — public profile of any player
 * GET  /api/player/formations        — get 6 saved troop formations
 * POST /api/player/formations/:slot  — save a troop formation (slot 1-6)
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
        $worldId=\Conquer\Game\World\WorldContext::id();

        $player = $db->query(
            'SELECT p.id, p.username, p.kill_count,
                    c.castle_level, c.power, c.coord_x, c.coord_y,
                    a.tag AS alliance_tag, a.name AS alliance_name
             FROM   players p
             JOIN cities c ON c.player_id = p.id AND c.world_id = ?
             LEFT JOIN alliance_members am ON am.player_id = p.id AND am.world_id = ?
             LEFT JOIN alliances a ON a.id = am.alliance_id
             WHERE  p.id = ?',
            [$worldId,$worldId,$playerId],
        )->fetch();

        if ($player === false) Response::error(404, 'NOT_FOUND', 'Spieler nicht gefunden.');

        $ap     = ActionPoints::get($playerId);
        $lord=LordLevel::snapshot($playerId,$worldId);
        $lordXp = $lord['xp'];
        $lordLv = $lord['level'];

        Response::ok([
            'id'             => $playerId,
            'username'       => $player['username'],
            'alliance_tag'   => $player['alliance_tag'],
            'alliance_name'  => $player['alliance_name'],
            'castle_level'   => (int) ($player['castle_level'] ?? 1),
            'power'          => (int) ($player['power'] ?? 0),
            'kill_count'     => (int) $player['kill_count'],
            'vip_level'      => \Conquer\Game\Vip\VipService::status($playerId,$worldId)['level'],
            'lord_level'     => $lordLv,
            'lord_xp'        => $lordXp,
            'lord_xp_into'   => LordLevel::xpIntoCurrentLevel($lordXp),
            'lord_xp_next'   => LordLevel::xpForNextLevel($lordLv),
            'action_points'  => $ap['current'],
            'ap_max'         => $ap['max'],
            'ap_regen_per_h' => $ap['regen_per_hour'],
            'coord_x'        => (int) ($player['coord_x'] ?? 0),
            'coord_y'        => (int) ($player['coord_y'] ?? 0),
            'world_id'       => $worldId,
        ]);
    }

    // ── GET /api/player/profile/:id ───────────────────────────────────────────

    public static function profile(array $params): void
    {
        $session = Session::current();
        if ($session === null) Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');

        $targetId = filter_var($params['id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if ($targetId === false) Response::error(400, 'INVALID_INPUT', 'Ungültige Spieler-ID.');

        $db = Connection::getInstance();
        $worldId=\Conquer\Game\World\WorldContext::id();

        $player = $db->query(
            'SELECT p.id, p.username, p.kill_count,
                    c.castle_level, c.power, c.coord_x, c.coord_y, c.is_hidden,
                    a.tag AS alliance_tag, a.name AS alliance_name
             FROM   players p
             JOIN cities c ON c.player_id = p.id AND c.world_id = ?
             LEFT JOIN alliance_members am ON am.player_id = p.id AND am.world_id = ?
             LEFT JOIN alliances a ON a.id = am.alliance_id
             WHERE  p.id = ?',
            [$worldId,$worldId,$targetId],
        )->fetch();

        if ($player === false) Response::error(404, 'NOT_FOUND', 'Spieler nicht gefunden.');

        // A public profile is not a scouting report. Hidden or locked enemy
        // cities must not become discoverable by enumerating player IDs.
        if ($targetId !== (int)$session['player_id']
            && ((bool)$player['is_hidden'] || !\Conquer\Game\World\LandAccessPolicy::isOpen($worldId,(int)$player['coord_x'],(int)$player['coord_y']))) {
            Response::error(404, 'NOT_FOUND', 'Spieler nicht gefunden.');
        }

        Response::ok([
            'id'            => $targetId,
            'username'      => $player['username'],
            'alliance_tag'  => $player['alliance_tag'],
            'alliance_name' => $player['alliance_name'],
            'castle_level'  => (int) ($player['castle_level'] ?? 1),
            'power'         => (int) ($player['power'] ?? 0),
            'kill_count'    => (int) $player['kill_count'],
            'vip_level'     => \Conquer\Game\Vip\VipService::status($targetId,$worldId)['level'],
            'lord_level'    => LordLevel::snapshot($targetId,$worldId)['level'],
            'world_id'      => $worldId,
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

        $formations = array_fill(1, DefenseService::FORMATION_SLOTS, []);
        foreach ($rows as $r) {
            $slot = (int) $r['slot'];
            if ($slot >= 1 && $slot <= DefenseService::FORMATION_SLOTS) {
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
        if ($slot < 1 || $slot > DefenseService::FORMATION_SLOTS) Response::error(400, 'INVALID_INPUT', \Conquer\Game\Locale::t('formation.invalid_slot',['count'=>DefenseService::FORMATION_SLOTS]));

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
