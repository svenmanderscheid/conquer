<?php
declare(strict_types=1);
namespace Conquer\Game\Treasure;

use Conquer\Db\Connection;
use Conquer\Game\Inventory\InventoryService;
use Conquer\Game\Quest\DailyQuestService;

/** Account-wide free chest timers; owned inventory chests are independent. */
final class ChestService
{
    private function __construct() {}
    public const FREE_SILVER_PER_DAY = 10;
    public const SILVER_COOLDOWN_SECONDS = 600;
    public const GOLD_COOLDOWN_SECONDS = 86400;
    public const GOLD_CHEST_COST_GEMS = 50;
    public const PLATINUM_CHEST_COST_GEMS = 200;
    private const VALID_TYPES = ['silver','gold','platinum'];
    private static ?array $dropTableCache = null;

    /** Times are ISO 8601 UTC and apply to the entire player account. */
    public static function getChestStatus(int $playerId): array
    {
        return self::status(self::loadOrCreateRow(Connection::getInstance(),$playerId),time());
    }

    private static function status(array $row,int $now): array
    {
        $today=gmdate('Y-m-d',$now);
        $used=substr((string)($row['last_free_silver_reset']??''),0,10)===$today?(int)$row['free_silver_used_today']:0;
        $remaining=max(0,self::FREE_SILVER_PER_DAY-$used);
        $tomorrow=strtotime($today.' 00:00:00 UTC')+86400;
        $silverAt=empty($row['last_free_silver_at'])?0:strtotime($row['last_free_silver_at'].' UTC')+self::SILVER_COOLDOWN_SECONDS;
        $goldAt=empty($row['last_free_gold_at'])?0:strtotime($row['last_free_gold_at'].' UTC')+self::GOLD_COOLDOWN_SECONDS;
        return [
            'server_time'=>gmdate('c',$now),
            'silver_count'=>(int)$row['silver_count'],'gold_count'=>(int)$row['gold_count'],'platinum_count'=>(int)$row['platinum_count'],
            'free_silver_limit'=>self::FREE_SILVER_PER_DAY,'free_silver_remaining'=>$remaining,
            'free_silver_available'=>$remaining>0&&$silverAt<=$now,
            'free_silver_next_at'=>gmdate('c',max($now,$silverAt,$remaining===0?$tomorrow:0)),
            'free_silver_resets_at'=>gmdate('c',$tomorrow),
            'free_gold_available'=>$goldAt<=$now,'free_gold_next_at'=>gmdate('c',max($now,$goldAt)),
        ];
    }

    /** Explicit free claim: never silently spends gems or an owned chest. */
    public static function openFreeChest(int $playerId,string $chestType): array
    {
        if(!in_array($chestType,['silver','gold'],true))throw new \DomainException('Diese kostenlose Schatztruhe gibt es nicht.');
        return self::atomic($playerId,static function(Connection $db)use($playerId,$chestType):array{
            $row=self::lockedRow($db,$playerId);self::consumeFree($db,$playerId,$chestType,$row,time());
            return self::grantRewards($playerId,$chestType);
        });
    }

    /** Legacy route: silver prefers an available free claim, otherwise an owned chest. */
    public static function openChest(int $playerId,string $chestType): array
    {
        self::validType($chestType);
        return self::atomic($playerId,static function(Connection $db)use($playerId,$chestType):array{
            $row=self::lockedRow($db,$playerId);$now=time();$status=self::status($row,$now);
            if($chestType==='silver'&&$status['free_silver_available']&&TreasureService::houseLevel($playerId)>=1)self::consumeFree($db,$playerId,$chestType,$row,$now);
            else{
                if((int)$row[$chestType.'_count']<1){
                    if($chestType==='silver')self::consumeFree($db,$playerId,$chestType,$row,$now);
                    throw new \DomainException('Keine '.($chestType==='gold'?'goldene':($chestType==='platinum'?'Platin-':'blaue')).' Schatztruhe im Besitz.');
                }
                $db->execute('UPDATE player_chests SET '.$chestType.'_count='.$chestType.'_count-1 WHERE player_id=?',[$playerId]);
            }
            return self::grantRewards($playerId,$chestType);
        });
    }

