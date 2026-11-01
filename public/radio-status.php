<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/music-public-status.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');
header('X-Content-Type-Options: nosniff');
if(!in_array($_SERVER['REQUEST_METHOD']??'',['GET','HEAD'],true)){http_response_code(405);header('Allow: GET, HEAD');exit;}
try {
    $config=music_playout_config();if(!$config)throw new RuntimeException('Unavailable');
    $root=$config['root'];$lock=music_playout_lock($root);
    try{$result=music_public_status(music_playout_state($root),music_playout_json($root.'/heartbeat.json'),music_playout_json($root.'/control.json'));}
    finally{flock($lock,LOCK_UN);fclose($lock);}
    echo json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
}catch(Throwable){http_response_code(503);echo '{"schemaVersion":1,"playing":false,"now":null,"next":null}';}
