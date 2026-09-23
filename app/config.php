<?php
declare(strict_types=1);
function config_valid(array $config): bool
{
    $mode = $config['auth_mode'] ?? '';
    if ($mode === 'demo') return true;
    if ($mode !== 'entra') return false;
    $uuid = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
    $url = parse_url($config['base_url'] ?? '');
    $validUrl = is_array($url) && !isset($url['user']) && !isset($url['pass'])
        && !isset($url['query']) && !isset($url['fragment'])
        && in_array($url['path'] ?? '', ['', '/'], true)
        && (($url['scheme'] ?? '') === 'https' || (($url['scheme'] ?? '') === 'http' && in_array($url['host'] ?? '', ['localhost', '127.0.0.1'], true)));
    return $validUrl && !empty($url['host'])
        && preg_match($uuid, $config['tenant_id'] ?? '') === 1
        && preg_match($uuid, $config['client_id'] ?? '') === 1
        && trim($config['client_secret'] ?? '') !== '';
}
function load_config(): array
{
    $config = require dirname(__DIR__) . '/config/example.php';
    $local = dirname(__DIR__) . '/config/local.php';
    if (is_file($local)) $config = array_replace($config, require $local);
    foreach (['STUDIO_AUTH_MODE'=>'auth_mode', 'STUDIO_BASE_URL'=>'base_url', 'ENTRA_TENANT_ID'=>'tenant_id', 'ENTRA_CLIENT_ID'=>'client_id', 'ENTRA_CLIENT_SECRET'=>'client_secret'] as $env=>$key) {
        if (getenv($env) !== false) $config[$key] = getenv($env);
    }
    return $config;
}
