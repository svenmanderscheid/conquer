<?php
declare(strict_types=1);
namespace Conquer\Observability;

/** Records API outcomes after receipt commit/rollback, with no raw payloads. */
final class RequestTrace
{
    private static string $id = '';
    private static string $route = '';
    private static string $method = '';
    private static string $operation = '';
    private static int $player = 0;
    private static int $world = 0;
    private static float $started = 0;
    private static bool $finished = false;
    private static array $context = [];

    public static function begin(string $method, string $route): void
    {
        self::$id = bin2hex(random_bytes(16)); self::$route = SafeData::route($route);
        self::$method = strtoupper($method); self::$started = microtime(true); self::$finished = false;
        self::$player = 0; self::$world = 0; self::$operation = '';
        self::$context = ['method'=>self::$method,'client_request_id'=>SafeData::token($_SERVER['HTTP_X_CLIENT_REQUEST_ID']??'')];
        if (!headers_sent()) header('X-Request-ID: '.self::$id);
    }

    public static function bind(array $session): void
    {
        self::$player = max(0,(int)($session['player_id']??0));
        self::$world = max(0,(int)($session['active_world_id']??0));
    }

    public static function captureBody(array $body): void
    {
        if (is_string($body['operation_key']??null) && preg_match('/^[A-Za-z0-9_-]{16,64}$/D',$body['operation_key'])) self::$operation=$body['operation_key'];
        // These stable action identifiers are never user messages, descriptions, passwords or names.
        $action = $body['action']??'';
        if (is_string($action) && preg_match('/^[a-z][a-z0-9_.-]{0,63}$/D',$action)) self::$context['action']=$action;
        foreach (['target_id','city_id','march_id','report_id','order_id','building_code','research_code','troop_code'] as $key) {
            $value=$body[$key]??null;
            if (is_int($value) || (is_string($value) && preg_match('/^[a-zA-Z0-9_.-]{1,48}$/D',$value))) self::$context[$key]=$value;
        }
    }

    public static function complete(int $status, array $body): void
    {
        if (self::$id==='' || self::$finished || self::$route==='/api/telemetry') return;
        self::$finished=true;
        $duration=(int)round((microtime(true)-self::$started)*1000);
        $code=SafeData::token($body['error']['code']??($status<400?'OK':'HTTP_'.$status),96);
        $replayed=in_array('X-Operation-Replayed: 1',headers_list(),true);
        $context=self::$context+['http_status'=>$status,'replayed'=>$replayed];
        if(is_array($body['data']??null)){
            foreach(['id','march_id','report_id','queue_id','order_id'] as $key){
                $value=$body['data'][$key]??null;
                if(is_int($value) || (is_string($value)&&ctype_digit($value)))$context['result_'.$key]=max(0,(int)$value);
            }
        }
        $successful=$status>=200 && $status<300 && ($body['ok']??false)===true;
        if (in_array(self::$method,['POST','PUT','PATCH','DELETE'],true)) {
            EventLog::record(['category'=>'action','severity'=>$status>=500?'error':'info','code'=>$code,
                'message'=>'API '.($context['action']??self::$method).' '.$code,
                'outcome'=>$successful?($replayed?'replayed':'success'):($status>=500?'failed':'rejected'),
                'duration_ms'=>$duration,'context'=>$context]);
        }
        if ($status>=500) EventLog::record(['category'=>'error','severity'=>'error','code'=>$code,
            'message'=>'API request failed','outcome'=>'failed','duration_ms'=>$duration,'context'=>$context]);
        elseif ($duration>=2000) EventLog::record(['category'=>'system','severity'=>'warning','code'=>'API_SLOW',
            'message'=>'API response exceeded two seconds','duration_ms'=>$duration,'context'=>$context]);
    }

    public static function id(): string { return self::$id; }
    public static function route(): string { return self::$route; }
    public static function player(): int { return self::$player; }
    public static function world(): int { return self::$world; }
    public static function operation(): string { return self::$operation; }
}
