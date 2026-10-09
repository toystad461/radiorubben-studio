<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$user = current_user();
if (!$user) redirect('/login.php');
require_once dirname(__DIR__).'/app/crm.php';
if (!studio_crm_allowed($user)) { http_response_code(403); exit('CRM er bare tilgjengelig for administrator.'); }
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'], true)) { http_response_code(405); header('Allow: GET, POST'); exit; }
$error = null; $postedAction = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) { http_response_code(403); exit('Last siden på nytt før du lagrer.'); }
    try {
        $postedAction = studio_crm_text($_POST, 'action', 20);
        $evidence = $_SESSION['crm_lookup'] ?? null;
        if ($evidence && (strtotime($evidence['fetchedAt']??'')?:0)<time()-3600) $evidence=null;
        $saved = studio_crm_apply($postedAction, $_POST, $user, null, $evidence);
        $_SESSION['crm_message'] = match ($postedAction) {
            'seed'=>'Startlisten er lagt til. Bedrifter som allerede finnes, er beholdt.',
            'activity'=>'Notatet er lagret i historikken.', default=>'Bedriftskortet er lagret.',
        };
        redirect('/crm.php'.($saved ? '?id='.rawurlencode($saved).'#crm-detail' : ''));
    } catch (InvalidArgumentException $e) { http_response_code(422); $error = $e->getMessage(); }
    catch (Throwable $e) { http_response_code(503); error_log('Studio CRM write failed'); $error = 'CRM kunne ikke lagres. Teksten din er beholdt nedenfor.'; }
}
try { $registry = studio_crm_read(); }
catch (Throwable $e) { http_response_code(503); exit('CRM er utilgjengelig. Kontakt administrator.'); }
$query = is_string($_GET['q'] ?? null) ? substr($_GET['q'], 0, 200) : '';
$stage = is_string($_GET['stage'] ?? null) && isset(studio_crm_stages()[$_GET['stage']]) ? $_GET['stage'] : '';
$due = ($_GET['due'] ?? '') === '1';
$selectedId = is_string($_GET['id'] ?? null) ? $_GET['id'] : '';
if ($error && in_array($postedAction, ['save', 'activity'], true)) $selectedId = is_string($_POST['id'] ?? null) ? $_POST['id'] : '';
$creating = ($_GET['new'] ?? '') === '1' || ($error && $postedAction === 'create');
$selected = null;
foreach ($registry['records'] as $row) if ($row['id'] === $selectedId) $selected = $row;
if ($selectedId !== '' && !$selected && !$error) { http_response_code(404); $error = 'Bedriftskortet finnes ikke.'; }
$rows = studio_crm_list($registry['records'], $query, $stage, $due);
$filters = array_filter(['q'=>$query, 'stage'=>$stage, 'due'=>$due ? '1' : ''], static fn($v) => $v !== '');
$listUrl = '/crm.php'.($filters ? '?'.http_build_query($filters) : '');
$defaults = ['company'=>'', 'contact'=>'', 'phone'=>'', 'email'=>'', 'website'=>'', 'owner'=>$user['name'] ?? '',
    'orgNumber'=>'', 'businessAddress'=>'', 'industry'=>'', 'organizationForm'=>'', 'stage'=>'candidate', 'priority'=>'2', 'opportunity'=>'', 'nextStep'=>'', 'followUp'=>'', 'oneDriveUrl'=>''];
$form = $selected ?? $defaults;
if ($error && in_array($postedAction, ['create', 'save'], true)) {
    foreach (array_keys($defaults) as $key) if (is_string($_POST[$key] ?? null)) $form[$key] = $_POST[$key];
}
$revision = $selected['revision'] ?? 0;
// Preserve the rejected revision: retrying an old form must never overwrite a newer edit.
if ($error && is_scalar($_POST['revision'] ?? null)) $revision = (int)$_POST['revision'];
$message = $_SESSION['crm_message'] ?? null; unset($_SESSION['crm_message']);
$today = studio_crm_today();
$stats = ['due'=>0, 'offer'=>0, 'active'=>0, 'open'=>0];
foreach ($registry['records'] as $row) {
    if (studio_crm_due($row, $today)) $stats['due']++;
    if (in_array($row['stage'], ['offer', 'active'], true)) $stats[$row['stage']]++;
    if (!in_array($row['stage'], ['paused', 'declined', 'archived', 'active'], true)) $stats['open']++;
}
$labels = ['company'=>'Bedrift', 'contact'=>'Kontaktperson', 'phone'=>'Telefon', 'email'=>'E-post', 'website'=>'Nettside',
    'orgNumber'=>'Organisasjonsnummer', 'businessAddress'=>'Adresse', 'industry'=>'Bransje', 'organizationForm'=>'Organisasjonsform', 'owner'=>'Ansvarlig', 'stage'=>'Status', 'priority'=>'Prioritet', 'opportunity'=>'Samarbeidsidé',
    'nextStep'=>'Neste steg', 'followUp'=>'Oppfølgingsdato', 'oneDriveUrl'=>'OneDrive-lenke'];
