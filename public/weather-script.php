<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$user=current_user();if(!$user)redirect('/login.php');
require dirname(__DIR__).'/app/weather-script.php';
$canPrepare=studio_can($user,'produce');
if(!in_array($_SERVER['REQUEST_METHOD'],['GET','POST'],true)){http_response_code(405);header('Allow: GET, POST');exit;}
$error=null;$message=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!$canPrepare || !is_string($_POST['csrf']??null) || !hash_equals($_SESSION['csrf'],$_POST['csrf'])){http_response_code(403);exit('Ingen tilgang.');}
    try{
        $action=is_string($_POST['action']??null)?$_POST['action']:'';
        $revision=filter_var($_POST['revision']??null,FILTER_VALIDATE_INT);
        if($revision===false || $revision===null || $revision<0)throw new InvalidArgumentException('Last siden på nytt.');
        if($action==='generate')$draft=studio_weather_script_generate();
        elseif($action==='edit' && is_string($_POST['text']??null))$draft=['text'=>$_POST['text']];
        else throw new InvalidArgumentException('Ukjent handling.');
        studio_weather_script_save($draft,$revision,$action);
        $_SESSION['weather_message']='Manuset er lagret som utkast. Les og kontroller før opplesning.';
        redirect('/weather-script.php');
    }catch(InvalidArgumentException $e){$error=$e->getMessage();}
    catch(Throwable $e){$error='Kunne ikke lage eller lagre værmanus. Prøv igjen når værgrunnlaget er tilgjengelig.';}
}
$message=$_SESSION['weather_message']??null;unset($_SESSION['weather_message']);
try{$draft=studio_board_read(studio_weather_script_path())['draft']??[];}
catch(Throwable $e){$draft=[];$error='Lagret værmanus kan ikke leses nå.';$canPrepare=false;}
$pageTitle='Værmanus';$extraStylesheet='/assets/control.css?v=20261006';
require dirname(__DIR__).'/app/views/head.php';
?>
<div class="shell">
<?php $activePage='overview';require dirname(__DIR__).'/app/views/sidebar.php'; ?>
<div class="workspace"><header class="topbar"><span>Arbeidsrom / <strong>Værmanus</strong></span><?php require dirname(__DIR__).'/app/views/account.php'; ?></header>
<main id="main" class="control-page">
<h1>Vær til opplesning</h1><p>Kort værmanus for Bømlo, Stord og Haugesund. Kontroller utkastet før du leser det i nyhetene.</p>
<?php if($message): ?><p role="status"><?= escape($message) ?></p><?php endif; ?>
<?php if($error): ?><p role="alert"><?= escape($error) ?></p><?php endif; ?>
<?php if($canPrepare): ?><form method="post"><input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>"><input type="hidden" name="revision" value="<?= (int)($draft['revision']??0) ?>"><button name="action" value="generate">Lag værmanus</button></form><?php endif; ?>
<?php if($draft): ?>
<p><strong><?= studio_weather_script_current($draft)?'Utkast – må kontrolleres':'Utgått – lag nytt manus før opplesning' ?></strong></p>
<p>Laget <?= escape($draft['generatedAt']) ?> · gjelder til <?= escape($draft['expiresAt']) ?></p>
<form method="post"><input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>"><input type="hidden" name="revision" value="<?= (int)$draft['revision'] ?>"><label for="weather-text">Manus</label><textarea id="weather-text" name="text" rows="8" maxlength="4000"<?= $canPrepare?'':' readonly' ?>><?= escape($draft['text']) ?></textarea><?php if($canPrepare && studio_weather_script_current($draft)): ?><button name="action" value="edit">Lagre redigering</button><?php endif; ?></form>
<h2>Værgrunnlag</h2><ul><?php foreach($draft['places'] as $place): ?><li><?= escape($place['place']) ?>: <?= escape((string)$place['temperature']) ?> °C · prognose <?= escape($place['time']) ?> · kilde oppdatert <?= escape((string)$place['updated']) ?></li><?php endforeach; ?></ul>
<p>Kilde: <a href="https://www.met.no/">Meteorologisk institutt</a> · <a href="https://creativecommons.org/licenses/by/4.0/">CC BY 4.0</a></p>
<?php endif; ?>
<p><a href="/control.php">Tilbake til Studiooversikt</a></p>
</main></div></div></body></html>