    /** Legacy URL rejects crystal purchases; free and owned chests remain available. */
    public static function purchaseAndOpenChest(int $playerId,string $chestType): array
    {
        \Conquer\Game\CrystalEconomy::reject();
    }

    public static function addChest(int $playerId,string $chestType,int $count=1): void
    {
        self::validType($chestType);if($count<1)throw new \InvalidArgumentException('count must be >= 1');
        $db=Connection::getInstance();self::ensureRowExists($db,$playerId);
        $db->execute('UPDATE player_chests SET '.$chestType.'_count='.$chestType.'_count+? WHERE player_id=?',[$count,$playerId]);
    }

    private static function validType(string $type): void
    {
        if(!in_array($type,self::VALID_TYPES,true))throw new \DomainException('Ungültige Schatztruhe.');
    }

    private static function consumeFree(Connection $db,int $playerId,string $type,array $row,int $now): void
    {
        if(TreasureService::houseLevel($playerId)<1)throw new \DomainException('Baue zuerst eine Schatzkammer in dieser Welt.');
        $status=self::status($row,$now);
        if(!$status['free_'.$type.'_available']){
            if($type==='silver'&&$status['free_silver_remaining']===0)throw new \DomainException('Alle zehn blauen Schatztruhen wurden heute geöffnet. Morgen gibt es neue.');
            throw new \DomainException($type==='silver'?'Zwischen kostenlosen blauen Schatztruhen liegen zehn Minuten.':'Die goldene Schatztruhe ist alle 24 Stunden kostenlos verfügbar.');
        }
        if($type==='silver')$db->execute('UPDATE player_chests SET free_silver_used_today=?,last_free_silver_reset=?,last_free_silver_at=? WHERE player_id=?',[
            self::FREE_SILVER_PER_DAY-$status['free_silver_remaining']+1,gmdate('Y-m-d',$now),gmdate('Y-m-d H:i:s',$now),$playerId]);
        else $db->execute('UPDATE player_chests SET last_free_gold_at=? WHERE player_id=?',[gmdate('Y-m-d H:i:s',$now),$playerId]);
    }

    /** Grants contents only; the caller consumes the owned chest in the same transaction. */
    public static function grantRewards(int $playerId,string $type): array
    {
        self::validType($type);
        if (!Connection::getInstance()->getPdo()->inTransaction()) {
            throw new \LogicException('Chest rewards require the enclosing consumption transaction.');
        }
        $rewards=[];
        foreach(self::rollDropTable($type)as$drop){
            $quantity=(int)$drop['quantity'];
            if ($quantity < 1) throw new \RuntimeException('Ungültige Beutemenge in der Schatztruhe.');
            if(isset($drop['fragment_grade'])){
                $result=TreasureService::addRandomFragment($playerId,(string)$drop['fragment_grade'],$quantity);
                $rewards[]=['type'=>'fragment','quantity'=>$quantity]+$result
                    +\Conquer\Game\Rewards\RewardPresentation::fragment((int)$result['treasure_code']);
            }else{
                $code=(int)$drop['item_code'];$def=InventoryService::getItemDef($code);
                if(!$def)throw new \RuntimeException('Unbekannter Gegenstand in der Schatztruhe.');
                InventoryService::addItems($playerId,$code,$quantity);
                $rewards[]=['type'=>'item','quantity'=>$quantity]+\Conquer\Game\Rewards\RewardPresentation::item($code);
            }
        }
        DailyQuestService::trackProgress($playerId,'open_chest');
        return $rewards;
    }

    private static function lockedRow(Connection $db,int $playerId): array
    {
        self::ensureRowExists($db,$playerId);
        return $db->query('SELECT * FROM player_chests WHERE player_id=? FOR UPDATE',[$playerId])->fetch();
    }

