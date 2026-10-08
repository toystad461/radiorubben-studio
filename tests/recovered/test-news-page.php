<?php
declare(strict_types=1);
$mode = $argv[1] ?? 'get';
$render=in_array($mode,['render-choices','render-bulletin'],true);
$tmp = sys_get_temp_dir() . '/news-page-' . bin2hex(random_bytes(4));
mkdir($tmp . '/app/views', 0700, true);
mkdir($tmp . '/app/integrations', 0700, true);
mkdir($tmp . '/config', 0700, true);
mkdir($tmp . '/public', 0700, true);
foreach (['board', 'programs','audio-profiles','stylebook','news-script', 'story-script', 'source-identity','case-workflow','web-publish','news-publication','bulletin','weather','weather-script','audio-workflow','audio-processing','audio-pronunciation','audio-storage'] as $name)
    copy(dirname(__DIR__, 2) . '/app/' . $name . '.php', $tmp . '/app/' . $name . '.php');
copy(dirname(__DIR__,2).'/app/integrations/ElevenLabs.php',$tmp.'/app/integrations/ElevenLabs.php');
copy(dirname(__DIR__, 2) . '/public/sending.php', $tmp . '/public/sending.php');
file_put_contents($tmp . '/app/bootstrap.php', '<?php
function current_user() { return $GLOBALS["mode"] === "guest" ? null : ["name"=>"Test editor","role"=>"admin"]; }
function studio_can($user, $permission) { return !in_array($GLOBALS["mode"],["observer","bulletin-observer"],true); }
function escape($text) { return htmlspecialchars((string)$text, ENT_QUOTES, "UTF-8"); }
function redirect($url) { $GLOBALS["redirected"] = $url; exit; }
$config = [];
');
file_put_contents($tmp . '/app/editorial-memory.php', '<?php function studio_memory_context($board, $program) { return []; }');
file_put_contents($tmp . '/app/producer.php', '<?php');
foreach (['head', 'sidebar', 'account'] as $name) file_put_contents($tmp . '/app/views/' . $name . '.php', '');
$item = ['id'=>'1234567890abcdef', 'originId'=>'fixture', 'title'=>'Test news', 'sourceName'=>'Bømlo kommune',
    'sourceUrl'=>'https://www.bomlo.kommune.no/aktuelt-og-kunngjeringar/test.123.aspx', 'sourceAt'=>gmdate('c'),
    'summary'=>'Kildeomtale', 'program'=>'god-morgen-vestland', 'script'=>'Test script.', 'notes'=>'',
    'status'=>'draft', 'verified'=>false, 'approvedBy'=>null, 'revision'=>1,
    'sourceCheck'=>['status'=>'needs_review', 'issues'=>['<script>alert(1)</script>'], 'segments'=>[]]];
if($render){
    foreach(['head','sidebar','account','bulletin']as$name)copy(dirname(__DIR__,2).'/app/views/'.$name.'.php',$tmp.'/app/views/'.$name.'.php');
    file_put_contents($tmp.'/app/bootstrap.php', '
function icon(...$args){return "";} function studio_roles(){return ["admin"=>"Administrator"];}
',FILE_APPEND);
}
$path = $tmp . '/config/sending-board.json';
file_put_contents($path, json_encode(['items'=>[$item]]));
if($render){
    require_once $tmp.'/app/audio-processing.php';
    $item['script']='Dette melder Bømlo kommune. Kommunen inviterer til møte om biblioteket.';$item['status']='ready';$item['verified']=true;$item['approvedBy']='Test editor';
    $text=str_repeat('Kommunen inviterer til møte om biblioteket. ',5);
    $item['sourceCheck']=['status'=>'passed','policy'=>STUDIO_NEWS_POLICY,'checkedAt'=>gmdate('c'),'fingerprint'=>studio_news_fingerprint($item,$item['script']),'source'=>['url'=>$item['sourceUrl'],'text'=>$text,'sha256'=>hash('sha256',$text),'fetchedAt'=>gmdate('c'),'kind'=>'original_article']];
    $second=$item;$second['id']='2222222222222222';$second['title']='Andre nyhet';$second['sourceCheck']['fingerprint']=studio_news_fingerprint($second,$second['script']);
    file_put_contents($path,json_encode(['items'=>[$item,$second]]));
    if($mode==='render-bulletin')$bulletinId=rr_bulletin_create([$item['id'],$second['id']],rr_bulletin_next_hour(),'none',['name'=>'Test editor','role'=>'admin'],$path);
}
$before = file_get_contents($path);
$_SESSION = ['csrf'=>'fixture-csrf']; $_GET = isset($bulletinId)?['item'=>$bulletinId]:[]; $_POST = [];
$_SERVER['REQUEST_METHOD'] = $mode === 'method' ? 'DELETE' : (in_array($mode, ['observer', 'csrf', 'forged','bulletin-csrf','bulletin-observer'], true) ? 'POST' : 'GET');
if ($_SERVER['REQUEST_METHOD'] === 'POST') $_POST = ['csrf'=>in_array($mode,['csrf','bulletin-csrf'],true) ? 'bad' : 'fixture-csrf',
    'id'=>$item['id'], 'revision'=>1, 'action'=>str_starts_with($mode,'bulletin-')?'bulletin_create':($mode === 'forged' ? 'source_checked' : 'generate'),
    'sourceCheck'=>['status'=>'passed']];
ob_start();
register_shutdown_function(static function () use ($tmp, $mode, $path, $before,$render): void {
    $html = ob_get_clean(); $ok = file_get_contents($path) === $before;
    if ($mode === 'get') $ok = $ok && str_contains($html, 'Lag og kildekontroller nyhetsmanus')
        && str_contains($html, 'Robåtens kildekontroll') && str_contains($html, '&lt;script&gt;alert(1)&lt;/script&gt;')
        && !str_contains($html, '<script>alert(1)</script>');
    elseif($render)$ok=$ok&&str_contains($html,'Lag nyhetssending')&&($mode!=='render-bulletin'||str_contains($html,'Godkjenn samlet manus'));
    elseif ($mode === 'guest') $ok = $ok && ($GLOBALS['redirected'] ?? '') === '/login.php';
    elseif ($mode === 'method') $ok = $ok && http_response_code() === 405;
    elseif ($mode === 'forged') $ok = $ok && ($_SESSION['sending_error'] ?? '') === 'Ukjent handling.';
    else $ok = $ok && http_response_code() === 403;
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    rmdir($tmp);
    if (!$ok) { fwrite(STDERR, "News page $mode failed\n"); exit(1); }
    if($render){echo $html;return;}
    echo "News page $mode OK\n";
});
require $tmp . '/public/sending.php';
