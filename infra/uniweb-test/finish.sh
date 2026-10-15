#!/usr/bin/env bash
# Run manually in the Uniweb terminal. Never pass a password as an argument.
set -Eeuo pipefail
set +x
umask 077
ulimit -c 0
trap 'unset rr_test_password rr_test_confirmation' EXIT
[[ $(id -u) == 0 && $(hostname) == vps-5363103 && -t 0 ]]
state=/var/lib/radiorubben-test
root=/srv/radiorubben-test
[[ -f $state/prepared && ! -f $state/active ]]
[[ ! -f $state/credential-rotation-required ]]
/usr/local/sbin/rr-test-verify
if [[ ! -f /etc/letsencrypt/live/5.249.252.78/fullchain.pem ]]; then
    printf '\nHTTPS for testserverens IP. Les og godta sertifikatvilkarene i neste steg.\n'
    /snap/bin/certbot certonly --webroot --webroot-path /var/www/rr-acme \
        --ip-address 5.249.252.78 --preferred-profile shortlived \
        --cert-name 5.249.252.78 --register-unsafely-without-email --no-eff-email
fi
[[ -f /etc/letsencrypt/live/5.249.252.78/fullchain.pem ]]
# Finish can safely resume after the owner has already created both accounts.
if [[ ! -f $state/accounts-created ]]; then
    [[ ! -f $root/studio-data/studio-users.json ]] || { echo 'Eksisterende konto ma kontrolleres manuelt.'; exit 1; }
    printf '\nVelg et NYTT testpassord pa 14-72 tegn. Ikke bruk serverpassordet.\n'
    IFS= read -r -s -p 'Nytt testpassord (skjult): ' rr_test_password
    printf '\n'
    IFS= read -r -s -p 'Gjenta testpassordet (skjult): ' rr_test_confirmation
    printf '\n'
    [[ $rr_test_password == "$rr_test_confirmation" && ${#rr_test_password} -ge 14 && ${#rr_test_password} -le 72 ]] || { unset rr_test_password rr_test_confirmation; echo 'Passordene samsvarte ikke eller hadde feil lengde.'; exit 1; }
    unset rr_test_confirmation
    printf '%s' "$rr_test_password" | php -r '$s=stream_get_contents(STDIN); exit(strlen($s)>=14 && strlen($s)<=72 ? 0 : 1);'
    # WP-CLI --prompt echoes the reconstructed command, including the password.
    # Keep credentials entirely out of WP-CLI and call core's API over stdin.
    install -o root -g rr-wordpress -m 0640 "$state/tools/wordpress-account.php" "$root/wordpress-account.php"
    printf '%s' "$rr_test_password" | runuser -u rr-wordpress -- php "$root/wordpress-account.php" install
    # The helper is copied outside root's private tools folder for this invocation.
    install -o root -g rr-studio -m 0640 "$state/tools/create-studio-user.php" "$root/create-studio-user.php"
    printf '%s\n' "$rr_test_password" | runuser -u rr-studio -- php "$root/create-studio-user.php"
    unset rr_test_password
    touch "$state/accounts-created"
fi
runuser -u rr-wordpress -- wp --path="$root/wordpress" option update timezone_string Europe/Oslo
runuser -u rr-wordpress -- wp --path="$root/wordpress" option update blog_public 0
runuser -u rr-wordpress -- wp --path="$root/wordpress" option update default_comment_status closed
runuser -u rr-wordpress -- wp --path="$root/wordpress" theme activate radio-rubben-next
# Production integrations stay inactive; theme and plugin code is ready to review.
install -m 0644 "$state/tools/nginx-tls.conf" /etc/nginx/conf.d/rr-test-tls.conf
install -d -m 0755 /etc/letsencrypt/renewal-hooks/deploy
cat > /etc/letsencrypt/renewal-hooks/deploy/rr-nginx <<'EOF'
#!/bin/sh
set -eu
/usr/sbin/nginx -t
/usr/bin/systemctl reload nginx
EOF
chmod 0755 /etc/letsencrypt/renewal-hooks/deploy/rr-nginx
nginx -t
systemctl reload nginx
/usr/local/sbin/rr-test-verify
# Both isolated apps share the standard HTTPS entry point.
ufw allow 443/tcp comment 'Radio Rubben TEST HTTPS'
printf '%s\n' "activated=$(date -Is)" > "$state/active"
printf '\nTESTMILJO KLART\nStudio: https://5.249.252.78 — thomas@example.test\nWordPress: https://5.249.252.78/wordpress/ — thomas\nBegge bruker testpassordet du nettopp valgte.\n'
