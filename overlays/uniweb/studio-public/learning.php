<?php
declare(strict_types=1);
require dirname(__DIR__) . '/studio-private/app/bootstrap.php';
$user = current_user();
if (!$user) redirect('/login.php');
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET','POST'], true)) {
    http_response_code(405); header('Allow: GET, POST'); exit;
}
require dirname(__DIR__) . '/studio-private/app/editorial-memory.php';
$canPrepare = studio_can($user, 'produce');
$isAdmin = ($user['role'] ?? '') === 'admin';
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        http_response_code(403); exit('Ugyldig forespørsel. Last siden på nytt.');
    }
    if (!$canPrepare || (($_POST['action'] ?? '') !== 'propose' && !$isAdmin)) {
        http_response_code(403); exit('Rollen har ikke tilgang til denne handlingen.');
    }
    try {
        studio_memory_change((string)($_POST['action'] ?? ''), $_POST, $user);
        $_SESSION['learning_message'] = 'Læringsregisteret er oppdatert. Bare godkjente regler brukes i nye utkast.';
        redirect('/learning.php?program=' . rawurlencode((string)($_POST['program'] ?? studio_program_default())));
    } catch (InvalidArgumentException $e) { $error = $e->getMessage(); }
    catch (Throwable $e) { error_log('Studio learning write failed'); $error = 'Kunne ikke lagre. Prøv igjen senere.'; }
}
try { $board = studio_board_read(); }
catch (Throwable $e) { http_response_code(503); exit('Læringsregisteret er utilgjengelig. Ingen regler kan endres nå.'); }
$selectedId = (string)($_POST['item'] ?? $_GET['item'] ?? '');
$selected = null;
foreach ($board['items'] as $item) if ($item['id'] === $selectedId) $selected = $item;
$program = $selected ? (string)($selected['program'] ?? '')
    : (string)($_POST['program'] ?? $_GET['program'] ?? studio_program_default());
try {
    $profile = studio_program_profile($program);
    $eligible = $selected && studio_memory_eligible($selected);
} catch (InvalidArgumentException $e) { http_response_code(400); exit('Ukjent program. Velg et registrert program i Sending.'); }
$rules = array_values(array_filter($board['editorialRules'] ?? [],
    static fn($rule) => ($rule['program'] ?? '') === $program));
