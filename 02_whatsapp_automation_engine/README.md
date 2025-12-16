# 02 — WhatsApp Automation Engine

## O que é
Organização dos **motores de execução**: Fluxos (flows), Automação (rules/events), Jobs agendados e Workers de WhatsApp.

## Sobe no servidor?
⚠️ Depende:
- Os scripts aqui podem ser executados por **CLI/cron** no servidor.
- Os arquivos reais ficam em `01_backend_painel_php/`.

## Importante (wrappers)
A maioria dos arquivos nesta pasta são **wrappers** (atalhos) que fazem `require_once` dos arquivos reais do backend em `01_backend_painel_php/`.  
Isso mantém o backend **deployável** e evita quebrar includes.

## Como rodar
### Fluxos / Automação / Jobs
- Para rodar via CLI:
```bash
php 02_whatsapp_automation_engine/jobs/jobs_runner.php
php 02_whatsapp_automation_engine/automation/automation_runner.php
php 02_whatsapp_automation_engine/flows/flows_engine_runner.php
```

### Scheduler
- Arquivo: `jobs/scheduler.php`
- Ele lê um JSON com tasks e executa os comandos.

**Configuração recomendada (padrão do pacote):**
- Copie o exemplo do backend:
  - `01_backend_painel_php/config/scheduler.example.json`
  - para `01_backend_painel_php/config/scheduler.json`
- Ajuste os comandos conforme seu deploy.

**Rodar:**
```bash
php 02_whatsapp_automation_engine/jobs/scheduler.php
```

**Opcional:**
- Usar um config externo:
```bash
php 02_whatsapp_automation_engine/jobs/scheduler.php --config=/caminho/scheduler.json
```
- Forçar baseDir do backend (quando o diretório não estiver no layout padrão):
```bash
php 02_whatsapp_automation_engine/jobs/scheduler.php --baseDir=/caminho/para/01_backend_painel_php
```

- Rodar em modo teste (não executa, apenas imprime):
```bash
php 02_whatsapp_automation_engine/jobs/scheduler.php --dry-run
```

- Override por variável de ambiente:
```bash
SCHEDULER_CONFIG=/caminho/scheduler.json php 02_whatsapp_automation_engine/jobs/scheduler.php
```

### WhatsApp Workers
- Worker de fila/flow:
```bash
php 02_whatsapp_automation_engine/whatsapp/whatsapp_flow_worker.php
```
- Worker bulk (contatos):
```bash
php 02_whatsapp_automation_engine/whatsapp/whatsapp_bulk_worker.php
```

> Nota: `whatsapp_bulk_worker.php` é um wrapper para o worker real do backend (`whatsapp_contacts_bulk_worker.php`).
