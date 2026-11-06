<?php
declare(strict_types=1);
// Exercise the actual endpoint files in an isolated PHP process, without Studio data.
$root=sys_get_temp_dir().'/rr-status-request-'.bin2hex(random_bytes(5));
mkdir($root.'/public',0700,true);mkdir($root.'/app',0700,true);
$setup=<<<'STUB'
<?php
$_SERVER['REQUEST_METHOD']=getenv('RR_TEST_METHOD')?:'GET';
$_GET=json_decode(getenv('RR_TEST_QUERY')?:'{}',true);
register_shutdown_function(function(){echo "\nHTTP=".(http_response_code()?:200);});
function current_user(){return ['role'=>'observer'];}
function studio_roles(){return ['observer'=>[]];}
function studio_can($u,$c){return false;}
function music_playout_config(){return ['root'=>sys_get_temp_dir()];}
function music_playout_lock($r){return tmpfile();}
function music_playout_state($r){return ['current'=>['title'=>'A']];}
function music_playout_json($p){return [];}
function studio_controlroom_read($c){return ['schemaVersion'=>1,'status'=>'playing','streamUrl'=>'/music-test-stream/'];}
function music_public_status($s,$h,$c){return ['schemaVersion'=>1,'playing'=>true,'now'=>['title'=>'A']];}
function music_library_root(){return sys_get_temp_dir();}
function music_public_artwork_index($r){return [];}
function music_public_artwork_url($s,$i){return null;}
STUB;
file_put_contents($root.'/app/setup.php',$setup);
foreach(['bootstrap.php','controlroom.php','music-public-status.php','music-public-artwork.php'] as $file)file_put_contents($root.'/app/'.$file,'<?php require_once __DIR__."/setup.php";');
try{
 foreach(['controlroom-api.php','radio-status.php'] as $endpoint){
  copy(__DIR__.'/../public/'.$endpoint,$root.'/public/'.$endpoint);
  $id=str_repeat('a',32);
  foreach([[[],200],[['requestId'=>$id],200],[['requestId'=>''],400],[['requestId'=>['x']],400],[['requestId'=>str_repeat('a',33)],400],[['requestId'=>str_repeat('A',32)],400],[['requestId'=>'<script>'],400]] as [$query,$status]){
   putenv('RR_TEST_QUERY='.json_encode($query));
   $proc=proc_open([PHP_BINARY,$root.'/public/'.$endpoint],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
   $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($proc);
   if($exit!==0||$err!==''||!str_ends_with($out,'HTTP='.$status))throw new RuntimeException($endpoint.': '.$out.' '.$err);
   $body=json_decode(explode("\nHTTP=",$out)[0],true,512,JSON_THROW_ON_ERROR);
   if($status===200 && ($body['requestId']??null)!==($query['requestId']??null))throw new RuntimeException('Request identity mismatch');
   if(isset($body['streamUrl']))throw new RuntimeException('Observer gained private listening');
  }
  echo 'PASS '.$endpoint.': legacy reply, request binding, malformed/array/oversized input, observer restriction'.PHP_EOL;
 }
}finally{
 foreach(glob($root.'/public/*') as $p)unlink($p);foreach(glob($root.'/app/*') as $p)unlink($p);
 rmdir($root.'/public');rmdir($root.'/app');rmdir($root);putenv('RR_TEST_QUERY');
}
