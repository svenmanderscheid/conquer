<?php
declare(strict_types=1);
namespace Conquer\Api\Handlers;

use Conquer\Api\Response;
use Conquer\Auth\Session;
use Conquer\Db\Connection;
use Conquer\Game\City\{BuildingData,CityState,TroopData};
use Conquer\Game\March\MarchTick;
use Conquer\Game\Research\{ResearchData,ResearchProcessor};
use Conquer\Game\Shrine\CongressService;

/** Compact read model for the illustrated game client. All actions use the game services. */
final class GameHandler
{
    public static function state(array $params): void
    {
        $session = Session::current();
        if (!$session) { Response::error(401, 'UNAUTHENTICATED', 'Bitte melde dich an.'); }
        $pid = (int) $session['player_id'];
        $returnSince = isset($_GET['return_since']) ? filter_var($_GET['return_since'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>time()]]) : null;
        if ($returnSince === false) { Response::error(400, 'INVALID_TIME', 'Ungültiger Zeitpunkt für die Rückkehrübersicht.'); }
        \Conquer\Game\Dungeon\DungeonService::tick($pid);
        MarchTick::runForPlayer($pid);
        \Conquer\Game\Rally\RallyService::tick();
        ResearchProcessor::processQueue($pid);
        $state = CityState::loadForPlayer($pid);
        if (!$state) { Response::error(404, 'NO_CITY', 'Keine Stadt vorhanden.'); }
        $db = Connection::getInstance();
        $city = $state['city'];
        $worldId=(int)$city['world_id'];
        $buffs=\Conquer\Game\Research\BuffEngine::getBuffs($pid,$worldId);
        $world=$db->query('SELECT speed_factor,gather_factor,map_size FROM worlds WHERE id=?',[$worldId])->fetch();
        $mapMax=(int)$world['map_size']-1;
        $mapX = filter_var($_GET['map_x'] ?? $city['coord_x'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>0,'max_range'=>$mapMax]]);
        $mapY = filter_var($_GET['map_y'] ?? $city['coord_y'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>0,'max_range'=>$mapMax]]);
        $mapRadius=filter_var($_GET['map_radius']??30,FILTER_VALIDATE_INT,['options'=>['min_range'=>12,'max_range'=>60]]);
        if ($mapX === false || $mapY === false || $mapRadius === false) { Response::error(400, 'INVALID_COORDINATES', 'Ungültige Kartenkoordinaten.'); }
        \Conquer\Game\Map\FrontierService::refresh($pid, $city);
        $ownedItems = array_column(\Conquer\Game\Inventory\InventoryService::getInventory($pid),'quantity','item_code');
        foreach ($state['buildings'] as $code => &$building) {
            $next = $building['level'] + 1;
            $building['cost'] = BuildingData::getCost($code, $next);
            $building['item_requirements'] = BuildingData::itemRequirements($code, $next, $ownedItems);
            $building['seconds'] = BuildingData::getBuildTime($code, $next, $state['vip']['bonuses'] ?? []);
            $effects=['castle'=>['base_march_capacity','Marschkapazität ohne Boni'],'barrack'=>['base_training_amount','Truppen je Ausbildung ohne Boni'],'archery_range'=>['base_ranged_training_amount','Truppen je Ausbildung ohne Boni'],'stable'=>['base_cavalry_training_amount','Truppen je Ausbildung ohne Boni'],'storage'=>['storage_protection_flat','Grundschutz je Ressource']];
            if(isset($effects[$code])){
                [$key,$label]=$effects[$code];
                $current=\Conquer\Game\City\BuildingProgression::atLevels([$code=>$building['level']]);
                $future=\Conquer\Game\City\BuildingProgression::atLevels([$code=>$next]);
                $building['progression']=['label'=>$label,'current'=>$current[$key],'next'=>$future[$key]];
            }
            $building['production'] = $state['production_rates'][$code] ?? 0;
            $building['requirements'] = BuildingData::getUpgradeRequirements($code, $next);
        }
        unset($building);
        // Public map identities only; never include garrisons or resource stocks.
        $players=$db->query("SELECT p.id,p.username,COALESCE(k.display_name,p.username) AS display_name,
            c.coord_x,c.coord_y,c.castle_level,COALESCE(k.city_skin,'default') AS city_skin,
            COALESCE(k.name_frame,'default') AS name_frame,am.alliance_id,a.tag AS alliance_tag
            FROM cities c JOIN players p ON p.id=c.player_id
            LEFT JOIN kingdom_profiles k ON k.player_id=p.id
            LEFT JOIN alliance_members am ON am.player_id=p.id AND am.world_id=c.world_id
            LEFT JOIN alliances a ON a.id=am.alliance_id AND a.world_id=c.world_id
            WHERE c.world_id=? AND c.player_id<>? AND c.is_hidden=0
            AND c.coord_x BETWEEN ? AND ? AND c.coord_y BETWEEN ? AND ?
            ORDER BY POW(c.coord_x-?,2)+POW(c.coord_y-?,2),p.id LIMIT 80",
            [$worldId,$pid,max(0,$mapX-$mapRadius),min($mapMax,$mapX+$mapRadius),max(0,$mapY-$mapRadius),min($mapMax,$mapY+$mapRadius),$mapX,$mapY])->fetchAll();
        $monsters = $db->query('SELECT id,monster_code,coord_x,coord_y,hp_current FROM field_monsters WHERE world_id = ? AND hp_current > 0 AND coord_x BETWEEN ? AND ? AND coord_y BETWEEN ? AND ? ORDER BY POW(coord_x - ?,2)+POW(coord_y - ?,2) LIMIT 80', [$worldId,max(0,$mapX-$mapRadius),min($mapMax,$mapX+$mapRadius),max(0,$mapY-$mapRadius),min($mapMax,$mapY+$mapRadius),$mapX,$mapY])->fetchAll();
        foreach ($monsters as &$monster) {
            $monster = \Conquer\Game\Map\MonsterData::mapData($monster);
        }
        unset($monster);
        $nodes = $db->query('SELECT id,coord_x,coord_y,object_type,level,resource_amount,gatherer_march_id FROM field_objects WHERE world_id = ? AND resource_amount > 0 AND (expires_at > UTC_TIMESTAMP() OR gatherer_march_id IS NOT NULL) AND coord_x BETWEEN ? AND ? AND coord_y BETWEEN ? AND ? ORDER BY POW(coord_x - ?,2)+POW(coord_y - ?,2) LIMIT 80', [$worldId,max(0,$mapX-$mapRadius),min($mapMax,$mapX+$mapRadius),max(0,$mapY-$mapRadius),min($mapMax,$mapY+$mapRadius),$mapX,$mapY])->fetchAll();
        $nodes=\Conquer\Game\Map\FieldObjectService::withOccupations($nodes,$worldId,$pid);
        $research = $db->query('SELECT research_code,level FROM player_research WHERE player_id = ? AND world_id = ?', [$pid,$worldId])->fetchAll(\PDO::FETCH_KEY_PAIR);
        foreach($nodes as &$node){$resource=\Conquer\Game\Map\FieldObjectService::RESOURCE_BY_TYPE[(int)$node['object_type']];$node['gather_rate']=\Conquer\Game\March\GatherService::rate($resource,(int)$node['level'],$buffs,(float)$world['gather_factor']);}unset($node);
        $trainingBoost=\Conquer\Game\Buff\ActiveBuffService::getMultiplier($pid,'training_boost');
        $marchSkinMultiplier=\Conquer\Game\March\MarchSkinService::speedMultiplier(\Conquer\Game\March\MarchSkinService::currentSnapshot($pid));
        $mixedFormation=[50100101=>1,50200101=>1,50300101=>1];
        $monsterBuffs=\Conquer\Game\Player\TalentEffects::combat($buffs,'monster');
        $monsterMixed=\Conquer\Game\Research\ResearchEffects::armyBuffs($monsterBuffs,$mixedFormation);
        $rallyBuffs=\Conquer\Game\Player\TalentEffects::combat($buffs,'monster',true);
        $rallyMixed=\Conquer\Game\Research\ResearchEffects::armyBuffs($rallyBuffs,$mixedFormation,true);
        $troopDefs=[];
        foreach(TroopData::forCity($state,$buffs,$research,$trainingBoost) as $troop){
            $code=(int)$troop['code'];
            // Keep the server's full precision so the client can choose the true
            // minimum that reaches the exact threshold used by BattleEngine.
            $troop['monster_power']=\Conquer\Game\March\ArmyPower::unit($troop,$monsterMixed);
            $troop['monster_power_single_type']=\Conquer\Game\March\ArmyPower::unit($troop,\Conquer\Game\Research\ResearchEffects::armyBuffs($monsterBuffs,[$code=>1]));
            $troop['monster_rally_power']=\Conquer\Game\March\ArmyPower::unit($troop,$rallyMixed);
            $troop['monster_rally_power_single_type']=\Conquer\Game\March\ArmyPower::unit($troop,\Conquer\Game\Research\ResearchEffects::armyBuffs($rallyBuffs,[$code=>1],true));
            $troop['gather_carry']=\Conquer\Game\Research\ResearchEffects::carryPerTroop($code,\Conquer\Game\Player\TalentEffects::gather($buffs));
            $troop+=\Conquer\Game\March\MarchSpeed::readModel($code,$buffs,$marchSkinMultiplier);
            $troop['gather_speed']=\Conquer\Game\March\GatherService::troopSpeed($code,$buffs,(float)$world['speed_factor'],false,$marchSkinMultiplier);
            $troop['field_attack_speed']=\Conquer\Game\March\GatherService::troopSpeed($code,$buffs,(float)$world['speed_factor'],true,$marchSkinMultiplier);
            $troopDefs[]=$troop;
        }
        $researchQueue = $db->query('SELECT id,research_code,level_to,started_at,finishes_at FROM research_queue WHERE player_id = ? AND world_id = ? AND is_processed = 0', [$pid,$worldId])->fetchAll();
        $reports = \Conquer\Game\March\BattleReportService::list($pid);
        $marches = \Conquer\Game\March\MarchDispatcher::listActive($pid);
        $rallyMarches=\Conquer\Game\Rally\RallyService::activeMarchesForPlayer($pid);
        $marches=array_merge($marches,$rallyMarches);
        // Permanent world landmarks must be part of every map snapshot. They
        // deliberately ignore the current viewport so navigation can always
        // reach the Congress and all four elemental shrines.
        $congress=CongressService::state($pid);
        $shrines=CongressService::eventShrines($pid);
        $allianceStructures=\Conquer\Game\Alliance\AllianceTerritoryService::worldStructures($worldId);
        $openTarget=static fn(array $row):bool=>\Conquer\Game\World\LandAccessPolicy::isOpen($worldId,(int)($row['coord_x']??$row['x']),(int)($row['coord_y']??$row['y']));
        $monsters=array_values(array_filter($monsters,static fn(array $row):bool=>$openTarget($row)&&\Conquer\Game\Map\MonsterData::isActive((int)$row['monster_code'])));
        $nodes=array_values(array_filter($nodes,$openTarget));
        $players=array_values(array_filter($players,$openTarget));
        $charms=array_values(array_filter(\Conquer\Game\Charm\CharmQueryService::inBounds($worldId,$pid,$mapX,$mapY,$mapRadius),$openTarget));
        $land=\Conquer\Game\World\LandProgressService::at($worldId,(int)$city['coord_x'],(int)$city['coord_y']);
        $zoneBounds=\Conquer\Game\World\LandGeometry::zoneBounds((int)$world['map_size']);
        $zones=array_map(static fn(array $zone):array=>$zone+['bounds'=>$zoneBounds[$zone['key']]??null],\Conquer\Game\World\LandUnlockService::status($worldId));
        $landState=['parcel_size'=>8,'map_size'=>(int)$world['map_size'],'zones'=>$zones,'current'=>$land?array_intersect_key($land,array_flip(['id','level','zone','open'])):null];
        Response::ok($state + [
            'return_summary'=>$returnSince === null ? null : \Conquer\Game\City\ReturnSummary::since($city, $returnSince),
            'active_effects'=>\Conquer\Game\Buff\ActiveEffectService::forPlayer($pid,$worldId),
            'building_plots'=>\Conquer\Game\City\BuildingPlotService::snapshot($state),
            'plot_queue'=>\Conquer\Game\City\BuildingPlotService::queue((int)$city['id']),
            'storage_caps'=>\Conquer\Game\City\ResourceTick::storageCaps($state['buildings'],$state['vip']['bonuses']),
            'trained_total' => (int) $db->query('SELECT COALESCE(SUM(count),0) FROM troop_queue WHERE city_id = ? AND is_processed = 1', [$city['id']])->fetchColumn(),
            'player' => ['name' => $session['username'], 'csrf' => $session['csrf_token'],
                'name_frame'=>(string)($db->query("SELECT COALESCE(name_frame,'default') FROM kingdom_profiles WHERE player_id=?",[$pid])->fetchColumn()?:'default')],
            'active_rally_count'=>count($rallyMarches),
            'map_center'=>['x'=>$mapX,'y'=>$mapY,'radius'=>$mapRadius],
            'server_time' => time(), 'monsters' => $monsters, 'nodes' => $nodes, 'players'=>$players, 'charms'=>$charms,'land_progression'=>$landState,
            'training_promotions'=>$db->query("SELECT id,source_code,target_code,count,started_at,finishes_at FROM defense_promotions WHERE city_id=? AND state='training'",[$city['id']])->fetchAll(),
            'troop_defs' => $troopDefs, 'army_limits'=>\Conquer\Game\Research\ResearchEffects::limits($buffs), 'building_progression'=>\Conquer\Game\City\BuildingProgression::forPlayer($pid,$worldId), 'research' => $research,
            // Locked and advanced technologies must remain visible in the full tree.
            'research_defs' => array_values(ResearchData::allNodes()), 'research_queue' => $researchQueue,
            'reports' => $reports, 'marches' => $marches,
            'congress' => $congress, 'shrines' => $shrines, 'alliance_structures'=>$allianceStructures,
        ]);
    }
}
