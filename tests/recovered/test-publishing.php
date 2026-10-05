<?php
require dirname(__DIR__, 2).'/app/case-workflow.php';
function check($ok,$label){if(!$ok)throw new RuntimeException($label);echo "OK $label\n";}
function reject($f,$label){try{$f();}catch(Throwable $e){echo "OK $label\n";return;}throw new RuntimeException($label);}
$dir=sys_get_temp_dir().'/web-'.bin2hex(random_bytes(4));mkdir($dir);$path=$dir.'/board.json';
$admin=['name'=>'Test','role'=>'admin'];$c=['username'=>'test','application_password'=>'mock'];
try{
 studio_board_add_source(['id'=>'source','title'=>'Test','sourceName'=>'Kommune','url'=>'https://www.bomlo.kommune.no/aktuelt-og-kunngjeringar/test.123.aspx','publishedAt'=>'2026-09-30T08:00:00Z'],$admin,$path);
 $item=studio_board_active(studio_board_read($path))[0];$id=$item['id'];
 $w=['title'=>'Et møte','intro'=>'Kommunen inviterer.','body'=>'Et åpent møte holdes på mandag.'];
 studio_web_save($id,1,'save',$w,$admin,$path);
 reject(fn()=>studio_web_save($id,1,'save',$w,$admin,$path),'stale revision');
 reject(fn()=>studio_web_publish($id,2,'publish',$admin,$c,null,$path),'unreviewed publication blocked');
 $calls=0;$req=function($c,$m,$r,$p)use(&$calls){$calls++;return ['id'=>123,'status'=>$p['status'],'link'=>'https://www.radiorubben.no/test/'];};
 studio_web_publish($id,2,'draft',$admin,$c,$req,$path);
 $item=studio_case_get($id,$path);studio_web_publish($id,$item['revision'],'draft',$admin,$c,$req,$path);
 check($calls===1,'duplicate draft does not send again');
 $review=['source'=>['url'=>$item['sourceUrl'],'text'=>str_repeat('Et kontrollert kildebelegg. ',8),'sha256'=>hash('sha256',str_repeat('Et kontrollert kildebelegg. ',8)),'fetchedAt'=>gmdate('c')],'policy'=>STUDIO_NEWS_POLICY,'status'=>'passed','checkedAt'=>gmdate('c'),'fingerprint'=>studio_news_fingerprint($item,studio_web_text($w))];
 studio_web_save($id,$item['revision'],'check',['check'=>$review],$admin,$path);
 $item=studio_case_get($id,$path);studio_web_save($id,$item['revision'],'approve',['confirmed'=>'1'],$admin,$path);
 $item=studio_case_get($id,$path);$seen='';$publish=function($c,$m,$r,$p)use(&$seen){$seen=$r;return ['id'=>123,'status'=>'publish','link'=>'https://www.radiorubben.no/test/'];};
 studio_web_publish($id,$item['revision'],'publish',$admin,$c,$publish,$path);check($seen==='/posts/123','publishes existing draft');
 $item=studio_case_get($id,$path);studio_web_save($id,$item['revision'],'save',$w,$admin,$path);
 check(!studio_web_checked(studio_case_get($id,$path)),'editing invalidates web review');
 reject(fn()=>studio_web_publish($id,studio_case_get($id,$path)['revision'],'publish',['role'=>'presenter'],$c,$req,$path),'presenter cannot publish');
 // Separate item for ambiguous network delivery.
 studio_board_add_manual('Second',$admin,$path);$other=studio_board_active(studio_board_read($path))[1];
 studio_web_save($other['id'],1,'save',$w,$admin,$path);
 reject(fn()=>studio_web_publish($other['id'],2,'draft',$admin,$c,fn()=>throw new RuntimeException('timeout'),$path),'timeout handled');
 $other=studio_case_get($other['id'],$path);
 check($other['web']['delivery']['state']==='unknown','unknown result retained');
 reject(fn()=>studio_web_publish($other['id'],$other['revision'],'draft',$admin,$c,$req,$path),'no blind retry after timeout');
 check(studio_case_get($id,$path)['script']==='','web operations preserve radio script');
}finally{foreach(glob($dir.'/*')as$f)unlink($f);rmdir($dir);}