$extraStylesheet = '/assets/crm.css?v=1'; require dirname(__DIR__).'/app/views/head.php';
?>
<div class="shell crm-shell">
<?php $activePage = 'crm'; require dirname(__DIR__).'/app/views/sidebar.php'; ?>
<div class="workspace">
<header class="topbar"><span>Radio Rubben / <strong>Samarbeid</strong></span><?php require dirname(__DIR__).'/app/views/account.php'; ?></header>
<main id="main" class="crm-page">
<header class="crm-heading"><div><p class="eyebrow">SAMARBEID OG LOKALT ENGASJEMENT</p><h1>Samarbeidspartnere</h1><p>Gode samtaler. Tydelige avtaler. Neste steg på ett sted.</p></div><a class="crm-button primary" href="/crm.php?new=1#crm-detail">+ Ny bedrift</a></header>
<?php if ($message): ?><p class="crm-notice" role="status"><?= escape($message) ?></p><?php endif; ?>
<?php if ($error): ?><p class="crm-notice error" role="alert"><?= escape($error) ?></p><?php endif; ?>
<div class="crm-stats" aria-label="Statusoversikt">
<a href="/crm.php?due=1"><strong><?= $stats['due'] ?></strong><span>Til oppfølging</span></a>
<a href="/crm.php"><strong><?= $stats['open'] ?></strong><span>Åpne muligheter</span></a>
<a href="/crm.php?stage=offer"><strong><?= $stats['offer'] ?></strong><span>Tilbud sendt</span></a>
<a href="/crm.php?stage=active"><strong><?= $stats['active'] ?></strong><span>Aktive partnere</span></a>
</div>
<div class="crm-layout <?= $selected || $creating ? 'has-detail' : '' ?>">
<section class="crm-panel crm-list" aria-labelledby="crm-list-title">
<h2 id="crm-list-title">Bedrifter <span class="crm-muted">(<?= count($rows) ?>)</span></h2>
<form method="get" class="crm-filters">
<label>Søk etter bedrift eller kontakt<input type="search" name="q" value="<?= escape($query) ?>" maxlength="200" placeholder="Bedriftsnavn, person, telefon …"></label>
<div class="crm-filter-row"><label>Status<select name="stage"><option value="">Alle uten arkiv</option><?php foreach (studio_crm_stages() as $key=>$label): ?><option value="<?= $key ?>" <?= $stage === $key ? 'selected' : '' ?>><?= escape($label) ?></option><?php endforeach; ?></select></label><button type="submit">Vis</button></div>
<label class="crm-check"><input type="checkbox" name="due" value="1" <?= $due ? 'checked' : '' ?>> Bare oppfølging i dag og tidligere</label>
</form>
<?php if (!$rows): ?><p class="crm-muted">Ingen bedrifter i denne visningen.</p><?php endif; ?>
<ul class="crm-records">
<?php foreach ($rows as $row): ?>
<li><a class="crm-card <?= $row['id'] === $selectedId ? 'selected' : '' ?>" href="/crm.php?<?= escape(http_build_query($filters + ['id'=>$row['id']])) ?>#crm-detail" <?= $row['id'] === $selectedId ? 'aria-current="true"' : '' ?>>
<span class="crm-card-top"><small>#<?= sprintf('%03d', $row['number']) ?> · Prioritet <?= escape($row['priority']) ?></small><span class="crm-badge"><?= escape(studio_crm_stages()[$row['stage']]) ?></span></span>
<strong><?= escape($row['company']) ?></strong><span class="crm-muted"><?= escape($row['contact'] ?: 'Kontaktperson må avklares') ?></span>
<?php if ($row['nextStep']): ?><span class="crm-next"><?= escape($row['nextStep']) ?></span><?php endif; ?>
<small class="<?= studio_crm_due($row) ? 'crm-due' : 'crm-muted' ?>"><?= $row['followUp'] ? 'Oppfølging: '.escape($row['followUp']) : 'Ingen oppfølgingsdato' ?></small>
</a></li>
<?php endforeach; ?>
</ul>
<details class="crm-import"><summary>Startliste med fem lokale kandidater</summary><p>Kulleseidkanalen, Bømlo Storsenter, MEKK Bømlo, Bømlo Hotell og Finnås Kraftlag. Offentlige opplysninger sjekket 7. oktober 2026. Tidligere kontakt, interesse og budsjett er uavklart.</p><form method="post"><input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="seed"><button type="submit">Legg til manglende kandidater</button></form></details>
</section>
<section id="crm-detail" class="crm-panel crm-detail" aria-labelledby="crm-detail-title">
<?php if (!$selected && !$creating): ?>
<div class="crm-empty"><p class="eyebrow">FRA FØRSTE PRAT TIL SAMARBEID</p><h2 id="crm-detail-title">Hvem følger du opp neste gang?</h2><p>Velg en bedrift for å samle kontaktinformasjon, notater og neste steg. Legg til en ny bedrift eller bruk startlisten.</p><p class="crm-muted">Avtalene og tilbudene kan ligge i OneDrive. Lagre lenken på bedriftskortet, så finner du dem igjen her.</p></div>
<?php else: ?>
<a class="crm-back" href="<?= escape($listUrl) ?>">← Til bedriftslisten</a>
<div class="crm-detail-heading"><p class="eyebrow"><?= $creating ? 'NY MULIGHET' : 'BEDRIFTSKORT #'.sprintf('%03d', $selected['number']) ?></p><h2 id="crm-detail-title"><?= escape($creating ? 'Legg til bedrift' : $selected['company']) ?></h2>
<?php if ($selected): ?><p class="crm-muted">Siste registrerte kontakt: <?= escape($selected['lastContact'] ?: 'Ikke registrert') ?></p>
<div class="crm-actions">
<?php if ($selected['phone']): ?><a class="crm-button" href="tel:<?= escape(preg_replace('/[^+0-9]/', '', $selected['phone'])) ?>">Ring</a><?php endif; ?>
<?php if ($selected['email']): ?><a class="crm-button" href="mailto:<?= escape($selected['email']) ?>">Åpne e-post</a><?php endif; ?>
<?php if ($selected['website']): ?><a class="crm-button" href="<?= escape($selected['website']) ?>" target="_blank" rel="noopener noreferrer">Nettside ↗</a><?php endif; ?>
<?php if ($selected['oneDriveUrl']): ?><a class="crm-button" href="<?= escape($selected['oneDriveUrl']) ?>" target="_blank" rel="noopener noreferrer">OneDrive ↗</a><?php endif; ?>
</div><?php endif; ?></div>
<?php if ($selected && !$creating): ?>
<section class="crm-activity"><h3>Logg en samtale eller et notat</h3>
<form method="post" class="crm-form">
<input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="activity"><input type="hidden" name="id" value="<?= escape($selected['id']) ?>"><input type="hidden" name="revision" value="<?= $revision ?>">
<div class="crm-two"><label>Type<select name="channel"><?php foreach (studio_crm_channels() as $key=>$label): ?><option value="<?= $key ?>" <?= ($error && ($_POST['channel'] ?? '') === $key) || (!$error && $key === 'phone') ? 'selected' : '' ?>><?= escape($label) ?></option><?php endforeach; ?></select></label><label>Dato<input type="date" name="date" value="<?= escape($error && is_string($_POST['date'] ?? null) ? $_POST['date'] : $today) ?>" max="<?= $today ?>" required></label></div>
<label>Hva ble sagt eller avtalt?<textarea name="text" rows="3" maxlength="5000" required placeholder="Skriv kort om behov, respons og avtaler."><?= escape($error && $postedAction === 'activity' && is_string($_POST['text'] ?? null) ? $_POST['text'] : '') ?></textarea></label>
<button type="submit">Lagre i historikken</button></form></section>
<?php endif; ?>
<form method="post" class="crm-form crm-edit">
<input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="<?= $creating ? 'create' : 'save' ?>">
<?php if ($selected && !$creating): ?><input type="hidden" name="id" value="<?= escape($selected['id']) ?>"><input type="hidden" name="revision" value="<?= $revision ?>"><?php endif; ?>
<h3>Status og neste steg</h3>
<div class="crm-two"><label>Status<select name="stage"><?php foreach (studio_crm_stages() as $key=>$label): ?><option value="<?= $key ?>" <?= $form['stage'] === $key ? 'selected' : '' ?>><?= escape($label) ?></option><?php endforeach; ?></select></label><label>Prioritet<select name="priority"><?php foreach (['1'=>'1 – Først', '2'=>'2 – Deretter', '3'=>'3 – Langsiktig'] as $key=>$label): ?><option value="<?= $key ?>" <?= (string)$form['priority'] === (string)$key ? 'selected' : '' ?>><?= escape($label) ?></option><?php endforeach; ?></select></label></div>
<label>Neste steg<input name="nextStep" maxlength="500" value="<?= escape($form['nextStep']) ?>" placeholder="For eksempel: Ring og avtal et kort møte."></label>
<div class="crm-two"><label>Oppfølgingsdato<input type="date" name="followUp" value="<?= escape($form['followUp']) ?>"></label><label>Ansvarlig<input name="owner" maxlength="140" value="<?= escape($form['owner']) ?>"></label></div>
<p class="crm-muted crm-help">Datoen vises under «Til oppfølging» når den er nådd. På vent, avslåtte og arkiverte kort er unntatt. Ingen varsler sendes.</p>
<h3>Bedrift og kontakt</h3>
<label>Organisasjonsnummer<input name="orgNumber" inputmode="numeric" maxlength="20" value="<?= escape($form['orgNumber']??'') ?>" placeholder="9 siffer"></label>
<button type="button" data-crm-lookup>Hent fra Brønnøysundregistrene</button>
<p data-lookup-status role="status" class="crm-muted">Fyller tomme felt. Kontroller at opplysningene gjelder riktig bedrift.</p>
<label>Forretningsadresse<input name="businessAddress" maxlength="500" value="<?= escape($form['businessAddress']??'') ?>"></label>
<div class="crm-two"><label>Registrert bransje<input name="industry" maxlength="500" value="<?= escape($form['industry']??'') ?>"></label><label>Organisasjonsform<input name="organizationForm" maxlength="200" value="<?= escape($form['organizationForm']??'') ?>"></label></div>
<label>Bedriftsnavn<input name="company" maxlength="180" required value="<?= escape($form['company']) ?>" autocomplete="organization"></label>
<label>Kontaktperson<input name="contact" maxlength="140" value="<?= escape($form['contact']) ?>" autocomplete="name"></label>
<div class="crm-two"><label>Telefon<input type="tel" name="phone" maxlength="40" value="<?= escape($form['phone']) ?>"></label><label>E-post<input type="email" name="email" maxlength="200" value="<?= escape($form['email']) ?>"></label></div>
<label>Nettside<input type="url" name="website" maxlength="1000" value="<?= escape($form['website']) ?>" placeholder="https://"></label>
<label>Samarbeidsidé og behov<textarea name="opportunity" rows="4" maxlength="2000"><?= escape($form['opportunity']) ?></textarea></label>
<label>Lenke til tilbud eller mappe i OneDrive<input type="url" name="oneDriveUrl" maxlength="2000" value="<?= escape($form['oneDriveUrl']) ?>" placeholder="Lim inn lenken fra OneDrive"></label>
<p class="crm-muted crm-help">Lenken åpner filen eller mappen i OneDrive. Filer og tilgangsrettigheter håndteres der.</p>
<button class="primary" type="submit"><?= $creating ? 'Opprett bedriftskort' : 'Lagre bedriftskort' ?></button>
</form>
<?php if ($selected && !$creating): ?><p><a class="crm-button primary" href="/crm-outreach.php?id=<?= escape($selected['id']) ?>">Lag introduksjonsmail og lydforslag</a></p><section class="crm-history"><h3>Historikk</h3><ol>
<?php foreach (array_reverse($selected['history']) as $event): ?><li>
<p class="crm-muted"><?= escape((new DateTimeImmutable($event['at']))->setTimezone(new DateTimeZone('Europe/Oslo'))->format('d.m.Y H:i').' · '.$event['actor']) ?></p>
<?php if ($event['kind'] === 'activity'): ?><strong><?= escape(studio_crm_channels()[$event['channel']].' · '.$event['date']) ?></strong><?php endif; ?>
<p class="crm-prewrap"><?= escape($event['text']) ?></p>
<?php if (!empty($event['changes'])): ?><details><summary>Se endringene</summary><dl><?php foreach ($event['changes'] as $key=>$change): ?><dt><?= escape($labels[$key] ?? $key) ?></dt><dd><span class="crm-muted">Fra:</span> <?= escape($key === 'stage' ? (studio_crm_stages()[$change['before']] ?? $change['before']) : ($change['before'] ?: 'Tomt')) ?><br><span class="crm-muted">Til:</span> <?= escape($key === 'stage' ? (studio_crm_stages()[$change['after']] ?? $change['after']) : ($change['after'] ?: 'Tomt')) ?></dd><?php endforeach; ?></dl></details><?php endif; ?>
</li><?php endforeach; ?></ol></section><?php endif; ?>
<?php endif; ?>
</section></div>
<script src="/assets/crm.js?v=1" defer></script></main></div></div></body></html>
