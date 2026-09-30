<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
$user = current_user();
if (($user['provider'] ?? null) !== 'local') { http_response_code(403); exit('Ingen tilgang.'); }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        http_response_code(403); exit('Ugyldig forespørsel.');
    }
    $password = (string) ($_POST['password'] ?? '');
    if (strlen($password) < 14 || strlen($password) > 72 || $password !== ($_POST['confirm'] ?? null)) {
        $error = 'Velg minst 14 tegn (maks 72) og bekreft samme passord.';
    } else {
        $record = studio_local_user_by_id($user['id']);
        if (!$record || password_verify($password, $record['hash'])) {
            $error = 'Velg et nytt passord.';
        } else {
            $updated = studio_local_user_set_password($user['id'], $password);
            session_regenerate_id(true);
            $_SESSION['user']['version'] = $updated['version'];
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            redirect('/');
        }
    }
}
require dirname(__DIR__, 2) . '/app/views/head.php';
?>
<main id="main" class="login-wrap"><section class="login-card">
<p class="eyebrow">RADIO RUBBEN / STUDIO</p><h1>Velg nytt passord</h1>
<p>Dette passordet brukes bare til Studio-kontoen din.</p>
<?php if ($error): ?><p role="alert" class="notice"><?= escape($error) ?></p><?php endif; ?>
<form method="post" action="/local/change-password.php">
  <input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>">
  <p><label>Nytt passord <input type="password" name="password" autocomplete="new-password" minlength="14" maxlength="72" required></label></p>
  <p><label>Gjenta passordet <input type="password" name="confirm" autocomplete="new-password" minlength="14" maxlength="72" required></label></p>
  <button class="button" type="submit">Lagre passord</button>
</form>
<form method="post" action="/logout.php"><input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>"><button type="submit" class="button secondary">Logg ut</button></form>
</section></main></body></html>
