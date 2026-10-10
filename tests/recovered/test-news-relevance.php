<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/newsroom.php';
require __DIR__.'/relevance-fixture.php';
function rel_ok(bool $ok,string $label):void {if(!$ok)throw new RuntimeException($label);echo "PASS Relevance: $label\n";}
function rel_no(callable $fn,string $label):void {try{$fn();}catch(InvalidArgumentException){rel_ok(true,$label);return;}throw new RuntimeException($label);}
$config=['openai_api_key'=>'mock','openai_model'=>'mock'];
$item=['id'=>'1234567890abcdef','originId'=>'rss','title'=>'Dugnad','sourceUrl'=>'https://www.nrk.no/vestland/dugnad-1.12345','sourceAt'=>gmdate('c')];
$local='Frivillige på Bømlo inviterer naboene til dugnad ved grendahuset. Alle kan bidra til maling og rydding av uteplassen. Tiltaket gir et felles møtested i bygda.';
function rel_source(array $item,string $text):array {return ['url'=>$item['sourceUrl'],'text'=>$text,'sha256'=>hash('sha256',$text),'fetchedAt'=>gmdate('c'),'kind'=>'original_article'];}
$source=rel_source($item,$local);
$assess=fn($changes=[],$text=null,$previous=[])=>studio_relevance_assess($item,rel_source($item,$text??$local),$previous,$config,fn($c,$p)=>relevance_fixture_response($changes));
$r=$assess();rel_ok($r['recommendation']==='both'&&$r['localEvidence']===[$local],'small local volunteer story retains verbatim evidence');
$r=$assess(['locality'=>'regional_national','web'=>false,'webReason'=>'Kort trafikkmelding gir ikke nok stoff til en selvstendig artikkel.'],'Ferjesambandet som betjener Bømlo får endrede avganger. Reisende fra Bømlo må benytte den oppgitte alternative avgangen mandag. Operatøren opplyser om endringen.');
rel_ok($r['recommendation']==='radio','documented regional transport can be radio only');
foreach(['Bergen sentrum får en ny restaurant. Åpningen samler mange gjester fra Vestland. Restauranten ligger ved kysten og har utsikt til en øy.',
    'En nasjonal kjendis har gitt ut et nytt album. Publikum i hele landet kan strømme musikken. Lanseringen skjedde i hovedstaden.'] as $text){
    rel_ok($assess(['locality'=>'none','localEvidence'=>[],'impactEvidence'=>[]],$text)['recommendation']==='rejected','unrelated Bergen or celebrity story rejected despite radio/web model flags');
    rel_ok($assess([],$text)['recommendation']==='needs_source','forged high local recommendation without explicit evidence cannot pass');
}
rel_ok($assess([], 'Artisten er oppvokst på Bømlo og gir sin første konsert i Oslo. Hun presenterer egen musikk fra debutalbumet. Arrangøren har kunngjort programmet for konserten.')['recommendation']==='both','documented person from Bømlo elsewhere can qualify');
rel_ok($assess(['current'=>false,'eventDate'=>'2020-06-01','eventEvidence'=>[0],'timelinessReason'=>'Teksten gjelder en hendelse i 2020 uten ny utvikling.'],
    'Dugnaden på Bømlo ble holdt 1. juni 2020. Frivillige malte grendahuset og ryddet uteplassen. Teksten er publisert på nytt, uten nye opplysninger.')['recommendation']==='rejected','fresh publication cannot make old event current');
