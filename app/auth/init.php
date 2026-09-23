<?php
require dirname(__DIR__) . '/bootstrap.php';
if ($config['auth_mode'] !== 'entra') {
    http_response_code(503);
    exit('Microsoft-innlogging er ikke aktivert.');
}
$autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(503);
    exit('Innlogging er ikke ferdig installert. Kontakt administrator.');
}
require $autoload;
require __DIR__ . '/EntraClient.php';
