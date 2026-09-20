<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/src/Game/Locale.php';
use Conquer\Game\Locale;
function checkLocale(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);echo 'PASS '.$message."\n";}
$german=Locale::catalog('de');foreach(['fr','en']as$locale){$catalog=Locale::catalog($locale);checkLocale(array_keys($catalog)===array_keys($german),'catalog parity for '.$locale);foreach($catalog as$key=>$value)if(!is_string($value)||trim($value)==='')throw new RuntimeException('Empty translation '.$key);}
checkLocale(Locale::normalize('en-US')==='en'&&Locale::normalize('fr-LU')==='fr'&&Locale::normalize('../../config')==='de','locale normalization rejects paths and accepts regional language tags');
checkLocale(Locale::t('mail.send',[],'fr')==='Envoyer le courrier'&&Locale::t('mail.send',[],'en')==='Send mail','PHP shared-key translations');
foreach(['de','en','fr'] as $locale)foreach(['cta','tab','success','intro','first_name','last_name','email','consent','submit','privacy_title','privacy','privacy_contact','redeem','error_csrf','error_rate','error_unavailable','error_name','error_email','error_consent'] as $key)checkLocale(Locale::t('waitlist.'.$key,[],$locale)!=='waitlist.'.$key,'waitlist text exists: '.$locale.'.'.$key);
checkLocale(Locale::html('{name}',['name'=>'<script>bad</script>'],'fr')==='&lt;script&gt;bad&lt;/script&gt;','semantic parameter output is HTML escaped');
$_COOKIE['conquer_locale']='fr';checkLocale(Locale::current()==='fr','server locale follows validated cookie');
$bootstrap=Locale::bootstrap();checkLocale(str_starts_with($bootstrap,'window.CONQUER_I18N=')&&!str_contains($bootstrap,'</script>'),'bootstrap is safe to embed in an HTML script');
echo 'ALL LOCALE CHECKS PASSED ('.count($german)." translation keys).\n";
