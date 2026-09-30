<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$user=current_user(); if(!$user) redirect('/login.php');
require dirname(__DIR__).'/app/case-workflow.php';
require dirname(__DIR__).'/app/editorial-memory.php';
require dirname(__DIR__).'/app/producer.php';
if(!in_array($_SERVER['REQUEST_METHOD'],['GET','POST'],true)){http_response_code(405);exit;}
$json=($_POST['response']??'')==='json';
$autoPrepare=false;
$can=studio_can($user,'produce');
$inboxError=null;
require dirname(__DIR__).'/app/integrations/NewsDesk.php';
if(($_POST['action']??'')==='open_source'){
    if(!$can || !is_string($_POST['csrf']??null) || !hash_equals($_SESSION['csrf'],$_POST['csrf'])){http_response_code(403);exit;}
    try {
        $feeds=newsdesk_all(dirname(__DIR__).'/config');$source=null;
        foreach($feeds as $feed)foreach($feed['items'] as $row)if($row['id']===($_POST['source']??''))$source=$row;
        if(!$source)throw new InvalidArgumentException('Kilden er ikke lenger i innboksen.');
        studio_board_add_source($source,$user);
        foreach(studio_board_active(studio_board_read()) as $row)if(($row['originId']??'')===$source['id']){
            $_GET['item']=$row['id'];$autoPrepare=empty($row['script'])&&empty($row['web']['body']);break;
        }
    }catch(Throwable $e){$inboxError='Saken kunne ikke åpnes. Sendelisten kan være full eller kilden utilgjengelig.';}
    $_POST=[];
}
$id=(string)($_POST['id']??$_GET['item']??'');
if($id!=='' && !preg_match('/^[a-f0-9]{16}$/D',$id)){http_response_code(404);exit('Velg en sak fra sendelisten.');}
$can=studio_can($user,'produce'); $admin=($user['role']??'')==='admin'; $wp=studio_wp_config();
$error=$inboxError;$item=null;
try {if($id!=='')$item=studio_case_get($id);} catch(Throwable $e){http_response_code(404);exit('Saken er ikke tilgjengelig.');}
if($_SERVER['REQUEST_METHOD']==='POST' && !empty($_POST)) {
    if(!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf'],$_POST['csrf'])||!$can){http_response_code(403);exit('Ingen tilgang.');}
    try {
        $rev=(int)($_POST['revision']??0); if($rev!==$item['revision']) throw new InvalidArgumentException('Saken ble endret. Last siden på nytt.');
        $action=(string)($_POST['action']??'');
        if(!$item)throw new InvalidArgumentException('Velg en sak først.');
        if($action==='prepare_radio') {
            studio_board_update($id,$rev,'source_checked',['sourceCheck'=>['status'=>'checking']],$user);
            $editorial=studio_memory_context(studio_board_read(),(string)($item['program']??''));
            $r=studio_news_prepare($item,$config,$editorial);
            studio_board_update($id,$rev+1,'generated',['script'=>$r['script'],'sourceCheck'=>$r['check'],'generation'=>['model'=>$config['openai_model'],'editorial'=>$editorial]],$user);
        } elseif($action==='save_radio') {
            studio_board_update($id,$rev,'save',['title'=>$item['title'],'script'=>(string)($_POST['script']??''),'notes'=>$item['notes'],'program'=>$item['program']??''],$user);
        } elseif($action==='check_radio') {
            studio_board_update($id,$rev,'source_checked',['sourceCheck'=>['status'=>'checking']],$user);
            $r=studio_news_prepare($item,$config,[],(string)$item['script']);
            studio_board_update($id,$rev+1,'source_checked',['sourceCheck'=>$r['check']],$user);
        } elseif($action==='ready_radio') {
            if(($_POST['confirmed']??'')!=='1') throw new InvalidArgumentException('Bekreft at du har lest og kontrollert manuset.');
            studio_board_update($id,$rev,'save',['title'=>$item['title'],'script'=>$item['script'],'notes'=>$item['notes'],'program'=>$item['program']??'','verified'=>'1'],$user);
            studio_board_update($id,$rev+1,'ready',[],$user);
        } elseif($action==='prepare_web') {
            studio_web_save($id,$rev,'invalidate',[],$user);
            $w=studio_web_prepare($item,$config,studio_memory_context(studio_board_read(),(string)($item['program']??'')));
            studio_web_save($id,$rev+1,'generated',$w,$user);
        } elseif($action==='save_web') studio_web_save($id,$rev,'save',$_POST,$user);
        elseif($action==='check_web') {
            studio_web_save($id,$rev,'invalidate',[],$user);
            $source=studio_news_source($item);
            $check=studio_news_review($item,studio_web_text($item['web']??[]),$source,$config,'producer_request');
            studio_web_save($id,$rev+1,'check',['check'=>$check],$user);
        } elseif($action==='approve_web') studio_web_save($id,$rev,'approve',$_POST,$user);
        elseif($action==='approve_publish') {
            if(($user['role']??'')!=='admin' || ($_POST['confirmed']??'')!=='1')throw new InvalidArgumentException('Administrator må lese og godkjenne nettsaken.');
            if(!studio_wp_ready($wp))throw new InvalidArgumentException('WordPress-tilgang mangler.');
            studio_web_save($id,$rev,'approve',$_POST,$user);
            studio_web_publish($id,$rev+1,'publish',$user,$wp);
        }
        elseif(in_array($action,['wp_draft','wp_publish'],true)) studio_web_publish($id,$rev,$action==='wp_publish'?'publish':'draft',$user,$wp);
        else throw new InvalidArgumentException('Ukjent handling.');
        if(!$json)redirect('/case.php?item='.$id);
    } catch(InvalidArgumentException $e){$error=$e->getMessage();}
    catch(Throwable $e){$error='Handlingen kunne ikke fullføres. Ingen ny godkjenning er gitt.';error_log('Studio case operation failed.');}
    $item=studio_case_get($id);
}
if($json){
    header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
    if($error)http_response_code(409);
    echo json_encode(['ok'=>!$error,'error'=>$error,'revision'=>$item['revision']??0,'radioChecked'=>$item?studio_news_check_current($item):false,'webChecked'=>$item?studio_web_checked($item):false],JSON_UNESCAPED_UNICODE);exit;
}
$w=$item['web']??[];
// Preserve the editor's submitted text on a failed save. It is still unapproved.
if($error && ($_POST['action']??'')==='save_web') foreach(['title','intro','body'] as $field) if(is_string($_POST[$field]??null)) $w[$field]=substr($_POST[$field],0,20000);
if($error && ($_POST['action']??'')==='save_radio' && is_string($_POST['script']??null)) $item['script']=substr($_POST['script'],0,20000);
function case_fields(array $item): void { ?><input type="hidden" name="csrf" value="<?=escape($_SESSION['csrf'])?>"><input type="hidden" name="id" value="<?=escape($item['id'])?>"><input type="hidden" name="revision" value="<?=(int)$item['revision']?>"><?php }
function case_report(array $check): void {
    $problems=$check['issues']??[];
    foreach($check['segments']??[] as $seg) if(($seg['verdict']??'')!=='supported') $problems[]=($seg['text']??'').': '.($seg['reason']??'');
    if($problems){echo '<ul>';foreach($problems as $problem)echo '<li>'.escape($problem).'</li>';echo '</ul>';}
    if(!empty($check['source'])){?><details><summary>Vis kildegrunnlag og kontroll</summary><p><?=nl2br(escape($check['source']['text']))?></p><p>Hentet <?=escape($check['source']['fetchedAt'])?></p></details><?php }
}
$items=studio_board_active(studio_board_read());
$feeds=newsdesk_all(dirname(__DIR__).'/config');
$extraStylesheet='/assets/control.css?v=2'; require dirname(__DIR__).'/app/views/head.php';
?>
<style>
.case-workspace .sending-layout{grid-template-columns:repeat(2,minmax(0,1fr));align-items:start}
.case-toolbar{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin:16px 0}
.case-toolbar select{max-width:100%;min-width:240px}.case-progress{padding:16px;border-left:4px solid #24a7df;background:#102635;margin:12px 0}
.case-inbox{max-height:340px;overflow:auto}.case-inbox article{border-bottom:1px solid #345;padding:12px 0}
.case-inbox h3{font-size:1rem;margin:4px 0}.case-workspace textarea{width:100%;box-sizing:border-box}
.case-workspace [hidden]{display:none!important}.case-final{border-top:1px solid #345;padding-top:16px;margin-top:16px}
@media(max-width:900px){.case-workspace .sending-layout{grid-template-columns:1fr}}
</style>
<div class="shell"><?php $activePage='newsdesk';require dirname(__DIR__).'/app/views/sidebar.php';?><div class="workspace"><main id="main" class="control-page case-workspace">
<header><p class="eyebrow">RADIO RUBBEN / REDAKSJON</p><h1>Fra sak til sending og nettside</h1><p>Robåt klargjør og kontrollerer. Du leser, retter ved behov og sluttgodkjenner her.</p></header>
<div class="case-toolbar"><form method="get" action="/case.php"><label for="case-picker">Saker under arbeid</label><select id="case-picker" name="item"><option value="">Velg sak</option><?php foreach($items as $row):?><option value="<?=escape($row['id'])?>" <?=$id===$row['id']?'selected':''?>><?=escape($row['title'])?></option><?php endforeach;?></select><button>Åpne</button></form><a href="/sending.php">Rekkefølge i sendelisten</a></div>
<details <?=!$item?'open':''?>><summary>Innkomne saker – velg og klargjør automatisk</summary><div class="case-inbox"><?php foreach($feeds as $feed):foreach($feed['items'] as $source):?><article><h3><?=escape($source['title'])?></h3><p><?=escape($source['sourceName'])?> · <?=escape($source['summary'])?></p><?php if($can):?><form method="post" action="/case.php"><input type="hidden" name="csrf" value="<?=escape($_SESSION['csrf'])?>"><input type="hidden" name="source" value="<?=escape($source['id'])?>"><button name="action" value="open_source">Velg og klargjør</button></form><?php endif;?></article><?php endforeach;endforeach;?></div></details>
<?php if($error):?><p class="control-alert error" role="alert"><?=escape($error)?></p><?php endif;?>
<?php if($item):?>
<h2><?=escape($item['title'])?></h2><p><?=escape($item['sourceName'])?> · <a href="<?=escape($item['sourceUrl'])?>" target="_blank" rel="noopener noreferrer">Åpne originalkilden</a></p>
<?php if($can):?><div class="case-toolbar"><button type="button" id="prepare-all">Klargjør radio og nett automatisk</button><span>Henter original, lager utkast og kjører kilde- og språkkontroll. Publiserer ingenting.</span></div><?php endif;?>
<p id="case-progress" class="case-progress" role="status" aria-live="polite">Les utkastene nedenfor. Avvik vises ved teksten. Endringer må lagres og kontrolleres før sluttgodkjenning.</p>
<div class="sending-layout">
<section class="control-panel"><h2>Radio</h2><p>Status: <?=$item['status']==='ready'?'Klar til sending':(studio_news_check_current($item)?'Venter på din sluttgodkjenning':'Trenger kontroll / rettelser')?></p>
<?php if($can):?><form method="post" class="editor-form" data-save="radio"><?php case_fields($item);?><label for="radio-script">Manus til opplesning</label><textarea id="radio-script" name="script" rows="14" maxlength="5000"><?=escape($item['script'])?></textarea><button name="action" value="save_radio">Lagre og kontroller rettelser</button></form><?php else:?><p><?=nl2br(escape($item['script']))?></p><?php endif;?>
<?php case_report($item['sourceCheck']??[]);?>
<?php if($can && studio_news_check_current($item)):?><form method="post" class="case-final" data-final><?php case_fields($item);?><label><input type="checkbox" name="confirmed" value="1" required> Jeg har lest radiomanuset og godkjenner opplysningene</label><button name="action" value="ready_radio">Sluttgodkjenn til sending</button></form><?php endif;?>
<details><summary>Flere valg for radio</summary><?php if($can):foreach(['prepare_radio'=>'Lag radiomanus på nytt','check_radio'=>'Kontroller lagret manus på nytt'] as $action=>$label):?><form method="post" data-step="<?=$action?>"><?php case_fields($item);?><button name="action" value="<?=$action?>"><?=$label?></button></form><?php endforeach;endif;?><a href="/learning.php?item=<?=escape($id)?>">Lær av godkjent rettelse</a></details></section>
<section class="control-panel"><h2>Nettside</h2><p>Status: <?=studio_web_checked($item)?'Venter på din sluttgodkjenning':'Trenger kontroll / rettelser'?></p>
<?php if($can):?><form method="post" class="editor-form" data-save="web"><?php case_fields($item);?><label for="web-title">Overskrift</label><input id="web-title" name="title" maxlength="180" value="<?=escape($w['title']??'')?>" required><label for="web-intro">Ingress</label><textarea id="web-intro" name="intro" maxlength="500" required><?=escape($w['intro']??'')?></textarea><label for="web-body">Artikkeltekst</label><textarea id="web-body" name="body" rows="14" maxlength="5000" required><?=escape($w['body']??'')?></textarea><button name="action" value="save_web">Lagre og kontroller rettelser</button></form><?php else:?><h3><?=escape($w['title']??'')?></h3><p><?=nl2br(escape(studio_web_text($w)))?></p><?php endif;?>
<?php case_report($w['check']??[]);?>
<?php if($admin && studio_web_checked($item) && studio_wp_ready($wp) && !in_array($w['delivery']['state']??'', ['pending','unknown'],true)):?><form method="post" class="case-final" data-final><?php case_fields($item);?><label><input type="checkbox" name="confirmed" value="1" required> Jeg har lest nettsaken og godkjenner publisering på radiorubben.no</label><button name="action" value="approve_publish">Sluttgodkjenn og publiser</button></form><?php endif;?>
<?php if(!studio_wp_ready($wp)):?><p>WordPress-tilgang mangler. Du kan fortsatt klargjøre saken.</p><?php endif;?>
<?php if(isset($w['delivery'])):?><p>WordPress: <?=escape(['confirmed'=>'Overføring bekreftet','pending'=>'Overføring pågår','unknown'=>'Overføringen må avklares før nytt forsøk'][$w['delivery']['state']]??'Ikke overført')?></p><?php if(!empty($w['delivery']['link'])):?><a href="<?=escape($w['delivery']['link'])?>" target="_blank" rel="noopener noreferrer">Åpne på nettsiden</a><?php endif;endif;?>
<details><summary>Flere valg for nettsak</summary><?php if($can):foreach(['prepare_web'=>'Lag nettsak på nytt','check_web'=>'Kontroller lagret nettsak på nytt'] as $action=>$label):?><form method="post" data-step="<?=$action?>"><?php case_fields($item);?><button name="action" value="<?=$action?>"><?=$label?></button></form><?php endforeach;endif;?><?php if($admin && studio_wp_ready($wp) && !in_array($w['delivery']['state']??'', ['pending','unknown'],true)):?><form method="post"><?php case_fields($item);?><button name="action" value="wp_draft">Overfør bare som WordPress-kladd</button></form><?php endif;?></details>
</section></div>
<?php if($can):?><script>
(()=>{
const state={id:<?=json_encode($id)?>,revision:<?=(int)$item['revision']?>,csrf:<?=json_encode($_SESSION['csrf'])?>};
const progress=document.querySelector('#case-progress');let busy=false;const dirty=new Set();
const say=text=>{progress.textContent=text;};
const lock=value=>{busy=value;document.querySelectorAll('button,select,input[type=checkbox]').forEach(b=>b.disabled=value);document.querySelectorAll('textarea,input:not([type=hidden]):not([type=checkbox])').forEach(e=>e.readOnly=value);};
async function step(action,form){
 const data=form?new FormData(form):new FormData();
 for(const [k,v] of Object.entries({...state,action,response:'json'}))data.set(k,String(v));
 const res=await fetch('/case.php',{method:'POST',body:data,credentials:'same-origin'});
 let result;try{result=await res.json();}catch(e){throw new Error('Kontakten ble avbrutt eller innloggingen utløp. Last saken på nytt før du fortsetter. Ingen automatisk gjentakelse.');}
 if(!res.ok||!result.ok)throw new Error(result.error||'Handlingen kunne ikke fullføres.');
 state.revision=result.revision;document.querySelectorAll('input[name="revision"]').forEach(e=>e.value=state.revision);
 return result;
}
async function run(work){if(busy)return;lock(true);try{await work();busy=false;location.replace('/case.php?item='+encodeURIComponent(state.id));}catch(e){say(e.message+' Lagrede delresultater er bevart.');lock(false);document.querySelectorAll('[data-final] button').forEach(b=>b.disabled=true);}}
document.querySelector('#prepare-all').addEventListener('click',()=>{
 if(dirty.size){say('Lagre rettelsene før du lager nye utkast.');return;}
 run(async()=>{say('1 av 2: Henter originalen, lager radiomanus og kontrollerer språk og kilder …');await step('prepare_radio');say('2 av 2: Lager nettsak og kontrollerer språk og kilder …');await step('prepare_web');});
});
document.querySelectorAll('[data-save]').forEach(form=>{
 form.addEventListener('input',()=>{dirty.add(form);document.querySelectorAll('[data-final] button').forEach(b=>b.disabled=true);});
 form.addEventListener('submit',e=>{e.preventDefault();const kind=form.dataset.save;if([...dirty].some(f=>f!==form)){say('Du har rettelser i begge tekster. Bruk knappen nedenfor for å lagre og kontrollere begge.');document.querySelector('#save-both').hidden=false;return;}
 run(async()=>{say('Lagrer rettelser og kontrollerer mot originalkilden …');await step('save_'+kind,form);dirty.delete(form);await step('check_'+kind);});});
});
const saveBoth=document.createElement('button');saveBoth.id='save-both';saveBoth.type='button';saveBoth.hidden=true;saveBoth.textContent='Lagre og kontroller begge tekster';progress.after(saveBoth);
saveBoth.addEventListener('click',()=>run(async()=>{for(const form of [...dirty]){const kind=form.dataset.save;say('Lagrer og kontrollerer '+(kind==='radio'?'radiomanus':'nettsak')+' …');await step('save_'+kind,form);dirty.delete(form);await step('check_'+kind);}}));
document.querySelectorAll('[data-step]').forEach(form=>form.addEventListener('submit',e=>{e.preventDefault();if(dirty.size){say('Lagre rettelsene først.');return;}run(async()=>{say('Robåt arbeider med saken …');await step(form.dataset.step);});}));
document.querySelectorAll('[data-final]').forEach(form=>form.addEventListener('submit',e=>{if(busy||dirty.size){e.preventDefault();say('Lagre og kontroller rettelsene før sluttgodkjenning.');}}));
window.addEventListener('beforeunload',e=>{if(busy||dirty.size){e.preventDefault();e.returnValue='';}});
<?php if($autoPrepare):?>document.querySelector('#prepare-all').click();<?php endif;?>
})();
</script><noscript><p>Automatisk klargjøring krever JavaScript. Bruk «Flere valg» for trinnvis kontroll. Lagrede rettelser må kontrolleres før godkjenning.</p></noscript><?php endif;?>
<?php endif;?></main></div></div></body></html>
