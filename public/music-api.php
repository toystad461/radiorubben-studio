<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/music.php';
require dirname(__DIR__) . '/app/producer.php';
header('Content-Type: application/json; charset=utf-8');
function music_reply(int $status,array $data): never {http_response_code($status);echo json_encode($data,JSON_UNESCAPED_UNICODE);exit;}
if ($config['auth_mode'] !== 'demo' && !studio_can(current_user(), 'produce')) music_reply(403,['error'=>'Rollen din gir ikke tilgang til denne funksjonen.']);
// This private music library is local-only, including when the public site launches.
if(PHP_SAPI!=='cli-server' || !in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true))music_reply(403,['error'=>'Musikkbiblioteket krever lokal Studio-forbindelse.']);
try{$catalog=music_catalog(music_root());}catch(RuntimeException $e){music_reply(503,['error'=>'Musikkmappen kunne ikke leses.']);}
try{$breaks=music_breaks();$breakStatus=$breaks?'Stikkbibliotek klart':'Ingen stikkmappe med lydfiler tilkoblet';}catch(RuntimeException $e){$breaks=[];$breakStatus='Stikkmappen er utilgjengelig';}
$catalog+=$breaks;
if(($_GET['action']??'')==='stream'){
    if(!in_array($_SERVER['REQUEST_METHOD'],['GET','HEAD'],true))music_reply(405,['error'=>'Ugyldig metode.']);
    $token=$_GET['token']??'';
    if(!is_string($token) || !isset($_SESSION['music_token']) || !hash_equals($_SESSION['music_token'],$token))music_reply(403,['error'=>'Ugyldig avspillingsøkt.']);
    $id=$_GET['id']??'';if(!is_string($id)||!isset($catalog[$id]))music_reply(404,['error'=>'Låten finnes ikke.']);
    session_write_close();$t=$catalog[$id];$size=filesize($t['path']);
    try{[$start,$end,$partial]=music_range($_SERVER['HTTP_RANGE']??null,$size);}catch(InvalidArgumentException $e){header('Content-Range: bytes */'.$size);music_reply(416,['error'=>'Ugyldig byteområde.']);}
    header('Content-Type: '.$t['mime']);header('Accept-Ranges: bytes');header('Content-Length: '.($end-$start+1));
    if($partial){http_response_code(206);header("Content-Range: bytes $start-$end/$size");}
    if($_SERVER['REQUEST_METHOD']==='HEAD')exit;
    $handle=fopen($t['path'],'rb');fseek($handle,$start);$remaining=$end-$start+1;
    while($remaining>0 && !feof($handle) && !connection_aborted()){$chunk=fread($handle,min(65536,$remaining));if($chunk===false||$chunk==='')break;echo $chunk;$remaining-=strlen($chunk);}
    fclose($handle);exit;
}
if($_SERVER['REQUEST_METHOD']!=='POST')music_reply(405,['error'=>'Bruk POST.']);
if(!hash_equals($_SESSION['csrf'],$_SERVER['HTTP_X_CSRF_TOKEN']??''))music_reply(403,['error'=>'Last siden på nytt.']);
$raw=file_get_contents('php://input',false,null,0,5001);$data=json_decode($raw,true);
if(strlen($raw)>5000||!is_array($data))music_reply(400,['error'=>'Ugyldig forespørsel.']);
if(($data['action']??'')==='mood')music_reply(200,['schedule'=>music_mood()]);
if(($data['action']??'')==='catalog'){
    $_SESSION['music_token']??=bin2hex(random_bytes(24));
    music_reply(200,['tracks'=>music_public($catalog),'token'=>$_SESSION['music_token'],'schedule'=>music_mood(),'breakStatus'=>$breakStatus]);
}
if(($data['action']??'')!=='choose')music_reply(400,['error'=>'Ugyldig handling.']);
$recent=$data['recent']??[];$wish=$data['wish']??'';
if(!is_array($recent)||count($recent)>20||!is_string($wish)||strlen($wish)>1200)music_reply(400,['error'=>'Ugyldig musikkønske.']);
foreach($recent as $id)if(!is_string($id))music_reply(400,['error'=>'Ugyldig spillehistorikk.']);
if(trim($config['openai_api_key']??'')==='')music_reply(503,['error'=>'AI er ikke konfigurert. Velg låt manuelt.']);
if(time()-($_SESSION['music_ai_at']??0)<5)music_reply(429,['error'=>'Vent litt før neste AI-valg.']);
$_SESSION['music_ai_at']=time();session_write_close();
try{music_reply(200,music_choose($config,$catalog,$recent,$wish));}catch(RuntimeException $e){music_reply(502,['error'=>$e->getMessage()]);}
