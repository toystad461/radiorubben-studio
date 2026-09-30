<?php
declare(strict_types=1);
require dirname(__DIR__).'/studio-private/app/bootstrap.php';
$user=current_user(); if(!$user) redirect('/login.php');
require dirname(__DIR__).'/studio-private/app/case-workflow.php';
require dirname(__DIR__).'/studio-private/app/editorial-memory.php';
require dirname(__DIR__).'/studio-private/app/producer.php';
if(!in_array($_SERVER['REQUEST_METHOD'],['GET','POST'],true)){http_response_code(405);exit;}
$id=(string)($_POST['id']??$_GET['item']??'');
if(!preg_match('/^[a-f0-9]{16}$/D',$id)){http_response_code(404);exit('Velg en sak fra sendelisten.');}
$can=studio_can($user,'produce'); $admin=($user['role']??'')==='admin'; $wp=studio_wp_config();
$error=null;
try {$item=studio_case_get($id);} catch(Throwable $e){http_response_code(404);exit('Saken er ikke tilgjengelig.');}
if($_SERVER['REQUEST_METHOD']==='POST') {
    if(!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf'],$_POST['csrf'])||!$can){http_response_code(403);exit('Ingen tilgang.');}
    try {
        $rev=(int)($_POST['revision']??0); if($rev!==$item['revision']) throw new InvalidArgumentException('Saken ble endret. Last siden på nytt.');
        $action=(string)($_POST['action']??'');
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
        elseif(in_array($action,['wp_draft','wp_publish'],true)) studio_web_publish($id,$rev,$action==='wp_publish'?'publish':'draft',$user,$wp);
        else throw new InvalidArgumentException('Ukjent handling.');
        redirect('/case.php?item='.$id);
    } catch(InvalidArgumentException $e){$error=$e->getMessage();}
    catch(Throwable $e){$error='Handlingen kunne ikke fullføres. Ingen ny godkjenning er gitt.';error_log('Studio case operation failed.');}
    $item=studio_case_get($id);
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
$extraStylesheet='/assets/control.css?v=2'; require dirname(__DIR__).'/studio-private/app/views/head.php';
?>
<div class="shell"><?php $activePage='newsdesk';require dirname(__DIR__).'/studio-private/app/views/sidebar.php';?><div class="workspace"><main id="main" class="control-page">
<div class="control-heading"><div><p class="eyebrow">INNKOMMET → KLARGJØR → KONTROLLER → BRUK</p><h1><?=escape($item['title'])?></h1><p><?=escape($item['sourceName'])?> · <a href="<?=escape($item['sourceUrl'])?>" target="_blank" rel="noopener noreferrer">Les originalen</a></p></div><a href="/sending.php">Til sendelisten</a></div>
<?php if($error):?><p class="control-alert error" role="alert"><?=escape($error)?></p><?php endif;?>
<p>Arbeid med radio og nettside her. De har hver sin kontroll og godkjenning. Bokmål brukes i begge utkast.</p>
<div class="sending-layout">
<section class="control-panel"><h2>Radio</h2><p>Status: <?= $item['status']==='ready'?'Klar til sending':(studio_news_check_current($item)?'Kontroll bestått – les og godkjenn':'Utkast / trenger kontroll')?></p>
<?php if($can):?><form method="post"><?php case_fields($item);?><button name="action" value="prepare_radio">Klargjør radiomanus</button></form><form method="post" class="editor-form"><?php case_fields($item);?><label for="radio-script">Manus</label><textarea id="radio-script" name="script" rows="12" maxlength="5000"><?=escape($item['script'])?></textarea><button name="action" value="save_radio">Lagre rettelser</button></form><form method="post"><?php case_fields($item);?><button name="action" value="check_radio">Kontroller lagret manus</button></form><?php else:?><p><?=nl2br(escape($item['script']))?></p><?php endif;?>
<?php case_report($item['sourceCheck']??[]);?>
<?php if($can && studio_news_check_current($item)):?><form method="post"><?php case_fields($item);?><label><input type="checkbox" name="confirmed" value="1" required> Jeg har lest manuset og kontrollert opplysningene</label><button name="action" value="ready_radio">Legg klart til sending</button></form><?php endif;?>
<a href="/learning.php?item=<?=escape($id)?>">Lær av godkjent rettelse</a></section>
<section class="control-panel"><h2>Nettside</h2><p>Status: <?=studio_web_checked($item)?'Kontroll bestått':'Utkast / trenger kontroll'?></p>
<?php if($can):?><form method="post"><?php case_fields($item);?><button name="action" value="prepare_web">Klargjør nettsak</button></form>
<form method="post" class="editor-form"><?php case_fields($item);?><label for="web-title">Overskrift</label><input id="web-title" name="title" maxlength="180" value="<?=escape($w['title']??'')?>" required><label for="web-intro">Ingress</label><textarea id="web-intro" name="intro" maxlength="500" required><?=escape($w['intro']??'')?></textarea><label for="web-body">Artikkeltekst</label><textarea id="web-body" name="body" rows="14" maxlength="5000" required><?=escape($w['body']??'')?></textarea><button name="action" value="save_web">Lagre rettelser</button></form>
<form method="post"><?php case_fields($item);?><button name="action" value="check_web">Kontroller lagret nettsak</button></form><?php else:?><h3><?=escape($w['title']??'')?></h3><p><?=nl2br(escape(studio_web_text($w)))?></p><?php endif;?>
<?php case_report($w['check']??[]);?>
<?php if($admin && studio_web_checked($item)):?><form method="post"><?php case_fields($item);?><label><input type="checkbox" name="confirmed" value="1" required> Jeg har lest nettsaken og godkjenner den for publisering</label><button name="action" value="approve_web">Godkjenn nettsak</button></form><?php endif;?>
<?php if(!studio_wp_ready($wp)):?><p>WordPress-koblingen venter på serveroppsett. Utkast kan klargjøres og kontrolleres.</p><?php elseif($admin):?><form method="post"><?php case_fields($item);?><button name="action" value="wp_draft">Send som kladd til WordPress</button><?php if(studio_web_checked($item) && ($w['approvedHash']??'')===hash('sha256',studio_web_text($w))):?><button name="action" value="wp_publish">Publiser godkjent nettsak</button><?php endif;?></form><?php endif;?>
<?php if(isset($w['delivery'])):?><p>WordPress: <?=escape($w['delivery']['state'])?> <?=escape($w['delivery']['status']??'')?></p><?php if(!empty($w['delivery']['link'])):?><a href="<?=escape($w['delivery']['link'])?>" target="_blank" rel="noopener noreferrer">Åpne i WordPress/nettsiden</a><?php endif;endif;?>
</section></div></main></div></div></body></html>
