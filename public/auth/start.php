<?php
require dirname(__DIR__, 2) . '/app/auth/init.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || $_GET !== []) { http_response_code(400); exit('Ugyldig forespørsel.'); }
$_REQUEST = [];
unset($_SESSION['auth_flow'], $_SESSION['auth_admin_oid'], $_SESSION['graph_token'], $_SESSION['graph_expires']);
$_SESSION['auth_started'] = time();
try {
    (new EntraClient($config))->authenticate();
} catch (Throwable $error) {
    // Never log the exception text: provider errors may contain credentials/tokens.
    error_log('Studio: Microsoft authorization could not start.');
    redirect('/login.php?error=signin');
}
