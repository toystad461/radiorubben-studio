<?php
declare(strict_types=1);

/** One assessment per invocation. The private lock spans the API call. */
function music_ai_worker_run(array $config, string $root, ?callable $request=null, ?int $now=null):array {
    if(empty($config['openai_model'])||(!$request&&empty($config['openai_api_key'])))return ['status'=>'configuration_required'];
    $now??=time();
    if(!music_library_directory($root,false))return ['status'=>'empty'];
    $lockPath=$root.'/ai-worker.lock';
    if(is_link($lockPath))throw new RuntimeException('Invalid worker lock');
    $lock=fopen($lockPath,'c+b');
    if(!$lock)throw new RuntimeException('Worker lock unavailable');
    try {
        if(!flock($lock,LOCK_EX|LOCK_NB))return ['status'=>'busy'];
        chmod($lockPath,0600);
        foreach(music_library_read($root) as $track){
            if(($track['audioQueue']['status']??null)==='running')continue;
            if(!music_library_analysis_current($track)||!music_audio_profile_current($track))continue;
            try{$input=music_library_producer_input($track);}catch(InvalidArgumentException){continue;}
            $key=hash('sha256',json_encode([$input,$config['openai_model'],'music-ai-worker-1'],JSON_THROW_ON_ERROR));
            if(music_library_producer_current($track))continue;
            $previous=$track['musicAiJob']??[];
            $same=($previous['inputKey']??null)===$key;
            if($same&&(($previous['attempts']??0)>=3||($previous['nextAttemptAt']??0)>$now))continue;
            $job=['inputKey'=>$key,'status'=>'running','attempts'=>$same?($previous['attempts']??0)+1:1,
                'updatedAt'=>gmdate('c',$now),'nextAttemptAt'=>$now+900];
            // Persist an attempt before the external call, including on process death.
            $track=music_worker_commit($root,$track,['musicAiJob'=>$job]);
            try {
                $assessment=music_library_producer_assess($config,$track,$request);
                $job['status']='prepared';$job['nextAttemptAt']=null;
                music_worker_commit($root,$track,['producerAssessment'=>$assessment,'musicAiJob'=>$job]);
                return ['status'=>'prepared','trackId'=>$track['id']];
            } catch(Throwable) {
                $job['status']=$job['attempts']>=3?'failed':'retry';
                $job['errorCode']='assessment_unavailable';
                $job['nextAttemptAt']=$job['attempts']>=3?null:$now+900;
                try{music_worker_commit($root,$track,['musicAiJob'=>$job]);}
                catch(Throwable){return ['status'=>'changed','trackId'=>$track['id']];}
                return ['status'=>$job['status'],'trackId'=>$track['id']];
            }
        }
        return ['status'=>'idle'];
    } finally {flock($lock,LOCK_UN);fclose($lock);}
}

if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    try {
        $args=$argv;array_shift($args);
        $mode=array_shift($args);
        if(!in_array($mode,['--check','--smoke','--once'],true)||count($args)!==1||!str_starts_with($args[0],'--app-root=')){
            throw new RuntimeException('Usage: music-ai-worker.php --check|--smoke|--once --app-root=/absolute/path');
        }
        $app=realpath(substr($args[0],11));
        if(!$app||!is_file($app.'/app/config.php')||!is_file($app.'/app/music-library-queue.php'))throw new RuntimeException('Application unavailable');
        require_once $app.'/app/config.php';
        require_once $app.'/app/music-library-queue.php';
        $config=load_config();
        $model=getenv('RR_MUSIC_AI_MODEL');
        $config['openai_model']=$model!==false&&$model!==''?$model:'gpt-4.1-mini';
        if(empty($config['openai_api_key']))throw new RuntimeException('API key unavailable');
        if($mode==='--check'){
            foreach(['curl','mbstring','json'] as $ext)if(!extension_loaded($ext))throw new RuntimeException('Extension unavailable');
            $tracks=music_library_read();$ready=0;$done=0;
            foreach($tracks as $t){if(music_library_producer_current($t))$done++;elseif(music_library_analysis_current($t)&&music_audio_profile_current($t)){try{music_library_producer_input($t);$ready++;}catch(InvalidArgumentException){}}}
            echo json_encode(['status'=>'ready','model'=>$config['openai_model'],'awaitingAi'=>$ready,'assessed'=>$done],JSON_THROW_ON_ERROR)."\n";
        }elseif($mode==='--smoke'){
            // One small synthetic API call; no original, metadata or library writes.
            $raw=producer_request($config,['model'=>$config['openai_model'],'store'=>false,'max_output_tokens'=>50,
                'input'=>'Return the JSON object with ok set to true.',
                'text'=>['format'=>['type'=>'json_schema','name'=>'worker_probe','strict'=>true,'schema'=>[
                    'type'=>'object','properties'=>['ok'=>['type'=>'boolean']],'required'=>['ok'],'additionalProperties'=>false]]]]);
            if(json_decode($raw,true,8,JSON_THROW_ON_ERROR)!==['ok'=>true])throw new RuntimeException('Unexpected probe response');
            echo "PASS: Synthetic structured API response. No library changes.\n";
        }else{
            echo json_encode(music_ai_worker_run($config,music_library_root()),JSON_THROW_ON_ERROR)."\n";
        }
    }catch(Throwable){fwrite(STDERR,"STOPP: AI worker could not complete. No credentials are logged.\n");exit(1);}
}
