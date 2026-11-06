<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/controlroom.php';
function cue_check(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);echo "PASS $message\n";}
$sha=str_repeat('a',64);$id=str_repeat('b',32);
$t=['id'=>$id,'sha256'=>$sha,'metadata'=>['source'=>'file','sourceSha256'=>$sha,'fields'=>[],'durationSeconds'=>120.0]];
$points=['start'=>1.0,'vocalStart'=>15.0,'transition'=>110.0,'end'=>119.0];
$profile=['engine'=>MUSIC_AUDIO_QUEUE_ENGINE,'durationSeconds'=>120.0,'rmsDbfs'=>-12.0,'peakDbfs'=>-.5,'clippedFraction'=>0.0,'leadingSilenceSeconds'=>1.0,'trailingSilenceSeconds'=>1.0,'vocalStartSeconds'=>15.0,'lastVocalSeconds'=>110.0,'vocalScoreMax'=>.8,'voiceModel'=>'yamnet-tfjs-1','voiceCoverageSeconds'=>120.0,'waveformPeaks'=>array_fill(0,256,.4),'sourceSha256'=>$sha,'cueSuggestion'=>$points];
cue_check(music_library_cues_editor($t)['source']==='missing','missing analysis remains missing');
$t['cuePoints']=['schemaVersion'=>1,'sourceSha256'=>$sha,'source'=>'automatic-silence','points'=>array_replace($points,['vocalStart'=>null,'transition'=>null])];
cue_check(music_library_cues_editor($t)['source']==='silence','silence-only points are identified');
$t['musicAudioProfile']=$profile;
cue_check(music_library_cues_editor($t)['points']===$points,'fresh vocal analysis is not hidden by automatic silence points');
cue_check($t['cuePoints']['points']['vocalStart']===null,'display does not mutate persisted points');
$t['cuePoints']['source']='manual';
cue_check(music_library_cues_editor($t)['points']['vocalStart']===null,'manual unknown is preserved, not silently replaced');
$t['cuePoints']['source']='automatic-silence';$t['musicAudioProfile']['sourceSha256']=str_repeat('c',64);
cue_check(music_library_cues_editor($t)['source']==='silence','stale source analysis is rejected');
$t['musicAudioProfile']=$profile;
$entry=['trackId'=>$id,'sha256'=>$sha,'duration'=>118.0,'playback'=>['cueIn'=>1.0,'cueOut'=>119.0]];
$view=studio_controlroom_cues($entry,[$t]);
cue_check($view['points']===$points&&$view['playbackIn']===1.0&&count($view['waveformPeaks'])===256,'controlroom uses same source-bound points and actual trimmed window');
cue_check(!str_contains(json_encode($view),$sha)&&!str_contains(json_encode($view),$id),'projection excludes file hash and identity');
cue_check(studio_controlroom_cues(array_replace($entry,['sha256'=>str_repeat('d',64)]),[$t])===null,'other audio version cannot inherit cues');
cue_check(studio_controlroom_cues(array_replace($entry,['duration'=>100.0]),[$t])===null,'inconsistent playback window is rejected');
cue_check(studio_controlroom_cues(array_replace($entry,['playback'=>null]),[$t])===null,'missing playback origin is not guessed');
$t['musicAudioProfile']['cueSuggestion']['vocalStart']=null;$t['musicAudioProfile']['vocalStartSeconds']=null;$t['musicAudioProfile']['lastVocalSeconds']=null;
cue_check(studio_controlroom_cues($entry,[$t])['points']['vocalStart']===null,'no detection is unknown, not instrumental');
