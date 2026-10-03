<?php
declare(strict_types=1);
namespace Conquer\Game\Ui;

use Conquer\Db\Connection;
use Conquer\Game\Locale;
use Conquer\Game\World\WorldSettings;

/** A scheduled shortcut, never an event rule or reward supplied by the client. */
final class ExtraEventButton
{
    public const TARGETS=['events','dungeons','market','community','land','world','alliance'];
    public const ICONS=['events','quests','alliance','world-map','market'];

    public static function read(int $worldId): array
    {
        return self::all($worldId)[0]??[];
    }

    public static function all(int $worldId): array
    {
        try {$json=Connection::getInstance()->query('SELECT settings_json FROM world_extra_event_buttons WHERE world_id=?',[$worldId])->fetchColumn();}
        catch(\PDOException $e){if(($e->errorInfo[1]??null)===1146)return [];throw $e;}
        $settings=$json?json_decode($json,true,32,JSON_THROW_ON_ERROR):[];
        // Preserve the original single-event record and give it a stable tab ID.
        return isset($settings['events'])?array_values($settings['events']):($settings?[$settings+['id'=>1]]:[]);
    }

    public static function validate(array $input): array
    {
        $out=['enabled'=>($input['enabled']??'0')==='1'];
        foreach(['en','de','fr'] as $locale){
            $name=$input['name_'.$locale]??'';
            if(!is_string($name)||!mb_check_encoding($name,'UTF-8')||mb_strlen(trim($name))>40||preg_match('/[\x00-\x1F\x7F]/u',$name))throw new \InvalidArgumentException(Locale::t('extra_event.invalid'));
            $out['name_'.$locale]=trim($name);
        }
        if($out['name_en']==='')throw new \InvalidArgumentException(Locale::t('extra_event.invalid'));
        foreach(['en','de','fr'] as $locale){
            $description=$input['description_'.$locale]??'';
            if(!is_string($description)||!mb_check_encoding($description,'UTF-8')||mb_strlen($description)>2000||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',$description))throw new \InvalidArgumentException(Locale::t('extra_event.invalid'));
            $out['description_'.$locale]=trim($description);
        }
        foreach(['starts_at','ends_at'] as $key){
            $value=$input[$key]??null;
            if(!is_string($value))throw new \InvalidArgumentException(Locale::t('extra_event.invalid'));
            $value=str_replace('T',' ',$value);if(strlen($value)===16)$value.=':00';
            $date=\DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$value,new \DateTimeZone('UTC'));
            if(!$date||$date->format('Y-m-d H:i:s')!==$value)throw new \InvalidArgumentException(Locale::t('extra_event.invalid'));
            $out[$key]=$value;
        }
        if($out['ends_at']<=$out['starts_at'])throw new \InvalidArgumentException(Locale::t('extra_event.invalid'));
        foreach(['target'=>self::TARGETS,'icon'=>self::ICONS] as $key=>$allowed){
            if(!is_string($input[$key]??null)||!in_array($input[$key],$allowed,true))throw new \InvalidArgumentException(Locale::t('extra_event.invalid'));
            $out[$key]=$input[$key];
        }
        return $out;
    }

    /** Runs inside the authenticated, CSRF-protected AdminService transaction. */
    public static function save(Connection $db,array $input): array
    {
        $world=WorldSettings::integer($input['world_id']??0,1,2147483647,'World');
        if(!$db->query('SELECT id FROM worlds WHERE id=? FOR UPDATE',[$world])->fetchColumn())throw new \InvalidArgumentException(Locale::t('extra_event.invalid'));
        $settings=self::validate($input);$before=self::all($world);
        $id=WorldSettings::integer($input['event_id']??1,0,2147483647,'Event');
        $events=$before;$found=false;
        if($id===0){
            if(count($events)>=50)throw new \InvalidArgumentException(Locale::t('extra_event.limit'));
            $id=max([0,...array_column($events,'id')])+1;$events[]=$settings+['id'=>$id];$found=true;
        }else foreach($events as &$event){if($event['id']===$id){$event=$settings+['id'=>$id];$found=true;break;}}unset($event);
        // Existing integrations saved the original event without an event_id.
        if(!$events&&$id===1&&!array_key_exists('event_id',$input)){$events[]=$settings+['id'=>1];$found=true;}
        if(!$found)throw new \InvalidArgumentException(Locale::t('extra_event.invalid'));
        $db->execute('INSERT INTO world_extra_event_buttons(world_id,settings_json) VALUES(?,?) ON DUPLICATE KEY UPDATE settings_json=VALUES(settings_json)',[$world,json_encode(['events'=>$events],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
        return ['target_type'=>'world','target_id'=>$world,'event_id'=>$id,'before'=>$before,'after'=>$events,'message'=>Locale::t('extra_event.saved')];
    }

    public static function active(int $worldId,?int $time=null): ?array
    {
        return self::activeEvents($worldId,$time)[0]??null;
    }

    public static function activeEvents(int $worldId,?int $time=null): array
    {
        $now=gmdate('Y-m-d H:i:s',$time??time());$events=[];
        $locale=Locale::current();
        foreach(self::all($worldId) as $settings){
            if(empty($settings['enabled'])||$now<$settings['starts_at']||$now>=$settings['ends_at'])continue;
            $events[]=['id'=>$settings['id'],'name'=>$settings['name_'.$locale]?:$settings['name_en'],'description'=>($settings['description_'.$locale]??'')?:($settings['description_en']??''),'icon'=>$settings['icon'],'target'=>$settings['target'],'starts_at'=>$settings['starts_at'],'ends_at'=>$settings['ends_at']];
        }
        return $events;
    }
}
