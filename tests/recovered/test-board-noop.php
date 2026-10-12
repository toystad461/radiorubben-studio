<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/board.php';
function noop_ok(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS Board writes: $label\n";}
$dir=sys_get_temp_dir().'/rr-noop-'.bin2hex(random_bytes(5));mkdir($dir,0700);
$path=$dir.'/board.json';$user=['role'=>'producer','name'=>'Syntetisk redaktør'];
$source=['id'=>'synthetic-origin','title'=>'Syntetisk sak','sourceName'=>'Syntetisk kilde','url'=>'https://www.nrk.no/vestland/synthetic-1.12345678','publishedAt'=>gmdate('c')];
try{
 studio_board_add_source($source,$user,$path);
 $b=studio_board_read($path);$b['updatedAt']='2000-01-01T00:00:00Z';
 $b['items'][0]['script']='Manuelt rettet syntetisk manus.';
 $b['items'][0]['audioScripts']=['test'=>['scriptApproval'=>['actor'=>'Test'],'audio'=>['approval'=>['actor'=>'Test']]]];
 file_put_contents($path,json_encode($b,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));touch($path,946684800);clearstatcache(true,$path);
 $before=file_get_contents($path);$inode=fileinode($path);$mtime=filemtime($path);
 $result=studio_board_change(static fn(array &$board)=>['state'=>'unchanged'],$path);
 clearstatcache(true,$path);
 noop_ok(file_get_contents($path)===$before,'empty update preserves exact bytes and timestamp');
 noop_ok(fileinode($path)===$inode&&filemtime($path)===$mtime,'empty update does not replace the file');
 noop_ok($result===['state'=>'unchanged'],'empty update preserves callback return value');
 studio_board_add_source($source,$user,$path);
 noop_ok(file_get_contents($path)===$before,'duplicate source does not rewrite the rundown');
 studio_board_change(static function(array &$board){$board['items'][0]['title']='Midlertidig tittel';$board['items'][0]['title']='Syntetisk sak';},$path);
 noop_ok(file_get_contents($path)===$before,'restored callback content remains unchanged');
 try{studio_board_change(static function(array &$board){$board['items'][0]['title']='Skal ikke lagres';throw new InvalidArgumentException('synthetic');},$path);}catch(InvalidArgumentException){}
 noop_ok(file_get_contents($path)===$before,'failed update preserves file');
 $missing=$dir.'/missing.json';studio_board_change(static fn(array &$board)=>null,$missing);
 noop_ok(!is_file($missing),'empty update cannot create a new data file');
 $empty=$dir.'/empty.json';studio_board_change(static function(array &$board){$board['newsroomSettings']=['enabled'=>false];},$empty);
 noop_ok(is_file($empty)&&studio_board_read($empty)['newsroomSettings']['enabled']===false,'genuine first configuration is stored');
 studio_board_change(static function(array &$board){$board['newsroomSettings']=['enabled'=>false];},$path);
 $changed=studio_board_read($path);
 noop_ok($changed['updatedAt']!==$b['updatedAt']&&$changed['items']===$b['items'],'actual metadata edit is stored without touching content or approvals');
 studio_board_change(static function(array &$board){$board['items'][0]['script']='Nytt syntetisk manus som krever godkjenning.';},$path);
 $changed=studio_board_read($path);
 noop_ok($changed['items'][0]['script']==='Nytt syntetisk manus som krever godkjenning.'&&$changed['items'][0]['audioScripts']['test']['scriptApproval']===null&&$changed['items'][0]['audioScripts']['test']['audio']['approval']===null,'actual manuscript edit still invalidates script and audio approvals');
 $last=file_get_contents($path);studio_board_change(static fn(array &$board)=>null,$path);
 noop_ok(file_get_contents($path)===$last,'empty update after real edit is stable');
 $changed['items'][0]['bulletin']=['valid'=>true,'sources'=>[['id'=>'missing-source','hash'=>str_repeat('0',64)]]];
 file_put_contents($path,json_encode($changed));
 studio_board_change(static fn(array &$board)=>null,$path);
 $safe=studio_board_read($path);
 noop_ok($safe['items'][0]['bulletin']['valid']===false&&$safe['items'][0]['revision']===$changed['items'][0]['revision']+1,'derived invalidation persists even when callback is empty');
}finally{foreach(glob($dir.'/*')as$file)unlink($file);rmdir($dir);}
