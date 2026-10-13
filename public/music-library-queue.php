<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/music-library-queue.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');$user=current_user();
if(!$user||!music_library_can_upload($user)){http_response_code($user?403:401);echo '{"error":"Logg inn med produsenttilgang."}';exit;}
try{
 $method=$_SERVER['REQUEST_METHOD']??'';if($method==='GET'){echo json_encode(['jobs'=>music_audio_status()],JSON_THROW_ON_ERROR);exit;}if($method!=='POST'){http_response_code(405);header('Allow: GET, POST');echo '{"error":"Ugyldig metode."}';exit;}
 $raw=file_get_contents('php://input',false,null,0,16385);if($raw===false||strlen($raw)>16384)throw new InvalidArgumentException('For stor forespørsel.');$v=json_decode($raw,true,12,JSON_THROW_ON_ERROR);if(!is_array($v)||!is_string($v['csrf']??null)||!is_string($v['action']??null))throw new InvalidArgumentException('Ugyldig køhandling.');music_audio_authorize($user,$v['csrf'],$_SESSION['csrf']);
 if($v['action']==='next'){echo json_encode(['job'=>music_audio_claim($user,$v['csrf'],$_SESSION['csrf'])],JSON_THROW_ON_ERROR);exit;}
 if($v['action']==='retry'){if(!is_string($v['id']??null)||!is_string($v['version']??null)||!is_int($v['revision']??null))throw new InvalidArgumentException('Ugyldig jobb.');$t=music_audio_find(music_library_root(),$v['id']);if($t['sha256']!==$v['version']||$t['revision']!==$v['revision'])throw new InvalidArgumentException('Låten er endret.');music_audio_enqueue($t['id'],$t['sha256'],$user,$v['csrf'],$_SESSION['csrf'],null,true);echo '{"ok":true}';exit;}
 foreach(['id','token']as$k)if(!is_string($v[$k]??null))throw new InvalidArgumentException('Ugyldig jobb.');if(!preg_match('/^[a-f0-9]{32}$/D',$v['id'])||!preg_match('/^[a-f0-9]{48}$/D',$v['token']))throw new InvalidArgumentException('Ugyldig jobb.');
 if($v['action']==='heartbeat'){music_audio_heartbeat($v['id'],$v['token'],$user,$v['csrf'],$_SESSION['csrf']);echo '{"ok":true}';exit;}
 if(in_array($v['action'],['pause','fail'],true)){music_audio_release($v['id'],$v['token'],$user,$v['csrf'],$_SESSION['csrf'],$v['action']==='fail');echo '{"ok":true}';exit;}
 if($v['action']!=='complete'||!is_string($v['version']??null)||!preg_match('/^[a-f0-9]{64}$/D',$v['version'])||!is_int($v['revision']??null)||$v['revision']<0||!is_array($v['measurements']??null)||!is_array($v['profile']??null))throw new InvalidArgumentException('Ugyldig analyseresultat.');
 $t=music_audio_complete($v['id'],$v['version'],$v['revision'],$v['token'],$v['measurements'],$v['profile'],$user,$v['csrf'],$_SESSION['csrf']);echo json_encode(['saved'=>true,'revision'=>$t['revision']],JSON_THROW_ON_ERROR);
}catch(InvalidArgumentException|JsonException){http_response_code(409);echo '{"error":"Jobben eller låten er endret. Oppdater køen før nytt forsøk."}';}
catch(Throwable){http_response_code(503);echo '{"error":"Køhandlingen kunne ikke bekreftes. Kontroller køen før nytt forsøk."}';}
