<?php
declare(strict_types=1);
require dirname(__DIR__).'/studio-private/app/bootstrap.php';
$user=current_user();if(!$user)redirect('/login.php');
if(!in_array($_SERVER['REQUEST_METHOD'],['GET','POST'],true)){http_response_code(405);header('Allow: GET, POST');exit;}
require dirname(__DIR__).'/studio-private/app/music-plan.php';
$canPrepare=studio_can($user,'produce');$error=null;
if($_SERVER['REQUEST_METHOD']==='POST') {
    if(!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf'],$_POST['csrf'])){http_response_code(403);exit('Last siden på nytt.');}
    if(!$canPrepare){http_response_code(403);exit('Rollen kan bare lese musikkplanen.');}
    try{studio_music_plan_change((string)($_POST['action']??''),$_POST,$user);$_SESSION['music_plan_message']='Musikkplanen er lagret. Velg arkivmappen på nytt for fersk filkontroll.';redirect('/music-plan.php');}
    catch(InvalidArgumentException $e){$error=$e->getMessage();}
    catch(Throwable $e){error_log('Studio music plan write failed');$error='Musikkplanen kunne ikke lagres.';}
}
try{$board=studio_board_read();}catch(Throwable $e){http_response_code(503);exit('Musikkplanen er utilgjengelig.');}
$tracks=array_values(array_filter($board['musicPlan']??[],static fn($t)=>empty($t['archived'])));
$message=$_SESSION['music_plan_message']??null;unset($_SESSION['music_plan_message']);
$extraStylesheet='/assets/control.css?v=2';require dirname(__DIR__).'/studio-private/app/views/head.php';
?>
<link rel="stylesheet" href="/assets/music-check.css?v=1">
<div class="shell"><?php $activePage='music-plan';require dirname(__DIR__).'/studio-private/app/views/sidebar.php'; ?>
<div class="workspace"><header class="topbar"><span>God morgen Vestland / <strong>Musikkontroll</strong></span><?php require dirname(__DIR__).'/studio-private/app/views/account.php'; ?></header>
<main id="music-check" class="control-page">
<div class="control-heading"><div><p class="eyebrow">FØR LIVE-SENDING</p><h1>Musikkplan og reservespor</h1><p class="control-intro">Kontroller musikkfilene på studiomaskinen før sending. Planen deles med redaksjonen; filkontrollen gjelder denne nettleseren.</p></div><a class="control-link" href="/sending.php">Til manus</a></div>
<?php if($message):?><p class="control-alert" role="status"><?= escape($message) ?></p><?php endif; ?>
<?php if($error):?><p class="control-alert error" role="alert"><?= escape($error) ?></p><?php endif; ?>
<section class="control-panel"><h2>1. Koble til musikkmappen</h2><p>Velg arkivmappen på Mac/PC, gjerne den lokale OneDrive-mappen. Lydfiler lastes ikke opp. Etter at siden er lastet på nytt, må mappen velges igjen.</p>
<label for="archive-folder">Velg musikkmappe</label><input id="archive-folder" type="file" webkitdirectory multiple accept="audio/*"><button type="button" id="check-again">Kontroller på nytt</button>
<p id="archive-status" role="status">Arkivet er ikke tilkoblet.</p><p class="control-muted">Første versjon bruker filnavn «Artist - Tittel.mp3» eller mappestrukturen «Arkiv/Artist/Album/Tittel.mp3». ID3-tagger leses ikke ennå. «Ikke funnet» betyr at navnene ikke matcher i valgt mappe. Flere versjoner må avklares. Lesbar fil er ikke en lydkvalitetskontroll.</p>
<audio id="archive-audition" controls preload="none" aria-label="Lytt til lokal musikkfil"></audio><p class="control-muted">Lytt avspiller hele filen lokalt. Kontroller lydruting før lytting mens du er på lufta.</p></section>
<?php if($canPrepare):?><section class="control-panel"><h2>2. Legg til planlagt sang</h2><form method="post" class="editor-form music-add"><input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="add"><label for="music-artist">Artist</label><input id="music-artist" name="artist" maxlength="180" required><label for="music-title">Tittel</label><input id="music-title" name="title" maxlength="180" required><label for="music-slot">Sendetime</label><select id="music-slot" name="slot"><?php for($h=0;$h<24;$h++):$slot=sprintf('%02d:00',$h);?><option value="<?= $slot ?>" <?= $h===6?'selected':'' ?>><?= $slot ?></option><?php endfor;?></select><button type="submit">Legg i musikkplan</button></form></section><?php endif;?>
<section class="control-panel"><h2>3. Kontroller og velg reserve</h2><p>Dette er redaksjonens aktive musikkplan, ikke en tilkoblet avspillingskø. Reservespor hentes som kandidater fra valgt mappe, med samme artist først. Du vurderer versjon, stemning og varighet før bruk. Ingen eksterne musikkfiler kjøpes eller lastes ned.</p>
<?php if(!$tracks):?><p>Musikkplanen er tom. Legg inn sangforslagene for God morgen Vestland.</p><?php endif;?>
<?php foreach($tracks as $track):?><article class="music-plan-row" data-track-id="<?= escape($track['id']) ?>"><h3><?= escape($track['slot'].' · '.$track['artist'].' – '.$track['title']) ?></h3><p data-result role="status">Ikke kontrollert</p><?php if($canPrepare):?><button type="button" data-listen hidden>Lytt til fil</button><?php endif;?>
<?php if($track['reserve']):?><p><strong>Valgt reserve:</strong> <?= escape($track['reserve']['artist'].' – '.$track['reserve']['title']) ?></p><?php endif;?><p data-reserve-status>Reserve ikke kontrollert.</p>
<?php if($canPrepare):?><details><summary>Finn eller registrer reservespor</summary><ul data-candidates></ul><form method="post" class="editor-form" data-reserve-form><input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="reserve"><input type="hidden" name="id" value="<?= escape($track['id']) ?>"><input type="hidden" name="revision" value="<?= (int)$track['revision'] ?>"><label>Reserveartist<input name="reserveArtist" required maxlength="180"></label><label>Reservetittel<input name="reserveTitle" required maxlength="180"></label><button type="submit">Lagre reserve</button></form></details><div class="item-controls"><?php foreach(['clear-reserve'=>'Fjern reservevalg','archive'=>'Arkiver punkt'] as $action=>$label):?><form method="post"><input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="<?= $action ?>"><input type="hidden" name="id" value="<?= escape($track['id']) ?>"><input type="hidden" name="revision" value="<?= (int)$track['revision'] ?>"><button type="submit"><?= $label ?></button></form><?php endforeach;?></div><?php endif;?></article><?php endforeach;?></section>
<section class="control-panel"><h2>Planlagt: Dette får du den neste timen</h2><p>Skisse for starten av partallstimer, for eksempel 06:00 og 08:00 norsk tid. Tre sanger fra den aktuelle timen, med 5–7 sekunder av hver. Forhåndsvisningen under bruker seks sekunder per sang og starter ingen lyd.</p><label for="preview-hour">Partallstime</label><select id="preview-hour"><option value="">Velg time</option><?php for($h=0;$h<24;$h+=2):$slot=sprintf('%02d:00',$h);?><option value="<?= $slot ?>"><?= $slot ?></option><?php endfor;?></select><div id="hour-preview"></div><p class="control-muted">Automatisk avspilling er ikke aktivert. Neste steg er en faktisk køkobling, valgte klippunkter, ferdig innlest intro, nivåkontroll og en tidsstyring som ikke avbryter en pågående låt eller mikrofon. Planendringer må oppdatere forhåndsvisningen.</p></section>
</main></div></div>
<script type="application/json" id="music-plan-data"><?= json_encode(array_map(static fn($t)=>array_intersect_key($t,array_flip(['id','artist','title','slot','reserve'])), $tracks), JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR) ?></script>
<script type="module" src="/assets/music-check.mjs?v=1"></script></body></html>
