<?php
require dirname(__DIR__, 2) . '/app/auth/init.php';
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET'
        || !isset($_GET['code'], $_GET['state'], $_SESSION['openid_connect_state'], $_SESSION['auth_started'])
        || !is_string($_GET['code']) || !is_string($_GET['state'])
        || !hash_equals($_SESSION['openid_connect_state'], $_GET['state'])
        || time() - $_SESSION['auth_started'] > 600) throw new RuntimeException('Invalid callback');
    // Only query parameters enter the OIDC callback, never cookies or POST parameters.
    $_REQUEST = $_GET;
    $oidc = new EntraClient($config);
    if (!$oidc->authenticate()) throw new RuntimeException('Authentication failed');
    $claims = $oidc->getVerifiedClaims();
    session_regenerate_id(true);
    $_SESSION = [
        'user'=>['id'=>$claims->sub, 'name'=>(string)($claims->name ?? 'medarbeider')],
        'expires'=>min(time() + 28800, $claims->exp),
        'csrf'=>bin2hex(random_bytes(32)),
    ];
    redirect('/');
} catch (Throwable $error) {
    unset($_SESSION['auth_started'], $_SESSION['openid_connect_state'], $_SESSION['openid_connect_nonce'], $_SESSION['openid_connect_code_verifier']);
    error_log('Studio: Microsoft callback rejected.');
    redirect('/login.php?error=signin');
}
