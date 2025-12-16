# Deploy na Railway (Railpack)

Este repositório sobe o **painel PHP** usando o servidor embutido do PHP (`php -S`) via `start.sh`.

## Importante (causa do erro "Script start.sh not found")
A Railway/Railpack só encontra `start.sh` se você fizer o deploy a partir da **raiz do projeto** (onde estão `start.sh` e `Procfile`).

Se você estiver usando a Railway CLI, rode os comandos **dentro desta pasta** (não na pasta `~` do Mac).

## O que o start.sh faz
- Entra em `01_backend_painel_php`
- Exporta `ALABAMA_BACKEND_DIR` para manter compatibilidade com módulos externos
- Sobe o app com **paridade de rotas** via `router.php` (aliases `/marketing` e `/ai` e hardening para o built-in server)
- Sobe em `0.0.0.0:$PORT` (Railway define `PORT` automaticamente)

> Obs.: em Apache/Nginx o docroot recomendado continua sendo `01_backend_painel_php/`.

## Uploads
A pasta `01_backend_painel_php/uploads/` deve ser persistida via Volume/Storage no ambiente de produção.
