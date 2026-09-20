<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';
(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();

use Conquer\Game\Inventory\InventoryService;
use Conquer\Game\Map\MonsterData;
use Conquer\Game\Rewards\MonsterRewardRules;

$checks=0;
function rewardCheck(bool $ok,string $message):void{global $checks;if(!$ok)throw new RuntimeException($message);$checks++;echo "PASS $message\n";}
function drop(array $monster,int $code):?array{foreach($monster['drops'] as $entry)if((int)$entry['item_code']===$code)return $entry;return null;}

foreach(['Orc'=>[20200101,10201001],'Skeleton'=>[20200201,10201008],'Golem'=>[20200301,10201016]] as $name=>[$first,$resource]){
    for($level=1;$level<=10;$level++){
        $monster=MonsterData::definition($first+$level-1);
        rewardCheck($monster['name']===$name&&(int)$monster['level']===$level,$name.' level '.$level.' resolves exactly');
        rewardCheck(drop($monster,$resource)['count']===5*$level&&drop($monster,$resource)['probability']===1.0,$name.' level '.$level.' grants 5,000 resource per level');
        rewardCheck(drop($monster,10203022)['count']===$level&&drop($monster,10203022)['probability']===1.0,$name.' level '.$level.' grants training speedups');
        rewardCheck(drop($monster,10203030)['count']===$level&&drop($monster,10203030)['probability']===1.0,$name.' level '.$level.' grants healing speedups');
        rewardCheck(drop($monster,10105001)['count']===1&&drop($monster,10105001)['probability']===0.30,$name.' level '.$level.' grants the blue chest chance');
        rewardCheck($monster['gems_drop']===['chance'=>0.25,'amount'=>10*$level],$name.' level '.$level.' grants scaled crystals');
    }
}

foreach([20200401,20200410] as $code){
    $monster=MonsterData::definition($code);$level=(int)$monster['level'];
    rewardCheck(count($monster['drops'])===3&&drop($monster,10203022)['count']===$level&&drop($monster,10203030)['count']===$level,'Treasure Goblin level '.$level.' uses the shared solo rewards');
    rewardCheck($monster['gems_drop']===['chance'=>0.25,'amount'=>10*$level],'Treasure Goblin level '.$level.' grants scaled crystals');
}

foreach([20200501,20200510,20202101,20202210,20202305,20202410] as $code){
    $monster=MonsterData::definition($code);$level=(int)$monster['level'];
    rewardCheck(($monster['reward_family']??null)==='deathkar','Deathkar successor '.$code.' keeps its reward family');
    rewardCheck(drop($monster,10201024)['count']===5*$level&&drop($monster,10201024)['probability']===1.0,'Deathkar successor '.$code.' grants 5,000 gold per level');
    rewardCheck(drop($monster,10203022)['count']===$level&&drop($monster,10203030)['count']===$level,'Deathkar successor '.$code.' grants both speedups');
    rewardCheck(drop($monster,10105001)['probability']===0.30&&drop($monster,10300001)['count']===2&&drop($monster,10300001)['probability']===0.35,'Deathkar successor '.$code.' grants chest and AP chances');
    rewardCheck(drop($monster,MonsterRewardRules::ALLIANCE_COIN)['count']===5&&drop($monster,MonsterRewardRules::ALLIANCE_COIN)['probability']===0.80,'Deathkar successor '.$code.' grants alliance coins');
    rewardCheck(drop($monster,10106001)['probability']===0.25&&$monster['gems_drop']===['chance'=>0.25,'amount'=>20*$level],'Deathkar successor '.$code.' grants VIP points and scaled crystals');
}

rewardCheck(InventoryService::getItemDef(MonsterRewardRules::ALLIANCE_COIN)['name_de']==='Allianzmünze','Alliance coin is a real inventory item');
echo "ALL $checks MONSTER REWARD BALANCE CHECKS PASSED\n";
