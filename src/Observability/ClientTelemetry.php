<?php
declare(strict_types=1);
namespace Conquer\Observability;

use Conquer\Api\Response;
use Conquer\Security\ApiGuard;

/** Browser observations are untrusted signals, never proof of gameplay outcomes or misconduct. */
final class ClientTelemetry
{
    private const CODES = [
        'JAVASCRIPT_ERROR'=>['error','error','Browser reported a script error'],
        'UNHANDLED_REJECTION'=>['error','error','Browser reported an unhandled rejection'],
        'RESOURCE_ERROR'=>['error','warning','Browser reported a failed resource'],
        'INVALID_RESPONSE'=>['error','error','Browser could not read an API response'],
        'NETWORK_FAILURE'=>['connection','warning','Browser could not reach the API'],
        'NETWORK_TIMEOUT'=>['connection','warning','Browser API request timed out'],
        'CONNECTION_RESTORED'=>['connection','info','API response received after a failed connection'],
        'BROWSER_OFFLINE'=>['connection','info','Browser reported offline status'],
        'BROWSER_ONLINE'=>['connection','info','Browser reported online status; server reachability unconfirmed'],
        'CLIENT_QUEUE_DROPPED'=>['system','warning','Browser diagnostics queue reached its bound'],
    ];

    public static function handle(array $session): never
    {
        if (($_SERVER['REQUEST_METHOD']??'GET')!=='POST') Response::error(405,'METHOD_NOT_ALLOWED');
        if ((int)($session['player_id']??0)<1) Response::error(401,'UNAUTHENTICATED');
        $csrf=$_SERVER['HTTP_X_CSRF_TOKEN']??'';
        if (!is_string($csrf) || $csrf==='' || !hash_equals((string)($session['csrf_token']??''),$csrf)) Response::error(403,'CSRF_INVALID');
        ApiGuard::limit('telemetry.player',(string)$session['player_id'],6,60);
        if ((int)($_SERVER['CONTENT_LENGTH']??0)>16384) Response::error(413,'REQUEST_TOO_LARGE');
        $raw=(string)file_get_contents('php://input',false,null,0,16385);
        if (strlen($raw)>16384) Response::error(413,'REQUEST_TOO_LARGE');
        try { $body=json_decode($raw,true,8,JSON_THROW_ON_ERROR); }
        catch (\JsonException) { Response::error(400,'INVALID_JSON'); }
        if (!is_array($body) || !is_array($body['events']??null) || count($body['events'])>10) Response::error(422,'INVALID_TELEMETRY');
        $count=0;
        foreach($body['events'] as $event) {
            if (!is_array($event)) continue;
            $normalized=self::normalize($event,$session);
            if ($normalized!==null && EventLog::record($normalized)) $count++;
        }
        // Drop after a file fallback as well: replays would otherwise amplify a database outage.
        Response::ok(['accepted'=>count($body['events']),'indexed'=>$count]);
    }

    public static function normalize(array $event, array $session): ?array
    {
        $code=$event['code']??null;
        if (!is_string($code) || !isset(self::CODES[$code])) return null;
        $uid=$event['id']??null;
        if (!is_string($uid) || !preg_match('/^[a-zA-Z0-9_-]{16,64}$/D',$uid)) return null;
        [$category,$severity,$message]=self::CODES[$code];
        $context=is_array($event['context']??null)?$event['context']:[];
        // Client identity, severity, outcomes, release attribution and arbitrary message text are ignored.
        $allowed=array_intersect_key($context,array_flip(['client_request_id','client_release','screen','browser','platform','online','visibility','file','line','column','exception_class','previous_code','queue_dropped']));
        $allowed['repeat_count']=is_numeric($context['repeat_count']??null)?max(1,min(1000000,(int)$context['repeat_count'])):1;
        $activeWorld=(int)($session['active_world_id']??0);
        $pageWorld=is_numeric($event['world_id']??null)?max(0,(int)$event['world_id']):0;
        $allowed['client_world_id']=$pageWorld;
        return ['event_uid'=>hash('sha256',(string)$session['player_id'].':'.$uid),'category'=>$category,
            'severity'=>$severity,'origin'=>'client','code'=>$code,'message'=>$message,'outcome'=>'observed',
            // A queued event from a previous world must never be attributed to the current world.
            'player_id'=>(int)$session['player_id'],'world_id'=>$pageWorld===$activeWorld?$activeWorld:0,
            'request_id'=>'','operation_id'=>'','route'=>SafeData::route($event['route']??''),
            'duration_ms'=>is_numeric($event['duration_ms']??null)?$event['duration_ms']:null,
            'occurred_at'=>is_numeric($event['at']??null)?((float)$event['at']/1000):null,
            'context'=>SafeData::context($allowed)];
    }
}
