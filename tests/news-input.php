<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/helpers.php';
require dirname(__DIR__).'/app/news-input.php';
function expect(bool $ok,string $label):void {if(!$ok)throw new RuntimeException($label);echo "PASS: $label\n";}
function reject(callable $call,string $label):void {try{$call();}catch(InvalidArgumentException $e){expect(true,$label);return;}throw new RuntimeException($label);}
$path=tempnam(sys_get_temp_dir(),'rr-input-');$user=['id'=>'synthetic-editor','name'=>'Testredaktør','role'=>'producer'];
$long=str_repeat('Dette er syntetiske opplysninger for testen. ',30);
$spec=newsdesk_sources()['nrk_vestland_siste'];
$xml='<rss version="2.0"><channel><item><title>Test fra Bømlo</title><link>https://www.nrk.no/vestland/test-1.12345678</link><description>'.htmlspecialchars($long,ENT_XML1).'</description><pubDate>Sun, 11 Oct 2026 08:00:00 GMT</pubDate></item></channel></rss>';
$source=newsdesk_parse_rss($xml,'nrk_vestland_siste',$spec,'2026-10-11T09:00:00Z')[0];
expect($source['rssText']===trim($long)&&mb_strlen($source['summary'])===360,'full RSS text kept separately from preview');
expect(newsdesk_decode_rss('<!DOCTYPE rss [<!ENTITY x SYSTEM "file:///private">]>'.$xml,'nrk_vestland_siste',$spec,'2026-10-11T09:00:00Z')===null,'DTD and external entities rejected');
$feeds=['nrk_vestland_siste'=>['status'=>'updated','items'=>[$source],'fetchedAt'=>$source['fetchedAt']],
    'nrk_vestland_toppsaker'=>['status'=>'stale','items'=>[$source],'fetchedAt'=>$source['fetchedAt']]];
expect(count(studio_input_sources($feeds))===1,'same NRK source deduplicated across feeds');
expect(count(studio_input_sources($feeds,'nrk_vestland_siste'))===1&&count(studio_input_sources($feeds,'nrk_vestland_toppsaker'))===1,'feed membership retained');
$different=$feeds;$different['nrk_vestland_toppsaker']['status']='updated';$different['nrk_vestland_toppsaker']['items'][0]['rssText']='';
expect(array_values(studio_input_sources($different))[0]['rssText']===trim($long),'combined view keeps the richer equally fresh RSS copy');
expect(array_values(studio_input_sources($different,'nrk_vestland_toppsaker'))[0]['rssText']==='','top feed view preserves its own empty description');
$item=['id'=>'synthetic-case','title'=>'Egen test','script'=>'Manuelt manus','channel'=>'both','revision'=>7,'approvedBy'=>'Test','status'=>'draft','audioScripts'=>['test'=>['scriptApproval'=>['actor'=>'Test'],'audio'=>['approval'=>['actor'=>'Test']]]]];
$board=['items'=>[$item],'newsSelections'=>['synthetic'=>['assessment'=>['reason'=>'Existing judgement']]]];
file_put_contents($path,json_encode($board));
$post=['csrf'=>'test-csrf','action'=>'review','source'=>$source['id'],'sourceVersion'=>studio_input_version($source),'reviewRevision'=>'0','channel'=>'web','reason'=>'Saken har betydning for publikum på Bømlo.'];
try {
    reject(fn()=>studio_input_save($source,$post,array_replace($user,['role'=>'observer']),'test-csrf',$path),'observer cannot change judgement');
    reject(fn()=>studio_input_save($source,array_replace($post,['csrf'=>'wrong']),$user,'test-csrf',$path),'invalid CSRF rejected');
    reject(fn()=>studio_input_save($source,array_replace($post,['action'=>'approve']),$user,'test-csrf',$path),'approval action not accepted');
    reject(fn()=>studio_input_save($source,array_replace($post,['channel'=>'publish']),$user,'test-csrf',$path),'invalid channel rejected');
    reject(fn()=>studio_input_save($source,array_replace($post,['reason'=>'Kort']),$user,'test-csrf',$path),'concrete reason required');
    reject(fn()=>studio_input_save($source,array_replace($post,['source'=>'other']),$user,'test-csrf',$path),'forged source rejected');
    $changed=array_replace($source,['rssText'=>'Nye syntetiske fakta']);
    reject(fn()=>studio_input_save($changed,$post,$user,'test-csrf',$path),'changed source blocks old form');
    expect(studio_board_read($path)===$board,'rejected requests leave board intact');
    $saved=studio_input_save($source,$post,$user,'test-csrf',$path);
    $after=studio_board_read($path);
    expect($after['items']===$board['items']&&$after['newsSelections']===$board['newsSelections'],'case, approvals, audio and existing relevance untouched');
    expect($saved['sourceVersion']===studio_input_version($source)&&$saved['actorId']==='synthetic-editor','source and editor provenance recorded');
    expect(studio_input_review($after,$changed)===null,'changed RSS invalidates current judgement');
    expect(studio_input_review($after,array_replace($source,['fetchedAt'=>'2026-10-11T10:00:00Z']))!==null,'same content refresh does not invalidate judgement');
    reject(fn()=>studio_input_save($source,$post,$user,'test-csrf',$path),'stale review revision rejected');
    $second=studio_input_save($source,array_replace($post,['reviewRevision'=>'1','channel'=>'skip']),$user,'test-csrf',$path);
    expect($second['revision']===2&&count($second['history'])===1,'judgement history retained');
    $legacy=$source;unset($legacy['rssText'],$legacy['rssTitle']);
    expect(studio_input_version($legacy)!==studio_input_version($source),'legacy short cache is a distinct source version');
    // Real PHP view, synthetic board/source only; browser QA consumes this static output.
    $sources=[];for($i=0;$i<40;$i++){$row=$source;$row['id']=hash('sha256',(string)$i);$row['title']=$row['rssTitle']='Syntetisk nyhet '.$i;$sources[$row['id']]=$row;}
    $source=reset($sources);$review=null;$old=null;$can=true;$error=$notice=null;$filter='all';$_SESSION=['csrf'=>'synthetic-csrf'];
    function input_url(string $feed,string $source=''):string{return '?'.http_build_query(['feed'=>$feed,'source'=>$source]);}
    function input_time(?string $at):string{return $at??'Ukjent';}
    ob_start();require dirname(__DIR__).'/app/views/news-input.php';$html=ob_get_clean();
    $out=getenv('RR_INPUT_PREVIEW');if($out)file_put_contents($out,'<!doctype html><html lang="nb"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="../public/assets/news-input.css"><body>'.$html.'</body></html>');
    expect(!str_contains($html,'name="action" value="approve"')&&!str_contains($html,'name="action" value="open_source"'),'unassessed intake exposes neither approval nor further generation');
    expect(str_contains($html,escape($source['rssText'])),'RSS text rendered without AI rewrite');
}finally{if(is_file($path))unlink($path);if(is_file($path.'.lock'))unlink($path.'.lock');}
