<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/music-library-preparation.php';
require dirname(__DIR__).'/app/music.php';
$user=current_user();if(!$user){http_response_code(401);exit('Logg inn i Studio.');}if(!studio_can($user,'produce')){http_response_code(403);exit('Rollen har ikke lydtilgang.');}
if(!in_array($_SERVER['REQUEST_METHOD']??'',['GET','HEAD'],true)){http_response_code(405);header('Allow: GET, HEAD');exit;}
$id=$_GET['id']??null;$version=$_GET['version']??null;if(!is_string($id)||!preg_match('/^[a-f0-9]{32}$/D',$id)||!is_string($version)||!preg_match('/^[a-f0-9]{64}$/D',$version)){http_response_code(404);exit('Ugyldig lydreferanse.');}
session_write_close();$h=null;
try{$track=null;foreach(music_library_read()as$t)if($t['id']===$id){$track=$t;break;}if(!$track){http_response_code(404);exit('Låten finnes ikke.');}if(!hash_equals($track['sha256'],$version)){http_response_code(409);exit('Lydreferansen er endret.');}
 $h=music_library_verified_handle($track,music_library_root());$size=$track['bytes'];try{[$start,$end,$partial]=music_range($_SERVER['HTTP_RANGE']??null,$size);}catch(InvalidArgumentException){http_response_code(416);header('Content-Range: bytes */'.$size);exit;}
 http_response_code($partial?206:200);header('Content-Type: audio/mpeg');header('Accept-Ranges: bytes');header('Content-Length: '.($end-$start+1));header('Content-Disposition: inline; filename="'.$id.'.mp3"');if($partial)header("Content-Range: bytes $start-$end/$size");if($_SERVER['REQUEST_METHOD']==='HEAD')exit;
 fseek($h,$start);$remaining=$end-$start+1;while($remaining>0&&!feof($h)&&!connection_aborted()){$data=fread($h,min(65536,$remaining));if($data===false||$data==='')break;echo $data;$remaining-=strlen($data);}
}catch(Throwable){http_response_code(503);header('Content-Type: text/plain; charset=utf-8');exit('Lydfilen kan ikke bekreftes.');}finally{if(is_resource($h))fclose($h);}
