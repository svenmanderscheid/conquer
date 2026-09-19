<?php
declare(strict_types=1);
namespace Conquer\Security;

use Conquer\Api\Response;
use Conquer\Db\Connection;
use Conquer\Game\World\WorldContext;

/** Receipt and all side effects commit together, including nested service transactions. */
final class ApiOperation
{
    private static ?array $pending = null;
    private static bool $combatLocked = false;

    public static function protects(string $path, array $body): bool
    {
        return in_array($path, [
            '/api/march/dispatch','/api/march/dispatch-charm','/api/march/dispatch-player',
            '/api/march/dispatch-scout','/api/march/dispatch-gather','/api/march/dispatch-field-attack',
            '/api/march/reinforce','/api/rally/start','/api/rally/start-monster','/api/rally/join',
        ], true) || ($path === '/api/defense/action' && in_array($body['action'] ?? '', ['scout','reinforce','promotion.start','wall.repair'], true));
    }

    public static function begin(int $player, string $method, string $path): void
    {
        if ($method !== 'POST') return;
        $raw=(string)file_get_contents('php://input');
        try { $object=json_decode($raw,false,32,JSON_THROW_ON_ERROR); }
        catch (\JsonException) { return; } // Normal handler supplies its input error.
        if (!$object instanceof \stdClass) return;
        $body=(array)$object;
        if (!self::protects($path,$body)) return;
        $key=$body['operation_key'] ?? null;
        if (!is_string($key) || !preg_match('/^[A-Za-z0-9_-]{16,64}$/D',$key)) {
            Response::error(400,'OPERATION_KEY_REQUIRED','Bitte lade das Spiel neu und wiederhole den Auftrag.');
        }
        $world=WorldContext::id();
        $canonical = self::canonical($object);
        $hash=hash('sha256',json_encode([$path,$world,$canonical],JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
        $db=Connection::getInstance();
        // The front controller already owns the player lock. Hold the combat lock
        // through the outer commit; inner services can acquire it reentrantly.
        if ((int)$db->query('SELECT GET_LOCK(?,5)',['conquer-city-combat'])->fetchColumn()!==1) {
            Response::error(409,'BUSY','Deine Armee wird gerade aktualisiert. Bitte versuche es erneut.');
        }
        self::$combatLocked=true;
        register_shutdown_function([self::class,'abort']);
        $db->getPdo()->beginTransaction();
        $old=$db->query('SELECT * FROM api_operation_receipts WHERE player_id=? AND operation_key=? FOR UPDATE',[$player,$key])->fetch();
        if ($old) {
            self::abort();
            if ((int)$old['world_id']!==$world || !hash_equals($old['payload_hash'],$hash)) {
                Response::error(409,'OPERATION_CONFLICT','Diese Vorgangskennung gehört zu einem anderen Auftrag.');
            }
            header('X-Operation-Replayed: 1');
            Response::ok(json_decode($old['response_json'],true,64,JSON_THROW_ON_ERROR)['data']);
        }
        self::$pending=[$player,$key,$world,$hash];
    }

    private static function canonical(mixed $value): mixed
    {
        if ($value instanceof \stdClass) {
            $properties=get_object_vars($value);ksort($properties,SORT_STRING);
            foreach($properties as &$child)$child=self::canonical($child);
            return (object)$properties;
        }
        if (is_array($value)) return array_map([self::class,'canonical'],$value);
        return $value;
    }

    public static function finish(int $status, string $response): void
    {
        if (self::$pending===null) return;
        if ($status < 200 || $status >= 300) {
            self::abort();
            // The browser may forget this key only when this transaction definitely rolled back.
            if ($status >= 400 && $status < 500) header('X-Operation-Rejected: 1');
            return;
        }
        $db=Connection::getInstance();
        [$player,$key,$world,$hash]=self::$pending;
        try {
            $db->execute('INSERT INTO api_operation_receipts(player_id,operation_key,world_id,payload_hash,response_json) VALUES(?,?,?,?,?)',[$player,$key,$world,$hash,$response]);
            $db->getPdo()->commit();
            self::$pending=null;
        } finally { self::abort(); }
        // Observe only committed new commands, never network retries or polling.
        ActivityMonitor::record($player,$world);
    }

    public static function abort(): void
    {
        if (!self::$combatLocked) return;
        $db=Connection::getInstance();
        try { if($db->getPdo()->inTransaction())$db->getPdo()->rollBack(); }
        finally {
            self::$pending=null;self::$combatLocked=false;
            $db->query('SELECT RELEASE_LOCK(?)',['conquer-city-combat']);
        }
    }
}
