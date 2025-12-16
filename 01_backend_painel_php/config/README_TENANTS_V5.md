# Multi-tenant (V5/V6)

Ordem de resolução do tenant_id:

1. `TenantResolver::forceTenantId($id)` – usado em workers/CLI.
2. `$_SESSION['tenant_id']` – definido no login.
3. `config/tenants.json` – mapeamento de host para tenant.
4. Fallback: `tenant_id = 1`.

Para configurar, copie `tenants.example.json` para `tenants.json` e ajuste o mapa.
