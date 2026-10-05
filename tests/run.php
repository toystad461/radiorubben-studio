<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/config.php';
require dirname(__DIR__).'/app/helpers.php';
require dirname(__DIR__).'/app/integrations/RobotInbox.php';
require dirname(__DIR__).'/vendor/autoload.php';
require dirname(__DIR__).'/app/auth/EntraClient.php';
function check(bool $ok, string $label): void { if (!$ok) throw new RuntimeException($label); echo "PASS: $label\n"; }
$root = dirname(__DIR__);
foreach (['app', 'public', 'config', 'tests', 'scripts'] as $dir) {
 foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir, FilesystemIterator::SKIP_DOTS)) as $file) {
  if ($file->getExtension() !== 'php') continue;
  exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file->getPathname()).' 2>&1', $output, $code);
  if ($code !== 0) throw new RuntimeException(implode("\n", $output));
 }
}
check(true, 'PHP syntax');
$valid = ['auth_mode'=>'entra','base_url'=>'http://localhost:8080','tenant_id'=>'00000000-0000-0000-0000-000000000001','client_id'=>'00000000-0000-0000-0000-000000000002','client_secret'=>'test-only'];
check(config_valid(['auth_mode'=>'demo']), 'explicit demo mode');
check(config_valid($valid), 'local Entra configuration');
check(config_valid(array_replace($valid, ['base_url'=>'https://studio.radiorubben.no'])), 'production URL');
foreach (['tenant_id', 'client_id', 'client_secret', 'base_url'] as $key) check(!config_valid(array_replace($valid, [$key=>''])), 'missing '.$key.' fails closed');
foreach (['http://example.com', 'https://user@example.com', 'https://example.com?x=1'] as $url) check(!config_valid(array_replace($valid,['base_url'=>$url])), 'reject unsafe base URL');
check(!config_valid(array_replace($valid,['tenant_id'=>'common'])), 'reject multi-tenant login');
check(!config_valid(['auth_mode'=>'other']), 'invalid mode fails closed');
check(escape('<script>"') === '&lt;script&gt;&quot;', 'escape displayed account names');
$feedPath=tempnam(sys_get_temp_dir(),'rr-feed-');
file_put_contents($feedPath,json_encode(['schemaVersion'=>1,'items'=>[
 ['event'=>['id'=>'football:test:1:finished','type'=>'football.match.finished','editorialStatus'=>'review','facts'=>[],'source'=>['url'=>'https://www.fotball.no/fotballdata/kamp/?fiksId=1']], 'draft'=>['eventId'=>'football:test:1:finished','status'=>'review','title'=>'Test','body'=>'Utkast']],
 ['event'=>['id'=>'evil','type'=>'football.match.finished','editorialStatus'=>'review','facts'=>[],'source'=>['url'=>'javascript:alert(1)']], 'draft'=>['eventId'=>'evil','status'=>'review','title'=>'Feil','body'=>'Feil']]
]]));
check(count(robot_inbox_items($feedPath))===1,'robot inbox validates source and schema');
file_put_contents($feedPath,json_encode(['schemaVersion'=>1,'items'=>[
 ['event'=>['id'=>'news:test:1','type'=>'news.item.discovered','editorialStatus'=>'new','facts'=>['publishedAt'=>'2026-09-25T12:00:00Z'],'source'=>['url'=>'https://www.bomlo.kommune.no/aktuelt-og-kunngjeringar/test.123.aspx']], 'draft'=>['eventId'=>'news:test:1','status'=>'review','title'=>'Kommunesak','body'=>'Kort kildebeskrivelse']]
]]));
check(count(robot_inbox_items($feedPath))===1,'municipal news appears as source card');
$fixtureDirectory = sys_get_temp_dir() . '/rr-production-' . bin2hex(random_bytes(8));
mkdir($fixtureDirectory, 0700);
try {
 $newsFixture = json_decode((string) file_get_contents($feedPath), true);
 file_put_contents($fixtureDirectory . '/robot-news.json', json_encode($newsFixture));
 $duplicate = $newsFixture;
 $duplicate['items'][0]['draft']['title'] = 'Older duplicate';
 file_put_contents($fixtureDirectory . '/robot-inbox.json', json_encode($duplicate));
 $productionItems = robot_production_items($fixtureDirectory);
 check(count($productionItems) === 1 && $productionItems[0]['draft']['title'] === 'Kommunesak', 'shared event ID deduplicates exports with news priority');
 $productionItems[0]['draft']['title'] = '<script>alert(1)</script>';
 ob_start(); require $root . '/app/views/production-inbox.php'; $panel = ob_get_clean();
 check(str_contains($panel, 'data-event-id="news:test:1"') && !str_contains($panel, '<script>') && str_contains($panel, '&lt;script&gt;'), 'production panel preserves identity and escapes source content');
 unlink($fixtureDirectory . '/robot-news.json');
 unlink($fixtureDirectory . '/robot-inbox.json');
 check(robot_production_items($fixtureDirectory) === [], 'missing exports stay empty without a live RSS fallback');
} finally {
 foreach (['robot-news.json', 'robot-inbox.json'] as $filename) {
  if (is_file($fixtureDirectory . '/' . $filename)) unlink($fixtureDirectory . '/' . $filename);
 }
 rmdir($fixtureDirectory);
}
unlink($feedPath);
require dirname(__DIR__).'/app/integrations/MunicipalityRss.php';
$sampleRss='<rss><channel><item><title>Kommunesak</title><link>https://www.bomlo.kommune.no/aktuelt-og-kunngjeringar/test.123.aspx</link><guid>aid123</guid><description>Kort omtale.</description><pubDate>Fri, 25 Sep 2026 12:12:30 GMT</pubDate></item></channel></rss>';
check(count(municipality_rss_parse($sampleRss,'2026-09-27T10:00:00Z'))===1,'municipal RSS parses source card');
check(count(municipality_rss_parse(str_replace('www.bomlo.kommune.no','example.org',$sampleRss),'2026-09-27T10:00:00Z'))===0,'external RSS link rejected');

