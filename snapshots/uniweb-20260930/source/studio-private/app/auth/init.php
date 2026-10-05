<?php
require dirname(__DIR__) . '/bootstrap.php';
if (!in_array($config['auth_mode'], ['entra', 'vipps'], true)) {
    http_response_code(503);
    exit('Innlogging er ikke aktivert.');
}
$autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(503);
    exit('Innlogging er ikke ferdig installert. Kontakt administrator.');
}
require $autoload;
require __DIR__ . '/EntraClient.php';

require __DIR__ . '/VippsClient.php';
function studio_auth_client(array $config): \Jumbojett\OpenIDConnectClient
{
    return $config['auth_mode'] === 'vipps' ? new VippsClient($config) : new EntraClient($config);
}
