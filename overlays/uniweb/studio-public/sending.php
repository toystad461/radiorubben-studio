<?php
declare(strict_types=1);
require dirname(__DIR__) . '/studio-private/app/bootstrap.php';
$user = current_user();
if (!$user) redirect('/login.php');
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) {
    http_response_code(405); header('Allow: GET, POST'); exit;
}
require dirname(__DIR__) . '/studio-private/app/board.php';
require dirname(__DIR__) . '/studio-private/app/story-script.php';
require dirname(__DIR__) . '/studio-private/app/editorial-memory.php';
require dirname(__DIR__) . '/studio-private/app/producer.php';
$canPrepare = studio_can($user, 'produce');
function sending_time(?string $value): string
{
    if (!$value) return 'Ukjent tidspunkt';
    try { return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('Europe/Oslo'))->format('d.m H:i'); }
    catch (Throwable $e) { return 'Ukjent tidspunkt'; }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        http_response_code(403); exit('Ugyldig forespørsel. Last siden på nytt.');
    }
    if (!$canPrepare) { http_response_code(403); exit('Rollen kan bare lese sendelisten.'); }
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $id = is_string($_POST['id'] ?? null) ? $_POST['id'] : '';
    try {
        if ($action === 'add') studio_board_add_manual((string)($_POST['title'] ?? ''), $user);
        elseif (in_array($action, ['generate', 'recheck'], true)) {
            $revision = (int)($_POST['revision'] ?? 0);
            $sourceItem = null;
            foreach (studio_board_active(studio_board_read()) as $candidate) {
                if (($candidate['id'] ?? null) === $id) { $sourceItem = $candidate; break; }
            }
            if (!$sourceItem || ($sourceItem['revision'] ?? 0) !== $revision)
                throw new InvalidArgumentException('Punktet er endret. Last siden på nytt før du genererer manus.');
            $editorial = studio_memory_context(studio_board_read(), (string)($sourceItem['program'] ?? ''));
            if (studio_news_allowed_url((string)$sourceItem['sourceUrl']) || $action === 'recheck') {
                // Invalidate the old result before network work, including on timeout/failure.
                studio_board_update($id, $revision, 'source_checked', ['sourceCheck'=>['status'=>'checking']], $user);
                $revision++;
                $result = studio_news_prepare($sourceItem, $config, $editorial,
                    $action === 'recheck' ? (string)$sourceItem['script'] : null);
                if ($action === 'recheck') {
                    studio_board_update($id, $revision, 'source_checked', ['sourceCheck'=>$result['check']], $user);
                } else {
                    studio_board_update($id, $revision, 'generated', ['script'=>$result['script'],
                        'generation'=>['model'=>$config['openai_model'], 'editorial'=>$editorial],
                        'sourceCheck'=>$result['check']], $user);
                }
                $_SESSION['sending_message'] = $result['check']['status'] === 'passed'
                    ? 'AI-kontrollen fant kildebelegg. Les manus og kontrollrapport før du godkjenner for sending.'
                    : 'Manuset har kildeavvik eller usikkerheter. Se kontrollrapporten og rett teksten før sending.';
            } else {
                $script = studio_story_script_generate($sourceItem, $config, null, $editorial);
                studio_board_update($id, $revision, 'generated', ['script'=>$script,
                    'generation'=>['model'=>$config['openai_model'], 'editorial'=>$editorial]], $user);
                $_SESSION['sending_message'] = 'Utkast fra kildeomtale laget. Automatisk originalkontroll er ikke utført for denne kilden.';
            }
        } else {
            if (!in_array($action, ['save', 'ready', 'draft', 'up', 'down', 'archive'], true))
                throw new InvalidArgumentException('Ukjent handling.');
            studio_board_update($id, (int)($_POST['revision'] ?? 0), $action, $_POST, $user);
        }
        if (!in_array($action, ['generate', 'recheck'], true)) $_SESSION['sending_message'] = 'Sendelisten er oppdatert.';
    } catch (InvalidArgumentException $e) {
        $_SESSION['sending_error'] = $e->getMessage();
    } catch (RuntimeException $e) {
        error_log('Studio sending save failed: ' . $e->getMessage());
        $_SESSION['sending_error'] = in_array($action, ['generate', 'recheck'], true)
            ? 'Manus eller kildekontroll kunne ikke fullføres. Kilden eller AI-tjenesten er utilgjengelig, teksten kan ikke leses, eller tjenesten er ikke konfigurert. Ingen ny kontroll er godkjent.'
            : 'Kunne ikke lagre. Prøv igjen litt senere.';
    } catch (Throwable $e) {
        error_log('Studio sending save failed: ' . $e->getMessage());
        $_SESSION['sending_error'] = 'Kunne ikke lagre. Prøv igjen litt senere.';
    }
    redirect('/sending.php' . ($id !== '' && preg_match('/^[a-f0-9]{16}$/D', $id) ? '?item=' . $id : ''));
}
try { $board = studio_board_read(); }
catch (Throwable $e) { error_log('Studio sending read failed: ' . $e->getMessage()); $board = ['items'=>[]]; $readError = true; }
$items = studio_board_active($board);
$selectedId = is_string($_GET['item'] ?? null) ? $_GET['item'] : '';
$selected = null;
foreach ($items as $item) if ($item['id'] === $selectedId) $selected = $item;
if (!$selected && $items) $selected = $items[0];
$readyCount = count(array_filter($items, static fn($item) => $item['status'] === 'ready'));
$message = $_SESSION['sending_message'] ?? null; $error = $_SESSION['sending_error'] ?? null;
unset($_SESSION['sending_message'], $_SESSION['sending_error']);
if (($_GET['export'] ?? null) === '1' && $canPrepare) {
    if (isset($readError)) { http_response_code(503); exit('Sendelisten kan ikke leses nå. Prøv igjen senere.'); }
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="RadioRubben-sendeliste-' . gmdate('Ymd-Hi') . '.txt"');
    echo "RADIO RUBBEN / SENDELISTE\nLaget " . sending_time(gmdate('c')) . " norsk tid\n";
    echo "Kontroller kilder og tidspunkt før sending.\n\n";
    foreach ($items as $index=>$item) {
        echo ($index + 1) . '. ' . $item['title'] . ' [' . ($item['status'] === 'ready' ? 'KLAR' : 'UTKAST') . "]\n";
        echo $item['script'] . "\n" . $item['sourceName'] . ' · ' . sending_time($item['sourceAt']) . "\n";
        if ($item['sourceUrl']) echo str_replace(["\r", "\n"], '', $item['sourceUrl']) . "\n";
        echo "\n";
    }
    exit;
}
$extraStylesheet = '/assets/control.css?v=2';
require dirname(__DIR__) . '/studio-private/app/views/head.php';
?>
<div class="shell">
<?php $activePage = 'sending'; require dirname(__DIR__) . '/studio-private/app/views/sidebar.php'; ?>
<div class="workspace">
<header class="topbar"><span>Arbeidsrom / <strong>Sending</strong></span><span>Redaksjonell sendeliste</span><?php require dirname(__DIR__) . '/studio-private/app/views/account.php'; ?></header>
<main id="main" class="control-page">
  <div class="control-heading"><div><p class="eyebrow">RADIO RUBBEN / PRODUKSJON</p><h1>Sending</h1><p class="control-intro">Felles sendeliste for medarbeiderne. Ingen punkter sendes automatisk på lufta.</p></div><div class="control-heading-actions"><a class="control-link" href="/newsdesk.php">Finn saker ↗</a><?php if ($canPrepare && $items): ?><a class="control-link" href="/sending.php?export=1">Last ned sendeliste</a><?php endif; ?></div></div>
  <?php if ($message): ?><p class="control-alert" role="status"><?= escape($message) ?></p><?php endif; ?>
  <?php if ($error || isset($readError)): ?><p class="control-alert error" role="alert"><?= escape($error ?? 'Sendelisten er utilgjengelig. Prøv igjen senere.') ?></p><?php endif; ?>
  <div class="control-stats"><span><strong><?= count($items) ?></strong> punkter</span><span><strong><?= $readyCount ?></strong> klare</span><span><strong><?= count($items) - $readyCount ?></strong> utkast</span></div>
  <div class="sending-layout">
    <section class="control-panel sending-list" aria-labelledby="sending-list-title"><div class="panel-top"><h2 id="sending-list-title">Rekkefølge</h2><span class="control-muted">Felles arbeidsliste</span></div>
      <?php if (!$items): ?><p class="control-empty">Listen er tom. Legg til en sak fra Nyhetsdesk eller opprett et eget punkt.</p><?php endif; ?>
      <ol class="sending-items"><?php foreach ($items as $position=>$item): ?><li><a class="sending-item <?= $selected && $selected['id'] === $item['id'] ? 'selected' : '' ?>" href="/sending.php?item=<?= escape($item['id']) ?>"><span class="sending-position"><?= $position + 1 ?></span><span><strong><?= escape($item['title']) ?></strong><small><?= escape($item['sourceName']) ?></small></span><span class="status-pill <?= escape($item['status']) ?>"><?= $item['status'] === 'ready' ? 'Klar' : 'Utkast' ?></span></a></li><?php endforeach; ?></ol>
      <?php if ($canPrepare): ?><form class="manual-add" method="post"><input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="add"><label for="new-title">Nytt punkt</label><div><input id="new-title" name="title" maxlength="180" required placeholder="For eksempel: Værmelding kl. 12"><button type="submit">Legg til</button></div></form><?php endif; ?>
    </section>
    <section class="control-panel sending-editor" aria-labelledby="editor-title">
      <?php if ($selected): ?>
      <div class="panel-top"><div><p class="eyebrow">PUNKT <?= array_search($selected, $items, true) + 1 ?></p><h2 id="editor-title">Manus og kontroll</h2><p class="control-muted"><?= escape($selected['title']) ?></p></div><span class="status-pill <?= escape($selected['status']) ?>"><?= $selected['status'] === 'ready' ? 'Klar' : 'Utkast' ?></span></div><a class="sending-jump" href="#sending-list-title">Velg annet punkt ↓</a>
      <p class="control-muted">Fra <?= escape($selected['sourceName']) ?> · <?= escape(sending_time($selected['sourceAt'])) ?><?php if ($selected['sourceUrl']): ?> · <a href="<?= escape($selected['sourceUrl']) ?>" target="_blank" rel="noopener noreferrer">Kontroller original ↗</a><?php endif; ?></p>
      <?php if ($selected['summary']): ?><details class="source-summary"><summary>Vis kildeomtale</summary><p><?= escape($selected['summary']) ?></p></details><?php endif; ?>
      <?php if ($canPrepare && !empty($selected['originId'])): ?><form method="post" class="script-generate"><input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="generate"><input type="hidden" name="id" value="<?= escape($selected['id']) ?>"><input type="hidden" name="revision" value="<?= (int)$selected['revision'] ?>"><button type="submit"><?= studio_news_allowed_url((string)$selected['sourceUrl']) ? 'Lag og kildekontroller nyhetsmanus' : 'Generer manusutkast fra omtale' ?></button><span class="control-muted"><?= studio_news_allowed_url((string)$selected['sourceUrl']) ? 'Henter originaltekst og kjører separat AI-kontroll. ' : 'Bruker lagret kildeomtale uten automatisk originalkontroll. ' ?><?= $selected['script'] ? 'Lagrer forrige versjon i historikken. ' : '' ?></span></form><?php endif; ?>
      <?php if ($canPrepare && $selected['script'] && studio_news_allowed_url((string)$selected['sourceUrl'])): ?>
      <form method="post" class="script-generate"><input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="recheck"><input type="hidden" name="id" value="<?= escape($selected['id']) ?>"><input type="hidden" name="revision" value="<?= (int)$selected['revision'] ?>"><button type="submit">Kontroller lagret manus på nytt</button><span class="control-muted">Lagre tekstendringer først. Henter originalen på nytt.</span></form>
      <?php endif; ?>
      <?php if (isset($selected['sourceCheck'])): $check = $selected['sourceCheck']; ?>
      <section aria-labelledby="source-check-title" class="source-summary">
        <h3 id="source-check-title">Robåtens kildekontroll</h3>
        <p role="status"><?= studio_news_check_current($selected) ? 'AI-kontroll bestått – venter på redaksjonell godkjenning.' : 'Må kontrolleres: avvik, endret manus, utløpt eller ufullført kontroll.' ?></p>
        <p class="control-muted">Kontrollert <?= escape(sending_time($check['checkedAt'] ?? null)) ?>. Kontroll gjelder denne teksten i én time. AI-kontroll er ikke en garanti for at kilden er korrekt eller uendret.</p>
        <?php if (!empty($check['issues'])): ?><ul><?php foreach ($check['issues'] as $issue): ?><li><?= escape($issue) ?></li><?php endforeach; ?></ul><?php endif; ?>
        <details><summary>Se påstander og kildebelegg</summary>
        <?php foreach ($check['segments'] ?? [] as $segment): ?><p><strong><?= escape($segment['text']) ?></strong><br><?= escape(['supported'=>'Har kildebelegg', 'unsupported'=>'Mangler kildebelegg', 'uncertain'=>'Usikkert'][$segment['verdict']] ?? 'Ukjent') ?>: <?= escape($segment['reason']) ?></p><blockquote><?= escape($segment['evidence']) ?></blockquote><?php endforeach; ?>
        </details>
        <?php if (!empty($check['source']['text'])): ?><details><summary>Originaltekst brukt i kontrollen</summary><p>Hentet <?= escape(sending_time($check['source']['fetchedAt'])) ?> fra <?= escape($check['source']['url']) ?></p><p class="script-view"><?= nl2br(escape($check['source']['text'])) ?></p></details><?php endif; ?>
      </section>
      <?php endif; ?>
      <?php if (!empty($selected['generatedAt'])): ?><p class="control-muted">AI-utkast laget <?= escape(sending_time($selected['generatedAt'])) ?>. Må gjennomleses og kildekontrolleres.</p><?php endif; ?>
      <?php if ($canPrepare): ?><form method="post" class="editor-form"><input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= escape($selected['id']) ?>"><input type="hidden" name="revision" value="<?= (int)$selected['revision'] ?>"><label for="item-program">Program</label><select id="item-program" name="program"><option value="">Ikke tilordnet</option><?php foreach (studio_program_registry()['programs'] as $profile): ?><option value="<?= escape($profile['id']) ?>" <?= ($selected['program'] ?? '') === $profile['id'] ? 'selected' : '' ?>><?= escape($profile['name']) ?></option><?php endforeach; ?></select><label for="item-title">Tittel</label><input id="item-title" name="title" maxlength="180" required value="<?= escape($selected['title']) ?>"><label for="item-script">Manus</label><textarea id="item-script" name="script" maxlength="5000" rows="9" placeholder="Skriv et kort manus som kan leses på lufta."><?= escape($selected['script']) ?></textarea><label for="item-notes">Notater til sendingen</label><textarea id="item-notes" name="notes" maxlength="1000" rows="3"><?= escape($selected['notes']) ?></textarea><label class="verify-row"><input type="checkbox" name="verified" value="1" <?= $selected['verified'] ? 'checked' : '' ?>> <?= $selected['sourceUrl'] ? 'Jeg har kontrollert opplysningene i originalkilden' : 'Jeg har kontrollert innholdet' ?></label><p class="control-muted">Lagring setter punktet til utkast. Merk det klart etter siste kontroll.</p><button type="submit">Lagre utkast</button></form>
      <div class="item-controls"><?php foreach (['ready'=>'Merk klar', 'draft'=>'Tilbake til utkast', 'up'=>'Flytt opp', 'down'=>'Flytt ned', 'archive'=>'Arkiver'] as $action=>$label): if ($action === 'ready' && $selected['status'] === 'ready' || $action === 'draft' && $selected['status'] !== 'ready') continue; ?><form method="post"><input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="<?= $action ?>"><input type="hidden" name="id" value="<?= escape($selected['id']) ?>"><input type="hidden" name="revision" value="<?= (int)$selected['revision'] ?>"><button type="submit" class="<?= $action === 'archive' ? 'quiet' : '' ?>"><?= $label ?></button></form><?php endforeach; ?></div>
      <?php else: ?><h3><?= escape($selected['title']) ?></h3><p class="script-view"><?= nl2br(escape($selected['script'] ?: 'Manus er ikke skrevet ennå.')) ?></p><?php endif; ?>
      <p><strong>Program:</strong> <?= escape(studio_program_label((string)($selected['program'] ?? ''))) ?> · <a href="/learning.php?item=<?= escape($selected['id']) ?>">Lær av rettelsen / se regler</a></p>
      <details class="source-summary"><summary>Manushistorikk (<?= count($selected['history'] ?? []) ?>)</summary>
      <?php foreach (array_reverse($selected['history'] ?? []) as $entry): ?><div><p><?= escape(sending_time($entry['at'])) ?> · <?= escape($entry['actor']) ?> · <?= escape($entry['action']) ?></p><p class="script-view"><?= nl2br(escape($entry['before']['script'] ?: 'Tomt manus')) ?></p></div><?php endforeach; ?>
      </details>
      <?php if ($selected['approvedBy']): ?><p class="control-muted">Merket klar av <?= escape($selected['approvedBy']) ?>.</p><?php endif; ?>
      <?php else: ?><h2 id="editor-title">Velg et punkt</h2><p class="control-empty">Når listen får et punkt, kan manus og kildekontroll gjøres her.</p><?php endif; ?>
    </section>
  </div>
</main></div></div></body></html>


