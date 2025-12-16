# RedeAlabama_Platform

Este pacote é a plataforma **Rede Alabama**, reorganizada para ficar **claramente separada por responsabilidade** (backend/painel, motores de automação WhatsApp, IA/LLM, IA de marketing, extensão Chrome, deploy/infra, docs/SDKs e extras).

## Pastas principais

- `01_backend_painel_php/` — **Backend + Painel (PHP)**. **É o que sobe no servidor** (Apache/Nginx + PHP + DB).
- `02_whatsapp_automation_engine/` — Entrypoints e organização dos **workers / engine de fluxos / automações / jobs**.  
  ⚠️ Contém *wrappers* que carregam os arquivos reais de `01_backend_painel_php/` para evitar duplicação e manter o backend intacto.
- `03_ai_llm_platform/` — Entrypoints e organização do **núcleo de IA/LLM** (classes e dashboards).  
  ⚠️ Também usa *wrappers* apontando para `01_backend_painel_php/`.
- `04_marketing_ai_strategy/` — **IA de Marketing** (painel + endpoint).
- `05chromeextensionwhatsapp/` — **Extensão do Google Chrome** (não sobe no servidor).
- `06_deploy_infra/` — **Docker / scripts / observabilidade** (Prometheus/Grafana/OTel).
- `07_docs_openapi_sdk/` — **Documentação, OpenAPI e SDKs**.
- `99_extras_optional/` — **Recursos opcionais** (PWA, push notifications, seeds DEV, legados).

## Quickstart (local com Docker)

1) Configure variáveis do backend:
- copie `01_backend_painel_php/.env.example` para `01_backend_painel_php/.env` e ajuste DB/Redis/keys.

2) Suba a infra:
- `cd 06_deploy_infra/docker`
- `docker compose up -d`

3) Aponte o webserver para `01_backend_painel_php/` como DocumentRoot.

## Extensão Chrome

- `05chromeextensionwhatsapp/` → carregue via `chrome://extensions` → **Modo do desenvolvedor** → **Carregar sem compactação**.