    private static function atomic(int $playerId,callable $fn): mixed
    {
        $db=Connection::getInstance();$key='conquer-player-'.$playerId;
        if((int)$db->query('SELECT GET_LOCK(?,5)',[$key])->fetchColumn()!==1)throw new \DomainException('Deine Schatzkammer wird gerade aktualisiert.',503);
        try{return $db->getPdo()->inTransaction()?$fn($db):$db->transaction($fn);}
        finally{$db->query('SELECT RELEASE_LOCK(?)',[$key]);}
    }

    // -------------------------------------------------------------------------
    // Drop table
    // -------------------------------------------------------------------------

    /**
     * Rolls the drop table for the given chest type.
     * Each roll produces exactly one entry from the table.
     *
     * @return list<array<string, mixed>>  One entry per roll.
     */
    public static function rollDropTable(string $chestType): array
    {
        $table  = self::loadDropTable();
        $config = $table['chests'][$chestType] ?? null;
        $config = \Conquer\Game\Rewards\RewardCatalog::override('chest',$chestType) ?? $config;

        if ($config === null) {
            throw new \RuntimeException('No drop table configured for chest type: ' . $chestType);
        }

        $rolls   = (int) ($config['rolls'] ?? 1);
        $entries = (array) $config['drop_table'];
        $results = [];

        for ($i = 0; $i < $rolls; $i++) {
            $results[] = self::weightedRandom($entries);
        }

        return $results;
    }

    /**
     * Selects one entry from a weighted table using cumulative weight.
     *
     * @param list<array<string, mixed>> $table
     * @return array<string, mixed>
     */
    private static function weightedRandom(array $table): array
    {
        $totalWeight = 0;
        foreach ($table as $entry) {
            $totalWeight += (int) ($entry['weight'] ?? 0);
        }

        if ($totalWeight <= 0) {
            throw new \RuntimeException('Drop table has zero total weight.');
        }

        $roll      = random_int(1, $totalWeight);
        $cumulated = 0;

        foreach ($table as $entry) {
            $cumulated += (int) ($entry['weight'] ?? 0);
            if ($roll <= $cumulated) {
                return $entry;
            }
        }

        // Fallback (should never be reached with a valid table).
        return $table[array_key_last($table)];
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Loads the chest drop table JSON and caches it.
     *
     * @return array<string, mixed>
     */
    private static function loadDropTable(): array
    {
        if (self::$dropTableCache !== null) {
            return self::$dropTableCache;
        }

        $file = ROOT_DIR . '/data/chest_drops.json';

        if (!is_file($file)) {
            throw new \RuntimeException('chest_drops.json not found at: ' . $file);
        }

        $raw = file_get_contents($file);
        if ($raw === false) {
            throw new \RuntimeException('Cannot read chest_drops.json');
        }

        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new \RuntimeException('chest_drops.json has unexpected structure.');
        }

        self::$dropTableCache = $decoded;
        return $decoded;
    }

    /**
     * Loads the player_chests row, creating it if absent.
     *
     * @return array<string, mixed>
     */
    private static function loadOrCreateRow(Connection $db, int $playerId): array
    {
        self::ensureRowExists($db, $playerId);

        $row = $db->query(
            'SELECT silver_count, gold_count, platinum_count,
                    free_silver_used_today, last_free_silver_reset, last_free_silver_at, last_free_gold_at
             FROM   player_chests
             WHERE  player_id = ?',
            [$playerId],
        )->fetch();

        if ($row === false) {
            throw new \RuntimeException('Failed to load player_chests row for player ' . $playerId);
        }

        /** @var array<string, mixed> */
        return $row;
    }

    /**
     * Inserts a default player_chests row if one doesn't exist yet.
     */
    private static function ensureRowExists(Connection $db, int $playerId): void
    {
        $db->execute(
            'INSERT IGNORE INTO player_chests
                (player_id, silver_count, gold_count, platinum_count,
                 free_silver_used_today, last_free_silver_reset)
             VALUES (?, 0, 0, 0, 0, NULL)',
            [$playerId],
        );
    }

}
