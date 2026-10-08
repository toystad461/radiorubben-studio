<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/audio-processing.php';
function config_audio_ok(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS Audio config: $label\n";}
function config_audio_reject(callable $fn,string $label):void{try{$fn();}catch(InvalidArgumentException|RuntimeException){config_audio_ok(true,$label);return;}throw new RuntimeException('Expected rejection: '.$label);}
$root=dirname(__DIR__,2);$c=(require $root.'/config/example.php')['rr_audio'];$voice='azrGjm6gYkR15bxb9cVv';
config_audio_reject(fn()=>rr_audio_voice($c,'xF681s0UeE04gsf0mVsJ',''),'superseded voice is unavailable for new generation');
config_audio_ok($c['enabled']===false&&$c['daily_character_limit']===0,'trial approval does not activate TTS or spend');
config_audio_ok($c['model_id']==='eleven_v4','owner-selected v4 is the configured model');
$p=rr_audio_processing_profile($c['audio_profile']);
config_audio_ok($p['lufs']===-18.0&&$p['true_peak_db']===-2.0&&$p['sample_rate']===44100&&$p['send_format']==='mp3'&&$p['bitrate_kbps']===192,'approved trial values retained');
foreach(array_merge([''],array_keys(studio_program_registry()['programs']))as$program)config_audio_ok(rr_audio_voice($c,$voice,$program)['scope']==='all','all-context voice works without reassigning legacy program');
config_audio_reject(fn()=>rr_audio_voice($c,$voice,'invented-program'),'unknown program remains invalid');
$bad=$c;$bad['voices'][$voice]['approved']=false;config_audio_reject(fn()=>rr_audio_voice($bad,$voice,''),'all-context scope cannot override voice revocation');
$bad=$c;$bad['voices'][$voice]['scope']='selected';config_audio_reject(fn()=>rr_audio_voice($bad,$voice,''),'unassigned requires explicit all-context permission');
$bad=$c;$bad['voices'][$voice]['rights_reference']='';config_audio_reject(fn()=>rr_audio_voice($bad,$voice,''),'all-context scope cannot replace rights confirmation');
$tmp=sys_get_temp_dir().'/rr-key-config-'.bin2hex(random_bytes(5));mkdir($tmp.'/app',0700,true);mkdir($tmp.'/config',0700);
copy($root.'/app/config.php',$tmp.'/app/config.php');copy($root.'/config/example.php',$tmp.'/config/example.php');
require $tmp.'/app/config.php';putenv('ELEVENLABS_API_KEY');$key=$tmp.'/config/elevenlabs.key';
try{
    config_audio_ok(load_config()['rr_audio']['api_key']==='','absent key stays unconfigured');
    file_put_contents($key,'fixture-not-a-real-key-1234');chmod($key,0600);
    config_audio_ok(load_config()['rr_audio']['api_key']==='fixture-not-a-real-key-1234'&&load_config()['rr_audio']['enabled']===false,'private key loads without activation');
    chmod($key,0644);clearstatcache();config_audio_reject(fn()=>load_config(),'readable-by-others key rejected');
    chmod($key,0600);clearstatcache();putenv('ELEVENLABS_API_KEY=fixture-env-key');config_audio_ok(load_config()['rr_audio']['api_key']==='fixture-env-key','environment takes precedence');putenv('ELEVENLABS_API_KEY');
    file_put_contents($tmp.'/config/audio-local.php',"<?php return ['enabled'=>true,'test_only'=>true,'daily_character_limit'=>3000];");
    $loaded=load_config();config_audio_ok($loaded['rr_audio']['enabled']===true&&$loaded['rr_audio']['test_only']===true&&$loaded['rr_audio']['model_id']==='eleven_v4'&&$loaded['auth_mode']==='demo','private audio settings preserve model and authentication');
    unlink($key);symlink($tmp.'/config/example.php',$key);config_audio_reject(fn()=>load_config(),'key symlink rejected');unlink($key);
}finally{
    putenv('ELEVENLABS_API_KEY');foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST)as$f)$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());rmdir($tmp);
}
