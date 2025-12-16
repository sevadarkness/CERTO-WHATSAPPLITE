#!/usr/bin/env bash
set -euo pipefail

# Run backend PHPUnit in a disposable php:8.2-cli container (uses composer inside container)
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT/01_backend_painel_php"

if ! command -v docker >/dev/null 2>&1; then
  echo "Docker not found. Install Docker or run tests locally with PHP + Composer." >&2
  exit 2
fi

echo "Running composer install and phpunit in php:8.2-cli container..."
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" php:8.2-cli bash -lc '
  set -e
  apt-get update -y >/dev/null 2>&1 || true
  apt-get install -y --no-install-recommends unzip git curl >/dev/null 2>&1 || true
  if [ ! -f composer.phar ]; then
    curl -sS https://getcomposer.org/installer | php >/dev/null 2>&1
  fi
  php composer.phar install --no-progress --no-suggest --prefer-dist
  ./vendor/bin/phpunit --configuration phpunit.xml.dist --colors=always
'