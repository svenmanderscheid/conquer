<?php
declare(strict_types=1);
namespace Conquer\Admin;

use Conquer\Db\Connection;
use Conquer\Game\Inventory\InventoryService;
use Conquer\Game\Rewards\RewardCatalog;
use Conquer\Game\World\WorldContext;

/** Confirmed inventory credits and rejected attempts are deliberately separate. */
final class RewardLedger
{
    private static ?Connection $connection = null;
    private static ?bool $available = null;

    public static function available(): bool
    {
        if (!Connection::isInitialized()) return false;
        $db=Connection::getInstance();
        if (self::$connection!==$db) { self::$connection=$db;self::$available=null; }
        if(self::$available!==null)return self::$available;
        return self::$available=(bool)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='reward_grant_ledger'")->fetchColumn();
    }

    /** Context is built only by server code, never by an API request body. */
    public static function ruleContext(string $type,string $key,int $world,string $reference='',?array $config=null): array
    {
        $config??=RewardCatalog::effective($type,$key,$world);
        $allowed=[];
        foreach(['drops','items','drop_table','bonus_drops'] as $field)foreach($config[$field]??[] as $entry){
            if(isset($entry['item_code'])&&(float)($entry['probability']??$entry['weight']??1)>0)$allowed[]=(int)$entry['item_code'];
        }
        // A content fingerprint remains correct for saved encounters even after edits.
        return ['source_type'=>$type,'source_key'=>$key,'reference'=>$reference,'world_id'=>$world,
            'rule_revision'=>'sha256:'.hash('sha256',json_encode($config,JSON_THROW_ON_ERROR)),
            'allowed_items'=>array_values(array_unique($allowed))];
    }

    public static function validate(int $player,int $world,int $item,int $quantity,array $context=[]): void
    {
        $reason=$quantity<=0||$quantity>4294967295?'invalid_quantity':
            (InventoryService::getItemDef($item)===null?'unknown_item':
            (!InventoryService::isDropEligible($item)?'unregistered_item':null));
        if($reason===null&&isset($context['allowed_items'])&&!in_array($item,$context['allowed_items'],true))$reason='source_not_allowed';
        if($reason===null)return;
        self::rejected($reason,$player,$world,$item,$quantity,$context);
        throw new \DomainException(\Conquer\Game\Locale::t('admin.reward_ledger.grant_rejected'));
    }

    public static function rejected(string $reason,int $player,int $world,int $item,int $quantity,array $context=[]): void
    {
        $context=self::context($context);
        \Conquer\Observability\EventLog::record(['category'=>'reward','severity'=>'warning',
            'code'=>'REWARD_'.strtoupper($reason),'message'=>'Reward attempt rejected: '.$reason,'outcome'=>'rejected',
            'player_id'=>$player?:null,'world_id'=>$world?:null,'operation_id'=>$context['operation_id'],
            'context'=>['reason'=>$reason,'item_id'=>$item,'quantity'=>$quantity,'source_type'=>$context['source_type'],
                'source_key'=>$context['source_key'],'rule_revision'=>$context['rule_revision'],'reference'=>$context['reference']]]);
    }

