<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/helpers.php';
require dirname(__DIR__,2).'/app/case-workflow.php';
require dirname(__DIR__,2).'/app/wordpress-status.php';
function status_ok(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS WP status: $label\n";}
function status_no(callable $fn,string $label):void{try{$fn();}catch(InvalidArgumentException|RuntimeException){status_ok(true,$label);return;}throw new RuntimeException($label);}
$path=tempnam(sys_get_temp_dir(),'rr-wp-status-');$admin=['role'=>'admin','name'=>'Syntetisk redaktør'];$config=['username'=>'synthetic','application_password'=>'synthetic'];
$item=['id'=>'0123456789abcdef','originId'=>'synthetic','title'=>'Test','sourceUrl'=>'https://www.bomlo.kommune.no/aktuelt/test.123.aspx','sourceName'=>'Testkilde','script'=>'Manuelt radiomanus','status'=>'draft','revision'=>1,
 'web'=>['title'=>'Syntetisk nettsak','intro'=>'Syntetisk ingress.','body'=>'Manuelt rettet syntetisk tekst.','publication'=>['version'=>STUDIO_NEWS_PUBLICATION_VERSION,'featured_media'=>1079,'categories'=>[8,27]]]];
$item['web']['approvedHash']=studio_web_approval_hash($item);
$item['web']['delivery']=['state'=>'unknown','requestedStatus'=>'publish','hash'=>studio_web_approval_hash($item),'token'=>'synthetic-token','at'=>gmdate('c',time()-300)];
$remote=['id'=>1326,'status'=>'publish','link'=>'https://www.radiorubben.no/studio-'.$item['id'].'/','slug'=>'studio-'.$item['id'],'title'=>['raw'=>$item['web']['title']],'excerpt'=>['raw'=>$item['web']['intro']],'content'=>['raw'=>studio_web_html($item)]];
$reset=static function($case)use($path){file_put_contents($path,json_encode(['items'=>[$case]]));};
$calls=0;$lookup=static function($c,$method,$route)use(&$calls,$remote){$calls++;status_ok($method==='GET'&&str_contains($route,'slug=studio-0123456789abcdef'),'only exact identity GET used');return [$remote];};
try{
 $reset($item);$before=file_get_contents($path);
 status_no(fn()=>studio_web_status_check($item['id'],1,['role'=>'producer'],$config,$path,$lookup),'producer cannot reconcile');
 status_no(fn()=>studio_web_status_check($item['id'],0,$admin,$config,$path,$lookup),'stale revision blocks network');
 status_ok($calls===0&&file_get_contents($path)===$before,'negative boundaries preserve board');
 $result=studio_web_status_check($item['id'],1,$admin,$config,$path,$lookup);$saved=studio_case_get($item['id'],$path);
 status_ok($result['state']==='matched'&&$saved['web']['delivery']['state']==='confirmed'&&$saved['web']['delivery']['id']===1326,'matching published post repairs receipt');
 status_ok(studio_web_status_label($saved)==='Publisert på radiorubben.no','published status shared by surfaces');
 $copy=$saved;unset($copy['web']['delivery'],$copy['web']['deliveryHistory']);$original=$item;unset($original['web']['delivery']);$copy['revision']=$original['revision'];unset($copy['updatedAt']);
 status_ok($copy===$original,'receipt check preserves manual text, approval and radio');
 status_ok($saved['web']['deliveryHistory'][0]['delivery']===$item['web']['delivery'],'previous attempt preserved');
 $reset($item);$before=file_get_contents($path);
 status_no(fn()=>studio_web_status_check($item['id'],1,$admin,$config,$path,fn()=>throw new RuntimeException('synthetic failure')),'transport failure blocked');
 status_ok(file_get_contents($path)===$before,'transport failure never clears uncertainty');
 foreach(['different text'=>array_replace_recursive($remote,['content'=>['raw'=>'Different article']]),'wrong status'=>array_replace($remote,['status'=>'draft'])]as$label=>$post){
  $reset($item);$r=studio_web_status_check($item['id'],1,$admin,$config,$path,fn()=>[$post]);$s=studio_case_get($item['id'],$path);
  status_ok($r['state']==='different'&&$s['web']['delivery']['state']==='unknown', $label.' remains blocked');
 }
 foreach(['none'=>[],'multiple'=>[$remote,$remote]]as$label=>$posts){$reset($item);$r=studio_web_status_check($item['id'],1,$admin,$config,$path,fn()=>$posts);status_ok(studio_case_get($item['id'],$path)['web']['delivery']['state']==='unknown',$label.' cannot allow retry');}
 foreach(['evil host'=>array_replace($remote,['link'=>'https://evil.invalid/test']),'wrong slug'=>array_replace($remote,['slug'=>'other']),'missing raw'=>array_replace($remote,['title'=>[]])]as$label=>$post){
  $reset($item);if($label==='missing raw'){$r=studio_web_status_check($item['id'],1,$admin,$config,$path,fn()=>[$post]);status_ok($r['state']==='different','rendered or missing text never confirms');}
  else status_no(fn()=>studio_web_status_check($item['id'],1,$admin,$config,$path,fn()=>[$post]),$label.' blocked');
 }
 $changed=$item;$changed['web']['intro']='Changed after reservation';$reset($changed);
 status_ok(studio_web_status_check($item['id'],1,$admin,$config,$path,fn()=>[$remote])['state']==='different','changed content cannot confirm old attempt');
 $reset($item);$before=file_get_contents($path);
 status_no(fn()=>studio_web_status_check($item['id'],1,$admin,$config,$path,function()use($reset,$item,$remote){$case=$item;$case['revision']=2;$reset($case);return [$remote];}),'concurrent revision prevents status write');
 status_ok(studio_case_get($item['id'],$path)['web']['delivery']['state']==='unknown','concurrent outcome preserved');
 $pending=$item;$pending['web']['delivery']['state']='pending';$pending['web']['delivery']['at']=gmdate('c');$reset($pending);
 status_no(fn()=>studio_web_status_check($item['id'],1,$admin,$config,$path,$lookup),'active transfer cannot be reconciled early');
 $known=$item;$known['web']['delivery']['id']=1326;$reset($known);
 studio_web_status_check($item['id'],1,$admin,$config,$path,function($c,$method,$route)use($remote){status_ok($method==='GET'&&str_starts_with($route,'/posts/1326?'),'known id uses direct GET');return $remote;});
 $draft=$item;$draft['web']['delivery']['requestedStatus']='draft';$reset($draft);studio_web_status_check($item['id'],1,$admin,$config,$path,fn()=>[array_replace($remote,['status'=>'draft'])]);
 status_ok(studio_web_status_label(studio_case_get($item['id'],$path))==='WordPress-kladd lagret','draft never labelled published');
 $unapproved=$item;unset($unapproved['web']['approvedHash']);$reset($unapproved);studio_web_status_check($item['id'],1,$admin,$config,$path,$lookup);
 status_ok(empty(studio_case_get($item['id'],$path)['web']['approvedHash']),'lookup cannot create human approval');
 $reset($item);status_no(fn()=>studio_web_publish($item['id'],1,'publish',$admin,$config,fn()=>throw new RuntimeException('must not call'),$path),'unresolved attempt still blocks publishing');
}finally{if(is_file($path))unlink($path);if(is_file($path.'.lock'))unlink($path.'.lock');}
