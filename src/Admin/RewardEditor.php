<?php
declare(strict_types=1);
namespace Conquer\Admin;

use Conquer\Db\Connection;
use Conquer\Game\Rewards\RewardCatalog;
use Conquer\Game\World\WorldSettings;

final class RewardEditor
{
    /** Runs within AdminService's transaction, role check, idempotency and audit. */
    public static function save(Connection $db, int $adminId, string $action, array $input): array
    {
        $type=$input['source_type']??null; $key=$input['source_key']??null;
        if (!is_string($type)||!is_string($key)) throw new \InvalidArgumentException('Ungültige Beutequelle.');
        $defaults=RewardCatalog::defaults($type,$key);
        $scope=$input['reward_scope']??'global';
        if(!in_array($scope,['global','world'],true))throw new \InvalidArgumentException('Ungültiger Geltungsbereich.');
        $world=$scope==='world'?WorldSettings::integer($input['world_id']??null,1,2147483647,'Welt-ID'):0;
        if($world&&!$db->query('SELECT id FROM worlds WHERE id=?',[$world])->fetchColumn())throw new \InvalidArgumentException('Welt nicht gefunden.');
        $revision=WorldSettings::integer($input['revision']??null,0,2147483647,'Version');
        $current=$db->query('SELECT * FROM '.($world?'reward_world_overrides':'reward_overrides').' WHERE '.($world?'world_id=? AND ':'').'source_type=? AND source_key=? FOR UPDATE',$world?[$world,$type,$key]:[$type,$key])->fetch();
        if ($revision!==(int)($current['revision']??0)) throw new \InvalidArgumentException('Diese Beute wurde inzwischen geändert. Lade die Seite neu, bevor du erneut speicherst.');
        $before=$current && $current['config_json']!==null?json_decode($current['config_json'],true,64,JSON_THROW_ON_ERROR):RewardCatalog::effective($type,$key,$world);
        $after=$action==='reward-reset'?null:RewardCatalog::validate($type,$key,$input['config']??null);
        $json=$after===null?null:json_encode($after,JSON_THROW_ON_ERROR);
        if($world)$db->execute('INSERT INTO reward_world_overrides(world_id,source_type,source_key,config_json,revision,updated_by) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE config_json=VALUES(config_json),revision=VALUES(revision),updated_by=VALUES(updated_by),updated_at=UTC_TIMESTAMP()',[$world,$type,$key,$json,$revision+1,$adminId]);
        else $db->execute('INSERT INTO reward_overrides(source_type,source_key,config_json,revision,updated_by) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE config_json=VALUES(config_json),revision=VALUES(revision),updated_by=VALUES(updated_by),updated_at=UTC_TIMESTAMP()',[$type,$key,$json,$revision+1,$adminId]);
        $db->execute('INSERT INTO reward_rule_revisions(scope_world_id,source_type,source_key,revision,config_json,updated_by) VALUES(?,?,?,?,?,?)',[$world,$type,$key,$revision+1,$json,$adminId]);
        RewardCatalog::resetCache();
        return ['target_type'=>'reward','target_id'=>null,'before'=>['source'=>$type.':'.$key,'scope_world_id'=>$world,'config'=>$before],'after'=>['source'=>$type.':'.$key,'scope_world_id'=>$world,'config'=>$after??RewardCatalog::effective($type,$key,$world),'revision'=>$revision+1], 'message'=>$action==='reward-reset'?'Übergeordnete Beute wiederhergestellt.':($world?'Beute für diese Welt gespeichert.':'Grundbeute für alle Welten gespeichert.')];
    }
}
