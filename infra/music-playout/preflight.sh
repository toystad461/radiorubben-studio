#!/usr/bin/env bash
# Read-only. Must be run on the actual isolated VPS before installation.
set -Eeuo pipefail
[[ $(hostname) == vps-5363103 ]] || { echo 'Wrong host: test VPS 5363103 required.' >&2; exit 1; }
ip -4 addr show | grep -Fq '5.249.252.78/'
source /etc/os-release
[[ $ID == ubuntu && $VERSION_ID == 24.04 ]]
root=/srv/radiorubben-test
[[ -L $root/studio && -d $root/studio-data && -f $root/studio-data/local.php ]]
[[ $(systemctl show php8.3-fpm -p PrivateNetwork --value) == yes ]]
[[ $(systemctl show php8.3-fpm -p RestrictAddressFamilies --value) == AF_UNIX ]]
[[ $(systemctl show php8.3-fpm -p NoNewPrivileges --value) == yes ]]
[[ $(systemctl show php8.3-fpm -p PrivateTmp --value) == yes ]]
# Print infrastructure, never credentials or local.php contents.
printf 'Studio release: %s\n' "$(readlink -f "$root/studio")"
php -v | head -1
if command -v ffmpeg >/dev/null; then ffmpeg -version | head -1; else echo 'FFmpeg must be installed from Ubuntu packages.'; fi
systemctl is-active php8.3-fpm nginx
php-fpm8.3 -t
nginx -t
awk '/^\[rr-studio\]/{s=1;next} /^\[/{s=0} s && /disable_functions|open_basedir|allow_url_fopen/{print}' /etc/php/8.3/fpm/pool.d/rr-test.conf
[[ -d $root/studio/media ]] && printf 'Existing media: %s\n' "$(readlink -f "$root/studio/media")"
df -h "$root"
echo 'Preflight passed. Authentication, actual library, active release hashes and audible output still require verification.'
