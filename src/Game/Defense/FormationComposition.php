<?php
declare(strict_types=1);
namespace Conquer\Game\Defense;

use Conquer\Game\Locale;

/** Percentage templates do not reserve an army. Server dispatch retains the final checks. */
final class FormationComposition
{
    public static function clean(mixed $input, int $capacity): array
    {
        if (!is_array($input) || !is_int($input['total'] ?? null) || $input['total'] < 1 || $input['total'] > $capacity) {
            throw new \DomainException(Locale::t('formation.invalid_total', ['capacity'=>$capacity]));
        }
        $percentages=$input['percentages'] ?? null;
        if (!is_array($percentages) || count($percentages)!==3) throw new \DomainException(Locale::t('formation.invalid_percentages'));
        foreach ([1,2,3] as $type) {
            if (!is_int($percentages[$type] ?? null) || $percentages[$type]<0 || $percentages[$type]>100) throw new \DomainException(Locale::t('formation.invalid_percentages'));
        }
        if (array_sum($percentages)!==100) throw new \DomainException(Locale::t('formation.invalid_percentages'));
        return ['percentages'=>[1=>$percentages[1],2=>$percentages[2],3=>$percentages[3]],'total'=>$input['total']];
    }

    /** Highest tiers first; shortages reduce the whole army, never replace an excluded type. */
    public static function allocate(array $definitions, array $stocks, int $capacity, array $composition): array
    {
        $percentages=$composition['percentages'];$available=[1=>0,2=>0,3=>0];
        foreach ($definitions as $troop) $available[(int)$troop['type']]+=max(0,(int)($stocks[$troop['code']] ?? 0));
        $total=min(max(0,$capacity),(int)$composition['total']);
        foreach ([1,2,3] as $type) if ($percentages[$type]>0) $total=min($total,(int)floor($available[$type]*100/$percentages[$type]));
        $quotas=[];
        foreach ([1,2,3] as $type) $quotas[$type]=intdiv($total*$percentages[$type],100);
        $left=$total-array_sum($quotas);$order=[1,2,3];
        usort($order,static fn($a,$b)=>($total*$percentages[$b]%100)<=>($total*$percentages[$a]%100) ?: $a<=>$b);
        foreach ($order as $type) if ($left>0 && $percentages[$type]>0) {$quotas[$type]++;$left--;}
        usort($definitions,static fn($a,$b)=>(int)$b['tier']<=>(int)$a['tier'] ?: (int)$a['code']<=>(int)$b['code']);
        $counts=[];
        foreach ($definitions as $troop) {
            $type=(int)$troop['type'];$code=(int)$troop['code'];$count=min($quotas[$type],max(0,(int)($stocks[$code] ?? 0)));
            if ($count>0) $counts[$code]=$count;
            $quotas[$type]-=$count;
        }
        ksort($counts);return $counts;
    }
}
