<?php
declare(strict_types=1);
use RadioRubben\Integrations\AzuraCastClient;
require $root.'/app/integrations/AzuraCastClient.php';

function azuraReject(callable $call, string $label): void {
    try { $call(); } catch (RuntimeException $e) {
        check(!str_contains($e->getMessage(), 'test-only-secret') && $e->getPrevious() === null, $label.' (redacted)');
        return;
    }
    throw new RuntimeException('Expected rejection: '.$label);
}
$fixture = require $root.'/app/integrations/fixtures/azuracast-nowplaying.php';
$mock = new AzuraCastClient([], static function () { throw new LogicException('Mock attempted network'); });
check($mock->isMock() && $mock->nowPlaying() === $fixture && $mock->station() === $fixture['station'], 'AzuraCast mock is deterministic and offline');
check($mock->playlists() === [] && $mock->media() === [] && $mock->queue() === [], 'unconfigured admin reads also stay offline');
$settings = ['azuracast_base_url'=>'https://radio.example.test', 'azuracast_station_id'=>'radio_test', 'azuracast_api_key'=>'test-only-secret'];
$requests = [];
$transport = static function (string $url, array $headers) use (&$requests, $fixture): array {
    $requests[] = [$url, $headers];
    return ['status'=>200, 'body'=>json_encode(str_contains($url,'/nowplaying/') ? $fixture : (str_ends_with($url,'/station/radio_test') ? $fixture['station'] : []))];
};
$client = new AzuraCastClient($settings, $transport);
$client->station(); $client->nowPlaying(); $client->playlists(); $client->media(); $client->queue();
check(array_column($requests, 0) === array_map(static fn($p)=>'https://radio.example.test/api'.$p, ['/station/radio_test','/nowplaying/radio_test','/station/radio_test/playlists','/station/radio_test/files','/station/radio_test/queue']), 'exact read-only routes');
check($requests[0][1] === ['Accept: application/json'] && $requests[1][1] === ['Accept: application/json'], 'public reads never receive API key');
foreach (array_slice($requests, 2) as $request) check(in_array('Authorization: Bearer test-only-secret', $request[1], true), 'authenticated read uses Bearer header');
$withoutKey = new AzuraCastClient(array_replace($settings,['azuracast_api_key'=>'']), $transport);
azuraReject(fn()=>$withoutKey->playlists(), 'admin reads require a key');
check(count($requests) === 5, 'missing key rejected before transport');
foreach (['http://radio.example.test','file:///etc/passwd','https://u:p@host.test','https://host.test/api','https://host.test?key=test-only-secret','https://host.test#frag',"https://host.test\r\nX:secret",'http://127.0.0.1:8085'] as $url) {
    azuraReject(fn()=>new AzuraCastClient(['azuracast_base_url'=>$url]), 'unsafe URL rejected');
}
foreach (['../backend/skip', '1?key=x', "1\n"] as $id) azuraReject(fn()=>new AzuraCastClient(array_replace($settings,['azuracast_station_id'=>$id])), 'station path injection rejected');
azuraReject(fn()=>new AzuraCastClient(array_replace($settings,['azuracast_api_key'=>"key\r\nX:test"])), 'header injection rejected');
$local = ['azuracast_base_url'=>'http://127.0.0.1:8198', 'azuracast_allow_local_http'=>true, 'azuracast_station_id'=>'test', 'azuracast_api_key'=>'test-only-secret'];
check(!(new AzuraCastClient($local))->isMock(), 'explicit local HTTP accepted');
foreach ([301, 302, 401, 403, 404, 429, 500] as $status) {
    azuraReject(fn()=>(new AzuraCastClient($settings, static fn()=>['status'=>$status,'body'=>'test-only-secret']))->nowPlaying(), 'HTTP '.$status.' never falls back to mock');
}
foreach (['<html>test-only-secret</html>', '{}', 'null', '[]', '42', str_repeat('x',2097153)] as $body) {
    azuraReject(fn()=>(new AzuraCastClient($settings, static fn()=>['status'=>200,'body'=>$body]))->nowPlaying(), 'invalid upstream response rejected');
}
azuraReject(fn()=>(new AzuraCastClient($settings, static function(){throw new RuntimeException('test-only-secret');}))->nowPlaying(), 'transport exception stripped');
foreach (['playlists','media','queue'] as $method) azuraReject(fn()=>(new AzuraCastClient($settings, static fn()=>['status'=>200,'body'=>'{}']))->$method(), 'admin response must be a list');
foreach (['skip','request','enqueue','upload','delete'] as $method) check(!method_exists($client,$method), 'no mutation method: '.$method);

