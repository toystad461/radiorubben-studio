<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/audio-processing.php';
function audio_ok(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS Audio: $label\n";}
function audio_reject(callable $fn,string $label):void{try{$fn();}catch(InvalidArgumentException|RuntimeException){audio_ok(true,$label);return;}throw new RuntimeException('Expected rejection: '.$label);}
$dir=sys_get_temp_dir().'/rr-audio-'.bin2hex(random_bytes(6));mkdir($dir,0700);$path=$dir.'/board.json';
$admin=['name'=>'Testredaktør','role'=>'admin'];$script='Dette melder Bømlo kommune. Kommunen inviterer til møte.';
$item=['id'=>'1234567890abcdef','revision'=>1,'title'=>'Møte i kommunen','sourceName'=>'Bømlo kommune','originId'=>'fixture','program'=>'god-morgen-vestland','sourceUrl'=>'https://www.bomlo.kommune.no/aktuelt-og-kunngjeringar/mote.123.aspx','sourceAt'=>gmdate('c'),'status'=>'draft','script'=>$script];
$text='Bømlo kommune inviterer til et åpent møte på biblioteket 8. oktober. Møtet handler om trafikksikkerhet. Alle innbyggere kan delta og stille spørsmål.';
$source=['url'=>$item['sourceUrl'],'text'=>$text,'sha256'=>hash('sha256',$text),'fetchedAt'=>gmdate('c'),'kind'=>'original_article'];
$review=['source'=>$source,'status'=>'passed','policy'=>STUDIO_NEWS_POLICY,'checkedAt'=>gmdate('c'),'fingerprint'=>studio_news_fingerprint($item,$script)];$item['sourceCheck']=$review;
$voice='azrGjm6gYkR15bxb9cVv';$profile='news-short';
// These are synthetic test settings, NOT the station's approved audio profile.
$config=['enabled'=>true,'api_key'=>'MOCK-ONLY','model_id'=>'eleven_multilingual_v2','daily_character_limit'=>10000,'voices'=>[$voice=>['approved'=>true,'rights_reference'=>'fixture only','programs'=>[$item['program']]]],'audio_profile'=>['approved'=>true,'version'=>'fixture-1','sample_rate'=>24000,'send_format'=>'wav','lufs'=>-23,'true_peak_db'=>-1,'max_silence_seconds'=>1]];
if(getenv('RR_AUDIO_TEST_FFMPEG')){$config['ffmpeg_binary']=getenv('RR_AUDIO_TEST_FFMPEG');if(getenv('RR_AUDIO_TEST_FORMAT')==='mp3'){$config['audio_profile']['send_format']='mp3';$config['audio_profile']['bitrate_kbps']=128;}}
if(getenv('RR_AUDIO_TEST_PROFILE')==='approved'&&getenv('RR_AUDIO_TEST_FFMPEG'))$config['audio_profile']=(require dirname(__DIR__,2).'/config/example.php')['rr_audio']['audio_profile'];
$input=['profile'=>$profile,'script'=>$script,'check'=>$review];
$pcm='';for($n=0;$n<24000;$n++)$pcm.=pack('v',(int)(3000*sin(2*M_PI*440*$n/24000))&65535);$pcm=str_repeat($pcm,16);$wav=rr_audio_wav($pcm);
$calls=0;$transport=static function($c,$v,$p)use(&$calls,$pcm,$voice){$calls++;audio_ok($v===$voice&&!isset($p['api_key'])&&$p['model_id']==='eleven_multilingual_v2','voice and supported settings, no key in payload');return $pcm;};
$runner=static function($args,$binary)use($wav){$last=end($args);if($last!=='-')file_put_contents($last,$wav);return '{"input_i":"-23.0","input_tp":"-20.0"}';};
if(getenv('RR_AUDIO_TEST_FFMPEG'))$runner='rr_audio_ffmpeg';
$get=fn()=>studio_case_get($item['id'],$path);
$change=static function($action,$extra=[])use($get,$item,$profile,$path,$admin){rr_audio_change($item['id'],$get()['revision'],$action,['profile'=>$profile]+$extra,$admin,$path);};
$generate=static function($tr=null)use($get,$item,$profile,$voice,$admin,$config,$path,$transport){rr_audio_generate($item['id'],$get()['revision'],$profile,$voice,[],$admin,$config,$path,$tr??$transport);};
$approve=static function($action)use($get,$item,$profile,$admin,$config,$path){rr_audio_approve_or_queue($item['id'],$get()['revision'],$profile,$action,['confirmed'=>'1'],$admin,$config,$path);};
try {
    file_put_contents($path,json_encode(['items'=>[$item]]));
    audio_reject(fn()=>$generate(),'no TTS before a variant exists');audio_ok($calls===0,'missing facts never reach provider');
    audio_reject(fn()=>rr_audio_change($item['id'],1,'save',$input,['role'=>'observer'],$path),'observer cannot mutate audio');
    audio_reject(fn()=>$change('save',['script'=>'<script>bad</script>']),'markup is not a spoken manuscript');
    $change('save',['script'=>$script]);audio_reject(fn()=>$change('approve_script',['confirmed'=>'1']),'missing fact check blocks approval');
    $change('checked',['check'=>$review]);audio_reject(fn()=>$generate(),'AI check alone cannot authorize TTS');
    $change('approve_script',['confirmed'=>'1']);
    audio_reject(fn()=>rr_audio_generate($item['id'],$get()['revision'],$profile,$voice,[],$admin,array_replace($config,['enabled'=>false]),$path,$transport),'disabled integration rejects real and injected transport');
    $bad=$config;$bad['voices'][$voice]['programs']=[];audio_reject(fn()=>rr_audio_generate($item['id'],$get()['revision'],$profile,$voice,[],$admin,$bad,$path,$transport),'cross-program voice rejected');
    $bad=$config;$bad['daily_character_limit']=1;audio_reject(fn()=>rr_audio_generate($item['id'],$get()['revision'],$profile,$voice,[],$admin,$bad,$path,$transport),'budget checked before external call');
    $generate();$i=$get();$v=$i['audioScripts'][$profile];audio_ok($calls===1&&$v['audio']['status']==='needs_processing','one external call, private raw preview stored');
    audio_ok(abs($v['audio']['measurements']['duration']-16)<0.001,'duration measured from PCM bytes');
    audio_reject(fn()=>$approve('enqueue'),'raw audio cannot enter rundown');audio_reject(fn()=>$approve('approve_audio'),'raw audio cannot receive final approval');
    $bad=$config;$bad['audio_profile']['approved']=false;audio_reject(fn()=>rr_audio_process($item['id'],$get()['revision'],$profile,$admin,$bad,$path,$runner),'unknown station profile blocks processing');
    rr_audio_process($item['id'],$get()['revision'],$profile,$admin,$config,$path,$runner);audio_ok($calls===1,'processing never regenerates TTS');
    audio_reject(fn()=>$approve('enqueue'),'QA is not human audio approval');$approve('approve_audio');$approve('enqueue');
    $i=$get();audio_ok(rr_audio_queued($i,studio_board_read($path),$config,$path)!==null,'approved WAV is attached to existing board item');
    $changed=$i;$changed['script'].=' Ny rettelse.';audio_ok(rr_audio_queued($changed,studio_board_read($path),$config,$path)===null,'primary manuscript change invalidates audio');
    $changed=$i;$changed['audioScripts'][$profile]['script'].=' Endret.';audio_ok(rr_audio_queued($changed,studio_board_read($path),$config,$path)===null,'variant edit invalidates all approvals');
    audio_ok($i['audioScripts'][$profile]['audio']['aiPolicyVersion']==='1.0.0'&&$i['audioScripts'][$profile]['audio']['synthetic']===true,'TTS records policy version and synthetic origin');
    foreach(['aiPolicyVersion','synthetic'] as $field) {
        $changed=$i;unset($changed['audioScripts'][$profile]['audio'][$field]);
        audio_ok(rr_audio_queued($changed,studio_board_read($path),$config,$path)===null,'missing '.$field.' blocks queued audio');
        $changed=$i;$changed['audioScripts'][$profile]['audio'][$field]=$field==='synthetic'?false:'old';
        audio_ok(rr_audio_queued($changed,studio_board_read($path),$config,$path)===null,'changed '.$field.' blocks queued audio');
    }
    $changed=$i;$changed['audioScripts'][$profile]['check']['checkedAt']=gmdate('c',time()-3601);audio_ok(rr_audio_queued($changed,studio_board_read($path),$config,$path)===null,'expired source check blocks rundown');
    $bad=$config;$bad['voices'][$voice]['approved']=false;audio_ok(rr_audio_queued($i,studio_board_read($path),$bad,$path)===null,'revoked voice blocks existing queued audio');
    $asset=$i['audioScripts'][$profile]['audio']['send'];file_put_contents(rr_audio_asset($asset,$path),'changed');audio_ok(rr_audio_queued($i,studio_board_read($path),$config,$path)===null,'changed file cannot be sent');
    studio_board_update($item['id'],$get()['revision'],'save',['title'=>$item['title'],'script'=>$script.' Endret.','notes'=>'','program'=>$item['program']],$admin,$path);
    studio_board_update($item['id'],$get()['revision'],'save',['title'=>$item['title'],'script'=>$script,'notes'=>'','program'=>$item['program']],$admin,$path);
    audio_ok(empty($get()['audioScripts'][$profile]['scriptApproval'])&&empty($get()['audioQueue']),'restore old primary text does not resurrect approvals');
    $change('approve_script',['confirmed'=>'1']);
    $generate(static function(){throw new RRAudioUncertainException('provider timeout with secret body');});$i=$get();audio_ok($i['audioScripts'][$profile]['audio']['status']==='unknown'&&!str_contains(json_encode($i),'secret body'),'uncertain call stays blocked and does not leak error body');
    audio_reject(fn()=>studio_board_update($item['id'],$get()['revision'],'archive',[],$admin,$path),'unresolved TTS cannot be hidden by archive');
    audio_reject(fn()=>$generate(),'timeout cannot trigger duplicate charge');rr_audio_resolve($item['id'],$get()['revision'],$profile,['confirmed'=>'1','resolution'=>'Fixture: leverandørhistorikk kontrollert'],$admin,$path);
    $generate(static function(){throw new InvalidArgumentException('429');});audio_ok($get()['audioScripts'][$profile]['audio']['status']==='failed','provider rejection preserves manuscript and records failure');
    $generate(static function()use($change,$pcm,$script){$change('save',['script'=>$script.' En endring.']);return $pcm;});audio_ok($get()['audioScripts'][$profile]['audio']['status']==='stale','concurrent edit cannot resurrect old audio');
    audio_reject(fn()=>$approve('enqueue'),'stale generated audio never enters rundown');
    rr_pronunciation_change('propose',['term'=>'Rubbestadneset','alias'=>'Rubbestad neset'],$admin,$path);$b=studio_board_read($path);$e=end($b['pronunciation']['entries']);
    audio_ok(rr_pronunciation_text('Rubbestadneset og Bremnes',rr_pronunciation_context($b))==='Rubbestadneset og Bremnes','unapproved aliases do not affect speech');
    audio_reject(fn()=>rr_pronunciation_change('approve',['id'=>$e['id'],'revision'=>1,'confirmed'=>'1'],['role'=>'presenter'],$path),'pronunciation activation requires admin');
    rr_pronunciation_change('approve',['id'=>$e['id'],'revision'=>1,'confirmed'=>'1'],$admin,$path);$d=rr_pronunciation_context(studio_board_read($path));audio_ok($d['version']===2&&rr_pronunciation_text('Rubbestadneset', $d)==='Rubbestad neset','approved pronunciation version reused');
    audio_ok(!rr_audio_current($i,$i['audioScripts'][$profile],studio_board_read($path),$config),'dictionary change invalidates previous audio');
    $silent=rr_audio_measure(rr_audio_wav(str_repeat("\0",24000*2*16)));audio_ok(!rr_audio_quality($silent,['lufs'=>-23,'truePeakDb'=>-20],$config['audio_profile'],rr_audio_profile($profile))['passed'],'silence QA fails independently of loudness');
    $clip=rr_audio_measure(rr_audio_wav(str_repeat(pack('v',32767),24000*16)));audio_ok($clip['clippedSamples']>0,'clipping detected');
    $measure=rr_audio_measure($wav);$measure['duration']=1;audio_ok(!rr_audio_quality($measure,['lufs'=>-23,'truePeakDb'=>-20],$config['audio_profile'],rr_audio_profile($profile))['passed'],'too-short audio rejected');
    foreach(['missing_permissions','private-secret-in-unrecognized-code']as$code){
        try{rr_elevenlabs_response(true,401,'application/json',json_encode(['detail'=>['status'=>$code,'message'=>'private-secret-body']]));throw new RuntimeException('Expected rejection');}
        catch(RRElevenLabsRejectedException $e){audio_ok($e->httpStatus===401&&$e->providerCode===($code==='missing_permissions'?$code:'unclassified')&&!str_contains($e->getMessage(),'private-secret'),'safe provider diagnosis without response text');}
    }
    foreach([401,403,422,429,500,503]as$status)audio_reject(fn()=>rr_elevenlabs_response(true,$status,'application/json','{secret error body}'),'HTTP '.$status.' rejected');
    audio_reject(fn()=>rr_elevenlabs_response(false,0,'',''),'network timeout rejected');
    audio_reject(fn()=>rr_elevenlabs_response(true,200,'application/json','{}'),'non-audio response rejected');
    audio_ok(rr_elevenlabs_response(true,200,'audio/pcm',$pcm)===$pcm,'valid bounded PCM response accepted');
    audio_reject(fn()=>rr_audio_measure('not audio'),'invalid file format rejected');audio_reject(fn()=>rr_audio_asset(['name'=>'../../config/local.php'],$path),'path traversal rejected');
    audio_reject(fn()=>rr_audio_loudness('{"input_i":"-inf","input_tp":"-inf"}'),'nonfinite loudness cannot pass');
    $request=static function($c,$p)use($source,$script){$in=json_decode($p['input'],true);if(isset($in['segments']))return json_encode(['segments'=>array_map(static fn($s)=>['index'=>$s['index'],'verdict'=>'supported','evidence_indexes'=>[0],'reason'=>'Fixture'],$in['segments']),'issues'=>[]]);audio_ok($in['source']===$source&&!str_contains($p['input'],'WEB POISON')&&str_contains($p['instructions'],'15–20'),'standalone manuscript uses shared source and profile, never web prose');return $script;};
    $item['web']=['body'=>'WEB POISON'];$r=rr_audio_prepare($item,$profile,['openai_api_key'=>'fixture','openai_model'=>'fixture'],[],null,$request);audio_ok($r['check']['source']===$source,'same fact snapshot preserved');
    $item['sourceCheck']=[];audio_reject(fn()=>rr_audio_prepare($item,$profile,[]),'missing shared original rejected');
} finally {
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST)as$f)$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());rmdir($dir);
}
