<?php
declare(strict_types=1);
namespace Conquer\Game\March;

use Conquer\Db\Connection;
use Conquer\Game\Map\MonsterData;
use Conquer\Game\Player\TalentEffects;
use Conquer\Game\Research\{BuffEngine,ResearchEffects};
use Conquer\Game\World\{WorldContext,LandAccessPolicy};

/** Advisory only: never reserves troops, rolls loot or settles a battle. */
final class BattlePreview
{
    public static function calculate(int $playerId, array $input): array
    {
        $world = WorldContext::id();
        $db = Connection::getInstance();
        $city = $db->query('SELECT id FROM cities WHERE player_id=? AND world_id=?', [$playerId,$world])->fetch();
        if (!$city) throw new \DomainException('Keine Stadt in dieser Welt.', 404);
        $kind = $input['kind'] ?? '';
        if (!in_array($kind, ['monsters','monster-rally','players','rally'], true)) {
            throw new \DomainException('Diese Kampfart wird vom Rechner nicht unterstützt.', 422);
        }
        foreach (['target_x','target_y'] as $key) {
            if (!isset($input[$key]) || !is_int($input[$key]) || $input[$key]<0 || $input[$key]>1023) {
                throw new \DomainException('Ungültige Zielkoordinaten.', 422);
            }
        }
        $x=$input['target_x']; $y=$input['target_y'];
        LandAccessPolicy::assertTargetOpen($world,$x,$y);
        $monsterMode = str_starts_with($kind,'monster');
        // Match live combat: PvE uses the origin bonuses; city PvP uses the target territory.
        $buffs = BuffEngine::getBuffs($playerId,$world,$monsterMode?null:$x,$monsterMode?null:$y);
        $troops = MarchArmy::clean($input['troops']??null,ResearchEffects::limits($buffs)['march_capacity']);
        $stock = $db->query('SELECT troop_code,count FROM city_troops WHERE city_id=?',[$city['id']])->fetchAll(\PDO::FETCH_KEY_PAIR);
        foreach ($troops as $code=>$count) {
            if ($count>(int)($stock[$code]??0)) throw new \DomainException('Die ausgewählten Truppen sind nicht mehr verfügbar.',422);
        }
        if ($monsterMode) {
            $id=$input['target_id']??null;
            if (!is_int($id)||$id<1) throw new \DomainException('Ungültiges Monsterziel.',422);
            $monster=$db->query('SELECT * FROM field_monsters WHERE id=? AND world_id=? AND coord_x=? AND coord_y=? AND hp_current>0 AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP())',[$id,$world,$x,$y])->fetch();
            if (!$monster||!MonsterData::isActive((int)$monster['monster_code'])) throw new \DomainException('Dieses Monster ist nicht mehr verfügbar.',404);
            $definition=MonsterData::get((int)$monster['monster_code']);
            $rally=($definition['type']??'solo')==='rally'||($monster['monster_type']??'solo')==='rally';
            if ($rally!==($kind==='monster-rally')) throw new \DomainException('Die Kampfart passt nicht zu diesem Monster.',422);
            $result=$rally
                ? BattleEngine::resolveMonsterArmies([['troops'=>$troops,'buffs'=>$buffs]],$monster,$definition)
                : BattleEngine::resolveMonster($troops,$monster,$definition,$buffs);
            return ['kind'=>$kind,'calculated_at'=>time(),'outcome'=>$result['outcome'],
                'attacker'=>self::totals(['survivors'=>$result['attacker_survivors'],'wounded'=>$result['attacker_losses'],'dead'=>[]]),
                'troops'=>$result['report']['troops'],'army_power'=>$result['report']['army_power'],
                'required_power'=>$result['report']['required_power'],'monster_hp_before'=>(int)$monster['hp_current'],
                'monster_hp_after'=>$result['new_monster_hp'],
                'assumptions'=>$rally?'Nur dein Beitrag, ohne weitere Rally-Mitglieder.':'Aktuelle Monster-LP und deine derzeitigen Boni.',
                'notice'=>'Bei Ankunft können sich Ziel und Boni geändert haben. Verwundete benötigen freie Hospitalbetten.'];
        }
        // Opposing troops are deliberately user supplied. Never reveal an unscouted garrison.
        $defenders=MarchArmy::clean($input['defender_troops']??null,500000);
        $wall=$input['wall_bonus']??0;
        $enemyBonus=$input['defender_bonus']??0;
        foreach ([$wall,$enemyBonus] as $value) {
            if ((!is_int($value)&&!is_float($value))||!is_finite((float)$value)||$value<0||$value>1000) throw new \DomainException('Boni müssen zwischen 0 und 1.000 Prozent liegen.',422);
        }
        $buffs=TalentEffects::combat(ResearchEffects::armyBuffs($buffs,$troops,$kind==='rally'),'pvp',$kind==='rally');
        $enemyBuffs=['troops_atk'=>$enemyBonus/100,'troops_def'=>$enemyBonus/100,'troops_hp'=>$enemyBonus/100];
        $attack=0.0; $defense=0.0;
        foreach ($troops as $code=>$count) $attack+=PvpRules::strength($code,$count,$buffs);
        foreach ($defenders as $code=>$count) $defense+=PvpRules::strength($code,$count,$enemyBuffs);
        $defense*=1.1*(1+$wall/100);
        $won=PvpRules::attackerWins($attack,$defense);
        return ['kind'=>$kind,'calculated_at'=>time(),'outcome'=>$won?'attacker_wins':'defender_wins',
            'attacker_score'=>(int)round($attack),'defender_score'=>(int)round($defense),
            'attacker'=>self::totals(PvpRules::losses($troops,$won?.1:.3)),
            'defender'=>self::totals(PvpRules::losses($defenders,$won?.3:.1)),
            'assumptions'=>'Beispielrechnung mit deinen eingegebenen Gegnertruppen, einem gemeinsamen Gegnerbonus und Mauerbonus. Keine echten Gegnerdaten.',
            'notice'=>'Weitere Rally-Mitglieder, Verstärkungen und spezielle Verteidigertalente sind nicht enthalten. Verwundete benötigen freie Hospitalbetten.'];
    }

    private static function totals(array $loss): array
    {
        return array_map('array_sum',$loss);
    }
}
