#!/usr/bin/env bash
set -euo pipefail

API_URL="${API_URL:-http://localhost:8000/api/test_prompt.php}"

if [ "$#" -eq 0 ]; then
  echo "Uso: $0 \"seu prompt aqui\""
  echo "Exemplo:"
  echo "  $0 \"Resuma as vendas de hoje para o gerente\""
  echo
  echo "Dica: sobrescreva o endpoint com API_URL=..."
  echo "  API_URL=http://localhost:8000/api/test_prompt.php $0 \"...\""
  exit 1
fi

PROMPT="$*"

# Escapa aspas para JSON simples
ESCAPED_PROMPT=$(printf '%s' "$PROMPT" | sed 's/"/\"/g')

JSON_PAYLOAD=$(printf '{"prompt":"%s","temperature":0.2,"max_tokens":256}' "$ESCAPED_PROMPT")

curl -sS -X POST \
  -H "Content-Type: application/json" \
  -d "$JSON_PAYLOAD" \
  "$API_URL"
