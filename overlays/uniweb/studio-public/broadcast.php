<?php
declare(strict_types=1);
require dirname(__DIR__) . '/studio-private/app/bootstrap.php';
$user = current_user();
if (!$user) redirect('/login.php');
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET','POST'], true)) {
    http_response_code(405); header('Allow: GET, POST'); exit;
}
require dirname(__DIR__) . '/studio-private/app/broadcast.php';
$canPrepare = studio_can($user, 'produce');
$_SESSION['broadcast_token'] ??= bin2hex(random_bytes(16));
$program = is_string($_GET['program'] ?? null) ? $_GET['program'] : studio_program_default();
$selectedId = is_string($_GET['draft'] ?? null) ? $_GET['draft'] : '';
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf']) || !$canPrepare) {
        http_response_code(403); exit('Ingen tilgang til å opprette sendeforslag.');
    }
    if (!is_string($_POST['token'] ?? null) || !hash_equals($_SESSION['broadcast_token'], $_POST['token'])) {
        http_response_code(409); exit('Skjemaet er allerede brukt eller utløpt. Last siden på nytt.');
    }
    try {
        if (($_POST['generateNews'] ?? '') === '1') require_once dirname(__DIR__) . '/studio-private/app/producer.php';
        $selectedId = studio_broadcast_create($_POST, $user, $config);
        $_SESSION['broadcast_token'] = bin2hex(random_bytes(16));
        redirect('/broadcast.php?draft=' . rawurlencode($selectedId));
    } catch (InvalidArgumentException $e) { $error = $e->getMessage(); }
    catch (Throwable $e) { error_log('Studio broadcast draft failed'); $error = 'Kunne ikke lage sendeforslaget. Kontroller AI-oppsettet eller prøv uten AI-manus. Ingen delvis sendepakke er lagret.'; }
    $program = is_string($_POST['program'] ?? null) ? $_POST['program'] : studio_program_default();
}
try { $board = studio_board_read(); }
catch (Throwable $e) { http_response_code(503); exit('Sendelisten kan ikke leses. Ingen sendeforslag kan opprettes nå.'); }
$selected = null;
foreach ($board['broadcastDrafts'] ?? [] as $draft) if ($draft['id'] === $selectedId) $selected = $draft;
if ($selectedId !== '' && !$selected) { http_response_code(404); exit('Sendeforslaget finnes ikke.'); }
if ($selected) $program = $selected['program'];
try { $profile = studio_program_profile($program); }
catch (InvalidArgumentException $e) { http_response_code(400); exit('Ukjent program.'); }
if (!$profile) { http_response_code(400); exit('Velg et program.'); }
if (($_GET['export'] ?? '') === '1') {
    if (!$canPrepare) { http_response_code(403); exit('Rollen kan ikke eksportere.'); }
    if (!$selected) { http_response_code(400); exit('Velg et sendeforslag.'); }
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="RadioRubben-sendeforslag-' . $selected['date'] . '.txt"');
    echo studio_broadcast_export($selected); exit;
}
$sources = array_filter(studio_board_active($board), static fn($item) => ($item['program'] ?? '') === $program && !empty($item['originId']));
$extraStylesheet = '/assets/control.css?v=2';
require dirname(__DIR__) . '/studio-private/app/views/head.php';
?>
<div class="shell">
<?php $activePage='broadcast'; require dirname(__DIR__) . '/studio-private/app/views/sidebar.php'; ?>
<div class="workspace"><header class="topbar"><span>Robåt / <strong>Sendeforslag</strong></span><?php require dirname(__DIR__) . '/studio-private/app/views/account.php'; ?></header>
<main id="main" class="control-page">
<div class="control-heading"><div><p class="eyebrow">RADIO RUBBEN / ROBÅTEN</p><h1>Lag en hel sending</h1><p class="control-intro">Programmal, musikkforslag og valgte kildesaker i én kjøreplan. Alle nye forslag er utkast.</p></div><a class="control-link" href="/sending.php">Velg og rediger kildesaker i Sending</a></div>
<?php if ($error): ?><p class="control-alert error" role="alert"><?= escape($error) ?></p><?php endif; ?>
<form method="get"><label for="program">Program</label><select id="program" name="program"><?php foreach (studio_program_registry()['programs'] as $entry): ?><option value="<?= escape($entry['id']) ?>" <?= $program === $entry['id'] ? 'selected' : '' ?>><?= escape($entry['name']) ?></option><?php endforeach; ?></select><button type="submit">Vis program</button></form>
<section class="control-panel"><h2><?= escape($profile['name']) ?></h2><p><?= escape($profile['style']) ?></p><p>Profilversjon <?= (int)$profile['profileVersion'] ?> · <a href="/learning.php?program=<?= escape($program) ?>">Robåt – læring</a></p>
<?php foreach ($profile['format'] ?? [] as $key=>$value): ?><p><?= escape($value) ?></p><?php endforeach; ?>
</section>
<?php if ($canPrepare && !empty($profile['templateId'])): ?>
<section class="control-panel"><h2>Nytt sendeforslag</h2><form method="post" class="editor-form">
<input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>"><input type="hidden" name="token" value="<?= escape($_SESSION['broadcast_token']) ?>"><input type="hidden" name="program" value="<?= escape($program) ?>">
<label for="date">Sendedato</label><input type="date" id="date" name="date" value="<?= escape(is_string($_POST['date'] ?? null) ? $_POST['date'] : (new DateTimeImmutable('now', new DateTimeZone('Europe/Oslo')))->format('Y-m-d')) ?>" required>
<label for="presenter">Programledernavn på lufta</label><input id="presenter" name="presenter" maxlength="80" value="<?= escape(is_string($_POST['presenter'] ?? null) ? $_POST['presenter'] : ($user['name'] ?? '')) ?>" required>
<fieldset><legend>Velg inntil fem kildesaker fra dette programmet</legend>
<?php if (!$sources): ?><p>Ingen kildesaker tilgjengelig. Legg saker fra Nyhetsdesk i Sending og velg riktig program. Uten kilder får forslaget tomme nyhetsplasser.</p><?php endif; ?>
<?php foreach ($sources as $source): ?><label class="verify-row"><input type="checkbox" name="sources[]" value="<?= escape($source['id']) ?>"> <?= escape($source['title']) ?> · <?= escape($source['sourceName']) ?> · publisert <?= escape($source['sourceAt'] ?? 'ukjent') ?></label><?php endforeach; ?>
</fieldset><label class="verify-row"><input type="checkbox" name="generateNews" value="1"> La Robåten skrive AI-utkast for valgte saker som mangler manus.</label>
<p>Eksisterende manus kopieres som utkast for ny sendedato. AI krever konfigurert modell og nøkkel. Programlederstikk og musikkforslag kommer fra den faste malen; nye nettfunn, vær og trafikk hentes ikke automatisk her.</p>
<button type="submit">Lag sendeforslag</button></form></section>
<?php endif; ?>
<section class="control-panel"><h2>Lagrede sendeforslag</h2><?php foreach (array_reverse($board['broadcastDrafts'] ?? []) as $draft): if ($draft['program'] !== $program) continue; ?><p><a href="/broadcast.php?draft=<?= escape($draft['id']) ?>"><?= escape($draft['date'].' · '.$draft['name'].' · '.$draft['createdAt']) ?></a> · Utkast</p><?php endforeach; ?></section>
<?php if ($selected): $stories = array_column($selected['stories'], null, 'id'); ?>
<section class="control-panel"><h2><?= escape($selected['name'].' · '.$selected['date']) ?></h2><p>Tema: <?= escape($selected['theme']) ?> · Programleder: <?= escape($selected['presenter']) ?></p><p>Lagret profilversjon <?= (int)$selected['editorial']['profileVersion'] ?> · malversjon <?= (int)$selected['templateVersion'] ?> · norsk tid</p>
<?php foreach ($selected['reviewNotes'] as $note): ?><p><?= escape($note) ?></p><?php endforeach; ?>
<?php if ($canPrepare): ?><a class="control-link" href="/broadcast.php?draft=<?= escape($selected['id']) ?>&amp;export=1">Last ned hele kjøreplanen</a><?php endif; ?></section>
<?php foreach ($selected['blocks'] as $block): ?><section class="control-panel"><h2><?= escape($block['start'].'–'.$block['end'].' · '.$block['title']) ?></h2>
<p class="script-view"><?= nl2br(escape($block['script'])) ?></p>
<?php if ($block['news'] && !$block['storyIds']): ?><p class="control-alert">Nyhetsplass: ingen kilder valgt.</p><?php endif; ?>
<?php foreach ($block['storyIds'] as $id): $story=$stories[$id]; $source=$story['sourceSnapshot']; ?>
<article class="source-summary"><h3><?= escape($source['title']) ?> · Utkast</h3><p class="script-view"><?= nl2br(escape($story['script'] ?: 'Manus mangler. Skriv eller generer i Sending og lag et nytt forslag.')) ?></p><p><a href="<?= escape($source['sourceUrl']) ?>" target="_blank" rel="noopener noreferrer"><?= escape($source['sourceName']) ?> – originalkilde</a> · publisert <?= escape($source['sourceAt']) ?> · hentet <?= escape($source['capturedAt'] ?? 'ukjent') ?></p><p>Må kontrolleres for denne sendedatoen. <a href="/sending.php?item=<?= escape($id) ?>">Åpne kildepunkt i Sending</a></p></article>
<?php endforeach; ?>
<?php if ($block['service']): ?><p class="control-alert">Serviceplass: vær og trafikk krever ferske, kontrollerte opplysninger.</p><?php endif; ?>
<h3>Musikkforslag</h3><ol><?php foreach ($block['music'] as $song): ?><li><?= escape($song['artist'].' – '.$song['title']) ?> · fil/spilletid ukjent</li><?php endforeach; ?></ol><p><?= escape($block['note']) ?></p></section><?php endforeach; ?>
<section class="control-panel"><h2>Reservemusikk – må kontrolleres i arkivet</h2><ul><?php foreach ($selected['reserves'] as $song): ?><li><?= escape($song['artist'].' – '.$song['title']) ?></li><?php endforeach; ?></ul></section>
<?php endif; ?>
</main></div></div></body></html>
