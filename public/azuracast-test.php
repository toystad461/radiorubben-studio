<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
// Bootstrap's waiting-page/HTTPS/session rules run first. No preview or authentication bypass.
if (($config['azuracast_test_enabled'] ?? false) !== true) {
    http_response_code(404);
    exit('Siden finnes ikke.');
}
if ($config['auth_mode'] !== 'entra') {
    http_response_code(403);
    exit('AzuraCast-test krever Microsoft-innlogging.');
}
if (!current_user()) redirect('/login.php');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    exit('Bare lesing er tillatt.');
}
require dirname(__DIR__) . '/app/integrations/AzuraCastClient.php';
$nowPlaying = null;
$error = null;
$isMock = false;
try {
    $client = new \RadioRubben\Integrations\AzuraCastClient($config);
    $isMock = $client->isMock();
    $nowPlaying = $client->nowPlaying();
} catch (\RuntimeException $exception) {
    http_response_code(502);
    $error = $exception->getMessage();
}
require dirname(__DIR__) . '/app/views/head.php';
require dirname(__DIR__) . '/app/views/azuracast-test.php';
