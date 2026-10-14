<?php
$imageChoice = $item['web']['imageSelection'] ?? null;
if (!is_array($imageChoice)) return;
$imageIssue = studio_images_issue($item);
$imageDelivered = !empty($item['web']['delivery']);
?>
<section class="rr-image-choice" aria-label="Bildevalg til saken">
<h3>Illustrasjonsbilde</h3>
<p><strong>KI-generert illustrasjon</strong> · <?=escape(RRNI_PACKAGE)?> · <?=($imageChoice['mode']??'')==='manual'?'Manuelt valgt':'Automatisk forslag'?><?=!empty($imageChoice['locked'])?' · Låst mot automatisk endring':''?></p>
<p><?=escape($imageChoice['reason']??'Begrunnelse mangler.')?></p>
<?php if($imageIssue):?><p class="nr-alert" role="alert"><?=escape($imageIssue)?></p><?php endif;?>
<?php if(($imageChoice['status']??'')==='manual_none'):?><p>Redaksjonelt valg: publisering uten bilde. Begrunnelsen og valget inngår i sluttgodkjenningen.</p><?php endif;?>
<p class="rr-image-load-error nr-alert" role="alert" hidden>En bildefil kunne ikke vises. Kontroller mediekobling og opplasting før godkjenning.</p>
<div class="rr-image-variants">
<?php foreach($imageChoice['variants']??[] as $placement=>$variant): $preview=studio_images_preview($variant); if(!$preview)continue;?>
<figure class="rr-image-variant rr-image-<?=escape($placement)?>"><img src="<?=escape($preview)?>" alt="<?=escape($variant['alt_text'])?>" width="<?=(int)$variant['width_px']?>" height="<?=(int)$variant['height_px']?>"><figcaption><strong><?=escape(['article_landscape'=>'Artikkel og nyhetskort','feed_square'=>'Kvadratisk innlegg','story_vertical'=>'Story'][$placement]??$placement)?></strong><br><?=escape($variant['caption'])?><br><?=(int)$variant['width_px']?> × <?=(int)$variant['height_px']?> px</figcaption></figure>
<?php endforeach;?>
</div>
<p>Motivene er illustrasjoner, ikke dokumentasjon av hendelsen. Hele bildet og logoen beholdes. Artikkelbildet er liggende også på mobil.</p>
<?php if($can&&!$imageDelivered):?>
<details><summary>Velg tema eller bekreft bildet etter tekstendring</summary>
<form method="post" <?=($imageFormContext??'')==='newsdesk'?'data-newsroom':''?>>
<?php if(($imageFormContext??'')==='newsdesk') newsroom_fields($selected,$card); else case_fields($item);?>
<input type="hidden" name="action" value="image_select">
<label>Tema<select name="image_theme">
<?php foreach(rrni_themes() as $theme=>$a):?><option value="<?=escape($theme)?>" <?=($imageChoice['theme_id']??'')===$theme?'selected':''?>><?=escape($a['theme'])?></option><?php endforeach;?>
<option value="none" <?=($imageChoice['status']??'')==='manual_none'?'selected':''?>>Ingen passende bilde – publiser uten bilde etter godkjenning</option></select></label>
<label>Hvorfor passer dette valget?<textarea name="image_reason" minlength="12" maxlength="1600" required><?=escape($imageChoice['reason']??'')?></textarea></label>
<label><input type="checkbox" name="image_locked" value="1" <?=!empty($imageChoice['locked'])?'checked':''?>> Lås mitt bildevalg mot automatisk endring</label>
<p>Bremnes-logo krever Bremnes IL-fotballvinkling. Verkstedmotivet passer ikke alle bedrifter. Endringen trekker tilbake tidligere sluttgodkjenning.</p>
<button>Lagre bildevalg</button></form></details>
<?php if(($imageChoice['status']??'')==='MANGLER_MEDIEFIL'):?><form method="post" <?=($imageFormContext??'')==='newsdesk'?'data-newsroom':''?>><?php if(($imageFormContext??'')==='newsdesk') newsroom_fields($selected,$card); else case_fields($item);?><input type="hidden" name="image_operation" value="retry_upload"><button name="action" value="image_select">Prøv mediekoblingen på nytt – behold tema</button></form><?php endif;?>
<?php endif;?>
<?php if($imageDelivered):?><p>Bildet er levert til WordPress. Senere redaksjonelle bildevalg i WordPress beholdes.</p><?php endif;?>
<?php if($can&&$imageIssue&&($item['web']['delivery']['state']??'')==='confirmed'&&($item['web']['delivery']['status']??'')==='draft'):?><form method="post" <?=($imageFormContext??'')==='newsdesk'?'data-newsroom':''?>><?php if(($imageFormContext??'')==='newsdesk') newsroom_fields($selected,$card); else case_fields($item);?><input type="hidden" name="action" value="image_select"><input type="hidden" name="image_operation" value="confirm_context"><label>Hvorfor passer det samme bildet fortsatt?<textarea name="image_reason" minlength="12" maxlength="1600" required></textarea></label><button>Bekreft samme bilde til den endrede teksten</button></form><?php endif;?>
<?php if(($item['web']['approvedHash']??'')===studio_web_approval_hash($item)&&!$imageIssue):?><details><summary>Godkjente bilder til deling</summary><p>Bruk bildeteksten «<?=escape(RRNI_CAPTION)?>» og sakens faktiske kildelenke i delingsteksten. 4:5 og 1,91:1 krever luft/bakgrunn eller manuell formattilpasning.</p><?php foreach($imageChoice['variants']??[] as $placement=>$v):?><p><a href="/news-image-export.php?item=<?=escape($item['id'])?>&amp;placement=<?=escape($placement)?>">Last ned <?=escape($v['format'])?> med hele logoen</a></p><?php endforeach;?></details><?php endif;?>
</section>
