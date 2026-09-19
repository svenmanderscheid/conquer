<?php
declare(strict_types=1);
namespace ConquerTests;

final class MailboxFixture
{
    /** Synthetic data only; the caller must already own a disposable FeatureDatabase. */
    public static function seed(): void
    {
        $db=\Conquer\Db\Connection::getInstance();
        if(!str_starts_with((string)$db->query('SELECT DATABASE()')->fetchColumn(),'conquer_feature_test_'))throw new \RuntimeException('Mailbox fixture requires a disposable database.');
        $db->execute("INSERT IGNORE INTO players(id,username,email,password_hash)VALUES(1,'PreviewPlayer','preview@tests.invalid','unused'),(2,'Elara','elara@tests.invalid','unused'),(3,'Fremder','outsider@tests.invalid','unused')");
        foreach([1,2,3] as $id)$db->execute("INSERT IGNORE INTO cities(id,player_id,world_id,name,coord_x,coord_y)VALUES(?,?,1,'Post-Test',?,50)",[$id,$id,50+$id]);
        $db->execute("INSERT IGNORE INTO alliances(id,world_id,name,tag,leader_id)VALUES(1,1,'Die Morgenwacht','MW',1)");
        $db->execute("INSERT IGNORE INTO alliance_members(alliance_id,player_id,world_id,role)VALUES(1,1,1,'leader'),(1,2,1,'member')");
        $db->execute("INSERT INTO community_mail(world_id,sender_id,recipient_id,subject,body)VALUES(1,2,1,'Treffen am Feuerschrein','Hallo!\nWir treffen uns heute am Feuerschrein. Bring deine stärksten Truppen mit.\n\nBis gleich, Elara'),(1,1,2,'Unsere nächste Erkundung','Ich bin dabei!')");
        $db->execute("INSERT INTO admin_operations(operation_id,admin_id,action,payload_hash)VALUES(?,1,'send-gift',?)",[str_repeat('a',32),str_repeat('a',64)]);
        $db->execute("INSERT INTO admin_gifts(operation_id,player_id,world_id,title,message,rewards_json,before_json,after_json)VALUES(?,1,1,'Ein Dankeschön aus dem Königreich','Danke für deinen Einsatz! Diese Vorräte wurden deinem Reich bereits gutgeschrieben.',?,'{}','{}')",[str_repeat('a',32),json_encode(['food'=>5000,'gems'=>100])]);
        for($n=0;$n<58;$n++)$db->execute("INSERT INTO notifications(player_id,type,data_json,created_at)VALUES(1,'research_complete',?,DATE_SUB(UTC_TIMESTAMP(),INTERVAL ? MINUTE))",[json_encode(['world_id'=>1,'title'=>'Forschung abgeschlossen','message'=>'Deine Gelehrten haben eine neue Forschung abgeschlossen. Dein Reich profitiert ab sofort davon.']),$n+10]);
        $db->execute("INSERT INTO notifications(player_id,type,data_json)VALUES(1,'alliance_notice',?)",[json_encode(['world_id'=>1,'title'=>'Nachricht der Allianzführung','message'=>'Die Morgenwacht erkundet heute den Norden. Unterstützt die Sammeltrupps!'])]);
        $db->execute("INSERT INTO alliance_gifts(alliance_id,trigger_type,gift_json,expires_at,created_by)VALUES(1,'monster_kill',?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY),2)",[json_encode(['item_code'=>10103001,'quantity'=>2])]);
        foreach([[1,null,'attacker_wins','monster','Ork-Späher'],[1,2,'attacker_wins','city','Elaras Stadt'],[1,2,'attacker_wins','city','Angreifende Armee'],[2,1,'scouted','scout','Deine Stadt']] as [$attacker,$defender,$outcome,$kind,$name]){
            $details=['battle_kind'=>$kind,'type'=>$kind==='scout'?'scout':'battle','target_name'=>$name,'perspective'=>$attacker===1?'attacker':'defender','attacker_damage'=>2400,'monster_hp_after'=>0,'troops'=>[['code'=>50100101,'sent'=>100,'survived'=>92,'injured'=>8,'dead'=>0]],'loot'=>['food'=>600,'gold'=>90]];
            if($kind==='monster')$details['monster_snapshot']=['name'=>$name,'level'=>8,'art'=>'orc'];
            if($kind==='scout')$details['resources']=['gold'=>987654321];
            if($name==='Angreifende Armee')$details['perspective']='defender';
            $db->execute('INSERT INTO battle_reports(world_id,attacker_id,attacker_city_id,defender_id,target_type,target_x,target_y,outcome,data_json)VALUES(1,?,?,?,3,64,67,?,?)',[$attacker,$attacker,$defender,$outcome,json_encode($details)]);
        }
    }
}
