<?php
declare(strict_types=1);
require __DIR__.'/music-rotation.php';
$hour=strtotime('2026-11-02T12:00:00+01:00');
$tone=$tracks[105];$tone['jingle']['category']='hour';$tracks[105]=$tone;
$q['studio']['rotation']['hourly']=['trackId'=>$tone['id'],'sha256'=>$tone['sha256'],'notBefore'=>$hour];
$queue=music_rotation_extend($q,$tracks,music_playout_empty(),$hour-360);
$signals=array_values(array_filter($queue['entries'],fn($e)=>isset($e['hourlyHour'])));
check(count($signals)===1&&$signals[0]['hourlyHour']===$hour,'one signal at the upcoming hour');
$idx=array_search($signals[0]['id'],array_column($queue['entries'],'id'),true);
$previous=$queue['entries'][$idx-1];
check($previous['playback']['crossfadeOut']==0,'no overlap into the signal');
check(music_rotation_seconds($signals[0]['plannedAt'])>=$hour,'not before top of hour');
check(abs(music_rotation_seconds($signals[0]['plannedAt'])-music_rotation_seconds($previous['plannedAt'])-($previous['playback']['cueOut']-$previous['playback']['cueIn']))<.002,'previous song ends naturally');
$again=music_hourly_reconcile($queue,$tracks,music_playout_empty(),$hour-360);
check($again['entries']===$queue['entries'],'refill has stable occurrence IDs and timing');
$preview=music_hourly_preview($queue,$hour);
check(count($preview['songs'])===3&&!$preview['speechReady']&&!$preview['clipsReady'],'source-bound next three songs, no invented audio');
$state=music_playout_empty();$state['current']=$queue['entries'][0]+['observedWallStart'=>$hour-360.];
$state['reserved'][$queue['entries'][1]['id']]=$queue['entries'][1];
$live=music_hourly_reconcile($queue,$tracks,$state,$hour-300);
check(array_slice($live['entries'],0,2)===array_slice($queue['entries'],0,2),'active and reserved occurrences untouched');
$state=music_playout_empty();$state['outcomes'][$signals[0]['id']]=$signals[0]+['status'=>'interrupted'];
$resumed=music_hourly_reconcile($queue,$tracks,$state,$hour+60);
check(!array_filter($resumed['entries'],fn($e)=>($e['hourlyHour']??null)===$hour),'no replay after interruption or restart');
$unready=$tracks;$unready[105]['technicalCheck']['sourceSha256']='stale';
$fallback=music_hourly_reconcile($queue,$unready,music_playout_empty(),$hour-360);
check(!array_filter($fallback['entries'],fn($e)=>isset($e['hourlyHour']))&&count($fallback['entries'])===count($queue['entries'])-1,'missing signal falls back to existing music');
foreach(['2026-10-25T02:00:00+02:00','2026-10-25T02:00:00+01:00','2026-03-29T03:00:00+02:00']as$local){
 $h=strtotime($local);$v=$q;$v['studio']['rotation']['hourly']['notBefore']=$h;
 $result=music_rotation_extend($v,$tracks,music_playout_empty(),$h-60);
 $found=array_values(array_filter($result['entries'],fn($e)=>isset($e['hourlyHour'])));
 check($found[0]['hourlyHour']===$h,'DST hour identity '.$local);
}
reject(fn()=>music_hourly_settings(['trackId'=>'bad','sha256'=>'bad','notBefore'=>$hour]),'invalid signal reference rejected');

$disabled=$queue;$disabled['studio']['rotation']['hourly']=null;
$disabled=music_hourly_reconcile($disabled,$tracks,music_playout_empty(),$hour-360);
check(!array_filter($disabled['entries'],fn($e)=>isset($e['hourlyHour'])),'disable removes only future hour signals');

$persisted=json_decode(json_encode($queue),true);
check(music_hourly_reconcile($persisted,$tracks,music_playout_empty(),$hour-360)['entries']===$persisted['entries'],'JSON round trip does not cause unnecessary queue revisions');

// Keep completed occurrence history across three consecutive planning cycles.
// This is a clock simulation; actual MP3 transport is checked separately.
$history=music_playout_empty();$hourIds=[];
foreach(range(0,2) as $offset){
 $h=$hour+$offset*3600;
 $cycle=music_rotation_extend($q,$tracks,$history,$h-60);
 $found=array_values(array_filter($cycle['entries'],fn($e)=>($e['hourlyHour']??null)===$h));
 check(count($found)===1,'exactly one signal for consecutive hour '.$offset);
 $entry=$found[0];$hourIds[]=$entry['id'];
 $history['outcomes'][$entry['id']]=$entry+['status'=>'completed'];
 $after=music_hourly_reconcile($cycle,$tracks,$history,$h+60);
 check(!array_filter($after['entries'],fn($e)=>($e['hourlyHour']??null)===$h),'completed hour cannot replay '.$offset);
}
check(count(array_unique($hourIds))===3,'consecutive hours retain distinct identities');
