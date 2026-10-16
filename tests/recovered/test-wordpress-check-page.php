<?php
declare(strict_types=1);
if(empty($argv[1])){
    foreach(['get','guest','observer','csrf','wrong-target','missing-credentials']as$mode){
        passthru(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' '.escapeshellarg($mode),$code);
        if($code)exit($code);
    }
    exit;
}
$mode=$argv[1];$root=dirname(__DIR__,2);$tmp=sys_get_temp_dir().'/rr-wp-check-'.bin2hex(random_bytes(5));
foreach(['/app','/config','/public']as$dir)mkdir($tmp.$dir,0700,true);
copy($root.'/public/wordpress-check.php',$tmp.'/public/wordpress-check.php');
copy($root.'/app/wordpress-destination.php',$tmp.'/app/wordpress-destination.php');
// Use the actual configuration/readiness functions with no publishing dependencies.
$publisher=file_get_contents($root.'/app/web-publish.php');
$start=strpos($publisher,'function studio_wp_config');$end=strpos($publisher,'function studio_wp_request');
file_put_contents($tmp.'/app/web-publish.php',"<?php require_once __DIR__.'/wordpress-destination.php';\n".substr($publisher,$start,$end-$start));
file_put_contents($tmp.'/app/bootstrap.php','<?php
function current_user(){return $GLOBALS["mode"]==="guest"?null:["name"=>"Test","role"=>$GLOBALS["mode"]==="observer"?"observer":"admin"];}
function redirect($url){$GLOBALS["redirected"]=$url;exit;}
function escape($value){return htmlspecialchars((string)$value,ENT_QUOTES,"UTF-8");}');
$settings=['environment'=>'test','site_url'=>'https://5.249.252.78/wordpress','username'=>'synthetic-private-user','application_password'=>'synthetic-private-value'];
if($mode==='wrong-target')$settings['site_url']='https://www.radiorubben.no';
if($mode==='missing-credentials')unset($settings['application_password']);
file_put_contents($tmp.'/config/wordpress.php','<?php return '.var_export($settings,true).';');
putenv('STUDIO_WORDPRESS_ENVIRONMENT=test');
$_SESSION=['csrf'=>'synthetic-csrf'];$_SERVER['REQUEST_METHOD']=in_array($mode,['csrf','wrong-target','missing-credentials'],true)?'POST':'GET';
$_POST=['csrf'=>$mode==='csrf'?'wrong':'synthetic-csrf'];
ob_start();register_shutdown_function(static function()use($tmp,$mode):void{
    $html=ob_get_clean();$ok=!str_contains($html,'synthetic-private');
    if($mode==='guest')$ok=$ok&&($GLOBALS['redirected']??'')==='/login.php';
    elseif(in_array($mode,['observer','csrf'],true))$ok=$ok&&http_response_code()===403&&!str_contains($html,'https://5.249.252.78');
    elseif($mode==='get')$ok=$ok&&str_contains($html,'https://5.249.252.78/wordpress')&&str_contains($html,'Bare lesetilgang');
    else $ok=$ok&&str_contains($html,'WordPress-tilkoblingen mangler eller gjelder feil miljø.')&&!str_contains($html,'"http"');
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST)as$f){$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());}rmdir($tmp);
    if(!$ok)throw new RuntimeException('WordPress diagnostic page: '.$mode);
    echo "PASS WP diagnostic page: $mode\n";
});
require $tmp.'/public/wordpress-check.php';
