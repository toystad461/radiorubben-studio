<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
require dirname(__DIR__).'/app/music-studio.php';
require dirname(__DIR__).'/app/music-autocue.php';
$actor=['role'=>'admin','id'=>'authorized-standard-rotation-worker'];$nonce=bin2hex(random_bytes(16));
$library=music_library_root();$playout=music_playout_config();$q=music_play_queue_read();
if($playout&&($q['studio']['rotation']['enabled']??false)){try{music_studio_change(['action'=>'fill','revision'=>$q['revision']],$actor,$nonce,$nonce,null,$library,$playout);}catch(DomainException){/* The operator changed the queue. Retry next tick. */}}

$library=music_library_root();$tracks=music_library_read($library,'all');
$pending=array_filter($tracks,fn($t)=>($t['technicalCheck']['sourceSha256']??null)!==$t['sha256']);
if($pending){
    $report=tempnam($library,'.autocue-');chmod($report,0600);
    try{
        $pipes=[];$process=proc_open(['/usr/bin/python3',__DIR__.'/music-autocue.py','--library',$library,'--report',$report,'--missing-only','--limit','1'],[0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['file','/dev/null','w']],$pipes);
        if(!is_resource($process)||proc_close($process)!==0)throw new RuntimeException('Automatisk lydkontroll feilet.');
        foreach(json_decode(file_get_contents($report),true,32,JSON_THROW_ON_ERROR)as$m)music_autocue_apply($m,$actor,$nonce,$nonce,$library);
    }finally{if(is_file($report))unlink($report);}
}
// Optional artwork must never prevent playout or queue maintenance.
try{require_once dirname(__DIR__).'/app/music-public-artwork.php';music_public_artwork_build($library,1);}catch(Throwable $e){error_log('Public artwork preparation deferred');}
echo "Rotation check completed\n";
