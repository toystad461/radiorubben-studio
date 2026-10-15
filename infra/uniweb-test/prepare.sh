#!/usr/bin/env bash
# This bootstrap is deliberately specific to the disposable VPS, never production.
set -Eeuo pipefail
umask 027
[[ $(id -u) == 0 && $(hostname) == vps-5363103 ]]
ip -4 addr show | grep -q '5.249.252.78/'
source /etc/os-release
[[ $ID == ubuntu && $VERSION_ID == 24.04 ]]
root=/srv/radiorubben-test
state=/var/lib/radiorubben-test
here=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
[[ ! -e $state/prepared ]] || { echo 'Already prepared; refusing to overwrite runtime data.'; exit 1; }
studio_commit=96ff332d9b26f07b3be68c1d9cf2c9e58b6a7c60
web_commit=c349fc24dd8e6df588755680f1e16c87bf281204
if [[ ${1:-} == --resume-after-wordpress ]]; then
    [[ -L $root/studio && -f $root/studio-data/local.php && ! -f $root/wordpress/wp-config.php ]]
    [[ $(runuser -u rr-build -- git -C "$root/sources/radiorubben-studio" rev-parse HEAD) == "$studio_commit" ]]
    runuser -u rr-wordpress -- wp --path="$root/wordpress" core verify-checksums --locale=nb_NO
