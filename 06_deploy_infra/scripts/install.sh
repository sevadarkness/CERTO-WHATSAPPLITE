#!/usr/bin/env bash
set -euo pipefail

echo "=== Rede Alabama – Instalador ==="

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
DOCKER_DIR="$PROJECT_ROOT/06_deploy_infra/docker"

# Detecta docker compose
if command -v docker &>/dev/null && docker compose version &>/dev/null; then
  COMPOSE="docker compose"
elif command -v docker-compose &>/dev/null; then
  COMPOSE="docker-compose"
else
  echo "ERRO: docker compose ou docker-compose não encontrados."
  echo "Instale Docker + Docker Compose antes de continuar."
  exit 1
fi


cd "$DOCKER_DIR"

# Cria .env a partir de .env.example se não existir
if [ ! -f .env ]; then
  if [ -f .env.example ]; then
    cp .env.example .env
    echo "Arquivo .env criado a partir de 06_deploy_infra/docker/.env.example."
  else
    echo "ERRO: .env.example não encontrado em $DOCKER_DIR"
    exit 1
  fi
  echo "ATENÇÃO: edite 06_deploy_infra/docker/.env e ajuste DB_*, OPENAI_API_KEY (ou ALABAMA_OPENAI_API_KEY / LLM_OPENAI_API_KEY) e tokens antes de produção."
fi

echo "Subindo containers (app, db, redis, prometheus, grafana)..."
$COMPOSE up -d --build

echo "Aguardando o banco ficar pronto..."
DB_ROOT_PASSWORD=$(grep -E '^DB_ROOT_PASSWORD=' .env 2>/dev/null | head -n1 | cut -d= -f2- || true)
DB_ROOT_PASSWORD=${DB_ROOT_PASSWORD:-root}
DB_ROOT_PASSWORD=${DB_ROOT_PASSWORD%\"}
DB_ROOT_PASSWORD=${DB_ROOT_PASSWORD#\"}

for i in $(seq 1 40); do
  if $COMPOSE exec -T db mysqladmin ping -h127.0.0.1 -uroot -p"$DB_ROOT_PASSWORD" --silent >/dev/null 2>&1; then
    echo "[OK] Banco pronto."
    break
  fi
  if [ "$i" -eq 40 ]; then
    echo "ERRO: banco não ficou pronto a tempo. Verifique logs: $COMPOSE logs db"
    exit 1
  fi
  sleep 2
done

echo "Rodando migrations dentro do container app..."
$COMPOSE exec -T app php /var/www/html/01_backend_painel_php/migrate.php up

echo
echo "Instalação concluída."
echo "Acesse o painel em: http://localhost:8000"
echo "Marketing AI (opcional): http://localhost:8000/marketing/marketing_strategy_panel.php"
echo "Usuário inicial: cadastre manualmente na tabela 'usuarios' ou use o fluxo de criação no painel."
