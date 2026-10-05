<?php
require dirname(__DIR__) . '/app/bootstrap.php';
$user = current_user();
if (!$user) redirect('/login.php');
if (($user['role'] ?? '') !== 'admin') { http_response_code(403); exit('Kun administrator kan administrere brukere.'); }
if ($config['auth_mode'] === 'entra') { require __DIR__ . '/local/admin-panel.php'; exit; }
$error = ''; $rows = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) { http_response_code(403); exit('Ugyldig forespørsel. Last siden på nytt.'); }
    try {
        foreach (['action', 'phone', 'name', 'role'] as $key) if (isset($_POST[$key]) && !is_string($_POST[$key])) throw new InvalidArgumentException('Ugyldig skjema.');
        studio_users_change($config, $_POST['action'] ?? '', $_POST['phone'] ?? '', $_POST['name'] ?? '', null, $_POST['role'] ?? 'producer');
        $_SESSION['users_notice'] = match ($_POST['action']) { 'add'=>'Brukeren er lagt til og kan nå logge inn med Vipps.', 'role'=>'Rollen er oppdatert.', default=>'Tilgangen er fjernet.' };
        redirect('/users.php');
    } catch (InvalidArgumentException $e) { $error = $e->getMessage(); }
      catch (Throwable $e) { $error = 'Kunne ikke lagre brukere. Prøv igjen eller kontakt administrator.'; }
} elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); header('Allow: GET, POST'); exit; }
try { $rows = studio_users_read(); } catch (Throwable $e) { $error = 'Brukerlisten kunne ikke leses. Ingen tilganger er endret.'; }
$notice = $_SESSION['users_notice'] ?? ''; unset($_SESSION['users_notice']);
require dirname(__DIR__) . '/app/views/head.php';
?>
<link rel="stylesheet" href="/users.css?v=20260924">
<div class="shell">
<?php $activePage = 'users'; require dirname(__DIR__) . '/app/views/sidebar.php'; ?>
<div class="workspace">
<header class="topbar"><span>Arbeidsrom / <strong>Brukere</strong></span><span>Administrator</span><?php require dirname(__DIR__) . '/app/views/account.php'; ?></header>
<main id="main">
<div class="heading-row"><div><p class="eyebrow">RADIO RUBBEN STUDIO</p><h1>Brukere</h1><p class="intro">Bestem hvem som får tilgang til arbeidsrommet.</p></div></div>
<?php if ($error): ?><p role="alert"><?= escape($error) ?></p><?php endif; ?>
<?php if ($notice): ?><p role="status"><?= escape($notice) ?></p><?php endif; ?>
<section class="users-panel"><h2>Roller</h2><p><strong>Administrator:</strong> Alt, inkludert brukere. <strong>Produsent:</strong> Studio og tekniske innstillinger. <strong>Programleder:</strong> Musikk og stikk/manus. <strong>Observatør:</strong> Lesevisning uten betjening.</p><p class="small">Din beskyttede administratortilgang kan ikke fjernes her. Rolleendringer gjelder ved neste forespørsel; åpne sider bør lastes på nytt.</p></section>
<section class="users-panel" aria-labelledby="add-user"><h2 id="add-user">Legg til medarbeider</h2>
<p>Bruk telefonnummeret personen har registrert i Vipps. Velg hva personen skal ha tilgang til. Ingen invitasjon sendes automatisk.</p>
<form method="post" class="users-form">
<input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="add">
<p><label for="user-name">Navn</label><br><input id="user-name" name="name" maxlength="120" autocomplete="off" required></p>
<p><label for="user-phone">Telefonnummer</label><br><input id="user-phone" name="phone" type="tel" placeholder="+47 …" maxlength="30" autocomplete="off" required></p>
<p><label for="user-role">Rolle</label><select id="user-role" name="role"><?php foreach (studio_roles() as $value=>$label): ?><option value="<?= escape($value) ?>" <?= $value === 'producer' ? 'selected' : '' ?>><?= escape($label) ?></option><?php endforeach; ?></select></p>
<button type="submit" class="button">Gi tilgang</button>
</form></section>
<section class="users-panel" aria-labelledby="user-list"><h2 id="user-list">Personer med tilgang</h2>
<?php foreach ($config['vipps_allowed_phones'] as $phone): ?><p><strong>Administrator</strong> · <?= escape($phone) ?><br><span class="small">Beskyttet administratortilgang</span></p><?php endforeach; ?>
<?php if (!$rows): ?><p>Ingen medarbeidere er lagt til ennå.</p><?php endif; ?>
<?php foreach ($rows as $row): ?>
<form method="post"><p><strong><?= escape($row['name']) ?></strong> · <?= escape($row['phone']) ?> · <?= escape(studio_roles()[$row['role']]) ?></p>
<input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>"><input type="hidden" name="phone" value="<?= escape($row['phone']) ?>">
<label>Rolle for <?= escape($row['name']) ?> <select name="role"><?php foreach (studio_roles() as $value=>$label): ?><option value="<?= escape($value) ?>" <?= $value === $row['role'] ? 'selected' : '' ?>><?= escape($label) ?></option><?php endforeach; ?></select></label>
<button class="button" type="submit" name="action" value="role">Lagre rolle</button>
<button class="button" type="submit" name="action" value="remove" aria-label="<?= escape('Fjern tilgang for '.$row['name']) ?>">Fjern tilgang</button><hr></form>
<?php endforeach; ?>
<p class="small">Fjerning gjelder ved neste forespørsel til Studio, også for personer som allerede er innlogget.</p>
</section>
</main></div></div></body></html>
