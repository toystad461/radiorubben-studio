<main id="main" class="ni-main">
<header><h1>Nyhetsinngang</h1><p>Velg en innkommet sak og vurder om den er aktuell for Radio Rubben.</p></header>
<?php if($error):?><p role="alert"><?=escape($error)?></p><?php endif;?>
<?php if($notice):?><p role="status"><?=escape($notice)?></p><?php endif;?>
<div class="ni-layout">
<section class="ni-list" id="news-list" aria-label="Innkomne nyheter">
<h2>Innkommet · <?=count($sources)?></h2>
<form method="get"><label>Kilde<select name="feed"><option value="all">Alle kilder</option><?php foreach(newsdesk_sources() as $id=>$spec):?><option value="<?=escape($id)?>" <?=$filter===$id?'selected':''?>><?=escape($spec['name'])?></option><?php endforeach;?></select></label><button>Vis</button></form>
<details><summary>Kildestatus</summary><?php foreach(newsdesk_sources() as $id=>$spec):?><p><?=escape($spec['name'])?>: <?=escape(['updated'=>'Oppdatert','stale'=>'Eldre kopi – henting feilet','unavailable'=>'Utilgjengelig'][$feeds[$id]['status']]??'Ukjent')?> · <?=escape(input_time($feeds[$id]['fetchedAt']??null))?></p><?php endforeach;?></details>
<nav class="ni-stories" aria-label="Velg nyhet">
<?php foreach($sources as $row):$r=studio_input_review($board,$row);?><a href="<?=escape(input_url($filter,$row['id']))?>#selected-news" <?=($source['id']??'')===$row['id']?'aria-current="page"':''?>><small><?=escape($row['sourceName'])?> · <?=escape(input_time($row['publishedAt']))?></small><strong><?=escape($row['rssTitle']??$row['title'])?></strong><span><?=escape($r?studio_input_labels()[$r['channel']]:'Ikke vurdert')?><?=!empty($row['isTopStory'])?' · Toppsak':''?></span></a><?php endforeach;?>
<?php if(!$sources):?><p>Ingen tilgjengelige saker i denne kilden.</p><?php endif;?></nav>
</section>
<section class="ni-reader" id="selected-news" aria-label="Valgt nyhet">
<a class="ni-back" href="#news-list">← Til nyhetslisten</a>
<?php if($source):?>
<h2><?=escape($source['rssTitle']??$source['title'])?></h2>
<p><?=escape($source['sourceName'])?> · Publisert <?=escape(input_time($source['publishedAt']))?></p>
<?php if(isset(newsdesk_sources()[$source['rssFeed']??''])):?><p>RSS-tekst fra <?=escape(newsdesk_sources()[$source['rssFeed']]['name'])?></p><?php endif;?>
<?php if(($source['feedStatus']??'')!=='updated'):?><p role="status">Dette er en eldre kildekopi. Kontroller aktualiteten hos kilden.</p><?php endif;?>
<article class="ni-original"><h3>Kildens RSS-tekst</h3><p><?=nl2br(escape($source['rssText']??$source['summary']))?></p><?php if(($source['rssText']??$source['summary'])===''):?><p>Kilden har bare levert en overskrift. Vi fyller ikke inn manglende opplysninger.</p><?php endif;?></article>
<?php if(!array_key_exists('rssText',$source)):?><p>Dette er et kort utdrag fra eldre cache. Full RSS-tekst vises etter neste vellykkede kildehenting.</p><?php endif;?>
<p><a href="<?=escape($source['url'])?>" target="_blank" rel="noopener noreferrer">Les originalen hos <?=escape($source['sourceName'])?> ↗</a></p>
<section class="ni-review"><h3><?=$review?'Din vurdering':'Passer saken for vårt publikum?'?></h3>
<p>Vurder lokal betydning, nytte, aktualitet og om vi allerede har omtalt saken. En kanalvurdering er ikke tillatelse til gjenbruk eller publisering.</p>
<?php if($old&&!$review):?><p role="status">RSS-grunnlaget har endret seg. Tidligere vurdering må fornyes.</p><?php endif;?>
<?php if($review):?><p><strong><?=escape(studio_input_labels()[$review['channel']])?></strong>: <?=escape($review['reason'])?><br><small><?=escape($review['actor'])?> · <?=escape(input_time($review['at']))?></small></p><?php endif;?>
<?php if($can):?><form method="post" action="<?=escape(input_url($filter,$source['id']))?>#selected-news">
<input type="hidden" name="csrf" value="<?=escape($_SESSION['csrf'])?>"><input type="hidden" name="action" value="review"><input type="hidden" name="source" value="<?=escape($source['id'])?>"><input type="hidden" name="sourceVersion" value="<?=escape(studio_input_version($source))?>"><input type="hidden" name="reviewRevision" value="<?=(int)($old['revision']??0)?>">
<fieldset><legend>Bruksområde</legend><?php foreach(studio_input_labels() as $value=>$label):?><label class="ni-choice"><input type="radio" name="channel" value="<?=$value?>" <?=($review['channel']??'review')===$value?'checked':''?>><?=escape($label)?></label><?php endforeach;?></fieldset>
<label>Hvorfor er saken aktuell eller ikke aktuell?<textarea name="reason" rows="3" minlength="15" maxlength="1000" required><?=escape($review['reason']??'')?></textarea></label><button>Lagre vurdering</button>
</form><?php else:?><p>Du kan lese sakene. Vurdering krever produksjonstilgang.</p><?php endif;?></section>
<details><summary>Se grunnlaget</summary><p>Hentet <?=escape(input_time($source['fetchedAt']))?>. RSS-tekst vises som ren tekst; ingen artikkel eller bilde er hentet her.</p><p>Kildeidentitet: <?=escape($source['id'])?></p></details>
<?php if($can&&$review&&in_array($review['channel'],['both','radio','web'],true)):?><details><summary>Videre behandling i dagens Nyhetsdesk</summary><p>Dette er en separat arbeidsflyt som henter hele originalartikkelen og vurderer den. Det er ikke direkte videreformidling av RSS. Avklar kildebruk før klargjøring.</p><form method="post" action="/newsdesk.php"><input type="hidden" name="csrf" value="<?=escape($_SESSION['csrf'])?>"><input type="hidden" name="action" value="open_source"><input type="hidden" name="source" value="<?=escape($source['id'])?>"><input type="hidden" name="channel" value="<?=escape($review['channel'])?>"><button>Hent hele originalen og vurder videre</button></form></details><?php endif;?>
<?php else:?><h2>Velg en kilde med tilgjengelige saker</h2><?php endif;?>
</section></div></main>
