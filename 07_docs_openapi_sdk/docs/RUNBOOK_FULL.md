# 📘 RUNBOOK — Rede Alabama Enterprise (V33)

Este documento descreve os procedimentos padrão de operação, diagnóstico, mitigação e continuidade
do sistema multitenant.

---

# 🔍 1. Monitoramento

## Grafana
URL: https://grafana.redealabama.com

Dashboards:
- Tenant Overview
- Worker Queue Metrics
- PHP-FPM Usage
- Database/Schema Heatmap

## Prometheus
Todas as métricas expostas em `/metrics` via exporter nativo.

## Sentry
URL: https://sentry.redealabama.com  
Captura:
- Exceptions
- Tenant context
- Trace ID
- Payload reduzido de request

---

# 🚨 2. Diagnóstico Rápido

Ver pods:
```
kubectl get pods -n redealabama
```

Logs da aplicação (tenant-aware):
```
kubectl logs deploy/redealabama-api | grep tenant=acme
```

Status do worker:
```
kubectl logs deploy/redealabama-worker
```

---

# 🛑 3. Incidentes

## Queda de API
1. Verificar readiness:
```
kubectl get deploy redealabama-api
```
2. Sentry para stacktrace
3. Rollback automático via CI/CD

## Fila travada
```
kubectl delete pod -l app=redealabama-worker
```

## Tenant corrompido
```
php scripts/migrate_tenant.php --tenant={tenant}
php scripts/seed_tenant.php --tenant={tenant}
```

---

# 🔄 4. Rollback

Via Helm:
```
helm rollback redealabama 1
```

Via Terraform:
```
terraform apply -target=null_resource.rollback
```

---

# 📦 5. Rotinas críticas

- Replicação de logs por tenant
- Rotação de logs
- Backup do schema por tenant
- Monitoramento de latência do EventBus
