<?php
declare(strict_types=1);
/** Deterministic catalogue/art audit. No database or live account is used. */
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
use Conquer\Game\Treasure\{TreasureData,TreasureService};
$checks=0;
function catalogCheck(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;}
try{
 $all=TreasureData::all();$refs=json_decode(file_get_contents(__DIR__.'/fixtures/treasure_reference_manifest.json'),true,512,JSON_THROW_ON_ERROR);
 $legacy=json_decode(file_get_contents(__DIR__.'/fixtures/treasure_legacy_balance.json'),true,512,JSON_THROW_ON_ERROR);
 catalogCheck(count($all)===82,'82 catalogue entries');catalogCheck(count($refs)===77,'77 supplied original cards');catalogCheck(count($legacy)===24,'24 protected original balances');
 catalogCheck(count(array_unique(array_column($refs,'source_reference')))===77,'one mapping for every source');
 catalogCheck(count(array_unique(array_column($refs,'treasure_code')))===77,'one code per source');
 catalogCheck(count(array_unique(array_column($refs,'icon')))===77,'one unchanged image per source');
 $observed=['normal'=>0,'rare'=>0,'epic'=>0,'legendary'=>0,'mythic'=>0];$existing=0;
 foreach($refs as$ref){
  $code=$ref['treasure_code'];$def=$all[$code]??null;catalogCheck($def!==null,'reference exists '.$code);
  foreach(['source_reference','icon','grade']as$field)catalogCheck($def[$field]===$ref[$field],'source mapping '.$code.' '.$field);
  catalogCheck($def['icon_framed']===true,'original frame retained '.$code);
  catalogCheck(preg_match('/^treasures\/[a-z0-9-]+\.png$/D',$def['icon'])===1,'safe local path '.$code);
  $path=ROOT_DIR.'/assets/art/items/'.$def['icon'];catalogCheck(is_file($path),'image exists '.$code);
  catalogCheck(hash_file('sha256',$path)===$ref['sha256'],'unaltered source bytes '.$code);
  $size=getimagesize($path);catalogCheck($size!==false&&$size[2]===IMAGETYPE_PNG&&$size[0]>=128&&$size[1]>=128,'full PNG source '.$code);
  $observed[$ref['grade']]++;if($ref['existing_code'])$existing++;
 }
 catalogCheck($observed===['normal'=>14,'rare'=>15,'epic'=>26,'legendary'=>16,'mythic'=>6],'all77 inspected frame colors agree with rarity');
 catalogCheck($existing===19,'19 existing source codes reused');
 $files=glob(ROOT_DIR.'/assets/art/items/treasures/*.png');catalogCheck(count($files)===77,'exact77 original image assets');
 foreach($legacy as$protected){
  $current=$all[$protected['code']]??null;catalogCheck($current!==null,'old code preserved '.$protected['code']);
  foreach(['stats','max_level','fragments_per_level']as$field)catalogCheck($current[$field]===$protected[$field],'old balance preserved '.$protected['code'].' '.$field);
 }
 // All numeric types must have real, active consumers; none can silently become decorative metadata.
 $consumers=[
  'all_attack'=>['src/Game/March/BattleEngine.php',"effectiveMultiplier(\$buffs, \$type, 'atk')"],
  'all_defense'=>['src/Game/March/BattleEngine.php',"effectiveMultiplier(\$buffs, \$type, 'def')"],
  'all_hp'=>['src/Game/March/BattleEngine.php',"effectiveMultiplier(\$buffs, \$type, 'hp')"],
  'infantry_defense'=>['src/Game/Research/BuffEngine.php',"'infantry_defense'=>'infantry_def'"],
  'infantry_hp'=>['src/Game/Research/BuffEngine.php','effectiveMultiplier'],
  'ranged_attack'=>['src/Game/Research/BuffEngine.php',"'ranged_attack'=>'ranged_atk'"],
  'cavalry_attack'=>['src/Game/Research/BuffEngine.php',"'cavalry_attack'=>'cavalry_atk'"],
  'food_production'=>['src/Game/City/ResourceTick.php',"\$resource.'_production'"],
  'lumber_production'=>['src/Game/City/ResourceTick.php',"\$resource.'_production'"],
  'stone_production'=>['src/Game/City/ResourceTick.php',"\$resource.'_production'"],
  'gold_production'=>['src/Game/City/ResourceTick.php',"\$resource.'_production'"],
  'construction_speed'=>['src/Game/City/BuildingData.php',"\$vipBonuses['construction_speed']"],
  'research_speed'=>['src/Api/Handlers/ResearchHandler.php',"\$buffs['research_speed']"],
  'training_speed'=>['src/Game/Research/ResearchEffects.php',"\$buffs['training_speed']"],
  'march_speed'=>['src/Game/March/MarchDispatcher.php',"\$buffs['march_speed']"],
  'gathering_speed'=>['src/Game/March/GatherService.php',"\$buffs['gathering_speed']"],
  'vs_monster_attack'=>['src/Game/March/BattleEngine.php',"\$buffs['vs_monster_attack']"],
  'march_capacity'=>['src/Game/Research/ResearchEffects.php',"\$buffs['march_capacity_flat']"],
  'hospital_capacity'=>['src/Game/Hospital/HospitalService.php',"\$buffs['hospital_capacity_flat']"],
  'resource_protection'=>['src/Game/Defense/DefenseService.php',"\$buffs['resource_protection']"],
 ];
 foreach(TreasureService::ACTIVE_STATS as$key){catalogCheck(isset($consumers[$key]),'documented consumer '.$key);[$path,$token]=$consumers[$key];catalogCheck(str_contains(file_get_contents(ROOT_DIR.'/'.$path),$token),'consumer still exists '.$key);}
 $native=0;
 foreach($all as$code=>$def){
  catalogCheck(is_string($def['name_de'])&&strlen($def['name_de'])>2,'German name '.$code);
  catalogCheck(is_string($def['description'])&&strlen($def['description'])>25,'German functional description '.$code);
  catalogCheck(is_bool($def['icon_framed'])&&is_file(ROOT_DIR.'/assets/art/items/'.$def['icon']),'explicit original or native art '.$code);
  if($def['source_reference']===null){$native++;catalogCheck(!$def['icon_framed'],'native icon has no embedded original frame '.$code);}
  catalogCheck($def['fragments_per_level']>0&&$def['max_level']===10,'fragment progression '.$code);
  catalogCheck(count($def['stats'])>0&&count(array_unique(array_column($def['stats'],'type')))===count($def['stats']),'unique positive effects '.$code);
  foreach($def['stats']as$stat){
   catalogCheck(in_array($stat['type'],TreasureService::ACTIVE_STATS,true),'active key '.$code.' '.$stat['type']);
   catalogCheck($stat['base_value']>0&&$stat['per_level']>=0,'meaningful positive effect '.$code.' '.$stat['type']);
   $previous=0.0;for($level=1;$level<=10;$level++){$value=TreasureData::getStatValue($def,$level,$stat['type']);catalogCheck(is_finite($value)&&$value>0&&$value>=$previous,'valid level '.$code.'/'.$level);$previous=$value;}
  }
  catalogCheck(in_array($code,TreasureData::getCodesByGrade($def['grade']),true),'obtainable through actual rarity drop pool '.$code);
 }
 catalogCheck($native===5,'five own legacy relics retained');
 echo "ALL $checks TREASURE CATALOG CHECKS PASSED (82 relics,77 unchanged source cards,24 preserved balances).\n";
}catch(Throwable $e){fwrite(STDERR,'FAIL '.$e->getMessage()."\n");exit(1);}
