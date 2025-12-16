#!/usr/bin/env bash
set -euo pipefail

# Quick secret scanner: finds potential OpenAI keys and other likely secrets.
# Usage: ./scripts/scan_secrets.sh

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "Scanning repository for likely secrets (sk- tokens, AWS keys, etc.)..."

matches=$(grep -RIn --line-number \
  --exclude-dir=.git \
  --exclude-dir=node_modules \
  --exclude-dir=vendor \
  --exclude-dir=dist \
  --exclude-dir=coverage \
  --exclude-dir=__MACOSX \
  --exclude=*.min.js \
  --exclude=scan_secrets.sh \
  -E "\bsk-[A-Za-z0-9_\/_-]{20,}\b|AKIA[0-9A-Z]{16}|aws_secret_access_key\s*=|-----BEGIN PRIVATE KEY-----|-----BEGIN RSA PRIVATE KEY-----|-----BEGIN EC PRIVATE KEY-----" . || true)
if [ -n "$matches" ]; then
	echo "POTENTIAL SECRETS FOUND:";
	echo "$matches";
	if [ "${SCAN_FAIL_ON_MATCH:-false}" = "true" ]; then
		echo "Failing because SCAN_FAIL_ON_MATCH=true";
		exit 1;
	fi
else
	echo "No obvious secrets found."
fi

echo "Done. Review matches carefully and rotate any leaked keys."