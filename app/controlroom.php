<?php
declare(strict_types=1);
require_once __DIR__.'/music-playout.php';
require_once __DIR__.'/music-library-cues.php';
/** Only attach analysis from the exact file being played. No private paths/hashes leave this projection. */
function studio_controlroom_cues(array $entry,array $library):?array {
    foreach($library as $track){
        if(($track['id']??null)!==($entry['trackId']??null)||($track['sha256']??null)!==($entry['sha256']??null))continue;
        $meta=music_library_metadata($track);if(!$meta)return null;
        $editor=music_library_cues_editor($track);if($editor['source']==='missing')return null;
        $duration=$meta['durationSeconds'];$playback=$entry['playback']??null;
        if(!is_array($playback))return null;
        $in=$playback['cueIn']??null;$out=$playback['cueOut']??null;
        foreach([$in,$out]as$n)if((!is_float($n)&&!is_int($n))||!is_finite((float)$n))return null;
        if($in<0||$out>$duration+.001||$out<=$in||abs(($entry['duration']??0)-($out-$in))>.01)return null;
        $profile=music_audio_profile_current($track);
        return ['points'=>$editor['points'],'source'=>$editor['source'],'duration'=>$duration,
            'playbackIn'=>$in,'playbackOut'=>$out,'waveformPeaks'=>$profile['waveformPeaks']??null];
    }
    return null;
}
/** Read-only, bounded projection of the same journal/manifest used by the engine. */
function studio_controlroom_project(array $s,array $beat,array $control,?float $now=null,array $library=[]):array {
    $now??=microtime(true);
    $fresh=music_playout_heartbeat_fresh($s,$beat,$now);
    $connected=$fresh && ($beat['connected']??false)===true;
    $mode=$s['mode']??'unknown';
    $status=!$fresh?'unknown':(in_array($mode,['stopped','paused','blocked'],true)?$mode:
        (!$connected?'disconnected':($mode==='playing' && is_array($s['current']??null)?'playing':($mode==='starting'?'starting':'unknown'))));
    $number=static fn($v)=>is_numeric($v)&&!is_bool($v)&&is_finite((float)$v)&&(float)$v>=0?(float)$v:null;
    $track=static function(array $e)use($number):array {
        $text=static fn($v)=>is_string($v)?mb_substr(trim(strip_tags($v)),0,200):'';
        $type=$e['eventType']??null;
        return ['artist'=>$text($e['artist']??''),'title'=>$text($e['title']??''),'kind'=>($e['kind']??'music')==='jingle'?'jingle':'music','duration'=>$number($e['duration']??null),
            'eventType'=>in_array($type,['stikk','innlegg','jingle','reklame','lydteppe','program','hilsen','onske','sang'],true)?$type:null];
    };
    $current=$status==='playing'?$track($s['current']):null;
    if($current)$current['cues']=studio_controlroom_cues($s['current'],$library);
    $position=null;
    // Motor position is measured PCM time since this occurrence started, not wall-clock guessing.
    if($current && ($s['engine']??'')==='liquidsoap' && ($s['current']['evidence']??'')==='liquidsoap-pcm'
        && is_numeric($s['current']['observedWallStart']??null) && $beat['timestamp']>=$s['current']['observedWallStart']) {
        $position=$number($beat['position']??null);
        if($current['duration']===null || $position===null || $position>$current['duration']+1)$position=null;
        elseif($position!==null)$position=min($position,$current['duration']);
    }
    $next=[];$seen=[];$queueKnown=$fresh && ($control['ready']??false)===true;
    // A reserved occurrence is already accepted by Liquidsoap and precedes unclaimed entries.
    if($queueKnown)foreach(array_merge(array_values($s['reserved']??[]),$control['entries']??[])as $e){
        $id=$e['id']??null;if(!is_string($id)||isset($seen[$id]))continue;$seen[$id]=true;
        if($id===($s['current']['id']??null)||isset($s['active'][$id])||isset($s['outcomes'][$id]))continue;
        if(!array_key_exists('blocked',$e)||$e['blocked'])break;
        $next[]=$track($e);if(count($next)===3)break;
    }
    return ['schemaVersion'=>1,'generatedAt'=>gmdate('c',(int)$now),'status'=>$status,'engineOnline'=>$fresh,
        'streamConnected'=>$connected,'now'=>$current,'position'=>$position,'next'=>$next,'queueKnown'=>$queueKnown];
}
function studio_controlroom_read(?array $config):array {
    if(!$config)return ['schemaVersion'=>1,'generatedAt'=>gmdate('c'),'status'=>'unconfigured','now'=>null,'position'=>null,'next'=>[],'queueKnown'=>false,'engineOnline'=>false,'streamConnected'=>false];
    $root=$config['root'];$lock=music_playout_lock($root);
    try{$state=music_playout_state($root);$beat=music_playout_json($root.'/heartbeat.json');$control=music_playout_json($root.'/control.json');}
    finally{flock($lock,LOCK_UN);fclose($lock);}
    // Never hold the player lock while acquiring the library lock.
    try{$library=music_library_read(null,'all');}catch(Throwable){$library=[];}
    $view=studio_controlroom_project($state,$beat,$control,null,$library);
    if(($config['stream_path']??null)==='/music-test-stream/')$view['streamUrl']='/music-test-stream/';return $view;
}
