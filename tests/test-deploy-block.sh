#!/usr/bin/env bash
set -Eeuo pipefail
root=$(cd -- "$(dirname -- "$0")/.." && pwd)
tmp=$(mktemp -d)
trap 'rm -rf -- "$tmp"' EXIT
# Fail closed before dependencies, credentials, staging, or transport are used.
mkdir "$tmp/bin"
for cmd in ssh scp rsync php curl tar mkdir flock realpath stat sha256sum; do
  printf '#!/usr/bin/env bash\nprintf "%%s\\n" "$0" >> "$DEPLOY_GUARD_CALLS"\nexit 99\n' > "$tmp/bin/$cmd"
  chmod +x "$tmp/bin/$cmd"
done
export DEPLOY_GUARD_CALLS="$tmp/calls"
export STUDIO_SSH_HOST=example.invalid STUDIO_SSH_USER=fixture
export STUDIO_DEPLOY_AUTO_APPLY=true STUDIO_DEPLOY_ENABLED=true
for script in deploy-studio.sh deploy-studio-remote.sh; do
  bash -n "$root/scripts/$script"
  status=0
  PATH="$tmp/bin:$PATH" bash "$root/scripts/$script" apply 0.0.2 "$(printf '%064d' 0)" fixture > "$tmp/output" 2>&1 || status=$?
  [[ "$status" == 1 ]] || { cat "$tmp/output"; echo "Expected blocked apply: $script" >&2; exit 1; }
  grep -q 'Full-package apply is blocked' "$tmp/output"
  [[ ! -e "$tmp/calls" ]] || { cat "$tmp/calls"; echo 'A deployment dependency was invoked before the stop.' >&2; exit 1; }
  echo "PASS $script: apply blocked before server or filesystem operations"
done
