<?php
declare(strict_types=1);
namespace Conquer\Game\Trading;

use Conquer\Db\Connection;
use Conquer\Game\City\{CityState, ResourceTick};
use Conquer\Game\World\WorldContext;

/** A transparent resource exchange with bounded, server-owned prices. */
final class MarketService
{
    private const VISIBLE_OFFERS = 8;

    public static function offers(): array
    {
        return [
            ['id'=>'food_lumber','name'=>'Holz für den Aufbau','give'=>['resource'=>'food','amount'=>1000],'receive'=>['resource'=>'lumber','amount'=>1000]],
            ['id'=>'lumber_food','name'=>'Vorräte für die Expedition','give'=>['resource'=>'lumber','amount'=>1000],'receive'=>['resource'=>'food','amount'=>1000]],
            ['id'=>'food_stone','name'=>'Steine vom Steinmetz','give'=>['resource'=>'food','amount'=>1000],'receive'=>['resource'=>'stone','amount'=>1000]],
            ['id'=>'stone_food','name'=>'Ernte vom Grünland','give'=>['resource'=>'stone','amount'=>1000],'receive'=>['resource'=>'food','amount'=>1000]],
            ['id'=>'lumber_gold','name'=>'Gold des Händlers','give'=>['resource'=>'lumber','amount'=>1000],'receive'=>['resource'=>'gold','amount'=>1000]],
            ['id'=>'gems_lumber','name'=>'Bauholz des Kristallhändlers','give'=>['resource'=>'gems','amount'=>20],'receive'=>['resource'=>'lumber','amount'=>1100]],
            ['id'=>'stone_lumber','name'=>'Holz aus dem Wald','give'=>['resource'=>'stone','amount'=>1000],'receive'=>['resource'=>'lumber','amount'=>1000]],
            ['id'=>'lumber_stone','name'=>'Steinlieferung','give'=>['resource'=>'lumber','amount'=>1000],'receive'=>['resource'=>'stone','amount'=>1000]],
            ['id'=>'food_gold','name'=>'Münzen für die Ernte','give'=>['resource'=>'food','amount'=>1000],'receive'=>['resource'=>'gold','amount'=>1000]],
            ['id'=>'gold_food','name'=>'Vorräte vom Goldmarkt','give'=>['resource'=>'gold','amount'=>1000],'receive'=>['resource'=>'food','amount'=>1000]],
            ['id'=>'stone_gold','name'=>'Gold für den Steinbruch','give'=>['resource'=>'stone','amount'=>1000],'receive'=>['resource'=>'gold','amount'=>1000]],
            ['id'=>'gold_stone','name'=>'Steine der Bergkarawane','give'=>['resource'=>'gold','amount'=>1000],'receive'=>['resource'=>'stone','amount'=>1000]],
            ['id'=>'gold_lumber','name'=>'Holz vom Fernhändler','give'=>['resource'=>'gold','amount'=>1000],'receive'=>['resource'=>'lumber','amount'=>1000]],
            ['id'=>'gems_food','name'=>'Vorratskisten des Kristallhändlers','give'=>['resource'=>'gems','amount'=>20],'receive'=>['resource'=>'food','amount'=>1100]],
        ];
    }

    public static function period(?int $now=null): array
    {
        $now ??= time();
        $start=intdiv($now,86400)*86400;
        return ['rotation'=>'merchant-'.$start,'start'=>$start,'end'=>$start+86400];
    }

    /** Eight stable daily slots; every successful purchase advances only its bought slot. */
    private static function activeOffers(int $playerId,int $worldId,array $period): array
    {
        $catalog=self::offers();
        $seed=$playerId.':'.$worldId.':'.$period['rotation'].':';
        usort($catalog,static fn(array $a,array $b):int=>strcmp(hash('sha256',$seed.$a['id']),hash('sha256',$seed.$b['id'])));
        $gemOffers=array_values(array_filter($catalog,static fn(array $offer):bool=>$offer['give']['resource']==='gems'));
        $resourceOffers=array_values(array_filter($catalog,static fn(array $offer):bool=>$offer['give']['resource']!=='gems'));
        // Every fresh day includes one special crystal trade alongside seven fair resource trades.
        $catalog=array_merge(array_slice($gemOffers,0,1),array_slice($resourceOffers,0,7),array_slice($resourceOffers,7),array_slice($gemOffers,1));
        $active=array_slice($catalog,0,self::VISIBLE_OFFERS);
        $purchases=Connection::getInstance()->query(
            'SELECT offer_id FROM market_exchanges WHERE player_id=? AND world_id=? AND created_at>=? AND created_at<? ORDER BY id',
            [$playerId,$worldId,gmdate('Y-m-d H:i:s',$period['start']),gmdate('Y-m-d H:i:s',$period['end'])]
        )->fetchAll();
        $cursor=self::VISIBLE_OFFERS;
        foreach($purchases as $purchase){
            $slot=null;
            foreach($active as $index=>$offer){if($offer['id']===$purchase['offer_id']){$slot=$index;break;}}
            if($slot===null)continue;
            for($attempt=0;$attempt<count($catalog);$attempt++){
                $candidate=$catalog[$cursor++%count($catalog)];
                if(!in_array($candidate['id'],array_column($active,'id'),true)){$active[$slot]=$candidate;break;}
            }
        }
        return array_values($active);
    }

