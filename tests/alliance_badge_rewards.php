<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
require __DIR__.'/Support/FeatureDatabase.php';
date_default_timezone_set('UTC');

use Conquer\Db\Connection;
use Conquer\Game\Map\MonsterData;
use Conquer\Game\Rally\MonsterRally;
use Conquer\Game\Rewards\{MonsterRewardRules,RewardCatalog};
use Conquer\Game\World\WorldContext;

$checks=0;$fixture=null;$exit=0;
function badgeCheck(bool $ok,string $message):void{global $checks;if(!$ok)throw new RuntimeException($message);$checks++;echo "PASS $message\n";}
function badgeRows(array $drops):array{return array_values(array_filter($drops,static fn(array $drop):bool=>(int)$drop['item_code']===MonsterRewardRules::ALLIANCE_BADGE));}
function badgeFields(array $drops):array{return array_map(static fn(array $drop):array=>array_intersect_key($drop,array_flip(['item_code','count','probability'])),$drops);}
try{
    $expected=[1=>2,2=>2,3=>2,4=>3,5=>3,6=>3,7=>4,8=>4,9=>4,10=>5];
    foreach($expected as $level=>$count)badgeCheck(MonsterRewardRules::allianceBadgeCount($level)===$count,'level '.$level.' awards '.$count.' alliance badges when the drop succeeds');
    $base=[['item_code'=>10201024,'count'=>15,'probability'=>1.0],['item_code'=>10300005,'count'=>5,'probability'=>.8]];
    $definition=['type'=>'rally','level'=>7];$once=MonsterRewardRules::rallyDrops($definition,$base);
    badgeCheck(array_slice($once,0,2)===$base&&MonsterRewardRules::rallyDrops($definition,$once)===$once,'badge normalization preserves other drops and does not duplicate itself');
    $duplicate=$base;for($i=0;$i<2;$i++)$duplicate[]=['item_code'=>MonsterRewardRules::ALLIANCE_BADGE,'count'=>99,'probability'=>.5];
    badgeCheck(MonsterRewardRules::rallyDrops($definition,$duplicate)===$once,'existing duplicate badge defaults become one level-scaled drop');
    foreach([0,11,30] as $level)badgeCheck(MonsterRewardRules::allianceBadgeCount($level)===0&&MonsterRewardRules::rallyDrops(['type'=>'rally','level'=>$level],$base)===$base,'unsupported rally level '.$level.' leaves the supplied drops unchanged');
    badgeCheck(MonsterRewardRules::rallyDrops(['type'=>'solo','level'=>10],$base)===$base,'solo rewards are unchanged by the badge rule');
    badgeCheck(MonsterRewardRules::apply(['type'=>'rally','level'=>11])===['type'=>'rally','level'=>11],'unsupported definition gains no new drop field');
    $active=0;$inactive=0;$solo=0;
    foreach(RewardCatalog::json('monsters')['monsters'] as $raw){
        $code=(int)$raw['code'];$def=MonsterData::definition($code);
        if($def['type']!=='rally'){if(badgeRows($def['drops']??[]))throw new RuntimeException('Solo monster gained badges: '.$code);$solo++;continue;}
        $want=[['item_code'=>MonsterRewardRules::ALLIANCE_BADGE,'count'=>$expected[(int)$def['level']],'probability'=>0.50]];
        $map=MonsterData::mapData(['monster_code'=>$code,'hp_current'=>1]);
        foreach(['definition'=>$def['drops'],'catalog'=>RewardCatalog::defaults('monster',(string)$code)['drops'],'rally'=>MonsterRally::drops($def),'detail'=>MonsterData::get($code)['drops'],'map'=>$map['definition']['drops']] as $surface=>$drops){
            if(badgeFields(badgeRows($drops))!==$want)throw new RuntimeException('Wrong badge reward for '.$code.' on '.$surface);
        }
        if(MonsterData::isActive($code))$active++;else $inactive++;
    }
    badgeCheck($active===50,'all 50 active imported and regional rally levels show the same 50% chance across definition, catalog, detail, map and rally snapshots');
    badgeCheck($inactive===12&&!MonsterData::isActive(20200601)&&!MonsterData::isActive(20201001),'the 12 dragon and Magdar templates remain inactive');
    badgeCheck($solo>0,'all '.$solo.' solo definitions remain free of alliance badges');

    $fixture=new \ConquerTests\FeatureDatabase();$db=Connection::getInstance();
    $db->execute("INSERT INTO worlds(id,name,slug,status,map_size)VALUES(2,'Badge override world','badge-override','running',256)");
    $key='20202110';$original=MonsterRally::drops(MonsterData::definition((int)$key));
    $global=['drops'=>[['item_code'=>10203022,'count'=>7,'probability'=>1.0]],'resource_reward'=>['food'=>11,'lumber'=>0,'stone'=>0,'gold'=>0],'gems_drop'=>['chance'=>0,'amount'=>0],'charms'=>['chance'=>1,'normal'=>100,'epic'=>0,'legendary'=>0]];
    $db->execute("INSERT INTO reward_overrides(source_type,source_key,config_json,updated_by)VALUES('monster',?,?,1)",[$key,json_encode($global,JSON_PRESERVE_ZERO_FRACTION)]);RewardCatalog::resetCache();WorldContext::bind(1);
    badgeCheck(MonsterRally::drops(MonsterData::definition((int)$key))===$global['drops']&&badgeRows(MonsterData::get((int)$key)['drops'])===[],'explicit global override keeps precedence and may replace the normal badge default');
    $world=$global;$world['drops']=[['item_code'=>MonsterRewardRules::ALLIANCE_BADGE,'count'=>9,'probability'=>1.0]];
    $db->execute("INSERT INTO reward_world_overrides(world_id,source_type,source_key,config_json)VALUES(2,'monster',?,?)",[$key,json_encode($world,JSON_PRESERVE_ZERO_FRACTION)]);RewardCatalog::resetCache();
    badgeCheck(RewardCatalog::effective('monster',$key,1)['drops']===$global['drops']&&RewardCatalog::effective('monster',$key,2)['drops']===$world['drops'],'world reward override wins only inside its own world');
    WorldContext::bind(2);
    badgeCheck(badgeFields(MonsterData::get((int)$key)['drops'])===$world['drops']&&MonsterRally::drops(MonsterData::definition((int)$key))===$world['drops'],'map/detail and new rally rewards honor the same explicit world badge count');
    badgeCheck(badgeRows($original)[0]['count']===5&&badgeRows($original)[0]['probability']===0.50,'already captured default drops keep their 50% chance after a later admin override');
    $db->execute("DELETE FROM reward_world_overrides WHERE world_id=2 AND source_type='monster' AND source_key=?",[$key]);$db->execute("DELETE FROM reward_overrides WHERE source_type='monster' AND source_key=?",[$key]);RewardCatalog::resetCache();
    $restored=badgeRows(MonsterRally::drops(MonsterData::definition((int)$key)))[0];
    badgeCheck($restored['count']===5&&$restored['probability']===0.50,'resetting both overrides restores five badges with a 50% chance at level ten');
    echo "ALL $checks ALLIANCE BADGE REWARD CHECKS PASSED\n";
}catch(Throwable $e){$exit=1;fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString()."\n");}
finally{if($fixture)$fixture->close();}
exit($exit);
