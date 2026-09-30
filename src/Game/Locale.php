<?php
declare(strict_types=1);
namespace Conquer\Game;

/** Explicit translation keys, shared with the browser; no automatic translation of player data. */
final class Locale
{
    public const DEFAULT='en';
    public const SUPPORTED=['en'=>'English','de'=>'Deutsch','fr'=>'Français'];
    private static array $catalogs=[];

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
        $catalogs=[];foreach(self::SUPPORTED as$locale=>$name)$catalogs[$locale]=self::catalog($locale);
        return 'window.CONQUER_I18N='.json_encode(['locale'=>self::current(),'catalogs'=>$catalogs],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).';';
    }
}