$older=$item;$older['sourceAt']=gmdate('c',time()-6*86400);
$future=gmdate('Y-m-d',time()+7*86400);
$futureSource=rel_source($older,"Bømlo frivilligsentral inviterer til samling $future. Alle kan delta. Samlingen inneholder verksted, musikk og anledning til å møte naboer i lokalsamfunnet.");
rel_ok(studio_relevance_assess($older,$futureSource,[],$config,fn()=>relevance_fixture_response(['eventDate'=>$future,'eventEvidence'=>[0]]))['recommendation']==='both','older publication with documented future local event survives');
rel_ok(studio_relevance_assess($older,$futureSource,[],$config,fn()=>relevance_fixture_response())['recommendation']==='needs_source','old publication and unknown event date waits');
rel_ok(studio_relevance_assess($older,$futureSource,[],$config,fn()=>relevance_fixture_response(['temporalKind'=>'ongoing','eventEvidence'=>[0]]))['recommendation']==='both','documented ongoing local service is not expired by publication age');
rel_ok($assess(['sourceSufficient'=>false])['recommendation']==='needs_source','insufficient source blocks production');
$peer=$item;$peer['id']='fedcba0987654321';$peer['sourceUrl']='https://www.bomlo.kommune.no/aktuelt-og-kunngjeringar/dugnad.123.aspx';$peer['web']['delivery']['status']='publish';
$identical=$peer;$identical['sourceCheck']['source']=$source;
rel_ok($assess([],null,[$identical])['recommendation']==='rejected','identical original cannot be marked new even by faulty model response');
$dup=$assess(['relation'=>'duplicate','relatedId'=>$peer['id'],'relationReason'=>'Samme dugnad, tidspunkt og arrangør uten nye fakta.'],null,[$peer]);
rel_ok($dup['recommendation']==='rejected'&&$dup['relatedId']===$peer['id'],'cross-source duplicate linked to prior published story');
rel_ok($assess(['relation'=>'update','relatedId'=>$peer['id'],'newEvidence'=>[0]],null,[$peer])['recommendation']==='both','material update needs cited new evidence');
rel_ok($assess(['relation'=>'update','relatedId'=>$peer['id']],null,[$peer])['recommendation']==='needs_source','claimed update without new evidence waits');
rel_no(fn()=>$assess(['override'=>['channel'=>'both']]),'model cannot inject an editorial override or server metadata');
rel_no(fn()=>$assess(['localEvidence'=>[999]]),'fabricated paragraph evidence rejected');
rel_no(fn()=>$assess(['relatedId'=>'invented']),'invented prior story rejected');
rel_no(fn()=>$assess(['eventDate'=>'2026-99-40','eventEvidence'=>[0]]),'invalid event date rejected');
$ready=$item;$ready['relevance']=$assess();
studio_relevance_require($ready,$source,'radio');
$changed=$source;$changed['text'].=' Endret.';$changed['sha256']=hash('sha256',$changed['text']);
rel_no(fn()=>studio_relevance_require($ready,$changed,'radio'),'changed original invalidates assessment');
$changedItem=$ready;$changedItem['sourceAt']=gmdate('c',time()-300);
rel_no(fn()=>studio_relevance_require($changedItem,$source,'radio'),'changed publication metadata invalidates assessment');
$calls=0;$paid=function()use(&$calls){$calls++;throw new RuntimeException('Must never reach generation');};
rel_no(fn()=>studio_news_prepare($item,$config,[],null,$paid,null,$source),'direct radio generation cannot bypass selection');
rel_no(fn()=>studio_web_prepare($item,$config,[],$paid,null,$source),'direct web generation cannot bypass selection');
$published=$ready;$published['web']['delivery']['status']='publish';
rel_no(fn()=>studio_web_prepare($published,$config,[],$paid,null,$source),'published story cannot regenerate');
rel_ok($calls===0,'blocked paths make no generation calls');
$dir=sys_get_temp_dir().'/relevance-'.bin2hex(random_bytes(5));mkdir($dir,0700);$path=$dir.'/board.json';$admin=['role'=>'admin','name'=>'Editor'];
try{
    $feed=['id'=>'rss','title'=>'Dugnad','url'=>$item['sourceUrl'],'publishedAt'=>$item['sourceAt'],'sourceName'=>'NRK'];
    studio_board_add_source($feed,$admin,$path);
    $saved=studio_board_read($path)['items'][0];
    $saved=studio_relevance_record($saved['id'],1,$assess(),$admin,$path);
    rel_no(fn()=>studio_relevance_override($saved['id'],$saved['revision'],'radio','Konkret lokal grunn til radio.',['role'=>'observer'],$path),'observer cannot override');
    rel_no(fn()=>studio_relevance_override($saved['id'],$saved['revision'],'radio','kort',$admin,$path),'override needs concrete reason');
    rel_no(fn()=>studio_relevance_override($saved['id'],1,'radio','Konkret lokal grunn til radio.',$admin,$path),'stale override rejected');
    studio_relevance_override($saved['id'],$saved['revision'],'radio','Kort nytteinformasjon er nok for radio, ikke nett.',$admin,$path);
    $saved=studio_case_get($saved['id'],$path);
    rel_ok($saved['relevance']['override']['actor']==='Editor'&&count($saved['relevanceHistory'])===2,'override actor and history recorded');
    rel_ok(empty(studio_board_read($path)['editorialRules'])&&empty($saved['approvedBy'])&&empty($saved['web']['approvedHash']),'override neither learns a rule nor approves publication');
    rel_no(fn()=>studio_relevance_require($saved,$source,'web'),'radio override does not permit web');
    $bad=$saved['relevance'];$bad['sourceSufficient']=false;
    $saved=studio_relevance_record($saved['id'],$saved['revision'],$bad,$admin,$path);
    rel_no(fn()=>studio_relevance_require($saved,$source,'radio'),'override never repairs missing source facts');
    studio_board_update($saved['id'],$saved['revision'],'archive',[],$admin,$path);
    studio_board_add_source($feed,$admin,$path);
    rel_ok(count(studio_board_read($path)['items'])===1,'archived canonical source cannot return via other intake');
    studio_board_change(static function(&$b){$b['newsroomSettings']=['enabled'=>true,'dailyLimit'=>8];},$path);
    $feed['id']='unrelated';$feed['url']='https://www.nrk.no/vestland/bergen-1.23456';
    $fetch=fn($url)=>'<link rel="canonical" href="'.$url.'"><article><p>'.str_repeat('Bergen har en restaurant ved kysten. ',5).'</p></article>';
    $assessmentCalls=0;
    $onlyAssess=function($c,$p)use(&$assessmentCalls){
        rel_ok(($p['text']['format']['name']??'')==='rss_relevance','rejected story only invokes selection, never a writer');
        $assessmentCalls++;return relevance_fixture_response(['locality'=>'none','localEvidence'=>[],'impactEvidence'=>[]]);
    };
    $result=studio_newsroom_tick([['status'=>'updated','items'=>[$feed]]],$config,$path,$onlyAssess,$fetch);
    rel_ok($result['recommendation']==='rejected'&&count(studio_board_read($path)['items'])===1,'rejected item stays in inbox outside production capacity');
    studio_newsroom_tick([['status'=>'updated','items'=>[$feed]]],$config,$path,$onlyAssess,$fetch);
    rel_ok($assessmentCalls===1,'unchanged rejection is not repeatedly assessed or produced');
}finally{foreach(glob($dir.'/*')as$f)unlink($f);rmdir($dir);}
echo "RSS relevance boundaries OK (synthetic fixtures, no real model, publication or TTS).\n";
