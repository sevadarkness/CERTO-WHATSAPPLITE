# Deploy - Rede Alabama (layout reorganizado)

Este documento descreve o deploy do **painel PHP** usando a estrutura reorganizada deste ZIP.

> Documentos mais antigos podem citar `adm.redealabama/adm.redealabama/`. No layout atual, o webroot é `01_backend_painel_php/`.

## 1. Pré-requisitos

- Docker + Docker Compose
- (Opcional) PHP 8+ para rodar sem containers
- MySQL/MariaDB (se não usar o container `db`)

## 2. Deploy com Docker (recomendado)

1) Vá para o diretório de Docker:

```bash
cd 06_deploy_infra/docker
```

2) Crie o arquivo `.env` (primeira vez):

```bash
cp .env.example .env
# edite DB_*, OPENAI_API_KEY (ou ALABAMA_OPENAI_API_KEY / LLM_OPENAI_API_KEY), tokens etc
```

3) Suba os containers:

```bash
docker compose up -d --build
```

4) Acesse:

- Painel: `http://localhost:8000`

### Webroot dentro do container

- O Apache aponta para: `/var/www/html/01_backend_painel_php`

Isso é importante para rotas absolutas como `/modules/*`, `/api/*`, `/plugins/*`.

## 3. Migrations

```bash
cd 06_deploy_infra/docker
docker compose exec app php /var/www/html/01_backend_painel_php/migrate.php up
```

## 4. Logs e troubleshooting

```bash
cd 06_deploy_infra/docker
docker compose logs -f app
# ou:
docker compose logs -f db
```

## 5. Rodar sem Docker (dev)

```bash
# recomendado: start.sh usa router.php para manter aliases (/marketing e /ai)
cp 01_backend_painel_php/.env.example 01_backend_painel_php/.env
PORT=8000 bash start.sh

# equivalente (manual):
# cd 01_backend_painel_php
# php -S localhost:8000 -t . router.php
```

## 6. OpenAPI e SDKs

- OpenAPI v1: `07_docs_openapi_sdk/openapi/openapi_v1.json`
- OpenAPI v2: `07_docs_openapi_sdk/openapi/openapi_v2.json`
- Test prompt: `07_docs_openapi_sdk/openapi/openapi_test_prompt.json`

SDKs mínimos (somente test_prompt):
- JS: `07_docs_openapi_sdk/sdk/alabama-sdk-js/index.js`
- PHP: `07_docs_openapi_sdk/sdk/alabama-sdk-php/index.php`
- Python: `07_docs_openapi_sdk/sdk/alabama-sdk-py/index.py`

## 7. Autenticação das APIs (v1/v2)

A maior parte de `/api/v1/*` e `/api/v2/*` exige sessão do painel (cookie **`ALABAMA_SESSID`**).

- Para testes: faça login no painel via navegador e reutilize o cookie em ferramentas como Postman/cURL.
