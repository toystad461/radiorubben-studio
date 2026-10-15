<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || gethostname() !== 'vps-5363103') exit(1);
ini_set('display_errors', '0');
ini_set('log_errors', '0');
ini_set('zend.exception_ignore_args', '1');
ob_start(static fn(string $output): string => '');
try {
    $action = $argv[1] ?? '';
    if (!in_array($action, ['check', 'check-new', 'rotate'], true)) throw new RuntimeException();
    require '/srv/radiorubben-test/studio/app/auth/StudioLocalUsers.php';
    $user = studio_local_user_by_email('thomas@example.test');
    if (!$user || !$user['enabled'] || $user['role'] !== 'admin') throw new RuntimeException();
    if ($action !== 'check') {
        $password = (string)stream_get_contents(STDIN);
        if (strlen($password) < 14 || strlen($password) > 72 || trim($password) !== $password || preg_match('/[\x00-\x1f\x7f]/', $password)) throw new RuntimeException();
        if (password_verify($password, $user['hash'])) throw new RuntimeException();
        if ($action === 'rotate') {
            // Existing API increments version, invalidating previous sessions.
            $updated = studio_local_user_set_password($user['id'], $password);
            if (!password_verify($password, $updated['hash']) || $updated['version'] <= $user['version']) throw new RuntimeException();
        }
        unset($password);
    }
    fwrite(STDOUT, "Studio-testkonto: OK.\n");
} catch (Throwable $error) {
    unset($password);
    fwrite(STDERR, "Studio-testkonto: kontrollen feilet. Bruk et nytt passord uten innledende/avsluttende mellomrom.\n");
    exit(1);
}
