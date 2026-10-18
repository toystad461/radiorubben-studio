<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/music-play-queue.php';
function mq_ok(bool $ok,string $label):void { if(!$ok)throw new RuntimeException($label); echo "PASS music queue: $label\n"; }
function mq_reject(callable $call,string $label):void { try{$call();}catch(Throwable){mq_ok(true,$label);return;}throw new RuntimeException($label); }
$tmp=sys_get_temp_dir().'/rr-music-queue-'.bin2hex(random_bytes(5));mkdir($tmp,0700);$tmp=realpath($tmp);
mkdir($tmp.'/config',0700);$library=$tmp.'/media/music';music_library_directory($library,true);
$path=$tmp.'/config/queue.json';$user=['id'=>'synthetic','role'=>'producer'];$tracks=[];
for($i=1;$i<=3;$i++){
 $id=md5('queue-test-'.$i);$bytes='synthetic-'.$i;$sha=hash('sha256',$bytes);file_put_contents($library.'/originals/'.$id.'.mp3',$bytes);
 $tracks[]=['id'=>$id,'sha256'=>$sha,'bytes'=>strlen($bytes),'originalName'=>'Track '.$i.'.mp3','metadata'=>null,'manualOverrides'=>['artist'=>'Testartist','title'=>'Låt '.$i]];
}
file_put_contents($library.'/library.lock','');file_put_contents($library.'/library.json',json_encode(['schemaVersion'=>1,'tracks'=>$tracks]));
$libraryBefore=file_get_contents($library.'/library.json');
$change=static fn($input,$actor=null,$csrf='csrf')=>music_play_queue_change($input,$actor??$user,$csrf,'csrf',$path,$library);
$add=static fn($i,$revision)=>['action'=>'add','revision'=>$revision,'trackId'=>$tracks[$i]['id'],'sha256'=>$tracks[$i]['sha256']];
try {
 mq_ok(music_play_queue_read($path)===music_play_queue_empty()&&!file_exists($path.'.lock'),'empty read creates no state');
 mq_reject(fn()=>$change($add(0,0),['role'=>'observer']),'observer cannot write');
 mq_reject(fn()=>$change($add(0,0),null,'wrong'),'invalid CSRF cannot write');
 mq_reject(fn()=>$change(array_replace($add(0,0),['sha256'=>str_repeat('f',64)])),'stale audio version rejected');
 mq_ok(!file_exists($path),'denied operations create no queue');
 $q=$change($add(0,0));$q=$change($add(1,1));$q=$change($add(2,2));
 mq_ok(count($q['entries'])===3&&music_play_queue_read($path)===$q,'queue survives a fresh read');
 $ids=array_column($q['entries'],'id');$before=file_get_contents($path);
 mq_reject(fn()=>$change(['action'=>'clear','revision'=>2]),'second tab cannot overwrite a newer revision');
 mq_ok(file_get_contents($path)===$before,'conflict preserves saved queue');
 $q=$change(['action'=>'move','revision'=>3,'id'=>$ids[2],'beforeId'=>$ids[0]]);
 mq_ok(array_column($q['entries'],'id')===[$ids[2],$ids[0],$ids[1]],'move preserves exact entries and audio versions');
 $before=file_get_contents($path);$q=$change(['action'=>'move','revision'=>4,'id'=>$ids[2],'beforeId'=>$ids[2]]);
 mq_ok(file_get_contents($path)===$before,'no-op move does not rewrite or increment revision');
 mq_reject(fn()=>$change(['action'=>'move','revision'=>4,'id'=>$ids[2],'beforeId'=>'forged']),'unknown move target rejected');
 mq_ok(file_get_contents($path)===$before,'bad move cannot remove the source item');
 $q=$change(['action'=>'remove','revision'=>4,'id'=>$ids[0]]);
 mq_ok(count($q['entries'])===2&&is_file($library.'/originals/'.$tracks[0]['id'].'.mp3'),'remove keeps original music');
 $q=$change($add(1,5));mq_ok(count(array_unique(array_column($q['entries'],'id')))===3,'same song can have distinct rundown occurrences');
 $view=music_play_queue_snapshot($q,music_library_read($library));mq_ok($view['entries'][0]['available']&&str_starts_with($view['entries'][0]['audioUrl'],'/music-library-audio.php?'),'audio uses existing authenticated route');
 unlink($library.'/originals/'.$tracks[2]['id'].'.mp3');$view=music_play_queue_snapshot($q,music_library_read($library));
 mq_ok(!$view['entries'][0]['available']&&$view['entries'][0]['audioUrl']===null,'missing file is visible and cannot be played');
 $changed=music_library_read($library);$changed[0]['sha256']=str_repeat('f',64);$changed[0]['available']=true;$view=music_play_queue_snapshot($q,$changed);
 mq_ok(!$view['entries'][0]['available'],'changed original is not silently substituted');
 $q=$change(['action'=>'clear','revision'=>6]);mq_ok($q['entries']===[]&&file_get_contents($library.'/library.json')===$libraryBefore,'clear affects only the rundown');
 file_put_contents($path,'broken');mq_reject(fn()=>$change(['action'=>'clear','revision'=>7]),'corrupt state is never replaced');mq_ok(file_get_contents($path)==='broken','corrupt bytes preserved');
 unlink($path);symlink($library.'/library.json',$path);mq_reject(fn()=>music_play_queue_read($path),'symlink queue refused');
 mq_ok(file_get_contents($library.'/library.json')===$libraryBefore,'library is unchanged throughout');
} finally {foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST)as$f)$f->isDir()&&!$f->isLink()?rmdir($f->getPathname()):unlink($f->getPathname());rmdir($tmp);}
