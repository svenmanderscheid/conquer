<?php
declare(strict_types=1);

namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Game\City\{BuildingData, BuildingUpgrader, CityState};

/** Narrow building read model; shares the existing account, city and build queue. */
final class City3dHandler
{
    private const NAMES = [
        'castle'=>'Festung', 'wall'=>'Stadtmauer', 'farm'=>'Bauernhof',
        'lumber_camp'=>'Holzfällerlager', 'quarry'=>'Steinbruch', 'gold_mine'=>'Goldmine',
        'storage'=>'Lagerhaus', 'treasure_house'=>'Schatzkammer', 'barrack'=>'Kaserne', 'archery_range'=>'Schützenlager', 'stable'=>'Reiterhof',
        'hospital'=>'Krankenhaus', 'academy'=>'Akademie', 'trading_post'=>'Handelsposten',
        'hall_of_alliance'=>'Allianzhalle', 'watch_tower'=>'Wachturm',
    ];

    public static function snapshot(array $params): void
    {
        header('Cache-Control: no-store');
        $session = Session::current();
        if (!$session) { Response::error(401, 'UNAUTHENTICATED', 'Bitte melde dich an.'); }
        \Conquer\Game\Research\ResearchProcessor::processQueue((int) $session['player_id']);
        $state = CityState::loadForPlayer((int) $session['player_id']);
        if (!$state) { Response::error(404, 'NO_CITY', 'Keine Stadt vorhanden.'); }
        $buildings = $state['buildings'];
        $ownedItems = array_column(\Conquer\Game\Inventory\InventoryService::getInventory((int)$session['player_id']),'quantity','item_code');
        foreach ($buildings as $code => &$building) {
            $level = (int) $building['level'];
            $requirements = BuildingData::getUpgradeRequirements($code, $level + 1);
            $cost = $level < 30 ? BuildingData::getCost($code, $level + 1) : null;
            $itemRequirements = BuildingData::itemRequirements($code, $level + 1, $ownedItems);
            $reasons = [];
            foreach ($itemRequirements as $item) if (!$item['met']) $reasons[] = 'Es fehlt: '.$item['name'].' ('.$item['owned'].' / '.$item['count'].').';
            if ($level >= 30) { $reasons[] = 'Die höchste Stufe ist erreicht.'; }
            $slots = (int) $session['vip_level'] >= 4 ? 2 : 1;
            if (count($state['build_queue']) + \Conquer\Game\City\BuildingPlotService::busy((int)$state['city']['id']) >= $slots) { $reasons[] = 'Der Bauplatz ist belegt.'; }
            foreach ($state['build_queue'] as $entry) {
                if ($entry['building_code'] === $code) { $reasons[] = 'Dieses Gebäude wird bereits ausgebaut.'; }
            }
            $required = [];
            foreach ($requirements as $req => $needed) {
                $current = (int) $state['buildings'][$req]['level'];
                $required[] = ['code'=>$req, 'name'=>self::NAMES[$req], 'level'=>$needed, 'current'=>$current, 'met'=>$current >= $needed];
                if ($level < 30 && $current < $needed) { $reasons[] = self::NAMES[$req] . ' muss Stufe ' . $needed . ' erreichen.'; }
            }
            foreach ($cost ?? [] as $resource => $amount) {
                if ((int) $state['city'][$resource] < $amount) { $reasons[] = 'Es fehlen Ressourcen.'; break; }
            }
            $building += [
                'name'=>self::NAMES[$code], 'cost'=>$cost, 'item_requirements'=>$itemRequirements,
                'seconds'=>$level < 30 ? BuildingData::getBuildTime($code, $level + 1, $state['vip']['bonuses'] ?? []) : 0,
                'requirements'=>$required, 'can_upgrade'=>!$reasons, 'reasons'=>array_values(array_unique($reasons)),
                'power'=>BuildingData::getTotalPower($code, $level),
                'next_power'=>BuildingData::getTotalPower($code, min(30, $level + 1)),
            ];
        }
        unset($building);
        $citySkin = \Conquer\Db\Connection::getInstance()->query(
            'SELECT city_skin FROM kingdom_profiles WHERE player_id = ?',
            [(int) $session['player_id']],
        )->fetchColumn() ?: 'default';
        Response::ok([
            'server_time'=>time(), 'player'=>['name'=>$session['username'], 'csrf'=>$session['csrf_token']],
            'city'=>array_intersect_key($state['city'], array_flip(['id','world_id','name','wall_hp_current','wall_hp_max'])) + ['city_skin'=>$citySkin],
            'troops'=>$state['troops'],
            'troop_defs'=>\Conquer\Game\City\TroopData::forCity($state,\Conquer\Game\Research\BuffEngine::getBuffs((int)$session['player_id'],(int)$state['city']['world_id']),\Conquer\Db\Connection::getInstance()->query('SELECT research_code,level FROM player_research WHERE player_id=? AND world_id=?',[(int)$session['player_id'],(int)$state['city']['world_id']])->fetchAll(\PDO::FETCH_KEY_PAIR),\Conquer\Game\Buff\ActiveBuffService::getMultiplier((int)$session['player_id'],'training_boost')),
            'production_rates'=>$state['production_rates'],
            'storage_caps'=>BuildingData::getStorageCaps($state['buildings']),
            'resources'=>array_intersect_key($state['city'], array_flip(['food','lumber','stone','gold'])),
            'plot_queue'=>\Conquer\Game\City\BuildingPlotService::queue((int)$state['city']['id']),
            'building_plots'=>\Conquer\Game\City\BuildingPlotService::snapshot($state),
            'buildings'=>$buildings, 'build_queue'=>$state['build_queue'],
            'troop_queue'=>$state['troop_queue'],
            // Cancelled jobs are deleted; only settled jobs are completion receipts.
            'recent_training'=>\Conquer\Db\Connection::getInstance()->query(
                'SELECT id,troop_code,count,finishes_at FROM troop_queue WHERE city_id = ? AND is_processed = 1 ORDER BY finishes_at DESC,id DESC LIMIT 3',
                [(int) $state['city']['id']],
            )->fetchAll(),
            'research_queue'=>\Conquer\Db\Connection::getInstance()->query(
                'SELECT id,research_code,level_to,started_at,finishes_at FROM research_queue WHERE player_id = ? AND world_id = ? AND is_processed = 0 ORDER BY finishes_at',
                [(int) $session['player_id'],(int)$state['city']['world_id']],
            )->fetchAll(),
        ]);
    }

