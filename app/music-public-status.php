<?php
declare(strict_types=1);
require_once __DIR__.'/music-playout.php';
/** Public projection: never return IDs, file paths, hashes, users or queue internals. */
function music_public_status(array $state,array $heartbeat,array $control,?float $now=null):array {
    $now??=microtime(true);
    $playing=music_playout_heartbeat_fresh($state,$heartbeat,$now)
        && ($heartbeat['connected']??false)===true && ($state['mode']??'')==='playing'
        && is_array($state['current']??null);
    $public=static function(array $e):array {
        $text=static fn($v)=>is_string($v)?mb_substr(trim(strip_tags($v)),0,200):'';
        return ['artist'=>$text($e['artist']??''),'title'=>$text($e['title']??''),'kind'=>($e['kind']??'music')==='jingle'?'jingle':'music'];
    };
    $next=null;
    if($playing && ($control['ready']??false))foreach($control['entries']??[] as $e){
        $id=$e['id']??'';
        if($id===($state['current']['id']??null)||isset($state['outcomes'][$id])||isset($state['active'][$id]))continue;
        if($e['blocked']??true)break;
        $next=$public($e);break;
    }
    return ['schemaVersion'=>1,'generatedAt'=>gmdate('c',(int)$now),'playing'=>$playing,'now'=>$playing?$public($state['current']):null,'next'=>$next];
}
