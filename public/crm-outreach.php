<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$user=current_user(); if(!$user)redirect('/login.php');
require_once dirname(__DIR__).'/app/crm-outreach.php';
if(!studio_crm_allowed($user)){http_response_code(403);exit('Ingen tilgang.');}
if(!in_array($_SERVER['REQUEST_METHOD'],['GET','POST'],true)){http_response_code(405);exit;}
header('Cache-Control: no-store');
$id=is_string($_GET['id']??null)?$_GET['id']:'';$error=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf'],$_POST['csrf'])){http_response_code(403);exit('Last siden på nytt.');}
    try{
        $id=studio_crm_text($_POST,'id',16);$action=studio_crm_text($_POST,'action',30);
        if($action==='queue')studio_crm_demo_queue($_POST,$user,$config['rr_audio']??[]);
        elseif($action==='export'){
            $row=null;foreach(studio_crm_read()['records']as$r)if($r['id']===$id)$row=$r;
            if(!$row||(string)$row['revision']!==($_POST['revision']??''))throw new InvalidArgumentException('Kortet er endret. Last siden på nytt.');
            $eml=studio_crm_eml($row,null,$config['rr_audio']??[]);
            header('Content-Type: message/rfc822');header('Content-Disposition: attachment; filename="Radio-Rubben-introduksjon.eml"');header('X-Content-Type-Options: nosniff');echo $eml;exit;
        }else studio_crm_outreach_apply($action,$_POST,$user);
        redirect('/crm-outreach.php?id='.rawurlencode($id));
    }catch(InvalidArgumentException $e){http_response_code(422);$error=$e->getMessage();}
    catch(Throwable $e){http_response_code(503);$error='Forslaget kunne ikke behandles. Last siden på nytt og kontroller status før nytt forsøk.';}
}
try{$row=null;foreach(studio_crm_read()['records']as$r)if($r['id']===$id)$row=$r;}
catch(Throwable $e){http_response_code(503);exit('CRM er utilgjengelig.');}
if(!$row){http_response_code(404);exit('Bedriften finnes ikke.');}
$p=$row['proposal']??null;$job=$p['demo']??[];
$fields=$p??[];
if($error&&($_POST['action']??'')==='save')foreach(['subject','intro','script','sponsor']as$key)if(is_string($_POST[$key]??null))$fields[$key]=$_POST[$key];
$revision=$error&&is_string($_POST['revision']??null)?$_POST['revision']:(string)$row['revision'];
$hidden=static function(string $action)use($id,$revision):void{ ?>
<input type="hidden" name="csrf" value="<?=escape($_SESSION['csrf'])?>"><input type="hidden" name="id" value="<?=escape($id)?>"><input type="hidden" name="revision" value="<?=escape($revision)?>"><input type="hidden" name="action" value="<?=escape($action)?>">
<?php };
$extraStylesheet='/assets/crm.css?v=2';require dirname(__DIR__).'/app/views/head.php';
?>
<div class="shell crm-shell"><?php $activePage='crm';require dirname(__DIR__).'/app/views/sidebar.php';?><div class="workspace">
<header class="topbar"><a href="/crm.php?id=<?=escape($id)?>">← Bedriftskort</a><?php require dirname(__DIR__).'/app/views/account.php';?></header>
<main id="main" class="crm-page"><h1>Introduksjon til <?=escape($row['company'])?></h1><p>Et uforpliktende reklameforslag, programsamarbeid og en lydprøve. Du sender selv fra Outlook.</p>
<?php if($error):?><p role="alert" class="crm-notice error"><?=escape($error)?></p><?php endif;?>
<section class="crm-panel"><h2>1. Bedriftsgrunnlag</h2><p>Mottaker: <?=escape($row['email']?:'Må legges inn på bedriftskortet')?></p>
<p><?=escape($row['orgNumber']??'')?> · <?=escape($row['businessAddress']??'')?> · <?=escape($row['industry']??'')?></p>
<?php if($row['website']):?><p><a href="<?=escape($row['website'])?>" target="_blank" rel="noopener noreferrer">Kontroller bedriftens hjemmeside ↗</a></p><?php endif;?>
<?php if(!empty($row['registryEvidence'])):$e=$row['registryEvidence'];?><p class="crm-muted">Registeroppslag <?=escape($e['fetchedAt'])?>. <a href="<?=escape($e['source'])?>" target="_blank" rel="noopener noreferrer">Se registrerte opplysninger</a>. Egne endringer på kortet må kontrolleres separat.</p><?php endif;?>
<p class="crm-muted">Første utkast bruker en fast mal og bedriftsnavnet. Hjemmesidens innhold hentes ikke automatisk. Tilpass tjenester og lokal relevans med kontrollerte opplysninger; priser og avtaler må ikke gjettes.</p>
<?php if(!$p):?><form method="post"><?php $hidden('draft');?><button class="primary">Lag forslagspakke</button></form><?php endif;?></section>
<?php if($p):?>
<section class="crm-panel"><h2>2. Tekstforslag · versjon <?=(int)$p['version']?></h2>
<?php if(!studio_crm_proposal_current($row)):?><p role="alert">Bedriftsgrunnlaget er endret eller kortet er satt på vent. Kontroller og lagre tekstene på nytt før godkjenning.</p><?php endif;?>
<form method="post" class="crm-form"><?php $hidden('save');?>
<label>Emne<input name="subject" maxlength="180" required value="<?=escape($fields['subject'])?>"></label>
<label>Introduksjonsmail<textarea name="intro" rows="8" maxlength="4000" required><?=escape($fields['intro'])?></textarea></label>
<label>Reklamemanus · mål 15–20 sekunder<textarea name="script" rows="5" maxlength="1500" required><?=escape($fields['script'])?></textarea></label>
<p class="crm-muted">Lyden starter med «<?=escape(rr_audio_disclosure()['text'])?>». Merkingen teller med i varigheten. Kort ned teksten hvis prøven blir for lang.</p>
<label>Forslag til programsponsing<textarea name="sponsor" rows="4" maxlength="2000" required><?=escape($fields['sponsor'])?></textarea></label>
<button>Lagre tekstene som ny versjon</button></form>
<form method="post" class="crm-form"><?php $hidden('approve_script');?><label class="crm-check"><input type="checkbox" name="confirmed" value="1" required>Jeg har kontrollert bedriften, faktapåstandene og de lagrede tekstene.</label><button>Godkjenn manus for lydprøve</button></form>
</section>
<section class="crm-panel"><h2>3. Lydprøve</h2>
<p>Uforpliktende demo med KI-generert stemme. Dette er ikke en ferdig sendefil.</p>
<?php $states=['queued'=>'Venter på start','generating'=>'Lager lydprøve','unknown'=>'Må avklares hos leverandøren','ready'=>'Klar til gjennomlytting','needs_revision'=>'Må justeres: kontroller lengde eller lyd','failed'=>'Leverandøren avviste prøven','stale'=>'Utdatert lyd','resolved'=>'Avklart – ny prøve kan bestilles'];?><p data-demo-status role="status"><?=escape($states[$job['status']??'']??'Ingen lydprøve bestilt')?></p>
<?php if(isset($job['asset'])):?><audio controls preload="none" src="/crm-demo.php?id=<?=escape($id)?>&amp;token=<?=escape($job['token'])?>"></audio><p>Målt varighet: <?=number_format((float)($job['measure']['duration']??0),1,',','')?> sekunder.</p><?php endif;?>
<?php if(($job['status']??'')==='queued'):?><form method="post" action="/crm-demo-job.php" data-crm-demo-job><?php $hidden('run');?><input type="hidden" name="token" value="<?=escape($job['token'])?>"><button>Start bestilt lydprøve</button></form><?php endif;?>
<?php if(in_array($job['status']??'',['queued','generating','unknown'],true)):?><details><summary>Avklar eller avbryt lydjobben</summary><form method="post" class="crm-form"><?php $hidden('resolve');?><label>Resultat av kontroll<textarea name="resolution" minlength="10" maxlength="500" required></textarea></label><label class="crm-check"><input type="checkbox" name="confirmed" value="1" required>Jeg har kontrollert leverandørhistorikken. Ingen automatisk gjentakelse.</label><button>Registrer avklaring</button></form></details>
<?php else:?>
<?php if(($config['rr_audio']['crm_demo_enabled']??false)!==true):?><p>CRM-lydprøver er ikke aktivert ennå. Eget budsjett og godkjent kommersiell stemme må konfigureres.</p><?php else:?>
<form method="post" class="crm-form"><?php $hidden('queue');?><label>Stemme<select name="voice" required><option value="">Velg stemme</option><?php foreach($config['rr_audio']['crm_demo_voice_ids']??[]as$voice):?><option value="<?=escape($voice)?>"><?=escape($config['rr_audio']['voices'][$voice]['label']??$voice)?></option><?php endforeach;?></select></label><p>En ny prøve bruker ElevenLabs-kvoten. Ingen automatisk regenerering.</p><button>Bestill lydprøve av godkjent manus</button></form>
<?php endif;endif;?></section>
<section class="crm-panel"><h2>4. Klargjør for Outlook</h2><p>Kontroller mottakeradressen på bedriftskortet. Eksport lagrer ikke status som «sendt»; selve sendingen skjer i Outlook.</p>
<form method="post" class="crm-form"><?php $hidden('approve_package');?><label class="crm-check"><input type="checkbox" name="heard" value="1" required>Jeg har lyttet til hele prøven og godkjent innhold og uttale.</label><label class="crm-check"><input type="checkbox" name="disclosure" value="1" required>Opplysningen om KI-generert stemme høres tydelig.</label><label class="crm-check"><input type="checkbox" name="recipient" value="1" required>Jeg har kontrollert mottakeren og at denne henvendelsen skal sendes.</label><button>Godkjenn denne forslagspakken</button></form>
<?php if(studio_crm_demo_current($row)&&!empty($p['approval'])):?><form method="post"><?php $hidden('export');?><button class="primary">Last ned Outlook-utkast med lydvedlegg</button></form><p class="crm-muted">Åpne .eml-filen i Outlook. Støtte for redigerbare .eml-utkast varierer mellom Outlook-versjoner. Hvis filen åpnes som en mottatt melding, velg videresending, kontroller teksten og mottakeren før du sender.</p><?php endif;?></section>
<?php endif;?>
<script src="/assets/crm.js?v=1" defer></script></main></div></div></body></html>
