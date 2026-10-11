<?php
declare(strict_types=1);
// Only used inside the temporary copy created by news-input-http.cjs.
$root=$argv[1]??'';if(!is_dir($root.'/config')||!getenv('RR_INPUT_TEST_PASSWORD'))throw new RuntimeException('Missing isolated test directory.');
require $root.'/app/news-input.php';require $root.'/app/auth/StudioLocalUsers.php';
file_put_contents($root.'/config/local.php','<?php return '.var_export([
 'site_mode'=>'app','auth_mode'=>'entra','base_url'=>'http://127.0.0.1:8099',
 'tenant_id'=>'00000000-0000-0000-0000-000000000001','client_id'=>'00000000-0000-0000-0000-000000000002',
 'client_secret'=>'synthetic-local-test-only','local_users_enabled'=>true,
 'openai_api_key'=>'','openai_model'=>'','rr_audio'=>['enabled'=>false,'api_key'=>'']
],true).';');
$user=studio_local_user_create('Isolert testredaktør','editor@example.test',getenv('RR_INPUT_TEST_PASSWORD'),'producer');
studio_local_users_update(static function(array &$users)use($user){$users[$user['id']]['mustChange']=false;});
$counts=[];$now=time();
foreach(newsdesk_sources() as $id=>$spec){
 $body=(string)file_get_contents($root.'/feeds/'.$id.'.xml');$at=gmdate('c',$now);
 $rows=newsdesk_decode_rss($body,$id,$spec,$at);if($rows===null)throw new RuntimeException('Invalid feed: '.$id);
 file_put_contents($root.'/config/newsdesk-'.$id.'.json',json_encode(['checkedAt'=>$now,'fetchedAt'=>$at,'items'=>$rows,'failed'=>false],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
 $counts[$id]=count(newsdesk_recent_sources($rows,$now));
}
file_put_contents($root.'/config/sending-board.json',json_encode(['items'=>[['id'=>'synthetic-sentinel','script'=>'Dette testmanuset skal ikke endres.','revision'=>1]],'newsroomSettings'=>['enabled'=>false]]));
echo json_encode(['feeds'=>$counts],JSON_THROW_ON_ERROR);
