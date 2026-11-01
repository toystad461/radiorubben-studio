<?php
declare(strict_types=1);
// No bootstrap, web credentials, external endpoint or AI client in the player process.
function music_playout_config(): ?array {
    $path=dirname(__DIR__).'/config/music-playout.local.php';
    if (!is_file($path)) return null;
    $config=require $path;
    if (!is_array($config) || ($config['enabled']??false)!==true || !is_string($config['root']??null)) return null;
    music_playout_root($config['root']);return $config;
}
function music_playout_root(string $root): void {
    if (!is_dir($root) || is_link($root) || realpath($root)!==$root) throw new RuntimeException('Testavspilleren er ikke klargjort.');
}
function music_playout_atomic(string $path,array $data,int $mode=0660): void {
    if (is_link($path)) throw new RuntimeException('Ugyldig spillerfil.');
    $tmp=tempnam(dirname($path),'.rr-');$h=false;
    try {
        if (!$tmp || !chmod($tmp,$mode) || !($h=fopen($tmp,'wb'))) throw new RuntimeException('Kan ikke lagre spillerdata.');
        $raw=json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
        if (fwrite($h,$raw)!==strlen($raw) || !fflush($h) || !fsync($h)) throw new RuntimeException('Spillerdata kunne ikke bekreftes.');
        fclose($h);$h=false;if (!rename($tmp,$path)) throw new RuntimeException('Spillerdata kunne ikke lagres.');$tmp=null;
    } finally {if (is_resource($h)) fclose($h);if ($tmp && is_file($tmp)) unlink($tmp);}
}
function music_playout_json(string $path,array $default=[]): array {
    if (is_link($path)) throw new RuntimeException('Ugyldig spillerfil.');
    if (!file_exists($path)) return $default;
    $v=json_decode((string)file_get_contents($path),true,64,JSON_THROW_ON_ERROR);
    if (!is_array($v)) throw new RuntimeException('Spillerdata må kontrolleres.');return $v;
}
function music_playout_lock(string $root) {
    music_playout_root($root);$path=$root.'/control.lock';
    if (is_link($path)) throw new RuntimeException('Ugyldig spillerlås.');
    $h=fopen($path,'c+b');if (!$h) throw new RuntimeException('Spilleren er opptatt.');
    try {
        if (!flock($h,LOCK_EX)) throw new RuntimeException('Spilleren er opptatt.');
        // The web and player users share a group, not ownership. Only adjust a
        // newly created/nonconforming lock; chmod on the peer's 0660 file fails.
        $stat=fstat($h);
        if ($stat===false || (($stat['mode']&0777)!==0660 && !chmod($path,0660))) throw new RuntimeException('Spillerlåsen har feil rettigheter.');
        return $h;
    } catch (Throwable $e) {flock($h,LOCK_UN);fclose($h);throw $e;}
}
function music_playout_empty(): array {return ['seq'=>0,'mode'=>'stopped','current'=>null,'reserved'=>[],'active'=>[],'outcomes'=>[],'lastCompleted'=>null,'lastStarted'=>null,'ack'=>null,'message'=>'Ikke startet'];}
/** Journal is authoritative; state changes and their events share a single fsynced record. */
function music_playout_state(string $root): array {
    $path=$root.'/events.jsonl';if (is_link($path)) throw new RuntimeException('Ugyldig hendelseslogg.');
    if (!is_file($path)) return music_playout_empty();
    $h=fopen($path,'rb');$state=music_playout_empty();
    try {while (($line=fgets($h))!==false) {
        if (!str_ends_with($line,"\n")) throw new RuntimeException('Ufullstendig spillerlogg. Krever kontroll; ingen automatisk gjenspilling.');
        $record=json_decode($line,true,32,JSON_THROW_ON_ERROR);
        if (($record['seq']??null)!==$state['seq']+1 || !is_array($record['state']??null)) throw new RuntimeException('Ugyldig hendelsesrekkefølge.');$outcomes=$state['outcomes'];$state=$record['state'];$state['outcomes']=$outcomes;
        if (in_array($record['event']??'', ['completed','interrupted','failed'],true) && isset($record['detail']['entry']['id'])) $state['outcomes'][$record['detail']['entry']['id']]=$record['detail']['entry'];
    }} finally {fclose($h);}return $state;
}
function music_playout_event(string $root,array &$s,string $event,array $detail=[]): void {
    $s['seq']++;$s['updatedAt']=gmdate('c');$record=['seq'=>$s['seq'],'event'=>$event,'at'=>$s['updatedAt'],'output'=>'isolated-test','detail'=>$detail,'state'=>$s];
    unset($record['state']['outcomes']); // Outcomes are journal deltas, not an ever-growing copy in every record.
    $raw=json_encode($record,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)."\n";$path=$root.'/events.jsonl';
    if (is_link($path)) throw new RuntimeException('Ugyldig hendelseslogg.');
    $h=fopen($path,'ab');if (!$h) throw new RuntimeException('Hendelsen kunne ikke lagres.');
    try {chmod($path,0660);if (fwrite($h,$raw)!==strlen($raw) || !fflush($h) || !fsync($h)) throw new RuntimeException('Hendelsen kunne ikke bekreftes.');} finally {fclose($h);}
}
function music_playout_history(string $root): array {
    return music_playout_history_from_state(music_playout_state($root));
}
/** Reserved audio and every contributor to a crossfade remain owned by the engine. */
function music_playout_inflight_entries(array $s): array {
    $entries=[];
    foreach (['reserved','active'] as $key) foreach ($s[$key]??[] as $entry) $entries[$entry['id']]=$entry;
    if ($s['current']??null) $entries[$s['current']['id']]=$s['current'];
    return $entries;
}
function music_playout_protected_ids(array $s): array {
    return array_values(array_unique(array_merge(array_keys($s['outcomes']??[]),array_keys(music_playout_inflight_entries($s)))));
}
/** A heartbeat proves only the matching engine is alive, never that audio is connected. */
function music_playout_heartbeat_fresh(array $s,array $live,?float $now=null): bool {
    $timestamp=$live['timestamp']??null;
    if ((!is_int($timestamp) && !is_float($timestamp)) || !is_finite((float)$timestamp)
        || ($live['engine']??null)!==($s['engine']??null)) return false;
    $age=($now??microtime(true))-$timestamp;
    return $age>=0 && $age<5;
}
function music_playout_history_from_state(array $s): array {
    $history=[];
    $entries=array_merge(array_values($s['outcomes']??[]),array_values($s['active']??[]),($s['current']??null)?[$s['current']]:[]);
    foreach ($entries as $entry) {
        if (!isset($entry['startedAt']) || isset($history[$entry['id']])) continue;
        $at=strtotime($entry['startedAt']);if ($at===false) continue;
        $history[$entry['id']]=['entryId'=>$entry['id'],'trackId'=>$entry['trackId'],'artist'=>$entry['artist'],'at'=>$at];
    }
    return array_values($history);
}
function music_playout_snapshot(string $root): array {
    $h=music_playout_lock($root);
    try {$s=music_playout_state($root);$live=music_playout_json($root.'/heartbeat.json');$control=music_playout_json($root.'/control.json');
        $s['online']=music_playout_heartbeat_fresh($s,$live);
        $s['streamConnected']=$s['online'] && ($live['connected']??false)===true;
        $s['capabilities']=['pause'=>($s['engine']??null)!=='liquidsoap'];
        $position=$live['position']??0;$s['position']=$s['online'] && (is_int($position)||is_float($position)) && is_finite((float)$position) && $position>=0?$position:0;
        $s['segments']=$s['online'] && ($s['engine']??null)!=='liquidsoap'?($live['segments']??[]):[];
        $protected=music_playout_protected_ids($s);
        $s['next']=null;foreach ($control['entries']??[] as $entry) if (!in_array($entry['id'],$protected,true)) {$s['next']=$entry;break;}
        $s['programProfile']=$control['profile']??null;
        $s['pendingCommand']=isset($control['command']['id']) && $control['command']['id']!==$s['ack'];$s['ready']=$control['ready']??false;$s['manifestRevision']=$control['revision']??null;
        return $s;
    } finally {flock($h,LOCK_UN);fclose($h);}
}
/** Copy verified originals into a bounded private spool. The player never reads Studio secrets or originals. */
function music_playout_publish(string $root,array $q,array $plan,array $tracks,string $libraryRoot,?array $command=null): void {
    $old=music_playout_json($root.'/control.json');$byId=array_column($tracks,null,'id');$entries=[];
    if (count($plan['rows'])!==count($q['entries'])) throw new InvalidArgumentException('Køen har ukjent varighet eller endrede filer. Rett den før avvikling.');
    foreach ($plan['rows'] as $row) {
        $entry=array_intersect_key($row,array_flip(['id','kind','trackId','sha256','artist','title','duration','plannedAt','blocked','reasons','cuePoints','audioAnalysis','sources','energy','bpm','key']));
        if (!$entry['blocked']) {
            $t=$byId[$row['trackId']];$source=music_library_verified_handle($t,$libraryRoot);$target=$root.'/audio/'.$row['sha256'].'.mp3';$tmp=null;
            try {
                if (is_link($target)) throw new RuntimeException('Ugyldig lydkopi.');
                if (!is_file($target) || hash_file('sha256',$target)!==$row['sha256']) {
                    $tmp=tempnam($root.'/audio','.copy-');$out=fopen($tmp,'wb');
                    try {if (!$out || stream_copy_to_stream($source,$out)!==$t['bytes'] || !fflush($out) || !fsync($out)) throw new RuntimeException('Testlyden kunne ikke klargjøres.');} finally {if (is_resource($out)) fclose($out);}
                    if (hash_file('sha256',$tmp)!==$row['sha256'] || !chmod($tmp,0640) || !rename($tmp,$target)) throw new RuntimeException('Testlyden kunne ikke bekreftes.');$tmp=null;
                }
            } finally {fclose($source);if ($tmp && is_file($tmp)) unlink($tmp);}
        }
        $entries[]=$entry;
    }
    music_playout_atomic($root.'/control.json',['ready'=>true,'revision'=>$q['revision'],'entries'=>$entries,'profile'=>$plan['profile'],'command'=>$command??($old['command']??null)]);
    $keep=array_column($entries,'sha256');$state=music_playout_state($root);
    foreach (['reserved','active'] as $key) foreach ($state[$key]??[] as $entry) $keep[]=$entry['sha256'];
    if ($state['current']??null) $keep[]=$state['current']['sha256'];
    foreach (glob($root.'/audio/*.mp3')?:[] as $copy) if (!is_link($copy) && preg_match('/^[a-f0-9]{64}\.mp3$/D',basename($copy)) && !in_array(basename($copy,'.mp3'),$keep,true)) unlink($copy);
}
