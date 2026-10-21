<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/music-library-cover.php';
$user=current_user();if(!$user){http_response_code(401);exit;}
header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; sandbox");
$method=$_SERVER['REQUEST_METHOD']??'GET';
if($method==='POST'){
    if(!music_library_can_upload($user)||!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf'],$_POST['csrf'])){http_response_code(403);exit;}
    $id=$_POST['id']??'';
    try{
        foreach(['id','version','revision','mode']as$k)if(!is_string($_POST[$k]??null))throw new InvalidArgumentException('Ugyldig coverhandling.');
        if(!preg_match('/^[a-f0-9]{32}$/D',$id)||!preg_match('/^[0-9]{1,8}$/D',$_POST['revision']))throw new InvalidArgumentException('Ugyldig coverhandling.');
        $bytes=null;
        if($_POST['mode']==='manual'){
            $f=$_FILES['cover']??null;
            if(!is_array($f)||($f['error']??null)!==UPLOAD_ERR_OK||!is_string($f['tmp_name']??null)||!is_uploaded_file($f['tmp_name'])||is_link($f['tmp_name'])||filesize($f['tmp_name'])>MUSIC_COVER_LIMIT)throw new InvalidArgumentException('Velg et JPEG- eller PNG-cover på inntil 2 MiB.');
            $bytes=file_get_contents($f['tmp_name']);if($bytes===false)throw new RuntimeException('Cover kunne ikke leses.');
        }
        music_cover_save($id,$_POST['version'],(int)$_POST['revision'],$_POST['mode'],$bytes,$user,$_POST['csrf'],$_SESSION['csrf']);
        $message=$_POST['mode']==='none'?'Cover fjernet. Plassholder vises.':'Albumcover er lagret. Originalfilen er uendret.';
    }catch(InvalidArgumentException $e){$message=$e->getMessage();}catch(Throwable){$message='Cover kunne ikke lagres. Prøv igjen etter ny innlasting.';}
    $_SESSION['music_library_results']=[['name'=>'Albumcover','message'=>$message]];
    redirect('/music-library.php?detail=1'.(is_string($id)&&preg_match('/^[a-f0-9]{32}$/D',$id)?'&track='.$id:'').'#album-cover');
}
if(!in_array($method,['GET','HEAD'],true)){http_response_code(405);header('Allow: GET, HEAD, POST');exit;}
$cover=null;
try{
    $id=$_GET['id']??null;$version=$_GET['version']??null;
    if($id!==null){
        if(!is_string($id)||!is_string($version)||!preg_match('/^[a-f0-9]{32}$/D',$id)||!preg_match('/^[a-f0-9]{64}$/D',$version)){http_response_code(400);exit;}
        $track=null;foreach(music_library_read()as$t)if($t['id']===$id&&$t['sha256']===$version){$track=$t;break;}
        if(!$track){http_response_code(404);exit;}
        $cover=music_cover_read($track);
    }
}catch(Throwable){$cover=null;}
if(isset($cover['bytes'])){header('Content-Type: '.$cover['mime']);header('Content-Length: '.strlen($cover['bytes']));if($method!=='HEAD')echo $cover['bytes'];exit;}
header('Content-Type: image/svg+xml; charset=utf-8');
if($method!=='HEAD')echo '<svg xmlns="http://www.w3.org/2000/svg" width="240" height="240" viewBox="0 0 240 240"><rect width="240" height="240" rx="20" fill="#0d2235"/><circle cx="120" cy="100" r="52" fill="#183b52"/><circle cx="120" cy="100" r="18" fill="#ed1640"/><path d="M115 88v24l19-12z" fill="white"/><text x="120" y="184" text-anchor="middle" fill="white" font-family="sans-serif" font-size="19" font-weight="bold">RADIO RUBBEN</text><text x="120" y="210" text-anchor="middle" fill="#aac6da" font-family="sans-serif" font-size="13">Cover mangler</text></svg>';
