<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/src/Game/Locale.php';
use Conquer\Game\Locale;
$base='';foreach($argv as $arg)if(str_starts_with($arg,'--base='))$base=substr($arg,7);
define('APP_BASE',$base);
function deliveryCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
function deliveryPath(string $url):string{return substr($url,strlen(APP_BASE));}
function deliveryConfig():array{return json_decode(substr(Locale::bootstrap(),strlen('window.CONQUER_I18N='),-1),true,512,JSON_THROW_ON_ERROR);}
$fixture=['bootstrap'=>[],'scripts'=>[],'urls'=>[]];$sizes=[];
foreach(['en','de','fr','sources-de'] as $name){
    $jsonUrl=Locale::assetUrl($name);$jsUrl=Locale::assetUrl($name,'js');
    deliveryCheck(str_starts_with($jsonUrl,APP_BASE.'/locale-assets/'.$name.'.'),'portable versioned URL');
    $json=Locale::assetResponse(deliveryPath($jsonUrl));$js=Locale::assetResponse(deliveryPath($jsUrl));
    deliveryCheck($json['status']===200&&$js['status']===200,'current asset exists');
    deliveryCheck(str_contains($json['headers']['Content-Type'],'application/json')&&str_contains($js['headers']['Content-Type'],'application/javascript'),'correct executable / data types');
    deliveryCheck($json['headers']['Cache-Control']==='public, max-age=31536000, immutable'&&!isset($json['headers']['Set-Cookie']),'public immutable editorial asset');
    deliveryCheck($json['headers']['X-Content-Type-Options']==='nosniff'&&$json['headers']['Cross-Origin-Resource-Policy']==='same-origin','asset boundaries');
    deliveryCheck((int)$json['headers']['Content-Length']===strlen($json['body']),'content length matches UTF-8 bytes');
    $head=Locale::assetResponse(deliveryPath($jsonUrl),'HEAD');
    $headHeaders=$head['headers'];$getHeaders=$json['headers'];unset($headHeaders['Expires'],$getHeaders['Expires']);
    deliveryCheck($head['body']===''&&$headHeaders===$getHeaders,'HEAD preserves representation metadata');
    $conditional=Locale::assetResponse(deliveryPath($jsonUrl),'GET','"different", W/'.$json['headers']['ETag']);
    deliveryCheck($conditional['status']===304&&$conditional['body']==='','conditional GET validates the content version');
    deliveryCheck(Locale::assetResponse(deliveryPath($jsonUrl),'GET',$js['headers']['ETag'])['status']===200,'JSON and executable representations have distinct validators');
    $_COOKIE=['conquer_locale'=>'fr','conquer_session'=>'PRIVATE_ACCOUNT_SENTINEL'];
    deliveryCheck(Locale::assetResponse(deliveryPath($jsonUrl))['body']===$json['body']&&!str_contains($js['body'],'PRIVATE_ACCOUNT_SENTINEL'),'cookies cannot enter public asset data');
    $data=json_decode($json['body'],true,512,JSON_THROW_ON_ERROR);
    if($name!=='sources-de')deliveryCheck($data===Locale::catalog($name),'target catalogue is complete: '.$name);
    else{
        $expected=[];foreach(Locale::catalog('de') as $key=>$value)if((Locale::catalog('en')[$key]??null)!==$value)$expected[$key]=$value;
        deliveryCheck($data===$expected,'source map retains every German string differing from English');
    }
    $fixture['scripts'][$name]=$js['body'];$fixture['urls'][$name]=$jsonUrl;
    $sizes[$name]=['jsonBytes'=>strlen($json['body']),'jsBytes'=>strlen($js['body']),'entries'=>count($data),'sha256'=>hash('sha256',$json['body']),'cacheStorageEligible'=>strlen($js['body'])<=512*1024];
}
foreach(['en','de','fr'] as $locale){
    $_COOKIE['conquer_locale']=$locale;$config=deliveryConfig();$markup=Locale::bootstrapScripts('safe"nonce');
    deliveryCheck($config['locale']===$locale&&$config['catalogs']===[]&&$config['sourceCatalogs']===[],'bootstrap only contains metadata');
    deliveryCheck(strlen(Locale::bootstrap())<1500&&!str_contains(Locale::bootstrap(),'</script>'),'small HTML-safe bootstrap');
    deliveryCheck(str_contains($markup,'nonce="safe&quot;nonce"')&&!str_contains($markup,'safe"nonce'),'CSP nonce attribute is escaped');
    preg_match_all('~<script src="([^"]+)" defer></script>~',$markup,$matches);
    $expected=['en'];if($locale!=='en')$expected[]=$locale;if($locale!=='de')$expected[]='sources-de';
    deliveryCheck($matches[1]===array_map(static fn($name)=>Locale::assetUrl($name,'js'),$expected),'EN then current locale then source data, deferred in order');
    $fixture['bootstrap'][$locale]=Locale::bootstrap();
    $sizes['initial'][$locale]=['inlineBytes'=>strlen(Locale::bootstrap()),'markupBytes'=>strlen($markup),'catalogueNames'=>$expected,'coldScriptBytes'=>array_sum(array_map(static fn($name)=>$sizes[$name]['jsBytes'],$expected))];
}
unset($_COOKIE['conquer_locale']);deliveryCheck(deliveryConfig()['locale']==='en','English is the default without browser sniffing');
foreach(['/locale-assets/../config/database.php','/locale-assets/it.'.str_repeat('a',20).'.json','/locale-assets/en.'.str_repeat('0',20).'.json','/locale-assets/en.'.str_repeat('a',20).'.php','/locale-assets/en.json','/locale-assets/%2e%2e/data/i18n/en.json'] as $badPath){
    $response=Locale::assetResponse($badPath);deliveryCheck($response['status']===404&&$response['headers']['Cache-Control']==='no-store'&&$response['body']==='','invalid or stale asset is not served or cached');
}
$post=Locale::assetResponse(deliveryPath(Locale::assetUrl('en')),'POST');deliveryCheck($post['status']===405&&$post['headers']['Allow']==='GET, HEAD'&&$post['body']==='','no write methods');
if(in_array('--fixture',$argv,true)){echo json_encode($fixture,JSON_THROW_ON_ERROR);exit;}
$legacyCatalogs=[];foreach(Locale::SUPPORTED as $locale=>$label)$legacyCatalogs[$locale]=Locale::catalog($locale);
$legacy='window.CONQUER_I18N='.json_encode(['locale'=>'en','catalogs'=>$legacyCatalogs],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).';';
$evidence=['measuredAt'=>gmdate('c'),'base'=>APP_BASE,'description'=>'Uncompressed source bytes; excludes HTML outside the locale block and does not measure browser network timing or gzip. Cold locale payload includes the German legacy-source map. CacheStorage keeps its unchanged 512 KiB per-file cap; browser HTTP caching remains available for larger assets.','oldInlineBytes'=>strlen($legacy),'assets'=>$sizes];
if(in_array('--evidence',$argv,true)){
    $directory=dirname(__DIR__).'/artifacts/ui-ux-implementation-2026-09-30/locale-delivery';if(!is_dir($directory))mkdir($directory,0777,true);
    file_put_contents($directory.'/source-bytes.json',json_encode($evidence,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n");
}
echo 'PASS locale delivery: complete public catalogues, source map, EN default, ordered CSP-safe loading, root/subdirectory URLs, cookie isolation, validators, HEAD and route allowlist. Base: '.(APP_BASE?:'/')."\n";
echo 'Inline catalogue bytes: '.strlen($legacy).' -> '.strlen(Locale::bootstrap())."; EN cold script bytes: ".$sizes['initial']['en']['coldScriptBytes']."\n";
