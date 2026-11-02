<?php
declare(strict_types=1);
/** UTC hour identity distinguishes both occurrences of 02:00 at Oslo's autumn clock change. */
function music_hourly_settings(mixed $value): ?array {
    if($value===null)return null;
    if(!is_array($value)||!preg_match('/^[a-f0-9]{32}$/D',(string)($value['trackId']??''))
        ||!preg_match('/^[a-f0-9]{64}$/D',(string)($value['sha256']??''))
        ||!is_int($value['notBefore']??null)||$value['notBefore']<0||$value['notBefore']%3600!==0)
        throw new InvalidArgumentException('Ugyldig timesignal.');
    return array_intersect_key($value,array_flip(['trackId','sha256','notBefore']));
}
/** Reconcile only unclaimed future entries. Never change an active/prefetched occurrence. */
function music_hourly_reconcile(array $q,array $tracks,array $state,int $now):array {
    $config=music_hourly_settings($q['studio']['rotation']['hourly']??null);
    if(!$config&&!array_filter($q['entries'],fn($e)=>isset($e['hourlyHour'])))return $q;
    $catalog=music_schedule_catalog($tracks,$q['studio']['policies']);$tone=$catalog[$config['trackId']??'']??null;
    $ready=$tone&&$tone['sha256']===$config['sha256']&&$tone['kind']==='jingle'&&$tone['technicalReady']&&$tone['available']
        &&!($tone['jingle']['disabled']??false)&&($tone['jingle']['category']??'')==='hour';
    $q['studio']['rotation']['hourlyStatus']=!$config?'disabled':($ready?'ready':'unavailable');
    $inflight=music_playout_inflight_entries($state);$lastHour=($config['notBefore']??0)-3600;
    foreach(array_merge(array_values($state['outcomes']??[]),array_values($inflight))as$e)
        if(isset($e['hourlyHour']))$lastHour=max($lastHour,$e['hourlyHour']);
    $rows=[];$cursor=(float)$now;$previous=null;
    foreach($q['entries']as$e){
        if(isset($state['outcomes'][$e['id']]))continue;
        $protected=isset($inflight[$e['id']]);
        if(isset($e['hourlyHour'])&&!$protected){continue;}
        if($protected){
            $actual=$inflight[$e['id']];
            if(isset($actual['observedWallStart']))$cursor=(float)$actual['observedWallStart'];
            elseif($previous)$cursor-=$previous['playback']['crossfadeOut'];
            $rows[]=$e;$cursor+=$e['playback']['cueOut']-$e['playback']['cueIn'];$previous=$e;continue;
        }
        $hour=(int)(floor($cursor/3600)*3600);
        // A prefetched music tail cannot be changed to remove its crossfade.
        $canInsert=!$previous||!isset($inflight[$previous['id']])||$previous['playback']['crossfadeOut']==0;
        if($ready&&$hour>=$config['notBefore']&&$hour>$lastHour&&$canInsert&&count($rows)<97){
            if($previous){$rows[count($rows)-1]['playback']['crossfadeOut']=0.;}
            $signal=['id'=>md5('rr-hour:'.$hour.':'.$config['sha256']),'kind'=>'jingle','trackId'=>$config['trackId'],'sha256'=>$config['sha256'],
                'locked'=>false,'hourlyHour'=>$hour,'plannedAt'=>music_rotation_time($cursor),'playback'=>music_rotation_audio($tone)];
            $rows[]=$signal;$cursor+=$signal['playback']['cueOut']-$signal['playback']['cueIn'];$previous=$signal;$lastHour=$hour;
        }elseif($previous){$cursor-=$previous['playback']['crossfadeOut'];}
        // Restore music-only overlap after removing/repositioning a future signal.
        // Existing zero overlap is safe and deliberately retained.
        $e['plannedAt']=music_rotation_time($cursor);$rows[]=$e;
        $cursor+=$e['playback']['cueOut']-$e['playback']['cueIn'];$previous=$e;
    }
    // JSON storage normalizes 0.0 to 0. Reuse unchanged saved rows so a timer tick
    // does not create a false revision conflict for an operator.
    $saved=array_column($q['entries'],null,'id');
    foreach($rows as &$row)if(isset($saved[$row['id']])&&json_encode($row)===json_encode($saved[$row['id']]))$row=$saved[$row['id']];unset($row);
    $q['entries']=$rows;
    $q['studio']['rotation']['plannedUntil']=music_rotation_time($cursor);
    return $q;
}
/** Source-bound input for a later hour introduction; never claims clips or speech exist. */
function music_hourly_preview(array $q,int $hour):array {
    $songs=[];$found=false;
    foreach($q['entries']as$e){
        if(($e['hourlyHour']??null)===$hour){$found=true;continue;}
        if(!$found)continue;
        if(isset($e['hourlyHour']))break;
        if($e['kind']==='music')$songs[]=array_intersect_key($e,array_flip(['id','trackId','sha256','plannedAt']));
        if(count($songs)===3)break;
    }
    return ['hourUtc'=>$hour,'localHour'=>(new DateTimeImmutable('@'.$hour))->setTimezone(new DateTimeZone('Europe/Oslo'))->format('Y-m-d H:i P'),
        'queueRevision'=>$q['revision'],'songs'=>$songs,'speechReady'=>false,'clipsReady'=>false];
}
