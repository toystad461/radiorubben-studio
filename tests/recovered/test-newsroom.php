<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/newsroom.php';
require __DIR__.'/relevance-fixture.php';
function check($ok,$label){if(!$ok)throw new RuntimeException($label);echo "OK $label\n";}
function reject($f,$label){try{$f();}catch(InvalidArgumentException $e){echo "OK $label\n";return;}throw new RuntimeException($label);}
$dir=sys_get_temp_dir().'/newsroom-'.bin2hex(random_bytes(4));mkdir($dir);$path=$dir.'/board.json';
$user=['role'=>'admin','name'=>'Test'];$config=['openai_api_key'=>'mock','openai_model'=>'mock'];$calls=0;$reads=0;
$text='Bømlo kommune inviterer innbyggerne til et åpent møte om trafikksikkerhet. Møtet arrangeres på biblioteket 8. oktober. Alle som ønsker det kan delta på møtet.';
$source=['id'=>'fixture','title'=>'RSS er bare en pekepinn','sourceName'=>'NRK','publishedAt'=>gmdate('c'),'url'=>'https://www.nrk.no/vestland/mote-1.12345678','publishedAt'=>gmdate('c')];
$fetch=function($url)use(&$reads,$text){$reads++;return '<html><head><link rel="canonical" href="'.$url.'"></head><body><article><p>'.$text.'</p><p>Kildesak: '.$url.'</p></article></body></html>';};
$request=function($c,$payload)use(&$calls,$text){if(($payload['text']['format']['name']??'')==='rss_relevance')return relevance_fixture_response();$calls++;$input=json_decode($payload['input'],true);
    check(($input['source']['text']??'')===$text."\nKildesak: ".$input['source']['url'],'model sees fetched original, never RSS summary');
    if(str_starts_with($payload['instructions'],'Skriv ett nyhetsmanus'))return 'Kommunen inviterer innbyggerne til et åpent møte om trafikksikkerhet.';
    if(!isset($input['segments']))return json_encode(['title'=>'Åpent møte på biblioteket','intro'=>'Kommunen inviterer til et åpent møte.','body'=>"Møtet handler om trafikksikkerhet.\nDette melder NRK."]);
    $segments=[];foreach($input['segments'] as $i=>$segment)$segments[]=['index'=>$i,'verdict'=>'supported','evidence'=>[$text],'reason'=>'Belegg i originalen.'];
    return json_encode(['segments'=>$segments,'issues'=>[]]);
};
try{
    check(studio_newsroom_tick([], $config,$path,$request,$fetch)['state']==='paused','automatic preparation opt-in');
    studio_board_change(static function(&$b){$b['newsroomSettings']=['enabled'=>true,'dailyLimit'=>8];},$path);
    $feed=[['status'=>'updated','items'=>[$source,$source+['summary'=>'Kort omtale']]]];
    check(studio_newsroom_tick($feed,$config,$path,$request,$fetch)['state']==='prepared','new story is prepared');
    $item=studio_board_active(studio_board_read($path))[0];$card=studio_newsroom_card($item);
    check(count(studio_board_read($path)['items'])===1&&$calls===4&&$reads===1,'one source read and separate web/radio writing and review passes');
    check($card['status']==='ready'&&$card['originalRead']&&!isset($item['web']['delivery']),'ready story stays local, no publication');
    check($item['script']!==''&&$item['status']==='draft'&&!$item['verified'],'radio production is visible but never auto-approved');
    check($item['sourceCheck']['source']===$item['web']['check']['source'],'radio and web share the exact original snapshot on one item');
    $radioBefore=$item['script'];
    $hash=studio_web_approval_hash($item);
    check(studio_newsroom_tick($feed,$config,$path,$request,$fetch)['state']==='idle'&&$calls===4,'duplicate feed does not spend or rewrite');
    $tampered=$item;$tampered['web']['check']['source']['text'].='Changed';
    check(!studio_newsroom_card($tampered)['canApprove'],'tampered original snapshot blocks readiness');
    $missing=$item;unset($missing['web']['check']['source']);check(studio_newsroom_card($missing)['status']==='attention','RSS-only evidence is not ready');
    $unknown=$item;$unknown['web']['delivery']=['state'=>'unknown'];check(!studio_newsroom_card($unknown)['canApprove'],'ambiguous delivery needs resolution');
    reject(fn()=>studio_newsroom_prepare($item['id'],$item['revision']-1,$user,$config,'',$path,$request,$fetch),'stale preparation rejected');
    reject(fn()=>studio_newsroom_prepare($item['id'],$item['revision'],['role'=>'observer'],$config,'',$path,$request,$fetch),'observer cannot prepare');
    reject(fn()=>studio_news_source($item,fn($url)=>'<link rel="canonical" href="https://www.nrk.no/vestland/other-1.99999999"><article><p>'.$text.'</p><p>Kildesak: '.$url.'</p></article>'),'wrong NRK original is rejected');
    studio_board_change(static function(&$b){$b['items'][0]['web']['check']['checkedAt']=gmdate('c',time()-3601);},$path);
    check(studio_newsroom_tick($feed,$config,$path,$request,$fetch)['state']==='rechecked','expired check is renewed without rewriting');
    $item=studio_case_get($item['id'],$path);check(studio_web_approval_hash($item)===$hash&&$calls===5,'renewed check preserves text and notification identity');
    check($item['script']===$radioBefore,'web recheck preserves radio production');
    // Legacy web-only items are completed without rewriting their saved web text.
    studio_board_change(static function(&$b){$b['items'][0]['script']='';unset($b['items'][0]['sourceCheck']);},$path);
    $legacy=studio_case_get($item['id'],$path);$legacyWeb=studio_web_text($legacy['web']);$beforeCalls=$calls;$beforeReads=$reads;
    studio_newsroom_prepare($legacy['id'],$legacy['revision'],$user,$config,'',$path,$request,$fetch,true);
    $item=studio_case_get($legacy['id'],$path);
    check(studio_web_text($item['web'])===$legacyWeb&&$calls===$beforeCalls+3&&$reads===$beforeReads+1,'legacy backfill preserves web text and uses one original fetch');
    check($item['script']!==''&&$item['sourceCheck']['source']===$item['web']['check']['source'],'legacy productions share refreshed original on the existing item');
    check(!$item['verified']&&empty($item['web']['approval'])&&empty($item['web']['delivery']),'legacy backfill remains unapproved and unpublished');
    $failedReview=studio_web_prepare($item,$config,[],function($c,$p)use($request){return isset(json_decode($p['input'],true)['segments'])?'{}':$request($c,$p);},$fetch);
    check($failedReview['body']!==''&&$failedReview['check']['status']==='needs_review'&&studio_news_original_read($item,$failedReview['check']),'failed review preserves draft and original without allowing approval');
    studio_board_update($item['id'],$item['revision'],'archive',[],$user,$path);
    check(studio_newsroom_tick($feed,$config,$path,$request,$fetch)['state']==='idle','rejected source cannot return via RSS');
    $next=$source;$next['id']='failure';$next['url']='https://www.nrk.no/vestland/next-1.12345679';
    $waiting=studio_newsroom_tick([['status'=>'updated','items'=>[$next]]],$config,$path,$request,fn()=>throw new StudioNewsPreparationException('Originalen er utilgjengelig.'));
    check($waiting['recommendation']==='needs_source'&&count(studio_board_active(studio_board_read($path)))===0,'unreadable source waits without creating production item');
    $selections=studio_board_read($path)['newsSelections'];
    check(end($selections)['assessment']['recommendation']==='needs_source','failed selection visible in inbox');
    check(studio_newsroom_tick([['status'=>'updated','items'=>[$next]]],$config,$path,$request,$fetch)['state']==='idle','failed automatic jobs do not retry paid generation');
    $partial=$source;$partial['id']='partial';$partial['url']='https://www.nrk.no/vestland/partial-1.12345680';
    $radioFails=function($c,$p)use($request){
        if(str_starts_with($p['instructions'],'Skriv ett nyhetsmanus'))throw new StudioNewsPreparationException('Radiogeneratoren sviktet.');
        return $request($c,$p);
    };
    reject(fn()=>studio_newsroom_tick([['status'=>'updated','items'=>[$partial]]],$config,$path,$radioFails,$fetch),'radio failure is visible without blind retry');
    $partialItem=studio_case_source_item(studio_board_active(studio_board_read($path)),$partial);
    check(!empty($partialItem['web']['body'])&&$partialItem['newsroom']['state']==='failed'&&!isset($partialItem['web']['delivery']),'radio failure preserves web draft without publication');
    studio_board_update($partialItem['id'],$partialItem['revision'],'save',['title'=>$partialItem['title'],'script'=>'Redaktørens eget radiomanus.','notes'=>'Bevar.','program'=>$partialItem['program']],$user,$path);
    $edited=studio_case_get($partialItem['id'],$path);
    studio_newsroom_prepare($edited['id'],$edited['revision'],$user,$config,'Kortere netttekst',$path,$radioFails,$fetch);
    check(studio_case_get($edited['id'],$path)['script']==='Redaktørens eget radiomanus.','web revision never overwrites human radio script');
}finally{foreach(glob($dir.'/*')as$f)unlink($f);rmdir($dir);}
