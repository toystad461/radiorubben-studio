<?php
declare(strict_types=1);
require_once __DIR__.'/music-library-preparation.php';
const MUSIC_ANALYSIS_ENGINE='essentia.js-0.1.3';
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
    try{music_analysis_values(array_intersect_key($v,array_flip(['engine','bpm','key','scale','tempoConfidence','keyStrength','startSeconds','durationSeconds'])),$m['durationSeconds']);return $v;}catch(Throwable){return null;}
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
