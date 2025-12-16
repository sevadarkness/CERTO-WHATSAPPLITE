# PATCH_NOTES

Este arquivo registra correções aplicadas automaticamente (autofix) com:

- **Arquivo(s)**
- **Motivo**
- **Risco**
- **Como testar** (com comandos)

---

## 2025-12-14 — Railway/local readiness + hardening + paridade de rotas

### 01_backend_painel_php (núcleo do deploy)

1) **Entrypoint / docroot incorreto no Railway/local**
- **Arquivo(s):** `start.sh` (raiz)
- **Motivo:** o script preferia `public/` como docroot, mas o painel e as rotas não moram integralmente em `public/`, causando 404/rotas quebradas.
- **Correção:** `start.sh` passou a iniciar `php -S` com `-t .` dentro de `01_backend_painel_php/` e usar o router (`router.php`).
- **Risco:** **baixo** (muda apenas o modo como o servidor embutido é iniciado).
- **Como testar:**
  - `bash start.sh`
  - acessar `http://localhost:8080/login.php`

2) **Router para `php -S` com hardening e aliases**
- **Arquivo(s):** `01_backend_painel_php/router.php` (**novo**)
- **Motivo:** o PHP built-in server não aplica regras de `.htaccess`/Apache; arquivos sensíveis podem ficar expostos e o ambiente local/Railway não tinha aliases `/marketing` e `/ai`.
- **Correção:** router implementa:
  - bloqueio de dotfiles e artefatos (`.env`, dumps, logs, etc.)
  - bloqueio de diretórios internos (`/app`, `/database`, `/vendor`, etc.)
  - bloqueio de execução de PHP em `/uploads`
  - alias `/marketing/*` → `../04_marketing_ai_strategy/*`
  - alias `/ai/*` → `../03_ai_llm_platform/*`
- **Risco:** **médio/baixo** (pode bloquear acesso direto a algum arquivo que *não deveria* ser público).
- **Como testar:**
  - `bash start.sh`
  - `curl -I http://localhost:8080/marketing/marketing_strategy_panel.php`
  - `curl -I http://localhost:8080/ai/`

3) **Healthcheck derrubava o processo quando o DB falhava**
- **Arquivo(s):** `01_backend_painel_php/healthz.php`
- **Motivo:** `healthz.php` incluía `db_config.php`, que fazia `echo + exit` em falha de DB, quebrando readiness/observabilidade.
- **Correção:** healthcheck agora:
  - carrega `.env` (melhor esforço)
  - tenta conectar ao DB **sem** usar `db_config.php` e **degrada** para `status=degraded` quando não há DB ou conexão falha
  - sempre responde JSON (HTTP 200)
- **Risco:** **baixo**.
- **Como testar:**
  - `curl -sS http://localhost:8080/healthz.php | jq .`

4) **Proxy OpenAI com CORS preflight quebrado + endpoint fixo**
- **Arquivo(s):** `01_backend_painel_php/api_openai_proxy.php`
- **Problemas corrigidos:**
  - `OPTIONS` (preflight) retornava 405 antes de responder CORS
  - exigia sessão sempre, impedindo uso por extensão/WhatsApp Web
  - fixava o target para `/v1/chat/completions` (não preservava endpoints modernos como `/v1/responses`)
  - não limitava `target` (risco SSRF) e não limitava tamanho de body
- **Correção:** proxy reescrito com:
  - CORS correto (OPTIONS antes de validações)
  - auth coerente: **sessão** OU **X-Alabama-Proxy-Key** quando `OPENAI_PROXY_SECRET` está setado; se não setado, exige sessão
  - suporte a múltiplos endpoints via `?target=https://api.openai.com/v1/...`
  - validação de `target` (apenas `https://api.openai.com/...`)
  - limite de payload (`OPENAI_PROXY_MAX_BODY_BYTES`, default 1MB)
  - rate limiting (melhor esforço) por IP (`OPENAI_PROXY_RL_MAX_ATTEMPTS` / `OPENAI_PROXY_RL_WINDOW_SECONDS`)
  - fallback quando `curl` não existe (stream context)
  - logs mais claros **sem** vazar payload/segredo
