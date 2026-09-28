<?php
declare(strict_types=1);
// Included from /users.php after the administrator check.
require_once dirname(__DIR__, 2) . '/studio-private/app/intro-mail.php';
$error = '';
$notice = '';
$mailNotice = '';
$temporaryPassword = null;
$createdEmail = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])
        || !is_string($_POST['action_nonce'] ?? null)
        || !hash_equals($_SESSION['action_nonce'] ?? '', $_POST['action_nonce'])) {
        http_response_code(403); exit('Ugyldig forespørsel.');
    }
    unset($_SESSION['action_nonce']);
    try {
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'create') {
            $temporaryPassword = studio_local_new_password();
            $record = studio_local_user_create((string)($_POST['name'] ?? ''), (string)($_POST['email'] ?? ''),
                $temporaryPassword, (string)($_POST['role'] ?? 'presenter'));
            $createdEmail = $record['email'];
            $notice = 'Studio-kontoen er opprettet. Del det midlertidige passordet gjennom en sikker kanal.';
            try {
                if (!studio_intro_send($record, $config)) throw new RuntimeException('Mail transport rejected message');
                $mailNotice = 'Intro-e-posten er levert til e-postserveren for utsending.';
                try { studio_local_user_mark_intro_sent($record['id']); }
                catch (Throwable $statusError) { error_log('Studio introduction status save failed: ' . $statusError->getMessage()); }
            } catch (Throwable $mailError) {
                error_log('Studio introduction email failed: ' . $mailError->getMessage());
                $mailNotice = 'Intro-e-posten ble ikke sendt. Kontoen er opprettet; du kan prøve igjen fra brukerlisten.';
            }
        } elseif ($action === 'intro') {
            $id = (string)($_POST['id'] ?? '');
            if (!preg_match('/^[a-f0-9]{32}$/D', $id)) throw new InvalidArgumentException('Ugyldig bruker.');
            $record = studio_local_user_by_id($id);
            if (!$record || !$record['enabled']) throw new InvalidArgumentException('Brukeren finnes ikke eller er deaktivert.');
            if (!studio_intro_send($record, $config)) throw new RuntimeException('Mail transport rejected message');
            $notice = 'Intro-e-posten er levert til e-postserveren for utsending.';
            try { studio_local_user_mark_intro_sent($id); }
            catch (Throwable $statusError) { error_log('Studio introduction status save failed: ' . $statusError->getMessage()); }
        } elseif (in_array($action, ['disable', 'enable', 'reset', 'role'], true)) {
            $id = (string)($_POST['id'] ?? '');
            if (!preg_match('/^[a-f0-9]{32}$/D', $id)) throw new InvalidArgumentException('Ugyldig bruker.');
            if ($action === 'reset') {
                $temporaryPassword = studio_local_new_password();
                $record = studio_local_user_change($id, null, $temporaryPassword);
                $createdEmail = $record['email'];
                $notice = 'Passordet er tilbakestilt. Gamle økter er avsluttet.';
            } elseif ($action === 'role') {
                studio_local_user_set_role($id, (string)($_POST['role'] ?? ''));
                $notice = 'Rollen er oppdatert. Gamle økter er avsluttet.';
            } else {
                studio_local_user_change($id, $action === 'enable');
                $notice = $action === 'enable' ? 'Brukeren er aktivert.' : 'Brukeren er deaktivert. Gamle økter er avsluttet.';
            }
        } else throw new InvalidArgumentException('Ukjent handling.');
    } catch (Throwable $exception) {
        if ($action === 'intro') {
            error_log('Studio introduction email failed: ' . $exception->getMessage());
            $error = $exception instanceof InvalidArgumentException ? $exception->getMessage() : 'Intro-e-posten kunne ikke sendes. Kontroller avsender og e-postoppsett.';
        } else {
            $temporaryPassword = null;
            $error = $exception instanceof InvalidArgumentException ? $exception->getMessage()
                : 'Endringen kunne ikke lagres. Kontroller at privat config-mappe er skrivbar.';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); header('Allow: GET, POST'); exit;
}
$_SESSION['action_nonce'] = bin2hex(random_bytes(24));
try { $rows = studio_local_users_read(); }
catch (Throwable $exception) { $rows = []; $error = 'Brukerlisten kunne ikke leses.'; }
usort($rows, static fn($a, $b) => strcmp($a['name'], $b['name']));
require dirname(__DIR__, 2) . '/studio-private/app/views/head.php';
?>
<link rel="stylesheet" href="/users.css?v=20260924">
<div class="shell">
<?php $activePage = 'users'; require dirname(__DIR__, 2) . '/studio-private/app/views/sidebar.php'; ?>
<div class="workspace">
<header class="topbar"><span>Arbeidsrom / <strong>Brukere</strong></span><span>Administrator</span><?php require dirname(__DIR__, 2) . '/studio-private/app/views/account.php'; ?></header>
<main id="main">
<div class="heading-row"><div><p class="eyebrow">RADIO RUBBEN STUDIO</p><h1>Brukere</h1><p class="intro">Bestem hvem som får tilgang til arbeidsrommet.</p></div></div>
<?php if (($config['local_users_enabled'] ?? false) !== true): ?><p class="notice">Studio-kontoer er ikke aktivert i privat konfigurasjon ennå.</p><?php endif; ?>
<?php if (!studio_intro_sender_ready($config)): ?><p class="notice" role="status">Intro-e-post er ikke aktivert. Opprettelse av bruker fungerer fortsatt, men en avsenderadresse må konfigureres før e-post kan sendes.</p><?php endif; ?>
<?php if ($error): ?><p role="alert" class="notice"><?= escape($error) ?></p><?php endif; ?>
<?php if ($notice): ?><p role="status"><?= escape($notice) ?></p><?php endif; ?>
<?php if ($mailNotice): ?><p role="status" class="notice"><?= escape($mailNotice) ?></p><?php endif; ?>
<?php if ($temporaryPassword !== null): ?>
<section class="users-panel" aria-label="Midlertidig innlogging">
<h2>Kopier nå – vises bare én gang</h2><p>E-post: <strong><?= escape($createdEmail) ?></strong></p>
<p>Midlertidig passord: <code><?= escape($temporaryPassword) ?></code></p>
<p>Brukeren velger nytt passord ved første innlogging.</p>
</section>
<?php endif; ?>
<section class="users-panel"><h2>Roller</h2><p><strong>Administrator:</strong> Brukere og alle verktøy.
<strong>Produsent:</strong> Studio og tekniske innstillinger.
<strong>Programleder:</strong> Musikk og stikk/manus.
<strong>Observatør:</strong> Lesevisning.</p><p class="small">Administrator er knyttet til din Microsoft-konto. Studio-kontoer kan ikke få administratorrollen.</p></section>
<section class="users-panel" aria-labelledby="add-user"><h2 id="add-user">Opprett medarbeider</h2>
<p>En kort intro sendes til e-postadressen når avsenderen er konfigurert. Midlertidig passord vises bare her og deles separat gjennom en sikker kanal.</p>
<form method="post" class="users-form" autocomplete="off">
<input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>">
<input type="hidden" name="action_nonce" value="<?= escape($_SESSION['action_nonce']) ?>">
<input type="hidden" name="action" value="create">
<p><label>Navn<br><input name="name" maxlength="120" required></label></p>
<p><label>E-post<br><input name="email" type="email" required></label></p>
<p><label>Rolle<br><select name="role"><?php foreach (['producer','presenter','observer'] as $role): ?>
<option value="<?= escape($role) ?>" <?= $role === 'presenter' ? 'selected' : '' ?>><?= escape(studio_roles()[$role]) ?></option>
<?php endforeach; ?></select></label></p>
<button class="button" type="submit">Opprett Studio-konto</button>
</form></section>
<section class="users-panel" aria-labelledby="user-list"><h2 id="user-list">Personer med tilgang</h2>
<p><strong>Thomas Magne Sellevold-Øystad</strong> · Administrator via Microsoft</p>
<?php if (!$rows): ?><p>Ingen Studio-kontoer er opprettet ennå.</p><?php endif; ?>
<?php foreach ($rows as $entry): ?>
<form method="post"><p><strong><?= escape($entry['name']) ?></strong> · <?= escape($entry['email']) ?> · <?= escape(studio_roles()[$entry['role']] ?? 'Ukjent') ?> · <?= $entry['enabled'] ? 'Aktiv' : 'Deaktivert' ?><br><span class="small">Intro: <?= !empty($entry['introSentAt']) ? 'levert til e-postserveren ' . escape($entry['introSentAt']) : 'ikke sendt' ?></span></p>
<input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>">
<input type="hidden" name="action_nonce" value="<?= escape($_SESSION['action_nonce']) ?>">
<input type="hidden" name="id" value="<?= escape($entry['id']) ?>">
<label>Rolle <select name="role"><?php foreach (['producer','presenter','observer'] as $role): ?>
<option value="<?= escape($role) ?>" <?= $role === $entry['role'] ? 'selected' : '' ?>><?= escape(studio_roles()[$role]) ?></option>
<?php endforeach; ?></select></label>
<button class="button" name="action" value="role">Lagre rolle</button>
<button class="button" name="action" value="<?= $entry['enabled'] ? 'disable' : 'enable' ?>"><?= $entry['enabled'] ? 'Deaktiver' : 'Aktiver' ?></button>
<button class="button" name="action" value="reset">Tilbakestill passord</button>
<?php if ($entry['enabled']): ?><button class="button" name="action" value="intro">Send intro-e-post<?= empty($entry['introSentAt']) ? '' : ' på nytt' ?></button><?php endif; ?><hr></form>
<?php endforeach; ?>
</section>
</main></div></div></body></html>
