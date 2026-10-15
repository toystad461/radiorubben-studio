#!/usr/bin/env bash
set -Eeuo pipefail
[[ $(id -u) == 0 && $(hostname) == vps-5363103 ]]
state=/var/lib/radiorubben-test
root=/srv/radiorubben-test
[[ ! -f $root/wordpress/wp-load.php ]]
curl -fsSL 'https://api.wordpress.org/core/version-check/1.7/?locale=nb_NO' -o "$state/wordpress-offer.json"
package=$(jq -r '.offers[0].packages.full' "$state/wordpress-offer.json")
[[ $package =~ ^https://downloads.wordpress.org/release/nb_NO/wordpress-[0-9.]+\.zip$ ]]
curl -fsSL "$package" -o "$state/wordpress.zip"
# Native unzip preserves long filenames; WP-CLI's initial extraction did not.
unzip -q "$state/wordpress.zip" -d "$root"
chmod -R a+rX "$root/wordpress"
runuser -u rr-wordpress -- wp --path="$root/wordpress" core verify-checksums --locale=nb_NO
