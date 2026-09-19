<?php
declare(strict_types=1);
namespace Conquer\Game\Trading;

use Conquer\Db\Connection;
use Conquer\Game\City\{CityState, ResourceTick};
use Conquer\Game\World\WorldContext;

/** A transparent resource exchange with bounded, server-owned prices. */
final class MarketService
{
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
        ];
    }

    public static function state(int $playerId): array
    {
        $state = CityState::loadForPlayer($playerId);
        if (!$state) { throw new \RuntimeException('Deine Stadt wurde nicht gefunden.'); }
        $offers = self::offers();
        $names = array_column($offers, 'name', 'id');
        $history = Connection::getInstance()->query('SELECT id,offer_id,created_at FROM market_exchanges WHERE player_id=? AND world_id=? ORDER BY id DESC LIMIT 15',[$playerId,WorldContext::id()])->fetchAll();
        foreach ($history as &$entry) { $entry['name'] = $names[$entry['offer_id']] ?? 'Warentausch'; }
        $resources=array_intersect_key($state['city'],array_flip(['food','lumber','stone','gold']));
        $resources['gems']=(int)Connection::getInstance()->query('SELECT gems FROM players WHERE id=?',[$playerId])->fetchColumn();
        return ['offers'=>$offers, 'history'=>$history, 'resources'=>$resources, 'cooldown_seconds'=>0];
    }

    public static function exchange(int $playerId, string $offerId): void
    {
        WorldContext::assertActionAvailable();
        $offer = null;
        foreach (self::offers() as $candidate) { if ($candidate['id'] === $offerId) { $offer=$candidate; break; } }
        if (!$offer) { throw new \InvalidArgumentException('Dieses Handelsangebot ist nicht verfügbar.'); }
        $db = Connection::getInstance();
        $lock = 'conquer-player-' . $playerId;
        if ((int)$db->query('SELECT GET_LOCK(?,5)',[$lock])->fetchColumn() !== 1) { throw new \RuntimeException('Dein Königreich wird gerade aktualisiert. Bitte versuche es erneut.'); }
        try {
            $state=CityState::loadForPlayer($playerId);
            if (!$state) { throw new \RuntimeException('Deine Stadt wurde nicht gefunden.'); }
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
