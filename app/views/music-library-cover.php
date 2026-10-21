<?php
$cover=null;$coverReadable=true;
try{$cover=music_cover_read($selected);}catch(Throwable){$coverReadable=false;}
?>
<section id="album-cover" class="library-cover-panel" aria-label="Albumcover">
<img class="library-cover-large" src="<?=escape(music_cover_url($selected))?>" width="240" height="240" alt="Albumcover eller Radio Rubben-plassholder">
<div><h3>Albumcover</h3><p class="small"><?=escape(match($cover['source']??''){ 'manual'=>'Lastet opp manuelt','embedded'=>'Hentet fra MP3-filen',default=>'Cover mangler – Radio Rubben-plassholder vises'})?></p>
<?php if($canUpload&&$coverReadable):?><details><summary>Bytt eller hent cover</summary>
<form action="/music-library-cover.php" method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?=escape($_SESSION['csrf'])?>"><input type="hidden" name="id" value="<?=escape($selected['id'])?>"><input type="hidden" name="version" value="<?=escape($selected['sha256'])?>"><input type="hidden" name="revision" value="<?=escape((string)($cover['revision']??0))?>">
<label>JPEG eller PNG · inntil 2 MiB<input type="file" name="cover" accept="image/jpeg,image/png"></label>
<button type="submit" name="mode" value="manual">Lagre valgt bilde</button>
<button type="submit" name="mode" value="embedded">Hent cover fra MP3</button>
<?php if($cover&&$cover['source']!=='none'):?><button type="submit" name="mode" value="none">Bruk plassholder</button><?php endif;?>
<p class="small">Originalfilen bevares. Cover brukes internt i Studio.</p>
</form></details><?php elseif(!$coverReadable):?><p>Coverdata må kontrolleres før endring.</p><?php endif;?></div>
</section>
