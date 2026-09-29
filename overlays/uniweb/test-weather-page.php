<?php
declare(strict_types=1);
$mode=$argv[1] ?? 'get';
if(!in_array($mode,['get','guest','method'],true)) throw new RuntimeException('Unknown mode');
$dir=sys_get_temp_dir().'/weather-page-'.bin2hex(random_bytes(5));
mkdir($dir.'/studio-public',0700,true);mkdir($dir.'/studio-private/app/views',0700,true);
mkdir($dir.'/studio-private/app/integrations',0700,true);mkdir($dir.'/studio-private/config',0700,true);
copy(__DIR__.'/studio-public/weather.php',$dir.'/studio-public/weather.php');
copy(__DIR__.'/studio-private/app/views/sidebar.php',$dir.'/studio-private/app/views/sidebar.php');
file_put_contents($dir.'/studio-private/app/views/head.php','<!doctype html><html><body>');
file_put_contents($dir.'/studio-private/app/views/account.php','');
// Keep the real parser/cache. Seed unexpired fixture caches to forbid network access.
copy(__DIR__.'/studio-private/app/integrations/WeatherOverview.php',$dir.'/studio-private/app/integrations/WeatherOverview.php');
file_put_contents($dir.'/studio-private/app/bootstrap.php', <<<'CODE'
<?php
function current_user() { return $GLOBALS['mode']==='guest' ? null : ['role'=>'observer']; }
function studio_can($user,$permission) { return false; }
function escape($value) { return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8'); }
function icon($name,$size) { return ''; }
function redirect($url) { http_response_code(302); echo 'REDIRECT '.$url; exit; }
CODE);
$now=time();$points=[];
for($i=0;$i<49;$i++) $points[]=['time'=>(int)(floor($now/3600)*3600)+$i*3600,'temperature'=>12.0,'wind'=>4.0,'rain'=>0.2,'hours'=>1,'symbol'=>'partlycloudy_day'];
foreach(['bomlo','stord','haugesund','bergen','stavanger'] as $id)
    file_put_contents($dir.'/studio-private/config/weather-overview-'.$id.'.json',json_encode(['nextCheck'=>$now+3600,'checkedAt'=>$now,'fetchedAt'=>$now,'failed'=>false,'forecast'=>['updatedAt'=>$now,'points'=>$points]]));
$_SERVER['REQUEST_METHOD']=$mode==='method'?'POST':'GET';
ob_start();
register_shutdown_function(static function()use($mode,$dir){
    $html=ob_get_clean();$code=http_response_code() ?: 200;
    $expected=match($mode){'guest'=>302,'method'=>405,default=>200};
    $ok=$code===$expected;
    if($mode==='get') $ok=$ok && str_contains($html,'Bømlo') && str_contains($html,'Stavanger')
        && str_contains($html,'Kort værstikk') && substr_count($html,'weather-row updated')===5;
    else $ok=$ok && !str_contains($html,'Kort værstikk');
    if(getenv('WEATHER_RENDER_PATH') && $mode==='get') file_put_contents(getenv('WEATHER_RENDER_PATH'),$html);
    $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($files as $file) { if($file->isDir()) rmdir($file->getPathname()); else unlink($file->getPathname()); }
    rmdir($dir);
    if(!$ok){fwrite(STDERR,"FAIL weather page $mode HTTP $code\n$html\n");exit(1);}
    echo "OK weather page $mode HTTP $code\n";
});
require $dir.'/studio-public/weather.php';
