#!/usr/bin/env bash
# Builds the SaxonC oracle image for one PHP version and runs the oracle test group in it
# against the working tree (vendor/ must be installed: composer install).
# A skipped test fails the run: inside the image the extension must be present.
#
#   docker/oracle/run.sh [8.3|8.4|8.5] [extra PHPUnit arguments]
#
# ORACLE_SKIP_BUILD=1 reuses an existing image. ORACLE_BUILD_TIMEOUT and ORACLE_RUN_TIMEOUT
# (seconds, default 900 and 600) bound the build and the test run.
set -euo pipefail

php_version="${1:-8.5}"
shift $(( $# > 0 ? 1 : 0 ))

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
image="php-einvoicing-oracle:php${php_version}"

if [ ! -f "$root/vendor/autoload.php" ]; then
    echo "vendor/autoload.php is missing; run composer install first." >&2
    exit 1
fi

if [ "${ORACLE_SKIP_BUILD:-0}" != "1" ]; then
    timeout "${ORACLE_BUILD_TIMEOUT:-900}" \
        docker build --build-arg PHP_VERSION="$php_version" --tag "$image" "$root/docker/oracle"
fi

timeout "${ORACLE_RUN_TIMEOUT:-600}" \
    docker run --rm --user "$(id -u):$(id -g)" --volume "$root:/app" --workdir /app "$image" \
    vendor/bin/phpunit --group oracle --fail-on-skipped "$@"
