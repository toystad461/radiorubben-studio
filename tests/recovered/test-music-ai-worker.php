<?php
declare(strict_types=1);
$package=dirname(__DIR__,2);$app=getenv('RR_MUSIC_AI_TEST_APP')?:$package;
require $app.'/app/music-library-queue.php';
require (getenv('RR_MUSIC_AI_TEST_WORKER')?:$package.'/scripts/music-ai-worker.php');
function ai_ok(bool $ok):void{if(!$ok)throw new RuntimeException('AI worker assertion failed');}
$dir=sys_get_temp_dir().'/rr-ai-'.bin2hex(random_bytes(6));mkdir($dir,0700);$dir=realpath($dir);$root=$dir.'/media/music';
$u=['role'=>'producer','id'=>'fixture'];$config=['openai_model'=>'fixture-model'];$calls=0;
$mock=function($c,$payload)use(&$calls){$calls++;ai_ok($payload['store']===false);return json_encode(['energy'=>'moderat','periods'=>['Morgen'],'tags'=>['Melodisk'],'reason'=>'Vurdering basert på målinger.','confidence'=>'low']);};
try {
 $bytes=str_repeat(hex2bin('fffb9000').str_repeat("\0",413),1000);$file=$dir.'/test.mp3';file_put_contents($file,$bytes);
 $r=music_library_upload(['name'=>'Test.mp3','tmp_name'=>$file,'size'=>strlen($bytes),'error'=>0],$u,'c','c',$root,fn()=>true,fn($a,$b)=>copy($a,$b));
 $t=music_library_prepare($r['track']['id'],$r['track']['sha256'],0,$u,'c','c',$root);$id=$t['id'];$sha=$t['sha256'];
 $t=music_library_edit($id,$sha,$t['revision'],['artist'=>'Fixture','title'=>'Test','tags'=>'Min tag'],$u,'c','c',$root);
 ai_ok(music_ai_worker_run($config,$root,$mock)['status']==='idle'&&$calls===0);
 music_audio_enqueue($id,$sha,$u,'c','c',$root);$job=music_audio_claim($u,'c','c',$root);
 $duration=music_library_metadata($t)['durationSeconds'];
 $measure=['engine'=>MUSIC_ANALYSIS_ENGINE,'bpm'=>120.0,'key'=>'C','scale'=>'major','tempoConfidence'=>2.0,'keyStrength'=>.8,'startSeconds'=>0.0,'durationSeconds'=>15.0];
 $profile=['engine'=>MUSIC_AUDIO_QUEUE_ENGINE,'durationSeconds'=>$duration,'rmsDbfs'=>-12.0,'peakDbfs'=>-.5,'clippedFraction'=>0.0,'leadingSilenceSeconds'=>.2,'trailingSilenceSeconds'=>.3,'vocalStartSeconds'=>3.0,'lastVocalSeconds'=>20.0,'vocalScoreMax'=>.8,'voiceModel'=>'yamnet-tfjs-1','voiceCoverageSeconds'=>$duration,'waveformPeaks'=>array_fill(0,256,.2)];
 $t=music_audio_complete($id,$sha,$job['revision'],$job['token'],$measure,$profile,$u,'c','c',$root);
 $manual=['start'=>1.0,'vocalStart'=>4.0,'transition'=>22.0,'end'=>25.0];
 $t=music_library_cues_save($id,$sha,$t['revision'],$manual,$u,'c','c',$root);
 $before=$t;
 ai_ok(music_ai_worker_run($config,$root,$mock)['status']==='prepared'&&$calls===1);
 $t=music_audio_find($root,$id);
 ai_ok(music_library_producer_current($t)!==null&&$t['producerAssessment']['rotationApproved']===false);
 ai_ok($t['cuePoints']==$before['cuePoints']&&$t['tags']===$before['tags']&&$t['audioAnalysis']==$before['audioAnalysis']);
 ai_ok(hash_file('sha256',$root.'/originals/'.$id.'.mp3')===$sha);
 ai_ok(music_ai_worker_run($config,$root,$mock)['status']==='idle'&&$calls===1);
 $t=music_library_edit($id,$sha,$t['revision'],['artist'=>'Fixture','title'=>'New title','tags'=>'Min tag'],$u,'c','c',$root);
 $locked=fopen($root.'/ai-worker.lock','c+b');flock($locked,LOCK_EX);
 ai_ok(music_ai_worker_run($config,$root,$mock)['status']==='busy'&&$calls===1);flock($locked,LOCK_UN);fclose($locked);
 $fail=function()use(&$calls){$calls++;throw new RuntimeException('synthetic');};
 $now=time();ai_ok(music_ai_worker_run($config,$root,$fail,$now)['status']==='retry');
 ai_ok(music_ai_worker_run($config,$root,$fail,$now+1)['status']==='idle');
 ai_ok(music_ai_worker_run($config,$root,$fail,$now+901)['status']==='retry');
 ai_ok(music_ai_worker_run($config,$root,$fail,$now+1802)['status']==='failed');
 ai_ok(music_ai_worker_run($config,$root,$fail,$now+3000)['status']==='idle'&&$calls===4);

 // The operator recovery is limited to the named failed input, and is idempotent.
 $hash=hash_file('sha256',$root.'/library.json');$count=$calls;
 ai_ok(music_ai_worker_run($config,$root,$mock,$now+3001,str_repeat('a',29))['status']==='retry_not_eligible');
 ai_ok($calls===$count&&hash_file('sha256',$root.'/library.json')===$hash);
 $result=music_ai_worker_run($config,$root,$mock,$now+3002,$id);
 ai_ok($result['status']==='prepared'&&$result['assessment']['energy']==='moderat'&&$calls===$count+1);
 $recovered=music_audio_find($root,$id);
 ai_ok($recovered['musicAiJob']['attempts']===4&&$recovered['musicAiJob']['recovery']['previousAttempts']===3);
 ai_ok($recovered['cuePoints']==$before['cuePoints']&&$recovered['tags']===$before['tags']);
 ai_ok($recovered['producerAssessment']['rotationApproved']===false&&hash_file('sha256',$root.'/originals/'.$id.'.mp3')===$sha);
 ai_ok(music_ai_worker_run($config,$root,$mock,$now+3003,$id)['status']==='already_prepared'&&$calls===$count+1);
 $recovered=music_library_edit($id,$sha,$recovered['revision'],['artist'=>'Fixture','title'=>'Recovery failure','tags'=>'Min tag'],$u,'c','c',$root);
 foreach([4000,4901,5802]as$offset)music_ai_worker_run($config,$root,$fail,$now+$offset);
 $count=$calls;
 ai_ok(music_ai_worker_run($config,$root,$fail,$now+6800,$id)['status']==='failed'&&$calls===$count+1);
 ai_ok(music_ai_worker_run($config,$root,$mock,$now+6801,$id)['status']==='recovery_attempt_already_used'&&$calls===$count+1);
 ai_ok(music_ai_worker_run($config,$root,$mock,$now+7800)['status']==='idle'&&$calls===$count+1);

 $t=music_audio_find($root,$id);$t=music_library_edit($id,$sha,$t['revision'],['artist'=>'Fixture','title'=>'Concurrent fixture','tags'=>'Min tag'],$u,'c','c',$root);
 $concurrent=function($c,$payload)use($mock,$root,$id,$sha,$u){
     $t=music_audio_find($root,$id);
     music_library_edit($id,$sha,$t['revision'],['artist'=>'Fixture','title'=>'Operator edit','tags'=>'Behold'],$u,'c','c',$root);
     return $mock($c,$payload);
 };
 ai_ok(music_ai_worker_run($config,$root,$concurrent)['status']==='changed');
 $t=music_audio_find($root,$id);ai_ok($t['manualOverrides']['title']==='Operator edit'&&music_library_producer_current($t)===null);
 // The music transport ignores Studio's relay/socket and retains its model.
 $transportCalls=0;$keyFixture='sk-synthetic-never-log';
 $transportConfig=['openai_model'=>'music-fixture','openai_api_key'=>$keyFixture,'openai_socket'=>'/unrelated/manuscript.sock'];
 $body=json_encode(['status'=>'completed','output'=>[['type'=>'message','content'=>[['type'=>'output_text','text'=>'fixture-response']]]]]);
 $text=music_ai_worker_request($transportConfig,['model'=>'wrong-model','store'=>false],function($request)use(&$transportCalls,$body,$keyFixture){
     $transportCalls++;ai_ok($request['url']==='https://api.openai.com/v1/responses');
     ai_ok($request['payload']['model']==='music-fixture'&&$request['payload']['store']===false);
     ai_ok(in_array('Authorization: Bearer '.$keyFixture,$request['headers'],true));
     return ['curlCode'=>0,'httpStatus'=>200,'body'=>$body];
 });
 ai_ok($text==='fixture-response'&&$transportCalls===1);
 foreach([[60,0,'secret'],[0,401,$keyFixture],[0,200,'bad-json'],[0,200,'{"status":"incomplete"}']]as[$curlCode,$httpStatus,$responseBody]){
     $failed=false;try{music_ai_worker_request($transportConfig,[],fn()=>['curlCode'=>$curlCode,'httpStatus'=>$httpStatus,'body'=>$responseBody]);}
     catch(Throwable $error){$failed=true;ai_ok(!str_contains($error->getMessage(),$keyFixture));}ai_ok($failed);
 }
 echo "PASS: full-audio prerequisite, assessment save/reuse, bounded retry, exclusive lock, concurrent edit, single recovery retry, idempotency, isolated model/transport, manual fields and original preservation.\n";
}finally{foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST)as$f)$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());rmdir($dir);}
