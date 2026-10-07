<?php
declare(strict_types=1);
// Local/CI checks only: no requests, session, configuration writes or secret output.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__).'/app/config.php';
function entra_preflight(array $config): array
{
    $url = is_string($config['base_url'] ?? null) ? parse_url($config['base_url']) : false;
    $uuid = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD';
    $checks = [
        'entra_mode' => ($config['auth_mode'] ?? null) === 'entra',
        'tenant_uuid' => is_string($config['tenant_id'] ?? null) && preg_match($uuid, $config['tenant_id']) === 1,
        'client_uuid' => is_string($config['client_id'] ?? null) && preg_match($uuid, $config['client_id']) === 1,
        'secret_present' => is_string($config['client_secret'] ?? null) && trim($config['client_secret']) !== '',
        'https_origin' => is_array($url) && ($url['scheme'] ?? '') === 'https'
            && !empty($url['host']) && !isset($url['user'], $url['pass'])
            && !isset($url['user']) && !isset($url['pass'])
            && !isset($url['query']) && !isset($url['fragment'])
            && in_array($url['path'] ?? '', ['', '/'], true),
        'local_fallback_enabled' => ($config['local_users_enabled'] ?? false) === true,
    ];
    return $checks;
}
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        $checks = entra_preflight(load_config());
        $checks['php_version'] = PHP_VERSION_ID >= 80200;
        foreach (['curl','json','openssl','session'] as $extension) $checks['extension_'.$extension] = extension_loaded($extension);
        $checks['vendor_installed'] = is_file(dirname(__DIR__).'/vendor/autoload.php');
        foreach ($checks as $label=>$ok) echo ($ok ? 'PASS' : 'BLOCKED').': '.$label.PHP_EOL;
        echo "MANUAL: app assignment, member/guest login, fallback account, permissions, logout and web-server HTTPS remain unverified.".PHP_EOL;
        exit(in_array(false, $checks, true) ? 1 : 0);
    } catch (Throwable $error) {
        fwrite(STDERR, "BLOCKED: configuration could not be read. No values logged.\n");
        exit(1);
    }
}
