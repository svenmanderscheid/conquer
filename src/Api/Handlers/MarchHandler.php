<?php
declare(strict_types=1);

namespace Conquer\Api\Handlers;

use Conquer\Game\World\WorldContext;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Db\Connection;
use Conquer\Game\City\CityState;
use Conquer\Game\March\MarchDispatcher;
use Conquer\Game\March\MarchTick;

/**
 * Handles /api/march/* endpoints.
 *
 * POST /api/march/dispatch  — dispatch a monster attack
 * GET  /api/march/list      — all active marches for the player
 */
final class MarchHandler
{
    private function __construct() {}

    /**
     * POST /api/march/dispatch
     *
     * Body: { target_x: int, target_y: int, troops: {code: count, ...}, request_id?: string }
     */
    public static function dispatch(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($csrf === '' || !hash_equals($session['csrf_token'], $csrf)) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }

        $body = self::readArmyRequest();
        $targetX = $body['target_x'];
        $targetY = $body['target_y'];
        $troops = $body['troops'] ?? null;
        if(isset($body['request_id'])&&!is_string($body['request_id']))Response::error(400,'REQUEST_ID_INVALID','request_id muss eine Zeichenfolge sein.');
        if (!is_array($troops)) {
            Response::error(400, 'INVALID_INPUT', 'Eine Truppenzusammenstellung ist erforderlich.');
        }

        $state = CityState::loadForPlayer((int) $session['player_id']);
        if ($state === null) {
            Response::error(404, 'NO_CITY', 'Keine Stadt gefunden.');
        }

        $city   = $state['city'];
        $cityId = (int) $city['id'];

        try {
            $marchId = MarchDispatcher::dispatchMonster(
                playerId:      (int) $session['player_id'],
                cityId:        $cityId,
                originX:       (int) $city['coord_x'],
                originY:       (int) $city['coord_y'],
                targetX:       $targetX,
                targetY:       $targetY,
                selectedTroops: $troops,
                requestId: isset($body['request_id'])&&is_string($body['request_id'])?$body['request_id']:null,
            );
        } catch (\RuntimeException|\DomainException $e) {
            if($e->getMessage()==='REQUEST_ID_CONFLICT')Response::error(409,'REQUEST_ID_CONFLICT','Diese Anfrage-ID wurde bereits für einen anderen Marsch verwendet.');
            if($e->getMessage()==='REQUEST_ID_INVALID')Response::error(400,'REQUEST_ID_INVALID','request_id muss 16 bis 80 Zeichen aus Buchstaben, Zahlen, _ oder - enthalten.');
            if($e->getMessage()==='REQUEST_BUSY')Response::error(409,'REQUEST_BUSY','Deine Marschdaten werden gerade aktualisiert. Versuche es erneut.');
            if($e instanceof \DomainException)Response::error(in_array($e->getCode(),[403,409],true)?$e->getCode():422,'WORLD_RULE',$e->getMessage());
            if ($e->getMessage() === 'MARCH_SLOT_FULL') {
                Response::error(400, 'march_slot_full', 'Alle Marsch-Slots belegt. Warte auf eine Rückkehr.');
            }
            Response::error(400, 'DISPATCH_FAILED', $e->getMessage());
        }

