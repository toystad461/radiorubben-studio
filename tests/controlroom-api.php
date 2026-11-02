<?php
declare(strict_types=1);
$tmp=sys_get_temp_dir().'/rr-controlroom-test-'.bin2hex(random_bytes(5));
mkdir($tmp.'/public',0700,true);mkdir($tmp.'/app',0700,true);
copy(dirname(__DIR__).'/public/controlroom-api.php',$tmp.'/public/controlroom-api.php');
copy(dirname(__DIR__).'/app/users.php',$tmp.'/app/users.php');
file_put_contents($tmp.'/app/bootstrap.php','<?php require __DIR__."/users.php"; function current_user(){ $r=getenv("RR_TEST_ROLE");return $r==="guest"?null:["role"=>$r];} session_start(); $_SERVER["REQUEST_METHOD"]=getenv("RR_TEST_METHOD");register_shutdown_function(function(){echo "\nHTTP=".(http_response_code()?:200);});');
file_put_contents($tmp.'/app/controlroom.php','<?php function music_playout_config(){return null;} function studio_controlroom_read($c){return ["status"=>"unconfigured","streamUrl"=>"/music-test-stream/"];}');
try {
foreach([['guest','GET',401],['invalid','GET',401],['observer','GET',200],['presenter','GET',200],['producer','GET',200],['admin','GET',200],['admin','POST',405],['admin','DELETE',405]]as [$role,$method,$status]){
putenv('RR_TEST_ROLE='.$role);putenv('RR_TEST_METHOD='.$method);
$p=proc_open([PHP_BINARY,$tmp.'/public/controlroom-api.php'],[1=>['pipe','w'],2=>['pipe','w']],$pipes);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($p);
if($exit!==0||$err!==''||!str_contains($out,'HTTP='.$status))throw new RuntimeException("$role $method: $out $err");
if($status===200 && str_contains($out,'streamUrl')!==($role!=='observer'))throw new RuntimeException('Private monitor permission changed');
echo "PASS endpoint $role $method $status\n";
}
}finally{foreach(['public/controlroom-api.php','app/bootstrap.php','app/controlroom.php','app/users.php']as $n)unlink($tmp.'/'.$n);rmdir($tmp.'/public');rmdir($tmp.'/app');rmdir($tmp);putenv('RR_TEST_ROLE');putenv('RR_TEST_METHOD');}
