#!/usr/bin/env bash
set -euo pipefail

echo "[apply-env] Reiniciando containers..."

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
DOCKER_DIR="$PROJECT_ROOT/06_deploy_infra/docker"

if command -v docker &>/dev/null && docker compose version &>/dev/null; then
  COMPOSE="docker compose"
elif command -v docker-compose &>/dev/null; then
  COMPOSE="docker-compose"
else
  echo "ERRO: docker compose não encontrado."
  exit 1
fi

cd "$DOCKER_DIR"

# Normalmente basta reiniciar o app para aplicar .env (mantém DB/redis de pé)
$COMPOSE restart app

echo "[apply-env] feito."