        Response::ok(['march_id' => $marchId]);
    }

    /**
     * POST /api/march/dispatch-charm
     *
     * Body: { charm_id: int, target_x: int, target_y: int, troops: {code: count}, request_id?: string }
     */
    public static function dispatchCharm(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($csrf === '' || !hash_equals($session['csrf_token'], $csrf)) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }

        $body    = json_decode((string) file_get_contents('php://input'), true) ?? [];
        $charmId = (int) ($body['charm_id'] ?? 0);
        $targetX = (int) ($body['target_x'] ?? -1);
        $targetY = (int) ($body['target_y'] ?? -1);
        $troops  = (array) ($body['troops']  ?? []);
        if(isset($body['request_id'])&&!is_string($body['request_id']))Response::error(400,'REQUEST_ID_INVALID','request_id muss eine Zeichenfolge sein.');

        if ($charmId <= 0 || $targetX < 0 || $targetY < 0) {
            Response::error(400, 'INVALID_INPUT', 'charm_id, target_x und target_y sind erforderlich.');
        }

        $state = CityState::loadForPlayer((int) $session['player_id']);
        if ($state === null) {
            Response::error(404, 'NO_CITY', 'Keine Stadt gefunden.');
        }

        $city   = $state['city'];
        $cityId = (int) $city['id'];

        try {
            $marchId = \Conquer\Game\March\MarchDispatcher::dispatchCharm(
                playerId:       (int) $session['player_id'],
                cityId:         $cityId,
                originX:        (int) $city['coord_x'],
                originY:        (int) $city['coord_y'],
                targetX:        $targetX,
                targetY:        $targetY,
                charmId:        $charmId,
                selectedTroops: $troops,
                requestId: isset($body['request_id'])&&is_string($body['request_id'])?$body['request_id']:null,
            );
        } catch (\RuntimeException|\DomainException $e) {
            if($e->getMessage()==='REQUEST_ID_CONFLICT')Response::error(409,'REQUEST_ID_CONFLICT','Diese Anfrage-ID wurde bereits für einen anderen Marsch verwendet.');
            if($e->getMessage()==='REQUEST_ID_INVALID')Response::error(400,'REQUEST_ID_INVALID','request_id muss 16 bis 80 Zeichen aus Buchstaben, Zahlen, _ oder - enthalten.');
            if($e->getMessage()==='REQUEST_BUSY')Response::error(409,'REQUEST_BUSY','Deine Marschdaten werden gerade aktualisiert. Versuche es erneut.');
            if($e instanceof \DomainException)Response::error(in_array($e->getCode(),[403,409],true)?$e->getCode():422,'WORLD_RULE',$e->getMessage());
            if ($e->getMessage() === 'MARCH_SLOT_FULL') {
                Response::error(400, 'march_slot_full', 'Alle Marsch-Slots belegt. Warte auf eine Rückkehr.');
            }
            Response::error(400, 'DISPATCH_FAILED', $e->getMessage());
        }

        Response::ok(['march_id' => $marchId]);
    }

    /**
     * POST /api/march/dispatch-player
     *
     * Body: { target_x: int, target_y: int, troops: {code: count} }
     */
    public static function dispatchPlayer(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($csrf === '' || !hash_equals($session['csrf_token'], $csrf)) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }

        $body = self::readArmyRequest();
        $targetX=$body['target_x'];$targetY=$body['target_y'];$troops=$body['troops']??null;
        if(!is_array($troops)){Response::error(400,'INVALID_INPUT','Eine Truppenzusammenstellung ist erforderlich.');}

        $state = CityState::loadForPlayer((int) $session['player_id']);
        if ($state === null) {
            Response::error(404, 'NO_CITY', 'Keine Stadt gefunden.');
        }

        $city   = $state['city'];
        $cityId = (int) $city['id'];

        try {
            $marchId = MarchDispatcher::dispatchPlayerAttack(
                playerId:       (int) $session['player_id'],
                cityId:         $cityId,
                originX:        (int) $city['coord_x'],
                originY:        (int) $city['coord_y'],
                targetX:        $targetX,
                targetY:        $targetY,
                selectedTroops: $troops,
            );
        } catch (\RuntimeException|\DomainException $e) {
            if($e instanceof \DomainException)Response::error(in_array($e->getCode(),[403,409],true)?$e->getCode():422,'WORLD_RULE',$e->getMessage());
            if ($e->getMessage() === 'MARCH_SLOT_FULL') {
                Response::error(400, 'march_slot_full', 'Alle Marsch-Slots belegt. Warte auf eine Rückkehr.');
            }
            Response::error(400, 'DISPATCH_FAILED', $e->getMessage());
        }

        Response::ok(['march_id' => $marchId]);
    }

    /**
     * POST /api/march/dispatch-scout
     *
     * Body: { target_x: int, target_y: int }
     */
    public static function dispatchScout(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($csrf === '' || !hash_equals($session['csrf_token'], $csrf)) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }

        $body    = json_decode((string) file_get_contents('php://input'), true) ?? [];
        $targetX = (int) ($body['target_x'] ?? -1);
        $targetY = (int) ($body['target_y'] ?? -1);

        if ($targetX < 0 || $targetY < 0) {
            Response::error(400, 'INVALID_INPUT', 'target_x und target_y sind erforderlich.');
        }

        $state = CityState::loadForPlayer((int) $session['player_id']);
        if ($state === null) {
            Response::error(404, 'NO_CITY', 'Keine Stadt gefunden.');
        }

        $city   = $state['city'];
        $cityId = (int) $city['id'];

        try {
            $marchId = MarchDispatcher::dispatchScout(
                playerId: (int) $session['player_id'],
                cityId:   $cityId,
                originX:  (int) $city['coord_x'],
                originY:  (int) $city['coord_y'],
                targetX:  $targetX,
                targetY:  $targetY,
            );
        } catch (\RuntimeException|\DomainException $e) {
            if($e instanceof \DomainException)Response::error(in_array($e->getCode(),[403,409],true)?$e->getCode():422,'WORLD_RULE',$e->getMessage());
            if ($e->getMessage() === 'MARCH_SLOT_FULL') {
                Response::error(400, 'march_slot_full', 'Alle Marsch-Slots belegt. Warte auf eine Rückkehr.');
            }
            Response::error(400, 'DISPATCH_FAILED', $e->getMessage());
        }

        Response::ok(['march_id' => $marchId]);
    }

    /**
     * POST /api/march/dispatch-gather
     *
     * Body: { target_x: int, target_y: int, troops: {code: count} }
     * Legacy clients may continue to send troop_count instead of troops.
     */
    public static function dispatchGather(array $session,bool $attack=false): void
    {
        if (!isset($session['player_id'], $session['csrf_token'])) {
            Response::error(401, 'UNAUTHENTICATED', 'Bitte melde dich an.');
        }
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($csrf === '' || !hash_equals($session['csrf_token'], $csrf)) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }

        $body = self::readArmyRequest();
        $targetX = $body['target_x'];
        $targetY = $body['target_y'];
        $troops = null;
        $troopCount = 0;
        if (array_key_exists('troops', $body)) {
            if (!is_array($body['troops'])) { Response::error(400, 'INVALID_INPUT', 'Eine gültige Truppenzusammenstellung ist erforderlich.'); }
            $troops = $body['troops'];
        } else {
            $troopCount = $body['troop_count'] ?? null;
            if (!is_int($troopCount) || $troopCount < 1 || $troopCount > \Conquer\Game\March\MarchArmy::MAX_CAP) {
                Response::error(400, 'INVALID_INPUT', 'Entsende zwischen 1 und 50.000 Truppen zum Sammeln.');
            }
        }

        $state = CityState::loadForPlayer((int) $session['player_id']);
        if ($state === null) {
            Response::error(404, 'NO_CITY', 'Keine Stadt gefunden.');
        }

        $cityId = (int) $state['city']['id'];

        try {
            $marchId = MarchDispatcher::dispatchGather(
                playerId:   (int) $session['player_id'],
                cityId:     $cityId,
                targetX:    $targetX,
                targetY:    $targetY,
                troopCount: $troopCount,
                selectedTroops: $troops,
                attack: $attack,
            );
        } catch (\RuntimeException|\DomainException $e) {
            if($e instanceof \DomainException)Response::error(in_array($e->getCode(),[403,409],true)?$e->getCode():422,'WORLD_RULE',$e->getMessage());
            if ($e->getMessage() === 'MARCH_SLOT_FULL') {
                Response::error(400, 'march_slot_full', 'Alle Marsch-Slots belegt. Warte auf eine Rückkehr.');
            }
            Response::error(400, 'DISPATCH_FAILED', $e->getMessage());
        }

        Response::ok(['march_id' => $marchId]);
    }

    /**
     * POST /api/march/recall
     *
     * Body: { march_id: int }
     * Recalls a marching march — state must be 'marching', not yet arrived.
     */
    public static function recall(array $params): void
    {
        $session=Session::current();if(!$session)Response::error(401,'UNAUTHENTICATED','Bitte melde dich an.');
        $csrf=$_SERVER['HTTP_X_CSRF_TOKEN']??'';if($csrf===''||!hash_equals($session['csrf_token'],$csrf))Response::error(403,'CSRF_INVALID','Bitte lade das Spiel neu.');
        $body=json_decode(file_get_contents('php://input')?:'',true)??[];
        if(!is_array($body))Response::error(400,'INVALID_JSON','Ungültige Anfrage.');
        $body['action']='march.recall';
        try{$result=\Conquer\Game\Defense\DefenseService::action((int)$session['player_id'],$body);}
        catch(\RuntimeException|\DomainException $e){Response::error(in_array($e->getCode(),[403,409],true)?$e->getCode():422,'MARCH_RULE',$e->getMessage());}
        Response::ok($result);
    }

    /**
     * GET /api/map/marches
     *
     * Returns all publicly visible active marches (all players, types 5+7).
     * Used for the map overlay — includes origin_x/origin_y per march.
     */
    public static function listAll(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $marches = MarchDispatcher::listAllActive();
        Response::ok(['marches' => $marches]);
    }

    /**
     * POST /api/march/reinforce
     *
     * Body: { "target_player_id": int, "troops": {"50100101": 500} }
     * Sends troops as reinforcements to an alliance member's city.
     */
    public static function dispatchReinforce(array $params): void
    {
        $session=Session::current();if(!$session)Response::error(401,'UNAUTHENTICATED','Bitte melde dich an.');
        $csrf=$_SERVER['HTTP_X_CSRF_TOKEN']??'';if($csrf===''||!hash_equals($session['csrf_token'],$csrf))Response::error(403,'CSRF_INVALID','Bitte lade das Spiel neu.');
        $body=json_decode(file_get_contents('php://input')?:'',true)??[];
        if(!is_array($body))Response::error(400,'INVALID_JSON','Ungültige Anfrage.');
        $body['action']='reinforce';
        try{$result=\Conquer\Game\Defense\DefenseService::action((int)$session['player_id'],$body);}
        catch(\RuntimeException|\DomainException $e){Response::error(in_array($e->getCode(),[403,409],true)?$e->getCode():422,'MARCH_RULE',$e->getMessage());}
        Response::ok($result);
    }

    /**
     * POST /api/march/recall-reinforce
     *
     * Body: { "reinforcement_id": int }
     * Recalls active reinforcements — troops return to sender.
     */
    public static function recallReinforcement(array $params): void
    {
        $session=Session::current();if(!$session)Response::error(401,'UNAUTHENTICATED','Bitte melde dich an.');
        $csrf=$_SERVER['HTTP_X_CSRF_TOKEN']??'';if($csrf===''||!hash_equals($session['csrf_token'],$csrf))Response::error(403,'CSRF_INVALID','Bitte lade das Spiel neu.');
        $body=json_decode(file_get_contents('php://input')?:'',true)??[];
        if(!is_array($body))Response::error(400,'INVALID_JSON','Ungültige Anfrage.');
        $body['action']='reinforcement.recall';
        try{$result=\Conquer\Game\Defense\DefenseService::action((int)$session['player_id'],$body);}
        catch(\RuntimeException|\DomainException $e){Response::error(in_array($e->getCode(),[403,409],true)?$e->getCode():422,'MARCH_RULE',$e->getMessage());}
        Response::ok($result);
    }

    /**
     * GET /api/march/reinforcements
     *
     * Returns active reinforcements sent by and received by the current player.
     */
    public static function listReinforcements(array $session): void
    {
        $playerId = (int) $session['player_id'];
        $db       = Connection::getInstance();

        $state = CityState::loadForPlayer($playerId);
        $cityId = $state !== null ? (int) $state['city']['id'] : 0;

        $sent = $db->query(
            "SELECT r.id, r.target_player_id, p.username AS target_username,
                    r.troops_json, r.state, r.created_at
             FROM reinforcements r
             JOIN players p ON p.id = r.target_player_id
             WHERE r.sender_id = ? AND r.sender_city_id = ? AND r.state = 'active'
             ORDER BY r.created_at DESC",
            [$playerId, $cityId],
        )->fetchAll();

        $received = [];
        if ($cityId > 0) {
            $received = $db->query(
                "SELECT r.id, r.sender_id, p.username AS sender_username,
                        r.troops_json, r.state, r.created_at
                 FROM reinforcements r
                 JOIN players p ON p.id = r.sender_id
                 WHERE r.target_city_id = ? AND r.state = 'active'
                 ORDER BY r.created_at DESC",
                [$cityId],
            )->fetchAll();
        }

        // Decode troops_json for display
        foreach ($sent as &$s) {
            $s['troops'] = json_decode($s['troops_json'] ?? '{}', true);
            unset($s['troops_json']);
        }
        unset($s);
        foreach ($received as &$r) {
            $r['troops'] = json_decode($r['troops_json'] ?? '{}', true);
            unset($r['troops_json']);
        }
        unset($r);

        Response::ok(['sent' => $sent, 'received' => $received]);
    }

    /**
     * GET /api/march/list
     *
     * Returns all active marches (marching + returning) for the player.
     */
    public static function list(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $playerId = (int) $session['player_id'];

        // Lazy tick — resolve arrived/returning marches without needing a cron job.
        MarchTick::runForPlayer($playerId);

        $marches = MarchDispatcher::listActive($playerId);

        // Decode troops_json for each march.
        foreach ($marches as &$m) {
            $m['troops'] = json_decode($m['troops_json'] ?? '{}', true);
            unset($m['troops_json']);
        }
        unset($m);

        Response::ok(['marches' => $marches]);
    }

    /** Strict input shared by the two editable troop-composition dialogs. */
    private static function readArmyRequest(): array
    {
        $raw = (string) file_get_contents('php://input');
        if (strlen($raw) > 8192) { Response::error(413, 'INVALID_INPUT', 'Die Anfrage ist zu groß.'); }
        try { $body = json_decode($raw, true, 32, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { Response::error(400, 'INVALID_INPUT', 'Ungültige Anfrage.'); }
        if (!is_array($body) || array_is_list($body)) { Response::error(400, 'INVALID_INPUT', 'Ein Aktionsobjekt ist erforderlich.'); }
        $max = max(0, (int) Connection::getInstance()->query('SELECT map_size FROM worlds WHERE id=?', [WorldContext::id()])->fetchColumn() - 1);
        foreach (['target_x', 'target_y'] as $key) {
            if (!isset($body[$key]) || !is_int($body[$key]) || $body[$key] < 0 || $body[$key] > $max) {
                Response::error(400, 'INVALID_INPUT', 'Zielkoordinaten müssen ganze Zahlen zwischen 0 und '.$max.' sein.');
            }
        }
        return $body;
    }
}
