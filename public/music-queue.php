<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/music-play-queue.php';
$user=current_user(); if (!$user) redirect('/login.php');
if (($_SERVER['REQUEST_METHOD']??'GET')!=='GET') { http_response_code(405); header('Allow: GET'); exit; }
$canControl=studio_can($user,'produce'); $snapshot=null; $error='';
try { $snapshot=music_play_queue_snapshot(music_play_queue_read(),music_library_read()); }
catch (Throwable) { $error='Køen eller musikkbiblioteket kunne ikke leses. Prøv Oppdater.'; }
$extraStylesheet='/assets/music-queue.css?v=20261018-1';
require dirname(__DIR__).'/app/views/head.php';
?>
<div class="shell"><?php $activePage='music-queue'; require dirname(__DIR__).'/app/views/sidebar.php'; ?>
<div class="workspace"><header class="topbar"><span>Studio / <strong>Musikkø</strong></span><?php require dirname(__DIR__).'/app/views/account.php'; ?></header>
<main id="main" class="music-queue-page" data-csrf="<?=escape($_SESSION['csrf'])?>" data-can-control="<?=$canControl?'1':'0'?>">
 <header class="queue-heading"><div><p class="eyebrow">RADIO RUBBEN / MUSIKK</p><h1>Musikkø</h1><p>Sett rekkefølgen. Lytt gjennom. Gjør klart til programmet.</p></div><a class="button" href="/music-library.php">Åpne musikkbibliotek</a></header>
 <p class="queue-mode">Prøvelytting i denne fanen. Køen lagres på serveren. Lyd sendes ikke ut på radio.</p>
 <p id="queue-message" role="status"><?=escape($error?:'Legg låter i køen for å begynne. Ingen lyd starter automatisk.')?></p>
 <div class="queue-grid">
  <section class="queue-panel queue-library" aria-labelledby="queue-library-title"><h2 id="queue-library-title">Velg musikk</h2><label for="queue-search">Søk etter artist eller låt</label><input type="search" id="queue-search" placeholder="Søk i biblioteket" autocomplete="off"><p id="queue-library-count" class="small"></p><ul id="queue-catalog" class="queue-catalog"></ul></section>
  <section class="queue-panel queue-main" aria-labelledby="queue-title"><div class="queue-panel-heading"><div><h2 id="queue-title">Planlagt rekkefølge</h2><p id="queue-total" class="small">Henter kø …</p></div><div><button id="queue-refresh" type="button">Oppdater</button><button id="queue-clear" type="button" <?=$canControl?'':'disabled'?>>Tøm kø</button></div></div><ol id="queue-entries" class="queue-entries"></ol><p class="small">Dra låtene for å flytte dem, eller bruk Opp og Ned. Endringer lagres med én gang.</p></section>
  <section class="queue-panel queue-player" aria-labelledby="queue-player-title"><p class="eyebrow">PRØVELYTTING</p><h2 id="queue-player-title">Spilles her nå</h2><p id="queue-current">Ingen låt valgt</p><p id="queue-player-state" role="status">Stoppet</p><p id="queue-progress">—</p><audio id="queue-audio" preload="none"></audio><div class="queue-player-buttons"><button id="queue-play" type="button" disabled>Spill neste</button><button id="queue-pause" type="button" disabled>Pause</button><button id="queue-stop" type="button" disabled>Stopp</button></div><label class="queue-check"><input id="queue-autoplay" type="checkbox" <?=$canControl?'':'disabled'?>> Fortsett automatisk til neste låt</label><label for="queue-volume">Volum</label><input id="queue-volume" type="range" min="0" max="100" value="80" <?=$canControl?'':'disabled'?>><p class="small">Bruker enhetens valgte lydutgang. Avspillingen stopper når fanen lukkes.</p><h3>Neste låt</h3><p id="queue-next">Ingen</p><h3>Spilt i denne fanen</h3><ul id="queue-history"></ul><button id="queue-restart" type="button" <?=$canControl?'':'disabled'?>>Begynn køen på nytt</button><p class="small">Nullstiller prøvelyttingen uten å starte lyd eller endre lagret kø.</p></section>
 </div>
 <noscript><p>Musikkøen trenger JavaScript for redigering og prøvelytting.</p></noscript>
</main></div></div>
<script type="application/json" id="queue-initial"><?=json_encode($snapshot,JSON_THROW_ON_ERROR|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_QUOT|JSON_HEX_APOS)?></script>
<script type="module" src="/assets/music-queue.mjs?v=20261018-1"></script></body></html>
