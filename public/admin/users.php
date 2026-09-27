<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require dirname(__DIR__, 2) . '/app/auth/StudioAdmin.php';
require dirname(__DIR__, 2) . '/app/auth/StudioGraph.php';
if (!studio_is_admin(current_user())) { http_response_code(403); exit('Ingen tilgang.'); }

$message = '';
$error = '';
$temporaryPassword = null;
$createdUpn = null;
$createdId = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])
        || !is_string($_POST['action_nonce'] ?? null)
        || !hash_equals($_SESSION['action_nonce'] ?? '', $_POST['action_nonce'])) {
        http_response_code(403); exit('Ugyldig forespørsel.');
    }
    unset($_SESSION['action_nonce']);
    if (studio_graph_token() === null) { http_response_code(403); exit('Entra-tilkoblingen er utløpt.'); }
    try {
        $upn = strtolower(trim((string) ($_POST['upn'] ?? '')));
        if (!studio_valid_upn($upn)) throw new InvalidArgumentException('Bruk en adresse på radiorubben.no.');
        if (($_POST['action'] ?? '') === 'create') {
            $name = trim((string) ($_POST['name'] ?? ''));
            if (strlen($name) < 2 || strlen($name) > 120) throw new InvalidArgumentException('Oppgi fullt navn.');
            $temporaryPassword = studio_temporary_password();
            $created = studio_graph_create_user($name, $upn, $temporaryPassword);
            $createdUpn = $upn;
            $createdId = $created['id'];
            $message = 'Konto opprettet. ';
            $assigned = studio_graph_assign($createdId, $config['client_id']);
            $message .= $assigned ? 'Studio-tilgang tildelt.' : 'Studio-tilgang finnes allerede.';
        } elseif (($_POST['action'] ?? '') === 'assign') {
            $existing = studio_graph_user($upn);
            if (!$existing || !is_string($existing['id'] ?? null)) throw new RuntimeException('Fant ikke kontoen i Entra.');
            $assigned = studio_graph_assign($existing['id'], $config['client_id']);
            $message = $assigned ? 'Studio-tilgang tildelt ' . $upn . '.' : 'Kontoen har allerede Studio-tilgang.';
        } else {
            throw new InvalidArgumentException('Ukjent handling.');
        }
    } catch (Throwable $exception) {
        // Do not print Graph response bodies or exception details; they may contain private data.
        $error = $exception instanceof InvalidArgumentException
            ? $exception->getMessage()
            : 'Kunne ikke fullføre i Entra. Kontroller rettigheter og forsøk igjen.';
        if ($createdId !== null) $error .= ' Kontoen ble opprettet, men tildelingen må prøves igjen med skjemaet for eksisterende konto.';
    }
}
$_SESSION['action_nonce'] = bin2hex(random_bytes(24));
$connected = studio_graph_token() !== null;
$assignedUsers = [];
if ($connected) {
    try {
        $serviceId = studio_graph_service_principal($config['client_id']);
        $list = studio_graph_request('GET', '/servicePrincipals/' . $serviceId . '/appRoleAssignedTo?$select=principalId,principalDisplayName,principalType&$top=100');
        if ($list['status'] === 200) $assignedUsers = $list['data']['value'] ?? [];
    } catch (Throwable $exception) {
        $error = $error ?: 'Brukerlisten kunne ikke hentes fra Entra.';
    }
}
require dirname(__DIR__, 2) . '/app/views/head.php';
?>
<main id="main" class="login-wrap"><section class="login-card">
<p class="eyebrow">RADIO RUBBEN / STUDIO</p>
<h1>Brukere og tilgang</h1>
<p>Administrator: <?= escape((string) current_user()['name']) ?>. Kontoene ligger i Microsoft Entra; Studio tildeler bare tilgang til denne appen.</p>
<p><a href="/">← Til oversikten</a></p>
<?php if (isset($_GET['error'])): ?><p class="notice" role="alert">Entra-tilkoblingen ble ikke fullført. Kontroller API-samtykke og administratorrolle.</p><?php endif; ?>
<?php if ($error): ?><p class="notice" role="alert"><?= escape($error) ?></p><?php endif; ?>
<?php if ($message): ?><p role="status"><?= escape($message) ?></p><?php endif; ?>
<?php if ($temporaryPassword !== null && $createdUpn !== null): ?>
  <section aria-label="Midlertidig innlogging">
    <h2>Ta vare på innloggingsinformasjonen nå</h2>
    <p>Konto: <strong><?= escape($createdUpn) ?></strong></p>
    <p>Midlertidig passord: <code><?= escape($temporaryPassword) ?></code></p>
    <p>Passordet vises bare på denne siden. Brukeren må bytte det ved første pålogging. Del det gjennom en sikker kanal.</p>
  </section>
<?php endif; ?>
<?php if (!$connected): ?>
  <p>For å opprette kontoer eller tildele Studio-tilgang må du godkjenne en tidsbegrenset Microsoft Graph-tilkobling. Vanlig Studio-innlogging ber ikke om disse rettighetene.</p>
  <a class="button" href="/admin/connect.php">Koble til Entra for brukeradministrasjon</a>
<?php else: ?>
  <h2>Opprett ny Microsoft-konto</h2>
  <form method="post" action="/admin/users.php" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>">
    <input type="hidden" name="action_nonce" value="<?= escape($_SESSION['action_nonce']) ?>">
    <input type="hidden" name="action" value="create">
    <p><label>Fullt navn <input name="name" required maxlength="120"></label></p>
    <p><label>E-post / brukernavn <input name="upn" type="email" required placeholder="navn@radiorubben.no"></label></p>
    <button class="button" type="submit">Opprett konto og gi Studio-tilgang</button>
  </form>
  <h2>Gi eksisterende Microsoft-konto tilgang</h2>
  <form method="post" action="/admin/users.php">
    <input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>">
    <input type="hidden" name="action_nonce" value="<?= escape($_SESSION['action_nonce']) ?>">
    <input type="hidden" name="action" value="assign">
    <p><label>E-post / brukernavn <input name="upn" type="email" required placeholder="navn@radiorubben.no"></label></p>
    <button class="button" type="submit">Gi Studio-tilgang</button>
  </form>
  <h2>Tilordnet Studio</h2>
  <?php if (!$assignedUsers): ?><p>Ingen tilordninger å vise ennå.</p><?php endif; ?>
  <ul>
  <?php foreach ($assignedUsers as $entry): if (($entry['principalType'] ?? '') !== 'User') continue; ?>
    <li><?= escape((string) ($entry['principalDisplayName'] ?? 'Bruker')) ?><?= ($entry['principalId'] ?? null) === STUDIO_OWNER_OID ? ' · Administrator' : ' · Medarbeider' ?></li>
  <?php endforeach; ?>
  </ul>
<?php endif; ?>
</section></main></body></html>
