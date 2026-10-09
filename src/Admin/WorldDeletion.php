<?php
declare(strict_types=1);
namespace Conquer\Admin;

use Conquer\Db\Connection;
use Conquer\Game\Locale;
use Conquer\Game\World\WorldSettings;

/** Called inside the admin receipt/audit transaction, with spawn and combat locks held. */
final class WorldDeletion
{
    // Account purchase receipts and administrator history outlive the world.
    private const KEEP = ['theme_bundle_orders','player_theme_bundle_purchases','theme_bundle_provider_events','admin_gifts'];

    public static function delete(Connection $db,array $input): array
    {
        $id=WorldSettings::integer($input['world_id']??0,1,2147483647,'World ID');
        $worlds=$db->query('SELECT * FROM worlds ORDER BY id FOR UPDATE')->fetchAll();
        $world=null;foreach($worlds as $candidate)if((int)$candidate['id']===$id)$world=$candidate;
        self::check($world!==null,'missing');
        self::check(count($worlds)>1,'last');
        self::check($world['status']==='closed','close_first');
        self::check(is_string($input['confirm_name']??null)&&$input['confirm_name']===$world['name'],'name_mismatch');
        self::check(($input['confirm_delete']??null)==='1','confirmation_required');

        $columns=[];
        foreach($db->query('SELECT TABLE_NAME,COLUMN_NAME,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE()')->fetchAll() as $row)
            $columns[$row['TABLE_NAME']][$row['COLUMN_NAME']]=$row['IS_NULLABLE'];
        if(isset($columns['theme_bundle_orders'])){
            $orders=$db->query('SELECT id,status FROM theme_bundle_orders WHERE world_id=? FOR UPDATE',[$id])->fetchAll();
            foreach($orders as $order)self::check(!in_array($order['status'],['pending','paid'],true),'pending_purchase');
            if($orders){
                self::check($columns['theme_bundle_orders']['world_id']==='YES'&&$columns['theme_bundle_orders']['city_id']==='YES','migration_required');
                $db->execute('UPDATE theme_bundle_orders SET world_id=NULL,city_id=NULL WHERE world_id=?',[$id]);
            }
        }

        // Old tables did not declare foreign keys. Include their ownership links explicitly.
        $links=[];
        foreach($columns as $table=>$fields){
            if(in_array($table,self::KEEP,true)||$table==='worlds')continue;
            foreach(['city_id','origin_city_id','sender_city_id','source_city_id','target_city_id','attacker_city_id'] as $column)
                if(isset($fields[$column]))$links[$table][$column]=['cities','id'];
            foreach(['alliance_id','host_alliance_id','guest_alliance_id'] as $column)
                if(isset($fields[$column]))$links[$table][$column]=['alliances','id'];
            foreach(['expedition_id'=>'expeditions','rally_id'=>'rallies','shrine_id'=>'shrines','invasion_id'=>'world_invasions','march_id'=>'marches','structure_id'=>'alliance_structures'] as $column=>$parent)
                if(isset($fields[$column]))$links[$table][$column]=[$parent,'id'];
        }
        foreach([
            'alliance_diplomacy'=>['target_id','alliances'],
            'alliance_gift_claims'=>['gift_id','alliance_gifts'],
            'alliance_help_log'=>['request_id','alliance_help_requests'],
            'community_help_log'=>['request_id','community_help_requests'],
            'alliance_event_rsvps'=>['event_id','alliance_calendar_events'],
            'alliance_poll_votes'=>['poll_id','alliance_polls'],
            'conquest_contributions'=>['event_id','conquest_events'],
            'conquest_results'=>['event_id','conquest_events'],
            'conquest_reward_claims'=>['event_id','conquest_events'],
            'conquest_score_ticks'=>['event_id','conquest_events'],
            'dungeon_members'=>['run_id','dungeon_runs'],
            'dungeon_rewards'=>['run_id','dungeon_runs'],
            'dungeon_votes'=>['run_id','dungeon_runs'],
            'territory_crown_scores'=>['cycle_id','territory_crown_cycles'],
            'territory_crown_control'=>['cycle_id','territory_crown_cycles'],
        ] as $table=>[$column,$parent])if(isset($columns[$table][$column],$columns[$parent]['id']))$links[$table][$column]=[$parent,'id'];
        foreach($db->query('SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL')->fetchAll() as $row){
            if(!in_array($row['TABLE_NAME'],self::KEEP,true))$links[$row['TABLE_NAME']][$row['COLUMN_NAME']]=[$row['REFERENCED_TABLE_NAME'],$row['REFERENCED_COLUMN_NAME']];
        }

        $predicates=[];
        foreach($columns as $table=>$fields){
            if(in_array($table,self::KEEP,true)||$table==='worlds')continue;
            $parts=[];foreach(['world_id','scope_world_id'] as $column)if(isset($fields[$column]))$parts[]=self::quote($column).'='.$id;
            if($parts)$predicates[$table]='('.implode(' OR ',$parts).')';
        }
        $predicates['worlds']='`id`='.$id;
        if(isset($columns['notifications']))$predicates['notifications']="COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(data_json,'$.world_id')) AS UNSIGNED),1)=".$id;
        // Propagate ownership through unscoped children without loading potentially large ID lists.
        do{
            $added=false;
            foreach($links as $table=>$relations){
                if(isset($predicates[$table]))continue;
                $parts=[];
                foreach($relations as $column=>[$parent,$key])if(isset($predicates[$parent]))
                    $parts[]=self::quote($column).' IN (SELECT '.self::quote($key).' FROM '.self::quote($parent).' WHERE '.$predicates[$parent].')';
                if($parts){$predicates[$table]='('.implode(' OR ',$parts).')';$added=true;}
            }
        }while($added);

        // Delete children before their parents so both FK checks and ownership subqueries stay valid.
        $order=[];$visiting=[];$visited=[];
        $visit=function(string $table)use(&$visit,&$order,&$visiting,&$visited,$links,$predicates):void{
            if(isset($visited[$table]))return;
            if(isset($visiting[$table]))throw new \RuntimeException(Locale::t('admin.world_delete.dependency_error'));
            $visiting[$table]=true;
            foreach($links as $child=>$relations)if($child!==$table&&isset($predicates[$child]))
                foreach($relations as [$parent])if($parent===$table){$visit($child);break;}
            unset($visiting[$table]);$visited[$table]=true;$order[]=$table;
        };
        foreach(array_keys($predicates) as $table)$visit($table);
        // Atomic deletion requires transactional storage for every affected table.
        foreach($db->query('SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')->fetchAll() as $table)
            if(isset($predicates[$table['TABLE_NAME']]))self::check($table['ENGINE']==='InnoDB','storage_error');

        $sessions=$db->execute('DELETE FROM sessions WHERE active_world_id=?',[$id]);
        $deleted=[];
        foreach($order as $table)$deleted[$table]=$db->execute('DELETE FROM '.self::quote($table).' WHERE '.$predicates[$table]);
        return ['target_type'=>'world','target_id'=>$id,'world_id'=>$id,'before'=>$world,
            'after'=>['deleted'=>true,'deleted_rows'=>$deleted,'revoked_sessions'=>$sessions],
            'message'=>Locale::t('admin.world_delete.success',['name'=>$world['name']])];
    }

    private static function check(bool $condition,string $key): void
    {
        if(!$condition)throw new \InvalidArgumentException(Locale::t('admin.world_delete.'.$key));
    }

    private static function quote(string $identifier): string
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$identifier))throw new \LogicException('Invalid database identifier.');
        return '`'.$identifier.'`';
    }
}