function request(string $path, string $method='GET', string $body=''): array {
 $c = curl_init('http://127.0.0.1:8197'.$path);
 curl_setopt_array($c, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>3,CURLOPT_CUSTOMREQUEST=>$method]);
 if ($method==='POST') curl_setopt($c,CURLOPT_POSTFIELDS,$body);
 $response=curl_exec($c);$code=curl_getinfo($c,CURLINFO_RESPONSE_CODE);curl_close($c);
 return [$code, (string)$response];
}
function server(array $env, callable $test): void {
 global $root;
 $log=tempnam(sys_get_temp_dir(),'rubben-test-');
 $proc=proc_open([PHP_BINARY,'-S','127.0.0.1:8197','-t',$root.'/public'],[0=>['pipe','r'],1=>['file',$log,'a'],2=>['file',$log,'a']],$pipes,$root,array_merge(getenv(),['STUDIO_SITE_MODE'=>'app'],$env));
 if (!is_resource($proc)) throw new RuntimeException('Server failed');
 try {
  $ready=false;
  for($i=0;$i<50;$i++){usleep(100000);$socket=@fsockopen('127.0.0.1',8197);if($socket){fclose($socket);$ready=true;break;}}
  check($ready,'test server ready');$test();
 } finally {fclose($pipes[0]);proc_terminate($proc);proc_close($proc);unlink($log);}
}
server(['STUDIO_SITE_MODE'=>'coming-soon'],function(){
 foreach(['/', '/index.php', '/login.php', '/auth/start.php', '/auth/callback.php?code=fake', '/logout.php', '/robot.php', '/?preview=1'] as $path){
  [$code,$html]=request($path);check($code===503 && str_contains($html,'Vi klargjør det nye arbeidsrommet') && !str_contains($html,'Åpne demonstrasjonen') && !str_contains($html,'Set-Cookie:'),'waiting page blocks entry: '.$path);
 }
 check(request('/assets/studio.css')[0]===200,'waiting page assets accessible');
});
server(['STUDIO_AUTH_MODE'=>'demo'],function(){
 [$code,$html]=request('/');check($code===200 && str_contains($html,'Demonstrasjon') && !str_contains($html,'integrations.map'),'demo dashboard rendered');
 check(request('/login.php')[0]===200,'login explanation');
 check(request('/robot.php')[0]===403,'demo cannot view robot inbox');
 check(!str_contains($html, 'id="produksjon"'), 'demo does not expose production inbox');
 check(request('/assets/studio.css')[0]===200,'stylesheet served');
 check(request('/auth/start.php')[0]===503,'demo cannot initiate auth');
 check(request('/auth/callback.php?code=fake&state=fake')[0]===503,'demo rejects callback');
 check(request('/logout.php')[0]===405,'logout requires POST');
 check(request('/logout.php','POST','csrf=fake')[0]===403,'logout requires valid CSRF');
 foreach(['/config/example.php','/app/config.php','/composer.json','/.git/config'] as $path)check(request($path)[0]===404,'private file inaccessible: '.$path);
});
server(['STUDIO_AUTH_MODE'=>'entra','STUDIO_BASE_URL'=>'http://localhost:8080','ENTRA_TENANT_ID'=>$valid['tenant_id'],'ENTRA_CLIENT_ID'=>$valid['client_id'],'ENTRA_CLIENT_SECRET'=>'test-only'],function(){
 [$code,$html]=request('/');check($code===303 && str_contains($html,'Location: /login.php'),'configured dashboard requires session');
 check(str_contains(request('/login.php')[1],'Logg inn med Microsoft'),'Microsoft button enabled');
 check(request('/auth/start.php?code=fake')[0]===400,'start rejects injected callback parameters');
 [$code,$html]=request('/auth/callback.php?code=fake&state=fake');check($code===303 && str_contains($html,'error=signin'),'unsolicited callback rejected before token request');
});
server(['STUDIO_AUTH_MODE'=>'entra','ENTRA_CLIENT_SECRET'=>''],function(){check(request('/')[0]===503,'partial configuration never falls back to demo');});
echo "All tests passed. Real tenant login remains a deployment check.\n";
