# 06 — Deploy / Infra

## O que é
Kit de deploy (Docker) + observabilidade (Prometheus/Grafana/OTel) **alinhado com a estrutura atual do projeto**.

> **Importante:** o backend PHP (`01_backend_painel_php`) usa rotas absolutas como `/modules/...`.
> Por isso, no container o **DocumentRoot aponta para `01_backend_painel_php`**.

## Sobe no servidor?
✅ **SIM**. Essa pasta é o “kit de deploy”.

---

## Quickstart (Docker)

### 1) Configurar `.env` do Docker

Entre em:

```bash
cd 06_deploy_infra/docker
```

Crie o arquivo `.env`:

```bash
cp .env.example .env
```

Edite `.env` e ajuste pelo menos:
- `OPENAI_API_KEY` (ou `ALABAMA_OPENAI_API_KEY` / `LLM_OPENAI_API_KEY`)
- `DB_*` (se quiser mudar usuário/senha)

📌 **Obs:** este `.env` é montado no container em:
`/var/www/html/01_backend_painel_php/.env` (onde o backend espera).

### 2) Subir os containers

```bash
docker compose up -d --build
```

### 3) Rodar migrations

```bash
docker compose exec -T app php /var/www/html/01_backend_painel_php/migrate.php up
```

---

## Usando os scripts (recomendado)

Você pode rodar os scripts de qualquer lugar, eles se auto-localizam:

```bash
bash 06_deploy_infra/scripts/install.sh
```

E para atualizar:

```bash
bash 06_deploy_infra/scripts/update.sh
```

Para aplicar mudanças de `.env` (reinicia só o app):

```bash
bash 06_deploy_infra/scripts/apply-env.sh
```

---

## URLs (padrão)

- Painel / Backend: `http://localhost:8000`
- Marketing AI (alias): `http://localhost:8000/marketing/marketing_strategy_panel.php`
- Grafana: `http://localhost:3000`
- Prometheus: `http://localhost:9090`

---

## Observabilidade

- **Prometheus** está configurado para coletar:
  - `app:80/metrics.php` (do backend)
  - `otel-collector:9464` (exporter Prometheus do OTel Collector)

- **Grafana** já sobe com datasource provisionada apontando para o Prometheus.

---

## CI (GitHub Actions)

O workflow de lint PHP está em `06_deploy_infra/ci/deploy.yml`.
Se você quiser usar no GitHub Actions, copie para:

```
.github/workflows/deploy.yml
```

---

## Nota sobre arquivos do macOS
Arquivos como `.DS_Store` e a pasta `__MACOSX` (se existir) podem ser removidos sem impactar o sistema.