    /** Called after credit, within the same database transaction. Never records a replay. */
    public static function granted(int $player,int $world,int $scope,int $item,int $quantity,array $context=[]): void
    {
        if(!self::available())return; // Additive rollout: old installs remain playable, UI states unavailable.
        $db=Connection::getInstance();
        if(!$db->getPdo()->inTransaction())throw new \LogicException('Reward receipts require the inventory transaction.');
        $kind=in_array($context['reward_kind']??'item',['item','fragment','relic','resource'],true)?($context['reward_kind']??'item'):'item';
        $resource=in_array($context['resource_code']??'',['food','lumber','stone','gold','gems'],true)?$context['resource_code']:'';
        $result=isset($context['result'])?json_encode($context['result'],JSON_THROW_ON_ERROR):null;
        $context=self::context($context);
        $db->execute('INSERT INTO reward_grant_ledger(player_id,world_id,inventory_world_id,item_code,reward_kind,resource_code,quantity,source_type,source_key,source_reference,rule_revision,operation_id,result_json) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$player,$world,$scope,$item,$kind,$resource,$quantity,$context['source_type'],$context['source_key'],$context['reference'],$context['rule_revision'],$context['operation_id'],$result]);
    }

    /** Credits already applied by the authoritative reward operation, not passive production. */
    public static function resources(int $player,int $world,array $resources,array $context=[]): void
    {
        foreach(['food','lumber','stone','gold','gems'] as $resource){
            $amount=(int)($resources[$resource]??0);
            if($amount>0)self::granted($player,$world,$resource==='gems'?0:$world,0,$amount,$context+['reward_kind'=>'resource','resource_code'=>$resource]);
        }
    }

    private static function context(array $context): array
    {
        $context['operation_id']??=\Conquer\Observability\RequestTrace::operation();
        if(empty($context['source_type'])){
            // Legacy grant callers still receive a factual origin, with no invented rule revision.
            foreach(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS,8) as $frame){
                $class=$frame['class']??'';
                if(!$class||in_array($class,[self::class,InventoryService::class,Connection::class,\Conquer\Game\Treasure\TreasureService::class,RewardCatalog::class],true))continue;
                $context['source_type']=match($class){
                    'Conquer\\Game\\Dungeon\\MelusinaProgress'=>'dungeon_quest',
                    'Conquer\\Game\\Quest\\DailyQuestService'=>'daily_quest',
                    'Conquer\\Game\\Tutorial\\TutorialService'=>'tutorial',
                    'Conquer\\Game\\Kingdom\\KingdomService'=>'welcome',
                    'Conquer\\Game\\Alliance\\AllianceGiftService','Conquer\\Game\\Community\\MailboxService'=>'alliance_gift',
                    'Conquer\\Game\\Trading\\TradingShopService'=>'trading_shop',
                    'Conquer\\Game\\City\\BuildingUpgrader'=>'building_refund',
                    'Conquer\\Game\\Conquest\\WelcomeEventService'=>'welcome_event',
                    'Conquer\\Game\\Territory\\TerritoryEconomy'=>'territory',
                    'Conquer\\Admin\\AdminService'=>'admin_gift',
                    'Conquer\\Game\\Treasure\\ChestService'=>'chest',
                    'Conquer\\Game\\March\\MarchTick'=>'monster',
                    'Conquer\\Game\\Rally\\RallyService'=>'monster',
                    'Conquer\\Game\\Kingdom\\KingdomInventory'=>'item_use',
                    'Conquer\\Game\\Dungeon\\DungeonService'=>'dungeon',
                    default=>'inventory',
                };
                $context['reference']??=substr($class.'::'.($frame['function']??''),0,160);break;
            }
        }
        $out=[];
        foreach(['source_type'=>40,'source_key'=>120,'reference'=>160,'rule_revision'=>100,'operation_id'=>100] as $key=>$max)
            $out[$key]=mb_substr(is_scalar($context[$key]??null)?(string)$context[$key]:'',0,$max);
        if($out['source_type']==='')$out['source_type']='inventory';
        return $out;
    }

    /** Bounded, filterable item history. Older grants are not reconstructed. */
    public static function history(array $filters,bool $invalid=false): array
    {
        $db=Connection::getInstance();
        $world=max(0,(int)($filters['world_id']??0));$player=max(0,(int)($filters['player_id']??0));
        $item=max(0,(int)($filters['item_code']??0));$before=max(0,(int)($filters['before']??0));
        $days=(int)($filters['days']??7);if(!in_array($days,[1,7,30,90],true))$days=7;
        $source=is_string($filters['source_type']??null)?substr($filters['source_type'],0,40):'';
        if(!preg_match('/^[a-z_]*$/D',$source))$source='';
        $where=['e.'.($invalid?'occurred_at':'created_at').'>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL '.$days.' DAY)'];$params=[];
        if($invalid){
            if(!(bool)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='operational_events'")->fetchColumn())return ['available'=>false,'rows'=>[],'next'=>0];
            $where[]="e.category='reward' AND e.outcome='rejected'";
        }elseif(!self::available())return ['available'=>false,'rows'=>[],'next'=>0];
        foreach(['world_id'=>$world,'player_id'=>$player] as $column=>$value)if($value){$where[]='e.'.$column.'=?';$params[]=$value;}
        if($before){$where[]='e.id<?';$params[]=$before;}
        if($item){$where[]=$invalid?"JSON_UNQUOTE(JSON_EXTRACT(e.context_json,'$.item_id'))=?":'e.item_code=?';$params[]=$item;}
        if($source!==''){$where[]=$invalid?"JSON_UNQUOTE(JSON_EXTRACT(e.context_json,'$.source_type'))=?":'e.source_type=?';$params[]=$source;}
        $rows=$db->query('SELECT e.*'.($invalid?',e.occurred_at created_at':'').',p.username,w.name world_name FROM '.($invalid?'operational_events':'reward_grant_ledger').' e LEFT JOIN players p ON p.id=e.player_id LEFT JOIN worlds w ON w.id=e.world_id WHERE '.implode(' AND ',$where).' ORDER BY e.id DESC LIMIT 51',$params)->fetchAll();
        $next=count($rows)>50?(int)$rows[49]['id']:0;$rows=array_slice($rows,0,50);
        if($invalid)foreach($rows as &$row){$ctx=json_decode((string)$row['context_json'],true)?:[];$row=array_replace($row,['item_code'=>$ctx['item_id']??0,'quantity'=>$ctx['quantity']??0,'source_type'=>$ctx['source_type']??'inventory','source_key'=>$ctx['source_key']??'','source_reference'=>$ctx['reference']??'','rule_revision'=>$ctx['rule_revision']??'','reason'=>$ctx['reason']??'rejected']);}unset($row);
        return ['available'=>true,'rows'=>$rows,'next'=>$next];
    }
}
