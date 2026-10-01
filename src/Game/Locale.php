<?php
declare(strict_types=1);
namespace Conquer\Game;

/** Explicit translation keys, shared with the browser; no automatic translation of player data. */
final class Locale
{
    public const DEFAULT='en';
    public const SUPPORTED=['en'=>'English','de'=>'Deutsch','fr'=>'Français'];
    private static array $catalogs=[];
    private static array $assets=[];
    private const JSON_FLAGS=JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR;
    // Increment when the executable wrapper or source-index format changes.
    private const ASSET_FORMAT='locale-delivery-v1';

    public static function normalize(mixed $locale):string
    {
        if(!is_string($locale))return self::DEFAULT;$locale=strtolower(str_replace('_','-',trim($locale)));$locale=explode('-',$locale)[0];return isset(self::SUPPORTED[$locale])?$locale:self::DEFAULT;
    }

    public static function current():string{return self::normalize($_COOKIE['conquer_locale']??self::DEFAULT);}

    public static function catalog(?string $locale=null):array
    {
        $locale=self::normalize($locale??self::current());if(isset(self::$catalogs[$locale]))return self::$catalogs[$locale];
        $path=dirname(__DIR__,2).'/data/i18n/'.$locale.'.json';$text=file_get_contents($path);if($text===false)throw new \RuntimeException('Sprachdatei fehlt.');
        $data=json_decode($text,true,8,JSON_THROW_ON_ERROR);if(!is_array($data))throw new \RuntimeException('Sprachdatei ungültig.');return self::$catalogs[$locale]=$data;
    }

    public static function t(string $key,array $parameters=[],?string $locale=null):string
    {
        $catalog=self::catalog($locale);$value=$catalog[$key]??self::catalog(self::DEFAULT)[$key]??$key;
        foreach($parameters as$name=>$replacement)if(is_scalar($replacement))$value=str_replace('{'.$name.'}',(string)$replacement,$value);
        return $value;
    }

