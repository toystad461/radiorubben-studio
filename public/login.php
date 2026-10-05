<?php
require dirname(__DIR__) . '/app/bootstrap.php';
if (current_user()) redirect('/');
require dirname(__DIR__) . '/app/views/head.php';
?>
<main id="main" class="login-wrap"><section class="login-card">
<img class="login-logo" src="/assets/radio-rubben-logo.png" alt="Radio Rubben" width="2400" height="1073"><p class="eyebrow">RADIO RUBBEN / STUDIO</p><h1>Velkommen inn.</h1><p>Arbeidsrommet for deg som lager Radio Rubben.</p>
<?php if (isset($_GET['error'])): ?><p role="alert" class="notice">Innloggingen kunne ikke fullføres. Prøv igjen eller kontakt administrator.</p><?php endif; ?>
<?php if ($config['auth_mode'] !== 'demo'): ?>
<a class="button" href="/auth/start.php">Logg inn med <?= $config['auth_mode'] === 'vipps' ? 'Vipps' : 'Microsoft' ?></a>
<?php if ($config['auth_mode'] === 'entra' && ($config['local_users_enabled'] ?? false) === true): ?><p><a href="/local/login.php">Logg inn med Studio-konto</a></p><?php endif; ?>
<?php else: ?><p class="notice">Vipps-innlogging klargjøres. Du kan utforske demonstrasjonen uten konto.</p><a class="button" href="/">Åpne demonstrasjonen →</a><?php endif; ?>
<p class="small">Kun for medarbeidere i Radio Rubben.</p></section></main></body></html>
