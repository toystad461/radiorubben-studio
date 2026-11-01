<?php
declare(strict_types=1);
function music_rotation_time(float $seconds):string {
    $date=DateTimeImmutable::createFromFormat('U.u',sprintf('%.6F',$seconds));
    if(!$date)throw new RuntimeException('Ugyldig planlagt tid.');
    return $date->setTimezone(new DateTimeZone('Europe/Oslo'))->format('Y-m-d\\TH:i:s.vP');
}
function music_rotation_seconds(string $date):float{return (float)(new DateTimeImmutable($date))->format('U.u');}
/** Persistent one-hour rotation, selected in advance; no random choice in the player. */
function music_rotation_settings(array $v):array {
    if(!is_bool($v['enabled']??null)||(!is_int($v['crossfade']??null)&&!is_float($v['crossfade']??null))||!is_finite((float)$v['crossfade'])||$v['crossfade']<0||$v['crossfade']>10)throw new InvalidArgumentException('Crossfade må være mellom 0 og 10 sekunder.');
    return ['enabled'=>$v['enabled'],'crossfade'=>(float)$v['crossfade'],'horizonSeconds'=>3600,'musicPerJingle'=>3];
}
function music_rotation_audio(array $t):array {
    $duration=$t['duration'];$cue=$t['cuePoints']['points']??['start'=>0,'end'=>$duration,'vocalStart'=>null,'transition'=>null];
    music_cue_values($cue,(float)$duration);
    return ['cueIn'=>(float)$cue['start'],'cueOut'=>(float)$cue['end'],'crossfadeOut'=>0.0];
}
function music_rotation_playback(array $p,float $duration):array {
    foreach(['cueIn','cueOut','crossfadeOut']as$k)if((!is_int($p[$k]??null)&&!is_float($p[$k]??null))||!is_finite((float)$p[$k]))throw new InvalidArgumentException('Ugyldige avspillingspunkter.');
    if($p['cueIn']<0||$p['cueOut']>$duration+.001||$p['cueOut']-$p['cueIn']<.1||$p['crossfadeOut']<0||$p['crossfadeOut']>10||$p['crossfadeOut']>($p['cueOut']-$p['cueIn'])/2)throw new InvalidArgumentException('Avspillingspunktene er utenfor lydfilen.');
    return array_intersect_key($p,array_flip(['cueIn','cueOut','crossfadeOut']));
}
function music_rotation_plan(array $q,array $tracks,array $history=[]):array {
    $s=$q['studio'];$schedule=$s['schedule'];$profile=$s['profiles'][$schedule['programId']];$catalog=music_schedule_catalog($tracks,$s['policies']);
    $ids=array_column($q['entries'],'id');$context=array_values(array_filter($history,fn($h)=>!in_array($h['entryId']??null,$ids,true)));$previous=null;$rows=[];
    foreach($q['entries']as$e){
        $t=$catalog[$e['trackId']]??null;if(!$t||$t['sha256']!==$e['sha256']||!$t['duration'])return ['rows'=>$rows,'suggestions'=>[],'missing'=>['Køfilen mangler eller er endret.'],'profile'=>$profile];
        $p=music_rotation_playback($e['playback']??[],(float)$t['duration']);$at=new DateTimeImmutable($e['plannedAt']);$v=music_schedule_evaluate($t,$profile,$at,$context,$previous);
        if($e['kind']!==$t['kind'])$v['blocked'][]='Lydtypen er endret.';
        $row=array_merge($t,$e,$v,['duration'=>$p['cueOut']-$p['cueIn'],'sourceDuration'=>$t['duration'],'playback'=>$p]);$rows[]=$row;
        if($e['kind']==='music'){$context[]=['entryId'=>$e['id'],'trackId'=>$e['trackId'],'artist'=>$t['artist'],'at'=>$at->getTimestamp()];$previous=$t;}
    }
    return ['rows'=>$rows,'suggestions'=>[],'missing'=>[],'profile'=>$profile];
}
function music_rotation_extend(array $q,array $tracks,array $state,int $now):array {
    $s=&$q['studio'];$r=&$s['rotation'];$settings=music_rotation_settings($r);if(!$settings['enabled'])return $q;
    $q['entries']=array_values(array_filter($q['entries'],fn($e)=>!isset($state['outcomes'][$e['id']])));
    $history=music_playout_history_from_state($state);$catalog=music_schedule_catalog($tracks,$s['policies']);$profile=$s['profiles'][$s['schedule']['programId']];$inflight=music_playout_inflight_entries($state);
    $r['musicUsed']??=[];$r['jingleUsed']??=[];$r['sinceJingle']??=0;$r['shortage']=null;
    for($attempt=0;$attempt<100&&count($q['entries'])<95;$attempt++){
        $last=$q['entries']?end($q['entries']):null;
        $end=$last?music_rotation_seconds($last['plannedAt'])+($last['playback']['cueOut']-$last['playback']['cueIn']):$now;
        if($end-$now>=3600)break;
        $kind=$r['sinceJingle']>=3?'jingle':'music';$usedKey=$kind==='music'?'musicUsed':'jingleUsed';$available=[];
        $plan=music_rotation_plan($q,$tracks,$history);$context=$history;$previous=null;
        foreach($plan['rows']as$row)if($row['kind']==='music'){$context[]=['entryId'=>$row['id'],'trackId'=>$row['trackId'],'artist'=>$row['artist'],'at'=>strtotime($row['plannedAt'])];$previous=$row;}
        foreach($catalog as$t){
            if($t['kind']!==$kind||!$t['technicalReady']||!$t['available'])continue;
            if($kind==='jingle'&&(($t['jingle']['category']??'station')!=='station'||($t['jingle']['disabled']??false)))continue;
            $audio=music_rotation_audio($t);$overlap=0.;
            if($last&&$last['kind']==='music'&&$kind==='music'&&!isset($inflight[$last['id']]))$overlap=min($settings['crossfade'],($last['playback']['cueOut']-$last['playback']['cueIn'])/4,($audio['cueOut']-$audio['cueIn'])/4);
            $at=new DateTimeImmutable(music_rotation_time($end-$overlap));
            $v=music_schedule_evaluate($t,$profile,$at,$context,$previous);if($v['blocked'])continue;
            $available[]=[$t,$audio,$overlap,$at,$v['score']];
        }
        $fresh=array_values(array_filter($available,fn($c)=>!in_array($c[0]['trackId'],$r[$usedKey],true)));
        if(!$fresh&&$available){$r[$usedKey]=[];$fresh=$available;}
        if(!$fresh){$r['shortage']=$kind==='jingle'?'Ingen kontrollert stasjonsjingle er tilgjengelig.':'Ingen flere kontrollerte og godkjente sanger oppfyller programreglene.';break;}
        usort($fresh,fn($a,$b)=>($b[4]<=>$a[4])?:strcmp($a[0]['trackId'],$b[0]['trackId']));[$t,$audio,$overlap,$at]=$fresh[0];
        if($last&&!isset($inflight[$last['id']]))$q['entries'][count($q['entries'])-1]['playback']['crossfadeOut']=$overlap;
        $q['entries'][]=['id'=>bin2hex(random_bytes(16)),'kind'=>$kind,'trackId'=>$t['trackId'],'sha256'=>$t['sha256'],'locked'=>false,'plannedAt'=>$at->format('Y-m-d\\TH:i:s.vP'),'playback'=>$audio];
        $r[$usedKey][]=$t['trackId'];$r['sinceJingle']=$kind==='jingle'?0:$r['sinceJingle']+1;
    }
    $last=$q['entries']?end($q['entries']):null;$r['plannedUntil']=$last?music_rotation_time(music_rotation_seconds($last['plannedAt'])+$last['playback']['cueOut']-$last['playback']['cueIn']):null;
    $r['checkedAt']=gmdate('c',$now);return $q;
}
