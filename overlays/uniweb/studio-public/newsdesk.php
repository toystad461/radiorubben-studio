<?php
declare(strict_types=1);
require dirname(__DIR__) . '/studio-private/app/bootstrap.php';
$user = current_user();
if (!$user) redirect('/login.php');
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); header('Allow: GET, POST'); exit;
}
require dirname(__DIR__) . '/studio-private/app/integrations/NewsDesk.php';
require dirname(__DIR__) . '/studio-private/app/weather.php';
require dirname(__DIR__) . '/studio-private/app/board.php';
$feeds = newsdesk_all(dirname(__DIR__) . '/studio-private/config');
$traffic = newsdesk_traffic(dirname(__DIR__) . '/studio-private/config');
$all = [];
foreach ($feeds as $feed) foreach ($feed['items'] as $item) $all[$item['id']] = $item;
foreach ($traffic['items'] as $item) $all[$item['id']] = $item;
$canPrepare = studio_can($user, 'produce');
function newsdesk_local_time(string $value): string
{
    return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('Europe/Oslo'))->format('d.m.Y H:i');
}
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['export'] ?? null) === '1') redirect('/sending.php?export=1');
// Carry over selections made in the previous session-only desk once.
if ($canPrepare && is_array($_SESSION['newsdesk_rundown'] ?? null)) {
    try {
        foreach ($_SESSION['newsdesk_rundown'] as $oldId=>$oldItem) {
            if (isset($all[$oldId])) studio_board_add_source($all[$oldId], $user);
            elseif (is_array($oldItem) && ($oldItem['id'] ?? null) === $oldId) studio_board_add_source($oldItem, $user);
        }
        unset($_SESSION['newsdesk_rundown']);
    } catch (Throwable $e) { error_log('Newsdesk migration failed: ' . $e->getMessage()); }
}
try { $boardItems = studio_board_active(studio_board_read()); }
catch (Throwable $e) { error_log('Newsdesk board read failed: ' . $e->getMessage()); $boardItems = []; $boardUnavailable = true; }
$rundown = [];
foreach ($boardItems as $item) if ($item['originId']) $rundown[$item['originId']] = $item;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        http_response_code(403); exit('Ugyldig forespørsel. Last siden på nytt.');
    }
    if (!$canPrepare) { http_response_code(403); exit('Rollen kan bare lese nyhetsdesken.'); }
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $id = is_string($_POST['id'] ?? null) ? $_POST['id'] : '';
    if ($action === 'add' && isset($all[$id])) {
        try { studio_board_add_source($all[$id], $user); }
        catch (Throwable $e) { error_log('Newsdesk add failed: ' . $e->getMessage()); $_SESSION['newsdesk_error'] = 'Kunne ikke legge til saken. Kontroller sendelisten.'; }
    }
    if ($action === 'article' && isset($all[$id]) && ($all[$id]['source'] ?? '') !== 'vegvesen') {
        try {
            studio_board_add_source($all[$id], $user);
            foreach (studio_board_active(studio_board_read()) as $entry)
                if (($entry['originId'] ?? null) === $id) redirect('/article.php?item=' . $entry['id']);
            throw new RuntimeException('Saken ble ikke funnet etter lagring.');
        } catch (Throwable $e) {
            error_log('Newsdesk article start failed: ' . $e->getMessage());
            $_SESSION['newsdesk_error'] = 'Kunne ikke åpne artikkelutkastet. Kontroller sendelisten.';
        }
    }
    redirect('/newsdesk.php');
}
try { $weather = studio_weather(); } catch (Throwable $e) { $weather = null; }
$extraStylesheet = '/assets/newsdesk.css?v=3';
require dirname(__DIR__) . '/studio-private/app/views/head.php';
?>
<div class="shell">
<?php $activePage = 'newsdesk'; require dirname(__DIR__) . '/studio-private/app/views/sidebar.php'; ?>
<div class="workspace">
<header class="topbar"><span>Arbeidsrom / <strong>Nyhetsdesk</strong></span><span>Kilder til sending</span><?php require dirname(__DIR__) . '/studio-private/app/views/account.php'; ?></header>
<main id="main" class="newsdesk">
  <div class="heading-row"><div><p class="eyebrow">RADIO RUBBEN / REDAKSJON</p><h1>Nyhetsdesk</h1><p class="intro">Finn saker til sendingen. Åpne originalen og kontroller fakta før du leser noe på lufta.</p></div><a class="desk-refresh" href="/newsdesk.php">Oppdater visning</a></div>
  <?php if (isset($_SESSION['newsdesk_error'])): ?><p class="desk-empty" role="alert"><?= escape($_SESSION['newsdesk_error']) ?></p><?php unset($_SESSION['newsdesk_error']); endif; ?>
  <div class="desk-grid">
    <div class="desk-main">
      <?php foreach (newsdesk_sources() as $sourceId=>$spec): $feed = $feeds[$sourceId]; ?>
      <section class="desk-panel" aria-labelledby="source-<?= escape($sourceId) ?>">
        <div class="desk-panel-head"><div><p class="eyebrow"><?= $sourceId === 'bomlo' ? 'LOKALT' : 'NORGE' ?></p><h2 id="source-<?= escape($sourceId) ?>"><?= escape($spec['name']) ?></h2></div><span class="desk-state <?= escape($feed['status']) ?>"><?= match ($feed['status']) { 'updated'=>'Oppdatert', 'stale'=>'Eldre data', default=>'Utilgjengelig' } ?></span></div>
        <?php if ($feed['fetchedAt']): ?><p class="desk-meta">Hentet <?= escape(newsdesk_local_time($feed['fetchedAt'])) ?> norsk tid · Kontroller originalkilden</p><?php endif; ?>
        <?php if (!$feed['items']): ?><p class="desk-empty">Ingen saker tilgjengelig fra denne kilden nå. Andre kilder vises fortsatt.</p><?php endif; ?>
        <div class="desk-stories">
        <?php foreach ($feed['items'] as $index=>$item): ?>
          <?php if ($index === 3): ?><details class="desk-more"><summary>Vis <?= count($feed['items']) - 3 ?> flere saker</summary><?php endif; ?>
          <article class="desk-story">
            <p class="desk-meta"><?= escape($spec['name']) ?> · Publisert <?= escape(newsdesk_local_time($item['publishedAt'])) ?> norsk tid</p>
            <h3><?= escape($item['title']) ?></h3>
            <?php if ($item['summary']): ?><details class="desk-description"><summary>Kort omtale</summary><p><?= escape($item['summary']) ?></p></details><?php endif; ?>
            <div class="desk-actions"><a href="<?= escape($item['url']) ?>" target="_blank" rel="noopener noreferrer">Les originalen ↗</a>
            <?php if ($canPrepare && !isset($rundown[$item['id']])): ?><form method="post"><input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="add"><input type="hidden" name="id" value="<?= escape($item['id']) ?>"><button type="submit">Legg i sendeliste</button></form><?php endif; ?>
            <?php if ($canPrepare): ?><form method="post"><input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="article"><input type="hidden" name="id" value="<?= escape($item['id']) ?>"><button type="submit">Lag artikkelutkast</button></form><?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
        <?php if (count($feed['items']) > 3): ?></details><?php endif; ?>
        </div>
      </section>
      <?php endforeach; ?>
    </div>
    <aside class="desk-side" aria-label="Sendeforberedelse">
      <section class="desk-panel" aria-labelledby="traffic-title"><div class="desk-panel-head"><div><p class="eyebrow">STATENS VEGVESEN / SUNNHORDLAND</p><h2 id="traffic-title">Trafikk</h2></div><span class="desk-state <?= escape($traffic['status']) ?>"><?= match ($traffic['status']) { 'updated'=>'Oppdatert', 'stale'=>'Eldre data', default=>'Utilgjengelig' } ?></span></div>
      <?php if ($traffic['fetchedAt']): ?><p class="desk-meta">Hentet <?= escape(newsdesk_local_time($traffic['fetchedAt'])) ?> norsk tid · WFS/GeoJSON</p><?php endif; ?>
      <?php if ($traffic['status'] === 'unavailable'): ?><p>Trafikkmeldinger kunne ikke hentes nå. Kontroller Vegvesen trafikk direkte.</p>
      <?php elseif (!$traffic['items']): ?><p>Ingen registrerte hendelser i valgt område akkurat nå.</p>
      <?php else: ?><div class="desk-stories">
      <?php foreach ($traffic['items'] as $index=>$item): ?>
      <?php if ($index === 3): ?><details class="desk-more"><summary>Vis <?= count($traffic['items']) - 3 ?> flere meldinger</summary><?php endif; ?>
      <article class="desk-story"><p class="desk-meta">Oppdatert <?= escape(newsdesk_local_time($item['publishedAt'])) ?> norsk tid</p><h3><?= escape($item['title']) ?></h3><details class="desk-description"><summary>Les trafikkmeldingen</summary><p><?= escape($item['summary']) ?></p></details><div class="desk-actions"><?php if ($canPrepare && !isset($rundown[$item['id']])): ?><form method="post"><input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="add"><input type="hidden" name="id" value="<?= escape($item['id']) ?>"><button type="submit">Legg i sendeliste</button></form><?php endif; ?></div></article>
      <?php endforeach; ?>
      <?php if (count($traffic['items']) > 3): ?></details><?php endif; ?>
      </div><?php endif; ?>
      <p class="desk-meta">Meldingene kan gjelde planlagt arbeid. Kontroller tid og status i originalkilden før sending.</p><a href="https://www.vegvesen.no/trafikk/" target="_blank" rel="noopener noreferrer">Åpne Vegvesen trafikk ↗</a></section>
      <div class="desk-tools">
      <section class="desk-panel"><p class="eyebrow">BØMLO / BREMNES</p><h2>Vær</h2>
      <?php if ($weather): ?><p class="desk-weather"><?= escape((string)$weather['temperature']) ?>°</p><p>Prognose: <?= escape(str_replace('_', ' ', (string)$weather['symbol'])) ?></p><p class="desk-meta">MET Norge · prognosetid <?= escape((string)$weather['time']) ?></p><a href="https://www.met.no/" target="_blank" rel="noopener noreferrer">Kilde: MET Norge ↗</a>
      <?php else: ?><p>Værprognosen er utilgjengelig. Ikke bruk gamle tall som dagens vær.</p><?php endif; ?>
      </section>
      <section class="desk-panel" aria-labelledby="rundown-title"><p class="eyebrow">FELLES ARBEID</p><h2 id="rundown-title">Sendeliste <span class="desk-count"><?= count($boardItems) ?>/30</span></h2><p class="desk-meta">Lagret for medarbeiderne. Manus og kildekontroll gjøres i Sending. Ingen sak sendes automatisk.</p>
      <?php if (isset($boardUnavailable)): ?><p class="desk-empty">Sendelisten er utilgjengelig akkurat nå.</p><?php elseif (!$boardItems): ?><p class="desk-empty">Velg saker fra kildene eller opprett et eget punkt.</p><?php endif; ?>
      <ol class="desk-rundown"><?php foreach (array_slice($boardItems, 0, 5) as $item): ?><li><strong><?= escape($item['title']) ?></strong><span class="desk-meta"><?= escape($item['sourceName']) ?> · <?= $item['status'] === 'ready' ? 'Klar' : 'Utkast' ?></span><a href="/sending.php?item=<?= escape($item['id']) ?>">Åpne i Sending ↗</a></li><?php endforeach; ?></ol>
      <p><a href="/sending.php">Åpne hele sendelisten ↗</a></p>
      </section>
      </div>
    </aside>
  </div>
</main></div></div></body></html>
