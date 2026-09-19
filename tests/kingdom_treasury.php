<?php
declare(strict_types=1);
/** Real HTTP bank transfer checks with two temporary accounts and exact-ID cleanup. */
if(PHP_SAPI!=='cli') { http_response_code(403);exit; }
define('ROOT_DIR',dirname(__DIR__));require ROOT_DIR.'/src/Bootstrap.php';\Conquer\Bootstrap::init(ROOT_DIR);
$base=rtrim($argv[1]??'http://localhost/conquer','/');
if(!in_array(parse_url($base,PHP_URL_HOST),['localhost','127.0.0.1'],true)) { exit("Local test hosts only.\n"); }
$db=\Conquer\Db\Connection::getInstance();$players=[];$aid=0;$failed=false;
function checkBank(bool $ok,string $label): void { if(!$ok)throw new RuntimeException($label);echo "PASS $label\n"; }
function bankCall(int $actor,array $payload,int $status=200): array {
    global $base,$players;
    $p=$players[$actor];$ch=curl_init($base.'/api/kingdom/action');
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_HTTPHEADER=>['Content-Type: application/json','Cookie: conquer_session='.$p['token'],'X-CSRF-Token: '.$p['csrf']],CURLOPT_TIMEOUT=>20]);
    $raw=curl_exec($ch);$actual=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);$body=json_decode((string)$raw,true);
    checkBank($actual===$status && is_array($body),$payload['action'].' HTTP '.$status.($actual===$status?'':': '.$raw));return $body['data']??[];
}
function bankBalance(): int { global $db,$aid;return (int)$db->query('SELECT gold FROM alliance_treasury WHERE alliance_id=?',[$aid])->fetchColumn(); }
function cityGold(int $actor): int { global $db,$players;return (int)$db->query('SELECT gold FROM cities WHERE id=?',[$players[$actor]['city']])->fetchColumn(); }
try {
    for($i=0;$i<2;$i++) {
        $name='BankCheck'.bin2hex(random_bytes(5));
        $db->execute('INSERT INTO players(username,email,password_hash) VALUES(?,?,?)',[$name,$name.'@tests.invalid',password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT)]);
        $pid=$db->lastInsertId();$players[$i]=['id'=>$pid,'city'=>0,'token'=>bin2hex(random_bytes(32)),'csrf'=>bin2hex(random_bytes(32))];
        \Conquer\Auth\OAuth::createDefaultCity($db,$pid,$name);
        $players[$i]['city']=(int)$db->query('SELECT id FROM cities WHERE player_id=?',[$pid])->fetchColumn();
        $db->execute("INSERT INTO sessions(player_id,token,csrf_token,ip_address,user_agent,expires_at) VALUES(?,?,?,'127.0.0.1','kingdom treasury regression',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 10 MINUTE))",[$pid,$players[$i]['token'],$players[$i]['csrf']]);
    }
    $created=bankCall(0,['action'=>'alliance.create','name'=>'Bank '.bin2hex(random_bytes(5)),'tag'=>strtoupper(bin2hex(random_bytes(3))),'description'=>'Temporary treasury test']);$aid=(int)$created['result']['alliance_id'];
    bankCall(1,['action'=>'alliance.join','alliance_id'=>$aid]);
    bankCall(0,['action'=>'alliance.donate','resource'=>'gold','amount'=>1000]);checkBank(bankBalance()===1000,'donation is spendable treasury balance');
    $request=['action'=>'alliance.withdraw','resource'=>'gold','amount'=>250,'request_id'=>bin2hex(random_bytes(16))];
    bankCall(1,$request,403);checkBank(bankBalance()===1000 && cityGold(1)===5000,'ordinary member cannot take treasury resources');
    $before=cityGold(0);$result=bankCall(0,$request);
    checkBank(cityGold(0)===$before+250 && bankBalance()===750 && $result['result']['treasury_balance']===750,'withdrawal moves exact amount to authenticated leader and reports remaining bank');
    checkBank(!$result['result']['duplicate'],'first withdrawal creates one receipt');
    $again=bankCall(0,$request);checkBank($again['result']['duplicate'] && cityGold(0)===$before+250 && bankBalance()===750,'identical retry returns receipt without a second transfer');
    bankCall(0,array_replace($request,['amount'=>100]),422);checkBank(bankBalance()===750,'reusing request ID with another amount is rejected');
    bankCall(0,array_replace($request,['request_id'=>bin2hex(random_bytes(16)),'amount'=>1000]),422);checkBank(bankBalance()===750 && cityGold(0)===$before+250,'insufficient bank rolls back without charging either side');
    bankCall(0,array_replace($request,['request_id'=>bin2hex(random_bytes(16)),'amount'=>-1]),422);
    bankCall(0,array_replace($request,['request_id'=>bin2hex(random_bytes(16)),'amount'=>1000001]),422);
    bankCall(0,array_replace($request,['request_id'=>bin2hex(random_bytes(16)),'resource'=>'gems']),422);
    bankCall(0,['action'=>'alliance.transfer','player_id'=>$players[1]['id']]);
    bankCall(0,array_replace($request,['request_id'=>bin2hex(random_bytes(16))]),403);
    $otherBefore=cityGold(0);$leaderBefore=cityGold(1);
    $last=bankCall(1,['action'=>'alliance.withdraw','resource'=>'gold','amount'=>750,'request_id'=>bin2hex(random_bytes(16)),'player_id'=>$players[0]['id'],'city_id'=>$players[0]['city']]);
    checkBank(bankBalance()===0 && cityGold(1)===$leaderBefore+750 && cityGold(0)===$otherBefore,'new leadership is authoritative and forged destination IDs cannot divert resources');
    checkBank((int)$db->query('SELECT COUNT(*) FROM kingdom_treasury_withdrawals WHERE alliance_id=?',[$aid])->fetchColumn()===2,'only committed unique withdrawals create durable receipts');
    echo "ALL TREASURY HTTP CHECKS PASSED\n";
} catch(Throwable $e) { $failed=true;fwrite(STDERR,'FAIL '.$e->getMessage()."\n"); }
finally {
    if($aid) {
        foreach(['kingdom_treasury_withdrawals','alliance_donations','alliance_treasury','alliance_members'] as $table) { $db->execute("DELETE FROM $table WHERE alliance_id=?",[$aid]); }
        $db->execute('DELETE FROM alliances WHERE id=?',[$aid]);
    }
    foreach($players as $p) {
        foreach(['sessions','player_inventory','player_daily_quests','player_treasures','research_queue','player_research'] as $table) { $db->execute("DELETE FROM $table WHERE player_id=?",[$p['id']]); }
        if($p['city']) { $db->execute('DELETE FROM cities WHERE id=?',[$p['city']]); }
        $db->execute('DELETE FROM players WHERE id=?',[$p['id']]);
    }
}
if($failed)exit(1);
