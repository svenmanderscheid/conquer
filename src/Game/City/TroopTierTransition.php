<?php
declare(strict_types=1);
namespace Conquer\Game\City;

use Conquer\Db\Connection;
use Conquer\Game\WorldRules;

/** One-time, backed-up transition. Historical reports and paid timers stay intact. */
final class TroopTierTransition
{
    public const MARKER='data:five-troop-tiers-20260929';

    public static function army(array $army): array
    {
        $result=[];
        foreach($army as $code=>$count){
            if(!ctype_digit((string)$code)||!is_numeric($count)||$count<0)throw new \RuntimeException('Invalid saved army composition.');
            $target=TroopData::activeCode((int)$code);
            $result[$target]=($result[$target]??0)+(int)$count;
        }
        return $result;
    }

    /** Backup receives every affected row before any database write. */
    public static function run(Connection $db,bool $apply=false,?callable $backup=null): array
    {
        return WorldRules::combatLock(static function()use($db,$apply,$backup):array{
            return $db->transaction(static function()use($db,$apply,$backup):array{
                if($db->query('SELECT 1 FROM migrations WHERE filename=?',[self::MARKER])->fetchColumn()!==false)return ['already_applied'=>true,'changes'=>[]];
                if($apply)$db->query('SELECT id FROM cities ORDER BY id FOR UPDATE')->fetchAll();
                $changes=[];$lock=$apply?' FOR UPDATE':'';
                $tables=array_flip($db->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN));
                foreach($db->query('SELECT * FROM city_troops'.$lock)->fetchAll() as $row){
                    $target=TroopData::activeCode((int)$row['troop_code']);
                    if($target===(int)$row['troop_code'])continue;
                    $existing=$db->query('SELECT * FROM city_troops WHERE city_id=? AND troop_code=?'.$lock,[$row['city_id'],$target])->fetch();
                    $changes[]=['table'=>'city_troops','before'=>$row,'target_before'=>$existing,'target_code'=>$target];
                }
                // Convert compositions, including detached returns, but never battle reports.
                $jsonColumns=[
                    'marches'=>['troops_json','wounded_json'], 'rallies'=>['troops_json'],
                    'rally_participants'=>['troops_json'], 'reinforcements'=>['troops_json'],
                    'shrine_captures'=>['garrison_troops_json'], 'shrine_garrisons'=>['troops_json'],
                    'alliance_structure_garrisons'=>['troops_json'], 'troop_formations'=>['troops_json'],
                    'dungeon_members'=>['troops_json'], 'expedition_missions'=>['troops_json'],
                    'invasion_missions'=>['troops_json'], 'territory_garrisons'=>['troops_json'],
                    'territory_army_returns'=>['troops_json'], 'neutral_villages'=>['garrison_json'],
                ];
                foreach($jsonColumns as $table=>$columns){
                    if(!isset($tables[$table]))continue;
                    $primary=$db->query("SHOW KEYS FROM `$table` WHERE Key_name='PRIMARY'")->fetchAll();
                    $keys=array_column($primary,'Column_name');
                    if(!$keys)throw new \RuntimeException('Missing primary key: '.$table);
                    foreach($db->query("SELECT * FROM `$table`".$lock)->fetchAll() as $row){
                        $updates=[];
                        foreach($columns as $column){
                            if(empty($row[$column]))continue;
                            $army=json_decode($row[$column],true,512,JSON_THROW_ON_ERROR);
                            if(!is_array($army))continue;
                            $converted=self::army($army);
                            if($converted!=$army)$updates[$column]=json_encode((object)$converted,JSON_THROW_ON_ERROR);
                        }
                        if($updates)$changes[]=['table'=>$table,'keys'=>array_intersect_key($row,array_flip($keys)),'before'=>$row,'updates'=>$updates];
                    }
                }
                foreach(['troop_queue'=>['troop_code'],'defense_promotions'=>['source_code','target_code']] as $table=>$columns){
                    $where=$table==='troop_queue'?'is_processed=0':"state='training'";
                    foreach($db->query("SELECT * FROM `$table` WHERE $where".$lock)->fetchAll() as $row){
                        $updates=[];
                        foreach($columns as $column){$target=TroopData::activeCode((int)$row[$column]);if($target!==(int)$row[$column])$updates[$column]=$target;}
                        if($updates)$changes[]=['table'=>$table,'keys'=>['id'=>$row['id']],'before'=>$row,'updates'=>$updates];
                    }
                }
                $old=array_column(json_decode((string)file_get_contents(ROOT_DIR.'/data/balance-history/monsters-t10-20260929.json'),true,512,JSON_THROW_ON_ERROR)['monsters'],null,'code');
                $new=array_column(json_decode((string)file_get_contents(ROOT_DIR.'/data/monsters.json'),true,512,JSON_THROW_ON_ERROR)['monsters'],null,'code');
                // World-spawn IDs can differ from catalogue IDs (including overlapping IDs).
                $spawn=json_decode((string)file_get_contents(ROOT_DIR.'/data/world_spawn.json'),true,512,JSON_THROW_ON_ERROR)['monsters'];
                foreach(['old','new'] as $catalogue){
                    $names=[];foreach($$catalogue as $definition)$names[$definition['name'].'_'.$definition['level']]=$definition;
                    foreach($spawn as $entry)if(isset($names[$entry['monster'].'_'.$entry['level']]))${$catalogue}[$entry['code']]=$names[$entry['monster'].'_'.$entry['level']];
                }
                foreach($db->query('SELECT * FROM field_monsters WHERE hp_current>0'.$lock)->fetchAll() as $row){
                    $code=(int)$row['monster_code'];if(!isset($old[$code],$new[$code]))continue;
                    $oldMax=max(1,$old[$code]['amount']*$old[$code]['stats']['hp']);
                    $newMax=max(1,$new[$code]['amount']*$new[$code]['stats']['hp']);
                    if($oldMax===$newMax)continue;
                    $hp=max(1,(int)round($newMax*min(1,$row['hp_current']/$oldMax)));
                    if($hp!==(int)$row['hp_current'])$changes[]=['table'=>'field_monsters','keys'=>['id'=>$row['id']],'before'=>$row,'updates'=>['hp_current'=>$hp]];
                }
                $result=['already_applied'=>false,'changes'=>$changes];
                if(!$apply)return $result;
                if($backup===null)throw new \RuntimeException('A backup is required before applying the troop transition.');
                $backup($result);
                foreach($changes as $change){
                    $table=$change['table'];
                    if($table==='city_troops'){
                        $row=$change['before'];
                        $db->execute('INSERT INTO city_troops(city_id,troop_code,count) VALUES(?,?,?) ON DUPLICATE KEY UPDATE count=count+VALUES(count)',[$row['city_id'],$change['target_code'],$row['count']]);
                        $db->execute('DELETE FROM city_troops WHERE city_id=? AND troop_code=?',[$row['city_id'],$row['troop_code']]);
                    }else{
                        $set=implode(',',array_map(static fn($key)=>"`$key`=?",array_keys($change['updates'])));
                        $where=implode(' AND ',array_map(static fn($key)=>"`$key`=?",array_keys($change['keys'])));
                        $db->execute("UPDATE `$table` SET $set WHERE $where",[...array_values($change['updates']),...array_values($change['keys'])]);
                    }
                }
                $db->execute('INSERT INTO migrations(filename) VALUES(?)',[self::MARKER]);
                return $result;
            });
        });
    }
}
