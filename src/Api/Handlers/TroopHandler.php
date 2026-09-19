<?php
declare(strict_types=1);

namespace Conquer\Api\Handlers;

use Conquer\Game\World\WorldContext;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Db\Connection;
use Conquer\Game\City\CityState;
use Conquer\Game\City\TroopData;
use Conquer\Game\City\TroopTrainer;
use Conquer\Game\City\ResourceTick;

/**
 * Handles /api/troops/* endpoints.
 *
 * GET  /api/troops/list              — current troops + training queue + troop definitions
 * POST /api/troops/train             — enqueue a training batch
 * POST /api/troops/cancel-train/:id  — cancel a training batch (proportional refund)
 * POST /api/troops/speedup-train/:id — speedup training using an item
 * POST /api/troops/promote           — promote T1→T2 (or higher) troops
 * POST /api/troops/heal              — speedup hospital healing using an item
 */
final class TroopHandler
{
    private function __construct() {}

    /**
     * GET /api/troops/list
     *
     * Returns:
     *   troops      — {troop_code: count} for all troops the city has
     *   queue       — active training queue entries
     *   definitions — all troop definitions (for the UI)
     */
    public static function list(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $state = CityState::loadForPlayer((int) $session['player_id']);
        if ($state === null) {
            Response::error(404, 'NO_CITY', 'No city found.');
        }

        Response::ok([
            'troops'      => $state['troops'],
            'troop_queue' => $state['troop_queue'],
            'definitions' => array_values(TroopData::all()),
        ]);
    }

    /**
     * POST /api/troops/train
     *
     * Body (JSON): { troop_code: int, count: int, barrack_slot?: int }
     * Header: X-CSRF-Token
     */
    public static function train(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Bitte melde dich an.');
        }
        try{WorldContext::assertActionAvailable();}catch(\DomainException $e){Response::error(409,'WORLD_UNAVAILABLE',$e->getMessage());}


        // CSRF check
        $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($csrf === '' || !hash_equals((string) ($session['csrf_token'] ?? ''), $csrf)) {
            Response::error(403, 'CSRF_INVALID', 'Die Sitzung ist abgelaufen. Bitte lade die Seite neu.');
        }

        $body = (string) file_get_contents('php://input');
        $data = json_decode($body);
        if (!$data instanceof \stdClass) {
            Response::error(400, 'INVALID_INPUT', 'Der Trainingsauftrag muss ein gültiges JSON-Objekt sein.');
        }

        $troopCode = $data->troop_code ?? null;
        $count = $data->count ?? null;
        $barrackSlot = property_exists($data, 'barrack_slot') ? $data->barrack_slot : null;

        if (!is_int($troopCode) || $troopCode <= 0 || TroopData::get($troopCode) === null) {
            Response::error(400, 'INVALID_INPUT', 'Wähle einen gültigen Truppentyp.');
        }
        if (!is_int($count) || $count < 1 || $count > 50000) {
            Response::error(400, 'INVALID_INPUT', 'Wähle eine ganze Truppenanzahl von 1 bis 50.000.');
        }
        if (property_exists($data,'barrack_slot') && (!is_int($barrackSlot) || $barrackSlot !== TroopData::slotFor($troopCode))) {
            Response::error(400, 'INVALID_INPUT', 'Der Ausbildungsplatz passt nicht zur Truppenart.');
        }

        $state = CityState::loadForPlayer((int) $session['player_id']);
        if ($state === null) {
            Response::error(404, 'NO_CITY', 'Keine Stadt vorhanden.');
        }

        // Apply current resource tick so the check uses up-to-date resources.
        $city      = ResourceTick::apply($state['city'], $state['buildings']);
        $buildings = $state['buildings'];

