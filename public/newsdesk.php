<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$user=current_user();if(!$user)redirect('/login.php');
if(!in_array($_SERVER['REQUEST_METHOD'],['GET','POST'],true)){http_response_code(405);header('Allow: GET, POST');exit;}
require_once dirname(__DIR__).'/app/newsroom.php';
require_once dirname(__DIR__).'/app/newsroom-wordpress.php';
require_once dirname(__DIR__).'/app/integrations/NewsDesk.php';
require_once dirname(__DIR__).'/app/producer.php';
$can=studio_can($user,'produce');$admin=($user['role']??'')==='admin';$error=null;$notice=null;
$selected=(string)($_POST['item']??$_GET['item']??'');
$feeds=newsdesk_all(dirname(__DIR__).'/config');$sections=newsdesk_sections($feeds);
$sources=[];foreach($sections as $section)foreach($section['items'] as $source)$sources[$source['id']]=$source;
$board=studio_board_read();
if($can&&is_array($_SESSION['newsdesk_rundown']??null)){
    foreach($_SESSION['newsdesk_rundown'] as $source)if(is_array($source)&&isset($source['id']))studio_board_add_source($source,$user);
    unset($_SESSION['newsdesk_rundown']);$board=studio_board_read();
}
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!$can||!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf'],$_POST['csrf'])){http_response_code(403);exit('Ingen tilgang. Last siden på nytt.');}
    try{
        $action=(string)($_POST['action']??'');$revision=(int)($_POST['revision']??0);
        if($action==='settings'){
            if(!$admin)throw new InvalidArgumentException('Bare administrator kan endre automatisk klargjøring.');
            $enabled=($_POST['enabled']??'')==='1';
            studio_board_change(static function(array &$b)use($enabled){$b['newsroomSettings']=['enabled'=>$enabled,'activatedAt'=>gmdate('c'),'dailyLimit'=>8];});
            $notice=$enabled?'Automatisk klargjøring er slått på. Du godkjenner publisering.':'Automatisk klargjøring er satt på pause.';
        }elseif($action==='open_source'){
            $source=$sources[(string)($_POST['source']??'')]??null;if(!$source)throw new InvalidArgumentException('Saken er ikke lenger i RSS-innboksen.');
            if(studio_board_has_source($board['items'],$source)){
                $item=studio_case_source_item($board['items'],$source);
                if($item['status']==='archived')throw new InvalidArgumentException('Denne saken er allerede forkastet eller arkivert.');
            }else{studio_board_add_source($source,$user);$item=studio_case_source_item(studio_board_active(studio_board_read()),$source);}
            $selected='studio:'.$item['id'];
            if(empty($item['web']['body']))studio_newsroom_prepare($item['id'],$item['revision'],$user,$config);
        }elseif(preg_match('/^wp:([1-9][0-9]*)$/D',$selected,$match)){
            if(!$admin)throw new InvalidArgumentException('Bare administrator kan behandle WordPress-saker.');
            if(!in_array($action,['approve','reject','revise'],true))throw new InvalidArgumentException('Ukjent handling.');
            if($action==='approve'&&($_POST['confirmed']??'')!=='1')throw new InvalidArgumentException('Bekreft at du har lest saken.');
            studio_newsroom_wp('POST',['id'=>(int)$match[1],'token'=>(string)($_POST['token']??''),'operation'=>$action,'comment'=>(string)($_POST['comment']??''),'editorial_facts'=>(string)($_POST['editorial_facts']??''),'actor'=>$user['name']??'Studio-redaktør']);
            $notice=$action==='approve'?'Publisering er bekreftet.':($action==='reject'?'Forslaget er forkastet.':'Saken er bearbeidet og krever ny godkjenning.');
        }elseif(preg_match('/^studio:([a-f0-9]{16})$/D',$selected,$match)){
            $id=$match[1];$item=studio_case_get($id);
            if($item['revision']!==$revision)throw new InvalidArgumentException('Saken er endret. Last siden på nytt og les siste versjon.');
            if($action==='approve'){
                if(!$admin||($_POST['confirmed']??'')!=='1')throw new InvalidArgumentException('Administrator må lese og bekrefte saken.');
                studio_web_save($id,$revision,'approve',['confirmed'=>'1'],$user);
                studio_web_publish($id,$revision+1,'publish',$user,studio_wp_config());$notice='Saken er publisert på radiorubben.no.';
            }elseif($action==='reject'){
                if(in_array($item['web']['delivery']['state']??'',['pending','unknown'],true)||($item['web']['delivery']['status']??'')==='publish')throw new InvalidArgumentException('Publisering må avklares før saken kan forkastes.');
                studio_board_update($id,$revision,'archive',[],$user);$selected='';$notice='Saken er forkastet og historikken er bevart.';
            }elseif($action==='revise'){
                $comment=trim((string)($_POST['comment']??''));if($comment==='')throw new InvalidArgumentException('Skriv hva du ønsker endret.');
                studio_newsroom_prepare($id,$revision,$user,$config,$comment);$notice='Nytt utkast og ny kontroll er klare.';
            }elseif($action==='prepare'||$action==='check')studio_newsroom_prepare($id,$revision,$user,$config,'',null,null,null,$action==='check');
            elseif($action==='save'){
                studio_web_save($id,$revision,'save',$_POST,$user);
                studio_newsroom_prepare($id,$revision+1,$user,$config,'',null,null,null,true);$notice='Rettelsene er lagret og kontrollert.';
            }else throw new InvalidArgumentException('Ukjent handling.');
        }else throw new InvalidArgumentException('Velg en gyldig sak.');
    }catch(InvalidArgumentException $e){$error=$e->getMessage();}
    catch(Throwable $e){$error='Handlingen kunne ikke bekreftes. Last siden på nytt før et nytt forsøk.';error_log('Studio newsroom operation failed.');}
    if(($_POST['response']??'')==='json'){
        header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');if($error)http_response_code(409);
        echo json_encode(['ok'=>!$error,'error'=>$error,'url'=>'/newsdesk.php'.($selected!==''?'?item='.rawurlencode($selected):'')],JSON_UNESCAPED_UNICODE);exit;
    }
    $board=studio_board_read();
}
$settings=studio_newsroom_settings($board);$cards=[];$studioItems=[];
foreach(studio_board_active($board) as $item)if(studio_web_is_news($item)){$key='studio:'.$item['id'];$cards[$key]=studio_newsroom_card($item);$studioItems[$key]=$item;}
$wpError=null;$wpStatus=[];
try{$wpStatus=studio_newsroom_wp();foreach($wpStatus['items'] as $card)$cards['wp:'.$card['id']]=$card;}
catch(Throwable $e){$wpError=$e instanceof InvalidArgumentException?$e->getMessage():'WordPress-køen er utilgjengelig.';}
$labels=['ready'=>'Til godkjenning','attention'=>'Trenger avklaring','working'=>'Under arbeid','published'=>'Publisert'];$counts=array_fill_keys(array_keys($labels),0);
foreach($cards as $card)if(isset($counts[$card['status']]))$counts[$card['status']]++;
$filter=(string)($_GET['filter']??'all');if($filter!=='all'&&!isset($labels[$filter]))$filter='all';
$visible=array_filter($cards,static fn($c)=>$filter==='all'||$c['status']===$filter);
uasort($visible,static fn($a,$b)=>array_search($a['status'],array_keys($labels))<=>array_search($b['status'],array_keys($labels)));
if($selected===''&&$visible)$selected=(string)array_key_first($visible);
$card=$cards[$selected]??null;$item=$studioItems[$selected]??null;
function newsroom_fields(string $key,array $card):void{?><input type="hidden" name="csrf" value="<?=escape($_SESSION['csrf'])?>"><input type="hidden" name="item" value="<?=escape($key)?>"><input type="hidden" name="revision" value="<?=(int)($card['revision']??0)?>"><input type="hidden" name="token" value="<?=escape($card['token']??'')?>"><?php }
function newsroom_time(?string $value):string{try{return $value?(new DateTimeImmutable($value))->setTimezone(new DateTimeZone('Europe/Oslo'))->format('d.m. H:i'):'Ikke kontrollert';}catch(Throwable $e){return 'Ukjent tidspunkt';}}
$extraStylesheet='/assets/newsroom.css?v=20261004-1';require dirname(__DIR__).'/app/views/head.php';
?>
<div class="shell"><?php $activePage='newsdesk';require dirname(__DIR__).'/app/views/sidebar.php';?><div class="workspace">
<header class="topbar"><span>Radio Rubben / <strong>Nyhetsdesk</strong></span><?php require dirname(__DIR__).'/app/views/account.php';?></header>
<main id="main" class="newsroom">
<header class="nr-heading"><div><p class="eyebrow">REDAKSJONEN</p><h1>Gode historier. Klare valg.</h1><p>Les ferdige saker, se hva som mangler og godkjenn på ett sted.</p></div><a class="nr-refresh" href="/newsdesk.php">Oppdater køen</a></header>
<?php if($error):?><p class="nr-alert" role="alert"><?=escape($error)?></p><?php endif;?>
<?php if($notice):?><p class="nr-notice" role="status"><?=escape($notice)?></p><?php endif;?>
<?php if($wpError):?><p class="nr-alert">WordPress: <?=escape($wpError)?> <a href="/wordpress-check.php">Se tilkoblingskontroll</a></p><?php endif;?>
<nav class="nr-counts" aria-label="Filtrer saker"><a href="/newsdesk.php" <?=$filter==='all'?'aria-current="page"':''?>><strong><?=count($cards)?></strong>Alle saker</a><?php foreach($labels as $key=>$label):?><a href="/newsdesk.php?filter=<?=$key?>" <?=$filter===$key?'aria-current="page"':''?>><strong><?=$counts[$key]?></strong><?=escape($label)?></a><?php endforeach;?></nav>
<div class="nr-layout"><aside class="nr-queue" aria-label="Sakskø"><h2>Din godkjenningskø</h2>
<?php if(!$visible):?><p class="nr-empty">Ingen saker i denne gruppen. Nye utkast vises her når de er klargjort.</p><?php endif;?>
<?php foreach($visible as $key=>$row):?><a class="nr-card <?=$key===$selected?'selected':''?>" href="/newsdesk.php?filter=<?=escape($filter)?>&amp;item=<?=rawurlencode($key)?>"><span class="nr-badge <?=escape($row['status'])?>"><?=escape($labels[$row['status']]??'Utkast')?></span><h3><?=escape($row['title'])?></h3><p><?=escape($row['sourceName']??'Fotball')?></p><?php if($row['reasons']??[]):?><small><?=escape($row['reasons'][0])?></small><?php endif;?></a><?php endforeach;?></aside>
<section class="nr-reader" aria-label="Les og godkjenn saken">
<?php if(!$card):?><div class="nr-empty"><h2>Her leser du neste sak</h2><p>Velg en sak i køen, eller klargjør en fra innboksen nedenfor.</p></div><?php else:?>
<span class="nr-badge <?=escape($card['status'])?>"><?=escape($labels[$card['status']]??'Utkast')?></span><h2 class="nr-title"><?=escape($card['title'])?></h2>
<p class="nr-meta"><?=escape($card['sourceName']??'Fotballroboten')?><?php if(!empty($card['sourceUrl'])):?> · <a href="<?=escape($card['sourceUrl'])?>" target="_blank" rel="noopener noreferrer">Les originalen ↗</a><?php endif;?></p>
<?php if($item):?><figure class="nr-image"><img src="/assets/radio-rubben-nyheter.png" alt="Radio Rubben Nyheter" width="1024" height="576"><figcaption>Radio Rubbens KI-genererte illustrasjon.</figcaption></figure><?php endif;?>
<article class="nr-prose"><p class="nr-intro"><?=escape($card['intro']??'')?></p><?php foreach(preg_split('/\R+/u',trim($card['body']??'')) as $paragraph)if(trim($paragraph)!==''):?><p><?=escape($paragraph)?></p><?php endif;?></article>
<?php foreach($card['links']??[] as $link):?><p><a href="<?=escape($link['url'])?>" target="_blank" rel="noopener noreferrer"><?=escape($link['label'])?></a></p><?php endforeach;?>
<?php if($item&&$card['originalRead']):?><p class="nr-source">Originalartikkelen er lest · <?=escape(newsroom_time($card['sourceFetchedAt']))?>. <?php if(parse_url($item['sourceUrl'],PHP_URL_HOST)==='www.nrk.no'):?><a href="<?=escape($item['sourceUrl'])?>" target="_blank" rel="noopener noreferrer">Les hele saken hos NRK ↗</a><?php endif;?></p><?php endif;?>
<?php if($card['reasons']??[]):?><section class="nr-blockers"><h3>Dette gjenstår</h3><ul><?php foreach($card['reasons'] as $reason):?><li><?=escape($reason)?></li><?php endforeach;?></ul></section><?php endif;?>
<?php if($card['status']==='published'&&!empty($card['publishedUrl'])):?><p><a class="nr-primary" href="<?=escape($card['publishedUrl'])?>" target="_blank" rel="noopener noreferrer">Åpne saken på nett ↗</a></p>
<?php elseif($can):?><div class="nr-decisions">
<?php if($admin&&!empty($card['canApprove'])&&!$wpError):?><form method="post" data-newsroom><?php newsroom_fields($selected,$card);?><input type="hidden" name="action" value="approve"><label class="nr-confirm"><input type="checkbox" name="confirmed" value="1" required> Jeg har lest saken og godkjenner publisering</label><button class="nr-primary">Godkjenn og publiser</button></form><?php endif;?>
<?php if($item||!empty($card['canRevise'])):?><details><summary>Be om endringer</summary><form method="post" data-newsroom><?php newsroom_fields($selected,$card);?><input type="hidden" name="action" value="revise"><label>Hva ønsker du endret?<textarea name="comment" maxlength="2000" rows="3" required placeholder="Kort ned teksten og gjør overskriften tydeligere."></textarea></label><?php if(!$item):?><label>Egne opplysninger du står som kilde til<textarea name="editorial_facts" rows="2" maxlength="2000"></textarea></label><?php endif;?><button>Bearbeid og kontroller på nytt</button></form></details><?php endif;?>
<form method="post" data-newsroom><?php newsroom_fields($selected,$card);?><input type="hidden" name="action" value="reject"><button class="nr-quiet">Forkast forslaget</button></form>
<?php if($item&&$card['status']==='attention'):?><form method="post" data-newsroom><?php newsroom_fields($selected,$card);?><input type="hidden" name="action" value="<?=empty($card['body'])?'prepare':'check'?>"><button><?=empty($card['body'])?'Hent original og klargjør':'Kontroller lagret tekst på nytt'?></button></form><?php endif;?></div>
<?php if($item):?><details class="nr-edit"><summary>Rediger teksten selv</summary><form method="post" data-newsroom><?php newsroom_fields($selected,$card);?><input type="hidden" name="action" value="save"><label>Overskrift<input name="title" maxlength="180" value="<?=escape($card['title'])?>" required></label><label>Ingress<textarea name="intro" maxlength="500" required><?=escape($card['intro'])?></textarea></label><label>Artikkeltekst<textarea name="body" rows="12" maxlength="5000" required><?=escape($card['body'])?></textarea></label><label>Plassering<select name="news_scope"><option value="news">Nyheter</option><option value="local" <?=($item['web']['publication']['scope']??'')==='local'?'selected':''?>>Nyheter og Lokale Nyheter</option></select></label><button>Lagre og kontroller</button></form></details><?php endif;?>
<?php endif;?>
<?php if($item):?><details class="nr-evidence"><summary>Kilder, kontroll og radio</summary><p>Kildekontroll: <?=escape(newsroom_time($card['checkedAt']))?></p><?php if(!empty($item['web']['check']['source']['text'])):?><p><?=nl2br(escape($item['web']['check']['source']['text']))?></p><?php endif;?><a href="/case.php?item=<?=escape($item['id'])?>">Radiomanus og full sakshistorikk</a></details><?php endif;?>
<?php endif;?></section></div>
<p id="newsroom-progress" role="status" aria-live="polite" hidden></p>
<details class="nr-inbox"><summary>Innkommet fra NRK Vestland og Bømlo kommune · <?=count($sources)?> kildesaker</summary><div class="nr-source-grid">
<?php foreach($sources as $source):?><article><p class="nr-meta"><?=escape($source['sourceName'])?> · <?=escape(newsroom_time($source['publishedAt']))?></p><h3><?=escape($source['title'])?></h3><p><?=escape($source['summary'])?></p><a href="<?=escape($source['url'])?>" target="_blank" rel="noopener noreferrer">Åpne originalen ↗</a><?php if($can):?><form method="post" data-newsroom><input type="hidden" name="csrf" value="<?=escape($_SESSION['csrf'])?>"><input type="hidden" name="source" value="<?=escape($source['id'])?>"><input type="hidden" name="action" value="open_source"><button><?=studio_board_has_source($board['items'],$source)?'Se eksisterende sak':'Klargjør til godkjenning'?></button></form><?php endif;?></article><?php endforeach;?></div></details>
<details class="nr-settings"><summary>Automatikk og samlevarsler</summary><p>Automatisk klargjøring: <strong><?=$settings['enabled']?'På':'Pause'?></strong>. Inntil åtte nye saker per døgn. Originalen leses før skriving; du godkjenner publisering.</p><p>E-post samler nye, ferdige saker i ett varsel med lenke hit. Ingen artikkeltekst sendes i varselet. Uendrede saker gir ikke nye e-poster.</p><?php if(!empty($wpStatus['notification']['message'])):?><p><?=escape($wpStatus['notification']['message'])?></p><?php endif;?>
<?php if($admin):?><form method="post" data-newsroom><input type="hidden" name="csrf" value="<?=escape($_SESSION['csrf'])?>"><input type="hidden" name="action" value="settings"><input type="hidden" name="enabled" value="<?=$settings['enabled']?'0':'1'?>"><button><?=$settings['enabled']?'Sett klargjøring på pause':'Start automatisk klargjøring'?></button></form><?php endif;?><p><a href="/sending.php">Åpne sendelisten</a> · <a href="/control.php">Trafikk, vær og kontrollsenter</a></p></details>
</main></div></div><script src="/assets/newsroom.js?v=20261004-1" defer></script></body></html>
