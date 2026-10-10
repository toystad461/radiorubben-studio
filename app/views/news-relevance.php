<section class="nr-evidence" aria-label="Lokal relevans">
<h3>Redaksjonelt utvalg</h3>
<p>Publisert hos kilden: <?=escape(newsroom_time($item['sourceAt']??null))?></p>
<?php if($relevance):?>
<p><strong><?=escape(studio_relevance_labels()[$relevance['override']['channel']??$relevance['recommendation']]??'Venter')?></strong> · Hendelsesdato: <?=escape($relevance['eventDate']??'Ukjent – ikke lik publiseringstid')?></p>
<p><?=escape($relevance['reason'])?></p>
<?php foreach($relevance['localEvidence']??[] as $quote):?><blockquote><?=escape($quote)?></blockquote><?php endforeach;?>
<p>Radio: <?=escape($relevance['radioReason'])?><br>Nett: <?=escape($relevance['webReason'])?></p>
<?php if(!empty($relevance['relatedId'])):?><p><a href="/case.php?item=<?=rawurlencode($relevance['relatedId'])?>">Tidligere sak</a>: <?=escape($relevance['relationReason']??'')?></p><?php endif;?>
<?php if(!empty($relevance['override'])):?><p>Overstyrt av <?=escape($relevance['override']['actor'])?>: <?=escape($relevance['override']['reason'])?></p><?php endif;?>
<?php else:?><p>Lokal relevans er ikke vurdert. Generering venter.</p><?php endif;?>
<?php if($can&&($item['web']['delivery']['status']??'')!=='publish'):?>
<form method="post" data-newsroom><?php newsroom_fields($selected,$card);?><input type="hidden" name="action" value="assess"><button>Hent original og vurder på nytt</button></form>
<?php if($relevance&&studio_board_channel($item)==='radio'):?><form method="post" data-newsroom><?php newsroom_fields($selected,$card);?><input type="hidden" name="action" value="prepare"><button>Klargjør radiomanus</button></form><?php endif;?>
<?php if($relevance):?><details><summary>Overstyr utvalget</summary>
<p>Begrunnelsen logges for denne saken. Faste regler, kildekrav og publiseringsgodkjenning endres ikke. Valgt kanal brukes også til klargjøringen.</p>
<form method="post" data-newsroom><?php newsroom_fields($selected,$card);?><input type="hidden" name="action" value="relevance_override">
<label>Anbefalt kanal<select name="relevance_channel"><option value="radio">Radio</option><option value="web">Nett</option><option value="both">Radio og nett</option><option value="rejected">Avvis</option></select></label>
<label>Begrunnelse<textarea name="reason" minlength="15" maxlength="1000" required></textarea></label><button>Lagre overstyring</button></form></details><?php endif;?>
<?php endif;?></section>