    /** Expected level prevents delayed retries from purchasing another level. */
    public static function upgrade(array $params): void
    {
        header('Cache-Control: no-store');
        $session = Session::current();
        if (!$session) { Response::error(401, 'UNAUTHENTICATED', 'Bitte melde dich an.'); }
        if (!hash_equals($session['csrf_token'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
            Response::error(403, 'CSRF_INVALID', 'Die Sitzung ist abgelaufen. Bitte lade die Seite neu.');
        }
        $body = json_decode(file_get_contents('php://input') ?: '', true);
        if (!is_array($body) || !is_string($body['building_code'] ?? null)
            || !in_array($body['building_code'], CityState::BUILDING_CODES, true)
            || !is_int($body['expected_level'] ?? null) || $body['expected_level'] < 1 || $body['expected_level'] > 30) {
            Response::error(400, 'INVALID_INPUT', 'Gebäude oder Stufe ist ungültig.');
        }
        $state = CityState::loadForPlayer((int) $session['player_id']);
        if (!$state) { Response::error(404, 'NO_CITY', 'Keine Stadt vorhanden.'); }
        $code = $body['building_code'];
        if ((int) $state['buildings'][$code]['level'] !== $body['expected_level']) {
            Response::error(409, 'STALE_LEVEL', 'Das Gebäude wurde inzwischen verändert. Der aktuelle Stand wird geladen.');
        }
        try {
            $entry = BuildingUpgrader::start((int) $state['city']['id'], $code, $state['city'], $state['buildings'], (int) $session['vip_level'], $state['vip']['bonuses'] ?? []);
        } catch (\RuntimeException $e) {
            Response::error(422, 'UPGRADE_FAILED', 'Ausbau nicht möglich. Prüfe Bauplatz, Voraussetzungen und Ressourcen.');
        }
        Response::ok(['queue_entry'=>$entry]);
    }
    public static function plot(array $params):void {
        header('Cache-Control: no-store');$session=Session::current();
        if(!$session)Response::error(401,'UNAUTHENTICATED','Bitte melde dich an.');
        if(!hash_equals($session['csrf_token'],$_SERVER['HTTP_X_CSRF_TOKEN']??''))Response::error(403,'CSRF_INVALID','Bitte lade die Seite neu.');
        $body=json_decode(file_get_contents('php://input')?:'',true);
        if(!is_array($body)||!is_int($body['plot_id']??null)||!is_string($body['building_code']??null)||!is_int($body['expected_level']??null))Response::error(400,'INVALID_INPUT','Ungültiger Bauauftrag.');
        try{$plot=\Conquer\Game\City\BuildingPlotService::start((int)$session['player_id'],$body['plot_id'],$body['building_code'],$body['expected_level']);}
        catch(\DomainException $e){Response::error(422,'PLOT_BUILD_FAILED',$e->getMessage());}
        Response::ok(['plot'=>$plot]);
    }
}
