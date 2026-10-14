<?php
declare(strict_types=1);
// Read-only production inventory. No configuration or editorial content is emitted.
$root='/customers/9/3/1/cptk37ymg/webroots/r1417157';
if(realpath('/run/webroots/r1417157')!==$root)throw new RuntimeException('Unexpected root');
$statePath=getenv('HOME').'/.radiorubben-studio-deploy/selective-state.json';
$before=hash_file('sha256',$statePath);
$state=json_decode(file_get_contents($statePath),true,512,JSON_THROW_ON_ERROR);
$drift=[];$differences=[];$actual=[];
foreach($state['files'] as $path=>$expected){
 if(!preg_match('~^studio-(public|private)/~',$path)||str_contains($path,'..')||is_link($root.'/'.$path))throw new RuntimeException('Invalid state path');
 $hash=is_file($root.'/'.$path)?hash_file('sha256',$root.'/'.$path):null;
 if($hash!==$expected['live'])$drift[]=$path;
 if($expected['source']!==$expected['live'])$differences[$path]=$expected;
 $actual[$path]=$hash;
}
$paths=json_decode(base64_decode(getenv('RR_IMAGE_AUDIT_PATHS')),true,32,JSON_THROW_ON_ERROR);
$selected=[];
foreach($paths as $path){
 if(!preg_match('~^studio-(public|private)/[a-zA-Z0-9_./-]+$~D',$path)||str_contains($path,'..')||is_link($root.'/'.$path))throw new RuntimeException('Invalid candidate path');
 $selected[$path]=['actual'=>is_file($root.'/'.$path)?hash_file('sha256',$root.'/'.$path):null,'state'=>$state['files'][$path]??null];
}
if(hash_file('sha256',$statePath)!==$before)throw new RuntimeException('Deployment changed during audit');
echo json_encode(['at'=>gmdate('c'),'commit'=>$state['commit'],'run'=>$state['run']??null,'state_sha256'=>$before,'checked'=>count($actual),'drift'=>$drift,'preserved_differences'=>$differences,'selected'=>$selected,'php'=>PHP_VERSION],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
if($drift)exit(1);
