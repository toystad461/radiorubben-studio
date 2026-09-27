<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/auth/init.php';
require dirname(__DIR__, 2) . '/app/auth/StudioAdmin.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET' || $_GET !== [] || !studio_is_admin(current_user())) {
    http_response_code(403); exit('Ingen tilgang.');
}
$_REQUEST = [];
$_SESSION['auth_started'] = time();
$_SESSION['auth_flow'] = 'admin';
$_SESSION['auth_admin_oid'] = $_SESSION['user']['oid'];
try {
    (new EntraClient($config, true))->authenticate();
} catch (Throwable $error) {
    unset($_SESSION['auth_started'], $_SESSION['auth_flow'], $_SESSION['auth_admin_oid']);
    error_log('Studio: administrator authorization could not start.');
    redirect('/admin/users.php?error=connect');
}
