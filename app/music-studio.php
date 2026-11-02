<?php
declare(strict_types=1);
require_once __DIR__.'/music-play-queue.php';
require_once __DIR__.'/music-scheduling.php';
require_once __DIR__.'/music-playout.php';
require_once __DIR__.'/music-live-queue.php';
function music_studio_settings(array $q): array {
    return $q['studio']??['profiles'=>['god-morgen-bomlo'=>music_schedule_defaults()],'policies'=>[],'schedule'=>null];
}
function music_studio_view(?string $path=null,?string $library=null,?array $playout=null): array {
    $q=music_play_queue_read($path);$libraryError=null;try {$tracks=music_library_read($library,'all');}catch(Throwable){$tracks=[];$libraryError='Biblioteket kunne ikke leses. Stopp er fortsatt tilgjengelig; kontroller biblioteket før ny start.';}$q['studio']=music_studio_settings($q);
    $result=music_play_queue_snapshot($q,$tracks);$result['libraryError']=$libraryError;$result['studio']=$q['studio'];$result['catalog']=array_values(music_schedule_catalog($tracks,$q['studio']['policies']));
    // Both stored music and jingles can be selected for the next position.
    $result['player']=$playout?music_playout_snapshot($playout['root']):['online'=>false,'mode'=>'unconfigured'];
    // This same-origin route is served by an authenticated Nginx proxy; never
    // expose loopback addresses, upstream credentials or arbitrary URLs.
    if (($result['player']['engine']??null)==='liquidsoap' && ($playout['stream_path']??null)==='/music-test-stream/') $result['player']['streamUrl']='/music-test-stream/';
    $r=$q['studio']['rotation']??[];$request=$r['bridgeRequest']??null;
    $result['hourBridge']=['enabled'=>($r['bridgeEnabled']??false)===true,'requestedSeconds'=>$request['duration']??null,'ready'=>$request&&function_exists('music_hour_bridge_ready')?(bool)music_hour_bridge_ready($request,$tracks,music_schedule_catalog($tracks,$q['studio']['policies'])):false,'libraryFull'=>count($tracks)>=MUSIC_LIBRARY_COUNT_LIMIT];
    $result['plan']=null;
    if ($q['studio']['schedule']) $result['plan']=music_schedule_plan($q,$tracks,music_playout_history_from_state($result['player']+music_playout_empty()),0);
    return $result;
}
/** All Studio writes use the inherited queue lock and a revision. The bus lock serializes player commands. */
function music_studio_change(array $input,?array $user,string $csrf,string $expected,?string $path=null,?string $library=null,?array $playout=null): array {
    if (!studio_can($user,'produce') || $expected==='' || !hash_equals($expected,$csrf)) throw new InvalidArgumentException('Handlingen er ikke autorisert.');
    if (!is_int($input['revision']??null)) throw new InvalidArgumentException('Ugyldig køversjon.');
    $path??=music_play_queue_path();$library??=music_library_root();music_play_queue_location($path);
    $bus=$playout?music_playout_lock($playout['root']):null;$lock=fopen($path.'.lock','c+b');$changed=false;
    if (!$lock) {if($bus)fclose($bus);throw new RuntimeException('Køen kunne ikke låses.');}
    try {
        if (!flock($lock,LOCK_EX)) throw new RuntimeException('Køen er opptatt.');chmod($path.'.lock',0600);
        $q=music_play_queue_load($path);if ($q['revision']!==$input['revision']) throw new DomainException('Køen er endret av en annen operatør. Oppdater først.');$before=$q['entries'];
        $q['studio']=music_studio_settings($q);$action=$input['action']??'';$tracks=in_array($action,['pause','stop'],true)?[]:music_library_read($library,'all');$s=$playout?music_playout_state($playout['root']):music_playout_empty();
        $history=$playout?music_playout_history($playout['root']):[];$action=$input['action']??'';$command=null;
        $oldControl=$playout?music_playout_json($playout['root'].'/control.json'):[];
        if (isset($oldControl['command']['id']) && $oldControl['command']['id']!==$s['ack']) throw new DomainException('En spillerkommando venter på bekreftelse. Oppdater først.');
        $entryIndex=array_search($input['id']??null,array_column($q['entries'],'id'),true);
        $protected=music_playout_protected_ids($s);
        if (in_array($action,['remove','move','replace'],true) && ($entryIndex===false || in_array($input['id'],$protected,true))) throw new InvalidArgumentException('Innslaget mangler eller er allerede behandlet av spilleren.');
        if (in_array($action,['remove','move','replace'],true) && ($q['entries'][$entryIndex]['locked']??false)) throw new InvalidArgumentException('Lås opp innslaget først.');
        if(($q['studio']['rotation']['enabled']??false)&&!in_array($action,['standard','hourly','fill','start','stop','pause','sync','playnext','insert','remove'],true))throw new InvalidArgumentException('Slå av standardrotasjon før manuell køredigering.');
        if(in_array($action,['playnext','insert','remove'],true)&&array_filter(music_playout_inflight_entries($s),fn($e)=>isset($e['hourBridgeHour'])))throw new DomainException('Timeinnslaget er låst i motoren. Vent til det er fullført før du endrer køen.');
        if(in_array($action,['playnext','insert'],true)){
            if(!$playout||!music_playout_heartbeat_fresh($s,music_playout_json($playout['root'].'/heartbeat.json')))throw new RuntimeException('Spillerstatus må oppdateres.');
            $q=$action==='insert'?music_live_insert($q,$tracks,$s,$input):music_live_next($q,$tracks,$s,$input);$nextId=null;foreach($q['entries']as$e)if(!in_array($e['id'],$protected,true)){$nextId=$e['id'];break;}$changed=true;
        } elseif($action==='standard'){
            if(!studio_can($user,'settings'))throw new InvalidArgumentException('Bare produsent/administrator kan endre standardavspilling.');
            if(music_playout_inflight_entries($s)||in_array($s['mode'],['starting','playing'],true))throw new InvalidArgumentException('Stopp avspillingen før du endrer standardrotasjonen.');
            $rotationInput=$input['rotation']??[];if(!array_key_exists('hourly',$rotationInput))$rotationInput['hourly']=$q['studio']['rotation']['hourly']??null;
            $rotation=music_rotation_settings($rotationInput);
            if(!$q['studio']['schedule'])throw new InvalidArgumentException('Lagre program og starttid først.');
            if(array_filter($q['entries'],fn($e)=>$e['locked']??false))throw new InvalidArgumentException('Lås opp innslag før du lager standardrotasjon.');
            $q['studio']['rotation']=$rotation;$q['entries']=[];
            if($rotation['enabled'])$q=music_rotation_extend($q,$tracks,$s,time());$changed=true;
        } elseif($action==='hourly'){
            if(!studio_can($user,'settings'))throw new InvalidArgumentException('Bare produsent/administrator kan endre timesignal.');
            $hourly=music_hourly_settings($input['hourly']??null);
            $catalog=music_schedule_catalog($tracks,$q['studio']['policies']);$tone=$catalog[$hourly['trackId']??'']??null;
            if($hourly&&(!$tone||$tone['sha256']!==$hourly['sha256']||$tone['kind']!=='jingle'||!$tone['technicalReady']||!$tone['available']||($tone['jingle']['disabled']??false)||($tone['jingle']['category']??'')!=='hour'))throw new InvalidArgumentException('Timesignalet er ikke klart.');
            if(!($q['studio']['rotation']['enabled']??false))throw new InvalidArgumentException('Standardrotasjon må være aktiv.');
            $q['studio']['rotation']['hourly']=$hourly;
            $q=music_rotation_extend($q,$tracks,$s,time());$changed=true;
        } elseif($action==='fill'){
            if(!($q['studio']['rotation']['enabled']??false)||!in_array($s['mode'],['playing','starting'],true))return ['revision'=>$q['revision'],'saved'=>false];
            $next=music_rotation_extend($q,$tracks,$s,time());
            if($next['entries']===$q['entries']&&($next['studio']['rotation']['shortage']??null)===($q['studio']['rotation']['shortage']??null)&&($next['studio']['rotation']['bridgeRequest']??null)===($q['studio']['rotation']['bridgeRequest']??null))return ['revision'=>$q['revision'],'saved'=>false];
            $q=$next;$changed=true;
        } elseif ($action==='schedule') {
            $schedule=$input['schedule']??[];
            if (!isset($q['studio']['profiles'][$schedule['programId']??''])) throw new InvalidArgumentException('Velg et eksisterende program.');
            music_schedule_time((string)($schedule['date']??''),(string)($schedule['time']??''),(string)($schedule['occurrence']??''));
            if (music_playout_inflight_entries($s)) throw new InvalidArgumentException('Stopp avspilleren før du endrer programmets starttid.');
            $q['studio']['schedule']=array_intersect_key($schedule,array_flip(['programId','date','time','occurrence']));$changed=true;
        } elseif ($action==='profile') {
            if (!studio_can($user,'settings')) throw new InvalidArgumentException('Bare produsent/administrator kan endre regler.');
            $p=music_schedule_profile($input['profile']??[]);$p['proposal']=false;$q['studio']['profiles'][$p['programId']]=$p;$changed=true;
        } elseif ($action==='policy') {
            if (!studio_can($user,'settings')) throw new InvalidArgumentException('Bare produsent/administrator kan godkjenne musikk.');
            $track=null;foreach ($tracks as $t) if ($t['id']===($input['trackId']??null) && $t['sha256']===($input['sha256']??null)) $track=$t;
            if (!$track || ($track['kind']??'music')!=='music') throw new InvalidArgumentException('Lydversjonen finnes ikke lenger eller er ikke musikk.');
            $handle=music_library_verified_handle($track,$library);fclose($handle);
            $v=music_schedule_policy($input['policy']??[],$track['sha256']);foreach ($v['programs'] as $program) if (!isset($q['studio']['profiles'][$program])) throw new InvalidArgumentException('Ukjent programtilhørighet.');$v['updatedAt']=gmdate('c');$v['updatedBy']=(string)($user['id']??$user['role']);$q['studio']['policies'][$track['id']]=$v;$changed=true;
        } elseif ($action==='suggest' || $action==='replace') {
            $plan=music_schedule_plan($q,$tracks,$history,3,$action==='replace'?$input['id']:null);
            $result=['proposal'=>$plan,'revision'=>$q['revision']];
            if (($input['commit']??false)!==true) return $result;
            // Recompute under lock and require the exact displayed candidates; never substitute after review.
            if (($input['trackIds']??null)!==array_column($plan['suggestions'],'trackId')) throw new DomainException('Forslaget har endret seg. Se nye forslag før lagring.');
            if (!$plan['suggestions']) throw new InvalidArgumentException('Ingen forslag oppfyller sperrene.');
            $new=[];foreach ($plan['suggestions'] as $t) $new[]=['id'=>bin2hex(random_bytes(16)),'kind'=>'music','trackId'=>$t['trackId'],'sha256'=>$t['sha256'],'locked'=>false,'selection'=>array_intersect_key($t,array_flip(['plannedAt','reasons','sources','analysisSha256','warnings']))];
            if ($action==='replace') array_splice($q['entries'],$entryIndex,1,$new);else $q['entries']=array_merge($q['entries'],$new);$changed=true;
        } elseif ($action==='reset') {
            if (music_playout_inflight_entries($s) || $s['mode']==='playing') throw new InvalidArgumentException('Stopp spilleren før en ny testkø.');
            if (array_filter($q['entries'],fn($e)=>$e['locked']??false)) throw new InvalidArgumentException('Lås opp innslagene før en ny testkø.');
            $q['entries']=[];$changed=true;
        } elseif ($action==='add') {
            $track=null;foreach ($tracks as $t) if ($t['id']===($input['trackId']??null) && $t['sha256']===($input['sha256']??null) && $t['available']) $track=$t;
            if (!$track) throw new InvalidArgumentException('Lydfilen mangler eller er endret.');
            if(($track['kind']??'music')==='jingle' && (($track['jingle']['disabled']??false)||!music_library_metadata($track)))throw new InvalidArgumentException('Jinglen er deaktivert eller ikke klargjort.');
            $q['entries'][]=['id'=>bin2hex(random_bytes(16)),'kind'=>$track['kind']??'music','trackId'=>$track['id'],'sha256'=>$track['sha256'],'locked'=>false];$changed=true;
        } elseif ($action==='lock') {
            if ($entryIndex===false) throw new InvalidArgumentException('Innslaget finnes ikke.');
            if (isset(music_playout_inflight_entries($s)[$input['id']])) throw new InvalidArgumentException('Innslaget er reservert eller aktivt i motoren. Stopp før endring.');
            if (!is_bool($input['locked']??null)) throw new InvalidArgumentException('Ugyldig lås.');$q['entries'][$entryIndex]['locked']=$input['locked'];$changed=true;
        } elseif ($action==='remove' || $action==='move') {
            if(isset($q['entries'][$entryIndex]['hourlyHour']))throw new InvalidArgumentException('Automatiske timesignaler styres fra Jingles.');
            $entry=$q['entries'][$entryIndex];array_splice($q['entries'],$entryIndex,1);
            if ($action==='move') {
                $target=($input['beforeId']??null)===null?count($q['entries']):array_search($input['beforeId'],array_column($q['entries'],'id'),true);
                if ($target===false) throw new InvalidArgumentException('Ugyldig plassering.');
                array_splice($q['entries'],$target,0,[$entry]);
                // Locked and already played entries retain their positions, not just their identities.
                foreach ($before as $i=>$e) if (($e['locked']??false) || in_array($e['id'],$protected,true)) if (($q['entries'][$i]['id']??null)!==$e['id']) throw new InvalidArgumentException('Flyttingen krysser et låst eller behandlet innslag.');
            }$changed=true;
        } elseif (in_array($action,['start','pause','stop'],true)) {
            if (!$playout) throw new InvalidArgumentException('VPS-avspilleren er ikke installert.');
            if ($action==='pause' && ($s['engine']??null)==='liquidsoap') throw new InvalidArgumentException('Pause støttes ikke av Liquidsoap-testmotoren. Bruk Stopp for å avslutte avspillingen.');
            $live=music_playout_json($playout['root'].'/heartbeat.json');
            if (!music_playout_heartbeat_fresh($s,$live)) throw new RuntimeException('Spillertjenesten svarer ikke.');
            if (($input['playerRevision']??null)!==$s['seq']) throw new DomainException('Spillerstatus er endret. Oppdater først.');
            if($action==='start'&&($q['studio']['rotation']['enabled']??false)&&!music_playout_inflight_entries($s)&&!in_array($s['mode'],['starting','playing'],true)){
                $q['entries']=array_values(array_filter($q['entries'],fn($e)=>!isset($s['outcomes'][$e['id']])));
                $next=(float)time();foreach($q['entries']as&$entry){$entry['plannedAt']=music_rotation_time($next);$next+=$entry['playback']['cueOut']-$entry['playback']['cueIn']-$entry['playback']['crossfadeOut'];}unset($entry);
                $q=music_rotation_extend($q,$tracks,$s,time());$changed=true;
            }
            $command=['id'=>bin2hex(random_bytes(16)),'action'=>$action,'at'=>gmdate('c')];
            if (in_array($action,['pause','stop'],true)) {music_playout_atomic($playout['root'].'/control.json',array_replace($oldControl,['command'=>$command]));return ['revision'=>$q['revision'],'saved'=>true];}
        } elseif ($action!=='sync') throw new InvalidArgumentException('Ukjent studiohandling.');
        if ($changed && in_array($action,['remove','replace'],true)) foreach ($before as $i=>$e) {
            if (in_array($e['id'],$protected,true) && ($q['entries'][$i]['id']??null)!==$e['id']) throw new InvalidArgumentException('Endringen flytter et innslag som er reservert eller behandlet av spilleren.');
        }
        if($changed&&in_array($action,['playnext','insert','remove'],true)&&($q['studio']['rotation']['enabled']??false)){
            $q=music_live_retime($q,$s);
            $q=music_hourly_reconcile($q,$tracks,$s,time());
            foreach($before as$i=>$e)if(($e['locked']??false)||in_array($e['id'],$protected,true)){
                if(($q['entries'][$i]['id']??null)!==$e['id'])throw new InvalidArgumentException('Endringen flytter et låst innslag.');
            }
            $plan=music_schedule_plan($q,$tracks,$history,0);
            if(count($plan['rows'])!==count($q['entries']))throw new InvalidArgumentException('Køen kunne ikke beregnes.');
            $beforePlan=music_schedule_plan(array_replace($q,['entries'=>$before]),$tracks,$history,0);
            $blockedBefore=array_column($beforePlan['rows'],'blocked','id');
            foreach($plan['rows']as$row)if($row['blocked']&&((($action==='playnext'&&$row['id']===$nextId)||($action==='insert'&&!in_array($row['id'],array_column($before,'id'),true)))||!($blockedBefore[$row['id']]??[])))throw new InvalidArgumentException('Endringen bryter programreglene: '.implode(' · ',$row['blocked']));
        }
        if (count($q['entries'])>100) throw new InvalidArgumentException('Køen kan ha inntil 100 innslag.');
        if ($changed) {$q['revision']++;$q['updatedAt']=gmdate('c');$q['updatedBy']=(string)($user['id']??$user['role']);}
        // Barrier first: if any copy/write fails, no later entry from an old manifest may start.
        if ($playout) music_playout_atomic($playout['root'].'/control.json',array_replace($oldControl,['ready'=>false]));
        if ($changed) music_playout_atomic($path,$q,0600);
        if ($playout) {
            if (!$q['studio']['schedule']) throw new InvalidArgumentException('Valgene er lagret. Velg program/dato før synkronisering med spilleren.');
            // History is used for selection; planned queue context for manifest avoids counting itself twice.
            $plan=music_schedule_plan($q,$tracks,$history,0);
            music_playout_publish($playout['root'],$q,$plan,$tracks,$library,$command);
        }
        return ['revision'=>$q['revision'],'saved'=>true];
    } finally {flock($lock,LOCK_UN);fclose($lock);if ($bus) {flock($bus,LOCK_UN);fclose($bus);}}
}
