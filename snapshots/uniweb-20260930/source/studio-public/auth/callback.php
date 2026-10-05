<?php
require dirname(__DIR__, 2) . '/studio-private/app/auth/init.php';
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET'
        || !isset($_GET['code'], $_GET['state'], $_SESSION['openid_connect_state'], $_SESSION['auth_started'])
        || !is_string($_GET['code']) || !is_string($_GET['state'])
        || !hash_equals($_SESSION['openid_connect_state'], $_GET['state'])
        || ($_SESSION['auth_provider'] ?? null) !== $config['auth_mode']
        || time() - $_SESSION['auth_started'] > 600) throw new RuntimeException('Invalid callback');
    // Only query parameters enter the OIDC callback, never cookies or POST parameters.
    $_REQUEST = $_GET;
    $oidc = studio_auth_client($config);
    if (!$oidc->authenticate()) throw new RuntimeException('Authentication failed');
    $claims = $oidc->getVerifiedClaims();
    $user = $config['auth_mode'] === 'vipps'
        ? VippsClient::authorizedUser($claims, $oidc->requestUserInfo(), studio_login_phones($config))
        : ['id'=>$claims->sub, 'name'=>(string)($claims->name ?? 'medarbeider'),
            'provider'=>'entra', 'oid'=>strtolower((string)($claims->oid ?? ''))];
    session_regenerate_id(true);
    $_SESSION = [
        'user'=>$user,
        'expires'=>min(time() + 28800, $claims->exp),
        'csrf'=>bin2hex(random_bytes(32)),
    ];
    redirect('/');
} catch (Throwable $error) {
    unset($_SESSION['auth_provider'], $_SESSION['auth_started'], $_SESSION['openid_connect_state'], $_SESSION['openid_connect_nonce'], $_SESSION['openid_connect_code_verifier']);
    error_log('Studio: callback rejected.');
    redirect('/login.php?error=signin');
}
