<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/helpers.php';
require dirname(__DIR__,2).'/app/news-input.php';
require dirname(__DIR__,2).'/app/newsroom.php';
require __DIR__.'/relevance-fixture.php';
function hand_ok(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS Handoff: $label\n";}
function hand_no(callable $fn,string $label):void{try{$fn();}catch(InvalidArgumentException){hand_ok(true,$label);return;}throw new RuntimeException($label);}
$registry=tempnam(sys_get_temp_dir(),'rr-handoff-');$board=tempnam(sys_get_temp_dir(),'rr-board-');
$user=['id'=>'synthetic-editor','name'=>'Testredaktør','role'=>'producer'];$config=['openai_api_key'=>'mock','openai_model'=>'mock'];
$feed='nrk_vestland_siste';$text='Frivillige på Bømlo inviterer naboene til dugnad ved grendahuset. Alle kan bidra med rydding. Tiltaket gir et felles møtested i bygda.';
$source=['id'=>hash('sha256','synthetic-handoff'),'url'=>'https://www.nrk.no/vestland/dugnad-1.12345678','title'=>'Dugnad på Bømlo','rssTitle'=>'Dugnad på Bømlo','summary'=>$text,'rssText'=>$text,'publishedAt'=>gmdate('c'),'fetchedAt'=>gmdate('c'),'rssFeed'=>$feed,'feedIds'=>[$feed],'sourceName'=>'NRK','feedStatus'=>'updated'];
$feeds=[$feed=>['status'=>'updated','items'=>[$source],'fetchedAt'=>$source['fetchedAt']]];
file_put_contents($registry,'{"items":[]}');file_put_contents($board,'{"items":[]}');
try{
 $review=studio_input_save($source,['action'=>'review','csrf'=>'test','source'=>$source['id'],'sourceVersion'=>studio_input_version($source),'reviewRevision'=>0,'channel'=>'radio','reason'=>'Dugnaden er viktig for lokalmiljøet og passer i radiosendingen.'],$user,'test',$registry);
 $post=['csrf'=>'test','source'=>$source['id'],'intakeFeed'=>$feed,'intakeVersion'=>$review['sourceVersion'],'intakeRevision'=>1,'channel'=>'both','reason'=>'Forged reason','actor'=>'Forged actor'];
 $before=file_get_contents($board);
 $handoff=studio_input_handoff($feeds,$post,$user,'test',$registry);
 hand_ok($handoff['channel']==='radio'&&$handoff['source']['relevance']['intakeReview']['reason']===$review['reason']&&$handoff['source']['relevance']['intakeReview']['actor']===$user['name'],'server review wins over forged posted channel, reason and actor');
 hand_no(fn()=>studio_input_handoff($feeds,$post,array_replace($user,['role'=>'observer']),'test',$registry),'observer blocked');
 hand_no(fn()=>studio_input_handoff($feeds,array_replace($post,['csrf'=>'bad']),$user,'test',$registry),'CSRF blocked');
 hand_no(fn()=>studio_input_handoff($feeds,array_replace($post,['intakeRevision'=>0]),$user,'test',$registry),'stale review blocked');
 hand_no(fn()=>studio_input_handoff($feeds,array_replace($post,['source'=>'other']),$user,'test',$registry),'forged source blocked');
 $changed=$feeds;$changed[$feed]['items'][0]['rssText']='Endret kildegrunnlag';
 hand_no(fn()=>studio_input_handoff($changed,$post,$user,'test',$registry),'changed RSS blocked');
 $stale=$feeds;$stale[$feed]['status']='stale';
 hand_no(fn()=>studio_input_handoff($stale,$post,$user,'test',$registry),'failed feed blocked');
 hand_ok(file_get_contents($board)===$before,'handoff validation never creates a case');
 $item=studio_newsroom_intake($handoff['source'],$handoff['channel'],$user,$board);
 hand_ok($item['relevance']['intakeReview']['sourceVersion']===$review['sourceVersion']&&$item['channel']==='radio','actual intake stores versioned review');
 $original=['url'=>$item['sourceUrl'],'text'=>$text,'sha256'=>hash('sha256',$text),'fetchedAt'=>gmdate('c'),'kind'=>'original_article'];
 $assess=fn($candidate,$changes=[])=>studio_relevance_assess($candidate,$original,[],$config,function($c,$p)use($review,$changes){$input=json_decode($p['input'],true);hand_ok($input['editorial']['intakeReview']['reason']===$review['reason'],'original assessment receives human reason');return relevance_fixture_response($changes);});
 $assessment=$assess($item);
 hand_ok($assessment['intakeReviewStatus']==='carried'&&$assessment['override']['channel']==='radio','supported selection carries chosen channel without new reason');
 $saved=studio_relevance_record($item['id'],$item['revision'],$assessment,$user,$board);
 studio_relevance_require($saved,$original,'radio');
 hand_no(fn()=>studio_relevance_require($saved,$original,'web'),'unselected web channel stays blocked');
 hand_ok(empty($saved['approvedBy'])&&empty($saved['verified'])&&($saved['status']??'')==='draft'&&empty($saved['web']['approvedHash']),'review never approves manuscript, web or audio');
 $bad=$assess($item,['sourceSufficient'=>false]);$candidate=$item;$candidate['relevance']=$bad;
 hand_ok($bad['intakeReviewStatus']==='clarify'&&empty($bad['override']),'insufficient source cannot inherit permission');
 hand_no(fn()=>studio_relevance_require($candidate,$original,'radio'),'insufficient original blocks production');
 $bad=$assess($item,['radio'=>false]);$candidate['relevance']=$bad;
 hand_no(fn()=>studio_relevance_require($candidate,$original,'radio'),'channel conflict requires clarification');
 $altered=$saved;$altered['relevance']['intakeOriginalHash']=hash('sha256','old original');$bad=$assess($altered);
 hand_ok($bad['intakeReviewStatus']==='clarify'&&empty($bad['override']),'changed full original cannot silently renew human selection');
 $altered['relevance']=$bad;hand_ok($assess($altered)['intakeReviewStatus']==='clarify','second original check cannot erase unresolved source change');
 $item=$saved;$relevance=$saved['relevance'];$can=false;
 function newsroom_time(?string $at):string{return $at??'Ukjent';}
 ob_start();require dirname(__DIR__,2).'/app/views/news-relevance.php';$html=ob_get_clean();
 hand_ok(str_contains($html,$review['reason'])&&str_contains($html,'Din vurdering fra nyhetsinngangen: Radio')&&str_contains($html,'samme vurdering trenger ikke gjentas.'),'actual view leads with human judgement');
 hand_ok(strpos($html,$review['reason'])<strpos($html,'Kontroll av originalkilden'),'original control details follow human judgement');
 $relevance['intakeReview']['reason']='<script>synthetic</script>';
 ob_start();require dirname(__DIR__,2).'/app/views/news-relevance.php';$html=ob_get_clean();
 hand_ok(!str_contains($html,'<script>synthetic</script>')&&str_contains($html,'&lt;script&gt;synthetic&lt;/script&gt;'),'review rendered safely');
 $snapshot=file_get_contents($board);studio_newsroom_intake($handoff['source'],'both',$user,$board);
 hand_ok(file_get_contents($board)===$snapshot,'existing case and manual edits stay untouched');
 studio_input_save($source,['action'=>'discard','csrf'=>'test','source'=>$source['id'],'sourceVersion'=>studio_input_version($source),'reviewRevision'=>1,'reason'=>'Syntetisk forkastelse for negativ kontroll.'],$user,'test',$registry);
 hand_no(fn()=>studio_input_handoff($feeds,array_replace($post,['intakeRevision'=>2]),$user,'test',$registry),'discarded source cannot be carried');
}finally{foreach([$registry,$registry.'.lock',$board,$board.'.lock'] as $file)if(is_file($file))unlink($file);}
