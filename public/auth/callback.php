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
    $adminFlow = ($_SESSION['auth_flow'] ?? null) === 'admin';
    $oidc = new EntraClient($config, $adminFlow);
    if (!$oidc->authenticate()) throw new RuntimeException('Authentication failed');
    $claims = $oidc->getVerifiedClaims();
    if ($adminFlow) {
        require dirname(__DIR__, 2) . '/app/auth/StudioAdmin.php';
        $user = current_user();
        if (!studio_is_admin($user) || !hash_equals((string) ($_SESSION['auth_admin_oid'] ?? ''), (string) $claims->oid)
            || !hash_equals((string) $user['oid'], (string) $claims->oid)) throw new RuntimeException('Admin identity mismatch');
        $token = $oidc->getAccessToken();
        if (!is_string($token) || $token === '') throw new RuntimeException('No Graph token');
        session_regenerate_id(true);
        unset($_SESSION['auth_started'], $_SESSION['auth_flow'], $_SESSION['auth_admin_oid']);
        $_SESSION['graph_token'] = $token;
        $_SESSION['graph_expires'] = min(time() + 2700, $claims->exp - 30);
        redirect('/admin/users.php');
    }
    session_regenerate_id(true);
    $_SESSION = [
        'user'=>['id'=>$claims->sub, 'oid'=>strtolower((string) $claims->oid), 'name'=>(string)($claims->name ?? 'medarbeider')],
        'expires'=>min(time() + 28800, $claims->exp),
        'csrf'=>bin2hex(random_bytes(32)),
    ];
    redirect('/');
} catch (Throwable $error) {
    $adminFailure = ($_SESSION['auth_flow'] ?? null) === 'admin';
    unset($_SESSION['auth_started'], $_SESSION['auth_flow'], $_SESSION['auth_admin_oid'],
        $_SESSION['openid_connect_state'], $_SESSION['openid_connect_nonce'], $_SESSION['openid_connect_code_verifier']);
    error_log('Studio: Microsoft callback rejected.');
    redirect($adminFailure ? '/admin/users.php?error=connect' : '/login.php?error=signin');
}
