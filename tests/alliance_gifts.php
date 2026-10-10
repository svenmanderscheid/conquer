<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
define('ROOT_DIR', dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';
date_default_timezone_set('UTC');

use Conquer\Db\Connection;
use Conquer\Game\Alliance\{AllianceGiftService, RallyGiftRewards};
use Conquer\Game\Community\{CommunityService, MailboxService};
use Conquer\Game\Inventory\InventoryService;
use Conquer\Game\Kingdom\KingdomInventory;
use Conquer\Game\World\WorldContext;

$checks = 0;
function giftCheck(bool $ok, string $message): void {
    global $checks;
    if (!$ok) throw new RuntimeException($message);
    $checks++;
}
function giftReject(callable $action, string $reason): void {
    try { $action(); }
    catch (RuntimeException $e) {
        if ($e instanceof PDOException) throw $e;
        giftCheck($e->getMessage() === $reason, $reason);
        return;
    }
    throw new RuntimeException('Unexpected gift claim acceptance: '.$reason);
}

$fixture = new \ConquerTests\FeatureDatabase();
try {
    $db = Connection::getInstance();
    WorldContext::bind(1);
    for ($player = 1; $player <= 3; $player++) {
        $db->execute("INSERT INTO players(id,username,email,password_hash) VALUES(?,?,?,'unused')",
            [$player,'GiftFixture'.$player,'gift'.$player.'@tests.invalid']);
        $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y) VALUES(?,?,1,'Gift city',?,40)",
            [$player,$player,30 + $player * 5]);
    }
    $db->execute("INSERT INTO alliances(id,world_id,name,tag,leader_id) VALUES(1,1,'Gift allies','GFT',1)");
    $db->execute("INSERT INTO alliance_members(alliance_id,player_id,world_id,role) VALUES(1,1,1,'leader'),(1,2,1,'member')");

    $pool = RallyGiftRewards::pool();
    giftCheck(count($pool) === 26 && count(array_unique(array_column($pool,'item_code'))) === 26,
        'small gift pool has 26 distinct usable rewards');
    $coverage = [];
    foreach ($pool as $reward) {
        $code = $reward['item_code'];
        $def = InventoryService::getItemDef($code);
        giftCheck($reward['quantity'] === 1 && InventoryService::isDropEligible($code) && ($def['is_usable'] ?? true),
            'one usable item per gift '.$code);
        $kind = $def['category'] === 'resource_pack' ? $def['resource'] : $def['subcategory'];
        $coverage[$kind][] = $def['category'] === 'resource_pack' ? (int)$def['amount'] : (int)$def['duration_seconds'];

        // Exercise every possible saved outcome through both real claim paths.
        $db->execute("INSERT INTO alliance_gifts(alliance_id,trigger_type,gift_json,expires_at,created_by) VALUES(1,'monster_kill',?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 24 HOUR),1)",
            [json_encode($reward,JSON_THROW_ON_ERROR)]);
        $giftId = $db->lastInsertId();
        AllianceGiftService::claimGift(1,$giftId);
        giftReject(fn()=>AllianceGiftService::claimGift(1,$giftId),'already_claimed');
        giftReject(fn()=>AllianceGiftService::claimGift(3,$giftId),'not_alliance_member');
        MailboxService::state(2,1,['category'=>'alliance']);
        $mailId = (int)$db->query("SELECT id FROM mailbox_entries WHERE player_id=2 AND world_id=1 AND source='alliance_gift' AND source_id=?",[$giftId])->fetchColumn();
        giftCheck(MailboxService::message(2,1,$mailId)['metadata']['rewards'] === $reward,
            'mail displays the frozen actual gift '.$code);
        $body = ['action'=>'mailbox.claim','mail_id'=>$mailId,'request_id'=>'small_gift_claim_'.$code];
        $first = CommunityService::action(2,$body,1);
        giftCheck(CommunityService::action(2,$body,1) === $first,
            'mail claim retry replays the original result '.$code);
        giftReject(fn()=>AllianceGiftService::claimGift(2,$giftId),'already_claimed');
        giftCheck(InventoryService::quantity(1,$code) === 1 && InventoryService::quantity(2,$code) === 1
            && InventoryService::quantity(3,$code) === 0, 'both members receive the same single gift '.$code);
        giftCheck((int)$db->query("SELECT COUNT(*) FROM reward_grant_ledger WHERE source_type='alliance_gift' AND source_key=?",[(string)$giftId])->fetchColumn() === 2,
            'one audited claim per member '.$code);

        if ($def['category'] === 'resource_pack') {
            $resource = $def['resource'];
            $before = (int)($resource === 'gems'
                ? $db->query('SELECT gems FROM players WHERE id=1')->fetchColumn()
                : $db->query('SELECT '.$resource.' FROM cities WHERE id=1')->fetchColumn());
            $used = $db->transaction(fn()=>KingdomInventory::use(1,['city'=>['id'=>1]],['item_code'=>$code]));
            $after = (int)($resource === 'gems'
                ? $db->query('SELECT gems FROM players WHERE id=1')->fetchColumn()
                : $db->query('SELECT '.$resource.' FROM cities WHERE id=1')->fetchColumn());
            giftCheck($after - $before === (int)$def['amount'] && $used['amount'] === (int)$def['amount']
                && InventoryService::quantity(1,$code) === 0, 'gift pack credits exactly its small value '.$code);
        }
    }
    foreach (['food','lumber','stone','gold'] as $kind) {
        sort($coverage[$kind]);
        giftCheck($coverage[$kind] === [1000,5000,10000], $kind.' gifts stay within 1,000–10,000');
    }
    sort($coverage['gems']);
    giftCheck($coverage['gems'] === [10,50], 'crystal gifts are 10 or 50');
    foreach (['building','research','training','healing'] as $kind) {
        sort($coverage[$kind]);
        giftCheck($coverage[$kind] === [300,600,1800], $kind.' speedups stay within 5–30 minutes');
    }
    giftCheck(count($coverage) === 9, 'no chests, fragments, materials or percentage boosts enter rally gifts');

    $before = (int)$db->query('SELECT COUNT(*) FROM alliance_gifts')->fetchColumn();
    $db->transaction(fn()=>AllianceGiftService::createRallyGift(3,1));
    $db->transaction(fn()=>AllianceGiftService::createRallyGift(1,2));
    giftCheck((int)$db->query('SELECT COUNT(*) FROM alliance_gifts')->fetchColumn() === $before,
        'outsiders and membership in another world cannot create gifts');
    $db->transaction(fn()=>AllianceGiftService::createRallyGift(1,1));
    $created = $db->query('SELECT gift_json,TIMESTAMPDIFF(SECOND,UTC_TIMESTAMP(),expires_at) AS remaining FROM alliance_gifts ORDER BY id DESC LIMIT 1')->fetch();
    giftCheck(in_array(json_decode($created['gift_json'],true),$pool,true)
        && (int)$created['remaining'] >= 86390 && (int)$created['remaining'] <= 86400,
        'new rally gift stores one allowed outcome with a 24-hour expiry');
    echo "ALL $checks SMALL ALLIANCE GIFT CHECKS PASSED\n";
} finally {
    $fixture->close();
}
