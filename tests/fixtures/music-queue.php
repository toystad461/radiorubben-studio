<?php
declare(strict_types=1);
// Isolated real view with synthetic identity and local test data; never production auth.
$root=dirname(__DIR__,2);$tmp=sys_get_temp_dir().'/rr-queue-view-'.bin2hex(random_bytes(5));
foreach(['/app/views','/public','/config','/media/music/originals']as$d)mkdir($tmp.$d,0700,true);
foreach(['music-play-queue','music-library-preparation','music-library','music-library-tags','users','helpers']as$n)copy($root.'/app/'.$n.'.php',$tmp.'/app/'.$n.'.php');
foreach(['head','sidebar','navigation','account']as$n)copy($root.'/app/views/'.$n.'.php',$tmp.'/app/views/'.$n.'.php');
copy($root.'/public/music-queue.php',$tmp.'/public/music-queue.php');
$role=$argv[1]??'producer';
if($role==='server') {
 copy($root.'/public/music-queue-api.php',$tmp.'/public/music-queue-api.php');
 file_put_contents($tmp.'/app/bootstrap.php','<?php require __DIR__."/helpers.php";require_once __DIR__."/users.php";function current_user(){ $role=$_SERVER["HTTP_X_TEST_ROLE"]??"producer";return $role==="guest"?null:["id"=>"synthetic","name"=>"Test","role"=>$role];} $_SESSION=["csrf"=>"synthetic"]; $config=[];');
} else {
file_put_contents($tmp.'/app/bootstrap.php','<?php require __DIR__."/helpers.php";require_once __DIR__."/users.php";function current_user(){return $GLOBALS["role"]==="guest"?null:["id"=>"synthetic","name"=>"Test","role"=>$GLOBALS["role"]];} $config=[];');
}
$_SESSION=['csrf'=>'synthetic'];$_SERVER['REQUEST_METHOD']='GET';
$tracks=[];
for($i=1;$i<=4;$i++){
 $id=md5('fixture-'.$i);$bytes='synthetic-'.$i;$sha=hash('sha256',$bytes);file_put_contents($tmp.'/media/music/originals/'.$id.'.mp3',$bytes);
 $tracks[]=['id'=>$id,'sha256'=>$sha,'bytes'=>strlen($bytes),'originalName'=>'Låt '.$i.'.mp3','metadata'=>['source'=>'file','sourceSha256'=>$sha,'fields'=>['title'=>'Prøvelåt '.$i,'artist'=>'Testartist'],'durationSeconds'=>180+$i]];
}
file_put_contents($tmp.'/media/music/library.lock','');file_put_contents($tmp.'/media/music/library.json',json_encode(['schemaVersion'=>1,'tracks'=>$tracks]));
if($role==='server'){echo $tmp;exit;}
register_shutdown_function(static function()use($tmp){foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST)as$f)$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());rmdir($tmp);});
require $tmp.'/public/music-queue.php';
