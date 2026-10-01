<?php
declare(strict_types=1);
namespace ConquerTests;
/** Executes the actual front controller against FeatureDatabase, never the configured game DB. */
final class HttpApp
{
    public static function source(array $config=[],?string $mailFile=null): string
    {
        $source=(string)file_get_contents(ROOT_DIR.'/index.php');
        $source=str_replace(['declare(strict_types=1);', "define('ROOT_DIR', __DIR__);", "require_once ROOT_DIR . '/src/Bootstrap.php';", '\\Conquer\\Bootstrap::init(ROOT_DIR);'], '', $source);
        $source=preg_replace('/^<\?php\s*/', '', $source);
        $config=array_replace(['env'=>'production','base_url'=>'https://game.example.invalid','rate_limit_per_minute'=>10000],$config);
        $prefix="\$_SERVER['SCRIPT_NAME']='/index.php'; (new \\ReflectionProperty(\\Conquer\\Bootstrap::class,'config'))->setValue(null,".var_export($config,true)."); ";
        if($mailFile!==null)$prefix.="define('CONQUER_TEST_MAIL_FILE',".var_export($mailFile,true)."); require ROOT_DIR.'/tests/Support/AccountMailSink.php'; ";
        return $prefix.$source;
    }
    public static function request(string $base,string $path,string $method='GET',array $headers=[],?string $body=null,?string $jar=null): array
    {
        $responseHeaders=[];$h=curl_init($base.$path);
        $options=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,
            CURLOPT_HEADERFUNCTION=>static function($h,$line)use(&$responseHeaders){$responseHeaders[]=strtolower(trim($line));return strlen($line);}];
        if($body!==null)$options[CURLOPT_POSTFIELDS]=$body;
        if($jar!==null){$options[CURLOPT_COOKIEJAR]=$jar;$options[CURLOPT_COOKIEFILE]=$jar;}
        curl_setopt_array($h,$options);$raw=curl_exec($h);
        if($raw===false)throw new \RuntimeException(curl_error($h));
        $status=(int)curl_getinfo($h,CURLINFO_HTTP_CODE);curl_close($h);
        return ['status'=>$status,'body'=>$raw,'json'=>json_decode($raw,true),'headers'=>$responseHeaders];
    }
}
