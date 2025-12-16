# 03 — IA / LLM Platform

## O que é
Organização do **núcleo de IA/LLM**: classes principais, dashboards e consoles.

## Sobe no servidor?
✅ **SIM**, mas os arquivos “fonte” já estão em `01_backend_painel_php/`.
Esta pasta contém principalmente **wrappers** (atalhos) para facilitar navegação e separar responsabilidades.

## Como usar (importante)
Os wrappers desta pasta funcionam de duas formas:

- **Via web (HTTP):**
  - Para telas do painel (dashboards/consoles), o wrapper **redireciona** para o arquivo real em `01_backend_painel_php/`.
  - Isso garante que **RBAC, links e assets relativos** funcionem corretamente.

- **Via CLI:**
  - O wrapper apenas **carrega** (`require_once`) o arquivo real do backend.

## Entradas
- Dashboards:
  - `03_ai_llm_platform/dashboards/*` (redirecionam para as páginas do backend)
- Assistentes:
  - `03_ai_llm_platform/assistants/*`
- Training:
  - `03_ai_llm_platform/training/*`
- Core:
  - `03_ai_llm_platform/core/*` carrega classes dentro de `01_backend_painel_php/app/Services/Llm/`
