<?php
declare(strict_types=1);
/** A prepared montage is source-bound to the next three actual music occurrences. */
function music_hour_bridge_request(array $entries,int $index,array $catalog,float $start,int $hour):?array {
    $duration=round($hour-$start,3);
    if($duration<18||$duration>45)return null;
    $songs=[];
    foreach(array_slice($entries,$index)as$entry){
        if($entry['kind']!=='music')continue;
        $t=$catalog[$entry['trackId']]??null;
        if(!$t||!$t['available']||!$t['technicalReady']||$t['sha256']!==$entry['sha256'])return null;
        $audio=music_rotation_audio($t);$length=$duration/3;
        $cue=$t['cuePoints']['points']['vocalStart']??($audio['cueIn']+min(30.,($audio['cueOut']-$audio['cueIn'])/3));
        $cue=max($audio['cueIn'],min((float)$cue,$audio['cueOut']-$length));
        if($cue<$audio['cueIn']||$cue+$length>$audio['cueOut']+.001)return null;
        $songs[]=['entryId'=>$entry['id'],'trackId'=>$entry['trackId'],'sha256'=>$entry['sha256'],'artist'=>$t['artist'],'title'=>$t['title'],'cueIn'=>round($cue,3),'duration'=>$length];
        if(count($songs)===3)break;
    }
    if(count($songs)!==3)return null;
    $r=['hourUtc'=>$hour,'duration'=>$duration,'songs'=>$songs];
    $r['key']=hash('sha256',json_encode($r,JSON_THROW_ON_ERROR|JSON_PRESERVE_ZERO_FRACTION));
    return $r;
}
function music_hour_bridge_ready(array $request,array $tracks,array $catalog):?array {
    foreach($tracks as$source){
        if(($source['hourBridge']['key']??null)!==$request['key'])continue;
        $t=$catalog[$source['id']]??null;
        if($t&&$t['kind']==='jingle'&&$t['available']&&$t['technicalReady']&&!($t['jingle']['disabled']??false)&&$t['duration']>=$request['duration']-.001)return $t;
    }
    return null;
}
