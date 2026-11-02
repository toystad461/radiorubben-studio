<?php
declare(strict_types=1);
require __DIR__.'/music-hourly.php';
$base=$q;$base['studio']['rotation']['bridgeEnabled']=true;$base['entries']=[];
for($i=0;$i<4;$i++)$base['entries'][]=['id'=>md5('bridge-entry-'.$i),'kind'=>'music','trackId'=>$tracks[$i]['id'],'sha256'=>$tracks[$i]['sha256'],'locked'=>false,'plannedAt'=>music_rotation_time($hour-21+$i*178),'playback'=>music_rotation_audio(music_schedule_track($tracks[$i]))];
$draft=music_hourly_reconcile($base,$tracks,music_playout_empty(),$hour-21);
$r=$draft['studio']['rotation']['bridgeRequest'];check($r['duration']===21.&&count($r['songs'])===3,'21-second gap requests three actual queued songs');
check(!array_filter($draft['entries'],fn($e)=>isset($e['hourBridgeHour'])),'no missing audio inserted');
$asset=['id'=>md5('bridge-audio'),'sha256'=>hash('sha256','bridge-audio'),'kind'=>'jingle','available'=>true,'originalName'=>'preview.mp3','metadata'=>['source'=>'file','sourceSha256'=>hash('sha256','bridge-audio'),'durationSeconds'=>21.05,'fields'=>[]],'technicalCheck'=>['ready'=>true,'sourceSha256'=>hash('sha256','bridge-audio')],'jingle'=>['name'=>'Next hour','category'=>'hour','disabled'=>false],'hourBridge'=>$r];
$with=[...$tracks,$asset];$ready=music_hourly_reconcile($base,$with,music_playout_empty(),$hour-21);
check(isset($ready['entries'][0]['hourBridgeHour']),'prepared preview fills gap');check($ready['entries'][1]['hourlyHour']===$hour&&music_rotation_seconds($ready['entries'][1]['plannedAt'])===$hour*1.,'beep at exact planned hour');
check(array_slice(array_column($ready['entries'],'trackId'),2,4)===array_column($base['entries'],'trackId'),'whole songs retained after beep');
$again=music_hourly_reconcile(json_decode(json_encode($ready),true),$with,music_playout_empty(),$hour-21);check($again['entries']==json_decode(json_encode($ready['entries']),true),'stable saved montage');
$changed=$base;$changed['entries'][0]['id']=md5('changed');check(!array_filter(music_hourly_reconcile($changed,$with,music_playout_empty(),$hour-21)['entries'],fn($e)=>isset($e['hourBridgeHour'])),'changed queue falls back to full songs');
foreach([10,46]as$gap)check(music_hourly_reconcile($base,$with,music_playout_empty(),$hour-$gap)['studio']['rotation']['bridgeRequest']===null,'out-of-window gap remains music');
$off=$base;$off['studio']['rotation']['bridgeEnabled']=false;check(!array_filter(music_hourly_reconcile($off,$with,music_playout_empty(),$hour-21)['entries'],fn($e)=>isset($e['hourBridgeHour'])),'disable restores full music');