// Environment parsing is deliberately exact: only "1" enables these flags.
foreach (['0','false','true','unexpected','1'] as $value) {
    putenv('AZURACAST_TEST_ENABLED='.$value);
    check(load_config()['azuracast_test_enabled'] === ($value === '1'), 'test flag '.$value);
}
putenv('AZURACAST_TEST_ENABLED');
$env = ['STUDIO_AUTH_MODE'=>'entra','STUDIO_BASE_URL'=>'http://localhost:8080','ENTRA_TENANT_ID'=>$valid['tenant_id'],'ENTRA_CLIENT_ID'=>$valid['client_id'],'ENTRA_CLIENT_SECRET'=>'test-only','AZURACAST_TEST_ENABLED'=>'1','AZURACAST_BASE_URL'=>'','AZURACAST_API_KEY'=>'test-only-secret'];
server(array_replace($env,['STUDIO_SITE_MODE'=>'coming-soon']),function(){
    [$code,$html]=request('/azuracast-test.php?preview=1');
    check($code===503 && !str_contains($html,'Set-Cookie:'), 'enabled test cannot open waiting page');
});
server(array_replace($env,['AZURACAST_TEST_ENABLED'=>'0']),function(){check(request('/azuracast-test.php')[0]===404, 'test page disabled by default flag');});
server(array_replace($env,['STUDIO_AUTH_MODE'=>'demo']),function(){check(request('/azuracast-test.php')[0]===403, 'public demo cannot access test data');});
server($env,function(){
    [$code,$html]=request('/azuracast-test.php');
    check($code===303 && str_contains($html,'Location: /login.php') && !str_contains($html,'test-only-secret'), 'test requires Entra session');
});

// Seed a session only in a temporary PHP session directory; production authentication code is unmodified.
$sessionPath = sys_get_temp_dir().'/rubben-azura-'.bin2hex(random_bytes(8));
mkdir($sessionPath,0700);
$sessionId = bin2hex(random_bytes(16));
$expiredId = bin2hex(random_bytes(16));
file_put_contents($sessionPath.'/sess_'.$sessionId, 'user|'.serialize(['name'=>'Test user','id'=>'test']).'expires|'.serialize(time()+600));
file_put_contents($sessionPath.'/sess_'.$expiredId, 'user|'.serialize(['name'=>'Expired','id'=>'test']).'expires|'.serialize(time()-1));
$cookie = 'rubben_studio='.$sessionId;
$upstreamLog = tempnam(sys_get_temp_dir(),'rubben-upstream-');
$upstream = null;
try {
    server($env,function() use($cookie,$expiredId){
        [$code,$html]=request('/azuracast-test.php','GET','',$cookie);
        check($code===200 && str_contains($html,'EKSEMPELDATA') && str_contains($html,'En ny dag (test)'), 'authenticated fixture view renders');
        check(!str_contains($html,'test-only-secret') && str_contains($html,'Cache-Control: no-store'), 'no key or cacheable test data');
        check(request('/azuracast-test.php','POST','skip=1',$cookie)[0]===405,'test page refuses control POST');
        check(request('/azuracast-test.php','GET','','rubben_studio='.$expiredId)[0]===303, 'expired session denied');
    },$sessionPath);
    $upstream=proc_open([PHP_BINARY,'-S','127.0.0.1:8198',$root.'/tests/fixtures/azuracast-router.php'],[0=>['pipe','r'],1=>['file',$upstreamLog,'a'],2=>['file',$upstreamLog,'a']],$upstreamPipes,$root);
    if (!is_resource($upstream)) throw new RuntimeException('Cannot start fake AzuraCast');
    $ready=false;
    for($i=0;$i<50;$i++){usleep(100000);$socket=@fsockopen('127.0.0.1',8198);if($socket){fclose($socket);$ready=true;break;}}
    check($ready,'fake AzuraCast ready');
    $networkClient = new AzuraCastClient($local);
    check($networkClient->nowPlaying()['live']['is_live'] === true, 'real cURL public GET without key');
    check($networkClient->media()[0]['id']===1, 'real cURL authenticated read');
    $connectedEnv=array_replace($env,['AZURACAST_BASE_URL'=>'http://127.0.0.1:8198','AZURACAST_ALLOW_LOCAL_HTTP'=>'1','AZURACAST_STATION_ID'=>'test']);
    server($connectedEnv,function()use($cookie){
        [$code,$html]=request('/azuracast-test.php','GET','',$cookie);
        check($code===200 && str_contains($html,'Live DJ') && str_contains($html,'Test-DJ'), 'connected test shows live status');
        check(str_contains($html,'&lt;script&gt;') && !str_contains($html,'<script>') && !str_contains($html,'test-only-secret'),'upstream text escaped and key absent');
    },$sessionPath);
    server(array_replace($connectedEnv,['AZURACAST_STATION_ID'=>'offline']),function()use($cookie){
        [$code,$html]=request('/azuracast-test.php','GET','',$cookie);
        check($code===200 && str_contains($html,'Offline') && str_contains($html,'Ingen sang oppgitt') && str_contains($html,'Ingen historikk tilgjengelig.'), 'offline null song and empty history handled');
    },$sessionPath);
    server(array_replace($connectedEnv,['AZURACAST_STATION_ID'=>'invalid']),function()use($cookie){
        [$code,$html]=request('/azuracast-test.php','GET','',$cookie);
        check($code===502 && !str_contains($html,'EKSEMPELDATA') && !str_contains($html,'test-only-secret'), 'connection error page does not fabricate live data');
    },$sessionPath);
    foreach (['redirect','invalid','oversized','timeout'] as $id) {
        $start=microtime(true);
        azuraReject(fn()=>(new AzuraCastClient(array_replace($local,['azuracast_station_id'=>$id])))->nowPlaying(), 'real cURL rejects '.$id);
        check(microtime(true)-$start < 5.8, 'bounded request duration');
    }
} finally {
    if(is_resource($upstream)){fclose($upstreamPipes[0]);proc_terminate($upstream);proc_close($upstream);}
    unlink($upstreamLog);
    foreach(glob($sessionPath.'/sess_*') as $file) unlink($file);
    rmdir($sessionPath);
}
