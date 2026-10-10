<?php
declare(strict_types=1);
namespace Conquer\Game\Notification;

use Conquer\Db\Connection;
use Conquer\Game\Community\SocialService;

/** Called inside successful authoritative actions, so rolled-back/replayed actions do not notify. */
final class NotificationEvents
{
    public static function playerName(int $player): string
    {
        return (string) (Connection::getInstance()->query('SELECT COALESCE(NULLIF(k.display_name,\'\'),p.username) FROM players p LEFT JOIN kingdom_profiles k ON k.player_id=p.id WHERE p.id=?',[$player])->fetchColumn() ?: '');
    }

    public static function cityMarch(int $actor,int $recipient,int $world,int $march,int $type): void
    {
        if(!in_array($type,[7,8],true))return;
        self::record(function()use($actor,$recipient,$world,$march,$type):void{
            NotificationService::push($recipient,$type===8?'scout_incoming':'battle_incoming',[
                'world_id'=>$world,'push_only'=>true,'march_id'=>$march,'actor_id'=>$actor,'actor_name'=>self::playerName($actor)]);
        });
    }

    public static function rallyStarted(int $actor,int $world,int $rally,int $alliance,?int $target=null,?string $monster=null): void
    {
        self::record(function()use($actor,$world,$rally,$alliance,$target,$monster):void{
            $data=['world_id'=>$world,'push_only'=>true,'rally_id'=>$rally,'alliance_id'=>$alliance,'actor_id'=>$actor,'actor_name'=>self::playerName($actor)];
            if($target!==null){$data['target_id']=$target;$data['target_name']=self::playerName($target);NotificationService::push($target,'rally_incoming',$data);}
            else $data['monster_name']=$monster;
            $type=$target===null?'alliance_rally_monster':'alliance_rally_player';
            Connection::getInstance()->execute('INSERT INTO notifications(player_id,type,data_json)
                SELECT m.player_id,?,? FROM alliance_members m JOIN alliances a ON a.id=m.alliance_id
                JOIN cities c ON c.player_id=m.player_id AND c.world_id=a.world_id
                LEFT JOIN community_preferences p ON p.player_id=m.player_id AND p.world_id=a.world_id
                WHERE m.alliance_id=? AND a.world_id=? AND m.player_id<>? AND COALESCE(p.alliance_notifications,\'all\')<>\'off\'',[$type,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$alliance,$world,$actor]);
        });
    }

    public static function privateMessage(int $actor,int $recipient,int $world,int $message): void
    {
        self::record(function()use($actor,$recipient,$world,$message):void{
            $data=['world_id'=>$world,'push_only'=>true,'actor_id'=>$actor,'actor_name'=>self::playerName($actor),'message_id'=>$message];
            if(self::allowed($recipient,$world,'private_message',$data))NotificationService::push($recipient,'private_message',$data);
        });
    }

    /** Recheck access and existing channel mutes immediately before provider delivery. */
    public static function allowed(int $player,int $world,string $type,array $data): bool
    {
        $db=Connection::getInstance();
        if(str_starts_with($type,'alliance_rally_')){
            $member=$db->query('SELECT 1 FROM alliance_members m JOIN alliances a ON a.id=m.alliance_id WHERE m.player_id=? AND m.alliance_id=? AND a.world_id=?',[$player,(int)($data['alliance_id']??0),$world])->fetchColumn();
            return (bool)$member && SocialService::preferences($player,$world)['channels']['alliance']!=='off';
        }
        if($type==='private_message'){
            $actor=(int)($data['actor_id']??0);$id=(int)($data['message_id']??0);
            $message=$db->query('SELECT 1 FROM private_chat_messages WHERE id=? AND world_id=? AND sender_id=? AND recipient_id=?',[$id,$world,$actor,$player])->fetchColumn();
            if(!$message||SocialService::blocked($actor,$player,$world))return false;
            $mode=SocialService::preferences($player,$world)['channels']['private'];
            if($mode==='off')return false;
            if($mode==='mentions'&&!$db->query("SELECT 1 FROM community_message_meta WHERE world_id=? AND channel='private' AND message_id=? AND JSON_CONTAINS(mentions_json,?)",[$world,$id,(string)$player])->fetchColumn())return false;
            return (int)$db->query("SELECT COALESCE(MAX(message_id),0) FROM community_read_cursors WHERE world_id=? AND player_id=? AND channel='private' AND scope_id=?",[$world,$player,$actor])->fetchColumn()<$id;
        }
        return true;
    }

    private static function record(callable $event): void
    {
        try{$event();}catch(\Throwable $error){\Conquer\Observability\EventLog::exception($error,'notification.event');}
    }
}
