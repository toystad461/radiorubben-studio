<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
$user = current_user();
if (!$user) redirect('/login.php');

require_once dirname(__DIR__) . '/app/board.php';
require dirname(__DIR__) . '/app/integrations/NewsDesk.php';
require dirname(__DIR__) . '/app/weather.php';
require_once dirname(__DIR__) . '/app/newsroom.php';
require_once dirname(__DIR__) . '/app/producer.php';
$canPrepare = studio_can($user, 'produce');
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) { http_response_code(405); header('Allow: GET, POST'); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canPrepare || !is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) { http_response_code(403); exit('Ingen tilgang.'); }
    $id = is_string($_POST['id'] ?? null) ? $_POST['id'] : '';
    try {
        if (!preg_match('/^[a-f0-9]{16}$/D', $id)) throw new InvalidArgumentException('Velg en gyldig sak.');
        $action = $_POST['action'] ?? '';
        if ($action === 'discard') {
            if (($_POST['confirmed'] ?? '') !== '1') throw new InvalidArgumentException('Bekreft at du vil forkaste saken.');
            $current = null;
            foreach (studio_board_active(studio_board_read()) as $row) if ($row['id'] === $id) $current = $row;
            if (!$current) throw new InvalidArgumentException('Saken finnes ikke lenger.');
            if (in_array($current['web']['delivery']['state'] ?? '', ['pending', 'unknown'], true))
                throw new InvalidArgumentException('Avklar nettoverføringen før du forkaster saken.');
            $_SESSION['control_undo'] = studio_board_clear((string)($_POST['snapshot'] ?? ''), $user, null, [$id]);
            $_SESSION['control_message'] = 'Saken er forkastet fra arbeidslisten. Manus og historikk er bevart.';
         } elseif ($action === 'prepare') {
            studio_newsroom_prepare($id, (int)($_POST['revision'] ?? 0), $user, $config);
            $_SESSION['control_message'] = 'Robåt har klargjort valgt bruksområde og kjørt kildekontroll. Les forslaget før godkjenning.';
        } elseif ($action === 'channel') {
            studio_board_update($id, (int)($_POST['revision'] ?? 0), 'channel', $_POST, $user);
            $_SESSION['control_message'] = 'Bruksområdet er lagret. Saken må sluttgodkjennes på nytt.';
        } elseif ($action === 'undo') {
            studio_board_undo_clear((string)($_SESSION['control_undo'] ?? ''), $user);
            unset($_SESSION['control_undo']);
            $_SESSION['control_message'] = 'Saken er gjenopprettet som utkast.';
        } else throw new InvalidArgumentException('Ukjent handling.');
    } catch (InvalidArgumentException $e) { $_SESSION['control_error'] = $e->getMessage(); }
    catch (Throwable $e) { $_SESSION['control_error'] = 'Kunne ikke lagre. Last siden på nytt og prøv igjen.'; error_log('Studio control operation failed.'); }
    redirect('/control.php?item=' . rawurlencode($id) . '#case-treatment');
}
$message = $_SESSION['control_message'] ?? null; $error = $_SESSION['control_error'] ?? null;
unset($_SESSION['control_message'], $_SESSION['control_error']);
try { $board = studio_board_read(); $items = studio_board_active($board); }
catch (Throwable $e) { error_log('Studio control board read failed: ' . $e->getMessage()); $items = []; $boardUnavailable = true; }
try { $local = newsdesk_feed('bomlo', newsdesk_sources()['bomlo'], dirname(__DIR__) . '/config'); }
catch (Throwable $e) { $local = ['status'=>'unavailable', 'items'=>[]]; }
try { $traffic = newsdesk_traffic(dirname(__DIR__) . '/config'); }
catch (Throwable $e) { $traffic = ['status'=>'unavailable', 'items'=>[]]; }
try { $weather = studio_weather(); } catch (Throwable $e) { $weather = null; }
$ready = count(array_filter($items, static fn($item) => $item['status'] === 'ready'));
$drafts = count($items) - $ready;
$nextDraft = null;
foreach ($items as $item) {
    if ($item['status'] !== 'ready') { $nextDraft = $item; break; }
}
$selectedId = is_string($_GET['item'] ?? null) ? $_GET['item'] : '';
$selected = null;
foreach ($items as $row) if ($row['id'] === $selectedId) $selected = $row;
if (!$selected && $items) $selected = $nextDraft ?? $items[0];
function control_fields(array $item): void { ?>
<input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>"><input type="hidden" name="id" value="<?= escape($item['id']) ?>"><input type="hidden" name="revision" value="<?= (int)$item['revision'] ?>">
<?php }
$extraStylesheet = '/assets/control.css?v=20261006';
require dirname(__DIR__) . '/app/views/head.php';
?>
<div class="shell">
<?php $activePage = 'overview'; require dirname(__DIR__) . '/app/views/sidebar.php'; ?>
<div class="workspace">
<header class="topbar"><span>Arbeidsrom / <strong>Kontrollsenter</strong></span><span>Radio Rubben Studio</span><?php require dirname(__DIR__) . '/app/views/account.php'; ?></header>
<main id="main" class="control-page control-dashboard">
  <header class="dashboard-heading"><div><p class="eyebrow">RADIO RUBBEN / STUDIO</p><h1>Studiooversikt</h1><p class="control-muted">Velg en sak, velg bruksområde og gjør den klar til din godkjenning.</p></div><div class="dashboard-heading-actions"><span class="dashboard-air-state"><span class="status-led unknown" aria-hidden="true"></span> Sendestatus ukjent</span><a class="dashboard-primary-link" href="/sending.php">Åpne Sending <span aria-hidden="true">↗</span></a></div></header>
  <?php if ($message): ?><p class="control-alert" role="status"><?= escape($message) ?></p><?php endif; ?>
  <?php if ($error): ?><p class="control-alert error" role="alert"><?= escape($error) ?></p><?php endif; ?>
  <?php if ($canPrepare && !empty($_SESSION['control_undo'])): ?><form method="post"><?php control_fields($selected ?? ['id'=>'0000000000000000', 'revision'=>0]); ?><button name="action" value="undo">Angre forkasting</button></form><?php endif; ?>
  <?php if (isset($boardUnavailable)): ?><p class="control-alert error" role="alert">Arbeidslisten kan ikke leses nå. Prøv igjen senere.</p><?php endif; ?>
  <section class="dashboard-priority" aria-label="Neste oppgave">
    <div><p class="eyebrow">NESTE OPPGAVE</p>
      <?php if (isset($boardUnavailable)): ?><strong>Kontroller arbeidslisten</strong><span>Listen er midlertidig utilgjengelig.</span>
      <?php elseif ($nextDraft): ?><strong><?= escape($nextDraft['title']) ?></strong><span>Utkast · velg radio, nett eller begge, og behandle saken nedenfor.</span>
      <?php elseif (!$items): ?><strong>Bygg neste sending</strong><span>Velg en sak fra Nyhetsdesk eller legg inn et eget punkt.</span>
      <?php else: ?><strong>Alle punktene er redaksjonelt klare</strong><span>Kontroller rekkefølgen før sending. Avspilling er ikke bekreftet her.</span><?php endif; ?>
    </div>
    <a href="<?= $nextDraft ? '/control.php?item=' . rawurlencode((string)$nextDraft['id']) . '#case-treatment' : ($items || isset($boardUnavailable) ? '/sending.php' : '/newsdesk.php') ?>"><?= $nextDraft ? 'Åpne utkast' : ($items || isset($boardUnavailable) ? 'Se sendelisten' : 'Finn saker') ?> <span aria-hidden="true">↗</span></a>
  </section>
  <nav class="dashboard-flow" aria-label="Produksjonsflyt">
    <a href="/newsdesk.php"><span class="flow-number">01</span><span><strong>Finn saker</strong><small>Lokalt, trafikk og vær</small></span><span aria-hidden="true">↗</span></a>
    <a href="/sending.php"><span class="flow-number">02</span><span><strong>Lag manus</strong><small><?= $drafts ?> utkast · <?= $ready ?> klar<?= $ready === 1 ? '' : 'e' ?></small></span><span aria-hidden="true">↗</span></a>
    <a href="/ai-studio.php"><span class="flow-number">03</span><span><strong>Gå i studio</strong><small>Lyd og produksjon</small></span><span aria-hidden="true">↗</span></a>
  </nav>
  <div class="dashboard-columns">
    <section class="control-panel dashboard-rundown" aria-labelledby="today-title">
      <div class="panel-top"><div><p class="eyebrow">REDAKSJON</p><h2 id="today-title">Saker til behandling <span class="dashboard-count"><?= count($items) ?></span></h2></div><a href="/desk.php">Nyhetsdesk ↗</a></div>
      <?php if (!$items): ?><p class="control-empty">Listen er tom. Velg en sak i Nyhetsdesk eller legg til et punkt i Sending.</p><?php endif; ?>
      <ol class="control-rundown"><?php foreach ($items as $position=>$item): ?><li class="<?= $selected && $selected['id'] === $item['id'] ? 'selected' : '' ?>"><span class="dashboard-position"><?= $position + 1 ?></span><a href="/control.php?item=<?= escape($item['id']) ?>#case-treatment" <?= $selected && $selected['id'] === $item['id'] ? 'aria-current="true"' : '' ?>><strong><?= escape($item['title']) ?></strong><small><?= escape($item['sourceName']) ?> · <?= ['radio'=>'Radio', 'web'=>'Nett', 'both'=>'Radio og nett'][studio_board_channel($item)] ?></small></a><span class="status-pill <?= escape($item['status']) ?>"><?= $item['status'] === 'ready' ? 'Klar' : 'Utkast' ?></span></li><?php endforeach; ?></ol>
    </section>
    <section class="control-panel dashboard-treatment" id="case-treatment" aria-labelledby="treatment-title">
      <p class="eyebrow">VALGT SAK</p><h2 id="treatment-title"><?= $selected ? escape($selected['title']) : 'Velg en sak til venstre' ?></h2>
      <?php if ($selected): $channel = studio_board_channel($selected); ?>
      <p class="control-muted"><?= escape($selected['sourceName']) ?></p>
      <?php if ($selected['sourceUrl']): ?><p><a href="<?= escape($selected['sourceUrl']) ?>" target="_blank" rel="noopener noreferrer">Les originalkilden ↗</a></p><?php endif; ?>
      <?php if ($selected['summary']): ?><p><?= escape($selected['summary']) ?></p><?php endif; ?>
      <?php if ($canPrepare): ?><form method="post" class="editor-form"><?php control_fields($selected); ?>
        <label for="case-channel">Bruksområde</label><select id="case-channel" name="channel">
          <?php foreach (['radio'=>'Radiomateriale', 'web'=>'Nettmateriale', 'both'=>'Radio og nett'] as $value=>$label): ?><option value="<?= $value ?>" <?= $channel === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
        </select><button name="action" value="channel">Lagre bruksområde</button>
      </form><?php else: ?><p>Bruksområde: <?= ['radio'=>'Radiomateriale', 'web'=>'Nettmateriale', 'both'=>'Radio og nett'][$channel] ?></p><?php endif; ?>
      <?php if ($canPrepare && !empty($selected['originId']) && studio_news_allowed_url((string)$selected['sourceUrl'])): ?><form method="post" class="editor-form"><?php control_fields($selected); ?><button name="action" value="prepare">Robåt: klargjør og kontroller</button><p class="control-muted">Lagre bruksområdet først. Henter originalen og lager manglende forslag for valgt kanal. Eksisterende radiomanus beholdes og kontrolleres.</p></form><?php endif; ?>
      <?php if (parse_url((string)$selected['sourceUrl'], PHP_URL_HOST) === 'www.nrk.no'): ?><p class="dashboard-source-credit">Basert på opplysninger fra NRK. <a href="<?= escape($selected['sourceUrl']) ?>" target="_blank" rel="noopener noreferrer">Les hele saken hos NRK ↗</a></p><?php endif; ?>
      <?php if ($channel !== 'web' && trim((string)$selected['script']) !== ''): ?><details open class="dashboard-proposal"><summary>Forslag til radiomanus</summary><?php foreach (studio_news_reading_paragraphs($selected['script']) as $paragraph): ?><p><?= escape($paragraph) ?></p><?php endforeach; ?></details><?php endif; ?>
      <?php if ($channel !== 'radio' && !empty($selected['web']['body'])): ?><details open class="dashboard-proposal"><summary>Forslag til nettsak</summary><h3><?= escape($selected['web']['title'] ?? '') ?></h3><p><strong><?= escape($selected['web']['intro'] ?? '') ?></strong></p><?php foreach (studio_news_reading_paragraphs($selected['web']['body']) as $paragraph): ?><p><?= escape($paragraph) ?></p><?php endforeach; ?></details><?php endif; ?>
      <div class="dashboard-treatment-actions">
      <?php if ($channel !== 'web'): ?><p>Radio: <?= $selected['status'] === 'ready' ? 'Godkjent til sending' : 'Utkast – må kontrolleres og godkjennes' ?></p><a class="control-link" href="/case.php?item=<?= escape($selected['id']) ?>#radio-material">Behandle radiomanus ↗</a><?php endif; ?>
      <?php if ($channel !== 'radio'): ?><p>Nett: <?= escape(studio_web_status_label($selected)) ?></p><a class="control-link" href="/case.php?item=<?= escape($selected['id']) ?>#web-material">Behandle nettsak ↗</a><?php endif; ?>
      </div><p class="control-muted">Kanalvalg publiserer ingenting. Les teksten og kontrollrapporten før sluttgodkjenning.</p>
      <?php if ($canPrepare): ?><details class="source-summary"><summary>Forkast saken</summary><p>Saken arkiveres fra arbeidslisten. Manus og historikk beholdes. En allerede publisert nettsak trekkes ikke tilbake.</p><form method="post" class="editor-form"><?php control_fields($selected); ?><input type="hidden" name="snapshot" value="<?= escape(studio_board_snapshot($board)) ?>"><label class="verify-row"><input type="checkbox" name="confirmed" value="1" required> Jeg vil forkaste denne saken</label><button class="quiet" name="action" value="discard">Forkast sak</button></form></details><?php endif; ?>
      <?php else: ?><p class="control-empty">Velg en sak i Nyhetsdesk for å starte.</p><?php endif; ?>
    </section>
  </div>
  <div class="dashboard-bottom">
    <section class="control-panel dashboard-weather" aria-labelledby="weather-title"><div><p class="eyebrow">BREMNES / MET NORGE</p><h2 id="weather-title">Vær</h2></div><?php if ($weather): ?><p class="control-temperature"><?= escape((string)$weather['temperature']) ?>°</p><p class="control-muted">Prognose · <?= escape((string)$weather['time']) ?></p><?php else: ?><p class="control-muted">Værdata utilgjengelige.</p><?php endif; ?><a href="/newsdesk.php">Se kilde og detaljer ↗</a></section>
    <section class="control-panel dashboard-traffic" aria-labelledby="traffic-title"><div class="panel-top"><div><p class="eyebrow">STATENS VEGVESEN</p><h2 id="traffic-title">På vegen</h2></div><a href="/newsdesk.php">Alle meldinger ↗</a></div><?php if ($traffic['items']): ?><ul class="control-news"><?php foreach (array_slice($traffic['items'], 0, 2) as $event): ?><li><?= escape($event['title']) ?></li><?php endforeach; ?></ul><?php else: ?><p class="control-muted"><?= $traffic['status'] === 'unavailable' ? 'Vegmeldinger utilgjengelige.' : 'Ingen registrerte hendelser i området.' ?></p><?php endif; ?><p class="dashboard-caution">Kontroller tidspunkt og status før omtale.</p></section>
    <section class="control-panel dashboard-tools" aria-labelledby="tools-title"><p class="eyebrow">PRODUSER VIDERE</p><h2 id="tools-title">Verktøy</h2><div><a href="/robot.php">Artikkelutkast <span>↗</span></a><a href="/ai-studio.php#music-title">Musikk og lyd <span>↗</span></a><a href="/workspace.php?section=social">Sosiale medier <span>↗</span></a></div></section>
  </div>
  <p class="dashboard-footnote">«Klar» er redaksjonell status. Studio bekrefter ikke om lyden faktisk er på lufta.</p>
</main></div></div></body></html>
