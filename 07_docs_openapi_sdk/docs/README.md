# Rede Alabama Platform — Docs + OpenAPI + SDKs (Layout reorganizado)

Este diretório reúne documentação operacional, especificações OpenAPI e SDKs mínimos para integração.

> **Nota sobre este ZIP reorganizado:** a base do painel PHP está em `01_backend_painel_php/`.
> Documentos mais antigos podem citar o caminho legado `adm.redealabama/adm.redealabama/`.

## ✅ Deploy rápido (Docker)

Recomendado usar os scripts em `06_deploy_infra/`:

```bash
chmod +x 06_deploy_infra/scripts/install.sh
bash 06_deploy_infra/scripts/install.sh
```

Depois acesse:
- Painel / Backend: `http://localhost:8000`
- Marketing AI (opcional): `http://localhost:8000/marketing/marketing_strategy_panel.php`
- AI / LLM Platform (opcional): `http://localhost:8000/ai/`
- Grafana: `http://localhost:3000`
- Prometheus: `http://localhost:9090`

## ✅ Rodar sem Docker (PHP embutido)

```bash
# recomendado: usa start.sh (router + aliases /marketing e /ai)
cp 01_backend_painel_php/.env.example 01_backend_painel_php/.env
PORT=8000 bash start.sh

# equivalente (manual):
# cd 01_backend_painel_php
# php -S localhost:8000 -t . router.php
```

## 🧭 Estrutura principal (neste ZIP)

- `01_backend_painel_php/` → **painel administrativo + API** (`/api/*`, `/modules/*`, `/plugins/*`, `/exports/*`).
- `02_whatsapp_automation_engine/` → motor de automação / jobs (execução e agendamentos).
- `03_ai_llm_platform/` → módulos auxiliares de IA/LLM.
- `04_marketing_ai_strategy/` → painel de estratégia de marketing.
- `05chromeextensionwhatsapp/` → extensão do Chrome (carregar via “Load unpacked”).
- `06_deploy_infra/` → docker-compose, scripts de instalação, observabilidade.
- `07_docs_openapi_sdk/` → **esta pasta** (docs + OpenAPI + SDKs).
- `99_extras_optional/` → extras opcionais (ex.: `pwa/`).

## 🧪 Teste de prompt (API + CLI + SDK)

- Endpoint HTTP: `POST /api/test_prompt.php`
- OpenAPI: `07_docs_openapi_sdk/openapi/openapi_test_prompt.json`

### Formato de resposta (ApiResponse)

A API do painel padroniza respostas assim:

```json
{
  "ok": true,
  "data": {
    "answer": "...",
    "model": "..."
  },
  "error": null,
  "meta": {}
}
```

### CLI

Script:

```bash
bash 06_deploy_infra/scripts/alabama_prompt_cli.sh "Resuma as vendas de hoje."
```

### SDKs mínimos

- JS: `07_docs_openapi_sdk/sdk/alabama-sdk-js/index.js` → função `createClient()`
- PHP: `07_docs_openapi_sdk/sdk/alabama-sdk-php/index.php` → classe `AlabamaSdkPhp`
- Python: `07_docs_openapi_sdk/sdk/alabama-sdk-py/index.py` → classe `AlabamaClient`

## 🔐 Autenticação (APIs v1/v2)

- A maior parte de `/api/v1/*` e `/api/v2/*` exige usuário autenticado no painel.
- A sessão do painel usa cookie **`ALABAMA_SESSID`**.
- As specs OpenAPI (`openapi_v1.json` e `openapi_v2.json`) já declaram esse esquema de autenticação.

> Na prática: para testar via Postman/cURL, você pode fazer login no painel via browser e reutilizar o cookie de sessão.

## 📦 OpenAPI

- `07_docs_openapi_sdk/openapi/openapi_v1.json` — Fluxos + Regras de automação (v1)
- `07_docs_openapi_sdk/openapi/openapi_v2.json` — Leads, Entregadores, Matching, Remarketing + Vendas IA (v2)
- `07_docs_openapi_sdk/openapi/openapi_test_prompt.json` — Endpoint de teste do LLM

## 📚 Outras referências

- `07_docs_openapi_sdk/docs/DEPLOY.md` — guia de deploy (atualizado para o layout reorganizado)
- `07_docs_openapi_sdk/docs/SECURITY_FULL.md` — segurança
- `07_docs_openapi_sdk/docs/RUNBOOK_FULL.md` — runbook
- `07_docs_openapi_sdk/docs/USER_GUIDE.md` — guia do usuário

