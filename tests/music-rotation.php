<?php
declare(strict_types=1);
require __DIR__.'/../app/music-studio.php';
require __DIR__.'/../app/music-autocue.php';
function check(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS rotation: $label\n";}
function reject(callable $fn,string $label):void{try{$fn();}catch(Throwable){check(true,$label);return;}throw new RuntimeException($label);}
foreach([0,3,10]as$n)check(music_rotation_settings(['enabled'=>true,'crossfade'=>$n])['crossfade']===$n*1.,'valid crossfade '.$n);
foreach([-1,11,NAN,'3']as$n)reject(fn()=>music_rotation_settings(['enabled'=>true,'crossfade'=>$n]),'invalid crossfade');
$q=music_play_queue_empty();$q['studio']=music_studio_settings($q);$q['studio']['schedule']=['programId'=>'god-morgen-bomlo','date'=>'2026-11-01','time'=>'07:00'];$q['studio']['rotation']=music_rotation_settings(['enabled'=>true,'crossfade'=>3]);$tracks=[];
for($i=0;$i<106;$i++){
 $id=md5('rotation-'.$i);$sha=hash('sha256',$id);$kind=$i<100?'music':'jingle';$duration=$kind==='music'?180:8;
 $t=['id'=>$id,'sha256'=>$sha,'kind'=>$kind,'available'=>true,'originalName'=>$id.'.mp3','metadata'=>['source'=>'file','sourceSha256'=>$sha,'durationSeconds'=>$duration,'fields'=>['artist'=>'Artist '.$i,'title'=>'Track '.$i]],'technicalCheck'=>['ready'=>true,'sourceSha256'=>$sha],'cuePoints'=>['schemaVersion'=>1,'sourceSha256'=>$sha,'source'=>'automatic-silence','points'=>['start'=>1.,'end'=>$duration-1.,'vocalStart'=>null,'transition'=>null]]];
 if($kind==='jingle')$t['jingle']=['category'=>$i===105?'news':'station','disabled'=>false];
 $tracks[]=$t;if($kind==='music')$q['studio']['policies'][$id]=music_schedule_policy(['approved'=>true,'blocked'=>false,'programs'=>['god-morgen-bomlo'],'weekdays'=>[1,2,3,4,5,6,7],'periods'=>[],'fields'=>[]],$sha);
}
$now=strtotime('2026-11-01T07:00:00+01:00');$s=music_playout_empty();$built=music_rotation_extend($q,$tracks,$s,$now);$entries=$built['entries'];
check(strtotime($built['studio']['rotation']['plannedUntil'])-$now>=3600,'one hour chosen ahead');
foreach($entries as$i=>$e){check($e['kind']===($i%4===3?'jingle':'music'),'three music then jingle '.$i);check($e['trackId']!==$tracks[105]['id'],'news jingle excluded');if(isset($entries[$i+1]))check($e['playback']['crossfadeOut']===($i%4<2?3.:0.),'music crossfade only');}
check(count(array_unique(array_column(array_filter($entries,fn($e)=>$e['kind']==='music'),'trackId')))===count(array_filter($entries,fn($e)=>$e['kind']==='music')),'distinct music');
$again=music_rotation_extend($built,$tracks,$s,$now);check($again['entries']===$entries,'stable planned identities');
$s['outcomes'][$entries[0]['id']]=$entries[0]+['artist'=>'Artist 0','status'=>'completed','startedAt'=>gmdate('c',$now)];$later=music_rotation_extend($built,$tracks,$s,$now+180);
check(array_slice($later['entries'],0,count($entries)-1)===array_slice($entries,1),'refill preserves existing future');
$bad=$tracks;foreach($bad as&$t)$t['technicalCheck']['sourceSha256']='stale';unset($t);$none=music_rotation_extend($q,$bad,music_playout_empty(),$now);check(!$none['entries']&&!!$none['studio']['rotation']['shortage'],'unchecked files excluded');
$bad=$q;$bad['studio']['policies']=[];check(!music_rotation_extend($bad,$tracks,music_playout_empty(),$now)['entries'],'approval remains required');
reject(fn()=>music_rotation_playback(['cueIn'=>0,'cueOut'=>50,'crossfadeOut'=>3],40),'cue outside source rejected');

// Exercise more than a full catalogue cycle, carrying real completed history.
$cycle=$built;$state=music_playout_empty();$played=[];$catalog=music_schedule_catalog($tracks,$q['studio']['policies']);
for($n=0;$n<145;$n++){
 $entry=$cycle['entries'][0];$at=music_rotation_seconds($entry['plannedAt']);
 if($entry['kind']==='music')$played[]=$entry['trackId'];
 $state['outcomes'][$entry['id']]=$entry+['artist'=>$catalog[$entry['trackId']]['artist'],'status'=>'completed','startedAt'=>gmdate('c',(int)$at)];
 $next=(int)($at+$entry['playback']['cueOut']-$entry['playback']['cueIn']-$entry['playback']['crossfadeOut']);
 $cycle=music_rotation_extend($cycle,$tracks,$state,$next);
 if($cycle['studio']['rotation']['shortage'])throw new RuntimeException('Rotation shortage at '.$n);
}
check(count(array_unique(array_slice($played,0,100)))===100,'all 100 songs selected before the next cycle');
check(count($played)>100,'rotation continues into next catalogue cycle');
