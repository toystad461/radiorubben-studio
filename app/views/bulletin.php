<?php
$b=$selected['bulletin'];$v=$selected['audioScripts']['bulletin'];$a=$v['audio']??[];$ac=$config['rr_audio']??[];
$valid=rr_bulletin_checked($selected,$v,$board);$approved=$valid&&rr_audio_script_approved($selected,$v);
$fields=static function()use($selected):void { ?><input type="hidden" name="csrf" value="<?=escape($_SESSION['csrf'])?>"><input type="hidden" name="id" value="<?=escape($selected['id'])?>"><input type="hidden" name="revision" value="<?=(int)$selected['revision']?>"><?php };
?>
<h2 id="editor-title"><?=escape($selected['title'])?></h2>
<p><strong>Testmodus – forhåndslytting, ikke automatisk sending.</strong></p>
<p>Intro, saker og vær gjelder <?=escape(rr_bulletin_time($b['airAt'])->format('d.m.Y H:i T'))?>. <?=((int)rr_bulletin_time($b['airAt'])->format('G')>=5&&(int)rr_bulletin_time($b['airAt'])->format('G')<10)?'Rolig morgentempo.':'Vanlig nyhetsankertempo.'?></p>
<?php if(!$valid):?><p class="control-alert error">Sendingen må lages på nytt: en sak er endret, kontroll/vær er utløpt eller sendetiden er passert. Oppdater enkeltsakene og velg ny sendetid i listen.</p><?php endif;?>
<p class="script-view"><?=nl2br(escape($v['script']))?></p>
<details><summary>Kilder og redaksjonell kontroll</summary>
<?php foreach($b['sources']as$s):?><p><a href="/case.php?item=<?=escape($s['id'])?>"><?=escape($s['item']['title'])?></a> · <?=escape($s['item']['sourceName'])?> · kontrollert <?=escape(sending_time($s['item']['sourceCheck']['checkedAt']??''))?></p><?php endforeach;?>
<?php if($b['weather']):?><p>Vær: Meteorologisk institutt · gjelder <?=escape(sending_time($b['weather']['places'][0]['time']))?> · prognose oppdatert <?=escape(sending_time($b['weather']['places'][0]['updated']))?>. <a href="https://www.met.no/">MET</a> · <a href="https://creativecommons.org/licenses/by/4.0/">CC BY 4.0</a></p><?php endif;?>
</details>
<p>Rett tekst i enkeltsakene. Lag deretter en ny samlet sending; tidligere manus og lyd beholdes i historikken.</p>
<?php if($canPrepare&&$valid&&!$approved):?><form method="post"><?php $fields();?><label><input type="checkbox" name="confirmed" value="1" required> Jeg har lest hele manuset, kontrollert sakene, sendetidspunktet og været</label><button name="action" value="bulletin_approve">Godkjenn samlet manus</button></form><?php endif;?>
<?php if($approved):?><p>Samlet manus er godkjent.</p><?php if($canPrepare&&!empty($ac['enabled'])):?><form method="post"><?php $fields();?><label>Stemme<select name="voice" required><?php foreach($ac['voices']??[]as$key=>$voice):try{rr_audio_voice($ac,$key,(string)$selected['program']);}catch(Throwable){continue;}?><option value="<?=escape($key)?>"><?=escape($voice['label'])?></option><?php endforeach;?></select></label><p>Bruker <?=mb_strlen($v['script'])?> tegn før eventuelle uttalejusteringer. Eleven v4. Ny generering bruker kvote.</p><button name="action" value="bulletin_tts">Generer samlet lydprøve</button></form><?php endif;endif;?>
<?php if(!empty($a['error'])):?><p class="control-alert error"><?=escape($a['error'])?></p><?php endif;?>
<?php if(isset($a['raw'])):?><p>KI-generert rålyd · <?=number_format($a['measurements']['duration']??0,1,',','')?> sekunder. <?=!rr_audio_current($selected,$v,$board,$ac)?'Utdatert prøve – ikke til sending.':'Lytt gjennom innhold, uttale og tempo.'?></p><audio controls preload="none" src="/audio-file.php?item=<?=escape($selected['id'])?>&amp;profile=bulletin&amp;kind=raw"></audio><p>Automatisk normalisering er ikke tilgjengelig på serveren ennå.</p><?php endif;?>
<?php if(($user['role']??'')==='admin'&&in_array($a['status']??'',['unknown','generating'],true)):?><form method="post"><?php $fields();?><label>Avklaring fra ElevenLabs<input name="resolution" minlength="10" maxlength="500" required></label><label><input type="checkbox" name="confirmed" value="1" required> Jeg har kontrollert leverandørhistorikken</label><button name="action" value="bulletin_resolve">Registrer avklaring</button></form><?php endif;?>
<?php if($canPrepare):?><form method="post"><?php $fields();?><button name="action" value="archive" class="quiet">Arkiver denne prøvesendingen</button></form><?php endif;?>
