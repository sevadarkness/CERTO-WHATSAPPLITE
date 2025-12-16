# ⚠️ Nota (layout reorganizado)

Este documento existia antes da reorganização do ZIP e pode conter referências históricas.

**Para subir o projeto neste layout atual**, use:

```bash
chmod +x 06_deploy_infra/scripts/install.sh
bash 06_deploy_infra/scripts/install.sh
```

Migrations (manual, se necessário):

```bash
cd 06_deploy_infra/docker
docker compose exec app php /var/www/html/01_backend_painel_php/migrate.php up
```

---

# Rede Alabama Installer + Env Apply V105

> Pacote derivado da base REST frontdoor strict, com endurecimento do endpoint `/api/apply_env.php`,
> padronização de respostas JSON via `ApiResponse` e alinhamento completo com `.env.example` e painel de governança.


## 🔧 Instalação rápida em localhost (Mac + Docker)

1. Certifique-se de que o Docker Desktop está aberto.
2. No Terminal, rode:

```bash
cd /caminho/para/SEVADARKNESS_REDE_ALABAMA_V106
cp .env.example .env    # só na primeira vez
chmod +x 06_deploy_infra/scripts/install.sh
bash 06_deploy_infra/scripts/install.sh
```

3. Acesse: `http://localhost:8000`
4. Se precisar rodar migrations manualmente:

```bash
docker compose exec app php 01_backend_painel_php/migrate.php up
```

---
## ⚙️ Instalador CLI (php cli/installer.php)

Além do `bash 06_deploy_infra/scripts/install.sh`, você pode rodar um instalador CLI que:

- Carrega o `.env` e a config central
- Testa a conexão com o banco de dados
- Aplica as migrations pendentes
- Executa o seed de usuário administrador padrão (idempotente)

Uso sugerido (com Docker):

```bash
cd /caminho/para/SEVADARKNESS_REDE_ALABAMA_V106
docker compose exec app php /var/www/html/06_deploy_infra/scripts/cli/installer.php
```



## Rede Alabama LLM SaaSKit V46 (Optimized – histórico)


Este pacote foi enxugado para conter apenas o que é funcional e utilizável em produção ou em laboratório controlado.

## 🧭 Estrutura

- `01_backend_painel_php/` → painel PHP completo (bot WhatsApp + IA, vendas, remarketing, automações).
- `01_backend_painel_php/modules/` → módulos opcionais plugáveis (relatório diário, cartão fidelidade, dashboard próprio, auth módulo).
- `01_backend_painel_php/plugins/oauth_saml/` → plugin de autenticação OAuth2/SAML pronto para parametrizar via variáveis de ambiente.
- `sdk/` → SDKs mínimos (JS, PHP, Python) para consumir o endpoint de teste de prompt.
- `01_backend_painel_php/exports/relatorios_ia_export.php` → exportação de uso de IA em CSV/JSON.
- `docs/` → arquitetura, deploy, segurança e runbook.
- `06_deploy_infra/ci/deploy.yml` → pipeline de validação de sintaxe PHP.
- `01_backend_painel_php/themes/dark_mode_toggle.js` → helper de dark mode (painel).
- `99_extras_optional/pwa/` → base PWA (opcional): `manifest.json`, `icons/`, `splash.html`, `service-worker.js`.

## 🚀 Como rodar

### Via Docker

```bash
docker-compose up
# acesso: http://localhost:8000
```

### Via PHP embutido

```bash
cp 01_backend_painel_php/.env.example 01_backend_painel_php/.env
PORT=8000 bash start.sh

# manual:
# cd 01_backend_painel_php
# php -S localhost:8000 -t . router.php
```

## 🧪 Teste rápido do LLM

- Endpoint: `POST http://localhost:8000/api/test_prompt.php`
- Esquema: `07_docs_openapi_sdk/openapi/openapi_test_prompt.json`
- CLI:
  - `bash 06_deploy_infra/scripts/alabama_prompt_cli.sh "Explique o desempenho de vendas de ontem."`

## 🔐 Integrações

