<?php
declare(strict_types=1);
require_once __DIR__.'/audio-workflow.php';
function rr_audio_processing_profile(array $p):array {
    if(($p['approved']??false)!==true||trim((string)($p['version']??''))===''||!in_array($p['sample_rate']??null,[24000,44100,48000],true)||!in_array($p['send_format']??'', ['wav','mp3'],true))throw new InvalidArgumentException('En godkjent, versjonert lydprofil mangler.');
    foreach(['lufs'=>[-40,-5],'true_peak_db'=>[-9,-0.1],'max_silence_seconds'=>[0.2,5]]as$key=>$range)if(!is_numeric($p[$key]??null)||!is_finite((float)$p[$key])||$p[$key]<$range[0]||$p[$key]>$range[1])throw new InvalidArgumentException('Lydprofilen mangler gyldige nivå- eller stillhetsgrenser.');
    if($p['send_format']==='mp3'&&!in_array($p['bitrate_kbps']??null,[128,192,256,320],true))throw new InvalidArgumentException('Velg en godkjent MP3-bitrate.');
    return $p;
}
/** Bounded native process, argv array: no shell, no network inputs, no provider secrets. */
function rr_audio_ffmpeg(array $args,string $binary):string {
    if(!str_starts_with($binary,'/')||!is_executable($binary))throw new InvalidArgumentException('FFmpeg er ikke verifisert på denne serveren.');
    $p=proc_open(array_merge([$binary,'-nostdin','-hide_banner','-nostats','-threads','1'], $args),[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($p))throw new RuntimeException('Lydbehandlingen kunne ikke startes.');
    fclose($pipes[0]);stream_set_blocking($pipes[1],false);stream_set_blocking($pipes[2],false);$log='';$out='';$start=microtime(true);$exit=-1;
    try {do{$out.=stream_get_contents($pipes[1]);$log.=stream_get_contents($pipes[2]);if(strlen($out)+strlen($log)>200000||microtime(true)-$start>45)throw new RuntimeException('Lydbehandlingen overskred grensen.');$status=proc_get_status($p);if(!$status['running']){$exit=$status['exitcode'];break;}usleep(20000);}while(true);
        $log.=stream_get_contents($pipes[2]);if($exit!==0)throw new RuntimeException('Lydbehandlingen mislyktes.');return $log;
    }finally{foreach([1,2]as$n)fclose($pipes[$n]);if(proc_get_status($p)['running'])proc_terminate($p,9);proc_close($p);}
}
function rr_audio_loudness(string $log):array {
    if(!preg_match('/\{\s*"input_i".*?\}/s',$log,$m))throw new RuntimeException('Lydmåling mangler.');
    $d=json_decode($m[0],true,512,JSON_THROW_ON_ERROR);
    foreach(['input_i','input_tp']as$k)if(!is_numeric($d[$k]??null)||!is_finite((float)$d[$k]))throw new RuntimeException('Lydmålingen er ugyldig eller inneholder bare stillhet.');
    return ['lufs'=>(float)$d['input_i'],'truePeakDb'=>(float)$d['input_tp']];
}
function rr_audio_quality(array $measure,array $loudness,array $profile,array $scriptProfile):array {
    $issues=[];
    if($measure['duration']<$scriptProfile['min']||$measure['duration']>$scriptProfile['max'])$issues[]='Varigheten passer ikke valgt manusprofil.';
    if($measure['silenceSeconds']>$profile['max_silence_seconds'])$issues[]='For lang stillhet.';
    if($measure['clippedSamples']>0)$issues[]='Lyden har klipping.';
    if(abs($loudness['lufs']-$profile['lufs'])>0.5)$issues[]='Lydstyrken avviker fra profilen.';
    if($loudness['truePeakDb']>$profile['true_peak_db']+0.1)$issues[]='Sant toppnivå overstiger profilen.';
    if($measure['sampleRate']!==$profile['sample_rate'])$issues[]='Feil samplerate.';
    return ['passed'=>!$issues,'issues'=>$issues,'measurements'=>$measure,'loudness'=>$loudness];
}
/** Repeatable processing of saved raw audio never calls TTS again. */
function rr_audio_process(string $id,int $revision,string $profile,array $user,array $config,?string $path=null,?callable $runner=null):void {
    rr_audio_role($user);$p=rr_audio_processing_profile($config['audio_profile']??[]);$sp=rr_audio_profile($profile);$board=studio_board_read($path);$item=studio_case_get($id,$path);$v=$item['audioScripts'][$profile]??[];$a=$v['audio']??[];
    if(($item['revision']??0)!==$revision||!rr_audio_current($item,$v,$board,$config)||!in_array($a['status']??'', ['needs_processing','processed'],true))throw new InvalidArgumentException('Lydgrunnlaget er endret eller ikke klart.');
    $input=rr_audio_asset($a['raw'],$path);$run=$runner??'rr_audio_ffmpeg';$binary=(string)($config['ffmpeg_binary']??'');
    $dir=rr_audio_directory($path).'/'.bin2hex(random_bytes(16));if(!mkdir($dir,0700))throw new RuntimeException('Midlertidig lydlager mangler.');
    $master=$dir.'/master.wav';$send=$p['send_format']==='wav'?$master:$dir.'/send.mp3';$decoded=$dir.'/decoded.wav';
    try {
        $filter='loudnorm=I='.(float)$p['lufs'].':TP='.(float)$p['true_peak_db'].':LRA=7:print_format=json';
        $run(['-protocol_whitelist','file,pipe','-i',$input,'-map','0:a:0','-vn','-af',$filter,'-ar',(string)$p['sample_rate'],'-ac','1','-c:a','pcm_s16le','-f','wav',$master],$binary);
        if($p['send_format']==='mp3')$run(['-protocol_whitelist','file,pipe','-i',$master,'-map','0:a:0','-vn','-c:a','libmp3lame','-b:a',$p['bitrate_kbps'].'k','-ar',(string)$p['sample_rate'],'-f','mp3',$send],$binary);
        $measurements=[];$qa=[];
        foreach(['master'=>$master,'send'=>$send]as$kind=>$file){
            if(!is_file($file)||filesize($file)>24000000)throw new RuntimeException('Lydfilen kunne ikke behandles.');
            $log=$run(['-protocol_whitelist','file,pipe','-i',$file,'-af',$filter,'-f','null','-'],$binary);
            if($kind==='send'&&$p['send_format']==='mp3'){$run(['-protocol_whitelist','file,pipe','-i',$file,'-ar',(string)$p['sample_rate'],'-ac','1','-c:a','pcm_s16le','-f','wav',$decoded],$binary);$measure=rr_audio_measure((string)file_get_contents($decoded));}
            else $measure=rr_audio_measure((string)file_get_contents($file));
            $qa[$kind]=rr_audio_quality($measure,rr_audio_loudness($log),$p,$sp);
        }
        $rawMeasure=rr_audio_measure((string)file_get_contents($input));
        $issues=array_merge($qa['master']['issues'],$qa['send']['issues']);if($rawMeasure['clippedSamples']>0)$issues[]='TTS-originalen har klipping; normalisering reparerer ikke dette.';
        $result=['status'=>'processed','master'=>rr_audio_store((string)file_get_contents($master),'wav',$path),'send'=>rr_audio_store((string)file_get_contents($send),$p['send_format'],$path),'qa'=>['passed'=>!$issues,'issues'=>array_values(array_unique($issues)),'master'=>$qa['master'],'send'=>$qa['send']],'profileHash'=>rr_audio_hash($p),'profile'=>$p,'approval'=>null];
        studio_board_change(static function(&$b)use($id,$revision,$profile,$a,$result,$config){foreach($b['items']as&$i)if(($i['id']??'')===$id){$v=&$i['audioScripts'][$profile];if(($i['revision']??0)!==$revision||($v['audio']['token']??'')!==$a['token']||!rr_audio_current($i,$v,$b,$config))throw new InvalidArgumentException('Saken ble endret under lydbehandlingen. Resultatet er ikke godkjent.');$v['audio']=array_replace($v['audio'],$result);unset($i['audioQueue']);$i['revision']++;return;}throw new InvalidArgumentException('Saken finnes ikke.');},$path);
    }finally{foreach([$master,$dir.'/send.mp3',$decoded]as$file)if(is_file($file))unlink($file);rmdir($dir);}
}
function rr_audio_approve_or_queue(string $id,int $revision,string $profile,string $action,array $input,array $user,array $config,?string $path=null):void {
    rr_audio_role($user);rr_audio_profile($profile);
    studio_board_change(static function(&$b)use($id,$revision,$profile,$action,$input,$user,$config,$path){foreach($b['items']as&$i)if(($i['id']??'')===$id){
        if(($i['revision']??0)!==$revision)throw new InvalidArgumentException('Saken ble endret. Last siden på nytt.');
        $v=&$i['audioScripts'][$profile];if(!$v)throw new InvalidArgumentException('Lydvarianten finnes ikke.');$a=&$v['audio'];
        if($action==='approve_audio'){
            if(($input['confirmed']??'')!=='1')throw new InvalidArgumentException('Lytt gjennom og bekreft manuell sluttgodkjenning av lyden.');
            $a['approval']=['hash'=>rr_audio_hash([$a['master']??[],$a['send']??[],$a['token']??'']),'actor'=>$user['name']??'Medarbeider','at'=>gmdate('c')];
            if(!rr_audio_ready($i,$v,$b,$config,$path))throw new InvalidArgumentException('Lydfilen, kildekontrollen eller lydprofilen er ikke godkjennbar.');
        }elseif($action==='enqueue'){
            if(!rr_audio_ready($i,$v,$b,$config,$path))throw new InvalidArgumentException('Både manus og aktuell lyd må sluttgodkjennes først.');
            $i['audioQueue']=['profile'=>$profile,'token'=>$a['token'],'sendHash'=>$a['send']['sha256'],'actor'=>$user['name']??'Medarbeider','at'=>gmdate('c')];
        }else throw new InvalidArgumentException('Ukjent lydhandling.');$i['revision']++;return;
    }throw new InvalidArgumentException('Saken finnes ikke.');},$path);
}
function rr_audio_queued(array $item,array $board,array $config,?string $path=null):?array {
    $q=$item['audioQueue']??[];$v=$item['audioScripts'][$q['profile']??'']??[];$a=$v['audio']??[];
    return $q&&($q['token']??'')===($a['token']??'')&&($q['sendHash']??'')===($a['send']['sha256']??'')&&rr_audio_ready($item,$v,$board,$config,$path)?$q:null;
}