- **Risco:** **médio** (mudou contrato do proxy; agora o cliente deve passar `target=` para endpoints diferentes).
- **Como testar:**
  - Preflight:
    - `OPENAI_PROXY_ALLOWED_ORIGIN=https://web.whatsapp.com OPENAI_PROXY_SECRET=abc OPENAI_API_KEY="SUA_KEY" bash start.sh`
    - `curl -i -X OPTIONS "http://localhost:8080/api_openai_proxy.php?target=https%3A%2F%2Fapi.openai.com%2Fv1%2Fresponses" -H "Origin: https://web.whatsapp.com" -H "Access-Control-Request-Method: POST" -H "Access-Control-Request-Headers: content-type,x-alabama-proxy-key"`
  - Request:
    - `curl -sS "http://localhost:8080/api_openai_proxy.php?target=https%3A%2F%2Fapi.openai.com%2Fv1%2Fresponses" -H "Content-Type: application/json" -H "X-Alabama-Proxy-Key: abc" -d '{"model":"gpt-4o-mini","input":"ping"}' | jq .`

5) **Build blocker (syntax) no migrator**
- **Arquivo(s):** `01_backend_painel_php/migrate.php`
- **Motivo:** docblock continha `/* ... */` dentro do comentário, fechando o bloco prematuramente e quebrando `php -l`.
- **Correção:** ajustado texto do docblock para não conter o terminador.
- **Risco:** **baixo**.
- **Como testar:**
  - `php -l 01_backend_painel_php/migrate.php`

### 02_whatsapp_automation_engine

1) **Path hardcoded para Security.php quebrava em layouts não padrão**
- **Arquivo(s):** `02_whatsapp_automation_engine/jobs/scheduler.php`
- **Motivo:** o scheduler carregava `Security.php` via caminho fixo relativo, ignorando `--baseDir`/env.
- **Correção:** carrega `Security.php` a partir do `backendDir` resolvido e falha com mensagem clara se faltar.
- **Risco:** **baixo**.
- **Como testar:**
  - `php 02_whatsapp_automation_engine/jobs/scheduler.php --dry-run`

### 05chromeextensionwhatsapp (extensão)

1) **Shim do proxy OpenAI não preservava endpoint e não suportava auth opcional**
- **Arquivo(s):** `05chromeextensionwhatsapp/modules/watidy/content/assets/js/openai_proxy_shim.js`
- **Motivo:** o shim redirecionava qualquer chamada OpenAI para um único endpoint do backend, perdendo o path original; não existia mecanismo padronizado de header de auth.
- **Correção:**
  - reescreve URL para `PROXY_BASE?target=<URL_ORIGINAL>`
  - remove `Authorization` do client
  - injeta `X-Alabama-Proxy-Key` se existir `localStorage.ALABAMA_OPENAI_PROXY_KEY`
- **Risco:** **médio** (altera a forma de roteamento; depende do backend atualizado).
- **Como testar:**
  - carregar extensão (`chrome://extensions` → Carregar sem compactação)
  - setar:
    - `localStorage.setItem('ALABAMA_OPENAI_PROXY', 'http://localhost:8080/api_openai_proxy.php')`
    - `localStorage.setItem('ALABAMA_OPENAI_PROXY_KEY', 'abc')` (se configurado)

### scripts (ferramentas)

1) **Scanner de segredos dava falso-positivo e ruído em node_modules**
- **Arquivo(s):** `scripts/scan_secrets.sh`
- **Motivo:** o scanner se auto-detectava (string `PRIVATE_KEY` no próprio script) e varria `node_modules`, gerando ruído.
- **Correção:**
  - exclui `node_modules/`, `vendor/`, `dist/`, `coverage/` e o próprio `scan_secrets.sh`
  - remove pattern genérico `PRIVATE_KEY` e mantém detecção de blocos reais (`-----BEGIN ... PRIVATE KEY-----`)
- **Risco:** **baixo**.
- **Como testar:**
  - `bash scripts/scan_secrets.sh`

### docs/config

1) **Instruções inconsistentes**
- **Arquivo(s):** `README_RUN.md`, `README.md`, `.gitignore`
- **Motivo:** instruções antigas não refletiam o `start.sh`/router e havia referência incorreta da pasta da extensão.
- **Correção:**
  - `README_RUN.md` reescrito (local + Railway + extensão + smoke tests)
  - `README.md` corrigido (`05chromeextensionwhatsapp/`)
  - `.gitignore` passou a ignorar `.env` do backend e artefatos de runtime
- **Risco:** **baixo**.
- **Como testar:** leitura + executar comandos do README_RUN.