    public static function state(int $playerId): array
    {
        $state = CityState::loadForPlayer($playerId);
        if (!$state) { throw new \RuntimeException('Deine Stadt wurde nicht gefunden.'); }
        $period=self::period();
        $offers=self::activeOffers($playerId,WorldContext::id(),$period);
        $names = array_column(self::offers(), 'name', 'id');
        $history = Connection::getInstance()->query('SELECT id,offer_id,created_at FROM market_exchanges WHERE player_id=? AND world_id=? ORDER BY id DESC LIMIT 15',[$playerId,WorldContext::id()])->fetchAll();
        foreach ($history as &$entry) { $entry['name'] = $names[$entry['offer_id']] ?? 'Warentausch'; }
        $resources=array_intersect_key($state['city'],array_flip(['food','lumber','stone','gold']));
        $resources['gems']=(int)Connection::getInstance()->query('SELECT gems FROM players WHERE id=?',[$playerId])->fetchColumn();
        return ['offers'=>$offers,'history'=>$history,'resources'=>$resources,'cooldown_seconds'=>0,
            'server_time'=>gmdate('Y-m-d\TH:i:s\Z'),'refresh_at'=>gmdate('Y-m-d\TH:i:s\Z',$period['end']),'rotation'=>$period['rotation']];
    }

    public static function exchange(int $playerId, string $offerId): void
    {
        WorldContext::assertActionAvailable();
        $db = Connection::getInstance();
        $lock = 'conquer-player-' . $playerId;
        if ((int)$db->query('SELECT GET_LOCK(?,5)',[$lock])->fetchColumn() !== 1) { throw new \RuntimeException('Dein Königreich wird gerade aktualisiert. Bitte versuche es erneut.'); }
        try {
            $state=CityState::loadForPlayer($playerId);
            if (!$state) { throw new \RuntimeException('Deine Stadt wurde nicht gefunden.'); }
            $offer=null;$period=self::period();
            foreach(self::activeOffers($playerId,WorldContext::id(),$period) as $candidate){if($candidate['id']===$offerId){$offer=$candidate;break;}}
            if(!$offer){throw new \DomainException('Dieses Handelsangebot wurde bereits ersetzt. Bitte lade den Markt neu.',409);}
            $give=$offer['give']; $receive=$offer['receive'];
            $db->transaction(static function(Connection $db) use($state,$give,$receive,$playerId,$offerId): void {
                ResourceTick::persist($state['city'],$state['buildings']);
                // Resource names are exclusively selected from the fixed offers above.
                $changed=$give['resource']==='gems'
                    ?$db->execute('UPDATE players SET gems=gems-? WHERE id=? AND gems>=?',[$give['amount'],$playerId,$give['amount']])
                    :$db->execute('UPDATE cities SET '.$give['resource'].'='.$give['resource'].'-? WHERE id=? AND '.$give['resource'].'>=?',[$give['amount'],$state['city']['id'],$give['amount']]);
                if ($changed !== 1) { throw new \RuntimeException('Die Ressourcen haben sich verändert. Bitte lade den Markt neu.'); }
                if($receive['resource']==='gems')$db->execute('UPDATE players SET gems=gems+? WHERE id=?',[$receive['amount'],$playerId]);
                else $db->execute('UPDATE cities SET '.$receive['resource'].'='.$receive['resource'].'+? WHERE id=?',[$receive['amount'],$state['city']['id']]);
                $db->execute('INSERT INTO market_exchanges (player_id,world_id,offer_id) VALUES (?,?,?)',[$playerId,WorldContext::id(),$offerId]);
            });
        } finally { $db->query('SELECT RELEASE_LOCK(?)',[$lock]); }
    }
}
