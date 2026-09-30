<?php
require dirname(__DIR__, 2) . '/studio-private/app/auth/init.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || $_GET !== []) { http_response_code(400); exit('Ugyldig forespørsel.'); }
$_REQUEST = [];
$_SESSION['auth_started'] = time();
$_SESSION['auth_provider'] = $config['auth_mode'];
try {
    (studio_auth_client($config))->authenticate();
} catch (Throwable $error) {
    // Never log the exception text: provider errors may contain credentials/tokens.
    error_log('Studio: authorization could not start.');
    redirect('/login.php?error=signin');
}
