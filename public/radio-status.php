<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/music-public-status.php';
require dirname(__DIR__).'/app/music-public-artwork.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');
header('X-Content-Type-Options: nosniff');
if(!in_array($_SERVER['REQUEST_METHOD']??'',['GET','HEAD'],true)){http_response_code(405);header('Allow: GET, HEAD');exit;}
try {
    if(array_key_exists('cover',$_GET)){
        $bytes=music_public_artwork_bytes(music_library_root(),$_GET['cover']);
        if($bytes===null){http_response_code(404);exit;}
        header('Content-Type: image/jpeg');header('Cache-Control: public, max-age=86400, immutable');
        header('Content-Length: '.strlen($bytes));if(($_SERVER['REQUEST_METHOD']??'')!=='HEAD')echo $bytes;exit;
    }
    $config=music_playout_config();if(!$config)throw new RuntimeException('Unavailable');
    $root=$config['root'];$lock=music_playout_lock($root);
    try{$state=music_playout_state($root);$result=music_public_status($state,music_playout_json($root.'/heartbeat.json'),music_playout_json($root.'/control.json'));}
    finally{flock($lock,LOCK_UN);fclose($lock);}
    if($result['now']!==null){$url=music_public_artwork_url($state['current'],music_public_artwork_index(music_library_root()));if($url!==null)$result['now']['artworkUrl']=$url;}
    echo json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
}catch(Throwable){http_response_code(503);echo '{"schemaVersion":1,"playing":false,"now":null,"next":null}';}
