<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/music-liquidsoap.php';

function lse_ok(bool $ok,string $label): void {
    if (!$ok) throw new RuntimeException($label);
    echo "PASS Liquidsoap events: $label\n";
}
function lse_reject(callable $operation,string $label): void {
    try {$operation();} catch (RuntimeException) {lse_ok(true,$label);return;}
    throw new RuntimeException($label.': unexpectedly accepted');
}
function lse_fixture(string $base,int $count=3): array {
    $root=$base.'/case-'.bin2hex(random_bytes(5));mkdir($root,0700);mkdir($root.'/audio',0700);
    $entries=[];
    for ($i=0;$i<$count;$i++) {
        $bytes='synthetic event fixture '.$i;$sha=hash('sha256',$bytes);
        file_put_contents($root.'/audio/'.$sha.'.mp3',$bytes);
        $entries[]=['id'=>md5('occurrence '.$i),'kind'=>'music','trackId'=>md5($bytes),'sha256'=>$sha,
            'artist'=>'Fixture '.$i,'title'=>'Untrusted "title":http://invalid.example/','duration'=>180,'blocked'=>[]];
    }
    $session=bin2hex(random_bytes(16));$state=music_playout_empty();
    $state['engine']='liquidsoap';$state['session']=$session;$state['mode']='starting';$state['connected']=true;
    music_playout_event($root,$state,'service_started');
    music_playout_atomic($root.'/control.json',['ready'=>true,'revision'=>1,'entries'=>$entries,'command'=>null]);
    music_playout_atomic($root.'/heartbeat.json',['timestamp'=>microtime(true),'engine'=>'liquidsoap','connected'=>true,'position'=>0,'segments'=>[]]);
    return compact('root','entries','session');
}
function lse_state(array $fixture,array $changes): array {
    $lock=music_playout_lock($fixture['root']);
    try {
        $state=array_replace(music_playout_state($fixture['root']),$changes);
        music_playout_event($fixture['root'],$state,'fixture_state');return $state;
    } finally {flock($lock,LOCK_UN);fclose($lock);}
}
function lse_control(array $fixture,array $changes): void {
    music_playout_atomic($fixture['root'].'/control.json',array_replace(music_playout_json($fixture['root'].'/control.json'),$changes));
}
function lse_observe(array $fixture,array $event): array {
    $lock=music_playout_lock($fixture['root']);
    try {
        $state=music_playout_state($fixture['root']);
        music_liquidsoap_observe($fixture['root'],$state,$event+['session'=>$fixture['session']]);return $state;
    } finally {flock($lock,LOCK_UN);fclose($lock);}
}
function lse_start(array $fixture,int $index=0,float $clock=0): array {
    return lse_observe($fixture,['event'=>'started','entry'=>$fixture['entries'][$index]['id'],'clock'=>$clock]);
}
function lse_end(array $fixture,int $index=0,float $clock=180): array {
    return lse_observe($fixture,['event'=>'ended','entry'=>$fixture['entries'][$index]['id'],'clock'=>$clock]);
}

