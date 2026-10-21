#!/usr/bin/env bash
# Each file must be checked, and a failing child must fail CI.
set -euo pipefail
directory=${1:-public}
test -d "$directory"
find "$directory" -type f \( -name '*.mjs' -o -name '*.js' \) -print0 |
  while IFS= read -r -d '' file; do
    node --check "$file"
  done
