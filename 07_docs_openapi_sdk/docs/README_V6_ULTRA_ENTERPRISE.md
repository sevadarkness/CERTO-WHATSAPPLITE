# Rede Alabama WHATSAPP PANEL – V6 Ultra Enterprise

Esta variante adiciona:

- Router/MVC básico (`app/Http/*`, `routes/web_v6.php`, `public/index_v6.php`).
- TenantResolver central (`app/Support/TenantResolver.php` + `config/tenants*.json`).
- Camada de banco unificada (`app/Database/DB.php`, `app/Database/QueryBuilder.php`).
- Sistema de plugins (`app/Plugins/PluginManager.php`, `config/plugins.json`, `plugins/ExampleHelloPlugin`).
- Sistema de temas (`app/Themes/ThemeManager.php`, `config/themes.json`, `themes/default`).
- Scheduler (`scheduler.php`, `config/scheduler.example.json`).
- Baseline SQL opcional para bancos novos (`database/migrations/0000_00_00_000000_full_schema_v5_baseline.sql`).

O legado permanece funcionando (index.php, painéis antigos etc.). Use as novas camadas para rotas/funcionalidades novas.
