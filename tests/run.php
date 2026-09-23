<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/config.php';
require dirname(__DIR__).'/app/helpers.php';
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
 $proc=proc_open([PHP_BINARY,'-S','127.0.0.1:8197','-t',$root.'/public'],[0=>['pipe','r'],1=>['file',$log,'a'],2=>['file',$log,'a']],$pipes,$root,array_merge(getenv(),$env));
 if (!is_resource($proc)) throw new RuntimeException('Server failed');
 try {
  $ready=false;
  for($i=0;$i<50;$i++){usleep(100000);$socket=@fsockopen('127.0.0.1',8197);if($socket){fclose($socket);$ready=true;break;}}
  check($ready,'test server ready');$test();
 } finally {fclose($pipes[0]);proc_terminate($proc);proc_close($proc);unlink($log);}
}
server(['STUDIO_AUTH_MODE'=>'demo'],function(){
 [$code,$html]=request('/');check($code===200 && str_contains($html,'Demonstrasjon') && !str_contains($html,'integrations.map'),'demo dashboard rendered');
 check(request('/login.php')[0]===200,'login explanation');
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
