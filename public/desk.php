<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/desk.php';
$user = current_user();
if ($config['auth_mode'] !== 'entra' || !$user) redirect('/login.php');
$directory = getenv('STUDIO_DESK_STORAGE_DIR') ?: ($config['desk_storage_dir'] ?? '');
$error = ''; $available = false; $data = desk_empty();
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET','POST'], true)) { header('Allow: GET, POST'); http_response_code(405); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf']))) { http_response_code(403); exit('Ugyldig sikkerhetskode. Last siden på nytt.'); }
try {
    $data = desk_store($directory);
    $available = true;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        foreach ($_POST as $value) if (!is_string($value)) throw new DomainException('Ugyldige skjemaverdier.');
        desk_store($directory, $_POST, (string)($user['name'] ?? 'Medarbeider'));
        redirect('/desk.php');
    }
} catch (DomainException $e) { http_response_code(409); $error = $e->getMessage(); }
catch (Throwable $e) { http_response_code(503); $available = false; $error = 'Lagring er ikke tilgjengelig. Kontakt administrator. Ingen endringer er bekreftet lagret.'; error_log('Desk: '.$e->getMessage()); }
$selected = is_string($_GET['edit'] ?? null) ? ($data['items'][$_GET['edit']] ?? null) : null;
// Preserve the editor content on validation/conflict errors.
if ($error && $_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['save','create'], true)) $selected = array_filter($_POST, 'is_string');
$filter = is_string($_GET['category'] ?? null) ? $_GET['category'] : '';
$queue = array_filter($data['items'], fn($i) => $i['status'] === 'queued');
uasort($queue, fn($a,$b) => strcmp($a['queued_at'], $b['queued_at']));
function desk_fields(array $data, string $id, string $action): void { ?>
<input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>"><input type="hidden" name="revision" value="<?= $data['revision'] ?>"><input type="hidden" name="id" value="<?= escape($id) ?>"><input type="hidden" name="action" value="<?= escape($action) ?>">
<?php }
require dirname(__DIR__) . '/app/views/head.php';
?>
<link rel="stylesheet" href="/assets/desk.css?v=1">
<div class="desk-wrap">
<header class="desk-header"><a href="/">← Studio</a><span>RADIO RUBBEN / REDAKSJON</span><span><?= escape($user['name'] ?? '') ?></span></header>
<main id="main"><p class="eyebrow">LOKALE HISTORIER. KLARE FOR LUFTA.</p><h1>Desken</h1><p class="intro">Samle kildene, skriv stikket og gjør sendingen klar.</p>
<?php if ($error): ?><p class="desk-alert" role="alert"><?= escape($error) ?></p><?php endif; ?>
<section class="desk-sources" aria-label="Kildestatus"><strong>API-kilder · ikke tilkoblet</strong><p>Nyheter/RSS · MET vær · Vegvesen trafikk · Entur · Politiloggen · NTB / sport</p><small>Første versjon bruker saker du legger inn selv. Automatisk innhenting og morgenbrief kommer i neste trinn.</small></section>
<div class="desk-grid"><section aria-labelledby="stories-title"><h2 id="stories-title">Saker og stikk <span class="small"><?= count($data['items']) ?> saker</span></h2>
<form method="get" class="desk-filter"><label for="filter">Kategori</label><select id="filter" name="category"><option value="">Alle kategorier</option><?php foreach (DESK_CATEGORIES as $cat): ?><option <?= $filter === $cat ? 'selected' : '' ?>><?= escape($cat) ?></option><?php endforeach; ?></select><button class="button secondary">Filtrer</button></form>
<?php if (!$data['items']): ?><div class="desk-empty"><h3>Din neste historie starter her.</h3><p>Legg inn en kilde og skriv første stikk. Godkjenn teksten før den legges i sendingskø.</p></div><?php endif; ?>
<?php foreach (array_reverse($data['items'], true) as $item): if ($filter && $item['category'] !== $filter) continue; ?>
<article class="desk-card"><p class="eyebrow"><?= escape($item['category']) ?> · <?= escape(DESK_STATES[$item['status']]) ?></p><h3><?= escape($item['title']) ?></h3><p class="desk-script"><?= escape($item['script']) ?></p><p class="small">Kilde: <?= escape($item['source'] ?: 'Ikke oppgitt') ?><?php if ($item['url']): ?> · <a href="<?= escape($item['url']) ?>" target="_blank" rel="noopener noreferrer">Åpne kilde ↗</a><?php endif; ?></p><p class="small">Oppdatert <?= escape((new DateTimeImmutable($item['updated_at']))->setTimezone(new DateTimeZone('Europe/Oslo'))->format('d.m H:i')) ?> · <?= escape($item['updated_by']) ?></p>
<div class="desk-actions"><?php if ($item['status'] !== 'read'): ?><a href="?edit=<?= escape($item['id']) ?>#editor">Rediger</a><?php endif; ?>
<?php foreach (['draft'=>['approve','Godkjenn'], 'ready'=>['queue','Legg i sendingskø']] as $state=>$action): if ($item['status'] === $state): ?><form method="post"><?php desk_fields($data,$item['id'],$action[0]); ?><button class="button secondary"><?= $action[1] ?></button></form><?php endif; endforeach; ?></div></article>
<?php endforeach; ?></section>
<aside><section class="desk-panel" aria-labelledby="queue-title"><p class="eyebrow">KLAR FOR SENDING</p><h2 id="queue-title">Sendingskø · <?= count($queue) ?></h2><p class="small">Rekkefølge etter tidspunkt lagt i kø.</p><?php if (!$queue): ?><p>Ingen godkjente stikk i kø ennå.</p><?php endif; ?><?php foreach ($queue as $item): ?><article class="desk-queue-item"><h3><?= escape($item['title']) ?></h3><details><summary>Les manus</summary><p class="desk-script"><?= escape($item['script']) ?></p><p>Kilde: <?= escape($item['source']) ?></p></details><div class="desk-actions"><form method="post"><?php desk_fields($data,$item['id'],'read'); ?><button class="button">Lest på lufta</button></form><form method="post"><?php desk_fields($data,$item['id'],'unqueue'); ?><button class="button secondary">Ta ut av kø</button></form></div></article><?php endforeach; ?></section>
<section class="desk-panel" id="editor"><h2><?= $selected ? 'Rediger stikk' : 'Nytt stikk' ?></h2><p class="small">Endringer setter saken tilbake til utkast og tar den ut av sendingskøen.</p><form method="post"><?php desk_fields($data,(string)($selected['id'] ?? ''),empty($selected['id']) ? 'create' : 'save'); ?><fieldset <?= !$available ? 'disabled' : '' ?>><label>Tittel<input name="title" required maxlength="240" value="<?= escape($selected['title'] ?? '') ?>"></label><label>Kategori<select name="category"><?php foreach (DESK_CATEGORIES as $cat): ?><option <?= ($selected['category'] ?? '') === $cat ? 'selected' : '' ?>><?= escape($cat) ?></option><?php endforeach; ?></select></label><label>Kilde / egen observasjon<input name="source" maxlength="240" value="<?= escape($selected['source'] ?? '') ?>"></label><label>Kildelenke<input type="url" name="url" maxlength="2000" placeholder="https://" value="<?= escape($selected['url'] ?? '') ?>"></label><label>Manus<textarea name="script" rows="9" maxlength="20000"><?= escape($selected['script'] ?? '') ?></textarea></label><button class="button">Lagre utkast</button> <a href="/desk.php#editor">Nytt stikk</a></fieldset></form></section></aside></div>
<footer><span>Radio Rubben · Lokal · Inkluderende · Verdig · Engasjerende</span><span>Manuell redaksjonell kontroll</span></footer></main></div></body></html>
