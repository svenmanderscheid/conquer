<?php
declare(strict_types=1);
namespace Conquer\Game\Support;

use Conquer\Db\Connection;

final class BugReportService
{
    private const CATEGORIES=['gameplay','interface','performance','account','other'];
    private const SEVERITIES=['minor','normal','blocking'];
    private const TYPES=['bug','idea'];

    /** @return array{id:int,duplicate:bool,message:string} */
    public static function submit(int $playerId,int $worldId,array $input,string $userAgent,string $ip): array
    {
        $operation=self::operation($input['operation_key']??null);
        $type=self::choice($input['report_type']??'bug',self::TYPES,'Meldungsart');
        $category=self::choice($input['category']??null,self::CATEGORIES,'Kategorie');
        $severity=self::choice($input['severity']??'normal',self::SEVERITIES,'Auswirkung');
        $title=self::text($input['title']??null,5,120,'Titel');
        $description=self::text($input['description']??null,20,3000,'Beschreibung');
        $steps=self::text($input['reproduction_steps']??'',0,2000,'Schritte');
        $expected=self::text($input['expected_result']??'',0,1000,'Erwartetes Ergebnis');
        $page=self::text($input['page_path']??'',0,160,'Spielbereich');
        $context=self::context($input['client_context']??null);
        [$screenshot,$screenshotMime]=self::screenshot($input['screenshot']??null);
        $db=Connection::getInstance();
        $existing=$db->query('SELECT id FROM bug_reports WHERE player_id=? AND operation_key=?',[$playerId,$operation])->fetchColumn();
        if($existing)return ['id'=>(int)$existing,'duplicate'=>true,'message'=>'Diese Bugmeldung wurde bereits übermittelt.'];
        $recent=(int)$db->query('SELECT COUNT(*) FROM bug_reports WHERE player_id=? AND created_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 HOUR)',[$playerId])->fetchColumn();
        if($recent>=5)throw new \DomainException('Du hast in der letzten Stunde bereits mehrere Meldungen gesendet. Bitte versuche es später erneut.');
        try {
            $db->execute('INSERT INTO bug_reports(operation_key,player_id,world_id,report_type,category,severity,title,description,reproduction_steps,expected_result,page_path,client_context,screenshot,screenshot_mime,user_agent,ip_address) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[
                $operation,$playerId,$worldId,$type,$category,$severity,$title,$description,$steps,$expected,$page,
                $context===null?null:json_encode($context,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),
                $screenshot,$screenshotMime,
                mb_substr($userAgent,0,255),mb_substr($ip,0,45),
            ]);
            $id=$db->lastInsertId();
        } catch(\PDOException $e) {
            $existing=$db->query('SELECT id FROM bug_reports WHERE player_id=? AND operation_key=?',[$playerId,$operation])->fetchColumn();
            if(!$existing)throw $e;
            return ['id'=>(int)$existing,'duplicate'=>true,'message'=>'Diese Bugmeldung wurde bereits übermittelt.'];
        }
        return ['id'=>$id,'duplicate'=>false,'message'=>'Danke! Deine Bugmeldung #'.$id.' wurde an die Verwaltung gesendet.'];
    }

    private static function operation(mixed $value): string
    {
        if(!is_string($value)||!preg_match('/^[A-Za-z0-9_-]{16,64}$/D',$value))throw new \DomainException('Ungültige Vorgangskennung. Bitte lade die Ansicht neu.');
        return $value;
    }

    private static function choice(mixed $value,array $allowed,string $name): string
    {
        if(!is_string($value)||!in_array($value,$allowed,true))throw new \DomainException('Ungültige Auswahl: '.$name.'.');
        return $value;
    }

    private static function text(mixed $value,int $min,int $max,string $name): string
    {
        if(!is_string($value))throw new \DomainException($name.' fehlt.');
        $value=trim($value);$length=mb_strlen($value);
        if($length<$min||$length>$max)throw new \DomainException($name.' muss '.$min.' bis '.$max.' Zeichen enthalten.');
        return $value;
    }

    private static function context(mixed $value): ?array
    {
        if($value===null)return null;
        if(!is_array($value))throw new \DomainException('Ungültige technische Angaben.');
        $allowed=['viewport','screen','language','platform','online','reduced_motion','app_mode','client_time','menu'];$clean=[];
        foreach($allowed as $key){if(!array_key_exists($key,$value)||!is_scalar($value[$key])&&$value[$key]!==null)continue;$clean[$key]=mb_substr((string)$value[$key],0,120);}
        if(strlen(json_encode($clean,JSON_THROW_ON_ERROR))>2000)throw new \DomainException('Technische Angaben sind zu groß.');
        return $clean;
    }

    /** @return array{0:?string,1:string} */
    private static function screenshot(mixed $value): array
    {
        if($value===null||$value==='')return [null,''];
        if(!is_string($value)||!preg_match('#^data:(image/(?:jpeg|png));base64,([A-Za-z0-9+/=]+)$#D',$value,$match))throw new \DomainException('Der Screenshot hat ein ungültiges Format.');
        $binary=base64_decode($match[2],true);
        if($binary===false||strlen($binary)>900000)throw new \DomainException('Der Screenshot ist zu groß. Bitte nimm einen kleineren Ausschnitt auf.');
        $info=@getimagesizefromstring($binary);
        if($info===false||!in_array($info['mime']??'', ['image/jpeg','image/png'],true)||($info[0]??0)>1920||($info[1]??0)>1920)throw new \DomainException('Der Screenshot konnte nicht geprüft werden.');
        return [$binary,(string)$info['mime']];
    }
}