2) **Artefatos de SO**
- **Arquivo(s):** removidos `**/.DS_Store`
- **Motivo:** ruído em diffs e ZIPs.
- **Risco:** **nenhum**.

---

## 2025-12-15 — Hardening extra do router (Railway/php -S) + limpeza

### 01_backend_painel_php

1) **Exposição indevida de /config e /tests ao usar `php -S` (Railway/local)**
- **Arquivo(s):** `01_backend_painel_php/router.php`
- **Motivo:** o router bloqueava alguns diretórios internos, mas ainda permitia acesso direto a:
  - `/config/*` (ex.: `themes.json`, `plugins.json`, `scheduler*.json`)
  - `/tests/*` (ex.: `smoke_tests.php`), o que pode vazar detalhes internos em produção.
- **Correção:** o router agora bloqueia `/config` e `/tests` explicitamente (paridade com `.htaccess`).
- **Risco:** **baixo** (afeta apenas acesso direto via HTTP a diretórios que não deveriam ser públicos).
- **Como testar:**
  - `bash start.sh`
  - `curl -I http://localhost:8080/config/themes.json` → **404**
  - `curl -I http://localhost:8080/tests/smoke_tests.php` → **404**

2) **Artefato de backup exposto por extensão não bloqueada (.bak2)**
- **Arquivo(s):** `01_backend_painel_php/rule_engine_simple.php.bak2`, `01_backend_painel_php/.htaccess`, `01_backend_painel_php/router.php`
- **Motivo:** arquivos `.bak2` não eram bloqueados por regex e poderiam ser servidos acidentalmente.
- **Correção:**
  - removido `rule_engine_simple.php.bak2` (redundante)
  - `.htaccess` e `router.php` agora bloqueiam variações de `.bak*` (ex.: `.bak2`, `.bak2025`).
- **Risco:** **nenhum/baixo**.

### scripts

1) **Ruído ao rodar `scripts/check_php_syntax.sh` fora de um repositório git**
- **Arquivo(s):** `scripts/check_php_syntax.sh`
- **Motivo:** `git ls-files` imprimia erro em ZIPs sem `.git`.
- **Correção:** stderr do git foi silenciado (fallback para `find` continua igual).
- **Risco:** **nenhum**.

---

## Plano de testes consolidado (comandos exatos)

```bash
cd redealabama_railway_ready

# 0) Sanidade do repo
bash scripts/scan_secrets.sh
bash scripts/check_php_syntax.sh
jq . 05chromeextensionwhatsapp/manifest.json >/dev/null

# 1) Rodar local
cp 01_backend_painel_php/.env.example 01_backend_painel_php/.env
# edite 01_backend_painel_php/.env e configure DB_* e OPENAI_API_KEY
bash start.sh

# 2) Smoke HTTP (sem DB)
curl -sS http://localhost:8080/healthz.php | jq .
curl -sS -I http://localhost:8080/metrics.php | head -n 20
curl -sS -I http://localhost:8080/marketing/marketing_strategy_panel.php | head -n 20
curl -sS -I http://localhost:8080/ai/ | head -n 20

# 3) Proxy OpenAI (preflight + request)
OPENAI_PROXY_ALLOWED_ORIGIN=https://web.whatsapp.com OPENAI_PROXY_SECRET=abc OPENAI_API_KEY="SUA_KEY" bash start.sh
# em outro terminal:
curl -i -X OPTIONS \
  "http://localhost:8080/api_openai_proxy.php?target=https%3A%2F%2Fapi.openai.com%2Fv1%2Fresponses" \
  -H "Origin: https://web.whatsapp.com" \
  -H "Access-Control-Request-Method: POST" \
  -H "Access-Control-Request-Headers: content-type,x-alabama-proxy-key"

curl -sS \
  "http://localhost:8080/api_openai_proxy.php?target=https%3A%2F%2Fapi.openai.com%2Fv1%2Fresponses" \
  -H "Content-Type: application/json" \
  -H "X-Alabama-Proxy-Key: abc" \
  -d '{"model":"gpt-4o-mini","input":"ping"}' | jq .

# 4) Scheduler (dry-run)
php 02_whatsapp_automation_engine/jobs/scheduler.php --dry-run

# 5) Migrações (com DB real)
php 01_backend_painel_php/migrate.php status
php 01_backend_painel_php/migrate.php up
```
