<?php
declare(strict_types=1);
require_once __DIR__.'/music-library-preparation.php';
function music_public_artwork_index(string $root):array {
    $p=$root.'/public-artwork/index.json';
    if(!is_file($p)||is_link($p)||filesize($p)>1048576)return [];
    try{$v=json_decode(file_get_contents($p),true,8,JSON_THROW_ON_ERROR);return is_array($v)?$v:[];}catch(Throwable){return [];}
}
function music_public_artwork_url(array $entry,array $index):?string {
    if(($entry['kind']??'music')!=='music')return null;
    $image=$index[$entry['sha256']??'']??null;
    return is_string($image)&&preg_match('/^[a-f0-9]{64}$/D',$image)?'https://5.249.252.78/radio-status.php?cover='.$image:null;
}
/** Decode and re-encode bounded raster pixels: never publish tags or original image metadata. */
function music_public_artwork_jpeg(string $bytes):?string {
    if(strlen($bytes)>2097152)return null;
    $info=@getimagesizefromstring($bytes);
    if(!$info||!in_array($info[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG],true)||min($info[0],$info[1])<1||max($info[0],$info[1])>4096||$info[0]*$info[1]>16000000)return null;
    $src=@imagecreatefromstring($bytes);if(!$src)return null;
    $scale=min(1,512/max($info[0],$info[1]));$w=max(1,(int)round($info[0]*$scale));$h=max(1,(int)round($info[1]*$scale));
    $dst=imagecreatetruecolor($w,$h);imagefill($dst,0,0,imagecolorallocate($dst,21,21,21));imagecopyresampled($dst,$src,0,0,0,0,$w,$h,$info[0],$info[1]);
    ob_start();imagejpeg($dst,null,85);$out=ob_get_clean();imagedestroy($src);imagedestroy($dst);return $out?:null;
}
/** Only background work extracts art. Public requests never open music originals. */
function music_public_artwork_build(string $root,int $limit=1):array {
    $dir=$root.'/public-artwork';if(is_link($dir))throw new RuntimeException('Invalid artwork directory');
    if(!is_dir($dir)&&!mkdir($dir,0700))throw new RuntimeException('Artwork directory unavailable');
    $lock=fopen($dir.'/build.lock','c+b');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))return [];
    try{
        $index=music_public_artwork_index($root);$count=0;
        foreach(music_library_read($root)as$t){
            if(array_key_exists($t['sha256'],$index))continue;
            if($count++ >= $limit)break;
            $handle=music_library_verified_handle($t,$root);fclose($handle);
            require_once dirname(__DIR__).'/vendor/autoload.php';
            $reader=new getID3();$reader->option_save_attachments=true;$reader->option_tags_html=false;$reader->option_tag_lyrics3=false;
            $info=$reader->analyze($root.'/originals/'.$t['id'].'.mp3');$pictures=$info['comments']['picture']??$info['id3v2']['comments']['picture']??[];
            usort($pictures,fn($a,$b)=>(int)str_contains(strtolower((string)($b['picturetype']??'')),'front')<=>(int)str_contains(strtolower((string)($a['picturetype']??'')),'front'));
            $index[$t['sha256']]=null;
            foreach($pictures as$p){
                if(!is_string($p['data']??null))continue;$jpeg=music_public_artwork_jpeg($p['data']);if($jpeg===null)continue;
                $hash=hash('sha256',$jpeg);$target=$dir.'/'.$hash.'.jpg';
                if(!is_file($target)){file_put_contents($target.'.tmp',$jpeg);chmod($target.'.tmp',0600);rename($target.'.tmp',$target);}
                $index[$t['sha256']]=$hash;break;
            }
        }
        file_put_contents($dir.'/index.tmp',json_encode($index,JSON_THROW_ON_ERROR));chmod($dir.'/index.tmp',0600);rename($dir.'/index.tmp',$dir.'/index.json');return $index;
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}
function music_public_artwork_bytes(string $root,mixed $hash):?string {
    if(!is_string($hash)||!preg_match('/^[a-f0-9]{64}$/D',$hash)||!in_array($hash,music_public_artwork_index($root),true))return null;
    $p=$root.'/public-artwork/'.$hash.'.jpg';if(is_link($p)||!is_file($p)||filesize($p)>2097152)return null;
    $bytes=file_get_contents($p);return hash('sha256',$bytes)===$hash?$bytes:null;
}
