<?php
declare(strict_types=1);
require_once __DIR__.'/music-library-preparation.php';
const MUSIC_ANALYSIS_ENGINE='essentia.js-0.1.3';
const MUSIC_AUDIO_QUEUE_ENGINE='rr-audio-queue-1';
function music_analysis_values(array $v,float $duration):array {
    $keys=['engine','bpm','key','scale','tempoConfidence','keyStrength','startSeconds','durationSeconds'];
    if(array_diff(array_keys($v),$keys)||array_diff($keys,array_keys($v))||($v['engine']??null)!==MUSIC_ANALYSIS_ENGINE)throw new InvalidArgumentException('Ukjent analyseformat.');
    foreach(['bpm','tempoConfidence','keyStrength','startSeconds','durationSeconds']as$k)if((!is_int($v[$k])&&!is_float($v[$k]))||!is_finite((float)$v[$k]))throw new InvalidArgumentException('Ugyldig lydmåling.');
    if($duration<10||$v['bpm']<40||$v['bpm']>208||$v['tempoConfidence']<0||$v['tempoConfidence']>100||$v['keyStrength']<0||$v['keyStrength']>1||$v['startSeconds']<0||$v['durationSeconds']<10||$v['durationSeconds']>90.1||$v['startSeconds']+$v['durationSeconds']>$duration+1)throw new InvalidArgumentException('Lydmålingen er utenfor testgrensene.');
    if(!in_array($v['key'],['A','A#','Bb','B','C','C#','Db','D','D#','Eb','E','F','F#','Gb','G','G#','Ab'],true)||!in_array($v['scale'],['major','minor'],true))throw new InvalidArgumentException('Ugyldig toneart.');
    return $v;
}
function music_library_analysis_current(array $track):?array {
    $v=$track['audioAnalysis']??null;$m=music_library_metadata($track);
    if(!$m||!is_array($v)||($v['sourceSha256']??null)!==($track['sha256']??null)||($v['source']??null)!=='authenticated-browser-analysis')return null;
    try{music_analysis_values(array_intersect_key($v,array_flip(['engine','bpm','key','scale','tempoConfidence','keyStrength','startSeconds','durationSeconds'])),$m['durationSeconds']);$manual=$track['audioManualOverrides']??null;if(is_array($manual)&&($manual['sourceSha256']??null)===$track['sha256']&&is_array($manual['fields']??null)){foreach(['bpm','key','scale']as$k)if(array_key_exists($k,$manual['fields']))$v[$k]=$manual['fields'][$k];music_analysis_values(array_intersect_key($v,array_flip(['engine','bpm','key','scale','tempoConfidence','keyStrength','startSeconds','durationSeconds'])),$m['durationSeconds']);if($manual['fields'])$v['manualCorrections']=$manual['fields'];}return $v;}catch(Throwable){return null;}
}
function music_library_analysis_save(string $id,string $sha,int $revision,array $values,?array $user,string $csrf,string $expected,?string $root=null):array {
    if(!music_library_can_upload($user)||$expected===''||!hash_equals($expected,$csrf))throw new InvalidArgumentException('Analysen er ikke autorisert.');
    $root??=music_library_root();$track=null;foreach(music_library_read($root)as$t)if($t['id']===$id)$track=$t;
    if(!$track||($track['revision']??0)!==$revision||$track['sha256']!==$sha)throw new InvalidArgumentException('Låten er endret. Last siden på nytt.');
    $m=music_library_metadata($track);if(!$m)throw new InvalidArgumentException('Filopplysningene må klargjøres først.');
    $v=music_analysis_values($values,$m['durationSeconds']);
    // Browser measurements are authenticated estimates, never independent server verification.
    $v+=['source'=>'authenticated-browser-analysis','sourceSha256'=>$sha,'generatedAt'=>gmdate('c'),'generatedBy'=>(string)($user['id']??$user['role']),'rotationApproved'=>false];
    require_once __DIR__.'/music-library-worker.php';
    return music_worker_commit($root,$track,['audioAnalysis'=>$v]);
}

