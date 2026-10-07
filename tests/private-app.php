<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/auth/StudioLocalUsers.php';
require_once dirname(__DIR__).'/app/private-app.php';
$checks = 0;
function pa_check(bool $ok, string $message): void
{
    global $checks;
    if (!$ok) throw new RuntimeException($message);
    $checks++;
}
$owner = ['id'=>'fixture-sub', 'provider'=>'entra', 'oid'=>STUDIO_OWNER_OID, 'name'=>'Testredaktør'];
$private = ['auth_mode'=>'entra', 'private_app_enabled'=>true];
pa_check(studio_private_app_mode([]) === 'off', 'Unconfigured must be off');
pa_check(studio_private_app_mode(['private_app_enabled'=>false]) === 'off', 'False must be off');
pa_check(studio_private_app_mode($private) === 'on', 'Explicit Entra opt-in');
foreach (['true', 1, null, [], 'false'] as $invalid) {
    pa_check(studio_private_app_mode(['auth_mode'=>'entra','private_app_enabled'=>$invalid]) === 'invalid', 'Reject non-boolean config');
}
foreach (['demo','vipps',''] as $mode) {
    pa_check(studio_private_app_mode(['auth_mode'=>$mode,'private_app_enabled'=>true]) === 'invalid', 'No fallback provider');
}
pa_check(studio_private_app_filter_user($private, $owner) === $owner, 'Verified owner allowed');
$upper = array_replace($owner, ['oid'=>strtoupper(STUDIO_OWNER_OID)]);
pa_check(studio_private_app_filter_user($private, $upper) === $upper, 'OID case normalized');
pa_check(studio_private_app_filter_user($private, null) === null, 'Anonymous denied');
foreach ([['oid'=>'00000000-0000-0000-0000-000000000000','role'=>'admin'], ['provider'=>'local','role'=>'admin'], ['provider'=>'vipps'], ['provider'=>null], ['oid'=>null]] as $change) {
    pa_check(studio_private_app_filter_user($private, array_replace($owner, $change)) === null, 'No identity/role/provider bypass');
}
pa_check(studio_private_app_filter_user([], $owner) === $owner, 'Existing Studio unchanged while off');
pa_check(studio_private_app_filter_user(['private_app_enabled'=>'true'], $owner) === null, 'Invalid config fails closed');
$manifest = studio_private_app_manifest();
pa_check($manifest['start_url'] === '/mobil.php' && $manifest['display'] === 'standalone', 'PWA launches guarded route');
pa_check(!str_contains(json_encode($manifest), STUDIO_OWNER_OID), 'Manifest must not contain owner identity');
$icon = getimagesize(dirname(__DIR__).'/public/assets/studio-app-icon.png');
pa_check($icon !== false && $icon[0] === 512 && $icon[1] === 512, 'Square PNG icon');

