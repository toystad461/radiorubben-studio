<?php
declare(strict_types=1);
require dirname(__DIR__) . '/studio-private/app/bootstrap.php';
$user = current_user();
if (!$user) redirect('/login.php');
if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); header('Allow: GET'); exit; }
require dirname(__DIR__) . '/studio-private/app/board.php';
require dirname(__DIR__) . '/studio-private/app/integrations/NewsDesk.php';
require dirname(__DIR__) . '/studio-private/app/weather.php';
try { $board = studio_board_read(); $items = studio_board_active($board); }
catch (Throwable $e) { error_log('Studio control board read failed: ' . $e->getMessage()); $items = []; $boardUnavailable = true; }
try { $local = newsdesk_feed('bomlo', newsdesk_sources()['bomlo'], dirname(__DIR__) . '/studio-private/config'); }
catch (Throwable $e) { $local = ['status'=>'unavailable', 'items'=>[]]; }
try { $traffic = newsdesk_traffic(dirname(__DIR__) . '/studio-private/config'); }
catch (Throwable $e) { $traffic = ['status'=>'unavailable', 'items'=>[]]; }
try { $weather = studio_weather(); } catch (Throwable $e) { $weather = null; }
$ready = count(array_filter($items, static fn($item) => $item['status'] === 'ready'));
$drafts = count($items) - $ready;
$extraStylesheet = '/assets/control.css?v=3';
require dirname(__DIR__) . '/studio-private/app/views/head.php';
?>
<div class="shell">
<?php $activePage = 'overview'; require dirname(__DIR__) . '/studio-private/app/views/sidebar.php'; ?>
<div class="workspace">
<header class="topbar"><span>Arbeidsrom / <strong>Kontrollsenter</strong></span><span>Radio Rubben Studio</span><?php require dirname(__DIR__) . '/studio-private/app/views/account.php'; ?></header>
<main id="main" class="control-page control-dashboard">
  <header class="dashboard-heading"><div><p class="eyebrow">RADIO RUBBEN / STUDIO</p><h1>Studiooversikt</h1><p class="control-muted">Arbeidsflyten for neste sending.</p></div><div class="dashboard-heading-actions"><span class="dashboard-air-state"><span class="status-led unknown" aria-hidden="true"></span> Sendestatus ukjent</span><a class="dashboard-primary-link" href="/sending.php">Åpne Sending <span aria-hidden="true">↗</span></a></div></header>
  <?php if (isset($boardUnavailable)): ?><p class="control-alert error" role="alert">Sendelisten kan ikke leses nå. Prøv igjen senere.</p><?php endif; ?>
  <nav class="dashboard-flow" aria-label="Produksjonsflyt">
    <a href="/newsdesk.php"><span class="flow-number">01</span><span><strong>Finn saker</strong><small>Lokalt, trafikk og vær</small></span><span aria-hidden="true">↗</span></a>
    <a href="/sending.php"><span class="flow-number">02</span><span><strong>Lag manus</strong><small><?= $drafts ?> utkast · <?= $ready ?> klar<?= $ready === 1 ? '' : 'e' ?></small></span><span aria-hidden="true">↗</span></a>
    <a href="/ai-studio.php"><span class="flow-number">03</span><span><strong>Gå i studio</strong><small>Lyd og produksjon</small></span><span aria-hidden="true">↗</span></a>
  </nav>
  <div class="dashboard-columns">
    <section class="control-panel dashboard-rundown" aria-labelledby="today-title">
      <div class="panel-top"><div><p class="eyebrow">NESTE SENDING</p><h2 id="today-title">Sendeliste <span class="dashboard-count"><?= count($items) ?></span></h2></div><a href="/sending.php">Hele listen ↗</a></div>
      <?php if (!$items): ?><p class="control-empty">Listen er tom. Velg en sak i Nyhetsdesk eller legg til et punkt i Sending.</p><?php endif; ?>
      <ol class="control-rundown"><?php foreach (array_slice($items, 0, 5) as $position=>$item): ?><li><span class="dashboard-position"><?= $position + 1 ?></span><a href="/sending.php?item=<?= escape($item['id']) ?>"><strong><?= escape($item['title']) ?></strong><small><?= escape($item['sourceName']) ?></small></a><span class="status-pill <?= escape($item['status']) ?>"><?= $item['status'] === 'ready' ? 'Klar' : 'Utkast' ?></span></li><?php endforeach; ?></ol>
      <?php if (count($items) > 5): ?><a class="dashboard-more" href="/sending.php">Vis <?= count($items) - 5 ?> flere punkter ↗</a><?php endif; ?>
    </section>
    <section class="control-panel dashboard-local" aria-labelledby="local-title">
      <div class="panel-top"><div><p class="eyebrow">KILDESTRØM / BØMLO</p><h2 id="local-title">Lokale saker</h2></div><a href="/newsdesk.php">Nyhetsdesk ↗</a></div>
      <?php if (!$local['items']): ?><p class="control-empty">Ingen kommunesaker tilgjengelig akkurat nå.</p><?php endif; ?>
      <ul class="control-news"><?php foreach (array_slice($local['items'], 0, 3) as $story): ?><li><a href="/newsdesk.php"><?= escape($story['title']) ?> <span aria-hidden="true">↗</span></a></li><?php endforeach; ?></ul>
      <?php if ($local['status'] === 'stale'): ?><p class="dashboard-caution">Eldre data. Kontroller originalkilden.</p><?php endif; ?>
    </section>
  </div>
  <div class="dashboard-bottom">
    <section class="control-panel dashboard-weather" aria-labelledby="weather-title"><div><p class="eyebrow">BREMNES / MET NORGE</p><h2 id="weather-title">Vær</h2></div><?php if ($weather): ?><p class="control-temperature"><?= escape((string)$weather['temperature']) ?>°</p><p class="control-muted">Prognose · <?= escape((string)$weather['time']) ?></p><?php else: ?><p class="control-muted">Værdata utilgjengelige.</p><?php endif; ?><a href="/newsdesk.php">Se kilde og detaljer ↗</a></section>
    <section class="control-panel dashboard-traffic" aria-labelledby="traffic-title"><div class="panel-top"><div><p class="eyebrow">STATENS VEGVESEN</p><h2 id="traffic-title">På vegen</h2></div><a href="/newsdesk.php">Alle meldinger ↗</a></div><?php if ($traffic['items']): ?><ul class="control-news"><?php foreach (array_slice($traffic['items'], 0, 2) as $event): ?><li><?= escape($event['title']) ?></li><?php endforeach; ?></ul><?php else: ?><p class="control-muted"><?= $traffic['status'] === 'unavailable' ? 'Vegmeldinger utilgjengelige.' : 'Ingen registrerte hendelser i området.' ?></p><?php endif; ?><p class="dashboard-caution">Kontroller tidspunkt og status før omtale.</p></section>
    <section class="control-panel dashboard-tools" aria-labelledby="tools-title"><p class="eyebrow">PRODUSER VIDERE</p><h2 id="tools-title">Verktøy</h2><div><a href="/robot.php">Artikkelutkast <span>↗</span></a><a href="/ai-studio.php#music-title">Musikk og lyd <span>↗</span></a><a href="/workspace.php?section=social">Sosiale medier <span>↗</span></a></div></section>
  </div>
  <p class="dashboard-footnote">«Klar» er redaksjonell status. Studio bekrefter ikke om lyden faktisk er på lufta.</p>
</main></div></div></body></html>