else
[[ $# == 0 ]]
for account in rr-studio rr-wordpress rr-build; do
    id "$account" >/dev/null 2>&1 || useradd --system --create-home --shell /usr/sbin/nologin "$account"
done
install -d -m 0755 "$root" "$root/sources" "$root/releases"
install -d -m 0700 "$state" "$state/backup"
bash "$here/install-node.sh"
install -d -m 0755 /var/www/rr-acme/.well-known/acme-challenge

for repo in radiorubben-studio radiorubben-web; do
    [[ -d $root/sources/$repo/.git ]] || git clone --depth 1 "https://github.com/toystad461/$repo.git" "$root/sources/$repo"
done
git -C "$root/sources/radiorubben-studio" fetch --depth 1 origin "$studio_commit"
git -C "$root/sources/radiorubben-studio" checkout --detach "$studio_commit"
git -C "$root/sources/radiorubben-web" fetch --depth 1 origin "$web_commit"
git -C "$root/sources/radiorubben-web" checkout --detach "$web_commit"
chown -R rr-build:rr-build "$root/sources"
runuser -u rr-build -- sh -c "cd '$root/sources/radiorubben-studio' && composer install --no-dev --no-interaction --no-plugins --no-scripts --prefer-dist"
studio="$root/releases/studio-$studio_commit"
install -d -m 0755 "$studio"
for dir in app public vendor config; do cp -a "$root/sources/radiorubben-studio/$dir" "$studio/"; done
cp "$root/sources/radiorubben-studio/composer.lock" "$studio/"
chown -R root:root "$studio"
chmod -R a+rX "$studio"
install -d -o rr-studio -g rr-studio -m 0700 "$root/studio-data" "$root/studio-sessions"
install -o root -g rr-studio -m 0640 "$studio/config/example.php" "$root/studio-data/example.php"
mv "$studio/config" "$studio/config.dist"
ln -s "$root/studio-data" "$studio/config"
ln -s "$studio" "$root/studio"
install -o root -g rr-studio -m 0640 "$here/studio-config.php" "$root/studio-data/local.php"

install -d -o rr-wordpress -g rr-wordpress -m 0755 "$root/wordpress"
install -d -m 0700 "$state/wp-cli-verify"
curl -fsSL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o "$state/wp-cli-verify/wp-cli.phar"
curl -fsSL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar.asc -o "$state/wp-cli-verify/wp-cli.phar.asc"
curl -fsSL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/wp-cli.pgp -o "$state/wp-cli-verify/wp-cli.pgp"
gpg --homedir "$state/wp-cli-verify" --import "$state/wp-cli-verify/wp-cli.pgp"
gpg --homedir "$state/wp-cli-verify" --verify "$state/wp-cli-verify/wp-cli.phar.asc" "$state/wp-cli-verify/wp-cli.phar"
install -m 0755 "$state/wp-cli-verify/wp-cli.phar" /usr/local/bin/wp
bash "$here/download-wordpress.sh"
runuser -u rr-wordpress -- wp --path="$root/wordpress" core verify-checksums --locale=nb_NO
fi
# Unix-socket authentication avoids a stored database password altogether.
mariadb <<'SQL'
CREATE DATABASE IF NOT EXISTS rr_test_wp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'rr-wordpress'@'localhost' IDENTIFIED VIA unix_socket;
GRANT ALL PRIVILEGES ON rr_test_wp.* TO 'rr-wordpress'@'localhost';
SQL
install -o root -g rr-wordpress -m 0640 "$here/wp-config.php" "$root/wordpress/wp-config.php"
php -r '$p="/srv/radiorubben-test/wordpress/wp-salts.php"; $s="<?php\n"; foreach(["AUTH_KEY","SECURE_AUTH_KEY","LOGGED_IN_KEY","NONCE_KEY","AUTH_SALT","SECURE_AUTH_SALT","LOGGED_IN_SALT","NONCE_SALT"] as $k) $s.="define(".var_export($k,true).", ".var_export(bin2hex(random_bytes(48)),true).");\n"; file_put_contents($p,$s);'
chown root:rr-wordpress "$root/wordpress/wp-salts.php"
chmod 0640 "$root/wordpress/wp-salts.php"
cp -a "$root/sources/radiorubben-web/theme-next/radio-rubben-next" "$root/wordpress/wp-content/themes/"
cp -a "$root/sources/radiorubben-web/theme-next/rr-site-functions" "$root/wordpress/wp-content/plugins/"
cp -a "$root/sources/radiorubben-web/wordpress/wp-content/plugins/rr-news-images" "$root/wordpress/wp-content/plugins/"
install -d -m 0755 "$root/wordpress/wp-content/mu-plugins"
install -m 0644 "$here/wp-isolation.php" "$root/wordpress/wp-content/mu-plugins/rr-test-isolation.php"
chown -R root:root "$root/wordpress/wp-content/themes" "$root/wordpress/wp-content/plugins" "$root/wordpress/wp-content/mu-plugins"
chmod -R a+rX "$root/wordpress/wp-content/themes" "$root/wordpress/wp-content/plugins" "$root/wordpress/wp-content/mu-plugins"
install -d -o rr-wordpress -g rr-wordpress -m 0755 "$root/wordpress/wp-content/uploads"
chown -R root:root "$root/wordpress"
chmod -R a+rX "$root/wordpress"
chown root:rr-wordpress "$root/wordpress/wp-config.php" "$root/wordpress/wp-salts.php"
chmod 0640 "$root/wordpress/wp-config.php" "$root/wordpress/wp-salts.php"
chown rr-wordpress:rr-wordpress "$root/wordpress/wp-content/uploads"

install -m 0644 "$here/php-pools.conf" /etc/php/8.3/fpm/pool.d/rr-test.conf
if [[ -e /etc/php/8.3/fpm/pool.d/www.conf ]]; then mv /etc/php/8.3/fpm/pool.d/www.conf /etc/php/8.3/fpm/pool.d/www.conf.disabled; fi
install -d /etc/systemd/system/php8.3-fpm.service.d
cat > /etc/systemd/system/php8.3-fpm.service.d/rr-test-isolation.conf <<'EOF'
[Service]
PrivateNetwork=yes
RestrictAddressFamilies=AF_UNIX
NoNewPrivileges=yes
PrivateTmp=yes
EOF
install -m 0644 "$here/nginx-apps.conf" /etc/nginx/rr-test-apps.conf
install -m 0644 "$here/nginx-private.conf" /etc/nginx/sites-available/rr-test
if [[ -L /etc/nginx/sites-enabled/default ]]; then unlink /etc/nginx/sites-enabled/default; fi
ln -s /etc/nginx/sites-available/rr-test /etc/nginx/sites-enabled/rr-test
systemctl daemon-reload
php-fpm8.3 -t
nginx -t
systemctl restart php8.3-fpm nginx
systemctl enable php8.3-fpm nginx mariadb
ufw allow 80/tcp comment 'ACME validation only'
# Finish is interactive: owner accepts certificate terms and enters a test password.
install -d -m 0700 "$state/tools"
cp "$here"/* "$state/tools/"
install -m 0700 "$here/finish.sh" /usr/local/sbin/rr-test-finish
install -m 0700 "$here/verify.sh" /usr/local/sbin/rr-test-verify
install -m 0700 "$here/rotate-password.sh" /usr/local/sbin/rr-test-rotate-password
printf '%s\n' "studio=$studio_commit" "web=$web_commit" "prepared=$(date -Is)" > "$state/prepared"
echo 'PREPARED: apps are reachable only on loopback; public port 80 serves ACME only.'
