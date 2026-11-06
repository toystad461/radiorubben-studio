<?php
declare(strict_types=1);
require_once __DIR__.'/music-library-worker.php';
/** Outside queue/bus locks. Reconciliation checks source identities before insertion. */
function music_hour_bridge_prepare(array $q,string $root,int $now):string {
    $r=$q['studio']['rotation']['bridgeRequest']??null;
    if(!($q['studio']['rotation']['bridgeEnabled']??false)||!$r||$r['hourUtc']-$now>300||$r['hourUtc']-$r['duration']-$now<30)return 'idle';
    $tracks=music_library_read($root,'all');$catalog=music_schedule_catalog($tracks,$q['studio']['policies']);
    if(music_hour_bridge_ready($r,$tracks,$catalog))return 'ready';
    if(count($tracks)>=MUSIC_LIBRARY_COUNT_LIMIT)return 'capacity';
    // Bound regeneration if an operator changes the queue near the hour.
    if(count(array_filter($tracks,fn($t)=>($t['hourBridge']['hourUtc']??null)===$r['hourUtc']))>=2)return 'changed';
    $temp=tempnam($root,'.hour-request-');$audio=$temp.'.mp3';chmod($temp,0600);
    try{
        file_put_contents($temp,json_encode($r,JSON_THROW_ON_ERROR));
        $process=proc_open(['/usr/bin/python3',dirname(__DIR__).'/scripts/music-hour-bridge.py','--library',$root,'--request',$temp,'--output',$audio],[0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['file','/dev/null','w']],$pipes);
        if(!is_resource($process)||proc_close($process)!==0)throw new RuntimeException('Musikkglimtene kunne ikke klargjøres.');
        $user=['role'=>'producer','id'=>'authorized-hour-bridge-worker'];$nonce=bin2hex(random_bytes(16));
        $import=music_library_upload(['name'=>'Neste time - '.gmdate('Ymd-H',$r['hourUtc']).'.mp3','tmp_name'=>$audio,'size'=>filesize($audio),'error'=>UPLOAD_ERR_OK],$user,$nonce,$nonce,$root,fn($p)=>$p===$audio,fn($a,$b)=>copy($a,$b),'jingle');$t=$import['track'];
        if(!music_library_metadata($t))$t=music_library_prepare($t['id'],$t['sha256'],$t['revision']??0,$user,$nonce,$nonce,$root);
        $title='Neste time · '.implode(' / ',array_column($r['songs'],'artist'));
        music_worker_commit($root,$t,['jingle'=>['name'=>mb_substr($title,0,160),'category'=>'hour','disabled'=>false],'hourBridge'=>$r,
            'technicalCheck'=>['ready'=>true,'sourceSha256'=>$t['sha256'],'engine'=>'three-source-verified-excerpts-full-decode','checkedAtUtc'=>gmdate('c')],
            'cuePoints'=>['schemaVersion'=>1,'sourceSha256'=>$t['sha256'],'source'=>'manual','points'=>['start'=>0.,'end'=>$r['duration'],'vocalStart'=>null,'transition'=>null],'updatedAt'=>gmdate('c'),'updatedBy'=>$user['id'],'rotationApproved'=>false]]);
        return 'prepared';
    }finally{if(is_file($temp))unlink($temp);if(is_file($audio))unlink($audio);}
}
