#!/usr/bin/env bash
set -Eeuo pipefail
image=${1:-studio-render-test}
container=
volume="studio-render-test-$RANDOM-$$"
cleanup() {
  [[ -z "$container" ]] || docker rm -f "$container" >/dev/null 2>&1 || true
  docker volume rm "$volume" >/dev/null 2>&1 || true
}
trap cleanup EXIT
docker volume create "$volume" >/dev/null
password='synthetic-staging-password-for-ci-only'
start() {
  container=$(docker run -d -p 127.0.0.1:18197:10000 -v "$volume:/var/studio" \
    -e STUDIO_ENV=staging -e STUDIO_SITE_MODE=app -e STUDIO_AUTH_MODE=demo \
    -e STAGING_BASIC_USER=tester -e STAGING_BASIC_PASSWORD="$password" "$image")
  for i in {1..60}; do
    if curl -fsS http://127.0.0.1:18197/healthz.php >/dev/null; then return; fi
    sleep 1
  done
  docker logs "$container"; return 1
}
status() {
  local expected=$1 path=$2
  shift 2
  actual=$(curl --path-as-is -sS -o /tmp/studio-render-body -w '%{http_code}' "$@" "http://127.0.0.1:18197$path")
  [[ "$actual" = "$expected" ]] || { echo "Expected $expected for $path; received $actual"; return 1; }
}
start
status 401 /
status 401 /login.php
status 401 /assets/studio.css
status 200 /login.php -u "tester:$password"
status 303 / -u "tester:$password"
status 403 /robot.php -u "tester:$password"
for path in /config/example.php /app/config.php /composer.json /.git/config /snapshots/; do
  status 404 "$path" -u "tester:$password"
done
status 503 /auth/start.php -u "tester:$password"
docker exec -u www-data "$container" sh -c 'printf synthetic > /var/studio/config/persistence-test.txt'
docker rm -f "$container" >/dev/null
container=
start
docker exec -u www-data "$container" sh -c 'test "$(cat /var/studio/config/persistence-test.txt)" = synthetic'
# Startup must fail before opening a port when access credentials are absent.
if docker run --rm -e STUDIO_ENV=staging "$image"; then
  echo 'Missing credentials unexpectedly accepted'; exit 1
fi
echo 'Render container: access, private paths, closed auth and persistent data passed.'
