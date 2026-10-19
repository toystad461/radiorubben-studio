<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/music-playout.php';
if (PHP_SAPI!=='cli' || count($argv)!==2) {fwrite(STDERR,"Usage: php scripts/music-playout.php /private/playout-root\n");exit(2);}
$root=$argv[1];music_playout_root($root);umask(0007);
foreach (['audio','monitor'] as $dir) if (!is_dir($root.'/'.$dir) || is_link($root.'/'.$dir)) throw new RuntimeException('Spillermappene må klargjøres først.');
if (is_link($root.'/daemon.lock')) throw new RuntimeException('Ugyldig prosesslås.');
$singleton=fopen($root.'/daemon.lock','c+b');
if (!$singleton || !flock($singleton,LOCK_EX|LOCK_NB)) {fwrite(STDERR,"One active player only\n");exit(3);}
$proc=null;$pipes=[];$buffer='';$chunk='';$position=0.0;$segments=[];$attempt=null;$chunkNo=0;$nextTick=microtime(true);$exit=false;$childCode=null;$lastHeartbeat=0.;$lastDataAt=microtime(true);
if (function_exists('pcntl_async_signals')) {pcntl_async_signals(true);foreach ([SIGTERM,SIGINT] as $signal) pcntl_signal($signal,static function()use(&$exit){$exit=true;});}
function pl_close(&$proc,array &$pipes): void {
    if (is_resource($proc)) proc_terminate($proc);
    foreach ($pipes as $p) if (is_resource($p)) fclose($p);$pipes=[];
    if (is_resource($proc)) { $until=microtime(true)+1;while (proc_get_status($proc)['running'] && microtime(true)<$until) usleep(10000);if (proc_get_status($proc)['running']) proc_terminate($proc,9);proc_close($proc);}$proc=null;
}
function pl_wav(string $root,string $attempt,int $number,string $pcm): string {
    $name=$attempt.'-'.str_pad((string)$number,6,'0',STR_PAD_LEFT).'.wav';$path=$root.'/monitor/'.$name;
    $header='RIFF'.pack('V',36+strlen($pcm)).'WAVEfmt '.pack('VvvVVvv',16,1,2,24000,96000,4,16).'data'.pack('V',strlen($pcm));
    $tmp=$path.'.tmp';$h=fopen($tmp,'xb');if (!$h) throw new RuntimeException('Testutgangen er utilgjengelig.');
    try {if (fwrite($h,$header.$pcm)!==strlen($header.$pcm) || !fflush($h) || !fsync($h) || !chmod($tmp,0640)) throw new RuntimeException('Testlyden kunne ikke lagres.');} finally {fclose($h);}
    if (!rename($tmp,$path)) throw new RuntimeException('Testlyden kunne ikke bekreftes.');return $name;
}
function pl_outcome(string $root,array &$s,string $event,string $message): void {
    if ($s['current']) {$entry=$s['current'];$entry['status']=$event;$entry['endedAt']=gmdate('c');$entry['message']=$message;$s['outcomes'][$entry['id']]=$entry;if ($event==='completed') $s['lastCompleted']=$entry;$s['current']=null;}
    $s['message']=$message;music_playout_event($root,$s,$event,['entry'=>$entry??null]);
}
$lock=music_playout_lock($root);
try {
    $s=music_playout_state($root);
    if ($s['current']) {$s['mode']='stopped';pl_outcome($root,$s,'interrupted','Tjenesten ble startet på nytt. Avbrutt innslag spilles ikke automatisk om igjen.');}
    $s['mode']='stopped';$control=music_playout_json($root.'/control.json');
    // Never execute a start command left by an old process/browser after a restart.
    $s['ack']=$control['command']['id']??$s['ack'];music_playout_event($root,$s,'service_started');
} finally {flock($lock,LOCK_UN);fclose($lock);}
try {
    while (!$exit) {
        $lock=music_playout_lock($root);
        try {
            $control=music_playout_json($root.'/control.json');$command=$control['command']??null;
            if ($command && $command['id']!==$s['ack']) {
                $action=$command['action'];$s['ack']=$command['id'];$lastHeartbeat=0.;
                if ($action==='stop') {pl_close($proc,$pipes);$s['mode']='stopped';if ($s['current']) pl_outcome($root,$s,'interrupted','Stoppet av operatør.');$buffer='';$chunk='';}
                elseif ($action==='pause' && $s['mode']==='playing') $s['mode']='paused';
                elseif ($action==='start' && ($control['ready']??false)) {$s['mode']='playing';$nextTick=microtime(true);$lastDataAt=microtime(true);}
                music_playout_event($root,$s,'command',['action'=>$action]);
            }
            if ($s['current'] && ($control['ready']??false)) {
                foreach ($control['entries']??[] as $queued) if ($queued['id']===$s['current']['id'] && $queued['blocked']) {pl_close($proc,$pipes);$s['mode']='blocked';pl_outcome($root,$s,'interrupted','Innslaget ble sperret i Studio: '.implode(' · ',$queued['blocked']));$buffer='';$chunk='';break;}
            }
            if ($s['mode']==='playing' && !$s['current']) {
                if (!($control['ready']??false)) {$s['mode']='blocked';$s['message']='Køen er ikke synkronisert. Rett og synkroniser før start.';music_playout_event($root,$s,'blocked');}
                else {
                    $entry=null;foreach ($control['entries']??[] as $e) if (!isset($s['outcomes'][$e['id']])) {$entry=$e;break;}
                    if (!$entry) {$s['mode']='stopped';$s['message']='Køen er ferdig.';music_playout_event($root,$s,'queue_finished');}
                    elseif ($entry['blocked']) {$s['mode']='blocked';$s['message']=implode(' · ',$entry['blocked']);music_playout_event($root,$s,'blocked',['entryId'=>$entry['id']]);}
                    else {
                        $s['current']=$entry;$s['current']['status']='planned';$s['profile']=$control['profile'];music_playout_event($root,$s,'planned',['entryId'=>$entry['id'],'plannedAt'=>$entry['plannedAt']]);
                        $path=$root.'/audio/'.($entry['sha256']??'').'.mp3';
                        if (($entry['kind']??null)!=='music' || !preg_match('/^[a-f0-9]{64}$/D',$entry['sha256']??'') || is_link($path) || !is_file($path) || hash_file('sha256',$path)!==$entry['sha256']) {$s['mode']='blocked';pl_outcome($root,$s,'failed','Lydkopien mangler eller har feil kontrollsum.');}
                        else {
                            $proc=proc_open(['/usr/bin/ffmpeg','-nostdin','-v','error','-protocol_whitelist','file,pipe','-threads','1','-i',$path,'-filter_threads','1','-map','0:a:0','-vn','-f','s16le','-acodec','pcm_s16le','-ar','24000','-ac','2','pipe:1'],[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['file','/dev/null','w']],$pipes,null,['PATH'=>'/usr/bin:/bin']);
                            if (!is_resource($proc)) {$s['mode']='blocked';pl_outcome($root,$s,'failed','Lyddekoderen kunne ikke startes.');}
                            else {stream_set_blocking($pipes[1],false);$buffer='';$chunk='';$position=0;$attempt=bin2hex(random_bytes(12));$chunkNo=0;$childCode=null;$nextTick=microtime(true);$lastDataAt=microtime(true);}
                        }
                    }
                }
            }
            if ($s['mode']==='playing' && is_resource($proc) && (microtime(true)-$lastDataAt>15 || $position>$s['current']['duration']+max(2,$s['current']['duration']*.02))) {
                pl_close($proc,$pipes);$s['mode']='blocked';pl_outcome($root,$s,'failed','Dekoderen leverte ikke forventet lyd innen grensene.');
            }
            if ($s['mode']==='playing' && is_resource($proc)) {
                // Pace actual decoded samples here; pause does not make ffmpeg catch up at high speed.
                if (strlen($buffer)<19200) { $bytes=fread($pipes[1],19200-strlen($buffer));if ($bytes!==false && $bytes!=='') {$buffer.=$bytes;$lastDataAt=microtime(true);} }
                $child=proc_get_status($proc);if (!$child['running'] && $child['exitcode']>=0) $childCode=$child['exitcode'];
                if (microtime(true)>=$nextTick && $buffer!=='') {
                    $n=min(9600,strlen($buffer));$n-=($n%4);$pcm=substr($buffer,0,$n);$buffer=substr($buffer,$n);$position+=$n/96000;$chunk.=$pcm;$nextTick=microtime(true)+$n/96000;
                    if (!isset($s['current']['startedAt'])) {$s['current']['startedAt']=gmdate('c');$s['current']['status']='started';$s['lastStarted']=$s['current'];music_playout_event($root,$s,'started',['entryId'=>$s['current']['id']]);}
                    if (strlen($chunk)>=192000) {$segments[]=pl_wav($root,$attempt,$chunkNo++,substr($chunk,0,192000));$chunk=substr($chunk,192000);}
                }
                if (!$child['running'] && feof($pipes[1]) && $buffer==='') {
                    if ($chunk!=='') {$segments[]=pl_wav($root,$attempt,$chunkNo++,$chunk);$chunk='';}
                    $duration=$s['current']['duration'];$good=$childCode===0 && $position>0 && abs($position-$duration)<=max(1,$duration*.02);
                    pl_close($proc,$pipes);if (!$good) $s['mode']='blocked';
                    pl_outcome($root,$s,$good?'completed':'failed',$good?'Fullført på isolert testutgang.':'Dekoding feilet eller faktisk lydlengde avviker fra planlagt varighet.');
                }
            }
            if (microtime(true)-$lastHeartbeat>.25) {
                $segments=array_slice($segments,-20);
                music_playout_atomic($root.'/heartbeat.json',['timestamp'=>microtime(true),'position'=>$position,'segments'=>$segments,'pid'=>getmypid()]);$lastHeartbeat=microtime(true);
            }
        } finally {flock($lock,LOCK_UN);fclose($lock);}
        // Bounded monitor retention, never touch originals or the event journal.
        if (isset($nameCleanupAt)===false || microtime(true)-$nameCleanupAt>15) {
            foreach (glob($root.'/monitor/*.wav')?:[] as $file) if (!is_link($file) && filemtime($file)<time()-120 && !in_array(basename($file),$segments,true)) unlink($file);$nameCleanupAt=microtime(true);
        }
        usleep(10000);
    }
} finally {
    pl_close($proc,$pipes);$lock=music_playout_lock($root);
    try {$s['mode']='stopped';if ($s['current']) pl_outcome($root,$s,'interrupted','Spillertjenesten ble stoppet.');music_playout_event($root,$s,'service_stopped');music_playout_atomic($root.'/heartbeat.json',['timestamp'=>0,'position'=>$position,'segments'=>[]]);}
    finally {flock($lock,LOCK_UN);fclose($lock);flock($singleton,LOCK_UN);fclose($singleton);}
}
