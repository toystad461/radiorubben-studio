<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(2);
require dirname(__DIR__).'/app/music-boot.php';
$config=music_playout_config();if(!$config)exit(1);
$deadline=microtime(true)+45;
do {
    $q=music_play_queue_read();$s=music_playout_state($config['root']);
    $c=music_playout_json($config['root'].'/control.json');$h=music_playout_json($config['root'].'/heartbeat.json');
    if(($c['command']['action']??'')!=='start'||!($q['studio']['rotation']['enabled']??false)||in_array($s['mode'],['playing','starting','blocked'],true)){
        echo "Boot resume not required or not permitted\n";exit(0);
    }
    if(music_boot_allowed($q,$s,$c,$h)){
        $nonce=bin2hex(random_bytes(16));
        try {
            music_studio_change(['action'=>'start','revision'=>$q['revision'],'playerRevision'=>$s['seq']],['role'=>'admin','id'=>'authorized-boot-resume'],$nonce,$nonce,null,null,$config);
            echo "Boot resume requested through validated Studio queue\n";exit(0);
        }catch(DomainException){/* Concurrent operator action: read again, never overwrite it. */}
    }
    usleep(500000);
}while(microtime(true)<$deadline);
fwrite(STDERR,"Boot resume could not be confirmed\n");exit(1);
