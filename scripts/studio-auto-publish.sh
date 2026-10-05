#!/usr/bin/env bash
set -Eeuo pipefail
[[ "${GITHUB_REF:-}" == refs/heads/main ]] || { echo 'Only main may publish.' >&2; exit 1; }
[[ "${GITHUB_SHA:-}" =~ ^[a-f0-9]{40}$ ]] || exit 1
# Reuse the complete package validation, PHP lint and HTTPS/root checks.
bash scripts/deploy-studio.sh dry-run
run="${GITHUB_RUN_ID:?}-${GITHUB_RUN_ATTEMPT:?}"
stage=".radiorubben-studio-deploy/staging/$run"
remote="${STUDIO_SSH_USER:?}@${STUDIO_SSH_HOST:?}"
opts=(-o BatchMode=yes -o StrictHostKeyChecking=yes -o IdentitiesOnly=yes -o ConnectTimeout=15)
for pair in 'scripts/studio-selective.php:publish.php' 'scripts/studio-deploy-baseline.json:baseline.json'; do
  src=${pair%:*}; dst=${pair#*:}
  ssh "${opts[@]}" "$remote" "umask 077; cat > '$stage/$dst'" < "$src"
  hash=$(sha256sum "$src" | cut -d' ' -f1)
  printf '%s  %s\n' "$hash" "$dst" | ssh "${opts[@]}" "$remote" "cd '$stage' && sha256sum -c -"
done
ssh "${opts[@]}" "$remote" "php '$stage/publish.php' '$run' '$GITHUB_SHA'"
