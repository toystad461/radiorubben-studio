<?php
declare(strict_types=1);
require __DIR__.'/../app/music-autocue.php';
function ac_check(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS autocue: $label\n";}
function ac_reject(callable $fn,string $label):void{try{$fn();}catch(Throwable){ac_check(true,$label);return;}throw new RuntimeException($label);}
$root=sys_get_temp_dir().'/rr-autocue-'.bin2hex(random_bytes(6));music_library_directory($root,true);
try{
 $bytes='unchanged-original';$id=md5($bytes);$sha=hash('sha256',$bytes);file_put_contents($root.'/originals/'.$id.'.mp3',$bytes);
 $t=['id'=>$id,'sha256'=>$sha,'bytes'=>strlen($bytes),'kind'=>'music','revision'=>1,'originalName'=>'test.mp3','metadata'=>['source'=>'file','sourceSha256'=>$sha,'durationSeconds'=>100,'fields'=>[]]];
 file_put_contents($root.'/library.json',json_encode(['schemaVersion'=>1,'tracks'=>[$t]]));file_put_contents($root.'/library.lock','');
 $m=['id'=>$id,'sha256'=>$sha,'revision'=>1,'duration'=>100,'ready'=>true,'engine'=>'ffmpeg-silencedetect--50dB-100ms-v1','cueIn'=>.2,'cueOut'=>99.5,'warnings'=>[]];$actor=['role'=>'admin'];
 ac_reject(fn()=>music_autocue_apply($m,['role'=>'observer'],'x','x',$root),'role');ac_reject(fn()=>music_autocue_apply($m,$actor,'bad','x',$root),'CSRF');
 ac_reject(fn()=>music_autocue_apply(array_replace($m,['cueOut'=>20]),$actor,'x','x',$root),'excessive trim');
 music_autocue_apply($m,$actor,'x','x',$root);$saved=music_library_read($root)[0];ac_check($saved['cuePoints']['source']==='automatic-silence'&&$saved['technicalCheck']['ready'],'source-bound measured points');
 ac_reject(fn()=>music_autocue_apply($m,$actor,'x','x',$root),'stale result');
 music_library_cues_save($id,$sha,$saved['revision'],['start'=>1.,'end'=>98.,'vocalStart'=>4.,'transition'=>97.],$actor,'x','x',$root);
 $saved=music_library_read($root)[0];$m['revision']=$saved['revision'];music_autocue_apply($m,$actor,'x','x',$root);
 $saved=music_library_read($root)[0];ac_check($saved['cuePoints']['source']==='manual'&&$saved['cuePoints']['points']['start']==1.,'manual cues preserved');
 ac_check(hash_file('sha256',$root.'/originals/'.$id.'.mp3')===$sha,'original unchanged');
}finally{foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST)as$f)$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());rmdir($root);}
