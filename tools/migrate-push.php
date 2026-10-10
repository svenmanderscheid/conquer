<?php
declare(strict_types=1);
/** Targeted operator preflight; an explicit --apply installs only migration 0145. */
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
if(array_diff(array_slice($argv,1),['--check','--apply'])||in_array('--check',$argv,true)&&in_array('--apply',$argv,true)){
    fwrite(STDERR,"Usage: php tools/migrate-push.php [--check|--apply]\n");exit(1);
}
define('ROOT_DIR',dirname(__DIR__));
require ROOT_DIR.'/src/Bootstrap.php';
\Conquer\Bootstrap::init(ROOT_DIR);
try{
    $result=in_array('--apply',$argv,true)
        ?\Conquer\Game\Notification\PushInstallation::apply()
        :\Conquer\Game\Notification\PushInstallation::inspect();
    echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
    exit($result['ready']?0:2);
}catch(Throwable){
    // Never print a PDO/provider exception containing a DSN, query or token.
    fwrite(STDERR,"Push installation failed. Run --check and inspect private server diagnostics; no other migration was requested.\n");exit(1);
}