function music_cue_values(array $v,float $duration):array {
    $keys=['start','vocalStart','transition','end'];
    if(array_diff(array_keys($v),$keys)||array_diff($keys,array_keys($v))||!is_finite($duration)||$duration<=0)throw new InvalidArgumentException('Ugyldige cue-punkter.');
    foreach($keys as$k){if($v[$k]===null&&in_array($k,['vocalStart','transition'],true))continue;if((!is_float($v[$k])&&!is_int($v[$k]))||!is_finite((float)$v[$k])||$v[$k]<0||$v[$k]>$duration)throw new InvalidArgumentException('Cue-punkter må være innenfor låten.');}
    if($v['end']-$v['start']<0.1)throw new InvalidArgumentException('Slutt må være etter start.');
    $previous=$v['start'];foreach(['vocalStart','transition','end']as$k){if($v[$k]===null)continue;if($v[$k]<$previous)throw new InvalidArgumentException('Rekkefølgen må være start, vokalstart, overgang og slutt.');$previous=$v[$k];}
    return $v;
}


function music_audio_profile_values(array $v,float $duration):array {
    if(array_diff(array_keys($v),['engine','durationSeconds','rmsDbfs','peakDbfs','clippedFraction','leadingSilenceSeconds','trailingSilenceSeconds','vocalStartSeconds','lastVocalSeconds','vocalScoreMax','voiceModel','voiceCoverageSeconds','waveformPeaks'])||count($v)!==13||($v['engine']??null)!==MUSIC_AUDIO_QUEUE_ENGINE||($v['voiceModel']??null)!=='yamnet-tfjs-1')throw new InvalidArgumentException('Ukjent lydprofil.');
    foreach(['durationSeconds','rmsDbfs','peakDbfs','clippedFraction','leadingSilenceSeconds','trailingSilenceSeconds','vocalScoreMax','voiceCoverageSeconds']as$k)if((!is_int($v[$k])&&!is_float($v[$k]))||!is_finite((float)$v[$k]))throw new InvalidArgumentException('Ugyldig lydprofil.');
    if(abs($v['durationSeconds']-$duration)>1||abs($v['voiceCoverageSeconds']-$v['durationSeconds'])>1||$v['rmsDbfs']< -160||$v['rmsDbfs']>3||$v['peakDbfs']< -160||$v['peakDbfs']>3||$v['clippedFraction']<0||$v['clippedFraction']>1||$v['vocalScoreMax']<0||$v['vocalScoreMax']>1||$v['leadingSilenceSeconds']<0||$v['trailingSilenceSeconds']<0||$v['leadingSilenceSeconds']+$v['trailingSilenceSeconds']>=$duration)throw new InvalidArgumentException('Lydprofilen er utenfor grensene.');
    foreach(['vocalStartSeconds','lastVocalSeconds']as$k)if($v[$k]!==null&&((!is_float($v[$k])&&!is_int($v[$k]))||!is_finite((float)$v[$k])||$v[$k]<0||$v[$k]>$duration))throw new InvalidArgumentException('Ugyldig vokalforslag.');
    if(!is_array($v['waveformPeaks'])||!array_is_list($v['waveformPeaks'])||count($v['waveformPeaks'])!==256)throw new InvalidArgumentException('Ugyldig lydbølge.');foreach($v['waveformPeaks']as$peak)if((!is_int($peak)&&!is_float($peak))||!is_finite((float)$peak)||$peak<0||$peak>2)throw new InvalidArgumentException('Ugyldig lydbølge.');
    if(($v['vocalStartSeconds']===null)!==($v['lastVocalSeconds']===null)||($v['vocalStartSeconds']!==null&&$v['lastVocalSeconds']<$v['vocalStartSeconds']))throw new InvalidArgumentException('Ugyldig vokalforslag.');return $v;
}


function music_audio_profile_current(array $track):?array {
    $p=$track['musicAudioProfile']??null;$m=music_library_metadata($track);if(!$m||!is_array($p)||($p['sourceSha256']??null)!==$track['sha256'])return null;
    try{music_audio_profile_values(array_intersect_key($p,array_flip(['engine','durationSeconds','rmsDbfs','peakDbfs','clippedFraction','leadingSilenceSeconds','trailingSilenceSeconds','vocalStartSeconds','lastVocalSeconds','vocalScoreMax','voiceModel','voiceCoverageSeconds','waveformPeaks'])),$m['durationSeconds']);music_cue_values($p['cueSuggestion'],$m['durationSeconds']);return $p;}catch(Throwable){return null;}
}