    public static function html(string $key,array $parameters=[],?string $locale=null):string{return htmlspecialchars(self::t($key,$parameters,$locale),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}

    /** Translate shipped editorial copy only. Never pass player names or messages here. */
    public static function text(string $text,?string $locale=null):string
    {
        static $index=null,$patterns=[];
        if($index===null){
            $index=[];
            foreach(['de','en','fr'] as $source)foreach(self::catalog($source) as $key=>$value){
                $normalized=preg_replace('/\s+/u',' ',trim($value));
                $index[$normalized]??=$key;
                if(preg_match_all('/\{([a-zA-Z0-9_]+)\}/',$normalized,$names)){
                    $parts=preg_split('/\{[a-zA-Z0-9_]+\}/',$normalized);
                    $literal=implode('',$parts);
                    if(!preg_match('/\p{L}{2}/u',$literal))continue;
                    $pattern='~^'.implode('(.+?)',array_map(static fn($part)=>preg_quote($part,'~'),$parts)).'$~u';
                    $patterns[]=['key'=>$key,'names'=>$names[1],'pattern'=>$pattern,'weight'=>strlen($literal)];
                }
            }
            usort($patterns,static fn($a,$b)=>$b['weight']<=>$a['weight']);
        }
        $normalized=preg_replace('/\s+/u',' ',trim($text));
        if(isset($index[$normalized]))return self::t($index[$normalized],[],$locale);
        foreach($patterns as $entry)if(preg_match($entry['pattern'],$normalized,$matches)){
            $parameters=[];foreach($entry['names'] as $i=>$name)$parameters[$name]=$matches[$i+1];
            return self::t($entry['key'],$parameters,$locale);
        }
        return $text;
    }

    public static function bootstrap():string
    {
        $urls=[];foreach(self::SUPPORTED as $locale=>$name)$urls[$locale]=self::assetUrl($locale);
        return 'window.CONQUER_I18N='.json_encode([
            'locale'=>self::current(),'catalogs'=>new \stdClass(),'sourceCatalogs'=>new \stdClass(),
            'catalogUrls'=>$urls,'sourceUrl'=>self::assetUrl('sources-de'),
        ],self::JSON_FLAGS).';';
    }

    /** Ordered deferred scripts complete before localization.js, without repeating catalogues in HTML. */
    public static function bootstrapScripts(?string $nonce=null):string
    {
        $nonceAttribute=$nonce===null?'':' nonce="'.htmlspecialchars($nonce,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'"';
        $html='<script'.$nonceAttribute.'>'.self::bootstrap().'</script>';
        $names=array_values(array_unique([self::DEFAULT,self::current()]));
        // A loaded German target catalogue already supplies the same source text.
        if(self::current()!=='de')$names[]='sources-de';
        foreach($names as $name)$html.="\n".'<script src="'.htmlspecialchars(self::assetUrl($name,'js'),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'" defer></script>';
        return $html;
    }

    /** This URL contains only shipped editorial text, never the session or a player preference. */
    public static function assetUrl(string $name,string $format='json'):string
    {
        if(!in_array($format,['json','js'],true))throw new \InvalidArgumentException('Invalid locale asset format.');
        $asset=self::asset($name);
        return (defined('APP_BASE')?APP_BASE:'').'/locale-assets/'.$name.'.'.$asset['version'].'.'.$format;
    }

    private static function asset(string $name):array
    {
        if(!isset(self::SUPPORTED[$name])&&$name!=='sources-de')throw new \InvalidArgumentException('Invalid locale asset.');
        if(isset(self::$assets[$name]))return self::$assets[$name];
        if($name==='sources-de'){
            $english=self::catalog(self::DEFAULT);$data=[];
            foreach(self::catalog('de') as $key=>$value)if(($english[$key]??null)!==$value)$data[$key]=$value;
        }else $data=self::catalog($name);
        $json=json_encode((object)$data,self::JSON_FLAGS);
        $version=substr(hash('sha256',self::ASSET_FORMAT."\n".$json),0,20);
        $slot=$name==='sources-de'?'sourceCatalogs.de':'catalogs.'.$name;
        // Each public script remains harmless when requested outside a page bootstrap.
        $js='window.CONQUER_I18N=window.CONQUER_I18N||{};window.CONQUER_I18N.catalogs=window.CONQUER_I18N.catalogs||{};window.CONQUER_I18N.sourceCatalogs=window.CONQUER_I18N.sourceCatalogs||{};window.CONQUER_I18N.'.$slot.'='.$json.';';
        return self::$assets[$name]=['version'=>$version,'json'=>$json,'js'=>$js];
    }

    /** Pure response builder: the front controller calls it before bootstrap, DB or session work. */
    public static function assetResponse(string $path,string $method='GET',string $ifNoneMatch=''):array
    {
        $headers=['Content-Type'=>'application/json; charset=utf-8','Cache-Control'=>'no-store','X-Content-Type-Options'=>'nosniff','Cross-Origin-Resource-Policy'=>'same-origin'];
        if(!in_array($method,['GET','HEAD'],true))return ['status'=>405,'headers'=>$headers+['Allow'=>'GET, HEAD'],'body'=>''];
        if(!preg_match('~^/locale-assets/(en|de|fr|sources-de)\.([a-f0-9]{20})\.(json|js)$~D',$path,$matches))return ['status'=>404,'headers'=>$headers,'body'=>''];
        $asset=self::asset($matches[1]);
        if(!hash_equals($asset['version'],$matches[2]))return ['status'=>404,'headers'=>$headers,'body'=>''];
        $body=$asset[$matches[3]];$etag='"'.$asset['version'].'-'.$matches[3].'"';
        $headers['Content-Type']=$matches[3]==='js'?'application/javascript; charset=utf-8':'application/json; charset=utf-8';
        $headers['Cache-Control']='public, max-age=31536000, immutable';
        $headers['Expires']=gmdate('D, d M Y H:i:s',time()+31536000).' GMT';
        $headers['ETag']=$etag;
        foreach(explode(',',$ifNoneMatch) as $candidate)if(trim($candidate)==='*'||preg_replace('~^W/~','',trim($candidate))===$etag)return ['status'=>304,'headers'=>$headers,'body'=>''];
        $headers['Content-Length']=(string)strlen($body);
        return ['status'=>200,'headers'=>$headers,'body'=>$method==='HEAD'?'':$body];
    }
}
