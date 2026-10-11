<main id="main" class="ni-main">
<header><h1>Nyhetsinngang</h1><p>Velg sak → vurder relevans → ta videre eller forkast. <a href="/news-input.php?demo=updates&amp;view=updates">Prøv et oppdateringseksempel</a></p></header>
<?php if(!empty($demo)):?><p role="status">Syntetisk, skrivebeskyttet eksempel. Ingen ekte sak, publisering eller lyd påvirkes. <a href="/news-input.php">Til ekte RSS</a></p><?php endif;?>
<?php if(!empty($editorialError)):?><p role="alert"><?=escape($editorialError)?></p><?php endif;?>
<?php if($error):?><p role="alert"><?=escape($error)?></p><?php endif;?>
<?php if($notice):?><p role="status"><?=escape($notice)?></p><?php endif;?>
<div class="ni-layout">
<section class="ni-list" id="news-list" aria-label="Innkomne nyheter">
<h2>Innkommet · <?=count($sources)?></h2>
<form method="get"><?php if(!empty($demo)):?><input type="hidden" name="demo" value="updates"><?php endif;?><label>Vis saker<select name="view"><option value="active" <?=($view??'active')==='active'?'selected':''?>>Arbeidsliste</option><option value="updates" <?=($view??'active')==='updates'?'selected':''?>>Oppdateringer</option><option value="discarded" <?=($view??'active')==='discarded'?'selected':''?>>Forkastet</option><option value="all" <?=($view??'active')==='all'?'selected':''?>>Alle</option></select></label><label>Kilde<select name="feed"><option value="all">Alle kilder</option><?php foreach(newsdesk_sources() as $id=>$spec):?><option value="<?=escape($id)?>" <?=$filter===$id?'selected':''?>><?=escape($spec['name'])?></option><?php endforeach;?></select></label><button>Vis</button></form>
<details><summary>Kildestatus</summary><?php foreach(newsdesk_sources() as $id=>$spec):?><p><?=escape($spec['name'])?>: <?=escape(['updated'=>'Oppdatert','stale'=>'Eldre kopi – henting feilet','unavailable'=>'Utilgjengelig'][$feeds[$id]['status']]??'Ukjent')?> · <?=escape(input_time($feeds[$id]['fetchedAt']??null))?></p><?php endforeach;?></details>
<nav class="ni-stories" aria-label="Velg nyhet">
<?php foreach($sources as $row):$r=studio_input_review($board,$row);$u=studio_input_update($board,$row);?><a href="<?=escape(input_url($filter,$row['id']))?>#selected-news" <?=($source['id']??'')===$row['id']?'aria-current="page"':''?>><small><?=escape($row['sourceName'])?> · <?=escape(input_time($row['publishedAt']))?></small><strong><?=escape($row['rssTitle']??$row['title'])?></strong><span><?=escape(in_array($u['state'],['changed','unverified'],true)?$u['label']:($r?studio_input_labels()[$r['channel']]:'Ikke vurdert'))?><?=($r['updateDecision']??'')==='draft'?' · Oppdateringsutkast':''?><?=!empty($row['isTopStory'])?' · Toppsak':''?></span></a><?php endforeach;?>
<?php if(!$sources):?><p>Ingen saker i dette utvalget. Velg Arbeidsliste eller Alle for å se flere.</p><?php endif;?></nav>
</section>
<section class="ni-reader" id="selected-news" aria-label="Valgt nyhet">
<a class="ni-back" href="#news-list">← Til nyhetslisten</a>
<?php if($source):?>
<h2><?=escape($source['rssTitle']??$source['title'])?></h2>
<p><?=escape($source['sourceName'])?> · Publisert <?=escape(input_time($source['publishedAt']))?></p>
<?php if(isset(newsdesk_sources()[$source['rssFeed']??''])):?><p>RSS-tekst fra <?=escape(newsdesk_sources()[$source['rssFeed']]['name'])?></p><?php endif;?>
<?php if(($source['feedStatus']??'')!=='updated'):?><p role="status">Dette er en eldre kildekopi. Kontroller aktualiteten hos kilden.</p><?php endif;?>
<?php if(!empty($suggestion)):?><p><strong><?=escape($suggestion['label'])?></strong> · <?=escape($suggestion['reason'])?><br><small>Automatisk forslag fra RSS. Du tar beslutningen.</small></p><?php endif;?>
<?php if(!empty($update)&&$update['state']!=='new'):?><section class="ni-update" aria-label="Kildeoppdatering"><h3><?=escape($update['label'])?></h3>
<?php if($update['state']==='unverified'):?><p>Kilden mangler, henting feilet eller denne visningen bruker en annen RSS-feed. Dette er ikke bevis på at innholdet er uendret. Sist vurderte tekst beholdes.</p><?php endif;?>
<?php foreach($update['changes'] as $change):?><h4><?=escape($change['label'])?></h4><div class="ni-diff"><div><strong>Sist vurdert</strong><p><?=nl2br(escape($change['before']))?></p></div><div><strong>Nå i RSS</strong><p><?=nl2br(escape($change['after']))?></p></div></div><?php endforeach;?>
<p>Oppdateringer oppdages ved åpning av inngangen og eksisterende RSS-henting, maksimalt hvert 5. minutt for NRK og 15. minutt for kommunen. Endringer bare i fullartikkelen oppdages ikke i versjon 1.</p></section><?php endif;?>
<?php foreach($related??[] as $case):?><section class="ni-update"><h3><?=$case['published']?'Studio har registrert publisering':'Eksisterende Studio-sak'?></h3><p><?=escape($case['title'])?></p><?php if($case['uncertain']):?><p>Leveringen må avklares før videre publisering.</p><?php endif;?>
<?php if($case['published']):?><p>Publisert innhold beholdes. Nåværende nettside er ikke kontrollert her.</p><?php endif;?>
<details><summary>Se lagret netttekst og radiomanus</summary><h4>Netttekst</h4><p><?=nl2br(escape($case['webBody']))?></p><h4>Radiomanus</h4><p><?=nl2br(escape($case['script']))?></p></details>
<?php if(empty($demo)):?><a href="/case.php?item=<?=escape($case['id'])?>">Åpne eksisterende sak for kontroll og videre behandling</a><?php endif;?></section><?php endforeach;?>
<article class="ni-original"><h3>Kildens RSS-tekst</h3><p><?=nl2br(escape($source['rssText']??$source['summary']))?></p><?php if(($source['rssText']??$source['summary'])===''):?><p>Kilden har bare levert en overskrift. Vi fyller ikke inn manglende opplysninger.</p><?php endif;?></article>
<?php if(!array_key_exists('rssText',$source)):?><p>Dette er et kort utdrag fra eldre cache. Full RSS-tekst vises etter neste vellykkede kildehenting.</p><?php endif;?>
<?php if(empty($demo)):?><p><a href="<?=escape($source['url'])?>" target="_blank" rel="noopener noreferrer">Les originalen hos <?=escape($source['sourceName'])?> ↗</a></p><?php endif;?>
<section class="ni-review"><h3><?=$review?'Din vurdering':'Passer saken for vårt publikum?'?></h3>
<p>Vurder lokal betydning, nytte, aktualitet og om vi allerede har omtalt saken. En kanalvurdering er ikke tillatelse til gjenbruk eller publisering.</p>
<?php if($old&&!$review):?><p role="status">RSS-grunnlaget har endret seg. Tidligere vurdering må fornyes.</p><?php endif;?>
<?php if(!$old&&!empty($related)):?><p>En vurdering starter RSS-oppfølging fra denne versjonen. Vi har ikke et tidligere RSS-grunnlag som kan bevise hva som har endret seg.</p><?php endif;?>
<?php if($review):?><p><strong><?=escape(studio_input_labels()[$review['channel']])?></strong>: <?=escape($review['reason'])?><br><small><?=escape($review['actor'])?> · <?=escape(input_time($review['at']))?></small></p><?php endif;?>
<?php if($can&&empty($demo)&&empty($source['missingFromFeed'])&&($source['feedStatus']??'')==='updated'):
$formChannel=$review['channel']??'review';$formReason=$review['reason']??'';
if(!$review&&($update['state']??'')==='changed'&&in_array($old['channel']??'', ['both','web','radio'],true))$formChannel=$old['channel'];
if($error&&is_string($_POST['channel']??null)&&isset(studio_input_labels()[$_POST['channel']]))$formChannel=$_POST['channel'];
if($error&&is_string($_POST['reason']??null))$formReason=mb_substr($_POST['reason'],0,1000);
?><form method="post" action="<?=escape(input_url($filter,$source['id']))?>#selected-news">
<input type="hidden" name="csrf" value="<?=escape($_SESSION['csrf'])?>"><input type="hidden" name="source" value="<?=escape($source['id'])?>"><input type="hidden" name="sourceVersion" value="<?=escape(studio_input_version($source))?>"><input type="hidden" name="reviewRevision" value="<?=(int)($old['revision']??0)?>">
<fieldset><legend>Bruksområde</legend><?php foreach(studio_input_labels() as $value=>$label):?><label class="ni-choice"><input type="radio" name="channel" value="<?=$value?>" <?=$formChannel===$value?'checked':''?>><?=escape($label)?></label><?php endforeach;?></fieldset>
<label>Hvorfor er saken aktuell eller ikke aktuell?<textarea name="reason" rows="3" minlength="15" maxlength="1000" required><?=escape($formReason)?></textarea></label><button name="action" value="review">Lagre vurdering</button> <button name="action" value="discard">Forkast sak</button><p>Forkasting krever begrunnelse. Saken tas ut av arbeidslisten; kilden og historikken beholdes.</p>
<?php if(in_array($old['channel']??'', ['both','web','radio'],true)&&(($update['state']??'')==='changed'||($review['updateDecision']??'')==='draft')):
$draftText=$review['updateDraft']??($source['rssText']??$source['summary']);
if($error&&is_string($_POST['updateDraft']??null))$draftText=mb_substr($_POST['updateDraft'],0,10000);
?><h3>Oppdateringsutkast</h3><p>Utgangspunktet er kildens RSS-tekst. Tilpass teksten, begrunn valget og lagre til videre behandling. Dette erstatter ikke manus-, lyd- eller publiseringsgodkjenning.</p>
<label>Tekst til videre behandling<textarea name="updateDraft" rows="5" maxlength="10000"><?=escape($draftText)?></textarea></label>
<button name="action" value="draft_update">Lagre oppdateringsutkast</button> <button name="action" value="keep_published">Behold eksisterende innhold</button>
<?php endif;?>
</form><?php else:?><p>Du kan lese sakene. Lagring krever produksjonstilgang og fersk RSS. Eksempelet er skrivebeskyttet.</p><?php endif;?></section>
<details><summary>Se grunnlaget</summary><p>Hentet <?=escape(input_time($source['fetchedAt']))?>. RSS-tekst vises som ren tekst; ingen artikkel eller bilde er hentet her.</p><p>Kildeidentitet: <?=escape($source['id'])?></p></details>
<?php if(!empty($old['history'])):?><details><summary>Tidligere vurderinger · <?=count($old['history'])?></summary><?php foreach(array_reverse($old['history']) as $entry):?><section><p><strong><?=escape(studio_input_labels()[$entry['channel']]??'Tidligere vurdering')?></strong> · <?=escape($entry['actor']??'')?> · <?=escape(input_time($entry['at']??null))?></p><p><?=escape($entry['reason']??'')?></p><?php if(!empty($entry['updateDraft'])):?><p>Tidligere oppdateringsutkast: <?=nl2br(escape($entry['updateDraft']))?></p><?php endif;?></section><?php endforeach;?></details><?php endif;?>
<?php if($can&&empty($demo)&&empty($related)&&($source['feedStatus']??'')==='updated'&&$review&&in_array($review['channel'],['both','radio','web'],true)):?><details><summary>Videre behandling i dagens Nyhetsdesk</summary><p>Dette er en separat arbeidsflyt som henter hele originalartikkelen og vurderer den. Det er ikke direkte videreformidling av RSS. Avklar kildebruk før klargjøring.</p><form method="post" action="/newsdesk.php"><input type="hidden" name="csrf" value="<?=escape($_SESSION['csrf'])?>"><input type="hidden" name="action" value="open_source"><input type="hidden" name="source" value="<?=escape($source['id'])?>"><input type="hidden" name="channel" value="<?=escape($review['channel'])?>"><button>Hent hele originalen og vurder videre</button></form></details><?php endif;?>
<?php else:?><h2>Velg en kilde med tilgjengelige saker</h2><?php endif;?>
</section></div></main>
