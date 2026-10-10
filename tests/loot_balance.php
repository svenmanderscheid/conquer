<?php
declare(strict_types=1);
/** Shipped loot probabilities, quantity ranges and editor round-trips; no player writes. */
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
use Conquer\Game\Rewards\{RewardCatalog as R,MonsterRewardRules as M};
use Conquer\Game\Treasure\{ChestService,TreasureData};
use Conquer\Game\Map\MonsterData;
use Conquer\Admin\RewardEditor;
use Conquer\Game\Rewards\ItemSourceService;
$checks=0;
function lootCheck(bool $ok,string $message):void{global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
function lootReject(callable $fn):void{try{$fn();}catch(InvalidArgumentException){lootCheck(true,'Invalid range or probability rejected');return;}throw new RuntimeException('Invalid configuration accepted');}
function chestForm(array $config):array{
    $form=['rolls'=>$config['rolls'],'rows'=>[],'bonus_rows'=>[]];
    foreach($config['drop_table'] as $row)$form['rows'][]=['target'=>isset($row['fragment_grade'])?'fragment:'.$row['fragment_grade']:(isset($row['treasure_code'])?'treasure:'.$row['treasure_code']:(string)$row['item_code']),'quantity'=>$row['quantity'],'weight'=>$row['weight']]+(isset($row['quantity_min'])?['quantity_min'=>$row['quantity_min']]:[]);
    foreach($config['bonus_drops']??[] as $row)$form['bonus_rows'][]=['target'=>(string)$row['item_code'],'quantity'=>$row['count'],'chance'=>$row['probability']*100];
    return $form;
}
try{
    foreach(R::sources('monster') as $key=>$source){
        $d=MonsterData::get((int)$key);$level=(int)$d['level'];$f=$d['fragment_drops'];
        lootCheck(array_column($f,'fragment_grade')===['normal','rare'],'Every monster supports grey and blue fragments');
        lootCheck(abs(array_sum(array_column($f,'probability'))-(.2+.6*(min(10,$level)-1)/9))<.000002,'Monster chance scales from 20% to 80%');
        $config=R::defaults('monster',(string)$key);
        lootCheck(R::validate('monster',(string)$key,RewardEditor::formConfig('monster',$config))['fragment_drops']==$config['fragment_drops'],'Editor preserves ranges and exclusive rarity choices');
        if($d['type']==='rally'){
            $drops=array_column($d['drops'],null,'item_code');
            lootCheck($drops[10300001]['count']===1&&$drops[10300001]['probability']===.5,'Rally: one ten-AP bottle at 50%');
            lootCheck($drops[10105001]['probability']===.1&&$d['gems_drop']===['chance'=>.3,'amount'=>20*$level],'Rally: blue chest and scaled crystals');
            lootCheck($drops[M::ALLIANCE_BADGE]['count']===(int)ceil($level/2)&&$drops[M::ALLIANCE_BADGE]['probability']===.4,'Rally: one to five level-scaled alliance badges at 40%');
        }elseif($d['name']==='Treasure Goblin'){
            $drops=array_column($d['drops'],null,'item_code');
            lootCheck($drops[10105001]['probability']===.3&&$drops[10105002]['probability']===.1&&$drops[10201024]['count']===5*$level,'Goblin: chest chances and gold');
        }else lootCheck(array_column($d['drops'],null,'item_code')[10105001]['probability']===.1,'Ordinary monster blue chest chance');
    }
    foreach(R::sources('farm') as $key=>$source){
        $level=(int)$source['definition']['level'];$config=R::defaults('farm',(string)$key);$f=$config['fragment_drops'][0];
        lootCheck($f['treasure_code']===60300105&&$f['probability']===($level>=3?.8:.4)&&$f['count']===min(4,$level)&&$f['count_min']===1,'All resource families have the exact Glutblüte rule');
        lootCheck(R::validate('farm',(string)$key,RewardEditor::formConfig('farm',$config))==$config,'Farm editor preserves its quantity range');
    }
    $silver=R::defaults('chest','silver');$gold=R::defaults('chest','gold');
    foreach(['silver'=>$silver,'gold'=>$gold] as $key=>$config)lootCheck(R::validate('chest',$key,chestForm($config))==$config,'Chest editor preserves all main and bonus rewards');
    $sum=array_sum(array_column($silver['drop_table'],'weight'));$small=0;$whole=0;
    foreach($silver['drop_table'] as $row){
        if(isset($row['fragment_grade'])){lootCheck(in_array($row['fragment_grade'],['normal','rare'],true)&&$row['quantity_min']===1&&$row['quantity']===5,'Blue chest fragment quantity and rarity');$small+=$row['weight'];}
        if(isset($row['treasure_code'])){lootCheck(in_array(TreasureData::get($row['treasure_code'])['grade'],['normal','rare'],true)&&$row['quantity']===10,'Blue chest full reward is ten matching fragments');$whole+=$row['weight'];}
    }
    lootCheck($silver['rolls']===1&&$small/$sum===.3&&$whole/$sum===.05,'30% and 5% are per opening, not multiplied across draws');
    lootCheck(array_column($gold['drop_table'],'weight','fragment_grade')===['rare'=>50,'epic'=>40,'legendary'=>10]&&$gold['rolls']===1,'Violet chest rarity shares are exactly 50/40/10');
    $observed=[];$gradeCounts=['normal'=>0,'rare'=>0];$drops=M::fragments(10);foreach($drops as &$drop)$drop['probability']=.5;unset($drop);
    for($i=0;$i<5000;$i++){
        $fragments=R::rollFragments($drops);lootCheck(count($fragments)===1&&array_sum($fragments)>=1&&array_sum($fragments)<=5,'Exactly one relic and one to five fragments per successful monster roll');
        $gradeCounts[TreasureData::get((int)array_key_first($fragments))['grade']]++;$observed[array_sum($fragments)]=true;
        $result=ChestService::rollDropTable('gold');$primary=$result[0];lootCheck($primary['quantity']>=3&&$primary['quantity']<=5&&in_array($primary['fragment_grade'],['rare','epic','legendary'],true),'Actual violet rolls always grant three to five allowed fragments');
    }
    lootCheck(count($observed)===5&&abs($gradeCounts['normal']/5000-.5)<.05,'All quantities are reachable and grey/blue selection is balanced');
    $form=RewardEditor::formConfig('monster',R::defaults('monster','20200101'));$form['fragment_rows'][0]['quantity_min']=6;lootReject(fn()=>R::validate('monster','20200101',$form));
    $form['fragment_rows'][0]['quantity_min']=1;$form['fragment_rows'][0]['chance']=70;$form['fragment_rows'][1]['chance']=70;lootReject(fn()=>R::validate('monster','20200101',$form));
    $form=chestForm($gold);$form['rows'][0]['quantity_min']=6;lootReject(fn()=>R::validate('chest','gold',$form));
    $form=chestForm($gold);$form['bonus_rows'][0]['chance']=101;lootReject(fn()=>R::validate('chest','gold',$form));
    $source=ItemSourceService::matchReward(['treasure_code'=>60300105],M::farmFragments(4)[0],.8,4);
    lootCheck($source['quantity_min']===1&&$source['quantity']===4&&$source['chance']===.8,'Acquisition guide reports the actual quantity range');
    echo "ALL $checks LOOT BALANCE CHECKS PASSED\n";
}catch(Throwable $error){fwrite(STDERR,$error->getMessage()."\n".$error->getTraceAsString()."\n");exit(1);}
