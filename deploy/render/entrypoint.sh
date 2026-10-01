#!/bin/sh
set -eu
umask 077
[ "${STUDIO_ENV:-}" = staging ] || { echo 'This image is staging-only.' >&2; exit 1; }
[ -n "${STAGING_BASIC_USER:-}" ] && [ -n "${STAGING_BASIC_PASSWORD:-}" ] || {
    echo 'Staging access credentials are required.' >&2; exit 1;
}
case "$STAGING_BASIC_USER" in *[!a-zA-Z0-9_-]*|'') echo 'Invalid staging username.' >&2; exit 1;; esac
[ "${#STAGING_BASIC_PASSWORD}" -ge 20 ] || { echo 'Staging password must have at least 20 characters.' >&2; exit 1; }
mkdir -p /var/studio/config /var/studio/sessions
# Never import production config into this disk.
for forbidden in local.php wordpress.php openai.key; do
    [ ! -e "/var/studio/config/$forbidden" ] || { echo 'Unexpected private integration config; startup refused.' >&2; exit 1; }
done
cp /opt/studio-defaults/example.php /var/studio/config/example.php
chown www-data:www-data /var/studio/config /var/studio/sessions /var/studio/config/example.php
chmod 700 /var/studio/config /var/studio/sessions
printf '%s\n' "$STAGING_BASIC_PASSWORD" | htpasswd -iBc /run/studio.htpasswd "$STAGING_BASIC_USER" >/dev/null 2>&1
chown root:www-data /run/studio.htpasswd
chmod 640 /run/studio.htpasswd
# No paid AI, Vipps or production integrations in the initial test service.
unset OPENAI_API_KEY OPENAI_MODEL VIPPS_CLIENT_ID VIPPS_CLIENT_SECRET VIPPS_ALLOWED_PHONES
unset STAGING_BASIC_PASSWORD STAGING_BASIC_USER
exec docker-php-entrypoint "$@"