$message = $_SESSION['learning_message'] ?? null; unset($_SESSION['learning_message']);
$extraStylesheet = '/assets/control.css?v=2';
require dirname(__DIR__) . '/studio-private/app/views/head.php';
?>
<div class="shell">
<?php $activePage='learning'; require dirname(__DIR__) . '/studio-private/app/views/sidebar.php'; ?>
<div class="workspace"><header class="topbar"><span>Robåt / <strong>Læring</strong></span><?php require dirname(__DIR__) . '/studio-private/app/views/account.php'; ?></header>
<main id="main" class="control-page">
<div class="control-heading"><div><p class="eyebrow"><?= escape(studio_program_label($program)) ?></p><h1>Redaksjonell hukommelse</h1><p class="control-intro">Regler du godkjenner her følger med når Studio lager neste manusutkast for valgt program.</p></div><a class="control-link" href="/sending.php">Til Sending</a></div>
<?php if ($message): ?><p class="control-alert" role="status"><?= escape($message) ?></p><?php endif; ?>
<?php if ($error): ?><p class="control-alert error" role="alert"><?= escape($error) ?></p><?php endif; ?>
<form method="get"><label for="learning-program">Program</label><select id="learning-program" name="program"><?php foreach (studio_program_registry()['programs'] as $entry): ?><option value="<?= escape($entry['id']) ?>" <?= $program === $entry['id'] ? 'selected' : '' ?>><?= escape($entry['name']) ?></option><?php endforeach; ?></select><button type="submit">Vis program</button></form>
<section class="control-panel"><h2>Fast programprofil</h2><p><?= escape($profile['style'] ?? 'Punktet er ikke tilordnet et program.') ?></p><p>Kildekravene gjelder alltid. Tidligere manus brukes ikke som faktakilder. Reglene her gjelder foreløpig manusgeneratoren i Sending.</p></section>
<?php if ($canPrepare && !empty($profile['learningEnabled'])): ?>
<section class="control-panel"><h2><?= $selectedId ? 'Lær av rettelsen' : 'Foreslå en programregel' ?></h2>
<?php if ($selected): ?><p><?= escape($selected['title']) ?> · <a href="/sending.php?item=<?= escape($selected['id']) ?>">Åpne manuset</a></p><?php endif; ?>
<?php if ($eligible): ?><details open><summary>Originalutkast og rettet manus</summary><h3>Originalutkast</h3><p class="script-view"><?= nl2br(escape($selected['generatedOriginal'])) ?></p><h3>Rettet og kontrollert</h3><p class="script-view"><?= nl2br(escape($selected['script'])) ?></p></details><?php endif; ?>
<?php if (!$selectedId || $eligible): ?>
<form method="post" class="editor-form"><input type="hidden" name="program" value="<?= escape($program) ?>"><input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="propose"><input type="hidden" name="item" value="<?= escape($selectedId) ?>"><input type="hidden" name="itemRevision" value="<?= (int)($selected['revision'] ?? 0) ?>"><label for="rule-text">Hva skal Robåten huske om språk eller form?</label><textarea id="rule-text" name="text" rows="3" minlength="10" maxlength="500" required placeholder="For eksempel: Del lange setninger i korte setninger som er lette å lese høyt."><?= escape(is_string($_POST['text'] ?? null) ? $_POST['text'] : '') ?></textarea><label class="verify-row"><input type="checkbox" name="styleOnly" value="1" required> Regelen gjelder språk eller form, ikke fakta eller unntak fra kildekontroll.</label><p>Forslaget må godkjennes av administrator før det brukes. Kontroller at det passer sammen med aktive regler.</p><button type="submit">Lagre som forslag</button></form>
<?php else: ?><p>Velg et program med læring i Sending, rett et AI-utkast, lagre med kildekontroll og merk det klart. Deretter kan rettelsen brukes som grunnlag for læring.</p><a href="/learning.php">Foreslå en generell programregel</a><?php endif; ?>
</section><?php endif; ?>
<section class="control-panel"><h2>Programregler</h2><p>En regel endres ved å deaktivere eller avvise den og legge inn et nytt forslag. Historikken bevares.</p>
<?php $labels=['pending'=>'Venter på godkjenning','approved'=>'Aktiv','rejected'=>'Avvist','disabled'=>'Deaktivert']; ?>
<?php if (empty($rules)): ?><p>Ingen regler er lagt inn ennå.</p><?php endif; ?>
<?php foreach (array_reverse($rules) as $rule): ?>
<article class="source-summary"><h3><?= escape($labels[$rule['status']]) ?> · versjon <?= (int)$rule['revision'] ?></h3><p><?= escape($rule['text']) ?></p><p class="control-muted">Foreslått av <?= escape($rule['createdBy']) ?></p>
<?php if ($rule['evidence']): ?><details><summary>Se rettelsen regelen bygger på</summary><p><?= escape($rule['evidence']['title']) ?></p><h4>Før</h4><p class="script-view"><?= nl2br(escape($rule['evidence']['before'])) ?></p><h4>Etter</h4><p class="script-view"><?= nl2br(escape($rule['evidence']['after'])) ?></p></details><?php endif; ?>
<details><summary>Regelhistorikk</summary><?php foreach ($rule['history'] as $event): ?><p><?= escape($event['at'].' · '.$event['actor'].' · '.$event['action']) ?></p><?php endforeach; ?></details>
<?php if ($isAdmin): ?><div class="item-controls"><?php foreach (($rule['status'] === 'pending' ? ['approve'=>'Godkjenn','reject'=>'Avvis'] : ($rule['status'] === 'approved' ? ['disable'=>'Deaktiver'] : [])) as $action=>$label): ?><form method="post"><input type="hidden" name="program" value="<?= escape($program) ?>"><input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="<?= escape($action) ?>"><input type="hidden" name="id" value="<?= escape($rule['id']) ?>"><input type="hidden" name="revision" value="<?= (int)$rule['revision'] ?>"><button type="submit"><?= escape($label) ?></button></form><?php endforeach; ?></div><?php endif; ?>
</article><?php endforeach; ?></section>
</main></div></div></body></html>

