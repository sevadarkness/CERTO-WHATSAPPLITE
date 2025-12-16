#!/usr/bin/env bash
set -euo pipefail

# Run `php -l` across all PHP files. If Docker is available, use php:8.2-cli.
# Usage: ./scripts/check_php_syntax.sh

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

# find php files
# Em ZIPs/artefatos sem .git, o git pode não existir e imprimir erro. Silencia para evitar ruído.
php_files=$(git ls-files '*.php' 2>/dev/null || find . -type f -name "*.php")
if [ -z "$php_files" ]; then
  echo "No PHP files found."
  exit 0
fi

if command -v docker >/dev/null 2>&1; then
  echo "Docker found — running php -l in php:8.2-cli container"
  docker run --rm -v "$ROOT":/app -w /app php:8.2-cli bash -lc "set -e; for f in $php_files; do php -l \"$f\" || exit 2; done; echo 'PHP syntax OK';"
  exit 0
fi

if command -v php >/dev/null 2>&1; then
  echo "Local php found — running php -l"
  for f in $php_files; do
    php -l "$f" || exit 2
  done
  echo "PHP syntax OK"
  exit 0
fi

echo "Neither Docker nor php found. Install Docker or php to run syntax checks."
exit 3
