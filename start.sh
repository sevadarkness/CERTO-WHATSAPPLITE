#!/usr/bin/env bash
set -euo pipefail

echo "Starting Rede Alabama Platform (Railway/Railpack compatible)"

# Alguns ZIPs incluem uma pasta extra de topo (ex.: RedeAlabama_Platform0/).
# Mantém compatibilidade com ambos os layouts.
if [ -d "RedeAlabama_Platform0/01_backend_painel_php" ]; then
  cd "RedeAlabama_Platform0"
fi

BACKEND_DIR="01_backend_painel_php"
if [ ! -d "$BACKEND_DIR" ]; then
  echo "[start.sh] ERROR: backend directory not found: $BACKEND_DIR" >&2
  exit 1
fi

PORT_TO_USE="${PORT:-8080}"
HOST_TO_USE="${HOST:-0.0.0.0}"

cd "$BACKEND_DIR"

# Exporte o diretório do backend para módulos externos (marketing/ai) encontrarem o núcleo.
export ALABAMA_BACKEND_DIR="$(pwd)"

if [ ! -f "router.php" ]; then
  echo "[start.sh] ERROR: router.php missing in $BACKEND_DIR (needed for php -S + alias/hardening)" >&2
  exit 1
fi

echo "Serving PHP app from $(pwd) on ${HOST_TO_USE}:${PORT_TO_USE} (router: router.php)" >&2
exec php -d variables_order=EGPCS -S "${HOST_TO_USE}:${PORT_TO_USE}" -t . router.php
