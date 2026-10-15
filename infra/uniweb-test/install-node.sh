#!/usr/bin/env bash
set -Eeuo pipefail
[[ $(id -u) == 0 && $(hostname) == vps-5363103 ]]
state=/var/lib/radiorubben-test/node-download
install -d -m 0700 "$state"
cd "$state"
# Select the current supported 24.x release, then pin that concrete version.
curl -fsSL https://nodejs.org/dist/index.json -o index.json
version=$(jq -r '[.[] | select(.version | startswith("v24.")) | select(.lts != false)][0].version' index.json)
[[ $version =~ ^v24\.[0-9]+\.[0-9]+$ ]]
archive="node-$version-linux-x64.tar.xz"
curl -fsSL https://github.com/nodejs/release-keys/raw/HEAD/gpg/pubring.kbx -o nodejs-keyring.kbx
curl -fsSL "https://nodejs.org/dist/$version/SHASUMS256.txt.asc" -o SHASUMS256.txt.asc
gpgv --keyring "$state/nodejs-keyring.kbx" --output "$state/SHASUMS256.txt" "$state/SHASUMS256.txt.asc"
curl -fsSL "https://nodejs.org/dist/$version/$archive" -o "$archive"
sha256sum --check --ignore-missing SHASUMS256.txt
tar -xJf "$archive" -C /opt
ln -sfn "/opt/node-$version-linux-x64" /opt/rr-node
for tool in node npm npx; do ln -sfn "/opt/rr-node/bin/$tool" "/usr/local/bin/$tool"; done
node --version
npm --version
node -e 'if (!process.release.lts || !process.version.startsWith("v24.")) process.exit(1); console.log("Node LTS runtime OK");'
id rr-node >/dev/null 2>&1 || useradd --system --create-home --shell /usr/sbin/nologin rr-node
install -d -o rr-node -g rr-node -m 0750 /srv/radiorubben-test/node-data
# No worker/scheduler is enabled until its job and integration scope is defined.
printf '%s\n' "$version" > /var/lib/radiorubben-test/node-version
