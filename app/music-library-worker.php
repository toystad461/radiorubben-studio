<?php
declare(strict_types=1);
require_once __DIR__.'/music-library-online.php';
function music_worker_key(array $track):string {
    return hash('sha256',json_encode(['source'=>$track['sha256'],'fields'=>music_library_metadata($track),'tags'=>$track['tags']??[],'manual'=>$track['manualOverrides']??[]],JSON_THROW_ON_ERROR));
}
function music_worker_commit(string $root,array $before,array $changes):array {
    if(is_link($root.'/library.lock'))throw new RuntimeException('Ugyldig biblioteklås.');$lock=fopen($root.'/library.lock','rb');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Biblioteket er opptatt.');$tmp=null;
    try{$index=music_library_index($root);$at=null;foreach($index['tracks']as$i=>$t)if($t['id']===$before['id'])$at=$i;
      if($at===null||($index['tracks'][$at]['revision']??0)!==($before['revision']??0)||music_worker_key($index['tracks'][$at])!==music_worker_key($before))throw new RuntimeException('Låten er endret under arbeidet.');
      $h=music_library_verified_handle($index['tracks'][$at],$root);fclose($h);$index['tracks'][$at]=array_replace($index['tracks'][$at],$changes,['revision'=>($before['revision']??0)+1]);
      $tmp=$root.'/library-'.bin2hex(random_bytes(8)).'.tmp';$h=fopen($tmp,'xb');if(!$h)throw new RuntimeException('Kunne ikke lagre jobbstatus.');try{$raw=json_encode($index,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);if(!chmod($tmp,0600)||fwrite($h,$raw)!==strlen($raw)||!fflush($h))throw new RuntimeException('Kunne ikke lagre jobbstatus.');}finally{fclose($h);}if(!rename($tmp,$root.'/library.json'))throw new RuntimeException('Kunne ikke bekrefte jobben.');$tmp=null;return $index['tracks'][$at];
    }finally{if($tmp&&is_file($tmp))unlink($tmp);flock($lock,LOCK_UN);fclose($lock);}
}
function music_worker_run(array $config,?string $root=null,?callable $online=null,?callable $ai=null,?int $now=null):array {
    if(($config['music_processing']['enabled']??false)!==true)return ['status'=>'disabled'];
    if((!$online&&(($config['music_processing']['online_enabled']??false)!==true||($config['music_processing']['provider_terms_confirmed']??false)!==true))||(!$ai&&empty($config['openai_api_key'])))return ['status'=>'configuration_required'];
    $root??=music_library_root();$now??=time();if(!music_library_directory($root,false))return ['status'=>'empty'];$path=$root.'/processing.lock';if(is_link($path))throw new RuntimeException('Ugyldig jobblås.');$lock=fopen($path,'c+b');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){if($lock)fclose($lock);return ['status'=>'busy'];}chmod($path,0600);
    try{foreach(music_library_read($root)as$track){$key=music_worker_key($track);$job=$track['processing']??[];$same=($job['jobKey']??null)===$key;
      if($same&&(in_array($job['status']??null,['prepared','needs_review','failed'],true)||($job['nextAttemptAt']??0)>$now))continue;
      $attempt=$same?($job['attempts']??0)+1:1;$job=['jobKey'=>$key,'attempts'=>$attempt,'updatedAt'=>gmdate('c',$now),'rotationApproved'=>false];
      try{
        $h=music_library_verified_handle($track,$root);fclose($h);
        if(!music_library_metadata($track)){$track=music_library_prepare($track['id'],$track['sha256'],$track['revision']??0,['role'=>'producer','id'=>'music-worker'],'worker','worker',$root);$key=music_worker_key($track);$job['jobKey']=$key;}
        $evidence=music_online_lookup($config,$track,$root,$online);$assessment=null;
        if($evidence['status']==='matched'){$proposed=$track;$proposed['onlineIdentification']=$evidence;$assessment=music_library_producer_assess($config,$proposed,$ai);}
        $job['status']=$assessment?'prepared':'needs_review';$job['nextAttemptAt']=null;$changes=['onlineIdentification'=>$evidence,'processing'=>$job];if($assessment)$changes['producerAssessment']=$assessment;
        music_worker_commit($root,$track,$changes);return ['status'=>$job['status'],'trackId'=>$track['id'],'attempts'=>$attempt];
      }catch(Throwable){$job['status']=$attempt>=3?'failed':'retry';$job['errorCode']='preparation_unavailable';$job['nextAttemptAt']=$attempt>=3?null:$now+900*$attempt;
        try{music_worker_commit($root,$track,['processing'=>$job]);}catch(Throwable){return ['status'=>'changed','trackId'=>$track['id']];}return ['status'=>$job['status'],'trackId'=>$track['id'],'attempts'=>$attempt];}
    }return ['status'=>'idle'];}finally{flock($lock,LOCK_UN);fclose($lock);}
}
// Run by the hosting scheduler, never via an unauthenticated HTTP endpoint.
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){require_once __DIR__.'/config.php';try{echo json_encode(music_worker_run(load_config()),JSON_THROW_ON_ERROR)."\n";}catch(Throwable){fwrite(STDERR,"Musikkjobben kunne ikke kjøre.\n");exit(1);}}
