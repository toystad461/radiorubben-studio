<?php
declare(strict_types=1);
/** Insert a saved audio occurrence at an exact, still-adjacent queue boundary. */
function music_live_insert(array $q,array $tracks,array $state,array $input):array {
    $types=['stikk','innlegg','jingle','reklame','lydteppe','program','hilsen','onske','sang'];
    if(!in_array($input['eventType']??null,$types,true)||isset($input['id']))throw new InvalidArgumentException('Velg en innslagstype og lagret lyd.');
    $ids=array_column($q['entries'],'id');$after=$input['afterId']??null;$before=$input['beforeId']??null;
    $index=$after===null?-1:array_search($after,$ids,true);
    if($index===false||($ids[$index+1]??null)!==$before)throw new DomainException('Plasseringen er endret. Velg plusstegnet på nytt.');
    $at=$index+1;$protected=music_playout_protected_ids($state);
    foreach(array_slice($q['entries'],$at)as$e)if(in_array($e['id'],$protected,true)||($e['locked']??false))throw new DomainException('Plasseringen er låst av motoren eller operatøren.');
    // Reuse all library, technical readiness and expiry checks. Reservations
    // before this boundary stay untouched; inserting before one is rejected above.
    $validationState=$state;$validationState['reserved']=[];
    $candidate=music_live_next($q,$tracks,$validationState,array_intersect_key($input,array_flip(['trackId','sha256'])));
    $new=array_values(array_filter($candidate['entries'],fn($e)=>!in_array($e['id'],$ids,true)));
    if(count($new)!==1)throw new RuntimeException('Innslaget kunne ikke klargjøres.');
    $entry=$new[0];$music=in_array($input['eventType'],['sang','onske'],true);
    if(($entry['kind']==='music')!==$music)throw new InvalidArgumentException('Velg musikk til sang/ønske, og lagret innslagslyd til de øvrige typene.');
    $entry['eventType']=$input['eventType'];array_splice($q['entries'],$at,0,[$entry]);
    return music_live_retime($q,$state);
}
/** Caller owns the bus and queue locks. Never revoke PCM or decoder ownership. */
function music_live_next(array $q,array $tracks,array $state,array $input):array {
    if(!($q['studio']['rotation']['enabled']??false))throw new InvalidArgumentException('Spill neste krever standardrotasjon.');
    if(!empty($state['reserved']))throw new DomainException('Overgangen er låst av motoren. Vent til neste låt har startet.');
    $held=music_playout_protected_ids($state);$at=0;
    foreach($q['entries']as$i=>$e)if(in_array($e['id'],$held,true))$at=$i+1;
    if(isset($input['id'])){
        $index=array_search($input['id'],array_column($q['entries'],'id'),true);
        if($index===false||in_array($input['id'],$held,true)||$q['entries'][$index]['locked'])throw new InvalidArgumentException('Innslaget er låst eller finnes ikke.');
        $entry=$q['entries'][$index];
        if(isset($entry['hourlyHour']))throw new InvalidArgumentException('Automatiske timesignaler styres av timeplanen.');
        array_splice($q['entries'],$index,1);
    }else{
        $catalog=music_schedule_catalog($tracks,$q['studio']['policies']);$t=$catalog[$input['trackId']??'']??null;
        if(!$t||$t['sha256']!==($input['sha256']??null)||!$t['available']||!$t['technicalReady']||($t['jingle']['disabled']??false))throw new InvalidArgumentException('Velg et klargjort innslag fra biblioteket.');
        $entry=['id'=>bin2hex(random_bytes(16)),'kind'=>$t['kind'],'trackId'=>$t['trackId'],'sha256'=>$t['sha256'],'locked'=>false,'plannedAt'=>music_rotation_time(microtime(true)),'playback'=>music_rotation_audio($t)];
    }
    $catalog=music_schedule_catalog($tracks,$q['studio']['policies']);$selected=$catalog[$entry['trackId']]??null;
    if(!$selected||$selected['sha256']!==$entry['sha256']||!$selected['technicalReady']||!$selected['available']||($selected['jingle']['disabled']??false))throw new InvalidArgumentException('Innslaget er ikke klart for avspilling.');
    foreach($tracks as$source)if($source['id']===$entry['trackId']&&isset($source['hourBridge']))throw new InvalidArgumentException('Timeinnslag med musikkglimt styres av timeplanen.');
    foreach($tracks as$source)if($source['id']===$entry['trackId']&&isset($source['syntheticSpeech']['expiresAt'])){
        $expiry=strtotime($source['syntheticSpeech']['expiresAt']);
        if($expiry===false||$expiry<=time())throw new InvalidArgumentException('Timestikket er utløpt. Lag et nytt for riktig time.');
    }
    array_splice($q['entries'],$at,0,[$entry]);
    return music_live_retime($q,$state);
}
/** Recalculate only future timing; a jingle never overlaps its predecessor. */
function music_live_retime(array $q,array $state):array {
    $held=music_playout_inflight_entries($state);$cursor=microtime(true);$previous=null;
    foreach($q['entries']as$i=>&$e){
        if(isset($state['outcomes'][$e['id']]))continue;
        $overlap=$previous&&$previous['kind']==='music'&&$e['kind']==='music'?$previous['playback']['crossfadeOut']:0;
        if(isset($held[$e['id']]))$cursor=isset($held[$e['id']]['observedWallStart'])?(float)$held[$e['id']]['observedWallStart']:music_rotation_seconds($e['plannedAt']);
        else {$cursor-=$overlap;$e['plannedAt']=music_rotation_time($cursor);}
        $cursor+=$e['playback']['cueOut']-$e['playback']['cueIn'];$previous=$e;
    }unset($e);
    $q['studio']['rotation']['plannedUntil']=music_rotation_time($cursor);return $q;
}
