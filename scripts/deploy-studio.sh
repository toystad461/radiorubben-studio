#!/usr/bin/env bash
set -Eeuo pipefail
# Run only on a trusted runner/local machine with a verified SSH known_hosts file.
mode=${1:-dry-run}
[[ "$mode" == dry-run || "$mode" == apply ]] || { echo 'Expected dry-run or apply' >&2; exit 2; }
# Full-package deployment is unsafe while live Studio differs from GitHub.
# No environment variable or confirmation token may bypass reconciliation.
if [[ "$mode" == apply ]]; then
  echo 'STOP: Full-package apply is blocked. See docs/STUDIO-DEPLOY.md; reconcile a selective manifest and obtain Thomas approval.' >&2
  exit 1
fi
: "${STUDIO_SSH_HOST:?Set STUDIO_SSH_HOST}"
: "${STUDIO_SSH_USER:?Set STUDIO_SSH_USER}"
[[ "$STUDIO_SSH_HOST" =~ ^[a-zA-Z0-9][a-zA-Z0-9.-]+$ ]] || exit 2
[[ "$STUDIO_SSH_USER" =~ ^[a-zA-Z0-9_][a-zA-Z0-9_.-]*$ ]] || exit 2
root=$(cd -- "$(dirname -- "$0")/.." && pwd)
version=$(tr -d '\r\n' < "$root/VERSION")
[[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || exit 2
archive="$root/dist/radiorubben-studio-$version-uniweb.zip"
[[ -f "$archive" ]] || { echo 'Build the release package first.' >&2; exit 1; }
(cd "$root/dist" && sha256sum -c "$(basename "$archive").sha256")
digest=$(sha256sum "$archive" | cut -d' ' -f1)
run="${GITHUB_RUN_ID:-local-$(date -u +%Y%m%dT%H%M%SZ)}-${GITHUB_RUN_ATTEMPT:-1}"
[[ "$run" =~ ^[a-zA-Z0-9-]+$ ]] || exit 2
remote="$STUDIO_SSH_USER@$STUDIO_SSH_HOST"
stage=".radiorubben-studio-deploy/staging/$run"
opts=(-o BatchMode=yes -o StrictHostKeyChecking=yes -o IdentitiesOnly=yes -o ConnectTimeout=15)
ssh "${opts[@]}" "$remote" "umask 077; mkdir -p '$stage'"
scp "${opts[@]}" "$archive" "$remote:$stage/release.zip"
scp "${opts[@]}" "$root/scripts/deploy-studio-remote.sh" "$remote:$stage/deploy.sh"
ssh "${opts[@]}" "$remote" "bash '$stage/deploy.sh' '$mode' '$version' '$digest' '$run'"
