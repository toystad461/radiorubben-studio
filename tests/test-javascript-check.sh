#!/usr/bin/env bash
set -euo pipefail
repo=$(cd "$(dirname "$0")/.." && pwd)
fixture=$(mktemp -d)
trap 'rm -rf -- "$fixture"' EXIT
bash "$repo/scripts/check-javascript.sh" "$fixture"
printf 'const ok = true;\n' > "$fixture/first valid.js"
printf 'export const ok = true;\n' > "$fixture/second valid.mjs"
bash "$repo/scripts/check-javascript.sh" "$fixture"
printf 'const = ;\n' > "$fixture/last invalid.mjs"
if bash "$repo/scripts/check-javascript.sh" "$fixture" > "$fixture/result.txt" 2>&1; then
  echo 'FAIL: invalid JavaScript was accepted' >&2
  exit 1
fi
grep -q 'SyntaxError' "$fixture/result.txt"
if bash "$repo/scripts/check-javascript.sh" "$fixture/missing" > /dev/null 2>&1; then
  echo 'FAIL: missing source directory was accepted' >&2
  exit 1
fi
echo 'PASS: empty/valid sources accepted; invalid later file and missing directory fail'
