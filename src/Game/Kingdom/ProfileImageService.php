<?php
declare(strict_types=1);
namespace Conquer\Game\Kingdom;

use Conquer\Bootstrap;
use Conquer\Db\Connection;

/** Validates, normalizes and moderates player-supplied profile images. */
final class ProfileImageService
{
    private const MAX_BYTES=5_242_880;
    private const MAX_PIXELS=24_000_000;
    private const OUTPUT_SIZE=512;

    public static function upload(int $playerId,array $file): array
    {
        self::requireExtension('gd','Die Bildverarbeitung ist auf dem Server nicht verfügbar.');
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new \DomainException('Wähle ein JPG-, PNG- oder WebP-Bild aus.');
        $size=(int)($file['size']??0);
        if($size<1||$size>self::MAX_BYTES)throw new \DomainException('Das Profilfoto darf höchstens 5 MB groß sein.');
        $tmp=(string)($file['tmp_name']??'');
        if($tmp===''||!is_uploaded_file($tmp))throw new \DomainException('Der Bild-Upload konnte nicht bestätigt werden.');
        $info=@getimagesize($tmp);$allowed=[IMAGETYPE_JPEG,IMAGETYPE_PNG];
        if(defined('IMAGETYPE_WEBP'))$allowed[]=IMAGETYPE_WEBP;
        if(!$info||!in_array((int)$info[2],$allowed,true))throw new \DomainException('Nur echte JPG-, PNG- oder WebP-Bilder sind erlaubt.');
        $width=(int)$info[0];$height=(int)$info[1];
        if($width<128||$height<128||$width*$height>self::MAX_PIXELS)throw new \DomainException('Das Bild muss mindestens 128 × 128 Pixel groß sein.');
        $raw=file_get_contents($tmp);$source=$raw===false?false:@imagecreatefromstring($raw);
        if(!$source)throw new \DomainException('Das Bild ist beschädigt oder kann nicht gelesen werden.');
        $side=min($width,$height);$x=intdiv($width-$side,2);$y=intdiv($height-$side,2);
        $normalized=imagecreatetruecolor(self::OUTPUT_SIZE,self::OUTPUT_SIZE);
        imagefill($normalized,0,0,imagecolorallocate($normalized,251,246,236));
        imagecopyresampled($normalized,$source,0,0,$x,$y,self::OUTPUT_SIZE,self::OUTPUT_SIZE,$side,$side);imagedestroy($source);
        ob_start();imagejpeg($normalized,null,88);$jpeg=ob_get_clean();imagedestroy($normalized);
        if(!is_string($jpeg)||$jpeg==='')throw new \RuntimeException('Das Profilfoto konnte nicht verarbeitet werden.');
        self::moderate($jpeg);
        $directory=ROOT_DIR.'/assets/uploads/profile';
        if(!is_dir($directory)&&!mkdir($directory,0755,true)&&!is_dir($directory))throw new \RuntimeException('Der Speicher für Profilfotos ist nicht verfügbar.');
        $filename=$playerId.'-'.bin2hex(random_bytes(16)).'.jpg';$path=$directory.'/'.$filename;
        if(file_put_contents($path,$jpeg,LOCK_EX)===false)throw new \RuntimeException('Das Profilfoto konnte nicht gespeichert werden.');
        $db=Connection::getInstance();$old=$db->query('SELECT profile_image FROM kingdom_profiles WHERE player_id=?',[$playerId])->fetchColumn();
        try{$db->execute('UPDATE kingdom_profiles SET profile_image=?,profile_image_updated_at=UTC_TIMESTAMP() WHERE player_id=?',[$filename,$playerId]);}
        catch(\Throwable $e){@unlink($path);throw $e;}
        if(self::validFilename($old))@unlink($directory.'/'.$old);
        return ['message'=>'Dein geprüftes Profilfoto wurde gespeichert.','profile_image'=>'assets/uploads/profile/'.$filename];
    }

    public static function remove(int $playerId): array
    {
        $db=Connection::getInstance();$old=$db->query('SELECT profile_image FROM kingdom_profiles WHERE player_id=?',[$playerId])->fetchColumn();
        $db->execute('UPDATE kingdom_profiles SET profile_image=NULL,profile_image_updated_at=NULL WHERE player_id=?',[$playerId]);
        if(self::validFilename($old))@unlink(ROOT_DIR.'/assets/uploads/profile/'.$old);
        return ['message'=>'Dein eigenes Profilfoto wurde entfernt.'];
    }

    private static function moderate(string $jpeg): void
    {
        $cfg=Bootstrap::getConfig()['profile_images']??[];$key=(string)($cfg['openai_api_key']??getenv('OPENAI_API_KEY')?:'');
        if($key==='')throw new \DomainException('Profilfoto-Uploads sind noch nicht freigeschaltet.',503);
        self::requireExtension('curl','Die automatische Bildprüfung ist nicht verfügbar.');
        $payload=json_encode(['model'=>(string)($cfg['model']??'omni-moderation-latest'),'input'=>[['type'=>'image_url','image_url'=>['url'=>'data:image/jpeg;base64,'.base64_encode($jpeg)]]]],JSON_THROW_ON_ERROR);
        $ch=curl_init('https://api.openai.com/v1/moderations');curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$key,'Content-Type: application/json'],CURLOPT_POSTFIELDS=>$payload,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20]);
        $body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$error=curl_error($ch);curl_close($ch);
        if(!is_string($body)||$status<200||$status>=300){\Conquer\Logger::getInstance()->warn('Profile image moderation unavailable: HTTP '.$status.' '.$error);throw new \DomainException('Das Bild konnte gerade nicht sicher geprüft werden. Bitte versuche es später erneut.',503);}
        try{$result=json_decode($body,true,32,JSON_THROW_ON_ERROR)['results'][0]??null;}catch(\JsonException){$result=null;}
        if(!is_array($result)||!array_key_exists('flagged',$result))throw new \DomainException('Das Prüfergebnis war unvollständig. Bitte versuche es später erneut.',503);
        if($result['flagged'])throw new \DomainException('Dieses Bild kann wegen möglicherweise ungeeigneter Inhalte nicht als Profilfoto verwendet werden.');
    }

    private static function validFilename(mixed $value): bool{return is_string($value)&&(bool)preg_match('/^\d+-[a-f0-9]{32}\.jpg$/D',$value);}
    private static function requireExtension(string $extension,string $message): void{if(!extension_loaded($extension))throw new \DomainException($message,503);}
}
