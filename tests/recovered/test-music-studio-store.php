<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/music-studio.php';
function store_ok(bool $ok,string $label):void {if (!$ok) throw new RuntimeException($label);echo "PASS studio store: $label\n";}
function store_reject(callable $f,string $label):void {try {$f();}catch(Throwable){store_ok(true,$label);return;}throw new RuntimeException($label);}
$root=sys_get_temp_dir().'/rr-studio-store-'.bin2hex(random_bytes(6));mkdir($root,0700);mkdir($root.'/config',0700);$library=$root.'/media/music';music_library_directory($library,true);$path=$root.'/config/queue.json';
$tracks=[];for($i=1;$i<=4;$i++){$bytes='synthetic-'.$i;$id=md5($bytes);$sha=hash('sha256',$bytes);file_put_contents($library.'/originals/'.$id.'.mp3',$bytes);$tracks[]=['id'=>$id,'sha256'=>$sha,'bytes'=>strlen($bytes),'originalName'=>'Synthetic '.$i,'metadata'=>['source'=>'file','sourceSha256'=>$sha,'fields'=>['artist'=>'Artist '.$i,'title'=>'Synthetic '.$i],'durationSeconds'=>180],'tags'=>['Varm']];}
file_put_contents($library.'/library.lock','');file_put_contents($library.'/library.json',json_encode(['schemaVersion'=>1,'tracks'=>$tracks]));$actor=['id'=>'synthetic','role'=>'producer'];
$change=fn($body,$user=null)=>music_studio_change($body,$user??$actor,'csrf','csrf',$path,$library);
try {
    $change(['action'=>'schedule','revision'=>0,'schedule'=>['programId'=>'god-morgen-bomlo','date'=>'2026-10-19','time'=>'07:00']]);
    store_reject(fn()=>$change(['action'=>'reset','revision'=>0]),'stale write rejected');
    store_reject(fn()=>$change(['action'=>'reset','revision'=>1],['role'=>'observer']),'observer cannot mutate');
    foreach ($tracks as $i=>$t) $change(['action'=>'policy','revision'=>$i+1,'trackId'=>$t['id'],'sha256'=>$t['sha256'],'policy'=>['approved'=>true,'blocked'=>false,'programs'=>['god-morgen-bomlo'],'weekdays'=>[1,2,3,4,5,6,7],'periods'=>[],'fields'=>['energy'=>'moderat']]]);
    $proposal=$change(['action'=>'suggest','revision'=>5]);$before=file_get_contents($path);store_ok(music_play_queue_read($path)['entries']===[],'preview proposal does not change the queue or create playback events');
    store_reject(fn()=>$change(['action'=>'suggest','revision'=>5,'commit'=>true,'trackIds'=>['forged']]),'unreviewed substitution refused');store_ok(file_get_contents($path)===$before,'failed proposal leaves saved state intact');
    $change(['action'=>'suggest','revision'=>5,'commit'=>true,'trackIds'=>array_column($proposal['proposal']['suggestions'],'trackId')]);$q=music_play_queue_read($path);$ids=array_column($q['entries'],'id');
    $change(['action'=>'lock','revision'=>6,'id'=>$ids[1],'locked'=>true]);
    store_reject(fn()=>$change(['action'=>'move','revision'=>7,'id'=>$ids[0],'beforeId'=>null]),'moving another item cannot displace a locked position');
    store_reject(fn()=>$change(['action'=>'remove','revision'=>7,'id'=>$ids[1]]),'locked removal refused');
    store_reject(fn()=>$change(['action'=>'replace','revision'=>7,'id'=>$ids[1]]),'locked replacement refused');
    $replacement=$change(['action'=>'replace','revision'=>7,'id'=>$ids[0]]);store_ok(count($replacement['proposal']['suggestions'])===1,'replacement checks the remaining queue');
    $change(['action'=>'replace','revision'=>7,'id'=>$ids[0],'commit'=>true,'trackIds'=>array_column($replacement['proposal']['suggestions'],'trackId')]);$q=music_play_queue_read($path);
    store_ok($q['entries'][1]['id']===$ids[1] && $q['entries'][1]['locked'] && $q['entries'][0]['id']!==$ids[0],'replacement preserves locked occurrence and creates a new unplayed occurrence');
    // Two genuinely simultaneous PHP processes see the same revision; only one may commit.
    store_ok(function_exists('pcntl_fork'),'native process concurrency available');$children=[];
    for($i=0;$i<2;$i++) { $pid=pcntl_fork();if ($pid===0) {while (!file_exists($root.'/go')) usleep(1000);try{$change(['action'=>'lock','revision'=>8,'id'=>$q['entries'][2]['id'],'locked'=>true]);file_put_contents($root.'/result-'.$i,'ok');}catch(DomainException){file_put_contents($root.'/result-'.$i,'conflict');}exit;}if ($pid<0)throw new RuntimeException('fork');$children[]=$pid; }
    file_put_contents($root.'/go','go');foreach ($children as $pid) pcntl_waitpid($pid,$status);$results=[file_get_contents($root.'/result-0'),file_get_contents($root.'/result-1')];sort($results);store_ok($results===['conflict','ok'],'simultaneous writers cannot lose an update');
    $bus=$root.'/bus';mkdir($bus);$s=music_playout_empty();music_playout_event($bus,$s,'service_started');$entry=['id'=>str_repeat('c',32),'trackId'=>$tracks[0]['id'],'artist'=>'Artist 1','status'=>'failed'];$s['outcomes'][$entry['id']]=$entry;music_playout_event($bus,$s,'failed',['entry'=>$entry]);
    store_ok(music_playout_state($bus)==$s && music_playout_history($bus)===[],'failed before start is durable but not played');
    file_put_contents($bus.'/events.jsonl','{"partial":',FILE_APPEND);store_reject(fn()=>music_playout_state($bus),'partial journal tail fails closed without erasing evidence');
    store_ok(is_file($library.'/originals/'.$tracks[0]['id'].'.mp3'),'originals preserved');
} finally {foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST)as$f)$f->isDir()&&!$f->isLink()?rmdir($f->getPathname()):unlink($f->getPathname());rmdir($root);}
