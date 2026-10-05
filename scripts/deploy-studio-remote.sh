#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
mode=${1:?}; version=${2:?}; digest=${3:?}; run=${4:?}
[[ "$mode" == dry-run || "$mode" == apply ]] || exit 2
# Full-package deployment is unsafe while live Studio differs from GitHub.
# No environment variable or confirmation token may bypass reconciliation.
if [[ "$mode" == apply ]]; then
  echo 'STOP: Full-package apply is blocked. See docs/STUDIO-DEPLOY.md; reconcile a selective manifest and obtain Thomas approval.' >&2
  exit 1
fi
[[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || exit 2
[[ "$digest" =~ ^[a-f0-9]{64}$ && "$run" =~ ^[a-zA-Z0-9-]+$ ]] || exit 2
# Deliberately fixed Studio-only paths. Never copy to the WordPress root.
base=/run/webroots/r1417157
public="$base/studio-public"
private="$base/studio-private"
work="$HOME/.radiorubben-studio-deploy"
stage="$work/staging/$run"
url=https://studio.radiorubben.no
for cmd in php curl rsync tar sha256sum flock realpath stat; do command -v "$cmd" >/dev/null; done
for dir in "$public" "$private"; do
  [[ -d "$dir" && ! -L "$dir" && "$(realpath "$dir")" == "$dir" ]] || {
    echo 'STOP: Studio document paths must be verified before deployment.' >&2; exit 1;
  }
done
# Refuse a layout that could redirect an overlay or rollback outside Studio.
[[ -z "$(find "$public" "$private" -type l -print -quit)" ]] || {
  echo 'STOP: Symlinks in Studio require a separate deployment review.' >&2; exit 1;
}
[[ -f "$private/app/bootstrap.php" && -f "$public/index.php" ]] || exit 1
public_mode=$(stat -c '%a' "$public")
private_mode=$(stat -c '%a' "$private")
mkdir -p "$work/backups"
exec 9>"$work/deploy.lock"
flock -n 9 || { echo 'Another Studio deployment is running.' >&2; exit 1; }
(cd "$stage" && printf '%s  release.zip\n' "$digest" | sha256sum -c -)
# Validate PHP and archive paths before extraction; never print configuration values.
php -r '
if (PHP_VERSION_ID < 80200) { fwrite(STDERR,"PHP CLI must be 8.2 or newer.\n"); exit(1); }
foreach (["curl","json","openssl","session","zip"] as $e) if (!extension_loaded($e)) {
    fwrite(STDERR,"Missing required PHP CLI extension.\n"); exit(1);
}
$z=new ZipArchive(); if ($z->open($argv[1])!==true) exit(1);
for($i=0;$i<$z->numFiles;$i++) {
    $n=$z->getNameIndex($i);
    if (!is_string($n) || str_contains($n,"..") || str_contains($n,"\\")
        || !preg_match("#^studio-(public|private)/#",$n)
        || preg_match("#(^|/)(local\\.php|\\.env(?:\\.[^/]*)?|\\.git|node_modules)(/|$)#",$n)) exit(1);
    $opsys=0; $attr=0; $z->getExternalAttributesIndex($i,$opsys,$attr);
    if ((($attr >> 16) & 0170000) === 0120000) exit(1);
}
$m=json_decode($z->getFromName("studio-public/release.json"),true,512,JSON_THROW_ON_ERROR);
if (($m["version"]??"")!==$argv[3] || !preg_match("/^[a-f0-9]{40}$/D",$m["commit"]??"")) exit(1);
if (!$z->extractTo($argv[2])) exit(1); $z->close();
' "$stage/release.zip" "$stage/unpacked" "$version"
php -r 'require $argv[1]."/studio-private/vendor/autoload.php";' "$stage/unpacked"
find "$stage/unpacked" -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null
# Verify HTTPS and that this hostname serves the existing Studio CSS from this path.
# No --insecure and no redirects to another hostname are permitted.
code=$(curl -sS --proto '=https' --max-time 25 -o "$stage/current.css" -w '%{http_code}' "$url/assets/studio.css")
[[ "$code" == 200 ]]
[[ "$(sha256sum "$stage/current.css" | cut -d' ' -f1)" == "$(sha256sum "$public/assets/studio.css" | cut -d' ' -f1)" ]] || {
  echo 'STOP: Hostname/document-root verification failed.' >&2; exit 1;
}
# Preserve production mode and reject a publicly accessible demo configuration.
site_mode=$(php -r '
require $argv[1]."/app/config.php";
$c=load_config(); $m=getenv("STUDIO_SITE_MODE")!==false?getenv("STUDIO_SITE_MODE"):($c["site_mode"]??"coming-soon");
if ($m==="app" && (($c["auth_mode"]??"")!=="entra" || !config_valid($c)
    || ($c["base_url"]??"")!=="https://studio.radiorubben.no")) {
    fwrite(STDERR,"STOP: Production must use valid Entra configuration, not demo.\n"); exit(1);
}
echo $m==="app"?"app":"coming-soon";
' "$private")
verify_home() {
  local code
  code=$(curl -sS --proto '=https' --max-time 25 -D "$stage/home.headers" -o "$stage/home.html" -w '%{http_code}' "$url/") || return 1
  if [[ "$site_mode" == coming-soon ]]; then
    [[ "$code" == 503 ]] && grep -q 'Vi klargjør det nye arbeidsrommet' "$stage/home.html"
  else
    [[ "$code" == 303 ]] && grep -Eqi '^location: /login\.php[[:space:]]*$' "$stage/home.headers"
  fi
}
verify_home || { echo 'STOP: Existing web mode does not match the safe expected mode.' >&2; exit 1; }
# Staging/backups remain private. Apply predictable modes to shipped code only;
# never transfer the runner's owner/group or replace local secret configuration.
flags=(-rlpt --itemize-changes --chmod=D755,F644)
[[ "$mode" == dry-run ]] && flags+=(--dry-run)
if [[ "$mode" == dry-run ]]; then
  rsync "${flags[@]}" --exclude='/config/local.php' "$stage/unpacked/studio-private/" "$private/"
  rsync "${flags[@]}" "$stage/unpacked/studio-public/" "$public/"
  echo "DRY-RUN OK: v$version; mode=$site_mode. No website files changed."
  exit 0
fi
# Back up only Studio to a private path. No WordPress or database mutations.
backup="$work/backups/$run"
mkdir "$backup"
tar -czf "$backup/studio.tar.gz" -C "$base" studio-public studio-private
mkdir "$backup/restore"
tar -xzpf "$backup/studio.tar.gz" -C "$backup/restore"
rollback() {
  trap - ERR HUP INT TERM
  echo 'Deployment failed; attempting Studio-only rollback.' >&2
  rsync -rlpt --delete "$backup/restore/studio-private/" "$private/" &&
  rsync -rlpt --delete "$backup/restore/studio-public/" "$public/" || {
    echo 'ROLLBACK FAILED: manual restoration from the private backup is required.' >&2; exit 2;
  }
  echo 'Previous Studio files restored. Check the website before retrying.' >&2
  exit 1
}
trap rollback ERR HUP INT TERM
rsync "${flags[@]}" --exclude='/config/local.php' "$stage/unpacked/studio-private/" "$private/"
rsync "${flags[@]}" "$stage/unpacked/studio-public/" "$public/"
# Retain existing document-root restrictions even when copied archive roots are 755.
chmod "$private_mode" "$private"
chmod "$public_mode" "$public"
verify_home
code=$(curl -sS --proto '=https' --max-time 25 -o "$stage/live-release.json" -w '%{http_code}' "$url/release.json")
[[ "$code" == 200 ]]
cmp "$stage/unpacked/studio-public/release.json" "$stage/live-release.json"
trap - ERR HUP INT TERM
echo "DEPLOYED v$version; mode=$site_mode. Studio-only backup retained."
if [[ "$site_mode" == coming-soon ]]; then
  echo 'The waiting page is retained. This deployment did not open the internal Studio application.'
fi