$base=sys_get_temp_dir().'/rr-liquidsoap-events-'.bin2hex(random_bytes(6));mkdir($base,0700);
set_error_handler(static function (int $severity,string $message,string $file,int $line): never {
    throw new ErrorException($message,0,$severity,$file,$line);
});
try {
    // This test checks the journal/claim protocol, not whether a real engine's
    // callbacks coincide with delivered audio. The stream integration owns that proof.
    $f=lse_fixture($base);$root=$f['root'];$first=$f['entries'][0];
    $uri=music_liquidsoap_claim($root,$f['session']);
    lse_ok($uri==='annotate:rr_entry="'.$first['id'].'",rr_session="'.$f['session'].'":'.$root.'/audio/'.$first['sha256'].'.mp3',
        'claim returns only generated identities and the verified spool path');
    $state=music_playout_state($root);
    lse_ok(isset($state['reserved'][$first['id']]) && $state['current']===null && $state['active']===[]
        && music_playout_history($root)===[],'prefetch is durable ownership without a started/played claim');
    $secondUri=music_liquidsoap_claim($root,$f['session']);
    lse_ok($secondUri==='' && count(music_playout_state($root)['reserved'])===1,
        'another claim waits until the reserved occurrence actually starts, preventing a silent decoder skip');
    lse_start($f);$secondUri=music_liquidsoap_claim($root,$f['session']);$state=music_playout_state($root);
    lse_ok(str_contains($secondUri,$f['entries'][1]['id']) && count($state['reserved'])===1 && count($state['active'])===1,
        'active audio may have one following reservation without duplicating either occurrence');

    foreach ([['engine'=>'php'],['session'=>str_repeat('f',32)],['mode'=>'stopped'],['mode'=>'blocked'],['mode'=>'paused'],['connected'=>false]] as $changes) {
        $f=lse_fixture($base);$before=lse_state($f,$changes);
        lse_ok(music_liquidsoap_claim($f['root'],$f['session'])==='' && music_playout_state($f['root'])==$before,
            'claim requires the current session, Liquidsoap authority, playable mode and connection');
    }
    foreach ([['timestamp'=>microtime(true)-20],['timestamp'=>microtime(true)+20],['engine'=>'php'],['engine'=>null]] as $changes) {
        $f=lse_fixture($base);$before=music_playout_state($f['root']);
        music_playout_atomic($f['root'].'/heartbeat.json',array_replace(music_playout_json($f['root'].'/heartbeat.json'),$changes));
        lse_ok(music_liquidsoap_claim($f['root'],$f['session'])==='' && music_playout_state($f['root'])==$before,
            'stale, future or mismatched heartbeat cannot authorize a claim');
    }
    $f=lse_fixture($base);lse_control($f,['ready'=>false]);$before=music_playout_state($f['root']);
    lse_ok(music_liquidsoap_claim($f['root'],$f['session'])==='' && music_playout_state($f['root'])==$before,'publication barrier prevents claims without changing history');
    lse_reject(fn()=>music_liquidsoap_claim($f['root'],'invalid-session'),'malformed claim session is rejected');

    $f=lse_fixture($base);$entries=$f['entries'];$entries[0]['blocked']=['Manual test approval missing'];lse_control($f,['entries'=>$entries]);
    lse_ok(music_liquidsoap_claim($f['root'],$f['session'])==='','blocked first occurrence cannot be skipped to a later track');
    $state=music_playout_state($f['root']);
    lse_ok($state['mode']==='blocked' && $state['reserved']===[] && $state['outcomes']===[] && music_playout_history($f['root'])===[],
        'policy block is visible and never invents played history');
    foreach (['missing','changed','symlink'] as $fault) {
        $f=lse_fixture($base);$entry=$f['entries'][0];$audio=$f['root'].'/audio/'.$entry['sha256'].'.mp3';
        if ($fault==='changed') file_put_contents($audio,'different bytes');
        else {unlink($audio);if ($fault==='symlink') symlink($f['root'].'/audio/'.$f['entries'][1]['sha256'].'.mp3',$audio);}
        lse_ok(music_liquidsoap_claim($f['root'],$f['session'])==='','unsafe spool file stops the claim: '.$fault);
        $state=music_playout_state($f['root']);
        lse_ok($state['mode']==='blocked' && $state['outcomes'][$entry['id']]['status']==='failed'
            && !isset($state['outcomes'][$entry['id']]['startedAt']) && music_playout_history($f['root'])===[],
            'file failure is durable without a false start/completion: '.$fault);
        lse_ok(music_liquidsoap_claim($f['root'],$f['session'])==='','file failure does not silently continue with the next track');
    }

    $f=lse_fixture($base);$before=music_playout_state($f['root']);
    lse_reject(fn()=>lse_start($f),'start without reservation is rejected');
    lse_reject(fn()=>lse_end($f),'end without a matching start is rejected');
    lse_reject(fn()=>lse_observe($f,['event'=>'unknown']),'unknown event from current session fails closed');
    lse_ok(music_playout_state($f['root'])==$before,'rejected events leave the journal unchanged');
    $ignored=lse_observe($f,['session'=>str_repeat('e',32),'event'=>'started','entry'=>$f['entries'][0]['id'],'clock'=>0]);
    lse_ok($ignored==$before && music_playout_state($f['root'])==$before,'callbacks from an old child session cannot acquire authority');
    music_liquidsoap_claim($f['root'],$f['session']);
    foreach ([-1,'0',null,INF,NAN] as $clock) lse_reject(fn()=>lse_observe($f,['event'=>'started','entry'=>$f['entries'][0]['id'],'clock'=>$clock]),'invalid PCM clock is rejected');

    foreach (['barrier','removed','hash','blocked'] as $fault) {
        $f=lse_fixture($base);music_liquidsoap_claim($f['root'],$f['session']);$entries=$f['entries'];
        if ($fault==='barrier') lse_control($f,['ready'=>false]);
        else {
            if ($fault==='removed') array_shift($entries);
            elseif ($fault==='hash') $entries[0]['sha256']=str_repeat('a',64);
            else $entries[0]['blocked']=['Approval withdrawn'];
            lse_control($f,['entries'=>$entries]);
        }
        lse_reject(fn()=>lse_start($f),'reserved occurrence cannot start after manifest invalidation: '.$fault);
        $state=music_playout_state($f['root']);
        lse_ok($state['active']===[] && $state['outcomes']===[] && isset($state['reserved'][$f['entries'][0]['id']]),
            'rejected start retains ownership until child shutdown and creates no played outcome');
    }

    foreach (['missing','changed','symlink'] as $fault) {
        $f=lse_fixture($base);music_liquidsoap_claim($f['root'],$f['session']);
        $entry=$f['entries'][0];$audio=$f['root'].'/audio/'.$entry['sha256'].'.mp3';
        if ($fault==='changed') file_put_contents($audio,'modified after prefetch');
        else {unlink($audio);if ($fault==='symlink') symlink($f['root'].'/audio/'.$f['entries'][1]['sha256'].'.mp3',$audio);}
        $state=lse_start($f);
        lse_ok($state['mode']==='blocked' && $state['current']===null && $state['active']===[] && $state['reserved']===[]
            && $state['outcomes'][$entry['id']]['status']==='failed' && $state['lastCompleted']===null,
            'post-claim file change cannot acquire active/completed status: '.$fault);
        $seq=$state['seq'];lse_end($f);
        lse_ok(music_playout_state($f['root'])['seq']===$seq && music_playout_history($f['root'])===[],
            'late end cannot turn a post-claim file failure into completion or invented history');
    }

    foreach ([['connected'=>false],['mode'=>'blocked'],['mode'=>'stopped']] as $changes) {
        $f=lse_fixture($base);music_liquidsoap_claim($f['root'],$f['session']);lse_state($f,$changes);
        lse_reject(fn()=>lse_start($f),'reservation alone does not authorize start after connection or mode is revoked');
        lse_ok(music_playout_state($f['root'])['active']===[],'refused start creates no active occurrence');
    }

    $f=lse_fixture($base);music_liquidsoap_claim($f['root'],$f['session']);
    $state=lse_start($f,0,0);$seq=$state['seq'];$state=lse_start($f,0,0);
    lse_ok($state['seq']===$seq && count($state['active'])===1 && count(music_playout_history($f['root']))===1,'duplicate start callback does not duplicate history');
    music_liquidsoap_claim($f['root'],$f['session']);$state=lse_start($f,1,178);
    lse_ok(count($state['active'])===2 && $state['current']['id']===$f['entries'][1]['id'],'overlap retains both contributors with newest current');
    $state=lse_end($f,0,180);
    lse_ok($state['outcomes'][$f['entries'][0]['id']]['status']==='completed' && count($state['active'])===1
        && $state['current']['id']===$f['entries'][1]['id'],'one end only completes its matching contributor');
    $state=lse_end($f,1,358);$seq=$state['seq'];lse_end($f,0,180);lse_start($f,0,0);
    lse_ok(music_playout_state($f['root'])['seq']===$seq && count(music_playout_history($f['root']))===2,
        'terminal callback replays cannot create a second outcome or restart an occurrence');
    lse_ok(str_contains(music_liquidsoap_claim($f['root'],$f['session']),$f['entries'][2]['id']),'completed occurrence IDs are never returned by a later claim');

    foreach ([179,100,400] as $clock) {
        $f=lse_fixture($base);music_liquidsoap_claim($f['root'],$f['session']);lse_start($f,0,180);$state=lse_end($f,0,(float)$clock);
        lse_ok($state['mode']==='blocked' && $state['outcomes'][$f['entries'][0]['id']]['status']==='failed'
            && $state['lastCompleted']===null,'negative, short or excessive rendered duration cannot become completed');
    }
    foreach (['disconnected','output_error','claim_error'] as $fault) {
        $f=lse_fixture($base);music_liquidsoap_claim($f['root'],$f['session']);lse_start($f);
        $state=lse_observe($f,['event'=>$fault]);
        lse_ok(!$state['connected'] && $state['mode']==='blocked','engine fault withdraws authority: '.$fault);
        $state=lse_end($f);
        lse_ok($state['outcomes'][$f['entries'][0]['id']]['status']==='failed' && $state['lastCompleted']===null,
            'correct length alone cannot complete after an output/claim fault');
    }

    $f=lse_fixture($base);music_liquidsoap_claim($f['root'],$f['session']);lse_start($f,0);
    music_liquidsoap_claim($f['root'],$f['session']);lse_start($f,1,178);music_liquidsoap_claim($f['root'],$f['session']);
    $lock=music_playout_lock($f['root']);
    try {
        $state=music_playout_state($f['root']);$state['mode']='stopped';
        music_liquidsoap_reset($f['root'],$state,'Synthetic child exit confirmed');music_playout_event($f['root'],$state,'engine_stopped');
    } finally {flock($lock,LOCK_UN);fclose($lock);}
    lse_ok(count($state['outcomes'])===2 && array_unique(array_column($state['outcomes'],'status'))===['interrupted']
        && $state['active']===[] && $state['reserved']===[] && $state['current']===null && $state['session']===null && !$state['connected'],
        'confirmed stop interrupts both active contributors and releases only unplayed reservations');
    lse_ok(count(music_playout_history($f['root']))===2 && music_liquidsoap_claim($f['root'],$f['session'])==='',
        'reset preserves actual started history and never resumes the old session');
    $before=music_playout_state($f['root']);lse_end($f,0,180);
    lse_ok(music_playout_state($f['root'])==$before,'late callback after reset cannot turn an interruption into completion');

    // Two request helpers really contend for control.lock and the same journal.
    $f=lse_fixture($base,2);$children=[];
    for ($i=0;$i<2;$i++) {
        $pid=pcntl_fork();
        if ($pid===0) {
            while (!is_file($f['root'].'/go')) usleep(1000);
            file_put_contents($f['root'].'/claim-'.$i,music_liquidsoap_claim($f['root'],$f['session']));exit(0);
        }
        if ($pid<0) throw new RuntimeException('Could not fork competing claim helper.');$children[]=$pid;
    }
    file_put_contents($f['root'].'/go','go');
    foreach ($children as $pid) {pcntl_waitpid($pid,$status);lse_ok(pcntl_wifexited($status) && pcntl_wexitstatus($status)===0,'concurrent claim helper exits cleanly');}
    $uris=[file_get_contents($f['root'].'/claim-0'),file_get_contents($f['root'].'/claim-1')];$state=music_playout_state($f['root']);
    lse_ok(count(array_filter($uris,static fn($uri)=>$uri!==''))===1 && count($state['reserved'])===1 && $state['seq']===2,
        'simultaneous claims reserve exactly one occurrence without duplication or a lost update');
    lse_start($f);$uri=music_liquidsoap_claim($f['root'],$f['session']);$state=music_playout_state($f['root']);
    lse_ok(str_contains($uri,$f['entries'][1]['id']) && count($state['active'])===1 && count($state['reserved'])===1,
        'claim resumes with the next distinct occurrence only after a confirmed start');

    // Studio has already accepted Stop even if the supervisor's next tick has
    // not consumed that command. Do not authorize a fresh prefetch after that.
    // Buffered started callbacks still describe actual PCM and must be recorded
    // before the runner interrupts those occurrences in the same stop tick.
    $f=lse_fixture($base);lse_control($f,['command'=>['id'=>str_repeat('a',32),'action'=>'stop']]);
    $before=music_playout_state($f['root']);
    lse_ok(music_liquidsoap_claim($f['root'],$f['session'])==='' && music_playout_state($f['root'])==$before,
        'pending operator Stop prevents another claim before supervisor acknowledgement');
} finally {
    restore_error_handler();
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $file)
        $file->isDir()&&!$file->isLink()?rmdir($file->getPathname()):unlink($file->getPathname());
    rmdir($base);
}
