<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/src/Game/Locale.php';
use Conquer\Game\Locale;
function checkLocale(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);echo 'PASS '.$message."\n";}
unset($_COOKIE['conquer_locale']);
checkLocale(Locale::current()==='en'&&Locale::normalize(null)==='en','English default without a language preference');
checkLocale(Locale::text('Das Shrine-Limit einschließlich laufender Angriffe ist erreicht.')==='The Shrine limit has been reached, including ongoing attacks.','new territory errors are translated by default');
checkLocale(Locale::text('Die Heilung von 12 Truppen wurde gestartet.')==='Healing has started for 12 troops.','dynamic server messages preserve quantities');
checkLocale(Locale::text('Forschung')==='Research','default server text is English');
foreach(['Kampfzeitfenster','Angriffsvoraussetzung','Gemeinsamer Shrine-Auftrag','Der Thron ist unbesetzt','Ausbau auf Stufe 13 starten','Drachenrahmen','Allianzmünze'] as $copy)checkLocale(Locale::text($copy)!==$copy,'English coverage: '.$copy);
$german=Locale::catalog('de');foreach(['fr','en']as$locale){$catalog=Locale::catalog($locale);$sourceKeys=array_keys($german);$targetKeys=array_keys($catalog);sort($sourceKeys);sort($targetKeys);checkLocale($targetKeys===$sourceKeys,'catalog parity for '.$locale);foreach($catalog as$key=>$value)if(!is_string($value)||trim($value)==='')throw new RuntimeException('Empty translation '.$key);}
checkLocale(Locale::normalize('en-US')==='en'&&Locale::normalize('fr-LU')==='fr'&&Locale::normalize('../../config')==='en','locale normalization rejects paths and accepts regional language tags');
checkLocale(Locale::t('mail.send',[],'fr')==='Envoyer le courrier'&&Locale::t('mail.send',[],'en')==='Send mail','PHP shared-key translations');
foreach(['en','fr'] as $locale)foreach($german as $key=>$value){
    preg_match_all('/\{([a-zA-Z0-9_]+)\}/',$value,$sourceParameters);
    preg_match_all('/\{([a-zA-Z0-9_]+)\}/',Locale::catalog($locale)[$key],$targetParameters);
    sort($sourceParameters[1]);sort($targetParameters[1]);
    if($sourceParameters[1]!==$targetParameters[1])throw new RuntimeException('Changed parameters: '.$locale.'.'.$key);
}
checkLocale(Locale::text('Schatzgoblin','fr')==='Gobelin au trésor','authored catalogue names');
checkLocale(Locale::text('Spieler Forschung','en')==='Player Forschung','player-name parameter stays opaque');
checkLocale(Locale::text('950 verwundet','fr')==='950 blessés','dynamic wounded count');
checkLocale(Locale::text('Your kingdom.','fr')==='Votre royaume.','English landing source is localised');
checkLocale(Locale::text('Text outside the shipped catalogue','fr')==='Text outside the shipped catalogue','unknown text is unchanged');
foreach(['de','en','fr'] as $locale)foreach(['cta','tab','success','intro','first_name','last_name','email','consent','submit','privacy_title','privacy','privacy_contact','redeem','error_csrf','error_rate','error_unavailable','error_name','error_email','error_consent'] as $key)checkLocale(Locale::t('waitlist.'.$key,[],$locale)!=='waitlist.'.$key,'waitlist text exists: '.$locale.'.'.$key);
checkLocale(Locale::html('{name}',['name'=>'<script>bad</script>'],'fr')==='&lt;script&gt;bad&lt;/script&gt;','semantic parameter output is HTML escaped');
$_COOKIE['conquer_locale']='fr';checkLocale(Locale::current()==='fr','server locale follows validated cookie');
$bootstrap=Locale::bootstrap();checkLocale(str_starts_with($bootstrap,'window.CONQUER_I18N=')&&!str_contains($bootstrap,'</script>'),'bootstrap is safe to embed in an HTML script');
echo 'ALL LOCALE CHECKS PASSED ('.count($german)." translation keys).\n";
