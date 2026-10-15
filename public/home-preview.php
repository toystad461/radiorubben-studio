<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
$user = current_user();
if (!$user) redirect('/login.php');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') { http_response_code(405); header('Allow: GET'); exit; }
require_once dirname(__DIR__) . '/app/board.php';
$boardError = false;
try {
    $entries = studio_board_active(studio_board_read());
} catch (Throwable $e) {
    error_log('Studio home preview board read failed.');
    $entries = [];
    $boardError = true;
}
$pending = 0;
$ready = 0;
foreach ($entries as $entry) {
    if (($entry['status'] ?? '') === 'ready') $ready++;
    else $pending++;
}
$extraStylesheet = '/assets/home-preview.css?v=1';
require dirname(__DIR__) . '/app/views/head.php';
?>
<div class="shell">
<?php $activePage = 'overview'; require dirname(__DIR__) . '/app/views/sidebar.php'; ?>
<div class="workspace">
<header class="topbar"><span>Arbeidsrom / <strong>Min oversikt – test</strong></span><span>Radio Rubben Studio</span><?php require dirname(__DIR__) . '/app/views/account.php'; ?></header>
<main id="main" class="rr-home">
  <header class="rr-home-header">
    <div><p class="rr-home-eyebrow">RADIO RUBBEN / STUDIO</p><h1>Min oversikt</h1><p>Redaksjon, musikk og radiotid – på ett sted.</p></div>
    <span class="rr-home-preview">TESTVISNING · IKKE LIVE</span>
  </header>
  <section aria-label="Redaksjonell status" class="rr-home-stats">
    <a href="/newsdesk.php" class="rr-home-stat"><strong>—</strong><span>Sakskandidater</span><small>Åpne Nyhetsdesk ↗</small></a>
    <a href="/control.php" class="rr-home-stat"><strong><?= $boardError ? '—' : $pending ?></strong><span>Til behandling</span><small><?= $boardError ? 'Kunne ikke lese listen' : 'Arbeidsliste · utkast' ?> ↗</small></a>
    <a href="/sending.php" class="rr-home-stat"><strong><?= $boardError ? '—' : $ready ?></strong><span>Klare redaksjonspunkter</span><small>Ikke bekreftet sendt ↗</small></a>
    <a href="/ai-studio.php" class="rr-home-stat"><strong>—</strong><span>Planlagte innslag</span><small>Ikke tilkoblet planstatus ↗</small></a>
  </section>
  <section class="rr-home-listeners" aria-labelledby="rr-home-listener-title">
    <div class="rr-home-panel-head"><h2 id="rr-home-listener-title">Lyttere og radiostatistikk</h2><a href="/listener-stats-preview.php">Se lytterstatistikk ↗</a></div>
    <div class="rr-home-stats rr-home-listener-cards">
      <a class="rr-home-stat" href="/listener-stats-preview.php"><strong>—</strong><span>Lyttere nå</span><small>Sanntid · ikke tilkoblet</small></a>
      <a class="rr-home-stat" href="/listener-stats-preview.php"><strong>—</strong><span>Unike lyttere</span><small>Siste 24 timer</small></a>
      <a class="rr-home-stat" href="/listener-stats-preview.php"><strong>—</strong><span>Lyttetimer (TTSL)</span><small>Siste 7 dager</small></a>
      <a class="rr-home-stat" href="/listener-stats-preview.php"><strong>—</strong><span>Topp samtidige</span><small>Siste 24 timer</small></a>
    </div>
  </section>
  <div class="rr-home-grid">
    <section class="rr-home-panel rr-home-editorial" aria-labelledby="rr-home-editor-title">
      <div class="rr-home-panel-head"><h2 id="rr-home-editor-title">Redaksjonell arbeidskø</h2><a href="/control.php">Åpne kontrollsenter ↗</a></div>
      <?php if ($boardError): ?><p class="rr-home-empty" role="status">Arbeidslisten er utilgjengelig. Ingen tall eller saker blir gjettet.</p>
      <?php elseif (!$entries): ?><p class="rr-home-empty">Ingen saker i den aktive arbeidslisten.</p>
      <?php else: ?><ul class="rr-home-case-list">
      <?php foreach (array_slice($entries, 0, 6) as $entry): ?>
      <li><a href="/control.php?item=<?= rawurlencode((string)($entry['id'] ?? '')) ?>#case-treatment">
        <span class="rr-home-case-icon" aria-hidden="true">▤</span>
        <span class="rr-home-case-text"><strong><?= escape((string)($entry['title'] ?? 'Uten tittel')) ?></strong><small><?= escape((string)($entry['sourceName'] ?? 'Kilde uavklart')) ?> · <?= ($entry['status'] ?? '') === 'ready' ? 'Redaksjonelt klar' : 'Utkast / til behandling' ?></small></span>
        <span aria-hidden="true">›</span>
      </a></li>
      <?php endforeach; ?></ul><?php endif; ?>
      <nav class="rr-home-quicklinks" aria-label="Redaksjonssnarveier"><a href="/newsdesk.php">Nyhetsdesk ↗</a><a href="/desk.php">Saksarbeid ↗</a><a href="/sending.php">Sendeliste ↗</a></nav>
    </section>
    <section class="rr-home-panel" aria-labelledby="rr-home-radio-title">
      <div class="rr-home-panel-head"><h2 id="rr-home-radio-title">Musikk og radiotid</h2><a href="/ai-studio.php">Gå i Studio ↗</a></div>
      <div class="rr-home-onair"><span class="rr-home-eyebrow">SPILLES NÅ</span><strong>Ikke tilkoblet</strong><small>Radio.co-status er ikke verifisert her. Ingen antatt livedata.</small></div>
      <div class="rr-home-tiles">
        <a href="/music-library.php"><span aria-hidden="true">♫</span><strong>Musikk</strong><small>Musikkbibliotek ↗</small></a>
        <a href="/music-plan.php"><span aria-hidden="true">◷</span><strong>Radiotid</strong><small>Musikk- og programplan ↗</small></a>
      </div>
      <p class="rr-home-system">Systemstatus: <strong>ikke kontrollert</strong> · Ingen publisering eller avspilling kan startes fra denne testsiden.</p>
    </section>
  </div>
  <p class="rr-home-foot">Forhåndsvisning RR-STUDIO-HOME-001. Tall gjelder kun lesbare aktive redaksjonspunkter. Klare punkter er ikke lik godkjent lyd eller faktisk sendte innslag.</p>
</main></div></div></body></html>
