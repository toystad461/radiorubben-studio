#!/usr/bin/env bash
# Run by the owner in Uniweb. No password arguments, environment or log files.
set -Eeuo pipefail
set +x
umask 077
ulimit -c 0
[[ $(id -u) == 0 && $(hostname) == vps-5363103 && -t 0 ]]
state=/var/lib/radiorubben-test
root=/srv/radiorubben-test
[[ -f $state/accounts-created && -f $state/credential-rotation-required ]]
trap 'unset rr_test_password rr_test_confirmation' EXIT
install -o root -g rr-wordpress -m 0640 "$state/tools/wordpress-account.php" "$root/wordpress-account.php"
install -o root -g rr-studio -m 0640 "$state/tools/rotate-studio-password.php" "$root/rotate-studio-password.php"
runuser -u rr-wordpress -- php "$root/wordpress-account.php" check
runuser -u rr-studio -- php "$root/rotate-studio-password.php" check
/usr/local/sbin/rr-test-verify
printf '\nVelg et HELT NYTT testpassord pa 14-72 tegn. Ikke bruk tidligere passord.\n'
IFS= read -r -s -p 'Nytt testpassord (skjult): ' rr_test_password
printf '\n'
IFS= read -r -s -p 'Gjenta testpassordet (skjult): ' rr_test_confirmation
printf '\n'
[[ $rr_test_password == "$rr_test_confirmation" ]] || { echo 'Passordene samsvarte ikke. Ingen konto er endret.'; exit 1; }
unset rr_test_confirmation
printf '%s' "$rr_test_password" | runuser -u rr-wordpress -- php "$root/wordpress-account.php" check-new
printf '%s' "$rr_test_password" | runuser -u rr-studio -- php "$root/rotate-studio-password.php" check-new
printf '%s' "$rr_test_password" | runuser -u rr-wordpress -- php "$root/wordpress-account.php" rotate
printf '%s' "$rr_test_password" | runuser -u rr-studio -- php "$root/rotate-studio-password.php" rotate
unset rr_test_password
install -m 0644 "$state/tools/nginx-tls.conf" /etc/nginx/conf.d/rr-test-tls.conf
nginx -t
systemctl reload nginx
/usr/local/sbin/rr-test-verify
ufw allow 443/tcp comment 'Radio Rubben TEST HTTPS'
mv "$state/credential-rotation-required" "$state/credential-rotation-resolved"
printf '%s\n' "rotated=$(date -Is)" >> "$state/credential-rotation-resolved"
printf '%s\n' "activated=$(date -Is)" > "$state/active"
printf '\nTESTMILJO KLART. Begge testpassord er endret; tidligere innlogginger er ugyldige.\n'
