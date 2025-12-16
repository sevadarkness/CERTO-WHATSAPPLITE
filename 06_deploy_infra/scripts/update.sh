#!/usr/bin/env bash
set -euo pipefail

echo "=== Rede Alabama – Update ==="

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
DOCKER_DIR="$PROJECT_ROOT/06_deploy_infra/docker"

if command -v docker &>/dev/null && docker compose version &>/dev/null; then
  COMPOSE="docker compose"
elif command -v docker-compose &>/dev/null; then
  COMPOSE="docker-compose"
else
  echo "ERRO: docker compose ou docker-compose não encontrados."
  exit 1
fi

cd "$DOCKER_DIR"

echo "Rebuild da imagem do app..."
$COMPOSE build app

echo "Subindo containers atualizados..."
$COMPOSE up -d

echo "Rodando migrations pendentes..."
$COMPOSE exec -T app php /var/www/html/01_backend_painel_php/migrate.php up

echo "Update concluído."
