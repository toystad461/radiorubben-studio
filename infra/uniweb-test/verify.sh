#!/usr/bin/env bash
set -Eeuo pipefail
[[ $(id -u) == 0 && $(hostname) == vps-5363103 ]]
root=/srv/radiorubben-test
systemctl is-active --quiet nginx php8.3-fpm mariadb
[[ $(systemctl show php8.3-fpm -p PrivateNetwork --value) == yes ]]
test "$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8080/local/login.php)" == 200
test "$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8080/)" == 303
test "$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8080/auth/start.php)" == 403
test "$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8080/config/local.php)" == 404
test "$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8081/wordpress/wp-config.php)" == 403
test "$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8081/studio-data/local.php)" == 404
test "$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1/)" == 404
probe=$(mktemp "$root/studio/public/rr-isolation-XXXXXXXX.php")
trap 'rm -f "$probe"' EXIT
cat > "$probe" <<'PHP'
<?php
$socket = @stream_socket_client('tcp://1.1.1.1:443', $errno, $error, 2);
$blocked = $socket === false;
if (is_resource($socket)) fclose($socket);
$private = !@is_readable('/srv/radiorubben-test/wordpress/wp-config.php');
$mail = !function_exists('mail');
header('Content-Type: application/json');
echo json_encode(['networkBlocked' => $blocked, 'otherAppPrivate' => $private, 'mailDisabled' => $mail]);
PHP
chmod 0644 "$probe"
curl -fsS "http://127.0.0.1:8080/$(basename "$probe")" | jq -e '.networkBlocked and .otherAppPrivate and .mailDisabled' >/dev/null
if [[ -f /var/lib/radiorubben-test/accounts-created ]]; then
    runuser -u rr-wordpress -- wp --path="$root/wordpress" core is-installed
    test "$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8081/wordpress/)" == 302
fi
if [[ -f /etc/nginx/conf.d/rr-test-tls.conf ]]; then
    # Follow the real entry point, so an internal port in a redirect fails here.
    test "$(curl -fsSL --max-redirs 5 --proto-redir '=https' --resolve 5.249.252.78:443:127.0.0.1 -o /dev/null -w '%{http_code} %{url_effective}' https://5.249.252.78/)" == '200 https://5.249.252.78/local/login.php'
    wp_destination=$(curl -fsSL --max-redirs 5 --proto-redir '=https' --resolve 5.249.252.78:443:127.0.0.1 -o /dev/null -w '%{http_code} %{url_effective}' https://5.249.252.78/wordpress/)
    [[ $wp_destination == '200 https://5.249.252.78/wordpress/wp-login.php'* ]]
    test "$(curl -s --resolve 5.249.252.78:443:127.0.0.1 -o /dev/null -w '%{http_code}' https://5.249.252.78/wordpress/wp-config.php)" == 403
fi
echo 'PASS: private files, login gates, disabled external auth, network isolation and blocked email.'
