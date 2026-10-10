<?php
declare(strict_types=1);
namespace Conquer\Admin;

use Conquer\Db\Connection;
use Conquer\Game\Rewards\RewardCatalog;
use Conquer\Game\World\WorldSettings;

final class RewardEditor
{
    public const MAX_BATCH_SOURCES=200;
    /** Editable fields for independent monster/mine drops; other rewards stay authoritative. */
    public static function formConfig(string $type,array $config):array
    {
        $form=['rows'=>array_map(static fn(array $row):array=>['target'=>(string)$row['item_code'],'quantity'=>$row['count'],'chance'=>round($row['probability']*100,4)],$config['drops']),
            'fragment_rows'=>array_map(static fn(array $row):array=>['target'=>isset($row['treasure_code'])?'treasure:'.$row['treasure_code']:'fragment:'.$row['fragment_grade'],'quantity'=>$row['count'],'chance'=>round($row['probability']*100,4)],$config['fragment_drops']??[]),
            'relic_rows'=>array_map(static fn(array $row):array=>['target'=>'relic:'.$row['treasure_code'],'quantity'=>$row['count'],'chance'=>round($row['probability']*100,4)],$config['relic_drops']??[])];
        if($type==='monster')$form+=['resources'=>$config['resource_reward'],'gems_amount'=>$config['gems_drop']['amount'],'gems_chance'=>round($config['gems_drop']['chance']*100,4),'charms'=>$config['charms']];
        foreach(['rows'=>'drops','fragment_rows'=>'fragment_drops'] as $group=>$field){
            foreach($config[$field]??[] as $index=>$row){
                if(isset($row['count_min']))$form[$group][$index]['quantity_min']=$row['count_min'];
                if(isset($row['exclusive_group']))$form[$group][$index]['exclusive_group']=$row['exclusive_group'];
            }
        }
        return $form;
    }

    /** The caller owns one transaction, one mutation receipt and one audit for the whole batch. */
    public static function saveBatch(Connection $db,int $adminId,array $input):array
    {
        $type=$input['source_type']??null;
        if(!is_string($type)||!in_array($type,['farm','monster'],true))throw new \InvalidArgumentException(\Conquer\Game\Locale::t('admin.modern.batch_invalid'));
        $json=$input['updates_json']??null;
        if(!is_string($json)||strlen($json)>1000000)throw new \InvalidArgumentException(\Conquer\Game\Locale::t('admin.modern.batch_invalid'));
        try{$updates=json_decode($json,true,16,JSON_THROW_ON_ERROR);}catch(\JsonException){throw new \InvalidArgumentException(\Conquer\Game\Locale::t('admin.modern.batch_invalid'));}
        if(!is_array($updates)||!array_is_list($updates)||count($updates)<1||count($updates)>self::MAX_BATCH_SOURCES)throw new \InvalidArgumentException(\Conquer\Game\Locale::t('admin.modern.batch_limit'));
        $scope=$input['reward_scope']??'global';
        if(!in_array($scope,['global','world'],true))throw new \InvalidArgumentException(\Conquer\Game\Locale::t('admin.modern.batch_invalid'));
        $world=$scope==='world'?WorldSettings::integer($input['world_id']??null,1,2147483647,'World'):0;
        $seen=[];$before=[];$after=[];
        foreach($updates as $update){
            if(!is_array($update)||!is_string($update['source_key']??null)||!is_array($update['config']??null)||!$update['config'])throw new \InvalidArgumentException(\Conquer\Game\Locale::t('admin.modern.batch_invalid'));
            $key=$update['source_key'];
            if(isset($seen[$key])||array_diff(array_keys($update['config']),['rows','fragment_rows','relic_rows']))throw new \InvalidArgumentException(\Conquer\Game\Locale::t('admin.modern.batch_invalid'));
            $seen[$key]=true;
            if($world){
                $local=$db->query('SELECT config_json FROM reward_world_overrides WHERE world_id=? AND source_type=? AND source_key=? FOR UPDATE',[$world,$type,$key])->fetch();
                if(!$local||$local['config_json']===null){
                    $parent=WorldSettings::integer($update['parent_revision']??null,0,2147483647,'Version');
                    $actual=(int)$db->query('SELECT revision FROM reward_overrides WHERE source_type=? AND source_key=? FOR UPDATE',[$type,$key])->fetchColumn();
                    if($parent!==$actual)throw new \InvalidArgumentException(\Conquer\Game\Locale::t('admin.modern.batch_parent_stale'));
                }
            }
            $effective=RewardCatalog::effective($type,$key,$world);
            $form=array_replace(self::formConfig($type,$effective),$update['config']);
            $changed=[];foreach(array_keys($update['config']) as $field)$changed[match($field){'rows'=>'drops','relic_rows'=>'relic_drops',default=>'fragment_drops'}]=true;
            $preserve=array_diff_key($effective,$changed);
            $result=self::save($db,$adminId,'reward-save',['source_type'=>$type,'source_key'=>$key,'reward_scope'=>$scope,'world_id'=>$world,'revision'=>$update['revision']??null,'config'=>$form],$preserve);
            $before[]=$result['before'];$after[]=$result['after'];
        }
        return ['target_type'=>'reward_batch','target_id'=>null,'before'=>$before,'after'=>$after,'world_id'=>(int)($input['world_id']??0),'message'=>\Conquer\Game\Locale::t('admin.modern.batch_saved',['count'=>count($after)])];
    }

    /** Runs within AdminService's transaction, role check, idempotency and audit. */
    public static function save(Connection $db, int $adminId, string $action, array $input,array $preserve=[]): array
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
        $after=$action==='reward-reset'?null:array_replace(RewardCatalog::validate($type,$key,$input['config']??null),$preserve);
        $json=$after===null?null:json_encode($after,JSON_THROW_ON_ERROR|JSON_PRESERVE_ZERO_FRACTION);
        if($world)$db->execute('INSERT INTO reward_world_overrides(world_id,source_type,source_key,config_json,revision,updated_by) VALUES(?,?,?,?,?,?) ON DUPLICATE KEY UPDATE config_json=VALUES(config_json),revision=VALUES(revision),updated_by=VALUES(updated_by),updated_at=UTC_TIMESTAMP()',[$world,$type,$key,$json,$revision+1,$adminId]);
        else $db->execute('INSERT INTO reward_overrides(source_type,source_key,config_json,revision,updated_by) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE config_json=VALUES(config_json),revision=VALUES(revision),updated_by=VALUES(updated_by),updated_at=UTC_TIMESTAMP()',[$type,$key,$json,$revision+1,$adminId]);
        $db->execute('INSERT INTO reward_rule_revisions(scope_world_id,source_type,source_key,revision,config_json,updated_by) VALUES(?,?,?,?,?,?)',[$world,$type,$key,$revision+1,$json,$adminId]);
        RewardCatalog::resetCache();
        return ['target_type'=>'reward','target_id'=>null,'before'=>['source'=>$type.':'.$key,'scope_world_id'=>$world,'config'=>$before],'after'=>['source'=>$type.':'.$key,'scope_world_id'=>$world,'config'=>$after??RewardCatalog::effective($type,$key,$world),'revision'=>$revision+1], 'message'=>$action==='reward-reset'?'Übergeordnete Beute wiederhergestellt.':($world?'Beute für diese Welt gespeichert.':'Grundbeute für alle Welten gespeichert.')];
    }
}
