# README_RUN

Este guia é o **passo a passo único e correto** para rodar o RedeAlabama:

- **Local** (PHP built-in server via `start.sh`)
- **Railway** (deploy com `Procfile` + `start.sh`)
- **Extensão Chrome** (proxy OpenAI via backend, sem expor segredos)

## Pré-requisitos

- **PHP 8.1+** (CLI e server)
- (Opcional) **Composer** (se você quiser instalar dependências em `01_backend_painel_php/vendor/`)
- (Recomendado) **MySQL/MariaDB** para o painel e endpoints que usam DB

## 1) Rodar local (recomendado)

Na raiz do repositório:

```bash
# 1) Criar .env local do backend
cp 01_backend_painel_php/.env.example 01_backend_painel_php/.env

# 2) Edite 01_backend_painel_php/.env e configure DB_* + OPENAI_API_KEY (se for usar IA)

# 3) Subir servidor
bash start.sh
```

Acesse:

- Painel/login: `http://localhost:8080/login.php`
- Healthcheck: `http://localhost:8080/healthz.php`
- Métricas: `http://localhost:8080/metrics.php`

### Importante sobre rotas (/marketing e /ai)

O `start.sh` usa `01_backend_painel_php/router.php` para garantir paridade com o Apache (Docker), expondo também:

- `http://localhost:8080/marketing/marketing_strategy_panel.php`
- `http://localhost:8080/ai/` (wrappers/dashboards)

## 2) Smoke tests (sem DB)

Em outro terminal (com o servidor rodando):

```bash
# Healthcheck não deve crashar (mesmo sem DB) e retorna JSON
curl -sS http://localhost:8080/healthz.php | jq .

# Métricas Prometheus (texto)
curl -sS -I http://localhost:8080/metrics.php | head -n 20

# Alias marketing (provavelmente redireciona para login se não autenticado)
curl -sS -I http://localhost:8080/marketing/marketing_strategy_panel.php | head -n 20

# Alias AI (wrapper /ai)
curl -sS -I http://localhost:8080/ai/ | head -n 20
```

## 3) Proxy OpenAI (CORS + auth por secret)

O proxy fica em:

- `http://localhost:8080/api_openai_proxy.php`

Ele aceita **sessão** (se você estiver logado no painel) e/ou **header** `X-Alabama-Proxy-Key` quando `OPENAI_PROXY_SECRET` estiver configurado.

### Teste local (preflight + request)

No terminal do servidor, rode com secret:

```bash
OPENAI_PROXY_SECRET=abc OPENAI_API_KEY="SUA_OPENAI_API_KEY" bash start.sh
```

Em outro terminal:

```bash
# Preflight
curl -i -X OPTIONS \
  "http://localhost:8080/api_openai_proxy.php?target=https%3A%2F%2Fapi.openai.com%2Fv1%2Fresponses" \
  -H "Origin: https://web.whatsapp.com" \
  -H "Access-Control-Request-Method: POST" \
  -H "Access-Control-Request-Headers: content-type,x-alabama-proxy-key"

# Chamada de exemplo (responses)
curl -sS \
  "http://localhost:8080/api_openai_proxy.php?target=https%3A%2F%2Fapi.openai.com%2Fv1%2Fresponses" \
  -H "Content-Type: application/json" \
  -H "X-Alabama-Proxy-Key: abc" \
  -d '{"model":"gpt-4o-mini","input":"ping"}' | jq .
```

> Dica: para o preflight funcionar no navegador/WhatsApp Web, configure também `OPENAI_PROXY_ALLOWED_ORIGIN=https://web.whatsapp.com` no `.env`/Railway.

## 4) Scheduler / automações (CLI)

O scheduler roda via CLI:

```bash
php 02_whatsapp_automation_engine/jobs/scheduler.php --dry-run
```

Para produção, você precisa de um **cron externo** (Railway não agenda cron automaticamente no web service). Exemplo:

- Crie um segundo serviço/job que execute periodicamente `php 02_whatsapp_automation_engine/jobs/scheduler.php`

Observação de segurança:

- `Security::safe_exec()` exige `ALLOW_UNSAFE_SHELL_EXEC=true` para executar comandos externos.

## 5) Migrações (com DB real)

Com `DB_*` configurado em `01_backend_painel_php/.env` e o DB acessível:

```bash
php 01_backend_painel_php/migrate.php status
php 01_backend_painel_php/migrate.php up
```

## 6) Extensão Chrome (WhatsApp Web)

Carregar extensão:

1. Chrome → `chrome://extensions`
2. Ative **Modo do desenvolvedor**
3. **Carregar sem compactação** → selecione a pasta `05chromeextensionwhatsapp/`

Configurar proxy (no console do WhatsApp Web):

```js
localStorage.setItem("ALABAMA_OPENAI_PROXY", "https://SEU_DOMINIO/api_openai_proxy.php");
localStorage.setItem("ALABAMA_OPENAI_PROXY_KEY", "abc"); // se OPENAI_PROXY_SECRET=abc no backend
```

O shim reescreve chamadas `https://api.openai.com/...` para:

- `https://SEU_DOMINIO/api_openai_proxy.php?target=https%3A%2F%2Fapi.openai.com%2Fv1%2F...`

## 7) Verificações rápidas (repo)

```bash
# Scanner de segredos (melhor esforço)
bash scripts/scan_secrets.sh

# Sintaxe PHP (rápido)
bash scripts/check_php_syntax.sh

# Validar manifest.json da extensão
jq . 05chromeextensionwhatsapp/manifest.json >/dev/null
```
