<?php
declare(strict_types=1);
namespace Conquer\Game\Kingdom;

use Conquer\Db\Connection;
use Conquer\Game\City\{BuildingData,CityState,ResourceTick,TroopData};
use Conquer\Game\Hospital\HospitalService;
use Conquer\Game\Inventory\InventoryService;
use Conquer\Game\March\MarchSkinService;
use Conquer\Game\Player\{ActionPoints,LordLevel};
use Conquer\Game\Premium\{LocalCosmeticEntitlements,NameFrameService,ThemeBundleService};
use Conquer\Game\Quest\DailyQuestService;
use Conquer\Game\Research\ResearchProcessor;
use Conquer\Game\Treasure\{TreasureData,TreasureService};
use Conquer\Game\World\WorldContext;

/** Real read models and atomic mutations; no client-submitted balances or ownership. */
final class KingdomService
{
    public const AVATARS = [
        ['id'=>'knight','name'=>'Wächter','image'=>'assets/art/knight.png'],
        ['id'=>'archer','name'=>'Waldläuferin','image'=>'assets/art/archer.png'],
        ['id'=>'rider','name'=>'Reiter','image'=>'assets/art/rider.png'],
    ];


    public static function state(int $playerId, ?int $profileId = null): array
    {
        return self::locked($playerId, function () use ($playerId, $profileId): array {
            self::ensureProfile($playerId);
            ResearchProcessor::processQueue($playerId);
            $cityState = CityState::loadForPlayer($playerId);
            self::require($cityState !== null, 'Dein Königreich wurde nicht gefunden.');
            $cityId = (int) $cityState['city']['id'];
            HospitalService::processHealed($cityId);
            self::syncQuests($playerId, $cityId);
            // One database snapshot prevents a concurrent return from appearing in both army locations.
            $standings = Connection::getInstance()->transaction(static fn(): array => self::standings());
            $alliance = self::alliance($playerId, $standings);
            $db = Connection::getInstance();
            $settings = $db->query('SELECT reduced_motion,compact_numbers,confirm_actions FROM kingdom_profiles WHERE player_id=?', [$playerId])->fetch();
            $hospital = HospitalService::getStatus($cityId);
            foreach ($hospital['wounded'] as &$w) { $w['name'] = TroopData::get($w['troop_code'])['name'] ?? 'Truppen'; }
            unset($w);
            $rankings = array_values($standings);
            usort($rankings, static fn(array $a, array $b): int => ($b['power'] <=> $a['power']) ?: ($a['player_id'] <=> $b['player_id']));
            foreach ($rankings as $i => &$row) { $row['rank'] = $i + 1; }
            unset($row);
            return [
                'profile'=>self::profile($profileId ?? $playerId, $playerId, $standings),
                'avatars'=>self::AVATARS,
                'vip'=>\Conquer\Game\Vip\VipService::status($playerId),
                'settings'=>array_map(static fn($v): bool => (bool) $v, $settings),
                'alliance'=>$alliance,
                'alliances'=>$db->query("SELECT a.id,a.name,a.tag,a.description,a.max_members,
                    (SELECT COUNT(*) FROM alliance_members m WHERE m.alliance_id=a.id) AS member_count,
                    COALESCE(k.display_name,p.username) AS leader_name
                    FROM alliances a JOIN players p ON p.id=a.leader_id LEFT JOIN kingdom_profiles k ON k.player_id=p.id
                    WHERE a.world_id=" . WorldContext::id() . " ORDER BY member_count DESC,a.id LIMIT 100")->fetchAll(),
                'inventory'=>InventoryService::getInventory($playerId),
                'inventory_catalog'=>KingdomInventory::catalog($playerId),
                'inventory_shop'=>KingdomInventory::shop(),
                'quests'=>DailyQuestService::getQuests($playerId),
                'quest_resets_at'=>gmdate('Y-m-d 00:00:00', strtotime('tomorrow UTC')),
                'hospital'=>$hospital,
                'treasures'=>TreasureService::state($playerId),
                'march_skins'=>MarchSkinService::state($playerId),
                'name_frames'=>NameFrameService::state($playerId),
                'theme_bundles'=>ThemeBundleService::state($playerId),
                'chests'=>\Conquer\Game\Treasure\ChestService::getChestStatus($playerId),
                'trading'=>\Conquer\Game\Trading\TradingShopService::state($playerId),
                'rankings'=>array_slice($rankings, 0, 100),
                'arena'=>self::arena($playerId, $rankings),
                'queues'=>self::queues($playerId, $cityId),
                'server_time'=>time(),
            ];
        });
    }

    public static function action(int $playerId, array $body): array
    {
        WorldContext::current($body['expected_world_id']??null);
        if(!in_array($body['action']??'',['profile.save','settings.save','skin.save','march_skin.claim','march_skin.buy','march_skin.equip','name_frame.equip','theme_bundle.checkout'],true))WorldContext::assertActionAvailable();
        return self::locked($playerId, function () use ($playerId, $body): array {
            self::ensureProfile($playerId);
            $cityState = CityState::loadForPlayer($playerId);
            self::require($cityState !== null, 'Dein Königreich wurde nicht gefunden.');
            HospitalService::processHealed((int) $cityState['city']['id']);
            DailyQuestService::ensureDailyQuests($playerId);
            self::syncQuests($playerId, (int) $cityState['city']['id']);
            $db = Connection::getInstance();
            $mutation = function (Connection $db) use ($playerId, $body, $cityState): array {
                $action = $body['action'] ?? '';
                // Resource production must be settled before any resource credit/debit.
                if (in_array($action, ['inventory.use','alliance.donate','alliance.withdraw','hospital.heal','treasure.equip','treasure.unequip'], true)) {
                    ResourceTick::persist($cityState['city'], $cityState['buildings']);
                }
                return match ($action) {
                    'profile.save'=>self::saveProfile($playerId, $body),
                    'skin.save'=>self::saveSkin($playerId, $body),
                    'march_skin.claim'=>MarchSkinService::claim($playerId, $body['march_skin'] ?? null),
                    'march_skin.buy'=>MarchSkinService::buy($playerId, $body['march_skin'] ?? null),
                    'march_skin.equip'=>MarchSkinService::equip($playerId, $body['march_skin'] ?? null),
                    'name_frame.equip'=>NameFrameService::equip($playerId, $body['name_frame'] ?? null),
                    'theme_bundle.checkout'=>ThemeBundleService::checkout($playerId,$body),
                    'settings.save'=>self::saveSettings($playerId, $body),
                    'alliance.create'=>self::createAlliance($playerId, $body),
                    'alliance.join'=>self::joinAlliance($playerId, $body),
                    'alliance.leave','alliance.update','alliance.kick','alliance.transfer','alliance.donate','alliance.withdraw'=>self::manageAlliance($playerId, $body, (int) $cityState['city']['id']),
                    'inventory.use'=>KingdomInventory::use($playerId, $cityState, $body),
                    'inventory.buy'=>KingdomInventory::buy($playerId, $body),
                    'vip.daily'=>\Conquer\Game\Vip\VipService::claimDaily($playerId),
                    'quest.claim'=>self::claimQuest($playerId, $body),
                    'hospital.heal'=>HospitalService::perform($playerId,(int)$cityState['city']['id'],$body),
                    'treasure.equip','treasure.unequip'=>self::equip($playerId, $cityState, $body),
                    'treasure.preset_save','treasure.preset_apply'=>self::treasurePreset($playerId, $body),
                    'chest.free'=>self::freeChest($playerId,$body),
                    'trading.buy'=>self::tradingBuy($playerId,$body),
                    'arena.challenge','arena.accept','arena.decline','arena.cancel'=>self::arenaAction($playerId, $body),
                    default=>throw new \DomainException('Diese Aktion ist nicht verfügbar.'),
                };
            };
            $teleport=($body['action']??'')==='inventory.use' && (InventoryService::getItemDef((int)($body['item_code']??0))['category']??'')==='teleport';
            $replayable=($body['action']??'')==='hospital.heal'
                || (in_array($body['action']??'',['inventory.use','chest.free'],true) && array_key_exists('operation_key',$body));
            // Read the receipt before checking stock or rolling rewards. A retry must
            // return the original loot even when the last owned chest was consumed.
            $receiptBody=($body['action']??'')==='hospital.heal'?$body:$body+['expected_world_id'=>WorldContext::id()];
            $mutate = fn(): array => $replayable
                ? \Conquer\Game\Operation::run($playerId,$receiptBody,fn(): array => $mutation($db))
                : $db->transaction($mutation);
            $result=$teleport?\Conquer\Game\WorldRules::combatLock($mutate):$mutate();
            return ['message'=>$result['message'] ?? 'Gespeichert.', 'result'=>$result, 'state'=>self::state($playerId)];
        });
    }

    private static function tradingBuy(int $playerId,array $body): array
    {
        $mode=$body['mode']??null;$offer=$body['offer_id']??null;$quantity=$body['quantity']??null;$rotation=$body['rotation']??null;
        self::require(is_string($mode)&&is_string($offer)&&is_string($rotation)&&is_int($quantity),'Ungültige Kaufangaben.');
        return \Conquer\Game\Trading\TradingShopService::buy($playerId,$mode,$offer,$quantity,$rotation);
    }

    private static function freeChest(int $playerId,array $body): array
    {
        $type=$body['chest_type']??null;
        self::require(is_string($type)&&in_array($type,['silver','gold'],true),'Wähle eine blaue oder goldene Schatztruhe.');
        $drops=\Conquer\Game\Treasure\ChestService::openFreeChest($playerId,$type);
        return ['message'=>'Schatztruhe geöffnet. Deine Beute wurde gutgeschrieben.','drops'=>$drops];
    }

    private static function ensureProfile(int $playerId): void
    {
        $db = Connection::getInstance();
        $db->transaction(function (Connection $db) use ($playerId): void {
            $db->execute('INSERT IGNORE INTO kingdom_profiles (player_id,display_name) SELECT id,username FROM players WHERE id=?', [$playerId]);
            // The profile row acts as an immutable receipt for a one-time welcome parcel.
            if ($db->execute('UPDATE kingdom_profiles SET welcome_claimed=1 WHERE player_id=? AND welcome_claimed=0', [$playerId]) === 1) {
                foreach ([10103001=>2,10101001=>1,10101011=>1,10105001=>1] as $code=>$quantity) {
                    InventoryService::addItems($playerId, $code, $quantity);
                }
                TreasureService::addFragments($playerId, 60100002, 10);
            }
        });
    }

    /** Public rankings include armies away on missions so helping allies does not lower power. */
    private static function standings(): array
    {
        $db = Connection::getInstance();
        $rows = $db->query("SELECT p.id AS player_id,p.lord_level,p.kill_count,COALESCE(k.display_name,p.username) AS display_name,
            COALESCE(k.avatar,'knight') AS avatar,COALESCE(k.name_frame,'default') AS name_frame,COALESCE(k.city_skin,'default') AS city_skin,k.march_skin,c.id AS city_id,c.name AS city_name,c.castle_level,
            a.tag AS alliance_tag,a.id AS alliance_id
            FROM players p JOIN cities c ON c.player_id=p.id AND c.world_id=" . WorldContext::id() . "
            LEFT JOIN kingdom_profiles k ON k.player_id=p.id
            LEFT JOIN alliance_members m ON m.player_id=p.id AND m.world_id=c.world_id LEFT JOIN alliances a ON a.id=m.alliance_id
            WHERE p.is_banned=0")->fetchAll();
        $result = [];
        $cityPlayers = [];
        foreach ($rows as $row) {
            $id = (int) $row['player_id'];
            $row['power'] = 0;
            $row['stats'] = ['troops'=>0,'garrison'=>0,'buildings'=>0,'research'=>0];
            $result[$id] = $row;
            $cityPlayers[(int) $row['city_id']] = $id;
        }
        foreach ($db->query('SELECT b.city_id,b.building_code,b.level FROM city_buildings b JOIN cities c ON c.id=b.city_id AND c.world_id=' . WorldContext::id() . '')->fetchAll() as $r) {
            $id = $cityPlayers[(int) $r['city_id']] ?? null;
            if (!$id) { continue; }
            $result[$id]['power'] += BuildingData::getTotalPower($r['building_code'], (int) $r['level']);
            $result[$id]['stats']['buildings'] += (int) $r['level'];
            if ($r['building_code'] === 'castle') { $result[$id]['castle_level'] = (int) $r['level']; }
        }
        foreach ($db->query('SELECT t.city_id,t.troop_code,t.count FROM city_troops t JOIN cities c ON c.id=t.city_id AND c.world_id=' . WorldContext::id() . '')->fetchAll() as $r) {
            $id = $cityPlayers[(int) $r['city_id']] ?? null;
            if (!$id) { continue; }
            $result[$id]['stats']['troops'] += (int) $r['count'];
            $result[$id]['stats']['garrison'] += (int) $r['count'];
            $result[$id]['power'] += (int) $r['count'] * (int) (TroopData::get((int) $r['troop_code'])['power'] ?? 0);
        }
        foreach ($db->query('SELECT h.city_id,h.troop_code,h.count FROM hospital_wounded h JOIN cities c ON c.id=h.city_id AND c.world_id=' . WorldContext::id() . '')->fetchAll() as $r) {
            $id = $cityPlayers[(int) $r['city_id']] ?? null;
            if (!$id) { continue; }
            $result[$id]['stats']['troops'] += (int) $r['count'];
            $result[$id]['power'] += (int) $r['count'] * (int) (TroopData::get((int) $r['troop_code'])['power'] ?? 0);
        }
        $away = array_merge(
            $db->query("SELECT m.player_id,m.troops_json FROM invasion_missions m JOIN cities c ON c.id=m.city_id WHERE c.world_id=" . WorldContext::id() . " AND m.state IN ('marching','returning')")->fetchAll(),
            $db->query("SELECT p.player_id,JSON_OBJECT(p.source_code,p.count) AS troops_json FROM defense_promotions p JOIN cities c ON c.id=p.city_id WHERE c.world_id=" . WorldContext::id() . " AND p.state='training'")->fetchAll(),
            $db->query("SELECT g.player_id,g.troops_json FROM shrine_garrisons g JOIN shrines s ON s.id=g.shrine_id WHERE s.world_id=" . WorldContext::id() . "")->fetchAll(),
            $db->query("SELECT player_id,troops_json,haul_json,state FROM marches WHERE world_id=" . WorldContext::id() . " AND state IN ('marching','resolving','returning')")->fetchAll(),
            $db->query("SELECT m.player_id,m.troops_json FROM expedition_missions m JOIN expeditions e ON e.id=m.expedition_id WHERE e.world_id=" . WorldContext::id() . " AND m.status IN ('marching','returning')")->fetchAll()
        );
        foreach ($away as $mission) {
            $id = (int) $mission['player_id'];
            if (!isset($result[$id])) { continue; }
            $troops = json_decode((string) ($mission['troops_json'] ?? '{}'), true, 32, JSON_THROW_ON_ERROR);
            if (($mission['state'] ?? '') === 'returning') {
                $haul = json_decode((string) ($mission['haul_json'] ?? '{}'), true, 32, JSON_THROW_ON_ERROR);
                $troops = $haul['survivors'] ?? $troops;
            }
            foreach ($troops as $code=>$count) {
                $result[$id]['stats']['troops'] += (int) $count;
                $result[$id]['power'] += (int) $count * (int) (TroopData::get((int) $code)['power'] ?? 0);
            }
        }
        foreach ($db->query('SELECT player_id,SUM(level) AS levels FROM player_research WHERE world_id=' . WorldContext::id() . ' GROUP BY player_id')->fetchAll() as $r) {
            $id = (int) $r['player_id'];
            if (!isset($result[$id])) { continue; }
            $result[$id]['stats']['research'] = (int) $r['levels'];
            $result[$id]['power'] += (int) $r['levels'] * 100;
        }
        return $result;
    }

    private static function profile(int $target, int $viewer, array $standings): array
    {
        self::require(isset($standings[$target]), 'Dieses Spielerprofil wurde nicht gefunden.');
        $db = Connection::getInstance();
        $p = $standings[$target];
        $lord = LordLevel::snapshot($target, WorldContext::id());
        $extra = $db->query("SELECT COALESCE(k.bio,'') AS bio,p.created_at FROM players p LEFT JOIN kingdom_profiles k ON k.player_id=p.id WHERE p.id=?", [$target])->fetch();
        $p['stats']['monster_victories'] = (int) $db->query("SELECT COUNT(*) FROM battle_reports WHERE attacker_id=? AND world_id=? AND outcome='attacker_wins' AND defender_id IS NULL", [$target,WorldContext::id()])->fetchColumn();
        $p['stats']['arena_wins'] = (int) $db->query("SELECT COUNT(*) FROM kingdom_arena_challenges WHERE winner_id=? AND world_id=? AND status='completed'", [$target,WorldContext::id()])->fetchColumn();
        $p['stats']['expedition_victories'] = (int) $db->query("SELECT COUNT(*) FROM expedition_participants p JOIN expeditions e ON e.id=p.expedition_id WHERE p.player_id=? AND e.world_id=? AND p.contribution>0 AND e.phase='victory'", [$target,WorldContext::id()])->fetchColumn();
        $membership = $db->query('SELECT a.id,a.name,a.tag,m.role FROM alliance_members m JOIN alliances a ON a.id=m.alliance_id WHERE m.player_id=? AND m.world_id=?', [$target,WorldContext::id()])->fetch();
        $achievements = [
            ['code'=>'founder','name'=>'Ein neues Königreich','description'=>'Gründe deine erste Stadt.','unlocked'=>true],
            ['code'=>'builder','name'=>'Baumeister','description'=>'Erreiche Burgstufe 3.','unlocked'=>(int) $p['castle_level']>=3],
            ['code'=>'hunter','name'=>'Monsterjäger','description'=>'Besiege ein Monster auf der Weltkarte.','unlocked'=>$p['stats']['monster_victories']>0],
            ['code'=>'ally','name'=>'Gemeinsam stärker','description'=>'Schließe dich einer Allianz an.','unlocked'=>(bool) $membership],
            ['code'=>'liberator','name'=>'Befreier des Grenzlands','description'=>'Trage zu einem siegreichen gemeinsamen Feldzug bei.','unlocked'=>$p['stats']['expedition_victories']>0],
            ['code'=>'scholar','name'=>'Wissenshüter','description'=>'Schließe fünf Forschungsstufen ab.','unlocked'=>$p['stats']['research']>=5],
            ['code'=>'arena','name'=>'Fairer Wettstreit','description'=>'Gewinne ein freiwilliges Übungsduell.','unlocked'=>$p['stats']['arena_wins']>0],
        ];
        $profile = ['id'=>$target,'display_name'=>$p['display_name'],'avatar'=>$p['avatar'],'name_frame'=>$p['name_frame'],'bio'=>$extra['bio'],
            'city_skin'=>$p['city_skin'],'march_skin'=>$p['march_skin'] ?? null,'city_name'=>$p['city_name'],'castle_level'=>(int) $p['castle_level'],'power'=>$p['power'],
            'lord_level'=>$lord['level'],'lord_max_level'=>$lord['max_level'],'kill_count'=>(int) $p['kill_count'],
            'alliance'=>$membership ?: null,'stats'=>$p['stats'],'achievements'=>$achievements,
            'created_at'=>$extra['created_at'],'is_self'=>$target===$viewer];
        if ($target === $viewer) {
            $balances = $db->query('SELECT gems,vip_points FROM players WHERE id=?', [$viewer])->fetch();
            $ap = ActionPoints::get($viewer);
            $profile['gems'] = (int) $balances['gems'];
            $profile['action_points'] = $ap['current'];
            $profile['action_points_max'] = $ap['max'];
            $profile['action_points_regen_per_hour'] = $ap['regen_per_hour'];
            $profile['lord_xp'] = $lord['xp'];
            $profile['lord_xp_into'] = $lord['xp_into_level'];
            $profile['lord_xp_next'] = $lord['xp_next'];
            $profile['prestige_points'] = (int) $balances['vip_points'];
        }
        return $profile;
    }

    private static function alliance(int $playerId, array $standings): ?array
    {
        $db = Connection::getInstance();
        $a = $db->query('SELECT a.*,m.role FROM alliances a JOIN alliance_members m ON m.alliance_id=a.id WHERE m.player_id=? AND m.world_id=?', [$playerId,WorldContext::id()])->fetch();
        if (!$a) { return null; }
        $members = $db->query("SELECT m.player_id,m.role,m.joined_at,COALESCE(k.display_name,p.username) AS display_name,
            COALESCE(k.avatar,'knight') AS avatar,COALESCE(k.name_frame,'default') AS name_frame,c.coord_x,c.coord_y FROM alliance_members m JOIN players p ON p.id=m.player_id
            LEFT JOIN kingdom_profiles k ON k.player_id=m.player_id LEFT JOIN cities c ON c.player_id=m.player_id AND c.world_id=m.world_id WHERE m.alliance_id=?
            ORDER BY FIELD(m.role,'leader','vice_leader','officer','veteran','member'),m.joined_at", [$a['id']])->fetchAll();
        foreach ($members as &$m) { $m['power'] = $standings[(int) $m['player_id']]['power'] ?? 0; }
        unset($m);
        $a['members'] = $members;
        $a['member_count'] = count($members);
        $a['treasury'] = $db->query('SELECT food,lumber,stone,gold FROM alliance_treasury WHERE alliance_id=?', [$a['id']])->fetch() ?: ['food'=>0,'lumber'=>0,'stone'=>0,'gold'=>0];
        return $a;
    }

    private static function saveProfile(int $playerId, array $body): array
    {
        $name = self::text($body, 'display_name', 2, 30);
        $bio = self::text($body, 'bio', 0, 300);
        $avatar = self::text($body, 'avatar', 1, 20);
        self::require(in_array($avatar, array_column(self::AVATARS, 'id'), true), 'Wähle ein verfügbares Porträt.');
        Connection::getInstance()->execute('UPDATE kingdom_profiles SET display_name=?,avatar=?,bio=? WHERE player_id=?', [$name,$avatar,$bio,$playerId]);
        return ['message'=>'Dein Profil wurde gespeichert.'];
    }

    private static function saveSkin(int $playerId, array $body): array
    {
        LocalCosmeticEntitlements::sync($playerId);
        $skin = self::text($body, 'city_skin', 1, 20);
        self::require(in_array($skin, ['default','ironkeep','rosehall','sandspire','tidewatch','winterhold','jadecourt','emberforge','ravenloft','clockwork','sapphire','phoenix','astral','leviathan','yggdrasil','tempest','eclipse','dragon'], true), 'Wähle einen verfügbaren Dorf-Skin.');
        if($skin!=='default')self::require(Connection::getInstance()->query('SELECT 1 FROM player_castle_skins WHERE player_id=? AND skin_code=?',[$playerId,$skin])->fetchColumn()!==false,'Dieser Dorf-Skin gehört dir noch nicht.');
        Connection::getInstance()->execute('UPDATE kingdom_profiles SET city_skin=? WHERE player_id=?', [$skin,$playerId]);
        return ['message'=>'Dein Dorf-Skin wurde angelegt.'];
    }

    private static function saveSettings(int $playerId, array $body): array
    {
        $values = [];
        foreach (['reduced_motion','compact_numbers','confirm_actions'] as $key) {
            self::require(isset($body[$key]) && is_bool($body[$key]), 'Einstellungen müssen wahr oder falsch sein.');
            $values[] = (int) $body[$key];
        }
        $values[] = $playerId;
        Connection::getInstance()->execute('UPDATE kingdom_profiles SET reduced_motion=?,compact_numbers=?,confirm_actions=? WHERE player_id=?', $values);
        return ['message'=>'Deine Einstellungen wurden gespeichert.'];
    }

    private static function createAlliance(int $playerId, array $body): array
    {
        $db = Connection::getInstance();
        self::require(!$db->query('SELECT alliance_id FROM alliance_members WHERE player_id=? AND world_id=? FOR UPDATE', [$playerId,WorldContext::id()])->fetchColumn(), 'Du bist bereits Mitglied einer Allianz.');
        $name = self::text($body, 'name', 3, 50);
        $tag = strtoupper(self::text($body, 'tag', 2, 6));
        self::require((bool) preg_match('/^[A-Z0-9]{2,6}$/D', $tag), 'Das Kürzel braucht 2–6 Buchstaben oder Zahlen.');
        $description = self::text($body, 'description', 0, 500);
        $db->execute('INSERT INTO alliances (world_id,name,tag,description,leader_id) VALUES (?,?,?,?,?)', [WorldContext::id(),$name,$tag,$description,$playerId]);
        $id = $db->lastInsertId();
        $db->execute("INSERT INTO alliance_members (alliance_id,player_id,world_id,role) VALUES (?,?,?,'leader')", [$id,$playerId,WorldContext::id()]);
        $db->execute('INSERT INTO alliance_treasury (alliance_id) VALUES (?)', [$id]);
        return ['message'=>'Deine Allianz ist gegründet. Lade andere Königreiche ein, sich dir anzuschließen.','alliance_id'=>$id];
    }

    private static function joinAlliance(int $playerId, array $body): array
    {
        $db = Connection::getInstance();
        $id = self::integer($body, 'alliance_id');
        $a = $db->query('SELECT id,max_members FROM alliances WHERE id=? AND world_id=' . WorldContext::id() . ' FOR UPDATE', [$id])->fetch();
        self::require((bool) $a, 'Diese Allianz wurde nicht gefunden.');
        self::require(!$db->query('SELECT alliance_id FROM alliance_members WHERE player_id=? AND world_id=? FOR UPDATE', [$playerId,WorldContext::id()])->fetchColumn(), 'Verlasse zuerst deine bisherige Allianz.');
        $count = (int) $db->query('SELECT COUNT(*) FROM alliance_members WHERE alliance_id=?', [$id])->fetchColumn();
        self::require($count < (int) $a['max_members'], 'Diese Allianz ist bereits voll.');
        $db->execute("INSERT INTO alliance_members (alliance_id,player_id,world_id,role) VALUES (?,?,?,'member')", [$id,$playerId,WorldContext::id()]);
        $db->execute('UPDATE alliances SET member_count=? WHERE id=?', [$count+1,$id]);
        return ['message'=>'Du bist der Allianz beigetreten.','alliance_id'=>$id];
    }

    private static function manageAlliance(int $playerId, array $body, int $cityId): array
    {
        $db = Connection::getInstance();
        $membership = $db->query('SELECT alliance_id FROM alliance_members WHERE player_id=? AND world_id=?', [$playerId,WorldContext::id()])->fetchColumn();
        self::require((bool) $membership, 'Du gehörst keiner Allianz an.');
        $id = (int) $membership;
        // All membership operations lock the shared alliance first, preventing capacity/role races.
        $a = $db->query('SELECT * FROM alliances WHERE id=? FOR UPDATE', [$id])->fetch();
        $member = $db->query('SELECT role FROM alliance_members WHERE player_id=? AND alliance_id=? FOR UPDATE', [$playerId,$id])->fetch();
        self::require((bool) $a && (bool) $member, 'Deine Allianzmitgliedschaft hat sich geändert.');
        $leader = (int) $a['leader_id'] === $playerId && $member['role'] === 'leader';
        switch ($body['action']) {
            case 'alliance.withdraw':
                if (!$leader) { throw new \DomainException('Nur die aktuelle Allianzführung darf Vorräte aus der Bündniskasse entnehmen.', 403); }
                return self::withdraw($playerId, $cityId, $id, $body);
            case 'alliance.leave':
                self::require(!$leader, 'Übertrage zuerst die Führung an ein anderes Mitglied.');
                $db->execute('DELETE FROM alliance_members WHERE player_id=? AND alliance_id=?', [$playerId,$id]);
                $message = 'Du hast die Allianz verlassen.';
                break;
            case 'alliance.update':
                self::require($leader, 'Nur die Allianzführung kann die Beschreibung ändern.');
                $db->execute('UPDATE alliances SET description=? WHERE id=?', [self::text($body, 'description', 0, 500),$id]);
                $message = 'Die Allianzbeschreibung wurde gespeichert.';
                break;
            case 'alliance.kick':
            case 'alliance.transfer':
                self::require($leader, 'Nur die Allianzführung kann Mitglieder verwalten.');
                $target = self::integer($body, 'player_id');
                self::require($target !== $playerId, 'Wähle ein anderes Allianzmitglied.');
                self::require((bool) $db->query('SELECT player_id FROM alliance_members WHERE player_id=? AND alliance_id=? FOR UPDATE', [$target,$id])->fetch(), 'Dieses Königreich gehört nicht zu deiner Allianz.');
                if ($body['action'] === 'alliance.kick') {
                    $db->execute('DELETE FROM alliance_members WHERE player_id=? AND alliance_id=?', [$target,$id]);
                    $message = 'Das Mitglied wurde aus der Allianz entlassen.';
                } else {
                    $db->execute("UPDATE alliance_members SET role='member' WHERE player_id=? AND alliance_id=?", [$playerId,$id]);
                    $db->execute("UPDATE alliance_members SET role='leader' WHERE player_id=? AND alliance_id=?", [$target,$id]);
                    $db->execute('UPDATE alliances SET leader_id=? WHERE id=?', [$target,$id]);
                    $message = 'Die Allianzführung wurde übertragen.';
                }
                break;
            case 'alliance.donate':
                $resource = self::text($body, 'resource', 1, 10);
                self::require(in_array($resource, ['food','lumber','stone','gold'], true), 'Wähle einen gültigen Rohstoff.');
                $amount = self::integer($body, 'amount', 1, 1000000);
                self::require($db->execute("UPDATE cities SET $resource=$resource-? WHERE id=? AND $resource>=?", [$amount,$cityId,$amount])===1, 'Dafür reichen deine Rohstoffe nicht aus.');
                $db->execute("INSERT INTO alliance_treasury (alliance_id,$resource) VALUES (?,?) ON DUPLICATE KEY UPDATE $resource=$resource+VALUES($resource)", [$id,$amount]);
                $db->execute("INSERT INTO alliance_donations (alliance_id,player_id,$resource) VALUES (?,?,?)", [$id,$playerId,$amount]);
                $message = 'Deine Spende liegt in der gemeinsamen Allianzkasse.';
                break;
            default: throw new \DomainException('Unbekannte Allianzaktion.');
        }
        $db->execute('UPDATE alliances SET member_count=(SELECT COUNT(*) FROM alliance_members WHERE alliance_id=?) WHERE id=?', [$id,$id]);
        return ['message'=>$message];
    }

    /** Called inside the transaction after locking the alliance and verifying its current leader. */
    private static function withdraw(int $playerId, int $cityId, int $allianceId, array $body): array
    {
        $resource = self::text($body, 'resource', 1, 10);
        self::require(in_array($resource, ['food','lumber','stone','gold'], true), 'Wähle einen gültigen Rohstoff.');
        $amount = self::integer($body, 'amount', 1, 1000000);
        $requestId = self::text($body, 'request_id', 16, 64);
        self::require(preg_match('/^[A-Za-z0-9_-]{16,64}$/D', $requestId)===1, 'Die Entnahme benötigt eine gültige Vorgangskennung.');
        $db = Connection::getInstance();
        $previous = $db->query('SELECT alliance_id,resource,amount FROM kingdom_treasury_withdrawals WHERE player_id=? AND request_id=? FOR UPDATE', [$playerId,$requestId])->fetch();
        if ($previous) {
            self::require((int) $previous['alliance_id']===$allianceId && $previous['resource']===$resource && (int) $previous['amount']===$amount, 'Diese Vorgangskennung gehört zu einer anderen Entnahme. Bitte öffne das Formular erneut.');
        } else {
            self::require($db->execute("UPDATE alliance_treasury SET $resource=$resource-? WHERE alliance_id=? AND $resource>=?", [$amount,$allianceId,$amount])===1, 'In der Bündniskasse liegen dafür nicht genügend Vorräte.');
            self::require($db->execute("UPDATE cities SET $resource=$resource+? WHERE id=? AND player_id=? AND world_id=?", [$amount,$cityId,$playerId,WorldContext::id()])===1, 'Deine Stadt konnte nicht gefunden werden.');
            $db->execute('INSERT INTO kingdom_treasury_withdrawals(alliance_id,player_id,request_id,resource,amount) VALUES(?,?,?,?,?)', [$allianceId,$playerId,$requestId,$resource,$amount]);
        }
        $balance = (int) $db->query("SELECT $resource FROM alliance_treasury WHERE alliance_id=?", [$allianceId])->fetchColumn();
        $name = ['food'=>'Nahrung','lumber'=>'Holz','stone'=>'Stein','gold'=>'Gold'][$resource];
        return ['message'=>$previous ? 'Diese Entnahme wurde bereits deiner Stadt gutgeschrieben. Es wurde nichts erneut entnommen.' : "$amount $name wurden aus der Bündniskasse in deine Stadt geliefert. In der Kasse verbleiben $balance $name.",
            'resource'=>$resource,'amount'=>$amount,'treasury_balance'=>$balance,'request_id'=>$requestId,'duplicate'=>(bool) $previous];
    }

    private static function claimQuest(int $playerId, array $body): array
    {
        try { $rewards = DailyQuestService::claimReward($playerId, self::text($body, 'quest_code', 1, 60)); }
        catch (\RuntimeException $e) { throw new \DomainException($e->getMessage()); }
        return ['message'=>'Deine Auftragsbelohnung wurde gutgeschrieben.','rewards'=>$rewards];
    }

    /** Reconcile historical processors without hooks from their durable completion records. */
    private static function syncQuests(int $playerId, int $cityId): void
    {
        DailyQuestService::ensureDailyQuests($playerId);
        $db = Connection::getInstance();
        $progress = [
            'upgrade_building_1'=>(int) $db->query('SELECT COUNT(*) FROM building_queue WHERE city_id=? AND is_processed=1 AND finishes_at>=UTC_DATE() AND finishes_at<=UTC_TIMESTAMP()', [$cityId])->fetchColumn(),
            'train_troops_100'=>(int) $db->query('SELECT COALESCE(SUM(count),0) FROM troop_queue WHERE city_id=? AND is_processed=1 AND finishes_at>=UTC_DATE() AND finishes_at<=UTC_TIMESTAMP()', [$cityId])->fetchColumn(),
            'research_complete_1'=>(int) $db->query('SELECT COUNT(*) FROM research_queue WHERE player_id=? AND world_id=' . WorldContext::id() . ' AND is_processed=1 AND finishes_at>=UTC_DATE() AND finishes_at<=UTC_TIMESTAMP()', [$playerId])->fetchColumn(),
        ];
        $progress['collect_resources'] = 0;
        foreach ($db->query("SELECT haul_json FROM marches WHERE player_id=? AND world_id=" . WorldContext::id() . " AND march_type=9 AND state='complete' AND return_time>=UTC_DATE() AND return_time<=UTC_TIMESTAMP()", [$playerId])->fetchAll() as $row) {
            $haul = json_decode((string) ($row['haul_json'] ?? '{}'), true, 32, JSON_THROW_ON_ERROR);
            foreach (['food','lumber','wood','stone','gold'] as $resource) { $progress['collect_resources'] += max(0, (int) ($haul['loot'][$resource] ?? 0)); }
        }
        foreach ($progress as $code=>$count) {
            $db->execute('UPDATE player_daily_quests SET progress=GREATEST(progress,LEAST(target,?)),completed=IF(progress>=target,1,completed) WHERE player_id=? AND quest_code=? AND quest_date=UTC_DATE()', [$count,$playerId,$code]);
        }
    }


    private static function equip(int $playerId, array $cityState, array $body): array
    {
        $code = self::integer($body, 'treasure_code');
        if ($body['action'] === 'treasure.unequip') {
            self::require(TreasureService::unequipTreasure($playerId, $code), 'Dieses Relikt ist nicht ausgerüstet.');
            return ['message'=>'Das Relikt wurde abgelegt.'];
        }
        $definition = TreasureData::get($code);
        self::require($definition !== null && count(array_intersect(array_column($definition['stats'], 'type'), TreasureService::ACTIVE_STATS))>0, 'Dieses Sammlerrelikt hat im aktuellen Feldzug noch keinen wirksamen Bonus.');
        $slot = self::integer($body, 'slot', 1, 6);
        $level = (int) ($cityState['buildings']['treasure_house']['level'] ?? 1);
        self::require(TreasureService::equipTreasure($playerId, $code, $slot, $level), 'Schatz noch gesperrt oder Schatzkammerstufe zu niedrig.');
        return ['message'=>'Der Schatz ist angelegt. Seine Boni sind in dieser Welt aktiv.'];
    }

    private static function treasurePreset(int $playerId,array $body): array
    {
        $preset=self::integer($body,'preset',1,5);
        if($body['action']==='treasure.preset_save'){
            TreasureService::savePreset($playerId,$preset);
            return ['message'=>'Ausrüstung in Preset '.$preset.' gespeichert.'];
        }
        TreasureService::applyPreset($playerId,$preset);
        return ['message'=>'Preset '.$preset.' angelegt. Seine Boni sind in dieser Welt aktiv.'];
    }

    private static function queues(int $playerId, int $cityId): array
    {
        $db = Connection::getInstance();
        return array_merge(
            $db->query("SELECT id,'building' AS type,building_code AS label,finishes_at FROM building_queue WHERE city_id=? AND is_processed=0", [$cityId])->fetchAll(),
            $db->query("SELECT id,'research' AS type,research_code AS label,finishes_at FROM research_queue WHERE player_id=? AND world_id=" . WorldContext::id() . " AND is_processed=0", [$playerId])->fetchAll(),
            $db->query("SELECT id,'training' AS type,troop_code AS label,finishes_at FROM troop_queue WHERE city_id=? AND is_processed=0", [$cityId])->fetchAll(),
            $db->query("SELECT MIN(id) AS id,'healing' AS type,MIN(troop_code) AS label,MAX(healing_ends_at) AS finishes_at FROM hospital_wounded WHERE city_id=? AND healing_count>0 GROUP BY healing_batch", [$cityId])->fetchAll()
        );
    }

    private static function arena(int $playerId, array $rankings): array
    {
        $db = Connection::getInstance();
        $db->execute("UPDATE kingdom_arena_challenges SET status='expired',resolved_at=UTC_TIMESTAMP() WHERE status='pending' AND expires_at<=UTC_TIMESTAMP() AND (challenger_id=? OR opponent_id=?)", [$playerId,$playerId]);
        $rows = $db->query("SELECT a.id,a.challenger_id,a.opponent_id,a.status,a.winner_id,a.result_json,a.created_at,a.expires_at,
            COALESCE(k1.display_name,p1.username) AS challenger_name,COALESCE(k2.display_name,p2.username) AS opponent_name
            FROM kingdom_arena_challenges a JOIN players p1 ON p1.id=a.challenger_id JOIN players p2 ON p2.id=a.opponent_id
            LEFT JOIN kingdom_profiles k1 ON k1.player_id=p1.id LEFT JOIN kingdom_profiles k2 ON k2.player_id=p2.id
            WHERE a.world_id=? AND (a.challenger_id=? OR a.opponent_id=?) ORDER BY a.id DESC LIMIT 30", [WorldContext::id(),$playerId,$playerId])->fetchAll();
        foreach ($rows as &$row) { $row['result'] = $row['result_json'] ? json_decode($row['result_json'], true) : null; unset($row['result_json']); }
        unset($row);
        $opponents = array_values(array_filter($rankings, static fn(array $r): bool => $r['player_id'] !== $playerId));
        return ['challenges'=>$rows,'opponents'=>array_slice($opponents, 0, 100),'rules'=>'Freiwilliges Übungsduell mit der Garnison: Beide Seiten stimmen zu. Keine Truppen-, Ressourcen- oder Stadtverluste. Bei Gleichstand endet das Duell unentschieden.'];
    }

    private static function arenaAction(int $playerId, array $body): array
    {
        $db = Connection::getInstance();
        if ($body['action'] === 'arena.challenge') {
            $opponent = self::integer($body, 'opponent_id');
            self::require($opponent !== $playerId, 'Du kannst dich nicht selbst herausfordern.');
            self::require((bool) $db->query('SELECT p.id FROM players p JOIN cities c ON c.player_id=p.id AND c.world_id=' . WorldContext::id() . ' WHERE p.id=? AND p.is_banned=0', [$opponent])->fetch(), 'Dieses Königreich wurde nicht gefunden.');
            $count = (int) $db->query("SELECT COUNT(*) FROM kingdom_arena_challenges WHERE challenger_id=? AND created_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR)", [$playerId])->fetchColumn();
            self::require($count < 10, 'Du kannst höchstens zehn Herausforderungen pro Stunde senden.');
            $pending = (int) $db->query("SELECT COUNT(*) FROM kingdom_arena_challenges WHERE challenger_id=? AND opponent_id=? AND world_id=? AND status='pending' AND expires_at>UTC_TIMESTAMP()", [$playerId,$opponent,WorldContext::id()])->fetchColumn();
            self::require($pending===0, 'Für dieses Königreich wartet bereits eine Herausforderung.');
            $army = self::army($playerId);
            self::require(array_sum($army)>0, 'Trainiere zuerst Truppen für dein Übungsduell.');
            $db->execute('INSERT INTO kingdom_arena_challenges (world_id,challenger_id,opponent_id,challenger_army_json,expires_at) VALUES (?,?,?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 24 HOUR))', [WorldContext::id(),$playerId,$opponent,json_encode($army, JSON_THROW_ON_ERROR)]);
            return ['message'=>'Die Herausforderung wartet auf die Zustimmung deines Gegenübers.','challenge_id'=>$db->lastInsertId()];
        }
        $id = self::integer($body, 'challenge_id');
        $r = $db->query('SELECT *,expires_at>UTC_TIMESTAMP() AS valid FROM kingdom_arena_challenges WHERE id=? AND world_id=? FOR UPDATE', [$id,WorldContext::id()])->fetch();
        self::require((bool) $r && $r['status']==='pending' && (bool) $r['valid'], 'Diese Herausforderung ist nicht mehr offen.');
        if ($body['action'] === 'arena.cancel') {
            self::require((int) $r['challenger_id']===$playerId, 'Nur der Herausforderer kann dieses Duell zurückziehen.');
            $db->execute("UPDATE kingdom_arena_challenges SET status='cancelled',resolved_at=UTC_TIMESTAMP() WHERE id=?", [$id]);
            return ['message'=>'Die Herausforderung wurde zurückgezogen.'];
        }
        self::require((int) $r['opponent_id']===$playerId, 'Nur das herausgeforderte Königreich kann antworten.');
        if ($body['action'] === 'arena.decline') {
            $db->execute("UPDATE kingdom_arena_challenges SET status='declined',resolved_at=UTC_TIMESTAMP() WHERE id=?", [$id]);
            return ['message'=>'Die Herausforderung wurde abgelehnt.'];
        }
        $army = self::army($playerId);
        self::require(array_sum($army)>0, 'Trainiere zuerst Truppen für dein Übungsduell.');
        $attacker = json_decode($r['challenger_army_json'], true, 32, JSON_THROW_ON_ERROR);
        $result = self::simulateArena($attacker, $army,
            \Conquer\Game\Research\BuffEngine::getBuffs((int) $r['challenger_id']),
            \Conquer\Game\Research\BuffEngine::getBuffs($playerId));
        $winner = $result['outcome']==='draw' ? null : ($result['outcome']==='challenger_wins' ? (int) $r['challenger_id'] : $playerId);
        $result['winner_id'] = $winner;
        $db->execute("UPDATE kingdom_arena_challenges SET status='completed',winner_id=?,result_json=?,resolved_at=UTC_TIMESTAMP() WHERE id=?", [$winner,json_encode($result, JSON_THROW_ON_ERROR),$id]);
        return ['message'=>$winner===null ? 'Das Übungsduell endet unentschieden.' : ($winner===$playerId ? 'Du hast das Übungsduell gewonnen.' : 'Dein Gegenüber hat das Übungsduell gewonnen.'),'battle'=>$result];
    }

    private static function army(int $playerId): array
    {
        return Connection::getInstance()->query('SELECT t.troop_code,t.count FROM city_troops t JOIN cities c ON c.id=t.city_id WHERE c.player_id=? AND c.world_id=' . WorldContext::id() . ' AND t.count>0', [$playerId])->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    /** Reproducible virtual combat. Troop types counter cavalry/ranged/infantry respectively. */
    public static function simulateArena(array $challenger, array $opponent, array $challengerBuffs = [], array $opponentBuffs = []): array
    {
        $score = static function (array $army, array $enemy, array $buffs): int {
            $buffs = \Conquer\Game\Research\ResearchEffects::armyBuffs($buffs, $army);
            $enemyTypes = [1=>0,2=>0,3=>0];
            foreach ($enemy as $code=>$count) {
                $type = (int) (TroopData::get((int) $code)['type'] ?? 0);
                if (isset($enemyTypes[$type])) { $enemyTypes[$type] += max(0, (int) $count); }
            }
            $totalEnemy = max(1, array_sum($enemyTypes));
            $total = 0;
            foreach ($army as $code=>$count) {
                $def = TroopData::get((int) $code);
                if (!$def) { continue; }
                $counter = [1=>3,2=>1,3=>2][(int) $def['type']] ?? 1;
                $bonus = 1 + .2 * $enemyTypes[$counter] / $totalEnemy;
                $type = [1=>'infantry',2=>'ranged',3=>'cavalry'][(int) $def['type']];
                [$sourceType,$researchTarget,$targetType] = [1=>['infantry','archer',2],2=>['archer','cavalry',3],3=>['cavalry','infantry',1]][(int) $def['type']];
                $enemyShare = $enemyTypes[$targetType] / $totalEnemy;
                $multiplier = static fn(string $stat): float => \Conquer\Game\Research\BuffEngine::effectiveMultiplier($buffs,$type,$stat)
                    + (float)($buffs[$sourceType.'_'.$stat.'_against_'.$researchTarget] ?? 0) * $enemyShare;
                $total += max(0, (int) $count) * ((int) $def['attack'] * $multiplier('atk')
                    + (int) $def['defense'] * $multiplier('def') + (int) $def['hp']/5 * $multiplier('hp')) * $bonus;
            }
            return (int) round($total);
        };
        $a = $score($challenger, $opponent, $challengerBuffs);
        $b = $score($opponent, $challenger, $opponentBuffs);
        return ['outcome'=>$a===$b ? 'draw' : ($a>$b ? 'challenger_wins' : 'opponent_wins'),
            'challenger_score'=>$a,'opponent_score'=>$b,'challenger_troops'=>array_sum($challenger),
            'opponent_troops'=>array_sum($opponent),'losses'=>0,'resource_cost'=>0,
            'explanation'=>'Virtuelle Kampfstärke aus Angriff, Verteidigung, Lebenspunkten und einem Truppentypbonus von bis zu 20 %.'];
    }

    public static function require(bool $condition, string $message): void
    {
        if (!$condition) { throw new \DomainException($message); }
    }

    public static function integer(array $body, string $key, int $min = 1, int $max = 2147483647): int
    {
        $value = $body[$key] ?? null;
        self::require(is_int($value) || (is_string($value) && preg_match('/^[0-9]+$/D', $value)===1), 'Ungültige Zahl: ' . $key);
        $int = filter_var($value, FILTER_VALIDATE_INT, ['options'=>['min_range'=>$min,'max_range'=>$max]]);
        self::require($int !== false, 'Ungültiger Wertebereich: ' . $key);
        return (int) $int;
    }

    public static function text(array $body, string $key, int $min, int $max): string
    {
        $value = $body[$key] ?? '';
        self::require(is_string($value), 'Ungültiger Text: ' . $key);
        $value = trim($value);
        self::require(mb_check_encoding($value, 'UTF-8') && mb_strlen($value, 'UTF-8') >= $min && mb_strlen($value, 'UTF-8') <= $max, "Das Feld $key braucht {$min}–{$max} Zeichen.");
        self::require(!preg_match('/[<>\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value), 'Bitte verwende normalen Text ohne HTML.');
        return $value;
    }

    private static function locked(int $playerId, callable $fn): mixed
    {
        $db = Connection::getInstance();
        $key = 'conquer-player-' . $playerId;
        if ((int)$db->query('SELECT GET_LOCK(?,5)',[$key])->fetchColumn()!==1) {
            throw new \DomainException('Dein Königreich wird gerade aktualisiert. Bitte versuche es erneut.',503);
        }
        try { return $fn(); }
        finally { $db->query('SELECT RELEASE_LOCK(?)', [$key]); }
    }
}
