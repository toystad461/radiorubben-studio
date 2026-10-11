<?php
declare(strict_types=1);
// Fixed forced-command reader. No bootstrap, arbitrary path or command arguments.
if(getenv('SSH_ORIGINAL_COMMAND')!==false&&getenv('SSH_ORIGINAL_COMMAND')!=='rr-studio-inventory-v1')exit(64);
$root='/customers/9/3/1/cptk37ymg/webroots/r1417157';
if(realpath($root)!==$root)throw new RuntimeException('Unexpected root');
$work=getenv('HOME').'/.radiorubben-studio-deploy';
$lock=@fopen($work.'/deploy.lock','r');
if(!$lock||!flock($lock,LOCK_SH|LOCK_NB))throw new RuntimeException('Existing deploy lock unavailable');
$targets=['studio-private/app/integrations/NewsDesk.php','studio-private/app/views/newsroom.php',
 'studio-private/app/news-input.php','studio-private/app/views/news-input.php',
 'studio-public/news-input.php','studio-public/assets/news-input.css',
 'studio-private/composer.json','studio-public/release.json'];
$dependencies=['studio-private/app/board.php','studio-private/app/users.php',
 'studio-private/app/source-identity.php','studio-private/app/bootstrap.php',
 'studio-private/app/views/head.php','studio-private/app/views/sidebar.php'];
try{
 $statePath=$work.'/selective-state.json';$stateBefore=hash_file('sha256',$statePath);
 $state=json_decode((string)file_get_contents($statePath),true,512,JSON_THROW_ON_ERROR);
 $rows=[];
 foreach(array_merge($targets,$dependencies) as $relative){
  $at=$root;
  foreach(explode('/',$relative) as $part){$at.='/'.$part;if(is_link($at))throw new RuntimeException('Symlink: '.$relative);}
  $parent=dirname($at);if(realpath($parent)!==$parent||!is_readable($parent))throw new RuntimeException('Parent cannot be verified: '.$relative);
  if(!file_exists($at)){$rows[$relative]=['status'=>'absent','absenceVerified'=>true];continue;}
  if(!is_file($at)||!is_readable($at))throw new RuntimeException('Unreadable target: '.$relative);
  $before=stat($at);$hash=hash_file('sha256',$at);clearstatcache(true,$at);$after=stat($at);
  $rows[$relative]=['status'=>'present','sha256'=>$hash,'bytes'=>$after['size'],'mode'=>decoct($after['mode']&0777),
   'stableDuringRead'=>$before['mtime']===$after['mtime']&&$before['size']===$after['size'],
   'recordedLive'=>$state['files'][$relative]['live']??null];
 }
 $stateAfter=hash_file('sha256',$statePath);if($stateBefore!==$stateAfter)throw new RuntimeException('Deploy state changed during read');
 echo json_encode(['observedUtc'=>gmdate('c'),'productionWritten'=>false,'existingSharedLock'=>true,
  'collectorSha256'=>hash_file('sha256',__FILE__),
  'deployCommit'=>$state['commit']??null,'deployRun'=>$state['run']??null,'deployStateSha256'=>$stateBefore,
  'phpCli'=>PHP_VERSION,'extensions'=>array_combine(['mbstring','SimpleXML','libxml','curl'],array_map('extension_loaded',['mbstring','SimpleXML','libxml','curl'])),
  'privateConfigWritable'=>is_writable($root.'/studio-private/config'),'files'=>$rows],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
}finally{flock($lock,LOCK_UN);fclose($lock);}
