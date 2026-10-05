<?php
declare(strict_types=1);
/** Retired relics in existing saves and reward overrides; disposable database only. */
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';date_default_timezone_set('UTC');
use Conquer\Db\{Connection,MigrationSql};
use Conquer\Game\Treasure\{TreasureData,TreasureService as T,ChestService};
use Conquer\Game\Inventory\InventoryService as I;
use Conquer\Game\Rewards\{RewardCatalog as R,RewardPresentation,ItemSourceService};
use Conquer\Game\World\WorldContext;
$checks=0;$fixture=null;$exit=0;
function checkRetired(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;}
function rejectsRetired(callable $fn,string $label):void{try{$fn();}catch(DomainException|InvalidArgumentException|RuntimeException){checkRetired(true,$label);return;}throw new RuntimeException('Accepted '.$label);}
try{
 $fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();WorldContext::bind(1);
 $db->execute("UPDATE worlds SET status='open',speed_factor=1 WHERE id=1");
 $db->execute("INSERT INTO players(id,username,email,password_hash)VALUES(1,'RetiredFixture','retired@tests.invalid','unused')");
 $db->execute("INSERT INTO cities(id,player_id,world_id,name,coord_x,coord_y,castle_level)VALUES(1,1,1,'Fixture',30,40,5)");
 $db->execute("INSERT INTO city_buildings(city_id,building_code,level)VALUES(1,'treasure_house',25),(1,'castle',5)");
 foreach(['0074_treasure_loadouts.sql','0076_treasure_presets.sql','0116_treasure_effect_progress.sql'] as $file)MigrationSql::apply($db->getPdo(),(string)file_get_contents(ROOT_DIR.'/migrations/'.$file));
 foreach(TreasureData::RETIRED_CODES as $code){
  $db->execute('INSERT INTO player_treasures(player_id,treasure_code,fragments)VALUES(1,?,900)',[$code]);
  $db->execute('INSERT INTO player_treasure_effects(player_id,treasure_code,effect_index,parts)VALUES(1,?,0,5)',[$code]);
  checkRetired(TreasureData::get($code)===null,'retired definition absent '.$code);
  checkRetired(!T::equipTreasure(1,$code,1,25),'owned retired relic cannot equip '.$code);
  rejectsRetired(fn()=>T::addFragments(1,$code,1),'cannot grant retired fragments '.$code);
  rejectsRetired(fn()=>T::upgradeEffect(1,$code,0),'cannot upgrade retired relic '.$code);
  rejectsRetired(fn()=>T::exchangeUniversalFragments(1,$code,1),'cannot exchange retired fragments '.$code);
  rejectsRetired(fn()=>ItemSourceService::query(['treasure_code'=>$code]),'retired relic has no source guide '.$code);
 }
 $db->execute('UPDATE player_treasure_loadouts SET treasure_code=60500101 WHERE player_id=1 AND world_id=1 AND slot=1');
 $db->execute("INSERT INTO player_universal_treasure_fragments(player_id,grade,quantity)VALUES(1,'mythic',50),(1,'legendary',7)");
 $state=T::state(1);
 checkRetired(count($state['items'])===71&&!array_filter($state['items'],fn($i)=>$i['grade']==='mythic'),'even owned retired relics are invisible');
 checkRetired($state['bonuses']===[]&&T::getEquippedBuffs(1,1)===[],'equipped retired relics give no bonuses');
 checkRetired(!isset($state['universal_fragments']['mythic'])&&$state['universal_fragments']['legendary']===7,'retired universal balance is hidden');
 T::addFragments(1,60100001,10);
 $db->execute('INSERT INTO player_treasure_presets(player_id,world_id,preset,items_json)VALUES(1,1,1,?)',[json_encode([60500101,60100001,null,null,null,null])]);
 checkRetired(T::getPresets(1,1)[0]['items']===[null,60100001,null,null,null,null],'saved preset hides retired relic and retains valid relic');
 T::savePreset(1,2,1);checkRetired(T::getPresets(1,1)[1]['items']===array_fill(0,6,null),'saving old equipment drops retired entries');
 T::applyPreset(1,1,1);checkRetired(T::state(1)['items'][0]['treasure_code']===60100001&&T::getEquippedStats(1,1)!==[],'mixed old preset applies remaining relic');
 checkRetired($db->query('SELECT treasure_code FROM player_treasure_loadouts WHERE player_id=1 AND slot=1')->fetchColumn()===null,'retired slot clears on preset apply');
 $db->execute('INSERT INTO player_inventory(player_id,item_code,quantity)VALUES(1,10207005,10)');
 checkRetired(I::getInventory(1)===[]&&I::getItemDef(10207005)===null&&!I::isDropEligible(10207005),'owned mythic fragment pack hidden from inventory and drops');
 I::addItems(1,10207005,5);checkRetired(I::useItem(1,1,10207005)['ok']===false,'retired fragment pack cannot be used');
 checkRetired((int)$db->query('SELECT quantity FROM player_inventory WHERE player_id=1 AND item_code=10207005')->fetchColumn()===10,'retired pack neither granted nor consumed');
 checkRetired(!in_array('fragment:mythic',array_column(\Conquer\Admin\ItemPresentation::catalog(true),'code'),true),'reward editor offers no mythic fragments');
 rejectsRetired(fn()=>R::validate('chest','platinum',['rolls'=>1,'rows'=>[['target'=>'fragment:mythic','weight'=>1,'quantity'=>1]]]),'cannot configure new mythic drop');
 $config=['rolls'=>1,'drop_table'=>[['fragment_grade'=>'mythic','weight'=>1000,'quantity'=>1],['item_code'=>10207005,'weight'=>1000,'quantity'=>1],['fragment_grade'=>'normal','weight'=>1,'quantity'=>1]]];
 $db->execute("INSERT INTO reward_overrides(source_type,source_key,config_json,updated_by)VALUES('chest','platinum',?,1)",[json_encode($config)]);R::resetCache();
 checkRetired(count(R::effective('chest','platinum')['drop_table'])===1,'saved override removes retired rewards');
 for($n=0;$n<10;$n++)checkRetired(ChestService::rollDropTable('platinum')[0]['fragment_grade']==='normal','actual chest rolls use filtered override');
 $config['drop_table']=array_slice($config['drop_table'],0,2);$db->execute("UPDATE reward_overrides SET config_json=? WHERE source_type='chest' AND source_key='platinum'",[json_encode($config)]);R::resetCache();
 checkRetired(R::effective('chest','platinum')['drop_table']===array_values(array_filter(R::defaults('chest','platinum')['drop_table'],R::isDropEligible(...))),'all-retired override falls back to active defaults');
 $dungeon=(string)array_key_first(R::sources('dungeon'));$config=R::defaults('dungeon',$dungeon);$config['treasure_code']=60500101;
 $db->execute("INSERT INTO reward_overrides(source_type,source_key,config_json,updated_by)VALUES('dungeon',?,?,1)",[$dungeon,json_encode($config)]);R::resetCache();
 checkRetired(R::effective('dungeon',$dungeon)['treasure_code']===R::defaults('dungeon',$dungeon)['treasure_code'],'retired dungeon override uses active default relic');
 $report=RewardPresentation::report(['treasures'=>[['treasure_code'=>60500101,'name'=>'Ancient Bracelet'],['treasure_code'=>60100001]],'items'=>[10207005=>3,10103001=>1],'item_rewards'=>[['code'=>10207005,'count'=>3],['code'=>10103001,'count'=>1]]]);
 checkRetired(count($report['treasures'])===1&&count($report['item_rewards'])===1&&!isset($report['items'][10207005]),'historical reports hide retired relics and fragment packs');
 $rewardFilter=new ReflectionMethod(\Conquer\Game\Dungeon\DungeonService::class,'visibleReward');
 $reward=$rewardFilter->invoke(null,['treasure_code'=>60500101,'fragments'=>9,'grade'=>'mythic','item_code'=>10207005,'item_quantity'=>1,'success'=>true,'awarded'=>['treasure'=>['name'=>'Ancient Bracelet'],'item_code'=>10207005]]);
 checkRetired($reward['treasure_code']===0&&$reward['fragments']===0&&$reward['item_code']===0&&$reward['item_quantity']===0&&$reward['success']&&!isset($reward['awarded']['treasure'],$reward['awarded']['item_code']),'old dungeon reward snapshots hide retired payout and preserve completion');
 echo "ALL $checks RETIRED MYTHIC RELIC CHECKS PASSED (disposable database).\n";
}catch(Throwable $e){fwrite(STDERR,'FAIL '.$e->getMessage()."\n".$e->getTraceAsString()."\n");$exit=1;}finally{if($fixture)$fixture->close();}exit($exit);
