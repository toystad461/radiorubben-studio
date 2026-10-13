<?php
declare(strict_types=1);
function music_scheduler_verify(string $expected,string $root,string $statePath):void {
    if(!preg_match('/^[a-f0-9]{40}$/D',$expected)||is_link($statePath)||!is_file($statePath))throw new RuntimeException('Utrullingsgrunnlaget er ikke bekreftet.');
    $state=json_decode((string)file_get_contents($statePath),true,512,JSON_THROW_ON_ERROR);
    $release=$root.'/studio-public/release.json';if(is_link($release)||!is_file($release))throw new RuntimeException('Versjonskvitteringen mangler.');$v=json_decode((string)file_get_contents($release),true,32,JSON_THROW_ON_ERROR);
    if(($state['commit']??null)!==$expected||($v['commit']??null)!==$expected)throw new RuntimeException('Godkjent og utrullet versjon samsvarer ikke.');
    foreach(['music-library-scheduler.php','music-library-worker.php','music-library-online.php','music-library-producer.php','music-library-tags.php','music-library-preparation.php','music-library.php','music.php','producer.php','config.php']as$file){$relative='studio-private/app/'.$file;$path=$root.'/'.$relative;$hash=$state['files'][$relative]['live']??null;if(!is_string($hash)||!preg_match('/^[a-f0-9]{64}$/D',$hash)||is_link($path)||!is_file($path)||hash_file('sha256',$path)!==$hash)throw new RuntimeException('Musikkjobbens kode må avstemmes.');}
}
if(PHP_SAPI==='cli'&&realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){
    $work=getenv('HOME').'/.radiorubben-studio-deploy';$lock=null;
    try{if(is_link($work.'/deploy.lock'))throw new RuntimeException('Ugyldig driftslås.');$lock=fopen($work.'/deploy.lock','rb');if(!$lock||!flock($lock,LOCK_SH|LOCK_NB))throw new RuntimeException('Utrulling pågår eller driftslås mangler.');$root=dirname(__DIR__,2);music_scheduler_verify($argv[1]??'',$root,$work.'/selective-state.json');require_once __DIR__.'/music-library-worker.php';require_once __DIR__.'/config.php';echo json_encode(music_worker_run(load_config()),JSON_THROW_ON_ERROR)."\n";
    }catch(Throwable){fwrite(STDERR,"Musikkjobb stoppet: kontroller fastlåst utrulling og konfigurasjon.\n");exit(1);}finally{if($lock){flock($lock,LOCK_UN);fclose($lock);}}
}
