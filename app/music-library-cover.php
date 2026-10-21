<?php
declare(strict_types=1);
require_once __DIR__.'/music-library-preparation.php';
const MUSIC_COVER_LIMIT=2097152;

function music_cover_validate(string $bytes):array {
    if(strlen($bytes)<8||strlen($bytes)>MUSIC_COVER_LIMIT)throw new InvalidArgumentException('Coveret kan være maksimalt 2 MiB.');
    $info=@getimagesizefromstring($bytes);
    if(!$info||!in_array($info[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG],true)||$info[0]<1||$info[1]<1||$info[0]>4096||$info[1]>4096||$info[0]*$info[1]>16000000)throw new InvalidArgumentException('Velg et JPEG- eller PNG-bilde på inntil 4096 × 4096 piksler.');
    return ['mime'=>$info[2]===IMAGETYPE_JPEG?'image/jpeg':'image/png','width'=>$info[0],'height'=>$info[1]];
}
function music_cover_directory(string $root,bool $create=false):?string {
    $dir=$root.'/covers';
    if(is_link($dir))throw new RuntimeException('Ugyldig covermappe.');
    if(!is_dir($dir)){
        if(!$create)return null;
        if(!mkdir($dir,0700)&&!is_dir($dir))throw new RuntimeException('Covermappe kunne ikke opprettes.');
    }
    if(realpath($dir)!==$dir)throw new RuntimeException('Ugyldig covermappe.');
    return $dir;
}
function music_cover_read(array $track,?string $root=null):?array {
    $root??=music_library_root();
    if(!preg_match('/^[a-f0-9]{32}$/D',(string)($track['id']??'')))throw new InvalidArgumentException('Ugyldig låt.');
    $dir=music_cover_directory($root);if($dir===null)return null;
    $path=$dir.'/'.$track['id'].'.json';
    if(is_link($path))throw new RuntimeException('Ugyldig coverfil.');
    if(!is_file($path))return null;
    if(filesize($path)>3000000)throw new RuntimeException('Coverfil må kontrolleres.');
    $v=json_decode((string)file_get_contents($path),true,8,JSON_THROW_ON_ERROR);
    if(!is_array($v)||($v['sourceSha256']??null)!==$track['sha256'])return null;
    if(!is_int($v['revision']??null)||$v['revision']<1||!in_array($v['source']??null,['embedded','manual','none'],true))throw new RuntimeException('Coverdata må kontrolleres.');
    if($v['source']==='none')return $v;
    $bytes=base64_decode($v['data']??'',true);
    if($bytes===false||!hash_equals($v['sha256']??'',hash('sha256',$bytes)))throw new RuntimeException('Coverdata er endret.');
    $valid=music_cover_validate($bytes);
    return array_merge($v,$valid,['bytes'=>$bytes]);
}
function music_cover_embedded(array $track,string $root):?string {
    $h=music_library_verified_handle($track,$root);fclose($h);
    require_once dirname(__DIR__).'/vendor/autoload.php';
    $reader=new getID3();$reader->option_save_attachments=MUSIC_COVER_LIMIT;
    $reader->option_tags_html=false;$reader->option_tag_lyrics3=false;
    $info=$reader->analyze($root.'/originals/'.$track['id'].'.mp3');
    $pictures=$info['id3v2']['comments']['picture']??[];
    usort($pictures,fn($a,$b)=>(int)str_contains(strtolower((string)($b['picturetype']??'')),'front')<=>(int)str_contains(strtolower((string)($a['picturetype']??'')),'front'));
    foreach($pictures as$p){if(!is_string($p['data']??null))continue;try{music_cover_validate($p['data']);return $p['data'];}catch(InvalidArgumentException){}}
    return null;
}
/** Independent cover revision avoids invalidating audio analysis and AI jobs. */
function music_cover_save(string $id,string $version,int $revision,string $mode,?string $bytes,?array $user,string $csrf,string $expected,?string $root=null):array {
    if(!music_library_can_upload($user)||$expected===''||!hash_equals($expected,$csrf))throw new InvalidArgumentException('Handlingen kunne ikke kontrolleres.');
    if(!preg_match('/^[a-f0-9]{32}$/D',$id)||!preg_match('/^[a-f0-9]{64}$/D',$version)||$revision<0||!in_array($mode,['manual','embedded','none'],true))throw new InvalidArgumentException('Ugyldig coverhandling.');
    if($mode==='manual')music_cover_validate($bytes??'');
    $root??=music_library_root();
    if(!music_library_directory($root,false)||is_link($root.'/library.lock'))throw new RuntimeException('Biblioteket er utilgjengelig.');
    $library=fopen($root.'/library.lock','rb');if(!$library)throw new RuntimeException('Biblioteket er utilgjengelig.');
    $lock=null;$tmp=null;
    try{
        if(!flock($library,LOCK_SH))throw new RuntimeException('Biblioteket er opptatt.');
        $track=null;foreach(music_library_index($root)['tracks']as$t)if($t['id']===$id){$track=$t;break;}
        if(!$track||$track['sha256']!==$version)throw new InvalidArgumentException('Låten er endret eller fjernet.');
        $h=music_library_verified_handle($track,$root);fclose($h);
        $dir=music_cover_directory($root,true);$lockPath=$dir.'/'.$id.'.lock';
        if(is_link($lockPath))throw new RuntimeException('Ugyldig coverlås.');
        $lock=fopen($lockPath,'c+b');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Coveret er opptatt.');chmod($lockPath,0600);
        $old=music_cover_read($track,$root);
        if(($old['revision']??0)!==$revision)throw new InvalidArgumentException('Coveret er endret. Last siden på nytt.');
        if($mode==='embedded'){
            $bytes=music_cover_embedded($track,$root);
            if($bytes===null)throw new InvalidArgumentException('Fant ikke et støttet innebygd cover. Eksisterende cover er beholdt.');
        }
        $v=['sourceSha256'=>$version,'revision'=>$revision+1,'source'=>$mode,'updatedAt'=>gmdate('c'),'updatedBy'=>(string)($user['id']??$user['role'])];
        if($mode!=='none')$v+=music_cover_validate($bytes)+['sha256'=>hash('sha256',$bytes),'data'=>base64_encode($bytes)];
        $path=$dir.'/'.$id.'.json';if(is_link($path))throw new RuntimeException('Ugyldig coverfil.');
        $tmp=$dir.'/cover-'.bin2hex(random_bytes(12)).'.tmp';$out=fopen($tmp,'xb');if(!$out)throw new RuntimeException('Cover kunne ikke lagres.');
        try{$json=json_encode($v,JSON_THROW_ON_ERROR);if(!chmod($tmp,0600)||fwrite($out,$json)!==strlen($json)||!fflush($out))throw new RuntimeException('Cover kunne ikke lagres.');}finally{fclose($out);}
        if(!rename($tmp,$path))throw new RuntimeException('Cover kunne ikke lagres.');$tmp=null;return $v;
    }finally{if($tmp!==null&&is_file($tmp))unlink($tmp);if(is_resource($lock)){flock($lock,LOCK_UN);fclose($lock);}flock($library,LOCK_UN);fclose($library);}
}
function music_cover_auto(array $track,?array $user,string $csrf,string $expected,?string $root=null):void {
    // Optional enrichment must never fail the music upload or overwrite manual art.
    try{if(music_cover_read($track,$root)!==null)return;music_cover_save($track['id'],$track['sha256'],0,'embedded',null,$user,$csrf,$expected,$root);}catch(Throwable){}
}
function music_cover_url(array $track):string{return '/music-library-cover.php?id='.$track['id'].'&version='.$track['sha256'];}

function music_cover_forget(string $id,?string $root=null):void {
    try {
        if(!preg_match('/^[a-f0-9]{32}$/D',$id))return;
        $root??=music_library_root();$dir=music_cover_directory($root);if($dir===null||is_link($root.'/library.lock'))return;
        $h=fopen($root.'/library.lock','rb');if(!$h)return;
        try {
            if(!flock($h,LOCK_SH))return;
            foreach(music_library_index($root)['tracks']as$t)if($t['id']===$id)return;
            foreach(['.json','.lock']as$ext){$p=$dir.'/'.$id.$ext;if(is_file($p)&&!is_link($p))unlink($p);}
        }finally{flock($h,LOCK_UN);fclose($h);}
    }catch(Throwable){}
}
