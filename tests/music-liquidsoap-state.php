<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/music-studio.php';

function ls_state_ok(bool $ok,string $label): void {
    if (!$ok) throw new RuntimeException($label);
    echo "PASS Liquidsoap state: $label\n";
}
function ls_state_reject(callable $operation,string $message,string $label): void {
    try {$operation();} catch (Throwable $e) {
        if (!str_contains($e->getMessage(),$message)) throw new RuntimeException($label.': '.$e->getMessage(),0,$e);
        ls_state_ok(true,$label);return;
    }
    throw new RuntimeException($label.': operation unexpectedly succeeded');
}

// Synthetic byte fixtures exercise identities and storage only; the separate
// engine integration test verifies decoding, mixing and the actual output.
$root=sys_get_temp_dir().'/rr-liquidsoap-state-'.bin2hex(random_bytes(6));
mkdir($root,0700);mkdir($root.'/config',0700);mkdir($root.'/bus',0700);mkdir($root.'/bus/audio',0700);
$bus=$root.'/bus';$library=$root.'/media/music';$path=$root.'/config/queue.json';
music_library_directory($library,true);
$tracks=[];$entries=[];
for ($i=0;$i<8;$i++) {
    $bytes='synthetic-liquidsoap-state-'.$i;$id=md5($bytes);$sha=hash('sha256',$bytes);
    file_put_contents($library.'/originals/'.$id.'.mp3',$bytes);
    $tracks[]=['id'=>$id,'sha256'=>$sha,'bytes'=>strlen($bytes),'originalName'=>'Synthetic '.$i,
        'metadata'=>['source'=>'file','sourceSha256'=>$sha,'fields'=>['artist'=>'Artist '.$i,'title'=>'Synthetic '.$i],'durationSeconds'=>180],'tags'=>['Varm']];
    $entries[]=['id'=>md5('occurrence-'.$i),'kind'=>'music','trackId'=>$id,'sha256'=>$sha,'locked'=>false,
        'artist'=>'Artist '.$i,'title'=>'Synthetic '.$i,'duration'=>180,'blocked'=>[]];
}
file_put_contents($library.'/library.lock','');
file_put_contents($library.'/library.json',json_encode(['schemaVersion'=>1,'tracks'=>$tracks],JSON_THROW_ON_ERROR));
file_put_contents($path.'.lock','');
set_error_handler(static function (int $severity,string $message,string $file,int $line): never {
    throw new ErrorException($message,0,$severity,$file,$line);
});
try {
    [$done,$older,$current,$reserved,$next,$tail,$failed,$unplayed]=$entries;
    $done+=['startedAt'=>'2026-10-23T05:00:00+00:00','status'=>'completed'];
    $older+=['startedAt'=>'2026-10-23T05:03:00+00:00'];
    $current+=['startedAt'=>'2026-10-23T05:05:58+00:00'];
    $failed+=['status'=>'failed'];
    $s=music_playout_empty();$s['engine']='liquidsoap';
    music_playout_event($bus,$s,'service_started');
    $s['reserved']=[$reserved['id']=>$reserved];
    $s['active']=[$older['id']=>$older,$current['id']=>$current];$s['current']=$current;$s['mode']='playing';
    music_playout_event($bus,$s,'started',['entry'=>$current]);
    $s['outcomes'][$done['id']]=$done;music_playout_event($bus,$s,'completed',['entry'=>$done]);
    $s['outcomes'][$failed['id']]=$failed;music_playout_event($bus,$s,'failed',['entry'=>$failed]);
    ls_state_ok(music_playout_state($bus)==$s,'journal preserves engine, reservation, both overlap contributors and terminal outcomes');

    $protected=music_playout_protected_ids($s);sort($protected);
    $expected=array_column([$done,$older,$current,$reserved,$failed],'id');sort($expected);
    ls_state_ok($protected===$expected,'protected identities include outcomes, current, reserved and active exactly once');
    $history=music_playout_history($bus);
    ls_state_ok(count($history)===3 && array_column($history,'entryId')===array_column([$done,$older,$current],'id'),
        'history counts both started contributors once and excludes reserved or failed before start');
    $duplicate=$s;$duplicate['outcomes'][$current['id']]=$current;
    $duplicate['outcomes'][$done['id']]['trackId']=$current['trackId'];
    ls_state_ok(count(music_playout_history_from_state($duplicate))===3,
        'deduplication follows occurrence identity, including outcome overlap, and preserves separate plays of one track');

    $control=['ready'=>true,'revision'=>11,'entries'=>[$done,$older,$current,$reserved,$failed,$next,$tail],
        'profile'=>music_schedule_defaults(),'command'=>null];
    music_playout_atomic($bus.'/control.json',$control);
    $heartbeat=['engine'=>'liquidsoap','timestamp'=>microtime(true),'position'=>2.5,'connected'=>false,'segments'=>['legacy.wav']];
    music_playout_atomic($bus.'/heartbeat.json',$heartbeat);$snapshot=music_playout_snapshot($bus);
    ls_state_ok($snapshot['online'] && !$snapshot['streamConnected'] && !$snapshot['capabilities']['pause']
        && $snapshot['position']===2.5 && $snapshot['segments']===[],'engine liveness is distinct from output connection and Liquidsoap has no pause or PCM monitor');
    ls_state_ok($snapshot['next']['id']===$next['id'],'next skips finished, failed, active and prefetched occurrences');
    $heartbeat['connected']=true;music_playout_atomic($bus.'/heartbeat.json',$heartbeat);
    ls_state_ok(music_playout_snapshot($bus)['streamConnected'],'a fresh matching connected heartbeat reports the test stream connection');

    $valid=['engine'=>'liquidsoap','timestamp'=>998.0];
    ls_state_ok(music_playout_heartbeat_fresh($s,$valid,1000.0),'matching heartbeat inside the freshness window is accepted');
    foreach ([['timestamp'=>1000.01],['timestamp'=>995.0],['timestamp'=>'998'],['timestamp'=>null],
        ['timestamp'=>INF],['timestamp'=>NAN],['engine'=>'php'],['engine'=>null]] as $invalid) {
        ls_state_ok(!music_playout_heartbeat_fresh($s,array_replace($valid,$invalid),1000.0),'future, expired, nonnumeric or unmatched engine heartbeat is rejected');
    }
    foreach ([['timestamp'=>microtime(true)+60],['timestamp'=>microtime(true)-60],['engine'=>'php'],['engine'=>null]] as $invalid) {
        music_playout_atomic($bus.'/heartbeat.json',array_replace($heartbeat,$invalid));$snapshot=music_playout_snapshot($bus);
        ls_state_ok(!$snapshot['online'] && !$snapshot['streamConnected'] && $snapshot['position']===0 && $snapshot['segments']===[],
            'invalid heartbeat cannot leave a live connection or position in the snapshot');
    }

    // A new manifest may omit files that the engine still owns during prefetch
    // or overlap. Publishing must retain all of them until the journal releases them.
    foreach ([$done,$older,$current,$reserved] as $entry) file_put_contents($bus.'/audio/'.$entry['sha256'].'.mp3','owned fixture');
    music_playout_publish($bus,['revision'=>12,'entries'=>[$tail]],['rows'=>[$tail],'profile'=>music_schedule_defaults()],$tracks,$library);
    foreach ([$older,$current,$reserved] as $entry) ls_state_ok(is_file($bus.'/audio/'.$entry['sha256'].'.mp3'),'spool retains every active or prefetched audio hash outside the new manifest');
    ls_state_ok(!file_exists($bus.'/audio/'.$done['sha256'].'.mp3') && hash_file('sha256',$bus.'/audio/'.$tail['sha256'].'.mp3')===$tail['sha256'],
        'spool removes an unreferenced completed file and copies verified future audio');

    $queue=['schemaVersion'=>1,'revision'=>11,'entries'=>[$unplayed,$done,$older,$current,$reserved,$failed,$next,$tail],
        'studio'=>['profiles'=>['god-morgen-bomlo'=>music_schedule_defaults()],'policies'=>[],
            'schedule'=>['programId'=>'god-morgen-bomlo','date'=>'2026-10-23','time'=>'07:00']]];
    foreach ($tracks as $track) $queue['studio']['policies'][$track['id']]=music_schedule_policy([
        'approved'=>true,'blocked'=>false,'programs'=>['god-morgen-bomlo'],'weekdays'=>[1,2,3,4,5,6,7],
        'periods'=>[],'fields'=>['energy'=>'moderat']],$track['sha256']);
    music_playout_atomic($path,$queue,0600);music_playout_atomic($bus.'/control.json',$control);
    $revision=$queue['revision'];$actor=['id'=>'synthetic','role'=>'producer'];
    $change=function (array $input) use ($actor,$path,$library,$bus,&$revision): array {
        return music_studio_change($input+['revision'=>$revision],$actor,'csrf','csrf',$path,$library,['root'=>$bus]);
    };
    $beforeQueue=file_get_contents($path);$beforeControl=file_get_contents($bus.'/control.json');
    foreach ([$older,$current,$reserved] as $entry) foreach (['remove','move','replace'] as $action) {
        ls_state_reject(fn()=>$change(['action'=>$action,'id'=>$entry['id'],'beforeId'=>null]),'behandlet av spilleren',
            $action.' cannot mutate an active or reserved occurrence');
    }
    ls_state_reject(fn()=>$change(['action'=>'remove','id'=>$unplayed['id']]),'Endringen flytter','removal before a reservation cannot shift its position');
    ls_state_reject(fn()=>$change(['action'=>'move','id'=>$tail['id'],'beforeId'=>$older['id']]),'Flyttingen krysser','moving an unreserved occurrence cannot cross an active position');
    ls_state_ok(file_get_contents($path)===$beforeQueue && file_get_contents($bus.'/control.json')===$beforeControl,
        'rejected queue edits leave both queue and manifest unchanged');

    foreach (['reserved','active','current'] as $owned) {
        $s['current']=null;$s['reserved']=[];$s['active']=[];$s['mode']='stopped';
        if ($owned==='current') $s['current']=$current;else $s[$owned]=[$current['id']=>$current];
        music_playout_event($bus,$s,'state_changed');
        ls_state_reject(fn()=>$change(['action'=>'reset']),'Stopp spilleren','reset waits for '.$owned.' ownership to end');
        ls_state_reject(fn()=>$change(['action'=>'schedule','schedule'=>['programId'=>'god-morgen-bomlo','date'=>'2026-10-24','time'=>'07:00']]),
            'Stopp avspilleren','schedule change waits for '.$owned.' ownership to end');
    }
    $s['current']=$current;$s['reserved']=[$reserved['id']=>$reserved];$s['active']=[$older['id']=>$older,$current['id']=>$current];$s['mode']='playing';
    music_playout_event($bus,$s,'state_changed');
    $result=$change(['action'=>'remove','id'=>$tail['id']]);$revision=$result['revision'];
    ls_state_ok($revision===12 && count(music_play_queue_read($path)['entries'])===7,
        'future unreserved entries remain editable without moving protected positions');

    file_put_contents($library.'/library.json','{broken');
    $heartbeat['timestamp']=microtime(true);music_playout_atomic($bus.'/heartbeat.json',$heartbeat);
    $view=music_studio_view($path,$library,['root'=>$bus]);
    ls_state_ok($view['libraryError']!==null && $view['player']['online'],'status remains readable while the library is malformed');
    $beforeControl=file_get_contents($bus.'/control.json');
    ls_state_reject(fn()=>$change(['action'=>'pause','playerRevision'=>$s['seq']]),'Pause støttes ikke av Liquidsoap',
        'Liquidsoap pause is explicitly refused even with a malformed library');
    ls_state_reject(fn()=>$change(['action'=>'stop','playerRevision'=>$s['seq']-1]),'Spillerstatus er endret','stop still requires the displayed player revision');
    music_playout_atomic($bus.'/heartbeat.json',array_replace($heartbeat,['timestamp'=>microtime(true)+60]));
    ls_state_reject(fn()=>$change(['action'=>'stop','playerRevision'=>$s['seq']]),'Spillertjenesten svarer ikke','a future heartbeat cannot authorize stop');
    ls_state_ok(file_get_contents($bus.'/control.json')===$beforeControl,'rejected commands create no pending control command');
    $heartbeat['timestamp']=microtime(true);music_playout_atomic($bus.'/heartbeat.json',$heartbeat);
    $result=$change(['action'=>'stop','playerRevision'=>$s['seq']]);$stop=music_playout_json($bus.'/control.json')['command'];
    ls_state_ok($result['saved'] && $result['revision']===$revision && $stop['action']==='stop',
        'stop can be requested without a readable music library');

    // An existing PHP runner journal may lack both new maps and the engine tag.
    $s['ack']=$stop['id'];unset($s['engine'],$s['reserved'],$s['active']);$s['current']=$current;$s['mode']='paused';
    music_playout_event($bus,$s,'paused');
    $legacyHeartbeat=['timestamp'=>microtime(true),'position'=>4,'segments'=>['legacy.wav']];
    music_playout_atomic($bus.'/heartbeat.json',$legacyHeartbeat);$snapshot=music_playout_snapshot($bus);
    ls_state_ok($snapshot['online'] && $snapshot['capabilities']['pause'] && $snapshot['segments']===['legacy.wav']
        && !$snapshot['streamConnected'],'legacy PHP heartbeat, journal and monitor remain supported');
    $result=$change(['action'=>'pause','playerRevision'=>$s['seq']]);
    ls_state_ok($result['saved'] && music_playout_json($bus.'/control.json')['command']['action']==='pause',
        'legacy PHP pause still works with a malformed library');
    music_playout_publish($bus,['revision'=>13,'entries'=>[]],['rows'=>[],'profile'=>music_schedule_defaults()],[],$library);
    ls_state_ok(is_file($bus.'/audio/'.$current['sha256'].'.mp3') && !file_exists($bus.'/audio/'.$older['sha256'].'.mp3')
        && !file_exists($bus.'/audio/'.$reserved['sha256'].'.mp3'),'released ownership permits cleanup while legacy current remains retained');
    foreach ($tracks as $track) ls_state_ok(hash_file('sha256',$library.'/originals/'.$track['id'].'.mp3')===$track['sha256'],'music originals are unchanged');
} finally {
    restore_error_handler();
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $file)
        $file->isDir()&&!$file->isLink()?rmdir($file->getPathname()):unlink($file->getPathname());
    rmdir($root);
}
