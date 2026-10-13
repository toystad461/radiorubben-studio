<?php
declare(strict_types=1);
require_once __DIR__.'/music-library-worker.php';
function music_cue_form_values(array $input):array {
    $v=[];foreach(['start','vocalStart','transition','end']as$k){$s=$input[$k]??null;if(!is_string($s)||strlen($s)>20)throw new InvalidArgumentException('Ugyldig cue-tid.');if($s===''&&in_array($k,['vocalStart','transition'],true)){$v[$k]=null;continue;}if(!preg_match('/^[0-9]+(?:\.[0-9]{1,3})?$/D',$s))throw new InvalidArgumentException('Bruk sekunder med inntil tre desimaler.');$v[$k]=(float)$s;}return $v;
}
function music_library_cues_current(array $track):?array {
    $v=$track['cuePoints']??null;$m=music_library_metadata($track);if(!$m||!is_array($v)||($v['schemaVersion']??null)!==1||($v['sourceSha256']??null)!==$track['sha256']||!is_array($v['points']??null))return null;
    try{music_cue_values($v['points'],$m['durationSeconds']);return $v;}catch(Throwable){return null;}
}
function music_library_cues_save(string $id,string $sha,int $revision,array $values,?array $user,string $csrf,string $expected,?string $root=null):array {
    if(!music_library_can_upload($user)||$expected===''||!hash_equals($expected,$csrf))throw new InvalidArgumentException('Cue-redigeringen er ikke autorisert.');
    $root??=music_library_root();$track=null;foreach(music_library_read($root)as$t)if($t['id']===$id)$track=$t;
    if(!$track||($track['revision']??0)!==$revision||$track['sha256']!==$sha)throw new InvalidArgumentException('Låten er endret. Last siden på nytt.');
    $m=music_library_metadata($track);if(!$m)throw new InvalidArgumentException('Filopplysningene må klargjøres først.');
    $points=music_cue_values($values,$m['durationSeconds']);
    $saved=['schemaVersion'=>1,'sourceSha256'=>$sha,'points'=>$points,'updatedAt'=>gmdate('c'),'updatedBy'=>(string)($user['id']??$user['role']),'source'=>'manual','rotationApproved'=>false];
    // Retain the previous manual version for correction history, bounded to twenty revisions.
    $history=is_array($track['cueHistory']??null)?$track['cueHistory']:[];if($previous=music_library_cues_current($track))$history[]=$previous;
    return music_worker_commit($root,$track,['cuePoints'=>$saved,'cueHistory'=>array_slice($history,-20)]);
}
