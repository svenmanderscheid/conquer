<?php
declare(strict_types=1);
namespace Conquer\Game\Expedition;

/** Encounter rules are snapshotted on creation, keeping an ongoing raid stable. */
final class EncounterCatalog
{
    public const BOSSES=[
        'ashen_lord'=>['name'=>'Der Aschenfürst','chapter'=>1,'castle'=>1,'supplies'=>1000,'defenses'=>300,'pass'=>300,'hp'=>1800,'image'=>'ashen-lord.png','strategy'=>'Schutzanlagen mit Angriffskraft brechen, den Pass mit Verteidigung halten und gemeinsam den Feuerriesen besiegen.'],
        'frost_warden'=>['name'=>'Der Frostwächter','chapter'=>2,'castle'=>5,'supplies'=>2000,'defenses'=>600,'pass'=>1200,'hp'=>6000,'image'=>'golem.png','strategy'=>'Der vereiste Pass fordert doppelte Verteidigung. Infanterie erzielt hier 50 % mehr Beitrag. Reiter verlieren am Boss die Hälfte ihres Beitrags.'],
        'thorn_queen'=>['name'=>'Die Dornenkönigin','chapter'=>3,'castle'=>8,'supplies'=>4000,'defenses'=>1800,'pass'=>900,'hp'=>12000,'image'=>'orc.png','strategy'=>'Schützen durchbrechen die Dornenanlagen mit 50 % mehr Beitrag. Infanterie erzielt am Boss nur halben Beitrag; bereite eine gemischte Armee vor.'],
    ];
    public const DIFFICULTIES=['normal'=>['name'=>'Normal','factor'=>1,'castle'=>1],'heroic'=>['name'=>'Heroisch','factor'=>2,'castle'=>10],'legendary'=>['name'=>'Legendär','factor'=>4,'castle'=>20]];

    public static function create(string $boss,string $difficulty,int $castle,int $chapter): array
    {
        $b=self::BOSSES[$boss]??null;$d=self::DIFFICULTIES[$difficulty]??null;
        if(!$b||!$d)throw new ExpeditionException('INVALID_ENCOUNTER','Wähle einen verfügbaren Boss und Schwierigkeitsgrad.');
        if($castle<max($b['castle'],$d['castle'])||$chapter<$b['chapter'])throw new ExpeditionException('ENCOUNTER_LOCKED','Burgstufe oder Weltkapitel reicht für diese Begegnung noch nicht.');
        $factor=$d['factor'];$rewardFactor=$b['chapter']*$factor;
        $custom=\Conquer\Game\Rewards\RewardCatalog::override('expedition',$boss.'.'.$difficulty);
        return ['boss_code'=>$boss,'name'=>$b['name'],'chapter'=>$b['chapter'],'image'=>$b['image'],'difficulty'=>$difficulty,'difficulty_name'=>$d['name'],
            'supplies'=>$b['supplies']*$factor,'defenses'=>$b['defenses']*$factor,'pass'=>$b['pass']*$factor,'hp'=>$b['hp']*$factor,
            'reward'=>$custom['resources']??array_map(static fn($n)=>$n*$rewardFactor,ExpeditionRules::REWARD),'reward_drops'=>$custom['drops']??[],'reward_gems'=>$custom['gems']??0,'strategy'=>$b['strategy'],
            'loss_rule'=>'Keine dauerhaften Verluste. Die gesamte Armee kehrt zurück. Die Schwierigkeit erhöht Ziele und Beute.'];
    }

    public static function rules(array $raid): array
    {
        if(!empty($raid['encounter_rules']))return is_array($raid['encounter_rules'])?$raid['encounter_rules']:json_decode($raid['encounter_rules'],true,32,JSON_THROW_ON_ERROR);
        return self::create('ashen_lord','normal',1,1);
    }

    public static function damage(array $raid,array $troops,string $objective,array $buffs): int
    {
        $boss=self::rules($raid)['boss_code'];$total=0.0;
        foreach($troops as$code=>$count){$type=(int)(\Conquer\Game\City\TroopData::get((int)$code)['type']??1);$factor=1.0;
            if($boss==='frost_warden'){if($objective==='pass'&&$type===1)$factor=1.5;if($objective==='boss'&&$type===3)$factor=.5;}
            if($boss==='thorn_queen'){if($objective==='defenses'&&$type===2)$factor=1.5;if($objective==='boss'&&$type===1)$factor=.5;}
            $total+=ExpeditionRules::strength([(int)$code=>$count],$objective,$buffs)*$factor;
        }
        // Preserve army-composition bonuses of the original encounter.
        if($boss==='ashen_lord')return ExpeditionRules::strength($troops,$objective,$buffs);
        return max(1,(int)floor($total));
    }
}
