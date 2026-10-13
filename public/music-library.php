<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/music-library.php';
$user=current_user();if(!$user)redirect('/login.php');
$method=$_SERVER['REQUEST_METHOD']??'GET';if(!in_array($method,['GET','POST'],true)){http_response_code(405);header('Allow: GET, POST');exit;}
$canUpload=music_library_can_upload($user);$error='';$results=[];$tracks=[];
if($method==='POST'){
    if(!$canUpload){http_response_code(403);exit('Rollen gir ikke tilgang til opplasting.');}
    if(!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['csrf'],$_POST['csrf'])){http_response_code(403);exit('Opplastingen kunne ikke kontrolleres. Last siden på nytt og kontroller filstørrelsen.');}
    try{$files=$_FILES['tracks']??null;if(!is_array($files)||!is_array($files['name']??null)||count($files['name'])<1||count($files['name'])>10)throw new InvalidArgumentException('Velg mellom én og ti MP3-filer.');
      $total=0;foreach(['tmp_name','size','error']as$k)if(!is_array($files[$k]??null)||array_keys($files[$k])!==array_keys($files['name']))throw new InvalidArgumentException('Ugyldig opplasting.');
      foreach($files['size']as$n){if(!is_int($n)||$n<0)throw new InvalidArgumentException('Ugyldig filstørrelse.');$total+=$n;}if($total>64*1024*1024)throw new InvalidArgumentException('Velg færre filer: maksimalt 64 MiB per opplasting.');
      foreach($files['name']as$i=>$name){try{$r=music_library_upload(['name'=>$name,'tmp_name'=>$files['tmp_name'][$i],'size'=>$files['size'][$i],'error'=>$files['error'][$i]],$user,$_POST['csrf'],$_SESSION['csrf']);$results[]=['name'=>$name,'message'=>$r['duplicate']?'Finnes allerede – ingen ny kopi.':'Original lagret – venter på klargjøring.'];}catch(InvalidArgumentException $e){$results[]=['name'=>is_string($name)?$name:'Fil','message'=>$e->getMessage()];}catch(Throwable $e){error_log('Studio music upload failed');$results[]=['name'=>is_string($name)?$name:'Fil','message'=>'Kunne ikke lagre filen. Kontroller biblioteket før du prøver igjen.'];}}
      $_SESSION['music_library_results']=$results;redirect('/music-library.php');
    }catch(InvalidArgumentException $e){$error=$e->getMessage();}
}else{$results=$_SESSION['music_library_results']??[];unset($_SESSION['music_library_results']);}
$available=class_exists('finfo');if(!$available)$error='Filtypekontroll mangler på serveren. Opplasting er sperret.';try{$tracks=music_library_read();}catch(Throwable $e){$available=false;$error='Biblioteket kunne ikke leses. Opplasting er sperret til det er avklart.';}
$extraStylesheet='/assets/music-library.css?v=20261013-1';require dirname(__DIR__).'/app/views/head.php';
?>
<div class="shell"><?php $activePage='music-library';require dirname(__DIR__).'/app/views/sidebar.php';?><div class="workspace"><header class="topbar"><span>Studio / <strong>Musikkbibliotek</strong></span><?php require dirname(__DIR__).'/app/views/account.php';?></header>
<main id="main" class="music-library-page"><div class="library-heading"><div><p class="eyebrow">RADIO RUBBEN / MUSIKK</p><h1>Musikkbibliotek</h1><p>Last opp originalene. Klargjøring og musikkvalg kommer videre herfra.</p></div><a href="/ai-studio.php">Til Studio ↗</a></div>
<?php if($error!==''):?><p class="notice" role="alert"><?=escape($error)?></p><?php endif;?>
<?php if($results):?><section class="library-panel" aria-label="Resultat av opplasting"><h2>Resultat av opplasting</h2><ul><?php foreach($results as$r):?><li><strong><?=escape($r['name'])?></strong> — <?=escape($r['message'])?></li><?php endforeach;?></ul></section><?php endif;?>
<section class="library-panel" aria-labelledby="upload-title"><h2 id="upload-title">Last opp musikk</h2><p>Prøvebibliotek: opptil 100 filer / 512 MiB. Originaler lagres privat og blir ikke publisert eller spilt automatisk.</p>
<?php if($canUpload&&$available):?><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=escape($_SESSION['csrf'])?>"><label for="music-files">Velg MP3-filer<input id="music-files" name="tracks[]" type="file" accept=".mp3,audio/mpeg" multiple required></label><p class="small">Inntil 10 filer, 20 MiB per fil og 64 MiB samlet. Serverens grenser kan være lavere. Identiske filer gjenbrukes.</p><p class="small">PHP-grenser for denne siden: fil <?=escape((string)ini_get('upload_max_filesize'))?> · forespørsel <?=escape((string)ini_get('post_max_size'))?>.</p><button type="submit" class="button">Last opp originaler</button></form><?php elseif(!$canUpload):?><p>Administrator og produsent kan laste opp. Du kan lese biblioteket.</p><?php endif;?></section>
<section class="library-panel" aria-labelledby="tracks-title"><div class="library-list-heading"><h2 id="tracks-title">Opplastede låter</h2><span><?=count($tracks)?> filer · <?=number_format(array_sum(array_column($tracks,'bytes'))/1048576,1,',',' ')?> MiB</span></div><p class="small">Artist, tittel, varighet, tagging og programforslag er ikke identifisert ennå. Filnavnet er bare en referanse til originalen.</p>
<?php if($available&&!$tracks):?><p class="library-empty">Biblioteket er tomt. Start med prøvepakken din.</p><?php endif;?><ul class="library-tracks"><?php foreach($tracks as$t):?><li><div><strong><?=escape($t['originalName'])?></strong><small>ID <?=escape($t['id'])?> · <?=escape($t['uploadedAt']??'')?> · <?=number_format($t['bytes']/1048576,1,',',' ')?> MiB</small></div><span class="library-state"><?=$t['available']?'Venter på klargjøring':'Original må kontrolleres'?></span></li><?php endforeach;?></ul></section></main></div></div></body></html>
