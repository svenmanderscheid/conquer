<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Autoloader.php';(new \Conquer\Autoloader(ROOT_DIR.'/src'))->register();
use Conquer\Game\Rewards\ItemSourceService as S;
use Conquer\Game\Treasure\TreasureData;
function sourceRuleCheck(bool $ok,string $label):void {if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
sourceRuleCheck(S::query(['item_code'=>'10105001'])===['item_code'=>10105001],'valid unowned item is accepted');
sourceRuleCheck(S::query(['treasure_code'=>'60100001'])===['treasure_code'=>60100001],'valid zero-fragment relic is accepted');
foreach([[],['item_code'=>[]],['item_code'=>true],['item_code'=>'1.5'],['item_code'=>-1],['item_code'=>2147483648],['item_code'=>10105001,'treasure_code'=>60100001],['item_code'=>10102021],['treasure_code'=>123]]as$bad){try{S::query($bad);throw new RuntimeException('Invalid lookup accepted');}catch(DomainException){sourceRuleCheck(true,'invalid source query is rejected');}}
$item=S::matchReward(['item_code'=>10105001],['item_code'=>10105001],.25,3);
sourceRuleCheck($item===['quantity'=>3,'chance'=>.25],'ordinary item preserves chance and stack size');
sourceRuleCheck(S::matchReward(['item_code'=>10105001],['item_code'=>10105001],0,3)===null,'zero-probability rules are not sources');
sourceRuleCheck(S::matchReward(['item_code'=>10105001],['item_code'=>10105001],1,0)===null,'zero-quantity rules are not sources');
sourceRuleCheck(S::matchReward(['item_code'=>10105001],['item_code'=>10207001])===null,'different items are not inferred from descriptions');
$pool=TreasureData::getCodesByGrade('normal');$relic=['treasure_code'=>60100001];
$random=S::matchReward($relic,['fragment_grade'=>'normal'],.6,2);
sourceRuleCheck(abs($random['chance']-.6/count($pool))<1e-10&&$random['quantity']===2,'chest fragments include the chance of this exact relic');
sourceRuleCheck(S::matchReward($relic,['fragment_grade'=>'epic'],1,2)===null,'a different rarity cannot award the requested relic');
$pack=S::matchReward($relic,['item_code'=>10207001],.8,2);
sourceRuleCheck($pack['chance']===.8&&$pack['pack_quantity']===2&&abs($pack['selection_chance']-1/count($pool))<1e-10&&$pack['quantity']===10,'random packs keep acquisition and subsequent selection probabilities separate');
$fixed=S::matchReward(['treasure_code'=>60300115],['item_code'=>10300003],1,3);
sourceRuleCheck($fixed['pack_quantity']===3&&$fixed['quantity']===1&&$fixed['selection_chance']===1.0,'three fixed packs retain the single-pack fragment amount for opening instructions');
sourceRuleCheck(S::matchReward($relic,['treasure_code'=>60100001],1,3)===['quantity'=>3,'chance'=>1.0],'fixed dungeon and trade fragments are guaranteed for that relic');
sourceRuleCheck(S::matchReward($relic,['treasure_code'=>60200001],1,3)===null,'fixed fragments of another relic are excluded');
echo "ALL ITEM SOURCE RULE CHECKS PASSED\n";
