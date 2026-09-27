<?php
declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
require __DIR__ . '/config.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/auth/StudioUsers.php';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; img-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
$config = load_config();
// All entry points stay closed until launch is explicitly enabled server-side.
$siteMode = getenv('STUDIO_SITE_MODE') !== false ? getenv('STUDIO_SITE_MODE') : ($config['site_mode'] ?? 'coming-soon');
if ($siteMode !== 'app') {
    http_response_code(503);
    header('Retry-After: 86400');
    header('X-Robots-Tag: noindex, nofollow');
    ?>
<!doctype html>
<html lang="nb">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Radio Rubben Studio – snart klart</title>
<link rel="stylesheet" href="/assets/studio.css?v=20260923-brand">
</head>
<body>
<main id="main" class="login-wrap">
<section class="login-card" aria-labelledby="waiting-title">
<img class="login-logo" src="/assets/radio-rubben-logo.png" alt="Radio Rubben" width="2172" height="724">
<p class="eyebrow">RADIO RUBBEN / STUDIO</p>
<p class="hero-label"><span class="station-dot" aria-hidden="true"></span> VI GJØR KLART</p>
<h1 id="waiting-title">God radio starter her.</h1>
<p>Vi klargjør det nye arbeidsrommet for Radio Rubben. Snart samler vi innhold, samarbeid og gode sendinger på ett sted.</p>
<p>Studio åpner for medarbeidere når alt er klart.</p>
<a class="button" href="https://www.radiorubben.no/">Besøk Radio Rubben <span aria-hidden="true">→</span></a>
<p class="small">Lokal · Inkluderende · Verdig · Engasjerende</p>
</section>
</main>
</body>
</html>
<?php
    exit;
}
if (!config_valid($config)) {
    http_response_code(503);
    exit('Studio er ikke ferdig konfigurert. Kontakt administrator.');
}
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
if ($config['auth_mode'] === 'entra' && str_starts_with($config['base_url'], 'https://') && !$https) {
    http_response_code(503);
    exit('Studio krever HTTPS.');
}
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('rubben_studio');
session_set_cookie_params(['lifetime'=>0, 'path'=>'/', 'secure'=>$https, 'httponly'=>true, 'samesite'=>'Lax']);
session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
function current_user(): ?array
{
    global $config;
    if ($config['auth_mode'] !== 'entra' || !isset($_SESSION['user'], $_SESSION['expires'])
        || $_SESSION['expires'] <= time()) {
        unset($_SESSION['user'], $_SESSION['expires']);
        return null;
    }
    $user = $_SESSION['user'];
    if (($user['provider'] ?? 'entra') !== 'local') return $user;
    if (($config['local_users_enabled'] ?? false) !== true || !is_string($user['id'] ?? null)) {
        unset($_SESSION['user'], $_SESSION['expires']);
        return null;
    }
    $record = studio_user_by_id($user['id']);
    if (!$record || !$record['enabled'] || ($user['version'] ?? null) !== $record['version']) {
        unset($_SESSION['user'], $_SESSION['expires']);
        return null;
    }
    return ['provider'=>'local', 'id'=>$record['id'], 'name'=>$record['name'],
        'email'=>$record['email'], 'role'=>$record['role'], 'version'=>$record['version'],
        'mustChange'=>$record['mustChange']];
}
$activeUser = current_user();
if (($activeUser['provider'] ?? null) === 'local' && ($activeUser['mustChange'] ?? false)
    && !in_array(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), ['/local/change-password.php', '/logout.php'], true)) {
    redirect('/local/change-password.php');
}
