<?php
declare(strict_types=1);
require_once __DIR__.'/music-library-cues.php';
/** Accept only measured, source-bound technical points. Never replace manual cues. */
function music_autocue_apply(array $m, ?array $actor, string $csrf, string $expected, ?string $root=null):array {
    if(!music_library_can_upload($actor)||$expected===''||!hash_equals($expected,$csrf))throw new InvalidArgumentException('Lydkontrollen er ikke autorisert.');
    $root??=music_library_root();$track=null;foreach(music_library_read($root,'all') as $t)if($t['id']===($m['id']??null))$track=$t;
    if(!$track||$track['sha256']!==($m['sha256']??null)||($track['revision']??0)!==($m['revision']??null))throw new DomainException('Lydfilen er endret under kontrollen.');
    if(($m['ready']??null)===false&&($m['engine']??'')==='ffmpeg-silencedetect--50dB-100ms-v1')return music_worker_commit($root,$track,['technicalCheck'=>['sourceSha256'=>$track['sha256'],'ready'=>false,'checkedAtUtc'=>$m['checkedAtUtc']??gmdate('c'),'warnings'=>$m['warnings']??['Lydfilen bestod ikke kontrollen.']]]);
    $meta=music_library_metadata($track);
    if(!$meta||($m['ready']??false)!==true||($m['engine']??'')!=='ffmpeg-silencedetect--50dB-100ms-v1'||!is_numeric($m['duration']??null)||abs($m['duration']-$meta['durationSeconds'])>.1)throw new InvalidArgumentException('Lydkontrollen må fullføres først.');
    $points=music_cue_values(['start'=>$m['cueIn']??null,'end'=>$m['cueOut']??null,'vocalStart'=>null,'transition'=>null],$meta['durationSeconds']);
    if($points['end']-$points['start']<1||$points['end']-$points['start']<$meta['durationSeconds']*.75)throw new InvalidArgumentException('For mye lyd ville blitt klippet.');
    $check=array_intersect_key($m,array_flip(['engine','checkedAtUtc','leadingSilence','trailingSilence','peakDbfs','meanDbfs','warnings']));
    $check+=['sourceSha256'=>$track['sha256'],'ready'=>true,'points'=>$points];
    $changes=['technicalCheck'=>$check];$current=music_library_cues_current($track);
    if(($current['source']??'')!=='manual')$changes['cuePoints']=['schemaVersion'=>1,'sourceSha256'=>$track['sha256'],'source'=>'automatic-silence','points'=>$points,'updatedAt'=>gmdate('c'),'updatedBy'=>(string)($actor['id']??$actor['role']),'rotationApproved'=>false];
    return music_worker_commit($root,$track,$changes);
}
