<?php
declare(strict_types=1);
// Isolated HTTP-entry smoke test. No production bootstrap, network or private config.
$mode = $argv[1] ?? 'get';
if (!in_array($mode,['get','observer','csrf','nonce','unknown','create','method'],true)) throw new RuntimeException('Unknown test');
$dir=sys_get_temp_dir().'/broadcast-page-'.bin2hex(random_bytes(5));
mkdir($dir.'/studio-public',0700,true);mkdir($dir.'/studio-private/app/views',0700,true);
mkdir($dir.'/studio-private/app/templates',0700,true);mkdir($dir.'/studio-private/config',0700,true);
foreach (['broadcast','board','programs','editorial-memory','story-script'] as $file)
    copy(__DIR__.'/studio-private/app/'.$file.'.php',$dir.'/studio-private/app/'.$file.'.php');
copy(__DIR__.'/studio-private/app/templates/morning-v1.json',$dir.'/studio-private/app/templates/morning-v1.json');
copy(__DIR__.'/studio-public/broadcast.php',$dir.'/studio-public/broadcast.php');
copy(__DIR__.'/studio-private/app/views/sidebar.php',$dir.'/studio-private/app/views/sidebar.php');
file_put_contents($dir.'/studio-private/app/views/head.php','<!doctype html><html><body>');
file_put_contents($dir.'/studio-private/app/views/account.php','');
file_put_contents($dir.'/studio-private/app/bootstrap.php', <<<'PHP'
<?php
$config=[];
function current_user() { return $GLOBALS['testUser']; }
function studio_can($user,$permission) { return in_array($user['role'],['admin','producer','presenter'],true); }
function escape($value) { return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8'); }
function icon($name,$size) { return ''; }
function redirect($url) { http_response_code(302); echo 'REDIRECT '.$url; exit; }
PHP);
$testUser=['name'=>'<script>test</script>','role'=>$mode==='observer'?'observer':'presenter'];
$_SESSION=['csrf'=>'test-csrf','broadcast_token'=>str_repeat('a',32)];
$_GET=$mode==='unknown'?['program'=>'unknown']:[];
$_POST=['program'=>'god-morgen-vestland','date'=>'2026-10-05','presenter'=>'Thomas',
    'csrf'=>$mode==='csrf'?'bad':'test-csrf','token'=>$mode==='nonce'?'bad':str_repeat('a',32)];
$_SERVER['REQUEST_METHOD']=match($mode) {'get','unknown'=>'GET','method'=>'DELETE',default=>'POST'};
if ($_SERVER['REQUEST_METHOD'] !== 'POST') $_POST=[];
ob_start();
register_shutdown_function(static function() use($mode,$dir) {
    $html=ob_get_clean();$code=http_response_code() ?: 200;
    $expected=match($mode) {'observer','csrf'=>403,'nonce'=>409,'unknown'=>400,'create'=>302,'method'=>405,default=>200};
    $ok=$code===$expected;
    if($mode==='get') $ok=$ok && str_contains($html,'Lag sendeforslag') && str_contains($html,'Robåt – sendeforslag')
        && str_contains($html,'&lt;script&gt;test&lt;/script&gt;') && !str_contains($html,'<script>test</script>');
    $path=$dir.'/studio-private/config/sending-board.json';
    if($mode==='create') {
        $board=json_decode((string)file_get_contents($path),true);
        $ok=$ok && count($board['broadcastDrafts'][0]['blocks'] ?? [])===16 && $board['broadcastDrafts'][0]['status']==='draft';
    } else $ok=$ok && !is_file($path);
    $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($files as $file) { if($file->isDir()) rmdir($file->getPathname()); else unlink($file->getPathname()); }
    rmdir($dir);
    if(!$ok) { fwrite(STDERR,"FAIL broadcast page $mode (HTTP $code)\n$html\n");exit(1); }
    echo "OK broadcast page $mode (HTTP $code)\n";
});
require $dir.'/studio-public/broadcast.php';
