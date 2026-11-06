<?php
declare(strict_types=1);
require __DIR__.'/../app/music-public-status.php';
require __DIR__.'/../app/music-boot.php';
require __DIR__.'/../app/music-public-artwork.php';
function verify(bool $ok,string $name):void{if(!$ok)throw new RuntimeException($name);echo "PASS $name\n";}
$entry=['id'=>'a','title'=>'Song A','artist'=>'Artist A','kind'=>'music','sha256'=>'SECRET_HASH','path'=>'/private/file','blocked'=>false];
$second=array_replace($entry,['id'=>'b','title'=>'Song B']);$third=array_replace($entry,['id'=>'c','title'=>'Song C']);
$s=music_playout_empty()+['engine'=>'liquidsoap'];$s['mode']='playing';$s['current']=$entry;$s['active']=['a'=>$entry];$s['reserved']=['b'=>$second];
$h=['engine'=>'liquidsoap','connected'=>true,'timestamp'=>microtime(true)];$c=['ready'=>true,'entries'=>[$entry,$second,$third],'command'=>['action'=>'start','id'=>'command-a']];
$v=music_public_status($s,$h,$c);verify($v['playing']&&$v['now']['title']==='Song A'&&$v['next']['title']==='Song B','next includes prefetched but not yet playing song');
verify(!str_contains(json_encode($v),'SECRET')&&!str_contains(json_encode($v),'/private')&&!isset($v['now']['id']),'public allowlist excludes private fields');
$stale=music_public_status($s,array_replace($h,['timestamp'=>microtime(true)-8]),$c);verify(!$stale['playing']&&$stale['now']===null&&$stale['next']===null,'stale heartbeat clears metadata');
$off=music_public_status($s,array_replace($h,['connected'=>false]),$c);verify(!$off['playing'],'disconnected stream is not presented as live');
$s['active']['b']=$second;$s['current']=$second;verify(music_public_status($s,$h,$c)['next']['title']==='Song C','crossfade contributors excluded from next');
$q=['studio'=>['rotation'=>['enabled'=>true]]];$s=music_playout_empty()+['engine'=>'liquidsoap'];$s['ack']='command-a';
verify(music_boot_allowed($q,$s,$c,$h),'stopped service resumes previous start');
foreach(['stop','pause']as$action){$blocked=$c;$blocked['command']['action']=$action;verify(!music_boot_allowed($q,$s,$blocked,$h),'operator '.$action.' respected');}
$blocked=$c;$blocked['command']['id']='pending';verify(!music_boot_allowed($q,$s,$blocked,$h),'pending operator command respected');
foreach(['playing','starting','blocked']as$mode){$changed=$s;$changed['mode']=$mode;verify(!music_boot_allowed($q,$changed,$c,$h),'no resume in '.$mode);}
verify(!music_boot_allowed(['studio'=>[]],$s,$c,$h),'disabled rotation remains stopped');
verify(!music_boot_allowed($q,$s,$c,array_replace($h,['timestamp'=>0])),'dead player is not started');
$image=str_repeat('a',64);$source=str_repeat('b',64);
verify(music_public_artwork_url(['sha256'=>$source],[$source=>$image])==='https://5.249.252.78/radio-status.php?cover='.$image,'cover URL only exposes image content hash');
verify(music_public_artwork_url(['sha256'=>$source,'kind'=>'jingle'],[$source=>$image])===null,'jingles use logo');
verify(music_public_artwork_url(['sha256'=>$source],[$source=>'../private'])===null,'invalid image reference rejected');
verify(music_public_artwork_bytes(sys_get_temp_dir(),'../../secret')===null,'image traversal rejected');
verify(music_public_artwork_bytes(sys_get_temp_dir(),['bad'])===null,'array image parameter rejected');
verify(music_public_artwork_jpeg('<svg onload="bad"/>')===null,'active content rejected');
if(extension_loaded('gd')){
 $im=imagecreatetruecolor(800,400);ob_start();imagepng($im);$png=ob_get_clean();imagedestroy($im);
 $jpeg=music_public_artwork_jpeg($png);$size=getimagesizefromstring($jpeg);
 verify($size[0]===512&&$size[1]===256&&$size[2]===IMAGETYPE_JPEG,'raster decoded and resized to safe JPEG');
}
