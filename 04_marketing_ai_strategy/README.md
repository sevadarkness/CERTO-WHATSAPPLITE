# 04 — Marketing AI Strategy

## O que é
Módulo de **IA de Marketing**:
- `marketing_strategy_panel.php` (interface)
- `marketing_ai_strategy.php` (endpoint JSON que chama LLM)

## Sobe no servidor?
✅ **SIM.**
Você pode:
- servir estes arquivos junto do backend (recomendado), ou
- colocar em um subdomínio/pasta separada.

## Como rodar
- Configure a variável/segredo de API do provedor LLM no ambiente (veja o código do endpoint).
  - Por padrão o endpoint usa `OPENAI_API_KEY`.
- **Importante (deploy):** este módulo reutiliza o sistema de sessão/RBAC/CSRF do backend.
  - No layout deste ZIP, o backend fica em `../01_backend_painel_php`.
  - Se o seu deploy for diferente, defina:
    - `ALABAMA_BACKEND_DIR=/caminho/absoluto/para/01_backend_painel_php`
  - Se o backend estiver servido em outra URL (ex.: `/adm`), defina:
    - `ALABAMA_BASE_PATH=/adm`
- Acesse `marketing_strategy_panel.php` no navegador.

## Arquivos
- `marketing_strategy_panel.php`: UI (HTML + JS) para gerar a estratégia.
- `marketing_ai_strategy.php`: endpoint JSON que chama o provider LLM.
- `marketing_bootstrap.php`: localiza o backend e carrega sessão/RBAC/CSRF/logs.
