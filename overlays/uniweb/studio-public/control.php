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
$extraStylesheet = '/assets/control.css?v=1';
require dirname(__DIR__) . '/studio-private/app/views/head.php';
?>
<div class="shell">
<?php $activePage = 'overview'; require dirname(__DIR__) . '/studio-private/app/views/sidebar.php'; ?>
<div class="workspace">
<header class="topbar"><span>Arbeidsrom / <strong>Kontrollsenter</strong></span><span>Radio Rubben Studio</span><?php require dirname(__DIR__) . '/studio-private/app/views/account.php'; ?></header>
<main id="main" class="control-page">
  <div class="control-heading"><div><p class="eyebrow">RADIO RUBBEN / ARBEIDSROM</p><h1>Kontrollsenter</h1><p class="control-intro">Dette er klart til sending og dette bør du se på nå.</p></div><a class="control-link" href="/newsdesk.php">Finn en sak ↗</a></div>
  <section class="control-status" aria-label="Studiostatus"><div><span class="status-led unknown" aria-hidden="true"></span><strong>På lufta: ikke tilkoblet</strong><small>Bekreft sending i avspillingssystemet.</small></div><div><span class="eyebrow">KLAR I SENDELISTEN</span><strong><?= $ready ?> punkt<?= $ready === 1 ? '' : 'er' ?></strong><small>Dette er redaksjonell status, ikke avspilling.</small></div><div><span class="eyebrow">TIL BEHANDLING</span><strong><?= count($items) - $ready ?> utkast</strong><small>Manus og kildekontroll.</small></div></section>
  <?php if (isset($boardUnavailable)): ?><p class="control-alert error" role="alert">Sendelisten kan ikke leses nå. Prøv igjen senere.</p><?php endif; ?>
  <div class="control-grid">
    <section class="control-panel control-primary" aria-labelledby="today-title"><div class="panel-top"><div><p class="eyebrow">DAGENS ARBEID</p><h2 id="today-title">Sendeliste</h2></div><a href="/sending.php">Åpne Sending ↗</a></div>
      <?php if (!$items): ?><p class="control-empty">Ingen punkter ennå. Velg lokalsaker i Nyhetsdesk, eller legg til et eget punkt i Sending.</p><?php endif; ?>
      <ol class="control-rundown"><?php foreach (array_slice($items, 0, 5) as $item): ?><li><span class="status-pill <?= escape($item['status']) ?>"><?= $item['status'] === 'ready' ? 'Klar' : 'Utkast' ?></span><a href="/sending.php?item=<?= escape($item['id']) ?>"><?= escape($item['title']) ?></a><small><?= escape($item['sourceName']) ?></small></li><?php endforeach; ?></ol>
      <?php if (count($items) > 5): ?><p class="control-muted">Og <?= count($items) - 5 ?> punkter til i Sending.</p><?php endif; ?>
    </section>
    <section class="control-panel" aria-labelledby="local-title"><div class="panel-top"><div><p class="eyebrow">BØMLO</p><h2 id="local-title">Lokalt nå</h2></div><a href="/newsdesk.php">Nyhetsdesk ↗</a></div>
      <?php if (!$local['items']): ?><p class="control-empty">Ingen ferske saker tilgjengelig fra kommunen.</p><?php endif; ?>
      <ul class="control-news"><?php foreach (array_slice($local['items'], 0, 3) as $story): ?><li><a href="<?= escape($story['url']) ?>" target="_blank" rel="noopener noreferrer"><?= escape($story['title']) ?> ↗</a></li><?php endforeach; ?></ul>
      <?php if ($local['status'] === 'stale'): ?><p class="control-muted">Eldre data – kontroller kilden.</p><?php endif; ?>
    </section>
    <section class="control-panel" aria-labelledby="service-title"><div class="panel-top"><div><p class="eyebrow">SUNNHORDLAND</p><h2 id="service-title">Vær og veg</h2></div><a href="/newsdesk.php">Se alle ↗</a></div>
      <?php if ($weather): ?><p class="control-temperature"><?= escape((string)$weather['temperature']) ?>° <span>Bremnes</span></p><p class="control-muted">MET Norge · prognose, kontroller før opplesing.</p><?php else: ?><p class="control-muted">Værdata er utilgjengelige.</p><?php endif; ?>
      <?php if ($traffic['items']): ?><ul class="control-news"><?php foreach (array_slice($traffic['items'], 0, 2) as $event): ?><li><?= escape($event['title']) ?></li><?php endforeach; ?></ul><?php else: ?><p class="control-muted"><?= $traffic['status'] === 'unavailable' ? 'Vegmeldinger er utilgjengelige.' : 'Ingen registrerte hendelser i området.' ?></p><?php endif; ?>
      <p class="control-muted">Planlagt arbeid kan vises her. Sjekk tid og status hos Vegvesenet.</p>
    </section>
    <section class="control-panel control-shortcuts" aria-labelledby="tools-title"><p class="eyebrow">SNARVEIER</p><h2 id="tools-title">Gå videre</h2><div><a href="/sending.php">Skriv manus og bygg sending <span>↗</span></a><a href="/ai-studio.php">Åpne AI Studio <span>↗</span></a><a href="/robot.php">Åpne fotballroboten <span>↗</span></a><a href="/workspace.php?section=social">Publisering og sosiale medier <span>↗</span></a></div></section>
  </div>
</main></div></div></body></html>
