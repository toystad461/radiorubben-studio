<?php
/** Plugin Name: Radio Rubben — isolert VPS-test */
if (wp_get_environment_type() !== 'staging') {
    http_response_code(503);
    exit('Testoppsettet krever staging.');
}
add_filter('pre_wp_mail', '__return_true');
add_filter('pre_http_request', static fn() => new WP_Error('rr_test_offline', 'Eksterne kall er sperret i testmiljøet.'), PHP_INT_MAX);
add_filter('wp_is_application_passwords_available', '__return_false');
add_filter('xmlrpc_enabled', '__return_false');
add_filter('pre_option_blog_public', static fn() => '0');
add_filter('rest_authentication_errors', static function ($result) {
    return $result ?: (is_user_logged_in() ? null : new WP_Error('rr_test_private', 'Innlogging kreves.', ['status' => 401]));
});
add_action('template_redirect', static function () {
    nocache_headers();
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    if (!is_user_logged_in()) auth_redirect();
}, -100);
add_action('wp_footer', static function () {
    echo '<p style="padding:1rem;background:#ffed99;color:#111;text-align:center">Radio Rubben TEST — egne testdata. Ingen sending eller produksjonspublisering.</p>';
});
