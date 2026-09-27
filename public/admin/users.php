<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
if (!studio_is_admin(current_user())) { http_response_code(403); exit('Ingen tilgang.'); }
$message = '';
$error = '';
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
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'create') {
            $temporaryPassword = studio_new_password();
            $created = studio_user_create((string) ($_POST['name'] ?? ''), (string) ($_POST['email'] ?? ''), $temporaryPassword);
            $createdEmail = $created['email'];
            $message = 'Studio-konto opprettet. Gi den nye brukeren innloggingsinformasjonen gjennom en sikker kanal.';
        } elseif (in_array($action, ['disable', 'enable', 'reset'], true)) {
            $id = (string) ($_POST['id'] ?? '');
            if (!preg_match('/^[a-f0-9]{32}$/D', $id)) throw new InvalidArgumentException('Ugyldig bruker.');
            if ($action === 'reset') {
                $temporaryPassword = studio_new_password();
                $updated = studio_user_change($id, null, $temporaryPassword);
                $createdEmail = $updated['email'];
                $message = 'Passordet er tilbakestilt. Aktive økter er avsluttet.';
            } else {
                studio_user_change($id, $action === 'enable');
                $message = $action === 'enable' ? 'Brukeren er aktivert.' : 'Brukeren er deaktivert og aktive økter er avsluttet.';
            }
        } else {
            throw new InvalidArgumentException('Ukjent handling.');
        }
    } catch (Throwable $exception) {
        $temporaryPassword = null;
        $error = $exception instanceof InvalidArgumentException ? $exception->getMessage() : 'Endringen kunne ikke lagres. Kontroller at den private config-mappen er skrivbar.';
    }
}
$_SESSION['action_nonce'] = bin2hex(random_bytes(24));
try { $users = studio_users_read(); }
catch (Throwable $exception) { $users = []; $error = 'Brukerlisten kunne ikke leses.'; }
usort($users, static fn($a, $b) => strcmp($a['name'], $b['name']));
require dirname(__DIR__, 2) . '/app/views/head.php';
?>
<main id="main" class="login-wrap"><section class="login-card">
<p class="eyebrow">RADIO RUBBEN / STUDIO</p><h1>Brukere og tilgang</h1>
<p>Du er administrator. Studio-kontoer er egne innlogginger og trenger ikke Microsoft 365-konto.</p>
<p><a href="/">← Til oversikten</a></p>
<?php if (($config['local_users_enabled'] ?? false) !== true): ?><p class="notice">Studio-pålogging er ikke aktivert i privat konfigurasjon ennå. Kontoer kan klargjøres her.</p><?php endif; ?>
<?php if ($error): ?><p class="notice" role="alert"><?= escape($error) ?></p><?php endif; ?>
<?php if ($message): ?><p role="status"><?= escape($message) ?></p><?php endif; ?>
<?php if ($temporaryPassword !== null && $createdEmail !== null): ?>
<section aria-label="Midlertidig innlogging">
  <h2>Kopier nå – vises bare én gang</h2>
  <p>E-post: <strong><?= escape($createdEmail) ?></strong></p>
  <p>Midlertidig passord: <code><?= escape($temporaryPassword) ?></code></p>
  <p>Brukeren må velge nytt passord ved første innlogging. Send det midlertidige passordet via en sikker kanal.</p>
</section>
<?php endif; ?>
<h2>Opprett medarbeider</h2>
<form method="post" action="/admin/users.php" autocomplete="off">
  <input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>">
  <input type="hidden" name="action_nonce" value="<?= escape($_SESSION['action_nonce']) ?>">
  <input type="hidden" name="action" value="create">
  <p><label>Navn <input name="name" required maxlength="120"></label></p>
  <p><label>E-post <input type="email" name="email" required></label></p>
  <button class="button" type="submit">Opprett Studio-konto</button>
</form>
<h2>Eksisterende Studio-kontoer</h2>
<?php if (!$users): ?><p>Ingen medarbeidere er opprettet ennå.</p><?php endif; ?>
<?php foreach ($users as $entry): ?>
<section>
  <h3><?= escape($entry['name']) ?></h3>
  <p><?= escape($entry['email']) ?> · <?= $entry['enabled'] ? 'Aktiv' : 'Deaktivert' ?> · Medarbeider</p>
  <form method="post" action="/admin/users.php">
    <input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>">
    <input type="hidden" name="action_nonce" value="<?= escape($_SESSION['action_nonce']) ?>">
    <input type="hidden" name="id" value="<?= escape($entry['id']) ?>">
    <button class="button secondary" name="action" value="<?= $entry['enabled'] ? 'disable' : 'enable' ?>"><?= $entry['enabled'] ? 'Deaktiver' : 'Aktiver' ?></button>
    <button class="button secondary" name="action" value="reset">Tilbakestill passord</button>
  </form>
</section>
<?php endforeach; ?>
</section></main></body></html>