        try {
            $start=static function()use($city,$buildings,$troopCode,$count,$barrackSlot):array{
                TroopTrainer::train($city,$buildings,$troopCode,$count,$barrackSlot);
                return ['message'=>$count.' Truppen werden ausgebildet.'];
            };
            if(property_exists($data,'operation_key')){
                \Conquer\Game\Operation::run((int)$session['player_id'],['action'=>'troops.train','operation_key'=>$data->operation_key,'world_id'=>(int)$city['world_id'],'troop_code'=>$troopCode,'count'=>$count,'barrack_slot'=>TroopData::slotFor($troopCode)],$start);
            }else $start();
        } catch (\RuntimeException|\DomainException $e) {
            $reason = $e->getMessage();
            $message = match (true) {
                $e instanceof \DomainException => $reason,
                str_starts_with($reason, 'Not enough resources') => 'Es fehlen Ressourcen für diese Truppenanzahl.',
                str_starts_with($reason, 'Barrack slot ') => 'Dieses Ausbildungsgebäude bildet bereits Truppen aus.',
                str_starts_with($reason, 'TRAINING_LIMIT: ') => substr($reason,16),
                default => null,
            };
            if ($message === null) {
                // An unexpected storage failure may have happened after commit.
                Response::error(500, 'TRAIN_UNCONFIRMED', 'Der Trainingsauftrag konnte nicht bestätigt werden. Bitte prüfe den aktuellen Stand.');
            }
            Response::error(400, 'TRAIN_FAILED', $message);
        }

