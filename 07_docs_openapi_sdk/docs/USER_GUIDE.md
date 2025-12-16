# Guia rápido - Painel Rede Alabama

## Perfis de acesso

- **Administrador**: gerencia usuários, regras de automação, fluxos, IA.
- **Gerente**: visão geral de vendas, equipes, relatórios.
- **Vendedor**: acesso ao painel diário, catálogo, respostas via WhatsApp (com IA).

## Funcionalidades principais

- Login com proteção por CSRF e rate limiting básico.
- Catálogo de produtos e estoque por vendedor.
- Fluxos de WhatsApp versionados (quando tabelas de versionamento estão ativas).
- Motor de automação e execução (quando tabelas de automação estão ativas).
- IA de apoio em respostas via WhatsApp (Vendedor Hoje).

Para detalhes técnicos, consulte `docs/DEPLOY.md` e o código em `01_backend_painel_php/app/`.
