<?php
declare(strict_types=1);
// Owner-operated CLI only. Passwords arrive on stdin, never in CLI arguments.
if (PHP_SAPI !== 'cli' || gethostname() !== 'vps-5363103') exit(1);
ini_set('display_errors', '0');
ini_set('log_errors', '0');
ini_set('zend.exception_ignore_args', '1');
ob_start(static fn(string $output): string => '');
try {
    $rr_account_action = $argv[1] ?? '';
    if (!in_array($rr_account_action, ['install', 'check', 'check-new', 'rotate'], true)) throw new RuntimeException();
    if ($rr_account_action === 'install') define('WP_INSTALLING', true);
    $_SERVER['HTTP_HOST'] = '5.249.252.78:8443';
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['REQUEST_URI'] = '/';
    require '/srv/radiorubben-test/wordpress/wp-load.php';
    if (DB_NAME !== 'rr_test_wp' || wp_get_environment_type() !== 'staging') throw new RuntimeException();
    add_filter('pre_wp_mail', '__return_true', PHP_INT_MAX);
    if ($rr_account_action === 'install') {
        if (is_blog_installed()) throw new RuntimeException();
    } else {
        $user = get_user_by('login', 'thomas');
        if (!$user || $user->user_email !== 'thomas@example.test' || !in_array('administrator', $user->roles, true)) throw new RuntimeException();
    }
    if ($rr_account_action !== 'check') {
        $password = (string)stream_get_contents(STDIN);
        if (strlen($password) < 14 || strlen($password) > 72 || trim($password) !== $password || preg_match('/[\x00-\x1f\x7f]/', $password)) throw new RuntimeException();
        if ($rr_account_action !== 'install' && wp_check_password($password, $user->user_pass, $user->ID)) throw new RuntimeException();
        if ($rr_account_action === 'install') {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $result = wp_install('Radio Rubben TEST', 'thomas', 'thomas@example.test', false, '', $password, 'nb_NO');
            $id = (int)($result['user_id'] ?? 0);
            unset($result);
        } elseif ($rr_account_action === 'rotate') {
            $id = (int)$user->ID;
            wp_set_password($password, $id);
            WP_Session_Tokens::get_instance($id)->destroy_all();
        }
        if (isset($id)) {
            $updated = get_userdata($id);
            if (!$updated || !wp_check_password($password, $updated->user_pass, $id)) throw new RuntimeException();
        }
        unset($password);
    }
    fwrite(STDOUT, "WordPress-testkonto: OK.\n");
} catch (Throwable $error) {
    unset($password);
    fwrite(STDERR, "WordPress-testkonto: kontrollen feilet. Bruk et nytt passord uten innledende/avsluttende mellomrom.\n");
    exit(1);
}
