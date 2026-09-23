<?php
declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
require __DIR__ . '/config.php';
require __DIR__ . '/helpers.php';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; img-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
$config = load_config();
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
    if (!isset($_SESSION['user'], $_SESSION['expires']) || $_SESSION['expires'] <= time()) {
        unset($_SESSION['user'], $_SESSION['expires']);
        return null;
    }
    return $_SESSION['user'];
}