        Response::ok([
            'message' => $count . ' Truppen werden ausgebildet.',
        ]);
    }

    /**
     * POST /api/troops/cancel-train/:queue_id
     *
     * Cancels a training batch. Refunds resources proportionally
     * based on how many troops have NOT yet been trained.
     *
     * Proportional refund: (remaining_count / total_count) × full_cost
     * Remaining count is estimated from time elapsed vs total training time.
     */
    public static function cancelTrain(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }

        $supplied = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($supplied === '' || !hash_equals($session['csrf_token'], $supplied)) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }

        $queueId  = (int) ($params['queue_id'] ?? 0);
        $playerId = (int) $session['player_id'];

        if ($queueId <= 0) {
            Response::error(400, 'INVALID_INPUT', 'Invalid queue_id.');
        }

        try{$result=TroopTrainer::cancel($playerId,$queueId);}
        catch(\DomainException $e){Response::error($e->getCode()===404?404:400,'NOT_FOUND',$e->getMessage());}
        Response::ok($result);
    }

    /**
     * POST /api/troops/speedup-train/:queue_id
     *
     * Uses a speedup item (generic or training subcategory) to reduce a troop
     * training timer. If finishes_at moves into the past, the troops are added
     * to city_troops immediately.
     *
     * Body: {"item_code": 10103031}
     */
    public static function speedupTrain(array $params): void
    {
        $session = Session::current();
        if ($session === null) {
            Response::error(401, 'UNAUTHENTICATED', 'Not logged in.');
        }
        try{WorldContext::assertActionAvailable();}catch(\DomainException $e){Response::error(409,'WORLD_UNAVAILABLE',$e->getMessage());}


        $supplied = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($supplied === '' || !hash_equals($session['csrf_token'], $supplied)) {
            Response::error(403, 'CSRF_INVALID', 'CSRF token missing or invalid.');
        }

        $queueId  = (int) ($params['queue_id'] ?? 0);
        $playerId = (int) $session['player_id'];

        if ($queueId <= 0) {
            Response::error(400, 'INVALID_INPUT', 'Invalid queue_id.');
        }

        $body     = json_decode(file_get_contents('php://input') ?: '', true) ?? [];
        $itemCode = (int) ($body['item_code'] ?? 0);

        if ($itemCode <= 0) {
            Response::error(400, 'MISSING_FIELD', 'item_code is required.');
        }

        // Load item definition.
        $itemsJson = file_get_contents(ROOT_DIR . '/data/items.json') ?: '{}';
        $itemsData = json_decode($itemsJson, true) ?? [];
        $itemDef   = null;
        foreach ($itemsData['items'] ?? [] as $item) {
            if ((int) $item['code'] === $itemCode) {
                $itemDef = $item;
                break;
            }
        }

        if ($itemDef === null) {
            Response::error(400, 'UNKNOWN_ITEM', 'Item code ' . $itemCode . ' not found.');
        }

        $cat    = $itemDef['category']    ?? '';
        $subcat = $itemDef['subcategory'] ?? '';
        if ($cat !== 'speedup' || !in_array($subcat, ['generic', 'training'], true)) {
            Response::error(400, 'WRONG_ITEM_TYPE', 'This item cannot be used for training speedups.');
        }

        $durationSeconds = (int) ($itemDef['duration_seconds'] ?? 0);
        if ($durationSeconds <= 0) {
            Response::error(400, 'ITEM_NO_DURATION', 'Item has no valid duration.');
        }

        $db = Connection::getInstance();

        $entry = $db->query(
            'SELECT tq.id, tq.city_id, tq.troop_code, tq.count, tq.is_processed,
                    TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), tq.finishes_at) AS secs_remaining
             FROM   troop_queue tq
             JOIN   cities c ON c.id = tq.city_id
             WHERE  tq.id = ? AND c.player_id = ? AND c.world_id = ? AND tq.is_processed = 0',
            [$queueId, $playerId,WorldContext::id()],
        )->fetch();

        if ($entry === false) {
            Response::error(404, 'NOT_FOUND', 'Troop queue entry not found or already completed.');
        }

        $operation=['action'=>'inventory.use','item_code'=>$itemCode,'queue_type'=>'training','queue_id'=>$queueId,'expected_world_id'=>WorldContext::id()];
        if(array_key_exists('operation_key',$body))$operation['operation_key']=$body['operation_key'];
        try{\Conquer\Game\Kingdom\KingdomService::action($playerId,$operation);}
        catch(\DomainException $e){Response::error(400,'SPEEDUP_FAILED',$e->getMessage());}
        TroopTrainer::processQueue($db,(int)$entry['city_id']);
        $remaining=max(0,(int)$db->query('SELECT TIMESTAMPDIFF(SECOND,UTC_TIMESTAMP(),finishes_at) FROM troop_queue WHERE id=?',[$queueId])->fetchColumn());
        Response::ok(['speedup_applied'=>true,'item_code'=>$itemCode,'duration_seconds'=>$durationSeconds,'instantly_finished'=>$remaining===0,'secs_remaining'=>$remaining]);
    }

    /**
     * POST /api/troops/promote
     *
     * Promotes troops from one tier to the next (T1→T2, T2→T3, etc.).
     * Troop code mapping: tier is encoded in position 4 of the 9-digit code:
     *   50100101 → Infantry T1, 50100201 → Infantry T2, etc.
     * Promote: source_code + 100 (next tier), e.g. 50100101 → 50100201.
     *
     * Body: {"troop_code": 50100101, "count": 100}
     *
     * Cost:   70% of the promoted troop's training cost.
     * Time:   50% of the promoted troop's training time, added as a troop_queue entry.
     * Effect: source troops deducted immediately; promoted troops added after queue finishes.
     */
    public static function promote(array $params): void
    {
        $session=Session::current();if(!$session)Response::error(401,'UNAUTHENTICATED','Bitte melde dich an.');
        $csrf=$_SERVER['HTTP_X_CSRF_TOKEN']??'';if($csrf===''||!hash_equals($session['csrf_token'],$csrf))Response::error(403,'CSRF_INVALID','Bitte lade das Spiel neu.');
        $body=json_decode(file_get_contents('php://input')?:'',true)??[];
        if(!is_array($body))Response::error(400,'INVALID_JSON','Ungültige Anfrage.');$body['action']='promotion.start';
        try{$result=\Conquer\Game\Defense\DefenseService::action((int)$session['player_id'],$body);}catch(\RuntimeException|\DomainException $e){Response::error(422,'PROMOTION_FAILED',$e->getMessage());}
        Response::ok($result);
    }

    /**
     * POST /api/troops/heal
     *
     * Compatibility route for the authenticated hospital speedup command.
     * Body: item_code, quantity, batch_id, operation_key, expected_world_id.
     */
    public static function heal(array $params): void
    {
        HospitalHandler::speedup($params);
    }
}
