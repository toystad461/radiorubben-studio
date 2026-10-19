<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/music-studio.php';
function ms_check(bool $ok,string $label):void {if (!$ok) throw new RuntimeException($label);echo "PASS scheduling: $label\n";}
function ms_reject(callable $f,string $label):void {try {$f();}catch(Throwable){ms_check(true,$label);return;}throw new RuntimeException($label);}
function ms_track(int $i):array {
    $sha=hash('sha256','track-'.$i);$t=['id'=>md5('track-'.$i),'sha256'=>$sha,'bytes'=>7,'originalName'=>'Test '.$i.'.mp3','available'=>true,
      'metadata'=>['source'=>'file','sourceSha256'=>$sha,'fields'=>['artist'=>'Artist '.$i,'title'=>'Test '.$i,'genre'=>'Pop'],'durationSeconds'=>180],
      'audioAnalysis'=>['source'=>'authenticated-browser-analysis','sourceSha256'=>$sha,'engine'=>MUSIC_ANALYSIS_ENGINE,'bpm'=>90+$i,'key'=>'C','scale'=>'major','tempoConfidence'=>2,'keyStrength'=>.8,'startSeconds'=>0,'durationSeconds'=>90],
      'tags'=>['Varm']];
    $t['producerAssessment']=['source'=>'ai-assessment','sourceSha256'=>$sha,'inputSha256'=>hash('sha256',json_encode(music_library_producer_input($t),JSON_THROW_ON_ERROR)),'energy'=>'moderat','periods'=>['Morgen','Helgemorgen'],'reason'=>'Syntetisk testvurdering','confidence'=>'medium','tags'=>['Lett']];return $t;
}
function ms_policy(array $t):array {return music_schedule_policy(['approved'=>true,'blocked'=>false,'programs'=>['god-morgen-bomlo'],'weekdays'=>[1,2,3,4,5,6,7],'periods'=>[],'fields'=>[]],$t['sha256']);}
$t=ms_track(1);$p=music_schedule_defaults();$policy=ms_policy($t);$effective=music_schedule_track($t,$policy);
ms_check($effective['energy']==='moderat' && $effective['sources']['bpm']==='målt estimat','existing measurements and AI assessments are reused with provenance');
$t['audioManualOverrides']=['sourceSha256'=>$t['sha256'],'fields'=>['bpm'=>190]];$policy['fields']=['energy'=>'rolig','mood'=>['Positiv']];$e=music_schedule_track($t,$policy);
ms_check($e['bpm']===190 && $e['energy']==='rolig' && $e['sources']['bpm']==='manuell rettelse','manual corrections win; high BPM is not high energy');
ms_check(!$e['periods'] && in_array('Utdatert AI-vurdering brukes ikke',$e['warnings'],true),'AI basis invalidated by changed measurements');
$t['audioAnalysis']['sourceSha256']=str_repeat('f',64);$e=music_schedule_track($t,$policy);ms_check($e['bpm']===null && $e['key']===null,'stale measurements are not fabricated or used');
$t=ms_track(1);$t['producerAssessment']['inputSha256']='invalid';$e=music_schedule_track($t,ms_policy($t));ms_check($e['energy']===null,'stale AI energy ignored');
$at=music_schedule_time('2026-10-19','07:00');ms_check($at->format('P')==='+02:00' && music_mood($at)['period']==='Morgen','planned tomorrow morning is morning regardless of preparation clock');
ms_check(music_mood(music_schedule_time('2026-10-18','07:00'))['period']==='Helgemorgen','weekday/weekend rules reused');
ms_reject(fn()=>music_schedule_time('2026-03-29','02:30'),'DST gap refused');ms_reject(fn()=>music_schedule_time('2026-10-25','02:30'),'DST ambiguity requires explicit occurrence');
ms_check(music_schedule_time('2026-10-25','02:30','second')->getTimestamp()-music_schedule_time('2026-10-25','02:30','first')->getTimestamp()===3600,'both DST fold occurrences supported');
ms_reject(fn()=>music_schedule_time('2026-02-30','07:00'),'invalid calendar dates refused');
$tracks=array_map('ms_track',range(1,5));$q=music_play_queue_empty();$q['studio']=music_studio_settings($q);$q['studio']['schedule']=['programId'=>'god-morgen-bomlo','date'=>'2026-10-19','time'=>'07:00'];
foreach ($tracks as $t) $q['studio']['policies'][$t['id']]=ms_policy($t);
$plan=music_schedule_plan($q,$tracks);ms_check(count($plan['suggestions'])===3 && count(array_unique(array_column($plan['suggestions'],'trackId')))===3,'three distinct approved songs proposed');
ms_check(array_map(fn($t)=>substr($t['plannedAt'],11,8),$plan['suggestions'])===['07:00:00','07:03:00','07:06:00'],'each slot uses accumulated duration');
$q['studio']['schedule']['time']='09:59';$plan=music_schedule_plan($q,$tracks);ms_check(str_contains(implode(' ',$plan['suggestions'][1]['reasons']),'Formiddag'),'time boundary evaluated per entry');
$q['studio']['schedule']['time']='07:00';$short=music_schedule_plan($q,[$tracks[0]]);ms_check(count($short['suggestions'])===1 && count($short['missing'])===1,'small library does not silently relax repeat rules');
$unapproved=$q;$unapproved['studio']['policies']=[];ms_check(!music_schedule_plan($unapproved,$tracks)['suggestions'],'approval and program membership required');
$blocked=$q;foreach ($blocked['studio']['policies'] as &$v) $v['blocked']=true;unset($v);ms_check(!music_schedule_plan($blocked,$tracks)['suggestions'],'manual blocks are absolute');
$blocked=$q;foreach ($blocked['studio']['policies'] as &$v) $v['periods']=['Kveld'];unset($v);ms_check(!music_schedule_plan($blocked,$tracks)['suggestions'],'manual period exclusions cannot be outweighed by preferences');
$q['entries']=[['id'=>str_repeat('a',32),'kind'=>'music','trackId'=>$tracks[0]['id'],'sha256'=>$tracks[0]['sha256'],'locked'=>true]];
$plan=music_schedule_plan($q,$tracks);ms_check($plan['rows'][0]['id']===$q['entries'][0]['id'] && $plan['rows'][0]['locked'] && !in_array($tracks[0]['id'],array_column($plan['suggestions'],'trackId'),true),'locked entries and queued repeats respected');
ms_reject(fn()=>music_schedule_plan($q,$tracks,[],3,str_repeat('a',32)),'locked replacement rejected');
$tracks[0]['metadata']=null;ms_check(!music_schedule_plan($q,$tracks)['suggestions'],'unknown duration halts later airtime calculation');
$e=music_schedule_track(ms_track(3),ms_policy(ms_track(3)));$ctx=[['trackId'=>md5('other'),'artist'=>'Artist 3','at'=>$at->getTimestamp()-60]];
ms_check(in_array('Samme artist innen 60 min',music_schedule_evaluate($e,$p,$at,$ctx,null)['blocked'],true),'actual started history enforces artist distance');
$noKey=$p;$noKey['preferences']['keyWeight']=0;$previous=$e;$v1=music_schedule_evaluate($e,$p,$at,[],$previous);$v2=music_schedule_evaluate($e,$noKey,$at,[],$previous);ms_check($v1['score']-$v2['score']===1,'key is a small optional contribution');
ms_check(music_schedule_defaults()['proposal']===true,'starter values explicitly proposals');
