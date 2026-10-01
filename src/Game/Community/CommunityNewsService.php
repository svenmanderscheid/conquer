<?php
declare(strict_types=1);
namespace Conquer\Game\Community;

use Conquer\Db\Connection;
use Conquer\Game\Locale;

/** Official announcements are available in game independently of Discord. */
final class CommunityNewsService
{
    public static function discordInvite(): ?string
    {
        $configured=getenv('CONQUER_DISCORD_INVITE');
        if(!$configured){$path=dirname(__DIR__,3).'/data/community-discord.json';$settings=is_file($path)?json_decode((string)file_get_contents($path),true):[];$configured=$settings['invite_url']??'';}
        if(!is_string($configured)||!preg_match('~^https://(?:discord\.gg/[A-Za-z0-9-]+|discord\.com/invite/[A-Za-z0-9-]+)$~D',$configured))return null;
        return $configured;
    }

    public static function state(int $worldId): array
    {
        return ['discord'=>['invite_url'=>self::discordInvite()], 'posts'=>Connection::getInstance()->query('SELECT id,title,body,created_at,updated_at FROM community_news WHERE published=1 AND (world_id IS NULL OR world_id=?) ORDER BY id DESC LIMIT 40',[$worldId])->fetchAll()];
    }

    /** Called under AdminService's authentication, transaction, receipt and audit. */
    public static function save(int $adminId,array $input): array
    {
        $db=Connection::getInstance();
        if($db->query('SELECT role FROM admin_users WHERE id=?',[$adminId])->fetchColumn()!=='superadmin')throw new \DomainException(Locale::t('social.admin_required'));
        $id=filter_var($input['news_id']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
        $world=filter_var($input['world_id']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $title=$input['title']??null;$body=$input['body']??null;
        if($id===false||$world===false||!is_string($title)||!is_string($body)||!mb_check_encoding($title,'UTF-8')||!mb_check_encoding($body,'UTF-8')||mb_strlen(trim($title))<3||mb_strlen($title)>160||mb_strlen(trim($body))<3||mb_strlen($body)>6000||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u',$title.$body))throw new \DomainException(Locale::t('social.invalid_news'));
        if(!$db->query('SELECT id FROM worlds WHERE id=?',[$world])->fetchColumn())throw new \DomainException(Locale::t('social.invalid_news'));
        $before=$id?$db->query('SELECT * FROM community_news WHERE id=? AND world_id=? FOR UPDATE',[$id,$world])->fetch():null;
        if($id&&!$before)throw new \DomainException(Locale::t('social.invalid_news'));
        $published=($input['published']??'0')==='1'?1:0;
        if($id)$db->execute('UPDATE community_news SET title=?,body=?,published=? WHERE id=?',[trim($title),trim($body),$published,$id]);
        else{$db->execute('INSERT INTO community_news(world_id,title,body,published,created_by)VALUES(?,?,?,?,?)',[$world,trim($title),trim($body),$published,$adminId]);$id=(int)$db->getPdo()->lastInsertId();}
        return ['target_type'=>'community_news','target_id'=>$id,'before'=>$before,'after'=>['title'=>trim($title),'body'=>trim($body),'published'=>$published],'message'=>Locale::t('social.news_saved')];
    }
}
