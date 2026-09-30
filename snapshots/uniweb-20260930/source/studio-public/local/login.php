<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/studio-private/app/bootstrap.php';
if (($config['local_users_enabled'] ?? false) !== true || $config['auth_mode'] !== 'entra') {
    http_response_code(404); exit('Siden finnes ikke.');
}
if (current_user()) redirect('/');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        http_response_code(403); exit('Ugyldig forespørsel.');
    }
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $allowed = studio_local_valid_email($email) && strlen($password) <= 256
        && studio_local_login_allowed($email, $ip);
    $record = $allowed ? studio_local_user_by_email($email) : null;
    if ($allowed && $record && $record['enabled'] && password_verify($password, $record['hash'])) {
        studio_local_login_allowed($email, $ip, 'success');
        session_regenerate_id(true);
        $_SESSION = [
            'user' => ['provider'=>'local', 'id'=>$record['id'], 'version'=>$record['version']],
            'expires' => time() + 28800, 'csrf' => bin2hex(random_bytes(32)),
        ];
        redirect($record['mustChange'] ? '/local/change-password.php' : '/');
    }
    if ($allowed) studio_local_login_allowed($email, $ip, 'fail');
    $error = 'Innloggingen kunne ikke fullføres. Kontroller opplysningene eller kontakt administrator.';
}
require dirname(__DIR__, 2) . '/studio-private/app/views/head.php';
?>
<main id="main" class="login-wrap"><section class="login-card">
<img class="login-logo" src="/assets/radio-rubben-logo.png" alt="Radio Rubben" width="2172" height="724">
<p class="eyebrow">RADIO RUBBEN / STUDIO</p><h1>Studio-konto</h1>
<?php if ($error): ?><p class="notice" role="alert"><?= escape($error) ?></p><?php endif; ?>
<form method="post" action="/local/login.php">
  <input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>">
  <p><label>E-post <input type="email" name="email" autocomplete="username" required></label></p>
  <p><label>Passord <input type="password" name="password" autocomplete="current-password" required></label></p>
  <button type="submit" class="button">Logg inn</button>
</form>
<p><a href="/login.php">Tilbake til Microsoft-innlogging</a></p>
</section></main></body></html>
