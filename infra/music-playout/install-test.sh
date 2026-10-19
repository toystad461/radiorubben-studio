#!/usr/bin/env bash
# Install ONLY on the already isolated test VPS. No production, DNS, WordPress or public stream changes.
# Usage: sudo bash infra/music-playout/install-test.sh <reviewed exact 40-char source commit>
set -Eeuo pipefail
umask 027
# Inspection must not rewrite rr-build's index as root.
export GIT_OPTIONAL_LOCKS=0
[[ $(id -u) == 0 && $# == 1 && $1 =~ ^[a-f0-9]{40}$ ]]
here=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)
source_repo=$(cd "$here/../.." && pwd)
[[ $(git -C "$source_repo" rev-parse HEAD) == "$1" && -z $(git -C "$source_repo" status --porcelain) ]]
bash "$here/preflight.sh"
root=/srv/radiorubben-test
active=$(readlink -f "$root/studio")
base=96ff332d9b26f07b3be68c1d9cf2c9e58b6a7c60
foundation=bf46227247195e5f64b5b816b1253b206f66dc6b
[[ $active == "$root/releases/"* && ! -e $root/studio-data/music-playout.local.php ]]
[[ ! -e $root/playout && ! -e $root/player-code ]]
[[ $(systemctl is-active rr-music-playout 2>/dev/null || true) != active ]]
# Fail before writes if any touched file differs from BOTH reviewed bases.
mapfile -t changed < <(git -C "$source_repo" diff --name-only "$base" "$1" -- app public)
for file in "${changed[@]}"; do
    [[ -f $source_repo/$file && ! -L $source_repo/$file ]]
    if [[ -e $active/$file ]]; then
        [[ -f $active/$file && ! -L $active/$file ]]
        actual=$(sha256sum "$active/$file" | cut -d' ' -f1)
        matched=false
        for candidate in "$base" "$foundation"; do
            if git -C "$source_repo" cat-file -e "$candidate:$file" 2>/dev/null; then
                expected=$(git -C "$source_repo" show "$candidate:$file" | sha256sum | cut -d' ' -f1)
                [[ $actual != "$expected" ]] || matched=true
            fi
        done
        [[ $matched == true ]] || { echo "Unreconciled active file: $file" >&2; exit 1; }
    else
        ! git -C "$source_repo" cat-file -e "$base:$file" 2>/dev/null || { echo "Missing baseline file: $file" >&2; exit 1; }
    fi
done
# Application dependencies are unchanged. Fail if somebody attempts to use this installer for another change.
[[ -z $(git -C "$source_repo" diff "$foundation" "$1" -- composer.json composer.lock) ]]
for option in exec proc_open shell_exec mail; do grep '^php_admin_value\[disable_functions\]' /etc/php/8.3/fpm/pool.d/rr-test.conf | head -1 | grep -q "$option"; done
backup=/var/lib/radiorubben-test/music-playout-$1
[[ ! -e $backup ]];install -d -m 0700 "$backup"
cp -a /etc/php/8.3/fpm/pool.d/rr-test.conf "$backup/php-pools.conf"
printf '%s\n' "$active" > "$backup/previous-release"
printf '%s\n' "$1" > "$backup/source-commit"
if [[ -f $root/studio-data/music-play-queue.private.json ]]; then cp -a "$root/studio-data/music-play-queue.private.json" "$backup/"; fi
rollback() {
    status=$?
    trap - ERR
    systemctl stop rr-music-playout 2>/dev/null || true
    if [[ -L $root/studio && $(readlink -f "$root/studio") != "$active" ]]; then
        ln -s "$active" "$root/studio.rollback"
        mv -Tf "$root/studio.rollback" "$root/studio"
    fi
    cp -a "$backup/php-pools.conf" /etc/php/8.3/fpm/pool.d/rr-test.conf
    if [[ -f $root/studio-data/music-playout.local.php ]]; then mv "$root/studio-data/music-playout.local.php" "$backup/music-playout.failed.php";fi
    systemctl daemon-reload
    systemctl reload php8.3-fpm || true
    echo "Installation failed; previous code/pool restored. Preserve and inspect $backup and private playout data." >&2
    exit "$status"
}
trap rollback ERR
apt-get update
apt-get install --no-install-recommends -y ffmpeg
id rr-playout >/dev/null 2>&1 || useradd --system --no-create-home --shell /usr/sbin/nologin rr-playout
usermod -a -G rr-playout rr-studio
install -d -o root -g rr-playout -m 2770 "$root/playout"
install -d -o rr-studio -g rr-playout -m 2750 "$root/playout/audio"
install -d -o rr-playout -g rr-playout -m 2750 "$root/playout/monitor"
install -d -m 0755 "$root/player-code/app" "$root/player-code/scripts"
install -m 0644 "$source_repo/app/music-playout.php" "$root/player-code/app/music-playout.php"
install -m 0644 "$source_repo/scripts/music-playout.php" "$root/player-code/scripts/music-playout.php"
release="$root/releases/studio-music-$1"
[[ ! -e $release ]];install -d -m 0755 "$release"
# Retain all existing code and config links; keep original media at its existing real location.
for item in "$active"/*; do
    if [[ $(basename "$item") == media ]]; then ln -s "$(readlink -f "$item")" "$release/media"; else cp -a "$item" "$release/"; fi
done
if [[ ! -e $release/media ]]; then install -d -o rr-studio -g rr-studio -m 0700 "$root/studio-media";ln -s "$root/studio-media" "$release/media";fi
for file in "${changed[@]}"; do install -d -m 0755 "$(dirname "$release/$file")";install -m 0644 "$source_repo/$file" "$release/$file";done
# Add just the shared player directory to the Studio pool; preserve every existing isolation setting.
python3 - <<'PY'
from pathlib import Path
p=Path('/etc/php/8.3/fpm/pool.d/rr-test.conf');lines=p.read_text().splitlines();studio=False;changed=0
for i,line in enumerate(lines):
    if line.startswith('['): studio=line=='[rr-studio]'
    if studio and line.startswith('php_admin_value[open_basedir] = '):
        lines[i]=line+':/srv/radiorubben-test/playout:/srv/radiorubben-test/studio-media';changed+=1
assert changed==1
p.write_text('\n'.join(lines)+'\n')
PY
php-fpm8.3 -t
cat > "$root/studio-data/music-playout.local.php" <<'PHP'
<?php
return ['enabled'=>true,'root'=>'/srv/radiorubben-test/playout'];
PHP
chown root:rr-studio "$root/studio-data/music-playout.local.php"
chmod 0640 "$root/studio-data/music-playout.local.php"
install -m 0644 "$here/rr-music-playout.service" /etc/systemd/system/rr-music-playout.service
systemd-analyze verify /etc/systemd/system/rr-music-playout.service
ln -s "$release" "$root/studio.next"
mv -Tf "$root/studio.next" "$root/studio"
systemctl daemon-reload
systemctl reload php8.3-fpm
systemctl enable --now rr-music-playout
bash "$here/preflight.sh"
systemctl is-active rr-music-playout
trap - ERR
printf 'Installed test source %s. Player remains STOPPED until explicit Start in Studio.\n' "$1"
printf 'Rollback receipt: %s\n' "$backup"