function pa_copy(string $source, string $target): void
{
    if (!is_dir($target) && !mkdir($target, 0700, true) && !is_dir($target)) throw new RuntimeException('Cannot create fixture');
    foreach (new DirectoryIterator($source) as $entry) {
        if ($entry->isDot() || $entry->isLink()) continue;
        $dest = $target.'/'.$entry->getFilename();
        if ($entry->isDir()) pa_copy($entry->getPathname(), $dest);
        elseif (!copy($entry->getPathname(), $dest)) throw new RuntimeException('Cannot copy fixture');
    }
}
function pa_remove(string $path): void
{
    if (!is_dir($path)) return;
    foreach (new DirectoryIterator($path) as $entry) {
        if ($entry->isDot()) continue;
        if ($entry->isDir() && !$entry->isLink()) pa_remove($entry->getPathname());
        else unlink($entry->getPathname());
    }
    rmdir($path);
}
$tmp = sys_get_temp_dir().'/rr-private-app-'.bin2hex(random_bytes(8));
$server = null;
try {
    mkdir($tmp, 0700);
    // Never copy real config, sessions, editorial files or credentials.
    pa_copy(dirname(__DIR__).'/app', $tmp.'/app');
    pa_copy(dirname(__DIR__).'/public', $tmp.'/public');
    mkdir($tmp.'/config', 0700); mkdir($tmp.'/sessions', 0700);
    copy(dirname(__DIR__).'/config/example.php', $tmp.'/config/example.php');
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if (!$socket) throw new RuntimeException('Cannot reserve test port');
    $address = stream_socket_get_name($socket, false); fclose($socket);
    $base = 'http://'.$address;
    $cfg = ['site_mode'=>'app', 'auth_mode'=>'entra', 'base_url'=>$base,
        'tenant_id'=>'00000000-0000-4000-8000-000000000001', 'client_id'=>'00000000-0000-4000-8000-000000000002',
        'client_secret'=>'synthetic-not-a-real-secret', 'private_app_enabled'=>true];
    $writeConfig = static function (array $value) use ($tmp): void {
        file_put_contents($tmp.'/config/local.php', '<?php return '.var_export($value, true).';');
    };
    $writeConfig($cfg);
    $marker = 'SYNTHETIC-PRIVATE-CASE-ONLY';
    $board = ['items'=>[['id'=>'aaaaaaaaaaaaaaaa', 'title'=>$marker, 'status'=>'draft', 'sourceName'=>'Testkilde', 'channel'=>'both']]];
    file_put_contents($tmp.'/config/sending-board.json', json_encode($board));
    $before = hash_file('sha256', $tmp.'/config/sending-board.json');
    $session = static function (array $user, int $expires) use ($tmp): string {
        $id = bin2hex(random_bytes(16));
        $code = 'session_name("rubben_studio"); session_id($argv[1]); session_start(); $_SESSION=json_decode($argv[2],true); session_write_close();';
        $command = [PHP_BINARY, '-d', 'session.save_path='.$tmp.'/sessions', '-r', $code, $id,
            json_encode(['user'=>$user,'expires'=>$expires,'csrf'=>str_repeat('c',64)])];
        $p = proc_open($command, [0=>['pipe','r'],1=>['file',$tmp.'/seed.log','a'],2=>['file',$tmp.'/seed.log','a']], $pipes);
        if (!is_resource($p)) throw new RuntimeException('Cannot seed session');
        fclose($pipes[0]);
        if (proc_close($p) !== 0) throw new RuntimeException('Session seed failed');
        return $id;
    };
    $ownerSid = $session($owner, time()+600);
    $otherSid = $session(array_replace($owner,['oid'=>'00000000-0000-4000-8000-000000000003','role'=>'admin']), time()+600);
    $expiredSid = $session($owner, time()-1);
    $env = getenv();
    foreach (['STUDIO_SITE_MODE','STUDIO_AUTH_MODE','STUDIO_BASE_URL','ENTRA_TENANT_ID','ENTRA_CLIENT_ID','ENTRA_CLIENT_SECRET','OPENAI_API_KEY','VIPPS_CLIENT_ID','VIPPS_CLIENT_SECRET','VIPPS_ALLOWED_PHONES'] as $key) unset($env[$key]);
    $server = proc_open([PHP_BINARY,'-d','session.save_path='.$tmp.'/sessions','-d','opcache.enable=0','-S',$address,'-t',$tmp.'/public'],
        [0=>['pipe','r'],1=>['file',$tmp.'/server.log','a'],2=>['file',$tmp.'/server.log','a']],$pipes,$tmp,$env);
    if (!is_resource($server)) throw new RuntimeException('Cannot start HTTP fixture');
    fclose($pipes[0]);
    $request = static function (string $path, ?string $sid = null, string $method = 'GET', string $data = '') use ($base): array {
        $headers = "Connection: close\r\n";
        if ($sid !== null) $headers .= 'Cookie: rubben_studio='.$sid."\r\n";
        if ($method === 'POST') $headers .= "Content-Type: application/x-www-form-urlencoded\r\n";
        $context = stream_context_create(['http'=>['method'=>$method,'header'=>$headers,'content'=>$data,'timeout'=>4,'ignore_errors'=>true,'follow_location'=>0]]);
        $body = @file_get_contents($base.$path, false, $context);
        $lines = $http_response_header ?? [];
        preg_match('/^HTTP\/\S+ (\d+)/', $lines[0] ?? '', $match);
        return [(int)($match[1] ?? 0), $body === false ? '' : $body, implode("\n", $lines)];
    };
    for ($i=0;$i<50;$i++) { if ($request('/login.php')[0] > 0) break; usleep(20000); }
    [$code,$body,$headers] = $request('/mobil.php');
    pa_check($code === 303 && str_contains($headers,'/login.php') && !str_contains($body,$marker), 'Anonymous mobile blocked');
    foreach (['/mobil.php','/control.php','/sending.php','/case.php?item=aaaaaaaaaaaaaaaa','/producer-api.php'] as $path) {
        [$code,$body] = $request($path, $otherSid);
        pa_check(in_array($code,[303,401,403],true) && !str_contains($body,$marker), 'Wrong owner blocked: '.$path);
    }
    [$code,$body,$headers] = $request('/mobil.php', $ownerSid);
    pa_check($code === 200 && str_contains($body,$marker), 'Owner sees actual fixture worklist');
    pa_check(str_contains(strtolower($headers),'cache-control: no-store'), 'Authenticated page not cacheable');
    pa_check(str_contains($headers,'Content-Security-Policy:') && str_contains($headers,"frame-ancestors 'none'"), 'CSP retained');
    pa_check(str_contains($body,'/app-manifest.php') && str_contains($body,'aria-label="Mobilmeny"'), 'PWA and mobile navigation connected');
    [$code,,$headers] = $request('/', $ownerSid);
    pa_check($code === 303 && str_contains($headers,'/mobil.php'), 'Login root returns to mobile');
    pa_check($request('/mobil.php',$expiredSid)[0] === 303, 'Expired session denied');
    pa_check($request('/mobil.php',$ownerSid,'POST','action=approve')[0] === 405, 'Landing cannot approve or publish');
    [$code,$body,$headers] = $request('/app-manifest.php');
    pa_check($code === 200 && str_contains($headers,'application/manifest+json') && json_decode($body,true) === $manifest, 'Manifest HTTP and JSON');
    pa_check(!str_contains($body,$marker) && !str_contains($body,STUDIO_OWNER_OID), 'Manifest contains no private data');
    pa_check($request('/app-manifest.php',null,'POST')[0] === 405, 'Manifest is read-only');
    [$code,$body] = $request('/assets/studio-app-icon.png');
    pa_check($code === 200 && str_starts_with($body,"\x89PNG"), 'Icon served');
    pa_check($request('/logout.php',$ownerSid,'POST','csrf=bad')[0] === 403, 'Logout CSRF retained');
    pa_check($request('/mobil.php',$ownerSid)[0] === 200, 'Bad logout cannot end valid session');
    pa_check($request('/logout.php',$ownerSid,'POST','csrf='.str_repeat('c',64))[0] === 303, 'Explicit logout works');
    pa_check($request('/mobil.php',$ownerSid)[0] === 303, 'Logged-out session denied');
    pa_check(hash_file('sha256',$tmp.'/config/sending-board.json') === $before, 'HTTP tests never mutate editorial fixture');
    $writeConfig(array_replace($cfg,['private_app_enabled'=>false]));
    pa_check($request('/mobil.php?private_app_enabled=true')[0] === 503, 'No URL opt-in');
    pa_check($request('/app-manifest.php')[0] === 503, 'Manifest disabled when off');
    [$code,$body] = $request('/login.php');
    pa_check($code === 200 && !str_contains($body,'/app-manifest.php'), 'Default Studio head unchanged');
    $writeConfig(array_replace($cfg,['private_app_enabled'=>'true']));
    pa_check($request('/login.php')[0] === 503, 'Invalid private config closes bootstrap');
    $writeConfig(array_replace($cfg,['auth_mode'=>'demo']));
    pa_check($request('/mobil.php')[0] === 503, 'No demo fallback for private app');
    echo "Private app: $checks checks passed. Synthetic sessions; no real identity-provider login.\n";
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    pa_remove($tmp);
}
