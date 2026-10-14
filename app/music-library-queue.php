<?php
declare(strict_types=1);
require_once __DIR__.'/music-library-cues.php';
require_once __DIR__.'/music-library-edit.php';

function music_audio_authorize(?array $user,string $csrf,string $expected):void {
    if(!music_library_can_upload($user)||$expected===''||!hash_equals($expected,$csrf))throw new InvalidArgumentException('Analysekøen er ikke autorisert.');
}
function music_audio_find(string $root,string $id):array {foreach(music_library_read($root)as$t)if($t['id']===$id)return $t;throw new InvalidArgumentException('Låten finnes ikke.');}
function music_audio_job_key(array $track):string{return hash('sha256',$track['sha256'].'|'.MUSIC_AUDIO_QUEUE_ENGINE);}
function music_audio_enqueue(string $id,string $sha,?array $user,string $csrf,string $expected,?string $root=null,bool $retry=false):array {
    music_audio_authorize($user,$csrf,$expected);$root??=music_library_root();$track=music_audio_find($root,$id);if($track['sha256']!==$sha)throw new InvalidArgumentException('Låten er endret.');
    if(!$retry&&music_audio_profile_current($track)!==null&&music_library_analysis_current($track)!==null)return $track;
    $job=$track['audioQueue']??null;if(is_array($job)&&($job['jobKey']??null)===music_audio_job_key($track)&&((!$retry&&($job['status']??null)!=='prepared')||($job['status']??null)==='running'))return $track;
    return music_worker_commit($root,$track,['audioQueue'=>['jobKey'=>music_audio_job_key($track),'status'=>'queued','attempts'=>0,'updatedAt'=>time()]]);
}
function music_audio_status(?string $root=null):array {
    $root??=music_library_root();$result=[];foreach(array_reverse(music_library_read($root))as$t){$q=$t['audioQueue']??null;if(!is_array($q)||($q['jobKey']??null)!==music_audio_job_key($t))continue;$result[]=['id'=>$t['id'],'version'=>$t['sha256'],'revision'=>$t['revision']??0,'title'=>music_library_effective_fields($t)['title']??$t['originalName'],'status'=>$q['status'],'leaseActive'=>($q['status']??null)==='running'&&($q['leaseUntil']??0)>time(),'attempts'=>$q['attempts']??0];}return $result;
}
function music_audio_claim(?array $user,string $csrf,string $expected,?string $root=null,?int $now=null):?array {
    music_audio_authorize($user,$csrf,$expected);$root??=music_library_root();$now??=time();$tracks=array_reverse(music_library_read($root));
    // One browser analysis at a time across machines; expiring leases make navigation/crashes recoverable.
    foreach($tracks as$t){$q=$t['audioQueue']??[];if(($q['status']??null)==='running'&&($q['leaseUntil']??0)>$now)return null;}
    foreach($tracks as$t){$q=$t['audioQueue']??[];if(($q['jobKey']??null)!==music_audio_job_key($t)||!in_array($q['status']??null,['queued','running'],true))continue;
      if(($q['attempts']??0)>=3){music_worker_commit($root,$t,['audioQueue'=>['jobKey'=>$q['jobKey'],'status'=>'failed','attempts'=>3,'errorCode'=>'retry_limit','updatedAt'=>$now]]);continue;}
      if(!music_library_metadata($t)){try{$t=music_library_prepare($t['id'],$t['sha256'],$t['revision']??0,$user,$csrf,$expected,$root);}catch(Throwable){music_worker_commit($root,$t,['audioQueue'=>['jobKey'=>$q['jobKey'],'status'=>'failed','attempts'=>1,'errorCode'=>'metadata_required','updatedAt'=>$now]]);continue;}}
      $token=bin2hex(random_bytes(24));$job=['jobKey'=>$q['jobKey'],'status'=>'running','attempts'=>($q['attempts']??0)+1,'leaseSha256'=>hash('sha256',$token),'owner'=>(string)$user['id'],'leaseUntil'=>$now+900,'updatedAt'=>$now];
      try{$t=music_worker_commit($root,$t,['audioQueue'=>$job]);}catch(RuntimeException){return null;}
      return ['id'=>$t['id'],'version'=>$t['sha256'],'revision'=>$t['revision'],'token'=>$token,'title'=>music_library_effective_fields($t)['title']??$t['originalName'],'durationSeconds'=>music_library_metadata($t)['durationSeconds']];
    }return null;
}
function music_audio_lease(array $track,string $token,?array $user,?int $now=null):array {
    $q=$track['audioQueue']??[];if(($q['status']??null)!=='running'||($q['jobKey']??null)!==music_audio_job_key($track)||($q['owner']??null)!==(string)($user['id']??'')||!is_string($q['leaseSha256']??null)||!hash_equals($q['leaseSha256'],hash('sha256',$token))||($q['leaseUntil']??0)<=($now??time()))throw new InvalidArgumentException('Analysejobben er utløpt eller endret.');return $q;
}
function music_audio_heartbeat(string $id,string $token,?array $user,string $csrf,string $expected,?string $root=null):void {
    music_audio_authorize($user,$csrf,$expected);$root??=music_library_root();$t=music_audio_find($root,$id);$q=music_audio_lease($t,$token,$user);$q['leaseUntil']=time()+900;
    // Lease renewal under the same library lock; no track revision/content change.
    $lock=fopen($root.'/library.lock','rb');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Køen er opptatt.');
    try{$index=music_library_index($root);foreach($index['tracks']as&$current)if($current['id']===$id){music_audio_lease($current,$token,$user);$current['audioQueue']['leaseUntil']=$q['leaseUntil'];}unset($current);$tmp=$root.'/queue-'.bin2hex(random_bytes(8)).'.tmp';try{$h=fopen($tmp,'xb');if(!$h)throw new RuntimeException('Køen kunne ikke fornyes.');$raw=json_encode($index,JSON_THROW_ON_ERROR);try{if(!chmod($tmp,0600)||fwrite($h,$raw)!==strlen($raw)||!fflush($h))throw new RuntimeException('Køen kunne ikke fornyes.');}finally{fclose($h);}if(!rename($tmp,$root.'/library.json'))throw new RuntimeException('Køen kunne ikke fornyes.');}finally{if(is_file($tmp))unlink($tmp);}}finally{flock($lock,LOCK_UN);fclose($lock);}
}
function music_audio_release(string $id,string $token,?array $user,string $csrf,string $expected,bool $failed=false,?string $root=null):array {
    music_audio_authorize($user,$csrf,$expected);$root??=music_library_root();$t=music_audio_find($root,$id);$q=music_audio_lease($t,$token,$user);return music_worker_commit($root,$t,['audioQueue'=>['jobKey'=>$q['jobKey'],'status'=>$failed?'failed':'queued','attempts'=>$failed?$q['attempts']:max(0,$q['attempts']-1),'errorCode'=>$failed?'analysis_unavailable':null,'updatedAt'=>time()]]);
}
function music_audio_complete(string $id,string $sha,int $revision,string $token,array $measurements,array $profile,?array $user,string $csrf,string $expected,?string $root=null):array {
    music_audio_authorize($user,$csrf,$expected);$root??=music_library_root();$t=music_audio_find($root,$id);$q=music_audio_lease($t,$token,$user);if($t['sha256']!==$sha||($t['revision']??0)!==$revision)throw new InvalidArgumentException('Låten er endret under analysen.');$duration=music_library_metadata($t)['durationSeconds'];$a=music_analysis_values($measurements,$duration);$p=music_audio_profile_values($profile,$duration);
    $start=min($duration-.1,round($p['leadingSilenceSeconds'],3));$end=floor(min($duration,$p['durationSeconds']-$p['trailingSilenceSeconds'])*1000)/1000;if($end-$start<.1)throw new InvalidArgumentException('Lydutsnittet er for kort.');$vocal=$p['vocalStartSeconds'];if($vocal!==null&&($vocal<$start||$vocal>$end))$vocal=null;
    $transition=$p['lastVocalSeconds']!==null&&$p['lastVocalSeconds']<$end-2?max($start,round($p['lastVocalSeconds'],3)):null;
    if($vocal!==null&&$transition!==null&&$transition<$vocal)$transition=null;
    $points=music_cue_values(['start'=>$start,'vocalStart'=>$vocal,'transition'=>$transition,'end'=>$end],$duration);
    $a+=['source'=>'authenticated-browser-analysis','sourceSha256'=>$sha,'generatedAt'=>gmdate('c'),'generatedBy'=>(string)$user['id'],'rotationApproved'=>false];
    $p+=['sourceSha256'=>$sha,'generatedAt'=>gmdate('c'),'source'=>'authenticated-browser-analysis','cueSuggestion'=>$points,'cueSuggestionStatus'=>'unapproved','limitations'=>'Vokal er YAMNet-estimat med ca.0,48sek tidssteg og kan forveksles med instrumenter. Overgang avhenger av neste låt. Lydnivå er dBFS, ikke LUFS.'];
    // Suggestions are stored separately. Never overwrite manual cue points or corrected measurements.
    return music_worker_commit($root,$t,['audioAnalysis'=>$a,'musicAudioProfile'=>$p,'audioQueue'=>['jobKey'=>$q['jobKey'],'status'=>'prepared','attempts'=>$q['attempts'],'updatedAt'=>time()]]);
}
