# 01 — Backend + Painel (PHP)

## O que é
Backend + Painel administrativo em PHP (CRM, estoque/produtos, leads, relatórios, WhatsApp oficial, automações e IA).

## Sobe no servidor?
✅ **SIM.** Esta pasta deve ser o **DocumentRoot** do seu Apache/Nginx (ou apontada via alias).

## Como rodar
### Requisitos
- PHP 8.x com extensões comuns (PDO, mbstring, openssl, curl, json)
- MySQL/MariaDB
- (Opcional) Redis
- (Opcional) Prometheus/Grafana (via `06_deploy_infra/`)

### Passos recomendados
1) Crie `.env`:
- copie `.env.example` → `.env` e ajuste.

2) Banco:
- aplique as migrations em `database/migrations/` (via CLI/cliente SQL).

3) Criação de admin
⚠️ O seed com credenciais padrão foi removido do fluxo de produção e movido para:
`99_extras_optional/legacy/dev_seeds/`

Crie o primeiro admin manualmente (exemplo SQL — ajuste os campos conforme sua tabela):
```sql
-- gere o hash com password_hash() via PHP e cole aqui
INSERT INTO usuarios (nome, telefone, senha, nivel_acesso)
VALUES ('Administrador', 'SEU_TELEFONE', 'HASH_DA_SENHA', 'Administrador');
```

4) Workers/cron (opcional)
- Veja `02_whatsapp_automation_engine/README.md` para executar jobs/flows/bulk.

## Observação
Foram incorporados também módulos adicionais vindos do kit (MFA, relatórios, dashboards) em `modules/` e utilitários em `exports/`.
