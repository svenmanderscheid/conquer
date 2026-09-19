<?php
declare(strict_types=1);
namespace Conquer\Game;

use Conquer\Db\Connection;

/** Persistent receipts bind a browser retry to its original action and payload. */
final class Operation
{
    public static function run(int $playerId, array $body, callable $fn): array
    {
        $key=$body['operation_key']??'';
        if(!is_string($key)||!preg_match('/^[A-Za-z0-9_-]{16,64}$/D',$key))throw new \DomainException('Eine gültige Vorgangskennung ist erforderlich.');
        $action=(string)($body['action']??'');$hash=hash('sha256',json_encode($body,JSON_THROW_ON_ERROR));
        $db=Connection::getInstance();$lock='conquer-player-'.$playerId;
        if((int)$db->query('SELECT GET_LOCK(?,5)',[$lock])->fetchColumn()!==1)throw new \DomainException('Dein Königreich wird gerade aktualisiert.',503);
        try{return $db->transaction(function()use($db,$key,$playerId,$action,$hash,$fn):array{
            $old=$db->query('SELECT * FROM game_operation_receipts WHERE player_id=? AND operation_key=? FOR UPDATE',[$playerId,$key])->fetch();
            if($old){if(!hash_equals($old['payload_hash'],$hash)||$old['action']!==$action)throw new \DomainException('Diese Vorgangskennung gehört zu einer anderen Aktion.');return json_decode($old['result_json'],true,32,JSON_THROW_ON_ERROR);}
            $result=$fn();$db->execute('INSERT INTO game_operation_receipts(player_id,operation_key,action,payload_hash,result_json) VALUES(?,?,?,?,?)',[$playerId,$key,$action,$hash,json_encode($result,JSON_THROW_ON_ERROR)]);return $result;
        });}finally{$db->query('SELECT RELEASE_LOCK(?)',[$lock]);}
    }
}
