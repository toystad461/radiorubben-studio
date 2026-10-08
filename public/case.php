<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$user=current_user(); if(!$user) redirect('/login.php');
require_once dirname(__DIR__).'/app/case-workflow.php';
require_once dirname(__DIR__).'/app/audio-processing.php';
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
        $row=studio_case_source_item(studio_board_active(studio_board_read()),$source);
        $_GET['item']=$row['id'];$autoPrepare=empty($row['script'])&&empty($row['web']['body']);
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
        if(str_starts_with($action,'pronunciation_')) {
            rr_pronunciation_change(substr($action,14),array_replace($_POST,['id'=>$_POST['entry']??'','revision'=>$_POST['entry_revision']??0]),$user);
        } elseif(str_starts_with($action,'audio_')) {
            $profile=(string)($_POST['profile']??'');rr_audio_profile($profile);$audio=$config['rr_audio']??[];$step=substr($action,6);
            if(in_array($step,['generate_script','check'],true)) {
                $script=$step==='check'?(string)($item['audioScripts'][$profile]['script']??''):null;
                $r=rr_audio_prepare($item,$profile,$config,studio_memory_context(studio_board_read(),(string)($item['program']??'')),$script);
                rr_audio_change($id,$rev,$step==='check'?'checked':'generated',['profile'=>$profile,'script'=>$r['script'],'check'=>$r['check'],'generation'=>$r['generation']],$user);
            } elseif(in_array($step,['save','approve_script'],true))rr_audio_change($id,$rev,$step,$_POST,$user);
            elseif($step==='tts')rr_audio_generate($id,$rev,$profile,(string)($_POST['voice']??''),$_POST,$user,$audio);
            elseif($step==='process')rr_audio_process($id,$rev,$profile,$user,$audio);
            elseif($step==='resolve')rr_audio_resolve($id,$rev,$profile,$_POST,$user);
            elseif(in_array($step,['approve_audio','enqueue'],true))rr_audio_approve_or_queue($id,$rev,$profile,$step,$_POST,$user,$audio);
            else throw new InvalidArgumentException('Ukjent lydhandling.');
        } elseif($action==='prepare_radio') {
            studio_board_update($id,$rev,'source_checked',['sourceCheck'=>['status'=>'checking']],$user);
            $editorial=studio_memory_context(studio_board_read(),(string)($item['program']??''));
            $r=studio_news_prepare($item,$config,$editorial);
            studio_board_update($id,$rev+1,'generated',['script'=>$r['script'],'sourceCheck'=>$r['check'],'generation'=>['model'=>$config['openai_model'],'editorial'=>$editorial,'journalist'=>$r['generation']??null]],$user);
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
$newsProfile=$w['publication']??($item && studio_web_is_news($item) && empty($w['delivery']['id']) ? studio_news_publication($item) : null);
// Preserve the editor's submitted text on a failed save. It is still unapproved.
if($error && ($_POST['action']??'')==='save_web') foreach(['title','intro','body'] as $field) if(is_string($_POST[$field]??null)) $w[$field]=substr($_POST[$field],0,20000);
if($error && ($_POST['action']??'')==='save_web' && $newsProfile && in_array($_POST['news_scope']??null,['news','local'],true)) $newsProfile['scope']=$_POST['news_scope'];
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
$extraStylesheet='/assets/case.css?v=20261004-1'; require dirname(__DIR__).'/app/views/head.php';
?>

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
<section class="control-panel" id="radio-material"><h2>Radio</h2><p>Status: <?=$item['status']==='ready'?'Klar til sending':(studio_news_check_current($item)?'Venter på din sluttgodkjenning':'Trenger kontroll / rettelser')?></p>
<?php if($can):?><form method="post" class="editor-form" data-save="radio"><?php case_fields($item);?><label for="radio-script">Manus til opplesning</label><textarea id="radio-script" name="script" rows="14" maxlength="5000"><?=escape($item['script'])?></textarea><button name="action" value="save_radio">Lagre og kontroller rettelser</button></form><?php else:?><p><?=nl2br(escape($item['script']))?></p><?php endif;?>
<?php case_report($item['sourceCheck']??[]);?>
<?php if($can && studio_news_check_current($item)):?><form method="post" class="case-final" data-final><?php case_fields($item);?><label><input type="checkbox" name="confirmed" value="1" required> Jeg har lest radiomanuset og godkjenner opplysningene</label><button name="action" value="ready_radio">Sluttgodkjenn til sending</button></form><?php endif;?>
<details><summary>Flere valg for radio</summary><?php if($can):foreach(['prepare_radio'=>'Lag radiomanus på nytt','check_radio'=>'Kontroller lagret manus på nytt'] as $action=>$label):?><form method="post" data-step="<?=$action?>"><?php case_fields($item);?><button name="action" value="<?=$action?>"><?=$label?></button></form><?php endforeach;endif;?><a href="/learning.php?item=<?=escape($id)?>">Lær av godkjent rettelse</a></details></section>
<section class="control-panel" id="web-material"><h2>Nettside</h2><p>Status: <?=escape(studio_web_status_label($item))?></p>
<?php if(!empty($w['delivery']['id'])):?><p>Bilde og kategorier beholdes fra WordPress. <a href="https://www.radiorubben.no/wp-admin/post.php?post=<?=(int)$w['delivery']['id']?>&amp;action=edit" target="_blank" rel="noopener noreferrer">Se eller endre i WordPress</a></p>
<?php elseif($newsProfile):?><figure class="case-news-image"><img src="/assets/radio-rubben-nyheter.png" alt="Radio Rubben Nyheter – mikrofon og nyhetsstudio" width="1024" height="576"><figcaption>Illustrasjon: Radio Rubben (KI-generert). Viser ikke den aktuelle hendelsen.</figcaption></figure><p>Kategorier: <?=($newsProfile['scope']??'')==='local'?'Nyheter og Lokale Nyheter':'Nyheter'?>. Bildet følger med når WordPress-innlegget opprettes.</p><?php endif;?>
<?php if($can):?><form method="post" id="web-editor" class="editor-form" data-save="web"><?php case_fields($item);?><label for="web-title">Overskrift</label><input id="web-title" name="title" maxlength="180" value="<?=escape($w['title']??'')?>" required><label for="web-intro">Ingress</label><textarea id="web-intro" name="intro" maxlength="500" required><?=escape($w['intro']??'')?></textarea><label for="web-body">Artikkeltekst</label><textarea id="web-body" name="body" rows="14" maxlength="5000" required><?=escape($w['body']??'')?></textarea><button name="action" value="save_web">Lagre og kontroller rettelser</button></form><?php else:?><h3><?=escape($w['title']??'')?></h3><p><?=nl2br(escape(studio_web_text($w)))?></p><?php endif;?>
<?php if($can && $newsProfile && empty($w['delivery']['id'])):?><div class="case-news-placement"><label for="news-scope">Plassering av nyhetssaken</label><select id="news-scope" name="news_scope" form="web-editor"><option value="news" <?=($newsProfile['scope']??'')==='news'?'selected':''?>>Nyheter</option><option value="local" <?=($newsProfile['scope']??'')==='local'?'selected':''?>>Nyheter og Lokale Nyheter</option></select><p>Velg lokal kategori når saken gjelder Bømlo. Lagre endringen før sluttgodkjenning.</p></div><?php endif;?>
<?php case_report($w['check']??[]);?>
<?php if($admin && studio_web_checked($item) && studio_web_presentation_ready($item) && studio_wp_ready($wp) && !in_array($w['delivery']['state']??'', ['pending','unknown'],true)):?><form method="post" class="case-final" data-final><?php case_fields($item);?><label><input type="checkbox" name="confirmed" value="1" required> Jeg har lest nettsaken og godkjenner publisering på radiorubben.no</label><button name="action" value="approve_publish">Sluttgodkjenn og publiser</button></form><?php endif;?>
<?php if(!studio_web_presentation_ready($item)):?><p>Lagre nettsaken for å knytte til nyhetsbildet og kategoriene før overføring eller godkjenning.</p><?php endif;?>
<?php if(!studio_wp_ready($wp)):?><p>WordPress-tilgang mangler. Du kan fortsatt klargjøre saken.</p><?php endif;?>
<?php if(isset($w['delivery'])):?><p>WordPress: <?=escape(['confirmed'=>'Overføring bekreftet','pending'=>'Overføring pågår','unknown'=>'Overføringen må avklares før nytt forsøk'][$w['delivery']['state']]??'Ikke overført')?></p><?php if(!empty($w['delivery']['link'])):?><a href="<?=escape($w['delivery']['link'])?>" target="_blank" rel="noopener noreferrer">Åpne på nettsiden</a><?php endif;endif;?>
<details><summary>Flere valg for nettsak</summary><?php if($can):foreach(['prepare_web'=>'Lag nettsak på nytt','check_web'=>'Kontroller lagret nettsak på nytt'] as $action=>$label):?><form method="post" data-step="<?=$action?>"><?php case_fields($item);?><button name="action" value="<?=$action?>"><?=$label?></button></form><?php endforeach;endif;?><?php if($admin && studio_wp_ready($wp) && !in_array($w['delivery']['state']??'', ['pending','unknown'],true)):?><form method="post"><?php case_fields($item);?><button name="action" value="wp_draft">Overfør bare som WordPress-kladd</button></form><?php endif;?></details>
</section></div>
<?php require dirname(__DIR__).'/app/views/case-audio.php';?>
<?php if($can):?><script id="case-controller" src="/assets/case.js?v=20261008-audio1" defer data-item="<?=escape($id)?>" data-revision="<?=(int)$item['revision']?>" data-csrf="<?=escape($_SESSION['csrf'])?>" data-auto-prepare="<?=$autoPrepare?'1':'0'?>"></script><noscript><p>Automatisk klargjøring krever JavaScript. Bruk «Flere valg» for trinnvis kontroll. Lagrede rettelser må kontrolleres før godkjenning.</p></noscript><?php endif;?>
<?php endif;?></main></div></div></body></html>
