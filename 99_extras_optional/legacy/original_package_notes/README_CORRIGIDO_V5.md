# Pasta Sem Título — Pacote corrigido (V5)

Este pacote foi reempacotado e validado para evitar erros comuns de deploy e de execução.

## O que foi feito

### Correções aplicadas
- **Link quebrado** em `acesso_restrito.php`: o botão "Voltar ao Painel" apontava para `painel_adm.php` (arquivo inexistente).
  - Agora aponta para `painel_admin.php` (Administrador) e `painel_vendedor_hoje.php` (Vendedor).

### Higienização do ZIP
- Removidos artefatos do macOS: `__MACOSX/` e `.DS_Store`.
- Removidas duplicações de pastas causadas por compactação aninhada.
- Organizado em 3 módulos no topo:
  - `Pasta Sem Titulo CORRIGIDA/` (painel principal)
  - `Pasta Sem Titulo CORRIGIDA 2/` (adm_redealabama 2)
  - `Pasta Sem Titulo CORRIGIDA 3/` (parte_3 / plugins)

## Como usar (extração)
Extraia as 3 partes do ZIP **na mesma pasta de destino** e permita "mesclar/substituir" se o sistema perguntar.

## Observações de configuração

### Painel principal (Pasta Sem Titulo CORRIGIDA)
- Conexão com banco lê preferencialmente variáveis de ambiente `.env`.
- Também aceita fallback via `config.json`.

Variáveis usuais:
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`

### adm_redealabama 2
- Vários módulos dependem de `.env` (ex.: DB e WhatsApp). Veja `.env.example`.

