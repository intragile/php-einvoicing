#!/usr/bin/env bash
# Builds the KoSIT validator image, starts it as a daemon on the loopback interface and runs the
# kosit-oracle test group with the host PHP against it (vendor/ must be installed: composer
# install). A skipped test fails the run: with the daemon up, every oracle test must execute.
#
#   docker/kosit/run.sh [extra PHPUnit arguments]
#
# KOSIT_PORT (default 8081) is the host port. KOSIT_SKIP_BUILD=1 reuses an existing image.
# KOSIT_BUILD_TIMEOUT (default 900) and KOSIT_RUN_TIMEOUT (default 1800) in seconds bound the
# build and the test run; the daemon gets 60 x 2 seconds to answer. PHP (default: php on the PATH)
# picks the PHP binary. The container is removed on every exit, including INT and TERM (a signal
# sent only to this script takes effect when the command it is waiting for returns).
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
image="php-einvoicing-kosit:1.6.3"
port="${KOSIT_PORT:-8081}"
php="${PHP:-php}"
container="php-einvoicing-kosit-$$"

command -v timeout >/dev/null || { echo "timeout not found; on macOS: brew install coreutils" >&2; exit 1; }

if [ ! -f "$root/vendor/autoload.php" ]; then
    echo "vendor/autoload.php is missing; run composer install first." >&2
    exit 1
fi

if [ "${KOSIT_SKIP_BUILD:-0}" != "1" ]; then
    timeout "${KOSIT_BUILD_TIMEOUT:-900}" docker build --tag "$image" "$root/docker/kosit"
fi

cleanup() {
    timeout 60 docker rm --force "$container" > /dev/null 2>&1 || true
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

timeout 60 docker run --detach --name "$container" --publish "127.0.0.1:${port}:8080" "$image" > /dev/null

url="http://127.0.0.1:${port}/"
attempt=0
# Any HTTP status (the daemon answers 405 to a GET) means it is listening.
until [ "$(curl --silent --output /dev/null --max-time 2 --write-out '%{http_code}' "$url" || true)" != "000" ]; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 60 ]; then
        echo "The KoSIT daemon did not answer on $url within 120 seconds." >&2
        timeout 30 docker logs "$container" >&2 || true
        exit 1
    fi
    sleep 2
done

cd "$root"
KOSIT_ORACLE_URL="$url" timeout "${KOSIT_RUN_TIMEOUT:-1800}" \
    "$php" vendor/bin/phpunit --colors=never --group kosit-oracle --fail-on-skipped "$@"
