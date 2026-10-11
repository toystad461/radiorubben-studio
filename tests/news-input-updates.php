<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/helpers.php';
require dirname(__DIR__).'/app/news-input.php';
function check(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS updates: $label\n";}
function blocked(callable $call,string $label):void{try{$call();}catch(InvalidArgumentException $e){check(true,$label);return;}throw new RuntimeException($label);}
$example=studio_input_demo();$board=$example['board'];$sources=studio_input_sources($example['feeds']);$source=reset($sources);
$old=$board['rssIntakeReviews'][studio_source_identity($source['url'])];
check(studio_input_update($board,$source)['state']==='changed','changed RSS detected against human snapshot');
check(count(studio_input_update($board,$source)['changes'])===1,'actual text difference shown');
check(count(studio_input_visible_sources($sources,$board,'updates'))===1,'changed source appears in updates');
$previous=array_replace($source,['rssText'=>$old['rssText']]);
check(studio_input_update($board,$previous)['state']==='unchanged','unchanged content is not an update');
check(studio_input_update($board,array_replace($previous,['fetchedAt'=>gmdate('c',time()+10)]))['state']==='unchanged','refresh alone does not trigger update');
check(studio_input_update($board,array_replace($source,['feedStatus'=>'stale']))['state']==='unverified','failed feed never declares unchanged');
check(studio_input_update($board,array_replace($source,['rssFeed'=>'nrk_vestland_toppsaker']))['state']==='unverified','different NRK feed is not an update');
$missing=studio_input_watch_sources([],$board,$example['feeds']);
check(count($missing)===1&&!empty(reset($missing)['missingFromFeed']),'missing watched source retains last snapshot');
check(studio_input_update($board,reset($missing))['state']==='unverified','missing source needs manual attention');
$feeds=$example['feeds'];$feeds['nrk_vestland_toppsaker']['items']=[array_replace($source,['rssText'=>'Different top story excerpt','rssFeed'=>'nrk_vestland_toppsaker'])];
$monitored=studio_input_watch_sources(studio_input_sources($feeds),$board,$feeds);
check(reset($monitored)['rssFeed']===$old['rssFeed']&&studio_input_update($board,reset($monitored))['changes'][0]['after']===$source['rssText'],'combined view uses original reviewed feed');
$related=studio_input_related($source,$example['editorial']);
check(count($related)===1&&$related[0]['published'],'existing Studio delivery linked by source');
check(!studio_input_related(array_replace($source,['id'=>'other','url'=>'https://www.nrk.no/vestland/other-1.87654321']),$example['editorial']),'unrelated case never matched by title');
check(studio_input_suggestion($source)['label']==='Aktuell å vurdere','local text gives advisory relevance');
check(studio_input_suggestion(array_replace($source,['rssTitle'=>'Annen nyhet','rssText'=>'Uten lokalt treff']))['label']==='Usikker relevans','absence of local match does not discard');
$path=tempnam(sys_get_temp_dir(),'rr-update-');file_put_contents($path,json_encode($board));
$user=['id'=>'synthetic','name'=>'Testredaktør','role'=>'producer'];
$post=['action'=>'draft_update','csrf'=>'test','source'=>$source['id'],'sourceVersion'=>studio_input_version($source),
    'reviewRevision'=>'1','channel'=>'both','reason'=>'Oppdateringen endrer tidspunktet og må vurderes.',
    'updateDraft'=>'Syntetisk oppdateringsutkast som må kontrolleres før bruk.'];
try{
    blocked(fn()=>studio_input_save($source,$post,array_replace($user,['role'=>'observer']),'test',$path),'observer cannot draft update');
    blocked(fn()=>studio_input_save($source,array_replace($post,['csrf'=>'wrong']),$user,'test',$path),'draft CSRF enforced');
    blocked(fn()=>studio_input_save(array_replace($source,['rssText'=>'Newer facts']),$post,$user,'test',$path),'changed source blocks stale draft');
    blocked(fn()=>studio_input_save($source,array_replace($post,['reviewRevision'=>'0']),$user,'test',$path),'draft revision enforced');
    blocked(fn()=>studio_input_save($source,array_replace($post,['updateDraft'=>'<b>Unsafe draft</b>']),$user,'test',$path),'HTML draft rejected');
    blocked(fn()=>studio_input_save($source,array_replace($post,['action'=>'publish']),$user,'test',$path),'publication not an intake action');
    blocked(fn()=>studio_input_save(reset($missing),array_replace($post,['sourceVersion'=>studio_input_version(reset($missing))]),$user,'test',$path),'missing feed cannot be accepted');
    check(studio_board_read($path)===$board,'negative checks preserve snapshots');
    $saved=studio_input_save($source,$post,$user,'test',$path);$after=studio_board_read($path);
    check($saved['updateDecision']==='draft'&&$saved['updateBaseVersion']===$old['sourceVersion']&&$saved['updateDraft']===$post['updateDraft'],'draft bound to old and new source versions');
    check($after['items']===$board['items']&&$saved['history'][0]['rssText']===$old['rssText'],'original and editorial state preserved');
    check(count(studio_input_visible_sources($sources,$after,'updates'))===1,'pending draft remains in updates after assessment');
    $saved=studio_input_save($source,array_replace($post,['action'=>'review','reviewRevision'=>'2']),$user,'test',$path);
    check($saved['updateDraft']===$post['updateDraft'],'ordinary review retains pending draft');
    $saved=studio_input_save($source,array_replace($post,['action'=>'keep_published','reviewRevision'=>'3']),$user,'test',$path);
    check($saved['updateDecision']==='keep'&&count($saved['history'])===3&&studio_input_visible_sources($sources,studio_board_read($path),'updates')===[],'keep decision logged without publication');
}finally{unlink($path);if(is_file($path.'.lock'))unlink($path.'.lock');}
// Render the real update view as an isolated, read-only browser fixture.
$feeds=$example['feeds'];$board=$example['board'];$filter='all';$view='updates';$demo=true;$can=true;
$update=studio_input_update($board,$source);$suggestion=studio_input_suggestion($source);$review=null;$error=$notice=$editorialError=null;
$_SESSION=['csrf'=>'synthetic'];
function input_time(?string $at):string{return $at??'Ukjent';}
function input_url(string $feed,string $source=''):string{return '?'.http_build_query(['feed'=>$feed,'source'=>$source,'demo'=>'updates']);}
ob_start();require dirname(__DIR__).'/app/views/news-input.php';$html=(string)ob_get_clean();
check(str_contains($html,'Sist vurdert')&&str_contains($html,'Nå i RSS')&&str_contains($html,'klokken 10.')&&str_contains($html,'klokken 12.'),'before and after visible together');
check(!str_contains($html,'name="action"')&&!str_contains($html,'/case.php?item='),'live example has no mutable actions');
$out=getenv('RR_UPDATE_PREVIEW');if($out)file_put_contents($out,'<!doctype html><html lang="nb"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="../public/assets/studio.css"><link rel="stylesheet" href="../public/assets/theme-bridge.css"><link rel="stylesheet" href="../public/assets/news-input.css"><body>'.$html.'</body></html>');