- OAuth2/SAML: `01_backend_painel_php/plugins/oauth_saml/auth.php`
- Notificações push: `01_backend_painel_php/modules/api/send_push.php` + `99_extras_optional/notifications/send_push_fcm.php` + `99_extras_optional/notifications/tokens_vendedores.php`

## 🆙 O que mudou na V101 (Enterprise)

- Integração real da camada de IA do painel de sugestões WhatsApp com `LlmService`,
  usando provider configurável (`stub` ou `openai`) e suporte a `OPENAI_API_KEY` / `ALABAMA_OPENAI_API_KEY`.
- Mantida compatibilidade com a factory `LlmService::fromEnv()` utilizada pelo painel.
- Normalização da geração de respostas IA para o painel, com logging estruturado e uso de repositório dedicado.
- Código mantido 100% compatível com os testes já existentes (`WhatsappAiSuggestionServiceTest`).

## 📦 Objetivo desta versão

- Remover pastas/arquivos puramente conceituais.
- Manter apenas código executável, documentação de operação ou assets usados pelo painel.
- Deixar a base pronta para ser estendida sem precisar limpar "protótipos" antes.


## 🆙 O que mudou na V102 (Vendas IA)

- Adição do épico de Vendas IA com três módulos focados em aumento de receita:
  - IA Vendedora PRO (geração de combos/ofertas inteligentes).
  - Campanhas Automáticas de Recuperação de Vendas.
  - Vendedor Copiloto (IA focada em objeções).
- Inclusão das tabelas e services de domínio em `app/Services/Sales` e migration SQL única.
- Esqueletos de endpoints REST em `/api/v2/` para integração com o router atual.


## 🆙 O que mudou na V103 (Fábrica de Prompts IA Vendas)

- Criação da classe `SalesPromptFactory` em `app/Services/Sales/` para centralizar
  os prompts de IA dos módulos de vendas:
  - IA Vendedora PRO (ofertas inteligentes).
  - Campanhas Automáticas de Recuperação.
  - Vendedor Copiloto (objeções).
- Cada método da fábrica retorna `system_prompt` e `user_prompt`, prontos para uso
  com o `LlmService::generateChatCompletion()`.


## 🆙 O que mudou na V104 (Backend IA Vendas pronto para produção)

- Implementação funcional dos services de vendas IA em `app/Services/Sales/`:
  - `SalesSmartOfferService`: carrega lead, templates de oferta, histórico de WhatsApp,
    chama LLM com `SalesPromptFactory` e grava log em `sales_ai_offers_log`.
  - `SalesRecoveryCampaignService`: CRUD de campanhas, geração de segmentos básicos
    por inatividade e por clientes que perguntaram preço/valor.
  - `SalesRecoveryRunnerService`: processa fila de `sales_recovery_enrollments`,
    gera mensagens via IA e registra em `whatsapp_messages` como saída IA.
  - `SalesObjectionAssistantService`: catálogo de objeções, geração de resposta IA
    contextualizada pela conversa e log em `sales_objection_ai_log`.
- Criação de endpoints REST dedicados em `/api/v2/`:
  - `/api/v2/sales_ia_offers.php`
  - `/api/v2/sales_recovery_campaigns.php`
  - `/api/v2/sales_recovery_runner.php`
  - `/api/v2/sales_objections.php`


## 🆙 O que mudou na V105 (Console IA via painel)

- Mantida toda a estrutura de backend da V104 (services, endpoints e migrations).
- Ajuste dos endpoints para usar `tenant_id` do usuário logado.
- Criação da página `vendedor_ia_console.php` no painel:
  - Aba "IA Vendedora PRO": permite selecionar um lead e thread_id para gerar ofertas IA
    diretamente do painel e visualizar o JSON de proposta + mensagem sugerida.
  - Aba "Copiloto - Objeções": permite selecionar lead, thread e código de objeção para
    gerar a resposta IA e exibi-la pronta para copiar/usar.
  - Aba "Campanhas de Recuperação": mostra campanhas existentes e permite disparar
    manualmente o runner de recuperação (processa `sales_recovery_enrollments`).
- Com isso, todos os módulos de IA de vendas podem ser usados via painel web, sem necessidade
  de ferramentas externas (Postman/cURL) para operação básica.
