<?php
declare(strict_types=1);
namespace Conquer\Game\Notification;

use Conquer\Db\Connection;
use Conquer\Game\Locale;
use Conquer\Game\World\WorldContext;

final class PushWorker
{
    /** Reuse canonical, idempotent settlement and locks; no client-side timers. */
    public static function settleDue(int $limit = 100): int
    {
        $db = Connection::getInstance();
        $limit = max(1,min(200,$limit));
        $cities = $db->query("SELECT c.id,c.player_id,c.world_id FROM cities c JOIN worlds w ON w.id=c.world_id
            WHERE w.status IN ('open','running') AND EXISTS(
                SELECT 1 FROM push_subscriptions p JOIN sessions s ON s.id=p.session_id JOIN players u ON u.id=p.player_id
                WHERE p.player_id=c.player_id AND p.completions=1 AND s.expires_at>UTC_TIMESTAMP() AND u.is_banned=0)
            AND (EXISTS(SELECT 1 FROM building_queue q WHERE q.city_id=c.id AND q.is_processed=0 AND q.finishes_at<=UTC_TIMESTAMP())
                OR EXISTS(SELECT 1 FROM troop_queue q WHERE q.city_id=c.id AND q.is_processed=0 AND q.finishes_at<=UTC_TIMESTAMP())
                OR EXISTS(SELECT 1 FROM research_queue q WHERE q.player_id=c.player_id AND q.world_id=c.world_id AND q.is_processed=0 AND q.finishes_at<=UTC_TIMESTAMP())
                OR EXISTS(SELECT 1 FROM hospital_wounded q WHERE q.city_id=c.id AND q.healing_count>0 AND q.healing_ends_at<=UTC_TIMESTAMP()))
            ORDER BY c.id LIMIT ".$limit)->fetchAll();
        foreach ($cities as $city) {
            WorldContext::run((int)$city['world_id'],static function () use ($city): void {
                \Conquer\Game\City\CityState::loadForPlayer((int)$city['player_id'],(int)$city['world_id']);
                \Conquer\Game\Research\ResearchProcessor::processQueue((int)$city['player_id'],(int)$city['world_id']);
            });
        }
        return count($cities);
    }

    /** Scan bounded pages fairly; an event before opt-in is never backfilled. */
    public static function capture(int $limit = 100): int
    {
        $db = Connection::getInstance();
        $limit = max(1,min(500,$limit));
        $ids = $db->query('SELECT p.id FROM push_subscriptions p JOIN sessions s ON s.id=p.session_id JOIN players u ON u.id=p.player_id
            WHERE s.expires_at>UTC_TIMESTAMP() AND u.is_banned=0 ORDER BY p.scanned_at,p.id LIMIT '.$limit)->fetchAll(\PDO::FETCH_COLUMN);
        $captured = 0;
        foreach ($ids as $id) $captured += $db->transaction(static function () use ($db,$id): int {
            $subscription = $db->query('SELECT * FROM push_subscriptions WHERE id=? FOR UPDATE',[$id])->fetch();
            if (!$subscription) return 0;
            // The opt-in floor is immutable: IDs may commit out of order. Re-scan
            // the recent window and let the durable unique outbox deduplicate it.
            $rows = $db->query("SELECT n.id,n.type,n.data_json,n.created_at FROM notifications n
                JOIN cities c ON c.player_id=n.player_id AND c.world_id=COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(n.data_json,'$.world_id')) AS UNSIGNED),1)
                JOIN worlds w ON w.id=c.world_id
                WHERE n.player_id=? AND n.id>? AND n.created_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR)
                AND ((?=1 AND n.type IN ('build_complete','research_complete','train_complete','heal_complete'))
                    OR (?=1 AND n.type IN ('battle_incoming','scouted','wall_destroyed')))
                AND NOT EXISTS(SELECT 1 FROM push_deliveries d WHERE d.subscription_id=? AND d.notification_id=n.id)
                ORDER BY n.id LIMIT 200",[$subscription['player_id'],$subscription['last_notification_id'],$subscription['completions'],$subscription['security'],$id])->fetchAll();
            $count = 0;
            foreach ($rows as $row) {
                $category = PushService::category($row['type']);
                if ($category === null || !$subscription[$category] || strtotime($row['created_at'].' UTC') < time()-3600) continue;
                $data = json_decode((string)$row['data_json'],true) ?: [];
                $world = max(1,(int)($data['world_id'] ?? 1));
                if (!$db->query('SELECT c.id FROM cities c JOIN worlds w ON w.id=c.world_id WHERE c.player_id=? AND c.world_id=?',[$subscription['player_id'],$world])->fetchColumn()) continue;
                $count += $db->execute('INSERT IGNORE INTO push_deliveries(subscription_id,notification_id,category,world_id,delivery_tag) VALUES(?,?,?,?,?)',[$id,$row['id'],$category,$world,bin2hex(random_bytes(16))]);
            }
            $db->execute('UPDATE push_subscriptions SET scanned_at=UTC_TIMESTAMP() WHERE id=?',[$id]);
            return $count;
        });
        return $captured;
    }

    public static function payload(array $row): array
    {
        // Only generic localized copy and an opaque deduplication tag leave the server.
        return ['title'=>Locale::t('push.title',[],$row['locale']),
            'body'=>Locale::t('push.body.'.$row['category'],[],$row['locale']),
            'tag'=>'uok-'.$row['delivery_tag'],'url'=>'city#city'];
    }

    /** Inject transport in tests: no external requests or actual device messages. */
    public static function dispatch(callable $transport, int $limit = 50): array
    {
        $db = Connection::getInstance();
        $stats = ['sent'=>0,'retry'=>0,'expired'=>0,'failed'=>0,'unavailable'=>0];
        $rows = $db->query('SELECT d.id AS delivery_id,d.category,d.notification_id,d.world_id,d.delivery_tag,d.attempts,d.created_at AS queued_at,p.*
            FROM push_deliveries d JOIN push_subscriptions p ON p.id=d.subscription_id
            JOIN sessions s ON s.id=p.session_id JOIN players u ON u.id=p.player_id
            WHERE d.completed_at IS NULL AND d.next_attempt_at<=UTC_TIMESTAMP() AND s.expires_at>UTC_TIMESTAMP() AND u.is_banned=0
            ORDER BY d.id LIMIT '.max(1,min(200,$limit)))->fetchAll();
        $deadline = microtime(true)+45;
        foreach ($rows as $row) {
            if (microtime(true)>$deadline) break;
            $stale = strtotime($row['queued_at'].' UTC') < time()-3600;
            $enabled = $row['category'] === 'test' || !empty($row[$row['category']]);
            $ownsWorld = $db->query('SELECT c.id FROM cities c JOIN worlds w ON w.id=c.world_id WHERE c.player_id=? AND c.world_id=?',[$row['player_id'],$row['world_id']])->fetchColumn();
            if (!$enabled || $stale || !$ownsWorld) $result = 'failed';
            else {
                $active = $db->query('SELECT p.* FROM push_subscriptions p JOIN sessions s ON s.id=p.session_id WHERE p.id=? AND p.player_id=? AND s.expires_at>UTC_TIMESTAMP()',[$row['id'],$row['player_id']])->fetch();
                if (!$active) continue;
                $row = array_replace($row,$active);
                try { $result = $transport($row,self::payload($row)); }
                catch (\Throwable) { $result = 'retry'; }
            }
            if (!array_key_exists($result,$stats)) $result = 'failed';
            $persisted = self::persistResult($row,$result);
            if ($persisted !== null) $stats[$persisted]++;
        }
        return $stats;
    }

    /** The provider request has finished; hold locks only while recording its result. */
    private static function persistResult(array $row,string $result): ?string
    {
        $db = Connection::getInstance();
        return $db->transaction(static function () use ($db,$row,$result): ?string {
            // Rotation takes the same subscription lock. Keep the hash check and
            // its dependent DELETE/UPDATE atomic; a separate read would race again.
            $currentHash = $db->query('SELECT endpoint_hash FROM push_subscriptions WHERE id=? FOR UPDATE',[$row['id']])->fetchColumn();
            if (!is_string($currentHash)) return null;
            if (!hash_equals($row['endpoint_hash'],$currentHash)) return 'retry';
            $delivery = $db->query('SELECT attempts FROM push_deliveries WHERE id=? AND subscription_id=? AND completed_at IS NULL FOR UPDATE',[$row['delivery_id'],$row['id']])->fetch();
            if (!$delivery) return null;
            if ($result === 'expired') $db->execute('DELETE FROM push_subscriptions WHERE id=?',[$row['id']]);
            elseif ($result === 'unavailable') $db->execute('UPDATE push_deliveries SET next_attempt_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 5 MINUTE) WHERE id=?',[$row['delivery_id']]);
            elseif ($result === 'retry' && (int)$delivery['attempts'] < 3) {
                $delay = 60*(2 ** (int)$delivery['attempts']);
                $db->execute('UPDATE push_deliveries SET attempts=attempts+1,next_attempt_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND) WHERE id=?',[$delay,$row['delivery_id']]);
            } else $db->execute('UPDATE push_deliveries SET attempts=attempts+1,completed_at=UTC_TIMESTAMP(),outcome=? WHERE id=?',[$result==='retry'?'failed':$result,$row['delivery_id']]);
            return $result;
        });
    }

    public static function run(): array
    {
        $db = Connection::getInstance();
        $lock = 'uok-push-'.substr(hash('sha256',(string)$db->query('SELECT DATABASE()')->fetchColumn()),0,32);
        if ((int)$db->query('SELECT GET_LOCK(?,0)',[$lock])->fetchColumn() !== 1) return ['busy'=>true];
        try {
            $config = PushConfig::load();
            if (!PushService::schemaReady() || (!PushConfig::configured($config) && PushConfig::firebaseCredentials($config) === null)) return ['available'=>false];
            $db->execute('DELETE p FROM push_subscriptions p LEFT JOIN sessions s ON s.id=p.session_id WHERE s.id IS NULL OR s.expires_at<=UTC_TIMESTAMP()');
            $db->execute('DELETE FROM push_deliveries WHERE created_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 7 DAY)');
            $settled = self::settleDue();
            $captured = self::capture();
            return ['available'=>true,'cities_settled'=>$settled,'captured'=>$captured] + self::dispatch(new PushTransport($config));
        } finally { $db->query('SELECT RELEASE_LOCK(?)',[$lock]); }
    }
}
