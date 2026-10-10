<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/newsroom.php';
require __DIR__.'/relevance-fixture.php';
function fb_ok(bool $ok,string $msg):void {if(!$ok)throw new RuntimeException($msg);echo "PASS Feedback: $msg\n";}
function fb_no(callable $f,string $msg):void {try{$f();}catch(InvalidArgumentException){fb_ok(true,$msg);return;}throw new RuntimeException($msg);}
$dir=sys_get_temp_dir().'/feedback-'.bin2hex(random_bytes(5));mkdir($dir,0700);$path=$dir.'/board.json';
$admin=['name'=>'Editor','role'=>'admin'];$config=['openai_api_key'=>'mock','openai_model'=>'mock'];
try {
 studio_board_add_source(['id'=>'rss','title'=>'Dugnad','url'=>'https://www.nrk.no/vestland/dugnad-1.12345','publishedAt'=>gmdate('c'),'sourceName'=>'NRK'],$admin,$path);
 $item=studio_board_read($path)['items'][0];$id=$item['id'];$calls=0;
 $fetch=fn($url)=>'<link rel="canonical" href="'.$url.'"><article><p>'.str_repeat('Frivillige på Bømlo inviterer til dugnad og skaper et møtested for bygda. ',4).'</p></article>';
 studio_board_change(static function(&$b){$b['editorialRules']=[['text'=>'Kontroller lokal tilknytning i originalen.','status'=>'pending','evidence'=>['kind'=>'feedback','itemId'=>$b['items'][0]['id'],'revision'=>1]],['text'=>'OTHER CASE SECRET','status'=>'pending','evidence'=>['kind'=>'feedback','itemId'=>'other','revision'=>1]]];},$path);
 $request=function($c,$p)use(&$calls){$calls++;$input=json_decode($p['input'],true);fb_ok($input['editorial']['request']==='Undersøk betydningen for Bømlo.','new comment reaches assessment');fb_ok(count($input['editorial']['comments'])===1,'only this case comments included');fb_ok(count($input['editorial']['clarificationReasons'])>0,'clarification reasons included');return relevance_fixture_response();};
 fb_no(fn()=>studio_newsroom_reassess($id,1,'', ['role'=>'observer'],$config,$path,$request,$fetch),'observer blocked before model');
 fb_no(fn()=>studio_newsroom_reassess($id,99,'',$admin,$config,$path,$request,$fetch),'stale revision blocked before model');
 fb_ok($calls===0,'no paid calls for blocked actions');
 $saved=studio_newsroom_reassess($id,1,'Undersøk betydningen for Bømlo.',$admin,$config,$path,$request,$fetch);
 fb_ok($saved['revision']===2 && empty($saved['approvedBy']) && empty($saved['web']['approvedHash']) && empty($saved['verified']),'new assessment never approves');
 fb_ok($saved['relevance']['editorial']['request']==='Undersøk betydningen for Bømlo.' && count($saved['relevanceHistory'])===1,'context and assessment history retained');
 fb_no(fn()=>studio_board_update($id,2,'reject',['comment'=>'kort'],$admin,$path),'short reason rejected');
 fb_no(fn()=>studio_board_update($id,2,'reject',['comment'=>'Ikke relevant for Bømlo.'],['role'=>'observer'],$path),'observer cannot reject');
 fb_no(fn()=>studio_board_update($id,1,'reject',['comment'=>'Ikke relevant for Bømlo.'],$admin,$path),'stale rejection rejected');
 studio_board_change(static function(&$b){$b['items'][0]['web']['delivery']['state']='unknown';},$path);
 fb_no(fn()=>studio_newsroom_reassess($id,2,'',$admin,$config,$path,$request,$fetch),'uncertain delivery cannot be reassessed');
 fb_no(fn()=>studio_board_update($id,2,'reject',['comment'=>'Ikke relevant for Bømlo.'],$admin,$path),'uncertain delivery cannot be rejected');
 studio_board_change(static function(&$b){unset($b['items'][0]['web']['delivery']);},$path);
 studio_board_update($id,2,'reject',['comment'=>'Allerede omtalt, ingen nye opplysninger.'],$admin,$path);
 $rejected=studio_board_read($path)['items'][0];
 fb_ok($rejected['status']==='archived' && $rejected['rejection']['actor']==='Editor' && $rejected['rejection']['revision']===2,'reason, actor and version saved atomically with rejection');
 fb_ok(count(studio_board_read($path)['editorialRules'])===2,'no general rule created or approved');
 $candidate=$item;$candidate['id']='abcdef1234567890';$candidate['sourceUrl']='https://www.nrk.no/vestland/ny-1.54321';
 $original=studio_news_source($candidate,$fetch);
 studio_relevance_assess($candidate,$original,[$rejected],$config,function($c,$p){$v=json_decode($p['input'],true);fb_ok($v['previous'][0]['rejectionExample']==='Allerede omtalt, ingen nye opplysninger.','same-program rejection used as editorial example');return relevance_fixture_response();});
 $candidate['program']='different';
 studio_relevance_assess($candidate,$original,[$rejected],$config,function($c,$p){$v=json_decode($p['input'],true);fb_ok($v['previous'][0]['rejectionExample']==='','rejection learning isolated by program');return relevance_fixture_response();});
} finally {foreach(glob($dir.'/*') as $f)unlink($f);rmdir($dir);}
echo "Editorial rejection and reassessment OK (synthetic model).\n";
