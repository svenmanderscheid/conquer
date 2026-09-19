<?php
declare(strict_types=1);

namespace Conquer\Game\Dungeon;

use Conquer\Game\City\TroopData;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;

final class DungeonRules
{
    public const ROLES = ['attack','defense','gather','hunter'];
    public const STANCES = ['cautious','balanced','risky'];
    public const CHOICES = ['explore','skip'];
    public const DECISION_SECONDS = 1800;
    private const MIN_TROOPS = 10;
    private const MAX_ROUNDS = 18;
    private const GUIDANCE_SEEDS = [1, 42, 777, 20260912, 2147483647];

    public static function catalog(): array
    {
        static $catalog;
        if ($catalog !== null) return self::withRewards($catalog);
        $path = dirname(__DIR__, 3) . '/data/dungeons.json';
        $decoded = json_decode((string) file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
        if (!isset($decoded['dungeons'], $decoded['difficulties']) || count($decoded['dungeons']) !== 6) {
            throw new RuntimeException('Der Dungeon-Katalog ist unvollständig.');
        }
        $catalog = $decoded;
        return self::withRewards($catalog);
    }

    private static function withRewards(array $catalog): array
    {
        foreach ($catalog['dungeons'] as &$d) {
            $cfg=\Conquer\Game\Rewards\RewardCatalog::override('dungeon',$d['dungeon_code']);
            if ($cfg===null) continue;
            $d['reward_config']=$cfg;
            $d['treasure_code']=$cfg['treasure_code'];
            $d['reward_grade']=\Conquer\Game\Treasure\TreasureData::get($cfg['treasure_code'])['grade'];
            $d['item_codes']=array_column($cfg['items'],'item_code');
        }
        unset($d);
        return $catalog;
    }

    /** Three consecutive entries, anchored to the stable UTC Monday week. */
    public static function weeklyRotation(?DateTimeImmutable $at = null): array
    {
        $at = ($at ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'));
        $monday = $at->modify('monday this week')->setTime(0, 0);
        $week = intdiv($monday->getTimestamp(), 604800);
        $all = self::catalog()['dungeons'];
        $available = [];
        for ($i=0; $i<3; $i++) $available[] = $all[($week * 3 + $i) % count($all)];
        return ['week_start'=>$monday->format(DATE_ATOM),'week_end'=>$monday->modify('+7 days')->format(DATE_ATOM),'available'=>$available];
    }

    public static function preview(?DateTimeImmutable $at = null): array
    {
        $at = ($at ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'));
        return self::weeklyRotation($at->modify('monday next week'));
    }

    public static function validateParty(array $party): bool
    {
        if (count($party) < 2 || count($party) > 4) throw new InvalidArgumentException('Eine Gruppe braucht 2 bis 4 Mitglieder.');
        $playerIds=[];
        foreach ($party as $member) {
            if (!is_array($member)) throw new InvalidArgumentException('Jedes Gruppenmitglied braucht einen gültigen Snapshot.');
            $role=(string)($member['role']??'');
            if (!in_array($role,self::ROLES,true)) throw new InvalidArgumentException('Die gewählte Spezialisierung ist ungültig.');
            if (!is_int($member['specialty_points']??null)||(int)$member['specialty_points']<1) throw new InvalidArgumentException('Die Rolle benötigt mindestens einen echten Talentpunkt im gewählten Zweig.');
            if (!is_array($member['troops']??null)||$member['troops']===[]) throw new InvalidArgumentException('Jedes Mitglied muss mindestens 10 Truppen fest binden.');
            $troopTotal=0;
            foreach($member['troops'] as $code=>$count){
                if(!is_int($count)||$count<1||!preg_match('/^[1-9][0-9]*$/D',(string)$code)||TroopData::get((int)$code)===null)throw new InvalidArgumentException('Der Truppen-Snapshot enthält ungültige Werte.');
                $troopTotal+=$count;
            }
            if($troopTotal<self::MIN_TROOPS)throw new InvalidArgumentException('Jedes Mitglied muss mindestens 10 Truppen fest binden.');
            foreach (['attack','defense','hp'] as $stat) {
                $value=$member[$stat]??null;
                if ((!is_int($value)&&!is_float($value))||!is_finite((float)$value)||(float)$value<=0) throw new InvalidArgumentException('Der Kampf-Snapshot ist unvollständig.');
            }
            if(array_key_exists('player_id',$member)){
                if(!is_int($member['player_id'])||$member['player_id']<1||isset($playerIds[$member['player_id']]))throw new InvalidArgumentException('Spieler dürfen in einer Gruppe nur einmal vorkommen.');
                $playerIds[$member['player_id']]=true;
            }
        }
        return true;
    }

    public static function resolveSideChoice(array $votes,string $stance,int $deadline,int $now): ?string
    {
        if (!in_array($stance,self::STANCES,true)) throw new InvalidArgumentException('Unbekannte Haltung.');
        $counts=['explore'=>0,'skip'=>0];
        foreach ($votes as $vote) if (is_string($vote)&&isset($counts[$vote])) $counts[$vote]++;
        if ($now<$deadline) return null;
        if ($counts['explore']>$counts['skip']) return 'explore';
        if ($counts['skip']>$counts['explore']) return 'skip';
        return $stance==='cautious'?'skip':'explore';
    }

    public static function simulate(array $definition,array $party,string $difficulty,string $stance,int $seed,?string $sideChoice): array
    {
        self::validateParty($party);
        $diff=self::catalog()['difficulties'][$difficulty]??null;
        if (!$diff) throw new InvalidArgumentException('Unbekannter Schwierigkeitsgrad.');
        if (!in_array($stance,self::STANCES,true)) throw new InvalidArgumentException('Unbekannte Haltung.');
        if ($sideChoice!==null&&!in_array($sideChoice,self::CHOICES,true)) throw new InvalidArgumentException('Unbekannte Seitenkammer-Entscheidung.');
        $attack=$defense=$hp=0.0;$gather=$hunter=0.0;
        $effects=is_array($definition['army_profile']['type_effects']??null)?$definition['army_profile']['type_effects']:null;
        foreach($party as$m){
            if($effects!==null&&is_array($m['type_stats']??null)&&$m['type_stats']!==[]){
                foreach($m['type_stats']as$type=>$stats){
                    $effect=is_array($effects[(string)$type]??null)?$effects[(string)$type]:[];
                    $attack+=(float)($stats['attack']??0)*(float)($effect['attack']??1)/10;
                    $defense+=(float)($stats['defense']??0)*(float)($effect['defense']??1)/10;
                    $hp+=(float)($stats['hp']??0)*(float)($effect['hp']??1)/100;
                }
            }else{
                $attack+=(float)$m['attack']/10;$defense+=(float)$m['defense']/10;$hp+=(float)$m['hp']/100;
            }
            if($m['role']==='gather')$gather+=(float)($m['gather_bonus']??0);if($m['role']==='hunter')$hunter+=(float)($m['hunter_bonus']??0);
        }
        $maxHp=$hp;$rng=$seed & 0x7fffffff;$rounds=[];$success=true;
        // Stored legacy runs have no dungeon factor and therefore keep their original balance.
        $dungeonFactor=max(1.0,(float)($definition['enemy_factor']??1.0));
        $fight=function(array$enemy,string$type)use(&$hp,$maxHp,$attack,$defense,$diff,$dungeonFactor,&$rng,&$rounds,&$success):void{
            $enemyFactor=(float)$diff['enemy_factor']*$dungeonFactor;
            $enemyHp=(float)$enemy['hp']*$enemyFactor;$enemyAttack=(float)$enemy['attack']*$enemyFactor;$enemyDefense=(float)$enemy['defense']*$enemyFactor;
            for($round=1;$round<=self::MAX_ROUNDS&&$enemyHp>0&&$hp>0;$round++){
                $rng=(int)(($rng*1103515245+12345)&0x7fffffff);$jitter=.95+($rng/0x7fffffff)*.10;$enrage=$round>12?1+($round-12)*.12:1.0;
                $dealt=max(1,(int)round($attack*100/(100+$enemyDefense)*$jitter));$taken=max(1,(int)round($enemyAttack*100/(100+$defense)*(2-$jitter)*$enrage));
                $enemyHp=max(0,$enemyHp-$dealt);$hp=max(0,$hp-$taken);
                $rounds[]=['encounter'=>$type,'name'=>$enemy['name'],'round'=>$round,'damage_dealt'=>$dealt,'damage_taken'=>$taken,'enemy_hp'=> (int)ceil($enemyHp),'party_hp'=>(int)ceil($hp)];
            }
            if($enemyHp>0||$hp<=0){$success=false;return;}$hp=min($maxHp,$hp+($maxHp-$hp)*.12);
        };
        $fight($definition['encounters'][0],'encounter');
        if(!$success)return self::result(false,$rounds,$hp,$definition,$difficulty,$gather,$hunter,$sideChoice,false);
        if($sideChoice===null)return self::result(false,$rounds,$hp,$definition,$difficulty,$gather,$hunter,null,true);
        $fight($definition['encounters'][1],'encounter');
        if($success&&$sideChoice==='explore')$fight($definition['optional_encounter'],'side');
        if($success)$fight($definition['boss'],'boss');
        return self::result($success,$rounds,$hp,$definition,$difficulty,$gather,$hunter,$sideChoice,false);
    }

    /** Advisory reference army: two T1 members with one real point in attack/defense. */
    public static function guidance(array $definition): array
    {
        static $cache=[];
        $key=md5((string)json_encode($definition));
        if(isset($cache[$key]))return$cache[$key];
        $profile=$definition['army_profile']??null;$legacy=!is_array($profile);
        if($legacy)$profile=['profile_name'=>'Ausgewogene Formation','reason'=>'Dieser ältere Lauf nutzt die ursprünglichen Kampfregeln ohne besondere Vorteile für einen Truppentyp.','mix'=>[['type'=>1,'percent'=>40],['type'=>2,'percent'=>35],['type'=>3,'percent'=>25]]];
        $requirements=[];
        foreach(['normal','hard']as$difficulty)foreach(['skip','explore']as$choice){
            $low=20;$high=20;
            while($high<100000&&!self::referenceSafe($definition,$profile['mix'],$difficulty,$choice,$high))$high*=2;
            $high=min(100000,$high);
            while($low<$high){$mid=intdiv($low+$high,2);if(self::referenceSafe($definition,$profile['mix'],$difficulty,$choice,$mid))$high=$mid;else$low=$mid+1;}
            $requirements[$difficulty][$choice]=$low;
        }
        foreach(['normal','hard']as$difficulty)$requirements[$difficulty]['explore']=max($requirements[$difficulty]['explore'],$requirements[$difficulty]['skip']);
        return$cache[$key]=['profile_name'=>(string)$profile['profile_name'],'reason'=>(string)$profile['reason'],
            'mix'=>array_map(static fn(array$m):array=>['type'=>(int)$m['type'],'percent'=>(int)$m['percent']],$profile['mix']),
            'requirements'=>$requirements,'basis'=>'T1 · gesamte Gruppe · Beispiel: Angriff + Verteidigung, je 1 Talentpunkt · Andere Klassenkombinationen möglich · Schätzung mit Sicherheitsreserve'];
    }

    private static function referenceSafe(array$d,array$mix,string$difficulty,string$choice,int$total): bool
    {
        $party=self::referenceParty($mix,$total);$maxHp=self::effectivePartyHp($d,$party);
        try{self::validateParty($party);}catch(InvalidArgumentException){return false;}
        foreach(self::GUIDANCE_SEEDS as$seed){$result=self::simulate($d,$party,$difficulty,'balanced',$seed,$choice);if(!$result['success']||$result['hp_remaining']<$maxHp*.35)return false;}
        return true;
    }

    public static function effectivePartyHp(array$definition,array$party): float
    {
        $effects=is_array($definition['army_profile']['type_effects']??null)?$definition['army_profile']['type_effects']:null;$hp=0.0;
        foreach($party as$m){if($effects!==null&&is_array($m['type_stats']??null)&&$m['type_stats']!==[])foreach($m['type_stats']as$type=>$stats)$hp+=(float)($stats['hp']??0)*(float)($effects[(string)$type]['hp']??1)/100;else$hp+=(float)($m['hp']??0)/100;}
        return$hp;
    }

    private static function referenceParty(array$mix,int$total): array
    {
        $troops=TroopData::t1();$byType=[];foreach($troops as$t)$byType[(int)$t['type']]=$t;
        $counts=[];$assigned=0;
        foreach($mix as$i=>$row){$count=$i===array_key_last($mix)?$total-$assigned:(int)floor($total*(int)$row['percent']/100);$counts[(int)$row['type']]=$count;$assigned+=$count;}
        $members=[];
        foreach(['attack','defense']as$index=>$role){$typeStats=[];$army=[];$attack=$defense=$hp=0.0;
            foreach($counts as$type=>$count){$share=intdiv($count,2)+($index===0?$count%2:0);if($share<1)continue;$t=$byType[$type];$army[(string)$t['code']]=$share;
                $a=(float)$t['attack']*$share;$de=(float)$t['defense']*$share;$h=(float)$t['hp']*$share;
                if($role==='attack')$a*=1.02;if($role==='defense'){$de*=1.02;$h*=1.01;}
                $typeStats[(string)$type]=['attack'=>$a,'defense'=>$de,'hp'=>$h];$attack+=$a;$defense+=$de;$hp+=$h;}
            $members[]=['role'=>$role,'specialty_points'=>1,'troops'=>$army,'attack'=>$attack,'defense'=>$defense,'hp'=>$hp,'type_stats'=>$typeStats];
        }
        return$members;
    }

    private static function result(bool$success,array$rounds,float$hp,array$d,string$difficulty,float$gather,float$hunter,?string$choice,bool$decision):array
    {
        $rewardFactor=(float)self::catalog()['difficulties'][$difficulty]['reward_factor'];$loot=1+min(.5,max(0,$gather));$explore=$choice==='explore'?1.25:1;
        $sideDuration=$choice==='explore'?(float)(self::catalog()['explore_duration_factor']??1.25):1.0;
        $duration=(int)ceil((int)$d['base_duration']*$sideDuration*(1-min(.35,max(0,$hunter))));
        $completed=0;
        foreach($rounds as$row)if(($row['enemy_hp']??1)===0&&($row['party_hp']??0)>0)$completed++;
        $total=$choice==='explore'?4:3;
        return ['success'=>$success,'rounds'=>$rounds,'hp_remaining'=>(int)ceil($hp),'duration_seconds'=>$duration,'decision_required'=>$decision,'decision_seconds'=>self::DECISION_SECONDS,'choice'=>$choice,
            'encounters_done'=>$completed,'encounters_total'=>$total,
            'base_reward'=>['grade'=>$d['reward_grade'],'fragment_grade'=>$d['reward_grade'],'treasure_code'=>(int)$d['treasure_code'],'item_codes'=>array_map('intval',$d['item_codes']),'item_code'=>(int)($d['item_codes'][0]??0)] + (isset($d['reward_config'])?['item_weights'=>$d['reward_config']['items'],'item_quantity'=>$d['reward_config']['item_quantity']]:[]),
            'fragments'=>$success?(int)ceil(($d['reward_config']['fragments']??3)*$rewardFactor*$loot*$explore):0,'item_chance'=>$success?min(isset($d['reward_config'])?1:.85,($d['reward_config']['item_chance']??.18)*$rewardFactor*$loot*$explore):0.0];
    }
}
